<?php
if (!defined('ABSPATH')) {
    exit;
}

$product_id = intval(get_query_var('product_id'));
$product    = wc_get_product($product_id);

if (!$product) {
    wp_die('Dit product kon niet worden gevonden.');
}

// Zorg ervoor dat WordPress weet dat dit een geldige pagina is en Breakdance triggert
status_header(200);

// Roep de get_header aan zodat Breakdance zijn scripts en stijlen kan inladen
get_header();
?>

<main id="primary" class="site-main psm-breakdance-wrapper" data-product-id="<?php echo esc_attr($product_id); ?>">
    <?php
    /**
     * Dit zorgt ervoor dat Breakdance de content/template injectert 
     * die aan de condition voldoet.
     */
    if (function_exists('breakpress_render_template') || class_exists('\Breakdance\Core')) {
        // Breakdance rendering fallback indien nodig, 
        // maar meestal pakt Breakdance de 'singular' of 'custom template' automatisch op via the_content of de main loop.
    }
    ?>

    <!-- Hier kun je eventueel een Breakdance shortcode of de content placeholder neerzetten -->
    <div class="breakdance-template-outlet">
        <?php
        // Als je in Breakdance een sectie hebt gebouwd, kun je hier ook content tonen
        // of de template zijn werk laten doen.
        ?>
    </div>
</main>

<?php
get_footer();
