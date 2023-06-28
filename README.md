# wp_lti

A Wordpress plugin allowing for the creation of users who arrive at a single or multi-site Wordpress instance via LTI.

Go to Settings > Reading to set the LTI credentials.

This plugin has been tested with Canvas and EdX. Note that in EdX, permission to send user data via LTI must be enabled first. 

In Canvas, use the configuration type 'by URL' when adding the LTI link (Settings->Apps->View Apps Configurations->+App). An XML configuration file is available at http://websiteaddress/?lticonfig. The template, config.xml in the plugin directory, can be edited as needed.



