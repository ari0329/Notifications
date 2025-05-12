<?php
/*
 * Plugin Name: Custom Notifications Manager Test
 * Description: Test version of Custom Notifications Manager
 * Version: 3.0.7
 * Author: ari0329
 */
if (!defined('ABSPATH')) {
    exit;
}

// Ensure WordPress is fully loaded before executing critical code
function can_init_plugin() {
    // Define constants
    define('CAN_VERSION', '3.0.7');
    define('CAN_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('CAN_PLUGIN_URL', plugin_dir_url(__FILE__));
}
add_action('plugins_loaded', 'can_init_plugin');

function can_plugin_activation() {
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Custom Notifications Manager requires PHP 7.4 or higher. Your site is running PHP ' . PHP_VERSION . '. Please upgrade your PHP version.', 'custom-notifications-manager'),
            __('Plugin Activation Error', 'custom-notifications-manager'),
            array('back_link' => true)
        );
    }

    // Initialize can_modules option
    if (function_exists('is_multisite') && is_multisite()) {
        if (!get_site_option('can_modules')) {
            update_site_option('can_modules', array());
        }
    } else {
        if (!get_option('can_modules')) {
            update_option('can_modules', array());
        }
    }
    // Register post type and flush rewrite rules
    can_register_notifications_post_type();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'can_plugin_activation');

// Register the notifications post type
function can_register_notifications_post_type() {
    $labels = array(
        'name'               => __('Notifications', 'custom-notifications-manager'),
        'singular_name'      => __('Notification', 'custom-notifications-manager'),
        'menu_name'          => __('Notifications', 'custom-notifications-manager'),
        'add_new'            => __('Add Post', 'custom-notifications-manager'),
        'add_new_item'       => __('Add New Notification', 'custom-notifications-manager'),
        'edit_item'          => __('Edit Notification', 'custom-notifications-manager'),
        'new_item'           => __('New Notification', 'custom-notifications-manager'),
        'view_item'          => __('View Notification', 'custom-notifications-manager'),
        'search_items'       => __('Search Notifications', 'custom-notifications-manager'),
        'not_found'          => __('No notifications found', 'custom-notifications-manager'),
        'not_found_in_trash' => __('No notifications found in Trash', 'custom-notifications-manager'),
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => false,
        'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
        'menu_icon'           => 'dashicons-megaphone',
    );

    register_post_type('notifications', $args);

    register_post_meta('notifications', 'can_show_author', array(
        'type' => 'boolean',
        'description' => 'Show author name',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));

    register_post_meta('notifications', 'can_show_date', array(
        'type' => 'boolean',
        'description' => 'Show publication date',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));

    register_post_meta('notifications', 'can_show_site', array(
        'type' => 'boolean',
        'description' => 'Show site name',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));

    register_post_meta('notifications', 'can_homepage_request', array(
        'type' => 'boolean',
        'description' => 'Request to display on network homepage',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));

    register_post_meta('notifications', 'can_module', array(
        'type' => 'string',
        'description' => 'Associated module slug',
        'single' => true,
        'default' => '',
        'show_in_rest' => true,
    ));

    register_post_meta('notifications', 'can_homepage_approved', array(
        'type' => 'boolean',
        'description' => 'Approved for display on network homepage',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));

    register_post_meta('notifications', 'can_network_post_id', array(
        'type' => 'integer',
        'description' => 'Original post ID for synced notifications',
        'single' => true,
        'show_in_rest' => true,
    ));

    register_post_meta('notifications', 'can_network_site_id', array(
        'type' => 'integer',
        'description' => 'Original site ID for synced notifications',
        'single' => true,
        'show_in_rest' => true,
    ));
}
add_action('init', 'can_register_notifications_post_type');

function can_dynamic_post_type_labels($labels) {
    if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'notifications' && isset($_GET['module']) && !empty($_GET['module'])) {
        $module_slug = sanitize_title($_GET['module']);
        $modules = can_get_modules();
        $module_name = 'Notifications';

        foreach ($modules as $module) {
            if ($module['slug'] === $module_slug) {
                $module_name = $module['name'];
                break;
            }
        }

        $labels->name = $module_name;
        $labels->singular_name = $module_name;
        $labels->menu_name = $module_name;
        $labels->add_new = sprintf(__('Add New %s', 'custom-notifications-manager'), $module_name);
        $labels->add_new_item = sprintf(__('Add New %s', 'custom-notifications-manager'), $module_name);
        $labels->edit_item = sprintf(__('Edit %s', 'custom-notifications-manager'), $module_name);
        $labels->new_item = sprintf(__('New %s', 'custom-notifications-manager'), $module_name);
        $labels->view_item = sprintf(__('View %s', 'custom-notifications-manager'), $module_name);
        $labels->search_items = sprintf(__('Search %s', 'custom-notifications-manager'), $module_name);
        $labels->not_found = sprintf(__('No %s found', 'custom-notifications-manager'), strtolower($module_name));
        $labels->not_found_in_trash = sprintf(__('No %s found in Trash', 'custom-notifications-manager'), strtolower($module_name));
    }
    return $labels;
}
add_filter('post_type_labels_notifications', 'can_dynamic_post_type_labels');

