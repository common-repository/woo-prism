<?php

if (!defined('ABSPATH')) {
    exit;
}

class PrismCreateInvoiceWebhook extends PrismappIoWebhook implements iPrismappIoWebhook {

    private $request_data;
    private $response_data;

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

        $body = json_decode($this->request_data);
        $buyer = $this->get_buyer($body);
        $coupon = $this->check_coupon($body);

        // if voucher expired / not found
        if($coupon['status'] == 'EXPIRED')
            PrismappIoWebhook::response_with_not_acceptable($coupon['message']);
        if($coupon['status'] == 'NOT_FOUND')
            PrismappIoWebhook::response_with_not_found($coupon['message']);

        $products = array();
        $customer_notes = '';
        $line_item_notes = array();
        foreach($body->data->order->line_items as $item) {
            if(!isset($item->product->id) || !isset($item->quantity) || !is_int($item->quantity))
                PrismappIoWebhook::response_with_bad_request("Empty product id or quantity");

            $wc_product = wc_get_product($item->product->id);
            PrismShippingChargesWebhook::product_status($wc_product, $item->product->name, $item->quantity);
            
            array_push($products, array(
                'data' => $wc_product,
                'quantity' => $item->quantity
            ));

            // collect notes (if any)
            if(isset($item->product->notes) && $item->product->notes != ''){
                $customer_notes .= "{$item->product->name} : {$item->product->notes}\n";
                $line_item_notes[$item->product->id] = $item->product->notes;
            }
        }

        $address_2 = $city = $state = '';
        $shipment_data = $body->data->shipment;
        if(isset($shipment_data->choice)){
            $address = $this->set_address($shipment_data);
            $address_2 = $address['district'];
            $city = $address['city'];
            $state = $address['state'];
        }

        $order_data = array(
            'payment_method'        =>  '',
            'payment_method_title'  =>  '',
            'set_paid'              =>  FALSE,
            'billing'               =>  array(
                'first_name'        =>  $buyer['first_name'],
                'last_name'         =>  $buyer['last_name'],
                'address_1'         =>  $shipment_data->address->full_street,
                'address_2'         =>  $address_2,
                'city'              =>  $city,
                'state'             =>  $state,
                'postcode'          =>  $shipment_data->address->postal_code,
                'country'           =>  'ID',
                'email'             =>  $buyer['email'],
                'phone'             =>  $buyer['phone']
            ),
            'shipping'              =>  array(
                'first_name'        =>  $buyer['first_name'],
                'last_name'         =>  $buyer['last_name'],
                'address_1'         =>  $shipment_data->address->full_street,
                'address_2'         =>  $address_2,
                'city'              =>  $city,
                'state'             =>  $state,
                'postcode'          =>  $shipment_data->address->postal_code,
                'country'           =>  'ID'
            ),
            'line_items'            =>  $products
        );

        $base_order = new stdClass;
        $base_order->status = 'pending';
        $base_order->customer_id = $buyer['ID'];
        $base_order->created_via = 'prismappio';

        // Create the order object
        $wc_order = wc_create_order((array) $base_order);
        if(is_wp_error($wc_order)) {
            PrismappIoWebhook::response_with_server_error('Cannot create order');
        }

        // Add Billing & Shipping Addresses
        $wc_order->set_address($order_data['billing'], 'billing');
        $wc_order->set_address($order_data['shipping'], 'shipping');

        // Set Currency
        update_post_meta($wc_order->get_order_number(), '_order_currency', 'IDR');

        // set `kelurahan` and `kecamatan` (provide ekepen carrier)
        update_post_meta($wc_order->get_order_number(), 'billing_kelurahan', '');
        update_post_meta($wc_order->get_order_number(), 'billing_kecamatan', $address_2);
        update_post_meta($wc_order->get_order_number(), 'shipping_kelurahan', '');
        update_post_meta($wc_order->get_order_number(), 'shipping_kecamatan', $address_2);

        // add agent name who create invoice, for easy tracking (agent performance)
        if(isset($body->data->agent)){
            update_post_meta($wc_order->get_order_number(), 'agent_name', $body->data->agent->name);
            update_post_meta($wc_order->get_order_number(), 'agent_email', $body->data->agent->email);
            $wc_order->add_order_note('Agent Name : '.$body->data->agent->name);
        }

