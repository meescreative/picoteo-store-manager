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

register_activation_hook(__FILE__, [\Picoteo\StoreManager\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