function can_add_meta_boxes() {
    add_meta_box(
        'can_display_options',
        __('Display Options', 'custom-notifications-manager'),
        'can_display_options_callback',
        'notifications',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'can_add_meta_boxes');

function can_display_options_callback($post) {
    wp_nonce_field('can_save_meta_box_data', 'can_meta_box_nonce');

    $show_author = get_post_meta($post->ID, 'can_show_author', true) ? '1' : '0';
    $show_date = get_post_meta($post->ID, 'can_show_date', true) ? '1' : '0';
    $show_site = get_post_meta($post->ID, 'can_show_site', true) ? '1' : '0';
    $homepage_request = get_post_meta($post->ID, 'can_homepage_request', true) ? '1' : '0';
    $homepage_approved = get_post_meta($post->ID, 'can_homepage_approved', true) ? '1' : '0';
    $module = get_post_meta($post->ID, 'can_module', true);

    $is_new_post = empty($module) && in_array($post->post_status, ['auto-draft', 'draft']) && !isset($_GET['action']);
    $url_module = '';
    if (isset($_GET['module']) && !empty($_GET['module'])) {
        $url_module = sanitize_title($_GET['module']);
    }

    $modules = can_get_modules();
    $module_slugs = array_column($modules, 'slug');
    $is_valid_module = !empty($url_module) && in_array($url_module, $module_slugs);

    if ($is_new_post && empty($url_module)) {
        echo '<div class="notice notice-warning" style="margin-bottom: 10px;">';
        echo '<p>' . __('Please select a module from the admin menu to add a new notification. A module is required.', 'custom-notifications-manager') . '</p>';
        echo '</div>';
    }

    if ($is_new_post && $is_valid_module) {
        $module = $url_module;
    }

    ?>
    <p>
        <label>
            <input type="checkbox" name="can_show_author" value="1" <?php checked($show_author, '1'); ?> />
            <?php _e('Show Author Name', 'custom-notifications-manager'); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="can_show_date" value="1" <?php checked($show_date, '1'); ?> />
            <?php _e('Show Publication Date', 'custom-notifications-manager'); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="can_show_site" value="1" <?php checked($show_site, '1'); ?> />
            <?php _e('Show Site Name', 'custom-notifications-manager'); ?>
        </label>
    </p>
    <p>
        <label for="can_module"><?php _e('Module', 'custom-notifications-manager'); ?></label><br>
        <select name="can_module" id="can_module">
            <option value=""><?php _e('Select a module', 'custom-notifications-manager'); ?></option>
            <?php foreach ($modules as $mod): ?>
                <option value="<?php echo esc_attr($mod['slug']); ?>" <?php selected($module, $mod['slug']); ?>>
                    <?php echo esc_html($mod['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br>
        <small><?php _e('Select a module for this notification.', 'custom-notifications-manager'); ?></small>
    </p>
    <?php if (function_exists('is_multisite') && is_multisite()): ?>
    <hr>
    <p>
        <label>
            <input type="checkbox" name="can_homepage_request" value="1" <?php checked($homepage_request, '1'); ?> 
                   id="can_homepage_request_checkbox" />
            <?php _e('Request Homepage Display', 'custom-notifications-manager'); ?>
        </label>
        <br>
        <small><?php _e('Request this notification to be displayed on the network homepage', 'custom-notifications-manager'); ?></small>
    </p>
    <?php if (get_current_blog_id() == 1 && current_user_can('manage_options')): ?>
    <p>
        <label>
            <input type="checkbox" name="can_homepage_approved" value="1" <?php checked($homepage_approved, '1'); ?> />
            <?php _e('Approve for Homepage Display', 'custom-notifications-manager'); ?>
        </label>
        <br>
        <small><?php _e('Approve this notification for display on the network homepage (main site admin only)', 'custom-notifications-manager'); ?></small>
    </p>
    <?php endif; ?>
    <?php endif; ?>
    <?php
}

function can_save_meta_box_data($post_id) {
    if (!isset($_POST['can_meta_box_nonce']) || !wp_verify_nonce($_POST['can_meta_box_nonce'], 'can_save_meta_box_data')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    update_post_meta($post_id, 'can_show_author', isset($_POST['can_show_author']) ? '1' : '0');
    update_post_meta($post_id, 'can_show_date', isset($_POST['can_show_date']) ? '1' : '0');
    update_post_meta($post_id, 'can_show_site', isset($_POST['can_show_site']) ? '1' : '0');
    update_post_meta($post_id, 'can_module', isset($_POST['can_module']) ? sanitize_title($_POST['can_module']) : '');

    if (function_exists('is_multisite') && is_multisite()) {
        $old_homepage_request = get_post_meta($post_id, 'can_homepage_request', true);
        $new_homepage_request = isset($_POST['can_homepage_request']) ? '1' : '0';
        update_post_meta($post_id, 'can_homepage_request', $new_homepage_request);

        if (get_current_blog_id() != 1 && $new_homepage_request === '1' && $old_homepage_request !== '1') {
            $site_name = get_bloginfo('name');
            $post_title = get_the_title($post_id);
            $post_link = get_permalink($post_id);
            $author_id = get_post_field('post_author', $post_id);
            $author_name = get_the_author_meta('display_name', $author_id);

            $subject = sprintf(__('[%s] New Homepage Notification Request', 'custom-notifications-manager'), $site_name);
            $message = sprintf(__('A new notification has been requested for the network homepage:\n\nTitle: %s\nSite: %s\nAuthor: %s\nLink: %s\n\nPlease review this request in the Network Notifications section.', 'custom-notifications-manager'),
                $post_title,
                $site_name,
                $author_name,
                $post_link
            );

            switch_to_blog(1);
            $admin_email = get_option('admin_email');
            restore_current_blog();

            $mail_sent = wp_mail($admin_email, $subject, $message);
            if (!$mail_sent) {
                error_log('Failed to send homepage request email for post ID ' . $post_id);
            }
        }

        if (get_current_blog_id() == 1 && current_user_can('manage_options')) {
            $new_homepage_approved = isset($_POST['can_homepage_approved']) ? '1' : '0';
            $old_homepage_approved = get_post_meta($post_id, 'can_homepage_approved', true);
            update_post_meta($post_id, 'can_homepage_approved', $new_homepage_approved);

            // Update synced post status if approval status changed
            if ($new_homepage_approved !== $old_homepage_approved) {
                $network_post_id = get_post_meta($post_id, 'can_network_post_id', true);
                $network_site_id = get_post_meta($post_id, 'can_network_site_id', true);
                if ($network_post_id && $network_site_id) {
                    switch_to_blog(1);
                    wp_update_post([
                        'ID' => $post_id,
                        'post_status' => $new_homepage_approved === '1' ? 'publish' : 'pending'
                    ]);
                    restore_current_blog();
                }
            }
        }
    }
}
add_action('save_post', 'can_save_meta_box_data');

function can_get_modules() {
    if (function_exists('is_multisite') && is_multisite()) {
        $modules = get_site_option('can_modules', array());
    } else {
        $modules = get_option('can_modules', array());
    }
    return is_array($modules) ? $modules : array();
}

function can_generate_module_slug($name) {
    return sanitize_title($name);
}

function can_truncate_text($text, $length = 40) {
    if (mb_strlen($text) > $length) {
        return mb_substr($text, 0, $length) . '...';
    }
    return $text;
}

function can_add_module_menus() {
    $modules = can_get_modules();

    if (empty($modules)) {
        return;
    }

    foreach ($modules as $index => $module) {
        $menu_slug = 'notifications-' . $module['slug'];
        $menu_name = esc_html($module['name']);
        $all_posts_slug = 'edit.php?post_type=notifications&module=' . urlencode($module['slug']);

        add_menu_page(
            $menu_name,
            $menu_name,
            'edit_posts',
            $menu_slug,
            '',
            'dashicons-megaphone',
            25 + $index
        );

        add_submenu_page(
            $menu_slug,
            sprintf(__('All Posts - %s', 'custom-notifications-manager'), $menu_name),
            __('All Posts', 'custom-notifications-manager'),
            'edit_posts',
            $all_posts_slug
        );

        add_submenu_page(
            $menu_slug,
            sprintf(__('Add Post - %s', 'custom-notifications-manager'), $module['name']),
            __('Add Post', 'custom-notifications-manager'),
            'edit_posts',
            'post-new.php?post_type=notifications&module=' . urlencode($module['slug'])
        );

        global $submenu;
        if (isset($submenu[$menu_slug])) {
            global $menu;
            foreach ($menu as $key => $item) {
                if ($item[2] === $menu_slug) {
                    $menu[$key][2] = $all_posts_slug;
                    break;
                }
            }
        }
    }

    remove_menu_page('edit.php?post_type=notifications');
}
add_action('admin_menu', 'can_add_module_menus', 10);

// Add custom columns to notifications list table
function can_manage_notifications_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['shortcode'] = __('Shortcode', 'custom-notifications-manager');
            $new_columns['homepage_request'] = __('Homepage Request', 'custom-notifications-manager');
            $new_columns['homepage_approved'] = __('Homepage Approved', 'custom-notifications-manager');
        }
    }
    return $new_columns;
}
add_filter('manage_notifications_posts_columns', 'can_manage_notifications_columns');

function can_manage_notifications_custom_column($column, $post_id) {
    switch ($column) {
        case 'shortcode':
            $module = get_post_meta($post_id, 'can_module', true);
            $site_id = get_current_blog_id();
            if ($module) {
                echo '<code>[display_' . esc_attr($module) . '_notifications]</code><br>';
                echo '<code>[display_' . esc_attr($module) . '_notifications site="' . esc_attr($site_id) . '"]</code>';
            } else {
                echo '—';
            }
            break;
        case 'homepage_request':
            $homepage_request = get_post_meta($post_id, 'can_homepage_request', true);
            echo $homepage_request === '1' ? __('Requested', 'custom-notifications-manager') : __('Not Requested', 'custom-notifications-manager');
            break;
        case 'homepage_approved':
            $homepage_approved = get_post_meta($post_id, 'can_homepage_approved', true);
            echo $homepage_approved === '1' ? __('Approved', 'custom-notifications-manager') : __('Not Approved', 'custom-notifications-manager');
            break;
    }
}
add_action('manage_notifications_posts_custom_column', 'can_manage_notifications_custom_column', 10, 2);

// Add "Add Custom CSS" button next to "Add New" button on the notifications list page
function can_add_custom_css_button() {
    global $typenow;

    if ($typenow !== 'notifications' || !isset($_GET['module']) || empty($_GET['module'])) {
        return;
    }

    $module_slug = sanitize_title($_GET['module']);
    $modules = can_get_modules();
    $module_name = '';
    foreach ($modules as $module) {
        if ($module['slug'] === $module_slug) {
            $module_name = $module['name'];
            break;
        }
    }

    if (empty($module_name)) {
        return;
    }

    $css_page_url = admin_url('admin.php?page=notification-custom-css-' . $module_slug);

    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Find the "Add New" button
            var addNewButton = $('.page-title-action').first();
            if (addNewButton.length) {
                // Create the "Add Custom CSS" button
                var cssButton = $('<a href="<?php echo esc_url($css_page_url); ?>" class="page-title-action">Add Custom CSS</a>');
                // Insert the button after the "Add New" button
                addNewButton.after(cssButton);
            }
        });
    </script>
    <?php
}
add_action('admin_head', 'can_add_custom_css_button');

