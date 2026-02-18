<?php
/**
 * Frontend - Enqueue assets and render the chat widget markup.
 *
 * @package WP_SmartChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPSC_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_widget' ) );
    }

    /**
     * Enqueue CSS and JS on the frontend.
     */
    public function enqueue_assets() {
        // Don't load if disabled, in admin, or wp-login
        if ( is_admin() || get_option( 'wpsc_enabled', '1' ) !== '1' ) {
            return;
        }

        wp_enqueue_style(
            'wpsc-chat-style',
            WPSC_PLUGIN_URL . 'assets/css/chat-widget.css',
            array(),
            WPSC_VERSION
        );

        wp_enqueue_script(
            'wpsc-chat-script',
            WPSC_PLUGIN_URL . 'assets/js/chat-widget.js',
            array(),
            WPSC_VERSION,
            true // Load in footer
        );

        // Pass config to JS
        $color = get_option( 'wpsc_primary_color', '#2563eb' );
        $quick_replies_raw = get_option( 'wpsc_quick_replies', '' );
        $quick_replies = array_filter( array_map( 'trim', explode( "\n", $quick_replies_raw ) ) );

        wp_localize_script( 'wpsc-chat-script', 'wpscConfig', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'wpsc_chat_nonce' ),
            'botName'      => get_option( 'wpsc_bot_name', 'Intelligize Assistant' ),
            'welcome'      => get_option( 'wpsc_welcome_message', 'Hi there! 👋 How can I help you today?' ),
            'color'        => $color,
            'position'     => get_option( 'wpsc_position', 'bottom-right' ),
            'quickReplies' => array_values( $quick_replies ),
        ) );
    }

    /**
     * Render the chat widget HTML shell in the footer.
     */
    public function render_widget() {
        if ( is_admin() || get_option( 'wpsc_enabled', '1' ) !== '1' ) {
            return;
        }

        $color    = get_option( 'wpsc_primary_color', '#2563eb' );
        $position = get_option( 'wpsc_position', 'bottom-right' );
        $bot_name = get_option( 'wpsc_bot_name', 'Intelligize Assistant' );
        ?>
        <!-- WP SmartChat Widget -->
        <div id="wpsc-chat-widget" class="wpsc-widget wpsc-<?php echo esc_attr( $position ); ?>" style="--wpsc-primary: <?php echo esc_attr( $color ); ?>;">

            <!-- ▸ Floating Toggle Button -->
            <button id="wpsc-toggle" class="wpsc-toggle" aria-label="Open chat">
                <svg class="wpsc-icon-chat" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <svg class="wpsc-icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            <!-- ▸ Chat Window -->
            <div id="wpsc-window" class="wpsc-window" aria-hidden="true">

                <!-- Header -->
                <div class="wpsc-header">
                    <div class="wpsc-header-info">
                        <div class="wpsc-avatar">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8V4H8"/><rect x="2" y="2" width="20" height="20" rx="2"/><path d="m2 12 5.5-5.5"/><path d="M7 12h3l2.5-2.5"/><path d="M14 12h3l2 2"/><circle cx="16" cy="16" r="2"/></svg>
                        </div>
                        <div>
                            <span class="wpsc-header-name"><?php echo esc_html( $bot_name ); ?></span>
                            <span class="wpsc-header-status">Online</span>
                        </div>
                    </div>
                    <button id="wpsc-close" class="wpsc-close-btn" aria-label="Close chat">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <!-- Messages -->
                <div id="wpsc-messages" class="wpsc-messages"></div>

                <!-- Input -->
                <div class="wpsc-input-area">
                    <textarea id="wpsc-input" class="wpsc-input" placeholder="Type a message…" rows="1"></textarea>
                    <button id="wpsc-send" class="wpsc-send-btn" aria-label="Send message">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </div>

                <!-- Branding -->
                <div class="wpsc-branding">Powered by Intelligize Chat</div>
            </div>
        </div>
        <?php
    }
}
