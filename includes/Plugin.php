<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    public static function run()
    {
        // 1. Start centrale admin componenten
        if (class_exists(__NAMESPACE__ . '\\Admin_Menu')) {
            new Admin_Menu();
        }

        if (class_exists(__NAMESPACE__ . '\\Security_Guard')) {
            new Security_Guard();
        }

        if (class_exists(__NAMESPACE__ . '\\Setup_Manager')) {
            new Setup_Manager();
        }

        if (class_exists(__NAMESPACE__ . '\\Database_Manager')) {
            new Database_Manager();
        }

        if (class_exists(__NAMESPACE__ . '\\Location_Manager')) {
            new Location_Manager();
        }

        if (class_exists(__NAMESPACE__ . '\\PickUp_Manager')) {
            new PickUp_Manager();
        }

        if (class_exists(__NAMESPACE__ . '\\Form_Manager')) {
            new Form_Manager();
        }

        // 2. Initialiseer modules automatisch
        self::init_modules();

        // 3. Registreer globale frontend assets (zoals JS)
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_global_assets']);
    }

    private static function init_modules()
    {
        // Lijst van actieve module hoofdklassen
        $modules = [
            \Picoteo\StoreManager\Modules\Stock\Stock_Engine::class,
            \Picoteo\StoreManager\Modules\Recipes\Recipes_Engine::class,
        ];

        foreach ($modules as $module) {
            if (class_exists($module)) {
                new $module();
            }
        }
    }

    public static function enqueue_global_assets()
    {
        wp_enqueue_script(
            'psm-refresh-dom',
            plugins_url('../assets/js/psm-refresh-dom.js', __FILE__),
            [],
            '1.0.0',
            true
        );
        wp_enqueue_script(
            'psm-delete-btn',
            plugins_url('../assets/js/psm-delete-btn.js', __FILE__),
            [],
            '1.0.0',
            true
        );
    }
}
