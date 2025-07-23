<?php
namespace AICP\Core;

class API_Discovery {
    private $discovered_endpoints = [];
    private $plugin_capabilities = [];

    public function __construct() {
        // Hook into WordPress to scan endpoints after plugins are loaded
        add_action('rest_api_init', [$this, 'scan_rest_endpoints'], 99);
        add_action('plugins_loaded', [$this, 'scan_plugin_capabilities'], 99);
    }

    /**
     * Scan all registered REST API endpoints and fetch their schemas using the REST API index (internal call)
     */
    public function scan_rest_endpoints() {
        $server = rest_get_server();
        $request = new \WP_REST_Request( 'GET', '/' );
        $response = $server->dispatch( $request );
        if (is_wp_error($response)) {
            /** @var \WP_Error $response */
            $error_message = $response->get_error_message();
            error_log('[AICP] API_Discovery: Failed to get REST API index: ' . $error_message);
            $this->discovered_endpoints = [];
            $this->cache_endpoints();
            return;
        }
        $data = $response->get_data();
        if (!is_array($data) || !isset($data['routes'])) {
            error_log('[AICP] API_Discovery: Invalid REST API index response.');
            $this->discovered_endpoints = [];
            $this->cache_endpoints();
            return;
        }
        $this->discovered_endpoints = $data['routes'];
        $this->cache_endpoints();
    }

    /**
     * Process a single REST route and return endpoint info
     */
    private function process_route($route, $handler) {
        // Skip internal WordPress routes we don't want to expose
        if (strpos($route, '/wp/v2/block-renderer') !== false) {
            return [];
        }

        $endpoint_info = [
            'route' => $route,
            'methods' => $handler['methods'] ?? [],
            'callback' => $this->get_callback_info($handler),
            'permission_callback' => $this->get_permission_info($handler),
            'args' => $handler['args'] ?? [],
            'description' => $this->extract_description($handler),
        ];

        // Categorize the endpoint
        $endpoint_info['category'] = $this->categorize_endpoint($route);

        return $endpoint_info;
    }

    /**
     * Extract callback information
     */
    private function get_callback_info($handler) {
        if (isset($handler['callback'])) {
            if (is_array($handler['callback'])) {
                return [
                    'class' => is_object($handler['callback'][0])
                        ? get_class($handler['callback'][0])
                        : $handler['callback'][0],
                    'method' => $handler['callback'][1]
                ];
            }
            return 'function';
        }
        return null;
    }

    /**
     * Extract permission callback information
     */
    private function get_permission_info($handler) {
        if (isset($handler['permission_callback'])) {
            if (is_callable($handler['permission_callback'])) {
                return 'custom';
            }
        }
        return 'public';
    }

    /**
     * Try to extract description from handler
     */
    private function extract_description($handler) {
        // This could be enhanced to read from schema or documentation
        return $handler['description'] ?? '';
    }

    /**
     * Categorize endpoint based on route pattern
     */
    private function categorize_endpoint($route) {
        if (strpos($route, '/wp/v2/posts') !== false) {
            return 'content';
        } elseif (strpos($route, '/wp/v2/pages') !== false) {
            return 'content';
        } elseif (strpos($route, '/wp/v2/media') !== false) {
            return 'media';
        } elseif (strpos($route, '/wp/v2/users') !== false) {
            return 'users';
        } elseif (strpos($route, '/wp/v2/comments') !== false) {
            return 'comments';
        } elseif (strpos($route, '/wp/v2/taxonomies') !== false) {
            return 'taxonomies';
        } elseif (strpos($route, '/wp/v2/settings') !== false) {
            return 'settings';
        } elseif (strpos($route, '/wc/') !== false) {
            return 'woocommerce';
        } elseif (strpos($route, '/acf/') !== false) {
            return 'acf';
        }

        return 'other';
    }

