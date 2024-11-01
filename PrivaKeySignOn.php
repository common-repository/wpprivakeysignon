<?php

/**
 *
 *	Copyright Privakey, Inc. 2017
 *	
 *	This file is part of WPPrivaKeySignOn.
 *	WPPrivaKeySignOn is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	WPPrivaKeySignOn is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
*/

/*
	Plugin Name: Privakey Signon
	Description: This plugin allows users to securely sign into their WordPress accounts, without passwords, using the multi-factor authentication service Privakey.
	Version: 1.0.8
	Author: Privakey
	Author URI: http://www.privakey.com/
	License: GPL3
	License URI: https://www.gnu.org/licenses/gpl-3.0-standalone.html
*/


if (!session_id())
	session_start();



include_once( plugin_dir_path( __FILE__ ) . 'OpenID-Connect-PHP/OpenIDConnectClient.php5');
include_once( plugin_dir_path( __FILE__ ) . 'PrivaKeyConfig.php');

//Hooks
add_action('init', 'privaKeyInit');
add_action( 'wp_enqueue_scripts', 'privaKeyEnqueueScripts');
add_action( 'admin_enqueue_scripts', 'privaKeyEnqueueScripts');
add_action( 'login_enqueue_scripts', 'privaKeyEnqueueScripts');

add_action( 'login_form', 'privaKeyAddLoginButton');

//register_activation_hook(__FILE__, 'privaKeyActivate');
//register_deactivation_hook(__FILE__, 'privaKeyDeactivate');

add_filter( 'retrieve_password_message', 'privaKeyChangePasswordResetEmail', 10, 2);
add_action( 'password_reset', 'privaKeyDisable', 10, 1);

add_filter ('wp_authenticate_user' , 'privaKeyCheckPrivaKeyOnly');

add_filter('shake_error_codes', 'privaKeyAddShakeErrorCodes');
add_filter ('login_errors', 'privaKeyFilterLoginErrors');
add_filter ('wp_login_errors', 'privaKeyFilterWPLoginErrors');

add_action( 'show_user_profile', 'privaKeyUserSettings', 10, 1);
add_action( 'edit_user_profile', 'privaKeyUserSettings', 10, 1);

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'privaKeyPluginActionLinks' );
add_action('admin_menu', 'privaKeyAddSettingsPage');

//Function Declarations
function privaKeyInit() {
	privaKeyWriteConfigs();


	$base_page_url = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
	if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
		$base_page_url .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"];
	} else {
		$base_page_url .= $_SERVER["SERVER_NAME"];
	}

	$tmp = explode("?", $_SERVER['REQUEST_URI']);
	$base_page_url .= $tmp[0];

	if ($base_page_url == site_url('/') && (isset($_GET['privakey_login']) || (isset($_GET['state']) && strpos($_GET['state'], 'privakeylogin-') === 0))) {
		privaKeyPopout();
	}
}

function privaKeyEnqueueScripts() {
	wp_register_script( 'PrivaKeyAlerts', plugin_dir_url(__FILE__) . 'PrivaKeyAlerts.js', array( 'jquery', 'thickbox' ));
	wp_enqueue_script( 'PrivaKeyAlerts' );
	wp_register_style( 'PrivaKeyStyles', plugin_dir_url(__FILE__) . 'PrivaKeyStyles.css');
	wp_enqueue_style( 'PrivaKeyStyles' );
}

