<?php

class Prism_Ping extends WC_API_Resource {

    const PATH = '/prismappio';

    public function register_routes($routes) {
        $routes[self::PATH . '/ping'] = array(
            array(array($this, 'run'), WC_API_Server::READABLE),
        );
        return $routes;
    }

    public function run($data) {
        $prismappio = new PrismPingWebhook();
        $prismappio->run($data);
    }
}
