<?php
/**
 * Plugin Name: External Media FTP
  * Description: Uploads media to an external server via FTP and serves media from there.
   * Version: 1.0
    * Author: Your Name
     * License: GPL2
      */

      if (!defined('ABSPATH')) {
          exit; // Exit if accessed directly
          }

          // Define plugin constants
          define('EMF_PLUGIN_DIR', plugin_dir_path(__FILE__));
          define('EMF_PLUGIN_URL', plugin_dir_url(__FILE__));

          // Include necessary files
          require_once EMF_PLUGIN_DIR . 'includes/settings.php';
          require_once EMF_PLUGIN_DIR . 'includes/upload-handler.php';
          require_once EMF_PLUGIN_DIR . 'includes/url-filter.php';