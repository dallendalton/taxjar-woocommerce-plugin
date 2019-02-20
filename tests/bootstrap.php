<?php
class TaxJar_WC_Unit_Tests_Bootstrap {

	protected static $instance = null;

	public $wp_tests_dir;
	public $tests_dir;
	public $plugins_dir;
	public $test_wp_dir;
	public $api_token;

	public function __construct() {
		ini_set( 'display_errors', 'on' );
		error_reporting( E_ALL );

		$this->tests_dir    = dirname( __FILE__ );
		$this->plugin_dir   = __DIR__ . '/../../';
		$this->wp_tests_dir = !empty( getenv('WP_TESTS_DIR' ) ) ? getenv('WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib/';

		$this->api_token = getenv( 'TAXJAR_API_TOKEN' );

        require_once $this->wp_tests_dir . 'includes/functions.php';

        // load WC
        tests_add_filter( 'muplugins_loaded', array( $this, 'load_wc' ) );

        // install WC
        tests_add_filter( 'setup_theme', array( $this, 'install_wc' ) );

		$this->includes();

		$this->setup();
	}

	public function includes() {

		// load the WP testing environment
		require_once $this->wp_tests_dir . 'includes/bootstrap.php';

		// load taxjar core
		require_once $this->plugin_dir . 'taxjar-woocommerce-plugin/taxjar-woocommerce.php';

		// load framework
		require_once $this->tests_dir . '/framework/woocommerce-helper.php';
		require_once $this->tests_dir . '/framework/coupon-helper.php';
		require_once $this->tests_dir . '/framework/customer-helper.php';
		require_once $this->tests_dir . '/framework/product-helper.php';
		require_once $this->tests_dir . '/framework/shipping-helper.php';
        require_once $this->tests_dir . '/framework/wp-http-testcase.php';
        require_once $this->tests_dir . '/framework/api-helper.php';

		// load woocommerce subscriptions
		update_option( 'active_plugins', array( 'woocommerce/woocommerce.php' ) );
		update_option( 'woocommerce_db_version', WC_VERSION );
		TaxJar_Woocommerce_Helper::prepare_woocommerce();
		require_once $this->plugin_dir . 'woocommerce-subscriptions/woocommerce-subscriptions.php';
	}

	public function setup() {
		update_option( 'woocommerce_taxjar-integration_settings',
			array(
				'api_token' => $this->api_token,
				'enabled' => 'yes',
				'taxjar_download' => 'yes',
				'store_postcode' => '80111',
				'store_city' => 'Greenwood Village',
				'store_street' => '6060 S Quebec St',
				'debug' => 'yes',
			)
		);

		update_option( 'woocommerce_default_country', 'US:CO' );
        update_option( 'woocommerce_calc_shipping', 'yes' );
        update_option( 'woocommerce_coupons_enabled', 'yes' );
        update_option( 'woocommerce_currency', 'USD');

		do_action( 'plugins_loaded' );
	}

    public function load_wc() {
        define( 'WC_TAX_ROUNDING_MODE', 'auto' );
        define( 'WC_USE_TRANSACTIONS', false );
        require_once $this->plugin_dir . 'woocommerce/woocommerce.php';
    }

    /**
     * Install WooCommerce after the test environment and WC have been loaded.
     *
     */
    public function install_wc() {

        // Clean existing install first.
        define( 'WP_UNINSTALL_PLUGIN', true );
        define( 'WC_REMOVE_ALL_DATA', true );
        include $this->plugin_dir . 'woocommerce/uninstall.php';

        WC_Install::install();

        // Reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374
        if ( version_compare( $GLOBALS['wp_version'], '4.7', '<' ) ) {
            $GLOBALS['wp_roles']->reinit();
        } else {
            $GLOBALS['wp_roles'] = null; // WPCS: override ok.
            wp_roles();
        }

        echo esc_html( 'Installing WooCommerce...' . PHP_EOL );
    }

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}

TaxJar_WC_Unit_Tests_Bootstrap::instance();
