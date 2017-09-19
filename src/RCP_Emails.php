<?php

namespace Svbk\WP\Plugins\RCP\Mandrill;

use Svbk\WP\Helpers\Mailing\Mandrill;
use Mandrill_ValidationError;

class RCP_Emails extends \RCP_Emails {
    

	/**
	 * Holds the from address
	 *
	 * @since 2.7
	 */
	private $from_address;

	/**
	 * Holds the from name
	 *
	 * @since 2.7
	 */
	private $from_name;

	/**
	 * Holds the email content type
	 *
	 * @since 2.7
	 */
	private $content_type;

	/**
	 * Holds the email headers
	 *
	 * @since 2.7
	 */
	private $headers;

	/**
	 * Whether to send email in HTML
	 *
	 * @since 2.7
	 */
	private $html = true;

	/**
	 * The email template to use
	 *
	 * @since 2.7
	 */
	private $template;

	/**
	 * The header text for the email
	 *
	 * @since 2.7
	 */
	private $heading = '';

	/**
	 * Member ID
	 *
	 * @since 2.7
	 */
	public $member_id;

	/**
	 * Payment ID
	 *
	 * @since 2.7
	 */
	public $payment_id;

	/**
	 * Container for storing all tags
	 *
	 * @since 2.7
	 */
	private $tags;    
	
	public $merge_tags = array();  

	public static $defaultOptions = array(
		'html' => 'default HTML content',
		// 'text' => 'default TEXT content',
		'track_opens' => null,
		'track_clicks' => null,
		'auto_text' => null,
		'auto_html' => true,
		'inline_css' => null,
		'url_strip_qs' => null,
		'preserve_recipients' => null,
		'view_content_link' => null,
		'tracking_domain' => null,
		'signing_domain' => null,
		'return_path_domain' => null,
		'merge' => true,
		'merge_language' => 'mailchimp',
		'tags' => array(),
		'subaccount' => null,
	);

	/**
	 * Send the email
	 *
	 * @since 2.7
	 * @param string $template The mail template
	 * @param string $to The To address
	 * @param string $subject The subject line of the email
	 * @param string $message The body of the email
	 * @param string|array $attachments Attachments to the email
	 *
	 * @return bool Whether the email contents were sent successfully.
	 */   
    public function sendTemplate( $template, $to, $subject = null ){
    	
	    if( ! env('MD_APIKEY') ) {
	        return false;
	    }
	    
		if ( ! did_action( 'init' ) && ! did_action( 'admin_init' ) ) {
			_doing_it_wrong( __FUNCTION__, __( 'You cannot send emails with rcp_Emails until init/admin_init has been reached', 'rcp' ), null );
			return false;
		}

		$this->setup_email_tags();

		if ( empty( $this->payment_id ) ) {

			global $rcp_payments_db;

			$payment = $rcp_payments_db->get_payments( array(
				'user_id' => $this->member_id,
				'order'   => 'DESC',
				'number'  => 1
			) );

			$payment = reset( $payment );

			$this->payment_id = ! empty( $payment ) && is_object( $payment ) ? $payment->id : 0;
		}

		/**
		 * Hooks before email is sent
		 *
		 * @since 2.7
		 */
		do_action( 'rcp_email_send_before', $this );    	    
	
		$mandrill = new Mandrill( env('MD_APIKEY') );
		$email_tags = $this->get_tags();
		
		$merge_tags = array();
		
		foreach( $email_tags as $email_tag ) {
			if( isset( $email_tag['function'] ) && is_callable( $email_tag['function'] ) ) {
                $merge_tags[ $email_tag['tag'] ] = array(
        			'name' => strtoupper( $email_tag['tag'] ),
        			'content' => call_user_func( $email_tag['function'], $this->member_id, $this->payment_id, $email_tag['tag'] ),
    			);		    
			}
		}
	
        $recipients = $this->parse_recipients($to);
	
		$errors = array();
	
		try {
	
			$results = $mandrill->messages->sendTemplate( 
				$template, 
				array(), 
				array_merge(
					self::$defaultOptions,
					array(
						'text' => '',
						'to' => $recipients,
						'global_merge_vars' => array_merge( $merge_tags, $this->merge_tags ),
					)
				)
			);	
		
		} catch (Mandrill_ValidationError $e) {
			$errors[] = $e->getMessage();
		}
		

		/**
		 * Hooks after the email is sent
		 *
		 * @since 2.7
		 */
		do_action( 'rcp_email_send_after', $this );    		

		foreach ( $results as $result ) {
			if ( 'rejected' === $result['status'] ) {
				$errors[] = $result['reject_reason'];
			}
		}

        if( empty( $errors ) ) {
            return true;
        }

		rcp_log( '[Mandrill Emails] Mandrill send failure in RCP_Emails class: '. join('|', $errors ) );
		
		return false;
    }
    
    public function parse_recipients( $to ){
    	
    	$recipients = array();
    	
    	if ( is_array( $to ) ) {
    		foreach( $to as $recipient ) {
    			if ( empty( $recipient['email'] ) || empty( $recipient['type'] ) ){
    				$recipients[] = array(
    					'email' => $recipient,
    					'type' => 'to',
    				);
    			} else {
    				$recipient_bits = mailparse_rfc822_parse_addresses($to);
    				$recipients[] = array(
    					'email' => $recipient_bits['address'],
    					'name' => $recipient_bits['display'],
    					'type' => 'to',
    				);
    			}
    		}
    	}
    	
    	if ( is_string( $to ) ) {
    		$to = mailparse_rfc822_parse_addresses($to);
    		foreach( $to as $recipient ) {
    			$recipients[] = array(
    				'email' => $recipient['address'],
    				'name' => $recipient['display'],
    				'type' => 'to',
    			);
    		}
    	}	
    	
    	return $recipients;
    
    }    
    
	/**
	 * Search content for email tags and filter email tags through their hooks
	 *
	 * @since 2.7
	 * @param string $content Content to search for email tags
	 * @return string $content Filtered content
	 */
	protected function parse_tags( $content ) {

		// Make sure there's at least one tag
		if ( empty( $this->tags ) || ! is_array( $this->tags ) ) {
			return $content;
		}

		$new_content = preg_replace_callback( "/%([A-z0-9\-\_]+)%/s", array( $this, 'do_tag' ), $content );

		// Here for backwards compatibility
		$new_content = apply_filters( 'rcp_email_tags', $new_content, $this->member_id );

		return $new_content;
	}

	/**
	 * Setup all registered email tags
	 *
	 * @since 2.7
	 * @return void
	 */
	protected function setup_email_tags() {

		$tags = $this->get_tags();

		foreach( $tags as $tag ) {
			if ( isset( $tag['function'] ) && is_callable( $tag['function'] ) ) {
				$this->tags[ $tag['tag'] ] = $tag;
			}
		}

	}
    
	/**
	 * Parse a specific tag.
	 *
	 * @since 2.7
	 * @param $m Message
	 */
	protected function do_tag( $m ) {

		// Get tag
		$tag = $m[1];

		// Return tag if not set
		if ( ! $this->email_tag_exists( $tag ) ) {
			return $m[0];
		}

		return call_user_func( $this->tags[$tag]['function'], $this->member_id, $this->payment_id, $tag );
	}
    
}