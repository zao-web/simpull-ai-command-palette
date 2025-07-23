<?php
namespace AICP\Core;

class Execution_Engine {
    private $start_time;

    /**
     * Execute a command
     */
    public function execute($command, $params = []) {
        $this->start_time = microtime(true);

        try {
            // Check if it's a predefined command
            $command_registry = Command_Registry::get_instance();
            $registered_command = $command_registry->get_command($command);

            if ($registered_command) {
                return $this->execute_registered_command($registered_command, $params);
            }

            // Check if it's an AI-interpreted command
            if (isset($params['ai_plan'])) {
                return $this->execute_ai_plan($params['ai_plan']);
            }

            // Try to interpret as a direct action
            return $this->execute_direct_action($command, $params);

        } catch (\Exception $e) {
            return $this->error_response($e->getMessage());
        }
    }

    /**
     * Execute a registered command
     */
    private function execute_registered_command($command, $params) {
        // Check capability
        if (!empty($command['capability']) && !current_user_can($command['capability'])) {
            return $this->error_response(__('You do not have permission to execute this command.', 'ai-command-palette'));
        }

        // Execute callback if exists
        if (!empty($command['callback']) && is_callable($command['callback'])) {
            $result = call_user_func($command['callback'], $params);

            // Track execution
            $this->track_execution($command['id'], $command['title'], isset($result['success']) ? $result['success'] : true);

            return $this->format_response($result);
        }

        // Handle navigation commands
        if (!empty($command['action']) && $command['action']['type'] === 'navigate') {
            return $this->format_response([
                'success' => true,
                'action' => 'navigate',
                'url' => $command['action']['url']
            ]);
        }

        return $this->error_response(__('Command has no executable action.', 'ai-command-palette'));
    }

    /**
     * Execute an AI-generated multi-step plan with progress, rollback, and detailed results
     */
    private function execute_ai_plan($plan) {
        $results = [];
        $overall_success = true;
        $rollback_needed = false;
        $executed_steps = [];
        $start_time = microtime(true);

        // Execute each step in the plan
        foreach ($plan['steps'] as $i => $step) {
            $step_result = [
                'step' => $i + 1,
                'function' => $step['function'],
                'arguments' => $step['arguments'],
                'status' => 'running',
                'result' => null,
                'error' => null,
                'rolled_back' => false,
                'rollback_result' => null,
            ];
            try {
                $exec_result = $this->execute_ai_step($step);
                if ($exec_result['success']) {
                    $step_result['status'] = 'success';
                    $step_result['result'] = $exec_result;
                    $executed_steps[] = $step;
                } else {
                    $step_result['status'] = 'error';
                    $step_result['error'] = $exec_result['message'] ?? 'Unknown error';
                    $overall_success = false;
                    $rollback_needed = true;
                }
            } catch (\Exception $e) {
                $step_result['status'] = 'error';
                $step_result['error'] = $e->getMessage();
                $overall_success = false;
                $rollback_needed = true;
            }
            $results[] = $step_result;
            if ($rollback_needed) {
                break; // Stop on first failure
            }
        }

        // Rollback if needed and supported
        if ($rollback_needed && !empty($executed_steps)) {
            for ($j = count($executed_steps) - 1; $j >= 0; $j--) {
                $step = $executed_steps[$j];
                $rollback_result = $this->attempt_rollback($step);
                $results[$j]['rolled_back'] = $rollback_result['attempted'];
                $results[$j]['rollback_result'] = $rollback_result['result'];
            }
        }

        $execution_time = round((microtime(true) - $start_time) * 1000);
        return $this->format_response([
            'success' => $overall_success,
            'message' => $plan['summary'] ?? __('AI command executed', 'ai-command-palette'),
            'steps' => $results,
            'execution_time' => $execution_time
        ]);
    }

