<?php
/*
Plugin Name: WP Image URL Media
Description: Adds images to WordPress media library by URL without uploading the actual image to the server.
Version: 1.0
Author: Your Name
*/

// Hook to admin menu to add a custom page for adding image by URL
add_action('admin_menu', 'wp_image_url_media_menu');

function wp_image_url_media_menu() {
    add_media_page(
        'Add Image by URL', // Page title
        'Add Image by URL', // Menu title
        'upload_files',     // Capability
        'add-image-url',    // Menu slug
        'wp_image_url_media_page' // Callback function
    );
}

// Callback function to render the form to input image URL
function wp_image_url_media_page() {
    ?>
    <div class="wrap">
        <h1>Add Image by URL</h1>
        <form method="post" action="">
            <label for="image_url">Image URL:</label>
            <input type="text" name="image_url" id="image_url" style="width: 50%;" required />
            <input type="submit" name="submit_image_url" class="button button-primary" value="Add Image">
        </form>
    </div>
    <?php

    // Handle form submission
    if (isset($_POST['submit_image_url'])) {
        $image_url = esc_url_raw($_POST['image_url']);
        wp_image_url_add_to_media_library($image_url);
    }
}

// Function to add the image URL to the media library
function wp_image_url_add_to_media_library($image_url) {
    // Check if the URL is valid
    if (filter_var($image_url, FILTER_VALIDATE_URL)) {

        // Extract the file extension from the URL
        $filetype = wp_check_filetype(basename($image_url));
        $mime_type = $filetype['type'] ? $filetype['type'] : 'image/jpeg'; // Default to 'image/jpeg' if unknown

        // Set up post data to insert as attachment
        $attachment_data = array(
            'guid'           => $image_url, // Use the exact URL as the guid
            'post_mime_type' => $mime_type, // Set mime type based on file extension
            'post_title'     => sanitize_text_field(basename($image_url)),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        // Insert attachment into the database (with no physical file)
        $attachment_id = wp_insert_attachment($attachment_data, '', 0);

        // If the attachment is inserted successfully
        if (!is_wp_error($attachment_id)) {
            // Store the exact URL entered by the user in the _wp_attached_file meta key
            update_post_meta($attachment_id, '_wp_attached_file', $image_url);

            // Generate metadata (though no file exists locally)
            wp_update_attachment_metadata($attachment_id, array());

            echo '<div class="notice notice-success is-dismissible"><p>Image URL added to the media library successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Failed to add image URL to the media library.</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>Invalid image URL.</p></div>';
    }
}
