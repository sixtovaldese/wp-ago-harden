=== aGo Harden ===
Contributors: agolab
Donate link: https://paypal.me/sixtovaldes
Tags: security, hardening, login, brute-force, headers
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Security hardening dashboard with toggles: custom login URL, brute-force protection, security headers, file editor lockdown and real-time score.

== Description ==

aGo Harden gives you a single dashboard with reversible toggles to harden a WordPress site. Each switch ships with a short summary of what it does. Every switch starts off, so nothing changes until you turn it on.

**Features**

* Custom login URL to reduce login-page bot traffic.
* Brute-force login protection with cooldown after failed attempts.
* Security HTTP headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy).
* Disable WordPress file editor and plugin editor.
* Disable PHP execution inside `/wp-content/uploads/`.
* Disable directory listing.
* Block author enumeration probes (`?author=N`).
* Disable the XML-RPC endpoint.
* Hide the WordPress version meta tag and strip `?ver=` from scripts and styles.
* Hide detailed login errors behind a generic message.
* Force logout after a configurable number of hours.
* Real-time security score on the dashboard.
* No external services, no remote calls.
* English, Spanish (es_ES) and Brazilian Portuguese (pt_BR) bundled.

== Installation ==

1. Upload the `ago-harden` folder to `/wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin.
3. Go to **aGo Tools, then Harden**. Toggle switches as needed, then click **Save Settings**.

== Frequently Asked Questions ==

= Will it break my site? =

Each toggle is reversible and includes a caveat in the dashboard. The custom login URL is the only switch that can lock you out if you forget the URL: write it down before saving.

= Does it scan for malware? =

No. aGo Harden is preventive: it removes attack surface and slows automated probes. Use a dedicated scanner (WordFence, Sucuri SiteCheck) for active malware detection.

= Does it write to .htaccess? =

Yes, the PHP-in-uploads block and directory-listing toggles write a small rules block to `.htaccess` files. The block is removed when the toggle is disabled or the plugin is uninstalled.

== Screenshots ==

1. Security dashboard with real-time score.
2. Toggle list grouped by category.
3. Custom login URL settings.

== External services ==

This plugin does not connect to any external services. It runs entirely on your own server. No data is sent anywhere, and no remote APIs are contacted.

The sidebar links to external sites (PayPal donation pages and the aGo Lab website) that only open when you click them. No data is transmitted automatically.

== Privacy ==

aGo Harden stores a single option (`agoharden_settings`) holding your toggle preferences. The brute-force protection feature stores short-lived transients keyed by a salted hash of the visitor IP address (no plain IP is stored). Some toggles write a small rules block to `.htaccess` files in the site root and uploads directory.

On uninstall, the plugin deletes its option, removes its `.htaccess` rules blocks, and lets the transients expire. No personal data is collected, profiled, or shared.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