    /**
     * Fetch the REST schema for a given endpoint using the OPTIONS method
     * Returns null if not available or on error
     *
     * @param string $route
     * @return array|null
     */
    private function fetch_endpoint_schema($route) {
        // Only fetch for endpoints that look like REST endpoints
        if (strpos($route, '/wp-json/') === 0) {
            $rest_route = $route;
        } elseif (strpos($route, '/') === 0) {
            $rest_route = '/wp-json' . $route;
        } else {
            $rest_route = '/wp-json/' . $route;
        }

        // Use WordPress HTTP API to make an internal OPTIONS request
        $url = home_url($rest_route);
        $response = wp_remote_request($url, [
            'method' => 'OPTIONS',
            'timeout' => 5,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!$data || !isset($data['schema'])) {
            return null;
        }

        // Extract parameters, types, and descriptions if available
        $schema = $data['schema'];
        $parameters = [];
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $param => $info) {
                $parameters[$param] = [
                    'type' => $info['type'] ?? 'unknown',
                    'description' => $info['description'] ?? '',
                    'required' => $info['required'] ?? false,
                ];
            }
        }
        $schema['parameters'] = $parameters;
        return $schema;
    }

    /**
     * Scan for plugin capabilities
     */
    public function scan_plugin_capabilities() {
        global $wpdb;

        // Get all active plugins
        $active_plugins = get_option('active_plugins', []);

        foreach ($active_plugins as $plugin) {
            $plugin_data = $this->analyze_plugin($plugin);
            if ($plugin_data) {
                $this->plugin_capabilities[$plugin] = $plugin_data;
            }
        }

        // Special handling for known plugins
        $this->detect_woocommerce();
        $this->detect_acf();
        $this->detect_popular_plugins();

        // Cache the capabilities
        $this->cache_plugin_capabilities();
    }

    /**
     * Analyze a single plugin for capabilities
     */
    private function analyze_plugin($plugin_file) {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);

        $capabilities = [
            'name' => $plugin_data['Name'],
            'version' => $plugin_data['Version'],
            'slug' => dirname($plugin_file),
            'custom_post_types' => [],
            'custom_taxonomies' => [],
            'rest_endpoints' => [],
            'admin_pages' => [],
            'shortcodes' => []
        ];

        // Detect custom post types registered by this plugin
        $capabilities['custom_post_types'] = $this->detect_plugin_post_types($plugin_file);

        // Detect custom taxonomies
        $capabilities['custom_taxonomies'] = $this->detect_plugin_taxonomies($plugin_file);

        // Detect REST endpoints specific to this plugin
        $capabilities['rest_endpoints'] = $this->detect_plugin_rest_endpoints($plugin_file);

        return $capabilities;
    }

    /**
     * Detect custom post types from a plugin
     */
    private function detect_plugin_post_types($plugin_file) {
        $post_types = [];

        // This is simplified - in reality, we'd need to hook into
        // register_post_type calls or analyze the plugin code
        $all_post_types = get_post_types(['_builtin' => false], 'objects');

        // For now, we'll associate all non-builtin post types with plugins
        // In a real implementation, we'd trace which plugin registered each
        foreach ($all_post_types as $post_type) {
            $post_types[] = [
                'name' => $post_type->name,
                'label' => $post_type->label,
                'public' => $post_type->public,
                'show_in_rest' => $post_type->show_in_rest
            ];
        }

        return $post_types;
    }

    /**
     * Detect custom taxonomies from a plugin
     */
    private function detect_plugin_taxonomies($plugin_file) {
        $taxonomies = [];

        $all_taxonomies = get_taxonomies(['_builtin' => false], 'objects');

        foreach ($all_taxonomies as $taxonomy) {
            $taxonomies[] = [
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'public' => $taxonomy->public,
                'show_in_rest' => $taxonomy->show_in_rest
            ];
        }

        return $taxonomies;
    }

    /**
     * Detect REST endpoints from a plugin
     */
    private function detect_plugin_rest_endpoints($plugin_file) {
        $endpoints = [];

        // Filter discovered endpoints by plugin namespace
        $plugin_slug = dirname($plugin_file);

        foreach ($this->discovered_endpoints as $endpoint) {
            if (strpos($endpoint['route'], '/' . $plugin_slug . '/') !== false) {
                $endpoints[] = $endpoint;
            }
        }

        return $endpoints;
    }

    /**
     * Special detection for WooCommerce
     */
    private function detect_woocommerce() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $this->plugin_capabilities['woocommerce'] = [
            'name' => 'WooCommerce',
            'detected' => true,
            'capabilities' => [
                'products' => true,
                'orders' => true,
                'customers' => true,
                'coupons' => true,
                'reports' => true,
                'settings' => true
            ],
            'rest_namespace' => '/wc/v3/',
            'functions' => [
                'wc_get_products',
                'wc_get_orders',
                'wc_create_order',
                'wc_get_product'
            ]
        ];
    }

    /**
     * Special detection for Advanced Custom Fields
     */
    private function detect_acf() {
        if (!class_exists('ACF')) {
            return;
        }

        $this->plugin_capabilities['acf'] = [
            'name' => 'Advanced Custom Fields',
            'detected' => true,
            'capabilities' => [
                'field_groups' => true,
                'fields' => true,
                'options_pages' => function_exists('acf_add_options_page')
            ],
            'functions' => [
                'get_field',
                'update_field',
                'get_fields'
            ]
        ];
    }

    /**
     * Detect other popular plugins
     */
    private function detect_popular_plugins() {
        $popular_plugins = [
            'yoast-seo' => [
                'class' => 'WPSEO_Options',
                'name' => 'Yoast SEO',
                'capabilities' => [
                    'seo_analysis' => true,
                    'keyword_optimization' => true,
                    'sitemap_management' => true,
                    'social_media' => true
                ]
            ],
            'all-in-one-seo-pack' => [
                'class' => 'All_in_One_SEO_Pack',
                'name' => 'All in One SEO'
            ],
            'contact-form-7' => [
                'class' => 'WPCF7',
                'name' => 'Contact Form 7',
                'capabilities' => [
                    'form_management' => true,
                    'submission_viewing' => true,
                    'email_templates' => true
                ]
            ],
            'gravity-forms' => [
                'class' => 'GFForms',
                'name' => 'Gravity Forms',
                'capabilities' => [
                    'form_building' => true,
                    'entry_management' => true,
                    'payment_processing' => true,
                    'advanced_workflows' => true
                ]
            ],
            'wpforms' => [
                'class' => 'WPForms',
                'name' => 'WPForms',
                'capabilities' => [
                    'form_creation' => true,
                    'entry_viewing' => true,
                    'email_notifications' => true
                ]
            ],
            'elementor' => [
                'class' => 'Elementor\Plugin',
                'name' => 'Elementor',
                'capabilities' => [
                    'page_building' => true,
                    'template_management' => true,
                    'widget_management' => true
                ]
            ],
            'jetpack' => [
                'class' => 'Jetpack',
                'name' => 'Jetpack',
                'capabilities' => [
                    'site_stats' => true,
                    'security_features' => true,
                    'performance_optimization' => true,
                    'social_sharing' => true
                ]
            ],
            'wordfence' => [
                'class' => 'wordfence',
                'name' => 'Wordfence Security',
                'capabilities' => [
                    'security_scanning' => true,
                    'firewall_management' => true,
                    'login_security' => true
                ]
            ],
            'updraftplus' => [
                'class' => 'UpdraftPlus_Backup',
                'name' => 'UpdraftPlus',
                'capabilities' => [
                    'backup_management' => true,
                    'restore_operations' => true,
                    'cloud_storage' => true
                ]
            ],
            'redirection' => [
                'class' => 'Redirection_Api',
                'name' => 'Redirection',
                'capabilities' => [
                    'redirect_management' => true,
                    '404_monitoring' => true,
                    'seo_redirects' => true
                ]
            ],
            'wp-rocket' => [
                'class' => 'WP_Rocket',
                'name' => 'WP Rocket',
                'capabilities' => [
                    'cache_management' => true,
                    'performance_optimization' => true,
                    'cdn_integration' => true
                ]
            ],
            'smush' => [
                'class' => 'WP_Smush',
                'name' => 'Smush Image Optimization',
                'capabilities' => [
                    'image_optimization' => true,
                    'bulk_optimization' => true,
                    'webp_conversion' => true
                ]
            ]
        ];

        foreach ($popular_plugins as $slug => $info) {
            if (class_exists($info['class'])) {
                $this->plugin_capabilities[$slug] = [
                    'name' => $info['name'],
                    'detected' => true,
                    'capabilities' => $info['capabilities'] ?? []
                ];
            }
        }
    }

    /**
     * Helper to sanitize endpoint data for serialization
     */
    private function sanitize_for_serialization($data, &$seen = []) {
        // Prevent recursion on objects
        if (is_object($data)) {
            // Prevent infinite recursion
            if (in_array($data, $seen, true)) {
                return '[RECURSION]';
            }
            $seen[] = $data;

            // Skip closures
            if ($data instanceof \Closure) {
                return '[CLOSURE]';
            }

            // Convert object to array and sanitize
            $data = (array) $data;
        }

        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                // Skip closures
                if ($value instanceof \Closure) {
                    $sanitized[$key] = '[CLOSURE]';
                    continue;
                }
                // Skip unserializable objects
                if (is_object($value) && !method_exists($value, '__serialize') && !method_exists($value, '__sleep')) {
                    $sanitized[$key] = '[OBJECT: ' . get_class($value) . ']';
                    continue;
                }
                $sanitized[$key] = $this->sanitize_for_serialization($value, $seen);
            }
            return $sanitized;
        }

        // Scalar values are safe
        return $data;
    }

    /**
     * Cache discovered endpoints (now just stores the REST API index routes)
     */
    private function cache_endpoints() {
        set_transient('aicp_discovered_endpoints', $this->discovered_endpoints, DAY_IN_SECONDS);
        update_option('aicp_last_endpoint_scan', current_time('mysql'));
    }

    /**
     * Cache plugin capabilities
     */
    private function cache_plugin_capabilities() {
        global $wpdb;

        foreach ($this->plugin_capabilities as $plugin_slug => $capabilities) {
            $wpdb->replace(
                $wpdb->prefix . 'aicp_plugin_registry',
                [
                    'plugin_slug' => $plugin_slug,
                    'capabilities' => json_encode($capabilities['capabilities'] ?? []),
                    'endpoints' => json_encode($capabilities['rest_endpoints'] ?? []),
                    'commands' => json_encode($capabilities['commands'] ?? []),
                    'last_scanned' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }
    }

    /**
     * Get all discovered endpoints
     */
    public function get_endpoints() {
        if (empty($this->discovered_endpoints)) {
            $cached = get_transient('aicp_discovered_endpoints');
            if ($cached) {
                $this->discovered_endpoints = $cached;
            } else {
                $this->scan_rest_endpoints();
            }
        }
        return $this->discovered_endpoints;
    }

    /**
     * Get all discovered endpoints (for REST output)
     */
    public function get_discovered_endpoints() {
        // Optionally, load from cache if not already loaded
        return $this->discovered_endpoints;
    }

    /**
     * Get plugin capabilities
     */
    public function get_plugin_capabilities($plugin_slug = null) {
        if ($plugin_slug) {
            return $this->plugin_capabilities[$plugin_slug] ?? null;
        }

        return $this->plugin_capabilities;
    }

    /**
     * Search endpoints by keyword
     */
    public function search_endpoints($keyword) {
        $endpoints = $this->get_endpoints();
        $results = [];

        foreach ($endpoints as $endpoint) {
            if (stripos($endpoint['route'], $keyword) !== false ||
                stripos($endpoint['category'], $keyword) !== false) {
                $results[] = $endpoint;
            }
        }

        return $results;
    }
}