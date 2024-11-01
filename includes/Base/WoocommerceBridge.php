<?php

/**
 * @package VocoWooCommerce
 */

class VWP_WoocommerceBridge
{

  const DATA_BASE_NAME = 'vw_products_ids';

  function register()
  {
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
      add_action('woocommerce_init', array($this, 'init'));
      add_action('woocommerce_update_product', array($this, 'on_product_save'), 10, 1);
      add_action('sync_voco_schedule', array($this, 'my_schedule_hook'));
      add_action('sync_voco_schedule_single', array($this, 'my_schedule_hook'));
      add_action('woocommerce_order_status_processing', array($this, 'on_order_made'), 10, 1);
    }

    add_filter("vwp_get_user_hash", function () {
      return $this->getUserHash();
    });
  }

  // on activate plugin hash in plugin settings
  // will create a table if not exist and copy all woocommerce products ID's that under the post_status publish
  // after that will execute a sync with our server 
  function init($isOnActivateKey)
  {
    // cust_log('is woocommerce in array', in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ? "true" : "false");
    // gets a custom post to check if plugin is activated
    if (!$this->isUserActive() || !in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
      cust_log('not initilizing plugin', ($this->isUserActive() ? "missing woocommerce plugin" : "user is not active"), true, true);
      return;
    }

    // creates a custom table with 2 columns
    // a. post_id - containes the product post id
    // b. version_change - auto increment to see if while 
    //    some action the post changed and should be redone again  
    if ($isOnActivateKey) {
      cust_log("initilizing plugin", null, true);
      global $wpdb;
      $table_name = $wpdb->prefix . self::DATA_BASE_NAME;
      if ($wpdb->get_var("show tables like '{$table_name}'") != $table_name) {
        cust_log("creating voco table");
        $this->createTable();
      } else {
        cust_log("voco table exist cleaning up");
        $this->emptyTable();
      }

      // copy all woocommerce product ids from post table under status publish to new table 
      $this->copyProductsIds();

      // start sync the woocommerce products under the status 'publish'
      // with voco dashboard
      $this->createSingleCronTask();
      // $this->sync($this->getUserHash());

      // create a schedule task to check for updates evrey 1 hr 
      $this->createCronTask();
    }
    return $this;
  }

  function deinit()
  {
    remove_action('woocommerce_update_product', array($this, 'on_product_save'), 10, 1);
    remove_action('woocommerce_order_status_completed', array($this, 'on_order_made'), 10, 1);
    wp_clear_scheduled_hook('sync_voco_schedule');
    wp_clear_scheduled_hook('sync_voco_schedule_single');
    remove_action('sync_voco_schedule_single', array($this, 'my_schedule_hook'));
    remove_action('sync_voco_schedule', array($this, 'my_schedule_hook'));
  }

  function get_active_products_count()
  {
    global $wpdb;
    return $wpdb->get_var("SELECT COUNT(*) FROM wp_posts WHERE post_type='product' AND post_status='publish';");
  }

  function get_sync_left_count()
  {
    global $wpdb;
    $data_base = $wpdb->prefix . self::DATA_BASE_NAME;
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $data_base");

    return !is_null($count) ? intval($count) : 0;
  }

  // creates a custom table with 2 columns
  // a. post_id - containes the product post id
  // b. version_change - auto increment to see if while 
  //    some action the post changed and should be redone again   
  private function createTable()
  {
    global $wpdb;
    $data_base = $wpdb->prefix . self::DATA_BASE_NAME;
    $sqlCreate = "CREATE TABLE $data_base ( 
      post_id bigint(20) NOT NULL,
      version_change bigint(20) NOT NULL AUTO_INCREMENT,
      post_type varchar(255) DEFAULT 'product',
      PRIMARY KEY  (version_change),
      UNIQUE KEY  (post_id)
    );";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $resultCreate = dbDelta($sqlCreate);
    cust_log('creating table query', $sqlCreate);
    if (sizeof($resultCreate) > 0) {
      cust_log('create table result', $resultCreate);
    }
    return $resultCreate;
  }

  private function createCronTask($offset = 60 * 60)
  {
    cust_log("creating the each 1hour schedule task");
    wp_schedule_event(time() + $offset, 'hourly', 'sync_voco_schedule');
  }

  private function createSingleCronTask($offset = 0)
  {
    cust_log("creating a one time schedule task");
    wp_schedule_single_event(time() + $offset, 'sync_voco_schedule_single');
  }

  private function copyProductsIds()
  {
    cust_log("copying products");
    global $wpdb;
    $data_base = $wpdb->prefix . self::DATA_BASE_NAME;
    $sqlCopy = "INSERT INTO $data_base (post_id) SELECT ID FROM wp_posts WHERE post_type='product' AND post_status='publish';";
    $resultCopy = $wpdb->query($sqlCopy);
    cust_log('copy table result', $resultCopy);
    return $resultCopy;
  }

  private function emptyTable()
  {
    global $wpdb;
    $data_base = $wpdb->prefix . self::DATA_BASE_NAME;
    $sqlDelete = "TRUNCATE TABLE $data_base;";
    $wpdb->query($sqlDelete);
  }

  // start sync the woocommerce products under the status 'publish'
  // with voco dashboard
  private function sync($accountHash)
  {
    cust_log("syncing the products with voco");
    global $wpdb;
    $data_base = $wpdb->prefix . self::DATA_BASE_NAME;
    $min_post_id = 0;
    $body = $this->getPostBody($accountHash);
    $productsIds = null;
    do {
      $productsIds = $wpdb->get_results("SELECT post_id,version_change,post_type from $data_base WHERE post_id > $min_post_id LIMIT 10;", "ARRAY_A");
      cust_log('quarying products ids from sql productIds', $productsIds);
      $deleteIds = array();

      foreach ($productsIds as $productData) {
        $bodyCopy = $body;
        $isSucces = $productData["post_type"] === "order" ? $this->sendOrder($bodyCopy, $productData['post_id']) : $this->sendProduct($bodyCopy, $productData['post_id']);
        if ($isSucces) {
          $deleteIds[] = $productData;
        }

        if ($productData['post_id'] > $min_post_id)
          $min_post_id = $productData['post_id'];
      }

      $this->deleteProducts($deleteIds);
      cust_log('post handleProduct done');
      if (!$productsIds)
        $productsIds = array();
    } while (sizeof($productsIds) > 9);
  }

  // http post to voco dashboard with a product data array
  // if success will be deleted from the voco table else 
  // will not be deleted and will be tried to be sent again in an aproxamly an hour.
  private function sendProduct($body, $productId = null)
  {
    if (!is_null($productId) && !empty($productId)) {
      $currency = get_woocommerce_currency();
      $wc_product = new WC_Product($productId);
      $product = $wc_product->get_data();
      if ($wc_product->get_status() != "publish")
        return true;

      cust_log('productId:' . $productId . ' short_description context View ' . $wc_product->get_short_description());
      $product["short_description"] = $this->handleString($wc_product->get_short_description());
      $description = $this->handleString($wc_product->get_description());

      if (empty($product["short_description"]))
        if (!empty($description))
          $product["short_description"] = $description;

      cust_log('befor product short_description ' . $product["short_description"]);
      $product['permalink'] = $wc_product->get_permalink();
      $product['categories'] = array();
      $product['tags'] = array();
      $product['images'] = array();
      $product['product_id'] = $product['id'];
      $product["product_name"] = $product["name"];
      $product['price'] = (empty($product['regular_price']) ? empty($product['price']) ? "0"
        : $product['price']
        : $product['regular_price']) . ' ' . $currency;

      unset($product['id']);
      unset($product['name']);

      foreach ($product['category_ids'] as $cat_id) {
        if ($term = get_term_by('id', $cat_id, 'product_cat')) {
          $product['categories'][] = $term->name;
        }
      }

      if (sizeof($product['categories']) == 0) {
        $product['categories'][] = "Uncategorized";
      }

      foreach ($product["tag_ids"] as $tag_id) {
        $tag = get_tag($tag_id);
        $product['tags'][] = $tag->name;
      }

      if (!empty($product['image_id'])) {
        $imageResult = wp_get_attachment_image_src($product['image_id']);
        if (sizeof($imageResult) > 0) {
          $product['images'][] = array("src" => $imageResult[0]);
        }
      }

      $body['product'] = $product;
      cust_log("sending product to voco productId:$productId");
      $response = post(VWP_ADD_UPDATE_PRODUCT_API, 'POST', $body);
      if (is_wp_error($response))
        return false;
      return $response['Status'] == "Success" ? true : false;
    }
  }

  function sendOrder($body, $order_id)
  {
    $order = wc_get_order($order_id);
    $items = $order->get_items();
    $newItems = array();
    foreach ($items as $item) {
      $orderItem = $item->get_data();
      $orderItem["product_id"] = $item->get_product_id();
      $orderItem["product_name"] = $item->get_name();
      $orderItem["total_price"] = $item->get_total() . ' ' . $order->get_currency();
      $newItems[] = $orderItem;
    }

    $body = $this->getPostBody($this->getUserHash());
    $body["products"] = $newItems;
    $body["time_to_send"] = round(microtime(true));
    $body["order"] = $order->get_data();
    $body['customer_msisdn'] = $order->get_billing_phone();
    $body['customer_name'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $body["total_price"] = $order->get_total() . ' ' . $order->get_currency();

    cust_log("sending order to voco orderId:$order_id");
    $response = post(VWP_INVOKE_ORDER_API, 'POST', $body);

    if (is_wp_error($response))
      return false;
    else if (isset($response["Error"]))
        return false;
    return true;
  }

  private function deleteProducts($deleteIds)
  {
    cust_log("deleteProducts ids", $deleteIds);
    global $wpdb;
    $data_base = $wpdb->prefix . self::DATA_BASE_NAME;
    $ids = "";
    foreach ($deleteIds as $data) {
      $ids = $ids . (empty($ids) ? '' : ' OR ') . '(post_id="' . $data["post_id"] . '" AND version_change="' . $data["version_change"] . '")';
    }

    cust_log("delete sql query", $ids);
    if (!empty($ids)) {
      $result = $wpdb->query("DELETE from $data_base WHERE $ids");
      cust_log("delete sql query result", $result);
    }
  }

  function on_product_save($post_id)
  {
    if (!$this->isUserActive())
      return;

    cust_log('on_product_save id', $post_id);
    global $wpdb;
    $data_base = $wpdb->prefix . self::DATA_BASE_NAME;
    $sqlDelete = "DELETE FROM $data_base WHERE post_id='$post_id'";
    $sqlInsert = "INSERT INTO $data_base (post_id) VALUES ($post_id)";
    $resultDelete = $wpdb->query($sqlDelete);
    $resultInsert = $wpdb->query($sqlInsert);

    cust_log('delete row  in table result', $resultDelete);
    cust_log('copy table result', $resultInsert);
    $accountHash = $this->getUserHash();
    $body = $this->getPostBody($accountHash);
    cust_log("on_product_save sendProduct id:$post_id body", $body);
    if ($this->sendProduct($body, $post_id)) {
      $resultSelect = $wpdb->get_row("SELECT * FROM $data_base WHERE post_id='$post_id'", 'ARRAY_A');
      $version_chnge = $resultSelect["version_change"];
      $wpdb->query("$resultDelete AND version_change='$version_chnge'");
    }
    return $resultInsert;
  }

  function on_order_made($order_id)
  {
    if (!$this->isUserActive())
      return;

    global $wpdb;
    $data_base = $wpdb->prefix . self::DATA_BASE_NAME;
    $sqlDelete = "DELETE FROM " . $data_base . " WHERE post_id='$order_id'";
    $sqlInsert = "INSERT INTO " . $data_base . " (post_id,post_type) VALUES ($order_id,'order')";
    $resultDelete = $wpdb->query($sqlDelete);
    $resultInsert = $wpdb->query($sqlInsert);
    cust_log('delete row  in table result', $resultDelete);
    cust_log('copy table result', $resultInsert);
    $resultSelect = $wpdb->get_row("SELECT * FROM $data_base WHERE post_id='$order_id'", 'ARRAY_A');
    $version_chnge = $resultSelect["version_change"];
    cust_log('select table result', $resultSelect);
    if ($this->sendOrder($this->getPostBody($this->getUserHash()), $order_id))
      $wpdb->query("$sqlDelete AND version_change='$version_chnge'");

    return $resultInsert;
  }

  function my_schedule_hook()
  {
    $this->sync($this->getUserHash());
  }

  // -- helper functions 


  // checks if the user is activated 
  // the plugin from the plugin settings
  private function isUserActive()
  {
    $hash = $this->getUserHash();
    return !is_null($hash) && !empty($hash);
  }

  // return the user hash that the plugin is activated with 
  private function getUserHash()
  {
    $hash = "";
    $posts = get_posts(array("post_type" => VWP_POST_TITLE, 'post_status' => "private"));
    $ahPost = null;    
    if (sizeof($posts) > 0)
      $ahPost = $posts[0]->to_array();

    if (!is_null($ahPost)) {
      $content = json_decode($ahPost['post_content'], true);
      if (isset($content["accountHash"]))
        $hash =  $content["accountHash"];
    }

    return $hash;
  }

  private function getPostBody($accountHash)
  {
    return array(
      'vendor_key' => $accountHash,
    );
  }

  private function getUserProfile()
  {
    $hash = $this->getUserHash();
    return explode(";", $hash)[0];
  }

  private function handleString($string, $isShortDescription = true)
  {
    $newString = strip_tags($string);
    $newString = str_replace("&nbsp;", " ", $newString);
    $newString = trim($newString);
    if ($isShortDescription && mb_strlen($newString) > 512)
      $newString = mb_substr($newString, 0, 512);

    return $newString;
  }
}
