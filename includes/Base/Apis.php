<?php

/**
 * @package VocoWooCommerce
 */

class VWP_Apis
{
  public function register()
  {
    add_action('wp_ajax_get_plugin_logs', array($this, "ajax_get_plugin_logs"));
    add_action('wp_ajax_send_plugin_logs', array($this, "ajax_send_plugin_logs"));
  }

  public function ajax_get_plugin_logs()
  {
    $logdata = htmlentities(file_get_contents(VWP_PLUGIN_LOG_LOCATION), true);
    wp_send_json(array(
      "Status" => "Success",
      "Log" => $logdata
    ));
    exit;
  }

  public function ajax_send_plugin_logs()
  {
    cust_log("starting to send logs");
    $logdata = htmlentities(file_get_contents(VWP_PLUGIN_LOG_LOCATION), true);
    cust_log("logs loaded");
    
    $vendor_key = apply_filters("vwp_get_user_hash",null);
    cust_log("applying filter vendor_key:$vendor_key");
    $response = post("/vocoWoocommerceReportLogs", "POST", array("vendor_key" => $vendor_key, "logs" => $logdata));
    cust_log("post response:",$response);
    wp_send_json($response);
    exit;
  }
}
