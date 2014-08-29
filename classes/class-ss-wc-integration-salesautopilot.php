<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * SalesAutopilot Integration
 *
 * Allows integration with SalesAutopilot eCommerce
 *
 * @class 		SS_WC_Integration_SalesAutopilot
 * @extends		WC_Integration
 * @version		1.0.0
 * @package		WooCommerce SalesAutopilot
 * @author 		Gyorgy Khauth
 */
class SS_WC_Integration_SalesAutopilot extends WC_Integration {
	
	/**
	 * Init and hook in the integration.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {

		if ( !class_exists( 'SalesAutopilotAPI' ) ) {
			include_once( 'api/class-SalesAutopilotAPI.php' );
		}

		$this->id					= 'salesautopilot';
		$this->method_title     	= __( 'SalesAutopilot', 'ss_wc_salesautopilot' );
		$this->method_description	= __( 'SalesAutpilot - Put Your Sales on Autopilot ...once and for all', 'ss_wc_salesautopilot' );

		// Load the settings.
		$this->init_settings();
		
		// Use API username and password to connect SalesAutopilot and setup taget list and form
		if (is_array($_POST) && sizeof($_POST) > 0) {
			$this->api_username  = $_POST['woocommerce_salesautopilot_api_username'];
			$this->api_password  = $_POST['woocommerce_salesautopilot_api_password'];
			$this->enabled  	 = $_POST['woocommerce_salesautopilot_enabled'];
			$this->saplist		 = $_POST['woocommerce_salesautopilot_saplist'];
			$this->form 		 = $_POST['woocommerce_salesautopilot_form'];
		} else {
			$this->api_username = $this->get_option( 'api_username' );
			$this->api_password = $this->get_option( 'api_password' );

			// Get setting values
			$this->enabled        = $this->get_option( 'enabled' );
			$this->saplist        = $this->get_option( 'saplist' );
			$this->form           = $this->get_option( 'form' );
		}
		$this->init_form_fields();
		
		// Hooks
		add_action( 'admin_notices', array( &$this, 'checks' ) );
		add_action( 'woocommerce_update_options_integration', array( $this, 'process_admin_options') );
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options') );

		// Use 'woocommerce_thankyou' action hook which fires after the checkout process on the "thank you" page
		add_action( 'woocommerce_thankyou', array( &$this, 'order_status_changed' ), 10, 1 );

		// hook into woocommerce order status changed hook to handle the desired subscription event trigger
		add_action( 'woocommerce_order_status_changed', array( &$this, 'order_status_changed' ), 10, 3 );
		
	}

	/**
	 * Check if the user has enabled the plugin functionality, but hasn't provided an api key
	 **/
	function checks() {
		global $woocommerce;

		if ( $this->enabled == 'yes' ) {

			// Check required fields
			if ( ! $this->api_username ) {

				echo '<div class="error"><p>' . sprintf( __('SalesAutopilot error: Please enter your api username and password <a href="%s">here</a>', 'ss_wc_salesautopilot'), admin_url('admin.php?page=woocommerce&tab=integration&section=salesautopilot' ) ) . '</p></div>';

				return;

			}

		}
	}

	/**
	 * order_status_changed function.
	 *
	 * @access public
	 * @return void
	 */
	public function order_status_changed($id, $status = 'new', $new_status = 'pending') {
		if ($this->is_valid() && $new_status == 'pending') {

			$order = new WC_Order( $id );						
			
			self::log( 'Add order (' . $order->billing_email . ') to list(' . $this->list . ') ' );
			$this->send_order($order,$this->saplist,$this->form);
		}
	}

	/**
	 * List is set.
	 *
	 * @access public
	 * @return void
	 */
	public function has_list() {
		if ( $this->saplist )
			return true;
	}
	
	/**
	 * Form is set.
	 *
	 * @access public
	 * @return void
	 */
	public function has_form() {
		if ( $this->form )
			return true;
	}

	/**
	 * has_api_key function.
	 *
	 * @access public
	 * @return void
	 */
	public function has_api_key() {
		if ( $this->api_username && $this->api_password )
			return true;
	}

	/**
	 * is_valid function.
	 *
	 * @access public
	 * @return boolean
	 */
	public function is_valid() {
		if ( $this->enabled == 'yes' && $this->has_api_key() && $this->has_list() ) {
			return true;
		}
		return false;
	}
	
