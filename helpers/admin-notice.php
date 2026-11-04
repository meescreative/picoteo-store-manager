<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Universele helper om admin notices te renderen.
 * 
 * @param string $type Het type melding ('success', 'error', 'warning', 'info')
 * @param string|array $message De meldingstekst of data om weer te geven
 */

function psm_render_admin_notice($type, $message)
{
    // Als $message een array of object is, zetten we het netjes om naar leesbare tekst of print_r
    if (is_array($message) || is_object($message)) {
        $message = '<pre>' . print_r($message, true) . '</pre>';
    }

    set_transient('psm_store_notice', [
        'message' => $message,
        'type'    => $type
    ], 30);
}

// Haak in op de WordPress admin notices om de transient uit te lezen en te tonen
add_action('admin_notices', function () {
    $notice = get_transient('psm_store_notice');
    if ($notice) {
        // Map het type netjes naar WordPress CSS klassen
        $class = 'notice-success';
        if ($notice['type'] === 'error') {
            $class = 'notice-error';
        } elseif ($notice['type'] === 'warning') {
            $class = 'notice-warning';
        } elseif ($notice['type'] === 'info') {
            $class = 'notice-info';
        }

        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr($class),
            // We gebruiken wp_kses_post zodat eventuele HTML (zoals <pre> of <strong>) veilig wordt toegelaten
            wp_kses_post($notice['message'])
        );

        delete_transient('psm_store_notice');
    }
});
