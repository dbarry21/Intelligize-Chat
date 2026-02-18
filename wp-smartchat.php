<?php
/**
 * Plugin Name: Intelligize Chat
 * Plugin URI:  https://github.com/dbarry21/Intelligize-Chat
 * Description: An AI-powered floating chatbot that answers visitor questions using your website content.
 * Version:     1.0.2
 * Author:      dbarry21
 * Author URI:  https://github.com/dbarry21
 * License:     GPL v2 or later
 * Text Domain: intelligize-chat
 * GitHub Plugin URI: dbarry21/Intelligize-Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// ── Constants ────────────────────────────────────────────────────────────────
define( 'WPSC_VERSION', '1.0.2' );
define( 'WPSC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPSC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ── Autoload Includes ────────────────────────────────────────────────────────
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-content-indexer.php';
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-chat-engine.php';
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-admin.php';
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-frontend.php';
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-ajax.php';
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-github-updater.php';

// ── Boot the Plugin ──────────────────────────────────────────────────────────
function wpsc_init() {
    // Admin settings page
    if ( is_admin() ) {
        new WPSC_Admin();
    }

    // Frontend chat widget
    new WPSC_Frontend();

    // AJAX handlers (works for both logged-in and guest users)
    new WPSC_Ajax();

    // GitHub auto-updater
    new WPSC_GitHub_Updater(
        WPSC_PLUGIN_BASENAME,  // 'intelligize-chat/wp-smartchat.php'
        'dbarry21',            // GitHub username
        'Intelligize-Chat',    // GitHub repo name
        WPSC_VERSION           // Current version
    );
}
add_action( 'plugins_loaded', 'wpsc_init' );

// ── Activation: Index content & set defaults ─────────────────────────────────
function wpsc_activate() {
    // Set default options
    $defaults = array(
        'wpsc_enabled'         => '1',
        'wpsc_bot_name'        => 'Intelligize Assistant',
        'wpsc_welcome_message' => 'Hi there! 👋 How can I help you today?',
        'wpsc_primary_color'   => '#2563eb',
        'wpsc_position'        => 'bottom-right',
        'wpsc_post_types'      => array( 'page', 'post' ),
        'wpsc_ai_provider'     => 'local', // 'local' | 'openai' | 'anthropic'
        'wpsc_api_key'         => '',
        'wpsc_quick_replies'   => "What services do you offer?\nHow can I contact you?\nTell me about pricing",
    );

    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) {
            update_option( $key, $value );
        }
    }

    // Build the initial content index
    $indexer = new WPSC_Content_Indexer();
    $indexer->build_index();
}
register_activation_hook( __FILE__, 'wpsc_activate' );

// ── Deactivation ─────────────────────────────────────────────────────────────
function wpsc_deactivate() {
    delete_option( 'wpsc_content_index' );
}
register_deactivation_hook( __FILE__, 'wpsc_deactivate' );
