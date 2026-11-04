<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

class Security_Guard extends Base
{

    /**
     * Automatische hook registratie via de Base-klasse
     */
    public function register_hooks()
    {
        add_action('template_redirect', [$this, 'protect_psm_page']);
    }

    /**
     * Scherm de /psm pagina af voor niet-beheerders
     */
    public function protect_psm_page()
    {
        // Vraag het huidige URL-pad op (bijv. /psm of /psm/productenbeheer)
        $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        // Check of het pad 'psm' is, of begint met 'psm/'
        if ($current_path === 'psm' || strpos($current_path, 'psm/') === 0) {

            // Als de gebruiker niet is ingelogd, of geen beheerder (manage_options) is:
            if (!is_user_logged_in() || !current_user_can('manage_options')) {

                // Stuur ze direct door naar de loginpagina
                wp_redirect(wp_login_url(home_url($_SERVER['REQUEST_URI'])));
                exit;
            }
        }
    }
}
