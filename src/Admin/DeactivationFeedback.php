<?php

declare(strict_types=1);

namespace Netpeak\Admin;
if (!defined('ABSPATH')) {
    exit;
}

use Netpeak\Settings\SettingsRepository;

/**
 * "Why are you deactivating?" dialog on the Plugins screen.
 *
 * Two independent things happen when the dialog is confirmed:
 *  1. The "delete all data when the plugin is deleted" choice is saved into the settings (general.delete_data_on_uninstall).
 *     uninstall.php reads it, and keeps everything when it is off.
 *  2. Only when the user presses "Submit & Deactivate", the reason is emailed with wp_mail().
 *     "Skip & Deactivate" sends nothing. No site URL, user name or email is included.
 *
 * Filter: netpeak_aio_feedback_recipient (string $email).
 *
 * @since 0.1.2
 */
final class DeactivationFeedback
{
    private const ACTION        = 'netpeak_aio_feedback';
    private const NONCE         = 'netpeak_aio_feedback';
    private const HANDLE        = 'netpeak-aio-deactivation';
    private const RECIPIENT     = 'info@netpeak.bg';
    private const MAX_DETAILS   = 1000;

    /**
     * Reason slug => label. "needs_details" reasons show a text field.
     *
     * @return array<string, array{label: string, details: string}>
     */
    private static function reasons(): array
    {
        return [
            'temporary'  => ['label' => __('It is a temporary deactivation (debugging, testing)', 'netpeak-analytics-kit'), 'details' => ''],
            'not_working' => ['label' => __('The plugin does not work or causes errors', 'netpeak-analytics-kit'), 'details' => __('What went wrong?', 'netpeak-analytics-kit')],
            'missing'    => ['label' => __('It is missing a feature I need', 'netpeak-analytics-kit'), 'details' => __('Which feature?', 'netpeak-analytics-kit')],
            'complicated' => ['label' => __('It is too complicated to set up', 'netpeak-analytics-kit'), 'details' => __('What was unclear?', 'netpeak-analytics-kit')],
            'alternative' => ['label' => __('I found a better plugin', 'netpeak-analytics-kit'), 'details' => __('Which plugin?', 'netpeak-analytics-kit')],
            'no_need'    => ['label' => __('I no longer need it', 'netpeak-analytics-kit'), 'details' => ''],
            'other'      => ['label' => __('Other', 'netpeak-analytics-kit'), 'details' => __('Tell us more', 'netpeak-analytics-kit')],
        ];
    }

    /**
     * @param SettingsRepository $settings
     */
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /**
     * @return void
     */
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle']);
    }

    /**
     * @param string $hook
     *
     * @return void
     */
    public function enqueue(string $hook): void
    {
        if ($hook !== 'plugins.php') {
            return;
        }

        $reasons = [];
        foreach (self::reasons() as $slug => $reason) {
            $reasons[] = ['slug' => $slug, 'label' => $reason['label'], 'details' => $reason['details']];
        }

        wp_enqueue_style(
            self::HANDLE,
            NTP_AIO_URL . 'assets/admin/css/deactivation.css',
            [],
            NTP_AIO_VERSION
        );

        wp_enqueue_script(
            self::HANDLE,
            NTP_AIO_URL . 'assets/admin/js/deactivation.js',
            [],
            NTP_AIO_VERSION,
            true
        );

        $config = [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'action'     => self::ACTION,
            'nonce'      => wp_create_nonce(self::NONCE),
            'plugin'     => NTP_AIO_BASENAME,
            'deleteData' => (bool) $this->settings->get('general.delete_data_on_uninstall', false),
            'reasons'    => $reasons,
            'i18n'       => [
                'title'      => __('Quick feedback', 'netpeak-analytics-kit'),
                'lede'       => __('If you have a moment, tell us why you are deactivating Netpeak Analytics Kit.', 'netpeak-analytics-kit'),
                'privacy'    => __('Only the reason and the text you type are sent, together with the plugin, WordPress and PHP versions. No site address, no personal data.', 'netpeak-analytics-kit'),
                'deleteData' => __('Delete all plugin data (settings, Google tokens, cached reports) when the plugin is deleted', 'netpeak-analytics-kit'),
                'deleteHint' => __('Leave unchecked to keep your configuration if you reinstall the plugin later.', 'netpeak-analytics-kit'),
                'submit'     => __('Submit & Deactivate', 'netpeak-analytics-kit'),
                'skip'       => __('Skip & Deactivate', 'netpeak-analytics-kit'),
                'cancel'     => __('Cancel', 'netpeak-analytics-kit'),
                'sending'    => __('Please wait…', 'netpeak-analytics-kit'),
            ],
        ];

        wp_add_inline_script(
            self::HANDLE,
            'window.NetpeakAIOFeedback = ' . wp_json_encode($config) . ';',
            'before'
        );
    }

    /**
     * Saves the data-retention choice and optionally emails the reason. Never blocks the deactivation:
     * the browser continues to the deactivate URL whatever the response is.
     *
     * @return void
     */
    public function handle(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');

        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        $delete_data = isset($_POST['delete_data']) && sanitize_text_field(wp_unslash($_POST['delete_data'])) === '1';
        $this->save_delete_choice($delete_data);

        $send = isset($_POST['send']) && sanitize_text_field(wp_unslash($_POST['send'])) === '1';
        $sent = false;

        if ($send) {
            $slug    = isset($_POST['reason']) ? sanitize_key(wp_unslash($_POST['reason'])) : '';
            $reasons = self::reasons();

            if (isset($reasons[$slug])) {
                $details = isset($_POST['details'])
                    ? mb_substr(sanitize_textarea_field(wp_unslash($_POST['details'])), 0, self::MAX_DETAILS)
                    : '';

                $sent = $this->send_mail($reasons[$slug]['label'], $slug, $details);
            }
        }

        wp_send_json_success(['sent' => $sent]);
    }

    /**
     * @param bool $delete_data
     *
     * @return void
     */
    private function save_delete_choice(bool $delete_data): void
    {
        $all = $this->settings->all();

        $all['general'] = ['delete_data_on_uninstall' => $delete_data];

        $this->settings->save($all);
    }

    /**
     * @param string $label
     * @param string $slug
     * @param string $details
     *
     * @return bool
     */
    private function send_mail(string $label, string $slug, string $details): bool
    {
        global $wp_version;

        $recipient = sanitize_email((string) apply_filters('netpeak_aio_feedback_recipient', self::RECIPIENT));
        if ($recipient === '' || !is_email($recipient)) {
            return false;
        }

        $body = implode("\n", [
            'Reason: ' . $label . ' (' . $slug . ')',
            'Details: ' . ($details !== '' ? $details : '-'),
            '',
            'Plugin: ' . NTP_AIO_VERSION,
            'WordPress: ' . $wp_version,
            'PHP: ' . PHP_VERSION,
            'Locale: ' . get_locale(),
        ]);

        return wp_mail($recipient, '[Netpeak Analytics Kit] Deactivation feedback: ' . $slug, $body);
    }
}
