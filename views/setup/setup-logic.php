<?php
if (!defined('ABSPATH')) {
    exit;
}

// Functie die kijkt op CPT 'ingredient' bestaat in backend
function psm_check_ingredient_cpt_status()
{
    $slug = 'ingredient';

    // 1. Bestaat de CPT?
    $exists = post_type_exists($slug);

    // 2. Is de editor (post_content) uitgeschakeld?
    $editor_disabled = $exists && !post_type_supports($slug, 'editor');

    // 3. Zijn de ACF velden aanwezig? (Check via ACF field groups gekoppeld aan CPT 'ingredient')
    $fields_present = false;
    if ($exists && function_exists('acf_get_field_groups')) {
        $field_groups = acf_get_field_groups(['post_type' => $slug]);
        if (!empty($field_groups)) {
            $fields_present = true;
        }
    }

    // Alles in orde?
    $is_valid = ($exists && $editor_disabled && $fields_present);

    return [
        'exists'          => $exists,
        'editor_disabled' => $editor_disabled,
        'fields_present'  => $fields_present,
        'is_valid'        => $is_valid,
    ];
}

// Check status voor CPT ingredient

add_action('admin_post_psm_reset_ingredient_cpt', 'psm_handle_reset_ingredient_cpt');

function psm_handle_reset_ingredient_cpt()
{
    // 1. Veiligheidschecks (Nonce & rechten)
    if (!isset($_POST['psm_reset_nonce']) || !wp_verify_nonce($_POST['psm_reset_nonce'], 'psm_reset_cpt_nonce')) {
        wp_die('Beveiligingscontrole mislukt.');
    }

    if (!current_user_can('manage_options')) {
        wp_die('Onvoldoende rechten.');
    }

    // 2. Voer de reset-actie uit (bijv. optie uitzetten of database opschonen)
    // Bijvoorbeeld: update_option('psm_enable_ingredient_cpt', false);

    // 3. Stuur de gebruiker terug naar de setup pagina met een melding
    wp_redirect(admin_url('admin.php?page=psm-setup&reset=success'));
    exit;
}
