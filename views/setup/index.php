<?php
if (!defined('ABSPATH')) {
    exit;
}

// Laad de logica-file in die in dezelfde map staat
require_once __DIR__ . '/setup-logic.php';
$status = psm_check_ingredient_cpt_status();

?>

<div class="wrap">
    <?php
    // Laad de header partial en geef optioneel data mee
    psm_load_view('components/header', [
        'title' => get_admin_page_title(),
        'description' => "Setup voor Picoteo Webshop"
    ]);
    ?>

    <hr class="wp-header-end">

    <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
        <h2>
            1. Custom CPT 'Ingrediënten'
            <?php if ($status['is_valid']): ?>
                <span style="color: #46b450; float: right;">✅ In orde</span>
            <?php else: ?>
                <span style="color: #dc3232; float: right;">⛔️ Actie vereist</span>
            <?php endif; ?>
        </h2>

        <p>Dit Custom Post Type beheert alle basisingrediënten die gekoppeld worden aan je recepten.</p>

        <table class="widefat striped" style="margin-top: 15px; margin-bottom: 20px;">
            <thead>
                <tr>
                    <th>Onderdeel</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Custom Post Type <code>'ingredient'</code> geregistreerd</td>
                    <td><?php echo $status['exists'] ? '✅ Aanwezig' : '❌ Niet gevonden'; ?></td>
                </tr>
                <tr>
                    <td>Standaard WordPress Editor uitgeschakeld (schiedt vervuiling)</td>
                    <td><?php echo $status['editor_disabled'] ? '✅ Uitgeschakeld' : '❌ Editor is nog actief'; ?></td>
                </tr>
                <tr>
                    <td>ACF Velden (zoals <code>unit</code> en <code>quantity</code>) gekoppeld</td>
                    <td><?php echo $status['fields_present'] ? '✅ Gekoppeld' : '❌ Velden ontbreken'; ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Undo / Verwijder Formulier met JS Warning -->
        <?php if ($status['is_valid']): ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('⚠️ OPGELET: Weet je zeker dat je de ingrediënten-configuratie wilt terugdraaien/verwijderen? Dit kan je recepten-structuur breken.');">
                <input type="hidden" name="action" value="psm_reset_ingredient_cpt">
                <?php wp_nonce_field('psm_reset_cpt_nonce', 'psm_reset_nonce'); ?>

                <button type="submit" class="button button-link-delete" style="color: #a00;">
                    🗑️ Undo / CPT Configuratie Ongedaan Maken
                </button>
            </form>
        <?php endif; ?>

        <!-- Maakt custom post type aan -->
        <?php if (!$status['is_valid']): ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 15px;">
                <input type="hidden" name="action" value="psm_run_ingredient_setup">
                <?php wp_nonce_field('psm_setup_cpt_nonce', 'psm_setup_nonce'); ?>

                <button type="submit" class="button button-primary">
                    🚀 CPT Ingrediënten Nu Automatisch Instellen
                </button>
            </form>
        <?php endif; ?>

    </div>
</div>