<?php
/*
 * Plugin Name: Switchere.com Crypto Gateway
 * Description: Take crypto payments on your store.
 * Version: 1.0.0
 * Author: Switchere
 * Author URI: https://switchere.com
*/


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define base file
if ( ! defined( 'WC_SWITCHERE_PLUGIN_FILE' ) ) {
    define( 'WC_SWITCHERE_PLUGIN_FILE', __FILE__ );
}

/**
 * WooCommerce missing fallback notice.
 *
 * @return string
 */
function wc_switchere_missing_wc_notice() {
    /* translators: 1. URL link. */
    echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Switchere requires WooCommerce to be installed and active. You can download %s here.', 'switchere-com-crypto-gateway' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce version fallback notice.
 *
 * @return string
 */
function wc_switchere_version_wc_notice() {
    echo '<div class="error"><p><strong>' . esc_html__( 'Switchere requires mimumum WooCommerce 3.0. Please upgrade.', 'switchere-com-crypto-gateway' ) . '</strong></p></div>';
}

/**
 * Intialize everything after plugins_loaded action
 */
add_action( 'plugins_loaded', 'wc_switchere_init', 5 );
function wc_switchere_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wc_switchere_missing_wc_notice' );
        return;
    }

    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
        add_action( 'admin_notices', 'wc_switchere_version_wc_notice' );
        return;
    }

    // Load the main plug class
    if ( ! class_exists( 'WC_Switchere' ) ) {
        require dirname( __FILE__ ) . '/includes/class-wc-switchere.php';
    }

    wc_switchere();
}

/**
 * Plugin instance
 *
 * @return WC_Switchere Main class instance.
 */
function wc_switchere() {
    return WC_Switchere::get_instance();
}