// Register the custom CSS admin page for each module
function can_add_custom_css_admin_pages() {
    $modules = can_get_modules();

    if (empty($modules)) {
        return;
    }

    foreach ($modules as $module) {
        $menu_slug = 'notification-custom-css-' . $module['slug'];
        $menu_name = sprintf(__('Custom CSS - %s', 'custom-notifications-manager'), esc_html($module['name']));

        // Add the custom CSS page as a hidden admin page (not in the menu)
        add_submenu_page(
            null, // Parent slug set to null to hide from menu
            $menu_name,
            $menu_name,
            'edit_posts',
            $menu_slug,
            function() use ($module) {
                can_custom_css_page_callback($module);
            }
        );
    }
}
add_action('admin_menu', 'can_add_custom_css_admin_pages', 20);

// Custom CSS page callback
function can_custom_css_page_callback($module) {
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'custom-notifications-manager'));
    }

    $option_group = 'can_settings_' . $module['slug'];
    $custom_css_option = $option_group . '_custom_css';
    $custom_site_name_option = $option_group . '_custom_site_name';

    // Handle form submission for both Custom CSS and Custom Site Name
    if (isset($_POST['can_custom_css_submit']) && check_admin_referer('can_custom_css_nonce_' . $module['slug'])) {
        // Save Custom CSS
        $custom_css = isset($_POST['can_custom_css']) ? wp_strip_all_tags($_POST['can_custom_css']) : '';
        update_option($custom_css_option, $custom_css);

        // Save Custom Site Name
        $custom_site_name = isset($_POST['can_custom_site_name']) ? sanitize_text_field($_POST['can_custom_site_name']) : '';
        update_option($custom_site_name_option, $custom_site_name);

        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'custom-notifications-manager') . '</p></div>';
    }

    $custom_css = get_option($custom_css_option, '');
    $custom_site_name = get_option($custom_site_name_option, '');
    $site_name = !empty($custom_site_name) ? $custom_site_name : get_bloginfo('name');
    $module_name = $module['name'];
    ?>
    <div class="wrap">
        <h1><?php printf(__('%s Custom CSS', 'custom-notifications-manager'), esc_html($module['name'])); ?></h1>
        
        <!-- Add Custom Site Name Section with Editable Field -->
        <form method="post" action="">
            <?php wp_nonce_field('can_custom_css_nonce_' . $module['slug']); ?>
            <div style="margin-bottom: 20px;">
                <h3 style="text-transform: uppercase; font-size: 14px; margin-bottom: 5px;"><?php _e('Custom Site Name', 'custom-notifications-manager'); ?></h3>
                <div style="border: 1px solid #ddd; padding: 10px; background: #f9f9f9; font-size: 16px; font-weight: bold;">
                    <input type="text" name="can_custom_site_name" id="can_custom_site_name" value="<?php echo esc_attr($site_name); ?>" style="width: 70%; font-size: 16px; font-weight: bold; border: none; background: none;" />
                    - <span style="color: red;"><?php echo esc_html($module_name); ?></span>
                </div>
                <p style="font-size: 12px; color: #666; margin-top: 5px;">
                    <?php printf(__('This name will be displayed when an author checks "Show Site Name" for an %s.', 'custom-notifications-manager'), esc_html($module_name)); ?>
                </p>
            </div>

            <!-- Custom CSS Section -->
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="can_custom_css"><?php _e('Custom CSS', 'custom-notifications-manager'); ?></label>
                    </th>
                    <td>
                        <textarea name="can_custom_css" id="can_custom_css" rows="10" class="large-text code"><?php echo esc_textarea($custom_css); ?></textarea>
                        <p class="description"><?php printf(__('Add custom CSS to style the [display_%s_notifications] shortcode output.', 'custom-notifications-manager'), esc_attr($module['slug'])); ?></p>
                        <p class="description"><?php printf(__('For list layout: .%s-notifications-list { background: #F5F5F5; padding: 15px; }', 'custom-notifications-manager'), esc_attr($module['slug'])); ?></p>
                        <p class="description"><?php printf(__('For grid layout: .%s-notifications-grid { background: #F5F5F5; padding: 15px; }', 'custom-notifications-manager'), esc_attr($module['slug'])); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="can_custom_css_submit" class="button button-primary" value="<?php _e('Save Settings', 'custom-notifications-manager'); ?>">
            </p>
        </form>
    </div>
    <?php
}

function can_modify_add_new_link($url, $path, $blog_id) {
    if ($path === 'post-new.php?post_type=notifications' && is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'notifications' && isset($_GET['module']) && !empty($_GET['module'])) {
        $module = sanitize_title($_GET['module']);
        $url = add_query_arg('module', $module, $url);
    }
    return $url;
}
add_filter('admin_url', 'can_modify_add_new_link', 10, 3);

function can_modify_admin_bar_add_new_link($wp_admin_bar) {
    if (!is_admin() || !isset($_GET['post_type']) || $_GET['post_type'] !== 'notifications' || !isset($_GET['module']) || empty($_GET['module'])) {
        return;
    }

    $module = sanitize_title($_GET['module']);
    $node = $wp_admin_bar->get_node('new-notifications');
    if ($node) {
        $node->href = add_query_arg('module', $module, $node->href);
        $wp_admin_bar->add_node($node);
    }
}
add_action('admin_bar_menu', 'can_modify_admin_bar_add_new_link', 999);

function can_filter_notifications_by_module($query) {
    if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'notifications') {
        if (isset($_GET['module']) && !empty($_GET['module'])) {
            $module = sanitize_title($_GET['module']);
            $query->set('meta_query', array(
                array(
                    'key' => 'can_module',
                    'value' => $module,
                    'compare' => '='
                )
            ));
        }
    }
}
add_action('pre_get_posts', 'can_filter_notifications_by_module');

