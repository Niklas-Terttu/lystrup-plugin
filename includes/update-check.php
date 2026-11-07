<?php
/**
 * Admin-only manual plugin update metadata refresh.
 * Does not bypass signature checks or install code automatically.
 */
defined('ABSPATH') || exit;
add_action('admin_menu', function () {
    add_management_page('Lystrup pluginopdatering', 'Lystrup: Pluginopdatering', 'update_plugins', 'lystrup-plugin-updates', function () {
        if (!current_user_can('update_plugins')) wp_die('Ingen adgang.');
        echo '<div class="wrap"><h1>Lystrup pluginopdatering</h1><p>Kontrollerer tilgængelige opdateringer via WordPress. GitHub-udgivelser kræver separat konfigureret opdateringsintegration.</p>';
        if (isset($_GET['checked'])) echo '<div class="notice notice-success"><p>Opdateringskontrol gennemført. Se tilgængelige opdateringer under Kontrolpanel → Opdateringer.</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('lystrup_plugin_check_updates');
        echo '<input type="hidden" name="action" value="lystrup_plugin_check_updates">';
        submit_button('Søg efter opdatering nu');
        echo '</form></div>';
    });
});
add_action('admin_post_lystrup_plugin_check_updates', function () {
    if (!current_user_can('update_plugins')) wp_die('Ingen adgang.', '', ['response' => 403]);
    check_admin_referer('lystrup_plugin_check_updates');
    Lystrup_Release_Updates::clear('lystrup-plugin');
    delete_site_transient('update_plugins');
    wp_update_plugins();
    wp_safe_redirect(add_query_arg('checked', '1', admin_url('tools.php?page=lystrup-plugin-updates')));
    exit;
});
