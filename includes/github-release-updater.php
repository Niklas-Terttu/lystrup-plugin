<?php
/**
 * GitHub Release update source for Lystrup.
 * Only explicitly published, versioned release assets are installable.
 */
defined('ABSPATH') || exit;
if (!class_exists('Lystrup_Release_Updates')) {
final class Lystrup_Release_Updates {
    public static function release($repository) {
        $key = 'lystrup_release_' . md5($repository);
        $cached = get_site_transient($key);
        if (false !== $cached) return $cached;
        $url = 'https://api.github.com/repos/Niklas-Terttu/' . $repository . '/releases/latest';
        $response = wp_safe_remote_get($url, ['timeout' => 8, 'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'Lystrup-WordPress-Updater']]);
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            set_site_transient($key, [], 15 * MINUTE_IN_SECONDS);
            return [];
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || !empty($data['draft']) || !empty($data['prerelease'])) return [];
        $version = ltrim((string) ($data['tag_name'] ?? ''), 'v');
        if (!preg_match('/^\d+\.\d+\.\d+$/D', $version)) return [];
        $asset_name = $repository . '.zip';
        $package = '';
        foreach (($data['assets'] ?? []) as $asset) {
            if (($asset['name'] ?? '') === $asset_name) {
                $candidate = (string) ($asset['browser_download_url'] ?? '');
                $host = wp_parse_url($candidate, PHP_URL_HOST);
                if ('github.com' === $host && str_starts_with($candidate, 'https://github.com/Niklas-Terttu/' . $repository . '/releases/download/')) {
                    $package = $candidate;
                }
                break;
            }
        }
        // Do not offer updates without an explicit, named ZIP release asset.
        if (!$package) return [];
        $result = ['version' => $version, 'package' => $package, 'url' => 'https://github.com/Niklas-Terttu/' . $repository . '/releases/tag/' . rawurlencode((string)$data['tag_name'])];
        set_site_transient($key, $result, HOUR_IN_SECONDS);
        return $result;
    }
    public static function clear($repository) {
        delete_site_transient('lystrup_release_' . md5($repository));
    }
}
}
