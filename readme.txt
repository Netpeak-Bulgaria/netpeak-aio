=== Netpeak Analytics Kit ===
Contributors: netpeakbulgaria, masiknetpeak, finik2024
Tags: analytics, google analytics, google tag manager, search console, meta pixel
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: trunk
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

This plugin does not send analytics data, OAuth tokens or site data to Netpeak. The only exception is the optional deactivation feedback described under "Third-party services".

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

= Deactivation feedback (optional) =

When you deactivate the plugin, a dialog asks why. Nothing is sent unless you choose a reason and press "Submit & Deactivate". Then the plugin sends one email, with the WordPress mail function, to Netpeak (info@netpeak.bg). It contains the reason you chose, the text you typed, the plugin version, the WordPress version, the PHP version and the site language. It does not contain the site address, your name or your email. "Skip & Deactivate" sends nothing.

The dialog also has a checkbox that decides whether the plugin data is deleted when the plugin is deleted. It is off by default.

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

No analytics data, OAuth tokens or site data. The only message that can reach Netpeak is the optional deactivation feedback, sent by email only after you press "Submit & Deactivate".

= What happens to my data when I delete the plugin? =

By default everything is kept, so a reinstall finds your settings and the Google connection. To remove it all, turn on "Delete all plugin data when the plugin is deleted" in Settings, Data, or tick the same checkbox in the deactivation dialog.

= Why are the dashboard numbers not live? =

Reports from Google are cached for 30 minutes to keep the dashboard fast and to save API quota. Press Refresh on the dashboard to reload them. The cache is also cleared when you save the settings or connect or disconnect Google.

= Does the plugin use third-party services? =

Yes, when you enable integrations. Depending on your settings, the plugin can communicate directly with Google Analytics, Google Tag Manager, Google Search Console, Google Analytics Data API, and Meta Pixel.

= Are Google OAuth tokens stored? =

Yes. OAuth tokens are stored encrypted in the WordPress database so the admin dashboard can fetch Google Search Console and GA4 metrics.

= Is Meta Conversions API included? =

Not in version 0.1.0. Meta Conversions API support is planned for a future release.

== Developers ==

Filters for the front-end events. Channels are `ga4`, `gtm` and `meta`. Event names are the names of the channel: for `ga4` and `gtm` `view_item`, `add_to_cart`, `purchase` and so on, for `meta` `PageView`, `ViewContent`, `AddToCart`, `InitiateCheckout`, `Search`, `Purchase`.

* `netpeak_aio_event_enabled` ( bool $enabled, string $event, string $channel, array $payload ): return false to not send the event.
* `netpeak_aio_event_payload` ( array $payload, string $event, string $channel ): change the event data. For `gtm` the payload is the `ecommerce` object.
* `netpeak_aio_item_id` ( string $id, WC_Product $product, string $channel ): change the product ID. GA4 and GTM use the SKU (or the product ID when there is no SKU), Meta uses the WooCommerce product or variation ID.
* `netpeak_aio_item` ( array $item, WC_Product $product, string $channel ): change a whole GA4 or GTM item.
* `netpeak_aio_body_html` ( string $html, object $integration ): change the markup printed after the body tag.
* `netpeak_aio_cache_ttl` ( int $ttl, string $key ): cache time of the dashboard reports in seconds, 0 turns the cache off.
* `netpeak_aio_feedback_recipient` ( string $email ): receiver of the optional deactivation feedback.

Example: do not send `purchase` to Meta and use the SKU as the Meta product ID.

`add_filter( 'netpeak_aio_event_enabled', fn( $on, $event, $channel ) => ( 'meta' === $channel && 'Purchase' === $event ) ? false : $on, 10, 3 );`

`add_filter( 'netpeak_aio_item_id', fn( $id, $product, $channel ) => 'meta' === $channel && $product->get_sku() ? $product->get_sku() : $id, 10, 3 );`

Limits: the browser side `add_to_cart` listener for AJAX buttons gets the item from the plugin REST route, so `netpeak_aio_item_id` and `netpeak_aio_item` apply to it, but `netpeak_aio_event_payload` does not. `netpeak_aio_event_enabled` is checked once for it, without a payload.

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
