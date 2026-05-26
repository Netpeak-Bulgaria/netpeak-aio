<?php
/**
 * @var string              $title
 * @var string              $view  Absolute path to the concrete page view.
 * @var array<string,mixed> $data
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap netpeak-aio">
    <h1><?php echo esc_html($title); ?></h1>

    <div class="netpeak-aio__content">
        <?php include $view; ?>
    </div>
</div>
