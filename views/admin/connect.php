<?php
/**
 * @var array{connected?: bool} $data
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$connected      = !empty($data['connected']);

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by our own OAuth callback redirect, no state change.
$just_connected = isset($_GET['connected']) && sanitize_text_field(wp_unslash($_GET['connected'])) === '1';
$callback_url   = rest_url('netpeak-aio/v1/oauth/callback');
$settings_url   = admin_url('admin.php?page=netpeak-aio-settings');
?>
<div x-data="oauthButton(<?php echo $connected ? 'true' : 'false'; ?>)" class="netpeak-analytics-kit__connect">

    <?php if ($just_connected) : ?>
        <div class="notice notice-success inline">
            <p><?php esc_html_e('Google account connected successfully.', 'netpeak-analytics-kit'); ?></p>
        </div>
    <?php endif; ?>

    <template x-if="error">
        <div class="netpeak-analytics-kit__error" x-text="error"></div>
    </template>

    <div class="netpeak-analytics-kit__hero"
         :class="connected ? 'netpeak-analytics-kit__hero--connected' : 'netpeak-analytics-kit__hero--disconnected'">

        <div class="netpeak-analytics-kit__hero-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="40" height="40">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
        </div>

        <div class="netpeak-analytics-kit__hero-body">
            <div class="netpeak-analytics-kit__hero-status">
                <span class="netpeak-analytics-kit__status"
                      :class="connected ? 'netpeak-analytics-kit__status--connected' : 'netpeak-analytics-kit__status--disconnected'"
                      x-text="connected ? '<?php esc_html_e('Connected', 'netpeak-analytics-kit'); ?>' : '<?php esc_html_e('Not connected', 'netpeak-analytics-kit'); ?>'"></span>
            </div>

            <h2 class="netpeak-analytics-kit__hero-title"
                x-text="connected ? '<?php esc_html_e('Your Google account is linked', 'netpeak-analytics-kit'); ?>' : '<?php esc_html_e('Link your Google account', 'netpeak-analytics-kit'); ?>'"></h2>

            <p class="netpeak-analytics-kit__hero-lede"
               x-text="connected
                   ? '<?php esc_html_e('The plugin can now fetch Search Console data on your behalf. You can disconnect at any time.', 'netpeak-analytics-kit'); ?>'
                   : '<?php esc_html_e('Authorize access to Google Search Console so the dashboard can display performance metrics for your property.', 'netpeak-analytics-kit'); ?>'"></p>

            <div class="netpeak-analytics-kit__hero-actions">
                <template x-if="!connected">
                    <button type="button"
                            class="button button-primary button-hero"
                            @click="connect()"
                            :disabled="loading">
                        <span x-show="!loading"><?php esc_html_e('Connect Google account', 'netpeak-analytics-kit'); ?></span>
                        <span x-show="loading"><?php esc_html_e('Redirecting…', 'netpeak-analytics-kit'); ?></span>
                    </button>
                </template>

                <template x-if="connected">
                    <button type="button"
                            class="button button-secondary"
                            @click="disconnect()"
                            :disabled="loading">
                        <span x-show="!loading"><?php esc_html_e('Disconnect account', 'netpeak-analytics-kit'); ?></span>
                        <span x-show="loading"><?php esc_html_e('Disconnecting…', 'netpeak-analytics-kit'); ?></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <div class="netpeak-analytics-kit__checklist">
        <h3 class="netpeak-analytics-kit__checklist-title"><?php esc_html_e('Before you connect', 'netpeak-analytics-kit'); ?></h3>
        <ul class="netpeak-analytics-kit__checklist-list">
            <li class="netpeak-analytics-kit__checklist-item">
                <span class="netpeak-analytics-kit__checklist-num">1</span>
                <div>
                    <p class="netpeak-analytics-kit__checklist-label"><?php esc_html_e('OAuth credentials filled in', 'netpeak-analytics-kit'); ?></p>
                    <p class="netpeak-analytics-kit__checklist-desc">

                        <?php
                        echo wp_kses(
                            sprintf(
                                /* translators: %s: OAuth settings URL */
                                __('Open <a href="%s">Settings → OAuth</a> and paste your Client ID and Client Secret from Google Cloud Console.', 'netpeak-analytics-kit'),
                                esc_url($settings_url)
                            ),
                            [
                                'a' => [
                                    'href' => [],
                                ],
                            ]
                        );
                        ?>
                    </p>
                </div>
            </li>
            <li class="netpeak-analytics-kit__checklist-item">
                <span class="netpeak-analytics-kit__checklist-num">2</span>
                <div>
                    <p class="netpeak-analytics-kit__checklist-label"><?php esc_html_e('Settings in Google Cloud', 'netpeak-analytics-kit'); ?></p>
                    <p class="netpeak-analytics-kit__checklist-desc">
                        <?php esc_html_e('Setting up your OAuth consent screen and credentials in Google Cloud Console.', 'netpeak-analytics-kit'); ?>
                    </p>
                </div>
            </li>
        </ul>
    </div>

    <div class="netpeak-analytics-kit__privacy">
        <h3 class="netpeak-analytics-kit__privacy-title"><?php esc_html_e('What the plugin does with this connection', 'netpeak-analytics-kit'); ?></h3>
        <ul class="netpeak-analytics-kit__privacy-list">
            <li><?php esc_html_e('Reads Search Console metrics (clicks, impressions, CTR, position) for the selected property.', 'netpeak-analytics-kit'); ?></li>
            <li>
                <?php
                printf(
                    /* translators: 1: "encrypted" emphasised, 2: link to AES-256-GCM Wikipedia article */
                    esc_html__('Stores access and refresh tokens %1$s %2$s in your database.', 'netpeak-analytics-kit'),
                    '<strong>' . esc_html__('encrypted', 'netpeak-analytics-kit') . '</strong>',
                    '<a href="https://en.wikipedia.org/wiki/Galois/Counter_Mode" target="_blank" rel="noopener">' . esc_html__('(AES-256-GCM)', 'netpeak-analytics-kit') . '</a>'
                );
                ?>
            </li>
            <li><?php esc_html_e('Never transmits tokens or site data to any third-party server — everything goes directly to Google.', 'netpeak-analytics-kit'); ?></li>
            <li>
                <?php
                printf(
                    /* translators: %s: link to Google Account permissions page */
                    esc_html__('Disconnecting here revokes tokens locally. To fully revoke access, also remove it from your %s.', 'netpeak-analytics-kit'),
                    '<a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">' . esc_html__('Google Account permissions', 'netpeak-analytics-kit') . '</a>'
                );
                ?>
            </li>
        </ul>
    </div>

</div>
