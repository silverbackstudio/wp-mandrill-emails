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

if( !class_exists('RCP_Emails') ){
	return;
}

require __DIR__ . '/src/RCP_Emails.php';

use Svbk\WP\Helpers\Mailing\Mandrill;
use Svbk\WP\Plugins\RCP\Mandrill\RCP_Emails as RCP_Mandrill_Emails;

/**
 * Loads textdomain and main initializes main class
 *
 * @return void
 */
function svbk_mandrill_emails_init() {
	load_plugin_textdomain( 'svbk-mandrill-emails', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'plugins_loaded', 'svbk_mandrill_emails_init' );

/**
 * Triggers the activation notice when an account is marked as active.
 *
 * @param string $status  User's status.
 * @param int    $user_id ID of the user to email.
 *
 * @access  public
 * @since   2.1
 * @return  void
 */
function svbk_rcp_email_on_registration( $user_id ) {
	
	global $rcp_options;

	$rcp_member = new RCP_Member( $user_id );

	$emails = new RCP_Mandrill_Emails();
	$emails->member_id = $rcp_member->ID;
	
	$to = array(
		'email' => $rcp_member->user_email,
		'name' => $rcp_member->first_name . ' ' . $rcp_member->last_name,
		'type' => 'to',
	);

	$template = isset($rcp_options['mandrill_template_user_reg']) ? $rcp_options['mandrill_template_user_reg'] : '';

	if( $template && $emails->sendTemplate( $template, $to ) ) {
		rcp_log( sprintf( '[Mandrill Emails] Registration email sent to user %s. Template : %s', $rcp_member->first_name . ' ' . $rcp_member->last_name, $template ) );
	} else {
		rcp_log( sprintf( '[Mandrill Emails] Registration email not sent to user %s - template %s is empty or invalid.', $rcp_member->first_name . ' ' . $rcp_member->last_name, $template ) );
	}
	
}
add_action( 'user_register', 'svbk_rcp_email_on_registration', 99, 1);


/**
 * Send emails to members based on subscription status changes.
 *
 * @param int    $user_id ID of the user to send the email to.
 * @param string $status  User's status, to determine which email to send.
 *
 * @return void
 */
function svbk_rcp_email_subscription_status( $user_id, $status = 'active' ) {

	global $rcp_options;

	$user_info     = get_userdata( $user_id );
	$message       = '';
	$admin_message = '';
	$site_name     = stripslashes_deep( html_entity_decode( get_bloginfo('name'), ENT_COMPAT, 'UTF-8' ) );

	$emails = new RCP_Mandrill_Emails;
	$emails->member_id = $user_id;

	$admin_emails  = ! empty( $rcp_options['admin_notice_emails'] ) ? $rcp_options['admin_notice_emails'] : get_option('admin_email');
	$admin_emails  = apply_filters( 'rcp_admin_notice_emails', explode( ',', $admin_emails ) );
	$admin_emails  = array_map( 'sanitize_email', $admin_emails );

	// Allow add-ons to add file attachments

	$attachments = apply_filters( 'rcp_email_attachments', array(), $user_id, $status );

	switch ( $status ) :

		case "active" :

			if( rcp_is_trialing( $user_id ) ) {
				break;
			}

			if( ! isset( $rcp_options['disable_active_email'] ) ) {

				$message = isset( $rcp_options['active_email'] ) ? $rcp_options['active_email'] : '';
				$message = apply_filters( 'rcp_subscription_active_email', $message, $user_id, $status );
				$subject = isset( $rcp_options['active_subject'] ) ? $rcp_options['active_subject'] : '';
				$subject = apply_filters( 'rcp_subscription_active_subject', $subject, $user_id, $status );
				
				$template = isset($rcp_options['mandrill_template_user_active']) ? $rcp_options['mandrill_template_user_active'] : '';
			}

			if( ! isset( $rcp_options['disable_active_email_admin'] ) ) {
				$admin_message = isset( $rcp_options['active_email_admin'] ) ? $rcp_options['active_email_admin'] : '';
				$admin_subject = isset( $rcp_options['active_subject_admin'] ) ? $rcp_options['active_subject_admin'] : '';

				if( empty( $admin_message ) ) {
					$admin_message = __( 'Hello', 'rcp' ) . "\n\n" . $user_info->display_name . ' (' . $user_info->user_login . ') ' . __( 'is now subscribed to', 'rcp' ) . ' ' . $site_name . ".\n\n" . __( 'Subscription level', 'rcp' ) . ': ' . rcp_get_subscription( $user_id ) . "\n\n";
					$admin_message = apply_filters( 'rcp_before_admin_email_active_thanks', $admin_message, $user_id );
					$admin_message .= __( 'Thank you', 'rcp' );
				}

				if( empty( $admin_subject ) ) {
					$admin_subject = sprintf( __( 'New subscription on %s', 'rcp' ), $site_name );
				}
				
				$admin_template = isset($rcp_options['mandrill_template_admin_user_active']) ? $rcp_options['mandrill_template_admin_user_active'] : '';
				
			}
			break;

		case "cancelled" :

			if( ! isset( $rcp_options['disable_cancelled_email'] ) ) {

				$message = isset( $rcp_options['cancelled_email'] ) ? $rcp_options['cancelled_email'] : '';
				$message = apply_filters( 'rcp_subscription_cancelled_email', $message, $user_id, $status );
				$subject = isset( $rcp_options['cancelled_subject'] ) ? $rcp_options['cancelled_subject'] : '';
				$subject = apply_filters( 'rcp_subscription_cancelled_subject', $subject, $user_id, $status );
				
				$template = isset($rcp_options['mandrill_template_user_cancelled']) ? $rcp_options['mandrill_template_user_cancelled'] : '';
				
			}

			if( ! isset( $rcp_options['disable_cancelled_email_admin'] ) ) {
				$admin_message = isset( $rcp_options['cancelled_email_admin'] ) ? $rcp_options['cancelled_email_admin'] : '';
				$admin_subject = isset( $rcp_options['cancelled_subject_admin'] ) ? $rcp_options['cancelled_subject_admin'] : '';

				if( empty( $admin_message ) ) {
					$admin_message = __( 'Hello', 'rcp' ) . "\n\n" . $user_info->display_name . ' (' . $user_info->user_login . ') ' . __( 'has cancelled their subscription to', 'rcp' ) . ' ' . $site_name . ".\n\n" . __( 'Their subscription level was', 'rcp' ) . ': ' . rcp_get_subscription( $user_id ) . "\n\n";
					$admin_message = apply_filters( 'rcp_before_admin_email_cancelled_thanks', $admin_message, $user_id );
					$admin_message .= __( 'Thank you', 'rcp' );
				}

				if( empty( $admin_subject ) ) {
					$admin_subject = sprintf( __( 'Cancelled subscription on %s', 'rcp' ), $site_name );
				}
				
				$admin_template = isset($rcp_options['mandrill_template_admin_user_cancelled']) ? $rcp_options['mandrill_template_admin_user_cancelled'] : '';
			}

		break;

		case "expired" :

			if( ! isset( $rcp_options['disable_expired_email'] ) ) {

				$message = isset( $rcp_options['expired_email'] ) ? $rcp_options['expired_email'] : '';
				$message = apply_filters( 'rcp_subscription_expired_email', $message, $user_id, $status );

				$subject = isset( $rcp_options['expired_subject'] ) ? $rcp_options['expired_subject'] : '';
				$subject = apply_filters( 'rcp_subscription_expired_subject', $subject, $user_id, $status );

				add_user_meta( $user_id, '_rcp_expired_email_sent', 'yes' );
				
				$template = isset($rcp_options['mandrill_template_user_expired']) ? $rcp_options['mandrill_template_user_expired'] : '';
			}

			if( ! isset( $rcp_options['disable_expired_email_admin'] ) ) {
				$admin_message = isset( $rcp_options['expired_email_admin'] ) ? $rcp_options['expired_email_admin'] : '';
				$admin_subject = isset( $rcp_options['expired_subject_admin'] ) ? $rcp_options['expired_subject_admin'] : '';

				if ( empty( $admin_message ) ) {
					$admin_message = __( 'Hello', 'rcp' ) . "\n\n" . $user_info->display_name . "'s " . __( 'subscription has expired', 'rcp' ) . "\n\n";
					$admin_message = apply_filters( 'rcp_before_admin_email_expired_thanks', $admin_message, $user_id );
					$admin_message .= __( 'Thank you', 'rcp' );
				}

				if ( empty( $admin_subject ) ) {
					$admin_subject = sprintf( __( 'Expired subscription on %s', 'rcp' ), $site_name );
				}
				
				$admin_template = isset($rcp_options['mandrill_template_admin_user_expired']) ? $rcp_options['mandrill_template_admin_user_expired'] : '';
			}

		break;

		case "free" :

			if( ! isset( $rcp_options['disable_free_email'] ) ) {

				$message = isset( $rcp_options['free_email'] ) ? $rcp_options['free_email'] : '';
				$message = apply_filters( 'rcp_subscription_free_email', $message, $user_id, $status );

				$subject = isset( $rcp_options['free_subject'] ) ? $rcp_options['free_subject'] : '';
				$subject = apply_filters( 'rcp_subscription_free_subject', $subject, $user_id, $status );

				$template = isset($rcp_options['mandrill_template_user_free']) ? $rcp_options['mandrill_template_user_free'] : '';

			}

			if( ! isset( $rcp_options['disable_free_email_admin'] ) ) {
				$admin_message = isset( $rcp_options['free_email_admin'] ) ? $rcp_options['free_email_admin'] : '';
				$admin_subject = isset( $rcp_options['free_subject_admin'] ) ? $rcp_options['free_subject_admin'] : '';

				if( empty( $admin_message ) ) {
					$admin_message = __( 'Hello', 'rcp' ) . "\n\n" . $user_info->display_name . ' (' . $user_info->user_login . ') ' . __( 'is now subscribed to', 'rcp' ) . ' ' . $site_name . ".\n\n" . __( 'Subscription level', 'rcp' ) . ': ' . rcp_get_subscription( $user_id ) . "\n\n";
					$admin_message = apply_filters( 'rcp_before_admin_email_free_thanks', $admin_message, $user_id );
					$admin_message .= __( 'Thank you', 'rcp' );
				}

				if( empty( $admin_subject ) ) {
					$admin_subject = sprintf( __( 'New free subscription on %s', 'rcp' ), $site_name );
				}
				
				$admin_template = isset($rcp_options['mandrill_template_admin_user_free']) ? $rcp_options['mandrill_template_admin_user_free'] : '';
			}

		break;

		case "trial" :

			if( ! isset( $rcp_options['disable_trial_email'] ) ) {

				$message = isset( $rcp_options['trial_email'] ) ? $rcp_options['trial_email'] : '';
				$message = apply_filters( 'rcp_subscription_trial_email', $message, $user_id, $status );

				$subject = isset( $rcp_options['trial_subject'] ) ? $rcp_options['trial_subject'] : '';
				$subject = apply_filters( 'rcp_subscription_trial_subject', $subject, $user_id, $status );
				
				$template = isset($rcp_options['mandrill_template_user_trial']) ? $rcp_options['mandrill_template_user_trial'] : '';

			}

			if( ! isset( $rcp_options['disable_trial_email_admin'] ) ) {
				$admin_message = isset( $rcp_options['trial_email_admin'] ) ? $rcp_options['trial_email_admin'] : '';
				$admin_subject = isset( $rcp_options['trial_subject_admin'] ) ? $rcp_options['trial_subject_admin'] : '';

				if( empty( $admin_message ) ) {
					$admin_message = __( 'Hello', 'rcp' ) . "\n\n" . $user_info->display_name . ' (' . $user_info->user_login . ') ' . __( 'is now subscribed to', 'rcp' ) . ' ' . $site_name . ".\n\n" . __( 'Subscription level', 'rcp' ) . ': ' . rcp_get_subscription( $user_id ) . "\n\n";
					$admin_message = apply_filters( 'rcp_before_admin_email_trial_thanks', $admin_message, $user_id );
					$admin_message .= __( 'Thank you', 'rcp' );
				}

				if( empty( $admin_subject ) ) {
					$admin_subject = sprintf( __( 'New trial subscription on %s', 'rcp' ), $site_name );
				}
				
				$admin_template	= isset($rcp_options['mandrill_template_admin_user_trial']) ? $rcp_options['mandrill_template_admin_user_trial'] : '';
				
			}

		break;

		default:
			break;

	endswitch;
	
	$template = apply_filters( 'svbk_rcp_status_mandrill_template', $template, $user_id, $status, $this );
	$admin_template = apply_filters( 'svbk_rcp_status_mandrill_admin_template', $admin_template, $user_id, $status, $this );

	if( ! empty( $template ) && $emails->sendTemplate( $template, $user_info->user_email, $subject, $message, $attachments ) ) {
		rcp_log( sprintf( '[Mandrill Emails] %s email sent to user #%d.', ucwords( $status ), $user_info->ID ) );
	} else {
		rcp_log( sprintf( '[Mandrill Emails] %s email not sent to user #%d - template %s is empty or invalid.', ucwords( $status ), $user_info->ID, $template ) );
	}

	if( ! empty( $admin_template ) && $emails->sendTemplate( $admin_template, $admin_emails, $admin_subject, $admin_message ) ) {
		rcp_log( sprintf( '[Mandrill Emails] %s email sent to admin(s).', ucwords( $status ) ) );
	} else {
		rcp_log( sprintf( '[Mandrill Emails] %s email not sent to admin(s) - template %s is empty or invalid.', ucwords( $status ), $admin_template ) );
		return false;
	}
	
	return true;
	
}

/**
 * Triggers the activation notice when an account is marked as active.
 *
 * @param string $status  User's status.
 * @param int    $user_id ID of the user to email.
 *
 * @access  public
 * @since   2.1
 * @return  void
 */
function svbk_rcp_email_on_activation( $status, $user_id ) {

	if( 'active' == $status && get_user_meta( $user_id, '_rcp_new_subscription', true ) ) {

		// Send welcome email.
		if ( ! svbk_rcp_email_subscription_status( $user_id, 'active' ) ) {
			remove_action( 'rcp_set_status', 'rcp_email_on_activation', 11, 2 );
		}

	}

}
add_action( 'rcp_set_status', 'svbk_rcp_email_on_activation', 10, 2 );

/**
 * Email the site admin when a new payment is created.
 *
 * @param int                        $payment_id
 * @param array $args
 *
 * @since  2.7.3
 * @return void
 */
function svbk_rcp_email_new_payment( $payment_id, $args ) {

	global $rcp_options;
	
	/**
	 * @var RCP_Payments $rcp_payments_db
	 */
	global $rcp_payments_db;
	
	$rcp_payment  = $rcp_payments_db->get_payment( $payment_id );
	$member = new RCP_Member( $rcp_payment->user_id );	

	$template = isset($rcp_options['mandrill_template_admin_new_payment']) ? $rcp_options['mandrill_template_admin_new_payment'] : '';

	$admin_emails  = ! empty( $rcp_options['admin_notice_emails'] ) ? $rcp_options['admin_notice_emails'] : get_option('admin_email');
	$admin_emails  = apply_filters( 'rcp_admin_notice_emails', explode( ',', $admin_emails ) );
	$admin_emails  = array_map( 'sanitize_email', $admin_emails );

	$emails             = new RCP_Mandrill_Emails;
	$emails->member_id  = $member->ID;
	$emails->payment_id = $payment_id;

	$site_name = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );

	$admin_subject = sprintf( __( 'New manual payment on %s', 'rcp' ), $site_name );

	if( $template && $emails->sendTemplate($template, $admin_emails, $admin_subject ) ){
		rcp_log( sprintf( '[Mandrill Emails] New Pending Payment email sent to admin(s) regarding payment #%d. Template: %s', $payment_id, $template ) );
	} else {
		rcp_log( sprintf( '[Mandrill Emails] New Pending payment email not sent to admin(s) - template %s is empty or invalid.', ucwords( $status ), $template ) );
	}

}
add_action( 'rcp_create_payment', 'svbk_rcp_email_new_payment', 10, 2 );

