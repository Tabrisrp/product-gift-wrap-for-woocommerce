<?php
/**
 * Plugin Name: Product Gift Wrap for WooCommerce
 * Plugin URI: https://github.com/Tabrisrp/product-gift-wrap-for-woocommerce
 * Description: Add an option to your products to enable gift wrapping. Optionally charge a fee.
 * Version: 1.3.1
 * Author: Rémy Perona
 * Author URI: https://remyperona.fr
 * Requires at least: 3.5
 * Tested up to: 4.8
 * Text Domain: product-gift-wrap-for-woocommerce
 * Domain Path: /languages/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Original Author: Mike Jolley
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Product_Gift_Wrap' ) ) :
	define( 'WC_PRODUCT_GIFT_WRAP_PATH', realpath( plugin_dir_path( __FILE__ ) ) );

	require( WC_PRODUCT_GIFT_WRAP_PATH . '/classes/class-wc-product-gift-wrap.php' );

	register_activation_hook( __FILE__, array( 'WC_Product_Gift_Wrap', 'install' ) );
	add_action( 'plugins_loaded', array( 'WC_Product_Gift_Wrap', 'init' ) );

endif;
