<?php
if (!defined('ABSPATH')) {
    exit;
}
add_action('init', 'psm_register_ingredient_cpt');

function psm_register_ingredient_cpt()
{
    // Alleen registreren als de setup is doorgelopen
    if (!get_option('psm_ingredient_cpt_active')) {
        return;
    }

    $args = [
        'labels' => [
            'name' => 'Ingrediënten',
            'singular_name' => 'Ingrediënt',
            'add_new' => 'Nieuw Ingrediënt',
            'add_new_item' => 'Nieuw Ingrediënt toevoegen',
            'edit_item' => 'Ingrediënt bewerken',
            'new_item' => 'Nieuw Ingrediënt',
            'view_item' => 'Bekijk Ingrediënt',
            'search_items' => 'Zoek Ingrediënten',
            'not_found' => 'Geen ingrediënten gevonden',
            'not_found_in_trash' => 'Geen ingrediënten gevonden in prullenbak',
        ],
        'public' => false, // Niet op de publieke frontend nodig, puur voor admin/beheer
        'show_ui' => true, // Zichtbaar in admin menu
        'show_in_menu' => true,
        'supports' => ['title', 'thumbnail'], // ⚠️ HIER SCHAKEL JE 'editor' (post_content) UIT! Alleen de titel blijft over.
        'has_archive' => false,
        'hierarchical' => false,
        'menu_icon' => 'dashicons-carrot',
    ];

    register_post_type('ingredient', $args);
}

// Deze hook moet ALTIJD geladen worden, ook in de achtergrond!
add_action('admin_post_psm_run_ingredient_setup', 'psm_handle_run_ingredient_setup');

function psm_handle_run_ingredient_setup()
{
    // Beveiligingschecks
    if (!isset($_POST['psm_setup_nonce']) || !wp_verify_nonce($_POST['psm_setup_nonce'], 'psm_setup_cpt_nonce')) {
        wp_die('Beveiligingscontrole mislukt.');
    }

    if (!current_user_can('manage_options')) {
        wp_die('Onvoldoende rechten.');
    }

    // 1. Activeer de CPT in de database
    update_option('psm_ingredient_cpt_active', true);

    // 2. 🔑 CRUCIAAL: Ververs direct de WordPress rewrite rules zodat het CPT en diens URL's actief worden!
    flush_rewrite_rules();

    // 3. Stuur terug naar de setup pagina
    wp_redirect(admin_url('admin.php?page=psm-setup&setup=success'));
    exit;
}


// Voeg de Meta Box toe aan het CPT 'ingredient'
add_action('add_meta_boxes', 'psm_add_ingredient_meta_box');
function psm_add_ingredient_meta_box()
{
    add_meta_box(
        'psm_ingredient_details',           // Unieke ID
        'Stock',          // Titel van het blok
        'psm_render_ingredient_meta_box',    // Callback functie voor de HTML
        'ingredient',                        // Post type
        'normal',                            // Positie (normal = onder de titel)
        'high'                               // Prioriteit
    );
}

