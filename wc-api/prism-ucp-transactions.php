<?php

class Prism_UcpTransactions extends WC_API_Resource {

    const PATH = '/prismappio';

    public function register_routes($routes) {
        $routes[self::PATH . '/ucp-transactions'] = array(
            array(array($this, 'run'), WC_API_Server::READABLE),
        );
        return $routes;
    }

    public function run($data) {
        $prismappio = new PrismUcpTransactionsWebhook();
        $prismappio->run($data);
    }
}
