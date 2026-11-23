<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

class Stock_Manager extends Base
{

    public function register_hooks()
    {
        // Registreer de Custom Post Type standaard op init
        add_action('init', [$this, 'register_psm_stock_item']);
        add_action('init', [$this, 'register_psm_stock_purchase']);
        //add_action('wp_ajax_psm_get_deadline', [$this, 'handle_get_deadline']);
    }

    /**
     * Registreer de Custom Post Type 'psm_stock_item'
     */
    public function register_psm_stock_item()
    {
        register_post_type('psm_stock_item', [
            'labels' => [
                'name'               => __('Stockitems', 'picoteo-store-manager'),
                'singular_name'      => __('Stockitem', 'picoteo-store-manager'),
                'add_new'            => __('Nieuwe toevoegen', 'picoteo-store-manager'),
                'add_new_item'       => __('Nieuwe stockitem toevoegen', 'picoteo-store-manager'),
                'edit_item'          => __('Bewerk stockitem', 'picoteo-store-manager'),
                'new_item'           => __('Nieuwe stockitem', 'picoteo-store-manager'),
                'search_items'       => __('Zoek stockitems', 'picoteo-store-manager'),
                'not_found'          => __('Geen stockitems gevonden', 'picoteo-store-manager'),
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'picoteo-store-manager', // Hangt netjes onder je hoofdmenu
            'supports'           => ['title'], // Titel fungeert als overzichtelijke naam (bijv. "Locatie Brugge - Week 45")
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'has_archive'        => false,
            'rewrite'            => false,
        ]);
    }

    /**
     * Registreer de Custom Post Type 'psm_stock_purchase'
     */
    public function register_psm_stock_purchase()
    {
        register_post_type('psm_stock_purchase', [
            'labels' => [
                'name'               => __('Stockaankopen', 'picoteo-store-manager'),
                'singular_name'      => __('Stockaankoop', 'picoteo-store-manager'),
                'add_new'            => __('Nieuwe toevoegen', 'picoteo-store-manager'),
                'add_new_item'       => __('Nieuwe stockaankoop toevoegen', 'picoteo-store-manager'),
                'edit_item'          => __('Bewerk stockaankoop', 'picoteo-store-manager'),
                'new_item'           => __('Nieuwe stockaankoop', 'picoteo-store-manager'),
                'search_items'       => __('Zoek stockaankopen', 'picoteo-store-manager'),
                'not_found'          => __('Geen stockaankopen gevonden', 'picoteo-store-manager'),
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'picoteo-store-manager', // Hangt netjes onder je hoofdmenu
            'supports'           => ['title'], // Titel fungeert als overzichtelijke naam (bijv. "Locatie Brugge - Week 45")
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'has_archive'        => false,
            'rewrite'            => false,
        ]);
    }

    /**
     * Voeg een nieuwe afhaaldeadline toe als WordPress Post + Meta
     *
     * @param string $title
     * @param int    $location_id
     * @param int    $week
     * @param string $date (Formaat: Y-m-d)
     * @param string $hour (Formaat: HH:MM)
     * @return int|false Post ID of false bij falen
     */
    public static function add_psm_stock_item($item_title, $item_category, $item_unit, $item_extra)
    {
        $clean_title        = mb_substr(sanitize_text_field($item_title), 0, 255);
        $clean_category     = mb_substr(sanitize_text_field($item_category), 0, 255);
        $clean_unit         = mb_substr(sanitize_text_field($item_unit), 0, 255);
        $clean_extra        = mb_substr(sanitize_text_field($item_extra), 0, 255);


        if (empty($clean_title) || empty($clean_category) || empty($clean_unit)) {
            return false;
        }

        $post_data = [
            'post_title'  => $clean_title,
            'post_status' => 'publish',
            'post_type'   => 'psm_stock_item',
        ];

        $post_id = wp_insert_post($post_data, true);

        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, 'item_title', $clean_title);
            update_post_meta($post_id, 'item_category', $clean_category);
            update_post_meta($post_id, 'item_unit', $clean_unit);
            update_post_meta($post_id, 'item_extra', $clean_extra);
            update_post_meta($post_id, 'item_active', true);
            return $post_id;
        }

        return false;
    }

