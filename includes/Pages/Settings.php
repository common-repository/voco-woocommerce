<?php
/**
 * @package VocoWooCommerce
 */

class VWP_Settings
{
    public function register()
    {
        add_action('admin_menu', array( $this, 'add_menu_item' ));
    }

    /**
       * Add settings page to admin menu
       * @return void
       */
    public function add_menu_item()
    {
        $page = add_options_page('VOCO WooCommerce', 'VOCO WooCommerce', 'manage_options', 'voco_woocommerce_settings', array( $this, 'settings_page' ));
        // add_action('admin_print_styles-' . $page, array( $this, 'settings_assets' ));
    }

    public function settings_page()
    {
        require_once VWP_PLUGIN_PATH.'/templates/vwp_setting_page.php';
    }
}
