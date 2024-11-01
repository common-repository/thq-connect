=== TidyConnect ===

Contributors: mbelstead
Author URI: https://github.com/mbelstead
Donate link: https://paypal.me/MarkBelstead
Plugin URI: https://wordpress.org/plugins/thq-connect
Tags: Organizations, TidyHQ, Administration, Associations, Management
Requires at least: 4.6
Tested up to: 5.2.1
Stable tag: 3.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

TidyHQ and Wordpress integration. TidyConnect (previously THQ Connect) not only allows your members to sign in to WordPress with their TidyHQ credentials but it has been enhanced to also share information such as contact details, events, meetings, tasks and more.  A TidyHQ organisation is required.  Head to <tidyhq.com> now and start making your organisation management better.


== Description ==
TidyConnect has been designed to reduce the need for different log in details for your members and admins.  TidyConnect gives your members and admins more tools, not only for your website, but opens the door for more plug ins while keeping all your main administrative functionality in TidyHQ.  To keep your website secure, if they do not have a WordPress account with your organisation, the plugin will allow them to log in but only create them as a user with basic WordPress access.  Visitors not "Connected" to your organisation will be asked to connect when they log in giving you more opportunities to gain new members!

TidyConnect is Open-Source and available on [GitHub](http://www.github.com/mbelstead/tidy-connect-wp/).  If you have any suggestions, ideas or would like to contribute to TidyConnect, email [tidyconnect@iinet.net.au]('mailto:tidyconnect@iinet.net.au')

For more information, head to TidyConnect -> Help in your WordPress admin menu.


== Key Features ==
* Log in to WordPress using your TidyHQ email and password, meaning you don't need to save multiple passwords.
* Allow your TidyHQ contact information and more be shared across both platforms and your members easier access to keep their information up to date.
* Display a calendar or widget of all your TidyHQ events, meetings, tasks and sessions on your WordPress site.
* More features coming soon!


== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/tidy_connect` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. A TidyHQ Application is required.  You can add one [here](https://dev.tidyhq.com/oauth_applications).  Your application name should be TidyConnect and your Redirect URI should be your site's WordPress URL (ie. http://yoursite.com).
4. Use the Settings->TidyConnect screen to add Client ID & Client Secret from step 3.
5. Log out of WordPress, then log in by clicking the TidyHQ logo on your login page. Note: A new WordPress user with basic roles will be created. You will need to give this user any additional roles.


== Frequently Asked Questions ==
= What is TidyHQ =
TidyHQ is a cloud-based platform designed to help streamline the administration and management of organisations.  For more information about how TidyHQ can help your organisation visit <tidyhq.com>.

= Can my members log in to WordPress using TidyHQ credentials? =
Provided the TidyHQ credentials are correct, TidyConnect will allow them to log in, however they will not have any access to additional WordPress or TidyHQ functions unless granted through the WordPress users or TidyHQ Users & Roles.

= Can people not associated with my organisation log in? =
As above.  Provided the TidyHQ credentials are correct, TidyConnect will allow them to log in, however they will not have any access to additional WordPress or TidyHQ functions.

= Is my information secure? =
Data used by TidyConnect is read-only.  TidyConnect promises to never store confidential information from TidyHQ.  Login information is sent directly to TidyHQ for validation.

= Can I still log in with my normal WordPress username/password? =
Yes.  Simply login as you have done previously. To log in via TidyHQ, use the image displayed on your wp-login.php page.

= I have a query or suggestion.  Who do I contact? =
If you email [tidyconnect@iinet.net.au]('mailto:tidyconnect@iinet.net.au'), I will get back to you as soon as I can.


== Screenshots ==
1. Login screen.  Select WordPress for normal user access or TidyHQ to use TidyHQ credentials.  Alternatively add [thq_connect_login_form] or use the Login via TidyHQ widget to display a login form somewhere on your website.
2. Add user via TidyHQ; Menu option to Add user via TidyHQ; Allowing you to easily add members to your website;


== Changelog ==

= 3.0.1 =
* Updated help to include shortcodes.
* [tidy_connect_login] shortcode now allows image=true to display a TidyHQ image instead of text.
* A notice will now display when you are updating a Wordpress user which is linked to TidyHQ.
* WP User description and TidyHQ contact Additional Information are now linked.
* Your profile information will now be updated from TidyHQ more often.
* Calendar settings have returned. If an item is disabled in settings, all references to those items won't be displayed.
* Quicklink to your TidyHQ Dashboard... click on your site name at the top of your page.
* Option to data sync TidyHQ contacts and WordPress users. Enable one way keeping one system as a primary, or both ways allowing consistant information on both systems.

= 3.0.0 =
TidyConnect has been completely redeveloped and now works slightly differently. It no longer matches the user email address, but now will always create a new user for TidyHQ contacts upon login or when added by an administrator. This allows full compatibility between TidyHQ and WordPress allowing WordPress to always maintain correct information from TidyHQ.

== Prior Versions ==
All prior versions are no longer supported. Functions from these versions may be implemented in the future.
