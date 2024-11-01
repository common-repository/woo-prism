<?php

class Prism_Area extends WC_API_Resource {

    const PATH = '/prismappio';

    public function register_routes($routes) {
        $routes[self::PATH . '/area'] = array(
            array(array($this, 'run'), WC_API_Server::READABLE),
        );
        return $routes;
    }

    public function run($data) {
        $prismappio = new PrismSearchAreaWebhook();
        $prismappio->run($data);
    }
}
