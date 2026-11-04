<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

class Admin_Menu extends Base
{

    //Automatische hook registratie dankzij de Base-klasse
    public function register_hooks()
    {
        add_action('admin_menu', [$this, 'register_sidebar_menu']);
    }

    //Registreer het hoofdmenu en de submenu's
    public function register_sidebar_menu()
    {
        if (!current_user_can('edit_products') && !current_user_can('manage_options')) {
            return;
        }

        // Hoofdmenu in de WordPress sidebar
        add_menu_page(
            'Picoteo Beheer',
            'PSM',
            'manage_options',
            'picoteo-store-manager',
            function () {
?>
            <div class="wrap">
                <h1>Picoteo Store Manager</h1>
                <a href="/psm">Open de app</a>
            </div>
<?php
            },
            'dashicons-store',
            25
        );
    }
}
