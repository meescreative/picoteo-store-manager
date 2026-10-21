<?php

/**
 * Plugin Name: Picoteo Store Manager
 * Plugin URI: https://github.com/meescreative/picoteo-store-manager
 * Description: Officiële Picoteo WooCommerce Plugin.
 * Version: 1.0.1
 * Author: Jasper
 */

// Beveiliging: direct opvragen van het bestand blokkeren
if (!defined('ABSPATH')) {
    exit;
}

// Definieer constante paden voor de plugin
define('PSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PSM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Laad automatisch alle helper bestanden
foreach (glob(PSM_PLUGIN_DIR . 'helpers/*.php') as $helper_file) {
    require_once $helper_file;
}

// Laad de core functionaliteiten uit includes
require_once PSM_PLUGIN_DIR . 'includes/class-admin-menu.php';

// Activeer GitHub automatische updates via de Plugin Update Checker
require_once PSM_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
$psmUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/meescreative/picoteo-store-manager',
    __FILE__,
    'picoteo-store-manager'
);
// Stel in dat de updates vanaf de 'main' branch moeten worden gehaald
$psmUpdateChecker->setBranch('main');