function privaKeyAddLoginButton(){
	?>
		<p class="forgetmenot">
			<label for="rememberme">
				<input name="rememberme" type="checkbox" id="rememberme" value="forever" />Remember Me
			</label>
		</p>
		<p class="submit">
			<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Log In" />
			<?php if ( isset($interim_login) && $interim_login) { ?>
			<input type="hidden" name="interim-login" value="1" />
			<?php } else { ?>
			<input type="hidden" name="redirect_to" value="<?php esc_attr_e(admin_url()); ?>" />
			<?php  }
			if ( isset($customize_login) && $customize_login) { ?>
			<input type="hidden" name="customize-login" value="1" />
			<?php }	?>
			<input type="hidden" name="testcookie" value="1" />
		</p>
		<br/>
		<br/>
		<div class="privakey-group" style="font-size:16">
			<div class="privakey-item privakey-line"></div>
			<div class="privakey-item privakey-text">
				<pre>  Or  </pre>
			</div>
			<div class="privakey-item privakey-line"></div>
		</div>
		<br/>
		<input type="button" name="button" id="button" style="width:100%; height:40px;" class="button button-privakey"
			value="Log in with Privakey"
			onclick="window.open('<?php _e(site_url()); ?>?privakey_login=true', '', 'width=800, height=600');" />
		</form>
		<form  name="wrongform" id="notloginform" style="display:none;" disabled="true">
	<?php
}

function privaKeyActivate() {

}

function privaKeyDeactivate() {

}

function privaKeyChangePasswordResetEmail ($message, $key ){
	return ($message . "\n(NOTE: If your account is using Privakey authentication, resetting your password will disable it.)");
}

function privaKeyDisable( $user ){
	delete_user_meta($user->ID, 'privakey_guid');
}

function privaKeyCheckPrivaKeyOnly ($user) {
	$error_string = '<strong>ERROR</strong>: Invalid login, the username or password may be incorrect or your account is set to allow Privakey authentication only.';
	$guid = get_user_meta($user->get('ID'), 'privakey_guid', true);
	if ($guid != null) {
		return new WP_Error('invalidcombo', __($error_string));
	} else {
		return $user;
	}
}

function privaKeyAddShakeErrorCodes($shake_error_codes) {
	array_push ( $shake_error_codes, 'privakey_failed', 'privakey_invalid');
	return $shake_error_codes;
}

function privaKeyFilterLoginErrors ($error) {
	global $errors;

	$codes = $errors->get_error_codes();
	if ( in_array('invalid_username', $codes) || in_array('incorrect_password', $codes) || in_array('invalidcombo', $codes)) {
		$error_string = '<strong>ERROR</strong>: Invalid login, the username or password may be incorrect or your account is set to allow Privakey authentication only.';
		$error = $error_string;
	}

	return $error;
}

function privaKeyFilterWPLoginErrors ($error) {

	if (isset($_GET['privakey_failed'])){
		if ($_GET['privakey_failed'] == "true")
			$error-> add('privakey_failed', "<strong>Authentication failed</strong>, WordPress could not retrieve a valid response from Privakey's server.<br>Privakey may be temporarily unavailable or WordPress may be misconfigured");
		else
			$error-> add('privakey_failed', $_GET['privakey_failed']);
	}
	if (isset($_GET['privakey_invalid'])){
		$error-> add('privakey_invalid', "No user could be found with these credentials.\nPlease ensure your WordPress account is active and Privakey Login is enabled.");
	}

	return $error;
}

