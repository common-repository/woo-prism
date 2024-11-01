<?php

if (!defined('ABSPATH')) {
    exit;
}

class PrismTonjoo {

    // use to handle tonjoo's plugin

    public static function is_active() {
        $plugin = 'plugin-ongkos-kirim/plugin-ongkos-kirim.php';
        $is_active = in_array($plugin, (array) get_option('active_plugins',array()));
        if($is_active){
            $helpers = new \PluginOngkosKirim\Helpers\Helpers();
            if($helpers->is_plugin_active())
                return true;
            else
                return false;
        }
        else
            return false;
    }

    public static function calculate($province, $city, $district){
        global $woocommerce;

        $shipping_method_available = array();
        $path = ABSPATH . PLUGINDIR .'/plugin-ongkos-kirim/plugin-ongkos-kirim.php';
        if (file_exists($path)) {
            require_once( ABSPATH . PLUGINDIR .'/plugin-ongkos-kirim/plugin-ongkos-kirim.php' );
            plugin_ongkos_kirim_init();
            $tonjoo = new Plugin_Ongkos_Kirim();
            $packages = $woocommerce->cart->get_shipping_packages();
            
            $packages[0]['destination']['country'] = 'ID';
            $packages[0]['destination']['state'] = $province;
            $packages[0]['destination']['city'] = $city;

            $_POST['billing_district'] = $district;

            $tonjoo->calculate_shipping($packages[0]);
            $shipping_method_available = $tonjoo->rates;
        }
        return $shipping_method_available;
    }

}
