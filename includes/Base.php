<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Base
{
    public function __construct()
    {
        if (method_exists($this, 'register_hooks')) {
            $this->register_hooks();
        }
    }
}