        // add customer notes
        if($customer_notes != ''){
            $title_notes = "Product Notes\n";
            $title_notes .= "=============\n";
            $wc_order->add_order_note($title_notes.$customer_notes, $is_customer_note = 1);
        }

        // add invoice notes
        if(isset($body->data->notes) && $body->data->notes != ''){
            $title_notes = "Invoice Notes\n";
            $title_notes .= "=============\n";
            $wc_order->add_order_note($title_notes.$body->data->notes, $is_customer_note = 1);
            update_post_meta($wc_order->get_order_number(), 'invoice_notes', $body->data->notes);
        }
        
        // Add products to cart
        $item_args = array();
        $grand_total = 0;
        $fixed_cart_discount = 0;

        // set discount for coupon with fixed_cart type
        if($coupon['is_valid'] && $coupon['data']->discount_type == 'fixed_cart'){
            $cart_grant_total = 0;
            foreach($order_data['line_items'] as $product) {
                $product_id = ($product['data']->product_type == 'variation') ? $product['data']->variation_id : $product['data']->id;
                $cart_total = $product['data']->regular_price * $product['quantity'];
                if(WC_Integration_Prismappio::is_discount($product_id)){
                    $cart_total = $product['data']->sale_price * $product['quantity'];
                }
                $cart_grant_total += $cart_total;
            }
            $fixed_cart_discount = ($coupon['data']->amount / $cart_grant_total) * 100;
            $fixed_cart_discount = round($fixed_cart_discount, 3);
        }

        $new_line_items = array();
        foreach($order_data['line_items'] as $product) {
            if (method_exists($product['data'], 'get_variation_attributes')) {
                $item_args['variation'] = $product['data']->get_variation_attributes();
            }

            $product_id = WC_Integration_Prismappio::get_product_id($product['data']);
            $subtotal = $product['data']->get_regular_price() * $product['quantity'];
            $price = $product['data']->get_regular_price();
            if(WC_Integration_Prismappio::is_discount($product_id)){
                $subtotal = $product['data']->get_sale_price() * $product['quantity'];
                $price = $product['data']->get_sale_price();
            }

            $item_args['totals']['total'] = $subtotal;
            $item_args['totals']['subtotal'] = $subtotal;

            if($coupon['is_valid']){
                $total = $subtotal - $coupon['data']->amount;
                $price_coupon = $price - $coupon['data']->amount;
                if($coupon['data']->discount_type == 'percent' || $coupon['data']->discount_type == 'percent_product'){
                    $total = $subtotal - (($subtotal * $coupon['data']->amount) / 100);
                    $price_coupon = $price - (($price * $coupon['data']->amount) / 100);
                }
                elseif($coupon['data']->discount_type == 'fixed_cart'){
                    $total = $subtotal - (($subtotal * $fixed_cart_discount) / 100);
                    $total = round($total);
                    $price_coupon = $price - (($price * $fixed_cart_discount) / 100);
                    $price_coupon = round($price_coupon);
                }

                // to handle minus
                if($total < 0) $total = 0;
                if($price_coupon < 0) $price_coupon = 0;

                $item_args['totals']['total'] = $total;
                $item_args['totals']['subtotal'] = $subtotal;

                $new_item_product = array(
                    'product' => array(
                        'id' => WC_Integration_Prismappio::get_product_id($product['data']),
                        'name' => $product['data']->get_title(),
                        'price' => (string) $product['data']->get_regular_price(),
                        'currency_code' => 'IDR'
                    ),
                    'quantity' => $product['quantity'],
                    'discount' => array(
                        'discount_type' => 'NOMINAL',
                        'amount' => (string) ($product['data']->get_regular_price() - $price_coupon)
                    )
                );
                $new_line_items[] = $new_item_product;
            }

            $grand_total += $item_args['totals']['total'];
            $wc_order->add_product($product['data'], $product['quantity'], $item_args);
        }

