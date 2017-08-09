<?php
/**
 * Adds the custom fields to the registration form and profile editor
 *
 * @package svbk-mandrill-emails
 * @author Brando Meniconi <b.meniconi@silverbackstudio.it>
 */

/*
Plugin Name: Mandrill Transactional Emails
Description: Send plugins transactional email with Mandrill
Author: Silverback Studio
Version: 1.0
Author URI: http://www.silverbackstudio.it/
Text Domain: svbk-mandrill-emails
*/

use Svbk\WP\Helpers\Mailing\Mandrill;

/**
 * Loads textdomain and main initializes main class
 *
 * @return void
 */
function svbk_mandrill_emails_init() {
	load_plugin_textdomain( 'svbk-mandrill-emails', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	
	remove_action( 'rcp_insert_payment', 'rcp_email_payment_received', 10 );
	
}

add_action( 'plugins_loaded', 'svbk_mandrill_emails_init' );

function svbk_rcp_activate_subscription_email( $subscription_id, $member_id, $rcp_member ) {

	global $rcp_levels_db;

	switch( $subscription_id ) {
		case 1: 
			$template = 'vivere-di-turismo-biglietto-vdt-live';	
			break;
		case 2: 
			$template = 'vivere-di-turismo-biglietto-vdt-live';	
			break;
		case 3: 
			$template = 'vivere-di-turismo-acquisto-giornata-con-danilo';	
			break;	
		case 4: 
			$template = 'vivere-di-turismo-biglietto-vdt-streaming';	
			break;				
	}
	

	$mandrill = new Mandrill( env('MD_APIKEY') );

	if ( $template ) {
		
		$member_merge_tags = Mandrill::castMergeTags( array(
			'fname' => $rcp_member->first_name ,
			'lname' => $rcp_member->last_name,
			'email' => $rcp_member->user_email,
			'company_name' => $rcp_member->company_name,
			'billing_address' => $rcp_member->billing_address,
			'biling_city' => $rcp_member->biling_city,
			'billing_state' => $rcp_member->billing_state,
			'billing_postal_code' => $rcp_member->billing_postal_code,
			'billing_country' => $rcp_member->billing_country,	
		), 'MEMBER_' );
		
		$rcp_levels  = new RCP_Levels;
		$rcp_level  = $rcp_levels->get_level( $subscription_id );		
		
		$level_merge_tags = Mandrill::castMergeTags( array(
			'name' => $rcp_level->name,
			'description' => $rcp_level->description,
			'price' => $rcp_level->price
		), 'SUBSCR_' );		
		
		$results = $mandrill->messages->sendTemplate( 
			$template, 
			array(), 
			array_merge_recursive(
				Mandrill::$messageDefaults,
				array(
					'text' => '',
					'to' => array(
						array(
							'email' => $rcp_member->user_email,
							'name' => $rcp_member->first_name . ' ' . $rcp_member->last_name,
							'type' => 'to',
						),
					),
					'global_merge_vars' => array_merge( $member_merge_tags, $level_merge_tags ),
					'merge' => true,
					'tags' => array(
						'subscription-activation-request'
					),
				)
			)
		);
	} else {
		return;
	}

}

add_action( 'rcp_member_post_set_subscription_id', 'svbk_rcp_activate_subscription_email', 8, 3 );