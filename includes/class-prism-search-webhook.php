<?php

class PrismSearchWebhook extends PrismappIoWebhook implements iPrismappIoWebhook {

    const MIN_CHAR = 3;
    const PER_PAGE = 10;

    // force all images use https to handle LINE@ limitation
    const SCARLETT_URL = 'https://scarlett.prismapp.io/';

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
        
        $results = array();
        $body = json_decode($this->request_data);
        $keyword = trim(sanitize_text_field($body->must[0]->query_string->query));

        if(strlen($keyword) < self::MIN_CHAR){
            return $this->response_data = array(
                'status'    => 'success',
                'data'      => array(
                    'total'   => 0,
                    'results' => array(),
                )
            );
        }

        // product search rule
        $config = get_option('woocommerce_prismappio_settings');
        $exact = false;
        if(isset($config['product_search'])){
            $exact = ($config['product_search'] == 'title') ? true : false;
        }

        // search on title
        add_action('posts_where', array($this, 'search_keyword_on_title'), 10, 2);

        // get total
        $args = array(
            'post_type'         => 'product',
            'post_status'       => 'publish',
            'nopaging'          => true,
            's'                 => $keyword,
            'exact'             => $exact
        );
        $total = 0;
        $products = new WP_Query($args);
        if($products->found_posts > 0){
            foreach($products->posts as $value){
                $wc_variant = new WC_Product_Variable($value->ID);
                $variations = $wc_variant->get_available_variations();
                if(count($variations) > 0){
                    foreach ($variations as $variant) {
                        $total++;
                    }
                }
                else $total++;
            }
        }

        // get data
        $args = array(
            'post_type'         => 'product',
            'post_status'       => 'publish',
            'offset'            => isset($body->from) ? $body->from : 0,
            'posts_per_page'    => isset($body->size) ? $body->size : self::PER_PAGE,
            's'                 => $keyword,
            'exact'             => $exact
        );

        $products = new WP_Query($args);
        if($products->found_posts > 0){
            foreach($products->posts as $value){
                $wc_product = new WC_Product($value->ID);
                $wc_variant = new WC_Product_Variable($value->ID);
                $variations = $wc_variant->get_available_variations();
                if(count($variations) > 0){
                    foreach ($variations as $variant) {
                        $body = WC_Integration_Prismappio::get_create_product_request_payload($wc_product, $variant);
                        array_push($results, $this->buildData($body));
                    }
                }
                else{
                    $body = WC_Integration_Prismappio::get_create_product_request_payload($wc_product);
                    array_push($results, $this->buildData($body));
                }
            }
        }

        if($total == 0)
            PrismappIoWebhook::response_with_not_found("Product {$keyword} not found");

        $this->response_data = array(
            'status'    => 'success',
            'data'      => array(
                'total_hits' => $total,
                'results'    => $results
            )
        );
    }

    function search_keyword_on_title($where, &$wp_query){
        global $wpdb;
        if($wp_query->query['exact']){
            $sql = esc_sql($wpdb->esc_like( $wp_query->query['s'] ));
            $post_type = 'AND wp_posts.post_type = "'.$wp_query->query['post_type'].'"';
            $post_status = ' AND wp_posts.post_status = "'.$wp_query->query['post_status'].'"';
            $where .= ' OR (' . $wpdb->posts . '.post_title LIKE \'%' . $sql . '%\' ' . $post_type . $post_status .')';
        }
        return $where;
    }

    private function buildData($body){
        $payload = $body['payload'];

        $replace_urls = array();
        $image_urls = array_values($payload['image_urls']);
        foreach($image_urls as $url){
            $url = str_replace(parse_url($url, PHP_URL_SCHEME).'://', '', $url);
            $replace_urls[] = self::SCARLETT_URL.$url;
        }

        $result = array(
            'id'            => $payload['sku'],
            'name'          => $payload['name'],
            'description'   => $payload['description'],
            'image_urls'    => $replace_urls,
            'stock'         => $payload['stock'],
            'price'         => (string) $payload['price'],
            'discount'      => array(
                'discount_type' => 'NOMINAL',
                'amount'        => (string) $payload['discount']['amount']
            ),
            'currency_code' => 'IDR'
        );
        return $result;
    }

    /**
     * Validate requests
     * ---
     */
    public function validate_request() {
        $body = json_decode($this->request_data);
        if(empty($body))
            PrismappIoWebhook::response_with_bad_request('Empty JSON body');

        if(empty($body->must))
            PrismappIoWebhook::response_with_bad_request('Empty array of search object');

        if(empty($body->must[0]->query_string))
            PrismappIoWebhook::response_with_bad_request('Empty query_string object');

        if(empty($body->must[0]->query_string->query))
            PrismappIoWebhook::response_with_bad_request('Empty query object');
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
