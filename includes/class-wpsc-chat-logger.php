<?php
/**
 * Chat Logger — Stores conversations in a custom database table.
 *
 * @package Intelligize_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPSC_Chat_Logger {

    /**
     * Create the chat logs table on activation.
     */
    public static function create_table() {
        global $wpdb;
        $table   = $wpdb->prefix . 'wpsc_chat_logs';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            visitor_ip VARCHAR(45) DEFAULT '',
            visitor_name VARCHAR(100) DEFAULT '',
            visitor_email VARCHAR(100) DEFAULT '',
            visitor_phone VARCHAR(30) DEFAULT '',
            role ENUM('user','assistant') NOT NULL,
            message TEXT NOT NULL,
            page_url VARCHAR(500) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY created_at (created_at),
            KEY visitor_email (visitor_email)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Log a single message.
     */
    public static function log( $data ) {
        if ( get_option( 'wpsc_enable_logging', '1' ) !== '1' ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wpsc_chat_logs';

        $wpdb->insert( $table, array(
            'session_id'    => sanitize_text_field( $data['session_id'] ?? '' ),
            'visitor_ip'    => sanitize_text_field( $data['visitor_ip'] ?? '' ),
            'visitor_name'  => sanitize_text_field( $data['visitor_name'] ?? '' ),
            'visitor_email' => sanitize_email( $data['visitor_email'] ?? '' ),
            'visitor_phone' => sanitize_text_field( $data['visitor_phone'] ?? '' ),
            'role'          => in_array( $data['role'], array( 'user', 'assistant' ) ) ? $data['role'] : 'user',
            'message'       => sanitize_textarea_field( $data['message'] ?? '' ),
            'page_url'      => esc_url_raw( $data['page_url'] ?? '' ),
            'created_at'    => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
    }

    /**
     * Get conversations grouped by session for the admin viewer.
     */
    public static function get_sessions( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpsc_chat_logs';

        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
            'search'   => '',
        );
        $args = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $where = '1=1';
        $params = array();

        if ( ! empty( $args['search'] ) ) {
            $where .= ' AND (message LIKE %s OR visitor_email LIKE %s OR visitor_name LIKE %s)';
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Get unique sessions
        $sql = "SELECT session_id, 
                       MIN(created_at) as started_at,
                       MAX(created_at) as last_message_at,
                       COUNT(*) as message_count,
                       MAX(visitor_name) as visitor_name,
                       MAX(visitor_email) as visitor_email,
                       MAX(visitor_ip) as visitor_ip,
                       MAX(page_url) as page_url,
                       (SELECT message FROM {$table} t2 WHERE t2.session_id = t1.session_id AND t2.role = 'user' ORDER BY t2.created_at ASC LIMIT 1) as first_question
                FROM {$table} t1
                WHERE {$where}
                GROUP BY session_id
                ORDER BY last_message_at DESC
                LIMIT %d OFFSET %d";

        $params[] = $args['per_page'];
        $params[] = $offset;

        if ( ! empty( $params ) ) {
            $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        } else {
            $results = $wpdb->get_results( $sql );
        }

        return $results;
    }

    /**
     * Get total session count (for pagination).
     */
    public static function get_session_count( $search = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpsc_chat_logs';

        $where = '1=1';
        $params = array();

        if ( ! empty( $search ) ) {
            $where .= ' AND (message LIKE %s OR visitor_email LIKE %s OR visitor_name LIKE %s)';
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE {$where}";

        if ( ! empty( $params ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
        }
        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Get all messages for a specific session.
     */
    public static function get_session_messages( $session_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpsc_chat_logs';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE session_id = %s ORDER BY created_at ASC",
            $session_id
        ) );
    }

    /**
     * Delete a session.
     */
    public static function delete_session( $session_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpsc_chat_logs';
        $wpdb->delete( $table, array( 'session_id' => $session_id ), array( '%s' ) );
    }

    /**
     * Get basic stats.
     */
    public static function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpsc_chat_logs';

        return array(
            'total_sessions'  => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM {$table}" ),
            'total_messages'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
            'today_sessions'  => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE created_at >= %s",
                current_time( 'Y-m-d' ) . ' 00:00:00'
            ) ),
            'leads_collected' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT visitor_email) FROM {$table} WHERE visitor_email != ''" ),
        );
    }

    /**
     * Export all logs as CSV data.
     */
    public static function export_csv() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpsc_chat_logs';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
    }
}
