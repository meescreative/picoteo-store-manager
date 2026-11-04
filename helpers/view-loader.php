<?php
if (!defined('ABSPATH')) {
    exit;
}

function psm_load_view($view_name, $data = [])
{
    if (!empty($data) && is_array($data)) {
        extract($data);
    }

    // 1. Check of het direct een .php bestand is (bijv. 'dashboard/index')
    $file = PSM_PLUGIN_DIR . 'views/' . $view_name . '.php';

    // 2. Zo niet, check dan of het een map is en laad automatisch de index.php (bijv. 'setup' -> 'setup/index.php')
    if (!file_exists($file)) {
        $file = PSM_PLUGIN_DIR . 'views/' . $view_name . '/index.php';
    }

    if (file_exists($file)) {
        include $file;
    } else {
        echo '<div class="notice notice-error"><p>View niet gevonden: <code>' . esc_html($view_name) . '</code></p></div>';
    }
}
