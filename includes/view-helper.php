<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize integration settings form fields.
 */
function init_form_fields($webhook_namespace, $webhooks) {
    $url = construct_http_root_url();

	$fields = array(
        'merchant_id' => array(
            'title'             => __( 'Merchant ID', 'woocommerce-prismappio' ),
            'type'              => 'text',
            'description'       => __( 'Enter with your Merchant ID.', 'woocommerce-prismappio' ),
            'desc_tip'          => TRUE,
            'default'           => ''
        ),
        'webhook_token' => array(
            'title'             => __( 'Webhook Token', 'woocommerce-prismappio' ),
            'type'              => 'text',
            'description'       => __( 'Enter with your Webhook Token.', 'woocommerce-prismappio' ),
            'desc_tip'          => TRUE,
            'default'           => get_access_token_for_webhook()
        ),
        'endpoint_chat' => array(
            'title'             => __( 'Endpoint Chat Widget', 'woocommerce-prismappio' ),
            'type'              => 'text',
            'description'       => __( 'Prism Endpoint Chat Widget.', 'woocommerce-prismappio' ),
            'desc_tip'          => TRUE,
            'default'           => 'https://prismapp-files.s3.amazonaws.com/widget/prism.js'
        ),
        'order_status' => array(
            'title'             => __( 'New Order Status', 'woocommerce-prismappio' ),
            'type'              => 'select',
            'default'           => 'pending',
            'description'       => __( 'Select order status when a new order has been created', 'woocommerce-prismappio' ),
            'options'           => array(
                'pending'             => __( 'Pending Payment', 'woocommerce-prismappio' ),
                'onhold'              => __( 'On Hold - stock will be reduced', 'woocommerce-prismappio' ),
            )
        ),
        'show_description' => array(
            'title'             => __( 'Product Description', 'woocommerce-prismappio' ),
            'type'              => 'select',
            'default'           => 'short',
            'description'       => __( 'Select product description to show on chat widget', 'woocommerce-prismappio' ),
            'options'           => array(
                'full'             => __( 'Full description', 'woocommerce-prismappio' ),
                'short'            => __( 'Short description', 'woocommerce-prismappio' ),
                'hide'             => __( 'Hide description', 'woocommerce-prismappio' ),
            )
        ),
        'product_search' => array(
            'title'             => __( 'Search Product On', 'woocommerce-prismappio' ),
            'type'              => 'select',
            'default'           => 'title',
            'description'       => __( 'Select how plugin doing search on your product', 'woocommerce-prismappio' ),
            'options'           => array(
                'title'         => __( 'Title', 'woocommerce-prismappio' ),
                'title_desc'    => __( 'Title Or Description', 'woocommerce-prismappio' )
            )
        )
    );

    return $fields;
}

/**
 * Returns the webhook route of a webhook controller
 */
function get_webhook_route($controller, $webhooks) {
    foreach($webhooks as $webhook) {
        if($webhook['controller'] === $controller)
            return $webhook['route'];
    }
}

/**
 * Generate a secure random number as an access token
 */
function get_access_token_for_webhook() {
    return bin2hex(openssl_random_pseudo_bytes(16));
}

/**
 * Construct a valid root HTTP URL of this WooCommerce installation
 */
function construct_http_root_url() {
    $url = isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $url .= $_SERVER['HTTP_HOST'];
    
    return $url;
}
