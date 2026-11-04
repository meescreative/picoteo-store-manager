<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

class Form_Manager extends Base
{
    public function register_hooks()
    {
        add_action('wp_ajax_breakdance_form_custom', [$this, 'route_breakdance_form'], 10, 1);
    }

    public function route_breakdance_form()
    {
        $fields = isset($_POST['fields']) && is_array($_POST['fields']) ? $_POST['fields'] : [];
        $action_type = isset($fields['psm_action_type']) ? sanitize_text_field($fields['psm_action_type']) : '';

        // Routeren op basis van de ingevoerde actie-type
        switch ($action_type) {
            case 'add_psm_pickup_location':
                // Roep de logica van Location_Manager aan
                Location_Manager::process_form_submission($fields);
                break;

            case 'add_psm_pickup_moment':
                // Roep de logica van PickUp_Manager aan
                PickUp_Manager::process_form_submission($fields);
                break;

            default:
                wp_send_json_error(['message' => 'Onbekend formulier type.'], 400);
                break;
        }
    }
}
