<?php
namespace AICP\Core;

class Multisite_Support {
    private $is_multisite;
    private $current_site_id;
    private $network_admin;

    public function __construct() {
        $this->is_multisite = is_multisite();
        $this->current_site_id = get_current_blog_id();
        $this->network_admin = is_super_admin();

        if ($this->is_multisite) {
            $this->init_multisite_hooks();
        }
    }

    /**
     * Initialize multisite-specific hooks
     */
    private function init_multisite_hooks() {
        // Add network admin commands
        add_action('aicp_register_commands', [$this, 'register_network_commands']);

        // Add site-specific commands
        add_action('aicp_register_commands', [$this, 'register_site_commands']);

        // Filter commands based on context
        add_filter('aicp_filter_commands', [$this, 'filter_commands_by_context']);

        // Add multisite context to AI
        add_filter('aicp_ai_context', [$this, 'add_multisite_context']);
    }

    /**
     * Register network admin commands
     */
    public function register_network_commands($api) {
        if (!$this->network_admin) {
            return;
        }

        $network_commands = [
            'network_sites' => [
                'title' => __('View All Sites', 'ai-command-palette'),
                'description' => __('List all sites in the network', 'ai-command-palette'),
                'category' => 'network',
                'icon' => 'networking',
                'capability' => 'manage_network',
                'action' => [
                    'type' => 'callback',
                    'callback' => [$this, 'list_network_sites']
                ]
            ],
            'network_add_site' => [
                'title' => __('Add New Site', 'ai-command-palette'),
                'description' => __('Create a new site in the network', 'ai-command-palette'),
                'category' => 'network',
                'icon' => 'plus',
                'capability' => 'manage_sites',
                'action' => [
                    'type' => 'callback',
                    'callback' => [$this, 'add_network_site']
                ]
            ],
            'network_plugins' => [
                'title' => __('Network Plugins', 'ai-command-palette'),
                'description' => __('Manage network-activated plugins', 'ai-command-palette'),
                'category' => 'network',
                'icon' => 'admin-plugins',
                'capability' => 'manage_network_plugins',
                'action' => [
                    'type' => 'callback',
                    'callback' => [$this, 'manage_network_plugins']
                ]
            ],
            'network_themes' => [
                'title' => __('Network Themes', 'ai-command-palette'),
                'description' => __('Manage network-enabled themes', 'ai-command-palette'),
                'category' => 'network',
                'icon' => 'admin-appearance',
                'capability' => 'manage_network_themes',
                'action' => [
                    'type' => 'callback',
                    'callback' => [$this, 'manage_network_themes']
                ]
            ],
            'network_users' => [
                'title' => __('Network Users', 'ai-command-palette'),
                'description' => __('Manage network users', 'ai-command-palette'),
                'category' => 'network',
                'icon' => 'admin-users',
                'capability' => 'manage_network_users',
                'action' => [
                    'type' => 'callback',
                    'callback' => [$this, 'manage_network_users']
                ]
            ],
            'network_settings' => [
                'title' => __('Network Settings', 'ai-command-palette'),
                'description' => __('Configure network settings', 'ai-command-palette'),
                'category' => 'network',
                'icon' => 'admin-settings',
                'capability' => 'manage_network_options',
                'action' => [
                    'type' => 'navigate',
                    'url' => network_admin_url('settings.php')
                ]
            ],
            'network_updates' => [
                'title' => __('Network Updates', 'ai-command-palette'),
                'description' => __('Update WordPress, plugins, and themes network-wide', 'ai-command-palette'),
                'category' => 'network',
                'icon' => 'update',
                'capability' => 'manage_network',
                'action' => [
                    'type' => 'callback',
                    'callback' => [$this, 'network_updates']
                ]
            ],
            'network_backup' => [
                'title' => __('Network Backup', 'ai-command-palette'),
                'description' => __('Create backup of all network sites', 'ai-command-palette'),
                'category' => 'network',
                'icon' => 'backup',
                'capability' => 'manage_network',
                'action' => [
                    'type' => 'callback',
                    'callback' => [$this, 'network_backup']
                ]
            ],
            'network_health' => [
                'title' => __('Network Health', 'ai-command-palette'),
                'description' => __('Check health of all network sites', 'ai-command-palette'),
                'category' => 'network',
                'icon' => 'heart',
                'capability' => 'manage_network',
                'action' => [
                    'type' => 'callback',
                    'callback' => [$this, 'network_health_check']
                ]
            ]
        ];

        foreach ($network_commands as $id => $command) {
            $api->register_command($id, $command);
        }
    }

