<?php
/**
 * Frontend — Enqueue assets and render chat widget.
 *
 * @package Intelligize_Chat
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
     * Should the widget show on this page?
     */
    private function should_show() {
        if ( is_admin() ) return false;
        if ( get_option( 'wpsc_enabled', '1' ) !== '1' ) return false;

        // Page visibility
        $visibility = get_option( 'wpsc_page_visibility', 'all' );
        if ( 'homepage' === $visibility && ! is_front_page() && ! is_home() ) return false;
        if ( 'exclude' === $visibility ) {
            $excluded = array_map( 'trim', explode( ',', get_option( 'wpsc_excluded_pages', '' ) ) );
            if ( is_singular() && in_array( (string) get_the_ID(), $excluded, true ) ) return false;
        }

        return true;
    }

    public function enqueue_assets() {
        if ( ! $this->should_show() ) return;

        wp_enqueue_style( 'wpsc-chat-style', WPSC_PLUGIN_URL . 'assets/css/chat-widget.css', array(), WPSC_VERSION );
        wp_enqueue_script( 'wpsc-chat-script', WPSC_PLUGIN_URL . 'assets/js/chat-widget.js', array(), WPSC_VERSION, true );

        $quick_replies_raw = get_option( 'wpsc_quick_replies', '' );
        $quick_replies     = array_values( array_filter( array_map( 'trim', explode( "\n", $quick_replies_raw ) ) ) );

        // Contact buttons config
        $contacts = array();
        $email = get_option( 'wpsc_contact_email', '' );
        $phone = get_option( 'wpsc_contact_phone', '' );
        $sms   = get_option( 'wpsc_contact_sms', '' );
        $cpage = get_option( 'wpsc_contact_page_url', '' );
        if ( $email ) $contacts[] = array( 'type' => 'email', 'label' => '📧 Email Us',       'value' => 'mailto:' . $email );
        if ( $phone ) $contacts[] = array( 'type' => 'phone', 'label' => '📞 Call Us',         'value' => 'tel:' . preg_replace( '/[^+0-9]/', '', $phone ) );
        if ( $sms )   $contacts[] = array( 'type' => 'sms',   'label' => '💬 Text Us',         'value' => 'sms:' . preg_replace( '/[^+0-9]/', '', $sms ) );
        if ( $cpage ) $contacts[] = array( 'type' => 'link',  'label' => '📋 Contact Page',    'value' => $cpage );

        // Lead capture config
        $lead_capture = array(
            'enabled'      => get_option( 'wpsc_lead_capture', '0' ) === '1',
            'title'        => get_option( 'wpsc_lead_capture_title', 'Before we start, how can we reach you?' ),
            'requireEmail' => get_option( 'wpsc_lead_require_email', '1' ) === '1',
            'requireName'  => get_option( 'wpsc_lead_require_name', '0' ) === '1',
            'requirePhone' => get_option( 'wpsc_lead_require_phone', '0' ) === '1',
        );

        wp_localize_script( 'wpsc-chat-script', 'wpscConfig', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'wpsc_chat_nonce' ),
            'botName'        => get_option( 'wpsc_bot_name', 'Intelligize Assistant' ),
            'welcome'        => get_option( 'wpsc_welcome_message', 'Hi there! 👋 How can I help you today?' ),
            'color'          => get_option( 'wpsc_primary_color', '#2563eb' ),
            'position'       => get_option( 'wpsc_position', 'bottom-right' ),
            'quickReplies'   => $quick_replies,
            'showDelay'      => intval( get_option( 'wpsc_show_delay', 3 ) ),
            'autoOpen'       => get_option( 'wpsc_auto_open', '0' ) === '1',
            'autoOpenDelay'  => intval( get_option( 'wpsc_auto_open_delay', 5 ) ),
            'showOnMobile'   => get_option( 'wpsc_show_on_mobile', '1' ) === '1',
            'contacts'       => $contacts,
            'leadCapture'    => $lead_capture,
            'pageUrl'        => is_singular() ? get_permalink() : home_url( $_SERVER['REQUEST_URI'] ),
        ) );
    }

    public function render_widget() {
        if ( ! $this->should_show() ) return;

        $color    = get_option( 'wpsc_primary_color', '#2563eb' );
        $position = get_option( 'wpsc_position', 'bottom-right' );
        $bot_name = get_option( 'wpsc_bot_name', 'Intelligize Assistant' );
        $mobile   = get_option( 'wpsc_show_on_mobile', '1' );
        ?>
        <div id="wpsc-chat-widget" class="wpsc-widget wpsc-<?php echo esc_attr( $position ); ?> <?php echo $mobile !== '1' ? 'wpsc-hide-mobile' : ''; ?>" style="--wpsc-primary: <?php echo esc_attr( $color ); ?>; display:none;">

            <button id="wpsc-toggle" class="wpsc-toggle" aria-label="Open chat">
                <svg class="wpsc-icon-chat" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <svg class="wpsc-icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            <div id="wpsc-window" class="wpsc-window" aria-hidden="true">
                <div class="wpsc-header">
                    <div class="wpsc-header-info">
                        <div class="wpsc-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8V4H8"/><rect x="2" y="2" width="20" height="20" rx="2"/><path d="m2 12 5.5-5.5"/><path d="M7 12h3l2.5-2.5"/><path d="M14 12h3l2 2"/><circle cx="16" cy="16" r="2"/></svg></div>
                        <div>
                            <span class="wpsc-header-name"><?php echo esc_html( $bot_name ); ?></span>
                            <span class="wpsc-header-status">Online</span>
                        </div>
                    </div>
                    <button id="wpsc-close" class="wpsc-close-btn" aria-label="Close chat"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                </div>
                <div id="wpsc-messages" class="wpsc-messages"></div>
                <div class="wpsc-input-area">
                    <textarea id="wpsc-input" class="wpsc-input" placeholder="Type a message…" rows="1"></textarea>
                    <button id="wpsc-send" class="wpsc-send-btn" aria-label="Send message"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></button>
                </div>
                <div class="wpsc-branding">Powered by Intelligize Chat</div>
            </div>
        </div>
        <?php
    }
}