    /**
     * Attempt to rollback a step if supported
     * Returns ['attempted' => bool, 'result' => string|null]
     */
    private function attempt_rollback($step) {
        // For now, only support rollback for certain known functions
        // Extend this as needed for more robust rollback support
        $rollbackable = [
            'createPost' => 'deletePost',
            'wc_create_product' => 'wc_delete_product',
            // Add more mappings as needed
        ];
        $function = $step['function'] ?? '';
        if (isset($rollbackable[$function])) {
            $rollback_func = $rollbackable[$function];
            $args = $step['arguments'] ?? [];
            // Try to extract an ID or key for deletion
            $id = $args['id'] ?? $args['postId'] ?? $args['product_id'] ?? null;
            if ($id) {
                try {
                    $result = $this->execute_ai_step([
                        'function' => $rollback_func,
                        'arguments' => ['id' => $id]
                    ]);
                    return ['attempted' => true, 'result' => $result];
                } catch (\Exception $e) {
                    return ['attempted' => true, 'result' => $e->getMessage()];
                }
            }
        }
        return ['attempted' => false, 'result' => null];
    }

    /**
     * Execute a single AI step
     */
    private function execute_ai_step($step) {
        $function = $step['function'] ?? '';
        $args = $step['arguments'] ?? [];

        // Check if this is a REST API function (from discovered endpoints)
        if (strpos($function, 'wp_v2_') === 0 || strpos($function, 'wc_') === 0 || strpos($function, 'api_') === 0) {
            return $this->execute_rest_api_function($function, $args, $step);
        }

        // Map AI functions to WordPress actions
        switch ($function) {
            // Content creation functions
            case 'createPost':
            case 'create_post':
                return $this->ai_create_post($args);

            case 'createPage':
            case 'create_page':
                return $this->ai_create_page($args);

            case 'updatePost':
            case 'update_post':
                return $this->ai_update_post($args);

            case 'getPostByTitle':
            case 'get_post_by_title':
                return $this->ai_get_post_by_title($args);

            case 'searchPosts':
            case 'search_posts':
                return $this->ai_search_posts($args);

            case 'deletePost':
            case 'delete_post':
                return $this->ai_delete_post($args);

            // Media functions
            case 'uploadMedia':
            case 'upload_media':
                return $this->ai_upload_media($args);

            // Plugin management functions
            case 'activatePlugin':
            case 'activate_plugin':
                return $this->ai_activate_plugin($args);

            case 'deactivatePlugin':
            case 'deactivate_plugin':
                return $this->ai_deactivate_plugin($args);

            // Settings functions
            case 'updateOption':
            case 'update_option':
                return $this->ai_update_option($args);

            case 'getOption':
            case 'get_option':
                return $this->ai_get_option($args);

            case 'searchAndReplace':
            case 'search_and_replace':
                return $this->ai_search_and_replace($args);

            case 'listPosts':
            case 'list_posts':
                return $this->ai_list_posts($args);

            default:
                // Check if it's a plugin-specific function
                $result = apply_filters('aicp_execute_ai_function', null, $function, $args);
                if ($result !== null) {
                    return $result;
                }

                return [
                    'success' => false,
                    'message' => sprintf(__('Unknown function: %s', 'ai-command-palette'), $function)
                ];
        }
    }

    /**
     * Execute a REST API function call
     */
    private function execute_rest_api_function($function, $args, $step = []) {
        // Convert function name back to REST API route
        $route = $this->function_name_to_route($function);

        // Clean up route - remove duplicate leading slashes
        if (strpos($route, '//') === 0) {
            $route = substr($route, 1);
        }

        if (!$route) {
            error_log('[AICP] Could not map function to REST API route: ' . $function);
            return [
                'success' => false,
                'message' => sprintf(__('Could not map function %s to REST API route', 'ai-command-palette'), $function)
            ];
        }

        // Use method from step if provided, else infer
        $method = isset($step['method']) ? strtoupper($step['method']) : $this->determine_http_method($function, $args);

        // LOGGING: Log the REST API call details
        error_log('[AICP] WP_REST_Request CALL');
        error_log('  Function: ' . $function);
        error_log('  Route: ' . $route);
        error_log('  Method: ' . $method);
        error_log('  Args: ' . print_r($args, true));

        // Use WP_REST_Request for local dispatch
        $request = new \WP_REST_Request($method, $route);
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            foreach ($args as $key => $value) {
                $request->set_param($key, $value);
            }
        } else if ($method === 'GET') {
            foreach ($args as $key => $value) {
                $request->set_query_params([$key => $value]);
            }
        }

        // Set current user (if needed)
        if (is_user_logged_in()) {
            $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        }

        $server = rest_get_server();
        $response = $server->dispatch($request);

