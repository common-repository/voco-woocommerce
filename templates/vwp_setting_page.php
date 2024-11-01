<?php
$activateUrl = VWP_VALIDATE_VENDOR_KEY_API;
$isActivated = false;
$hash = "";
$posts = get_posts(array("post_type" => VWP_POST_TITLE, 'post_status' => "private"));
$ahPost = null;
if (sizeof($posts) > 0) {
  $ahPost = $posts[0]->to_array();
}

if (!is_null($ahPost)) {
  $content = json_decode($ahPost['post_content'], true);
  $hash = isset($content["accountHash"]) ? $content["accountHash"] : "";
}

$isActivated = !is_null($hash) && !empty($hash);

if (isset($_POST['accountHashText'])) {
  $accountHash = sanitize_text_field($_POST['accountHashText']);
  $hash = $accountHash;
  global $voco_env;

  if (isset($_POST['voco_env']))
    $voco_env = sanitize_text_field($_POST['voco_env']);

  $body = array('vendor_key' => $accountHash);
  $response = post($activateUrl, 'POST', $body);
  $isActivated = $response['Status'] == "Success";

  cust_log("activation post response:", $response);
  cust_log("isActive: ", $isActivated ? "true" : "false");
  cust_log("env", $voco_env);
  if ($isActivated) {
    // add_settings_error('vwp-plugin', 'activation-failed', 'Intergration completed!', 'updated');
    if (is_null($ahPost)) {
      $content = json_encode(array(
        "accountHash" => $accountHash,
        "env" => $voco_env
      ));

      $my_post = array(
        'post_title' => VWP_POST_TITLE,
        'post_type' => VWP_POST_TITLE,
        'post_content' => $content,
        'post_status' => "private",
      );

      $insertResult = wp_insert_post($my_post, true);
      cust_log("did insert post result", var_dump($insertResult));
    } else {
      $content["accountHash"] = $accountHash;
      $content["env"] = $voco_env;
      $ahPost['post_content'] = json_encode($content);
      $updateResult = wp_update_post($ahPost);
      cust_log("did update post", $updateResult);
    }

    require_once VWP_PLUGIN_PATH . '/includes/Base/WoocommerceBridge.php';
    (new VWP_WoocommerceBridge())->init(true);
  } else add_settings_error('vwp-plugin', 'activation-failed', 'Invalid vendor key');
}

if (isset($_POST["deactivate_vocoplugin"]) && !is_null($ahPost)) {
  $hash = "";
  $isActivated = false;
  $ahPost['post_content'] = $accountHash;
  wp_update_post($ahPost);
  (new VWP_WoocommerceBridge())->deinit();
}

$isSyncing = "Syncing";
$syncingPercentage = 0;