/**
 * Email the site admin when a new manual payment is received.
 *
 * @param RCP_Member                 $member
 * @param int                        $payment_id
 * @param RCP_Payment_Gateway_Manual $gateway
 *
 * @since  2.7.3
 * @return void
 */
function svbk_rcp_email_user_on_manual_payment( $member, $payment_id, $gateway ) {

	global $rcp_options;

	$user_info = get_userdata( $member->id );
	$template = isset($rcp_options['mandrill_template_user_manual_payment']) ? $rcp_options['mandrill_template_user_manual_payment'] : '';

	$emails             = new RCP_Mandrill_Emails;
	$emails->member_id  = $member->ID;
	$emails->payment_id = $payment_id;

	$site_name = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );
	$admin_subject = sprintf( __( 'New manual payment on %s', 'rcp' ), $site_name );

	if( $template && $emails->sendTemplate($template, $user_info->user_email, $admin_subject ) ){
		rcp_log( sprintf( '[Mandrill Emails] New Manual Payment email sent to user regarding payment #%d. Template: %s', $payment_id, $template ) );

	} else {
		rcp_log( sprintf( '[Mandrill Emails] New Manual payment email not sent to user - template %s is empty or invalid.', ucwords( $status ), $template ) );

		$admin_message = __( 'Hello', 'rcp' ) . "\n\n" . $member->display_name . ' (' . $member->user_login . ') ' . __( 'you just submitted a manual payment on', 'rcp' ) . ' ' . $site_name . ".\n\n" . __( 'Subscription:', 'rcp' ) . ': ' . $member->get_subscription_name() . "\n\n";
		$admin_message .= empty($rcp_options['manual_payment_instructions']) ? '' : ($rcp_options['manual_payment_instructions'] . "\n\n") ;
		$admin_message = apply_filters( 'rcp_before_user_email_manual_payment_thanks', $admin_message, $member->ID );
		$admin_message .= __( 'Thank you', 'rcp' );		
		
		$emails->send( $user_info->user_email, $admin_subject, $admin_message );
	}

}
add_action( 'rcp_process_manual_signup', 'svbk_rcp_email_user_on_manual_payment', 9, 3 );


