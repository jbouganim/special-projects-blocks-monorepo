<?php
/**
 * Product admin: mark a product as an add-on, and choose the add-ons offered with a product or variation.
 *
 * @package A8csp
 */

namespace A8CSP\WC_Product_Addons;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product edit screen fields.
 */
class Admin {

	/**
	 * AJAX action for the add-on product search.
	 */
	const SEARCH_ACTION = 'a8csp_wcpa_search_addons';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'product_type_options', array( __CLASS__, 'product_type_option' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'addon_settings_fields' ) );
		add_action( 'woocommerce_product_options_related', array( __CLASS__, 'product_addons_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variation_addons_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 10, 2 );
		add_action( 'wp_ajax_' . self::SEARCH_ACTION, array( __CLASS__, 'search_addons' ) );
	}

	/**
	 * "Add-on" checkbox next to Virtual / Downloadable (simple products only).
	 *
	 * @param array<string, array<string, string>> $options Product type options.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function product_type_option( $options ) {
		$options['a8csp_wcpa_is_addon'] = array(
			'id'            => A8CSP_WCPA_META_IS_ADDON,
			'wrapper_class' => 'show_if_simple',
			'label'         => __( 'Add-on', 'wc-product-addons' ),
			'description'   => __( 'Sold only as an extra on another cart line (for example a dinner with a ticket). Hidden from the shop and search.', 'wc-product-addons' ),
			'default'       => 'no',
		);

		return $options;
	}

	/**
	 * Quantity rule, in the General tab of simple products.
	 *
	 * @return void
	 */
	public static function addon_settings_fields() {
		global $product_object;

		echo '<div class="options_group show_if_simple">';

		woocommerce_wp_select(
			array(
				'id'          => A8CSP_WCPA_META_QUANTITY,
				'label'       => __( 'Add-on quantity', 'wc-product-addons' ),
				'description' => __( 'Only used when this product is an add-on.', 'wc-product-addons' ),
				'desc_tip'    => true,
				'value'       => $product_object instanceof WC_Product ? a8csp_wcpa_get_quantity_mode( $product_object ) : 'independent',
				'options'     => array(
					'independent' => __( 'Any quantity', 'wc-product-addons' ),
					'max_parent'  => __( 'No more than the line it is added to', 'wc-product-addons' ),
					'fixed'       => __( 'No more than a set number', 'wc-product-addons' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => A8CSP_WCPA_META_QUANTITY_LIMIT,
				'label'             => __( 'Add-on maximum', 'wc-product-addons' ),
				'description'       => __( 'Highest quantity per line it is added to.', 'wc-product-addons' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'value'             => $product_object instanceof WC_Product ? (string) a8csp_wcpa_get_quantity_limit( $product_object ) : '1',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			)
		);

		echo '</div>';

		// Only show the maximum with the "set number" rule.
		wc_enqueue_js(
			"jQuery( function ( $ ) {
				var mode = $( '#" . A8CSP_WCPA_META_QUANTITY . "' );
				var field = $( '." . A8CSP_WCPA_META_QUANTITY_LIMIT . "_field' );
				function toggle() { field.toggle( 'fixed' === mode.val() ); }
				mode.on( 'change', toggle );
				toggle();
			} );"
		);
	}

	/**
	 * "Add-ons" picker in the Linked Products tab.
	 *
	 * @return void
	 */
	public static function product_addons_field() {
		global $product_object;

		if ( ! $product_object instanceof WC_Product ) {
			return;
		}

		echo '<div class="options_group">';
		self::render_addons_select(
			A8CSP_WCPA_META_ADDONS,
			'a8csp_wcpa_addons[]',
			array_filter( array_map( 'absint', (array) $product_object->get_meta( A8CSP_WCPA_META_ADDONS ) ) ),
			__( 'Offered in the cart with this product. Variations can set their own list.', 'wc-product-addons' ),
			'form-field'
		);
		echo '</div>';
	}

	/**
	 * "Add-ons" picker on each variation.
	 *
	 * @param int      $loop           Variation index.
	 * @param array    $variation_data Variation data (unused).
	 * @param \WP_Post $variation      Variation post.
	 *
	 * @return void
	 */
	public static function variation_addons_field( $loop, $variation_data, $variation ) {
		unset( $variation_data );

		self::render_addons_select(
			A8CSP_WCPA_META_ADDONS . '_' . $loop,
			'a8csp_wcpa_variation_addons[' . absint( $loop ) . '][]',
			array_filter( array_map( 'absint', (array) get_post_meta( $variation->ID, A8CSP_WCPA_META_ADDONS, true ) ) ),
			__( 'Offered in the cart with this variation. Leave empty to use the product’s add-ons.', 'wc-product-addons' ),
			'form-row form-row-full'
		);
	}

	/**
	 * A WooCommerce product search select limited to add-on products.
	 *
	 * @param string $id          Field ID.
	 * @param string $name        Field name.
	 * @param int[]  $selected    Selected add-on IDs.
	 * @param string $description Help text.
	 * @param string $wrapper     Wrapper class.
	 *
	 * @return void
	 */
	private static function render_addons_select( $id, $name, $selected, $description, $wrapper ) {
		?>
		<p class="<?php echo esc_attr( $wrapper ); ?>">
			<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Add-ons', 'wc-product-addons' ); ?></label>
			<select class="wc-product-search" multiple="multiple" style="width: 50%;" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" data-placeholder="<?php esc_attr_e( 'Search for an add-on&hellip;', 'wc-product-addons' ); ?>" data-action="<?php echo esc_attr( self::SEARCH_ACTION ); ?>">
				<?php
				foreach ( $selected as $addon_id ) {
					$addon = wc_get_product( $addon_id );
					if ( $addon instanceof WC_Product ) {
						echo '<option value="' . esc_attr( (string) $addon_id ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $addon->get_formatted_name() ) ) . '</option>';
					}
				}
				?>
			</select>
			<?php echo wc_help_tip( $description ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip escapes. ?>
		</p>
		<?php
	}

	/**
	 * Save the product fields. Catalog hides add-ons from the shop on save.
	 *
	 * @param WC_Product $product Product being saved.
	 *
	 * @return void
	 */
	public static function save_product( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product save nonce.
		$is_addon = $product->is_type( 'simple' ) && isset( $_POST[ A8CSP_WCPA_META_IS_ADDON ] );
		$product->update_meta_data( A8CSP_WCPA_META_IS_ADDON, $is_addon ? 'yes' : 'no' );

		$mode = isset( $_POST[ A8CSP_WCPA_META_QUANTITY ] ) ? sanitize_key( wp_unslash( $_POST[ A8CSP_WCPA_META_QUANTITY ] ) ) : 'independent';
		$product->update_meta_data( A8CSP_WCPA_META_QUANTITY, in_array( $mode, array( 'max_parent', 'fixed' ), true ) ? $mode : 'independent' );

		$limit = isset( $_POST[ A8CSP_WCPA_META_QUANTITY_LIMIT ] ) ? absint( wp_unslash( $_POST[ A8CSP_WCPA_META_QUANTITY_LIMIT ] ) ) : 0;
		$product->update_meta_data( A8CSP_WCPA_META_QUANTITY_LIMIT, max( 1, $limit ) );

		$addons = isset( $_POST['a8csp_wcpa_addons'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['a8csp_wcpa_addons'] ) ) ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		self::update_addons_meta( $product, $addons );
	}

	/**
	 * Save a variation's add-ons.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation index.
	 *
	 * @return void
	 */
	public static function save_variation( $variation_id, $loop ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the variations save nonce.
		$posted = isset( $_POST['a8csp_wcpa_variation_addons'][ $loop ] ) ? (array) wp_unslash( $_POST['a8csp_wcpa_variation_addons'][ $loop ] ) : array();
		$addons = array_values( array_filter( array_map( 'absint', $posted ) ) );

		if ( array() === $addons ) {
			delete_post_meta( $variation_id, A8CSP_WCPA_META_ADDONS );
			return;
		}

		update_post_meta( $variation_id, A8CSP_WCPA_META_ADDONS, $addons );
	}

	/**
	 * Store the add-on list on a product, dropping products that aren't add-ons.
	 *
	 * @param WC_Product $product Product.
	 * @param int[]      $addons  Add-on IDs.
	 *
	 * @return void
	 */
	private static function update_addons_meta( $product, $addons ) {
		$addons = array_values( array_filter( $addons, 'a8csp_wcpa_is_addon' ) );

		if ( array() === $addons ) {
			$product->delete_meta_data( A8CSP_WCPA_META_ADDONS );
			return;
		}

		$product->update_meta_data( A8CSP_WCPA_META_ADDONS, $addons );
	}

	/**
	 * AJAX: search add-on products for the pickers (same response shape as WooCommerce's product search).
	 *
	 * @return void
	 */
	public static function search_addons() {
		check_ajax_referer( 'search-products', 'security' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_die( -1 );
		}

		$term = isset( $_GET['term'] ) ? wc_clean( wp_unslash( $_GET['term'] ) ) : '';
		if ( '' === $term ) {
			wp_die();
		}

		$data_store = \WC_Data_Store::load( 'product' );
		$ids        = $data_store->search_products( $term, '', false, false, 50 );
		$found      = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product instanceof WC_Product && a8csp_wcpa_is_addon( $product ) ) {
				$found[ $product->get_id() ] = rawurldecode( wp_strip_all_tags( $product->get_formatted_name() ) );
			}
		}

		wp_send_json( $found );
	}
}
