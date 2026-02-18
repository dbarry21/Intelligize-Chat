<?php
/**
 * Admin Settings Page
 *
 * @package WP_SmartChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPSC_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Re-index when a post is saved or deleted
        add_action( 'save_post', array( $this, 'schedule_reindex' ), 20 );
        add_action( 'delete_post', array( $this, 'schedule_reindex' ), 20 );
    }

    /**
     * Add the settings page under the Settings menu.
     */
    public function add_menu_page() {
        add_options_page(
            __( 'Intelligize Chat', 'intelligize-chat' ),
            __( 'Intelligize Chat', 'intelligize-chat' ),
            'manage_options',
            'wp-smartchat',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register all settings.
     */
    public function register_settings() {
        register_setting( 'wpsc_settings', 'wpsc_bot_name', 'sanitize_text_field' );
        register_setting( 'wpsc_settings', 'wpsc_welcome_message', 'sanitize_textarea_field' );
        register_setting( 'wpsc_settings', 'wpsc_primary_color', 'sanitize_hex_color' );
        register_setting( 'wpsc_settings', 'wpsc_position' );
        register_setting( 'wpsc_settings', 'wpsc_ai_provider' );
        register_setting( 'wpsc_settings', 'wpsc_api_key' );
        register_setting( 'wpsc_settings', 'wpsc_quick_replies', 'sanitize_textarea_field' );
        register_setting( 'wpsc_settings', 'wpsc_post_types', array(
            'type'              => 'array',
            'sanitize_callback' => function ( $input ) {
                return is_array( $input ) ? array_map( 'sanitize_text_field', $input ) : array( 'page', 'post' );
            },
        ) );
    }

    /**
     * Admin styles.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'settings_page_wp-smartchat' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }

    /**
     * Rebuild content index.
     */
    public function schedule_reindex() {
        $indexer = new WPSC_Content_Indexer();
        $indexer->build_index();
    }

    /**
     * Render the admin page.
     */
    public function render_settings_page() {
        // Handle manual reindex
        if ( isset( $_POST['wpsc_reindex'] ) && check_admin_referer( 'wpsc_reindex_action' ) ) {
            $indexer = new WPSC_Content_Indexer();
            $index   = $indexer->build_index();
            echo '<div class="notice notice-success"><p>Content index rebuilt — ' . count( $index ) . ' pages indexed.</p></div>';
        }

        $bot_name    = get_option( 'wpsc_bot_name', 'Intelligize Assistant' );
        $welcome     = get_option( 'wpsc_welcome_message', 'Hi there! 👋 How can I help you today?' );
        $color       = get_option( 'wpsc_primary_color', '#2563eb' );
        $position    = get_option( 'wpsc_position', 'bottom-right' );
        $provider    = get_option( 'wpsc_ai_provider', 'local' );
        $api_key     = get_option( 'wpsc_api_key', '' );
        $post_types  = get_option( 'wpsc_post_types', array( 'page', 'post' ) );
        $quick_replies = get_option( 'wpsc_quick_replies', "What services do you offer?\nHow can I contact you?\nTell me about pricing" );
        $all_types   = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <div class="wrap">
            <h1>🤖 Intelligize Chat Settings</h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'wpsc_settings' ); ?>

                <h2 class="title">Appearance</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="wpsc_bot_name">Bot Name</label></th>
                        <td><input type="text" id="wpsc_bot_name" name="wpsc_bot_name" value="<?php echo esc_attr( $bot_name ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_welcome_message">Welcome Message</label></th>
                        <td><textarea id="wpsc_welcome_message" name="wpsc_welcome_message" rows="3" class="large-text"><?php echo esc_textarea( $welcome ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_quick_replies">Quick Reply Suggestions</label></th>
                        <td>
                            <textarea id="wpsc_quick_replies" name="wpsc_quick_replies" rows="4" class="large-text"><?php echo esc_textarea( $quick_replies ); ?></textarea>
                            <p class="description">One suggestion per line. These appear as clickable buttons after the welcome message. Leave blank to disable.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_primary_color">Primary Color</label></th>
                        <td><input type="text" id="wpsc_primary_color" name="wpsc_primary_color" value="<?php echo esc_attr( $color ); ?>" class="wpsc-color-picker"></td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_position">Widget Position</label></th>
                        <td>
                            <select id="wpsc_position" name="wpsc_position">
                                <option value="bottom-right" <?php selected( $position, 'bottom-right' ); ?>>Bottom Right</option>
                                <option value="bottom-left" <?php selected( $position, 'bottom-left' ); ?>>Bottom Left</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2 class="title">AI Provider</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="wpsc_ai_provider">Provider</label></th>
                        <td>
                            <select id="wpsc_ai_provider" name="wpsc_ai_provider">
                                <option value="local" <?php selected( $provider, 'local' ); ?>>Local (keyword matching — no API needed)</option>
                                <option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI (GPT-4o-mini)</option>
                                <option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>>Anthropic (Claude)</option>
                            </select>
                            <p class="description">Local mode works without any API key. AI modes give much more natural answers.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpsc_api_key">API Key</label></th>
                        <td>
                            <input type="password" id="wpsc_api_key" name="wpsc_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
                            <p class="description">Only needed if using OpenAI or Anthropic.</p>
                        </td>
                    </tr>
                </table>

                <h2 class="title">Content Sources</h2>
                <table class="form-table">
                    <tr>
                        <th>Post Types to Index</th>
                        <td>
                            <?php foreach ( $all_types as $type ) : ?>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="checkbox" name="wpsc_post_types[]" value="<?php echo esc_attr( $type->name ); ?>"
                                        <?php checked( in_array( $type->name, $post_types, true ) ); ?>>
                                    <?php echo esc_html( $type->labels->singular_name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>

            <hr>
            <h2 class="title">Content Index</h2>
            <form method="post">
                <?php wp_nonce_field( 'wpsc_reindex_action' ); ?>
                <p>Rebuild the content index if your pages have changed and the chatbot isn't finding the right answers.</p>
                <button type="submit" name="wpsc_reindex" class="button button-secondary">🔄 Rebuild Index Now</button>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.wpsc-color-picker').wpColorPicker();
        });
        </script>
        <?php
    }
}