function privaKeyUserSettings($user) {
	?>

		<div id="privakey_popover" style="display:none;height:200px;">
			<div id="privakey_popovercontent" style="position:absolute;top: 50%;left: 50%;margin-right: -50%;transform: translate(-50%, -50%);">Hello world!</div>
			<div style="position:absolute;bottom:0px;right:0px;width:100%;height:50px">
				<div style="position:absolute;bottom:10px;right:15px;">
					<input type="button" id="privakey_popoverbutton" class="button button-privakey" onclick="tb_remove();" style="float:right;" value="OK">
						<input type="button" id="privakey_popoverbuttoncancel" class="button button-privakey" onclick="tb_remove(window.location.href.replace('&privakey_unbinding=confirm',''));" style="display:none;position:relative;right:10px;" value="Cancel">
				</div>
			</div>
		</div>

		<?php
	/*
	1. Is the profile currently bound to a privakey account?
	2. Is the user editing their own profile?
	3. Is the user an admin?

	Y1
	   Y2							N2
	Y3 Unbind with privakey login?	Unbind without privakey login
	N3 Unbind with privakey login	No button, only admins can edit others (can't reach page anyway)

	N1
	   Y2							N2
	Y3 Bind with privakey login		Gray button, you cannot bind other users
	N3 Bind with privakey login		No button, only admins can edit others (can't reach page anyway)

	*/

	if (isset($_GET['user_id']))
		$userID = $_GET['user_id'];
	else
		$userID = get_current_user_id(); //If the id can't be found in the url, assume we're editing ourselves

	$isOwner = IS_PROFILE_PAGE;
	$hasPrivaKey = (get_user_meta($userID, 'privakey_guid', true) != null);

	add_thickbox(); ?>
		<a id="privakey_modal" href="TB_inline/?inlineId=privakey_popover&height=150&width=650" class="thickbox"></a>

		<?php
	
	
	if (isset($_GET['privakey_unbinding']) && $_GET['privakey_unbinding'] == 'true') {
		if (isset($_SESSION['privakey_unbinding']) && $_SESSION['privakey_unbinding'] == 'confirmed') {
			delete_user_meta($userID, 'privakey_guid');
			$hasPrivaKey = false;
			?>
		<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible">
			<p>
				<strong>Account reverted.</strong>
			</p>
		</div>
		<?php 
		}
		unset($_SESSION['privakey_unbinding']);
	}


	if (isset($_GET['privakey_unbinding']) && $_GET['privakey_unbinding'] == 'confirm'
		&& !isset($_SESSION['privakey_unbinding'])
		&& !$isOwner && current_user_can('edit_users')) {
		$_SESSION['privakey_unbinding'] = 'confirmed';
		unset($_GET['privakey_unbinding']);
		?>
		<script type="text/javascript">
			privakey_confirmrevert();
		</script>
		<?php
	} else {
		unset($_SESSION['privakey_unbinding']);
		?>
		<script type="text/javascript">
			if (window.location.href.indexOf('&privakey_unbinding=confirm') != -1)
			window.location.href = window.location.href.replace('&privakey_unbinding=confirm', '');
		</script>
		<?php
	}

	unset($_GET['privakey_unbinding']);
	?>

		<table class="form-table">
			<tr>
				<th>
					<label>Privakey</label>
				</th>
				<td>
					<!-- no privakey -->
					<input type="button" name="button" id="bindbutton" style="<?php echo ($hasPrivaKey ? 'display:none': '') ?>" class="button button-privakey"
					<?php if (!$isOwner) echo ('disabled="true"'); ?>
					value="Enable Privakey Login"
					onclick = "<?php
						if ($isOwner) { ?>
							window.open('<?php echo site_url()?>?privakey_login=true&binding=true', '', 'width=800, height=600');"
						<?php } ?>"
					/>
					<!-- has privakey -->
					<input type="button" name="button" id="unbindbutton" style="<?php echo ($hasPrivaKey ? '' : 'display:none'); ?>" class="button button-privakey" value="Revert to Password Login"
					onclick = "<?php
						if ($isOwner) { //Unbinding self requires another authentication
							?>window.open('<?php echo site_url()?>?privakey_login=true&unbinding=true' , '', 'width=800, height=600');<?php
						} else { //Unbinding someone else as an admin (non-admins can't get to this page)
							?>window.location.href = window.location.href + '&privakey_unbinding=confirm';<?php
						}?>"
					/>
					<br>
						<span class="description">
							<?php
					if ($isOwner) {
						if ($hasPrivaKey) { ?>
							Requires an additional Privakey authentication
							<?php } else { ?>
							This will disable the use of password authentication. Requires an existing Privakey account.
							<?php }
					} else if (!$hasPrivaKey) { ?>
							You cannot enable Privakey for another user.
							<?php } ?>
						</span>
					</td>
			</tr>
		</table>
		<?php
}

function privaKeyPluginActionLinks( $links ) {
	$settings_link = "<a href='options-general.php?page=" . plugin_basename(__FILE__) . "'>" . __('Settings') . "</a>";
	array_unshift( $links, $settings_link );

	return $links;
}

