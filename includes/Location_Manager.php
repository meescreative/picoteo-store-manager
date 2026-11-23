<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

class Location_Manager extends Base
{

    public function register_hooks()
    {
        // Registreer de Custom Post Type standaard op init voor Breakdance
        add_action('init', [$this, 'register_psm_pickup_location']);

        // Voeg een custom rewrite rule toe zodat /psm/instellingen/{ID}/ werkt
        add_action('init', [$this, 'add_custom_rewrite_rules']);

        // Filter om de %psm_pickup_location_id% tag te vervangen door het echte Post ID in de permalinks
        add_filter('post_type_link', [$this, 'filter_pickup_location_permalink'], 10, 2);

        // Luister naar verwijderen record
        add_action('wp_ajax_delete_psm_pickup_location', [$this, 'handle_delete_psm_pickup_location']);
    }

    /**
     * Registreer de Custom Post Type 'psm_pickup_location' voor Breakdance
     */
    public function register_psm_pickup_location()
    {
        register_post_type('psm_pickup_location', [
            'labels' => [
                'name'               => __('Afhaallocaties', 'picoteo-store-manager'),
                'singular_name'      => __('Afhaallocatie', 'picoteo-store-manager'),
                'add_new'            => __('Nieuwe toevoegen', 'picoteo-store-manager'),
                'add_new_item'       => __('Nieuwe afhaallocatie toevoegen', 'picoteo-store-manager'),
                'edit_item'          => __('Bewerk afhaallocatie', 'picoteo-store-manager'),
                'new_item'           => __('Nieuwe afhaallocatie', 'picoteo-store-manager'),
                'search_items'       => __('Zoek afhaallocaties', 'picoteo-store-manager'),
                'not_found'          => __('Geen afhaallocaties gevonden', 'picoteo-store-manager'),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => 'picoteo-store-manager',
            'show_in_rest'       => true,
            'supports'           => ['title'], // Titel fungeert als 'name'
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'has_archive'        => false,
            'rewrite'            => [
                'slug'       => 'psm/instellingen',
                'with_front' => false,
                'pages'      => false,
                'feed'       => false,
            ],
        ]);
    }

    public function add_custom_rewrite_rules()
    {
        // Vang /psm/instellingen/{ID}/ af en mapt dit naar de psm_pickup_location CPT op basis van post_id
        add_rewrite_rule(
            '^psm/instellingen/([0-9]+)/?$',
            'index.php?post_type=psm_pickup_location&p=$matches[1]',
            'top'
        );
    }

    /**
     * Vervang %psm_pickup_location_id% in de permalink door het daadwerkelijke Post ID
     *
     * @param string   $post_link De gegenereerde URL
     * @param \WP_Post $post      Het huidige post object
     * @return string
     */
    public function filter_pickup_location_permalink($post_link, $post)
    {
        if ($post->post_type === 'psm_pickup_location') {
            // Forceer de URL naar /psm/instellingen/{ID}/
            return home_url('/psm/instellingen/' . $post->ID . '/');
        }

        return $post_link;
    }

    /**
     * Haal alle afhaallocaties op via WordPress Posts (voor Breakdance CPT queries)
     * 
     * @return array
     */
    public static function get_locations()
    {
        $posts = get_posts([
            'post_type'      => 'psm_pickup_location',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $locations = [];
        foreach ($posts as $post) {
            $locations[] = [
                'id'          => $post->ID,
                'name'        => $post->post_title,
                'address'     => get_post_meta($post->ID, 'address', true),
                'address_url' => get_post_meta($post->ID, 'address_url', true),
            ];
        }

        return $locations;
    }

    /**
     * Beveiligde AJAX-handler voor het verwijderen van een afhaallocatie
     */
    public function handle_delete_psm_pickup_location()
    {

        // CRSF & rechtencontrole: Alleen admins mogen locaties verwijderen via correct formulier
        if (!check_ajax_referer('psm_pickuck_location_delete_nonce', 'security_nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Geen toestemming om deze actie uit te voeren.'], 403);
        }

        // Haal het Post ID op en valideer dit
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id || get_post_type($post_id) !== 'psm_pickup_location') {
            wp_send_json_error(['message' => 'Afhaallocatie bestaat niet.'], 400);
        }

        // Verwijder de post (true = definitief verwijderen, false = naar prullenbak)
        $deleted = wp_delete_post($post_id, true);

        if ($deleted) {
            wp_send_json_success(['message' => 'Afhaallocatie succesvol verwijderd!']);
        }

        wp_send_json_error(['message' => 'Verwijderen uit de database is mislukt.'], 500);
    }

    /**
     * Voeg een nieuwe afhaallocatie toe als WordPress Post + Meta
     * 
     * @param string $name
     * @param string $address
     * @param string $address_url
     * @return int|false Post ID of false bij falen
     */
    public static function add_psm_pickup_location($name, $address, $address_url)
    {
        // 1. Valideer en snijd af op de maximale databaselengtes
        $clean_name        = mb_substr(sanitize_text_field($name), 0, 255);
        $clean_address     = mb_substr(sanitize_text_field($address), 0, 255);
        $clean_address_url = mb_substr(esc_url_raw($address_url), 0, 508);

        if (empty($clean_name) || empty($clean_address) || empty($clean_address_url)) {
            return false;
        }

        // 2. Maak de WordPress Post aan voor de Breakdance Loop Builder
        $post_data = [
            'post_title'  => $clean_name,
            'post_status' => 'publish',
            'post_type'   => 'psm_pickup_location',
        ];

        $post_id = wp_insert_post($post_data, true);

        if ($post_id && !is_wp_error($post_id)) {
            // 3. Sla de meta-velden op met exact de gevraagde veldnamen
            update_post_meta($post_id, 'address', $clean_address);
            update_post_meta($post_id, 'address_url', $clean_address_url);

            return $post_id;
        }

        return false;
    }

    /**
     * @param array $fields
     */
    public static function process_form_submission($fields)
    {

        // Rechten & CSRF check
        $csrf_token = isset($fields['csrfToken']) ? sanitize_text_field($fields['csrfToken']) : '';
        if (!current_user_can('manage_options') || empty($csrf_token)) {
            wp_send_json_error(['message' => 'Geen toestemming.'], 403);
        }

        // Haal de formuliervelden op uit $_POST
        $name        = isset($fields['location_name']) ? $fields['location_name'] : '';
        $address     = isset($fields['location_address']) ? $fields['location_address'] : '';
        $address_url = isset($fields['location_address_url']) ? $fields['location_address_url'] : '';

        if (empty($name) || empty($address) || empty($address_url)) {
            wp_send_json_error(['message' => 'Niet alle verplichte velden zijn ingevuld.'], 400);
        }

        try {
            $post_id = self::add_psm_pickup_location($name, $address, $address_url);

            if ($post_id && !is_wp_error($post_id) && get_post_type($post_id) === 'psm_pickup_location') {
                wp_send_json_success([
                    'post_id' => $post_id,
                    'message' => 'Locatie succesvol toegevoegd!'
                ]);
            } else {
                $error_detail = is_wp_error($post_id) ? $post_id->get_error_message() : 'wp_insert_post gaf false terug.';
                wp_send_json_error(['message' => 'Fout bij aanmaken CPT: ' . $error_detail], 500);
            }
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()], 500);
        }
    }
}
