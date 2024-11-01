<?php

class Prism_UcpItems extends WC_API_Resource {

    const PATH = '/prismappio';

    public function register_routes($routes) {
        $routes[self::PATH . '/ucp-items'] = array(
            array(array($this, 'run'), WC_API_Server::READABLE),
        );
        return $routes;
    }

    public function run($data) {
        $prismappio = new PrismUcpItemsWebhook();
        $prismappio->run($data);
    }
}
