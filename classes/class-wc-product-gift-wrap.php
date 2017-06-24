<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class for product gift wrap.
 *
 * @since 1.0.0
 */
class WC_Product_Gift_Wrap {

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string VERSION plugin version.
	 */
	const VERSION = '1.3';

	/**
	 * Plugin settings fields.
	 *
	 * @since 1.0.0
	 * @var array $settings settings fields.
	 */
	public $settings;

	/**
	 * Plugin option to enable the feature.
	 *
	 * @since 1.0.0
	 * @var string $gift_wrap_enabled plugin option.
	 */
	public $gift_wrap_enabled;

	/**
	 * Plugin option to set price.
	 *
	 * @since 1.0.0
	 * @var int $gift_wrap_cost plugin option to set price.
	 */
	public $gift_wrap_cost;

	/**
	 * Plugin option to display text next to checkbox for activating the gift wrap.
	 *
	 * @since 1.0.0
	 * @var string $product_gift_wrap_message Message to be displayed.
	 */
	public $product_gift_wrap_message;

	/**
	 * Construct function.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->gift_wrap_enabled         = get_option( 'product_gift_wrap_enabled' );
		$this->gift_wrap_cost            = get_option( 'product_gift_wrap_cost', 0 );
		$this->product_gift_wrap_message = get_option( 'product_gift_wrap_message' );
	}

	/**
	 * Initialise instance.
	 *
	 * @since 1.3.1
	 */
	public static function init() {
		$self = new self();

		// Load plugin text domain.
		add_action( 'init', array( $self, 'load_plugin_textdomain' ) );

		// Display on the front end.
		add_action( 'woocommerce_after_add_to_cart_button', array( $self, 'gift_option_html' ), 10 );

		// Filters for cart actions.
		add_filter( 'woocommerce_add_cart_item_data', array( $self, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $self, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $self, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item', array( $self, 'add_cart_item' ), 10, 1 );
		add_action( 'woocommerce_add_order_item_meta', array( $self, 'add_order_item_meta' ), 10, 2 );

		// Write Panels.
		add_action( 'woocommerce_product_options_pricing', array( $self, 'write_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $self, 'write_panel_save' ) );

		// Admin.
		add_action( 'woocommerce_settings_general_options_end', array( $self, 'display_admin_settings' ) );
		add_action( 'woocommerce_update_options_general', array( $self, 'save_admin_settings' ) );
	}

	/**
	 * Runs on plugin activation to initialize options.
	 *
	 * @since 1.0.0
	 */
	public static function install() {
		add_option( 'product_gift_wrap_enabled', false );
		add_option( 'product_gift_wrap_cost', '0' );
		// Translators: %s is the price for the gift wrap.
		add_option( 'product_gift_wrap_message', sprintf( __( 'Gift wrap this item for %s?', 'product-gift-wrap-for-woocommerce' ), '{price}' ) );
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since 1.0.0
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
	 * @param double $price The source price.
	 * @param string $to_currency The target currency. If empty, the active currency
	 * will be taken.
	 * @param string $from_currency The source currency. If empty, WooCommerce base
	 * currency will be taken.
	 * @return double The price converted from source to destination currency.
	 * @author Aelia <support@aelia.co>
	 * @link http://aelia.co
	 */
	protected function get_price_in_currency( $price, $to_currency = null, $from_currency = null ) {
		// If source currency is not specified, take the shop's base currency as a default.
		if ( empty( $from_currency ) ) {
			$from_currency = get_option( 'woocommerce_currency' );
		}

		/**
		 * If target currency is not specified, take the active currency as a default.
		 * The Currency Switcher sets this currency automatically, based on the context. Other
		 * plugins can also override it, based on their own custom criteria, by implementing
		 * a filter for the "woocommerce_currency" hook.
		 *
		 * For example, a subscription plugin may decide that the active currency is the one
		 * taken from a previous subscription, because it's processing a renewal, and such
		 * renewal should keep the original prices, in the original currency.
		 */
		if ( empty( $to_currency ) ) {
			$to_currency = get_woocommerce_currency();
		}

		/**
		 * Call the currency conversion filter. Using a filter allows for loose coupling. If the
		 * Aelia Currency Switcher is not installed, the filter call will return the original
		 * amount, without any conversion being performed. Your plugin won't even need to know if
		 * the multi-currency plugin is installed or active.
		 */
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

		if ( '' === $is_wrappable && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		if ( 'yes' === $is_wrappable ) {

			$current_value = ( isset( $_REQUEST['gift_wrap'] ) && ! empty( absint( $_REQUEST['gift_wrap'] ) ) ) ? 1 : 0;

			$cost = get_post_meta( $post->ID, '_gift_wrap_cost', true );

			if ( '' === $cost ) {
				$cost = $this->gift_wrap_cost;
			}

			$price_text = $cost > 0 ? wc_price( $this->get_price_in_currency( $cost ) ) : __( 'free', 'product-gift-wrap-for-woocommerce' );

			wc_get_template( 'gift-wrap.php', array(
				'product_gift_wrap_message' => $this->product_gift_wrap_message,
				'current_value'             => $current_value,
				'price_text'                => $price_text,
			), 'product-gift-wrap-for-woocommerce', WC_PRODUCT_GIFT_WRAP_PATH . '/templates/' );
		}
	}

	/**
	 * When added to cart, save any gift data
	 *
	 * @access public
	 * @param mixed $cart_item_meta The cart item data.
	 * @param mixed $product_id Product ID or object.
	 * @return array an Array of item meta
	 */
	public function add_cart_item_data( $cart_item_meta, $product_id ) {
		$is_wrappable = get_post_meta( $product_id, '_is_gift_wrappable', true );

		if ( '' === $is_wrappable && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		if ( ! empty( $_POST['gift_wrap'] ) && 'yes' === $is_wrappable ) {
			$cart_item_meta['gift_wrap'] = true;
		}

		return $cart_item_meta;
	}

	/**
	 * Get the gift data from the session on page load
	 *
	 * @access public
	 * @param mixed $cart_item Array of cart item data.
	 * @param mixed $values an array of values.
	 * @return array an array of cart item data
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {
		if ( empty( $values['gift_wrap'] ) ) {
			return $cart_item;
		}

		$cart_item['gift_wrap'] = true;

		$cost = get_post_meta( $cart_item['product_id'], '_gift_wrap_cost', true );

		if ( '' === $cost ) {
			$cost = $this->gift_wrap_cost;
		}

		$product = wc_get_product( $values['variation_id'] ? $values['variation_id'] : $values['product_id'] );

		$cart_item['data']->set_price( $product->get_price() + $this->get_price_in_currency( $cost ) );

		return $cart_item;
	}

	/**
	 * Display gift data if present in the cart
	 *
	 * @access public
	 * @param mixed $item_data array of gift wrap data.
	 * @param mixed $cart_item cart item.
	 * @return array an array for the gift wrap data
	 */
	public function get_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['gift_wrap'] ) ) {
			return $item_data;
		}

		$item_data[] = array(
			'name'    => __( 'Gift Wrapped', 'product-gift-wrap-for-woocommerce' ),
			'value'   => __( 'Yes', 'product-gift-wrap-for-woocommerce' ),
			'display' => __( 'Yes', 'product-gift-wrap-for-woocommerce' ),
		);

		return $item_data;
	}

	/**
	 * Adjust price after adding to cart
	 *
	 * @access public
	 * @param mixed $cart_item array of cart item data.
	 * @return array array of cart item data
	 */
	public function add_cart_item( $cart_item ) {
		if ( empty( $cart_item['gift_wrap'] ) ) {
			return $cart_item;
		}

		$cost = get_post_meta( $cart_item['product_id'], '_gift_wrap_cost', true );

		if ( '' === $cost ) {
			$cost = $this->gift_wrap_cost;
		}

		$product = wc_get_product( $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'] );

		$cart_item['data']->set_price( $product->get_price() + $this->get_price_in_currency( $cost ) );

		return $cart_item;
	}

	/**
	 * After ordering, add the data to the order line items.
	 *
	 * @access public
	 * @param mixed $item_id ID of the item.
	 * @param mixed $cart_item cart item data.
	 * @return void
	 */
	public function add_order_item_meta( $item_id, $cart_item ) {
		if ( empty( $cart_item['gift_wrap'] ) ) {
			return;
		}

		wc_add_order_item_meta( $item_id, __( 'Gift Wrapped', 'product-gift-wrap-for-woocommerce' ), __( 'Yes', 'product-gift-wrap-for-woocommerce' ) );
	}

	/**
	 * Add gift wrap option to product edit.
	 *
	 * @access public
	 * @return void
	 */
	public function write_panel() {
		global $post;

		echo '</div><div class="options_group show_if_simple show_if_variable">';

		$is_wrappable = get_post_meta( $post->ID, '_is_gift_wrappable', true );

		if ( '' === $is_wrappable && $this->gift_wrap_enabled ) {
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
	 * Save gift wrap values for the product.
	 *
	 * @access public
	 * @param mixed $post_id Product ID.
	 * @return void
	 */
	public function write_panel_save( $post_id ) {
		$_is_gift_wrappable = ! empty( $_POST['_is_gift_wrappable'] ) ? 'yes' : 'no';
		$_gift_wrap_cost    = ! empty( $_POST['_gift_wrap_cost'] ) ? wc_clean( $_POST['_gift_wrap_cost'] ) : '';
		$_gift_wrap_cost	= str_replace( ',', '.', $_gift_wrap_cost );

		update_post_meta( $post_id, '_is_gift_wrappable', $_is_gift_wrappable );
		update_post_meta( $post_id, '_gift_wrap_cost', $_gift_wrap_cost );
	}

	/**
	 * Create the settings for the plugin.
	 *
	 * @access public
	 * @return array Plugin settings
	 */
	public function admin_settings() {
		// Init settings.
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
				'desc_tip'  => true,
			),
			array(
				'name' 		=> __( 'Gift Wrap Message', 'product-gift-wrap-for-woocommerce' ),
				'id' 		=> 'product_gift_wrap_message',
				'desc' 		=> __( 'Note: <code>{price}</code> will be replaced with the gift wrap cost.', 'product-gift-wrap-for-woocommerce' ),
				'type' 		=> 'text',
				'desc_tip'  => __( 'Label shown to the user on the frontend.', 'product-gift-wrap-for-woocommerce' ),
			),
		);

		return $this->settings;
	}

	/**
	 * Display plugin settings in the WooCommerce settings.
	 *
	 * @access public
	 * @return void
	 */
	public function display_admin_settings() {
		woocommerce_admin_fields( $this->admin_settings() );
	}

	/**
	 * Save the plugin settings.
	 *
	 * @access public
	 * @return void
	 */
	public function save_admin_settings() {
		woocommerce_update_options( $this->admin_settings() );
	}
}
