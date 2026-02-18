<?php
/**
 * AJAX Handler
 *
 * Processes chat messages from the frontend widget.
 *
 * @package WP_SmartChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPSC_Ajax {

    public function __construct() {
        // For logged-in users
        add_action( 'wp_ajax_wpsc_send_message', array( $this, 'handle_message' ) );
        // For guest visitors (most important!)
        add_action( 'wp_ajax_nopriv_wpsc_send_message', array( $this, 'handle_message' ) );
    }

    /**
     * Handle an incoming chat message via AJAX.
     */
    public function handle_message() {
        // Verify nonce
        if ( ! check_ajax_referer( 'wpsc_chat_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }

        // Sanitize input
        $message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
        $history = isset( $_POST['history'] ) ? $this->sanitize_history( $_POST['history'] ) : array();

        if ( empty( $message ) ) {
            wp_send_json_error( array( 'message' => 'Empty message.' ), 400 );
        }

        // Rate limiting (simple: 20 requests per minute per IP)
        if ( $this->is_rate_limited() ) {
            wp_send_json_error( array( 'message' => 'Too many messages. Please wait a moment.' ), 429 );
        }

        // Get the response
        $engine   = new WPSC_Chat_Engine();
        $response = $engine->get_response( $message, $history );

        wp_send_json_success( $response );
    }

    /**
     * Sanitize conversation history array.
     */
    private function sanitize_history( $raw ) {
        if ( ! is_array( $raw ) ) {
            return array();
        }

        $clean = array();
        // Only keep last 10 messages to limit context size
        $raw = array_slice( $raw, -10 );

        foreach ( $raw as $msg ) {
            if ( isset( $msg['role'], $msg['content'] ) ) {
                $clean[] = array(
                    'role'    => in_array( $msg['role'], array( 'user', 'assistant' ), true ) ? $msg['role'] : 'user',
                    'content' => sanitize_text_field( $msg['content'] ),
                );
            }
        }

        return $clean;
    }

    /**
     * Simple transient-based rate limiter.
     */
    private function is_rate_limited() {
        $ip  = $this->get_client_ip();
        $key = 'wpsc_rate_' . md5( $ip );

        $count = get_transient( $key );

        if ( false === $count ) {
            set_transient( $key, 1, 60 ); // 60-second window
            return false;
        }

        if ( (int) $count >= 20 ) {
            return true;
        }

        set_transient( $key, (int) $count + 1, 60 );
        return false;
    }

    /**
     * Get client IP address.
     */
    private function get_client_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        );

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
                return trim( $ip[0] );
            }
        }

        return '0.0.0.0';
    }
}
