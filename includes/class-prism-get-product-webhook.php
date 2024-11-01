<?php

class PrismGetProductWebhook extends PrismappIoWebhook implements iPrismappIoWebhook {

    private $request_data;
    private $response_data;

    /**
     * Bootstrapper for the controller
     * ---
     */
    public function run($request_data) {
        $this->request_data = $request_data;
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
        
        $product_id = $this->request_data->get_param('id');
        $wc_product = wc_get_product($product_id);
        if(empty($wc_product))
            PrismappIoWebhook::response_with_not_found("The product with product_id {$product_id} does not exist");

        $wc_variant = new WC_Product_Variable($wc_product->id);
        $variations = $wc_variant->get_available_variations();
        $parent_product = true;
        if(count($variations) > 0){
            foreach ($variations as $variant) {
                if($variant['variation_id'] == $product_id){
                    $parent_product = false;
                    $body = WC_Integration_Prismappio::get_create_product_request_payload($wc_product, $variant);
                }
            }
            if($parent_product)
                PrismappIoWebhook::response_with_not_found("The product with product_id {$product_id} does not exist");
        }
        else{
            $body = WC_Integration_Prismappio::get_create_product_request_payload($wc_product);
        }

        $this->response_data = array(
            'status'    =>  'success',
            'data'      =>  array(
                'product' => $body
            )
        );
    }

    /**
     * Validate requests
     * ---
     */
    public function validate_request() {
        $body = json_decode($this->request_data->get_param('id'));
        if(empty($body))
            PrismappIoWebhook::response_with_bad_request('Empty Product ID');
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
