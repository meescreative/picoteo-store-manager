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

    <section>
        <a href="<?php echo esc_url(admin_url('admin.php?page=psm-products&action=add')); ?>" class="button button-primary">
            ➕ Hier kan je een product toevoegen
        </a>
    </section>
</div>