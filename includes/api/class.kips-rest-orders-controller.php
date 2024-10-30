<?php

/**
 * Kips API
 *
 * Handles Kips-API endpoint requests.
 *
 * @package Kips/API
 * @since   2.0.0
 */
defined( 'ABSPATH' ) || exit;

class Kips_API_Orders_Controller {

	const KIPS_API_NAMESPACE = 'kips/v';
	const KIPS_API_VERSION = '1';

  /**
	 * Route base.
	 *
	 * @var string
	 */
  protected $rest_base = 'orders';

	/**
	 * Kips API Constructor.
	 */
	public function __construct() {

  }
  
  public function register_routes() {
		$base = self::KIPS_API_NAMESPACE . self::KIPS_API_VERSION;
    
    register_rest_route( $base, '/' . $this->rest_base . '(?:/(?P<id>\d+))?', array(
      array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => array( $this, 'get_orders' ),
        'args' => [
          'id'
        ],
      )
    ));
  }
  
  public function get_orders( WP_REST_Request $request ) {
    $params = $request->get_params();
    
    if (isset($params) && !empty($params))
      $order_id = (int) $params['id'];

	// 	global $wpdb;

  //   $sql = "
  //   SELECT
  //     p.ID as order_id,
  //     p.post_date,
  //   max( CASE WHEN pm.meta_key = '_billing_email' and p.ID = pm.post_id THEN pm.meta_value END ) AS billing_email,
  //   max( CASE WHEN pm.meta_key = '_billing_first_name' and p.ID = pm.post_id THEN pm.meta_value END ) AS billing_first_name,
  //   max( CASE WHEN pm.meta_key = '_billing_last_name' and p.ID = pm.post_id THEN pm.meta_value END ) AS billing_last_name,
  //   max( CASE WHEN pm.meta_key = '_billing_address_1' and p.ID = pm.post_id THEN pm.meta_value END ) AS billing_address_1,
  //   max( CASE WHEN pm.meta_key = '_billing_address_2' and p.ID = pm.post_id THEN pm.meta_value END ) AS billing_address_2,
  //   max( CASE WHEN pm.meta_key = '_billing_city' and p.ID = pm.post_id THEN pm.meta_value END ) AS billing_city,
  //   max( CASE WHEN pm.meta_key = '_billing_state' and p.ID = pm.post_id THEN pm.meta_value END ) AS billing_state,
  //   max( CASE WHEN pm.meta_key = '_billing_postcode' and p.ID = pm.post_id THEN pm.meta_value END ) AS billing_postcode,
  //   max( CASE WHEN pm.meta_key = '_shipping_first_name' and p.ID = pm.post_id THEN pm.meta_value END ) AS shipping_first_name,
  //   max( CASE WHEN pm.meta_key = '_shipping_last_name' and p.ID = pm.post_id THEN pm.meta_value END ) AS shipping_last_name,
  //   max( CASE WHEN pm.meta_key = '_shipping_address_1' and p.ID = pm.post_id THEN pm.meta_value END ) AS shipping_address_1,
  //   max( CASE WHEN pm.meta_key = '_shipping_address_2' and p.ID = pm.post_id THEN pm.meta_value END ) AS shipping_address_2,
  //   max( CASE WHEN pm.meta_key = '_shipping_city' and p.ID = pm.post_id THEN pm.meta_value END ) AS shipping_city,
  //   max( CASE WHEN pm.meta_key = '_shipping_state' and p.ID = pm.post_id THEN pm.meta_value END ) AS shipping_state,
  //   max( CASE WHEN pm.meta_key = '_shipping_postcode' and p.ID = pm.post_id THEN pm.meta_value END ) AS shipping_postcode,
  //   max( CASE WHEN pm.meta_key = '_order_total' and p.ID = pm.post_id THEN pm.meta_value END ) as order_total,
  //   max( CASE WHEN pm.meta_key = '_order_tax' and p.ID = pm.post_id THEN pm.meta_value END ) as order_tax,
  //   max( CASE WHEN pm.meta_key = '_paid_date' and p.ID = pm.post_id THEN pm.meta_value END ) as paid_date
  // FROM
  //   wp_posts p 
  //   LEFT JOIN wp_postmeta pm on p.ID = pm.post_id
  // WHERE
  //   post_type = 'shop_order'
  //   AND p.ID = $order_id
  // GROUP BY
  //   p.ID";
    
  //   $order = $wpdb->get_results($sql);

		$order = wc_get_order( $order_id );

		if(!empty($order)) {
			$subtotal = 0;
			// subtotal
			foreach ( $order->get_items() as $item ) {
				$subtotal += $item->get_subtotal();
			}
			$order_data = array(
				'id'                        => $order->get_id(),
				'order_number'              => $order->get_order_number(),
				'created_at'                => $order->get_date_created(), // API gives UTC times.
				'updated_at'                => $order->get_date_modified(), // API gives UTC times.
				'completed_at'              => $order->get_date_completed(), // API gives UTC times.
				'status'                    => $order->get_status(),
				'currency'                  => $order->get_currency(),
				'total'                     => wc_format_decimal( $order->get_total(), 2 ),
				'subtotal'                  => wc_format_decimal( $subtotal, 2 ),
				'total_line_items_quantity' => $order->get_item_count(),
				'total_tax'                 => wc_format_decimal( $order->get_total_tax(), 2 ),
				'total_shipping'            => wc_format_decimal( $order->get_shipping_total(), 2 ),
				'cart_tax'                  => wc_format_decimal( $order->get_cart_tax(), 2 ),
				'shipping_tax'              => wc_format_decimal( $order->get_shipping_tax(), 2 ),
				'total_discount'            => wc_format_decimal( $order->get_total_discount(), 2 ),
				'cart_discount'             => wc_format_decimal( 0, 2 ),
				'order_discount'            => wc_format_decimal( 0, 2 ),
				'shipping_methods'          => $order->get_shipping_method(),
				'payment_details' => array(
					'method_id'    => $order->get_payment_method(),
					'method_title' => $order->get_payment_method_title(),
					'paid'         => ! is_null( $order->get_date_paid() ),
				),
				'billing_address' => array(
					'first_name' => $order->get_billing_first_name(),
					'last_name'  => $order->get_billing_last_name(),
					'company'    => $order->get_billing_company(),
					'address_1'  => $order->get_billing_address_1(),
					'address_2'  => $order->get_billing_address_2(),
					'city'       => $order->get_billing_city(),
					'state'      => $order->get_billing_state(),
					'postcode'   => $order->get_billing_postcode(),
					'country'    => $order->get_billing_country(),
					'email'      => $order->get_billing_email(),
					'phone'      => $order->get_billing_phone(),
				),
				'shipping_address' => array(
					'first_name' => $order->get_shipping_first_name(),
					'last_name'  => $order->get_shipping_last_name(),
					'company'    => $order->get_shipping_company(),
					'address_1'  => $order->get_shipping_address_1(),
					'address_2'  => $order->get_shipping_address_2(),
					'city'       => $order->get_shipping_city(),
					'state'      => $order->get_shipping_state(),
					'postcode'   => $order->get_shipping_postcode(),
					'country'    => $order->get_shipping_country(),
				),
				'note'                      => $order->get_customer_note(),
				'customer_ip'               => $order->get_customer_ip_address(),
				'customer_user_agent'       => $order->get_customer_user_agent(),
				'customer_id'               => $order->get_user_id(),
				'view_order_url'            => $order->get_view_order_url(),
				'line_items'                => array(),
				'shipping_lines'            => array(),
				'tax_lines'                 => array(),
				'fee_lines'                 => array(),
				'coupon_lines'              => array(),
			);

			// add line items
			foreach ( $order->get_items() as $item_id => $item ) {
				$product                    = $item->get_product();
				$order_data['line_items'][] = array(
					'id'         => $item_id,
					'subtotal'   => wc_format_decimal( $order->get_line_subtotal( $item ), 2 ),
					'total'      => wc_format_decimal( $order->get_line_total( $item ), 2 ),
					'total_tax'  => wc_format_decimal( $order->get_line_tax( $item ), 2 ),
					'price'      => wc_format_decimal( $order->get_item_total( $item ), 2 ),
					'quantity'   => $item->get_quantity(),
					'tax_class'  => $item->get_tax_class(),
					'name'       => $item->get_name(),
					'product_id' => $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id(),
					'sku'        => is_object( $product ) ? $product->get_sku() : null,
				);
			}

			// add shipping
			foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
				$order_data['shipping_lines'][] = array(
					'id'           => $shipping_item_id,
					'method_id'    => $shipping_item->get_method_id(),
					'method_title' => $shipping_item->get_name(),
					'total'        => wc_format_decimal( $shipping_item->get_total(), 2 ),
				);
			}

			// add taxes
			foreach ( $order->get_tax_totals() as $tax_code => $tax ) {
				$order_data['tax_lines'][] = array(
					'code'     => $tax_code,
					'title'    => $tax->label,
					'total'    => wc_format_decimal( $tax->amount, 2 ),
					'compound' => (bool) $tax->is_compound,
				);
			}

			// add fees
			foreach ( $order->get_fees() as $fee_item_id => $fee_item ) {
				$order_data['fee_lines'][] = array(
					'id'        => $fee_item_id,
					'title'     => $fee_item->get_name(),
					'tax_class' => $fee_item->get_tax_class(),
					'total'     => wc_format_decimal( $order->get_line_total( $fee_item ), 2 ),
					'total_tax' => wc_format_decimal( $order->get_line_tax( $fee_item ), 2 ),
				);
			}

			// add coupons
			foreach ( $order->get_items( 'coupon' ) as $coupon_item_id => $coupon_item ) {
				$order_data['coupon_lines'][] = array(
					'id'     => $coupon_item_id,
					'code'   => $coupon_item->get_code(),
					'amount' => wc_format_decimal( $coupon_item->get_discount(), 2 ),
				);
			}
		}

		$response = array(
			'data' => $order_data,
			'wp_version' => get_bloginfo( 'version' ),
			'wc_version' => defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : null,
			'kips_sync_version' => defined('KIPS_PLUGIN_VERSION') ? KIPS_PLUGIN_VERSION : null,
		);

		return $response;
  }
  
}