/**
 * Triggers an email to the member when a payment is received.
 *
 * @param int    $payment_id ID of the payment being completed.
 *
 * @access  public
 * @since   2.3
 * @return  void
 */
function svbk_rcp_email_payment_received( $payment_id ) {

	global $rcp_options;

	$template = isset($rcp_options['mandrill_template_payment_received']) ? $rcp_options['mandrill_template_payment_received'] : '';

	/**
	 * @var RCP_Payments $rcp_payments_db
	 */
	global $rcp_payments_db;

	$payment = $rcp_payments_db->get_payment( $payment_id );

	$user_info = get_userdata( $payment->user_id );

	if( ! $user_info ) {
		return;
	}

	// Don't send an email if payment amount is 0.
	$amount = (float) $payment->amount;
	if ( empty( $amount ) ) {
		rcp_log( sprintf( '[Mandrill Emails] Payment Received email not sent to user #%d - payment amount is 0.', $user_info->ID ) );

		return;
	}

	$payment = (array) $payment;

	$emails = new RCP_Mandrill_Emails;
	$emails->member_id = $payment['user_id'];
	$emails->payment_id = $payment_id;

	if( $template && $emails->sendTemplate( $template, $user_info->user_email, $rcp_options['payment_received_subject'] ) ){
		remove_action( 'rcp_update_payment_status_complete', 'rcp_email_payment_received', 100 );
		rcp_log( sprintf( '[Mandrill Emails] Payment Received email sent to user #%d. Template: %s', $user_info->ID, $template  ) );		
	} else {
		rcp_log( sprintf( '[Mandrill Emails] Payment Received email not sent to user #%d. Template: %s', $user_info->ID, $template ) );		
	}

}
add_action( 'rcp_update_payment_status_complete', 'svbk_rcp_email_payment_received', 99 );

