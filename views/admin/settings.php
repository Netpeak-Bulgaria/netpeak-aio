<?php
/**
 * @var array<string,mixed> $data
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$callback_url = rest_url('netpeak-aio/v1/oauth/callback');
?>
<div x-data="settingsForm" x-init="init()">
    <template x-if="settings.error">
        <div class="netpeak-analytics-kit__error" x-text="settings.error"></div>
    </template>

    <template x-if="settings.loading && !settings.data">
        <p class="netpeak-analytics-kit__loading"><?php esc_html_e('Loading settings…', 'netpeak-analytics-kit'); ?></p>
    </template>

    <template x-if="settings.data">
        <div class="netpeak-analytics-kit__layout">

            <aside class="netpeak-analytics-kit__sidebar">
                <template x-for="group in groups" :key="group.label">
                    <div class="netpeak-analytics-kit__nav-group">
                        <p class="netpeak-analytics-kit__nav-group-title" x-text="group.label"></p>
                        <ul class="netpeak-analytics-kit__nav-list">
                            <template x-for="tab in group.tabs" :key="tab.key">
                                <li>
                                    <button
                                        type="button"
                                        class="netpeak-analytics-kit__nav-item"
                                        :class="activeTab === tab.key ? 'netpeak-analytics-kit__nav-item--active' : ''"
                                        :aria-current="activeTab === tab.key ? 'page' : false"
                                        @click="activeTab = tab.key"
                                    >
                                        <span class="netpeak-analytics-kit__nav-label" x-text="tab.label"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </aside>

            <div class="netpeak-analytics-kit__panel">

                <section x-show="activeTab === 'ga4'">
                    <h2 class="netpeak-analytics-kit__section-title">Google Analytics 4</h2>
                    <p class="netpeak-analytics-kit__lede">
                        <?php esc_html_e('Loads gtag.js and emits GA4 Enhanced Ecommerce events directly. Requires WooCommerce for ecommerce events.', 'netpeak-analytics-kit'); ?>
                    </p>

                    <div class="netpeak-analytics-kit__checkbox-row">
                        <input type="checkbox" id="ga4-enabled" x-model="settings.data.ga4.enabled">
                        <label for="ga4-enabled"><?php esc_html_e('Enable Google Analytics 4', 'netpeak-analytics-kit'); ?></label>
                    </div>

                    <div class="netpeak-analytics-kit__row">
                        <label class="netpeak-analytics-kit__label" for="ga4-mid"><?php esc_html_e('Measurement ID', 'netpeak-analytics-kit'); ?></label>
                        <input id="ga4-mid" type="text" class="netpeak-analytics-kit__input regular-text"
                            x-model="settings.data.ga4.measurement_id"
                            placeholder="G-XXXXXXXXXX">
                    </div>

                    <div class="netpeak-analytics-kit__row">
                        <label class="netpeak-analytics-kit__label" for="ga4-pid"><?php esc_html_e('Property ID', 'netpeak-analytics-kit'); ?></label>
                        <input id="ga4-pid" type="text" class="netpeak-analytics-kit__input regular-text"
                            x-model="settings.data.ga4.property_id"
                            placeholder="347293851">
                    </div>

                    <p class="netpeak-analytics-kit__hint">
                        <?php esc_html_e('Numeric ID from GA4 → Admin → Property Settings. Different from Measurement ID — used only for API calls.', 'netpeak-analytics-kit'); ?>
                    </p>

                    <div class="netpeak-analytics-kit__checkbox-row">
                        <input type="checkbox" id="ga4-via-gtm" x-model="settings.data.ga4.route_via_gtm">
                        <label for="ga4-via-gtm"><?php esc_html_e('Route GA4 through Tag Manager (skip direct gtag.js output)', 'netpeak-analytics-kit'); ?></label>
                    </div>
                    <p class="netpeak-analytics-kit__hint">
                        <?php esc_html_e('Enable this when GA4 is already configured as a tag inside your GTM container. Prevents duplicate events.', 'netpeak-analytics-kit'); ?>
                    </p>

                    <div class="netpeak-analytics-kit__docs">
                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">1</span>
                                <?php esc_html_e('What fires automatically (Enhanced Ecommerce)', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p><?php esc_html_e('When WooCommerce is active and GA4 is not routed through GTM, the plugin emits these gtag events:', 'netpeak-analytics-kit'); ?></p>
                            <ul class="netpeak-analytics-kit__api-list">
                                <li><code>page_view</code> — <?php esc_html_e('every page (built-in gtag config)', 'netpeak-analytics-kit'); ?></li>
                                <li><code>view_item_list</code> — <?php esc_html_e('shop, category, tag archives', 'netpeak-analytics-kit'); ?></li>
                                <li><code>view_item</code> — <?php esc_html_e('single product pages', 'netpeak-analytics-kit'); ?></li>
                                <li><code>add_to_cart</code> — <?php esc_html_e('on add to cart (incl. AJAX)', 'netpeak-analytics-kit'); ?></li>
                                <li><code>remove_from_cart</code> — <?php esc_html_e('on cart item removal', 'netpeak-analytics-kit'); ?></li>
                                <li><code>view_cart</code> — <?php esc_html_e('cart page', 'netpeak-analytics-kit'); ?></li>
                                <li><code>begin_checkout</code> — <?php esc_html_e('checkout page', 'netpeak-analytics-kit'); ?></li>
                                <li><code>purchase</code> — <?php esc_html_e('thank-you page, deduplicated per order', 'netpeak-analytics-kit'); ?></li>
                            </ul>
                            <p class="netpeak-analytics-kit__hint">
                                <?php esc_html_e('Each event carries currency, value and items[] with product id, name, price, quantity, category and brand (when available).', 'netpeak-analytics-kit'); ?>
                            </p>
                        </div>

                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">2</span>
                                <?php esc_html_e('Verify in GA4', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    /* translators: %s: link to GA4 DebugView */
                                    esc_html__('Open %s to see events arrive in real time. Make sure your browser is not blocking google-analytics.com.', 'netpeak-analytics-kit'),
                                    '<a href="https://analytics.google.com/" target="_blank" rel="noopener">' . esc_html__('GA4 → Admin → DebugView', 'netpeak-analytics-kit') . '</a>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </section>

                <section x-show="activeTab === 'gtm'">
                    <h2 class="netpeak-analytics-kit__section-title">Google Tag Manager</h2>
                    <p class="netpeak-analytics-kit__lede">
                        <?php esc_html_e('Loads GTM container and pushes GA4 Enhanced Ecommerce events to window.dataLayer. Requires WooCommerce for ecommerce events.', 'netpeak-analytics-kit'); ?>
                    </p>

                    <div class="netpeak-analytics-kit__checkbox-row">
                        <input type="checkbox" id="gtm-enabled" x-model="settings.data.gtm.enabled">
                        <label for="gtm-enabled"><?php esc_html_e('Enable Google Tag Manager', 'netpeak-analytics-kit'); ?></label>
                    </div>
                    <div class="netpeak-analytics-kit__row">
                        <label class="netpeak-analytics-kit__label" for="gtm-id"><?php esc_html_e('Container ID', 'netpeak-analytics-kit'); ?></label>
                        <input id="gtm-id" type="text" class="netpeak-analytics-kit__input regular-text"
                               x-model="settings.data.gtm.container_id"
                               placeholder="GTM-XXXXXXX">
                    </div>

                    <div class="netpeak-analytics-kit__docs">
                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">1</span>
                                <?php esc_html_e('What gets pushed to dataLayer', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p><?php esc_html_e('When WooCommerce is active, the plugin pushes these GA4 Enhanced Ecommerce events to window.dataLayer:', 'netpeak-analytics-kit'); ?></p>
                            <ul class="netpeak-analytics-kit__api-list">
                                <li><code>view_item_list</code> — <?php esc_html_e('shop, category, tag archives', 'netpeak-analytics-kit'); ?></li>
                                <li><code>view_item</code> — <?php esc_html_e('single product pages', 'netpeak-analytics-kit'); ?></li>
                                <li><code>add_to_cart</code> — <?php esc_html_e('on add to cart (incl. AJAX)', 'netpeak-analytics-kit'); ?></li>
                                <li><code>remove_from_cart</code> — <?php esc_html_e('on cart item removal', 'netpeak-analytics-kit'); ?></li>
                                <li><code>view_cart</code> — <?php esc_html_e('cart page', 'netpeak-analytics-kit'); ?></li>
                                <li><code>begin_checkout</code> — <?php esc_html_e('checkout page', 'netpeak-analytics-kit'); ?></li>
                                <li><code>purchase</code> — <?php esc_html_e('thank-you page, deduplicated per order', 'netpeak-analytics-kit'); ?></li>
                            </ul>
                            <p class="netpeak-analytics-kit__hint">
                                <?php esc_html_e('Each push clears window.dataLayer.ecommerce before pushing new event data (GA4 best practice).', 'netpeak-analytics-kit'); ?>
                            </p>
                        </div>

                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">2</span>
                                <?php esc_html_e('Configure tags in GTM', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p>
                                <?php esc_html_e('The plugin only emits dataLayer events — GTM still needs to be configured to forward them to GA4, Meta, Google Ads, etc. Create one trigger per event name and route to your destination tags.', 'netpeak-analytics-kit'); ?>
                            </p>
                            <p class="netpeak-analytics-kit__hint">
                                <?php
                                printf(
                                    /* translators: %s: link to GTM Preview Mode docs */
                                    esc_html__('Use %s to verify events fire correctly during setup.', 'netpeak-analytics-kit'),
                                    '<a href="https://support.google.com/tagmanager/answer/6107056" target="_blank" rel="noopener">' . esc_html__('GTM Preview Mode', 'netpeak-analytics-kit') . '</a>'
                                );
                                ?>
                            </p>
                        </div>

                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">3</span>
                                <?php esc_html_e('Avoid duplicate events', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    /* translators: %s: "Route GA4 through Tag Manager" emphasised */
                                    esc_html__('If you also enable GA4 above and configure a GA4 tag inside GTM, turn on %s to prevent duplicate events.', 'netpeak-analytics-kit'),
                                    '<strong>' . esc_html__('Route GA4 through Tag Manager', 'netpeak-analytics-kit') . '</strong>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </section>

                <section x-show="activeTab === 'gsc'">
                    <h2 class="netpeak-analytics-kit__section-title"><?php esc_html_e('Search Console', 'netpeak-analytics-kit'); ?></h2>
                    <div class="netpeak-analytics-kit__checkbox-row">
                        <input type="checkbox" id="gsc-enabled" x-model="settings.data.gsc.enabled">
                        <label for="gsc-enabled"><?php esc_html_e('Enable Search Console integration', 'netpeak-analytics-kit'); ?></label>
                    </div>
                    <div class="netpeak-analytics-kit__row">
                        <label class="netpeak-analytics-kit__label" for="gsc-url"><?php esc_html_e('Property URL', 'netpeak-analytics-kit'); ?></label>
                        <input id="gsc-url" type="url" class="netpeak-analytics-kit__input regular-text"
                               x-model="settings.data.gsc.site_url"
                               placeholder="https://example.com/">
                    </div>
                    <div class="netpeak-analytics-kit__row">
                        <label class="netpeak-analytics-kit__label" for="gsc-file"><?php esc_html_e('HTML file name', 'netpeak-analytics-kit'); ?></label>
                        <input id="gsc-file" type="text" class="netpeak-analytics-kit__input regular-text"
                               x-model="settings.data.gsc.verification_file"
                               placeholder="googleXXXXXXXXXXXXXXX.html">
                    </div>
                </section>

                <section x-show="activeTab === 'meta-pixel'">
                    <h2 class="netpeak-analytics-kit__section-title">Meta Pixel</h2>
                    <p class="netpeak-analytics-kit__lede">
                        <?php esc_html_e('Client-side tracking for Facebook and Instagram. Fires events from the visitor\'s browser.', 'netpeak-analytics-kit'); ?>
                    </p>

                    <div class="netpeak-analytics-kit__checkbox-row">
                        <input type="checkbox" id="meta-pixel-enabled" x-model="settings.data.meta.pixel.enabled">
                        <label for="meta-pixel-enabled"><?php esc_html_e('Enable Meta Pixel', 'netpeak-analytics-kit'); ?></label>
                    </div>

                    <div class="netpeak-analytics-kit__row">
                        <label class="netpeak-analytics-kit__label" for="meta-pixel-id"><?php esc_html_e('Pixel ID', 'netpeak-analytics-kit'); ?></label>
                        <input id="meta-pixel-id" type="text" class="netpeak-analytics-kit__input regular-text"
                            x-model="settings.data.meta.pixel_id"
                            placeholder="1234567890123456"
                            autocomplete="off">
                    </div>
                    <p class="netpeak-analytics-kit__hint">
                        <?php esc_html_e('15-16 digit numeric ID from Meta Events Manager → your Pixel. Shared between Pixel and Conversions API.', 'netpeak-analytics-kit'); ?>
                    </p>

                    <div class="netpeak-analytics-kit__docs">
                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">1</span>
                                <?php esc_html_e('Open Events Manager', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    /* translators: %s: link to Meta Events Manager */
                                    esc_html__('Go to %s. Select your Pixel from the left sidebar.', 'netpeak-analytics-kit'),
                                    '<a href="https://business.facebook.com/events_manager" target="_blank" rel="noopener">Meta Events Manager</a>'
                                );
                                ?>
                            </p>
                        </div>

                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">2</span>
                                <?php esc_html_e('Copy the Pixel ID', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p>
                                <?php esc_html_e('The Pixel ID is displayed at the top of the page, right under the Pixel name. It\'s a 15-16 digit number. Paste it into the field above.', 'netpeak-analytics-kit'); ?>
                            </p>
                        </div>

                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">3</span>
                                <?php esc_html_e('What fires automatically', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p><?php esc_html_e('Once enabled, the plugin emits these events on the frontend:', 'netpeak-analytics-kit'); ?></p>
                            <ul class="netpeak-analytics-kit__api-list">
                                <li><code>PageView</code> — <?php esc_html_e('every page', 'netpeak-analytics-kit'); ?></li>
                                <li><code>ViewContent</code> — <?php esc_html_e('on single product pages', 'netpeak-analytics-kit'); ?></li>
                                <li><code>Search</code> — <?php esc_html_e('on search results', 'netpeak-analytics-kit'); ?></li>
                                <li><code>AddToCart</code> — <?php esc_html_e('on WooCommerce add to cart', 'netpeak-analytics-kit'); ?></li>
                                <li><code>InitiateCheckout</code> — <?php esc_html_e('on checkout page', 'netpeak-analytics-kit'); ?></li>
                                <li><code>Purchase</code> — <?php esc_html_e('on thank-you page', 'netpeak-analytics-kit'); ?></li>
                            </ul>
                            <p class="netpeak-analytics-kit__hint">
                                <?php
                                printf(
                                    /* translators: %s: <code>event_id</code> */
                                    esc_html__('Each event carries a unique %s so that when Conversions API sends the same event server-side, Meta deduplicates them automatically.', 'netpeak-analytics-kit'),
                                    '<code>event_id</code>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </section>

                <section x-show="activeTab === 'meta-capi'">
                    <h2 class="netpeak-analytics-kit__section-title">Meta Conversions API</h2>
                    <p class="netpeak-analytics-kit__lede">
                        <?php esc_html_e('Server-side event tracking. Bypasses ad blockers.', 'netpeak-analytics-kit'); ?>
                    </p>

                    <p class="netpeak-analytics-kit__hint">
                        <?php esc_html_e('Coming in the next update. Pixel above works independently.', 'netpeak-analytics-kit'); ?>
                    </p>
                </section>

                <section x-show="activeTab === 'oauth'">
                    <h2 class="netpeak-analytics-kit__section-title"><?php esc_html_e('Google OAuth', 'netpeak-analytics-kit'); ?></h2>
                    <p class="netpeak-analytics-kit__lede">
                        <?php esc_html_e('Create your own OAuth client in Google Cloud. This plugin never ships third-party credentials.', 'netpeak-analytics-kit'); ?>
                    </p>

                    <div class="netpeak-analytics-kit__docs">

                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">1</span>
                                <?php esc_html_e('Create a Google Cloud project', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    /* translators: %s: link to Google Cloud Console project creation page */
                                    esc_html__('Open %s. Pick any name — it\'s only visible to you.', 'netpeak-analytics-kit'),
                                    '<a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">' . esc_html__('Google Cloud Console → New Project', 'netpeak-analytics-kit') . '</a>'
                                );
                                ?>
                            </p>
                        </div>

                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">2</span>
                                <?php esc_html_e('Enable required Google APIs', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p><?php esc_html_e('Enable both APIs for your Google Cloud project:', 'netpeak-analytics-kit'); ?></p>
                            <ul class="netpeak-analytics-kit__api-list">
                                <li>
                                    <?php
                                    printf(
                                        /* translators: %s: link to Search Console API in Google Cloud */
                                        esc_html__('%s — required for Search Console dashboard metrics', 'netpeak-analytics-kit'),
                                        '<a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener">' . esc_html__('Search Console API', 'netpeak-analytics-kit') . '</a>'
                                    );
                                    ?>
                                </li>
                                <li>
                                    <?php
                                    printf(
                                        /* translators: %s: link to Google Analytics Data API in Google Cloud */
                                        esc_html__('%s — required for GA4 metrics', 'netpeak-analytics-kit'),
                                        '<a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank" rel="noopener">' . esc_html__('Google Analytics Data API', 'netpeak-analytics-kit') . '</a>'
                                    );
                                    ?>
                                </li>
                            </ul>
                            <p class="netpeak-analytics-kit__hint">
                                <?php esc_html_e('Skipping one of these results in a 403 / "API has not been used" error on the corresponding cards. Google may take 1–2 minutes to propagate changes.', 'netpeak-analytics-kit'); ?>
                            </p>
                        </div>

                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">3</span>
                                <?php esc_html_e('Configure the OAuth consent screen', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    /* translators: 1: link to OAuth consent screen, 2: "External" emphasised, 3: webmasters.readonly scope */
                                    esc_html__('Open %1$s. Choose %2$s audience, fill app name and contact email, then add the scope %3$s.', 'netpeak-analytics-kit'),
                                    '<a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank" rel="noopener">' . esc_html__('APIs & Services → OAuth consent screen', 'netpeak-analytics-kit') . '</a>',
                                    '<strong>' . esc_html__('External', 'netpeak-analytics-kit') . '</strong>',
                                    '<code>.../auth/webmasters.readonly</code>'
                                );
                                ?>
                            </p>
                            <p class="netpeak-analytics-kit__hint">
                                <?php
                                printf(
                                    /* translators: 1: "Testing mode" emphasised, 2: "Test users" emphasised */
                                    esc_html__('While the app is in %1$s, add your own Google account under %2$s — otherwise the consent screen will block with "Access denied".', 'netpeak-analytics-kit'),
                                    '<em>' . esc_html__('Testing mode', 'netpeak-analytics-kit') . '</em>',
                                    '<strong>' . esc_html__('Test users', 'netpeak-analytics-kit') . '</strong>'
                                );
                                ?>
                            </p>
                        </div>

                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">4</span>
                                <?php esc_html_e('Create the OAuth client ID', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    /* translators: 1: link to Credentials page, 2: "Web application" emphasised */
                                    esc_html__('Go to %1$s. Application type: %2$s.', 'netpeak-analytics-kit'),
                                    '<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">' . esc_html__('Credentials → Create credentials → OAuth client ID', 'netpeak-analytics-kit') . '</a>',
                                    '<strong>' . esc_html__('Web application', 'netpeak-analytics-kit') . '</strong>'
                                );
                                ?>
                            </p>
                            <p>
                                <?php
                                printf(
                                    /* translators: %s: "Authorized redirect URIs" emphasised */
                                    esc_html__('Paste this URL into %s — character by character, no trailing slash:', 'netpeak-analytics-kit'),
                                    '<strong>' . esc_html__('Authorized redirect URIs', 'netpeak-analytics-kit') . '</strong>'
                                );
                                ?>
                            </p>

                            <div class="netpeak-analytics-kit__callout">
                                <code class="netpeak-analytics-kit__callout-code"><?php echo esc_url($callback_url); ?></code>
                                <button type="button"
                                        class="button"
                                        @click="navigator.clipboard.writeText('<?php echo esc_js($callback_url); ?>')">
                                    <?php esc_html_e('Copy', 'netpeak-analytics-kit'); ?>
                                </button>
                            </div>

                            <p class="netpeak-analytics-kit__hint">
                                <?php
                                printf(
                                    /* translators: 1: <code>redirect_uri_mismatch</code>, 2: link to Permalinks settings */
                                    esc_html__('Mismatch here triggers a %1$s error on the Google consent screen. Make sure your site uses %2$s.', 'netpeak-analytics-kit'),
                                    '<code>redirect_uri_mismatch</code>',
                                    '<a href="' . esc_url(admin_url('options-permalink.php')) . '">' . esc_html__('pretty permalinks (Settings → Permalinks)', 'netpeak-analytics-kit') . '</a>'
                                );
                                ?>
                            </p>
                        </div>

                        <div class="netpeak-analytics-kit__step">
                            <h4 class="netpeak-analytics-kit__step-title">
                                <span class="netpeak-analytics-kit__step-num">5</span>
                                <?php esc_html_e('Paste credentials and save', 'netpeak-analytics-kit'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    /* translators: %s: "encrypted" emphasised */
                                    esc_html__('Google shows Client ID and Client Secret right after creating the client. Client Secret is stored %s (AES-256-GCM) in the database.', 'netpeak-analytics-kit'),
                                    '<strong>' . esc_html__('encrypted', 'netpeak-analytics-kit') . '</strong>'
                                );
                                ?>
                            </p>
                        </div>

                    </div>

                    <div class="netpeak-analytics-kit__row">
                        <label class="netpeak-analytics-kit__label" for="oauth-id"><?php esc_html_e('Client ID', 'netpeak-analytics-kit'); ?></label>
                        <input id="oauth-id" type="text" class="netpeak-analytics-kit__input regular-text"
                            x-model="settings.data.oauth.client_id"
                            autocomplete="off">
                    </div>
                    <div class="netpeak-analytics-kit__row">
                        <label class="netpeak-analytics-kit__label" for="oauth-secret"><?php esc_html_e('Client Secret', 'netpeak-analytics-kit'); ?></label>
                        <input id="oauth-secret" type="password" class="netpeak-analytics-kit__input regular-text"
                            x-model="settings.data.oauth.client_secret"
                            autocomplete="off">
                        <p class="netpeak-analytics-kit__hint">
                            <?php esc_html_e('Leave empty to keep the current value. The secret is stored encrypted and never returned to the browser.', 'netpeak-analytics-kit'); ?>
                        </p>
                    </div>
                </section>

                <div class="netpeak-analytics-kit__actions">
                    <button type="button" class="button button-primary"
                            @click="save()"
                            :disabled="settings.saving">
                        <span x-show="!settings.saving"><?php esc_html_e('Save settings', 'netpeak-analytics-kit'); ?></span>
                        <span x-show="settings.saving"><?php esc_html_e('Saving…', 'netpeak-analytics-kit'); ?></span>
                    </button>
                    <span x-show="settings.saved" class="netpeak-analytics-kit__toast"><?php esc_html_e('Saved', 'netpeak-analytics-kit'); ?></span>
                </div>
            </div>

        </div>
    </template>
</div>
