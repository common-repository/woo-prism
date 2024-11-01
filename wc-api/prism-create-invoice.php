<?php

class Prism_Invoice extends WC_API_Resource {

    const PATH = '/prismappio';

    public function register_routes($routes) {
        $routes[self::PATH . '/create-invoice'] = array(
            array(array($this, 'run'), WC_API_Server::CREATABLE | WC_API_Server::ACCEPT_RAW_DATA),
        );
        return $routes;
    }

    public function run($data) {
        $prismappio = new PrismCreateInvoiceWebhook();
        $prismappio->run($data);
    }
}