	/**
	 * Initialize Settings Form Fields
	 *
	 * @access public
	 * @return void
	 */
	function init_form_fields() {

		if ( is_admin() ) {
			$lists = $this->get_lists();
 			if ($lists === false ) {
 				$lists = array ();
 				$forms = array ();
 			} else {
				$forms = $this->get_forms();
				if ($forms === false ) {
					$forms = array ();
				}
			}
 			$sap_lists = $this->has_api_key() ? array('' => __('Select a list...', 'ss_wc_salesautopilot')) + $lists : array('' => __( 'Enter your API username/password and save to see your lists', 'ss_wc_salesautopilot'));
 			$sap_forms = $this->has_api_key() ? array( '' => __('Select a form...', 'ss_wc_salesautopilot')) + $forms : array('' => __( 'Select a list in order to see its order forms.', 'ss_wc_salesautopilot'));

			$this->form_fields = array(
				'enabled' => array(
								'title' => __( 'Enable/Disable', 'ss_wc_salesautopilot' ),
								'label' => __( 'Enable SalesAutopilot', 'ss_wc_salesautopilot' ),
								'type' => 'checkbox',
								'description' => '',
								'default' => 'no'
							),
				'api_username' => array(
								'title' => __( 'API Username', 'ss_wc_salesautopilot' ),
								'type' => 'text',
								'description' => __( 'SalesAutopilot API username. <a href="http://www.salesautopilot.com/knowledge-base/api/api-key-pairs" target="_blank">How to get your API username/password</a>', 'ss_wc_salesautopilot' ),
								'default' => ''
							),
				'api_password' => array(
								'title' => __( 'API Password', 'ss_wc_salesautopilot' ),
								'type' => 'text',
								'description' => __( 'SalesAutopilot API password. <a href="http://www.salesautopilot.com/knowledge-base/api/api-key-pairs" target="_blank">How to get your API username/password</a>', 'ss_wc_salesautopilot' ),
								'default' => ''
							),
				'saplist' => array(
								'title' => __( 'eCommerce List', 'ss_wc_salesautopilot' ),
								'type' => 'select',
								'description' => __( 'Orders will be added to this eCommerce list.', 'ss_wc_salesautopilot' ),
								'default' => '',
								'options' => $sap_lists,
							),
				'form' => array(
								'title' => __( 'Order Form', 'ss_wc_salesautopilot' ),
								'type' => 'select',
								'description' => __( 'Orders will be added through this eCommerce form.', 'ss_wc_salesautopilot' ),
								'default' => '',
								'options' => $sap_forms,
							),
			);

			$this->wc_enqueue_js("
				jQuery('#woocommerce_salesautopilot_saplist').change(function(){
					jQuery('#woocommerce_salesautopilot_form option:eq(0)').prop('selected', true);
					jQuery('#woocommerce_salesautopilot_saplist').parents('tr').next().hide();
				});
			");

		}

	} // End init_form_fields()

	/**
	 * WooCommerce 2.1 support for wc_enqueue_js
	 *
	 * @since 1.2.1
	 *
	 * @access private
	 * @param string $code
	 * @return void
	 */
	private function wc_enqueue_js( $code ) {

		if ( function_exists( 'wc_enqueue_js' ) ) {
			wc_enqueue_js( $code );
		} else {
			global $woocommerce;
			$woocommerce->add_inline_js( $code );
		}

	}

	/**
	 * Get eCommerce lists from SalesAutopilot.
	 *
	 * @access public
	 * @return array
	 */
	public function get_lists() {
		if ($this->has_api_key()) {
			$sap_lists = array();
			$SalesAutopilot = new SalesAutopilotAPI($this->api_username,$this->api_password);
			$retval 		= $SalesAutopilot->call('zapier/getlists/11');

			if (!is_array($retval)) {
				echo '<div class="error"><p>' . sprintf( __( 'Unable to load lists from SalesAutopilot: (%s) %s', 'ss_wc_salesautopilot' ), $SalesAutopilot->errorCode, $SalesAutopilot->errorMessage ) . '</p></div>';

				return false;

			} else {
				foreach ( $retval as $list )
					$sap_lists[$list['nl_id']] = $list['list_name'];
			}
			return $sap_lists;
		} else {
			return false;
		}
	}
	
	/**
	 * Get eCommerce forms of selected order lists.
	 *
	 * @access public
	 * @return array
	 */
	public function get_forms() {
		if ($this->has_api_key() && is_numeric($this->saplist) && $this->saplist > 0) {
			$sap_forms = array();
			$SalesAutopilot = new SalesAutopilotAPI($this->api_username,$this->api_password);
			$retval 		= $SalesAutopilot->call('zapier/getforms/'.$this->saplist.'/4');

			if (!is_array($retval)) {
				echo '<div class="error"><p>' . sprintf( __( 'Unable to load forms from SalesAutopilot: (%s) %s', 'ss_wc_salesautopilot' ), $SalesAutopilot->errorCode, $SalesAutopilot->errorMessage ) . '</p></div>';

				return false;

			} else {
				foreach ( $retval as $form )
					$sap_forms[$form['ns_id']] = $form['form_name'];
			}
			return $sap_forms;
		} else {
			return false;
		}
	}

	/**
	 * Send order to SalesAutopilot through API.
	 *
	 * @access public
	 * @param object $order_details
	 * @param integer $listid
	 * @return void
	 */
	public function send_order( $order_details, $listid = 0 , $formid = 0) {

		if ( $listid == 0 )
			$listid = $this->saplist;
		if ( $formid == 0 )
			$formid = $this->form;

		$SalesAutopilot = new SalesAutopilotAPI($this->api_username,$this->api_password);
		
		$data = array();
		$data['order_id']			= $order_details->id;
		$data['email'] 				= $order_details->billing_email;
		$data['mssys_firstname'] 	= $order_details->billing_first_name;
		$data['mssys_lastname']	 	= $order_details->billing_last_name;
		$data['mssys_phone']	 	= $order_details->billing_phone;
		$data['payment_method'] 	= $order_details->payment_method_title;
		$data['currency'] 			= $order_details->order_currency;
		$data['mssys_bill_company']	= $order_details->billing_company;
		$data['mssys_bill_country']	= strtolower($order_details->billing_country);
		$data['mssys_bill_state']	= $order_details->billing_state;
		$data['mssys_bill_zip']		= $order_details->billing_postcode;
		$data['mssys_bill_city']	= $order_details->billing_city;
		$data['mssys_bill_address']	= $order_details->billing_address_1.' '.$order_details->billing_address_2;
		$data['mssys_postal_company']	= $order_details->shipping_company;
		$data['mssys_postal_country']	= strtolower($order_details->shipping_country);
		$data['mssys_postal_state']		= $order_details->shipping_state;
		$data['mssys_postal_zip']		= $order_details->shipping_postcode;
		$data['mssys_postal_city']		= $order_details->shipping_city;
		$data['mssys_postal_address']	= $order_details->shipping_address_1.' '.$order_details->shipping_address_2;
		$data['netshippingcost']		= round($order_details->get_total_shipping(),2);
		$data['grossshippingcost']		= round($order_details->get_total_shipping(),2) + round($order_details->get_shipping_tax(),2);
		
		// Add fees like COD
		$extraFees = $order_details->get_fees();
		foreach ($extraFees as $feeData) {
			$data['netshippingcost'] += round($feeData['line_total'],2);
			$data['grossshippingcost'] += round($feeData['line_total'],2) + round($feeData['line_tax'],2);
		}
		
		foreach ( $order_details->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
			$data['shipping_method'] .= $shipping_item['name'];
		}
		error_log(var_export($data,1),3,'/var/www/ugyfelek.emesz.hu/htdocs/wordpress/tmp/woo-cod.log');
		// Add products to the API call
		$products = array();
		foreach($order_details->get_items() as $item_id => $item) {

			$product = $order_details->get_product_from_item($item);
			$category = get_the_terms($product->id,'product_cat');
						
			$taxPercent = round($order_details->get_line_tax($item),2) / round($order_details->get_line_total($item),2) * 100;
			if ($product->get_sku() != '') {
				$productID = $product->get_sku();
			} else {
				$productID = $product->id;
			}
			$products[] = array(
				'prod_id' 		=> $productID,
				'prod_name'		=> $item['name'],
				'qty'			=> (int)$item['qty'],
				'tax'			=> $taxPercent,
				'prod_price'	=> round($order_details->get_item_total($item),2),
				'category_id'	=> $category[0]->term_id,
				'category_name'	=> $category[0]->name
			);
		}
		$data['products'] = $products;

		$couponCodes = array();
		$couponNetto = 0;
		foreach ( $order_details->get_items( 'coupon' ) as $coupon_item_id => $coupon_item ) {

			$couponCodes[] = $coupon_item['name'];
			$couponNetto += round($coupon_item['discount_amount'],2);
		}
		if (sizeof($couponCodes) > 0) {
			$data['mssys_coupon'] = implode(',',$couponCodes);
			$data['mssys_coupon_discount_amount_netto'] = $couponNetto;
			if ($order_details->get_cart_discount() > 0) {
				// Add tax to the discount
				$data['mssys_coupon_discount_amount_brutto'] = round($data['mssys_coupon_discount_amount_netto'] * (1 + ($taxPercent / 100)),2);
			} else {
				$data['mssys_coupon_discount_amount_brutto'] = $data['mssys_coupon_discount_amount_netto'];
			}
		}
		
		self::log( 'Calling SalesAutopilot API with the following parameters: ' .
			'list id=' . $listid .
			', vars=' . print_r( $data, true )
		);
		$retval = $SalesAutopilot->call('processWebshopOrder/'.$listid.'/ns_id/'.$formid,$data);
		
		if ($SalesAutopilot->errorCode != 200) {
			self::log( 'WooCommerce to SalesAutopilot API call failed: (' . $SalesAutopilot->errorCode . ') ' . $SalesAutopilot->errorMessage );			
		}
	}

	/**
	 * Admin Panel Options
	 */
	function admin_options() {
    	?>
		<h3><?php _e( 'SalesAutopilot', 'ss_wc_salesautopilot' ); ?></h3>
    	<p><?php _e( 'Enter your SalesAutopilot settings below to WooCommerce could send order to your eCommerce list.', 'ss_wc_salesautopilot' ); ?></p>
    		<table class="form-table">
	    		<?php $this->generate_settings_html(); ?>
			</table><!--/.form-table-->
		<?php
	}

	/**
	 * Helper log function for debugging
	 *
	 * @since 1.2.2
	 */
	static function log( $message ) {
		if ( WP_DEBUG === true ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( print_r( $message, true ) );
			} else {
				error_log( $message );
			}
		}
	}

}