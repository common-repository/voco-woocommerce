<?php

/**
 * @package VocoWooCommerce
 */
/*
Plugin Name: Voco WooCommerce
Plugin URI: https://voconet.io/VocoWooCommerce
Description: Connect Voco to your WooCommerce.
Version: 1.0.0
Author: Cell Buddy LTD
License: GPLv2 or later
Text Domain:voco-woocommerce-plugin
 */

// 1. will instruct vendor to register with our dashboard, get a key and enter in the plugin
// 2. after inserting the key and validated, will sync in the background all products to our dashboard,
// and invoke affiliates on all purchases

/**
 * implementation:
 * 1. use a new sql table for changed items
 * 2. create sync() function which goes item by item of "changed items" table, posts to our dashboard, upon success deletes from "changed items" (if not changed in meantime again)
 * 3. invokeAffiliates on item bought
 * 4. upon start: copy all items to changed items table + sync()
 * 5. upon any change - update changed items table + sync()
 * 6. every hour - sync()
 */

/**
 * important:
 * 1. sync(): before deleting posted entry, make sure it was not updated while posting
 * 2. sync(): block multiple instances running at same time
 * 3. if item update pending post + updated again - save only latest value
 */

// if the files called directly , abort!!!
if (!defined('ABSPATH')) {
  die('Are you serious black?');
}

$vocoPlugin = __FILE__;

// Define CONSTANTS
define('VWP_PLUGIN_PATH', plugin_dir_path(($vocoPlugin)));
define('VWP_PLUGIN_URL', plugin_dir_url($vocoPlugin));
define('VWP_PLUGIN_BASE_NAME', plugin_basename($vocoPlugin));
define("VWP_DEBUG_MODE", true);
define("VWP_ENV", "production");
define('VWP_POST_TITLE', 'vp_at_status');

define('VWP_ADD_UPDATE_PRODUCT_API', '/addOrUpdateProduct');
define('VWP_VALIDATE_VENDOR_KEY_API', '/validateVendorKey');
define('VWP_INVOKE_ORDER_API', '/onOrderMade');

define('VWP_URL_SANDBOX', VWP_ENV === "production" ? "sandbox.voconet.io" : "go.voco1.com");
define('VWP_URL_PRODUCTION', VWP_ENV === "production" ? "go.voconet.io" : "go.voco2.co");


class VWP
{
  public function activate_vocowoocomerce_plugin()
  {
    require_once VWP_PLUGIN_PATH . 'includes/Base/Activate.php';
    VWP_Activate::activate_plugin();
  }
  public function deactivate_vocowoocomerce_plugin()
  {
    require_once VWP_PLUGIN_PATH . 'includes/Base/Deactivate.php';
    VWP_Deactivate::deactivate_plugin();
  }
}

$vwp = new VWP();
register_activation_hook($vocoPlugin, array($vwp, 'activate_vocowoocomerce_plugin'));
register_deactivation_hook($vocoPlugin, array($vwp, 'deactivate_vocowoocomerce_plugin'));

// Initilize the core classes of the plugin
require_once VWP_PLUGIN_PATH . 'includes/Init.php';
if (class_exists('VWP_Init')) {
  $posts = get_posts(array("post_type" => VWP_POST_TITLE, 'post_status' => "private"));
  $ahPost = null;
  if (sizeof($posts) > 0) {
    $ahPost = $posts[0]->to_array();
  }

  $envSelected = VWP_URL_SANDBOX;
  if (!is_null($ahPost)) {
    $content = json_decode($ahPost['post_content'], true);
    if (isset($content["env"]))
      $envSelected =  $content["env"];
  }

  global $voco_env;
  $voco_env = $envSelected;

  VWP_Init::register_services();
}

function post($function, $method, $data, $custom_url = null)
{
  global $voco_env;
  $url = 'https://' . $voco_env . '/wp-json/vocoapi/v1';
  if (!is_null($custom_url) && !empty($custom_url))
    $url = $custom_url;

  if ($function !== "/vocoWoocommerceReportLogs")
    cust_log("sending post data $url$function", $data);
  else
    cust_log("sending post data $url$function", $data["vendor_key"]);

  $response = wp_remote_post("$url$function", array(
    'method' => $method,
    'timeout' => 45,
    'headers' => array(
      "Content-Type" => "application/json"
    ),
    'body' => json_encode($data)
  ));
  cust_log("post response before format: ", $response);
  if (is_wp_error($response)) {
    cust_log("post response Error ", $response);
    return array(
      "Status" => 'Failed',
      'Error' => $response->get_error_message()
    );
  } else {
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body)) {
      $body = array('Status' => "Failed");
      $tempBody = wp_remote_retrieve_body($response);
      if (!empty($tempBody)) {
        if (strpos($tempBody, '"Status":"Success"') !== false) {
          $body['Status'] = "Success";
        }
      }
    }

    return $body;
  }
}

define("VWP_PLUGIN_LOG_LOCATION", plugin_dir_path(__FILE__) . 'debug.log');

function cust_log($message, $data = null, $is_start_new_line = false, $is_end_new_line = false)
{
  if (VWP_DEBUG_MODE) {
    $t = microtime(true);
    $micro = sprintf("%03d", ($t - floor($t)) * 1000);

    $caller = debug_backtrace()[1];
    $fileCaller = explode('/', $caller["file"]);
    $callerLog =  explode(".", $fileCaller[sizeof($fileCaller) - 1])[0] . ' -> ' . $caller["function"];

    $log = ($is_start_new_line ? "\n" : "") . "\n[ " . date('d-m-Y h:i:s.') . $micro  . ' ] :: ' .  $callerLog . " :: " . $message;
    if (isset($data) && !is_null($data))
      $log .= " :: " . json_encode($data);

    $log .= ($is_end_new_line ? "\n" : "");

    $file = fopen(VWP_PLUGIN_LOG_LOCATION, "a");
    fwrite($file, $log);
    fclose($file);
  }
}

add_action('admin_enqueue_scripts', 'load_assets');
function load_assets($hook)
{
  if ("settings_page_voco_woocommerce_settings" === $hook) {
    wp_register_script('voco_settings_script', plugins_url('assets/vwp_main.js', __FILE__), array('jquery'), '1.0', true);
    wp_enqueue_script('voco_settings_script');
    wp_enqueue_style('voco_settings_style', plugins_url('assets/vwp_main.css', __FILE__), '', '1.0');
  } else {
    wp_dequeue_style("voco_settings_style");
    wp_dequeue_script("voco_settings_script");
    wp_deregister_style('voco_settings_style');
  }
}

set_error_handler('vwp_error_handler');
function vwp_error_handler($errno, $errstr, $errfile, $errline)
{
  $pos_str = mb_strpos($errfile, "voco-woocommerce-plugin");
  if ($pos_str !== false) {
    $file_location = mb_substr($errfile, $pos_str);
    cust_log("ERROR", "FILE: $file_location, LINE: $errline, MESSAGE: $errstr", true, true);
  }
}
