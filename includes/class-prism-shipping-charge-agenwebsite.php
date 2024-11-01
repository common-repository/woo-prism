<?php

if (!defined('ABSPATH')) {
    exit;
}

class PrismAgenwebsite {

    // use to handle agenwebsite's plugin

    private static $active_plugins = array();

    public static function is_active() {

        $plugins = array(
            array(
                'class' => 'WC_JNE',
                'plugin' => 'woocommerce-jne/woocommerce-jne.php',
                'setting' => 'woocommerce_jne_shipping_settings'
            ),
            array(
                'class' => 'WC_JNE',
                'plugin' => 'woocommerce-jne-exclusive/woocommerce-jne-exclusive.php',
                'setting' => 'woocommerce_jne_shipping_settings'
            ),
            array(
                'class' => 'WC_TIKI',
                'plugin' => 'woocommerce-tiki-exclusive/woocommerce-tiki-exclusive.php',
                'setting' => 'woocommerce_tiki_shipping_settings'
            ),
            array(
                'class' => 'WC_POS',
                'plugin' => 'woocommerce-pos-exclusive/woocommerce-pos-exclusive.php',
                'setting' => 'woocommerce_pos_shipping_settings'
            ),
            array(
                'class' => 'WC_POS_International',
                'plugin' => 'woocommerce-pos-international/woocommerce-pos-international.php',
                'setting' => 'woocommerce_pos_international_shipping_settings'
            )
        );

        foreach ($plugins as $value) {
            $is_active = in_array($value['plugin'], (array) get_option('active_plugins',array()));
            if($is_active){
                $is_enabled = get_option($value['setting']);
                if($is_enabled['enabled'] == 'yes'){
                    array_push(self::$active_plugins, $value);
                }
            }
        }

        if(count(self::$active_plugins) > 0)
            return true;
        else
            return false;
    }

    public static function calculate($state, $city, $postcode = null, $country = 'ID'){
        global $woocommerce;
        $shipping_methods_available = array();
        
        foreach (self::$active_plugins as $value) {
            list($name, $file) = explode('/', $value['plugin']);
            $path = ABSPATH . PLUGINDIR .'/'. $name .'/includes/shipping/shipping-method.php';
            if (file_exists($path)) {
                require_once( ABSPATH . PLUGINDIR .'/'. $name .'/includes/shipping/shipping-method.php' );
                $shipping_method = new $value['class'];

                // JNE, TIKI requires $state, $city
                // POS require $postcode
                // POS International require $country

                // set destination to inject woo checkout flow and collect shipping cost data
                WC()->customer->set_country($country);
                WC()->customer->set_shipping_country($country);
                WC()->customer->set_shipping_state($state);
                WC()->customer->set_shipping_city($city);
                WC()->customer->set_shipping_postcode($postcode);

                $packages = $woocommerce->cart->get_shipping_packages();

                if ( version_compare( $woocommerce->version, '2.6', "<" ) ) {
                    $shipping_method->calculate_shipping($packages[0]);
                    $shipping_methods_available = array_merge($shipping_methods_available, $shipping_method->rates);
                }
                else {
                    // method get_rates_for_package available on woo 2.6
                    $shipping_methods_available = array_merge($shipping_methods_available, $shipping_method->get_rates_for_package($packages[0]));
                }
            } 
        }
        return $shipping_methods_available;
    }
}
