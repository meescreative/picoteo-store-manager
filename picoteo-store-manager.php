<?php

/**
 * Plugin Name: Picoteo Store Manager
 * Plugin URI: https://github.com/meescreative/picoteo-store-manager
 * Description: Officiële Picoteo WooCommerce Plugin.
 * Version: 1.0.3
 * Author: Jasper
 */

// Beveiliging: direct opvragen van het bestand blokkeren
if (!defined('ABSPATH')) {
    exit;
}


// 1. Laad Composer autoloader
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// 2. Start de bootstrap
require_once plugin_dir_path(__FILE__) . 'bootstrap/app.php';


/* oude code hieronder


// Definieer constante paden voor de plugin
define('PSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PSM_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', function () {
    // Laad automatisch alle helper bestanden
    foreach (glob(PSM_PLUGIN_DIR . 'helpers/*.php') as $helper_file) {
        require_once $helper_file;
    }

    // Check of WooCommerce klasse/plugin actief is
    if (!class_exists('WooCommerce')) {
        psm_render_admin_notice(
            'error',
            '<strong>Picoteo Store Manager:</strong> Deze plugin vereist dat WooCommerce is geïnstalleerd en geactiveerd.'
        );
        return;
    }

    // Laad automatisch alle helper bestanden
    foreach (glob(PSM_PLUGIN_DIR . 'includes/*.php') as $include_file) {
        require_once $include_file;
    }
});

// Activeer GitHub automatische updates via de Plugin Update Checker
require_once PSM_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
$psmUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/meescreative/picoteo-store-manager',
    __FILE__,
    'picoteo-store-manager'
);
// Stel in dat de updates vanaf de 'main' branch moeten worden gehaald
$psmUpdateChecker->setBranch('main');

*/
