<?php

if (!defined('ABSPATH')) {
    exit;
}

interface iPrismappIoWebhook {
    public function run($request_data);
    public function process();
    public function validate_request();
    public function validate_response();
}