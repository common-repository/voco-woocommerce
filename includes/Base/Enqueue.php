<?php
/**
 * @package VocoWooCommerce
 */

class VWP_Enqueue
{
    public function register()
    {
        add_action('admin_enqueue_scripts', array($this,'init_enqueue'));
    }

    /**
     * load all voco woocomerce plugin scripts and assets
     */
    public function init_enqueue()
    {
        // load the main css and main script js of voco plugin
        wp_enqueue_style('vwp_pluginstyle', VWP_PLUGIN_URL.'assets/vwp_main.css');
        wp_enqueue_script('vwp_pluginscript', VWP_PLUGIN_URL.'assets/vwp_main.js');
    }
}
