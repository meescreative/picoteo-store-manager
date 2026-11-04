<?php
// Als WordPress de uninstall niet aanroept, direct stoppen
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Hier kun je eventuele opties (options table) of transients opschonen als je die in de toekomst gebruikt
delete_transient('psm_store_notice');
