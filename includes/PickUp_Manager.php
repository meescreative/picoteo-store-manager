<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

class PickUp_Manager extends Base
{

    public function register_hooks()
    {
        // Registreer de Custom Post Type voor afhaalmomenten op init
        add_action('init', [$this, 'register_psm_pickup_moment']);

        // AJAX-handlers voor het toevoegen (via Breakdance formulier) en verwijderen van momenten
        add_action('wp_ajax_delete_psm_pickup_moment', [$this, 'handle_delete_psm_pickup_moment']);
    }

    /**
     * Registreer de Custom Post Type 'psm_pickup_moment' voor Breakdance
     */
    public function register_psm_pickup_moment()
    {
        register_post_type('psm_pickup_moment', [
            'labels' => [
                'name'               => __('Afhaalmomenten', 'picoteo-store-manager'),
                'singular_name'      => __('Afhaalmoment', 'picoteo-store-manager'),
                'add_new'            => __('Nieuw moment toevoegen', 'picoteo-store-manager'),
                'add_new_item'       => __('Nieuw afhaalmoment toevoegen', 'picoteo-store-manager'),
                'edit_item'          => __('Bewerk afhaalmoment', 'picoteo-store-manager'),
                'new_item'           => __('Nieuw afhaalmoment', 'picoteo-store-manager'),
                'search_items'       => __('Zoek afhaalmomenten', 'picoteo-store-manager'),
                'not_found'          => __('Geen afhaalmomenten gevonden', 'picoteo-store-manager'),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'picoteo-store-manager', // Optioneel: zet ze mooi onder de afhaallocaties in het menu!
            'supports'           => ['title'], // Titel kan bijvoorbeeld de datum + tijd of een omschrijving zijn
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'has_archive'        => false,
            'rewrite'            => false,
        ]);
    }

    /**
     * Voeg een nieuw afhaalmoment toe als WordPress Post + Meta relatie
     * 
     * @param int $location_id
     * @param string $date (YYYY-MM-DD)
     * @param string $start_hour (HH:MM:SS of HH:MM)
     * @param string $end_hour (HH:MM:SS of HH:MM)
     * @return int|false Post ID of false bij falen
     */
    public static function add_pick_up_moment($location_id, $date, $start_hour, $end_hour)
    {
        $clean_location_id = intval($location_id);
        $clean_date        = sanitize_text_field($date);
        $clean_start       = sanitize_text_field($start_hour);
        $clean_end         = sanitize_text_field($end_hour);

        if (!$clean_location_id || empty($clean_date) || empty($clean_start) || empty($clean_end)) {
            return false;
        }

        // Valideer of de gekoppelde afhaallocatie CPT wel bestaat
        if (get_post_type($clean_location_id) !== 'psm_pickup_location') {
            return false;
        }

        // Optioneel: Genereer automatisch een duidelijke titel voor de CPT post
        $location_title = get_the_title($clean_location_id);
        $post_title     = sprintf('%s (%s: %s - %s)', $location_title, $clean_date, $clean_start, $clean_end);

        // Maak de WordPress Post aan
        $post_data = [
            'post_title'  => $post_title,
            'post_status' => 'publish',
            'post_type'   => 'psm_pickup_moment',
        ];

        $post_id = wp_insert_post($post_data);

        if ($post_id && !is_wp_error($post_id)) {
            // Sla de gerelateerde ID en tijdsspecificaties op als post meta
            update_post_meta($post_id, 'pickup_location_id', $clean_location_id);
            update_post_meta($post_id, 'date', $clean_date);
            update_post_meta($post_id, 'start_hour', $clean_start);
            update_post_meta($post_id, 'end_hour', $clean_end);

            return $post_id;
        }

        return false;
    }

    /**
     * Haal alle afhaalmomenten op (optioneel gefilterd op locatie via WP_Query)
     * 
     * @param int|null $location_id
     * @return WP_Post[] Array van post objecten voor de Loop
     */
    public static function get_pick_up_moments($location_id = null)
    {
        $args = [
            'post_type'      => 'psm_pickup_moment',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value',
            'meta_key'       => 'date',
            'order'          => 'ASC',
        ];

        if ($location_id) {
            $args['meta_query'] = [
                [
                    'key'     => 'pickup_location_id',
                    'value'   => intval($location_id),
                    'compare' => '=',
                ],
            ];
        }

        $query = new \WP_Query($args);
        return $query->posts;
    }

    /**
     * Beveiligde AJAX-handler voor het verwijderen van een afhaalmoment
     */
    public function handle_delete_psm_pickup_moment()
    {
        // CSRF & rechtencontrole
        if (!check_ajax_referer('psm_pickup_moment_delete_nonce', 'security_nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Geen toestemming om deze actie uit te voeren.'], 403);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id || get_post_type($post_id) !== 'psm_pickup_moment') {
            wp_send_json_error(['message' => 'Afhaalmoment bestaat niet.'], 400);
        }

        $deleted = wp_delete_post($post_id, true);

        if ($deleted) {
            wp_send_json_success(['message' => 'Afhaalmoment succesvol verwijderd!']);
        }

        wp_send_json_error(['message' => 'Verwijderen is mislukt.'], 500);
    }

    /**
     * AJAX-handler voor het toevoegen via een Breakdance formulier
     * @param array $fields
     */
    public static function process_form_submission($fields)
    {

        $csrf_token = isset($fields['csrfToken']) ? sanitize_text_field($fields['csrfToken']) : '';
        if (!current_user_can('manage_options') || empty($csrf_token)) {
            wp_send_json_error(['message' => 'Geen toestemming.'], 403);
        }

        $location_id = isset($fields['pickup_location_id']) ? $fields['pickup_location_id'] : 0;
        $date        = isset($fields['pickup_date']) ? $fields['pickup_date'] : '';
        $start_hour  = isset($fields['pickup_start_hour']) ? $fields['pickup_start_hour'] : '';
        $end_hour    = isset($fields['pickup_end_hour']) ? $fields['pickup_end_hour'] : '';

        if (empty($location_id) || empty($date) || empty($start_hour) || empty($end_hour)) {
            wp_send_json_error(['message' => 'Niet alle verplichte velden zijn ingevuld.'], 400);
        }

        $post_id = self::add_pick_up_moment($location_id, $date, $start_hour, $end_hour);

        if ($post_id) {
            wp_send_json_success([
                'post_id' => $post_id,
                'message' => 'Afhaalmoment succesvol toegevoegd!'
            ]);
        }

        wp_send_json_error(['message' => 'Er ging iets mis bij het aanmaken.'], 500);
    }
}
