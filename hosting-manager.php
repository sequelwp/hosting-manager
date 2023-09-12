<?php
/*
Plugin Name: Hosting Manager
Description: Manages caching and purges when changes are made.
Version: 1.0
Author: SequelWP
*/

/**
 * Retrieve the API key from the dynamically determined file path.
 * 
 * @return string|false The API key if found, or false if not.
 */
function get_api_key_from_file() {
    $doc_root = $_SERVER['DOCUMENT_ROOT'];
    $api_key_path = "$doc_root/etc/apikey";
    
    if (!file_exists($api_key_path)) {
        error_log('API key file not found.');
        return false;
    }
    
    return trim(file_get_contents($api_key_path));
}

/**
 * Call the cache purge API.
 */
function purge_cache_on_change() {
    $api_key = get_api_key_from_file();

    if (!$api_key) {
        return;  // Exit if the API key couldn't be retrieved
    }

    // Set domain
    $domain = get_site_url();  // Adjust as needed

    // Setup the parameters
    $params = [
        'apiKey'   => $api_key,
        'function' => 'purgeCache',
        'domain'   => $domain
    ];

    $query = "https://my.sequelwp.com/client-api";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_URL, $query);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
    $result = curl_exec($curl);

    if (curl_errno($curl)) {
        error_log('cURL error: ' . curl_error($curl));
    }

    $json = json_decode($result, true); // Decoding as an array for easier use

    if (isset($json['error'])) {  // Assuming the API returns an 'error' key on failure
        error_log('API Error: ' . $json['error']);
    }

    curl_close($curl);
}

/**
 * Handle cache purging after core, plugin, or theme updates.
 */
function purge_cache_after_any_update($upgrader_object, $options) {
    if ($options['action'] == 'update') {
        purge_cache_on_change();
    }
}

// Hook into content and settings changes
add_action('save_post', 'purge_cache_on_change');
add_action('edit_post', 'purge_cache_on_change');
add_action('delete_post', 'purge_cache_on_change');
add_action('updated_option', 'purge_cache_on_change');

// Hook into updates for core, plugins, and themes
add_action('upgrader_process_complete', 'purge_cache_after_any_update', 10, 2);
