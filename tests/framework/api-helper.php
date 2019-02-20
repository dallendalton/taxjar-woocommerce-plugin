<?php
class TaxJar_API_Helper {

    public static function build_api_v3_request( $parameters = array() ) {

        $request = new WP_REST_Request( 'POST', '/wc/v3/orders' );

        $product_id = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

        $default_parameters = array(
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
                    'product_id' => $product_id,
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
        );

        $parameters = array_replace_recursive( $default_parameters, $parameters );
        $request->set_body_params( $parameters );

        return $request;
    }

}