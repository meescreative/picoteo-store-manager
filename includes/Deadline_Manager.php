<?php

namespace PSM\Includes;

/**
 * Class Deadline_Manager
 * Beheert de CPT psm_pickup_deadline en bijbehorende logica.
 */
class Deadline_Manager
{

    public function __construct()
    {
        // Registreer CPT en meta-velden bij de 'init' actie
        add_action('init', [$this, 'register_deadline_cpt']);
        add_action('init', [$this, 'register_deadline_meta']);
    }

    /**
     * Registreer de Custom Post Type psm_pickup_deadline
     */
    public function register_deadline_cpt()
    {
        $labels = [
            'name'               => 'Afhaaldeadlines',
            'singular_name'      => 'Afhaaldeadline',
            'menu_name'          => 'Deadlines',
            'add_new'            => 'Nieuwe deadline',
            'add_new_item'       => 'Nieuwe deadline toevoegen',
            'edit_item'          => 'Deadline bewerken',
            'new_item'           => 'Nieuwe deadline',
            'view_item'          => 'Deadline bekijken',
            'search_items'       => 'Zoek deadlines',
            'not_found'          => 'Geen deadlines gevonden',
            'not_found_in_trash' => 'Geen deadlines gevonden in prullenbak',
        ];

        $args = [
            'label'              => 'Afhaaldeadline',
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=psm_pickup_location', // Hangt onder je locaties-menu
            'supports'           => ['title'], // Titel voor overzicht (bijv. "Locatie X - Week 45")
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
        ];

        register_post_type('psm_pickup_deadline', $args);
    }

    /**
     * Registreer de benodigde meta-velden voor validatie en REST API
     */
    public function register_deadline_meta()
    {
        $meta_fields = [
            'deadline_pickup_location_id' => 'integer',
            'deadline_for_week'           => 'integer',
            'deadline_date'               => 'string', // Formaat: 'Y-m-d'
            'deadline_hour'               => 'string', // Formaat: 'HH:MM'
        ];

        foreach ($meta_fields as $meta_key => $type) {
            register_post_meta('psm_pickup_deadline', $meta_key, [
                'type'          => $type,
                'single'        => true,
                'show_in_rest'  => true,
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                }
            ]);
        }
    }

    /**
     * Hulpfunctie: Haal de deadline op voor een specifieke locatie en weeknummer
     * 
     * @ുന്നത് int $location_id De ID van de psm_pickup_location
     * @param int $week_number Het ISO-weeknummer (bijv. 45)
     * @param int|null $year Optioneel jaartal (standaard huidig jaar)
     * @return array|null Array met deadline details of null als niets is gevonden
     */
    public static function get_deadline_for_location_and_week($location_id, $week_number, $year = null)
    {
        if (!$year) {
            $year = (int) (new \DateTime('today'))->format('o');
        }

        $query = new \WP_Query([
            'post_type'      => 'psm_pickup_deadline',
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
                'date'        => get_post_meta($post_id, 'deadline_date', true),
                'hour'        => get_post_meta($post_id, 'deadline_hour', true),
                'location_id' => get_post_meta($post_id, 'deadline_pickup_location_id', true),
                'week'        => get_post_meta($post_id, 'deadline_for_week', true),
            ];
        }

        return null;
    }
}
