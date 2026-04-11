<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for Quick Stock updates.
 *
 * Exposes a single namespace `jalma-quick-stock/v1` with routes for listing
 * products, updating stock/threshold, and toggling variation stock management.
 *
 * All routes require the `manage_woocommerce` capability and CSRF-protected
 * by the default WordPress REST API nonce (X-WP-Nonce header).
 */
class JQSW_Rest_Controller {

	const NAMESPACE_ROOT = 'jalma-quick-stock/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE_ROOT, '/products', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_products' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'page'       => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
				'per_page'   => [ 'default' => 50, 'sanitize_callback' => 'absint' ],
				'search'     => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				'category'   => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
				'stock_status' => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
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
				'product_id'        => [ 'required' => true, 'sanitize_callback' => 'absint' ],
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
	 * List products for the table. Returns minimal fields needed for the UI.
	 * Variations are NOT returned inline — they're fetched on demand when
	 * a variable product is expanded. Keeps the initial payload small.
	 */
	public function list_products( $request ) {
		// Placeholder — full implementation in the next iteration.
		return rest_ensure_response( [
			'products' => [],
			'total'    => 0,
			'page'     => $request['page'],
			'per_page' => $request['per_page'],
		] );
	}

	public function update_product( $request ) {
		$product_id = $request['product_id'];
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error( 'jqsw_not_found', __( 'Product not found.', 'jalma-quick-stock-for-woocommerce' ), [ 'status' => 404 ] );
		}

		$stock            = $request->get_param( 'stock' );
		$low_stock_amount = $request->get_param( 'low_stock_amount' );

		if ( $stock !== null ) {
			$product->set_stock_quantity( $stock );
		}

		if ( $request->has_param( 'low_stock_amount' ) ) {
			// null → clear override (fall back to global)
			$product->set_low_stock_amount( $low_stock_amount === null ? '' : $low_stock_amount );
		}

		$product->save();

		return rest_ensure_response( [
			'product_id'       => $product_id,
			'stock'            => $product->get_stock_quantity(),
			'low_stock_amount' => $product->get_low_stock_amount(),
			'stock_status'     => $product->get_stock_status(),
		] );
	}

	public function toggle_variation_stock( $request ) {
		$product_id = $request['product_id'];
		$manage_per_variation = $request['manage_per_variation'];
		$parent = wc_get_product( $product_id );

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
			$parent->save();
		}

		return rest_ensure_response( [
			'product_id'           => $product_id,
			'manage_per_variation' => $manage_per_variation,
		] );
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

		return rest_ensure_response( [
			'product_id'   => $product_id,
			'manage_stock' => true,
			'stock'        => $product->get_stock_quantity(),
		] );
	}
}
