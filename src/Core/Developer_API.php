<?php
namespace AICP\Core;

class Developer_API {
    private static $instance = null;
    private $registered_commands = [];
    private $ai_extensions = [];
    private $custom_handlers = [];
    private $result_renderers = [];

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize hooks for developer integration
        add_action('init', [$this, 'init_developer_hooks']);
    }

    /**
     * Initialize developer hooks
     */
    public function init_developer_hooks() {
        // Allow plugins to register commands
        do_action('aicp_register_commands', $this);

        // Allow plugins to extend AI capabilities
        do_action('aicp_extend_ai', $this);

        // Allow plugins to register custom handlers
        do_action('aicp_register_handlers', $this);

        // Allow plugins to register result renderers
        do_action('aicp_register_renderers', $this);
    }

    /**
     * Register a custom command
     *
     * @param string $id Unique command identifier
     * @param array $command Command configuration
     * @return bool Success status
     */
    public function register_command($id, $command) {
        if (empty($id) || !is_array($command)) {
            return false;
        }

        // Validate required fields
        $required_fields = ['title', 'description'];
        foreach ($required_fields as $field) {
            if (empty($command[$field])) {
                error_log("AICP: Missing required field '{$field}' for command '{$id}'");
                return false;
            }
        }

        // Set defaults
        $command = array_merge([
            'category' => 'custom',
            'icon' => 'admin-generic',
            'capability' => 'read',
            'priority' => 10,
            'keywords' => [],
            'aliases' => [],
            'context' => 'all',
            'action' => [
                'type' => 'callback',
                'callback' => null
            ]
        ], $command);

        // Validate action type
        $valid_action_types = ['callback', 'navigate', 'ai_execute', 'dynamic_api'];
        if (!in_array($command['action']['type'], $valid_action_types)) {
            error_log("AICP: Invalid action type '{$command['action']['type']}' for command '{$id}'");
            return false;
        }

        // Validate callback if required
        if ($command['action']['type'] === 'callback' && !is_callable($command['action']['callback'])) {
            error_log("AICP: Invalid callback for command '{$id}'");
            return false;
        }

        $this->registered_commands[$id] = $command;

        // Trigger action for other plugins
        do_action('aicp_command_registered', $id, $command);

        return true;
    }

    /**
     * Unregister a custom command
     *
     * @param string $id Command identifier
     * @return bool Success status
     */
    public function unregister_command($id) {
        if (isset($this->registered_commands[$id])) {
            unset($this->registered_commands[$id]);
            do_action('aicp_command_unregistered', $id);
            return true;
        }
        return false;
    }

    /**
     * Get all registered custom commands
     *
     * @return array Registered commands
     */
    public function get_registered_commands() {
        return $this->registered_commands;
    }

    /**
     * Get a specific registered command
     *
     * @param string $id Command identifier
     * @return array|null Command configuration
     */
    public function get_registered_command($id) {
        return $this->registered_commands[$id] ?? null;
    }

    /**
     * Extend AI capabilities with custom functions
     *
     * @param string $name Function name
     * @param array $function Function definition
     * @return bool Success status
     */
    public function extend_ai_function($name, $function) {
        if (empty($name) || !is_array($function)) {
            return false;
        }

        // Validate required fields
        $required_fields = ['description', 'parameters', 'handler'];
        foreach ($required_fields as $field) {
            if (empty($function[$field])) {
                error_log("AICP: Missing required field '{$field}' for AI function '{$name}'");
                return false;
            }
        }

        // Validate handler is callable
        if (!is_callable($function['handler'])) {
            error_log("AICP: Invalid handler for AI function '{$name}'");
            return false;
        }

        $this->ai_extensions[$name] = $function;

        // Trigger action for other plugins
        do_action('aicp_ai_function_registered', $name, $function);

        return true;
    }

    /**
     * Get all AI extensions
     *
     * @return array AI extensions
     */
    public function get_ai_extensions() {
        return $this->ai_extensions;
    }

    /**
     * Register a custom command handler
     *
     * @param string $type Handler type
     * @param callable $handler Handler function
     * @return bool Success status
     */
    public function register_handler($type, $handler) {
        if (empty($type) || !is_callable($handler)) {
            return false;
        }

        $this->custom_handlers[$type] = $handler;

        // Trigger action for other plugins
        do_action('aicp_handler_registered', $type, $handler);

        return true;
    }

    /**
     * Get custom handlers
     *
     * @return array Custom handlers
     */
    public function get_handlers() {
        return $this->custom_handlers;
    }

    /**
     * Register a custom result renderer
     *
     * @param string $type Renderer type
     * @param callable $renderer Renderer function
     * @return bool Success status
     */
    public function register_renderer($type, $renderer) {
        if (empty($type) || !is_callable($renderer)) {
            return false;
        }

        $this->result_renderers[$type] = $renderer;

        // Trigger action for other plugins
        do_action('aicp_renderer_registered', $type, $renderer);

        return true;
    }

    /**
     * Get result renderers
     *
     * @return array Result renderers
     */
    public function get_renderers() {
        return $this->result_renderers;
    }

    /**
     * Execute a custom command
     *
     * @param string $id Command identifier
     * @param array $params Command parameters
     * @return array Execution result
     */
    public function execute_custom_command($id, $params = []) {
        $command = $this->get_registered_command($id);

        if (!$command) {
            return [
                'success' => false,
                'message' => "Command '{$id}' not found"
            ];
        }

        // Check capability
        if (!current_user_can($command['capability'])) {
            return [
                'success' => false,
                'message' => 'Insufficient permissions'
            ];
        }

        try {
            switch ($command['action']['type']) {
                case 'callback':
                    $result = call_user_func($command['action']['callback'], $params);
                    return [
                        'success' => true,
                        'data' => $result
                    ];

                case 'navigate':
                    return [
                        'success' => true,
                        'redirect' => $command['action']['url']
                    ];

                                case 'ai_execute':
                    // Handle AI execution
                    $ai_service = new \AICP\AI\AI_Service();
                    $result = $ai_service->interpret($command['action']['plan'], $params);
                    return $result;

                case 'dynamic_api':
                    // Handle dynamic API execution
                    $execution_engine = new \AICP\Core\Execution_Engine();
                    $result = $execution_engine->execute($command['action']['endpoint'], $params);
                    return $result;

                default:
                    return [
                        'success' => false,
                        'message' => 'Unsupported action type'
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a custom AI function
     *
     * @param string $name Function name
     * @param array $params Function parameters
     * @return array Execution result
     */
    public function execute_ai_function($name, $params = []) {
        $function = $this->ai_extensions[$name] ?? null;

        if (!$function) {
            return [
                'success' => false,
                'message' => "AI function '{$name}' not found"
            ];
        }

        try {
            $result = call_user_func($function['handler'], $params);
            return [
                'success' => true,
                'data' => $result
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Render a custom result
     *
     * @param string $type Renderer type
     * @param mixed $data Data to render
     * @return string Rendered output
     */
    public function render_result($type, $data) {
        $renderer = $this->result_renderers[$type] ?? null;

        if (!$renderer) {
            return "No renderer found for type '{$type}'";
        }

        try {
            return call_user_func($renderer, $data);
        } catch (\Exception $e) {
            return "Error rendering result: " . $e->getMessage();
        }
    }

    /**
     * Get developer API documentation
     *
     * @return array API documentation
     */
    public function get_api_documentation() {
        return [
            'version' => '1.0.0',
            'methods' => [
                'register_command' => [
                    'description' => 'Register a custom command',
                    'parameters' => [
                        'id' => 'string - Unique command identifier',
                        'command' => 'array - Command configuration'
                    ],
                    'returns' => 'bool - Success status'
                ],
                'unregister_command' => [
                    'description' => 'Unregister a custom command',
                    'parameters' => [
                        'id' => 'string - Command identifier'
                    ],
                    'returns' => 'bool - Success status'
                ],
                'extend_ai_function' => [
                    'description' => 'Extend AI capabilities with custom functions',
                    'parameters' => [
                        'name' => 'string - Function name',
                        'function' => 'array - Function definition'
                    ],
                    'returns' => 'bool - Success status'
                ],
                'register_handler' => [
                    'description' => 'Register a custom command handler',
                    'parameters' => [
                        'type' => 'string - Handler type',
                        'handler' => 'callable - Handler function'
                    ],
                    'returns' => 'bool - Success status'
                ],
                'register_renderer' => [
                    'description' => 'Register a custom result renderer',
                    'parameters' => [
                        'type' => 'string - Renderer type',
                        'renderer' => 'callable - Renderer function'
                    ],
                    'returns' => 'bool - Success status'
                ]
            ],
            'hooks' => [
                'aicp_register_commands' => 'Fired when registering commands',
                'aicp_extend_ai' => 'Fired when extending AI capabilities',
                'aicp_register_handlers' => 'Fired when registering handlers',
                'aicp_register_renderers' => 'Fired when registering renderers',
                'aicp_command_registered' => 'Fired when a command is registered',
                'aicp_command_unregistered' => 'Fired when a command is unregistered',
                'aicp_ai_function_registered' => 'Fired when an AI function is registered',
                'aicp_handler_registered' => 'Fired when a handler is registered',
                'aicp_renderer_registered' => 'Fired when a renderer is registered'
            ],
            'examples' => [
                'register_command' => [
                    'code' => "
// Register a custom command
add_action('aicp_register_commands', function(\$api) {
    \$api->register_command('my_custom_command', [
        'title' => 'My Custom Command',
        'description' => 'Execute my custom functionality',
        'category' => 'custom',
        'icon' => 'admin-generic',
        'capability' => 'manage_options',
        'action' => [
            'type' => 'callback',
            'callback' => function(\$params) {
                // Your custom logic here
                return ['success' => true, 'message' => 'Command executed'];
            }
        ]
    ]);
});"
                ],
                'extend_ai_function' => [
                    'code' => "
// Extend AI with custom function
add_action('aicp_extend_ai', function(\$api) {
    \$api->extend_ai_function('my_custom_function', [
        'description' => 'Perform custom operation',
        'parameters' => [
            'param1' => ['type' => 'string', 'description' => 'First parameter'],
            'param2' => ['type' => 'number', 'description' => 'Second parameter']
        ],
        'handler' => function(\$params) {
            // Your custom logic here
            return 'Custom result';
        }
    ]);
});"
                ]
            ]
        ];
    }

    /**
     * Get integration examples for popular plugins
     *
     * @return array Integration examples
     */
    public function get_integration_examples() {
        return [
            'woocommerce' => [
                'title' => 'WooCommerce Integration',
                'description' => 'Add WooCommerce-specific commands',
                'code' => "
// WooCommerce integration example
add_action('aicp_register_commands', function(\$api) {
    if (class_exists('WooCommerce')) {
        // Add order management commands
        \$api->register_command('wc_view_orders', [
            'title' => 'View Recent Orders',
            'description' => 'View the latest WooCommerce orders',
            'category' => 'ecommerce',
            'icon' => 'cart',
            'capability' => 'manage_woocommerce',
            'action' => [
                'type' => 'callback',
                'callback' => function(\$params) {
                    \$orders = wc_get_orders([
                        'limit' => 10,
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ]);

                    return [
                        'success' => true,
                        'data' => array_map(function(\$order) {
                            return [
                                'id' => \$order->get_id(),
                                'status' => \$order->get_status(),
                                'total' => \$order->get_total(),
                                'date' => \$order->get_date_created()->format('Y-m-d H:i:s')
                            ];
                        }, \$orders)
                    ];
                }
            ]
        ]);
    }
});"
            ],
            'gravity_forms' => [
                'title' => 'Gravity Forms Integration',
                'description' => 'Add form management commands',
                'code' => "
// Gravity Forms integration example
add_action('aicp_register_commands', function(\$api) {
    if (class_exists('GFAPI')) {
        \$api->register_command('gf_view_entries', [
            'title' => 'View Form Entries',
            'description' => 'View recent form entries',
            'category' => 'forms',
            'icon' => 'forms',
            'capability' => 'gravityforms_view_entries',
            'action' => [
                'type' => 'callback',
                'callback' => function(\$params) {
                    \$form_id = \$params['form_id'] ?? 1;
                    \$entries = GFAPI::get_entries(\$form_id, [
                        'status' => 'active'
                    ], [
                        'key' => 'date_created',
                        'direction' => 'DESC'
                    ], [
                        'offset' => 0,
                        'page_size' => 10
                    ]);

                    return [
                        'success' => true,
                        'data' => \$entries
                    ];
                }
            ]
        ]);
    }
});"
            ],
            'yoast_seo' => [
                'title' => 'Yoast SEO Integration',
                'description' => 'Add SEO management commands',
                'code' => "
// Yoast SEO integration example
add_action('aicp_register_commands', function(\$api) {
    if (class_exists('WPSEO_Options')) {
        \$api->register_command('yoast_seo_analysis', [
            'title' => 'SEO Analysis',
            'description' => 'Run SEO analysis on current page',
            'category' => 'seo',
            'icon' => 'chart-line',
            'capability' => 'edit_posts',
            'action' => [
                'type' => 'callback',
                'callback' => function(\$params) {
                    \$post_id = get_the_ID();
                    if (!\$post_id) {
                        return ['success' => false, 'message' => 'No post found'];
                    }

                    \$seo_score = get_post_meta(\$post_id, '_yoast_wpseo_linkdex', true);
                    \$readability_score = get_post_meta(\$post_id, '_yoast_wpseo_content_score', true);

                    return [
                        'success' => true,
                        'data' => [
                            'seo_score' => \$seo_score,
                            'readability_score' => \$readability_score,
                            'recommendations' => [
                                'Check keyword density',
                                'Improve meta description',
                                'Add internal links'
                            ]
                        ]
                    ];
                }
            ]
        ]);
    }
});"
            ]
        ];
    }

    /**
     * Validate command configuration
     *
     * @param array $command Command configuration
     * @return array Validation result
     */
    public function validate_command($command) {
        $errors = [];
        $warnings = [];

        // Check required fields
        $required_fields = ['title', 'description'];
        foreach ($required_fields as $field) {
            if (empty($command[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Check action configuration
        if (empty($command['action']) || !is_array($command['action'])) {
            $errors[] = 'Missing or invalid action configuration';
        } else {
            $action = $command['action'];

            if (empty($action['type'])) {
                $errors[] = 'Missing action type';
            } else {
                $valid_types = ['callback', 'navigate', 'ai_execute', 'dynamic_api'];
                if (!in_array($action['type'], $valid_types)) {
                    $errors[] = "Invalid action type: {$action['type']}";
                }
            }

            // Validate callback
            if ($action['type'] === 'callback' && !is_callable($action['callback'])) {
                $errors[] = 'Invalid callback function';
            }

            // Validate navigation URL
            if ($action['type'] === 'navigate' && empty($action['url'])) {
                $errors[] = 'Missing navigation URL';
            }
        }

        // Check capability
        if (!empty($command['capability']) && !is_string($command['capability'])) {
            $warnings[] = 'Capability should be a string';
        }

        // Check priority
        if (isset($command['priority']) && (!is_numeric($command['priority']) || $command['priority'] < 0)) {
            $warnings[] = 'Priority should be a positive number';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
}