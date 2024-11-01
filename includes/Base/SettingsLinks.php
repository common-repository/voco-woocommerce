<?php
/**
 * @package VocoWooCommerce
 */

class VWP_SettingsLinks
{
    public function register()
    {
        add_filter('plugin_action_links_'.VWP_PLUGIN_BASE_NAME, array($this,'settings_link'));
    }

    public function settings_link($links)
    {
        $settings_link = '<a href="options-general.php?page=voco_woocommerce_settings">Settings</a>';
        array_push($links, $settings_link);
        return $links;
    }
}
