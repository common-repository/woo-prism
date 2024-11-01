<?php

class PrismPingWebhook extends PrismappIoWebhook implements iPrismappIoWebhook {

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
        global $wp_version;
        global $woocommerce;
        global $wpdb;

        // Get this function from view-helper.php loaded from main integration class
        $url = construct_http_root_url();
        $list_of_plugins = $this->list_of_plugins();

        $this->response_data = array(
            'status'    =>  'ok',
            'data'      =>  array(
                'website'               =>  $_SERVER['HTTP_HOST'],
                'server_info'           =>  esc_html($_SERVER['SERVER_SOFTWARE']),
                'prism_version'         =>  WC_Prismappio::VERSION,
                'wordpress_version'     =>  $wp_version,
                'woocommerce_version'   =>  $woocommerce->version,
                'php_version'           =>  phpversion(),
                'mysql_version'         =>  $wpdb->db_version(),
                'webhooks'              =>  array(
                    'ping'              =>  $url.'/wp-json/prismappio/ping',
                    'search_product'    =>  $url.'/wp-json/prismappio/search',
                    'search_area'       =>  $url.'/wp-json/prismappio/area',
                    'shipping_charge'   =>  $url.'/wp-json/prismappio/shipping-charge',
                    'create_invoice'    =>  $url.'/wp-json/prismappio/create-invoice'
                ),
                'installed_modules'     =>  $list_of_plugins['active']
            )
        );
    }

    private function list_of_plugins() {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $active = $inactive = array();
        foreach(get_plugins() as $key => $plugin) {
            $path = ABSPATH . PLUGINDIR .'/'.$key;
            $plugin_data = get_plugin_data($path);
            $plugin_name = $plugin_data['Name'].' - '.$plugin_data['AuthorName'].' v'.$plugin_data['Version'];
            if(is_plugin_active($key))
                array_push($active, $plugin_name);
            else
                array_push($inactive, $plugin_name);
        }
        return array(
            'active' => $active,
            'inactive' => $inactive
        );
    }

    /**
     * Validate requests
     * ---
     */
    public function validate_request() {
        // No params check necessary
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

        if(!isset($this->response_data['data']['wordpress_version']))
            PrismappIoWebhook::response_with_server_error();

        if(empty($this->response_data['data']['woocommerce_version']))
            PrismappIoWebhook::response_with_server_error();

        if(empty($this->response_data['data']['php_version']))
            PrismappIoWebhook::response_with_server_error();

        if(empty($this->response_data['data']['webhooks']))
            PrismappIoWebhook::response_with_server_error();
    }
}
