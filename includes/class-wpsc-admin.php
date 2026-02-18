<?php
/**
 * Admin Settings + Chat Logs + Stats
 *
 * @package Intelligize_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPSC_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'save_post', array( $this, 'schedule_reindex' ), 20 );
        add_action( 'delete_post', array( $this, 'schedule_reindex' ), 20 );

        // AJAX for chat log viewer
        add_action( 'wp_ajax_wpsc_view_session', array( $this, 'ajax_view_session' ) );
        add_action( 'wp_ajax_wpsc_delete_session', array( $this, 'ajax_delete_session' ) );
        add_action( 'wp_ajax_wpsc_export_csv', array( $this, 'ajax_export_csv' ) );
    }

    public function add_menu_pages() {
        add_menu_page(
            'Intelligize Chat', 'Intelligize Chat', 'manage_options',
            'intelligize-chat', array( $this, 'render_settings_page' ),
            'dashicons-format-chat', 80
        );
        add_submenu_page(
            'intelligize-chat', 'Settings', 'Settings', 'manage_options',
            'intelligize-chat', array( $this, 'render_settings_page' )
        );
        add_submenu_page(
            'intelligize-chat', 'Chat Logs', 'Chat Logs', 'manage_options',
            'intelligize-chat-logs', array( $this, 'render_logs_page' )
        );
    }

    public function register_settings() {
        $fields = array(
            'wpsc_enabled', 'wpsc_bot_name', 'wpsc_welcome_message',
            'wpsc_primary_color', 'wpsc_position', 'wpsc_quick_replies',
            'wpsc_show_delay', 'wpsc_auto_open', 'wpsc_auto_open_delay',
            'wpsc_show_on_mobile', 'wpsc_page_visibility', 'wpsc_excluded_pages',
            'wpsc_contact_email', 'wpsc_contact_phone', 'wpsc_contact_sms',
            'wpsc_contact_page_url',
            'wpsc_lead_capture', 'wpsc_lead_capture_title',
            'wpsc_lead_require_email', 'wpsc_lead_require_name', 'wpsc_lead_require_phone',
            'wpsc_ai_provider', 'wpsc_api_key', 'wpsc_enable_logging',
            'wpsc_avatar',
        );
        foreach ( $fields as $f ) {
            register_setting( 'wpsc_settings', $f );
        }
        register_setting( 'wpsc_settings', 'wpsc_post_types', array(
            'type' => 'array',
            'sanitize_callback' => function ( $input ) {
                return is_array( $input ) ? array_map( 'sanitize_text_field', $input ) : array( 'page', 'post' );
            },
        ) );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( ! in_array( $hook, array( 'toplevel_page_intelligize-chat', 'intelligize-chat_page_intelligize-chat-logs' ) ) ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }

    public function schedule_reindex() {
        $indexer = new WPSC_Content_Indexer();
        $indexer->build_index();
    }

    // ══════════════════════════════════════════════════════════════════════
    // SETTINGS PAGE
    // ══════════════════════════════════════════════════════════════════════
    public function render_settings_page() {
        if ( isset( $_POST['wpsc_reindex'] ) && check_admin_referer( 'wpsc_reindex_action' ) ) {
            $indexer = new WPSC_Content_Indexer();
            $index   = $indexer->build_index();
            echo '<div class="notice notice-success"><p>Content index rebuilt — ' . count( $index ) . ' pages indexed.</p></div>';
        }

        $o = array(); // All options shorthand
        $keys = array(
            'wpsc_enabled','wpsc_bot_name','wpsc_welcome_message','wpsc_primary_color',
            'wpsc_position','wpsc_quick_replies','wpsc_show_delay','wpsc_auto_open',
            'wpsc_auto_open_delay','wpsc_show_on_mobile','wpsc_page_visibility',
            'wpsc_excluded_pages','wpsc_contact_email','wpsc_contact_phone',
            'wpsc_contact_sms','wpsc_contact_page_url','wpsc_lead_capture',
            'wpsc_lead_capture_title','wpsc_lead_require_email','wpsc_lead_require_name',
            'wpsc_lead_require_phone','wpsc_ai_provider','wpsc_api_key','wpsc_enable_logging',
            'wpsc_avatar',
        );
        foreach ( $keys as $k ) {
            $o[ $k ] = get_option( $k, '' );
        }
        $post_types = get_option( 'wpsc_post_types', array( 'page', 'post' ) );
        $all_types  = get_post_types( array( 'public' => true ), 'objects' );
        $stats      = WPSC_Chat_Logger::get_stats();
        ?>
        <div class="wrap">
            <h1>🤖 Intelligize Chat</h1>

            <!-- Stats Bar -->
            <div style="display:flex;gap:16px;margin:20px 0;">
                <?php foreach ( array(
                    array( '💬', 'Total Chats', $stats['total_sessions'] ),
                    array( '📅', 'Today', $stats['today_sessions'] ),
                    array( '📧', 'Leads', $stats['leads_collected'] ),
                    array( '💭', 'Messages', $stats['total_messages'] ),
                ) as $s ) : ?>
                <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:14px 20px;flex:1;text-align:center;">
                    <div style="font-size:24px;margin-bottom:4px;"><?php echo $s[0]; ?></div>
                    <div style="font-size:22px;font-weight:600;color:#1d2327;"><?php echo esc_html( $s[2] ); ?></div>
                    <div style="font-size:12px;color:#646970;"><?php echo esc_html( $s[1] ); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'wpsc_settings' ); ?>

                <!-- Enable/Disable -->
                <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo $o['wpsc_enabled'] ? '#00a32a' : '#d63638'; ?>;padding:16px 20px;margin:20px 0;border-radius:0 4px 4px 0;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <strong style="font-size:15px;">
                            <?php echo $o['wpsc_enabled'] ? '✅ Chatbot is <span style="color:#00a32a;">Enabled</span>' : '⛔ Chatbot is <span style="color:#d63638;">Disabled</span>'; ?>
                        </strong>
                        <p style="margin:4px 0 0;color:#646970;">Toggle the chatbot on or off across your entire site.</p>
                    </div>
                    <label style="position:relative;display:inline-block;width:52px;height:28px;flex-shrink:0;">
                        <input type="hidden" name="wpsc_enabled" value="0">
                        <input type="checkbox" name="wpsc_enabled" value="1" <?php checked( $o['wpsc_enabled'], '1' ); ?> style="opacity:0;width:0;height:0;position:absolute;" onchange="this.form.submit();">
                        <span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:<?php echo $o['wpsc_enabled'] ? '#00a32a' : '#c3c4c7'; ?>;border-radius:28px;transition:0.3s;">
                            <span style="position:absolute;height:22px;width:22px;left:<?php echo $o['wpsc_enabled'] ? '27px' : '3px'; ?>;bottom:3px;background:#fff;border-radius:50%;transition:0.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);display:block;"></span>
                        </span>
                    </label>
                </div>

                <!-- ▸ Widget Behavior -->
                <h2 class="title">Widget Behavior</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="wpsc_show_delay">Appear After (seconds)</label></th>
                        <td>
                            <input type="number" id="wpsc_show_delay" name="wpsc_show_delay" value="<?php echo esc_attr( $o['wpsc_show_delay'] ); ?>" min="0" max="60" style="width:80px;"> seconds
                            <p class="description">How long to wait before showing the chat button. Set to 0 for immediate.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Auto-Open Chat Window</th>
                        <td>
                            <label><input type="checkbox" name="wpsc_auto_open" value="1" <?php checked( $o['wpsc_auto_open'], '1' ); ?>> Automatically open the chat window</label>
                            <div style="margin-top:8px;">
                                After <input type="number" name="wpsc_auto_open_delay" value="<?php echo esc_attr( $o['wpsc_auto_open_delay'] ); ?>" min="0" max="120" style="width:80px;"> seconds
                            </div>
                            <p class="description">Opens the chat window automatically after the specified delay. Only triggers once per visit.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Mobile Visibility</th>
                        <td><label><input type="checkbox" name="wpsc_show_on_mobile" value="1" <?php checked( $o['wpsc_show_on_mobile'], '1' ); ?>> Show on mobile devices</label></td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_page_visibility">Show On</label></th>
                        <td>
                            <select id="wpsc_page_visibility" name="wpsc_page_visibility">
                                <option value="all" <?php selected( $o['wpsc_page_visibility'], 'all' ); ?>>All pages</option>
                                <option value="homepage" <?php selected( $o['wpsc_page_visibility'], 'homepage' ); ?>>Homepage only</option>
                                <option value="exclude" <?php selected( $o['wpsc_page_visibility'], 'exclude' ); ?>>All pages except...</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_excluded_pages">Excluded Page IDs</label></th>
                        <td>
                            <input type="text" id="wpsc_excluded_pages" name="wpsc_excluded_pages" value="<?php echo esc_attr( $o['wpsc_excluded_pages'] ); ?>" class="regular-text">
                            <p class="description">Comma-separated page/post IDs to hide the widget on. Only used when "All pages except..." is selected.</p>
                        </td>
                    </tr>
                </table>

                <!-- ▸ Appearance -->
                <h2 class="title">Appearance</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="wpsc_bot_name">Bot Name</label></th>
                        <td><input type="text" id="wpsc_bot_name" name="wpsc_bot_name" value="<?php echo esc_attr( $o['wpsc_bot_name'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Avatar</th>
                        <td>
                            <?php
                            $avatar = isset( $o['wpsc_avatar'] ) && $o['wpsc_avatar'] ? $o['wpsc_avatar'] : 'bot';
                            $avatars = array(
                                'bot'    => array( 'label' => 'Robot',  'file' => 'avatar-bot.svg' ),
                                'male'   => array( 'label' => 'Male',   'file' => 'avatar-male.svg' ),
                                'female' => array( 'label' => 'Female', 'file' => 'avatar-female.svg' ),
                            );
                            ?>
                            <div style="display:flex;gap:16px;align-items:flex-end;">
                                <?php foreach ( $avatars as $key => $av ) : ?>
                                <label style="text-align:center;cursor:pointer;">
                                    <div style="width:64px;height:64px;border-radius:50%;overflow:hidden;border:3px solid <?php echo $avatar === $key ? '#2563eb' : '#e2e8f0'; ?>;transition:border-color 0.2s;margin-bottom:6px;">
                                        <img src="<?php echo esc_url( WPSC_PLUGIN_URL . 'assets/images/' . $av['file'] ); ?>" style="width:100%;height:100%;display:block;" alt="<?php echo esc_attr( $av['label'] ); ?>">
                                    </div>
                                    <input type="radio" name="wpsc_avatar" value="<?php echo esc_attr( $key ); ?>" <?php checked( $avatar, $key ); ?> style="margin:0 auto;display:block;">
                                    <span style="font-size:12px;color:#475569;"><?php echo esc_html( $av['label'] ); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_welcome_message">Welcome Message</label></th>
                        <td><textarea id="wpsc_welcome_message" name="wpsc_welcome_message" rows="3" class="large-text"><?php echo esc_textarea( $o['wpsc_welcome_message'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_quick_replies">Quick Reply Suggestions</label></th>
                        <td>
                            <textarea id="wpsc_quick_replies" name="wpsc_quick_replies" rows="4" class="large-text"><?php echo esc_textarea( $o['wpsc_quick_replies'] ); ?></textarea>
                            <p class="description">One per line. Leave blank to disable.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_primary_color">Primary Color</label></th>
                        <td><input type="text" id="wpsc_primary_color" name="wpsc_primary_color" value="<?php echo esc_attr( $o['wpsc_primary_color'] ); ?>" class="wpsc-color-picker"></td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_position">Widget Position</label></th>
                        <td>
                            <select id="wpsc_position" name="wpsc_position">
                                <option value="bottom-right" <?php selected( $o['wpsc_position'], 'bottom-right' ); ?>>Bottom Right</option>
                                <option value="bottom-left" <?php selected( $o['wpsc_position'], 'bottom-left' ); ?>>Bottom Left</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- ▸ Contact Options -->
                <h2 class="title">Contact Options</h2>
                <p>These appear as action buttons in the chat widget below the "Return to Start" button.</p>
                <table class="form-table">
                    <tr>
                        <th><label for="wpsc_contact_email">Email Address</label></th>
                        <td><input type="email" id="wpsc_contact_email" name="wpsc_contact_email" value="<?php echo esc_attr( $o['wpsc_contact_email'] ); ?>" class="regular-text" placeholder="hello@example.com"></td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_contact_phone">Phone Number</label></th>
                        <td><input type="text" id="wpsc_contact_phone" name="wpsc_contact_phone" value="<?php echo esc_attr( $o['wpsc_contact_phone'] ); ?>" class="regular-text" placeholder="+1 (555) 123-4567"></td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_contact_sms">SMS Number</label></th>
                        <td>
                            <input type="text" id="wpsc_contact_sms" name="wpsc_contact_sms" value="<?php echo esc_attr( $o['wpsc_contact_sms'] ); ?>" class="regular-text" placeholder="+15551234567">
                            <p class="description">Include country code. Leave blank to hide SMS button.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_contact_page_url">Contact Page URL</label></th>
                        <td><input type="url" id="wpsc_contact_page_url" name="wpsc_contact_page_url" value="<?php echo esc_attr( $o['wpsc_contact_page_url'] ); ?>" class="regular-text" placeholder="https://yoursite.com/contact"></td>
                    </tr>
                </table>

                <!-- ▸ Lead Capture -->
                <h2 class="title">Lead Capture</h2>
                <table class="form-table">
                    <tr>
                        <th>Enable Lead Capture</th>
                        <td>
                            <label><input type="checkbox" name="wpsc_lead_capture" value="1" <?php checked( $o['wpsc_lead_capture'], '1' ); ?>> Ask visitors for their info before chatting</label>
                            <p class="description">When enabled, visitors see a form before the chat starts. Collected data appears in Chat Logs.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_lead_capture_title">Form Title</label></th>
                        <td><input type="text" id="wpsc_lead_capture_title" name="wpsc_lead_capture_title" value="<?php echo esc_attr( $o['wpsc_lead_capture_title'] ); ?>" class="large-text"></td>
                    </tr>
                    <tr>
                        <th>Required Fields</th>
                        <td>
                            <label style="display:block;margin-bottom:6px;"><input type="hidden" name="wpsc_lead_require_email" value="0"><input type="checkbox" name="wpsc_lead_require_email" value="1" <?php checked( $o['wpsc_lead_require_email'], '1' ); ?>> Email address</label>
                            <label style="display:block;margin-bottom:6px;"><input type="hidden" name="wpsc_lead_require_name" value="0"><input type="checkbox" name="wpsc_lead_require_name" value="1" <?php checked( $o['wpsc_lead_require_name'], '1' ); ?>> Name</label>
                            <label style="display:block;margin-bottom:6px;"><input type="hidden" name="wpsc_lead_require_phone" value="0"><input type="checkbox" name="wpsc_lead_require_phone" value="1" <?php checked( $o['wpsc_lead_require_phone'], '1' ); ?>> Phone number</label>
                        </td>
                    </tr>
                </table>

                <!-- ▸ AI Provider -->
                <h2 class="title">AI Provider</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="wpsc_ai_provider">Provider</label></th>
                        <td>
                            <select id="wpsc_ai_provider" name="wpsc_ai_provider">
                                <option value="local" <?php selected( $o['wpsc_ai_provider'], 'local' ); ?>>Local (keyword matching)</option>
                                <option value="openai" <?php selected( $o['wpsc_ai_provider'], 'openai' ); ?>>OpenAI (GPT-4o-mini)</option>
                                <option value="anthropic" <?php selected( $o['wpsc_ai_provider'], 'anthropic' ); ?>>Anthropic (Claude)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_api_key">API Key</label></th>
                        <td><input type="password" id="wpsc_api_key" name="wpsc_api_key" value="<?php echo esc_attr( $o['wpsc_api_key'] ); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <!-- ▸ Content & Logging -->
                <h2 class="title">Content Sources & Logging</h2>
                <table class="form-table">
                    <tr>
                        <th>Post Types to Index</th>
                        <td>
                            <?php foreach ( $all_types as $type ) : ?>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="checkbox" name="wpsc_post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $post_types, true ) ); ?>>
                                    <?php echo esc_html( $type->labels->singular_name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Chat Logging</th>
                        <td>
                            <label><input type="hidden" name="wpsc_enable_logging" value="0"><input type="checkbox" name="wpsc_enable_logging" value="1" <?php checked( get_option( 'wpsc_enable_logging', '1' ), '1' ); ?>> Log all conversations</label>
                            <p class="description">Stores chat messages in the database. View them under Intelligize Chat → Chat Logs.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>

            <hr>
            <h2 class="title">Content Index</h2>
            <form method="post">
                <?php wp_nonce_field( 'wpsc_reindex_action' ); ?>
                <p>Rebuild the content index if your pages have changed.</p>
                <button type="submit" name="wpsc_reindex" class="button button-secondary">🔄 Rebuild Index Now</button>
            </form>
        </div>
        <script>jQuery(document).ready(function($){ $('.wpsc-color-picker').wpColorPicker(); });</script>
        <?php
    }

    // ══════════════════════════════════════════════════════════════════════
    // CHAT LOGS PAGE
    // ══════════════════════════════════════════════════════════════════════
    public function render_logs_page() {
        $search  = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $paged   = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page = 20;

        $sessions = WPSC_Chat_Logger::get_sessions( array( 'per_page' => $per_page, 'page' => $paged, 'search' => $search ) );
        $total    = WPSC_Chat_Logger::get_session_count( $search );
        $pages    = ceil( $total / $per_page );
        $stats    = WPSC_Chat_Logger::get_stats();
        ?>
        <div class="wrap">
            <h1>💬 Chat Logs</h1>

            <!-- Stats -->
            <div style="display:flex;gap:12px;margin:16px 0;">
                <span class="button disabled">📊 <?php echo $stats['total_sessions']; ?> total chats</span>
                <span class="button disabled">📅 <?php echo $stats['today_sessions']; ?> today</span>
                <span class="button disabled">📧 <?php echo $stats['leads_collected']; ?> leads</span>
                <a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=wpsc_export_csv' ), 'wpsc_export' ); ?>" class="button">📥 Export CSV</a>
            </div>

            <!-- Search -->
            <form method="get" style="margin-bottom:16px;">
                <input type="hidden" name="page" value="intelligize-chat-logs">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search messages, emails, names..." style="width:300px;">
                <input type="submit" class="button" value="Search">
                <?php if ( $search ) : ?>
                    <a href="<?php echo admin_url( 'admin.php?page=intelligize-chat-logs' ); ?>" class="button">Clear</a>
                <?php endif; ?>
            </form>

            <?php if ( empty( $sessions ) ) : ?>
                <div class="notice notice-info"><p>No chat logs found. Conversations will appear here once visitors start chatting.</p></div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:160px;">Date</th>
                            <th style="width:130px;">Visitor</th>
                            <th>First Question</th>
                            <th style="width:60px;">Msgs</th>
                            <th style="width:160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $sessions as $s ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo date( 'M j, Y', strtotime( $s->started_at ) ); ?></strong><br>
                                <span style="color:#646970;font-size:12px;"><?php echo date( 'g:i A', strtotime( $s->started_at ) ); ?></span>
                            </td>
                            <td>
                                <?php if ( $s->visitor_name ) : ?>
                                    <strong><?php echo esc_html( $s->visitor_name ); ?></strong><br>
                                <?php endif; ?>
                                <?php if ( $s->visitor_email ) : ?>
                                    <a href="mailto:<?php echo esc_attr( $s->visitor_email ); ?>" style="font-size:12px;"><?php echo esc_html( $s->visitor_email ); ?></a><br>
                                <?php endif; ?>
                                <span style="color:#646970;font-size:11px;"><?php echo esc_html( $s->visitor_ip ); ?></span>
                            </td>
                            <td><?php echo esc_html( wp_trim_words( $s->first_question ?? '—', 15 ) ); ?></td>
                            <td><span class="button disabled" style="min-width:auto;padding:0 8px;"><?php echo intval( $s->message_count ); ?></span></td>
                            <td>
                                <button class="button wpsc-view-chat" data-session="<?php echo esc_attr( $s->session_id ); ?>">👁 View</button>
                                <button class="button wpsc-delete-chat" data-session="<?php echo esc_attr( $s->session_id ); ?>" style="color:#d63638;">🗑</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ( $pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
                            <?php if ( $i === $paged ) : ?>
                                <span class="button disabled"><?php echo $i; ?></span>
                            <?php else : ?>
                                <a class="button" href="<?php echo add_query_arg( array( 'paged' => $i, 's' => $search ), admin_url( 'admin.php?page=intelligize-chat-logs' ) ); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Chat viewer modal -->
            <div id="wpsc-chat-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100000;justify-content:center;align-items:center;">
                <div style="background:#fff;border-radius:12px;width:500px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                        <strong>💬 Chat Transcript</strong>
                        <button id="wpsc-close-modal" class="button">✕ Close</button>
                    </div>
                    <div id="wpsc-modal-body" style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;"></div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // View chat
            $('.wpsc-view-chat').on('click', function() {
                var sid = $(this).data('session');
                $('#wpsc-modal-body').html('<p>Loading...</p>');
                $('#wpsc-chat-modal').css('display','flex');
                $.post(ajaxurl, { action: 'wpsc_view_session', session_id: sid, _wpnonce: '<?php echo wp_create_nonce( 'wpsc_logs' ); ?>' }, function(r) {
                    if (r.success) {
                        var html = '';
                        r.data.forEach(function(m) {
                            var bg = m.role === 'user' ? '#2563eb' : '#f1f5f9';
                            var color = m.role === 'user' ? '#fff' : '#1e293b';
                            var align = m.role === 'user' ? 'flex-end' : 'flex-start';
                            var label = m.role === 'user' ? '👤 Visitor' : '🤖 Bot';
                            html += '<div style="align-self:'+align+';max-width:85%;background:'+bg+';color:'+color+';padding:10px 14px;border-radius:12px;">';
                            html += '<div style="font-size:11px;opacity:0.7;margin-bottom:4px;">'+label+' · '+m.time+'</div>';
                            html += m.message + '</div>';
                        });
                        $('#wpsc-modal-body').html(html);
                    }
                });
            });
            $('#wpsc-close-modal, #wpsc-chat-modal').on('click', function(e) {
                if (e.target === this) $('#wpsc-chat-modal').hide();
            });
            // Delete chat
            $('.wpsc-delete-chat').on('click', function() {
                if (!confirm('Delete this conversation?')) return;
                var btn = $(this), sid = btn.data('session');
                $.post(ajaxurl, { action: 'wpsc_delete_session', session_id: sid, _wpnonce: '<?php echo wp_create_nonce( 'wpsc_logs' ); ?>' }, function(r) {
                    if (r.success) btn.closest('tr').fadeOut();
                });
            });
        });
        </script>
        <?php
    }

    // ── AJAX Handlers ────────────────────────────────────────────────────
    public function ajax_view_session() {
        check_ajax_referer( 'wpsc_logs' );
        $sid = sanitize_text_field( $_POST['session_id'] );
        $msgs = WPSC_Chat_Logger::get_session_messages( $sid );
        $data = array();
        foreach ( $msgs as $m ) {
            $data[] = array(
                'role'    => $m->role,
                'message' => esc_html( $m->message ),
                'time'    => date( 'M j, g:i A', strtotime( $m->created_at ) ),
            );
        }
        wp_send_json_success( $data );
    }

    public function ajax_delete_session() {
        check_ajax_referer( 'wpsc_logs' );
        $sid = sanitize_text_field( $_POST['session_id'] );
        WPSC_Chat_Logger::delete_session( $sid );
        wp_send_json_success();
    }

    public function ajax_export_csv() {
        check_admin_referer( 'wpsc_export' );
        $rows = WPSC_Chat_Logger::export_csv();
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="intelligize-chat-logs-' . date( 'Y-m-d' ) . '.csv"' );
        $out = fopen( 'php://output', 'w' );
        if ( ! empty( $rows ) ) {
            fputcsv( $out, array_keys( $rows[0] ) );
            foreach ( $rows as $row ) {
                fputcsv( $out, $row );
            }
        }
        fclose( $out );
        exit;
    }
}