/**
 * Triggers an email to the member when a payment is abandoned.
 *
 * @param int    $payment_id ID of the payment being completed.
 *
 * @access  public
 * @since   2.3
 * @return  void
 */
function svbk_rcp_email_payment_abandoned( $payment_id ) {

	global $rcp_options;

	$template = isset($rcp_options['mandrill_template_payment_abandoned']) ? $rcp_options['mandrill_template_payment_abandoned'] : '';

	/**
	 * @var RCP_Payments $rcp_payments_db
	 */
	global $rcp_payments_db;

	$payment = $rcp_payments_db->get_payment( $payment_id );

	$user_info = get_userdata( $payment->user_id );

	if( ! $user_info ) {
		return;
	}

	$payment = (array) $payment;

	$emails = new RCP_Mandrill_Emails;
	$emails->member_id = $payment['user_id'];
	$emails->payment_id = $payment_id;

	if( $template && $emails->sendTemplate( $template, $user_info->user_email ) ){
		rcp_log( sprintf( '[Mandrill Emails] Payment Abandoned email sent to user #%d. Template: %s', $user_info->ID, $template  ) );		
	} else {
		rcp_log( sprintf( '[Mandrill Emails] Payment Abandoned email not sent to user #%d. Template: %s', $user_info->ID, $template ) );		
	}

}
add_action( 'rcp_update_payment_status_abandoned', 'svbk_rcp_email_payment_abandoned', 99 );

