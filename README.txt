=== Privakey SignOn ===
Contributors: nhauslerprobaris
Tags: password free authentication, MFA, 2FA, eliminate passwords, login
Requires at least: 3.6.0
Tested up to: 4.4.1
Stable tag: 1.0.8
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0-standalone.html

This plugin allows users to securely sign into their WordPress accounts, without passwords, using the multi-factor authentication service Privakey.

== Description ==

Privakey (www.privakey.com) is a secure, multi-factor authentication service that acts as an alternative to usernames and passwords. A user creates a Privakey account with a personal device, such as their mobile phone, and a PIN. They can then use this account to log into any Privakey-enabled website.

This plugin enables users to connect their Privakey accounts to their WordPress accounts, so anyone who switches can sign in with Privakey instead of their username & password. 

== Installation ==

Note: In order for this plugin to work with your WordPress website and allow users to sign in, you must register as a Relying Party (RP) on Privakey's website. Details about this process can be found here:
http://docs.privakey.com/docs/register-to-become-a-privakey-relying-party

1. Search Privakey in the WordPress admin panel and click "Install Now".
2. Once the plugin has installed, click "Activate".
3. Register as a Relying Party on Privakey.com by logging into your account and clicking "Learn More" and filling out the form on the account screen.
4. Once you are registered, you can complete setup by clicking “+ new relying party” and entering your website's Name and Call Back URL (your website URL) on your Administration page of your Privakey account.
5. On the WordPress administration panel go to Plugins and click “Settings” underneath Privakey. Input your Client ID and Secret and save. These values can be found on your PrivaKey Administration page.
6. Select Users>Your Name> then click the “Enable Privakey Login” button to turn Privakey Two Factor Authentication on.

== Frequently Asked Questions ==

= I'm getting "could not retrieve a valid response" errors =
Privakey's servers may be temporarily unavailable, or the Relying Party settings are misconfigured. Try again later or check the settings. Make sure the client ID, client secret, and call back URI match those shown on  your Privakey administrator page.


= I've lost my Privakey account and can't log in with my password =

For security reasons, a user's password login is disabled when they enable Privakey. If you would like to revert to password login but have lost access to your Privakey account, either contact an administrator and have them revert your account manually, or follow the "Lost your password?" link to set a new password. Doing so automatically reverts your account to password login.


== Changelog ==

= 1.0.1 =
* Popout window now runs inside wordpress core

= 1.0.2 =
* Popout now checks for 'state=privakeylogin' instead of 'privakey_login=true' in querystring

= 1.0.3 =
* Plugin no longer generates "n characters of unexpected output" warning on activation

= 1.0.4 =
* colon in state replaced with hyphen, prevents page from loading twice on newer versions of WP

= 1.0.5 =
* IDP url updated to reflect Privakey's domain change
* nonce no longer sent in requesttoken calls
* unix-server-compatible file paths
* error JSONs can now have no error_description field

= 1.0.6 =
* HTML in popout generated after redirect header sent

= 1.0.7 =
* Minor error-handling improvements

= 1.0.8 =
* Updated IDP URL