if (isset($_POST["refresh_status"]) || $isActivated) {
  $activeProductsCount = (new VWP_WoocommerceBridge())->get_active_products_count();
  $syncLeftCount = (new VWP_WoocommerceBridge())->get_sync_left_count();

  switch ($syncLeftCount) {
    case 0:
      $syncingPercentage = 100;
      $isSyncing = "Synced";
      break;
    case $activeProductsCount:
      $syncingPercentage = 0;
      break;
    default:
      $syncingPercentage = floor(100 - ($syncLeftCount / $activeProductsCount) * 100);
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Document</title>
</head>

<body>
  <div class="voco-logo">
    <img src="<?php echo plugin_dir_url(__FILE__)  ?>../assets/voco_logo.png" />
  </div>
  <!-- <h1>
    Voco WooCommerce Settings
  </h1> -->

  <div class="vwp-container">

    <div class="left-box">
      <div class="left-box-inner">
        <span class="left-box-title">Settings</span>

        <div class="left-box-content">
          <?php
          if (!$isActivated) {
            ?>
          <span class="completed-message">Your Integration key can be found here:
            <a href="https://go.voconet.io/dash/settings/integration/" target="_blank">https://go.voconet.io/dash/settings/integration/</a>

            You may use our <a href="https://voconet.io/articles/getting-started-dash-help/" target="blank">Getting started guide</a>.
          </span>
          <div class="status_container">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?page=voco_woocommerce_settings'; ?>" style="width: 100%;display: flex;flex-direction: column;">
              <div style="align-items: center;" class="flex-row">
                <label style="margin-right: auto;">Select Environment</label>
                <select name="voco_env" id="voco_env_url">
                  <option value="<?php echo VWP_URL_SANDBOX ?>"><?php echo VWP_ENV === "production" ? "sandbox" : "dev" ?></option>
                  <option value="<?php echo VWP_URL_PRODUCTION ?>"><?php echo VWP_ENV === "production" ? "production" : "qa" ?></option>
                </select>
              </div>
              <div class="flex-column" id="activate-plugin-container">
                <input type="text" placeholder="Enter your integration key" name="accountHashText" id="activate_vocowoocomerce_api" value="<?php echo esc_html( $hash ); ?>" />
                <input type="submit" class="voco-button" value="Activate" />
              </div>
            </form>
            <span>Don't have a VOCO account? <a href="https://go.voconet.io/registration/" target="_blank">Click Here</a></span>
          </div>
          <?php

          } else {
            ?>

          <?php if ($syncingPercentage === 100) { ?>
          <span class="completed-message">
            Integration completed! please go to <a href="https://go.voconet.io" target="blank">https://go.voconet.io</a> to complete your VOCO setup.

            You may use our <a href="https://voconet.io/articles/getting-started-dash-help/" target="blank">Getting started guide</a>.
          </span>
          <?php } ?>
          <div class="status_container">
            <div style="align-items: center; width: 100%;" class="flex-row">
              <div class="meter">
                <span style="background-color:<?php echo ($syncingPercentage === 100 ? 'rgb(43,194,83)' : '#fd8686') ?>; width: <?php echo $syncingPercentage; ?>%"></span>
              </div>
              <label style="cursor: default;" for=""><?php echo ' (' . $syncingPercentage . '% completed)' ?></label>
            </div>
            <div class="left_box_buttons_containes">
              <form style="align-items: center;" class="flex-row" method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?page=voco_woocommerce_settings'; ?>">
                <label for="" style="<?php echo ($syncingPercentage === 100 ? 'color:green;' : 'color:red;') ?>; margin-right: 5px;">Status: </label>
                <label style="margin-right: 10px;" for=""><?php echo $isSyncing ?></label>
                <input type="submit" class="voco-button" name="refresh_status" value="Refresh" />
              </form>
              <form style="align-items: center;margin-left: 10px;" class="flex-row" method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?page=voco_woocommerce_settings'; ?>">
                <input type="submit" class="voco-button" name="deactivate_vocoplugin" value="Deactivate" />
              </form>
            </div>

          </div>
          <?php
          }
          ?>
        </div>
      </div>

      <br />

      <!--
      <div class="left-box-inner">
        <span class="left-box-title">Getting started</span>
        <br />
        <iframe style="width: 100%; height: 2000px;" src="https://voconet.io/articles/getting-started-dash-help/" scrolling="auto"></iframe>
      </div>
      -->
    </div>

    <div class="right-box">
      <div>
        <div class="right-box-inner">

          <div class="title-bottom-hr">
            <span class="right-box-title">Support</span>
          </div>

          <div class="support-container">
            Support forums
            <a href="https://voconet.io/VOCO-Forum">https://voconet.io/VOCO-Forum</a>
            <br />
            Contact us
            <a href="mailto:support@voconet.io">support@voconet.io</a>
            <br />
          </div>
        </div>

        <br />

        <div class="right-box-inner">
          <div class="title-bottom-hr">
            <span class="right-box-title">Debug</span>
          </div>

          <div class="support-container" style="display: flex; flex-direction: row;">
            <input style="margin: 0 auto;" class="voco-button" id="get_voco_logs_button" type="button" value="Show Logs" onclick="return onClickShowLogs(this)">
            <?php
            if ($isActivated) {
              ?>
            <input style="margin: 0 auto;" class="voco-button" id="send_voco_logs_button" type="button" value="Send Report" onclick="return onClickSendReport(this)">
            <?php
            }
            ?>
          </div>
        </div>
        <br />
        <div class="right-box" id="vwp-messages">
          <?php settings_errors('vwp-plugin'); ?>
        </div>
      </div>
    </div>
    <div class="logviewer_container">
      <div class="loader">
        <div class="lds-ring">
          <div></div>
          <div></div>
          <div></div>
          <div></div>
        </div>
      </div>
      <div id="logviewer">
        <div class="log_container">
          <pre id="logviewerinner">
        </pre>
        </div>
        <input type="button" value="Close" onclick="handleLoggerBox(true)" class="voco-button">
      </div>
    </div>
    <p id="vwp_status_msg" style="white-space: pre-wrap;"></p>
  </div>

</body>

</html>