function can_add_custom_image_size() {
    add_image_size('can-notification-thumb', 224, 120, true);
}
add_action('init', 'can_add_custom_image_size');

// Enqueue Custom CSS for Shortcodes
function can_enqueue_custom_css() {
    if (is_admin()) {
        return;
    }

    $modules = can_get_modules();
    if (empty($modules)) {
        return;
    }

    global $post;
    if (!$post || !isset($post->post_content)) {
        return;
    }

    foreach ($modules as $module) {
        $shortcode = 'display_' . $module['slug'] . '_notifications';
        if (has_shortcode($post->post_content, $shortcode)) {
            $option_group = 'can_settings_' . $module['slug'];
            $custom_css_option = $option_group . '_custom_css';
            $custom_css = get_option($custom_css_option, '');

            if (!empty($custom_css)) {
                wp_register_style('can-custom-css-' . $module['slug'], false);
                wp_enqueue_style('can-custom-css-' . $module['slug']);
                wp_add_inline_style('can-custom-css-' . $module['slug'], $custom_css);
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'can_enqueue_custom_css');

function can_register_module_shortcodes() {
    $modules = can_get_modules();
    if (empty($modules)) {
        return;
    }

    foreach ($modules as $module) {
        $shortcode_name = 'display_' . $module['slug'] . '_notifications';
        
        // Pass $shortcode_name to the callback function using the use keyword
        add_shortcode($shortcode_name, function($atts) use ($module, $shortcode_name) {
            $atts = shortcode_atts(array(
                'limit' => 5,
                'site' => '',
            ), $atts, $shortcode_name);

            $limit = intval($atts['limit']);
            $site_id = !empty($atts['site']) ? intval($atts['site']) : get_current_blog_id();
            $module_slug = $module['slug'];

            $switch_site = function_exists('is_multisite') && is_multisite() && !empty($atts['site']) && $site_id !== get_current_blog_id();
            if ($switch_site) {
                if (!get_blog_details($site_id)) {
                    return '<p>Invalid site ID.</p>';
                }
                switch_to_blog($site_id);
            }

            // Fetch the custom site name for this module
            $option_group = 'can_settings_' . $module['slug'];
            $custom_site_name = get_option($option_group . '_custom_site_name', '');
            $site_name = !empty($custom_site_name) ? $custom_site_name : get_bloginfo('name');

            $query_args = array(
                'post_type' => 'notifications',
                'posts_per_page' => $limit,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => 'can_module',
                        'value' => $module_slug,
                        'compare' => '='
                    )
                )
            );

            $query = new WP_Query($query_args);
            $output = '';
            $module_class_list = esc_attr($module_slug) . '-notifications-list';
            $module_class_grid = esc_attr($module_slug) . '-notifications-grid';

            if ($query->have_posts()) {
                if (isset($atts['site']) && $atts['site'] !== '') {
                    // Grid layout for shortcode with site attribute
                    $output .= '<div class="can-shortcode-container ' . $module_class_grid . '">';
                    $output .= '<div class="can-notifications-grid">';
                    while ($query->have_posts()) {
                        $query->the_post();
                        $post_id = get_the_ID();
                        $title = can_truncate_text(wp_strip_all_tags(get_the_title()), 35);
                        $excerpt = wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 15, '...');
                        $show_author = get_post_meta($post_id, 'can_show_author', true) ? '1' : '0';
                        $show_date = get_post_meta($post_id, 'can_show_date', true) ? '1' : '0';
                        $show_site = get_post_meta($post_id, 'can_show_site', true) ? '1' : '0';

                        $output .= '<div class="can-notification-card">';
                        if (has_post_thumbnail()) {
                            $output .= '<div class="can-notification-thumbnail">';
                            $output .= get_the_post_thumbnail(null, 'can-notification-thumb');
                            $output .= '</div>';
                        } else {
                            $output .= '<div class="can-notification-placeholder">COMING SOON</div>';
                        }

                        $output .= '<h5 class="can-notification-title"><a href="' . esc_url(get_permalink()) . '">' . esc_html($title) . '</a></h5>';
                        $output .= '<div class="can-notification-excerpt">' . wp_kses_post($excerpt) . '</div>';

                        // Add meta information (author, date, site) if checked
                        $meta_info = array();
                        if ($show_author === '1') {
                            $meta_info[] = 'Author: ' . esc_html(get_the_author());
                        }
                        if ($show_date === '1') {
                            $meta_info[] = 'Date: ' . esc_html(get_the_date());
                        }
                        if ($show_site === '1') {
                            $meta_info[] = 'Dept: ' . esc_html($site_name);
                        }
                        if (!empty($meta_info)) {
                            $output .= '<div class="can-notification-meta" style="font-size: 9px; margin: 0 15px 15px;">' . implode(' | ', $meta_info) . '</div>';
                        }

                        $output .= '</div>';
                    }
                    $output .= '</div>';

                    $output .= '<style>
                        .can-shortcode-container.' . $module_class_grid . ' .can-notifications-grid {
                            display: grid;
                            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                            gap: 20px;
                            margin: 20px 0;
                        }
                        .can-shortcode-container.' . $module_class_grid . ' .can-notification-card {
                            background: #fff;
                            border-radius: 4px;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                            overflow: hidden;
                            display: flex;
                            flex-direction: column;
                        }
                        .can-shortcode-container.' . $module_class_grid . ' .can-notification-placeholder {
                            background: #993333;
                            color: #fff;
                            text-align: center;
                            padding: 20px;
                            font-size: 14px;
                            height: 80px;
                            width: 224px;
                            line-height: 80px;
                        }
                        .can-shortcode-container.' . $module_class_grid . ' .can-notification-thumbnail img {
                            width: 224px !important;
                            height: 120px !important;
                            object-fit: cover;
                            display: block;
                        }
                        .can-shortcode-container.' . $module_class_grid . ' .can-notification-title {
                            margin: 15px 15px 10px;
                            font-size: 18px;
                        }
                        .can-shortcode-container.' . $module_class_grid . ' .can-notification-excerpt {
                            margin: 0 15px 15px;
                            font-size: 15px;
                            line-height: 1.5;
                            flex-grow: 1;
                        }
                        @media (max-width: 768px) {
                            .can-shortcode-container.' . $module_class_grid . ' .can-notifications-grid {
                                grid-template-columns: 1fr;
                            }
                        }
                    </style>';
                } else {
                    // List layout for shortcode without site attribute
                    $output .= '<div class="can-shortcode-container ' . $module_class_list . '">';
                    $output .= '<ul>';
                    while ($query->have_posts()) {
                        $query->the_post();
                        $post_id = get_the_ID();
                        $title = can_truncate_text(wp_strip_all_tags(get_the_title()), 40);
                        $show_author = get_post_meta($post_id, 'can_show_author', true) ? '1' : '0';
                        $show_date = get_post_meta($post_id, 'can_show_date', true) ? '1' : '0';
                        $show_site = get_post_meta($post_id, 'can_show_site', true) ? '1' : '0';

                        $output .= '<li>';
                        $output .= '<a href="' . esc_url(get_permalink()) . '">' . esc_html($title) . '</a>';

                        // Add meta information (author, date, site) if checked
                        $meta_info = array();
                        if ($show_author === '1') {
                            $meta_info[] = 'Author: ' . esc_html(get_the_author());
                        }
                        if ($show_date === '1') {
                            $meta_info[] = 'Date: ' . esc_html(get_the_date());
                        }
                        if ($show_site === '1') {
                            $meta_info[] = 'Dept: ' . esc_html($site_name);
                        }
                        if (!empty($meta_info)) {
                            $output .= '<div class="can-notification-meta" style="font-size: 9px;">' . implode(' | ', $meta_info) . '</div>';
                        }

                        $output .= '</li>';
                    }
                    $output .= '</ul>';

                    $output .= '<style>
                        .can-shortcode-container.' . $module_class_list . ' ul {
                            list-style: none;
                            padding: 0;
                            margin: 0;
                        }
                        .can-shortcode-container.' . $module_class_list . ' ul li {
                            position: relative;
                            padding-left: 20px;
                            margin-bottom: 10px;
                            background: #f9f9f9;
                            border-left: 4px solid #993333;
                            padding: 10px 20px;
                        }
                        .can-shortcode-container.' . $module_class_list . ' ul li::before {
                            content: "";
                            display: inline-block;
                            width: 0;
                            height: 0;
                            border-top: 5px solid transparent;
                            border-bottom: 5px solid transparent;
                            border-left: 5px solid #6c757d;
                            position: absolute;
                            left: 5px;
                            top: 50%;
                            transform: translateY(-50%);
                        }
                        .can-shortcode-container.' . $module_class_list . ' ul li a {
                            color: #333;
                            text-decoration: none;
                            font-weight: 500;
                        }
                        .can-shortcode-container.' . $module_class_list . ' ul li a:hover {
                            text-decoration: underline;
                            color: #993333;
                        }
                        .can-shortcode-container.' . $module_class_list . ' .can-notification-meta {
                            color: #666;
                            margin-top: 5px;
                        }
                    </style>';
                }

                $output .= '</div>';
            } else {
                $output .= '<div class="can-shortcode-container ' . (isset($atts['site']) && $atts['site'] !== '' ? $module_class_grid : $module_class_list) . '">';
                $output .= '<p>No notifications found for this module.</p>';
                $output .= '</div>';
            }

            wp_reset_postdata();
            if ($switch_site) {
                restore_current_blog();
            }

            return $output;
        });
    }
}
add_action('init', 'can_register_module_shortcodes');

