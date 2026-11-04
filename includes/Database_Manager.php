<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

class Database_Manager extends Base
{

    /**
     * Hook-registratie via de Base-klasse
     */
    public function register_hooks()
    {
        // Luister naar acties vanaf het front-end dashboard (admin-post)
        add_action('admin_post_psm_install_database', [$this, 'handle_install_request']);
        add_action('admin_post_psm_reset_database', [$this, 'handle_reset_request']);
    }


    /**
     * Maakt alle 10 psm_* tabellen aan als ze nog niet bestaan
     */
    public static function install_tables()
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // SQL statements voor alle 10 V2 tabellen
        $sqls = [
            // 1. Stock Category
            "CREATE TABLE {$wpdb->prefix}psm_stock_category (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                PRIMARY KEY  (id)
            ) {$charset_collate};",

            // 2. Stock Unit
            "CREATE TABLE {$wpdb->prefix}psm_stock_unit (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                PRIMARY KEY  (id)
            ) {$charset_collate};",

            // 3. Stock Item
            "CREATE TABLE {$wpdb->prefix}psm_stock_item (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                stock_category_id BIGINT(20) UNSIGNED NOT NULL,
                stock_unit_id BIGINT(20) UNSIGNED NOT NULL,
                active TINYINT(1) DEFAULT 1 NOT NULL,
                PRIMARY KEY  (id),
                KEY stock_category_id (stock_category_id),
                KEY stock_unit_id (stock_unit_id)
            ) {$charset_collate};",

            // 4. Stock Purchase
            "CREATE TABLE {$wpdb->prefix}psm_stock_purchase (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                stock_item_id BIGINT(20) UNSIGNED NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                cost DECIMAL(10,2) NOT NULL,
                tax DECIMAL(10,2) NOT NULL,
                purchase_date DATE NOT NULL,
                PRIMARY KEY  (id),
                KEY stock_item_id (stock_item_id)
            ) {$charset_collate};",

            // 5. Pick Up Location
            "CREATE TABLE {$wpdb->prefix}psm_pick_up_location (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            address VARCHAR(255) NOT NULL,
            address_url VARCHAR(508) NOT NULL,
            PRIMARY KEY  (id)
            ) {$charset_collate};",

            // 6. Pick Up Moment
            "CREATE TABLE {$wpdb->prefix}psm_pick_up_moment (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                pick_up_location_id BIGINT(20) UNSIGNED NOT NULL,
                date DATE NOT NULL,
                start_hour TIME NOT NULL,
                end_hour TIME NOT NULL,
                PRIMARY KEY  (id),
                KEY pick_up_location_id (pick_up_location_id)
            ) {$charset_collate};",

            // 7. Order Deadline
            "CREATE TABLE {$wpdb->prefix}psm_order_deadline (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                pick_up_location_id BIGINT(20) UNSIGNED NOT NULL,
                date DATE NOT NULL,
                for_week INT(11) NOT NULL,
                hour TIME NOT NULL,
                PRIMARY KEY  (id),
                KEY pick_up_location_id (pick_up_location_id)
            ) {$charset_collate};",

            // 8. Order Status
            "CREATE TABLE {$wpdb->prefix}psm_order_status (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                PRIMARY KEY  (id)
            ) {$charset_collate};",

            // 9. Order (Brug naar WooCommerce Order)
            "CREATE TABLE {$wpdb->prefix}psm_order (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT(20) UNSIGNED NOT NULL,
                pick_up_moment_id BIGINT(20) UNSIGNED NOT NULL,
                order_status_id BIGINT(20) UNSIGNED NOT NULL,
                PRIMARY KEY  (id),
                KEY order_id (order_id),
                KEY pick_up_moment_id (pick_up_moment_id),
                KEY order_status_id (order_status_id)
            ) {$charset_collate};",

            // 10. Recipe (Brug naar WooCommerce Product/Variatie)
            "CREATE TABLE {$wpdb->prefix}psm_recipe (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id BIGINT(20) UNSIGNED NOT NULL,
                stock_item_id BIGINT(20) UNSIGNED NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                PRIMARY KEY  (id),
                KEY product_id (product_id),
                KEY stock_item_id (stock_item_id)
            ) {$charset_collate};"
        ];

        // Voer dbDelta uit voor elk SQL statement
        foreach ($sqls as $sql) {
            dbDelta($sql);
        }
    }

    /**
     * Controleert of alle 10 tabellen daadwerkelijk in de database bestaan
     * 
     * @return bool
     */
    public static function check_tables_exist()
    {
        global $wpdb;

        $required_tables = [
            'psm_stock_category',
            'psm_stock_unit',
            'psm_stock_item',
            'psm_stock_purchase',
            'psm_pick_up_location',
            'psm_pick_up_moment',
            'psm_order_deadline',
            'psm_order_status',
            'psm_order',
            'psm_recipe'
        ];

        foreach ($required_tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'") !== $full_table_name) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verwijder alle psm_* tabellen (Voor later/reset functionaliteit)
     */
    public static function drop_tables()
    {
        global $wpdb;

        $tables = [
            'psm_recipe',
            'psm_order',
            'psm_order_status',
            'psm_order_deadline',
            'psm_pick_up_moment',
            'psm_pick_up_location',
            'psm_stock_purchase',
            'psm_stock_item',
            'psm_stock_unit',
            'psm_stock_category'
        ];

        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $wpdb->query("DROP TABLE IF EXISTS {$full_table_name}");
        }
    }

    /**
     * Afhandeling van installatieverzoek
     */
    public function handle_install_request()
    {
        // 1. Check of de gebruiker wel mag inloggen/beheren
        if (!current_user_can('manage_options')) {
            wp_die('Onbevoegde actie.', 403);
        }

        // 2. CHECK DE NONCE: Dit is de beveiliging tegen externe misbruikers!
        if (!isset($_GET['psm_nonce']) || !wp_verify_nonce($_GET['psm_nonce'], 'psm_db_action_nonce')) {
            wp_die('Beveiligingscontrole mislukken (ongeldige nonce).', 403);
        }

        // 3. Als alles klopt: voer de installatie uit
        self::install_tables();

        // 4. Stuur netjes terug naar de /psm pagina
        wp_redirect(home_url('/psm/instellingen?db_installed=true'));
        exit;
    }

    /**
     * Afhandeling van reset/drop verzoek
     */
    public function handle_reset_request()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Onbevoegde actie.', 403);
        }

        if (!isset($_GET['psm_nonce']) || !wp_verify_nonce($_GET['psm_nonce'], 'psm_db_action_nonce')) {
            wp_die('Beveiligingscontrole mislukt (ongeldige nonce).', 403);
        }

        self::drop_tables();

        wp_redirect(home_url('/psm/instellingen?db_reset=true'));
        exit;
    }
}
