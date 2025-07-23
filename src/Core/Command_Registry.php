<?php
namespace AICP\Core;

class Command_Registry {
    private $commands = [];
    private $dynamic_commands = [];
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Refresh dynamic commands from current discovered endpoints
     */
    public static function refresh_dynamic_commands() {
        $instance = self::get_instance();
        // Remove old dynamic commands
        foreach ($instance->commands as $id => $cmd) {
            if (strpos($id, 'dynamic_') === 0) {
                unset($instance->commands[$id]);
            }
        }
        // Re-register from current endpoints
        $api_discovery = new \AICP\Core\API_Discovery();
        $instance->register_dynamic_commands_from_endpoints($api_discovery->get_discovered_endpoints());
    }

    /**
     * Register dynamic commands from discovered endpoints
     */
    public function register_dynamic_commands_from_endpoints($endpoints) {
        foreach ($endpoints as $ep) {
            if (empty($ep['route']) || empty($ep['methods'])) continue;
            $id = 'dynamic_' . md5($ep['route'] . implode(',', (array)$ep['methods']));
            $title = $ep['description'] ? $ep['description'] : 'Call ' . $ep['route'];
            $category = $ep['category'] ?? 'api';
            $plugin = $ep['plugin'] ?? $category; // Use plugin if available, else category
            $params = [];
            if (!empty($ep['schema']['parameters'])) {
                foreach ($ep['schema']['parameters'] as $pname => $pinfo) {
                    $params[] = [
                        'name' => $pname,
                        'type' => $pinfo['type'] ?? 'string',
                        'description' => $pinfo['description'] ?? '',
                        'required' => $pinfo['required'] ?? false,
                    ];
                }
            }
            $this->register_command($id, [
                'title' => $title,
                'description' => $ep['route'],
                'category' => $category,
                'plugin' => $plugin,
                'icon' => 'dashicons-rest-api',
                'action' => [
                    'type' => 'dynamic_api',
                    'endpoint' => $ep['route'],
                    'methods' => $ep['methods'],
                    'parameters' => $params,
                ],
                'keywords' => [$ep['route'], $title],
                'priority' => 5,
            ]);
        }
    }

    /**
     * In the constructor, after registering core/plugin commands, also register dynamic commands
     */
    private function __construct() {
        $this->register_core_commands();
        $this->register_plugin_commands();
        // Register dynamic commands from discovered endpoints
        $api_discovery = new \AICP\Core\API_Discovery();
        $this->register_dynamic_commands_from_endpoints($api_discovery->get_discovered_endpoints());
        // Allow other plugins to register commands
        do_action('aicp_register_commands', $this);
    }

    /**
     * Register a command
     */
    public function register_command($id, $command_data) {
        $defaults = [
            'id' => $id,
            'title' => '',
            'description' => '',
            'category' => 'general',
            'icon' => 'dashicons-admin-generic',
            'capability' => '',
            'callback' => null,
            'keywords' => [],
            'priority' => 10,
            'context' => ['admin', 'frontend']
        ];

        $command = wp_parse_args($command_data, $defaults);

        // Validate required fields
        if (empty($command['title'])) {
            return false;
        }

        $this->commands[$id] = $command;
        return true;
    }