/**
 * Print the options
 *
 * @param array $rcp_options  RCP_options array.
 *
 * @access  public
 * @since   2.1
 * @return  void
 */
function svbk_rcp_email_settings( $rcp_options ){ ?>
	
	<table class="form-table">
		<tr>
			<th colspan=2><h3><?php _e( 'Mandrill Templates', 'svbk-mandrill-emails' ); ?></h3></th>
		</tr>

		<tr>
			<th>
				<label for="rcp_settings[mandrill_template_user_reg]"><?php _e( 'User Registration', 'svbk-mandrill-emails' ); ?></label>
			</th>
			<td>
				<input class="regular-text" id="rcp_settings[mandrill_template_user_reg]" style="width: 300px;" 
				name="rcp_settings[mandrill_template_user_reg]" value="<?php echo esc_attr( isset( $rcp_options['mandrill_template_user_reg'] ) ? $rcp_options['mandrill_template_user_reg'] : '' ); ?>"/>
				<p class="description"><?php _e( 'Template sent to user at registration', 'svbk-mandrill-emails' ); ?></p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="rcp_settings[mandrill_template_user_active]"><?php _e( 'User Active Notification', 'svbk-mandrill-emails' ); ?></label>
			</th>
			<td>
				<input class="regular-text" id="rcp_settings[mandrill_template_user_active]" style="width: 300px;" 
				name="rcp_settings[mandrill_template_user_active]" value="<?php echo esc_attr( isset( $rcp_options['mandrill_template_user_active'] ) ? $rcp_options['mandrill_template_user_active'] : '' ); ?>"/>
				<p class="description"><?php _e( 'Template sent to user when set to active', 'svbk-mandrill-emails' ); ?></p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="rcp_settings[mandrill_template_user_cancelled]"><?php _e( 'User Cancelled Notification', 'svbk-mandrill-emails' ); ?></label>
			</th>
			<td>
				<input class="regular-text" id="rcp_settings[mandrill_template_user_cancelled]" style="width: 300px;" 
				name="rcp_settings[mandrill_template_user_cancelled]" value="<?php echo esc_attr( isset( $rcp_options['mandrill_template_user_cancelled'] )  ? $rcp_options['mandrill_template_user_cancelled'] : '' ); ?>"/>
				<p class="description"><?php _e( 'Template sent to user when set to cancelled', 'svbk-mandrill-emails' ); ?></p>
			</td>
		</tr>	
		<tr>
			<th>
				<label for="rcp_settings[mandrill_template_user_expired]"><?php _e( 'User Expired Notification', 'svbk-mandrill-emails' ); ?></label>
			</th>
			<td>
				<input class="regular-text" id="rcp_settings[mandrill_template_user_expired]" style="width: 300px;" 
				name="rcp_settings[mandrill_template_user_expired]" value="<?php echo esc_attr( isset( $rcp_options['mandrill_template_user_expired'] ) ? $rcp_options['mandrill_template_user_expired']: '' ); ?>"/>
				<p class="description"><?php _e( 'Template sent to user when set to expired', 'svbk-mandrill-emails' ); ?></p>
			</td>
		</tr>		
		<tr>
			<th>
				<label for="rcp_settings[mandrill_template_user_free]"><?php _e( 'User Free Notification', 'svbk-mandrill-emails' ); ?></label>
			</th>
			<td>
				<input class="regular-text" id="rcp_settings[mandrill_template_user_free]" style="width: 300px;" 
				name="rcp_settings[mandrill_template_user_free]" value="<?php echo esc_attr( isset( $rcp_options['mandrill_template_user_free'] ) ? $rcp_options['mandrill_template_user_free'] : '' ); ?>"/>
				<p class="description"><?php _e( 'Template sent to user when set to free', 'svbk-mandrill-emails' ); ?></p>
			</td>
		</tr>		
		<tr>
			<th>
				<label for="rcp_settings[mandrill_template_user_trial]"><?php _e( 'User Trial Notification', 'svbk-mandrill-emails' ); ?></label>
			</th>
			<td>
				<input class="regular-text" id="rcp_settings[mandrill_template_user_trial]" style="width: 300px;" 
				name="rcp_settings[mandrill_template_user_trial]" value="<?php echo esc_attr( isset( $rcp_options['mandrill_template_user_trial'] ) ? $rcp_options['mandrill_template_user_trial'] : '' ); ?>"/>
				<p class="description"><?php _e( 'Template sent to user when set to trial', 'svbk-mandrill-emails' ); ?></p>
			</td>
		</tr>		
		<tr>
			<th>
				<label for="rcp_settings[mandrill_template_payment_received]"><?php _e( 'User Payment Received', 'svbk-mandrill-emails' ); ?></label>
			</th>
			<td>
				<input class="regular-text" id="rcp_settings[mandrill_template_payment_received]" style="width: 300px;" 
				name="rcp_settings[mandrill_template_payment_received]" value="<?php echo esc_attr( isset( $rcp_options['mandrill_template_payment_received'] ) ? $rcp_options['mandrill_template_payment_received'] : '' ); ?>"/>
				<p class="description"><?php _e( 'Template sent to user when we receive his payment', 'svbk-mandrill-emails' ); ?></p>
			</td>
		</tr>		
		<tr>
			<th>
				<label for="rcp_settings[mandrill_template_user_manual_payment]"><?php _e( 'User Manual Payment Info', 'svbk-mandrill-emails' ); ?></label>
			</th>
			<td>
				<input class="regular-text" id="rcp_settings[mandrill_template_user_manual_payment]" style="width: 300px;" 
				name="rcp_settings[mandrill_template_user_manual_payment]" value="<?php echo esc_attr( isset( $rcp_options['mandrill_template_user_manual_payment'] ) ? $rcp_options['mandrill_template_user_manual_payment'] : '' ); ?>"/>
				<p class="description"><?php _e( 'Template sent to user with payment info', 'svbk-mandrill-emails' ); ?></p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="rcp_settings[mandrill_template_admin_new_payment]"><?php _e( 'Admin New Payment/Registration Notification', 'svbk-mandrill-emails' ); ?></label>
			</th>
			<td>
				<input class="regular-text" id="rcp_settings[mandrill_template_admin_new_payment]" style="width: 300px;" 
					name="rcp_settings[mandrill_template_admin_new_payment]" value="<?php echo esc_attr( isset( $rcp_options['mandrill_template_admin_new_payment'] ) ? $rcp_options['mandrill_template_admin_new_payment']: '' ); ?>"/>
				<p class="description"><?php _e( 'Template sent to admin at user registration, or when a new payment is created', 'svbk-mandrill-emails' ); ?></p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="rcp_settings[mandrill_template_payment_abandoned]"><?php _e( 'User Payment Abandoned Notification', 'svbk-mandrill-emails' ); ?></label>
			</th>
			<td>
				<input class="regular-text" id="rcp_settings[mandrill_template_payment_abandoned]" style="width: 300px;" 
					name="rcp_settings[mandrill_template_payment_abandoned]" value="<?php echo esc_attr( isset( $rcp_options['mandrill_template_payment_abandoned'] ) ? $rcp_options['mandrill_template_payment_abandoned']: '' ); ?>"/>
				<p class="description"><?php _e( 'Template sent to user when his payment is set to abandoned', 'svbk-mandrill-emails' ); ?></p>
			</td>
		</tr>		
	</table>	
	
<?php }

add_action('rcp_email_settings', 'svbk_rcp_email_settings');


add_filter( 'retrieve_password_message', 'svbk_rcp_patch_password_reset_email', 10, 4 );

function svbk_rcp_patch_password_reset_email($message, $key, $user_login, $user_data) {
	
	$message = __('Someone requested that the password be reset for the following account:', 'rcp') . "\r\n\r\n";
	$message .= network_home_url( '/' ) . "\r\n\r\n";
	$message .= sprintf(__('Username: %s', 'rcp'), $user_login) . "\r\n\r\n";
	$message .= __('If this was a mistake, just ignore this email and nothing will happen.', 'rcp') . "\r\n\r\n";
	$message .= __('To reset your password, visit the following address:', 'rcp') . "\r\n\r\n";
	
	$reset_url = esc_url_raw( add_query_arg( array( 'rcp_action' => 'lostpassword_reset', 'key' => $key, 'login' => rawurlencode( $user_login ) ), $_POST['rcp_redirect'] ) );
	$message .= '<a href="' . esc_attr($reset_url) . '">' . esc_html($reset_url) . '</a>' . "\r\n";	
	
	return $message;
}
