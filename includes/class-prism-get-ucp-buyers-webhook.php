<?php

if (!defined('ABSPATH')) {
    exit;
}

class PrismUcpBuyersWebhook extends PrismappIoWebhook implements iPrismappIoWebhook {

    const COUNTRY_CODE = '{"BD": "BGD", "BE": "BEL", "BF": "BFA", "BG": "BGR", "BA": "BIH", "BB": "BRB", "WF": "WLF", "BL": "BLM", "BM": "BMU", "BN": "BRN", "BO": "BOL", "BH": "BHR", "BI": "BDI", "BJ": "BEN", "BT": "BTN", "JM": "JAM", "BV": "BVT", "BW": "BWA", "WS": "WSM", "BQ": "BES", "BR": "BRA", "BS": "BHS", "JE": "JEY", "BY": "BLR", "BZ": "BLZ", "RU": "RUS", "RW": "RWA", "RS": "SRB", "TL": "TLS", "RE": "REU", "TM": "TKM", "TJ": "TJK", "RO": "ROU", "TK": "TKL", "GW": "GNB", "GU": "GUM", "GT": "GTM", "GS": "SGS", "GR": "GRC", "GQ": "GNQ", "GP": "GLP", "JP": "JPN", "GY": "GUY", "GG": "GGY", "GF": "GUF", "GE": "GEO", "GD": "GRD", "GB": "GBR", "GA": "GAB", "SV": "SLV", "GN": "GIN", "GM": "GMB", "GL": "GRL", "GI": "GIB", "GH": "GHA", "OM": "OMN", "TN": "TUN", "JO": "JOR", "HR": "HRV", "HT": "HTI", "HU": "HUN", "HK": "HKG", "HN": "HND", "HM": "HMD", "VE": "VEN", "PR": "PRI", "PS": "PSE", "PW": "PLW", "PT": "PRT", "SJ": "SJM", "PY": "PRY", "IQ": "IRQ", "PA": "PAN", "PF": "PYF", "PG": "PNG", "PE": "PER", "PK": "PAK", "PH": "PHL", "PN": "PCN", "PL": "POL", "PM": "SPM", "ZM": "ZMB", "EH": "ESH", "EE": "EST", "EG": "EGY", "ZA": "ZAF", "EC": "ECU", "IT": "ITA", "VN": "VNM", "SB": "SLB", "ET": "ETH", "SO": "SOM", "ZW": "ZWE", "SA": "SAU", "ES": "ESP", "ER": "ERI", "ME": "MNE", "MD": "MDA", "MG": "MDG", "MF": "MAF", "MA": "MAR", "MC": "MCO", "UZ": "UZB", "MM": "MMR", "ML": "MLI", "MO": "MAC", "MN": "MNG", "MH": "MHL", "MK": "MKD", "MU": "MUS", "MT": "MLT", "MW": "MWI", "MV": "MDV", "MQ": "MTQ", "MP": "MNP", "MS": "MSR", "MR": "MRT", "IM": "IMN", "UG": "UGA", "TZ": "TZA", "MY": "MYS", "MX": "MEX", "IL": "ISR", "FR": "FRA", "IO": "IOT", "SH": "SHN", "FI": "FIN", "FJ": "FJI", "FK": "FLK", "FM": "FSM", "FO": "FRO", "NI": "NIC", "NL": "NLD", "NO": "NOR", "NA": "NAM", "VU": "VUT", "NC": "NCL", "NE": "NER", "NF": "NFK", "NG": "NGA", "NZ": "NZL", "NP": "NPL", "NR": "NRU", "NU": "NIU", "CK": "COK", "XK": "XKX", "CI": "CIV", "CH": "CHE", "CO": "COL", "CN": "CHN", "CM": "CMR", "CL": "CHL", "CC": "CCK", "CA": "CAN", "CG": "COG", "CF": "CAF", "CD": "COD", "CZ": "CZE", "CY": "CYP", "CX": "CXR", "CR": "CRI", "CW": "CUW", "CV": "CPV", "CU": "CUB", "SZ": "SWZ", "SY": "SYR", "SX": "SXM", "KG": "KGZ", "KE": "KEN", "SS": "SSD", "SR": "SUR", "KI": "KIR", "KH": "KHM", "KN": "KNA", "KM": "COM", "ST": "STP", "SK": "SVK", "KR": "KOR", "SI": "SVN", "KP": "PRK", "KW": "KWT", "SN": "SEN", "SM": "SMR", "SL": "SLE", "SC": "SYC", "KZ": "KAZ", "KY": "CYM", "SG": "SGP", "SE": "SWE", "SD": "SDN", "DO": "DOM", "DM": "DMA", "DJ": "DJI", "DK": "DNK", "VG": "VGB", "DE": "DEU", "YE": "YEM", "DZ": "DZA", "US": "USA", "UY": "URY", "YT": "MYT", "UM": "UMI", "LB": "LBN", "LC": "LCA", "LA": "LAO", "TV": "TUV", "TW": "TWN", "TT": "TTO", "TR": "TUR", "LK": "LKA", "LI": "LIE", "LV": "LVA", "TO": "TON", "LT": "LTU", "LU": "LUX", "LR": "LBR", "LS": "LSO", "TH": "THA", "TF": "ATF", "TG": "TGO", "TD": "TCD", "TC": "TCA", "LY": "LBY", "VA": "VAT", "VC": "VCT", "AE": "ARE", "AD": "AND", "AG": "ATG", "AF": "AFG", "AI": "AIA", "VI": "VIR", "IS": "ISL", "IR": "IRN", "AM": "ARM", "AL": "ALB", "AO": "AGO", "AQ": "ATA", "AS": "ASM", "AR": "ARG", "AU": "AUS", "AT": "AUT", "AW": "ABW", "IN": "IND", "AX": "ALA", "AZ": "AZE", "IE": "IRL", "ID": "IDN", "UA": "UKR", "QA": "QAT", "MZ": "MOZ"}';
    
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
        global $woocommerce;

