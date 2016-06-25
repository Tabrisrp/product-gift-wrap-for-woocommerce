<?php
/*
Plugin Name: Product Gift Wrap for WooCommerce
Plugin URI: https://github.com/Tabrisrp/product-gift-wrap-for-woocommerce
Description: Add an option to your products to enable gift wrapping. Optionally charge a fee.
Version: 1.3
Author: Rémy Perona
Author URI: http://remyperona.fr
Requires at least: 3.5
Tested up to: 4.5
Text Domain: product-gift-wrap-for-woocommerce
Domain Path: /languages/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Original Author: Mike Jolley

*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WC_Product_Gift_wrap main class.
 */
if ( ! class_exists( 'WC_Product_Gift_Wrap' ) ) :

class WC_Product_Gift_Wrap {
    /**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.3';

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

    public $settings;
    public $gift_wrap_enabled;
    public $gift_wrap_cost;
    public $product_gift_wrap_message;
	/**
	 * Hook us in :)
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->gift_wrap_enabled         = get_option( 'product_gift_wrap_enabled' );
		$this->gift_wrap_cost            = get_option( 'product_gift_wrap_cost', 0 );
		$this->product_gift_wrap_message = get_option( 'product_gift_wrap_message' );

        // Load plugin text domain
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Display on the front end
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'gift_option_html' ), 10 );

		// Filters for cart actions
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 1 );
		add_action( 'woocommerce_add_order_item_meta', array( $this, 'add_order_item_meta' ), 10, 2 );

		// Write Panels
		add_action( 'woocommerce_product_options_pricing', array( $this, 'write_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'write_panel_save' ) );

		// Admin
		add_action( 'woocommerce_settings_general_options_end', array( $this, 'display_admin_settings' ) );
		add_action( 'woocommerce_update_options_general', array( $this, 'save_admin_settings' ) );
	}

    /**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

    public static function install() {
        add_option( 'product_gift_wrap_enabled', false );
		add_option( 'product_gift_wrap_cost', '0' );
		add_option( 'product_gift_wrap_message', sprintf( __( 'Gift wrap this item for %s?', 'product-gift-wrap-for-woocommerce' ), '{price}' ) );
    }

    /**
     * Load the plugin text domain for translation.
     */
    public function load_plugin_textdomain() {
        $locale = apply_filters( 'plugin_locale', get_locale(), 'product-gift-wrap-for-woocommerce' );

		load_textdomain( 'product-gift-wrap-for-woocommerce', trailingslashit( WP_LANG_DIR ) . 'product-gift-wrap-for-woocommerce/product-gift-wrap-for-woocommerce-' . $locale . '.mo' );
        load_plugin_textdomain( 'product-gift-wrap-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Basic integration with WooCommerce Currency Switcher, developed by Aelia
     * (http://aelia.co). This method can be used by any 3rd party plugin to
     * return prices converted to the active currency.
     *
     * @param double price The source price.
     * @param string to_currency The target currency. If empty, the active currency
     * will be taken.
     * @param string from_currency The source currency. If empty, WooCommerce base
     * currency will be taken.
     * @return double The price converted from source to destination currency.
     * @author Aelia <support@aelia.co>
     * @link http://aelia.co
     */
    protected function get_price_in_currency( $price, $to_currency = null, $from_currency = null ) {
      // If source currency is not specified, take the shop's base currency as a default
      if( empty( $from_currency ) ) {
        $from_currency = get_option( 'woocommerce_currency' );
      }
      // If target currency is not specified, take the active currency as a default.
      // The Currency Switcher sets this currency automatically, based on the context. Other
      // plugins can also override it, based on their own custom criteria, by implementing
      // a filter for the "woocommerce_currency" hook.
      //
      // For example, a subscription plugin may decide that the active currency is the one
      // taken from a previous subscription, because it's processing a renewal, and such
      // renewal should keep the original prices, in the original currency.
      if( empty( $to_currency ) ) {
        $to_currency = get_woocommerce_currency();
      }
    
      // Call the currency conversion filter. Using a filter allows for loose coupling. If the
      // Aelia Currency Switcher is not installed, the filter call will return the original
      // amount, without any conversion being performed. Your plugin won't even need to know if
      // the multi-currency plugin is installed or active
      return apply_filters( 'wc_aelia_cs_convert', $price, $from_currency, $to_currency );
    }

	/**
	 * Show the Gift Checkbox on the frontend
	 *
	 * @access public
	 * @return void
	 */
	public function gift_option_html() {
		global $post;

		$is_wrappable = get_post_meta( $post->ID, '_is_gift_wrappable', true );

		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		if ( $is_wrappable == 'yes' ) {

			$current_value = ! empty( $_REQUEST['gift_wrap'] ) ? 1 : 0;

			$cost = get_post_meta( $post->ID, '_gift_wrap_cost', true );

			if ( $cost == '' ) {
				$cost = $this->gift_wrap_cost;
			}

			$price_text = $cost > 0 ? wc_price( $this->get_price_in_currency( $cost ) ) : __( 'free', 'product-gift-wrap-for-woocommerce' );

			wc_get_template( 'gift-wrap.php', array(
				'product_gift_wrap_message' => $this->product_gift_wrap_message,
				'current_value'             => $current_value,
				'price_text'                => $price_text
			), 'product-gift-wrap-for-woocommerce', untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/' );
		}
	}

	/**
	 * When added to cart, save any gift data
	 *
	 * @access public
	 * @param mixed $cart_item_meta
	 * @param mixed $product_id
	 * @return void
	 */
	public function add_cart_item_data( $cart_item_meta, $product_id ) {
		$is_wrappable = get_post_meta( $product_id, '_is_gift_wrappable', true );

		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		if ( ! empty( $_POST['gift_wrap'] ) && $is_wrappable == 'yes' ) {
			$cart_item_meta['gift_wrap'] = true;
		}

		return $cart_item_meta;
	}

	/**
	 * Get the gift data from the session on page load
	 *
	 * @access public
	 * @param mixed $cart_item
	 * @param mixed $values
	 * @return void
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {

		if ( ! empty( $values['gift_wrap'] ) ) {
			$cart_item['gift_wrap'] = true;

			$cost = get_post_meta( $cart_item['data']->id, '_gift_wrap_cost', true );

			if ( $cost == '' ) {
				$cost = $this->gift_wrap_cost;
			}

			$cart_item['data']->adjust_price( $this->get_price_in_currency( $cost ) );
		}

		return $cart_item;
	}

	/**
	 * Display gift data if present in the cart
	 *
	 * @access public
	 * @param mixed $other_data
	 * @param mixed $cart_item
	 * @return void
	 */
	public function get_item_data( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['gift_wrap'] ) ) {
			$item_data[] = array(
				'name'    => __( 'Gift Wrapped', 'product-gift-wrap-for-woocommerce' ),
				'value'   => __( 'Yes', 'product-gift-wrap-for-woocommerce' ),
				'display' => __( 'Yes', 'product-gift-wrap-for-woocommerce' )
			);
        }

		return $item_data;
	}

	/**
	 * Adjust price after adding to cart
	 *
	 * @access public
	 * @param mixed $cart_item
	 * @return void
	 */
	public function add_cart_item( $cart_item ) {
		if ( ! empty( $cart_item['gift_wrap'] ) ) {

			$cost = get_post_meta( $cart_item['data']->id, '_gift_wrap_cost', true );

			if ( $cost == '' ) {
				$cost = $this->gift_wrap_cost;
			}

			$cart_item['data']->adjust_price( $this->get_price_in_currency( $cost ) );
		}

		return $cart_item;
	}

	/**
	 * After ordering, add the data to the order line items.
	 *
	 * @access public
	 * @param mixed $item_id
	 * @param mixed $values
	 * @return void
	 */
	public function add_order_item_meta( $item_id, $cart_item ) {
		if ( ! empty( $cart_item['gift_wrap'] ) ) {
			wc_add_order_item_meta( $item_id, __( 'Gift Wrapped', 'product-gift-wrap-for-woocommerce' ), __( 'Yes', 'product-gift-wrap-for-woocommerce' ) );
		}
	}

	/**
	 * write_panel function.
	 *
	 * @access public
	 * @return void
	 */
	public function write_panel() {
		global $post;

		echo '</div><div class="options_group show_if_simple show_if_variable">';

		$is_wrappable = get_post_meta( $post->ID, '_is_gift_wrappable', true );

		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		woocommerce_wp_checkbox( array(
				'id'            => '_is_gift_wrappable',
				'wrapper_class' => '',
				'value'         => $is_wrappable,
				'label'         => __( 'Gift Wrappable', 'product-gift-wrap-for-woocommerce' ),
				'description'   => __( 'Enable this option if the customer can choose gift wrapping.', 'product-gift-wrap-for-woocommerce' ),
			) );

		woocommerce_wp_text_input( array(
				'id'          => '_gift_wrap_cost',
				'label'       => __( 'Gift Wrap Cost', 'product-gift-wrap-for-woocommerce' ),
				'placeholder' => $this->gift_wrap_cost,
				'desc_tip'    => true,
				'description' => __( 'Override the default cost by inputting a cost here.', 'product-gift-wrap-for-woocommerce' ),
			) );

		wc_enqueue_js( "
			jQuery('input#_is_gift_wrappable').change(function(){

				jQuery('._gift_wrap_cost_field').hide();

				if ( jQuery('#_is_gift_wrappable').is(':checked') ) {
					jQuery('._gift_wrap_cost_field').show();
				}

			}).change();
		" );
	}

	/**
	 * write_panel_save function.
	 *
	 * @access public
	 * @param mixed $post_id
	 * @return void
	 */
	public function write_panel_save( $post_id ) {
		$_is_gift_wrappable = ! empty( $_POST['_is_gift_wrappable'] ) ? 'yes' : 'no';
		$_gift_wrap_cost   = ! empty( $_POST['_gift_wrap_cost'] ) ? wc_clean( $_POST['_gift_wrap_cost'] ) : '';
		$_gift_wrap_cost = str_replace( ',', '.', $_gift_wrap_cost );

		update_post_meta( $post_id, '_is_gift_wrappable', $_is_gift_wrappable );
		update_post_meta( $post_id, '_gift_wrap_cost', $_gift_wrap_cost );
	}

	/**
	 * admin_settings function.
	 *
	 * @access public
	 * @return $settings
	 */
    public function admin_settings() {
        // Init settings
		$this->settings = array(
			array(
				'name' 		=> __( 'Gift Wrapping Enabled by Default?', 'product-gift-wrap-for-woocommerce' ),
				'desc' 		=> __( 'Enable this to allow gift wrapping for products by default.', 'product-gift-wrap-for-woocommerce' ),
				'id' 		=> 'product_gift_wrap_enabled',
				'type' 		=> 'checkbox',
			),
			array(
				'name' 		=> __( 'Default Gift Wrap Cost', 'product-gift-wrap-for-woocommerce' ),
				'desc' 		=> __( 'The cost of gift wrap unless overridden per-product.', 'product-gift-wrap-for-woocommerce' ),
				'id' 		=> 'product_gift_wrap_cost',
				'type' 		=> 'text',
				'desc_tip'  => true
			),
			array(
				'name' 		=> __( 'Gift Wrap Message', 'product-gift-wrap-for-woocommerce' ),
				'id' 		=> 'product_gift_wrap_message',
				'desc' 		=> __( 'Note: <code>{price}</code> will be replaced with the gift wrap cost.', 'product-gift-wrap-for-woocommerce' ),
				'type' 		=> 'text',
				'desc_tip'  => __( 'Label shown to the user on the frontend.', 'product-gift-wrap-for-woocommerce' )
			),
		);

        return $this->settings;
    }

    /**
	 * display_admin_settings function.
	 *
	 * @access public
	 * @return void
	 */
	public function display_admin_settings() {
		woocommerce_admin_fields( $this->admin_settings() );
	}

	/**
	 * save_admin_settings function.
	 *
	 * @access public
	 * @return void
	 */
	public function save_admin_settings() {
		woocommerce_update_options( $this->admin_settings() );
	}
}

register_activation_hook( __FILE__, array( 'WC_Product_Gift_Wrap', 'install' ) );

add_action( 'plugins_loaded', array( 'WC_Product_Gift_Wrap', 'get_instance' ) );

endif;