    /**
     * Werk een bestaande psm_order_deadline CPT en meta-velden bij
     * 
     * @param array $fields De formuliervelden
     * @return int|false Post ID of false bij falen
     */
    public static function update_psm_order_deadline($fields)
    {
        $post_id     = isset($fields['psm_order_deadline_id']) ? intval($fields['psm_order_deadline_id']) : 0;
        $location_id = isset($fields['pickup_location_id']) ? intval($fields['pickup_location_id']) : 0;
        $date_string = isset($fields['deadline_date']) ? sanitize_text_field($fields['deadline_date']) : '';
        $hour        = isset($fields['deadline_hour']) ? sanitize_text_field($fields['deadline_hour']) : '';

        // Valideer of de post bestaat en daadwerkelijk de juiste CPT is
        if (!$post_id || get_post_type($post_id) !== 'psm_order_deadline') {
            return false;
        }

        // Bereken opnieuw het weeknummer en de titel op basis van de datum
        $week = 0;
        $year = 0;
        $title = '';

        if (!empty($date_string)) {
            $date_obj = \DateTime::createFromFormat('Y-m-d', $date_string);
            if ($date_obj instanceof \DateTime) {
                $week  = (int) $date_obj->format('W');
                $year  = (int) $date_obj->format('o');
                $title = 'Deadline week ' . $week . ' (' . $year . ')';
            }
        }

        if (empty($title) || empty($location_id) || empty($week) || empty($date_string) || empty($hour)) {
            return false;
        }

        // 1. Update de WordPress Post (bijv. de titel)
        $post_data = [
            'ID'         => $post_id,
            'post_title' => $title,
        ];

        $updated_post_id = wp_update_post($post_data, true);

        if (is_wp_error($updated_post_id)) {
            return false;
        }

        // 2. Update de bijbehorende meta-velden
        update_post_meta($post_id, 'deadline_pickup_location_id', $location_id);
        update_post_meta($post_id, 'deadline_for_week', $week);
        update_post_meta($post_id, 'deadline_date', $date_string);
        update_post_meta($post_id, 'deadline_hour', $hour);

        return $post_id;
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

        // Haal de formuliervelden op uit $fields
        $item_title     = isset($fields['psm_stock_item_title'])    ? sanitize_text_field($fields['psm_stock_item_title']) : '';
        $item_category  = isset($fields['psm_stock_item_category']) ? sanitize_text_field($fields['psm_stock_item_category']) : '';
        $item_unit      = isset($fields['psm_stock_item_unit'])     ? sanitize_text_field($fields['psm_stock_item_unit']) : '';
        $item_extra     = isset($fields['psm_stock_item_extra'])    ? sanitize_text_field($fields['psm_stock_item_extra']) : '';


        // Valideer of alle verplichte velden correct zijn gevuld
        if (empty($item_title) || empty($item_category) || empty($item_unit)) {
            wp_send_json_error(['message' => 'Stockitem: Niet alle verplichte velden zijn ingevuld.'], 400);
        }

        try {
            /* Indien ik update wil toevoegen
            if ($deadline_id > 0) {
                // --- UPDATE BESTAANDE DEADLINE ---
                $post_id = self::update_psm_order_deadline($fields);
                $success_message = 'Deadline succesvol geüpdatet!';
            } else {
                // --- NIEUWE DEADLINE AANMAKEN ---
                $post_id = self::add_psm_deadline($title, $location_id, $week, $date_string, $hour);
                $success_message = 'Deadline succesvol toegevoegd!';
                }
                */

            $post_id = self::add_psm_stock_item($item_title, $item_category, $item_unit, $item_extra);
            $success_message = 'Item succesvol toegevoegd!';

            if ($post_id && !is_wp_error($post_id) && get_post_type($post_id) === 'psm_stock_item') {
                wp_send_json_success([
                    'post_id' => $post_id,
                    'message' => $success_message
                ]);
            } else {
                $error_detail = is_wp_error($post_id) ? $post_id->get_error_message() : 'Opslaan gaf false terug.';
                wp_send_json_error(['message' => 'Fout bij opslaan CPT: ' . $error_detail], 500);
            }
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Haal de deadline op voor een specifieke locatie en weeknummer
     *
     * @param int      $location_id De ID van de psm_order_location
     * @param int      $week_number Het ISO-weeknummer (bijv. 45)
     * @param int|null $year        Optioneel jaartal (standaard huidig jaar)
     * @return array|null Array met deadline details of null als niets is gevonden
     */
    public static function get_deadline($location_id, $week_number, $year = null)
    {
        if (!$year) {
            $year = (int) (new \DateTime('today'))->format('o');
        }

        $query = new \WP_Query([
            'post_type'      => 'psm_order_deadline',
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'deadline_pickup_location_id',
                    'value'   => $location_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => 'deadline_for_week',
                    'value'   => $week_number,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);

        if (!empty($query->posts)) {
            $post_id = $query->posts[0]->ID;
            return [
                'post_id'     => $post_id,
                'title'       => get_the_title($post_id),
                'date'        => get_post_meta($post_id, 'deadline_date', true),
                'hour'        => get_post_meta($post_id, 'deadline_hour', true),
                'location_id' => get_post_meta($post_id, 'deadline_pickup_location_id', true),
                'week'        => get_post_meta($post_id, 'deadline_for_week', true),
            ];
        }

        return null;
    }

    /**
     * AJAX-handler: Haal bestaande deadline op basis van location_id en week
     */
    public function handle_get_deadline()
    {
        // Optioneel: Beveiliging of nonce-controle indien gewenst
        $location_id = isset($_REQUEST['location_id']) ? intval($_REQUEST['location_id']) : 0;
        $week_number = isset($_REQUEST['week']) ? intval($_REQUEST['week']) : 0;

        if (!$location_id || !$week_number) {
            wp_send_json_error(['message' => 'Ongeldige parameters.'], 400);
        }

        // Gebruik je reeds bestaande helper-methode in Deadline_Manager
        $deadline = self::get_deadline($location_id, $week_number);

        if ($deadline) {
            wp_send_json_success([
                'exists' => true,
                'data'   => $deadline // Bevat o.a. 'date' (Y-m-d) en 'hour' (HH:MM)
            ]);
        }

        wp_send_json_success([
            'exists' => false,
            'data'   => null
        ]);
    }
}
