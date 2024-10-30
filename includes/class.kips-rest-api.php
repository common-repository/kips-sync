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

class Kips_API {

	const KIPS_API_NAMESPACE = 'kips/v';
	const KIPS_API_VERSION = '1';

	/**
	 * Kips API Constructor.
	 */
	public function __construct() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			// The REST API wasn't integrated into core until 4.4, and we support 4.0+ (for now).
			return false;
		}

		$this->rest_api_includes();

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
    add_action( 'woocommerce_order_status_changed' , array( $this, 'woo_order_status_change_kips'), 10, 3);
	}

	/**
	 * Include REST API classes.
	 *
	 * @since 1.0.0
	 */
	private function rest_api_includes() {
		// Exception handler.
		include_once dirname( __FILE__ ) . '/api/class.kips-rest-products-controller.php';
		include_once dirname( __FILE__ ) . '/api/class.kips-rest-orders-controller.php';
		include_once dirname( __FILE__ ) . '/api/class.kips-rest-conversions-controller.php';
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		$controllers = array(
			'Kips_API_Products_Controller',
			'Kips_API_Orders_Controller',
			'Kips_API_Conversions_Controller',
		);

		foreach ( $controllers as $controller ) {
			$this->$controller = new $controller();
			$this->$controller->register_routes();
		}
	}
  
  public function woo_order_status_change_kips($order_id, $old_status, $new_status) {
		$order = new WC_Order( $order_id );
		$order->old_status = $old_status;
		$order->new_status = $new_status;

		$options = get_option( 'kips_options' );
		$kips_api_client_id = esc_attr( $options['kips_api_client_id'] );
		$kips_api_client_secret = esc_attr( $options['kips_api_client_secret'] );
		$kips_client_javascript_url = esc_attr( $options['kips_client_javascript_url'] );

		$data = array(
      'data' => $order ? $order : null,
      'kips_api_client_id' => $kips_api_client_id ? $kips_api_client_id : null,
      'kips_api_client_secret' => $kips_api_client_secret ? $kips_api_client_secret : null,
      'kips_client_javascript_url' => $kips_client_javascript_url ? $kips_client_javascript_url : null,
      'wp_version' => get_bloginfo( 'version' ),
			'wc_version' => defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : null,
			'kips_sync_version' => defined('KIPS_PLUGIN_VERSION') ? KIPS_PLUGIN_VERSION : null,
		);

		$ch = curl_init("https://feed.kips.io/shop/$kips_api_client_id/order/new");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($data))
				// 'Kips-Client-Api-Keys: ' . base64_encode($kips_api_client_id . ":" .$kips_api_client_secret)
		);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

		//execute post
		$result = curl_exec($ch);

		//close connection
		curl_close($ch);
	}

}