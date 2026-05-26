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
        <div class="netpeak-aio__error" x-text="settings.error"></div>
    </template>

    <template x-if="settings.loading && !settings.data">
        <p class="netpeak-aio__loading"><?php esc_html_e('Loading settings…', 'netpeak-aio'); ?></p>
    </template>

    <template x-if="settings.data">
        <div class="netpeak-aio__layout">

            <aside class="netpeak-aio__sidebar">
                <template x-for="group in groups" :key="group.label">
                    <div class="netpeak-aio__nav-group">
                        <p class="netpeak-aio__nav-group-title" x-text="group.label"></p>
                        <ul class="netpeak-aio__nav-list">
                            <template x-for="tab in group.tabs" :key="tab.key">
                                <li>
                                    <button
                                        type="button"
                                        class="netpeak-aio__nav-item"
                                        :class="activeTab === tab.key ? 'netpeak-aio__nav-item--active' : ''"
                                        :aria-current="activeTab === tab.key ? 'page' : false"
                                        @click="activeTab = tab.key"
                                    >
                                        <span class="netpeak-aio__nav-label" x-text="tab.label"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </aside>

            <div class="netpeak-aio__panel">

                <section x-show="activeTab === 'ga4'">
                    <h2 class="netpeak-aio__section-title">Google Analytics 4</h2>

                    <div class="netpeak-aio__checkbox-row">
                        <input type="checkbox" id="ga4-enabled" x-model="settings.data.ga4.enabled">
                        <label for="ga4-enabled"><?php esc_html_e('Enable Google Analytics 4', 'netpeak-aio'); ?></label>
                    </div>

                    <div class="netpeak-aio__row">
                        <label class="netpeak-aio__label" for="ga4-mid"><?php esc_html_e('Measurement ID', 'netpeak-aio'); ?></label>
                        <input id="ga4-mid" type="text" class="netpeak-aio__input regular-text"
                            x-model="settings.data.ga4.measurement_id"
                            placeholder="G-XXXXXXXXXX">
                    </div>

                    <div class="netpeak-aio__row">
                        <label class="netpeak-aio__label" for="ga4-pid"><?php esc_html_e('Property ID', 'netpeak-aio'); ?></label>
                        <input id="ga4-pid" type="text" class="netpeak-aio__input regular-text"
                            x-model="settings.data.ga4.property_id"
                            placeholder="347293851">
                    </div>

                    <p class="netpeak-aio__hint">
                        <?php esc_html_e('Numeric ID from GA4 → Admin → Property Settings. Different from Measurement ID — used only for API calls.', 'netpeak-aio'); ?>
                    </p>

                    <div class="netpeak-aio__checkbox-row">
                        <input type="checkbox" id="ga4-via-gtm" x-model="settings.data.ga4.route_via_gtm">
                        <label for="ga4-via-gtm"><?php esc_html_e('Route GA4 through Tag Manager (skip direct gtag.js output)', 'netpeak-aio'); ?></label>
                    </div>
                </section>

                <section x-show="activeTab === 'gtm'">
                    <h2 class="netpeak-aio__section-title">Google Tag Manager</h2>
                    <div class="netpeak-aio__checkbox-row">
                        <input type="checkbox" id="gtm-enabled" x-model="settings.data.gtm.enabled">
                        <label for="gtm-enabled"><?php esc_html_e('Enable Google Tag Manager', 'netpeak-aio'); ?></label>
                    </div>
                    <div class="netpeak-aio__row">
                        <label class="netpeak-aio__label" for="gtm-id"><?php esc_html_e('Container ID', 'netpeak-aio'); ?></label>
                        <input id="gtm-id" type="text" class="netpeak-aio__input regular-text"
                               x-model="settings.data.gtm.container_id"
                               placeholder="GTM-XXXXXXX">
                    </div>
                </section>

                <section x-show="activeTab === 'gsc'">
                    <h2 class="netpeak-aio__section-title"><?php esc_html_e('Search Console', 'netpeak-aio'); ?></h2>
                    <div class="netpeak-aio__checkbox-row">
                        <input type="checkbox" id="gsc-enabled" x-model="settings.data.gsc.enabled">
                        <label for="gsc-enabled"><?php esc_html_e('Enable Search Console integration', 'netpeak-aio'); ?></label>
                    </div>
                    <div class="netpeak-aio__row">
                        <label class="netpeak-aio__label" for="gsc-url"><?php esc_html_e('Property URL', 'netpeak-aio'); ?></label>
                        <input id="gsc-url" type="url" class="netpeak-aio__input regular-text"
                               x-model="settings.data.gsc.site_url"
                               placeholder="https://example.com/">
                    </div>
                    <div class="netpeak-aio__row">
                        <label class="netpeak-aio__label" for="gsc-file"><?php esc_html_e('HTML file name', 'netpeak-aio'); ?></label>
                        <input id="gsc-file" type="text" class="netpeak-aio__input regular-text"
                               x-model="settings.data.gsc.verification_file"
                               placeholder="googleXXXXXXXXXXXXXXX.html">
                    </div>
                </section>

                <section x-show="activeTab === 'meta-pixel'">
                    <h2 class="netpeak-aio__section-title">Meta Pixel</h2>
                    <p class="netpeak-aio__lede">
                        <?php esc_html_e('Client-side tracking for Facebook and Instagram. Fires events from the visitor\'s browser.', 'netpeak-aio'); ?>
                    </p>

                    <div class="netpeak-aio__checkbox-row">
                        <input type="checkbox" id="meta-pixel-enabled" x-model="settings.data.meta.pixel.enabled">
                        <label for="meta-pixel-enabled"><?php esc_html_e('Enable Meta Pixel', 'netpeak-aio'); ?></label>
                    </div>

                    <div class="netpeak-aio__row">
                        <label class="netpeak-aio__label" for="meta-pixel-id"><?php esc_html_e('Pixel ID', 'netpeak-aio'); ?></label>
                        <input id="meta-pixel-id" type="text" class="netpeak-aio__input regular-text"
                            x-model="settings.data.meta.pixel_id"
                            placeholder="1234567890123456"
                            autocomplete="off">
                    </div>
                    <p class="netpeak-aio__hint">
                        <?php esc_html_e('15-16 digit numeric ID from Meta Events Manager → your Pixel. Shared between Pixel and Conversions API.', 'netpeak-aio'); ?>
                    </p>

                    <div class="netpeak-aio__docs">
                        <div class="netpeak-aio__step">
                            <h4 class="netpeak-aio__step-title">
                                <span class="netpeak-aio__step-num">1</span>
                                <?php esc_html_e('Open Events Manager', 'netpeak-aio'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    esc_html__('Go to %s. Select your Pixel from the left sidebar.', 'netpeak-aio'),
                                    '<a href="https://business.facebook.com/events_manager" target="_blank" rel="noopener">Meta Events Manager</a>'
                                );
                                ?>
                            </p>
                        </div>

                        <div class="netpeak-aio__step">
                            <h4 class="netpeak-aio__step-title">
                                <span class="netpeak-aio__step-num">2</span>
                                <?php esc_html_e('Copy the Pixel ID', 'netpeak-aio'); ?>
                            </h4>
                            <p>
                                <?php esc_html_e('The Pixel ID is displayed at the top of the page, right under the Pixel name. It\'s a 15-16 digit number. Paste it into the field above.', 'netpeak-aio'); ?>
                            </p>
                        </div>

                        <div class="netpeak-aio__step">
                            <h4 class="netpeak-aio__step-title">
                                <span class="netpeak-aio__step-num">3</span>
                                <?php esc_html_e('What fires automatically', 'netpeak-aio'); ?>
                            </h4>
                            <p><?php esc_html_e('Once enabled, the plugin emits these events on the frontend:', 'netpeak-aio'); ?></p>
                            <ul class="netpeak-aio__api-list">
                                <li><code>PageView</code> — <?php esc_html_e('every page', 'netpeak-aio'); ?></li>
                                <li><code>ViewContent</code> — <?php esc_html_e('on single product pages', 'netpeak-aio'); ?></li>
                                <li><code>Search</code> — <?php esc_html_e('on search results', 'netpeak-aio'); ?></li>
                                <li><code>AddToCart</code> — <?php esc_html_e('on WooCommerce add to cart', 'netpeak-aio'); ?></li>
                                <li><code>InitiateCheckout</code> — <?php esc_html_e('on checkout page', 'netpeak-aio'); ?></li>
                                <li><code>Purchase</code> — <?php esc_html_e('on thank-you page', 'netpeak-aio'); ?></li>
                            </ul>
                            <p class="netpeak-aio__hint">
                                <?php
                                printf(
                                    esc_html__('Each event carries a unique %s so that when Conversions API sends the same event server-side, Meta deduplicates them automatically.', 'netpeak-aio'),
                                    '<code>event_id</code>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </section>

                <section x-show="activeTab === 'meta-capi'">
                    <h2 class="netpeak-aio__section-title">Meta Conversions API</h2>
                    <p class="netpeak-aio__lede">
                        <?php esc_html_e('Server-side event tracking. Bypasses ad blockers.', 'netpeak-aio'); ?>
                    </p>

                    <p class="netpeak-aio__hint">
                        <?php esc_html_e('Coming in the next update. Pixel above works independently.', 'netpeak-aio'); ?>
                    </p>
                </section>

                <section x-show="activeTab === 'oauth'">
                    <h2 class="netpeak-aio__section-title"><?php esc_html_e('Google OAuth', 'netpeak-aio'); ?></h2>
                    <p class="netpeak-aio__lede">
                        <?php esc_html_e('Create your own OAuth client in Google Cloud. This plugin never ships third-party credentials.', 'netpeak-aio'); ?>
                    </p>

                    <div class="netpeak-aio__docs">

                        <div class="netpeak-aio__step">
                            <h4 class="netpeak-aio__step-title">
                                <span class="netpeak-aio__step-num">1</span>
                                <?php esc_html_e('Create a Google Cloud project', 'netpeak-aio'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    esc_html__('Open %s. Pick any name — it\'s only visible to you.', 'netpeak-aio'),
                                    '<a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">' . esc_html__('Google Cloud Console → New Project', 'netpeak-aio') . '</a>'
                                );
                                ?>
                            </p>
                        </div>

                        <div class="netpeak-aio__step">
                            <h4 class="netpeak-aio__step-title">
                                <span class="netpeak-aio__step-num">2</span>
                                <?php esc_html_e('Enable required Google APIs', 'netpeak-aio'); ?>
                            </h4>
                            <p><?php esc_html_e('Enable both APIs for your Google Cloud project:', 'netpeak-aio'); ?></p>
                            <ul class="netpeak-aio__api-list">
                                <li>
                                    <?php
                                    printf(
                                        esc_html__('%s — required for Search Console dashboard metrics', 'netpeak-aio'),
                                        '<a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener">' . esc_html__('Search Console API', 'netpeak-aio') . '</a>'
                                    );
                                    ?>
                                </li>
                                <li>
                                    <?php
                                    printf(
                                        esc_html__('%s — required for GA4 metrics', 'netpeak-aio'),
                                        '<a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank" rel="noopener">' . esc_html__('Google Analytics Data API', 'netpeak-aio') . '</a>'
                                    );
                                    ?>
                                </li>
                            </ul>
                            <p class="netpeak-aio__hint">
                                <?php esc_html_e('Skipping one of these results in a 403 / "API has not been used" error on the corresponding cards. Google may take 1–2 minutes to propagate changes.', 'netpeak-aio'); ?>
                            </p>
                        </div>

                        <div class="netpeak-aio__step">
                            <h4 class="netpeak-aio__step-title">
                                <span class="netpeak-aio__step-num">3</span>
                                <?php esc_html_e('Configure the OAuth consent screen', 'netpeak-aio'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    esc_html__('Open %1$s. Choose %2$s audience, fill app name and contact email, then add the scope %3$s.', 'netpeak-aio'),
                                    '<a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank" rel="noopener">' . esc_html__('APIs & Services → OAuth consent screen', 'netpeak-aio') . '</a>',
                                    '<strong>' . esc_html__('External', 'netpeak-aio') . '</strong>',
                                    '<code>.../auth/webmasters.readonly</code>'
                                );
                                ?>
                            </p>
                            <p class="netpeak-aio__hint">
                                <?php
                                printf(
                                    esc_html__('While the app is in %1$s, add your own Google account under %2$s — otherwise the consent screen will block with "Access denied".', 'netpeak-aio'),
                                    '<em>' . esc_html__('Testing mode', 'netpeak-aio') . '</em>',
                                    '<strong>' . esc_html__('Test users', 'netpeak-aio') . '</strong>'
                                );
                                ?>
                            </p>
                        </div>

                        <div class="netpeak-aio__step">
                            <h4 class="netpeak-aio__step-title">
                                <span class="netpeak-aio__step-num">4</span>
                                <?php esc_html_e('Create the OAuth client ID', 'netpeak-aio'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    esc_html__('Go to %1$s. Application type: %2$s.', 'netpeak-aio'),
                                    '<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">' . esc_html__('Credentials → Create credentials → OAuth client ID', 'netpeak-aio') . '</a>',
                                    '<strong>' . esc_html__('Web application', 'netpeak-aio') . '</strong>'
                                );
                                ?>
                            </p>
                            <p>
                                <?php
                                printf(
                                    esc_html__('Paste this URL into %s — character by character, no trailing slash:', 'netpeak-aio'),
                                    '<strong>' . esc_html__('Authorized redirect URIs', 'netpeak-aio') . '</strong>'
                                );
                                ?>
                            </p>

                            <div class="netpeak-aio__callout">
                                <code class="netpeak-aio__callout-code"><?php echo esc_url($callback_url); ?></code>
                                <button type="button"
                                        class="button"
                                        @click="navigator.clipboard.writeText('<?php echo esc_js($callback_url); ?>')">
                                    <?php esc_html_e('Copy', 'netpeak-aio'); ?>
                                </button>
                            </div>

                            <p class="netpeak-aio__hint">
                                <?php
                                printf(
                                    esc_html__('Mismatch here triggers a %1$s error on the Google consent screen. Make sure your site uses %2$s.', 'netpeak-aio'),
                                    '<code>redirect_uri_mismatch</code>',
                                    '<a href="' . esc_url(admin_url('options-permalink.php')) . '">' . esc_html__('pretty permalinks (Settings → Permalinks)', 'netpeak-aio') . '</a>'
                                );
                                ?>
                            </p>
                        </div>

                        <div class="netpeak-aio__step">
                            <h4 class="netpeak-aio__step-title">
                                <span class="netpeak-aio__step-num">5</span>
                                <?php esc_html_e('Paste credentials and save', 'netpeak-aio'); ?>
                            </h4>
                            <p>
                                <?php
                                printf(
                                    esc_html__('Google shows Client ID and Client Secret right after creating the client. Client Secret is stored %s (AES-256-GCM) in the database.', 'netpeak-aio'),
                                    '<strong>' . esc_html__('encrypted', 'netpeak-aio') . '</strong>'
                                );
                                ?>
                            </p>
                        </div>

                    </div>

                    <div class="netpeak-aio__row">
                        <label class="netpeak-aio__label" for="oauth-id"><?php esc_html_e('Client ID', 'netpeak-aio'); ?></label>
                        <input id="oauth-id" type="text" class="netpeak-aio__input regular-text"
                            x-model="settings.data.oauth.client_id"
                            autocomplete="off">
                    </div>
                    <div class="netpeak-aio__row">
                        <label class="netpeak-aio__label" for="oauth-secret"><?php esc_html_e('Client Secret', 'netpeak-aio'); ?></label>
                        <input id="oauth-secret" type="password" class="netpeak-aio__input regular-text"
                            x-model="settings.data.oauth.client_secret"
                            autocomplete="off">
                        <p class="netpeak-aio__hint">
                            <?php esc_html_e('Leave empty to keep the current value. The secret is stored encrypted and never returned to the browser.', 'netpeak-aio'); ?>
                        </p>
                    </div>
                </section>

                <div class="netpeak-aio__actions">
                    <button type="button" class="button button-primary"
                            @click="save()"
                            :disabled="settings.saving">
                        <span x-show="!settings.saving"><?php esc_html_e('Save settings', 'netpeak-aio'); ?></span>
                        <span x-show="settings.saving"><?php esc_html_e('Saving…', 'netpeak-aio'); ?></span>
                    </button>
                    <span x-show="settings.saved" class="netpeak-aio__toast"><?php esc_html_e('Saved', 'netpeak-aio'); ?></span>
                </div>
            </div>

        </div>
    </template>
</div>
