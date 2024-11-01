<?php

class Prism_ShippingCharges extends WC_API_Resource {

    const PATH = '/prismappio';

    public function register_routes($routes) {
        $routes[self::PATH . '/shipping-charge'] = array(
            array(array($this, 'run'), WC_API_Server::CREATABLE | WC_API_Server::ACCEPT_RAW_DATA),
        );
        return $routes;
    }

    public function run($data) {
        $prismappio = new PrismShippingChargesWebhook();
        $prismappio->run($data);
    }
}