    /**
     * Register site-specific commands for multisite
     */
    public function register_site_commands($api) {
        if (!$this->is_multisite) {
            return;
        }

        $site_commands = [
            'switch_site' => [
                'title' => __('Switch Site', 'ai-command-palette'),
                'description' => __('Switch to a different site in the network', 'ai-command-palette'),
                'category' => 'multisite',
                'icon' => 'admin-site',
                'capability' => 'read',
                'action' => [
                    'type' => 'callback',
                    'callback' => [$this, 'switch_site']
                ]
            ],
            'site_info' => [
                'title' => __('Site Information', 'ai-command-palette'),
                'description' => __('Show current site information', 'ai-command-palette'),
                'category' => 'multisite',
                'icon' => 'info',
                'capability' => 'read',
                'action' => [
                    'type' => 'callback',
                    'callback' => [$this, 'get_site_info']
                ]
            ],
            'site_users' => [
                'title' => __('Site Users', 'ai-command-palette'),
                'description' => __('Manage users for current site', 'ai-command-palette'),
                'category' => 'multisite',
                'icon' => 'admin-users',
                'capability' => 'list_users',
                'action' => [
                    'type' => 'navigate',
                    'url' => admin_url('users.php')
                ]
            ]
        ];

        foreach ($site_commands as $id => $command) {
            $api->register_command($id, $command);
        }
    }

    /**
     * Filter commands based on multisite context
     */
    public function filter_commands_by_context($commands) {
        if (!$this->is_multisite) {
            return $commands;
        }

        $filtered_commands = [];

        foreach ($commands as $command) {
            // Show network commands only to network admins
            if (isset($command['category']) && $command['category'] === 'network') {
                if ($this->network_admin) {
                    $filtered_commands[] = $command;
                }
                continue;
            }

            // Show site-specific commands to all users
            if (isset($command['category']) && $command['category'] === 'multisite') {
                $filtered_commands[] = $command;
                continue;
            }

            // Show regular commands
            $filtered_commands[] = $command;
        }

        return $filtered_commands;
    }

    /**
     * Add multisite context to AI
     */
    public function add_multisite_context($context) {
        if (!$this->is_multisite) {
            return $context;
        }

        $context['multisite'] = [
            'is_multisite' => true,
            'current_site_id' => $this->current_site_id,
            'current_site_name' => get_bloginfo('name'),
            'current_site_url' => get_site_url(),
            'is_network_admin' => $this->network_admin,
            'total_sites' => $this->get_total_sites_count(),
            'network_domain' => get_site_url(1),
            'network_name' => get_site_option('site_name', 'WordPress Network')
        ];

        return $context;
    }

