<?php
/*
Plugin Name: URL Media Importer
Description: Add images and files to the WordPress media library using only a URL.
Version: 1.0
Author: Your Name
*/

// Add menu item under Media
function url_media_importer_menu() {
    add_media_page(
        'URL Media Importer',
        'URL Media Importer',
        'upload_files',
        'url-media-importer',
        'url_media_importer_page'
    );
}
add_action('admin_menu', 'url_media_importer_menu');

// Create the importer page
function url_media_importer_page() {
    ?>
    <div class="wrap">
        <h1>URL Media Importer</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th><label for="media_url">Media URL</label></th>
                    <td>
                        <input type="url" name="media_url" id="media_url" class="regular-text" required>
                        <p class="description">Enter the URL of the image or file you want to import.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Import Media'); ?>
        </form>
    </div>
    <?php

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['media_url'])) {
        $url = esc_url_raw($_POST['media_url']);
        $result = url_media_importer_process($url);

        if (is_wp_error($result)) {
            echo '<div class="error"><p>' . $result->get_error_message() . '</p></div>';
        } else {
            echo '<div class="updated"><p>Media imported successfully! <a href="' . esc_url(get_edit_post_link($result)) . '">View in Media Library</a></p></div>';
        }
    }
}

// Process the URL and add to media library
function url_media_importer_process($url) {
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Download file to temp dir
    $tmp = download_url($url);

    if (is_wp_error($tmp)) {
        return $tmp;
    }

    $file_array = array(
        'name' => basename($url),
        'tmp_name' => $tmp
    );

    // Check file type
    $file_type = wp_check_filetype(basename($url), null);
    if (!$file_type['type']) {
        unlink($tmp);
        return new WP_Error('invalid_file_type', 'The file type is not allowed.');
    }

    // Use media_handle_sideload to add file to media library
    $id = media_handle_sideload($file_array, 0);

    // Check for errors
    if (is_wp_error($id)) {
        @unlink($file_array['tmp_name']);
        return $id;
    }

    return $id;
}
