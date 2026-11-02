<?php
/**
 * GitHub Releases updater for the public MohanaAchuthan30/achuthan-jobs project.
 * Requires a tagged GitHub Release asset named achuthan-jobs.zip with a SHA-256 digest.
 * Uses the WordPress 5.8+ Update URI mechanism and normal WordPress background updates.
 * Does not install arbitrary branch commits or GitHub-generated source archives.
 */
if (!defined('ABSPATH')) { exit; }

final class Achuthan_Github_Updater {
    const UPDATE_URI = 'https://github.com/MohanaAchuthan30/achuthan-jobs';
    const API = 'https://api.github.com/repos/MohanaAchuthan30/achuthan-jobs/releases/latest';
    const ASSET = 'achuthan-jobs.zip';
    const CACHE_KEY = 'aj_github_public_release_v1';

    private static $plugin_file = '';
    private static $installed_version = '';

    public static function boot($plugin_file, $installed_version) {
        self::$plugin_file = plugin_basename($plugin_file);
        self::$installed_version = $installed_version;
        add_filter('update_plugins_github.com', array(__CLASS__, 'check_update'), 10, 4);
        add_filter('auto_update_plugin', array(__CLASS__, 'auto_update'), 10, 2);
        add_filter('upgrader_pre_download', array(__CLASS__, 'verify_download'), 10, 4);
        add_filter('plugins_api', array(__CLASS__, 'plugin_information'), 10, 3);
        add_action('admin_notices', array(__CLASS__, 'required_update_notice'), 3);
        add_action('network_admin_notices', array(__CLASS__, 'required_update_notice'), 3);
        add_action('admin_post_aj_check_updates_now', array(__CLASS__, 'force_check'));
    }

    /** Visible, persistent administrator notice when a verified newer release is available. */
    public static function required_update_notice() {
        if (!current_user_can('update_plugins')) return;
        if (wp_doing_ajax() || (defined('DOING_CRON')&&DOING_CRON)) return;
        $release=self::release();
        if (!$release || !version_compare($release['version'],self::$installed_version,'>')) return;
        $url=wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin='.rawurlencode(self::$plugin_file)), 'upgrade-plugin_'.self::$plugin_file);
        echo '<div class="notice notice-warning aj-github-update-warning"><p><strong>Achuthan Jobs update required:</strong> version '.esc_html($release['version']).' is available on the official GitHub Releases page (installed: '.esc_html(self::$installed_version).'). ';
        echo '<a class="button button-primary" href="'.esc_url($url).'">Update Achuthan Jobs now</a> ';
        echo '<a href="'.esc_url($release['release_url']).'" target="_blank" rel="noopener noreferrer">Review release details</a></p>';
        echo '<p>This reminder remains visible until an administrator installs the verified update. Automatic installation also requires WordPress cron and filesystem access.</p></div>';
    }

    /** Administrator can refresh release cache without waiting for the 4-hour timeout. */
    public static function force_check() {
        if (!current_user_can('update_plugins')) wp_die('Insufficient privileges.','',array('response'=>403));
        if (strtoupper($_SERVER['REQUEST_METHOD']??'')!=='POST') wp_die('Invalid method.','',array('response'=>405));
        check_admin_referer('aj_check_updates_now');
        delete_site_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
        if (!function_exists('wp_update_plugins')) require_once ABSPATH.'wp-includes/update.php';
        wp_update_plugins();
        wp_safe_redirect(admin_url('admin.php?page=bbs-recruitment-settings&updates_checked=1'),303);
        exit;
    }

    /** Pure parser; fails closed when a published release does not contain a valid package/digest. */
    public static function parse_release($data) {
        if (!is_array($data) || !empty($data['draft']) || !empty($data['prerelease'])) return false;
        $tag = isset($data['tag_name']) && is_string($data['tag_name']) ? $data['tag_name'] : '';
        if (!preg_match('/^v([0-9]+\.[0-9]+\.[0-9]+)$/D', $tag, $matches)) return false;
        if (empty($data['assets']) || !is_array($data['assets'])) return false;
        $expected = self::UPDATE_URI . '/releases/download/' . $tag . '/' . self::ASSET;
        foreach ($data['assets'] as $asset) {
            if (!is_array($asset) || ($asset['name'] ?? '') !== self::ASSET || ($asset['state'] ?? '') !== 'uploaded') continue;
            if (($asset['browser_download_url'] ?? '') !== $expected) continue;
            $digest = $asset['digest'] ?? '';
            if (!is_string($digest) || !preg_match('/^sha256:([a-f0-9]{64})$/iD', $digest, $hash)) continue;
            if (empty($asset['size']) || (int)$asset['size'] > 25000000) continue;
            return array(
                'version' => $matches[1],
                'url' => $expected,
                'sha256' => strtolower($hash[1]),
                'release_url' => self::UPDATE_URI . '/releases/tag/' . $tag,
                'notes' => is_string($data['body'] ?? null) ? $data['body'] : '',
            );
        }
        return false;
    }

