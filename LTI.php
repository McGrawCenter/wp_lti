<?php
/*
Plugin Name: LTI
Plugin URI: http://mcgraw.princeton.edu/
Description:  Accept LTI links from Canvas or EdX. Upon valid connection, creates a user account if necessary and logs the user in.
Version: 0.3
Author: Ben Johnston
Author URI: http://www.princeton.edu
License: GPL2
*/

class WPLTI
{
    function __construct()
    {
        add_action('init', array( $this, 'display_config') );
        add_action('init', array( $this, 'check_lti') );

        // Register and define the settings page
        add_action('admin_init', [$this, 'admin_init']);
    }


    /**********************************
     *  After the plugins have been loaded, check lti
     **********************************/

    function check_lti()
    {
        if (isset($_REQUEST['lti_message_type']) && ($_REQUEST['lti_message_type'] = "basic-lti-launch-request")) {
            // if user is not currently logged in, check LTI, otherwise do nothing

            $logged_in = is_user_logged_in();

            if (!$logged_in) {
                $options = get_option('lti_options');

                $name = $options['lti_name'];
                $pass = $options['lti_pass'];
                $secret = $options['lti_secret'];

                $CFG = new StdClass();
                $CFG->dirroot = dirname(__FILE__) . '/';
                $CFG->consumer = $pass;
                $CFG->secret = $secret;
                $CFG->set_session = false;
                $CFG->redirect = false;

                require_once 'lib/ims-blti/blti.php';

                $lti = new BLTI($CFG->secret, $CFG->set_session, $CFG->redirect);

                /*
			print_r($CFG);
			print_r($_REQUEST);
			print_r($lti);
			die();	
			*/

                if ($lti->valid == 1) {
                    if (isset($_POST['lis_person_contact_email_primary']) && ($email_address = strtolower($_POST['lis_person_contact_email_primary']))) {
                        $username = $this->generateUsername($email_address);
                    } else {
                        if (isset($_POST['lis_person_sourcedid'])) {
                            $username = $_POST['lis_person_sourcedid'];
                            $email_address = $username . "@wordpressss.org";
                        } else {
                            $username = $_POST['user_id'];
                            $email_address = $username . "@wordpressss.org";
                        }
                    }

                    if ($user = get_user_by('login', $username)) {
                        // user exists in the system
                        // are they part of this blog? If not then add them

                        if (!is_user_member_of_blog($user->ID, get_current_blog_id())) {
                            add_user_to_blog(get_current_blog_id(), $user->ID, "subscriber");
                            wp_update_user(['ID' => $user->ID, 'role' => 'subscriber']);
                        }

                        // log them in
                        session_start();

                        // If no error received, set the WP Cookie
                        if (!is_wp_error($user)) {
                            wp_clear_auth_cookie();
                            wp_set_current_user($user->ID); // Set the current user detail
                            wp_set_auth_cookie($user->ID); // Set auth details in cookie

                            update_user_caches($user);
                        } else {
                            die("Denied");
                        }
                    } else {
                        // user does not exist

                        $password = wp_generate_password(12, true);

                        if ($user_id = wp_create_user($username, $password, $email_address)) {
                            $user = new WP_User($user_id);

                            // If no error received, set the WP Cookie
                            if (!is_wp_error($user)) {
                                $role = 'subscriber';
                                if (is_multisite()) {
                                    add_user_to_blog(get_current_blog_id(), $user_id, $role);
                                }
                                wp_update_user(['ID' => $user_id, 'role' => $role]);

                                wp_clear_auth_cookie();
                                wp_set_current_user($user->ID); // Set the current user detail
                                wp_set_auth_cookie($user->ID); // Set auth details in cookie

                                update_user_caches($user);
                            } else {
                                die("Could not add user to blog");
                            }
                        } else {
                            die("Cound not create user");
                        }
                    }
                }
            } // end if not logged in
        } // end if LTI request sent
    } // end check_lti

    /***********************************
     * Path to the config.xml file is the https://siteaddress/?lticonfig
     ***********************************/

    function display_config()
    {
        if (isset($_GET['lticonfig'])) {
            $x = new LTIConfig();
            $x->display();
        }
    }

    /***********************************
     * Create a username from the first part of the email address
     ***********************************/
    function generateUsername($email)
    {
        $arr = explode("@", $email);
        return $arr[0];
    }

    /**********************
     * Settings Page Section
     ***********************************************************/

    /************************************
     * Register setting section page under Reading
     ************************************/
    function admin_init()
    {
        register_setting(
            'reading', // settings page
            'lti_options', // option name
            [$this, 'validate_options'] // validation callback
        );

        add_settings_field(
            'lti_notify_boss', // id
            'LTI', // setting title
            [$this, 'lti_setting_input'], // display callback
            'reading', // settings page
            'default' // settings section
        );
    }

    /************************************
     * Validate user input and return validated data
     ************************************/
    function validate_options($input)
    {
        $valid = [];
        $valid['lti_name'] = sanitize_text_field($input['lti_name']);
        $valid['lti_pass'] = sanitize_text_field($input['lti_pass']);
        $valid['lti_secret'] = sanitize_text_field($input['lti_secret']);
        return $valid;
    }

    /************************************
     * Display and populate the form fields
     ************************************/
    function lti_setting_input()
    {
        // get option 'boss_email' value from the database
        $options = get_option('lti_options');

        $name = $options['lti_name'];
        $pass = $options['lti_pass'];
        $secret = $options['lti_secret'];

        $config_url = site_url() . "/?lticonfig";
        ?>
		<p><input id='lti_name' name='lti_options[lti_name]' type='text' value='<?php echo esc_attr($name); ?>' /> Title (leave blank to use the title of the blog)</p>
		<p><input id='lti_pass' name='lti_options[lti_pass]' type='text' value='<?php echo esc_attr($pass); ?>' /> Pass</p>
		<p><input id='lti_secret' name='lti_options[lti_secret]' type='text' value='<?php echo esc_attr($secret); ?>' /> Secret</p>
		<p>The LTI config XML can be found at<br /><a href="<?php echo $config_url; ?>" target="_blank"><?php echo $config_url; ?></a></p>
		<?php
    }
}



/**************************
* LTIConfig class generates xml config file used by Canvas
**************************/

class LTIConfig
{
    function __construct()
    {
        $options = get_option('lti_options');

        $this->base = file_get_contents(plugin_dir_path(__FILE__) . 'config.xml');
        $this->title = $options['lti_name'];
        if ($this->title == "") {
            $this->title = get_bloginfo('name');
        }
        $this->description = "An LTI connection to the " . get_bloginfo('name') . " website on Wordpress";
        $this->icon = plugin_dir_url(__FILE__) . 'icon.png';
        $this->launch_url = site_url();
        $this->platform = "canvas.instructure.com";
        $this->tool_id = "princeton_wp_lti_tool";
    }

    function display()
    {
        foreach ($this as $key => $value) {
            $this->base = preg_replace("/{" . $key . "}/", $value, $this->base);
        }
        header("Content-Type:text/xml");
        echo $this->base;
        die();
        wp_die();
    }
}

new WPLTI();

