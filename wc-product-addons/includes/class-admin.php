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
	 * Add-on settings (quantity rule, details link text), in the General tab of simple products.
	 * Shown only while "Add-on" is ticked.
	 *
	 * @return void
	 */
	public static function addon_settings_fields() {
		global $product_object;

		$visible = $product_object instanceof WC_Product && $product_object->is_type( 'simple' ) && a8csp_wcpa_is_addon( $product_object );

		// Not `show_if_simple`: WooCommerce shows those again whenever the type, Virtual or Downloadable change.
		echo '<div class="options_group a8csp_wcpa_addon_settings"' . ( $visible ? '' : ' style="display: none;"' ) . '>';

		woocommerce_wp_select(
			array(
				'id'          => A8CSP_WCPA_META_QUANTITY,
				'label'       => __( 'Add-on quantity', 'wc-product-addons' ),
				'description' => __( 'How many can be added to the line it belongs to.', 'wc-product-addons' ),
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

		woocommerce_wp_text_input(
			array(
				'id'          => A8CSP_WCPA_META_DETAILS_LABEL,
				'label'       => __( 'Details link text', 'wc-product-addons' ),
				'description' => __( 'Text of the link that opens the description, for example "Menu details". Leave empty for "Details".', 'wc-product-addons' ),
				'desc_tip'    => true,
				'placeholder' => __( 'Details', 'wc-product-addons' ),
				'value'       => $product_object instanceof WC_Product ? (string) $product_object->get_meta( A8CSP_WCPA_META_DETAILS_LABEL ) : '',
			)
		);

		echo '</div>';

		// The settings show for simple products marked "Add-on"; the maximum only with the "set number" rule.
		wc_enqueue_js(
			"jQuery( function ( $ ) {
				var group = $( '.a8csp_wcpa_addon_settings' );
				var isAddon = $( '#" . A8CSP_WCPA_META_IS_ADDON . "' );
				var type = $( 'select#product-type' );
				var mode = $( '#" . A8CSP_WCPA_META_QUANTITY . "' );
				var limit = $( '." . A8CSP_WCPA_META_QUANTITY_LIMIT . "_field' );
				function toggle() {
					group.toggle( 'simple' === type.val() && isAddon.is( ':checked' ) );
					limit.toggle( 'fixed' === mode.val() );
				}
				isAddon.on( 'change', toggle );
				mode.on( 'change', toggle );
				$( document.body ).on( 'woocommerce-product-type-change', toggle );
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

		// Variations: hide the add-ons picker while "No add-ons" is ticked. Variations load over AJAX.
		wc_enqueue_js(
			"jQuery( function ( $ ) {
				function toggle( checkbox ) {
					$( checkbox ).closest( '.woocommerce_variation' ).find( '.a8csp_wcpa_variation_addons_field' ).toggle( ! checkbox.checked );
				}
				$( '#woocommerce-product-data' )
					.on( 'change', '.a8csp_wcpa_no_addons', function () { toggle( this ); } )
					.on( 'woocommerce_variations_loaded', function () {
						$( this ).find( '.a8csp_wcpa_no_addons' ).each( function () { toggle( this ); } );
					} );
			} );"
		);
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

		$no_addons = 'yes' === get_post_meta( $variation->ID, A8CSP_WCPA_META_NO_ADDONS, true );

		self::render_addons_select(
			A8CSP_WCPA_META_ADDONS . '_' . $loop,
			'a8csp_wcpa_variation_addons[' . absint( $loop ) . '][]',
			array_filter( array_map( 'absint', (array) get_post_meta( $variation->ID, A8CSP_WCPA_META_ADDONS, true ) ) ),
			__( 'Offered in the cart with this variation. Leave empty to use the product’s add-ons (Linked Products tab), or tick “No add-ons” to offer none.', 'wc-product-addons' ),
			'form-row form-row-full a8csp_wcpa_variation_addons_field',
			$no_addons,
			true
		);

		// Input inside its label, like WooCommerce's variation checkboxes. Not the `checkbox` class:
		// WooCommerce floats those and removes their margin in variations.
		?>
		<p class="form-row form-row-full">
			<label for="<?php echo esc_attr( A8CSP_WCPA_META_NO_ADDONS . '_' . $loop ); ?>">
				<input type="checkbox" class="a8csp_wcpa_no_addons" style="margin: 0 0.5em 0 0;" id="<?php echo esc_attr( A8CSP_WCPA_META_NO_ADDONS . '_' . $loop ); ?>" name="<?php echo esc_attr( 'a8csp_wcpa_variation_no_addons[' . absint( $loop ) . ']' ); ?>" value="yes" <?php checked( $no_addons ); ?> /><?php esc_html_e( 'No add-ons', 'wc-product-addons' ); ?>
				<?php echo wc_help_tip( __( 'Offer no add-ons with this variation, even when the product has add-ons (Linked Products tab).', 'wc-product-addons' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip escapes. ?>
			</label>
		</p>
		<?php
	}

	/**
	 * A WooCommerce product search select limited to add-on products.
	 *
	 * @param string $id          Field ID.
	 * @param string $name        Field name.
	 * @param int[]  $selected    Selected add-on IDs.
	 * @param string $description Help text.
	 * @param string $wrapper     Wrapper class.
	 * @param bool   $hidden      Whether the field starts hidden.
	 * @param bool   $tip_in_label Whether the help tip goes in the label (variations) rather than after the field.
	 *
	 * @return void
	 */
	private static function render_addons_select( $id, $name, $selected, $description, $wrapper, $hidden = false, $tip_in_label = false ) {
		$tip = wc_help_tip( $description );
		?>
		<p class="<?php echo esc_attr( $wrapper ); ?>"<?php echo $hidden ? ' style="display: none;"' : ''; ?>>
			<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Add-ons', 'wc-product-addons' ); ?> <?php echo $tip_in_label ? $tip : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip escapes. ?></label>
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
			<?php echo $tip_in_label ? '' : $tip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip escapes. ?>
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

		$details_label = isset( $_POST[ A8CSP_WCPA_META_DETAILS_LABEL ] ) ? sanitize_text_field( wp_unslash( $_POST[ A8CSP_WCPA_META_DETAILS_LABEL ] ) ) : '';
		if ( '' === $details_label ) {
			$product->delete_meta_data( A8CSP_WCPA_META_DETAILS_LABEL );
		} else {
			$product->update_meta_data( A8CSP_WCPA_META_DETAILS_LABEL, $details_label );
		}

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
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the variations save nonce.
		$posted    = isset( $_POST['a8csp_wcpa_variation_addons'][ $loop ] ) ? (array) wp_unslash( $_POST['a8csp_wcpa_variation_addons'][ $loop ] ) : array();
		$no_addons = isset( $_POST['a8csp_wcpa_variation_no_addons'][ $loop ] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$addons = array_values( array_filter( array_map( 'absint', $posted ) ) );

		// The list is kept while "No add-ons" is ticked, so unticking it brings the list back.
		if ( $no_addons ) {
			update_post_meta( $variation_id, A8CSP_WCPA_META_NO_ADDONS, 'yes' );
		} else {
			delete_post_meta( $variation_id, A8CSP_WCPA_META_NO_ADDONS );
		}

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
