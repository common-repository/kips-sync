<?php

class Kips {
	const KIPS_API_HOST = 'api.kips.io';
  const KIPS_API_PORT = 80;
  
  /**
	 * The single instance of the class.
	 * @var Kips
	 */
  protected static $_instance = null;

  /**
	 * Kips Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
  }
  
  /**
	 * Define Kips Constants.
	 */
	private function define_constants() {
		$upload_dir = wp_upload_dir( null, false );

		$this->define( 'KIPS_ABSPATH', dirname( KIPS_PLUGIN_FILE ) . '/' );
		$this->define( 'KIPS_PLUGIN_BASENAME', plugin_basename( KIPS_PLUGIN_FILE ) );
		$this->define( 'KIPS_VERSION', $this->version );
		$this->define( 'KIPS_LOG_DIR', $upload_dir['basedir'] . '/kips-logs/' );
  }
  
  /**
	 * Include required core files used in admin and on the frontend.
	 */
	private function includes() {
    /**
		 * REST API.
		 */
    include_once KIPS_ABSPATH . 'includes/class.kips-rest-api.php';
    
    $this->api = new Kips_API();
  }
  
  /**
	 * Main Kips Instance.
	 *
	 * Ensures only one instance of Kips is loaded or can be loaded.
	 * @static
	 * @return Kips - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

  /**
	 * Initializes WordPress hooks
	 */
	public static function init_hooks() {
    add_action( 'admin_init', array(self::$_instance, 'kips_settings_init') );
    add_action( 'admin_menu', array(self::$_instance, 'kips_options_page') );
    add_action( 'wp_footer', array(self::$_instance, 'kips_add_javascript') );
  }

  /**
	 * Define constant if not already set.
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
  }
  
  public static function kips_add_javascript() {
    $options = get_option( 'kips_options' );
    $kips_client_javascript_url = esc_attr( $options['kips_client_javascript_url'] );
    if (!empty($kips_client_javascript_url)) {
    ?>
    <script type="text/javascript" src="<?php echo $kips_client_javascript_url; ?>"></script>
    <?php
    }
  }

  public function kips_settings_init() {
    // Register a new setting for "kips" page
    register_setting( 'kips', 'kips_options' );
    
    // Register a new section in the "kips" page
    add_settings_section(
      'kips_section_developers',
      __( 'Paramètres de connexion', 'kips' ),
      array( $this , 'kips_section_developers_cb'),
      'kips'
    );
    
    add_settings_field(
      'kips_api_client_id',
      __( 'Kips API - Client ID', 'kips' ),
      array( $this , 'kips_api_client_id_cb'),
      'kips',
      'kips_section_developers',
      ['label_for' => 'kips_api_client_id',
      'class' => 'kips_row',
      'kips_custom_data' => 'custom',
      ]
    );
    
    add_settings_field(
      'kips_api_client_secret',
      __( 'Kips API - Client SECRET', 'kips' ),
      array( $this , 'kips_api_client_secret_cb'),
      'kips',
      'kips_section_developers',
      ['label_for' => 'kips_api_client_secret',
      'class' => 'kips_row',
      'kips_custom_data' => 'custom',
      ]
    );

    add_settings_field(
      'kips_client_javascript_url',
      null,
      array( $this , 'kips_client_javascript_url_cb'),
      'kips',
      'kips_section_developers',
      ['label_for' => 'kips_client_javascript_url',
      'kips_custom_data' => 'custom',
      ]
    );
  }

  /**
   * Callback functions
   */
  public function kips_section_developers_cb( $args ) {
    ?>
    <p id="<?php echo esc_attr( $args['id'] ); ?>"><?php esc_html_e( 'Renseignez les champs suivants pour permettre la synchronisation de vos produits / commandes avec Kips :', 'kips' ); ?></p>
    <?php
  }

  public function kips_api_client_id_cb( $args ) {
    $options = get_option( 'kips_options' );
    echo '<input type="text" name="kips_options['. esc_attr( $args['label_for'] ).']" value="' . esc_attr( $options['kips_api_client_id'] ) . '">';
  }

  public function kips_api_client_secret_cb( $args ) {
    $options = get_option( 'kips_options' );
    echo '<input type="text" name="kips_options['. esc_attr( $args['label_for'] ).']" value="' . esc_attr( $options['kips_api_client_secret'] ) . '">';
  }

  public function kips_client_javascript_url_cb( $args ) {
    $options = get_option( 'kips_options' );
    echo '<input type="hidden" name="kips_options['. esc_attr( $args['label_for'] ).']" value="' . esc_attr( $options['kips_client_javascript_url'] ) . '">';
  }

  public function kips_options_page() {
    add_menu_page(
      'Kips',
      'Kips Options',
      'manage_options',
      'kips',
      array( $this, 'kips_options_page_html')
    );
  }

  public function getJSfile($current_options) {

    $kips_api_client_id = esc_attr( $current_options['kips_api_client_id'] );
    $kips_api_client_secret = esc_attr( $current_options['kips_api_client_secret'] );

    $data = array(
      'api_client_id' => $kips_api_client_id,
      'api_client_secret' => $kips_api_client_secret,
      'wp_version' => get_bloginfo( 'version' ),
      'wc_version' => defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : null,
      'kips_sync_version' => defined('KIPS_PLUGIN_VERSION') ? KIPS_PLUGIN_VERSION : null,
    );

    $ch = curl_init("https://kips.io/api/clients/init");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

		//execute post
    $result = curl_exec($ch);
    
    return json_decode($result, true);
  }

  public function kips_options_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }
    
    // Wordpress will add the "settings-updated" $_GET parameter to the url
    if ( isset( $_GET['settings-updated'] ) ) {
      add_settings_error( 'kips_messages', 'kips_message', __( 'Les paramètres ont bien été mis à jour.', 'kips' ), 'updated' );
      // get the kips client javascript url from kips
      $current_options = get_option( 'kips_options' );
      $kips_data = $this->getJSfile();
      if (!empty($kips_data) && isset($kips_data['javascript_file']) && !empty($kips_data['javascript_file'])) {
        $current_options['kips_client_javascript_url'] = $kips_data['javascript_file'];
      } else {
        $current_options['kips_client_javascript_url'] = '';
      }

      // then update the javascript url option
      update_option( 'kips_options', $current_options);
    }
    
    settings_errors( 'kips_messages' );
  ?>
    <div class="wrap">
      <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
      <form action="options.php" method="POST">
        <?php
          // output security fields for the registered setting "kips"
          settings_fields( 'kips' );
          // output setting sections and their fields
          // (sections are registered for "kips", each field is registered to a specific section)
          do_settings_sections( 'kips' );
          submit_button( 'Enregistrer' );
        ?>
      </form>
    </div>
  <?php
  }

  /**
	 * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
	 * @static
	 */
	public static function plugin_activation() {
	}

	/**
	 * Removes all connection options
	 * @static
	 */
	public static function plugin_deactivation( ) {
	}
}
