=== aGo Harden ===
Contributors: agolab
Donate link: https://paypal.me/sixtovaldes
Tags: security, hardening, htaccess, login, security headers
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hardening switches that only report what is really in place, keep your cache busting intact and undo their own changes when turned off.

== Description ==

aGo Harden is one screen of switches that remove common attack surface from a WordPress site: a custom login URL, a lockout after failed logins, security headers, no file editor, no PHP in uploads, and more. Every switch starts off, so nothing changes until you turn it on.

Three things set it apart from other hardening plugins:

* **The score only counts what is in place.** Two protections are .htaccess rules, which only Apache and LiteSpeed read. On Nginx, or on a site without a root .htaccess, the switch is marked as having no effect on that server and adds nothing to the score, instead of showing a protection that does not exist.
* **The .htaccess rules are kept in line with the switches.** They are written with the WordPress function made for that file (`insert_with_markers`) and checked again on activation, on every save and every time the settings page opens. A rule lost to a hand edit, a migration or a host that resets the file comes back. Turning a switch off, deactivating or deleting the plugin removes the block and its markers, and deletes the file when the plugin created it, so the file is left as it was.
* **Hiding the version does not break your cache.** Most plugins delete the `?ver=` parameter from scripts and styles, and after an update browsers and CDNs keep serving the old file because the URL did not change. aGo Harden replaces the value with a short hash of the file's own date: it still changes when the file changes, and it no longer tells anyone which release is installed.

**Switches included**

* Custom login URL. `wp-login.php` stops answering and the login form moves to an address you choose.
* Limit login attempts: 3 failures in 15 minutes lock that IP out for 1 hour.
* Generic login error message, so a failed login does not reveal whether the username exists.
* Session length: log users out after a number of hours you set.
* Hide the WordPress version: removes the generator tag and masks `?ver=` as described above.
* Block author enumeration (`?author=N`) and the public REST users list for visitors who are not logged in.
* Security headers: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, X-XSS-Protection and Permissions-Policy.
* Disable XML-RPC, its discovery link and the X-Pingback header.
* Disable the theme and plugin file editor.
* Block PHP execution in the uploads folder (.htaccess).
* Disable directory listing (.htaccess).
* Security score from 0 to 100 that updates as you switch.

== Installation ==

1. Upload the `ago-harden` folder to `/wp-content/plugins/` or install it from the Plugins screen.
2. Activate the plugin.
3. Go to **aGo Tools, then Harden**. Turn on the switches you want and click **Save Settings**.

== Frequently Asked Questions ==

= Will it break my site? =

Each switch can be turned off again and undoes its own change. The custom login URL is the one to be careful with: write the new address down before saving. If you lose it, deactivate the plugin (rename its folder over FTP if needed) and `wp-login.php` works again.

= Why does a switch say it has no effect here? =

PHP blocking in uploads and the directory listing rule are .htaccess rules. Nginx and most other servers do not read .htaccess, so on them the switch cannot protect anything. The page says so next to the switch, and the score leaves it out. Your host can add the equivalent rule to the server configuration.

= Does it write to .htaccess? =

Only for those two switches, and only between `# BEGIN aGo Harden` and `# END aGo Harden` markers. The root .htaccess is edited only when it already exists. When the switch is turned off, the plugin is deactivated or it is deleted, the block is removed.

= Does it scan for malware? =

No. aGo Harden is preventive: it removes attack surface and slows automated probes. For malware detection use a dedicated scanner.

= Is there a Pro version? =

No. Every feature is in this plugin.

== Screenshots ==

1. The settings screen: security score and the login and authentication switches.
2. Information exposure, HTTP and file system switches, with the note shown when the server does not read .htaccess.

== External services ==

This plugin does not connect to any external service. It runs entirely on your own server, sends no data anywhere and contacts no remote API.

The settings screen has links to external sites (WPScan, securityheaders.com, Mozilla Observatory, PayPal donation pages and the aGo Lab website). They only open when you click them, and nothing is sent automatically.

== Privacy ==

aGo Harden stores one option (`agoharden_settings`) with your switch settings. The login attempt limit stores short-lived transients keyed by a salted hash of the visitor IP address; the IP itself is not stored, and the transients expire after at most one hour. The PHP-in-uploads and directory listing switches write a rules block to the .htaccess files of the uploads folder and the site root.

On uninstall, the plugin deletes its option and removes its .htaccess blocks. No personal data is collected, profiled or shared.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
