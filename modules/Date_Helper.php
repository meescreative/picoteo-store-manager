<?php

namespace Picoteo\StoreManager\Modules;

if (!defined('ABSPATH')) {
    exit;
}


class Date_Helper
{

    /**
     * bv. "Maandag 02 november 2026"
     */
    public static function get_formatted_date($dateString)
    {
        $date = new \DateTime($dateString);
        return ucfirst(wp_date('l j F Y', $date->getTimestamp()));
    }

    /**
     * bv. "Maandag 02 november 2026"
     */
    public static function get_date_value($dateString)
    {
        $date = new \DateTime($dateString);
        return wp_date('Y-m-d', $date->getTimestamp());
    }
}