        // add additional discount
        foreach($body->data->order->line_items as $key => $item) {
            if(isset($item->product->prism_additional_discount)){
                $item_id = wc_add_order_item( $wc_order->id, array(
                    'order_item_name' => "Discount for ".$item->product->name,
                    'order_item_type' => 'fee'
                ));
                wc_add_order_item_meta( $item_id, '_line_total', wc_format_decimal(-$item->product->prism_additional_discount));

                $additional_discount = $item->product->discount->amount + $item->product->prism_additional_discount;
                $body->data->order->line_items[$key]->product->discount->amount = $additional_discount;
                $body->data->order->line_items[$key]->product->prism_additional_discount = 0;
            }
        }

        // if shipping provider not found
        if(!isset($shipment_data->choice)){
            $shipping_info = array(
                'method_id'     =>  'Manual',
                'method_title'  =>  'Manual',
                'total'         =>  $shipment_data->cost->amount
            );
        }
        else{
            $shipping_info = array(
                'method_id'     =>  $shipment_data->choice->id,
                'method_title'  =>  $shipment_data->choice->name,
                'total'         =>  $shipment_data->cost->amount
            );
        }

        // using coupon
        if(($coupon['is_valid'] && $coupon['data']->enable_free_shipping()) || !isset($shipment_data->cost)) {
            $shipping_info = array(
                'method_id'     =>  'Free',
                'method_title'  =>  'Free Shipping',
                'total'         =>  0
            );
        }

        // Build Shipping costs
        $wc_ship_rate = new WC_Shipping_Rate($shipping_info['method_id'], $shipping_info['method_title'], 
            $shipping_info['total'], array(), '');
        $wc_order->add_shipping($wc_ship_rate);

        // using coupon
        if($coupon['is_valid']){
            $code = sanitize_text_field($coupon['code']);
            $amount = $woocommerce->cart->get_coupon_discount_amount($code);
            $amount_with_tax = $woocommerce->cart->get_coupon_discount_tax_amount($code);
            $wc_order->add_coupon($code, $amount, $amount_with_tax);
        }

        // update product note to line item meta
        if(count($line_item_notes) > 0){
            foreach($wc_order->get_items() as $key => $value){
                $notes = ($line_item_notes[$value['product_id']]) ? $line_item_notes[$value['product_id']] : $line_item_notes[$value['variation_id']];
                if($notes){
                    wc_update_order_item_meta($key, 'notes', $notes); 
                }
            }
        }

        // Calculate Totals
        $wc_order->calculate_totals();

        // add payment method
        $payment_type = $body->data->payment->provider->type;
        if(strtolower($payment_type) == 'transfer'){
            $bank_name = $body->data->payment->provider->transfer->bank_name;
            $account_number = $body->data->payment->provider->transfer->account_number;
            $payment_type = $bank_name.' - '.$account_number;
        }
        update_post_meta($wc_order->get_order_number(), '_payment_method', $payment_type);
        update_post_meta($wc_order->get_order_number(), '_payment_method_title', $payment_type);

        // status order by config
        $config = get_option('woocommerce_prismappio_settings');
        if($config['order_status'] == 'onhold'){
            // update status
            $wc_order->update_status( 'on-hold');
            // and reduce stock
            $wc_order->reduce_order_stock();
        }

        $response['invoice'] = array(
            'id' => "{$wc_order->get_order_number()}",
            'status' => 'ISSUED',
            'line_items' => (count($new_line_items) > 0) ? $new_line_items : $body->data->order->line_items,
            'grand_total' => array(
                'currency_code' => 'IDR',
                'amount' => (string) $wc_order->get_total()
            ),
            'shipment' => $body->data->shipment,
            'payment' => $body->data->payment
        );

        if($coupon['is_valid']){
            $response['invoice']['vouchers'] = array(array(
                'code' => $coupon['code'],
                'status' => 'APPLIED',
                'description' => 'the voucher is applied'
            ));
        }

        if(isset($body->data->notes) && $body->data->notes != ''){
            $response['invoice']['notes'] = $body->data->notes;
        }

