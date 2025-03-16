<?php
/**
 * Plugin Name: User Tags
 * Description: Adds a custom taxonomy 'User Tags' for WordPress users with filtering and AJAX search.
 * Version: 1.0.0
 * Author: Priyanka Sharma
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Register Custom Taxonomy: User Tags
 */
function ut_register_user_tags_taxonomy() {
    register_taxonomy('user_tags', 'user', array(
        'labels' => array(
            'name' => 'User Tags',
            'singular_name' => 'User Tag',
            'menu_name' => 'User Tags',
        ),
        'public' => true,
        'show_admin_column' => true,
        'hierarchical' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'capabilities' => array(
            'manage_terms' => 'manage_options',
            'edit_terms' => 'manage_options',
            'delete_terms' => 'manage_options',
            'assign_terms' => 'edit_users',
        ),
    ));
}
add_action('init', 'ut_register_user_tags_taxonomy');

/**
 * Add User Tags Management Page Under Users Menu
 */
function ut_add_user_tags_menu() {
    add_users_page('User Tags', 'User Tags', 'manage_options', 'edit-tags.php?taxonomy=user_tags');
}
add_action('admin_menu', 'ut_add_user_tags_menu');

/**
 * Add User Tags Field to User Profile while adding and editing users
 */
function ut_add_user_tags_field($user) {
    $terms = get_terms(array('taxonomy' => 'user_tags', 'hide_empty' => false));
    $user_terms = is_object($user) ? wp_get_object_terms($user->ID, 'user_tags', array('fields' => 'ids')) : array();
    ?>
    <h2>User Tags</h2>
    <table class="form-table">
        <tr>
            <th><label for="user_tags">Tags</label></th>
            <td>
                <select name="user_tags[]" id="user_tags" multiple>
                    <?php foreach ($terms as $term): ?>
                        <option value="<?php echo $term->term_id; ?>" <?php selected(in_array($term->term_id, $user_terms)); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'ut_add_user_tags_field');
add_action('edit_user_profile', 'ut_add_user_tags_field');
add_action('user_new_form', 'ut_add_user_tags_field');

/**
 * Save User Tags on Profile Update and User Creation
 */
function ut_save_user_tags($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;
    
    $tags = isset($_POST['user_tags']) ? array_map('intval', $_POST['user_tags']) : array();
    wp_set_object_terms($user_id, $tags, 'user_tags', false);
}
add_action('personal_options_update', 'ut_save_user_tags');
add_action('edit_user_profile_update', 'ut_save_user_tags');
add_action('user_register', 'ut_save_user_tags');

/**
 * Add User Tag Filter in Users List
 */
function ut_filter_users_by_tag($which) {
    if ($which !== 'top') return;
    $selected = $_GET['user_tag_filter'] ?? '';
    $terms = get_terms(array('taxonomy' => 'user_tags', 'hide_empty' => false));
    ?>
    <select name="user_tag_filter" style="margin-left: 10px;">
        <option value="">Filter by User Tag</option>
        <?php foreach ($terms as $term): ?>
            <option value="<?php echo $term->term_id; ?>" <?php selected($selected, $term->term_id); ?>>
                <?php echo esc_html($term->name); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
    submit_button(__('Filter'), '', 'filter_action', false);
}
add_action('restrict_manage_users', 'ut_filter_users_by_tag');

/**
 *  Dynamic dropdown for selecting User Tags 
 */
function ut_filter_users_query($query) {
    global $pagenow;
    if (is_admin() && $pagenow === 'users.php' && !empty($_GET['user_tag_filter'])) {
        $user_ids = get_objects_in_term(intval($_GET['user_tag_filter']), 'user_tags');
        $query->set('include', !empty($user_ids) ? $user_ids : array(0));
    }
}
add_action('pre_get_users', 'ut_filter_users_query');

/**
 * Add and Display User Tags Column in Users Table Admin
 */
function ut_add_user_tags_column($columns) {
    $columns['user_tags'] = 'User Tags';
    return $columns;
}
add_filter('manage_users_columns', 'ut_add_user_tags_column');

function ut_show_user_tags_column($value, $column_name, $user_id) {
    if ($column_name === 'user_tags') {
        $terms = wp_get_object_terms($user_id, 'user_tags', array('fields' => 'names'));
        return $terms ? implode(', ', array_map('esc_html', $terms)) : '-';
    }
    return $value;
}
add_filter('manage_users_custom_column', 'ut_show_user_tags_column', 10, 3);