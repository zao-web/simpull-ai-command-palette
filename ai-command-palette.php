<?php
/**
 * Plugin Name: AI Command Palette
 * Plugin URI: https://github.com/yourusername/ai-command-palette
 * Description: AI-powered universal command palette for WordPress that understands natural language and exposes all WordPress functionality
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-command-palette
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AICP_VERSION', '1.0.0');
define('AICP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AICP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AICP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
require_once AICP_PLUGIN_DIR . 'vendor/autoload.php';

// Main plugin class
class AI_Command_Palette {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Initialize components
        add_action('init', [$this, 'init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('rest_api_init', [$this, 'register_ai_processor_routes']);

        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Hook into plugin activation and deactivation to refresh dynamic commands
        add_action('activated_plugin', function() {
            \AICP\Core\Command_Registry::refresh_dynamic_commands();
        });
        add_action('deactivated_plugin', function() {
            \AICP\Core\Command_Registry::refresh_dynamic_commands();
        });
    }

    public function activate() {
        // Create database tables if needed
        $this->create_tables();

        // Set default options
        add_option('aicp_settings', [
            'api_provider' => 'openai',
            'api_key' => '',
            'enable_frontend' => true,
            'enable_personalization' => true,
            'keyboard_shortcut' => 'cmd+k,ctrl+k'
        ]);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function deactivate() {
        // Cleanup tasks
        flush_rewrite_rules();
    }

    public function init() {
        // Load text domain
        load_plugin_textdomain('ai-command-palette', false, dirname(AICP_PLUGIN_BASENAME) . '/languages');

        // Run database migrations for existing installations
        $this->run_database_migrations();

        // Initialize core components
        $this->load_components();
    }

    private function load_components() {
        // Core components
        new \AICP\Core\API_Discovery();
        \AICP\Core\Command_Registry::get_instance();
        new \AICP\Core\Context_Engine();
        new \AICP\Core\Execution_Engine();
        new \AICP\AI\AI_Service();
        new \AICP\UI\Frontend_Handler();
    }

    public function enqueue_admin_assets($hook) {
        // Enqueue on all admin pages
        $this->enqueue_palette_assets();
    }

    public function enqueue_frontend_assets() {
        // Only for logged-in users with appropriate permissions
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            return;
        }

        $settings = get_option('aicp_settings', []);
        if (empty($settings['enable_frontend'])) {
            return;
        }

        $this->enqueue_palette_assets();
    }

    private function enqueue_palette_assets() {
        // Use @wordpress/scripts build output and asset file
        $asset_file = include AICP_PLUGIN_DIR . 'build/index.asset.php';
        wp_enqueue_script(
            'aicp-command-palette',
            AICP_PLUGIN_URL . 'build/index.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        // Enqueue CSS if present
        $css_file = AICP_PLUGIN_DIR . 'build/index.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'aicp-command-palette',
                AICP_PLUGIN_URL . 'build/index.css',
                [],
                $asset_file['version']
            );
        }

        // Localize script with data
        wp_localize_script('aicp-command-palette', 'aicpData', [
            'apiUrl' => home_url('/wp-json/ai-command-palette/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUser' => [
                'id' => get_current_user_id(),
                'role' => wp_get_current_user()->roles[0] ?? '',
                'capabilities' => wp_get_current_user()->allcaps ?? []
            ],
            'context' => $this->get_current_context(),
            'staticCommands' => $this->get_static_commands(),
            'settings' => get_option('aicp_settings', []),
            'woocommerce_active' => class_exists('WooCommerce'),
        ]);
    }

    private function get_current_context() {
        global $pagenow, $post;

        $context = [
            'screen' => $pagenow,
            'isAdmin' => is_admin(),
            'isFrontend' => !is_admin(),
            'postId' => $post->ID ?? null,
            'postType' => $post->post_type ?? null,
        ];

        // Add current admin page info
        if (is_admin()) {
            $context['adminPage'] = $_GET['page'] ?? '';
            $context['action'] = $_GET['action'] ?? '';
        }

        return $context;
    }

    private function get_static_commands() {
        // Get admin menu items for quick navigation
        global $menu, $submenu;

        $commands = [];

        // Convert admin menu to commands
        if (is_admin()) {
            foreach ($menu as $item) {
                if (!empty($item[0]) && !empty($item[2]) && current_user_can($item[1])) {
                    $commands[] = [
                        'id' => 'nav_' . sanitize_key($item[2]),
                        'title' => $item[0],
                        'category' => 'navigation',
                        'icon' => $item[6] ?? 'dashicons-admin-generic',
                        'action' => [
                            'type' => 'navigate',
                            'url' => menu_page_url($item[2], false) !== '' ? menu_page_url($item[2], false) : admin_url($item[2])
                        ]
                    ];
                }
            }
        }

        // Add common actions
        $common_actions = [
            [
                'id' => 'new_post',
                'title' => __('Create New Post', 'ai-command-palette'),
                'category' => 'content',
                'icon' => 'dashicons-edit',
                'capability' => 'edit_posts',
                'action' => [
                    'type' => 'navigate',
                    'url' => admin_url('post-new.php?post_type=post')
                ]
            ],
            [
                'id' => 'new_page',
                'title' => __('Create New Page', 'ai-command-palette'),
                'category' => 'content',
                'icon' => 'dashicons-admin-page',
                'capability' => 'edit_pages',
                'action' => [
                    'type' => 'navigate',
                    'url' => admin_url('post-new.php?post_type=page')
                ]
            ],
            [
                'id' => 'media_library',
                'title' => __('Media Library', 'ai-command-palette'),
                'category' => 'navigation',
                'icon' => 'dashicons-admin-media',
                'capability' => 'upload_files',
                'action' => [
                    'type' => 'navigate',
                    'url' => admin_url('upload.php')
                ]
            ]
        ];

        foreach ($common_actions as $action) {
            if (empty($action['capability']) || current_user_can($action['capability'])) {
                $commands[] = $action;
            }
        }

        // Add submenu items as hidden navigation commands (searchable, but not shown by default)
        if (is_admin() && !empty($submenu)) {
            // Build a map of parent_slug => label for quick lookup
            $parent_labels = [];
            foreach ($menu as $item) {
                if (!empty($item[2]) && !empty($item[0])) {
                    $parent_labels[$item[2]] = wp_strip_all_tags($item[0]);
                }
            }
            foreach ($submenu as $parent_slug => $items) {
                $parent_label = isset($parent_labels[$parent_slug]) ? $parent_labels[$parent_slug] : ucfirst($parent_slug);
                foreach ($items as $item) {
                    // $item: [0] => label, [1] => capability, [2] => slug, [4] => icon (optional)
                    if (!empty($item[0]) && !empty($item[2]) && current_user_can($item[1])) {
                        $commands[] = [
                            'id' => 'nav_' . sanitize_key($parent_slug . '_' . $item[2]),
                            'title' => $item[0],
                            'category' => $parent_label,
                            'icon' => isset($item[4]) ? $item[4] : 'dashicons-admin-generic',
                            'action' => [
                                'type' => 'navigate',
                                'url' => menu_page_url($item[2], false) !== '' ? menu_page_url($item[2], false) : admin_url($item[2])
                            ],
                            'hidden' => true // Mark as hidden for default display
                        ];
                    }
                }
            }
        }

        return $commands;
    }

    public function register_rest_routes() {
        register_rest_route('ai-command-palette/v1', '/execute', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_execute_command'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        register_rest_route('ai-command-palette/v1', '/search', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_search'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        register_rest_route('ai-command-palette/v1', '/ai-interpret', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ai_interpret'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        // New: Discovered endpoints
        register_rest_route('ai-command-palette/v1', '/discovered-endpoints', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_discovered_endpoints'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        // New: Execute dynamic command
        register_rest_route('ai-command-palette/v1', '/execute-dynamic', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_execute_dynamic'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        // New: Contextual suggestions
        register_rest_route('ai-command-palette/v1', '/contextual-suggestions', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_contextual_suggestions'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        // User shortcut endpoints
        register_rest_route('ai-command-palette/v1', '/user-shortcut', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_user_shortcut'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
        register_rest_route('ai-command-palette/v1', '/user-shortcut', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_set_user_shortcut'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        // Available models endpoint
        register_rest_route('ai-command-palette/v1', '/models', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_models'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function register_ai_processor_routes() {
        register_rest_route('ai-command-palette/v1', '/ai-process', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ai_process'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        register_rest_route('ai-command-palette/v1', '/function-schema', [
            'methods'  => 'GET',
            'callback' => [ $this, 'handle_get_function_schema' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'name' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_string($param);
                    }
                ],
            ],
        ] );
    }

    public function handle_execute_command($request) {
        $command = $request->get_param('command');
        $params = $request->get_param('params');

        $execution_engine = new \AICP\Core\Execution_Engine();
        return $execution_engine->execute($command, $params);
    }

        public function handle_search($request) {
        $query = $request->get_param('query');
        $context = $request->get_param('context');

        $command_registry = \AICP\Core\Command_Registry::get_instance();
        return $command_registry->search($query, $context);
    }

    public function handle_ai_interpret($request) {
        $query = $request->get_param('query');
        $context = $request->get_param('context');

        $ai_service = new \AICP\AI\AI_Service();
        return $ai_service->interpret($query, $context);
    }

    public function handle_discovered_endpoints($request) {
        $api_discovery = new \AICP\Core\API_Discovery();
        $endpoints = $api_discovery->get_discovered_endpoints();
        // Optionally filter endpoints by user capability here
        return rest_ensure_response($endpoints);
    }

    public function handle_execute_dynamic($request) {
        $endpoint = $request->get_param('endpoint');
        $method = $request->get_param('method');
        $params = $request->get_param('params');
        // Validate input
        if (!$endpoint || !$method) {
            return new \WP_Error('invalid_params', 'Endpoint and method are required', ['status' => 400]);
        }
        // Make internal REST request
        $url = home_url($endpoint);
        $args = [
            'method' => strtoupper($method),
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];
        if (!empty($params)) {
            if ($method === 'GET') {
                $url = add_query_arg($params, $url);
            } else {
                $args['body'] = json_encode($params);
            }
        }
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return new \WP_Error('rest_proxy_error', $response->get_error_message(), ['status' => 500]);
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return rest_ensure_response([
            'status' => $code,
            'data' => $data,
        ]);
    }

    public function handle_contextual_suggestions($request) {
        $context_engine = new \AICP\Core\Context_Engine();
        $context = $this->get_current_context();
        $suggestions = $context_engine->get_contextual_suggestions($context);
        return rest_ensure_response($suggestions);
    }

    public function handle_get_user_shortcut($request) {
        $user_id = get_current_user_id();
        $shortcut = get_user_meta($user_id, 'aicp_palette_shortcut', true);
        return rest_ensure_response(['shortcut' => $shortcut]);
    }
    public function handle_set_user_shortcut($request) {
        $user_id = get_current_user_id();
        $shortcut = sanitize_text_field($request->get_param('shortcut'));
        if (!$shortcut) {
            return new \WP_Error('invalid_shortcut', 'Shortcut is required', ['status' => 400]);
        }
        update_user_meta($user_id, 'aicp_palette_shortcut', $shortcut);
        return rest_ensure_response(['success' => true, 'shortcut' => $shortcut]);
    }

    public function handle_get_function_schema( \WP_REST_Request $request ) {
        $function_name = $request->get_param('name');
        if (empty($function_name)) {
            return new \WP_Error('bad_request', 'Function name is required.', ['status' => 400]);
        }

        $ai_processor = new \AICP\Core\AI_Processor();
        $schema = $ai_processor->get_function_schema($function_name);

        if (empty($schema)) {
            return new \WP_Error('not_found', 'Function schema not found.', ['status' => 404]);
        }

        return new \WP_REST_Response($schema, 200);
    }

    public function handle_ai_process($request) {
        $request_data = $request->get_json_params();

        // Log the incoming request for debugging
        error_log('AICP AI Process Request: ' . wp_json_encode($request_data));

        if (empty($request_data)) {
            error_log('AICP AI Process Error: Empty request data');
            return new \WP_Error('invalid_request', 'Request data is required', ['status' => 400]);
        }

        try {
            $ai_processor = new \AICP\Core\AI_Processor();
            $result = $ai_processor->process_request($request_data);

            // Log successful processing
            error_log('AICP AI Process Success: ' . wp_json_encode($result));

            return rest_ensure_response($result);
        } catch (\Exception $e) {
            // Log the full exception details
            error_log('AICP AI Process Exception: ' . $e->getMessage());
            error_log('AICP AI Process Exception Trace: ' . $e->getTraceAsString());
            error_log('AICP AI Process Exception File: ' . $e->getFile() . ':' . $e->getLine());

            return new \WP_Error('ai_processing_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function handle_get_models($request) {
        $provider = $request->get_param('provider');
        $api_key = $request->get_param('api_key');

        if (empty($provider) || empty($api_key)) {
            return new \WP_Error('missing_params', 'Provider and API key are required', ['status' => 400]);
        }

        try {
            $ai_service = new \AICP\AI\AI_Service();
            $result = $ai_service->get_available_models($provider, $api_key);

            return rest_ensure_response($result);
        } catch (\Exception $e) {
            return new \WP_Error('models_fetch_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function add_admin_menu() {
        add_options_page(
            __('AI Command Palette', 'ai-command-palette'),
            __('AI Command Palette', 'ai-command-palette'),
            'manage_options',
            'ai-command-palette',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        include AICP_PLUGIN_DIR . 'templates/settings.php';
    }

    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Command usage tracking
        $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aicp_command_usage (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            command_id varchar(255) NOT NULL,
            command_text text NOT NULL,
            intent_classified varchar(100),
            execution_time_ms int(11),
            success tinyint(1),
            context longtext,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY command_id (command_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // User context and preferences
        $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aicp_user_context (
            user_id bigint(20) NOT NULL,
            preferred_commands longtext,
            usage_patterns longtext,
            role_permissions longtext,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) $charset_collate;";

        // Plugin capabilities cache
        $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aicp_plugin_registry (
            plugin_slug varchar(255) NOT NULL,
            capabilities longtext,
            endpoints longtext,
            commands longtext,
            last_scanned timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (plugin_slug)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);

        // Run database migrations
        $this->run_database_migrations();
    }

    private function run_database_migrations() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aicp_command_usage';
        $current_version = get_option('aicp_db_version', '1.0');

        // Migration to version 1.1 - Add command_id and context columns
        if (version_compare($current_version, '1.1', '<')) {
            // Check if command_id column exists
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'command_id'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN command_id varchar(255) NOT NULL DEFAULT '' AFTER user_id");
                $wpdb->query("ALTER TABLE {$table_name} ADD INDEX command_id (command_id)");
            }

            // Check if context column exists
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'context'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN context longtext AFTER success");
            }

            update_option('aicp_db_version', '1.1');
        }
    }
}

// Initialize the plugin
AI_Command_Palette::get_instance();