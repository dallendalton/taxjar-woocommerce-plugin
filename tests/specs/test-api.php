<?php

class TJ_WC_Tests_API extends WP_HTTP_TestCase {

    protected $server;

    protected $factory;

    function setUp() {

        parent::setUp();

        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        $this->server   = $wp_rest_server;
        do_action( 'rest_api_init' );

        $this->factory = new WP_UnitTest_Factory();
        $this->endpoint = new WC_REST_Orders_Controller();
        $this->user     = $this->factory->user->create(
            array(
                'role' => 'administrator',
            )
        );

        TaxJar_Woocommerce_Helper::prepare_woocommerce();
        $this->tj = new WC_Taxjar_Integration();

        // Reset shipping origin
        TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
            'store_country' => 'US',
            'store_state' => 'CO',
            'store_postcode' => '80111',
            'store_city' => 'Greenwood Village',
        ) );

    }

    function tearDown() {
        parent::tearDown();
    }

}