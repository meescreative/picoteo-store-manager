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

        if (class_exists(__NAMESPACE__ . '\\Deadline_Manager')) {
            new Deadline_Manager();
        }

        if (class_exists(__NAMESPACE__ . '\\Stock_Manager')) {
            new Stock_Manager();
        }

        if (class_exists(__NAMESPACE__ . '\\Product_Manager')) {
            new Product_Manager();
        }

        if (class_exists(__NAMESPACE__ . '\\Form_Manager')) {
            new Form_Manager();
        }

        // 2. Initialiseer modules automatisch
        self::init_modules();

        // 3. Registreer globale frontend assets (zoals JS)
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_global_assets']);
    }

    public static function activate()
    {
        // Zorg dat de rewrite rules van Product_Manager direct worden geregistreerd
        if (class_exists(Product_Manager::class)) {
            Product_Manager::add_custom_rewrite_rules();
        }

        // Flush de regels in de database
        flush_rewrite_rules();
    }

    private static function init_modules()
    {
        // Lijst van actieve module hoofdklassen
        $modules = [
            //\Picoteo\StoreManager\Modules\Date_Helper::class,
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
        wp_enqueue_script(
            'psm-set-deadline',
            plugins_url('../assets/js/psm-set-deadline.js', __FILE__),
            [],
            '1.0.0',
            true
        );
        wp_enqueue_script(
            'psm-set-product-info',
            plugins_url('../assets/js/psm-set-product-info.js', __FILE__),
            [],
            '1.0.0',
            true
        );
        wp_enqueue_script(
            'psm-set-variation-info',
            plugins_url('../assets/js/psm-set-variation-info.js', __FILE__),
            [],
            '1.0.0',
            true
        );
    }
}