        $this->response_data = array(
            'status'    =>  'success',
            'data'      =>  $response
        );
    }

    private function set_address($shipment_data){
        $provider_type = $shipment_data->provider->type;
        if($provider_type == 'custom'){
            $district = $shipment_data->provider->{ $provider_type }->district;
            $city = $shipment_data->provider->{ $provider_type }->city;
            $state = $shipment_data->provider->{ $provider_type }->province;
        }
        else{
            $district = $shipment_data->provider->{ $provider_type }->kecamatan;
            $city = ($provider_type == 'agenwebsite') ? 'kota' : 'kabupaten';
            $city = $shipment_data->provider->{ $provider_type }->{ $city };
            $state = ($provider_type == 'agenwebsite') ? $shipment_data->provider->{ $provider_type }->provinsi : '';
        }

        return array(
            'district' => $district,
            'city' => $city,
            'state' => $state
        );
    }

    /**
     * check buyer
     * ---
     */
    private function get_buyer($body){
        $buyer = $body->data->buyer;
        $name = $this->set_first_and_lastname($buyer->name);

        $user_id = email_exists($buyer->email);
        if($user_id !== FALSE) {
            // User has been registered before
            $user = get_userdata($user_id);
            return array(
                'ID'         => (string) $user_id,
                'email'      => $user->data->user_email,
                'first_name' => $name['first_name'],
                'last_name'  => $name['last_name'],
                'phone'      => $buyer->phone_number
            );
        }

        // Ensure username is unique.
        $username = sanitize_user($buyer->name, true);
        $username = str_replace(' ', '-', $username);

        $username_suffix     = 1;
        $original_username = $username;

        while (username_exists($username)){
            $username = $original_username . $username_suffix;
            $username_suffix++;
        }
        
        $random_password = wp_generate_password($length=12, $include_standard_special_chars=false);
        
        $user_id = wc_create_new_customer($buyer->email, $username, $random_password);
        if(is_int($user_id)) {
            $user_data       = array(
                'ID'         => (string) $user_id,
                'email'      => $buyer->email,
                'first_name' => $name['first_name'],
                'last_name'  => $name['last_name'],
                'phone'      => $buyer->phone_number
            );
            $update_user     = wp_update_user($user_data);

            // metadata for prismappio
            $update_metadata = add_user_meta($user_id, 'prismappio', true);

            return $user_data;
        }

        if(is_wp_error($user_id)) {
            PrismappIoWebhook::response_with_bad_request('failed to fetch customer data : '.$user_id->get_error_message());
        }
    }

    private function set_first_and_lastname($name){
        $name = explode(' ', trim($name));
        $first_name = $name[0];
        if(count($name) == 1){
            $last_name = $first_name;
        }
        else{
            array_shift($name);
            $last_name = implode(' ', $name);
        }

        return array(
            'first_name' => $first_name,
            'last_name'  => $last_name
            );
    }

    /**
     * // check coupon (if any)
     * ---
     */
    private function check_coupon($body){
        $is_valid = $coupon = $status = $message = $code = false;
        if(isset($body->data->vouchers) && count($body->data->vouchers) > 0){
            // get first array, woo only support 1 coupon 1 order
            $code = $body->data->vouchers[0]->code;
            $status = $body->data->vouchers[0]->status;
            $coupon = new WC_Coupon($code);

            if($coupon->id > 0 && $coupon->is_valid()){
                $is_valid = true;
                $status = 'VALIDATED';
            }
            else{
                $message = $coupon->get_error_message();
                $status = (empty($message)) ? 'NOT_FOUND' : 'EXPIRED';
            }
        }
        return array(
            'is_valid' => $is_valid,
            'data' => $coupon,
            'code' => $code,
            'status' => $status,
            'message' => $message
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

        if(empty($body->data->order))
            PrismappIoWebhook::response_with_bad_request('Empty order object');

        if(empty($body->data->order->line_items))
            PrismappIoWebhook::response_with_bad_request('Empty array of product object');

        if(empty($body->data->shipment))
            PrismappIoWebhook::response_with_bad_request('Empty shipment object');

        if(empty($body->data->shipment->address))
            PrismappIoWebhook::response_with_bad_request('Empty shipment object');

        if(empty($body->data->payment))
            PrismappIoWebhook::response_with_bad_request('Empty payment object');

        if(empty($body->data->buyer))
            PrismappIoWebhook::response_with_bad_request('Empty buyer object');
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