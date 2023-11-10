<?php
/*
Plugin Name: Hosting Manager
Description: Manages caching and purges when changes are made. Also disables emails on wpstaging.io.
Version: 1.8
Author: Hosting Provider
*/

// Block Search Engines for wpstaging.io Domains
function block_search_engines_on_staging($value) {
    if (strpos(get_site_url(), 'wpstaging.io') !== false) {
        return '0';
    }
    return $value;
}

add_filter('pre_option_blog_public', 'block_search_engines_on_staging');

// API Cache Purge Functions

function get_api_key_from_file() {
    $path_parts = explode("/", $_SERVER['DOCUMENT_ROOT']);
    $unique_id = $path_parts[count($path_parts) - 2];
    $api_key_path = "/var/www/$unique_id/.etc/apikey";

    if (!file_exists($api_key_path)) {
        error_log("API key file not found at $api_key_path.");
        return false;
    }

    return trim(file_get_contents($api_key_path));
}

function purge_cache_on_change() {
    $api_key = get_api_key_from_file();

    if (!$api_key) {
        return;
    }

    $domain = get_site_url();

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

    $json = json_decode($result, true);

    if (isset($json['error'])) {
        error_log('API Error: ' . $json['error']);
    }

    curl_close($curl);
}

function purge_cache_on_content_change($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }
    // Array of post types to be cached; add to or modify this list as needed
    $allowed_post_types = array('post', 'page', 'product'); 

    if (!in_array($post->post_type, $allowed_post_types)) {
        return; 
    }

    if ('publish' !== $post->post_status) {
        return;
    }

    purge_cache_on_change();
}

function purge_cache_after_any_update($upgrader_object, $options) {
    if ($options['action'] == 'update') {
        purge_cache_on_change();
    }
}

add_action('save_post', 'purge_cache_on_content_change', 10, 3);
add_action('delete_post', 'purge_cache_on_change');
add_action('upgrader_process_complete', 'purge_cache_after_any_update', 10, 2);

// Mail Control Functions

function prevent_emails_on_staging($args) {
    if (strpos(get_site_url(), 'wpstaging.io') !== false) {
        $args['to'] = null;
    }
    return $args;
}
add_filter('wp_mail', 'prevent_emails_on_staging', 10, 1);

function stop_smtp_emails($phpmailer) {
    if (strpos(get_site_url(), 'wpstaging.io') !== false) {
if ('1' === get_option('disable_mail_setting', '1')) {
        $phpmailer->ClearAllRecipients();
}
   }
}
add_action('phpmailer_init', 'stop_smtp_emails');

if (!get_option('disable_mail_setting')) {
    add_option('disable_mail_setting', '1');
}

function add_disable_mail_settings_page() {
    if (strpos(get_site_url(), 'wpstaging.io') !== false) {
        add_options_page(
            'Disable Mail',
            'Disable Mail',
            'manage_options',
            'disable-mail',
            'disable_mail_settings_page_html'
        );
    }
}
add_action('admin_menu', 'add_disable_mail_settings_page');

function disable_mail_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    settings_errors('disable_mail_messages');
    ?>
    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('disable_mail');
            do_settings_sections('disable_mail');
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

function disable_mail_settings_init() {
    register_setting('disable_mail', 'disable_mail_setting');

    add_settings_section(
        'disable_mail_section',
        'Mail Settings',
        'disable_mail_section_cb',
        'disable_mail'
    );

    add_settings_field(
        'disable_mail_field',
        'Disable mail on staging',
        'disable_mail_field_cb',
        'disable_mail',
        'disable_mail_section'
    );
}
add_action('admin_init', 'disable_mail_settings_init');

function disable_mail_section_cb() {
    echo '<p>Choose whether to disable emails on the wpstaging.io domain.</p>';
}

function disable_mail_field_cb() {
    $setting = get_option('disable_mail_setting', '1');
    ?>
    <input type="checkbox" name="disable_mail_setting" value="1" <?php checked(1, $setting, true); ?>>
    <?php
}