function can_display_network_notifications_shortcode($atts) {
    if (!function_exists('is_multisite') || !is_multisite()) {
        return '<p>This shortcode is designed for multisite networks only.</p>';
    }

    $atts = shortcode_atts(array(
        'limit' => 5,
        'sites' => '',
        'module' => '',
    ), $atts, 'display_network_notifications');

    $limit = intval($atts['limit']);
    $sites_list = !empty($atts['sites']) ? array_map('intval', explode(',', $atts['sites'])) : array();
    $module = sanitize_title($atts['module']);
    if (empty($module)) {
        return '<p>Please specify a module parameter (e.g., module="career").</p>';
    }

    // Fetch the custom site name for this module
    $option_group = 'can_settings_' . $module;
    $custom_site_name = get_option($option_group . '_custom_site_name', '');

    $sites = get_sites(array(
        'site__in' => !empty($sites_list) ? $sites_list : array(),
    ));

    ob_start();
    $module_class = esc_attr($module) . '-notifications';
    echo '<div class="can-network-notifications ' . $module_class . '">';

    if (!empty($sites)) {
        $all_notifications = array();
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            // Use custom site name if available, otherwise fall back to bloginfo
            $site_name = !empty($custom_site_name) ? $custom_site_name : get_bloginfo('name');

            $query_args = array(
                'post_type' => 'notifications',
                'posts_per_page' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => 'can_module',
                        'value' => $module,
                        'compare' => '='
                    )
                )
            );

            $query = new WP_Query($query_args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $all_notifications[] = array(
                        'ID' => $post_id,
                        'title' => get_the_title(),
                        'permalink' => get_permalink(),
                        'date' => get_the_date('Y-m-d H:i:s'),
                        'excerpt' => get_the_excerpt(),
                        'author' => get_the_author(),
                        'site_name' => $site_name,
                        'site_id' => $site->blog_id,
                        'show_author' => get_post_meta($post_id, 'can_show_author', true),
                        'show_date' => get_post_meta($post_id, 'can_show_date', true),
                        'show_site' => get_post_meta($post_id, 'can_show_site', true),
                        'timestamp' => get_post_time('U', true),
                        'has_thumbnail' => has_post_thumbnail(),
                        'thumbnail_html' => get_the_post_thumbnail(null, 'thumbnail')
                    );
                }
            }

            wp_reset_postdata();
            restore_current_blog();
        }

        usort($all_notifications, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        $all_notifications = array_slice($all_notifications, 0, $limit);

        if (!empty($all_notifications)) {
            foreach ($all_notifications as $notification) {
                echo '<div class="can-notification-item">';
                if ($notification['has_thumbnail']) {
                    echo '<div class="can-notification-thumbnail">';
                    echo $notification['thumbnail_html'];
                    echo '</div>';
                }

                $title = can_truncate_text($notification['title'], 40);
                echo '<h3 class="can-notification-title"><a href="' . esc_url($notification['permalink']) . '">' . esc_html($title) . '</a></h3>';

                $meta_info = array();
                if ($notification['show_author']) {
                    $meta_info[] = 'Author: ' . esc_html($notification['author']);
                }
                if ($notification['show_date']) {
                    $meta_info[] = 'Date: ' . date_i18n(get_option('date_format'), $notification['timestamp']);
                }
                if ($notification['show_site']) {
                    $meta_info[] = 'Dept: ' . esc_html($notification['site_name']);
                }
                if (!empty($meta_info)) {
                    echo '<div style="font-size: 9px;" class="can-notification-meta">' . implode(' | ', $meta_info) . '</div>';
                }

                $excerpt = can_truncate_text($notification['excerpt'], 100);
                echo '<div class="can-notification-excerpt">' . wp_kses_post($excerpt) . '</div>';
                echo '<a href="' . esc_url($notification['permalink']) . '" class="can-read-more">Read More</a>';
                echo '</div>';
            }
        } else {
            echo '<p>No notifications found.</p>';
        }
    } else {
        echo '<p>No sites found.</p>';
    }

    echo '</div>';
    return ob_get_clean();
}
add_shortcode('display_network_notifications', 'can_display_network_notifications_shortcode');

function can_add_network_admin_menu() {
    if (!function_exists('is_multisite') || !is_multisite() || !is_super_admin()) {
        return;
    }
    add_menu_page(
        __('Network Notifications', 'custom-notifications-manager'),
        __('Network Notifications', 'custom-notifications-manager'),
        'manage_network',
        'network-notifications',
        'can_network_notifications_page',
        'dashicons-megaphone',
        25
    );
    add_submenu_page(
        'network-notifications',
        __('Manage Modules', 'custom-notifications-manager'),
        __('Modules', 'custom-notifications-manager'),
        'manage_network',
        'network-notifications-modules',
        'can_network_modules_page'
    );
}
add_action('network_admin_menu', 'can_add_network_admin_menu');

