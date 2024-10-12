<?php
/**
 * Plugin Name: Imgbb Image Upload
 * Description: Automatically upload images to imgbb and store them in the WordPress media library.
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define the imgbb API key
define('IMGBB_API_KEY', 'bff07ffc45c353b36a9ab606e7500647');

// Hook into the media upload process
add_filter('wp_handle_upload', 'imgbb_upload_image_to_imgbb');

/**
 * Upload image to imgbb, then replace with imgbb URL
 *
 * @param array $upload
 * @return array
 */
function imgbb_upload_image_to_imgbb($upload) {
    // Get the file path and read the image data
    $file = $upload['file'];
    $image_data = base64_encode(file_get_contents($file));

    // Make the API request to imgbb
    $response = wp_remote_post('https://api.imgbb.com/1/upload?key=' . IMGBB_API_KEY, [
        'body' => [
            'image' => $image_data,
        ],
    ]);

    // Check if the request was successful
    if (is_wp_error($response)) {
        return $upload; // If failed, return the original upload
    }

    $response_body = wp_remote_retrieve_body($response);
    $json_response = json_decode($response_body, true);

    // Check if the upload to imgbb was successful
    if (isset($json_response['data']['url'])) {
        // Retrieve the imgbb URL
        $imgbb_url = $json_response['data']['url'];

        // Replace the local file URL with the imgbb URL
        $upload['url'] = $imgbb_url;

        // Delete the local file to save space (optional)
        if (file_exists($file)) {
            unlink($file);
        }
    }

    return $upload;
}
