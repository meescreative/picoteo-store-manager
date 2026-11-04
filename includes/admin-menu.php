<?php
if (!defined('ABSPATH')) {
    exit;
}

class PSM_Admin_Menu
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_sidebar_menu']);
    }

    public function register_sidebar_menu()
    {
        if (!current_user_can('edit_products') && !current_user_can('manage_options')) {
            return;
        }

        add_menu_page(
            'Picoteo Store Manager',
            'Picoteo Store Manager',
            'manage_options',
            'psm-store-manager',
            function () {
                psm_load_view('dashboard');
            },
            'dashicons-carrot',
            25
        );

        add_submenu_page(
            'psm-store-manager',
            'Producten',
            'Producten',
            'manage_options',
            'psm-producten',
            function () {
                // Vraag de 'action' op uit de URL (standaard is 'index')
                $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'index';

                if ($action === 'add') {
                    // Laad de view voor toevoegen: views/products/add.php
                    psm_load_view('products/add/index.php');
                } else {
                    // Laad de hoofdpagina: views/products/index.php
                    psm_load_view('products');
                }
            }
        );

        add_submenu_page(
            'psm-store-manager',
            'Picoteo Setup',
            'Picoteo Setup',
            'manage_options',
            'psm-setup',
            function () {
                psm_load_view('setup');
            }
        );
    }
}

new PSM_Admin_Menu();
