<?php
namespace AICP\AI;

class AI_Service {
    private $api_provider;
    private $api_key;
    private $model;
    private $max_tokens = 2000;

    public function __construct() {
        $settings = get_option('aicp_settings', []);
        $this->api_provider = $settings['api_provider'] ?? 'openai';
        $this->api_key = $settings['api_key'] ?? '';
        $this->model = $settings['ai_model'] ?? 'gpt-4';
    }

    /**
     * Interpret a natural language query
     *
     * Two-stage function selection:
     * 1. Initial call: Use succinct function list for selection (default).
     * 2. After function selection: Use get_full_function_schema to fetch full schema for argument population.
     */
    public function interpret($query, $context = [], $functions = null, $custom_prompt = null) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => __('AI API key not configured. Please configure it in settings.', 'ai-command-palette'),
                'requires_setup' => true,
                'fallback_available' => true
            ];
        }
        if (strlen($query) < 5) {
            return [
                'success' => false,
                'message' => __('Query too short for AI interpretation', 'ai-command-palette'),
                'fallback_available' => true
            ];
        }
        try {
            // Use provided functions or fall back to succinct ones for initial selection
            if ($functions === null) {
                $functions = $this->get_available_functions($context, 'succinct');
                error_log('AICP AI_Service: Using fallback succinct functions');
            } else {
                error_log('AICP AI_Service: Using provided functions: ' . count($functions) . ' functions');
            }
            $context['isAdmin'] = is_admin();
            $context['currentUser'] = wp_get_current_user();
            if ($custom_prompt !== null) {
                $prompt = $custom_prompt;
            } else {
                $prompt = $this->build_prompt($query, $context);
            }
            error_log('AICP AI_Service Prompt: ' . print_r($prompt, true));
            $response = $this->call_ai_api($prompt, $functions);
            return $this->parse_ai_response($response, $query);
        } catch (\Exception $e) {
            error_log('AI Command Palette - AI Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => sprintf(__('AI temporarily unavailable: %s', 'ai-command-palette'), $e->getMessage()),
                'fallback_available' => true,
                'error_type' => 'ai_unavailable'
            ];
        }
    }

    /**
     * Fetch the full schema for a selected function by name (for stage 2)
     * @param string $function_name
     * @param array $context
     * @return array|null
     */
    public function get_full_function_schema($function_name, $context = []) {
        $full = $this->get_available_functions($context, 'full', $function_name);
        return !empty($full) ? $full[0] : null;
    }

    /**
     * Get available functions for AI to use
     */
    private function get_available_functions($context, $mode = 'succinct', $specific_function_name = null) {
        $functions = [
            [
                'name' => 'createPost',
                'description' => 'Create a new WordPress post or page',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'postType' => [
                            'type' => 'string',
                            'enum' => ['post', 'page'],
                            'description' => 'Type of content to create'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Title of the post/page'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Content of the post/page'
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['draft', 'publish', 'private'],
                            'description' => 'Publication status'
                        ]
                    ],
                    'required' => ['postType', 'title']
                ]
            ],
            [
                'name' => 'updatePost',
                'description' => 'Update an existing WordPress post or page',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'postId' => [
                            'type' => 'integer',
                            'description' => 'ID of the post to update'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'New title'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'New content'
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['draft', 'publish', 'private'],
                            'description' => 'New publication status'
                        ]
                    ],
                    'required' => ['postId']
                ]
            ],
            [
                'name' => 'getPostByTitle',
                'description' => 'Find a post or page by its title',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Title to search for'
                        ],
                        'postType' => [
                            'type' => 'string',
                            'enum' => ['post', 'page', 'any'],
                            'description' => 'Type of post to search'
                        ]
                    ],
                    'required' => ['title']
                ]
            ],
            [
                'name' => 'searchAndReplace',
                'description' => 'Find and replace text in a string',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => [
                            'type' => 'string',
                            'description' => 'Text to search in'
                        ],
                        'find' => [
                            'type' => 'string',
                            'description' => 'Text to find'
                        ],
                        'replace' => [
                            'type' => 'string',
                            'description' => 'Text to replace with'
                        ]
                    ],
                    'required' => ['text', 'find', 'replace']
                ]
            ],
            [
                'name' => 'activatePlugin',
                'description' => 'Activate a WordPress plugin',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => [
                            'type' => 'string',
                            'description' => 'Plugin slug or name'
                        ]
                    ],
                    'required' => ['slug']
                ]
            ],
            [
                'name' => 'deactivatePlugin',
                'description' => 'Deactivate a WordPress plugin',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => [
                            'type' => 'string',
                            'description' => 'Plugin slug or name'
                        ]
                    ],
                    'required' => ['slug']
                ]
            ],
            [
                'name' => 'updateOption',
                'description' => 'Update a WordPress site option',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Option name (e.g., blogname, blogdescription)'
                        ],
                        'value' => [
                            'type' => 'string',
                            'description' => 'New value for the option'
                        ]
                    ],
                    'required' => ['name', 'value']
                ]
            ],
            [
                'name' => 'listPosts',
                'description' => 'List WordPress posts or pages',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'postType' => [
                            'type' => 'string',
                            'enum' => ['post', 'page'],
                            'description' => 'Type of posts to list'
                        ],
                        'count' => [
                            'type' => 'integer',
                            'description' => 'Number of posts to return'
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['publish', 'draft', 'private', 'any'],
                            'description' => 'Post status filter'
                        ]
                    ]
                ]
            ]
        ];

        // Add plugin-specific functions
        $functions = apply_filters('aicp_ai_functions', $functions, $context);

        if ($mode === 'succinct') {
            return $functions;
        } elseif ($mode === 'full' && $specific_function_name) {
            return array_filter($functions, function($func) use ($specific_function_name) {
                return $func['name'] === $specific_function_name;
            });
        }
        return [];
    }

    /**
     * Build the AI prompt
     */
    private function build_prompt($query, $context) {
        // Safely extract user role
        $user_role = 'unknown';
        if (isset($context['currentUser'])) {
            if (is_array($context['currentUser']) && isset($context['currentUser']['role'])) {
                $user_role = $context['currentUser']['role'];
            } elseif (is_object($context['currentUser']) && isset($context['currentUser']->roles) && is_array($context['currentUser']->roles)) {
                $user_role = implode(',', $context['currentUser']->roles);
            }
        }
        $current_page = !empty($context['isAdmin']) ? 'WordPress Admin' : 'Frontend';

        $system_prompt = "You are an AI assistant for WordPress administration. You help users manage their WordPress site by interpreting their natural language commands and converting them into function calls.

Current context:
- User role: {$user_role}
- Current page: {$current_page}
- Available plugins: " . $this->get_active_plugins_list() . "

Instructions:
1. Interpret the user's request and determine what WordPress actions they want to perform
2. Use the available functions to accomplish the task
3. For multi-step tasks, break them down into individual function calls
4. If the request is unclear, ask for clarification
5. Always respect WordPress permissions and capabilities
6. Be helpful but concise in your responses";

        return [
            'system' => $system_prompt,
            'user' => $query
        ];
    }

    /**
     * Call the AI API
     */
    private function call_ai_api($prompt, $functions) {
        switch ($this->api_provider) {
            case 'openai':
                return $this->call_openai_api($prompt, $functions);

            case 'anthropic':
                return $this->call_anthropic_api($prompt, $functions);

            default:
                throw new \Exception('Unsupported AI provider');
        }
    }

    /**
     * Call OpenAI API
     */
    private function call_openai_api($prompt, $functions) {
        $url = 'https://api.openai.com/v1/chat/completions';

        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $prompt['system']],
                ['role' => 'user', 'content' => $prompt['user']]
            ],
            'temperature' => 0.7,
            'max_tokens' => $this->max_tokens
        ];

        // Only include functions parameter if it's a non-empty array
        if (!empty($functions)) {
            $body['functions'] = $functions;
            $body['function_call'] = 'auto';
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new \Exception($body['error']['message'] ?? 'Unknown API error');
        }

        return $body;
    }

    /**
     * Call Anthropic API (Claude)
     */
    private function call_anthropic_api($prompt, $functions) {
        // Anthropic doesn't support function calling directly, so we need to format differently
        $url = 'https://api.anthropic.com/v1/messages';

        // Convert functions to text description for Claude
        $functions_description = $this->format_functions_for_claude($functions);

        $body = [
            'model' => 'claude-3-opus-20240229',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt['system'] . "\n\nAvailable functions:\n" . $functions_description . "\n\nUser request: " . $prompt['user'] . "\n\nRespond with a JSON object containing the function calls to execute."
                ]
            ],
            'max_tokens' => $this->max_tokens
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new \Exception($body['error']['message'] ?? 'Unknown API error');
        }

        return $body;
    }

    /**
     * Format functions for Claude
     */
    private function format_functions_for_claude($functions) {
        $descriptions = [];

        foreach ($functions as $func) {
            $params = [];
            foreach ($func['parameters']['properties'] as $name => $prop) {
                $params[] = "- $name ({$prop['type']}): {$prop['description']}";
            }

            $descriptions[] = "Function: {$func['name']}
Description: {$func['description']}
Parameters:
" . implode("\n", $params);
        }

        return implode("\n\n", $descriptions);
    }

    /**
     * Parse AI response
     */
    private function parse_ai_response($response, $original_query) {
        if ($this->api_provider === 'openai') {
            return $this->parse_openai_response($response, $original_query);
        } else {
            return $this->parse_anthropic_response($response, $original_query);
        }
    }

    /**
     * Parse OpenAI response
     */
    private function parse_openai_response($response, $original_query) {
        $message = $response['choices'][0]['message'] ?? [];

        // Check if AI wants to call a function
        if (isset($message['function_call'])) {
            $function_name = $message['function_call']['name'];
            $arguments = json_decode($message['function_call']['arguments'], true);

            return [
                'success' => true,
                'type' => 'function_call',
                'ai_plan' => [
                    'steps' => [
                        [
                            'function' => $function_name,
                            'arguments' => $arguments
                        ]
                    ],
                    'summary' => $message['content'] ?? "Executing: $function_name"
                ]
            ];
        }

        // Check if it's a clarification request
        if (isset($message['content'])) {
            return [
                'success' => true,
                'type' => 'clarification',
                'message' => $message['content']
            ];
        }

        return [
            'success' => false,
            'message' => __('Could not interpret the command', 'ai-command-palette')
        ];
    }

    /**
     * Parse Anthropic response
     */
    private function parse_anthropic_response($response, $original_query) {
        $content = $response['content'][0]['text'] ?? '';

        // Try to extract JSON from the response
        preg_match('/\{.*\}/s', $content, $matches);

        if (!empty($matches[0])) {
            $json = json_decode($matches[0], true);

            if ($json && isset($json['function'])) {
                return [
                    'success' => true,
                    'type' => 'function_call',
                    'ai_plan' => [
                        'steps' => [
                            [
                                'function' => $json['function'],
                                'arguments' => $json['arguments'] ?? []
                            ]
                        ],
                        'summary' => $json['summary'] ?? "Executing: {$json['function']}"
                    ]
                ];
            }
        }

        // If no JSON found, treat as clarification
        return [
            'success' => true,
            'type' => 'clarification',
            'message' => $content
        ];
    }

    /**
     * Get list of active plugins
     */
    private function get_active_plugins_list() {
        $active_plugins = get_option('active_plugins', []);
        $plugin_names = [];

        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
            if (!empty($plugin_data['Name'])) {
                $plugin_names[] = $plugin_data['Name'];
            }
        }

        return implode(', ', $plugin_names);
    }

    /**
     * Test AI connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => __('API key not configured', 'ai-command-palette')
            ];
        }

        try {
            switch ($this->api_provider) {
                case 'openai':
                    return $this->test_openai_connection();
                case 'anthropic':
                    return $this->test_anthropic_connection();
                default:
                    return [
                        'success' => false,
                        'message' => __('Unsupported provider', 'ai-command-palette')
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Connection failed: %s', 'ai-command-palette'), $e->getMessage())
            ];
        }
    }

    /**
     * Test OpenAI connection with a simple API call
     */
    private function test_openai_connection() {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello']
                ],
                'max_tokens' => 10
            ]),
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'message' => __('OpenAI connection successful', 'ai-command-palette')
            ];
        } elseif ($status_code === 401) {
            throw new \Exception(__('Invalid API key', 'ai-command-palette'));
        } elseif ($status_code === 429) {
            throw new \Exception(__('Rate limit exceeded', 'ai-command-palette'));
        } else {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            throw new \Exception($error_message);
        }
    }

    /**
     * Test Anthropic connection with a simple API call
     */
    private function test_anthropic_connection() {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 10,
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello']
                ]
            ]),
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'message' => __('Anthropic connection successful', 'ai-command-palette')
            ];
        } elseif ($status_code === 401) {
            throw new \Exception(__('Invalid API key', 'ai-command-palette'));
        } elseif ($status_code === 429) {
            throw new \Exception(__('Rate limit exceeded', 'ai-command-palette'));
        } else {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            throw new \Exception($error_message);
        }
    }

    /**
     * Fetch available models from OpenAI API
     */
    private function fetch_openai_models($api_key) {
        error_log('[AICP] Starting fetch_openai_models');
        $response = wp_remote_get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 5
        ]);

        if (is_wp_error($response)) {
            error_log('[AICP] OpenAI models error: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => 'OpenAI API error: ' . $response->get_error_message(),
                'models' => []
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200 || empty($data) || !isset($data['data'])) {
            error_log('[AICP] OpenAI models invalid response. Status: ' . $status_code . ' Body: ' . substr($body, 0, 200));
            return [
                'success' => false,
                'message' => 'Invalid response from OpenAI API. Status: ' . $status_code,
                'models' => []
            ];
        }

        $models = [];
        foreach ($data['data'] as $model) {
            $id = $model['id'];
            if (strpos($id, 'gpt-') === 0 || strpos($id, 'ft:') === 0) {
                $models[$id] = $this->format_openai_model_name($id);
            }
        }
        error_log('[AICP] OpenAI models fetch success. Count: ' . count($models));
        return $models;
    }

    /**
     * Fetch available models from Anthropic API
     */
    private function fetch_anthropic_models($api_key) {
        error_log('[AICP] Starting fetch_anthropic_models');
        $response = wp_remote_get('https://api.anthropic.com/v1/models', [
            'headers' => [
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ],
            'timeout' => 5
        ]);

        if (is_wp_error($response)) {
            error_log('[AICP] Anthropic models error: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => 'Anthropic API error: ' . $response->get_error_message(),
                'models' => []
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200 || empty($data) || !isset($data['data'])) {
            error_log('[AICP] Anthropic models invalid response. Status: ' . $status_code . ' Body: ' . substr($body, 0, 200));
            return [
                'success' => false,
                'message' => 'Invalid response from Anthropic API. Status: ' . $status_code,
                'models' => []
            ];
        }

        $models = [];
        foreach ($data['data'] as $model) {
            $id = $model['id'];
            if (strpos($id, 'claude-') === 0) {
                $models[$id] = $this->format_anthropic_model_name($id);
            }
        }
        error_log('[AICP] Anthropic models fetch success. Count: ' . count($models));
        return $models;
    }

    /**
     * Format OpenAI model name for display
     */
    private function format_openai_model_name($model_id) {
        $names = [
            'gpt-4o' => 'GPT-4o (Latest)',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4-turbo-preview' => 'GPT-4 Turbo Preview',
            'gpt-4' => 'GPT-4 (Recommended)',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Fast)',
            'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K'
        ];

        return $names[$model_id] ?? $model_id;
    }

    /**
     * Format Anthropic model name for display
     */
    private function format_anthropic_model_name($model_id) {
        $names = [
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Recommended)',
            'claude-3-opus-20240229' => 'Claude 3 Opus (Most Capable)',
            'claude-3-sonnet-20240229' => 'Claude 3 Sonnet (Balanced)',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku (Fastest)',
            'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku (Latest)'
        ];

        return $names[$model_id] ?? $model_id;
    }

    /**
     * Get available models from the configured API provider
     */
    public function get_available_models($provider = null, $api_key = null) {
        error_log('[AICP] get_available_models: start');
        if ($provider === null) {
            $provider = $this->api_provider;
        }
        if ($api_key === null) {
            $api_key = $this->api_key;
        }

        if (empty($api_key)) {
            error_log('[AICP] get_available_models: missing API key');
            return [
                'success' => false,
                'message' => __('API key not configured', 'ai-command-palette'),
                'models' => []
            ];
        }

        // Check cache first
        $cache_key = 'aicp_models_' . $provider . '_' . md5($api_key);
        $cached_models = get_transient($cache_key);

        if ($cached_models !== false) {
            error_log('[AICP] get_available_models: cache hit, count=' . (is_array($cached_models) ? count($cached_models) : 0));
            return [
                'success' => true,
                'models' => $cached_models,
                'cached' => true
            ];
        }

        try {
            error_log('[AICP] get_available_models: fetching models for provider ' . $provider);
            switch ($provider) {
                case 'openai':
                    $models = $this->fetch_openai_models($api_key);
                    break;
                case 'anthropic':
                    $models = $this->fetch_anthropic_models($api_key);
                    break;
                default:
                    error_log('[AICP] get_available_models: unsupported provider ' . $provider);
                    return [
                        'success' => false,
                        'message' => __('Unsupported provider', 'ai-command-palette'),
                        'models' => []
                    ];
            }
            error_log('[AICP] get_available_models: fetch complete for provider ' . $provider);

            // If the fetch method returned an error array, return it directly
            if (isset($models['success']) && $models['success'] === false) {
                error_log('[AICP] get_available_models: fetch returned error array: ' . $models['message']);
                return $models;
            }

            $model_count = is_array($models) ? count($models) : 0;
            $model_size = is_array($models) ? strlen(serialize($models)) : 0;
            error_log('[AICP] get_available_models: model count=' . $model_count . ', serialized size=' . $model_size);

            // Cache the results for 1 hour
            set_transient($cache_key, $models, HOUR_IN_SECONDS);
            error_log('[AICP] get_available_models: set_transient complete');

            error_log('[AICP] get_available_models: returning success');
            return [
                'success' => true,
                'models' => $models,
                'cached' => false
            ];

        } catch (\Exception $e) {
            error_log('[AICP] Exception in get_available_models: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => sprintf(__('Failed to fetch models: %s', 'ai-command-palette'), $e->getMessage()),
                'models' => []
            ];
        }
    }

    /**
     * Register REST API endpoint for fetching full function schema
     */
    public static function register_rest_routes() {
        register_rest_route('ai-command-palette/v1', '/function-schema', [
            'methods' => 'GET',
            'callback' => [self::class, 'rest_get_function_schema'],
            'permission_callback' => function () { return current_user_can('edit_posts'); },
            'args' => [
                'name' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
    }

    /**
     * REST callback to get full function schema by name
     */
    public static function rest_get_function_schema($request) {
        $name = $request->get_param('name');
        $service = new self();
        $schema = $service->get_full_function_schema($name);
        if ($schema) {
            return [ 'success' => true, 'schema' => $schema ];
        }
        return [ 'success' => false, 'message' => 'Function not found' ];
    }
}