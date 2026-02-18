<?php
/**
 * Plugin Name: Intelligize Chat
 * Plugin URI:  https://github.com/dbarry21/Intelligize-Chat
 * Description: An AI-powered floating chatbot that answers visitor questions using your website content.
 * Version:     2.3.2
 * Author:      dbarry21
 * Author URI:  https://github.com/dbarry21
 * License:     GPL v2 or later
 * Text Domain: intelligize-chat
 * GitHub Plugin URI: dbarry21/Intelligize-Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Constants ────────────────────────────────────────────────────────────────
define( 'WPSC_VERSION', '2.3.2' );
define( 'WPSC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPSC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ── Autoload ─────────────────────────────────────────────────────────────────
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-content-indexer.php';
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-chat-engine.php';
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-chat-logger.php';
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-admin.php';
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-frontend.php';
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-ajax.php';
require_once WPSC_PLUGIN_DIR . 'includes/class-wpsc-github-updater.php';

// ── Boot ─────────────────────────────────────────────────────────────────────
function wpsc_init() {
    if ( is_admin() ) {
        new WPSC_Admin();
    }
    new WPSC_Frontend();
    new WPSC_Ajax();
    new WPSC_GitHub_Updater(
        WPSC_PLUGIN_BASENAME, 'dbarry21', 'Intelligize-Chat', WPSC_VERSION
    );
}
add_action( 'plugins_loaded', 'wpsc_init' );

// ── Activation ───────────────────────────────────────────────────────────────
function wpsc_activate() {
    // Create chat logs table
    WPSC_Chat_Logger::create_table();

    $defaults = array(
        // General
        'wpsc_enabled'            => '1',
        'wpsc_bot_name'           => 'Intelligize Assistant',
        'wpsc_welcome_message'    => 'Hi there! 👋 How can I help you today?',
        'wpsc_primary_color'      => '#2563eb',
        'wpsc_position'           => 'bottom-right',
        'wpsc_quick_replies'      => "What services do you offer?\nHow can I contact you?\nTell me about pricing",

        // Behavior
        'wpsc_show_delay'         => '3',
        'wpsc_auto_open'          => '0',
        'wpsc_auto_open_delay'    => '5',
        'wpsc_show_on_mobile'     => '1',
        'wpsc_page_visibility'    => 'all',    // 'all' | 'homepage' | 'exclude'
        'wpsc_excluded_pages'     => '',

        // Contact
        'wpsc_contact_email'      => '',
        'wpsc_contact_phone'      => '',
        'wpsc_contact_sms'        => '',
        'wpsc_contact_page_url'   => '',

        // Lead capture
        'wpsc_lead_capture'       => '0',      // 0=off, 1=on
        'wpsc_lead_capture_title' => 'Before we start, how can we reach you?',
        'wpsc_lead_require_email' => '1',
        'wpsc_lead_require_name'  => '0',
        'wpsc_lead_require_phone' => '0',

        // AI
        'wpsc_post_types'         => array( 'page', 'post' ),
        'wpsc_ai_provider'        => 'local',
        'wpsc_api_key'            => '',
        'wpsc_avatar'             => 'bot',

        // Logging
        'wpsc_enable_logging'     => '1',
    );

    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) {
            update_option( $key, $value );
        }
    }

    $indexer = new WPSC_Content_Indexer();
    $indexer->build_index();
}
register_activation_hook( __FILE__, 'wpsc_activate' );

// ── Deactivation ─────────────────────────────────────────────────────────────
function wpsc_deactivate() {
    delete_option( 'wpsc_content_index' );
}
register_deactivation_hook( __FILE__, 'wpsc_deactivate' );
