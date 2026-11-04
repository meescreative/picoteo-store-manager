<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <?php
    // Laad de header partial en geef optioneel data mee
    psm_load_view('components/header', [
        'title' => get_admin_page_title(),
        'description' => "Beheer de producten voor Picoteo"
    ]);
    ?>

    <hr class="wp-header-end">

    <?php
    // Laad de widgets partial
    psm_load_view('dashboard/partials/widgets');
    ?>
</div>