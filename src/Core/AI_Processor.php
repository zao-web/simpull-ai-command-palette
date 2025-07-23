<?php

namespace AICP\Core;

/**
 * AI Processor for handling requests from the unified AI abstraction layer
 */
class AI_Processor {

    private $ai_service;
    private $context_engine;

    public function __construct() {
        $this->ai_service = new \AICP\AI\AI_Service();
        $this->context_engine = new Context_Engine();
    }

    /**
     * Process AI requests from the frontend abstraction layer
     */
    public function process_request($request_data) {
        $type = $request_data['type'] ?? '';
        $query = $request_data['query'] ?? '';
        $context = $request_data['context'] ?? [];
        $text = $request_data['text'] ?? '';
        $options = $request_data['options'] ?? [];

        try {
            switch ($type) {
                case 'intent_classification':
                    return $this->classify_intent($query, $context);
                case 'workflow_plan':
                    return $this->interpret_workflow($query, $context);
                case 'suggestions':
                    return $this->generate_suggestions($context);
                case 'embedding':
                    return $this->generate_embedding($text);
                case 'text_generation':
                    return $this->generate_text($query, $options);
                default:
                    return [
                        'success' => false,
                        'error' => 'Unknown request type: ' . $type
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

        /**
     * Classify the intent of a user query
     */
    private function classify_intent($query, $context = []) {
        if (empty($query)) {
            return [
                'success' => false,
                'error' => 'Query is required for intent classification'
            ];
        }

        try {
            // Use the AI service to classify intent
            $prompt = "Classify the following WordPress command into ONE of these categories: content, settings, plugin, user, media, analytics, system, ecommerce.\n\nCommand: \"{$query}\"\n\nInstructions:\n- Return ONLY the single most relevant category from the list above.\n- Do not return more than one category.\n- Respond with just the category name, nothing else. When you're not that certain, return what seems most likely.";

            // Use the interpret method with a simple context and user context
            $options = array_merge(['simple_classification' => true], is_array($context) ? $context : []);
            $response = $this->ai_service->interpret($prompt, $options);

            error_log('AI intent response: ' . print_r($response, true));

            if ($response['success']) {
                // Extract intent from the response
                $intent = 'system'; // Default
                if (isset($response['ai_plan']['steps'][0]['function'])) {
                    $function = $response['ai_plan']['steps'][0]['function'];
                    // Map function names to intents
                    if (strpos($function, 'post') !== false || strpos($function, 'content') !== false) {
                        $intent = 'content';
                    } elseif (strpos($function, 'plugin') !== false) {
                        $intent = 'plugin';
                    } elseif (strpos($function, 'user') !== false) {
                        $intent = 'user';
                    } elseif (strpos($function, 'media') !== false) {
                        $intent = 'media';
                    } elseif (strpos($function, 'setting') !== false) {
                        $intent = 'settings';
                    } elseif (strpos($function, 'order') !== false || strpos($function, 'product') !== false) {
                        $intent = 'ecommerce';
                    } elseif (strpos($function, 'analytics') !== false) {
                        $intent = 'analytics';
                    }
                } elseif (!empty($response['message'])) {
                    $msg_intents = array_map('trim', explode(',', strtolower($response['message'])));
                    error_log('Message intents: ' . print_r($msg_intents, true));
                    $valid_intents = ['content', 'settings', 'plugin', 'user', 'media', 'analytics', 'system', 'ecommerce'];
                    $selected_intent = 'system';
                    foreach ($msg_intents as $msg_intent) {
                        if (in_array($msg_intent, $valid_intents, true) && $msg_intent !== 'system') {
                            $selected_intent = $msg_intent;
                            error_log('Intent matched (non-system): ' . $selected_intent);
                            break;
                        }
                    }
                    $intent = $selected_intent;
                }
                error_log('Mapped intent: ' . $intent);
                return [
                    'success' => true,
                    'data' => $intent
                ];
            } else {
                // Fallback to rule-based classification
                $fallback = $this->fallback_intent_classification($query);
                error_log('Fallback intent: ' . $fallback);
                return [
                    'success' => true,
                    'data' => $fallback
                ];
            }

        } catch (\Exception $e) {
            // Fallback to rule-based classification
            $fallback = $this->fallback_intent_classification($query);
            error_log('Exception fallback intent: ' . $fallback);
            return [
                'success' => true,
                'data' => $fallback
            ];
        }
    }

    /**
     * Generate contextual suggestions
     */
    private function generate_suggestions($context) {
        try {
            $suggestions = $this->context_engine->get_contextual_suggestions($context);

            // Convert to simple string array
            $simple_suggestions = array_map(function($suggestion) {
                return $suggestion['title'] ?? $suggestion;
            }, $suggestions);

            return [
                'success' => true,
                'data' => array_slice($simple_suggestions, 0, 3)
            ];

        } catch (\Exception $e) {
            // Fallback to basic suggestions
            return [
                'success' => true,
                'data' => $this->fallback_suggestions($context)
            ];
        }
    }

        /**
     * Generate embeddings for text
     */
    private function generate_embedding($text) {
        if (empty($text)) {
            return [
                'success' => false,
                'error' => 'Text is required for embedding generation'
            ];
        }

        // Use fallback embedding since AI service doesn't have embedding support
        return [
            'success' => true,
            'data' => $this->fallback_embedding($text)
        ];
    }

        /**
     * Generate text using AI
     */
    private function generate_text($query, $options) {
        if (empty($query)) {
            return [
                'success' => false,
                'error' => 'Query is required for text generation'
            ];
        }

        try {
            // Use the interpret method and extract text from response
            $response = $this->ai_service->interpret($query, $options);

            if ($response['success']) {
                // Extract text from the AI response
                $text = $this->fallback_text_generation($query, $options); // Default fallback

                if (isset($response['message'])) {
                    $text = $response['message'];
                } elseif (isset($response['ai_plan']['steps'][0]['description'])) {
                    $text = $response['ai_plan']['steps'][0]['description'];
                }

                return [
                    'success' => true,
                    'data' => $text
                ];
            } else {
                return [
                    'success' => true,
                    'data' => $this->fallback_text_generation($query, $options)
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => true,
                'data' => $this->fallback_text_generation($query, $options)
            ];
        }
    }

    /**
     * Fallback intent classification using keyword matching
     */
    private function fallback_intent_classification($query) {
        $keywords = strtolower($query);

        if (strpos($keywords, 'post') !== false || strpos($keywords, 'article') !== false || strpos($keywords, 'blog') !== false) {
            return 'content';
        }
        if (strpos($keywords, 'plugin') !== false || strpos($keywords, 'theme') !== false) {
            return 'plugin';
        }
        if (strpos($keywords, 'user') !== false || strpos($keywords, 'admin') !== false) {
            return 'user';
        }
        if (strpos($keywords, 'media') !== false || strpos($keywords, 'image') !== false || strpos($keywords, 'file') !== false) {
            return 'media';
        }
        if (strpos($keywords, 'setting') !== false || strpos($keywords, 'config') !== false) {
            return 'settings';
        }
        if (strpos($keywords, 'order') !== false || strpos($keywords, 'product') !== false || strpos($keywords, 'woocommerce') !== false) {
            return 'ecommerce';
        }
        if (strpos($keywords, 'analytics') !== false || strpos($keywords, 'report') !== false || strpos($keywords, 'stats') !== false) {
            return 'analytics';
        }

        return 'system';
    }

    /**
     * Fallback suggestions based on context
     */
    private function fallback_suggestions($context) {
        $role = $context['role'] ?? 'administrator';
        $page = $context['page'] ?? 'dashboard';

        $suggestions = [
            'administrator' => [
                'Create a new post',
                'Manage plugins',
                'View site analytics',
                'Edit site settings'
            ],
            'editor' => [
                'Create a new post',
                'Edit existing posts',
                'Manage media',
                'View comments'
            ],
            'author' => [
                'Create a new post',
                'Edit my posts',
                'Upload media',
                'View my profile'
            ]
        ];

        return $suggestions[$role] ?? $suggestions['administrator'];
    }

    /**
     * Fallback embedding generation using simple hashing
     */
    private function fallback_embedding($text) {
        // Simple hash-based embedding for fallback
        $hash = 0;
        for ($i = 0; $i < strlen($text); $i++) {
            $hash = (($hash << 5) - $hash) + ord($text[$i]);
            $hash = $hash & $hash; // Convert to 32-bit integer
        }

        // Generate a simple 10-dimensional embedding
        $embedding = array_fill(0, 10, 0);
        for ($i = 0; $i < 10; $i++) {
            $embedding[$i] = sin($hash + $i) * 0.5;
        }

        return $embedding;
    }

    /**
     * Fallback text generation using templates
     */
    private function fallback_text_generation($query, $options) {
        $query_lower = strtolower($query);

        if (strpos($query_lower, 'hello') !== false || strpos($query_lower, 'hi') !== false) {
            return 'Hello! How can I help you with WordPress today?';
        }
        if (strpos($query_lower, 'help') !== false) {
            return 'I can help you with WordPress commands. Try asking me to create a post, manage plugins, or view analytics.';
        }

        return 'I understand you\'re asking about WordPress. Please try a more specific command.';
    }

    /**
     * Stage 1: Select candidate functions using a lightweight AI call
     */
    private function select_candidate_functions($query, $context, $count = 5) {
        $all_functions_desc = $this->get_available_functions($context, 'descriptions_only');
        $history = $context['conversation_history'] ?? [];
        $history_prompt = '';
        if (!empty($history)) {
            $history_prompt .= "\n\n--- Conversation History ---\n";
            foreach ($history as $entry) {
                $history_prompt .= "User: " . $entry['query'] . "\n";
                $history_prompt .= "AI Plan: " . json_encode($entry['plan']) . "\n";
            }
            $history_prompt .= "--- End History ---";
        }

        $prompt = "You are a WordPress expert routing assistant. Based on the user's request, and the conversation history, identify the most relevant functions to accomplish the task from the list below.
{$history_prompt}
User Request: \"{$query}\"

Available Functions:
" . json_encode($all_functions_desc, JSON_PRETTY_PRINT) . "

Instructions:
- Return a JSON array of the names of the top {$count} most relevant function(s).
- ONLY return the function names, nothing else.
- When there are multple functions that seem similar, favor core WordPress endpoints over third party plugins. These can be indicated by 'wp_v2' in the function name.
- Example response: [\"wp_v2_posts\", \"wp_v2_pages\"]";

        $custom_prompt = ['system' => $prompt, 'user' => $query];
        $response = $this->ai_service->interpret($query, $context, [], $custom_prompt);

        if ($response['success'] && !empty($response['message'])) {
            // The AI's response might be a JSON string, possibly with markdown
            $json_string = preg_replace('/^```json\s*|\s*```$/', '', $response['message']);
            $decoded = json_decode($json_string, true);
            if (is_array($decoded)) {
                return array_slice($decoded, 0, $count);
            }
        }
        return [];
    }

    /**
     * Stage 2: Generate a detailed workflow plan from a list of candidate functions
     */
    private function generate_workflow_from_candidates($query, $context, $candidate_functions) {
        $full_schemas = [];
        foreach ($candidate_functions as $function_name) {
            $schema = $this->get_available_functions($context, 'full', $function_name);
            if (!empty($schema)) {
                $full_schemas[] = $schema[0];
            }
        }

        if (empty($full_schemas)) {
            return ['success' => false, 'message' => 'No valid schemas found for candidate functions.'];
        }

        $history = $context['conversation_history'] ?? [];
        $history_prompt = '';
        if (!empty($history)) {
            $history_prompt .= "\n\n--- Conversation History ---\n";
            foreach ($history as $entry) {
                $history_prompt .= "User: " . $entry['query'] . "\n";
                $history_prompt .= "AI Plan: " . json_encode($entry['plan']) . "\n";
            }
            $history_prompt .= "--- End History ---";
        }

        $prompt = "You are a WordPress workflow generator. Create a step-by-step plan to fulfill the user's request using ONLY the provided functions, taking into account the conversation history.
{$history_prompt}
User Request: \"{$query}\"

Available Functions with full schemas:
" . json_encode($full_schemas, JSON_PRETTY_PRINT) . "

Instructions:
- Create a 'steps' array with one or more function calls.
- For each step, specify the 'function' name and 'arguments' object.
- Extract any necessary arguments from the user's request (e.g., titles, content, IDs, statuses).
- If an argument is not present in the user request, DO NOT include it in the arguments object unless it has a clear default.
- Provide a concise 'summary' of the plan.
- Return ONLY a valid JSON object representing the workflow plan.";

        $custom_prompt = ['system' => $prompt, 'user' => $query];
        $response = $this->ai_service->interpret($query, $context, $full_schemas, $custom_prompt);

        if ($response['success'] && !empty($response['message'])) {
            // The AI's response might be a JSON string, possibly with markdown
            $json_string = preg_replace('/^```json\s*|\s*```$/', '', $response['message']);
            $decoded = json_decode($json_string, true);
            if (is_array($decoded) && isset($decoded['steps'])) {
                // If it's a single step, embed the full schema for the frontend
                if (count($decoded['steps']) === 1) {
                    $function_name = $decoded['steps'][0]['function'];
                    $schema = $this->get_available_functions($context, 'full', $function_name);
                    if (!empty($schema)) {
                        $decoded['full_function_schema'] = $schema[0];
                    }
                }
                // --- Ensure each step has a method ---
                foreach ($decoded['steps'] as $i => &$step) {
                    $function_name = $step['function'] ?? null;
                    $method = null;
                    // Find the schema for this function
                    foreach ($full_schemas as $schema) {
                        if ($schema['name'] === $function_name) {
                            if (!empty($schema['methods']) && is_array($schema['methods'])) {
                                // Prefer POST if available, else first method
                                if (in_array('POST', $schema['methods'])) {
                                    $method = 'POST';
                                } else {
                                    $method = strtoupper(reset($schema['methods']));
                                }
                            }
                            break;
                        }
                    }
                    // If AI already provided a method, use it, else set from schema
                    if (empty($step['method']) && $method) {
                        $step['method'] = $method;
                    }
                    error_log('[AICP] Workflow step: ' . print_r([
                        'function' => $function_name,
                        'arguments' => $step['arguments'] ?? [],
                        'method' => $step['method'] ?? null
                    ], true));
                }
                unset($step);
                return ['success' => true, 'data' => $decoded];
            }
        }
        return $response; // Fallback to original response if parsing fails
    }

    /**
     * New method for workflow planning with two-stage AI processing
     */
    public function interpret_workflow($query, $context = []) {
        try {
            // Stage 1: Select candidate functions
            $candidate_functions = $this->select_candidate_functions($query, $context);

            if (empty($candidate_functions)) {
                return [
                    'success' => false,
                    'message' => 'Could not determine relevant functions for the query.',
                ];
            }

            // Stage 2: Generate workflow with full schemas of candidate functions
            return $this->generate_workflow_from_candidates($query, $context, $candidate_functions);

        } catch (\Exception $e) {
            error_log('AICP Two-Stage Workflow Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during workflow generation.',
                'error_details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Public accessor for getting a single function schema.
     *
     * @param string $function_name The name of the function.
     * @return array|null The schema or null if not found.
     */
    public function get_function_schema($function_name) {
        $schema = $this->get_available_functions([], 'full', $function_name);
        return !empty($schema) ? $schema[0] : null;
    }

    /**
     * Check if a route should be skipped
     */
    private function should_skip_route($route) {
        $skip_routes = [
            '/batch/v1',
            '/oembed',
            '/ai-command-palette',
            '/autosaves',
            '/global-styles',
            'sidebars',
            'widgets',
            'font-collections',
            'block-renderer',
        ];

        foreach ($skip_routes as $skip_route) {
            if (strpos($route, $skip_route) !== false) {
                return true;
            }

            if ( '/' === $route ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available functions dynamically from discovered REST API endpoints and command registry
     * @param array $context
     * @param string $mode 'succinct' (default), 'full', or 'descriptions_only'
     * @param string|null $only_function_name If set, only return the full schema for this function name
     * @return array
     */
    private function get_available_functions($context = [], $mode = 'succinct', $only_function_name = null) {
        $functions = [];

        // Get discovered REST API endpoints
        $api_discovery = new \AICP\Core\API_Discovery();
        $discovered_endpoints = $api_discovery->get_endpoints();

        // Convert REST API endpoints to AI functions
        foreach ($discovered_endpoints as $route => $endpoint_data) {
            if ($this->should_skip_route($route)) {
                continue;
            }
            $function_name = $this->route_to_function_name($route);

            if ($only_function_name && $function_name !== $only_function_name) {
                continue;
            }

            $methods = $endpoint_data['methods'] ?? [];
            $description = $this->generate_endpoint_description($route, $methods, $endpoint_data);
            $category = $endpoint_data['category'] ?? 'api';

            // --- Descriptions only mode: for candidate selection ---
            if ($mode === 'descriptions_only') {
                $functions[] = [
                    'name' => $function_name,
                    'description' => $description,
                ];
                continue;
            }

            // --- Succinct mode: only name, description, and up to 3 key params ---
            if ($mode === 'succinct') {
                $succinct_params = [];
                if (isset($endpoint_data['args']) && is_array($endpoint_data['args'])) {
                    $count = 0;
                    foreach ($endpoint_data['args'] as $param_name => $param_info) {
                        if ($count >= 3) break;
                        $succinct_params[$param_name] = [
                            'type' => $param_info['type'] ?? 'string',
                            'description' => $param_info['description'] ?? ''
                        ];
                        $count++;
                    }
                }
                $functions[] = [
                    'name' => $function_name,
                    'description' => $description,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $succinct_params
                    ],
                    'route' => $route,
                    'methods' => $methods,
                    'category' => $category
                ];
                continue;
            }

            // --- Full mode: all parameters ---
            $properties = [];
            $required = [];
            if (isset($endpoint_data['endpoints']) && is_array($endpoint_data['endpoints'])) {
                $all_args = [];
                foreach ($endpoint_data['endpoints'] as $endpoint_details) {
                    if (isset($endpoint_details['args'])) {
                        $all_args = array_merge($all_args, $endpoint_details['args']);
                    }
                }

                foreach ($all_args as $param_name => $param_info) {
                    if (!is_array($param_info)) { continue; }

                    $param_type = $param_info['type'] ?? 'string';
                    $property = ['description' => $param_info['description'] ?? ''];

                    // Handle complex types that are arrays of types, e.g., ['string', 'null'] or ['object', 'array']
                    if (is_array($param_type)) {
                        // If 'array' is one of the possible types, we need to simplify it for the AI.
                        if (in_array('array', $param_type)) {
                            $property['type'] = 'array';
                            // The WP REST API often puts the detailed array definition in a 'oneOf' clause.
                            if (isset($param_info['oneOf']) && is_array($param_info['oneOf'])) {
                                foreach ($param_info['oneOf'] as $schema_option) {
                                    if (isset($schema_option['type']) && $schema_option['type'] === 'array' && isset($schema_option['items'])) {
                                        $property['items'] = $schema_option['items'];
                                        break;
                                    }
                                }
                            }
                            if (!isset($property['items']) && isset($param_info['items'])) {
                                $property['items'] = $param_info['items'];
                            }
                            // If we still don't have an 'items' definition, we must provide a default to avoid an API error.
                            if (!isset($property['items'])) {
                                $property['items'] = ['type' => 'string'];
                            }
                        } else {
                            // For other union types (e.g., ['string', 'null']), pick the first non-null type.
                            $non_null_types = array_filter($param_type, function($t) { return $t !== 'null'; });
                            $property['type'] = !empty($non_null_types) ? reset($non_null_types) : 'string';
                        }
                    } else {
                        $property['type'] = $param_type;
                        if ($property['type'] === 'array') {
                            if (isset($param_info['items'])) {
                                $property['items'] = $param_info['items'];
                            } else {
                                // Add a default 'items' if missing, to prevent schema validation errors.
                                $property['items'] = ['type' => 'string'];
                            }
                        }
                    }

                    $is_required = $param_info['required'] ?? false;

                    // Copy over other schema properties if they exist
                    if (isset($param_info['enum'])) $property['enum'] = $param_info['enum'];
                    if (isset($param_info['default'])) $property['default'] = $param_info['default'];
                    if (isset($param_info['format'])) $property['format'] = $param_info['format'];

                    $properties[$param_name] = $property;
                    if ($is_required) {
                        $required[] = $param_name;
                    }
                }
            }
            $parameters = [
                'type' => 'object',
                'properties' => (object)$properties,
                'required' => $required
            ];
            $functions[] = [
                'name' => $function_name,
                'description' => $description,
                'parameters' => $parameters,
                'route' => $route,
                'methods' => $methods,
                'category' => $category
            ];
        }

        // Also include commands from the command registry
        $command_registry = Command_Registry::get_instance();
        $commands = $command_registry->get_all_commands();

        foreach ($commands as $command) {
            continue; // Skipping registries for now, as they are duplicates of the REST API endpoints and we have 128 function limits on OpenAI.
            // Skip commands the user doesn't have permission for
            if (!empty($command['capability']) && !current_user_can($command['capability'])) {
                continue;
            }

            // Convert command to function format for AI
            $function = [
                'name' => $command['id'],
                'description' => $command['description'] ?: $command['title'],
                'parameters' => [],
                'category' => $command['category'] ?? 'command'
            ];

            // Add parameters based on command type
            if (isset($command['action']) && $command['action']['type'] === 'dynamic_api' && !empty($command['action']['parameters'])) {
                // Dynamic API commands
                $properties = [];
                $required = [];
                foreach ($command['action']['parameters'] as $param) {
                    $properties[$param['name']] = [
                        'type' => $param['type'] ?? 'string',
                        'description' => $param['description'] ?? ''
                    ];
                    if (!empty($param['required'])) {
                        $required[] = $param['name'];
                    }
                }
                $function['parameters'] = [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required
                ];
            } elseif (isset($command['callback'])) {
                // Registry commands with callbacks - add parameters based on command ID
                if ($command['id'] === 'create_post') {
                    $function['parameters'] = [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'Post title'
                            ],
                            'content' => [
                                'type' => 'string',
                                'description' => 'Post content'
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['draft', 'publish', 'private'],
                                'description' => 'Post status (draft, publish, private)'
                            ],
                            'postType' => [
                                'type' => 'string',
                                'enum' => ['post', 'page'],
                                'description' => 'Post type (post, page)'
                            ]
                        ],
                        'required' => ['title']
                    ];
                } elseif ($command['id'] === 'create_page') {
                    $function['parameters'] = [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'Page title'
                            ],
                            'content' => [
                                'type' => 'string',
                                'description' => 'Page content'
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['draft', 'publish', 'private'],
                                'description' => 'Page status (draft, publish, private)'
                            ]
                        ],
                        'required' => ['title']
                    ];
                } else {
                    // For all other callback commands with no parameters
                    $function['parameters'] = [
                        'type' => 'object',
                        'properties' => []
                    ];
                }
            } else {
                // For commands with no parameters at all
                $function['parameters'] = [
                    'type' => 'object',
                    'properties' => []
                ];
            }

            $functions[] = $function;
        }

        // Debug log the final functions array
        error_log('AICP get_available_functions FINAL: ' . print_r($functions, true));

        // Different AI Models have different function limits, so we need to limit the number of functions we pass to the AI.
        if ($mode !== 'descriptions_only') {
            $functions = array_slice($functions, 0, 128);
        }
        return $functions;
    }

    /**
     * Convert REST API route to function name
     */
    private function route_to_function_name($route) {
        // Remove /wp-json prefix
        $route = preg_replace('/^\/wp-json\//', '', $route);

        // Convert route to function name
        $function_name = str_replace(['/', '-', '.'], '_', $route);
        $function_name = preg_replace('/[^a-zA-Z0-9_]/', '', $function_name);

        // Ensure it starts with a letter
        if (!preg_match('/^[a-zA-Z]/', $function_name)) {
            $function_name = 'api_' . $function_name;
        }

        return $function_name;
    }

    /**
     * Generate description for REST API endpoint
     */
    private function generate_endpoint_description($route, $methods, $endpoint_data) {
        $description = '';

        // Add method information
        if (!empty($methods)) {
            $method_list = implode(', ', array_keys($methods));
            $description .= "Handles {$method_list} requests";
        }

        // Add route information
        $description .= " for the {$route} endpoint.";

        // Try to guess action from methods
        $action_verbs = [];
        if (isset($methods['POST'])) { $action_verbs[] = 'create'; }
        if (isset($methods['GET'])) { $action_verbs[] = 'retrieve'; }
        if (isset($methods['PUT']) || isset($methods['PATCH'])) { $action_verbs[] = 'update'; }
        if (isset($methods['DELETE'])) { $action_verbs[] = 'delete'; }

        if (!empty($action_verbs)) {
            $description .= " Use it to " . implode(', ', $action_verbs) . " resources.";
        }

        return $description;
    }
}