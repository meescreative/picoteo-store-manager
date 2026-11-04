<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div style="display: flex; gap: 20px; margin-top: 20px;">
    <!-- Widget 1: Recepten -->
    <div class="card" style="flex: 1; max-width: 400px; padding: 20px;">
        <h3>🍲 Recepten & Producten</h3>
        <p>Beheer al je gerechten, portie-variaties en automatische voorraden.</p>
        <p>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>" class="button button-primary">Alle Recepten</a>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" class="button">Nieuw Recept</a>
        </p>
    </div>

    <!-- Widget 2: Ingrediënten -->
    <div class="card" style="flex: 1; max-width: 400px; padding: 20px;">
        <h3>🥕 Ingrediënten CPT</h3>
        <p>Beheer je basisingrediënten, eenheden (gram/stuks) en actuele voorraad.</p>
        <p>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=ingredient')); ?>" class="button button-primary">Beheer Ingrediënten</a>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=ingredient')); ?>" class="button">Nieuw Ingrediënt</a>
        </p>
    </div>
</div>