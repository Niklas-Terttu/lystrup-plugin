<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/github-release-updater.php';
add_filter('update_plugins_github.com', function ($update, $plugin_data, $plugin_file) {
    if ('lystrup-plugin/lystrup-plugin.php' !== $plugin_file || ($plugin_data['UpdateURI'] ?? '') !== 'https://github.com/Niklas-Terttu/lystrup-plugin') return $update;
    $release = Lystrup_Release_Updates::release('lystrup-plugin');
    if (!$release) return false;
    return [
        'slug' => 'lystrup-plugin', 'version' => $release['version'],
        'url' => $release['url'], 'package' => $release['package'],
        'requires_php' => '8.0', 'autoupdate' => false,
    ];
}, 10, 3);
