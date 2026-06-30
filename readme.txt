=== Netpeak Analytics Kit ===
Contributors: netpeakbulgaria, masiknetpeak, finik2024
Tags: analytics, google analytics, google tag manager, search console, meta pixel
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Google Analytics 4, Google Tag Manager, Google Search Console, and Meta Pixel from one WordPress admin screen.

== Description ==

Netpeak Analytics Kit helps site owners add and manage common marketing and analytics integrations without editing theme files.

Current features in version 0.1.0:

* Add Google Analytics 4 via Measurement ID.
* Add Google Tag Manager via Container ID.
* Route GA4 through GTM when you do not want direct gtag.js output.
* Connect a Google account with your own OAuth credentials.
* Display Google Search Console and GA4 metrics in the WordPress admin.
* Serve a Google Search Console HTML verification file.
* Add Meta Pixel browser tracking for standard page and WooCommerce events.

Meta Conversions API support is planned for a future release. It is not active in version 0.1.0.

This plugin does not send data to Netpeak servers.

== Third-party services ==

This plugin can connect your website to third-party services. These integrations are optional and only run when you configure and enable them.

= Google Analytics 4 =

When GA4 is enabled, the plugin loads Google's gtag.js script from `googletagmanager.com` and sends page measurement data to Google Analytics using the Measurement ID you enter.

Google Privacy Policy: https://policies.google.com/privacy

Google Terms of Service: https://policies.google.com/terms

= Google Tag Manager =

When GTM is enabled, the plugin loads Google Tag Manager scripts and a noscript iframe from `googletagmanager.com` using the Container ID you enter. Tags configured inside your GTM container may send additional data depending on your GTM setup.

Google Privacy Policy: https://policies.google.com/privacy

Google Terms of Service: https://policies.google.com/terms

= Google Search Console and Google Analytics Data APIs =

When you connect Google OAuth, the plugin sends requests directly from your WordPress site to Google APIs to fetch Search Console and GA4 reporting data for the properties you configure. The plugin stores Google OAuth access and refresh tokens encrypted in your WordPress database.

Google API Services User Data Policy: https://developers.google.com/terms/api-services-user-data-policy

Google APIs Terms of Service: https://developers.google.com/terms

= Meta Pixel =

When Meta Pixel is enabled, the plugin loads Meta's Pixel script from `connect.facebook.net` and can send browser events to Meta, including PageView and supported WooCommerce events. A noscript image request may be sent to `facebook.com` for basic PageView tracking when JavaScript is unavailable.

Meta Privacy Policy: https://www.facebook.com/privacy/policy/

Meta Business Tools Terms: https://www.facebook.com/legal/technology_terms

Site owners are responsible for obtaining any required consent before enabling tracking, and for ensuring their use of these services complies with applicable privacy laws.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/netpeak-analytics-kit/`.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open Netpeak AIO in the WordPress admin.
4. Configure the integrations you want to use.

== Frequently Asked Questions ==

= Does the plugin send data to Netpeak? =

No. The plugin does not send analytics data, OAuth tokens, or site data to Netpeak servers.

= Does the plugin use third-party services? =

Yes, when you enable integrations. Depending on your settings, the plugin can communicate directly with Google Analytics, Google Tag Manager, Google Search Console, Google Analytics Data API, and Meta Pixel.

= Are Google OAuth tokens stored? =

Yes. OAuth tokens are stored encrypted in the WordPress database so the admin dashboard can fetch Google Search Console and GA4 metrics.

= Is Meta Conversions API included? =

Not in version 0.1.0. Meta Conversions API support is planned for a future release.

== Screenshots ==

1. Netpeak AIO dashboard.
2. Integration settings.
3. Google OAuth connection screen.

== Changelog ==

= 0.1.0 =

* Initial release.
* Added Google Analytics 4, Google Tag Manager, Google Search Console, Google OAuth, admin reporting, and Meta Pixel browser tracking.

== Upgrade Notice ==

= 0.1.0 =

Initial release.

= 0.1.1 =

UI polish