function privaKeyAddSettingsPage() {
	if (function_exists('add_submenu_page')) {
		add_options_page(__('Settings'), __('Privakey'),'manage_options',__FILE__,'privaKeySettingsPage');
	}
}

function privaKeySettingsPage() {
	$changing = false;
	if (isset($_REQUEST['privakey_clientguid'])){
		update_option('privakey_clientguid', ($_POST['privakey_clientguid']));
		$changing = true;
	}
	if (isset($_REQUEST['privakey_clientsecret'])){
		update_option('privakey_clientsecret', ($_POST['privakey_clientsecret']));
		$changing = true;
	}

	if ($changing) {
		?>
		<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible">
			<p>
				<strong>Changes saved.</strong>
			</p>
		</div>
		<?php
	} ?>

		<form method="post" action="">
			<h3>Privakey Settings</h3>
			<p>The client ID and secret are used to identify that login requests are coming from your site. Copy them from your Privakey Administration settings and paste them here.</p>
			<table class="optiontable form-table">
				<tr valign="top">
					<th scope="row">
						<label for="clientguid">
							<?php _e('Client ID: '); ?>
						</label>
					</th>
					<td>
						<input name="privakey_clientguid" type="text" id="privakey_clientguid" value="<?php esc_html_e(get_option('privakey_clientguid')); ?>" size="40" class="regular-text" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="privakey_clientsecret">
							<?php _e('Client Secret: '); ?>
						</label>
					</th>
					<td>
						<input name="privakey_clientsecret" type="text" id="privakey_clientsecret" value="<?php esc_html_e(get_option('privakey_clientsecret')); ?>" size="40" class="regular-text" />
					</td>
				</tr>
			</table>
			<br/>
			<p>The URL to return to after a successful authentication. Copy this to your Privakey Administration settings.</p>
			<table class="optiontable form-table">
				<tr>
					<th scope="row">
						<label for="privakey_redirecturl">
							<?php _e('Call Back URL: '); ?>
						</label>
					</th>
					<td>
						<input name="privakey_loginurl" type="text" id="privakey_loginurl" style="width:600px;" disabled="true" value="<?php _e(site_url());?>" size="40" class="regular-text" />
					</td>
				</tr>
			</table>
			<br/>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e('Save Changes'); ?>" />
			</form>

		<?php
}


function privaKeyReturnToParent($message, $goToAdmin, $urlParams = "") {
	?><!DOCTYPE html>
	<html>
		<script type="text/javascript">

		function privakeyload() {
			if (window.opener != null) {
				var content = window.opener.document.getElementById( "privakey_popovercontent" );
				var trigger = window.opener.document.getElementById( "privakey_modal" );
			}

			//If the parent has a modal to fill, send the message there
			if (content != null && trigger != null) {
				content.innerHTML = <?php echo json_encode($message);?>;
				trigger.click();
			//Otherwise, redirect parent and indicate message by url parameter if needed
			} else {
				if (window.opener != null) {
					<?php
					if ($goToAdmin) {
						?>window.opener.location.href = '<?php _e(admin_url()) ?>';<?php
					} else {
						if ($urlParams != '') {
							?>window.opener.location.href = '<?php echo wp_login_url() . "?" . ($urlParams); ?>';<?php
						} else {
							?>window.opener.location.reload();<?php
						}
					}
					?>
				}
			}
			window.close();
		}
		</script>

		<body id="body" onLoad="privakeyload();" style="display:none;">
		</body>

	</html>
	<?php
}

