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

    /**
     * Tests creating an order with shipping on the V3 API
     */
    public function test_correct_taxes_with_shipping_api_v3() {

        wp_set_current_user( $this->user );
        $product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

        TaxJar_Shipping_Helper::create_simple_flat_rate( 5 );

        $request = new WP_REST_Request( 'POST', '/wc/v3/orders' );
        $request->set_body_params(
            array(
                'payment_method'       => 'bacs',
                'payment_method_title' => 'Direct Bank Transfer',
                'set_paid'             => true,
                'billing'              => array(
                    'first_name' => 'John',
                    'last_name'  => 'Doe',
                    'address_1'  => '969 Market',
                    'address_2'  => '',
                    'city'       => 'Greenwood Village',
                    'state'      => 'CO',
                    'postcode'   => '80111',
                    'country'    => 'US',
                    'email'      => 'john.doe@example.com',
                    'phone'      => '(555) 555-5555',
                ),
                'shipping'             => array(
                    'first_name' => 'John',
                    'last_name'  => 'Doe',
                    'address_1'  => '969 Market',
                    'address_2'  => '',
                    'city'       => 'Greenwood Village',
                    'state'      => 'CO',
                    'postcode'   => '80111',
                    'country'    => 'US',
                ),
                'line_items'           => array(
                    array(
                        'product_id' => $product,
                        'quantity'   => 1
                    )
                ),
                'shipping_lines'       => array(
                    array(
                        'method_id'    => 'flat_rate',
                        'method_title' => 'Flat rate',
                        'total'        => '5',
                    ),
                ),
            )
        );

        $response = $this->server->dispatch( $request );
        $data     = $response->get_data();
        $order    = wc_get_order( $data['id'] );

        $this->assertEquals( 201, $response->get_status() );
        $this->assertEquals( $data['total_tax'], 1.09, '', 0.01 );
        $this->assertEquals( $data['cart_tax'], 0.73, '', 0.01 );
        $this->assertEquals( $data['shipping_tax'], 0.36, '', 0.01 );

        foreach ( $data['line_items'] as $key => $item ) {
            $this->assertEquals( $item['total_tax'], 0.73, '', 0.01 );
        }

        TaxJar_Shipping_Helper::delete_simple_flat_rate();
    }

}