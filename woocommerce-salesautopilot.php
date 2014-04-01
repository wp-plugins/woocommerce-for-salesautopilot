<?php
/**
 * Plugin Name: WooCommerce SalesAutopilot
 * Plugin URI: http://www.salesautopilot.com/knowledge-base/ecommerce/woocommerce-integration
 * Description: WooCommerce SalesAutopilot provides integration with SalesAutopilot eCommerce functions.
 * Author: Gyorgy Khauth
 * Author URI: http://salesautopilot.com
 * Version: 1.0.0
 * Text Domain: ss_wc_salesautopilot
 * Domain Path: languages
 * 
 * Copyright: © 2014 Gyorgy Khauth
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 *  Copyright 2014  Gyorgy Khauth  (email : gykhauth@salesautopilot.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

add_action( 'plugins_loaded', 'woocommerce_salesautopilot_init', 0 );

function woocommerce_salesautopilot_init() {

	if ( ! class_exists( 'WC_Integration' ) )
		return;

	load_plugin_textdomain( 'ss_wc_salesautopilot', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	include_once( 'classes/class-ss-wc-integration-salesautopilot.php' );

	/**
 	* Add the Integration to WooCommerce
 	**/
	function add_salesautopilot_integration($methods) {
    	$methods[] = 'SS_WC_Integration_SalesAutopilot';
		return $methods;
	}

	add_filter('woocommerce_integrations', 'add_salesautopilot_integration' );
	
	function action_links( $links ) {

		global $woocommerce;

		$settings_url = admin_url( 'admin.php?page=woocommerce_settings&tab=integration&section=salesautopilot' );

		if ( $woocommerce->version >= '2.1' ) {
			$settings_url = admin_url( 'admin.php?page=wc-settings&tab=integration&section=salesautopilot' );
		}

		$plugin_links = array(
			'<a href="' . $settings_url . '">' . __( 'Settings', 'ss_wc_salesautopilot' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}
	// Add the "Settings" links on the Plugins administration screen
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'action_links' );
}
