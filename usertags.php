<?php
/**
 * Plugin Name: User Tags
 * Description: Adds custom taxonomy "User Tags" to categorize users in WordPress.
 * Version: 1.0
 * Author: Priyanka Sharma
 */

if (!defined('ABSPATH')) exit;

// Register the "User Tags" taxonomy
function ut_register_user_tags_taxonomy() {
    register_taxonomy('user_tags', 'user', [
        'labels' => [
            'name' => 'User Tags',
            'singular_name' => 'User Tag',
            'menu_name' => 'User Tags',
        ],
        'public' => true,
        'show_admin_column' => true,
        'hierarchical' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'capabilities' => [
            'manage_terms' => 'manage_options',
            'edit_terms' => 'manage_options',
            'delete_terms' => 'manage_options',
            'assign_terms' => 'edit_users',
        ],
    ]);
}
add_action('init', 'ut_register_user_tags_taxonomy');

// Add User Tags Taxonomy Under Users Menu
function ut_add_user_tags_menu() {
    add_users_page('User Tags', 'User Tags', 'manage_options', 'edit-tags.php?taxonomy=user_tags');
}
add_action('admin_menu', 'ut_add_user_tags_menu');

// Display User Tags in Users List Table
function ut_add_user_tags_column($columns) {
    $columns['user_tags'] = 'User Tags';
    return $columns;
}
add_filter('manage_users_columns', 'ut_add_user_tags_column');

function ut_show_user_tags_column_content($value, $column_name, $user_id) {
    if ($column_name == 'user_tags') {
        $terms = wp_get_object_terms($user_id, 'user_tags', ['fields' => 'names']);
        return !empty($terms) ? implode(', ', $terms) : '-';
    }
    return $value;
}
add_filter('manage_users_custom_column', 'ut_show_user_tags_column_content', 10, 3);

// Show user tags meta box in user profile and add new user pages
function ut_user_tags_meta_box($user) {
    $user_tags = wp_get_object_terms($user->ID, 'user_tags', ['fields' => 'ids']);
    ?>
    <h2>User Tags</h2>
    <table class="form-table">
        <tr>
            <th><label for="user_tags">Assign Tags</label></th>
            <td>
                <select name="user_tags[]" multiple="multiple" id="user_tags_select" style="width: 50%;">
                    <?php foreach ($user_tags as $tag_id) { 
                        $tag = get_term($tag_id, 'user_tags');
                        if ($tag) {
                            echo '<option value="' . esc_attr($tag_id) . '" selected>' . esc_html($tag->name) . '</option>';
                        }
                    } ?>
                </select>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'ut_user_tags_meta_box');
add_action('edit_user_profile', 'ut_user_tags_meta_box');
add_action('user_new_form', 'ut_user_tags_meta_box');

// Save user tags in edit and add new user pages
function ut_save_user_tags($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    $tags = isset($_POST['user_tags']) ? array_map('intval', $_POST['user_tags']) : [];
    wp_set_object_terms($user_id, $tags, 'user_tags', false);
}
add_action('personal_options_update', 'ut_save_user_tags');
add_action('edit_user_profile_update', 'ut_save_user_tags');
add_action('user_register', 'ut_save_user_tags');

// Add user tag filter dropdown in Users admin page
function ut_filter_users_by_tags($which) {
    if ($which === 'top') {
        ?>
        <select name="user_tag_filter" id="user_tag_filter" style="width: 300px;">
            <option value="">Filter by User Tag</option>
        </select>
        <input type="submit" value="Filter" class="button">
        <?php
    }
}
add_action('restrict_manage_users', 'ut_filter_users_by_tags');

// Modify user query based on tag filter
function ut_filter_users_query($query) {
    global $pagenow;
    if ($pagenow === 'users.php' && isset($_GET['user_tag_filter']) && !empty($_GET['user_tag_filter'])) {
        $tag_id = intval($_GET['user_tag_filter']);
        $users = get_objects_in_term($tag_id, 'user_tags');
        $query->set('include', $users);
    }
}
add_action('pre_get_users', 'ut_filter_users_query');

// Enqueue Select2 for AJAX-powered user tag selection
function ut_enqueue_admin_scripts($hook) {
    if (in_array($hook, ['users.php', 'profile.php', 'user-edit.php', 'user-new.php'])) {
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js', ['jquery'], null, true);
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css');
        wp_enqueue_script('ut-custom-js', plugin_dir_url(__FILE__) . 'usertags.js', ['jquery', 'select2-js'], null, true);
        wp_localize_script('ut-custom-js', 'ut_ajax', ['ajaxurl' => admin_url('admin-ajax.php')]);
    }
}
add_action('admin_enqueue_scripts', 'ut_enqueue_admin_scripts');

// AJAX callback for fetching user tags dynamically
function ut_search_user_tags() {
    $search = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
    $tags = get_terms(['taxonomy' => 'user_tags', 'search' => $search, 'hide_empty' => false, 'number' => 20]);
    $results = [];
    foreach ($tags as $tag) {
        $results[] = ['id' => $tag->term_id, 'text' => $tag->name];
    }
    wp_send_json($results);
}
add_action('wp_ajax_ut_search_user_tags', 'ut_search_user_tags');