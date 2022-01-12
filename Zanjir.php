<?php
require_once 'define.php';

/*
Plugin Name: Zanjir Cryptocurrency Payment Gateway for WooCommerce
Plugin URI: https://Zanjir.network/
Description: Accept cryptocurrency on your website.Forget all about lengthy sign-ups, applying for merchant accounts and being restricted to one cryptocurrency.
Version: 1.0.0
Author: Zanjir Network
Author URI: https://zanjir.network/
License: GPLv2 or later
Text Domain: zanjir
*/


function zanjir_missing_wc_notice() {
    echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Zanjir requires WooCommerce to be installed and active. You can download %s here.', 'Zanjir' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

function zanjir_include_gateway($methods) {
    $methods[] = 'WC_Zanjir_Gateway';
    return $methods;
}
function zanjir_loader() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'zanjir_missing_wc_notice' );
        return;
    }
    require_once 'function.php';
    require_once 'zanjir.class.php';
    $zanjir = new WC_Zanjir_Gateway();
}

add_action('plugins_loaded', 'zanjir_loader');
add_filter('woocommerce_payment_gateways', 'zanjir_include_gateway');
?>