function can_network_modules_page() {
    if (!is_super_admin()) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $modules = can_get_modules();

    if (isset($_POST['can_module_submit']) && check_admin_referer('can_module_nonce')) {
        $module_name = sanitize_text_field($_POST['can_module_name']);
        if (!empty($module_name)) {
            $module_slug = can_generate_module_slug($module_name);
            $existing_slugs = array_column($modules, 'slug');

            if (!in_array($module_slug, $existing_slugs)) {
                $modules[] = array(
                    'id' => uniqid(),
                    'name' => $module_name,
                    'slug' => $module_slug
                );
                if (is_multisite()) {
                    update_site_option('can_modules', $modules);
                } else {
                    update_option('can_modules', $modules);
                }
                echo '<div class="notice notice-success is-dismissible"><p>Module added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Module name already exists!</p></div>';
            }
        }
    }

    if (isset($_POST['can_module_edit_submit']) && check_admin_referer('can_module_edit_nonce')) {
        $module_id = sanitize_text_field($_POST['can_module_id']);
        $module_name = sanitize_text_field($_POST['can_module_name']);
        if (!empty($module_name) && !empty($module_id)) {
            $module_slug = can_generate_module_slug($module_name);
            $existing_slugs = array_column($modules, 'slug');

            $duplicate = false;
            foreach ($modules as $mod) {
                if ($mod['id'] !== $module_id && $mod['slug'] === $module_slug) {
                    $duplicate = true;
                    break;
                }
            }

            if ($duplicate) {
                echo '<div class="notice notice-error is-dismissible"><p>Module name already exists!</p></div>';
            } else {
                foreach ($modules as &$module) {
                    if ($module['id'] === $module_id) {
                        $module['name'] = $module_name;
                        $module['slug'] = $module_slug;
                        break;
                    }
                }
                if (is_multisite()) {
                    update_site_option('can_modules', $modules);
                } else {
                    update_option('can_modules', $modules);
                }
                echo '<div class="notice notice-success is-dismissible"><p>Module updated successfully!</p></div>';
            }
        }
    }

    if (isset($_POST['can_module_delete']) && check_admin_referer('can_module_delete_nonce')) {
        $module_id = sanitize_text_field($_POST['can_module_id']);
        $modules = array_filter($modules, function($module) use ($module_id) {
            return $module['id'] !== $module_id;
        });
        if (is_multisite()) {
            update_site_option('can_modules', array_values($modules));
        } else {
            update_option('can_modules', array_values($modules));
        }
        echo '<div class="notice notice-success is-dismissible"><p>Module deleted successfully!</p></div>';
    }

    $edit_module = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['module_id'])) {
        $module_id = sanitize_text_field($_GET['module_id']);
        foreach ($modules as $module) {
            if ($module['id'] === $module_id) {
                $edit_module = $module;
                break;
            }
        }
    }

    ?>
    <div class="wrap">
        <h1><?php _e('Manage Notification Modules', 'custom-notifications-manager'); ?></h1>

        <?php if ($edit_module): ?>
            <h2><?php _e('Edit Module', 'custom-notifications-manager'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('can_module_edit_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="can_module_name"><?php _e('Module Name', 'custom-notifications-manager'); ?></label></th>
                        <td>
                            <input type="text" name="can_module_name" id="can_module_name" class="regular-text" value="<?php echo esc_attr($edit_module['name']); ?>" required>
                            <input type="hidden" name="can_module_id" value="<?php echo esc_attr($edit_module['id']); ?>">
                            <p class="description"><?php _e('Edit the name of the module.', 'custom-notifications-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="can_module_edit_submit" class="button button-primary" value="<?php _e('Update Module', 'custom-notifications-manager'); ?>">
                    <a href="<?php echo admin_url('network/admin.php?page=network-notifications-modules'); ?>" class="button button-secondary"><?php _e('Cancel', 'custom-notifications-manager'); ?></a>
                </p>
            </form>
        <?php else: ?>
            <h2><?php _e('Add New Module', 'custom-notifications-manager'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('can_module_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="can_module_name"><?php _e('Module Name', 'custom-notifications-manager'); ?></label></th>
                        <td>
                            <input type="text" name="can_module_name" id="can_module_name" class="regular-text" required>
                            <p class="description"><?php _e('Enter a unique name for the module.', 'custom-notifications-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="can_module_submit" class="button button-primary" value="<?php _e('Add Module', 'custom-notifications-manager'); ?>">
                </p>
            </form>
        <?php endif; ?>

        <h2><?php _e('Existing Modules', 'custom-notifications-manager'); ?></h2>
        <?php if (!empty($modules)): ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Module Name', 'custom-notifications-manager'); ?></th>
                        <th><?php _e('Module Slug', 'custom-notifications-manager'); ?></th>
                        <th><?php _e('Shortcode', 'custom-notifications-manager'); ?></th>
                        <th><?php _e('Actions', 'custom-notifications-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $module): ?>
                        <tr>
                            <td><?php echo esc_html($module['name']); ?></td>
                            <td><?php echo esc_html($module['slug']); ?></td>
                            <td><code>[display_<?php echo esc_attr($module['slug']); ?>_notifications]</code></td>
                            <td>
                                <a href="<?php echo admin_url('network/admin.php?page=network-notifications-modules&action=edit&module_id=' . esc_attr($module['id'])); ?>" class="button button-secondary"><?php _e('Edit', 'custom-notifications-manager'); ?></a>
                                <form method="post" action="" style="display:inline;">
                                    <?php wp_nonce_field('can_module_delete_nonce'); ?>
                                    <input type="hidden" name="can_module_id" value="<?php echo esc_attr($module['id']); ?>">
                                    <input type="submit" name="can_module_delete" class="button button-secondary" value="<?php _e('Delete', 'custom-notifications-manager'); ?>" onclick="return confirm('<?php _e('Are you sure you want to delete this module?', 'custom-notifications-manager'); ?>');">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No modules found. Add a new module above.', 'custom-notifications-manager'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

function can_network_notifications_page() {
    if (!is_super_admin()) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (isset($_POST['can_approve_selected']) && isset($_POST['can_notifications']) && is_array($_POST['can_notifications'])) {
        $approved_count = 0;
        foreach ($_POST['can_notifications'] as $notification) {
            list($site_id, $post_id) = explode('_', $notification);
            $site_id = intval($site_id);
            $post_id = intval($post_id);
            if ($site_id > 0 && $post_id > 0) {
                switch_to_blog($site_id);
                update_post_meta($post_id, 'can_homepage_approved', '1');
                wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
                restore_current_blog();
                $approved_count++;
            }
        }
        if ($approved_count > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                sprintf(_n('%d notification approved.', '%d notifications approved.', $approved_count, 'custom-notifications-manager'), $approved_count) . 
                '</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h1><?php _e('Network Notifications Overview', 'custom-notifications-manager'); ?></h1>
        <h2><?php _e('Homepage Notification Requests', 'custom-notifications-manager'); ?></h2>
        <p><?php _e('These notifications have been requested for display on the network homepage.', 'custom-notifications-manager'); ?></p>

        <?php
        $sites = get_sites();
        $homepage_requests = array();

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $query_args = array(
                'post_type' => 'notifications',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'can_homepage_request',
                        'value' => '1',
                        'compare' => '='
                    )
                )
            );

            $query = new WP_Query($query_args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $homepage_approved = get_post_meta($post_id, 'can_homepage_approved', true);
                    $homepage_requests[] = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'site_id' => $site->blog_id,
                        'site_name' => get_bloginfo('name'),
                        'date' => get_the_date(),
                        'permalink' => get_permalink(),
                        'author' => get_the_author(),
                        'approved' => $homepage_approved,
                        'module' => get_post_meta($post_id, 'can_module', true)
                    );
                }
            }

            wp_reset_postdata();
            restore_current_blog();
        }

        if (!empty($homepage_requests)) {
            ?>
            <form method="post" action="">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="can-select-all"></th>
                            <th><?php _e('Title', 'custom-notifications-manager'); ?></th>
                            <th><?php _e('Module', 'custom-notifications-manager'); ?></th>
                            <th><?php _e('Site', 'custom-notifications-manager'); ?></th>
                            <th><?php _e('Author', 'custom-notifications-manager'); ?></th>
                            <th><?php _e('Date', 'custom-notifications-manager'); ?></th>
                            <th><?php _e('Status', 'custom-notifications-manager'); ?></th>
                            <th><?php _e('Actions', 'custom-notifications-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $modules = can_get_modules();
                        $module_names = array_column($modules, 'name', 'slug');
                        foreach ($homepage_requests as $request): 
                        ?>
                        <tr>
                            <td>
                                <?php if ($request['approved'] != '1'): ?>
                                <input type="checkbox" name="can_notifications[]" value="<?php echo $request['site_id'] . '_' . $request['id']; ?>">
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($request['title']); ?></td>
                            <td><?php echo esc_html(isset($module_names[$request['module']]) ? $module_names[$request['module']] : 'None'); ?></td>
                            <td><?php echo esc_html($request['site_name']); ?></td>
                            <td><?php echo esc_html($request['author']); ?></td>
                            <td><?php echo esc_html($request['date']); ?></td>
                            <td>
                                <?php if ($request['approved'] == '1'): ?>
                                <span style="color:green;"><span class="dashicons dashicons-yes"></span> <?php _e('Approved', 'custom-notifications-manager'); ?></span>
                                <?php else: ?>
                                <span style="color:orange;"><span class="dashicons dashicons-clock"></span> <?php _e('Pending Approval', 'custom-notifications-manager'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($request['permalink']); ?>" target="_blank"><?php _e('View', 'custom-notifications-manager'); ?></a>
                                <?php if ($request['approved'] != '1'): ?>
                                | <a href="<?php echo get_admin_url($request['site_id'], 'post.php?post=' . $request['id'] . '&action=edit'); ?>" target="_blank"><?php _e('Edit', 'custom-notifications-manager'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="tablenav bottom">
                    <div class="alignleft actions">
                        <input type="submit" name="can_approve_selected" id="can_approve_selected" class="button button-primary" value="<?php _e('Approve Selected', 'custom-notifications-manager'); ?>">
                    </div>
                </div>
            </form>

            <script>
            jQuery(document).ready(function($) {
                $('#can-select-all').on('change', function() {
                    $('input[name="can_notifications[]"]').prop('checked', $(this).prop('checked'));
                });
            });
            </script>

            <h3><?php _e('Approved Homepage Notifications', 'custom-notifications-manager'); ?></h3>
            <p><?php _e('These notifications are currently approved for display on the network homepage.', 'custom-notifications-manager'); ?></p>

            <?php
            $approved_count = 0;
            foreach ($homepage_requests as $request) {
                if ($request['approved'] == '1') {
                    $approved_count++;
                }
            }

            if ($approved_count > 0) {
                ?>
                <p><?php echo sprintf(_n('There is %d approved notification.', 'There are %d approved notifications.', $approved_count, 'custom-notifications-manager'), $approved_count); ?></p>
                <?php
            } else {
                ?>
                <p><?php _e('No notifications have been approved yet.', 'custom-notifications-manager'); ?></p>
                <?php
            }
            ?>

            <h3><?php _e('Homepage Display Shortcode', 'custom-notifications-manager'); ?></h3>
            <p><?php _e('Use this shortcode on your network homepage to display the approved notifications:', 'custom-notifications-manager'); ?></p>
            <code>[display_homepage_notifications module="career" limit="3"]</code>
            <p><?php _e('Replace "career" with the desired module slug.', 'custom-notifications-manager'); ?></p>
            <?php
        } else {
            echo '<p>' . __('No homepage notification requests found.', 'custom-notifications-manager') . '</p>';
        }
        ?>
    </div>
    <?php
}

function can_display_homepage_notifications_shortcode($atts) {
    if (!function_exists('is_multisite') || !is_multisite()) {
        return '<p>This shortcode is designed for multisite networks only.</p>';
    }

    $atts = shortcode_atts(array(
        'limit' => 5,
        'module' => '',
    ), $atts, 'display_homepage_notifications');

    $limit = intval($atts['limit']);
    $module = sanitize_title($atts['module']);
    if (empty($module)) {
        return '<p>Please specify a module parameter (e.g., module="career").</p>';
    }

    // Fetch the custom site name for this module
    $option_group = 'can_settings_' . $module;
    $custom_site_name = get_option($option_group . '_custom_site_name', '');

    ob_start();
    $module_class = esc_attr($module) . '-notifications';
    echo '<div class="can-homepage-notifications ' . $module_class . '">';

    $sites = get_sites();
    $approved_notifications = array();

    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);

        // Use custom site name if available, otherwise fall back to bloginfo
        $site_name = !empty($custom_site_name) ? $custom_site_name : get_bloginfo('name');

        $query_args = array(
            'post_type' => 'notifications',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => 'can_homepage_approved',
                    'value' => '1',
                    'compare' => '='
                ),
                array(
                    'key' => 'can_module',
                    'value' => $module,
                    'compare' => '='
                )
            )
        );

        $query = new WP_Query($query_args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $approved_notifications[] = array(
                    'ID' => $post_id,
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'date' => get_the_date('Y-m-d H:i:s'),
                    'excerpt' => get_the_excerpt(),
                    'author' => get_the_author(),
                    'site_name' => $site_name,
                    'site_id' => $site->blog_id,
                    'show_author' => get_post_meta($post_id, 'can_show_author', true),
                    'show_date' => get_post_meta($post_id, 'can_show_date', true),
                    'show_site' => get_post_meta($post_id, 'can_show_site', true),
                    'timestamp' => get_post_time('U', true),
                    'has_thumbnail' => has_post_thumbnail(),
                    'thumbnail_html' => get_the_post_thumbnail(null, 'thumbnail')
                );
            }
        }

        wp_reset_postdata();
        restore_current_blog();
    }

    usort($approved_notifications, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    $approved_notifications = array_slice($approved_notifications, 0, $limit);

    if (!empty($approved_notifications)) {
        echo '<div class="can-notifications-grid">';
        foreach ($approved_notifications as $notification) {
            echo '<div class="can-notification-card">';
            if ($notification['has_thumbnail']) {
                echo '<div class="can-notification-thumbnail">';
                echo $notification['thumbnail_html'];
                echo '</div>';
            }

            echo '<h5 class="can-notification-title"><a href="' . esc_url($notification['permalink']) . '">' . esc_html($notification['title']) . '</a></h5>';

            $meta_info = array();
            if ($notification['show_author']) {
                $meta_info[] = 'Author: ' . esc_html($notification['author']);
            }
            if ($notification['show_date']) {
                $meta_info[] = 'Date: ' . date_i18n(get_option('date_format'), $notification['timestamp']);
            }
            if ($notification['show_site']) {
                $meta_info[] = 'Dept: ' . esc_html($notification['site_name']);
            }
            if (!empty($meta_info)) {
                echo '<div style="font-size: 9px;" class="can-notification-meta">' . implode(' | ', $meta_info) . '</div>';
            }

            echo '<div class="can-notification-excerpt">' . wp_kses_post(can_truncate_text($notification['excerpt'], 100)) . '</div>';
            echo '<a href="' . esc_url($notification['permalink']) . '" class="can-read-more">Read More</a>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p>No approved homepage notifications found.</p>';
    }

    echo '</div>';

    echo '<style>
        .can-homepage-notifications.' . $module_class . ' .can-notifications-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .can-homepage-notifications.' . $module_class . ' .can-notification-card {
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .can-homepage-notifications.' . $module_class . ' .can-notification-thumbnail img {
            width: 100%;
            height: auto;
            display: block;
        }
        .can-homepage-notifications.' . $module_class . ' .can-notification-title {
            margin: 15px 15px 10px;
            font-size: 18px;
        }
        .can-homepage-notifications.' . $module_class . ' .can-notification-meta {
            margin: 0 15px 10px;
            font-size: 9px;
            color: #666;
        }
        .can-homepage-notifications.' . $module_class . ' .can-notification-excerpt {
            margin: 0 15px 15px;
            font-size: 15px;
            line-height: 1.5;
            flex-grow: 1;
        }
        .can-homepage-notifications.' . $module_class . ' .can-read-more {
            display: inline-block;
            margin: 0 15px 15px;
            padding: 8px 15px;
            background: #993333;
            color: #fff;
            text-decoration: none;
            border-radius: 3px;
            font-size: 14px;
            align-self: flex-start;
        }
        .can-homepage-notifications.' . $module_class . ' .can-read-more:hover {
            background: #7a2828;
        }
        @media (max-width: 768px) {
            .can-homepage-notifications.' . $module_class . ' .can-notifications-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>';

    return ob_get_clean();
}
add_shortcode('display_homepage_notifications', 'can_display_homepage_notifications_shortcode');

function can_sync_notifications_to_main($post_id, $post, $update) {
    if ($post->post_type !== 'notifications' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || $post->post_status !== 'publish') {
        return;
    }

    $homepage_request = get_post_meta($post_id, 'can_homepage_request', true);
    if ($homepage_request != '1') {
        return;
    }

    $current_blog_id = get_current_blog_id();
    if ($current_blog_id == 1) {
        return;
    }

    if (!function_exists('is_multisite') || !is_multisite()) {
        error_log('Custom Notifications Manager: Multisite not enabled for syncing post ID ' . $post_id);
        return;
    }

    if (!function_exists('media_handle_sideload')) {
        error_log('Custom Notifications Manager: media_handle_sideload function not available for post ID ' . $post_id);
        return;
    }

    $all_meta = get_post_meta($post_id);
    $show_author = isset($all_meta['can_show_author'][0]) ? $all_meta['can_show_author'][0] : '0';
    $show_date = isset($all_meta['can_show_date'][0]) ? $all_meta['can_show_date'][0] : '0';
    $show_site = isset($all_meta['can_show_site'][0]) ? $all_meta['can_show_site'][0] : '0';
    $module = isset($all_meta['can_module'][0]) ? $all_meta['can_module'][0] : '';

    switch_to_blog(1);

    $existing_posts = get_posts([
        'post_type'   => 'notifications',
        'meta_query'  => [
            [
                'key'   => 'can_network_post_id',
                'value' => $post_id,
                'compare' => '='
            ],
            [
                'key'   => 'can_network_site_id',
                'value' => $current_blog_id,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1,
        'post_status'    => 'any'
    ]);

    if (!empty($existing_posts)) {
        $main_post_id = $existing_posts[0]->ID;
        $result = wp_update_post([
            'ID'           => $main_post_id,
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status'  => get_post_meta($post_id, 'can_homepage_approved', true) === '1' ? 'publish' : 'pending'
        ], true);
        if (is_wp_error($result)) {
            error_log('Custom Notifications Manager: Failed to update synced post ID ' . $main_post_id . ': ' . $result->get_error_message());
        }
    } else {
        $main_post_id = wp_insert_post([
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status'  => 'pending',
            'post_type'    => 'notifications',
            'post_author'  => 1,
        ], true);

        if (is_wp_error($main_post_id)) {
            error_log('Custom Notifications Manager: Failed to insert synced post for post ID ' . $post_id . ': ' . $main_post_id->get_error_message());
            restore_current_blog();
            return;
        }

        update_post_meta($main_post_id, 'can_network_post_id', $post_id);
        update_post_meta($main_post_id, 'can_network_site_id', $current_blog_id);
    }

    update_post_meta($main_post_id, 'can_show_author', $show_author);
    update_post_meta($main_post_id, 'can_show_date', $show_date);
    update_post_meta($main_post_id, 'can_show_site', $show_site);
    update_post_meta($main_post_id, 'can_module', $module);
    update_post_meta($main_post_id, 'can_homepage_request', '1');
    update_post_meta($main_post_id, 'can_homepage_approved', get_post_meta($post_id, 'can_homepage_approved', true));

    // Handle featured image
    if (has_post_thumbnail($post_id)) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        $thumbnail_url = wp_get_attachment_url($thumbnail_id);

        if ($thumbnail_url) {
            $temp_file = download_url($thumbnail_url);
            if (!is_wp_error($temp_file)) {
                $file_array = [
                    'name' => basename($thumbnail_url),
                    'tmp_name' => $temp_file,
                ];

                $new_thumbnail_id = media_handle_sideload($file_array, 0, false);

                if (!is_wp_error($new_thumbnail_id)) {
                    set_post_thumbnail($main_post_id, $new_thumbnail_id);
                } else {
                    error_log('Custom Notifications Manager: Failed to sideload thumbnail for post ID ' . $main_post_id . ': ' . $new_thumbnail_id->get_error_message());
                }

                @unlink($temp_file);
            } else {
                error_log('Custom Notifications Manager: Failed to download thumbnail for post ID ' . $main_post_id . ': ' . $temp_file->get_error_message());
            }
        }
    } else {
        delete_post_thumbnail($main_post_id);
    }

    restore_current_blog();
}
add_action('save_post', 'can_sync_notifications_to_main', 20, 3);

function can_delete_synced_notification($post_id) {
    if (get_post_type($post_id) !== 'notifications') {
        return;
    }

    $network_post_id = get_post_meta($post_id, 'can_network_post_id', true);
    $network_site_id = get_post_meta($post_id, 'can_network_site_id', true);

    if ($network_post_id && $network_site_id) {
        switch_to_blog(1);
        wp_delete_post($network_post_id, true);
        restore_current_blog();
    }
}
add_action('before_delete_post', 'can_delete_synced_notification');

function can_handle_notification_trash($post_id) {
    if (get_post_type($post_id) !== 'notifications') {
        return;
    }

    $network_post_id = get_post_meta($post_id, 'can_network_post_id', true);
    $network_site_id = get_post_meta($post_id, 'can_network_site_id', true);

    if ($network_post_id && $network_site_id) {
        switch_to_blog(1);
        wp_trash_post($network_post_id);
        restore_current_blog();
    }
}
add_action('wp_trash_post', 'can_handle_notification_trash');

function can_handle_notification_untrash($post_id) {
    if (get_post_type($post_id) !== 'notifications') {
        return;
    }

    $network_post_id = get_post_meta($post_id, 'can_network_post_id', true);
    $network_site_id = get_post_meta($post_id, 'can_network_site_id', true);

    if ($network_post_id && $network_site_id) {
        switch_to_blog(1);
        wp_untrash_post($network_post_id);
        restore_current_blog();
    }
}
add_action('untrash_post', 'can_handle_notification_untrash');

function can_plugin_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'can_plugin_deactivation');