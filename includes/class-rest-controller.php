<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for Quick Stock.
 *
 * Namespace: jalma-quick-stock/v1
 *
 * Routes:
 * - GET  /products                  List products with filter/sort/pagination
 * - GET  /variations/(?P<id>\d+)    List variations of a variable product
 * - POST /update                    Update stock and/or low_stock_amount for a product or variation
 * - POST /toggle-variation-stock    Flip a variable product between parent-level and per-variation stock management
 * - POST /enable-stock-management   Enable _manage_stock on a product that currently has it disabled
 *
 * All routes require `manage_woocommerce` capability and the default WP REST
 * nonce (X-WP-Nonce header). Nonce is supplied from wp_localize_script.
 */
class JQSW_Rest_Controller {

	const NAMESPACE_ROOT = 'jalma-quick-stock/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		/**
		 * Fires before the built-in REST routes are registered. Add-on plugins
		 * can register additional routes under the same namespace.
		 *
		 * @since 1.0.5
		 *
		 * @param string $namespace The REST namespace ('jalma-quick-stock/v1').
		 */
		do_action( 'jqsw_before_register_routes', self::NAMESPACE_ROOT );

		register_rest_route( self::NAMESPACE_ROOT, '/products', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_products' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'page'         => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
				'per_page'     => [ 'default' => 50, 'sanitize_callback' => 'absint' ],
				'search'       => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				'category'     => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
				'stock_status' => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				'orderby'      => [ 'default' => 'title', 'sanitize_callback' => 'sanitize_text_field' ],
				'order'        => [ 'default' => 'asc', 'sanitize_callback' => 'sanitize_text_field' ],
			],
		] );

		register_rest_route( self::NAMESPACE_ROOT, '/variations/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_variations' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
			],
		] );

		register_rest_route( self::NAMESPACE_ROOT, '/update', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'update_product' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'product_id'       => [ 'required' => true, 'sanitize_callback' => 'absint' ],
				'stock'            => [ 'sanitize_callback' => [ $this, 'sanitize_nullable_int' ] ],
				'low_stock_amount' => [ 'sanitize_callback' => [ $this, 'sanitize_nullable_int' ] ],
			],
		] );

		register_rest_route( self::NAMESPACE_ROOT, '/toggle-variation-stock', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'toggle_variation_stock' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'product_id'           => [ 'required' => true, 'sanitize_callback' => 'absint' ],
				'manage_per_variation' => [ 'required' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ],
			],
		] );

		register_rest_route( self::NAMESPACE_ROOT, '/enable-stock-management', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'enable_stock_management' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'product_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
			],
		] );

		register_rest_route( self::NAMESPACE_ROOT, '/disable-stock-management', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'disable_stock_management' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'product_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
			],
		] );

		/**
		 * Fires after the built-in REST routes are registered.
		 *
		 * @since 1.0.5
		 *
		 * @param string $namespace The REST namespace ('jalma-quick-stock/v1').
		 */
		do_action( 'jqsw_after_register_routes', self::NAMESPACE_ROOT );
	}

	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	public function sanitize_nullable_int( $value ) {
		if ( $value === '' || $value === null ) {
			return null;
		}
		return (int) $value;
	}

	/**
	 * List products for the table.
	 *
	 * Returns top-level products only (simple + variable). Variations are
	 * fetched on demand via /variations/<id>.
	 */
	public function list_products( $request ) {
		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $request['per_page'],
			'paged'          => max( 1, (int) $request['page'] ),
			'orderby'        => 'title',
			'order'          => strtoupper( $request['order'] ) === 'DESC' ? 'DESC' : 'ASC',
		];

		// Order-by aliases
		switch ( $request['orderby'] ) {
			case 'sku':
				$args['orderby']  = 'meta_value';
				$args['meta_key'] = '_sku';
				break;
			case 'stock':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_stock';
				break;
			case 'modified':
				$args['orderby'] = 'modified';
				break;
			case 'title':
			default:
				$args['orderby'] = 'title';
		}

		// Search on title and SKU
		if ( ! empty( $request['search'] ) ) {
			$args['s'] = $request['search'];
			// wc_product_sku_search is injected via WP filter when WC is loaded,
			// so a plain ?s= hits both title and SKU.
		}

		// Category filter
		if ( ! empty( $request['category'] ) ) {
			$args['tax_query'] = [
				[
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => [ (int) $request['category'] ],
					'include_children' => true,
				],
			];
		}

		// Stock status filter
		if ( ! empty( $request['stock_status'] ) ) {
			$status = $request['stock_status'];
			if ( in_array( $status, [ 'instock', 'outofstock', 'onbackorder' ], true ) ) {
				$args['meta_query'][] = [
					'key'   => '_stock_status',
					'value' => $status,
				];
			} elseif ( $status === 'notmanaged' ) {
				$args['meta_query'][] = [
					'relation' => 'OR',
					[
						'key'     => '_manage_stock',
						'value'   => 'yes',
						'compare' => '!=',
					],
					[
						'key'     => '_manage_stock',
						'compare' => 'NOT EXISTS',
					],
				];
			}
		}

		$query    = new WP_Query( $args );
		$products = [];

		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}
			$products[] = $this->product_to_array( $product );
		}

		return rest_ensure_response( [
			'products' => $products,
			'total'    => (int) $query->found_posts,
			'pages'    => (int) $query->max_num_pages,
			'page'     => (int) $args['paged'],
			'per_page' => (int) $args['posts_per_page'],
		] );
	}

	public function list_variations( $request ) {
		$parent = wc_get_product( (int) $request['id'] );
		if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
			return new WP_Error( 'jqsw_not_variable', __( 'Product is not a variable product.', 'jalma-quick-stock-for-woocommerce' ), [ 'status' => 400 ] );
		}

		$variations = [];
		foreach ( $parent->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}
			$variations[] = $this->product_to_array( $variation );
		}

		return rest_ensure_response( [
			'parent_id'  => (int) $request['id'],
			'variations' => $variations,
		] );
	}

	public function update_product( $request ) {
		$product_id = $request['product_id'];
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error( 'jqsw_not_found', __( 'Product not found.', 'jalma-quick-stock-for-woocommerce' ), [ 'status' => 404 ] );
		}

		$old_stock     = $product->get_stock_quantity();
		$old_threshold = $product->get_low_stock_amount();

		$stock            = $request->get_param( 'stock' );
		$low_stock_amount = $request->get_param( 'low_stock_amount' );

		if ( $request->has_param( 'stock' ) && $stock !== null ) {
			$product->set_stock_quantity( $stock );
		}

		if ( $request->has_param( 'low_stock_amount' ) ) {
			// null → clear override (fall back to global default)
			$product->set_low_stock_amount( $low_stock_amount === null ? '' : $low_stock_amount );
		}

		$product->save();

		/**
		 * Fires after a product's stock and/or low-stock threshold has been
		 * updated through Quick Stock. This is the hook an adjustment-log
		 * add-on would listen to for writing audit entries.
		 *
		 * @since 1.0.5
		 *
		 * @param WC_Product $product       The saved product object.
		 * @param array      $changes       Associative array with 'old_stock',
		 *                                  'new_stock', 'old_threshold',
		 *                                  'new_threshold'.
		 * @param WP_REST_Request $request  The original request.
		 */
		do_action( 'jqsw_after_product_update', $product, [
			'old_stock'     => $old_stock,
			'new_stock'     => $product->get_stock_quantity(),
			'old_threshold' => $old_threshold,
			'new_threshold' => $product->get_low_stock_amount(),
		], $request );

		return rest_ensure_response( $this->product_to_array( $product ) );
	}

	public function toggle_variation_stock( $request ) {
		$product_id           = $request['product_id'];
		$manage_per_variation = $request['manage_per_variation'];
		$parent               = wc_get_product( $product_id );

		if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
			return new WP_Error( 'jqsw_not_variable', __( 'Product is not a variable product.', 'jalma-quick-stock-for-woocommerce' ), [ 'status' => 400 ] );
		}

		if ( $manage_per_variation ) {
			// Per-variation mode: parent stops managing stock, each variation starts.
			$parent->set_manage_stock( false );
			$parent->save();
			foreach ( $parent->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$variation->set_manage_stock( true );
					if ( $variation->get_stock_quantity() === null ) {
						$variation->set_stock_quantity( 0 );
					}
					$variation->save();
				}
			}
		} else {
			// Parent-level mode: parent manages stock, variations don't.
			foreach ( $parent->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$variation->set_manage_stock( false );
					$variation->save();
				}
			}
			$parent->set_manage_stock( true );
			if ( $parent->get_stock_quantity() === null ) {
				$parent->set_stock_quantity( 0 );
			}
			$parent->save();
		}

		return rest_ensure_response( $this->product_to_array( $parent ) );
	}

	public function enable_stock_management( $request ) {
		$product_id = $request['product_id'];
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error( 'jqsw_not_found', __( 'Product not found.', 'jalma-quick-stock-for-woocommerce' ), [ 'status' => 404 ] );
		}

		$product->set_manage_stock( true );
		if ( $product->get_stock_quantity() === null ) {
			$product->set_stock_quantity( 0 );
		}
		$product->save();

		return rest_ensure_response( $this->product_to_array( $product ) );
	}

	public function disable_stock_management( $request ) {
		$product_id = $request['product_id'];
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error( 'jqsw_not_found', __( 'Product not found.', 'jalma-quick-stock-for-woocommerce' ), [ 'status' => 404 ] );
		}

		// Keep the existing _stock value so it can be restored if the user
		// re-enables tracking later — just flip the management flag off.
		$product->set_manage_stock( false );
		$product->save();

		return rest_ensure_response( $this->product_to_array( $product ) );
	}

	/**
	 * Serialize a WC_Product (or WC_Product_Variation) to the shape expected
	 * by the JS table renderer. Kept private to avoid confusion with
	 * wc_rest_prepare_product_for_response (which is a much heavier payload).
	 */
	private function product_to_array( $product ) {
		$thumbnail_id = $product->get_image_id();
		$thumbnail    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, [ 40, 40 ] ) : '';
		// For variations without their own image, fall back to parent's.
		if ( ! $thumbnail && $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$parent_thumb = $parent->get_image_id();
				if ( $parent_thumb ) {
					$thumbnail = wp_get_attachment_image_url( $parent_thumb, [ 40, 40 ] );
				}
			}
		}

		$low_stock = $product->get_low_stock_amount();
		// WC returns '' for unset overrides; normalize to null for JSON clarity.
		$low_stock_normalized = ( $low_stock === '' || $low_stock === null ) ? null : (int) $low_stock;

		$data = [
			'id'                    => $product->get_id(),
			'title'                 => $product->is_type( 'variation' ) ? $product->get_name() : $product->get_title(),
			'sku'                   => $product->get_sku(),
			'type'                  => $product->get_type(),
			'parent_id'             => $product->is_type( 'variation' ) ? $product->get_parent_id() : 0,
			'thumbnail'             => $thumbnail,
			'manage_stock'          => (bool) $product->get_manage_stock(),
			'stock'                 => $product->get_stock_quantity(),
			'low_stock_amount'      => $low_stock_normalized,
			'stock_status'          => $product->get_stock_status(),
			'edit_url'              => $product->is_type( 'variation' )
				? get_edit_post_link( $product->get_parent_id(), '' )
				: get_edit_post_link( $product->get_id(), '' ),
			'variation_count'       => $product->is_type( 'variable' ) ? count( $product->get_children() ) : 0,
			'manage_per_variation'  => $product->is_type( 'variable' ) ? ( ! $product->get_manage_stock() ) : false,
		];

		/**
		 * Filter the serialized product data returned in REST responses.
		 * Add-on plugins can add custom fields here that their own JS columns
		 * will consume when rendering the table.
		 *
		 * @since 1.0.5
		 *
		 * @param array      $data    The serialized product data.
		 * @param WC_Product $product The product object.
		 */
		return apply_filters( 'jqsw_product_row_data', $data, $product );
	}
}