// Render de HTML in de Meta Box
function psm_render_ingredient_meta_box($post)
{
    // Beveiligingsnonce zodat we weten dat het formulier van onze admin komt
    wp_nonce_field('psm_save_ingredient_meta', 'psm_ingredient_nonce');

    // Haal eventuele bestaande waardes op uit de database
    $in_stock = get_post_meta($post->ID, 'in_stock', true);
    $unit     = get_post_meta($post->ID, 'unit', true);
    $type     = get_post_meta($post->ID, 'type', true);
    $purchase = get_post_meta($post->ID, 'purchase', true);
    $sell     = get_post_meta($post->ID, 'sell', true);

    // Standaardwaarde voor eenheid als er nog niets is opgeslagen
    if (empty($unit)) {
        $unit = 'gram';
    }

    // Standaardwaarde voor type als er nog niets is opgeslagen
    if (empty($type)) {
        $type = 'kaas';
    }


?>
    <div style="margin-bottom: 15px;">
        <label for="in_stock" style="display: block; font-weight: 600; margin-bottom: 5px;">Hoeveelheid:</label>
        <input type="number" id="in_stock" name="in_stock" value="<?php echo esc_attr($in_stock); ?>" step="1" min="0" style="width: 100%; padding: 6px;" placeholder="Bijv. 500" />
    </div>

    <div style="margin-bottom: 15px;">
        <label for="unit" style="display: block; font-weight: 600; margin-bottom: 5px;">Eenheid:</label>
        <select id="unit" name="unit" style="width: 100%; padding: 6px;">
            <option value="gram" <?php selected($unit, 'gram'); ?>>Gram</option>
            <option value="stuks" <?php selected($unit, 'stuks'); ?>>Stuks</option>
        </select>
    </div>

    <div style="margin-bottom: 15px;">
        <label for="type" style="display: block; font-weight: 600; margin-bottom: 5px;">Soort:</label>
        <select id="type" name="type" style="width: 100%; padding: 6px;">
            <option value="kaas" <?php selected($type, 'kaas'); ?>>Kaas</option>
            <option value="charcuterie" <?php selected($type, 'charcuterie'); ?>>Charcuterie</option>
            <option value="cracker" <?php selected($type, 'cracker'); ?>>Cracker</option>
            <option value="spread" <?php selected($type, 'spread'); ?>>Spread</option>
            <option value="fruit" <?php selected($type, 'fruit'); ?>>Fruit</option>
            <option value="snack" <?php selected($type, 'snack'); ?>>Snack</option>
            <option value="topping" <?php selected($type, 'topping'); ?>>Topping</option>
            <option value="drank" <?php selected($type, 'drank'); ?>>Drank</option>
            <option value="verpakking" <?php selected($type, 'verpakking'); ?>>Verpakking</option>
        </select>
    </div>

    <div style="margin-bottom: 15px;">
        <label for="purchase" style="display: block; font-weight: 600; margin-bottom: 5px;">Aankoopprijs:</label>
        <input type="number" id="purchase" name="purchase" value="<?php echo esc_attr($purchase); ?>" step="0.01" min="0" style="width: 100%; padding: 6px;" placeholder="Bijv. 500" />
    </div>

    <div style="margin-bottom: 15px;">
        <label for="sell" style="display: block; font-weight: 600; margin-bottom: 5px;">Verkoopprijs:</label>
        <input type="number" id="sell" name="sell" value="<?php echo esc_attr($sell); ?>" step="0.01" min="0" style="width: 100%; padding: 6px;" placeholder="Bijv. 500" />
    </div>
<?php
}

// Sla de data op wanneer het ingrediënt wordt opgeslagen
add_action('save_post_ingredient', 'psm_save_ingredient_meta');
function psm_save_ingredient_meta($post_id)
{
    // Check nonce (beveiliging)
    if (!isset($_POST['psm_ingredient_nonce']) || !wp_verify_nonce($_POST['psm_ingredient_nonce'], 'psm_save_ingredient_meta')) {
        return;
    }

    // Negeer autosaves en revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check rechten van de gebruiker
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Sla 'in_stock' op (als getal opgeslagen)
    if (isset($_POST['in_stock'])) {
        update_post_meta($post_id, 'in_stock', sanitize_text_field($_POST['in_stock']));
    }

    // Sla 'purchase' op (als getal opgeslagen)
    if (isset($_POST['purchase'])) {
        update_post_meta($post_id, 'purchase', sanitize_text_field($_POST['purchase']));
    }

    // Sla 'sell' op (als getal opgeslagen)
    if (isset($_POST['sell'])) {
        update_post_meta($post_id, 'sell', sanitize_text_field($_POST['sell']));
    }

    // Sla 'unit' op (valideer of het 'gram' of 'stuks' is)
    if (isset($_POST['unit'])) {
        $clean_unit = sanitize_text_field($_POST['unit']);
        if (in_array($clean_unit, ['gram', 'stuks'], true)) {
            update_post_meta($post_id, 'unit', $clean_unit);
        }
    }

    // Sla 'type' op (valideer of het correcte waarde is)
    if (isset($_POST['type'])) {
        $clean_type = sanitize_text_field($_POST['type']);
        if (in_array($clean_type, [
            'kaas',
            'charcuterie',
            'cracker',
            'spread',
            'fruit',
            'verpakking',
            'drank',
            'snack',
            'topping',
        ], true)) {
            update_post_meta($post_id, 'type', $clean_type);
        }
    }
}