        if ( version_compare( $woocommerce->version, '2.2', "<" ) ) {
            PrismappIoWebhook::response_woo_need_upgrade();
        }

        $request_params = $this->request_data->get_params();
        $customer_id = $this->request_data->get_param('customer_id');
        $user_id = $this->get_user_id($customer_id);

        if ( version_compare( $woocommerce->version, '3.0', "<" ) ) {
            $buyer = $this->get_buyer_v2($user_id);
        } else {
            $buyer = $this->get_buyer_v3($user_id);
        }

        $response['buyer_info'] = $buyer;

        $this->response_data = array(
            'status'    =>  'success',
            'data'      =>  $response
        );
    }

    /**
     * Get buyer for Woo between 2.4.x and 2.6.x
     * ---
     */
     private function get_buyer_v2($customer_id){
        $userdata = get_userdata($customer_id);
        $usermeta = get_user_meta($customer_id);
        
        // get post return in descending date order
        $customer_orders = get_posts(
            array(
                'meta_key'    => '_customer_user',
                'meta_value'  => $customer_id,
                'post_type'   => wc_get_order_types(),
                'post_status' => array_keys( wc_get_order_statuses() ),
                'posts_per_page' => 1,
                'offset'=> 0,
            )
        );

        $last_order = $customer_orders[0] ? new WC_Order($customer_orders[0]->ID) : null ;
        $last_oder_meta = $customer_orders[0] ? get_post_meta($customer_orders[0]->ID) : null ;

        $country_code_mapper = json_decode(self::COUNTRY_CODE);
        
        $billing_address = $last_oder_meta ? array(
            'address_type'  => 'billing',
            'address'       => $last_oder_meta['_billing_address_1'][0] ?: '',
            'city'          => $last_oder_meta['_billing_city'][0] ? $last_oder_meta['_billing_city'][0] : '',
            'country_code'  => $last_oder_meta['_billing_country'][0] ? (string)$country_code_mapper->{$last_oder_meta['_billing_country'][0]} : '',
            'locality'      => $last_oder_meta['_billing_address_2'][0] ? $last_oder_meta['_billing_address_2'][0] : '',
            'state'         => $last_oder_meta['_billing_state'][0] ? $last_oder_meta['_billing_state'][0] : '',
            'zip_code'      => $last_oder_meta['_billing_postcode'][0] ? $last_oder_meta['_billing_postcode'][0] : ''
        ) : array(
            'address_type'  => 'billing',
            'address'       => $usermeta['billing_address_1'][0] ? $usermeta['billing_address_1'][0] : '',
            'city'          => $usermeta['billing_city'][0] ? $usermeta['billing_city'][0] : '',
            'country_code'  => $usermeta['billing_country'][0] ? (string)$country_code_mapper->{$usermeta['billing_country'][0]} : '',
            'locality'      => $usermeta['billing_address_2'][0] ? $usermeta['billing_address_2'][0] : '',
            'state'         => $usermeta['billing_state'][0] ? $usermeta['billing_state'][0] : '',
            'zip_code'      => $usermeta['billing_postcode'][0] ? $usermeta['billing_postcode'][0] : ''
        );

        $shipping_address = $last_oder_meta ? array(
            'address_type'  => 'shipping',
            'address'       => $last_oder_meta['_shipping_address_1'][0] ?: '',
            'city'          => $last_oder_meta['_shipping_city'][0] ? $last_oder_meta['_shipping_city'][0] : '',
            'country_code'  => $last_oder_meta['_shipping_country'][0] ? (string)$country_code_mapper->{$last_oder_meta['_shipping_country'][0]} : '',
            'locality'      => $last_oder_meta['_shipping_address_2'][0] ? $last_oder_meta['_shipping_address_2'][0] : '',
            'state'         => $last_oder_meta['_shipping_state'][0] ? $last_oder_meta['_shipping_state'][0] : '',
            'zip_code'      => $last_oder_meta['_shipping_postcode'][0] ? $last_oder_meta['_shipping_postcode'][0] : ''
        ) : array(
            'address_type'  => 'shipping',
            'address'       => $usermeta['shipping_address_1'][0] ? $usermeta['shipping_address_1'][0] : '',
            'city'          => $usermeta['shipping_city'][0] ? $usermeta['shipping_city'][0] : '',
            'country_code'  => $usermeta['shipping_country'][0] ? (string)$country_code_mapper->{$usermeta['shipping_country'][0]} : '',
            'locality'      => $usermeta['shipping_address_2'][0] ? $usermeta['shipping_address_2'][0] : '',
            'state'         => $usermeta['shipping_state'][0] ? $usermeta['shipping_state'][0] : '',
            'zip_code'      => $usermeta['shipping_postcode'][0] ? $usermeta['shipping_postcode'][0] : ''
        );

        return array(
            'addresses'  => array(
                $billing_address,
                $shipping_address
            ),
            'email'      => $userdata->user_email,
            'fullname'   => (string)($userdata->first_name ?: ''). ' ' .(string)($userdata->last_name ?: ''),
            'gender'     => '',
            'member_since' => str_replace(" ", "T" , date("Y-m-d H:i:s.000",strtotime($userdata->user_registered))."Z"),
            'metadata'   => array(
            ),
            'phone'      => $userdata->billing_phone ?: ''
        );

        PrismappIoWebhook::response_with_not_found('Customer not found');
    }

    /**
     * Get buyer for Woo > 3.0.x
     * ---
     */
    private function get_buyer_v3($customer_id){
        $userdata = get_userdata($customer_id);
        $usermeta = get_user_meta($customer_id);
        $wc_customer = new WC_Customer($customer_id);
        $last_order = $wc_customer->get_last_order() ?: null;
        $country_code_mapper = json_decode(self::COUNTRY_CODE);
        return array(
            'addresses'  => array(
                array(
                    'address_type'  => 'billing',
                    'address'       => $last_order ? $last_order->get_billing_address_1() : '',
                    'city'          => $last_order ? $last_order->get_billing_city() : '',
                    'country_code'  => $last_order ? (string)$country_code_mapper->{$last_order->get_billing_country()} : '',
                    'locality'      => $last_order ? $last_order->get_billing_address_2() : '',
                    'state'         => $last_order ? $last_order->get_billing_state() : '',
                    'zip_code'      => $last_order ? $last_order->get_billing_postcode() : ''
                ),
                array(
                    'address_type'  => 'shipping',
                    'address'       => $last_order ? $last_order->get_shipping_address_1() : '',
                    'city'          => $last_order ? $last_order->get_shipping_city() : '',
                    'country_code'  => $last_order ? (string)$country_code_mapper->{$last_order->get_shipping_country()} : '',
                    'locality'      => $last_order ? $last_order->get_shipping_address_2() : '',
                    'state'         => $last_order ? $last_order->get_shipping_state() : '',
                    'zip_code'      => $last_order ? $last_order->get_shipping_postcode() : ''
                )
            ),
            'age'        => '',
            'birth_date' => '',
            'email'      => $userdata->user_email,
            'fullname'   => $userdata->first_name ?: '' . ' ' . $userdata->last_name ?: '',
            'gender'     => '',
            'member_since' => str_replace(" ", "T" , date("Y-m-d H:i:s.000",strtotime($userdata->user_registered))."Z"),
            'metadata'   => array(
                array(
                    'paying_customer'  => $wc_customer->get_is_paying_customer() ? 'Yes' : 'No'
                ),
                array(
                    'order_count'  => (string)$wc_customer->get_order_count()
                ),
                array(
                    'total_spent'     => (string)$wc_customer->get_total_spent()
                ),
                array(
                    'last_updated'  => str_replace(" ", "T" , date("Y-m-d H:i:s.000",strtotime($wc_customer->get_date_modified()))."Z"),
                )
            ),
            'phone'      => $userdata->billing_phone ?: ''
        );

        PrismappIoWebhook::response_with_not_found('Customer not found');
    }

    /**
     * Get user ID from id or email
     * ---
     */
    private function get_user_id($customer_id) {
        $user = get_user_by('email', $customer_id);
        if (empty($user))
            $user = get_user_by('id', $customer_id );
        if (!empty($user))
            return $user->ID;
        
        PrismappIoWebhook::response_with_not_found('Customer not found');
    }

    /**
     * Validate requests
     * ---
     */
    public function validate_request() {
        // fix php 5.4 for empty result Can't use method return value in write context
        $customer_id = $this->request_data->get_param('customer_id');
        if(empty($customer_id))
            PrismappIoWebhook::response_with_bad_request('No customer_id parameter provided');
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