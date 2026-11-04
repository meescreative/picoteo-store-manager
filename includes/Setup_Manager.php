<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

class Setup_Manager extends Base
{


    public function register_hooks()
    {
        // Voeg eventuele hooks toe
    }

    /**
     * Haal de status op voor een specifiek onderdeel (of alles tegelijk)
     * Direct aan te roepen via: Setup_Manager::get_setup_status('database')
     * 
     * @param string $type Bijv. 'database' of leeg voor alles
     * @return mixed
     */
    public static function get_setup_status($type = '')
    {
        // V2 Database check via de Database_Manager (controleert alle 10 tabellen)
        $database_status = Database_Manager::check_tables_exist();

        // Verzamel alle status-waarden in een array
        $statuses = [
            'database'       => $database_status,
            'all' => $database_status
        ];

        // Als er om een specifiek type wordt gevraagd, geef dan enkel die waarde terug
        if (!empty($type)) {
            return isset($statuses[$type]) ? $statuses[$type] : false;
        }

        return $statuses;
    }
}
