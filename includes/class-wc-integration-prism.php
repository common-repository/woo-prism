<?php
/**
 * Integration.
 *
 * @package  WC_Integration_Prismappio
 * @category Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

// View Helper
require_once 'view-helper.php';

if ( ! class_exists( 'WC_Integration_Prismappio' ) ) :

    class WC_Integration_Prismappio extends WC_Integration {

        private $webhooks = array(
            array(
                'method'        =>  'GET',
                'controller'    =>  'PrismPingWebhook',
                'route'         =>  '/ping'
            ),
            array(
                'method'        =>  'GET',
                'controller'    =>  'PrismGetProductWebhook',
                'route'         =>  '/product/(?<id>\d+)'
            ),
            array(
                'method'        =>  'POST',
                'controller'    =>  'PrismSearchWebhook',
                'route'         =>  '/search'
            ),
            array(
                'method'        =>  'POST',
                'controller'    =>  'PrismShippingChargesWebhook',
                'route'         =>  '/shipping-charge'
            ),
            array(
                'method'        =>  'POST',
                'controller'    =>  'PrismCreateInvoiceWebhook',
                'route'         =>  '/create-invoice'
            ),
            array(
                'method'        => 'GET',
                'controller'    =>  'PrismUcpBuyersWebhook',
                'route'         =>  '/ucp-buyers'
            ),
            array(
                'method'        => 'GET',
                'controller'    =>  'PrismUcpTransactionsWebhook',
                'route'         =>  '/ucp-transactions'
            ),
            array(
                'method'        => 'GET',
                'controller'    =>  'PrismUcpItemsWebhook',
                'route'         =>  '/ucp-items'
            ),
            array(
                'method'        =>  'GET',
                'controller'    =>  'PrismSearchAreaWebhook',
                'route'         =>  '/area'
            ),
        );

        /**
         * Init and hook in the integration.
         */
        public function __construct() {
            $this->id                 = 'prismappio';
            $this->method_title       = __('Prismapp.Io', 'woocommerce-prismappio');
            $this->method_description = __('Settings page to enable Prism for WooCommerce', 'woocommerce-prismappio');
            $this->webhook_namespace  = 'prismappio';

            // Load the settings.
            $this->init_form_fields();

            // Define user set variables.
            $this->merchant_id          = $this->get_option('merchant_id');
            $this->endpoint_chat         = $this->get_option('endpoint_chat');
            $this->webhook_token        = $this->get_option('webhook_token');

            // Integration Tab on Woocommerce Settings
            add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));

            // register webhooks
            add_action('rest_api_init', array($this, 'register_webhooks'));

            // Load chat widget
            add_action('wp_footer', array($this, 'load_prismappio_js'));

            // check requirement
            add_action('admin_notices', array($this, 'check_requirement'));

            add_action('woocommerce_shop_order_search_fields', array($this, 'search_order_by_agent'));
        }

        function search_order_by_agent($search_fields){
            $search_fields[] = 'agent_name';
            $search_fields[] = 'agent_email';
            return $search_fields;
        }

        function check_requirement(){
            global $woocommerce;

            $current_tab     = empty( $_GET['tab'] ) ? 'general' : sanitize_title( $_GET['tab'] );
            $current_section = empty( $_REQUEST['section'] ) ? 'prismappio' : sanitize_title( $_REQUEST['section'] );

            if($current_tab != 'integration' || $current_section != 'prismappio') return;

            // check woocommerce version
            if ( version_compare( $woocommerce->version, '2.2', "<" ) ) {
                WC_Admin_Settings::add_error('This plugin only support Woocommerce v2.2.x up to latest, please update it.');
            }
        }


        public static function get_product_id($wc_product){
            global $woocommerce;
            if ( version_compare( $woocommerce->version, '2.5', "<" ) ) {
                return $wc_product->post->ID;
            }
            else return $wc_product->get_id();
        }

        public static function get_product_desc($wc_product, $type = 'full'){
            global $woocommerce;
            if ( version_compare( $woocommerce->version, '3.0', "<" ) ) {
                return ($type == 'full') ? $wc_product->post->post_content : $wc_product->post->post_excerpt;
            }
            else return ($type == 'full') ? $wc_product->get_description() : $wc_product->get_short_description();
        }

        /**
         * Create Product Request Payload
         */
        public static function get_create_product_request_payload($wc_product, $variation = false) {
            $default_description = '';
            $config = get_option('woocommerce_prismappio_settings');
            if ($config['show_description'] == 'full')
                $default_description = self::get_product_desc($wc_product, 'full');
            elseif ($config['show_description'] == 'short')
                $default_description = self::get_product_desc($wc_product, 'short');

            $quantity            = (int) self::check_stock($wc_product, $variation);
            $default_images      = array(
                wp_get_attachment_image_src(get_post_thumbnail_id(self::get_product_id($wc_product)), 'full')[0]
            );

            $gallery_images = array();
            if(count($wc_product->get_gallery_attachment_ids()) > 0){
                foreach ($wc_product->get_gallery_attachment_ids() as $image_id) {
                    array_push($gallery_images, wp_get_attachment_url($image_id, 'full'));
                }
            }

            $default_images = array_merge($default_images, $gallery_images);

            if($variation){
                // set product name with attributes
                $arr_attrs = array();
                foreach ($wc_product->get_attributes() as $key => $val) {
                    if($val['is_variation'])
                        array_push($arr_attrs, get_post_meta($variation['variation_id'], 'attribute_'.$key, true));
                }
                $name = $wc_product->get_title().' - '.implode(' - ', $arr_attrs);

                $discount        = self::get_discount_product_variant($variation);
                $price           = (int) $variation['display_regular_price'];
                $description     = ($variation['variation_description'] != '') ? $variation['variation_description'] : $default_description;
                $sku             = (string) $variation['variation_id'];
                list($weight)    = explode(' ', $variation['weight']);
                $weight          = (int) wc_get_weight($weight, 'g');
                if(isset($variation['image']['src']) != '')
                    $image_urls  = array($variation['image']['src']);
                else
                    $image_urls  = $default_images;
            }
            else{
                $discount        = self::get_discount($wc_product);
                $name            = $wc_product->get_title();
                $price           = (int) $wc_product->get_regular_price();
                $description     = $default_description;
                $weight          = (int) wc_get_weight($wc_product->get_weight(), 'g');
                $sku             = (string) self::get_product_id($wc_product);
                $image_urls      = $default_images;
            }

            foreach($image_urls as $key => $value){
                if(is_null($value) || $value == '')
                    unset($image_urls[$key]);
            }

            $payload = array(
                'id' => (string) $sku,
                'payload' => array(
                    'name'              =>  $name,
                    'description'       =>  $description,
                    'price'             =>  $price,
                    'currency_code'     =>  'IDR',
                    'stock'             =>  $quantity,
                    'image_urls'        =>  $image_urls,
                    'weight'            =>  ($weight == 0) ? 1000 : $weight,
                    'sku'               =>  $sku,
                    'discount'          =>  array(
                        'discount_type'     =>  'NOMINAL',
                        'amount'            =>  $discount
                    )
                )
            );
            return $payload;
        }

        /**
         * Check stock availability
         * ---
         */
        public static function check_stock($wc_product, $variation = false) {
            if($variation){
                $stock_is_managed = ($variation['max_qty']) ? true : false;
                $is_in_stock = $variation['is_in_stock'];
            }
            else{
                $stock_is_managed = $wc_product->managing_stock();
                $is_in_stock = $wc_product->is_in_stock();
            }
            
            // Stock is NOT managed and item is in stock
            if(!$stock_is_managed && $is_in_stock)
                return 999;

            // Stock is NOT managed and item is NOT is stock
            if(!$stock_is_managed && !$is_in_stock)
                return 0;

            // Stock is managed and item is out of stock
            if($stock_is_managed && !$is_in_stock)
                return 0;

            // Stock is managed and item is in stock
            if($stock_is_managed && $is_in_stock)
                return ($variation) ? $variation['max_qty'] : $wc_product->get_stock_quantity();
        }

        /**
         * Validate sale price
         */
        static function get_discount($wc_product) {
            // fix php 5.4 for empty result Can't use method return value in write context
            $sale_price = $wc_product->get_sale_price();
            
            $product_id = self::get_product_id($wc_product);
            $sale_price_exists = !empty($sale_price);
            
            $sale_price_over_equal_zero = $wc_product->get_sale_price() >= 0;
            $sale_price_less_equal_regular_price = $wc_product->get_sale_price() <= $wc_product->get_regular_price();
            $sale_price_is_valid = $sale_price_exists && $sale_price_over_equal_zero && $sale_price_less_equal_regular_price;

            if(!$sale_price_is_valid) {
                update_post_meta($product_id, '_sale_price', NULL);
                update_post_meta($product_id, '_price', $wc_product->get_regular_price());

                return 0;
            }

            if(self::is_discount($product_id)){
                return $wc_product->get_regular_price() - $wc_product->get_sale_price();
            }
            else{
                return 0;
            }
        }

        /**
         * Validate sale price for product variant
         */
        static function get_discount_product_variant($variation) {
            $discount = 0;
            if(self::is_discount($variation['variation_id'])){
                if($variation['display_price'] < $variation['display_regular_price']){
                    $discount = $variation['display_regular_price'] - $variation['display_price'];
                }
            }
            return (int) $discount;
        }

        /**
         * is discount by date
         */
        public static function is_discount($product_id){
            // schedule
            $from = get_post_meta($product_id, '_sale_price_dates_from', true);
            $to = get_post_meta($product_id, '_sale_price_dates_to', true);
            $sale_price = get_post_meta($product_id, '_sale_price', true);
            $now  = strtotime(date('Y-m-d'));
            
            $is_discount = false;
            if(!empty($from) && !empty($to)){
                if($now >= $from && $now <= $to) $is_discount = true;
            }
            elseif(!empty($from)){
                if($now >= $from) $is_discount = true;
            }
            elseif(!empty($to)){
                if($now <= $to) $is_discount = true;
            }
            elseif($sale_price > 0){
                $is_discount = true;
            }

            return $is_discount;
        }

        /**
         * Register all webhooks
         */
        public function register_webhooks() {
            foreach($this->webhooks as $webhook) {
                $controller = new $webhook['controller']();
                register_rest_route($this->webhook_namespace, $webhook['route'], array(
                    'methods'   =>  $webhook['method'],
                    'callback'  =>  array($controller, 'run'),
                    // 'permission_callback' => array($this, 'auth_webhook')
                ));

                // TODO: check if the webhook controller instance implements iPrismappIoWebhook interface
            }
        }

        /**
         * Authorization all webhooks
         */
        public function auth_webhook($request) {
            $header = $request->get_headers();
            if(isset($header['x_prism_webhook_key']) && count($header['x_prism_webhook_key']) > 0){
                if($header['x_prism_webhook_key'][0] == $this->webhook_token) return true;
            }

            $response = array(
                'status'    => 'failed',
                'message'   => 'Unauthorized',
                'data'      => array()
            );
            PrismappIoWebhook::response_with_json(json_encode($response), 401);
        }

        /**
         * Initialize integration settings form fields.
         */
        public function init_form_fields() {
            $this->form_fields = init_form_fields($this->webhook_namespace, $this->webhooks);
        }

        public function load_prismappio_js() {
            if(empty($this->merchant_id)) return;
            ?>
                <script type="text/javascript">
                    (function(globals) {
                        var script = document.createElement('script');
                        var body = document.getElementsByTagName('body')[0].appendChild(script);
                        script.src = '<?php echo $this->get_option('endpoint_chat') ?>';
                        script.async = true;
                        script.onload = script.onreadystatechange = function() {
                            globals.Shamu = new Prism('<?php echo $this->get_option('merchant_id') ?>');
                            Shamu.display();
                        }
                    })(window);
                </script>
            <?php
        }

        /**
         * Generate HTML for our custom `textlabel` form type with a hidden input
         */
        public function generate_textlabel_html($key, $data) {
            $field    = $this->plugin_id . $this->id . '_' . $key;
            $defaults = array(
                'class'             => '',
                'css'               => '',
                'custom_attributes' => array(),
                'desc_tip'          => false,
                'description'       => '',
                'title'             => '',
            );
            $data = wp_parse_args($data, $defaults);
            
            $value = $this->get_option($key);
            if(empty($value))
                $value = $data['default'];

            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                    <?php echo $this->get_tooltip_html($data); ?>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                        <?php echo $value; ?>

                        <input type="hidden" name="<?php echo esc_attr($field); ?>" id="<?php echo esc_attr($field); ?>" value="<?php echo $value; ?>" />
                    </fieldset>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }

    }

endif;
