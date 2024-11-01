<?php

if (!defined('ABSPATH')) {
    exit;
}

class PrismUcpTransactionsWebhook extends PrismappIoWebhook implements iPrismappIoWebhook {

    const TRANSACTION_LIMIT_COUNT = 5;
    const TRANSACTION_PREFIX = '';
    
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

    public function process() {
        global $woocommerce;

        if ( version_compare( $woocommerce->version, '2.2', "<" ) ) {
            PrismappIoWebhook::response_woo_need_upgrade();
        }

        $request_params = $this->request_data->get_params();
        $customer_id = $this->request_data->get_param('customer_id');
        
        $user_id = $this->get_user_id($customer_id);

        if ( version_compare( $woocommerce->version, '3.0', "<" ) ) {
            $transactions = $this->get_all_orders_v2($user_id);
        } else {
            $transactions = $this->get_all_orders_v3($user_id);
        }

        $response['transactions'] = $transactions;

        $this->response_data = array(
            'status'    =>  'success',
            'data'      =>  $response
        );
    }

    private function get_all_orders_v2($customer_id) {
        // get post return in descending date order
        $customer_orders = get_posts(
            array(
                'meta_key'    => '_customer_user',
                'meta_value'  => $customer_id,
                'post_type'   => wc_get_order_types(),
                'post_status' => array_keys( wc_get_order_statuses() ),
                'posts_per_page' => self::TRANSACTION_LIMIT_COUNT,
                'offset'=> 0,
            )
        );

        $transactions = array();

        foreach ($customer_orders as $order) {
            $trx_ = array();
            $order_object = wc_get_order( $order->ID );
            $order_items = $order_object->get_items();
            
            $trx_['transaction_id'] = self::TRANSACTION_PREFIX . (string)$order->ID;
            $trx_['discount'] = array(
                'currency_code' => $order_object->get_order_currency(),
                'value'         => (string)$order_object->get_total_discount()
            );
            $trx_['sub_total'] = array(
                'currency_code' => $order_object->get_order_currency(),
                'value'         => (string)$order_object->get_subtotal()
            );
            $trx_['grand_total'] = array(
                'currency_code' => $order_object->get_order_currency(),
                'value'         => (string)$order_object->get_total()
            );
            $trx_['shipping_cost'] = array(
                'currency_code' => $order_object->get_order_currency(),
                'value'         => (string)$order_object->get_total_shipping()
            );
            $trx_['transaction_date'] = empty($order->post_date_gmt) ? "" : str_replace(" ", "T" , $order->post_date_gmt.".000Z");
            $trx_['transaction_statuses'] = array(
                array(
                    'status'        => $order_object->get_status(),
                    'updated_at'    => empty($order->post_modified_gmt) ? "" : str_replace(" ", "T" , $order->post_modified_gmt.".000Z")
                )
            );

            $customer_note = '';
            foreach($order_object->get_customer_order_notes() as $note){
                $customer_note .= $note->comment_content;
            }

            // fix php 5.4 for empty result Can't use method return value in write context
            $used_coupons = $order_object->get_used_coupons();

            $trx_['metadata'] = array(
                array(
                    'shipping_tax'  => (string)$order_object->get_shipping_tax()
                ),
                array(
                    'total_tax'  => (string)$order_object->get_total_tax()
                ),
                array(
                    'customer_note'  => (string)$customer_note
                ),
                array(
                    'used_coupons'   => empty($used_coupons) ? "" : (string)$order_object->get_used_coupons()[0]
                )
            );

            $trx_['items'] = array();

            foreach ($order_items as $order_item) {
                $item_price = 0;
                if ($order_item['qty'] && $order_item['line_subtotal']) {
                    $item_price = (int)$order_item['line_subtotal']/(int)$order_item['qty'];
                }

                $product_id = $order_item['product_id'];
                if ($order_item['variation_id'] > 0) {
                    $product_id = $order_item['variation_id'];
                }

                $item = array();
                $product = wc_get_product($product_id);
                
                // fix php 5.4 for empty result Can't use method return value in write context
                $image_id = $product->get_image_id();

                $item['product_id'] = (string)$order_item['product_id'] ?: '';
                $item['quantity'] = (string)$order_item['qty'] ?: '0';
                $item['sku'] = $product ? $product->get_sku() : '';
                $item['product_name'] = $order_item['name'] ?: 'Product #'.$item['product_id'];
                $item['images'] = $product ? (empty($image_id) ? array() : array(
                    wp_get_attachment_url($product->get_image_id())
                )) : array();
                $item['price'] = array(
                    'currency_code' => $order_object->get_order_currency(),
                    'value'         => (string)$item_price
                );
                $item['transaction_date'] = empty($order->post_date_gmt) ? "" : str_replace(" ", "T" , $order->post_date_gmt.".000Z");
                $item['metadata'] = array(
                    array(
                        'subtotal'  => (string)$order_item['line_subtotal']
                    ),
                    array(
                        'total'     => (string)$order_item['line_total']
                    )
                );
                $item['product_metadata'] = array(
                    array(
                        'average_rating'  => $product ? (string)$product->get_average_rating() : ''
                    )
                );
                array_push($trx_['items'], $item);
            }

            array_push($transactions, $trx_);
        }

        return $transactions;
    }

