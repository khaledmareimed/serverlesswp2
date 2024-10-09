<?php
// Register settings
add_action('admin_init', 'emf_register_settings');

function emf_register_settings() {
    register_setting('emf_settings_group', 'emf_ftp_host');
    register_setting('emf_settings_group', 'emf_ftp_username');
    register_setting('emf_settings_group', 'emf_ftp_password');
    register_setting('emf_settings_group', 'emf_ftp_port');
    register_setting('emf_settings_group', 'emf_ftp_base_url');
    register_setting('emf_settings_group', 'emf_ftp_directory'); // Add directory setting
}

// Add settings page to Media menu
add_action('admin_menu', 'emf_add_settings_page');

function emf_add_settings_page() {
    add_media_page(
        'External Media FTP Settings',
        'External Media FTP',
        'manage_options',
        'external-media-ftp',
        'emf_render_settings_page'
    );
}

function emf_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>External Media FTP Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('emf_settings_group'); ?>
            <?php do_settings_sections('emf_settings_group'); ?>
            <table class="form-table">
                <!-- Existing settings -->
                <tr valign="top">
                    <th scope="row">FTP Host</th>
                    <td><input type="text" name="emf_ftp_host" value="<?php echo esc_attr(get_option('emf_ftp_host')); ?>" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">FTP Username</th>
                    <td><input type="text" name="emf_ftp_username" value="<?php echo esc_attr(get_option('emf_ftp_username')); ?>" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">FTP Password</th>
                    <td><input type="password" name="emf_ftp_password" value="<?php echo esc_attr(get_option('emf_ftp_password')); ?>" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">FTP Port</th>
                    <td><input type="number" name="emf_ftp_port" value="<?php echo esc_attr(get_option('emf_ftp_port')) ?: '21'; ?>" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Base URL of External Server</th>
                    <td><input type="text" name="emf_ftp_base_url" value="<?php echo esc_attr(get_option('emf_ftp_base_url')); ?>" required placeholder="https://your-external-server.com/media/" /></td>
                </tr>
                <!-- New directory field -->
                <tr valign="top">
                    <th scope="row">FTP Directory</th>
                    <td><input type="text" name="emf_ftp_directory" value="<?php echo esc_attr(get_option('emf_ftp_directory')); ?>" placeholder="/your/custom/directory/" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
