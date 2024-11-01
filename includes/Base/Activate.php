<?php

/**
 * @package VocoWooCommerce
 */

class VWP_Activate
{
  public static function activate_plugin()
  {
    // $vwActivate = new VWP_Activate;
    // $isRest = $vwActivate->vpw_is_rest();
    // $isWoocommerce = $vwActivate->vpw_is_woocommerce_activated();
    // if (!$isWoocommerce || !$isRest) {
    //   $msg = 'Voco requires ' . (!$isWoocommerce ? 'WooCommerce, ' : '') . ($isRest ? '' : 'Rest Api') . ' plugins to be activated';
    //   deactivate_plugins(VWP_PLUGIN_BASE_NAME);
    //   die($msg);
    // }
    $posts = get_posts(array("post_type" => VWP_POST_TITLE, 'post_status' => "private"));    
    cust_log('vw-post settings', $posts);
    flush_rewrite_rules();
  }

  function vpw_is_woocommerce_activated()
  {
    if (class_exists('woocommerce')) {
      return true;
    } else {
      return false;
    }
  }

  function vpw_is_rest()
  {

    // FIXME - fix the check for rest route in wordpress
    $prefix = rest_get_url_prefix();
    cust_log("checking for rest api existence");
    cust_log("prefix: $prefix");
    cust_log("rest rout", $_GET['rest_route']);
    if (
      defined('REST_REQUEST') && REST_REQUEST // (#1)
      || isset($_GET['rest_route']) // (#2)
      && strpos(trim($_GET['rest_route'], '\\/'), $prefix, 0) === 0
    )
      return true;

    // (#3)
    $rest_url = wp_parse_url(site_url($prefix));
    $current_url = wp_parse_url(add_query_arg(array()));
    return strpos($current_url['path'], $rest_url['path'], 0) === 0;
  }
}
