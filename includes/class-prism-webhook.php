<?php

if (!defined('ABSPATH')) {
    exit;
}

class PrismappIoWebhook {

    public static function response_with_bad_request($message, $data=NULL) {
        $response = json_encode(array(
            'status'    =>  'failed',
            'message'   =>  $message,
            'data'      =>  !empty($data) ? $data : new stdClass
        ));

        PrismappIoWebhook::response_with_json($response, 400);
    }

    public static function response_with_not_found($message='', $data=NULL) {
        $response = json_encode(array(
            'status'    =>  'failed',
            'message'   =>  !empty($message) ? $message : 'Not found',
            'data'      =>  !empty($data) ? $data : new stdClass
        ));

        PrismappIoWebhook::response_with_json($response, 404);
    }

    public static function response_with_server_error($message='Server error', $data=NULL) {
        $response = json_encode(array(
            'status'    =>  'failed',
            'message'   =>  $message,
            'data'      =>  !empty($data) ? $data : new stdClass
        ));

        PrismappIoWebhook::response_with_json($response, 500);
    }

    public static function response_woo_need_upgrade() {
        $response = json_encode(array(
            'status'    =>  'failed',
            'message'   =>  'This plugin only support Woocommerce v2.2.x up to latest, please update it.',
            'data'      =>  new stdClass
        ));

        PrismappIoWebhook::response_with_json($response, 501);
    }

    public static function response_with_not_acceptable($message='Voucher Invalid', $data=NULL) {
        $response = json_encode(array(
            'status'    =>  'failed',
            'message'   =>  $message,
            'data'      =>  !empty($data) ? $data : new stdClass
        ));

        PrismappIoWebhook::response_with_json($response, 406);
    }

    public static function response_with_json($json, $status_code=200) {
        header('Content-Type: application/json');
        header(PrismappIoWebhook::header_status_code($status_code));
        echo $json;
        die();
    }

    private static function header_status_code($status_code) {
        switch ($status_code) {
            case 201:
                return 'HTTP/1.1 201 Created';

            case 400:
                return 'HTTP/1.1 400 Bad Request';

            case 401:
                return 'HTTP/1.1 401 Unauthorized';

            case 403:
                return 'HTTP/1.1 403 Forbidden';

            case 404:
                return 'HTTP/1.1 404 Not Found';

            case 406:
                return 'HTTP/1.1 406 Not Acceptable';

            case 500:
                return 'HTTP/1.1 500 Internal Server Error';

            case 501:
                return 'HTTP/1.1 501 Not Implemented';

            case 502:
                return 'HTTP/1.1 502 Bad Gateway';

            case 503:
                return 'HTTP/1.1 503 Service Unavailable';

            case 504:
                return 'HTTP/1.1 504 Gateway Timeout';

            case 200:
            default:
                return 'HTTP/1.1 200 OK';
        };
    }
}
