<?php
/**
 * Plugin Name: External FTP Media Storage
 * Plugin URI: https://example.com/plugins/external-ftp-media-storage
 * Description: Store media files on an external server using FTP
 * Version: 1.1
 * Author: Your Name
 * Author URI: https://example.com
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class External_FTP_Media_Storage {
    private $ftp_server;
    private $ftp_username;
    private $ftp_password;
    private $ftp_port;
    private $remote_base_path;

    public function __construct() {
        // Initialize FTP settings
        $this->ftp_server = get_option('efms_ftp_server', '');
        $this->ftp_username = get_option('efms_ftp_username', '');
        $this->ftp_password = get_option('efms_ftp_password', '');
        $this->ftp_port = get_option('efms_ftp_port', 21);
        $this->remote_base_path = get_option('efms_remote_base_path', '/');

        // Hook into WordPress media handling
        add_filter('wp_handle_upload', array($this, 'handle_ftp_upload'), 10, 2);
        add_filter('wp_get_attachment_url', array($this, 'get_remote_attachment_url'), 10, 2);

        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function handle_ftp_upload($file, $overrides) {
        // Only handle image uploads
        if (strpos($file['type'], 'image') === false) {
            return $file;
        }

        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once (ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }

        $connection = $this->ftp_connect();
        if (!$connection) {
            return $file;
        }

        $remote_file = $this->remote_base_path . basename($file['file']);
        $upload_result = $wp_filesystem->put_contents($remote_file, file_get_contents($file['file']));

        if ($upload_result) {
            // Update file location to remote URL
            $file['url'] = $this->get_remote_url($remote_file);
            // Remove local file
            unlink($file['file']);
        }

        return $file;
    }

    public function get_remote_attachment_url($url, $post_id) {
        $file = get_post_meta($post_id, '_wp_attached_file', true);
        if ($file) {
            return $this->get_remote_url($this->remote_base_path . $file);
        }
        return $url;
    }

    private function ftp_connect() {
        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            require_once (ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }

        $ftp_url = "ftp://{$this->ftp_username}:{$this->ftp_password}@{$this->ftp_server}:{$this->ftp_port}";
        
        if ($wp_filesystem->connect($ftp_url)) {
            return true;
        }
        return false;
    }

    private function get_remote_url($path) {
        // Construct the remote URL based on your setup
        return 'http://' . $this->ftp_server . '/' . ltrim($path, '/');
    }

    public function add_settings_page() {
        add_options_page(
            'External FTP Media Storage Settings',
            'External FTP Media',
            'manage_options',
            'external-ftp-media-storage',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('efms_settings_group', 'efms_ftp_server');
        register_setting('efms_settings_group', 'efms_ftp_username');
        register_setting('efms_settings_group', 'efms_ftp_password');
        register_setting('efms_settings_group', 'efms_ftp_port');
        register_setting('efms_settings_group', 'efms_remote_base_path');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h2>External FTP Media Storage Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields('efms_settings_group'); ?>
                <?php do_settings_sections('efms_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">FTP Server</th>
                        <td><input type="text" name="efms_ftp_server" value="<?php echo esc_attr(get_option('efms_ftp_server')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">FTP Username</th>
                        <td><input type="text" name="efms_ftp_username" value="<?php echo esc_attr(get_option('efms_ftp_username')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">FTP Password</th>
                        <td><input type="password" name="efms_ftp_password" value="<?php echo esc_attr(get_option('efms_ftp_password')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">FTP Port</th>
                        <td><input type="number" name="efms_ftp_port" value="<?php echo esc_attr(get_option('efms_ftp_port', 21)); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Remote Base Path</th>
                        <td><input type="text" name="efms_remote_base_path" value="<?php echo esc_attr(get_option('efms_remote_base_path', '/')); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
new External_FTP_Media_Storage();