    private static function release() {
        $cached = get_site_transient(self::CACHE_KEY);
        if ($cached === 'unavailable') return false;
        if (is_array($cached)) return $cached;
        $response = wp_safe_remote_get(self::API, array(
            'timeout' => 8,
            'redirection' => 2,
            'sslverify' => true,
            'headers' => array(
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Achuthan-Jobs-WordPress-Updater/' . self::$installed_version,
                'X-GitHub-Api-Version' => '2022-11-28',
            ),
        ));
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            set_site_transient(self::CACHE_KEY, 'unavailable', 20 * MINUTE_IN_SECONDS);
            return false;
        }
        $json = json_decode(wp_remote_retrieve_body($response), true);
        $release = self::parse_release($json);
        set_site_transient(self::CACHE_KEY, $release ?: 'unavailable', $release ? 4 * HOUR_IN_SECONDS : 20 * MINUTE_IN_SECONDS);
        return $release;
    }

    public static function check_update($update, $plugin_data, $plugin_file, $locales) {
        // WordPress invokes this hook for every plugin with a github.com Update URI.
        if ($plugin_file !== self::$plugin_file || ($plugin_data['UpdateURI'] ?? '') !== self::UPDATE_URI) return $update;
        $release = self::release();
        if (!$release || !version_compare($release['version'], self::$installed_version, '>')) return false;
        return array(
            'id' => self::UPDATE_URI,
            'slug' => 'achuthan-jobs',
            'version' => $release['version'],
            'url' => $release['release_url'],
            'package' => $release['url'],
            'requires_php' => '8.0',
            'autoupdate' => get_option('aj_github_auto_update', '1') === '1',
        );
    }

    public static function auto_update($update, $item) {
        $plugin = isset($item->plugin) ? (string)$item->plugin : '';
        // Never change the auto-update behaviour of any other WordPress plugin.
        if ($plugin !== self::$plugin_file) return $update;
        if (($item->package ?? '') === '' || ($item->id ?? '') !== self::UPDATE_URI) return false;
        return get_option('aj_github_auto_update', '1') === '1';
    }

    public static function verify_download($reply, $package, $upgrader, $hook_extra) {
        if (false !== $reply) return $reply;
        if (!is_string($package)) return false;
        $expected_prefix = self::UPDATE_URI . '/releases/download/';
        if (strpos($package, $expected_prefix) !== 0) return false;
        // A same-repository URL is never a sufficient reason to install anything.
        $release = self::release();
        if (!$release || !hash_equals($release['url'], $package)) {
            return new WP_Error('aj_update_unverified_package', 'Achuthan Jobs update blocked: release package was not verified.');
        }
        if (!function_exists('download_url')) require_once ABSPATH . 'wp-admin/includes/file.php';
        $tmp = download_url($package, 300);
        if (is_wp_error($tmp)) return $tmp;
        $actual = hash_file('sha256', $tmp);
        if (!is_string($actual) || !hash_equals($release['sha256'], strtolower($actual))) {
            @unlink($tmp);
            return new WP_Error('aj_update_checksum_mismatch', 'Achuthan Jobs update blocked: GitHub release checksum did not match.');
        }
        return $tmp;
    }

    public static function plugin_information($result, $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== 'achuthan-jobs') return $result;
        $release = self::release();
        if (!$release) return $result;
        return (object)array(
            'name' => 'Achuthan Jobs',
            'slug' => 'achuthan-jobs',
            'version' => $release['version'],
            'author' => 'Achuthan',
            'homepage' => self::UPDATE_URI,
            'download_link' => $release['url'],
            'requires' => '6.2',
            'requires_php' => '8.0',
            'sections' => array(
                'description' => 'Job listings and hiring dashboard. Updates are supplied by the official public GitHub Releases feed.',
                'changelog' => nl2br(esc_html($release['notes'])),
            ),
        );
    }
}