    /**
     * Register core WordPress commands
     */
    private function register_core_commands() {
        // Content creation commands
        $this->register_command('create_post', [
            'title' => __('Create New Post', 'ai-command-palette'),
            'description' => __('Create a new blog post', 'ai-command-palette'),
            'category' => 'content',
            'icon' => 'dashicons-edit',
            'capability' => 'edit_posts',
            'keywords' => ['new', 'post', 'blog', 'article', 'write'],
            'callback' => [$this, 'create_post_callback']
        ]);

        $this->register_command('create_page', [
            'title' => __('Create New Page', 'ai-command-palette'),
            'description' => __('Create a new page', 'ai-command-palette'),
            'category' => 'content',
            'icon' => 'dashicons-admin-page',
            'capability' => 'edit_pages',
            'keywords' => ['new', 'page', 'create'],
            'callback' => [$this, 'create_page_callback']
        ]);

        // Media commands
        $this->register_command('upload_media', [
            'title' => __('Upload Media', 'ai-command-palette'),
            'description' => __('Upload images, videos, or other media files', 'ai-command-palette'),
            'category' => 'media',
            'icon' => 'dashicons-admin-media',
            'capability' => 'upload_files',
            'keywords' => ['upload', 'media', 'image', 'video', 'file']
        ]);

        // User management commands
        $this->register_command('create_user', [
            'title' => __('Create New User', 'ai-command-palette'),
            'description' => __('Add a new user to the site', 'ai-command-palette'),
            'category' => 'users',
            'icon' => 'dashicons-admin-users',
            'capability' => 'create_users',
            'keywords' => ['new', 'user', 'add', 'member']
        ]);

        // Plugin management commands
        $this->register_command('activate_plugin', [
            'title' => __('Activate Plugin', 'ai-command-palette'),
            'description' => __('Activate an installed plugin', 'ai-command-palette'),
            'category' => 'plugins',
            'icon' => 'dashicons-admin-plugins',
            'capability' => 'activate_plugins',
            'keywords' => ['activate', 'plugin', 'enable']
        ]);

        $this->register_command('deactivate_plugin', [
            'title' => __('Deactivate Plugin', 'ai-command-palette'),
            'description' => __('Deactivate an active plugin', 'ai-command-palette'),
            'category' => 'plugins',
            'icon' => 'dashicons-admin-plugins',
            'capability' => 'activate_plugins',
            'keywords' => ['deactivate', 'plugin', 'disable']
        ]);

        // Settings commands
        $this->register_command('update_site_title', [
            'title' => __('Update Site Title', 'ai-command-palette'),
            'description' => __('Change the site title', 'ai-command-palette'),
            'category' => 'settings',
            'icon' => 'dashicons-admin-settings',
            'capability' => 'manage_options',
            'keywords' => ['site', 'title', 'name', 'settings']
        ]);

        $this->register_command('update_tagline', [
            'title' => __('Update Tagline', 'ai-command-palette'),
            'description' => __('Change the site tagline', 'ai-command-palette'),
            'category' => 'settings',
            'icon' => 'dashicons-admin-settings',
            'capability' => 'manage_options',
            'keywords' => ['tagline', 'description', 'settings']
        ]);

        // Cache commands
        $this->register_command('clear_cache', [
            'title' => __('Clear Cache', 'ai-command-palette'),
            'description' => __('Clear all caches', 'ai-command-palette'),
            'category' => 'performance',
            'icon' => 'dashicons-performance',
            'capability' => 'manage_options',
            'keywords' => ['cache', 'clear', 'flush', 'purge']
        ]);

        // Theme commands
        $this->register_command('customize_theme', [
            'title' => __('Customize Theme', 'ai-command-palette'),
            'description' => __('Open the theme customizer', 'ai-command-palette'),
            'category' => 'appearance',
            'icon' => 'dashicons-admin-appearance',
            'capability' => 'edit_theme_options',
            'keywords' => ['customize', 'theme', 'appearance', 'design']
        ]);
    }

    /**
     * Register plugin-specific commands
     */
    private function register_plugin_commands() {
        // WooCommerce commands
        if (class_exists('WooCommerce')) {
            $this->register_command('wc_create_product', [
                'title' => __('Create Product', 'ai-command-palette'),
                'description' => __('Create a new WooCommerce product', 'ai-command-palette'),
                'category' => 'woocommerce',
                'icon' => 'dashicons-cart',
                'capability' => 'edit_products',
                'keywords' => ['product', 'woocommerce', 'shop', 'store']
            ]);

            $this->register_command('wc_view_orders', [
                'title' => __('View Orders', 'ai-command-palette'),
                'description' => __('View WooCommerce orders', 'ai-command-palette'),
                'category' => 'woocommerce',
                'icon' => 'dashicons-list-view',
                'capability' => 'edit_shop_orders',
                'keywords' => ['orders', 'woocommerce', 'sales']
            ]);
        }

        // ACF commands
        if (class_exists('ACF')) {
            $this->register_command('acf_create_field_group', [
                'title' => __('Create Field Group', 'ai-command-palette'),
                'description' => __('Create a new ACF field group', 'ai-command-palette'),
                'category' => 'acf',
                'icon' => 'dashicons-welcome-widgets-menus',
                'capability' => 'manage_options',
                'keywords' => ['acf', 'field', 'group', 'custom']
            ]);
        }
    }

