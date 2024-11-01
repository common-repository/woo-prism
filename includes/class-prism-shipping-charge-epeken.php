<?php

if (!defined('ABSPATH')) {
    exit;
}

class PrismEpeken {

    // use to handle epeken's plugin

    public static function is_active() {
        $plugin = 'epeken-all-kurir/epeken_courier.php';
        $is_active = in_array($plugin, (array) get_option('active_plugins',array()));
        if($is_active){
            $is_enabled = get_option('woocommerce_epeken_courier_settings');
            if($is_enabled['enabled'] == 'yes')
                return true;
            else
                return false;
        }
        else
            return false;
    }

    public static function calculate($city, $district){
        global $woocommerce;

        $shipping_method_available = array();
        $path = ABSPATH . PLUGINDIR .'/epeken-all-kurir/class/shipping.php';
        if (file_exists($path)) {
            require_once( ABSPATH . PLUGINDIR .'/epeken-all-kurir/class/shipping.php' );
            $epeken = new WC_Shipping_Tikijne();

            // set destination to inject woo checkout flow and collect shipping cost data
            $_POST['billing_city'] = $city;
            $_POST['billing_address_2'] = $district;

            $packages = $woocommerce->cart->get_shipping_packages();
            $epeken->calculate_shipping($packages[0]);
            $shipping_method_available = $epeken->rates;
        }
        return $shipping_method_available;
    }

}
