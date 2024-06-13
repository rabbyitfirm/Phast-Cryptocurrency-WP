<?php
require_once 'define.php';

/*
Plugin Name: Phast Cryptocurrency Payment Gateway for WooCommerce
Plugin URI: https://Phast.dev/
Description: Accept cryptocurrency on your website.Forget all about lengthy sign-ups, applying for merchant accounts and being restricted to one cryptocurrency.
Version: 1.0.3
Author: Phast Network
Author URI: https://Phast.Dev/
License: GPLv2 or later
Text Domain: phast
*/


function phast_missing_wc_notice() {
    echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Phast requires WooCommerce to be installed and active. You can download %s here.', 'Phast' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

function phast_include_gateway($methods) {
    $methods[] = 'WC_Phast_Gateway';
    return $methods;
}
function phast_loader() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'phast_missing_wc_notice' );
        return;
    }
    require 'vendor/autoload.php';
    require_once 'function.php';

    $phast_class = new Phast\Phast();
    $phast = new WC_Phast_Gateway();

}

add_action('plugins_loaded', 'phast_loader');
add_filter('woocommerce_payment_gateways', 'phast_include_gateway');
?>
