<?php

if (!defined('ABSPATH')) {
    exit;
}

class PrismShippingChargesWebhook extends PrismappIoWebhook implements iPrismappIoWebhook {

    private $request_data;
    private $response_data;
    private $province;
    private $city;
    private $district;
    private $provider_type;
    private $postcode;

    /**
     * Bootstrapper for the controller
     * ---
     */
    public function run($request_data) {
        $this->request_data = $request_data;
        if ($request_data instanceof WP_REST_Request) {
            $this->request_data = $request_data->get_body();
        }
        $this->validate_request();

        $this->process();
        $this->validate_response();

        PrismappIoWebhook::response_with_json(json_encode($this->response_data), 200);
    }

    /**
     * Do Webhook Logic
     * ---
     */
    public function process() {
        global $woocommerce;

        if ( version_compare( $woocommerce->version, '2.2', "<" ) ) {
            PrismappIoWebhook::response_woo_need_upgrade();
        }
        
        $this->set_destination();
        $this->add_to_cart();
        $result = $this->shipment_choices();
        if(count($result) == 0){
            PrismappIoWebhook::response_with_not_found('There are no shipping methods available');
        }

        $this->response_data = array(
            'status'    =>  'success',
            'data'      =>  array(
                'shipment_choices' => $result
            )
        );
    }

    /**
     * Check shipping charge
     * ---
     */
    public function shipment_choices(){
        
        if($this->provider_type == 'epeken' && PrismEpeken::is_active()){
            $shipment_choices = PrismEpeken::calculate($this->city, $this->district);
            return $this->shipment_payload($shipment_choices);
        }

        if($this->provider_type == 'agenwebsite' && PrismAgenwebsite::is_active()){
            $shipment_choices = PrismAgenwebsite::calculate($this->city, $this->district, $this->postcode);
            return $this->shipment_payload($shipment_choices);
        }

        if($this->provider_type == 'custom' && PrismTonjoo::is_active()){
            $shipment_choices = PrismTonjoo::calculate($this->province, $this->city, $this->district);
            return $this->shipment_payload($shipment_choices);
        }
    }

    /**
     * format payload
     * ---
     */
    public function shipment_payload($shipment_choices){
        $result = array();
        foreach ($shipment_choices as $provider) {
            $item = array(
                'id' => $provider->id,
                'name' => $provider->label,
                'cost' => array(
                    'currency_code' => get_woocommerce_currency(),
                    'amount' => (string) floatval($provider->cost)
                )
            );
            array_push($result, $item);
        }
        return $result;
    }

    /**
     * set destination
     * ---
     */
    private function set_destination(){
        $body = json_decode($this->request_data);
        $type = isset($body->data->shipment) ? $body->data->shipment->provider->type : 'custom';
        $this->provider_type = $type;
        if($this->provider_type == 'epeken'){
            $this->city = $body->data->shipment->provider->$type->kabupaten;
            $this->district = $body->data->shipment->provider->$type->kecamatan;
        }
        elseif($this->provider_type == 'agenwebsite'){
            $this->city = $this->get_id_state($body->data->shipment->provider->$type->provinsi);
            $this->district = $body->data->shipment->provider->$type->kecamatan;
            if (isset($body->data->shipment->provider->$type->kota))
                $this->district .= ', '.$body->data->shipment->provider->$type->kota;
            $this->postcode = $body->data->shipment->provider->$type->kodepos;
        }
        else{
            $this->province = $body->data->shipment_area->custom->province_id;
            $this->city = $body->data->shipment_area->custom->city_id;
            $this->district = $body->data->shipment_area->custom->district_id;
            $this->provider_type = 'custom';
        }
    }

    /**
     * get id state from province
     * ---
     */
    private function get_id_state($province){
        $id = '';
        $states = WC()->countries->get_states('ID');
        foreach( $states as $id => $name ){
            if($name == $province){
                $id = $id;
                break;
            }
        }
        return $id;
    }

    /**
     * add to cart
     * ---
     */
    private function add_to_cart(){
        global $woocommerce;

        $body = json_decode($this->request_data);

        $woocommerce->cart->empty_cart();
        foreach($body->data->cart->line_items as $item) {
            if(!isset($item->product->id) || !isset($item->quantity) || !is_int($item->quantity)){
                PrismappIoWebhook::response_with_bad_request("Empty product id or quantity must be integer");
            }

            $wc_product = wc_get_product($item->product->id);
            self::product_status($wc_product, $item->product->name, $item->quantity, $body);
            $woocommerce->cart->add_to_cart($item->product->id, $item->quantity);
        }
    }

    /**
     * check product status
     * ---
     */
    public static function product_status($wc_product, $product_name, $quantity, $payload = null){
        if(empty($wc_product) || !$wc_product->is_purchasable()){
            PrismappIoWebhook::response_with_not_found("Product {$product_name} does not exist");
        }

        $stock = WC_Integration_Prismappio::check_stock($wc_product);
        
        if($stock < $quantity || $stock == 0){
            PrismappIoWebhook::response_with_not_acceptable("Product {$product_name} is out of stock");
        }
    }

    /**
     * write log
     * ---
     */
    public static function logger($message, $request, $webhook = 'shipping-charge'){
        $logger = new WC_Logger();
        $logger->add('woo-prism', json_encode(
                array(
                    'webhook' => $webhook, 
                    'message' => $message, 
                    'request' => $request
                )
            )
        );
    }

    /**
     * Validate requests
     * ---
     */
    public function validate_request() {
        $body = json_decode($this->request_data);
        if(empty($body))
            PrismappIoWebhook::response_with_bad_request('Empty JSON body');

        if(empty($body->data))
            PrismappIoWebhook::response_with_bad_request('Empty data object');

        if(empty($body->data->shipment)){
            if(empty($body->data->shipment_area))
                PrismappIoWebhook::response_with_bad_request('Empty shipment object');
        }

        if(empty($body->data->cart))
            PrismappIoWebhook::response_with_bad_request('Empty cart object');
    }

    /**
     * Validate responses
     * ---
     */
    public function validate_response() {
        if(empty($this->response_data))
            PrismappIoWebhook::response_with_server_error();

        if(empty($this->response_data['status']))
            PrismappIoWebhook::response_with_server_error();

        if(empty($this->response_data['data']))
            PrismappIoWebhook::response_with_server_error();
    }
}
