<?php
/**
 * @package VocoWooCommerce
 */

class VWP_Deactivate
{
    public static function deactivate_plugin()
    {
        flush_rewrite_rules();
    }
}
