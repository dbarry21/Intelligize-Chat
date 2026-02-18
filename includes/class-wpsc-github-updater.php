<?php
/**
 * GitHub Plugin Updater
 *
 * Checks a public GitHub repository's releases for a newer version
 * and hooks into WordPress's native update system.
 *
 * @package Intelligize_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPSC_GitHub_Updater {

    private $slug;        // e.g. 'intelligize-chat'
    private $plugin_file; // e.g. 'intelligize-chat/wp-smartchat.php'
    private $github_user; // e.g. 'dbarry21'
    private $github_repo; // e.g. 'Intelligize-Chat'
    private $version;     // Current installed version
    private $cache_key;   // Transient key for caching

    /**
     * @param string $plugin_file  Plugin basename (plugin_basename(__FILE__))
     * @param string $github_user  GitHub username
     * @param string $github_repo  GitHub repository name
     * @param string $version      Current plugin version
     */
    public function __construct( $plugin_file, $github_user, $github_repo, $version ) {
        $this->plugin_file = $plugin_file;
        $this->slug        = dirname( $plugin_file ); // 'intelligize-chat'
        $this->github_user = $github_user;
        $this->github_repo = $github_repo;
        $this->version     = $version;
        $this->cache_key   = 'wpsc_github_update_' . md5( $plugin_file );

        // Hook into WordPress update system
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );

        // Clear cache when plugin list is refreshed
        add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 0 );
    }

    /**
     * Get release info from GitHub (cached for 6 hours).
     */
    private function get_github_release() {
        $cached = get_transient( $this->cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_user,
            $this->github_repo
        );

        $response = wp_remote_get( $url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            ),
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );

        if ( empty( $release ) || ! isset( $release->tag_name ) ) {
            return false;
        }

        // Find the zip asset (uploaded to the release)
        $download_url = '';
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( substr( $asset->name, -4 ) === '.zip' ) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        // Fallback: use GitHub's auto-generated zipball
        if ( empty( $download_url ) ) {
            $download_url = $release->zipball_url;
        }

        $result = (object) array(
            'version'      => ltrim( $release->tag_name, 'v' ), // 'v1.0.1' → '1.0.1'
            'download_url' => $download_url,
            'name'         => $release->name,
            'body'         => $release->body,
            'published_at' => $release->published_at,
            'html_url'     => $release->html_url,
        );

        // Cache for 6 hours
        set_transient( $this->cache_key, $result, 6 * HOUR_IN_SECONDS );

        return $result;
    }

    /**
     * Hook: Check if a newer version exists on GitHub.
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_github_release();

        if ( false === $release ) {
            return $transient;
        }

        // Compare versions
        if ( version_compare( $release->version, $this->version, '>' ) ) {
            $transient->response[ $this->plugin_file ] = (object) array(
                'slug'        => $this->slug,
                'plugin'      => $this->plugin_file,
                'new_version' => $release->version,
                'url'         => $release->html_url,
                'package'     => $release->download_url,
                'icons'       => array(),
                'banners'     => array(),
            );
        } else {
            // Tell WP we checked and there's no update (prevents false positives)
            $transient->no_update[ $this->plugin_file ] = (object) array(
                'slug'        => $this->slug,
                'plugin'      => $this->plugin_file,
                'new_version' => $this->version,
                'url'         => $release->html_url,
                'package'     => $release->download_url,
            );
        }

        return $transient;
    }

    /**
     * Hook: Provide plugin info for the "View details" popup.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || $this->slug !== $args->slug ) {
            return $result;
        }

        $release = $this->get_github_release();

        if ( false === $release ) {
            return $result;
        }

        return (object) array(
            'name'            => 'Intelligize Chat',
            'slug'            => $this->slug,
            'version'         => $release->version,
            'author'          => '<a href="https://github.com/' . $this->github_user . '">dbarry21</a>',
            'homepage'        => 'https://github.com/' . $this->github_user . '/' . $this->github_repo,
            'short_description' => 'AI-powered floating chatbot that answers visitor questions using your website content.',
            'sections'        => array(
                'description'  => 'Intelligize Chat is a floating chatbot widget for WordPress that uses your site content to answer visitor questions.',
                'changelog'    => nl2br( esc_html( $release->body ) ),
            ),
            'download_link'   => $release->download_url,
            'last_updated'    => $release->published_at,
            'requires'        => '5.8',
            'tested'          => '6.7',
            'requires_php'    => '7.4',
        );
    }

    /**
     * Hook: After install, make sure the folder name is correct.
     *
     * GitHub zips can extract to weird folder names like 'Intelligize-Chat-main'.
     * This renames it to match the expected plugin slug.
     */
    public function after_install( $response, $hook_extra, $result ) {
        // Only run for our plugin
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_file ) {
            return $result;
        }

        global $wp_filesystem;

        $install_dir = $result['destination'];
        $plugin_dir  = WP_PLUGIN_DIR . '/' . $this->slug;

        // If the extracted folder doesn't match our slug, rename it
        if ( $install_dir !== $plugin_dir ) {
            $wp_filesystem->move( $install_dir, $plugin_dir );
            $result['destination']      = $plugin_dir;
            $result['destination_name'] = $this->slug;
        }

        // Re-activate the plugin
        activate_plugin( $this->plugin_file );

        return $result;
    }

    /**
     * Clear the cached release data.
     */
    public function clear_cache() {
        delete_transient( $this->cache_key );
    }
}
