<?php
if (!defined('ABSPATH')) {
    exit;
}
// Hier is de variabele $title direct beschikbaar dankzij extract()!
?>
<h1><?php echo esc_html($title ?? ''); ?></h1>
<p><?php echo esc_html($description ?? ''); ?></p>