        if (is_wp_error($response)) {
            /** @var WP_Error $response */
            $error_message = $response->get_error_message();
            error_log('  WP_Error: ' . $error_message);
            return [
                'success' => false,
                'message' => $error_message
            ];
        }

        if ($response instanceof \WP_REST_Response) {
            $status_code = $response->get_status();
            $data = $response->get_data();
        } else {
            $status_code = is_array($response) && isset($response['status']) ? $response['status'] : 500;
            $data = is_array($response) ? $response : [];
        }

        error_log('  Response Status: ' . $status_code);
        error_log('  Response Data: ' . print_r($data, true));

        if ($status_code >= 200 && $status_code < 300) {
            return [
                'success' => true,
                'message' => sprintf(__('REST API call successful: %s', 'ai-command-palette'), $route),
                'data' => $data,
                'status_code' => $status_code
            ];
        } else {
            return [
                'success' => false,
                'message' => sprintf(__('REST API call failed: %s (Status: %d)', 'ai-command-palette'), $route, $status_code),
                'data' => $data,
                'status_code' => $status_code
            ];
        }
        // TODO: For remote endpoints, use wp_remote_request as fallback
    }

    /**
     * Convert function name back to REST API route
     */
    private function function_name_to_route($function) {
        // Handle common patterns
        if (strpos($function, 'wp_v2_') === 0) {
            $route = str_replace('wp_v2_', '/wp/v2/', $function);
            $route = str_replace('_', '/', $route);
            return $route;
        }

        if (strpos($function, 'wc_') === 0) {
            $route = str_replace('wc_', '/wc/v3/', $function);
            $route = str_replace('_', '/', $route);
            return $route;
        }

        if (strpos($function, 'api_') === 0) {
            $route = str_replace('api_', '/', $function);
            $route = str_replace('_', '/', $route);
            return $route;
        }

        return null;
    }

    /**
     * Determine HTTP method based on function name and arguments
     */
    private function determine_http_method($function, $args) {
        // Default to GET for most functions
        $method = 'GET';

        // POST for creation functions
        if (strpos($function, 'create') !== false || strpos($function, 'add') !== false) {
            $method = 'POST';
        }

        // PUT for update functions
        if (strpos($function, 'update') !== false || strpos($function, 'edit') !== false) {
            $method = 'PUT';
        }

        // DELETE for deletion functions
        if (strpos($function, 'delete') !== false || strpos($function, 'remove') !== false) {
            $method = 'DELETE';
        }

        return $method;
    }

    /**
     * Execute a direct action
     */
    private function execute_direct_action($action, $params) {
        // This handles simple direct actions that don't need AI interpretation
        switch ($action) {
            case 'clear_cache':
                return $this->clear_cache();

            case 'flush_rewrite_rules':
                flush_rewrite_rules();
                return $this->success_response(__('Rewrite rules flushed', 'ai-command-palette'));

            default:
                return $this->error_response(__('Unknown action', 'ai-command-palette'));
        }
    }

    // AI Function Implementations

    private function ai_create_post($args) {
        if (!current_user_can('edit_posts')) {
            return $this->error_response(__('Permission denied', 'ai-command-palette'));
        }

        $post_data = [
            'post_title' => $args['title'] ?? __('Untitled', 'ai-command-palette'),
            'post_content' => $args['content'] ?? '',
            'post_status' => $args['status'] ?? 'draft',
            'post_type' => $args['postType'] ?? 'post'
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $this->error_response($post_id->get_error_message());
        }

        return [
            'success' => true,
            'message' => __('Post created successfully', 'ai-command-palette'),
            'data' => [
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw')
            ]
        ];
    }

    private function ai_create_page($args) {
        if (!current_user_can('edit_pages')) {
            return $this->error_response(__('Permission denied', 'ai-command-palette'));
        }

        $post_data = [
            'post_title' => $args['title'] ?? __('Untitled', 'ai-command-palette'),
            'post_content' => $args['content'] ?? '',
            'post_status' => $args['status'] ?? 'draft',
            'post_type' => 'page'
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $this->error_response($post_id->get_error_message());
        }

        return [
            'success' => true,
            'message' => __('Page created successfully', 'ai-command-palette'),
            'data' => [
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw')
            ]
        ];
    }

    private function ai_update_post($args) {
        $post_id = $args['postId'] ?? 0;

        if (!current_user_can('edit_post', $post_id)) {
            return $this->error_response(__('Permission denied', 'ai-command-palette'));
        }

        $post_data = ['ID' => $post_id];

        if (isset($args['title'])) {
            $post_data['post_title'] = $args['title'];
        }
        if (isset($args['content'])) {
            $post_data['post_content'] = $args['content'];
        }
        if (isset($args['status'])) {
            $post_data['post_status'] = $args['status'];
        }

        $result = wp_update_post($post_data);

        if (is_wp_error($result)) {
            return $this->error_response($result->get_error_message());
        }

        return $this->success_response(__('Post updated successfully', 'ai-command-palette'));
    }

    private function ai_get_post_by_title($args) {
        $title = $args['title'] ?? '';
        $post_type = $args['postType'] ?? 'any';

        $posts = get_posts([
            'title' => $title,
            'post_type' => $post_type,
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        if (empty($posts)) {
            return [
                'success' => false,
                'message' => __('Post not found', 'ai-command-palette')
            ];
        }

        $post = $posts[0];

        return [
            'success' => true,
            'data' => [
                'post_id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'status' => $post->post_status,
                'type' => $post->post_type
            ]
        ];
    }

    private function ai_search_and_replace($args) {
        $text = $args['text'] ?? '';
        $find = $args['find'] ?? '';
        $replace = $args['replace'] ?? '';

        if (empty($find)) {
            return $this->error_response(__('Find text cannot be empty', 'ai-command-palette'));
        }

        $new_text = str_replace($find, $replace, $text);

        return [
            'success' => true,
            'data' => [
                'text' => $new_text,
                'replacements' => substr_count($text, $find)
            ]
        ];
    }

    private function ai_activate_plugin($args) {
        if (!current_user_can('activate_plugins')) {
            return $this->error_response(__('Permission denied', 'ai-command-palette'));
        }

        $plugin_slug = $args['slug'] ?? '';

        // Find the plugin file
        $plugin_file = $this->find_plugin_file($plugin_slug);

        if (!$plugin_file) {
            return $this->error_response(__('Plugin not found', 'ai-command-palette'));
        }

        $result = activate_plugin($plugin_file);

        if (is_wp_error($result)) {
            return $this->error_response($result->get_error_message());
        }

        return $this->success_response(__('Plugin activated successfully', 'ai-command-palette'));
    }

    private function ai_deactivate_plugin($args) {
        if (!current_user_can('activate_plugins')) {
            return $this->error_response(__('Permission denied', 'ai-command-palette'));
        }

        $plugin_slug = $args['slug'] ?? '';

        // Find the plugin file
        $plugin_file = $this->find_plugin_file($plugin_slug);

        if (!$plugin_file) {
            return $this->error_response(__('Plugin not found', 'ai-command-palette'));
        }

        deactivate_plugins($plugin_file);

        return $this->success_response(__('Plugin deactivated successfully', 'ai-command-palette'));
    }

    private function ai_update_option($args) {
        if (!current_user_can('manage_options')) {
            return $this->error_response(__('Permission denied', 'ai-command-palette'));
        }

        $option_name = $args['name'] ?? '';
        $option_value = $args['value'] ?? '';

        // Whitelist of allowed options for safety
        $allowed_options = [
            'blogname',
            'blogdescription',
            'admin_email',
            'users_can_register',
            'default_role',
            'timezone_string',
            'date_format',
            'time_format',
            'start_of_week'
        ];

        if (!in_array($option_name, $allowed_options)) {
            return $this->error_response(__('Option not allowed for modification', 'ai-command-palette'));
        }

        update_option($option_name, $option_value);

        return $this->success_response(__('Option updated successfully', 'ai-command-palette'));
    }

    private function ai_get_option($args) {
        if (!current_user_can('manage_options')) {
            return $this->error_response(__('Permission denied', 'ai-command-palette'));
        }

        $option_name = $args['option_name'] ?? '';
        if (empty($option_name)) {
            return $this->error_response(__('Option name is required', 'ai-command-palette'));
        }

        $value = get_option($option_name);

        return [
            'success' => true,
            'message' => sprintf(__('Retrieved option: %s', 'ai-command-palette'), $option_name),
            'data' => [
                'option_name' => $option_name,
                'value' => $value
            ]
        ];
    }

    private function ai_list_posts($args) {
        $post_type = $args['postType'] ?? 'post';
        $number = $args['count'] ?? 10;
        $status = $args['status'] ?? 'any';

        $posts = get_posts([
            'post_type' => $post_type,
            'numberposts' => $number,
            'post_status' => $status
        ]);

        $post_data = [];
        foreach ($posts as $post) {
            $post_data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'date' => $post->post_date,
                'edit_url' => get_edit_post_link($post->ID, 'raw')
            ];
        }

        return [
            'success' => true,
            'data' => [
                'posts' => $post_data,
                'count' => count($post_data)
            ]
        ];
    }

    private function ai_delete_post($args) {
        $post_id = $args['postId'] ?? 0;
        $force = $args['force'] ?? false;

        if (!current_user_can('delete_post', $post_id)) {
            return $this->error_response(__('Permission denied', 'ai-command-palette'));
        }

        $result = wp_delete_post($post_id, $force);

        if (!$result) {
            return $this->error_response(__('Failed to delete post', 'ai-command-palette'));
        }

        return $this->success_response(__('Post deleted successfully', 'ai-command-palette'));
    }

    private function ai_upload_media($args) {
        if (!current_user_can('upload_files')) {
            return $this->error_response(__('Permission denied', 'ai-command-palette'));
        }

        // This would need actual file upload handling
        // For now, return a placeholder response
        return [
            'success' => false,
            'message' => __('Media upload requires file selection', 'ai-command-palette')
        ];
    }

    private function ai_search_posts($args) {
        if (!current_user_can('edit_posts')) {
            return $this->error_response(__('Permission denied', 'ai-command-palette'));
        }

        $query = $args['query'] ?? '';
        $post_type = $args['postType'] ?? 'post';
        $limit = $args['limit'] ?? 10;

        $posts = get_posts([
            's' => $query,
            'post_type' => $post_type,
            'posts_per_page' => $limit,
            'post_status' => 'any'
        ]);

        $results = array_map(function($post) {
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'status' => $post->post_status,
                'edit_url' => get_edit_post_link($post->ID, 'raw')
            ];
        }, $posts);

        return [
            'success' => true,
            'message' => sprintf(__('Found %d posts', 'ai-command-palette'), count($results)),
            'data' => $results
        ];
    }

    // Helper methods

    private function find_plugin_file($slug) {
        $plugins = get_plugins();

        foreach ($plugins as $plugin_file => $plugin_data) {
            if (strpos($plugin_file, $slug) !== false) {
                return $plugin_file;
            }
        }

        return false;
    }

    private function clear_cache() {
        // Try various cache plugins
        if (function_exists('wp_cache_clear_cache')) {
            call_user_func('wp_cache_clear_cache');
        }

        if (function_exists('w3tc_flush_all')) {
            call_user_func('w3tc_flush_all');
        }

        if (function_exists('rocket_clean_domain')) {
            call_user_func('rocket_clean_domain');
        }

        // WordPress object cache
        wp_cache_flush();

        // Allow other plugins to clear their cache
        do_action('aicp_clear_cache');

        return $this->success_response(__('Cache cleared successfully', 'ai-command-palette'));
    }

    private function track_execution($command_id, $command_text, $success) {
        $execution_time = $this->get_execution_time();

        do_action('aicp_command_executed', $command_id, $command_text, $success);

        // Log execution time if it's slow
        if ($execution_time > 1000) { // More than 1 second
            error_log(sprintf(
                'AI Command Palette: Slow command execution - %s took %dms',
                $command_id,
                $execution_time
            ));
        }
    }

    private function get_execution_time() {
        return round((microtime(true) - $this->start_time) * 1000);
    }

    private function format_response($response) {
        if (!isset($response['execution_time'])) {
            $response['execution_time'] = $this->get_execution_time();
        }

        return $response;
    }

    private function success_response($message, $data = []) {
        return $this->format_response(array_merge([
            'success' => true,
            'message' => $message
        ], $data));
    }

    private function error_response($message) {
        return $this->format_response([
            'success' => false,
            'message' => $message
        ]);
    }
}