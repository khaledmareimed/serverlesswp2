<?php
/**
 * Plugin Name: ImgBB Integration Pro
 * Plugin URI: https://yourwebsite.com/imgbb-integration-pro
 * Description: Professional WordPress plugin for ImgBB integration with advanced features and security.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: imgbb-integration-pro
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('IMGBB_INTEGRATION_PRO_VERSION', '1.0.0');

class ImgBB_Integration_Pro {
    private static $instance = null;
    private $plugin_name;
    private $version;

    private function __construct() {
        $this->plugin_name = 'imgbb-integration-pro';
        $this->version = IMGBB_INTEGRATION_PRO_VERSION;

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load_dependencies() {
        // No external dependencies in this single-file version
    }

    private function set_locale() {
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
    }

    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'imgbb-integration-pro',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    private function define_admin_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'register_and_build_fields'));
        add_filter('wp_handle_upload', array($this, 'handle_upload'), 10, 2);
        add_action('add_attachment', array($this, 'save_imgbb_metadata'));
    }

    private function define_public_hooks() {
        add_filter('wp_get_attachment_url', array($this, 'modify_attachment_url'), 10, 2);
    }

    public function enqueue_admin_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/imgbb-integration-pro-admin.css', array(), $this->version, 'all');
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/imgbb-integration-pro-admin.js', array('jquery'), $this->version, false);
    }

    public function add_plugin_admin_menu() {
        add_options_page(
            'ImgBB Integration Settings',
            'ImgBB Integration',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page')
        );
    }

    public function display_plugin_setup_page() {
        include_once 'partials/imgbb-integration-pro-admin-display.php';
    }

    public function register_and_build_fields() {
        add_settings_section(
            'imgbb_integration_pro_general_section',
            'General Settings',
            array($this, 'imgbb_integration_pro_display_general_account'),
            'imgbb_integration_pro_general_settings'
        );

        add_settings_field(
            'imgbb_api_key',
            'ImgBB API Key',
            array($this, 'imgbb_integration_pro_render_settings_field'),
            'imgbb_integration_pro_general_settings',
            'imgbb_integration_pro_general_section',
            array(
                'type' => 'input',
                'subtype' => 'text',
                'id' => 'imgbb_api_key',
                'name' => 'imgbb_api_key',
                'required' => 'true',
                'get_options_list' => '',
                'value_type' => 'normal',
                'wp_data' => 'option'
            )
        );

        register_setting('imgbb_integration_pro_general_settings', 'imgbb_api_key');
    }

    public function imgbb_integration_pro_display_general_account() {
        echo '<p>These settings apply to all ImgBB Integration functionality.</p>';
    }

    public function imgbb_integration_pro_render_settings_field($args) {
        $value = get_option($args['name']);
        $placeholder = isset($args['placeholder']) ? 'placeholder="' . $args['placeholder'] . '"' : '';
        $helper = isset($args['helper']) ? '<span class="helper">' . $args['helper'] . '</span>' : '';
        $supplemental = isset($args['supplemental']) ? '<p class="description">' . $args['supplemental'] . '</p>' : '';
        $type = isset($args['type']) ? $args['type'] : 'text';

        switch ($type) {
            case 'checkbox':
                $checked = checked(1, $value, false);
                echo '<label for="' . $args['id'] . '"><input type="checkbox" id="' . $args['id'] . '" name="' . $args['name'] . '" value="1" ' . $checked . ' />' . $args['label'] . '</label>';
                break;
            default:
                echo '<input type="' . $type . '" id="' . $args['id'] . '" name="' . $args['name'] . '" value="' . esc_attr($value) . '" ' . $placeholder . ' class="regular-text" />';
        }
        echo $helper;
        echo $supplemental;
    }

    public function handle_upload($file_array, $attachment_id = null) {
        $api_key = get_option('imgbb_api_key');
        if (empty($api_key)) {
            return $file_array;
        }

        $file_path = $file_array['file'];
        $file_name = basename($file_path);

        $imgbb_upload = wp_remote_post('https://api.imgbb.com/1/upload', array(
            'timeout' => 60,
            'headers' => array(),
            'body' => array(
                'key' => $api_key,
                'image' => base64_encode(file_get_contents($file_path)),
                'name' => $file_name,
            ),
        ));

        if (is_wp_error($imgbb_upload)) {
            error_log('ImgBB Upload Error: ' . $imgbb_upload->get_error_message());
            return $file_array;
        }

        $imgbb_response = json_decode(wp_remote_retrieve_body($imgbb_upload), true);

        if (isset($imgbb_response['data']['url'])) {
            $file_array['url'] = $imgbb_response['data']['url'];
            $file_array['type'] = $imgbb_response['data']['image']['mime'];
            $file_array['file'] = $imgbb_response['data']['url'];
        } else {
            error_log('ImgBB Upload Error: Unexpected response format');
        }

        return $file_array;
    }

    public function save_imgbb_metadata($attachment_id) {
        $attachment = get_post($attachment_id);
        $imgbb_url = $attachment->guid;
        update_post_meta($attachment_id, '_imgbb_url', $imgbb_url);
    }

    public function modify_attachment_url($url, $attachment_id) {
        $imgbb_url = get_post_meta($attachment_id, '_imgbb_url', true);
        return $imgbb_url ? $imgbb_url : $url;
    }
}

function run_imgbb_integration_pro() {
    $plugin = ImgBB_Integration_Pro::get_instance();
}
run_imgbb_integration_pro();

// Admin display partial
function imgbb_integration_pro_admin_display() {
    ?>
    <div class="wrap">
        <h2>ImgBB Integration Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('imgbb_integration_pro_general_settings');
            do_settings_sections('imgbb_integration_pro_general_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