    private function get_all_orders_v3($customer_id) {
        // get post return in descending date order
        $customer_orders = get_posts(
            array(
                // 'numberposts' => -1,
                'meta_key'    => '_customer_user',
                'meta_value'  => $customer_id,
                'post_type'   => wc_get_order_types(),
                'post_status' => array_keys( wc_get_order_statuses() ),
                'posts_per_page' => self::TRANSACTION_LIMIT_COUNT,
                'offset'=> 0,
            )
        );

        $transactions = array();

        foreach ($customer_orders as $order) {
            $trx_ = array();
            $order_object = wc_get_order( $order->ID );
            $order_data = $order_object->get_data();
            $order_items = $order_object->get_items();

            // fix php 5.4 for empty result Can't use method return value in write context
            $date_created = $order_object->get_date_created();
            $date_modified = $order_object->get_date_modified();
            $date_completed = $order_object->get_date_completed();
            $date_paid = $order_object->get_date_paid();
            $used_coupons = $order_object->get_used_coupons();
            
            $trx_['transaction_id'] = self::TRANSACTION_PREFIX . (string)$order_data['id'];
            $trx_['discount'] = array(
                'currency_code' => $order_data['currency'],
                'value'         => (string)$order_data['discount_total']
            );
            $trx_['sub_total'] = array(
                'currency_code' => $order_data['currency'],
                'value'         => (string)$order_object->get_subtotal()
            );
            $trx_['grand_total'] = array(
                'currency_code' => $order_data['currency'],
                'value'         => (string)$order_data['total']
            );
            $trx_['shipping_cost'] = array(
                'currency_code' => $order_data['currency'],
                'value'         => (string)$order_data['shipping_total']
            );
            $trx_['transaction_date'] = empty($date_created) ? "" : str_replace(" ", "T" , $order_object->get_date_created()->date("Y-m-d H:i:s.000")."Z");
            $trx_['transaction_statuses'] = array(
                array(
                    'status'        => $order_data['status'],
                    'updated_at'    => empty($date_modified) ? "" : str_replace(" ", "T" , $order_object->get_date_modified()->date("Y-m-d H:i:s.000")."Z")
                )
            );
            $trx_['metadata'] = array(
                array(
                    'discount_tax'  => $order_data['discount_tax'] ? (string)$order_data['discount_tax'] : ''
                ),
                array(
                    'shipping_tax'  => $order_data['shipping_tax'] ? (string)$order_data['shipping_tax'] : ''
                ),
                array(
                    'total_tax'  => $order_data['total_tax'] ? (string)$order_data['total_tax'] : ''
                ),
                array(
                    'payment_method'  => $order_data['payment_method'] ? (string)$order_data['payment_method'] : ''
                ),
                array(
                    'payment_method_title'  => $order_data['payment_method_title'] ? (string)$order_data['payment_method_title'] : ''
                ),
                array(
                    'created_via'  => $order_data['created_via'] ? (string)$order_data['created_via'] : ''
                ),
                array(
                    'customer_note'  => $order_data['customer_note'] ? (string)$order_data['customer_note'] : ''
                ),
                array(
                    'date_completed'  => empty($date_completed) ? "" : str_replace(" ", "T" , $order_object->get_date_completed()->date("Y-m-d H:i:s.000")."Z")
                ),
                array(
                    'date_paid'  => empty($date_paid) ? "" : str_replace(" ", "T" , $order_object->get_date_paid()->date("Y-m-d H:i:s.000")."Z")
                ),
                array(
                    'used_coupons'   => empty($used_coupons) ? "" : (string)$order_object->get_used_coupons()[0]
                )
            );

            $trx_['items'] = array();

            foreach ($order_items as $order_item) {

                $item_price = 0;
                if ($order_item->get_quantity() && $order_item->get_subtotal()) {
                    $item_price = (int)$order_item->get_subtotal()/(int)$order_item->get_quantity();
                }

                $item = array();
                $product = $order_item->get_product();

                // fix php 5.4 for empty result Can't use method return value in write context
                $image_id = $product->get_image_id();
                $date_created = $order_object->get_date_created();
                
                $item['product_id'] = (string)$order_item['product_id'];
                $item['quantity'] = (string)$order_item['quantity'];
                $item['sku'] = $product ? $product->get_sku() : '';
                $item['product_name'] = $order_item->get_name() ? $order_item->get_name() : 'Product #'.$item['product_id'];
                $item['images'] = $product ? (empty($image_id) ? array() : array(
                    wp_get_attachment_url($product->get_image_id())
                )) : array();
                $item['price'] = array(
                    'currency_code' => $order_data['currency'],
                    'value'         => (string)$item_price
                );
                $item['transaction_date'] = empty($date_created) ? "" : str_replace(" ", "T" , $order_object->get_date_created()->date("Y-m-d H:i:s.000")."Z");
                $item['metadata'] = array(
                    array(
                        'variation_id'  => (string)$order_item->get_variation_id()
                    ),
                    array(
                        'subtotal'  => (string)$order_item->get_subtotal()
                    ),
                    array(
                        'total'     => (string)$order_item->get_total()
                    )
                );
                $item['product_metadata'] = array(
                    array(
                        'short_description'  => $product ? (string)$product->get_short_description() : ''
                    ),
                    array(
                        'average_rating'  => $product ? (string)$product->get_average_rating() : ''
                    ),
                    array(
                        'product_url'     => $product ? (string)$product->get_permalink() : ''
                    )
                );
                array_push($trx_['items'], $item);
            }

            array_push($transactions, $trx_);
        }

        return $transactions;
    }

    private function get_user_id($customer_id) {
        $user = get_user_by('email', $customer_id);
        if (empty($user))
            $user = get_user_by('id', $customer_id );
        if (!empty($user))
            return $user->ID;
        
        PrismappIoWebhook::response_with_not_found('Customer not found');
    }

    /**
     * Validate requests
     * ---
     */
    public function validate_request() {
        // fix php 5.4 for empty result Can't use method return value in write context
        $customer_id = $this->request_data->get_param('customer_id');
        if(empty($customer_id))
            PrismappIoWebhook::response_with_bad_request('No customer_id parameter provided');
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