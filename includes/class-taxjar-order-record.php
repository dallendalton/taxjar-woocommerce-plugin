<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Order_Record extends TaxJar_Record {

	function load_object() {
		$this->object = wc_get_order( $this->get_record_id() );
	}

	public function get_record_type() {
		return 'order';
	}

	public function sync() {
		$error_responses = array( 400, 401, 403, 404, 405, 406, 410, 429, 500, 503 );
		$success_responses = array( 200, 201 );

		if ( $this->get_status() == 'new' ) {
			$response = $this->create_in_taxjar();
			if ( isset( $response['response']['code'] ) && $response['response']['code'] == 422 ) {
				$response = $this->update_in_taxjar();
			}
		} else {
			$response = $this->update_in_taxjar();
			if ( isset( $response['response']['code'] ) && $response['response']['code'] == 404 ) {
				$response = $this->create_in_taxjar();
			}
		}

		if ( is_wp_error( $response ) ) {
			// handle wordpress error and add message to log here
			$this->sync_failure();
			return false;
		}

		if ( ! isset( $response[ 'response' ][ 'code' ] ) ) {
			$this->sync_failure();
			return false;
		}

		if ( in_array( $response[ 'response' ][ 'code' ], $error_responses ) ) {
			$this->sync_failure();
			return false;
		}

		if ( in_array( $response[ 'response' ][ 'code' ], $success_responses ) ) {
			$this->sync_success();
			return true;
		}

		// handle any unexpected responses
		$this->sync_failure();
		return false;
	}

	public function sync_success() {
		$current_datetime =  gmdate( 'Y-m-d H:i:s' );
		$this->set_processed_datetime( $current_datetime );
		$this->set_status( 'completed' );
		$this->save();

		// prevent creating new record in queue when updating a successfully synced order
		remove_action( 'woocommerce_update_order', array( 'WC_Taxjar_Transaction_Sync', 'order_updated' ) );
		$this->object->update_meta_data( '_taxjar_last_sync', $current_datetime );
		$this->object->save();
		add_action( 'woocommerce_update_order', array( 'WC_Taxjar_Transaction_Sync', 'order_updated' ) );

		//update_post_meta( $this->get_record_id(), '_taxjar_last_sync', $current_datetime );
	}

	public function sync_failure() {
		$retry_count = $this->get_retry_count() + 1;
		$this->set_retry_count( $retry_count );
		if ( $this->get_retry_count() >= 3 ) {
			$this->set_status( 'failed' );
		} else {
			$this->set_batch_id( 0 );
		}

		$this->save();
	}

	public function create_in_taxjar() {
		$data = $this->get_order_data();
		$url = self::API_URI . 'transactions/orders';
		$data[ 'provider' ] = 'woo';
		$body = wp_json_encode( $data );

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Token token="' . $this->taxjar_integration->settings['api_token'] . '"',
				'Content-Type' => 'application/json',
			),
			'user-agent' => $this->taxjar_integration->ua,
			'body' => $body,
		) );

		return $response;
	}

	public function update_in_taxjar(){
		$order_id = $this->get_transaction_id();
		$data = $this->get_order_data();

		$url = self::API_URI . 'transactions/orders/' . $order_id;
		$data[ 'provider' ] = 'woo';
		$body = wp_json_encode( $data );

		$response = wp_remote_request( $url, array(
			'method' => 'PUT',
			'headers' => array(
				'Authorization' => 'Token token="' . $this->taxjar_integration->settings['api_token'] . '"',
				'Content-Type' => 'application/json',
			),
			'user-agent' => $this->taxjar_integration->ua,
			'body' => $body,
		) );

		return $response;
	}

	public function delete_in_taxjar(){
		$order_id = $this->get_transaction_id();
		$url = self::API_URI . 'transactions/orders/' . $order_id;
		$data = array(
			'transaction_id' => $order_id,
			'provider' => 'woo'
		);
		$body = wp_json_encode( $data );

		$response = wp_remote_request( $url, array(
			'method' => 'DELETE',
			'headers' => array(
				'Authorization' => 'Token token="' . $this->taxjar_integration->settings['api_token'] . '"',
				'Content-Type' => 'application/json',
			),
			'user-agent' => $this->taxjar_integration->ua,
			'body' => $body,
		) );

		return $response;
	}

	public function get_order_data() {
		$store_settings   = $this->taxjar_integration->get_store_settings();
		$from_country     = $store_settings['country'];
		$from_state       = $store_settings['state'];
		$from_zip         = $store_settings['postcode'];
		$from_city        = $store_settings['city'];
		$from_street      = $store_settings['street'];

		$amount = $this->object->get_total() - $this->object->get_total_tax();

		$order_data = array(
			'transaction_id' => $this->get_transaction_id(),
			'transaction_date' => $this->object->get_date_created()->date( DateTime::ISO8601 ),
			'from_country' => $from_country,
			'from_zip' => $from_zip,
			'from_state' => $from_state,
			'from_city' => $from_city,
			'from_street' => $from_street,
			'to_country' => $this->object->get_shipping_country(),
			'to_zip' => $this->object->get_shipping_postcode(),
			'to_state' => $this->object->get_shipping_state(),
			'to_city' => $this->object->get_shipping_city(),
			'to_street' => $this->object->get_shipping_address_1(),
			'amount' => $amount,
			'shipping' => $this->object->get_shipping_total(),
			'sales_tax' => $this->object->get_total_tax(),
			'line_items' => $this->get_line_items(),
		);

		$customer_id = $this->object->get_customer_id();
		if ( $customer_id ) {
			$order_data[ 'customer_id' ] = $customer_id;
		}

		return $order_data;
	}

	public function get_line_items() {
		$line_items_data = array();
		$items = $this->object->get_items();

		if ( ! empty( $items ) ) {
			foreach( $items as $item ) {
				$product = $item->get_product();

				$quantity = $item->get_quantity();
				$unit_price = $item->get_subtotal() / $quantity;
				$discount = $item->get_subtotal() - $item->get_total();

				$tax_class = explode( '-', $product->get_tax_class() );
				$tax_code = '';
				if ( isset( $tax_class ) && is_numeric( end( $tax_class ) ) ) {
					$tax_code = end( $tax_class );
				}

				if ( ! $product->is_taxable() || 'zero-rate' == sanitize_title( $product->get_tax_class() ) ) {
					$tax_code = '99999';
				}

				$line_items_data[] = array(
					'id' => $item->get_id(),
					'quantity' => $quantity,
					'product_identifier' => $product->get_sku(),
					'description' => $product->get_name(),
					'product_tax_code' => $tax_code,
					'unit_price' => $unit_price,
					'discount' => $discount,
					'sales_tax' => $item->get_total_tax(),
				);
			}
		}

		return $line_items_data;
	}

	public function get_transaction_id() {
		return apply_filters( 'taxjar_get_order_transaction_id', $this->object->get_id() );
	}
}