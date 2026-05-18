=== aGo Harden ===
Contributors: sixtovaldese
Donate link: https://paypal.me/sixtovaldes
Tags: security, hardening, login, brute-force, headers
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Security hardening dashboard with toggles: custom login URL, brute-force protection, security headers, file editor lockdown and real-time score.

== Description ==

aGo Harden gives you a single dashboard with reversible toggles to harden a WordPress site. Each switch ships with a 1-line summary plus an expandable explanation (how it works, why, caveats). Safe defaults seed on first activation.

**Features**

* Custom login URL to reduce login-page bot traffic.
* Brute-force login protection with cooldown after failed attempts.
* Security HTTP headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy).
* Disable WordPress file editor and plugin editor.
* Disable PHP execution inside `/wp-content/uploads/`.
* Disable directory listing.
* Block author enumeration probes (`?author=N`).
* XML-RPC fully disabled or pingback-only.
* Real-time security score on the dashboard.
* No external services, no remote calls.
* English, Spanish (es_ES) and Brazilian Portuguese (pt_BR) bundled.

== Installation ==

1. Upload the `ago-harden` folder to `/wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin.
3. Go to **aGo Tools, then Harden**. Toggle switches as needed. Each toggle saves on change.

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

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
