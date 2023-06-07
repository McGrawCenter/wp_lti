<?php
/*
Plugin Name: LTI
Plugin URI: http://www.princeton.edu
Description:  Restrict access to a website to only users coming from an LTI link
Version: 0.2
Author: Ben Johnston
Author URI: http://www.princeton.edu
License: GPL2
*/


$denied_message = "This site can only be accessed via LTI";
$dashboard_access = strstr($_SERVER['REQUEST_URI'],'wp-admin');


if((isset($_REQUEST['lti_message_type']) && $_REQUEST['lti_message_type'] = "basic-lti-launch-request") || $dashboard_access ) {
    // This is an LTI request OR the user is trying to access the dashboard
    add_action( 'plugins_loaded', 'check_lti' );
}
else { 
    //echo $denied_message." B";
    //die();
}




/**********************************
*  After the plugins have been loaded, check lti
**********************************/



function check_lti() {


  global $denied_message;

  // if user is not currently logged in, check LTI, otherwise do nothing
  $logged_in = is_user_logged_in();
  
  
  if( !$logged_in ) {


        $options = get_option( 'lti_options' );

	$name = $options['lti_name'];
	$pass = $options['lti_pass'];
	$secret = $options['lti_secret'];
	
	$CFG = new StdClass();
	$CFG->dirroot = dirname(__FILE__) . '/';
	$CFG->consumer = $pass;
	$CFG->secret = $secret;
	$CFG->set_session = false;
	$CFG->redirect = false;

 	require_once('lib/ims-blti/blti.php');
  
	$lti = new BLTI($CFG->secret,$CFG->set_session,$CFG->redirect);

	if($lti->valid == 1) {

			session_start();
			    
			$email_address = strtolower($_REQUEST['lis_person_contact_email_primary']);
			$username = generateUsername($email_address);
			
			if ( $user = get_user_by('login', $username ) ) // user exists, log them in
			{
			
				// the user exists, log them in
				$user_id = $user->data->ID;
				$username = $user->data->user_login;
				$role = 'subscriber';
			       			
				clean_user_cache($user_id);
				wp_clear_auth_cookie();
				//print_r($lti);
				//echo $user_id; die();

				if(!wp_set_current_user($user_id)) { die('could not set current user'); }
				if(!wp_set_auth_cookie($user_id, true, true)) {  
			        	die('could not set cookie');
			       	}

				update_user_caches($user);
				$redirect_to = site_url();
				wp_safe_redirect( $redirect_to );
				exit();
				
			}
			else { // user is not in the system

			       $password = wp_generate_password( 12, true );

			       if($user_id = wp_create_user( $username, $password, $email_address )) { 
				  $user = new WP_User( $user_id );
				  $user->set_role('subscriber');
				  wp_clear_auth_cookie();
				  wp_set_current_user ( $user->ID );
				  wp_set_auth_cookie  ( $user->ID );
				  $redirect_to = site_url();
				  wp_safe_redirect( $redirect_to );
				  exit();
			       }
			       else { die("Cound not create user"); }


			}
			

	}
	else {	// LTI not valid
	
	 	echo $denied_message." A";
	 	die();
	}




  }  // end !$logged_in
  else { 
  
	// the user is valid and was already logged in    
  }

} // end check_lti





function getRole($roles_str) {
  if(strstr($roles_str, 'Instructor')) { return "administrator"; }
  else { return "subscriber"; }
}


function generateUsername($email) {
  $arr = explode("@",$email);
  return $arr[0];
}







// Register and define the settings
add_action('admin_init', 'lti_admin_init');

function lti_admin_init(){
	register_setting(
		'reading',                 // settings page
		'lti_options',          // option name
		'lti_validate_options'  // validation callback
	);
	
	add_settings_field(
		'lti_notify_boss',      // id
		'LTI',              // setting title
		'lti_setting_input',    // display callback
		'reading',                 // settings page
		'default'                  // settings section
	);

}
// Validate user input and return validated data
function lti_validate_options( $input ) {
	$valid = array();
	$valid['lti_name'] = sanitize_text_field( $input['lti_name'] );
	$valid['lti_pass'] = sanitize_text_field( $input['lti_pass'] );
	$valid['lti_secret'] = sanitize_text_field( $input['lti_secret'] ); ;
	return $valid;
}




// Display and fill the form field
function lti_setting_input() {
	// get option 'boss_email' value from the database
	$options = get_option( 'lti_options' );

	$name = $options['lti_name'];
	$pass = $options['lti_pass'];
	$secret = $options['lti_secret'];
	// echo the field
	?>
	<p><input id='lti_name' name='lti_options[lti_name]' type='text' value='<?php echo esc_attr( $name ); ?>' /> Title (leave blank to use the title of the blog)</p>
	<p><input id='lti_pass' name='lti_options[lti_pass]' type='text' value='<?php echo esc_attr( $pass ); ?>' /> Key</p>
	<p><input id='lti_secret' name='lti_options[lti_secret]' type='text' value='<?php echo esc_attr( $secret ); ?>' /> Secret</p>
	<?php
}
