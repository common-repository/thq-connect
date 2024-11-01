<?PHP
/**
* Tidy Connect
* 
* @package		TidyConnect
* @author		Mark Belstead
* @copyright		2017-2019 Mark Belstead
* @licence		GPL-2.0+
* @link			https://github.com/mbelstead/tidy-connect-wp
*
* @wordpress-plugin
* Plugin Name:		TidyConnect
* Plugin URI:		https://github.com/mbelstead/tidy-connect-wp
* Version: 		3.0.1
* Requires WP: 		4.4
* Description:		TidyHQ is a cloud-based platform designed to help streamline the administration and management of organisations.  For more information about how TidyHQ can help your organisation visit [tidyhq.com](http://tidyhq.com).  For the plugin to work correctly, a WordPress user will be linked to a corresponding TidyHQ user. If they are not in WordPress, the plugin will create them as a user with basic WordPress access. An admin will need to modify their access to allow more functionality. Data used by TidyConnect is read-only.  TidyConnect promises to never store confidential information from TidyHQ.
* Author:		Mark Belstead
* License:		GPL2
*  
* TidyConnect is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.
* 
* TidyConnect is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
*  
* You should have received a copy of the GNU General Public License along with TidyConnect. If not, see http://www.gnu.org/licenses/.
*/


