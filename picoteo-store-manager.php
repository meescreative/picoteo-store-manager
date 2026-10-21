<?php

/**
 * Plugin Name: Picoteo Store Manager
 * Description: Officiële Picoteo WooCommerce Plugin.
 * Version: 1.0.0
 * Author: Jasper
 */

// Beveiliging: direct opvragen van het bestand blokkeren
if (!defined('ABSPATH')) {
    exit;
}

// Definieer constante paden voor de plugin
define('WCR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Activeer GitHub automatische updates via de Plugin Update Checker
require_once WCR_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/meescreative/picoteo-store-manager',
    __FILE__,
    'picoteo-store-manager'
);
// Stel in dat de updates vanaf de 'main' branch moeten worden gehaald
$myUpdateChecker->setBranch('main');