    /**
     * Search commands
     */
    public function search($query, $context = []) {
        $results = [];
        $query_lower = strtolower($query);

        foreach ($this->commands as $command) {
            // Check capability
            if (!empty($command['capability']) && !current_user_can($command['capability'])) {
                continue;
            }

            // Check context
            if (!empty($context['isAdmin']) && !in_array('admin', $command['context'])) {
                continue;
            }
            if (!empty($context['isFrontend']) && !in_array('frontend', $command['context'])) {
                continue;
            }

            // Calculate relevance score
            $score = $this->calculate_relevance($query_lower, $command);

            if ($score > 0) {
                $command['score'] = $score;
                $results[] = $command;
            }
        }

        // Sort by score
        usort($results, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        // Add dynamic commands based on query
        $dynamic_results = $this->get_dynamic_commands($query, $context);
        $results = array_merge($results, $dynamic_results);

        return array_slice($results, 0, 20); // Limit to 20 results
    }

    /**
     * Calculate relevance score for a command
     */
    private function calculate_relevance($query, $command) {
        $score = 0;

        // Exact title match
        if (strtolower($command['title']) === $query) {
            $score += 100;
        }

        // Title contains query
        if (stripos($command['title'], $query) !== false) {
            $score += 50;
        }

        // Description contains query
        if (stripos($command['description'], $query) !== false) {
            $score += 30;
        }

        // Keywords match
        foreach ($command['keywords'] as $keyword) {
            if (stripos($keyword, $query) !== false) {
                $score += 20;
            }
            if (stripos($query, $keyword) !== false) {
                $score += 10;
            }
        }

        // Category match
        if (stripos($command['category'], $query) !== false) {
            $score += 15;
        }

        // Boost by priority
        $score += $command['priority'];

        return $score;
    }

    /**
     * Get dynamic commands based on query
     */
    private function get_dynamic_commands($query, $context) {
        $commands = [];

        // Search for posts/pages by title
        if (strlen($query) > 2) {
            $posts = get_posts([
                's' => $query,
                'post_type' => ['post', 'page'],
                'posts_per_page' => 5,
                'post_status' => 'any'
            ]);

            foreach ($posts as $post) {
                if (current_user_can('edit_post', $post->ID)) {
                    $commands[] = [
                        'id' => 'edit_' . $post->ID,
                        'title' => sprintf(__('Edit: %s', 'ai-command-palette'), $post->post_title),
                        'description' => sprintf(__('Edit %s "%s"', 'ai-command-palette'), $post->post_type, $post->post_title),
                        'category' => 'content',
                        'icon' => $post->post_type === 'page' ? 'dashicons-admin-page' : 'dashicons-edit',
                        'action' => [
                            'type' => 'navigate',
                            'url' => get_edit_post_link($post->ID, 'raw')
                        ],
                        'score' => 40
                    ];
                }
            }

            // Search for users
            if (current_user_can('list_users')) {
                $users = get_users([
                    'search' => '*' . $query . '*',
                    'number' => 3
                ]);

                foreach ($users as $user) {
                    $commands[] = [
                        'id' => 'edit_user_' . $user->ID,
                        'title' => sprintf(__('Edit User: %s', 'ai-command-palette'), $user->display_name),
                        'description' => $user->user_email,
                        'category' => 'users',
                        'icon' => 'dashicons-admin-users',
                        'action' => [
                            'type' => 'navigate',
                            'url' => get_edit_user_link($user->ID)
                        ],
                        'score' => 35
                    ];
                }
            }
        }

        return $commands;
    }

    /**
     * Get command by ID
     */
    public function get_command($id) {
        return $this->commands[$id] ?? null;
    }

    /**
     * Get all commands
     */
    public function get_all_commands() {
        return $this->commands;
    }

    /**
     * Get commands by category
     */
    public function get_commands_by_category($category) {
        return array_filter($this->commands, function($command) use ($category) {
            return $command['category'] === $category;
        });
    }

    // Callback methods for core commands
    private function create_post_callback($params = []) {
        $post_data = [
            'post_title' => $params['title'] ?? __('New Post', 'ai-command-palette'),
            'post_content' => $params['content'] ?? '',
            'post_status' => 'draft',
            'post_type' => 'post'
        ];

        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            return [
                'success' => true,
                'message' => __('Post created successfully', 'ai-command-palette'),
                'redirect' => get_edit_post_link($post_id, 'raw')
            ];
        }

        return [
            'success' => false,
            'message' => $post_id->get_error_message()
        ];
    }

    private function create_page_callback($params = []) {
        $post_data = [
            'post_title' => $params['title'] ?? __('New Page', 'ai-command-palette'),
            'post_content' => $params['content'] ?? '',
            'post_status' => 'draft',
            'post_type' => 'page'
        ];

        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            return [
                'success' => true,
                'message' => __('Page created successfully', 'ai-command-palette'),
                'redirect' => get_edit_post_link($post_id, 'raw')
            ];
        }

        return [
            'success' => false,
            'message' => $post_id->get_error_message()
        ];
    }
}