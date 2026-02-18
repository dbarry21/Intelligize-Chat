<?php
/**
 * AJAX Handler — Processes chat messages + lead capture.
 *
 * @package Intelligize_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPSC_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_wpsc_send_message', array( $this, 'handle_message' ) );
        add_action( 'wp_ajax_nopriv_wpsc_send_message', array( $this, 'handle_message' ) );

        add_action( 'wp_ajax_wpsc_save_lead', array( $this, 'handle_lead' ) );
        add_action( 'wp_ajax_nopriv_wpsc_save_lead', array( $this, 'handle_lead' ) );
    }

    public function handle_message() {
        if ( ! check_ajax_referer( 'wpsc_chat_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }

        $message    = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
        $history    = isset( $_POST['history'] ) ? $this->sanitize_history( $_POST['history'] ) : array();
        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] ) : '';
        $page_url   = isset( $_POST['page_url'] ) ? esc_url_raw( $_POST['page_url'] ) : '';
        $lead_name  = isset( $_POST['visitor_name'] ) ? sanitize_text_field( $_POST['visitor_name'] ) : '';
        $lead_email = isset( $_POST['visitor_email'] ) ? sanitize_email( $_POST['visitor_email'] ) : '';
        $lead_phone = isset( $_POST['visitor_phone'] ) ? sanitize_text_field( $_POST['visitor_phone'] ) : '';

        if ( empty( $message ) ) {
            wp_send_json_error( array( 'message' => 'Empty message.' ), 400 );
        }

        if ( $this->is_rate_limited() ) {
            wp_send_json_error( array( 'message' => 'Too many messages. Please wait a moment.' ), 429 );
        }

        // Log user message
        WPSC_Chat_Logger::log( array(
            'session_id'    => $session_id,
            'visitor_ip'    => $this->get_client_ip(),
            'visitor_name'  => $lead_name,
            'visitor_email' => $lead_email,
            'visitor_phone' => $lead_phone,
            'role'          => 'user',
            'message'       => $message,
            'page_url'      => $page_url,
        ) );

        // Get bot response
        $engine   = new WPSC_Chat_Engine();
        $response = $engine->get_response( $message, $history );

        // Log bot response
        WPSC_Chat_Logger::log( array(
            'session_id'    => $session_id,
            'visitor_ip'    => $this->get_client_ip(),
            'visitor_name'  => $lead_name,
            'visitor_email' => $lead_email,
            'visitor_phone' => $lead_phone,
            'role'          => 'assistant',
            'message'       => $response['answer'],
            'page_url'      => $page_url,
        ) );

        wp_send_json_success( $response );
    }

    /**
     * Save lead capture info (separate AJAX call).
     */
    public function handle_lead() {
        if ( ! check_ajax_referer( 'wpsc_chat_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }

        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] ) : '';
        $name       = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        $email      = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $phone      = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
        $page_url   = isset( $_POST['page_url'] ) ? esc_url_raw( $_POST['page_url'] ) : '';

        // Log a system entry so the lead shows up in logs
        WPSC_Chat_Logger::log( array(
            'session_id'    => $session_id,
            'visitor_ip'    => $this->get_client_ip(),
            'visitor_name'  => $name,
            'visitor_email' => $email,
            'visitor_phone' => $phone,
            'role'          => 'user',
            'message'       => '[Lead captured] Name: ' . $name . ', Email: ' . $email . ', Phone: ' . $phone,
            'page_url'      => $page_url,
        ) );

        wp_send_json_success( array( 'message' => 'Lead saved.' ) );
    }

    private function sanitize_history( $raw ) {
        if ( ! is_array( $raw ) ) return array();
        $clean = array();
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

    private function is_rate_limited() {
        $ip  = $this->get_client_ip();
        $key = 'wpsc_rate_' . md5( $ip );
        $count = get_transient( $key );
        if ( false === $count ) { set_transient( $key, 1, 60 ); return false; }
        if ( (int) $count >= 20 ) return true;
        set_transient( $key, (int) $count + 1, 60 );
        return false;
    }

    private function get_client_ip() {
        foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $h ) {
            if ( ! empty( $_SERVER[ $h ] ) ) {
                $ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) );
                return trim( $ip[0] );
            }
        }
        return '0.0.0.0';
    }
}
