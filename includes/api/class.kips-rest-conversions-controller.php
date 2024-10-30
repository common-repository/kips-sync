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

class Kips_API_Conversions_Controller {

	const KIPS_API_NAMESPACE = 'kips/v';
	const KIPS_API_VERSION = '1';

  /**
	 * Route base.
	 *
	 * @var string
	 */
  protected $rest_base = 'conversions';

	/**
	 * Kips API Constructor.
	 */
	public function __construct() {

  }
  
  public function register_routes() {
		$base = self::KIPS_API_NAMESPACE . self::KIPS_API_VERSION;
    
    register_rest_route( $base, '/' . $this->rest_base . '/new', array(
      array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => array( $this, 'create_conversion' )
      )
    ));
  }
  
  public function create_conversion( WP_REST_Request $request ) {
    
    $req = @json_decode(file_get_contents('php://input'), true);
			
    if (!isset($req['data']) || empty($req['data'])) {
      return array(
        'error' => 'No content',
        'message' => "data can't be null or empty."
      );
    }

    global $woocommerce;

    $data = $req['data'];

    $order = wc_create_order();

    $query_args['post_type'] = array( 'product', 'product_variation' );
    $query_args = $this->merge_query_args( $query_args, $args );

    foreach ($data['order']['products'] as $p) {
      if(isset($p['product_id']) && !empty($p['product_id'])) {
        $query_args['p'] = $p['product_id'];
      }
  
      $query_args['posts_per_page'] = 1;

      $query = new WP_Query( $query_args );
      
      $products = array();
  
      foreach ( $query->posts as $prdt ) {
        $order->add_product( wc_get_product((int) $prdt->ID) );
      }
    }

    $address = [
      'first_name' => $data['customer']['firstname'],
      'last_name'  => $data['customer']['lastname'],
      'company'    => $data['customer']['company'],
      'email'      => $data['customer']['email'],
      'phone'      => $data['customer']['phone'],
      'address_1'  => $data['customer']['address_1'],
      'address_2'  => $data['customer']['address_2'],
      'city'       => $data['customer']['city'],
      'state'      => $data['customer']['state'],
      'postcode'   => $data['customer']['postal_code'],
      'country'    => $data['customer']['country']
    ];

    $order->set_address($address, 'billing');
    $order->set_address($address, 'shipping');

    $order->calculate_totals();
    $order->update_status( 'on-hold', 'New order from Kips.');

    $new_order = $this->get_order((int) $order->get_order_number() );

		$response = array(
			'data' => $new_order,
			'wp_version' => get_bloginfo( 'version' ),
			'wc_version' => defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : null,
			'kips_sync_version' => defined('KIPS_PLUGIN_VERSION') ? KIPS_PLUGIN_VERSION : null,
		);

		return $response;
  }

   /**
	 * Get the product for the given ID
	 *
	 * @since 2.1
	 * @param int $id the product ID
	 * @param string $fields
	 * @return array|WP_Error
	 */
	public function get_product( $id, $fields = null ) {

		$product = wc_get_product( $id );

		// add data that applies to every product type
		$product_data = $this->get_product_data( $product );

		// add variations to variable products
		if ( $product->is_type( 'variable' ) && $product->has_child() ) {
			$product_data['variations'] = $this->get_variation_data( $product );
		}

		// add the parent product data to an individual variation
		if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
			$product_data['parent'] = $this->get_product_data( $product->get_parent_id() );
		}

		// Add grouped products data
		if ( $product->is_type( 'grouped' ) && $product->has_child() ) {
			$product_data['grouped_products'] = $this->get_grouped_products_data( $product );
		}

		if ( $product->is_type( 'simple' ) ) {
			$parent_id = $product->get_parent_id();
			if ( ! empty( $parent_id ) ) {
				$_product               = wc_get_product( $parent_id );
				$product_data['parent'] = $this->get_product_data( $_product );
			}
		}

		return array( 'product' => apply_filters( 'woocommerce_api_product_response', $product_data, $product, $fields, $this->server ) );
	}
	
	/**
	 * Get an individual variation's data
	 *
	 * @since 2.1
	 * @param WC_Product $product
	 * @return array
	 */
	private function get_variation_data( $product ) {
		$variations = array();

		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );

			if ( ! $variation || ! $variation->exists() ) {
				continue;
			}

			$variations[] = array(
				'id'                => $variation->get_id(),
				'created_at'        => $variation->get_date_created(),
				'updated_at'        => $variation->get_date_modified(),
				'downloadable'      => $variation->is_downloadable(),
				'virtual'           => $variation->is_virtual(),
				'permalink'         => $variation->get_permalink(),
				'sku'               => $variation->get_sku(),
				'price'             => wc_format_decimal( $variation->get_price(), 2 ),
				'regular_price'     => wc_format_decimal( $variation->get_regular_price(), 2 ),
				'sale_price'        => $variation->get_sale_price() ? wc_format_decimal( $variation->get_sale_price(), 2 ) : null,
				'taxable'           => $variation->is_taxable(),
				'tax_status'        => $variation->get_tax_status(),
				'tax_class'         => $variation->get_tax_class(),
				'stock_quantity'    => (int) $variation->get_stock_quantity(),
				'in_stock'          => $variation->is_in_stock(),
				'backordered'       => $variation->is_on_backorder(),
				'purchaseable'      => $variation->is_purchasable(),
				'visible'           => $variation->variation_is_visible(),
				'on_sale'           => $variation->is_on_sale(),
				'weight'            => $variation->get_weight() ? wc_format_decimal( $variation->get_weight(), 2 ) : null,
				'dimensions'        => array(
					'length' => $variation->get_length(),
					'width'  => $variation->get_width(),
					'height' => $variation->get_height(),
					'unit'   => get_option( 'woocommerce_dimension_unit' ),
				),
				'shipping_class'    => $variation->get_shipping_class(),
				'shipping_class_id' => ( 0 !== $variation->get_shipping_class_id() ) ? $variation->get_shipping_class_id() : null,
				'image'             => $this->get_images( $variation ),
				'attributes'        => $this->get_attributes( $variation ),
				'downloads'         => $this->get_downloads( $variation ),
				'download_limit'    => (int) $product->get_download_limit(),
				'download_expiry'   => (int) $product->get_download_expiry(),
			);
		}

		return $variations;
	}
  
  /**
	 * Get standard product data that applies to every product type
	 *
	 * @since 2.1
	 * @param WC_Product|int $product
	 *
	 * @return array
	 */
	private function get_product_data( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! is_a( $product, 'WC_Product' ) ) {
			return array();
		}

		return array(
			'title'              => $product->get_name(),
			'id'                 => $product->get_id(),
			'created_at'         => $product->get_date_created(),
			'updated_at'         => $product->get_date_modified(),
			'type'               => $product->get_type(),
			'status'             => $product->get_status(),
			'downloadable'       => $product->is_downloadable(),
			'virtual'            => $product->is_virtual(),
			'permalink'          => $product->get_permalink(),
			'sku'                => $product->get_sku(),
			'price'              => $product->get_price(),
			'regular_price'      => $product->get_regular_price(),
			'sale_price'         => $product->get_sale_price() ? $product->get_sale_price() : null,
			'price_html'         => $product->get_price_html(),
			'currency'    		   => get_option('woocommerce_currency'),
			'currency_symbol'    => html_entity_decode(get_woocommerce_currency_symbol(get_option('woocommerce_currency'))),
			'taxable'            => $product->is_taxable(),
			'tax_status'         => $product->get_tax_status(),
			'tax_class'          => $product->get_tax_class(),
			'managing_stock'     => $product->managing_stock(),
			'stock_quantity'     => $product->get_stock_quantity(),
			'in_stock'           => $product->is_in_stock(),
			'backorders_allowed' => $product->backorders_allowed(),
			'backordered'        => $product->is_on_backorder(),
			'sold_individually'  => $product->is_sold_individually(),
			'purchaseable'       => $product->is_purchasable(),
			'featured'           => $product->is_featured(),
			'visible'            => $product->is_visible(),
			'catalog_visibility' => $product->get_catalog_visibility(),
			'on_sale'            => $product->is_on_sale(),
			'product_url'        => $product->is_type( 'external' ) ? $product->get_product_url() : '',
			'button_text'        => $product->is_type( 'external' ) ? $product->get_button_text() : '',
			'weight'             => $product->get_weight() ? $product->get_weight() : null,
			'dimensions'         => array(
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
				'unit'   => get_option( 'woocommerce_dimension_unit' ),
			),
			'shipping_required'  => $product->needs_shipping(),
			'shipping_taxable'   => $product->is_shipping_taxable(),
			'shipping_class'     => $product->get_shipping_class(),
			'shipping_class_id'  => ( 0 !== $product->get_shipping_class_id() ) ? $product->get_shipping_class_id() : null,
			'description'        => wpautop( do_shortcode( $product->get_description() ) ),
			'short_description'  => apply_filters( 'woocommerce_short_description', $product->get_short_description() ),
			'reviews_allowed'    => $product->get_reviews_allowed(),
			'average_rating'     => wc_format_decimal( $product->get_average_rating(), 2 ),
			'rating_count'       => $product->get_rating_count(),
			'related_ids'        => array_map( 'absint', array_values( wc_get_related_products( $product->get_id() ) ) ),
			'upsell_ids'         => array_map( 'absint', $product->get_upsell_ids() ),
			'cross_sell_ids'     => array_map( 'absint', $product->get_cross_sell_ids() ),
			'parent_id'          => $product->get_parent_id(),
			'categories'         => wc_get_object_terms( $product->get_id(), 'product_cat', 'name' ),
			'tags'               => wc_get_object_terms( $product->get_id(), 'product_tag', 'name' ),
			'images'             => $this->get_images( $product ),
			'featured_src'       => wp_get_attachment_url( get_post_thumbnail_id( $product->get_id() ) ),
			'attributes'         => $this->get_attributes( $product ),
			'downloads'          => $this->get_downloads( $product ),
			'download_limit'     => $product->get_download_limit(),
			'download_expiry'    => $product->get_download_expiry(),
			'download_type'      => 'standard',
			'purchase_note'      => wpautop( do_shortcode( wp_kses_post( $product->get_purchase_note() ) ) ),
			'total_sales'        => $product->get_total_sales(),
			'variations'         => array(),
			'parent'             => array(),
			'grouped_products'   => array(),
			'menu_order'         => $this->get_product_menu_order( $product ),
		);
  }
  
  /**
	 * Get the images for a product or product variation
	 *
	 * @since 2.1
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	private function get_images( $product ) {
		$images        = $attachment_ids = array();
		$product_image = $product->get_image_id();

		// Add featured image.
		if ( ! empty( $product_image ) ) {
			$attachment_ids[] = $product_image;
		}

		// add gallery images.
		$attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );

		// Build image data.
		foreach ( $attachment_ids as $position => $attachment_id ) {

			$attachment_post = get_post( $attachment_id );

			if ( is_null( $attachment_post ) ) {
				continue;
			}

			$attachment = wp_get_attachment_image_src( $attachment_id, 'full' );

			if ( ! is_array( $attachment ) ) {
				continue;
			}

			$images[] = array(
				'id'         => (int) $attachment_id,
				'created_at' => $attachment_post->post_date_gmt,
				'updated_at' => $attachment_post->post_modified_gmt,
				'src'        => current( $attachment ),
				'title'      => get_the_title( $attachment_id ),
				'alt'        => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'position'   => $position,
			);
		}

		// Set a placeholder image if the product has no images set.
		if ( empty( $images ) ) {

			$images[] = array(
				'id'         => 0,
				'created_at' => time(), // default to now
				'updated_at' => time(),
				'src'        => wc_placeholder_img_src(),
				'title'      => __( 'Placeholder', 'woocommerce' ),
				'alt'        => __( 'Placeholder', 'woocommerce' ),
				'position'   => 0,
			);
		}

		return $images;
  }
  
  /**
	 * Get the attributes for a product or product variation
	 *
	 * @since 2.1
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	private function get_attributes( $product ) {

		$attributes = array();

		if ( $product->is_type( 'variation' ) ) {

			// variation attributes
			foreach ( $product->get_variation_attributes() as $attribute_name => $attribute ) {

				// taxonomy-based attributes are prefixed with `pa_`, otherwise simply `attribute_`
				$attributes[] = array(
					'name'   => ucwords( str_replace( 'attribute_', '', str_replace( 'pa_', '', $attribute_name ) ) ),
					'option' => $attribute,
				);
			}
		} else {

			foreach ( $product->get_attributes() as $attribute ) {
				$attributes[] = array(
					'name'      => ucwords( str_replace( 'pa_', '', $attribute['name'] ) ),
					'position'  => $attribute['position'],
					'visible'   => (bool) $attribute['is_visible'],
					'variation' => (bool) $attribute['is_variation'],
					'options'   => $this->get_attribute_options( $product->get_id(), $attribute ),
				);
			}
		}

		return $attributes;
	}
	
	/**
	 * Get attribute options.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $attribute  Attribute data.
	 * @return array
	 */
	protected function get_attribute_options( $product_id, $attribute ) {
		if ( isset( $attribute['is_taxonomy'] ) && $attribute['is_taxonomy'] ) {
			return wc_get_product_terms(
				$product_id, $attribute['name'], array(
					'fields' => 'names',
				)
			);
		} elseif ( isset( $attribute['value'] ) ) {
			return array_map( 'trim', explode( '|', $attribute['value'] ) );
		}

		return array();
	}
  
  /**
	 * Get the downloads for a product or product variation
	 *
	 * @since 2.1
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	private function get_downloads( $product ) {

		$downloads = array();

		if ( $product->is_downloadable() ) {

			foreach ( $product->get_downloads() as $file_id => $file ) {

				$downloads[] = array(
					'id'   => $file_id, // do not cast as int as this is a hash
					'name' => $file['name'],
					'file' => $file['file'],
				);
			}
		}

		return $downloads;
  }
  
  /**
	 * Get product menu order.
	 *
	 * @deprecated 3.0.0
	 * @param WC_Product $product Product instance.
	 * @return int
	 */
	protected function get_product_menu_order( $product ) {
		return $product->get_menu_order();
	}
  
  /**
	 * Add common request arguments to argument list before WP_Query is run
	 *
	 * @since 2.1
	 * @param array $base_args required arguments for the query (e.g. `post_type`, etc)
	 * @param array $request_args arguments provided in the request
	 * @return array
	 */
	protected function merge_query_args( $base_args, $request_args ) {

		$args = array();

		// date
		if ( ! empty( $request_args['created_at_min'] ) || ! empty( $request_args['created_at_max'] ) || ! empty( $request_args['updated_at_min'] ) || ! empty( $request_args['updated_at_max'] ) ) {

			$args['date_query'] = array();

			// resources created after specified date
			if ( ! empty( $request_args['created_at_min'] ) ) {
				$args['date_query'][] = array( 'column' => 'post_date_gmt', 'after' => $this->server->parse_datetime( $request_args['created_at_min'] ), 'inclusive' => true );
			}

			// resources created before specified date
			if ( ! empty( $request_args['created_at_max'] ) ) {
				$args['date_query'][] = array( 'column' => 'post_date_gmt', 'before' => $this->server->parse_datetime( $request_args['created_at_max'] ), 'inclusive' => true );
			}

			// resources updated after specified date
			if ( ! empty( $request_args['updated_at_min'] ) ) {
				$args['date_query'][] = array( 'column' => 'post_modified_gmt', 'after' => $this->server->parse_datetime( $request_args['updated_at_min'] ), 'inclusive' => true );
			}

			// resources updated before specified date
			if ( ! empty( $request_args['updated_at_max'] ) ) {
				$args['date_query'][] = array( 'column' => 'post_modified_gmt', 'before' => $this->server->parse_datetime( $request_args['updated_at_max'] ), 'inclusive' => true );
			}
		}

		// search
		if ( ! empty( $request_args['q'] ) ) {
			$args['s'] = $request_args['q'];
		}

		// resources per response
		if ( ! empty( $request_args['limit'] ) ) {
			$args['posts_per_page'] = $request_args['limit'];
		}

		// resource offset
		if ( ! empty( $request_args['offset'] ) ) {
			$args['offset'] = $request_args['offset'];
		}

		// order (ASC or DESC, ASC by default)
		if ( ! empty( $request_args['order'] ) ) {
			$args['order'] = $request_args['order'];
		}

		// orderby
		if ( ! empty( $request_args['orderby'] ) ) {
			$args['orderby'] = $request_args['orderby'];

			// allow sorting by meta value
			if ( ! empty( $request_args['orderby_meta_key'] ) ) {
				$args['meta_key'] = $request_args['orderby_meta_key'];
			}
		}

		// allow post status change
		if ( ! empty( $request_args['post_status'] ) ) {
			$args['post_status'] = $request_args['post_status'];
			unset( $request_args['post_status'] );
		}

		// filter by a list of post id
		if ( ! empty( $request_args['in'] ) ) {
			$args['post__in'] = explode( ',', $request_args['in'] );
			unset( $request_args['in'] );
		}

		// exclude by a list of post id
		if ( ! empty( $request_args['not_in'] ) ) {
			$args['post__not_in'] = explode( ',', $request_args['not_in'] );
			unset( $request_args['not_in'] );
		}

		// resource page
		$args['paged'] = ( isset( $request_args['page'] ) ) ? absint( $request_args['page'] ) : 1;

		$args = apply_filters( 'woocommerce_api_query_args', $args, $request_args );

		return array_merge( $base_args, $args );
	}

	function get_woocommerce_currency_symbol( $currency = '' ) { 
		if ( ! $currency ) { 
				$currency = get_woocommerce_currency(); 
		} 

		$symbols = apply_filters( 'woocommerce_currency_symbols', array( 
				'AED' => 'د.إ',  
				'AFN' => '؋',  
				'ALL' => 'L',  
				'AMD' => 'AMD',  
				'ANG' => 'ƒ',  
				'AOA' => 'Kz',  
				'ARS' => '$',  
				'AUD' => '$',  
				'AWG' => 'ƒ',  
				'AZN' => 'AZN',  
				'BAM' => 'KM',  
				'BBD' => '$',  
				'BDT' => '৳ ',  
				'BGN' => 'лв.',  
				'BHD' => '.د.ب',  
				'BIF' => 'Fr',  
				'BMD' => '$',  
				'BND' => '$',  
				'BOB' => 'Bs.',  
				'BRL' => 'R$',  
				'BSD' => '$',  
				'BTC' => '฿',  
				'BTN' => 'Nu.',  
				'BWP' => 'P',  
				'BYR' => 'Br',  
				'BZD' => '$',  
				'CAD' => '$',  
				'CDF' => 'Fr',  
				'CHF' => 'CHF',  
				'CLP' => '$',  
				'CNY' => '¥',  
				'COP' => '$',  
				'CRC' => '₡',  
				'CUC' => '$',  
				'CUP' => '$',  
				'CVE' => '$',  
				'CZK' => 'Kč',  
				'DJF' => 'Fr',  
				'DKK' => 'DKK',  
				'DOP' => 'RD$',  
				'DZD' => 'د.ج',  
				'EGP' => 'EGP',  
				'ERN' => 'Nfk',  
				'ETB' => 'Br',  
				'EUR' => '€',  
				'FJD' => '$',  
				'FKP' => '£',  
				'GBP' => '£',  
				'GEL' => 'ლ',  
				'GGP' => '£',  
				'GHS' => '₵',  
				'GIP' => '£',  
				'GMD' => 'D',  
				'GNF' => 'Fr',  
				'GTQ' => 'Q',  
				'GYD' => '$',  
				'HKD' => '$',  
				'HNL' => 'L',  
				'HRK' => 'Kn',  
				'HTG' => 'G',  
				'HUF' => 'Ft',  
				'IDR' => 'Rp',  
				'ILS' => '₪',  
				'IMP' => '£',  
				'INR' => '₹',  
				'IQD' => 'ع.د',  
				'IRR' => '﷼',  
				'IRT' => 'تومان',  
				'ISK' => 'kr.',  
				'JEP' => '£',  
				'JMD' => '$',  
				'JOD' => 'د.ا',  
				'JPY' => '¥',  
				'KES' => 'KSh',  
				'KGS' => 'сом',  
				'KHR' => '៛',  
				'KMF' => 'Fr',  
				'KPW' => '₩',  
				'KRW' => '₩',  
				'KWD' => 'د.ك',  
				'KYD' => '$',  
				'KZT' => 'KZT',  
				'LAK' => '₭',  
				'LBP' => 'ل.ل',  
				'LKR' => 'රු',  
				'LRD' => '$',  
				'LSL' => 'L',  
				'LYD' => 'ل.د',  
				'MAD' => 'د.م.',  
				'MDL' => 'MDL',  
				'MGA' => 'Ar',  
				'MKD' => 'ден',  
				'MMK' => 'Ks',  
				'MNT' => '₮',  
				'MOP' => 'P',  
				'MRO' => 'UM',  
				'MUR' => '₨',  
				'MVR' => '.ރ',  
				'MWK' => 'MK',  
				'MXN' => '$',  
				'MYR' => 'RM',  
				'MZN' => 'MT',  
				'NAD' => '$',  
				'NGN' => '₦',  
				'NIO' => 'C$',  
				'NOK' => 'kr',  
				'NPR' => '₨',  
				'NZD' => '$',  
				'OMR' => 'ر.ع.',  
				'PAB' => 'B/.',  
				'PEN' => 'S/.',  
				'PGK' => 'K',  
				'PHP' => '₱',  
				'PKR' => '₨',  
				'PLN' => 'zł',  
				'PRB' => 'р.',  
				'PYG' => '₲',  
				'QAR' => 'ر.ق',  
				'RMB' => '¥',  
				'RON' => 'lei',  
				'RSD' => 'дин.',  
				'RUB' => '₽',  
				'RWF' => 'Fr',  
				'SAR' => 'ر.س',  
				'SBD' => '$',  
				'SCR' => '₨',  
				'SDG' => 'ج.س.',  
				'SEK' => 'kr',  
				'SGD' => '$',  
				'SHP' => '£',  
				'SLL' => 'Le',  
				'SOS' => 'Sh',  
				'SRD' => '$',  
				'SSP' => '£',  
				'STD' => 'Db',  
				'SYP' => 'ل.س',  
				'SZL' => 'L',  
				'THB' => '฿',  
				'TJS' => 'ЅМ',  
				'TMT' => 'm',  
				'TND' => 'د.ت',  
				'TOP' => 'T$',  
				'TRY' => '₺',  
				'TTD' => '$',  
				'TWD' => 'NT$',  
				'TZS' => 'Sh',  
				'UAH' => '₴',  
				'UGX' => 'UGX',  
				'USD' => '$',  
				'UYU' => '$',  
				'UZS' => 'UZS',  
				'VEF' => 'Bs F',  
				'VND' => '₫',  
				'VUV' => 'Vt',  
				'WST' => 'T',  
				'XAF' => 'Fr',  
				'XCD' => '$',  
				'XOF' => 'Fr',  
				'XPF' => 'Fr',  
				'YER' => '﷼',  
				'ZAR' => 'R',  
        'ZMW' => 'ZK',
      )); 

		$currency_symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : ''; 

    return apply_filters( 'woocommerce_currency_symbol', $currency_symbol, $currency ); 
    }

    public function get_order($order_id) {
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

      return $order_data;
    }
  
}