<?php
/**
 * Plugin Name: WooCommerce Prism
 * Plugin URI: https://wordpress.org/plugins/woo-prism
 * Description: A plugin to integrate Prism with WooCommerce
 * Version: 0.2.8.10
 * Author: Prismapp.io
 * Author URI: https://www.prismapp.io
 * Text Domain: woocommerce-extension
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once(ABSPATH . 'wp-admin/includes/plugin.php');

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists( 'WC_Prismappio')) {

        class WC_Prismappio {

            const VERSION = '0.2.8.10';
            
            /**
             * Construct the plugin.
             */
            public function __construct() {
                add_action('plugins_loaded', array($this, 'init'));
                add_action('woocommerce_api_loaded', array($this, 'load'));
                add_action('admin_notices', 'need_credential');
            }

            public function load() {
                include_once 'wc-api/prism-ping.php';
                include_once 'wc-api/prism-area.php';
                include_once 'wc-api/prism-search.php';
                include_once 'wc-api/prism-shipping-charges.php';
                include_once 'wc-api/prism-create-invoice.php';
                include_once 'wc-api/prism-ucp-buyers.php';
                include_once 'wc-api/prism-ucp-items.php';
                include_once 'wc-api/prism-ucp-transactions.php';
                add_filter('woocommerce_api_classes', array($this, 'wc_api_register'));
            }

            public function wc_api_register($api_classes = array()) {
                $new_api = array(
                    'Prism_Ping',
                    'Prism_Area',
                    'Prism_Search', 
                    'Prism_ShippingCharges', 
                    'Prism_Invoice',
                    'Prism_UcpBuyers',
                    'Prism_UcpItems',
                    'Prism_UcpTransactions'
                );
                $api_classes = array_merge($api_classes, $new_api);
                return $api_classes;
            }

            /**
             * Initialize the plugin.
             */
            public function init() {
                // Checks if WooCommerce is installed.
                if (class_exists('WC_Integration')) {
                    // Bootstrap the plugin includes
                    include_once 'includes/interface-prism-webhook.php';
                    include_once 'includes/class-prism-webhook.php';
                    include_once 'includes/class-prism-ping-webhook.php';
                    include_once 'includes/class-prism-search-webhook.php';
                    include_once 'includes/class-prism-search-area-webhook.php';
                    include_once 'includes/class-prism-get-product-webhook.php';
                    include_once 'includes/class-prism-shipping-charges-webhook.php';
                    include_once 'includes/class-prism-create-invoice-webhook.php';
                    
                    //UCP features
                    include_once 'includes/class-prism-get-ucp-buyers-webhook.php';
                    include_once 'includes/class-prism-get-ucp-transactions-webhook.php';
                    include_once 'includes/class-prism-get-ucp-items-webhook.php';

                    // connect to other plugins
                    include_once 'includes/class-prism-shipping-charge-epeken.php';
                    include_once 'includes/class-prism-shipping-charge-agenwebsite.php';
                    include_once 'includes/class-prism-shipping-charge-tonjoo.php';

                    // Include our integration class.
                    include_once 'includes/class-wc-integration-prism.php';

                    // Register the integration.
                    add_filter('woocommerce_integrations', array($this, 'add_integration'));

                    // add setting link
                    $plugin = plugin_basename( __FILE__ );
                    add_filter("plugin_action_links_{$plugin}", array($this, 'plugin_settings_link'));

                } else {
                    // throw an admin error if you like
                }
            }

            /**
             * Add a new integration to WooCommerce.
             */
            public function add_integration($integrations) {
                $integrations[] = 'WC_Integration_Prismappio';
                return $integrations;
            }

            public function plugin_settings_link($links) {
                $settings_link = array(
                    'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=integration&section=prismappio' ) . '" title="View Prismappio Settings">Settings</a>'
                );
                return array_merge( $settings_link, $links );
            }
        }

        $WC_Prismappio = new WC_Prismappio();
    }
}
else{
    deactivate_plugins(plugin_basename(__FILE__));
    add_action('admin_notices', 'need_woocommerce');
}

function need_woocommerce(){
    ?>
    <div class="notice notice-error">
        <p><b>Woocommerce</b> not active, so <b>PRISM</b> plugin has been deactivated</p>
    </div>
    <?php
}

function need_credential(){
    $prismappio_notice = get_option('woocommerce_prismappio_notice');
    if(!$prismappio_notice) {
    $setting = admin_url('admin.php?page=wc-settings&tab=integration&section=prismappio');
    ?>
    <div class="notice notice-info">
        <h4>Congratulations! you are one step closer to integrating Prism to your site</h4>
        <ol>
            <li>
                Please login to <a target="_blank" href="https://dashboard.prismapp.io" style="text-decoration: none;">Prism Dashboard</a> and get your MerchantID on Settings > General > Show advanced information > MerchantID
            </li>
            <li>
                Update your MerchantID on Woocommerce > Settings > Integration > Prismapp.Io
            </li>
        </ol>
    </div>
    <?php 
    update_option('woocommerce_prismappio_notice', 1);
    }
}
