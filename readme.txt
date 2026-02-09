=== Lika Redirect Manager ===
Contributors: Laye
Tags: redirect, redirection, 301, 302, 404, security
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later

Gestion de redirections via Réglages → Redirections (301/302/307/308). Suivi des 404 et durcissement sécurité.

== Installation ==
1. Uploadez `lika-redirect-manager` dans `wp-content/plugins/`.
2. Activez le plugin dans Extensions.
3. Réglages → Redirections.

== Changelog ==
= 1.3.0 =
* Add: Security hardening - hide plugin detection, Yoast comments, version strings.
* Add: Disable XML-RPC and user enumeration via REST API.

= 1.2.1 =
* Fix: auto-create tables if missing on init.

= 1.2.0 =
* Add: 404 tracking and admin UI with tabs for redirections and 404 errors.
