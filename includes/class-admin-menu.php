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
        if (!current_user_can('edit_products')) {
            return;
        }

        add_menu_page(
            'Picoteo Beheer',
            'Picoteo Store',
            'edit_products',
            'psm-store-manager',
            [$this, 'render_dashboard_page'],
            'dashicons-carrot',
            25
        );

        add_submenu_page('psm-store-manager', 'Alle Recepten', 'Alle Recepten', 'edit_products', 'edit.php?post_type=product');
        add_submenu_page('psm-store-manager', 'Nieuw Recept', 'Nieuw Recept Toevoegen', 'edit_products', 'post-new.php?post_type=product');
        add_submenu_page('psm-store-manager', 'Ingrediënten', 'Ingrediënten Beheren', 'edit_products', 'edit.php?post_type=ingredient');
    }

    public function render_dashboard_page()
    {
?>
        <div class="wrap">
            <h1>Picoteo Store Manager</h1>
            <p>Centraal beheersysteem voor je recepten, variaties en voorraden.</p>
        </div>
<?php
    }
}

new PSM_Admin_Menu();