function privaKeyPopout() {
	if (!session_id())
		session_start();

	global $PrivaKeyConfig;

	$clientID = get_option('privakey_clientguid');
	$clientsecret = get_option('privakey_clientsecret');
	$iss = $PrivaKeyConfig['idp_address'];

	if ( $iss == null
		|| $clientID == null
		|| $clientsecret == null ) {
		error_log("Privakey Sign On: Server address, clientID, or clientsecret not set.");
	}

	$oidc = new OpenIDConnectClient($iss, $clientID, $clientsecret);

	$currentID = get_current_user_id();
	$binding = false;
	$unbinding = false;


	if (isset($_GET['error'])) {
		$errorstring = ("Encountered an error from Privakey: " . $_GET['error']);
		if (isset($_GET['error_description'])) 
			$errorstring .= (esc_attr($_GET['error_description']));

		privaKeyReturnToParent($errorstring, false, "privakey_failed=" . $errorstring);
	} else {
		if ((isset($_GET['binding']) || isset($_SESSION['binding'])) && $currentID != 0) {
			$_SESSION['binding'] = 'true';
			$binding = true;
		}
			
		if ((isset($_GET['unbinding']) || isset($_SESSION['unbinding'])) && $currentID != 0) {
			$_SESSION['unbinding'] = 'true';
			$unbinding = true;
		}
		unset($_GET['binding']);
		unset($_GET['unbinding']);
				
				
		try {
			//Going to IDP
			if ( !isset($_GET['code']) || !isset($_GET['state']) ) {
				if (!isset($_GET['code']) && !isset($_GET['state'])) {
					// Privakey only supports openid scope
					$oidc->addScope('openid');
					$oidc->setRedirectUrl(site_url());
					$oidc->authenticate( $email );
				}
			//Coming from IDP
			} else {
				$oidc->setRedirectUrl(site_url());
				$oidc->authenticate($clientID);
				$guid = $oidc -> requestUserInfo('sub');

				global $wpdb;

				if ($guid == null) {
					throw new OpenIDConnectClientException('No user guid found');
				} else {
					$rows = $wpdb -> get_results(
						$wpdb->prepare("SELECT * FROM `" . $wpdb->prefix . "usermeta` WHERE
							meta_key = 'privakey_guid' and meta_value = '%s' ", $guid
						), ARRAY_A);

					//if binding or unbinding account
					if ($binding){
						//if the current guid isn't already in use, bind the user to it
						if (count($rows) == 0){
							update_user_meta($currentID, 'privakey_guid', $guid);
							error_log('Bound Privakey guid ' . $guid . ' to user: ' . $currentID);
							
							privaKeyReturnToParent('Your profile is now bound to this Privakey account.<br>'
								. 'Use these credentials instead of your username & password when logging in.<br><br>'
								. 'This may be reverted from your profile settings.', false);
						//if it is, throw an error
						} else {
							privaKeyReturnToParent('Privakey credentials already in use.', false);
							error_log('Privakey credentials already in use: ' . $guid);
						}
					} else if ($unbinding) {
						//if the current guid is in use by the current user, unbind them
						if (get_user_meta($currentID, 'privakey_guid', true) == $guid){
							delete_user_meta($currentID, 'privakey_guid');
							privaKeyReturnToParent('Your profile is no longer connected to your Privakey account.<br>'
								. 'You may re-connect it at any time from your profile settings.', false);
							error_log('Unbound Privakey guid ' . $guid . ' from user: ' . $currentID);
						//otherwise, throw an error
						} else {
							privaKeyReturnToParent("These Privakey credentials don't match your account.", false);
							error_log('Invalid Privakey credentials: ' . $guid);
						}
					} else { //neither binding nor unbinding, normal login
						if (count($rows) == 0) {
							privaKeyReturnToParent("No user could be found with these credentials.\nPlease ensure your WordPress account is active and Privakey Login is enabled.", false, 'privakey_invalid=true');
							error_log('No user with guid ' . $guid . ' found');
						} else {
							$userID = $rows[0]['user_id'];
							$data = wp_set_auth_cookie($userID, true, is_ssl());
							privaKeyReturnToParent("", true);
						}
					}
				}
				unset($_SESSION['binding']);
				unset($_SESSION['unbinding']);
			}
		} catch ( Exception $e ) {
			error_log( $e->__toString() . PHP_EOL );
			privaKeyReturnToParent("<strong>Authentication failed</strong>, WordPress could not retrieve a valid response from Privakey's server.<br>Privakey may be temporarily unavailable or WordPress may be misconfigured", false, 'privakey_failed=true');
			session_destroy();
			unset( $_SESSION );
		}
	}
}


