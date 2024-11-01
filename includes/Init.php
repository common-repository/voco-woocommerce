<?php

/**
 * @package VocoWooCommerce
 */
require_once VWP_PLUGIN_PATH . 'includes/Base/Apis.php';
require_once VWP_PLUGIN_PATH . 'includes/Pages/Settings.php';
require_once VWP_PLUGIN_PATH . 'includes/Base/SettingsLinks.php';
require_once VWP_PLUGIN_PATH . 'includes/Base/Enqueue.php';
require_once VWP_PLUGIN_PATH . 'includes/Base/WoocommerceBridge.php';

final class VWP_Init
{

  /**
   * stores all classes in array
   * @return array full list of classes
   */
  public static function get_services()
  {
    return array(
      VWP_Settings::class,
      VWP_SettingsLinks::class,
      VWP_Enqueue::class,
      VWP_Apis::class,
      VWP_WoocommerceBridge::class,
    );
  }

  /**
   * loop through all the classes and initilized them
   * and register the functionality
   * @return
   */
  public static function register_services()
  {
    foreach (self::get_services() as $class) {
      $service = new $class;
      if (method_exists($service, 'register')) {
        $service->register();
      }
    }    
  }
}