    /**
     * List all sites in the network
     */
    public function list_network_sites($params = []) {
        if (!$this->network_admin) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'ai-command-palette')];
        }

        $sites = get_sites([
            'number' => $params['limit'] ?? 50,
            'orderby' => $params['orderby'] ?? 'registered',
            'order' => $params['order'] ?? 'DESC'
        ]);

        $sites_data = [];
        foreach ($sites as $site) {
            $sites_data[] = [
                'id' => $site->blog_id,
                'name' => get_blog_option($site->blog_id, 'blogname'),
                'url' => get_site_url($site->blog_id),
                'registered' => $site->registered,
                'last_updated' => $site->last_updated,
                'public' => $site->public,
                'archived' => $site->archived,
                'mature' => $site->mature,
                'spam' => $site->spam,
                'deleted' => $site->deleted
            ];
        }

        return [
            'success' => true,
            'data' => $sites_data,
            'total' => count($sites_data)
        ];
    }

    /**
     * Add a new site to the network
     */
    public function add_network_site($params = []) {
        if (!$this->network_admin) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'ai-command-palette')];
        }

        $domain = $params['domain'] ?? '';
        $path = $params['path'] ?? '/';
        $title = $params['title'] ?? '';
        $user_id = $params['user_id'] ?? get_current_user_id();

        if (empty($domain)) {
            return ['success' => false, 'message' => __('Domain is required', 'ai-command-palette')];
        }

        $site_id = wpmu_create_blog($domain, $path, $title, $user_id);

        if (is_wp_error($site_id)) {
            return ['success' => false, 'message' => $site_id->get_error_message()];
        }

        return [
            'success' => true,
            'message' => sprintf(__('Site created successfully with ID: %d', 'ai-command-palette'), $site_id),
            'site_id' => $site_id
        ];
    }

    /**
     * Manage network plugins
     */
    public function manage_network_plugins($params = []) {
        if (!$this->network_admin) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'ai-command-palette')];
        }

        $action = $params['action'] ?? 'list';
        $plugin = $params['plugin'] ?? '';

        switch ($action) {
            case 'activate':
                if (empty($plugin)) {
                    return ['success' => false, 'message' => __('Plugin name is required', 'ai-command-palette')];
                }
                $result = activate_plugin($plugin, '', true);
                break;

            case 'deactivate':
                if (empty($plugin)) {
                    return ['success' => false, 'message' => __('Plugin name is required', 'ai-command-palette')];
                }
                deactivate_plugins($plugin, true);
                $result = true;
                break;

            case 'list':
            default:
                $active_plugins = get_site_option('active_sitewide_plugins', []);
                $plugins_data = [];
                foreach ($active_plugins as $plugin_file => $timestamp) {
                    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
                    $plugins_data[] = [
                        'file' => $plugin_file,
                        'name' => $plugin_data['Name'],
                        'description' => $plugin_data['Description'],
                        'version' => $plugin_data['Version'],
                        'author' => $plugin_data['Author']
                    ];
                }
                return [
                    'success' => true,
                    'data' => $plugins_data,
                    'total' => count($plugins_data)
                ];
        }

        if (is_wp_error($result)) {
            return ['success' => false, 'message' => $result->get_error_message()];
        }

        return ['success' => true, 'message' => __('Plugin action completed successfully', 'ai-command-palette')];
    }

    /**
     * Manage network themes
     */
    public function manage_network_themes($params = []) {
        if (!$this->network_admin) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'ai-command-palette')];
        }

        $action = $params['action'] ?? 'list';
        $theme = $params['theme'] ?? '';

        switch ($action) {
            case 'enable':
                if (empty($theme)) {
                    return ['success' => false, 'message' => __('Theme name is required', 'ai-command-palette')];
                }
                $allowed_themes = get_site_option('allowedthemes', []);
                $allowed_themes[$theme] = true;
                update_site_option('allowedthemes', $allowed_themes);
                break;

            case 'disable':
                if (empty($theme)) {
                    return ['success' => false, 'message' => __('Theme name is required', 'ai-command-palette')];
                }
                $allowed_themes = get_site_option('allowedthemes', []);
                unset($allowed_themes[$theme]);
                update_site_option('allowedthemes', $allowed_themes);
                break;

            case 'list':
            default:
                $allowed_themes = get_site_option('allowedthemes', []);
                $themes_data = [];
                foreach ($allowed_themes as $theme_slug => $enabled) {
                    $theme_data = wp_get_theme($theme_slug);
                    $themes_data[] = [
                        'slug' => $theme_slug,
                        'name' => $theme_data->get('Name'),
                        'description' => $theme_data->get('Description'),
                        'version' => $theme_data->get('Version'),
                        'author' => $theme_data->get('Author'),
                        'enabled' => $enabled
                    ];
                }
                return [
                    'success' => true,
                    'data' => $themes_data,
                    'total' => count($themes_data)
                ];
        }

        return ['success' => true, 'message' => __('Theme action completed successfully', 'ai-command-palette')];
    }

    /**
     * Manage network users
     */
    public function manage_network_users($params = []) {
        if (!$this->network_admin) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'ai-command-palette')];
        }

        $action = $params['action'] ?? 'list';
        $user_id = $params['user_id'] ?? 0;

        switch ($action) {
            case 'add':
                $user_data = $params['user_data'] ?? [];
                if (empty($user_data['user_login']) || empty($user_data['user_email'])) {
                    return ['success' => false, 'message' => __('Username and email are required', 'ai-command-palette')];
                }
                $user_id = wpmu_create_user($user_data['user_login'], $user_data['user_pass'], $user_data['user_email']);
                if (is_wp_error($user_id)) {
                    return ['success' => false, 'message' => $user_id->get_error_message()];
                }
                break;

            case 'remove':
                if (empty($user_id)) {
                    return ['success' => false, 'message' => __('User ID is required', 'ai-command-palette')];
                }
                wpmu_delete_user($user_id);
                break;

            case 'list':
            default:
                $users = get_users([
                    'number' => $params['limit'] ?? 50,
                    'orderby' => $params['orderby'] ?? 'registered',
                    'order' => $params['order'] ?? 'DESC'
                ]);
                $users_data = [];
                foreach ($users as $user) {
                    $users_data[] = [
                        'id' => $user->ID,
                        'login' => $user->user_login,
                        'email' => $user->user_email,
                        'display_name' => $user->display_name,
                        'registered' => $user->user_registered,
                        'sites' => get_blogs_of_user($user->ID)
                    ];
                }
                return [
                    'success' => true,
                    'data' => $users_data,
                    'total' => count($users_data)
                ];
        }

        return ['success' => true, 'message' => __('User action completed successfully', 'ai-command-palette')];
    }

    /**
     * Network updates
     */
    public function network_updates($params = []) {
        if (!$this->network_admin) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'ai-command-palette')];
        }

        $type = $params['type'] ?? 'check';

        switch ($type) {
            case 'wordpress':
                // Check for WordPress core updates
                $core_updates = get_core_updates();
                return [
                    'success' => true,
                    'data' => $core_updates,
                    'message' => sprintf(__('Found %d WordPress updates', 'ai-command-palette'), count($core_updates))
                ];

            case 'plugins':
                // Check for plugin updates across network
                $plugin_updates = get_site_transient('update_plugins');
                return [
                    'success' => true,
                    'data' => $plugin_updates,
                    'message' => __('Plugin updates checked', 'ai-command-palette')
                ];

            case 'themes':
                // Check for theme updates across network
                $theme_updates = get_site_transient('update_themes');
                return [
                    'success' => true,
                    'data' => $theme_updates,
                    'message' => __('Theme updates checked', 'ai-command-palette')
                ];

            case 'check':
            default:
                // Check all updates
                $core_updates = get_core_updates();
                $plugin_updates = get_site_transient('update_plugins');
                $theme_updates = get_site_transient('update_themes');

                return [
                    'success' => true,
                    'data' => [
                        'core' => $core_updates,
                        'plugins' => $plugin_updates,
                        'themes' => $theme_updates
                    ],
                    'message' => __('All updates checked', 'ai-command-palette')
                ];
        }
    }

    /**
     * Network backup
     */
    public function network_backup($params = []) {
        if (!$this->network_admin) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'ai-command-palette')];
        }

        $sites = get_sites(['number' => 0]);
        $backup_data = [];

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $backup_data[$site->blog_id] = [
                'site_id' => $site->blog_id,
                'site_name' => get_bloginfo('name'),
                'site_url' => get_site_url(),
                'tables' => $this->get_site_tables(),
                'options' => $this->get_site_options(),
                'users' => $this->get_site_users()
            ];

            restore_current_blog();
        }

        return [
            'success' => true,
            'data' => $backup_data,
            'message' => sprintf(__('Network backup completed for %d sites', 'ai-command-palette'), count($sites))
        ];
    }

    /**
     * Network health check
     */
    public function network_health_check($params = []) {
        if (!$this->network_admin) {
            return ['success' => false, 'message' => __('Insufficient permissions', 'ai-command-palette')];
        }

        $sites = get_sites(['number' => 0]);
        $health_data = [];

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $health_data[$site->blog_id] = [
                'site_id' => $site->blog_id,
                'site_name' => get_bloginfo('name'),
                'site_url' => get_site_url(),
                'status' => $this->check_site_health(),
                'last_check' => current_time('mysql')
            ];

            restore_current_blog();
        }

        return [
            'success' => true,
            'data' => $health_data,
            'message' => sprintf(__('Health check completed for %d sites', 'ai-command-palette'), count($sites))
        ];
    }

    /**
     * Switch to a different site
     */
    public function switch_site($params = []) {
        $site_id = $params['site_id'] ?? 0;
        $site_url = $params['site_url'] ?? '';

        if (empty($site_id) && empty($site_url)) {
            return ['success' => false, 'message' => __('Site ID or URL is required', 'ai-command-palette')];
        }

        if (!empty($site_url)) {
            $site = get_sites(['domain' => parse_url($site_url, PHP_URL_HOST), 'path' => parse_url($site_url, PHP_URL_PATH)]);
            if (!empty($site)) {
                $site_id = $site[0]->blog_id;
            }
        }

        if (empty($site_id)) {
            return ['success' => false, 'message' => __('Site not found', 'ai-command-palette')];
        }

        $site_url = get_site_url($site_id);

        return [
            'success' => true,
            'redirect' => $site_url . '/wp-admin/',
            'message' => sprintf(__('Switched to site: %s', 'ai-command-palette'), get_blog_option($site_id, 'blogname'))
        ];
    }

    /**
     * Get current site information
     */
    public function get_site_info($params = []) {
        return [
            'success' => true,
            'data' => [
                'site_id' => $this->current_site_id,
                'site_name' => get_bloginfo('name'),
                'site_url' => get_site_url(),
                'admin_url' => admin_url(),
                'network_admin_url' => network_admin_url(),
                'is_network_admin' => $this->network_admin,
                'total_sites' => $this->get_total_sites_count(),
                'current_user' => wp_get_current_user()->display_name,
                'current_user_role' => $this->get_user_role_in_site()
            ]
        ];
    }

    /**
     * Get total sites count
     */
    private function get_total_sites_count() {
        return get_sites(['count' => true]);
    }

    /**
     * Get site tables
     */
    private function get_site_tables() {
        global $wpdb;
        return $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'", ARRAY_N);
    }

    /**
     * Get site options
     */
    private function get_site_options() {
        return wp_load_alloptions();
    }

    /**
     * Get site users
     */
    private function get_site_users() {
        return get_users(['fields' => 'all']);
    }

    /**
     * Check site health
     */
    private function check_site_health() {
        $health = [
            'status' => 'healthy',
            'issues' => []
        ];

        // Check if site is accessible
        $response = wp_remote_get(get_site_url());
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $health['status'] = 'unhealthy';
            $health['issues'][] = 'Site not accessible';
        }

        // Check database connection
        global $wpdb;
        if ($wpdb->last_error) {
            $health['status'] = 'unhealthy';
            $health['issues'][] = 'Database connection issues';
        }

        // Check for critical errors
        $errors = get_option('site_health_errors', []);
        if (!empty($errors)) {
            $health['status'] = 'warning';
            $health['issues'][] = 'Site health issues detected';
        }

        return $health;
    }

    /**
     * Get user role in current site
     */
    private function get_user_role_in_site() {
        $user = wp_get_current_user();
        $roles = $user->roles;
        return !empty($roles) ? $roles[0] : 'subscriber';
    }

    /**
     * Check if multisite is enabled
     */
    public function is_multisite() {
        return $this->is_multisite;
    }

    /**
     * Check if user is network admin
     */
    public function is_network_admin() {
        return $this->network_admin;
    }

    /**
     * Get current site ID
     */
    public function get_current_site_id() {
        return $this->current_site_id;
    }
}