if( ! class_exists( "TidyConnect" ) ) {
	
	class TidyConnect {
		
		public $settings;
		public $configured;
		private $access_token;
		private $tidy_user;
		private $wp_user;
		
		public function __construct() {
			
			$this->settings = array();
			$this->settings['tidy_connect_client_id'] = get_option( "tidy_connect_client_id" );
			$this->settings['tidy_connect_client_secret'] = get_option( "tidy_connect_client_secret" );
			$this->settings['tidy_connect_domain_prefix'] = get_option( "tidy_connect_domain_prefix" );

			$this->configured = !empty( $this->settings['tidy_connect_client_id'] ) && !empty( $this->settings['tidy_connect_client_secret'] ) && !empty( $this->settings['tidy_connect_domain_prefix'] ) ? true : false;
			
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu_items' ) );
			add_action( 'login_form', array( $this, 'login_form_alt' ) );
			add_action( 'admin_post_add_user_via_tidyhq', array( $this, 'add_user_via_tidyhq_post' ) );
			add_action( 'show_user_profile', array( $this, 'profile_page_comment'), 10, 1);
			add_action( 'edit_user_profile', array( $this, 'profile_page_comment'), 10, 1);
			add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 999 );
			$do_update_user = get_option( 'tidy_connect_update_tidyhq_contact' );
			if( $do_update_user ) { add_action( 'profile_update', array( $this, 'update_tidyhq_contact'), 10, 2 ); }
			
			add_shortcode( 'tidyhq_login_link', array( $this, 'login_link_shortcode' ) );
			
			add_filter( 'plugin_action_links', array( $this, 'plugin_page_links' ), 10, 2 );
			add_filter( 'get_avatar' , array( $this,'display_avatar' ), 1, 5 );
		
			require_once( dirname( __FILE__ ) . "/calendar.php" ); //include calendar
			
		} //end __construct
		
		
		public function display_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
			$user = $this->get( 'contacts/me' );
			
			if( !empty( $user['profile_image'] ) ) {
				$avatar = $user['profile_image'];
				$avatar = "<img alt='{$alt}' src='{$avatar}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
			return $avatar;
			}
			
		}
		
		
		public function admin_bar( $wp_admin_bar ) {
			
			$user = wp_get_current_user();
			$href = "https://" . $this->settings['tidy_connect_domain_prefix'] . ".tidyhq.com/";
			
			$wp_admin_bar->add_node(
				array(
					'parent'	=> 'site-name',
					'id'		=> 'tidyhq-dashboard',
					'title'		=> 'Visit TidyHQ Dashboard',
					'href'		=> $href . "dashboard"
				)
			);
			
			$email_count = 0;
			$task_count = 0;
			$event_count = 0;
			
			/* get the emails */
			$emails = $this->get(
				'emails',array(
					'read'=>'false',
					'deleted'=>'false',
					'junk'=>'false',
					'archived'=>'false',
					'way'=>'inbound',
					'type'=>'email',
					'limit'=>'100')
			); //Max 100 emails to make run faster
			
			/* Make totals match, if 100, it will show more than 100 */
			if( !empty( $emails ) ) {
				$email_count = ( count( $emails ) == 100 ) ? "100+" : count( $emails );
			} //end if
			
			/* Get the events */
			$now = date('c'); //only looking for events after today
			$events = $this->get( 'events', array( 'start_at'=>$now, 'limit'=>'100' ) );
	
			/* Make totals match, if 100, it will show more than 100 */
			if( !empty($events) ) {
				$event_count = ( count($events) == 100 ) ? '100+' : count( $events );
			} //end if
	
			/* Get the Tasks */
			$tasks_get = 'contacts/' . str_replace( "tidy_", "", $user->user_login ) . '/tasks';
			$tasks = $this->get( $tasks_get, array( 'completed'=>'false', 'limit'=>'100' ) );
	
			/* Make totals match, if 100, it will show more than 100 */
			if( !empty( $tasks ) ) {
				$task_count = ( count( $tasks ) == 100 ) ? '100+' : count( $tasks );
			} //end if
			
			$href = "https://" . $this->settings['tidy_connect_domain_prefix'] . ".tidyhq.com";
			
			if( $email_count > 0 ) {
				$label = " unread email";
				$label .= $email_count > 1 ? "s" : "";
				$label .= "!";
				$wp_admin_bar->add_node( array(
					'id'     => 'tidy-connect-unread-email',
					'title'  => '<span class="ab-icon dashicons-before dashicons-email-alt"></span>'.__( $email_count, 'tidy-connect' ),
					'href'   => $href.'/communicate/inbox',
					'meta'   => array(
						'target'   => '_self',
						'title'    => __( $email_count . $label, 'tidy-connect' ),
						'html'     => '',
						),
					)
				);
			} //end if
			/* Event count has a value */
			if( $event_count > 0 ) {
				$label = " upcoming event";
				$label .= $event_count > 1 ? "s" : "";
				$label .= "!";
				$wp_admin_bar->add_node( array(
					'id'     => 'tidy-connect-upcoming-events',
					'title'  => '<span class="ab-icon dashicons-before dashicons-calendar-alt"></span>' . __( $event_count, 'tidy-connect' ),
					'href'   => $href . '/member/events',
					'meta'   => array(
						'target'   => '_self',
						'title'    => __( $event_count . $label, 'tidy-connect' ),
						'html'     => '',
					),
				)
				);
			} //end if
			
			/* Task count has a value */
			if( $task_count > 0 ) {
				$label = " outstanding task";
				$label .= $task_count > 1 ? "s" : "";
				$label .= "!";
				$wp_admin_bar->add_node( array(
					'id'     => 'tidy-connect-outstanding-tasks',
					'title'  => '<span class="ab-icon dashicons-before dashicons-clipboard"></span><span class="ab-label">' . __( $task_count, 'tidy-connect' ) . "</span>",
					'href'   => $href . '/member/tasks',
					'meta'   => array(
						'target'   => '_self',
						'title'    => __( $task_count . $label, 'tidy-connect' ),
						'html'     => '',
					),
				)
				);
			} //end if
			
		} //end function
		
		
		public function init_settings() {
			
			register_setting( 'tidy_connect_settings', 'tidy_connect_client_id' );
			register_setting( 'tidy_connect_settings', 'tidy_connect_client_secret' );
			register_setting( 'tidy_connect_settings', 'tidy_connect_domain_prefix' );
			register_setting( 'tidy_connect_settings', 'tidy_connect_update_wp_user' );
			register_setting( 'tidy_connect_settings', 'tidy_connect_update_tidy_contact' );
			register_setting( 'tidy_connect_calendar_settings', 'tidy_connect_calendar_settings' );
			
			//add_action( 'wp_before_admin_bar_render', array( $this, 'admin_bar' ) );
			
			if( !empty( $_GET['tidy_connect_user_created'] ) ) {
				add_action( 'admin_notices', function() {
					echo "<div class='notice notice-success is-dismissible'><p>User created successfully.</p></div>";
					}
				);
			}
			if( !empty( $_GET['tidy_connect_user_notcreated'] ) ) {
				add_action( 'admin_notices', function() {
					echo "<div class='notice error is-dismissible'><p>" . str_replace("_", " ", $_GET['tidy_connect_user_notcreated'] ) . "</p></div>";
					}
				);
			}
			
		} //end function
		
		
		public function init() {
			
			if( !is_user_logged_in() && $this->configured && !empty( $_GET['code'] ) ) {
				
				/* Required content to retrieve token */
				$content = array(
					"client_id"		=> $this->settings['tidy_connect_client_id'],
					"client_secret"	=> $this->settings['tidy_connect_client_secret'],
					"domain_prefix"	=> $this->settings['tidy_connect_domain_prefix'],
					"redirect_uri" 	=> site_url(),
					"code"			=> $_GET['code'],
					"grant_type"	=> "authorization_code"
				);  //end of content
	
				/* send request for token */
				$response = wp_remote_post(
					"https://accounts.tidyhq.com/oauth/token",
					array(
						'method' => 'POST',
						'headers' =>  array("Content-type: application/json"),
						'body' => $content
					)
				); //end of response
				/* Make response readable as array */
				$newresponse = json_decode($response['body'], true);
	
				/* Use token for next step */
				if(!empty($newresponse['access_token'])) {
					//Save access token
					$this->access_token = $newresponse['access_token'];
			
					//Get the current user from TidyHQ
					$this->tidy_user = $this->get( '/contacts/me', array( 'access_token'=>$this->access_token));
			
					//Get the current user id
					if(!empty( $this->tidy_user['id'] ) ) {
						$this->wp_user = get_user_by( 'login', "tidy_" . $this->tidy_user['id'] );
				
						if( ! $this->wp_user ) {
							// User does not exist in WordPress. Create new user.
							$new_user_id = wp_create_user(
								"tidy_" . $this->tidy_user['id'],
								wp_generate_password(),
								$this->tidy_user['email_address']
							);
							wp_new_user_notification( $new_user_id, false, 'both' ); //email admin that a new user was created (if enabled)
							$this->wp_user = get_user_by( 'login', "tidy_" . $this->tidy_user['id'] );
						}//end if
				
						$active_user = $this->wp_user;
						
						$do_update_user = get_option( 'tidy_connect_update_wp_user' );
						if( $do_update_user ) {
							$update_user = get_userdata( $this->wp_user->ID );
							$args = array();
							$args['ID'] = $this->wp_user->ID;
							$args['user_login'] = ( $update_user->user_login != "tidy_" . $this->tidy_user['id'] ) ? "tidy_" . $this->tidy_user['id'] : $update_user->user_login;
							$args['user_email'] = ( $update_user->user_email != $this->tidy_user['email_address'] ) ? $this->tidy_user['email_address'] : $update_user->user_email;
							$args['first_name'] = ( $update_user->first_name != $this->tidy_user['first_name'] ) ? $this->tidy_user['first_name'] : $update_user->first_name;
							$args['last_name'] = ( $update_user->last_name != $this->tidy_user['last_name'] ) ? $this->tidy_user['last_name'] : $update_user->last_name;
							if( !empty( $this->tidy_user['nick_name'] ) ) {
								$args['nickname'] = $this->tidy_user['nick_name'];
							} else {
								$args['nickname'] = $args['first_name'];
							}
							$args['display_name'] = $args['nickname'];
							if( !empty( $this->tidy_user['details'] ) ) { $args['description'] = $this->tidy_user['details']; }
							if( !empty( $this->tidy_user['website'] ) ) { $args['user_url'] = $this->tidy_user['website']; }
							$update_user_id = wp_update_user( $args );
						} //end if
            
						update_user_meta( $active_user->ID, 'tidy_connect_token', $this->access_token ); //updates the user's THQ token
						wp_set_current_user( $active_user->ID ); //set the current user
						wp_set_auth_cookie( $active_user->ID ); //set a cookie.  Yum.
				
						$url = admin_url(); //get the url to admin page
						wp_redirect($url); //go to admin page
						exit(); // finished
				
					} else {
					
						//USER DOES NOT EXIST IN TIDYHQ
						error_log( "Attempted login via TidyHQ but no TidyHQ contact was found.", 0);
				
					} //end if
			
				} else {
					/* No token.  Code was bad */
					error_log('Unable to log in via TidyHQ. Code incorrect or expired.');
					//DISPLAY AN ERROR
				} //end else
				
			} //end if
			$wp_user_id = !empty( $_GET['user_id'] ) ? $_GET['user_id'] : get_current_user_id();
			$do_update_user = get_option( 'tidy_connect_update_wp_user' );
			if( $wp_user_id && $do_update_user ) { $this->update_wordpress_user( $wp_user_id ); }
		} //end function
		
		
		public function get( $type, $and = array() ) {
			
			$wp_user = wp_get_current_user();
			
			if( ! empty( $wp_user ) ) {
				
				$access_token = get_user_meta( $wp_user->ID, 'tidy_connect_token', true); //get access token from DB.
				$access_token = !empty( $and['access_token'] ) ? $and['access_token'] : $access_token; //use defined access token if available.
				
				if( !empty( $access_token ) ) {
					//Only start if there is an access token.
					
					$requests = '';
					//Break down $and parameters
					foreach( $and as $key => $value ) {
						$requests .= ( $key != 'access_token' )  ? $key . "=" . $value . "&" : ""; //always adds the & on the end.  Last & is reserved for Token at the end
					} //end foreach
					
					$url = "https://api.tidyhq.com/v1/" . $type . "?" . $requests . "access_token=" . $access_token; //create URL
					
					$response = wp_remote_request(
						$url,
						array(
							'method' => 'GET',
							'timeout' => 45,
							'headers' => array( "Content-type" => "application/json", "charset" => "utf-8" )
						)
					);	//Send request
					
					if ( is_wp_error( $response ) ) {
						$error_message = $response->get_error_message();
						error_log( "Something went wrong processing TidyHQ request [" . $url. "]: $error_message" );
						return false;
					} else {
						$userdata = json_decode( $response['body'], true ); //make response readable as array
						/* If the response isn't as expected */
						if( !empty( $userdata['message'] ) ) {
							//$newresponse = $response['response'];
							//error_log( "There was an error processing TidyHQ request (" . $type . ") - " . $newresponse['code'] . " " . $newresponse['message'].': ' . $userdata['message'] );
							return false;
						} else {
							return $userdata; //return correct response
						} //end else
					} //end if/else
				} else {
					error_log( "Unable to process request to TidyHQ. Token not found.", 0);
					return false;
				} //end if
			} else {
				error_log( "Unable to process request to TidyHQ. User not found.", 0 );
				return false;
			} //end if
			
		} //end function
		
		
		public function put( $type, $args ) {
			
			$wp_user = wp_get_current_user();
			
			if( ! empty( $wp_user ) ) {
				
				$access_token = get_user_meta( $wp_user->ID, 'tidy_connect_token', true); //get access token from DB.
				$access_token = !empty( $and['access_token'] ) ? $and['access_token'] : $access_token; //use defined access token if available.
				
				if( !empty( $access_token ) ) {
					//Only start if there is an access token.
					
					$url = "https://api.tidyhq.com/v1/" . $type; //create URL
					
					$response = wp_remote_request(
						$url,
						array(
							'method' => 'PUT',
							'timeout' => 45,
							'headers' => array( "Content-type" => "application/json", "Authorization" => "Bearer " . $access_token ),
							'body'	=> json_encode( $args )
						)
					);	//Send request
					
					if ( is_wp_error( $response ) ) {
						$error_message = $response->get_error_message();
						error_log( "Something went wrong processing TidyHQ PUT request [" . $url. "]: $error_message" );
						return false;
					} else {
						$userdata = json_decode( $response['body'], true ); //make response readable as array
						/* If the response isn't as expected */
						if( !empty( $userdata['message'] ) ) {
							$newresponse = $response['response'];
							error_log( "There was an error processing TidyHQ request (" . $type . ") - " . $newresponse['code'] . " " . $newresponse['message'].': ' . $userdata['message'] );
							return false;
						} else {
							return $userdata; //return correct response
						} //end else
					} //end if/else
				} else {
					error_log( "Unable to process request to TidyHQ. Token not found.", 0);
					return false;
				} //end if
			} else {
				error_log( "Unable to process request to TidyHQ. User not found.", 0 );
				return false;
			} //end if
			
		} //end function
		
		
		public function login_form_alt() {
			
			if( $this->configured ) {
				echo sprintf(
					"<center><div style='background: #1531ac; padding: 2px; display: block; text-align: center; width: 100px;'><a href='%s' title='Click here to log in via TidyHQ' style='color: #fff; text-decoration: none;'>Login via<br><img src='%s' alt='Click here to log in via TidyHQ' width='100'></a></div></center>",
					$this->login_url(),
					"https://uploads-ssl.webflow.com/5b43b3614a052736729f16dd/5b61f3c1a5fc3add06d0fd95_TIDYHQ_Inverse.png"
				);
			} //end if
			
		} //end function
		
		
		public function admin_menu_items() {
			
			/* Menu items to show only when settings are defined */
			
			if( !empty( $this->settings['tidy_connect_client_id'] ) && !empty( $this->settings['tidy_connect_client_secret'] ) ) {
				/* Add user via TidyHQ */
				add_users_page(
					'Add User via TidyHQ',
					'Add User via TidyHQ',
					'edit_users',
					'add-user-via-tidyhq',
					array( $this, 'add_user_via_tidyhq_page' )
				);
			} //end if
			
			/* Menu items to show at all times */
			
			/* Tidy-Connect Settings */
			add_options_page(
				'TidyConnect',
				'TidyConnect',
				'manage_options',
				'tidy-connect-options',
				array( $this, 'tidy_connect_options_page' )
			);
			add_action( 'admin_init', array( $this, 'init_settings' ) );
			
		} //end function
		
		
		
		
		
		public function tidy_connect_options_page() {
			$active_tab = !empty( $_GET['tab'] ) ? $_GET['tab'] : 'settings';
			?>
			<div class="wrap">
				<h1><?php echo get_admin_page_title() . " .:. " . ucfirst( str_replace( "_", " ", $active_tab) ); ?></h1>
				<h2 class="nav-tab-wrapper">
					<?php
						$tabs = array( 'settings', 'calendar', 'help' );
						foreach($tabs as $tab) {
							echo sprintf(
								"<a href='options-general.php?page=tidy-connect-options&tab=%s' class='nav-tab%s'>%s</a>",
								$tab,
								$active_tab != $tab ? "" : " nav-tab-active",
								ucfirst( str_replace( "_", " ", $tab ) )
							);
						}
					?>
				</h2>
				<?php
					switch( $active_tab ) {
						case 'help':
							$plugin_data = get_plugin_data( __FILE__ );
							?>
							<h2>About <?PHP echo $plugin_data['Name']; ?></h2>
							<p><?PHP echo $plugin_data['Description']; ?></p>
							<dl>
								<dt><strong>Version</strong></dt>
								<dd><?PHP echo $plugin_data['Version']; ?></dd>
								<dt><strong>Website:</strong></dt>
								<dd><a href="<?php echo $plugin_data['PluginURI']; ?>"><?php echo $plugin_data['PluginURI']; ?></a></dd>
								<dt><strong>Email:</strong></dt>
								<dd><a href="mailto:tidyconnect@iinet.net.au">tidyconnect@iinet.net.au</a></dd>
							</dl>
							<h2>Logging in</h2>
							<p>You can use your WordPress username and password, or click the "Log in via TidyHQ" image on your login screen. Note: You will need to log in via TidyHQ to take full advantage of TidyConnect features. As TidyConnect will create a new user upon first login via TidyHQ, you may need to add additional roles.</p>
							<h2>Shortcode</h2>
							<dl>
								<dt><code>[tidy_connect_login]</code></dt>
								<dd>Add a login link to your website. Use label=yourtext or image=true to change what the link displays. If the user is already logged in, a simple "Log out" link will display.</dd>
								<dt><code>[tidy_connect_calendar]</code></dt>
								<dd>Display your TidyHQ calendar to your members. Those with access to view all events, meetings, tasks and sessions will show all, otherwise it will just show all public meetings and events.</dd>
							</dl>
							<h2>Need more help?</h2>
							<p>You can send an email with any problems, questions or suggestions to <a href='mailto:tidyconnect@iinet.net.au'>tidyconnect@iinet.net.au</a>.  Note: As TidyConnect is not affiliated with TidyHQ all and only TidyConnect queries should go here.  TidyHQ support should be directed using the chat functionality on your TidyHQ site.</p>
							<?php
						break;
						case 'calendar':
						?>
						<p>You can change the colours and options for the calendar below.  Alternatively, you can disable those event types from displaying (this will effect all users).</p>
						<form method="post" action="options.php">
						<?php $options = get_option( 'tidy_connect_calendar_settings' ); ?>
						<?php settings_fields( 'tidy_connect_calendar_settings' ); ?>
						<?php do_settings_sections( 'tidy_connect_calendar_settings' ); ?>
						<table class="form-table">
							<tr valign="top">
								<th scope="row">Events</th>
								<td>
									<?php
									echo sprintf('<input type="text" id="tidy_connect_calendar_event" name="tidy_connect_calendar_settings[event_colour]" value="%s" style="background: %s; color: #fff;" class="thq-connect-cf" data-default-color="#0b88f1" /> <label><input type="checkbox" value="disabled" name="tidy_connect_calendar_settings[event_flag]" %s /> Disable?</label><p class="description" id="thq_connect_calendar_event_description">The colour which Events show in the calendar.</p>',
									isset($options['event_colour']) ? $options['event_colour'] : '#0b88f1',
		isset($options['event_colour']) ? $options['event_colour'] : '#0b88f1',
		!empty($options['event_flag'] ) ? ' checked="checked"' : ''
		);
									?>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Tasks</th>
								<td>
								<?php
								echo sprintf('<input type="text" id="thq_connect_calendar_task" name="tidy_connect_calendar_settings[task_colour]" value="%s" style="background: %s; color: #fff;" class="thq-connect-cf" data-default-color="#66c1d2" /> <label><input type="checkbox" value="disabled" name="tidy_connect_calendar_settings[task_flag]" %s /> Disable?</label><p class="description" id="thq_connect_calendar_task_description">The colour which Tasks show in the calendar.</p>',
		isset($this->options['task_colour']) ? $this->options['task_colour'] : '#66c1d2',
		isset($this->options['task_colour']) ? $this->options['task_colour'] : '#66c1d2',
		!empty( $this->options['task_flag'] ) ? ' checked="checked"' : ''
		);
		?>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Meetings</th>
								<td>
								<?php
								echo sprintf('<input type="text" id="thq_connect_calendar_meeting" name="tidy_connect_calendar_settings[meeting_colour]" value="%s" style="background: %s; color: #fff;" class="thq-connect-cf" data-default-color="#732e64" /> <label><input type="checkbox" value="disabled" name="tidy_connect_calendar_settings[meeting_flag]" %s /> Disable?</label><p class="description" id="thq_connect_calendar_meeting_description">The colour which Meetings show in the calendar.</p>',
		isset($this->options['meeting_colour']) ? $this->options['meeting_colour'] : '#732e64',
		isset($this->options['meeting_colour']) ? $this->options['meeting_colour'] : '#732e64',
		!empty($this->options['meeting_flag'] ) ? ' checked="checked"' : ''
		);
		?>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Sessions</th>
								<td>
								<?php
								echo sprintf('<input type="text" id="thq_connect_calendar_session" name="tidy_connect_calendar_settings[session_colour]" value="%s" style="background: %s; color: #fff;" class="thq-connect-cf" data-default-color="#7266D2" /> <label><input type="checkbox" value="disabled" name="tidy_connect_calendar_settings[session_flag]" %s  /> Disable?</label><p class="description" id="thq_connect_calendar_session_description">The colour which Sessions show in the calendar.</p>',
		isset($this->options['session_colour']) ? $this->options['session_colour'] : '#7266D2',
		isset($this->options['session_colour']) ? $this->options['session_colour'] : '#7266D2',
		!empty($this->options['session_flag'] ) ? ' checked="checked"' : ''
		);
		?>
						</table>
						<?php submit_button(); ?>
						</form>
						<?php
						break;
						default:
							
							$update_tidy_contact = get_option('tidy_connect_update_tidy_contact') != 'true' ? "" : "checked='checked'";
							$update_wp_user = get_option('tidy_connect_update_wp_user') != 'true' ? "" : "checked='checked'";
							
					?>
							<h2>Required Options</h2>
				<p>
					The following details are required for the plugin to work. You can get the information by creating a TidyHQ application at <a href="https://dev.tidyhq.com/oauth_applications">https://dev.tidyhq.com/oauth_applications</a>.
				</p>
				<form method="post" action="options.php">
					<?php settings_fields( 'tidy_connect_settings' ); ?>
					<?php do_settings_sections( 'tidy_connect_settings' ); ?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">Client ID</th>
							<td><input type="text" name="tidy_connect_client_id" class="regular-text" value="<?php echo esc_attr( get_option('tidy_connect_client_id') ); ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row">Client Secret</th>
							<td><input type="password" name="tidy_connect_client_secret" class="regular-text" value="<?php echo esc_attr( get_option('tidy_connect_client_secret') ); ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row">Redirect URI</th>
							<td><input type="text" name="redirect_uri" class="regular-text" disabled read-only value="<?php echo site_url(); ?>" /><p class="description">This is your WordPress Site Address found in <a href="options-general.php">General Settings</a></p>
</td>
						</tr>
						<tr valign="top">
							<th scope="row">TidyHQ URL</th>
							<td>https://<input type="text" name="tidy_connect_domain_prefix"  value="<?php echo esc_attr( get_option('tidy_connect_domain_prefix') ); ?>" />.tidyhq.com <p class="description">Using a custom domain? Enter your "TidyHQ Domain" found in <code>Organisation Settings > Organisation Details</code> here.</p></td>
						</tr>
					</table>
					<h2>Optional</h2>
					<p>The following are optional features which you can use.</p>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">Data syncing</th>
							<td>
								<input type="checkbox" name="tidy_connect_update_tidy_contact" value="true" <?php echo $update_tidy_contact; ?>/> Update TidyHQ contact when WordPress user information changed.<br>
								<input type="checkbox" name="tidy_connect_update_wp_user" value="true" <?php echo $update_wp_user; ?>/> Keep WordPress user information updated from TidyHQ contact.
							</td>
						</tr>
					</table>
			<?php submit_button(); ?>
					</form>
				<?php
					}
			?>
				</div>
			<?php
		} //end function
		
		public function add_user_via_tidyhq_page() {
			$contacts = $this->get( 'contacts', array('access_token' => $this->access_token ) );
			?>
			<div class="wrap">
				<h1><?php echo get_admin_page_title(); ?></h1>
				<p>
					Find a TidyHQ contact and add them as a WordPress user. Simply select the user and select the role. Note: You would have needed to log in via TidyHQ for this function to work. The contact must also have a first name, last name and email address.
				</p>
				<?php settings_errors(); ?>
				<form method="post" action="admin-post.php">
					<input type="hidden" name="action" value="add_user_via_tidyhq">
					<table class="form-table">
						<tr valign="top">
							<th scope="row">TidyHQ Contact</th>
							<td>
								<?php
									if( !empty( $contacts ) ) {
								?>
								<input list="tidy_contacts" name="tidy_contact" class="regular-text" required onchange="updateTidyId();" />
								<datalist id="tidy_contacts">
								<?php
										foreach( $contacts as $contact ) {
											$user_exists = get_user_by( 'login', "tidy_" . $contact['id'] );
											$user_exists = !$user_exists && !empty($contact['email_address']) ? get_user_by( 'email', $contact['email_address'] ) : $user_exists;
											if( !empty( $contact['email_address'] ) && !empty( $contact['first_name'] ) && !empty( $contact['last_name'] ) && !$user_exists ) {
												echo "<option value='" . $contact['id'] . "'>" . $contact['first_name'] . " " . $contact['last_name'] . "</option>";
											}
										}	
	 							?>
								</datalist>
								<?php
									} else {
										?>
								No contacts found.
										<?php
									}
								?>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">WordPress Role</th>
							<td><select name="wordpress_role" required>
   <?php wp_dropdown_roles( ); ?>
</select></td>
						</tr>
					</table>
			<?php submit_button( "Add User" ); ?>
					</form>
				</div>
			<?php
			?>
			<?php
		} //end function
		
		
		public function add_user_via_tidyhq_post() {
			
			if( !empty( $_POST['tidy_contact'] ) && !empty( $_POST['wordpress_role'] ) ) {
				
				$wp_user = get_user_by( 'login', "tidy_" . $_POST['tidy_contact'] );
				
				if( !empty( $wp_user ) ) {
					// User already exists
					wp_redirect( admin_url( "users.php?page=add-user-via-tidyhq&tidy_connect_user_notcreated=Contact_already_exists_in_WordPress." ) );
				} else {
					//Create the user
					$contact = $this->get( 'contacts/' . $_POST['tidy_contact'] );
					$new_user_id = wp_create_user(
						"tidy_" . $contact['id'],
						wp_generate_password(),
						$contact['email_address']
					);
					if( $new_user_id ) {
						wp_new_user_notification( $new_user_id, false, 'admin' ); //email admin that a new user was created (if enabled)

						$do_update_user = get_option( 'tidy_connect_update_wp_user' );
						if( $do_update_user ) {
							$args = array();
							$args['ID'] = $new_user_id;
							$args['first_name'] = $contact['first_name'];
							$args['last_name'] = $contact['last_name'];
							$args['user_email'] = $contact['email_address'];
							if( !empty( $contact['nick_name'] ) ) { $args['nickname'] = $contact['nick_name']; }
							else { $args['nickname'] = $args['first_name']; }
							$args['display_name'] = $args['nickname'];
							if( !empty( $contact['details'] ) ) { $args['description'] = $contact['details']; }
							if( !empty( $contact['website'] ) ) { $args['user_url'] = $contact['website']; }
							$update_user_id = wp_update_user( $args );
						} //end if

						wp_redirect( admin_url( 'users.php?tidy_connect_user_created=true' ) );
						exit;
					}
				}
			} else {
				wp_redirect( admin_url( "users.php?page=add-user-via-tidyhq&tidy_connect_user_notcreated=Contact_or_role_not_provided." ) );
			}
			
		} //end function
		
		
		/** LOGIN FUNCTIONS **/
		
		public function login_link_shortcode( $atts ) {
			
			$inner_html = !empty( $atts['label'] ) ? $atts['label'] : 'Sign in via TidyHQ';
			$inner_html = !empty( $atts['image'] ) && $atts['image'] == true ? "<img src='https://uploads-ssl.webflow.com/5b43b3614a052736729f16dd/5b61f3c1a5fc3add06d0fd95_TIDYHQ_Inverse.png' alt='Click here to log in via TidyHQ' width='100'>" : $inner_html;
				
				
			if( !is_user_logged_in() ) {
				return sprintf(
				"<a href='%s'>%s</a>",
				$this->login_url(),
				$inner_html
			);
				
			} else {
				return sprintf(
					"<a href='%s'>Log Out</a>",
					wp_logout_url()
				);
		}
			
		}
		
		/* Use login_url() to insert the login link */
		public function login_url() {
			
			return "https://accounts.tidyhq.com/oauth/authorize?client_id=" . $this->settings['tidy_connect_client_id'] . "&domain_prefix=" . $this->settings['tidy_connect_domain_prefix'] . "&redirect_uri=" . site_url() . "&response_type=code";
			
		} //end function
		
	/* Support link */
	public static function plugin_page_links( $links, $file ) {
		if ( strpos( $file, 'tidy_connect.php' ) !== false ) {
			$settings_link = array(	'settings' => '<a href="' . admin_url( 'admin.php?page=tidy-connect-options' ) . '">Settings</a>' );
			$support_link = array( 'support' => '<a href="mailto:tidyconnect@iinet.net.au" target="_blank">Support</a>' );
			
			$links = array_merge( $settings_link, $links );
			$links = array_merge( $links, $support_link );
		} //end if
		return $links;
	} //end function
	
	
		/* Profile page comment/link */
		public function profile_page_comment() {
			$wp_user_id = !empty( $_GET['user_id'] ) ? $_GET['user_id'] : get_current_user_id();
			//$this->update_wordpress_user( $wp_user_id );
			
			if( $wp_user_id ) {
				$wp_user = get_userdata( $wp_user_id );
				if( strpos( $wp_user->user_login, 'tidy_' ) !== false ) {
						// if we have a setting to use WP as priority data, then we will need to show something different.
						echo sprintf(
							"<div class='notice notice-info'><p>NOTE: %s is linked to TidyHQ. Some details will need to be changed <a href='%s'>here</a> otherwise they may get changed on next login.</p></div>",
							$wp_user_id != get_current_user_id() ? "This user" : "Your profile",
							$wp_user_id != get_current_user_id() ? "https://" . $this->settings['tidy_connect_domain_prefix'] . ".tidyhq.com/contacts/" . str_replace( "tidy_", "", $wp_user->user_login ) : "https://" . $this->settings['tidy_connect_domain_prefix'] . ".tidyhq.com/profile/my_details/edit"
						);

				} //end if
			} //end if
		} //end function
		
		
		/* Update TidyHQ contact with Wordpress user information */

		public function update_tidyhq_contact( $user_id, $old_user_data ) {
			
			$wp_user = get_userdata( $user_id );
			$tidy_contact_id = str_replace( "tidy_", "", $wp_user->user_login );
			$args = array(
				'first_name'	=> $wp_user->first_name,
				'last_name'		=> $wp_user->last_name,
				'email_address'	=> $wp_user->user_email,
				'nickname'		=> $wp_user->nickname,
				'website'		=> $wp_user->user_url,
				'details'		=> $wp_user->description
			);
			
			return $this->put( 'contacts/' . $tidy_contact_id, $args );

		}
		
		/* Update WordPress user with TidyHQ details */
		public function update_wordpress_user( $user_id ) {
			
			$wp_user = get_user_by( 'ID', $user_id );
			
			$tidy_contact_id = str_replace( "tidy_", "", $wp_user->user_login );
			
			$contact = $this->get( 'contacts/' . $tidy_contact_id );
			
			$args = array();
			
			if( !empty( $contact ) && !empty( $wp_user ) ) {
						
				$args['ID'] = $user_id;
				$args['first_name'] = $contact['first_name'];
				$args['last_name'] = $contact['last_name'];
				$args['user_email'] = $contact['email_address'];

				if( !empty( $contact['nick_name'] ) ) { $args['nickname'] = $contact['nick_name']; }
				else { $args['nickname'] = $args['first_name']; }
				$args['display_name'] = $args['nickname'];
				if( !empty( $contact['details'] ) ) { $args['description'] = $contact['details']; }
				if( !empty( $contact['website'] ) ) { $args['user_url'] = $contact['website']; }
			}
			
			$updated = !empty( $args ) ? wp_update_user( $args ) : false;
			
			if( $updated ) {
				return true;
			} else {
				return false;
			}
		}
		
	} //end class
	
	$tidy_connect = new TidyConnect();
	
} //end if

?>
