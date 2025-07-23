<?php
namespace AICP\Core;

class Context_Engine {
    private $user_id;
    private $user_role;
    private $current_page;
    private $usage_patterns = [];
    private $suggestions_cache = [];

    public function __construct() {
        $this->user_id = get_current_user_id();
        $this->user_role = $this->get_user_role();
        $this->current_page = $this->get_current_page();

        // Only load usage patterns if we have a valid user
        if ($this->user_id > 0) {
            $this->load_usage_patterns();
        }
    }

    /**
     * Get contextual suggestions based on user behavior and current context
     */
    public function get_contextual_suggestions($context = [], $limit = 5) {
        $suggestions = [];

        // Get role-based suggestions
        $role_suggestions = $this->get_role_based_suggestions();
        $suggestions = array_merge($suggestions, $role_suggestions);

        // Get time-based suggestions
        $time_suggestions = $this->get_time_based_suggestions();
        $suggestions = array_merge($suggestions, $time_suggestions);

        // Get usage-based suggestions
        $usage_suggestions = $this->get_usage_based_suggestions();
        $suggestions = array_merge($suggestions, $usage_suggestions);

        // Get page-specific suggestions
        $page_suggestions = $this->get_page_specific_suggestions();
        $suggestions = array_merge($suggestions, $page_suggestions);

        // Score and rank suggestions
        $ranked_suggestions = $this->rank_suggestions($suggestions);

        $result = [];
        $sliced = array_slice($ranked_suggestions, 0, $limit);
        foreach ($sliced as $command => $data) {
            $result[] = array_merge(['command' => $command], $data);
        }
        return $result;
    }

    /**
     * Get suggestions based on user role
     */
    private function get_role_based_suggestions() {
        $suggestions = [];

        switch ($this->user_role) {
            case 'administrator':
                $suggestions = [
                    'create_post' => ['score' => 8, 'reason' => 'Administrators frequently create content'],
                    'manage_plugins' => ['score' => 9, 'reason' => 'Plugin management is an admin task'],
                    'view_site_health' => ['score' => 7, 'reason' => 'Site health monitoring'],
                    'manage_users' => ['score' => 6, 'reason' => 'User management'],
                    'backup_site' => ['score' => 5, 'reason' => 'Site backup and maintenance']
                ];
                break;

            case 'editor':
                $suggestions = [
                    'create_post' => ['score' => 9, 'reason' => 'Editors primarily create and manage content'],
                    'edit_pages' => ['score' => 8, 'reason' => 'Page editing is common for editors'],
                    'manage_media' => ['score' => 7, 'reason' => 'Media library management'],
                    'moderate_comments' => ['score' => 6, 'reason' => 'Comment moderation'],
                    'view_analytics' => ['score' => 5, 'reason' => 'Content performance tracking']
                ];
                break;

            case 'author':
                $suggestions = [
                    'create_post' => ['score' => 9, 'reason' => 'Authors focus on content creation'],
                    'edit_own_posts' => ['score' => 8, 'reason' => 'Managing own content'],
                    'upload_media' => ['score' => 7, 'reason' => 'Media uploads for posts'],
                    'view_own_analytics' => ['score' => 5, 'reason' => 'Personal content performance']
                ];
                break;

            case 'contributor':
                $suggestions = [
                    'create_draft' => ['score' => 9, 'reason' => 'Contributors create draft content'],
                    'view_own_posts' => ['score' => 7, 'reason' => 'Review own submissions']
                ];
                break;

            case 'subscriber':
                $suggestions = [
                    'view_profile' => ['score' => 8, 'reason' => 'Profile management'],
                    'view_site_content' => ['score' => 6, 'reason' => 'Content browsing']
                ];
                break;
        }

        return $suggestions;
    }

    /**
     * Get suggestions based on time of day
     */
    private function get_time_based_suggestions() {
        $hour = (int) current_time('H');
        $suggestions = [];

        // Morning suggestions (6-12)
        if ($hour >= 6 && $hour < 12) {
            $suggestions = [
                'view_analytics' => ['score' => 7, 'reason' => 'Morning analytics review'],
                'create_post' => ['score' => 8, 'reason' => 'Morning content creation'],
                'check_notifications' => ['score' => 6, 'reason' => 'Daily notifications check']
            ];
        }
        // Afternoon suggestions (12-18)
        elseif ($hour >= 12 && $hour < 18) {
            $suggestions = [
                'moderate_comments' => ['score' => 7, 'reason' => 'Afternoon comment moderation'],
                'edit_content' => ['score' => 8, 'reason' => 'Content editing and refinement'],
                'manage_media' => ['score' => 6, 'reason' => 'Media organization']
            ];
        }
        // Evening suggestions (18-24)
        elseif ($hour >= 18 && $hour < 24) {
            $suggestions = [
                'schedule_posts' => ['score' => 8, 'reason' => 'Evening post scheduling'],
                'backup_site' => ['score' => 6, 'reason' => 'Evening maintenance tasks'],
                'review_analytics' => ['score' => 7, 'reason' => 'End-of-day review']
            ];
        }
        // Night suggestions (0-6)
        else {
            $suggestions = [
                'view_site_health' => ['score' => 6, 'reason' => 'Overnight monitoring'],
                'check_security' => ['score' => 5, 'reason' => 'Security monitoring']
            ];
        }

        return $suggestions;
    }

    /**
     * Get suggestions based on usage patterns
     */
    private function get_usage_based_suggestions() {
        $suggestions = [];

        if (empty($this->usage_patterns)) {
            return $suggestions;
        }

        // Get most frequently used commands
        $frequent_commands = array_slice($this->usage_patterns, 0, 3);

        foreach ($frequent_commands as $command => $usage) {
            $suggestions[$command] = [
                'score' => 8,
                'reason' => 'Frequently used command'
            ];
        }

        // Get recently used commands (last 24 hours)
        $recent_commands = $this->get_recent_commands();
        foreach ($recent_commands as $command) {
            if (!isset($suggestions[$command])) {
                $suggestions[$command] = [
                    'score' => 6,
                    'reason' => 'Recently used command'
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Get suggestions based on current page context
     */
    private function get_page_specific_suggestions() {
        $suggestions = [];

        // Admin page specific suggestions
        if (is_admin()) {
            $screen = get_current_screen();
            if ($screen) {
                switch ($screen->id) {
                    case 'edit-post':
                        $suggestions = [
                            'create_post' => ['score' => 9, 'reason' => 'You\'re on the posts page'],
                            'view_drafts' => ['score' => 7, 'reason' => 'Manage draft posts'],
                            'bulk_edit_posts' => ['score' => 6, 'reason' => 'Bulk operations']
                        ];
                        break;

                    case 'edit-page':
                        $suggestions = [
                            'create_page' => ['score' => 9, 'reason' => 'You\'re on the pages page'],
                            'edit_page' => ['score' => 7, 'reason' => 'Edit existing pages'],
                            'manage_page_templates' => ['score' => 6, 'reason' => 'Page template management']
                        ];
                        break;

                    case 'upload':
                        $suggestions = [
                            'upload_media' => ['score' => 9, 'reason' => 'You\'re in the media library'],
                            'organize_media' => ['score' => 7, 'reason' => 'Media organization'],
                            'bulk_edit_media' => ['score' => 6, 'reason' => 'Bulk media operations']
                        ];
                        break;

                    case 'edit-comments':
                        $suggestions = [
                            'moderate_comments' => ['score' => 9, 'reason' => 'You\'re in comments'],
                            'approve_comments' => ['score' => 7, 'reason' => 'Comment approval'],
                            'spam_management' => ['score' => 6, 'reason' => 'Spam handling']
                        ];
                        break;

                    case 'plugins':
                        $suggestions = [
                            'manage_plugins' => ['score' => 9, 'reason' => 'You\'re in plugins'],
                            'activate_plugin' => ['score' => 7, 'reason' => 'Plugin activation'],
                            'update_plugins' => ['score' => 6, 'reason' => 'Plugin updates']
                        ];
                        break;
                }
            }
        }
        // Frontend specific suggestions
        else {
            if (is_single() || is_page()) {
                $suggestions = [
                    'edit_this_post' => ['score' => 9, 'reason' => 'Edit this content'],
                    'view_analytics' => ['score' => 7, 'reason' => 'View content analytics'],
                    'share_content' => ['score' => 6, 'reason' => 'Share this content']
                ];
            } elseif (is_home() || is_front_page()) {
                $suggestions = [
                    'view_analytics' => ['score' => 8, 'reason' => 'Homepage analytics'],
                    'edit_homepage' => ['score' => 7, 'reason' => 'Edit homepage'],
                    'manage_slider' => ['score' => 6, 'reason' => 'Manage homepage slider']
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Rank and sort suggestions by score
     */
    private function rank_suggestions($suggestions) {
        // Remove duplicates and merge scores
        $ranked = [];
        foreach ($suggestions as $command => $data) {
            if (!isset($ranked[$command])) {
                $ranked[$command] = $data;
            } else {
                // If command appears multiple times, boost the score
                $ranked[$command]['score'] = min(10, $ranked[$command]['score'] + 1);
                $ranked[$command]['reason'] = 'Multiple contextual factors';
            }
        }

        // Sort by score (highest first)
        uasort($ranked, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $ranked;
    }

    /**
     * Track command usage for learning
     */
        public function track_command_usage($command_id, $success = true) {
        global $wpdb;

        // Check if the table exists to prevent errors
        $table_name = $wpdb->prefix . 'aicp_command_usage';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            // Table doesn't exist yet, just update local patterns
            $this->update_usage_patterns($command_id);
            return;
        }

        $data = [
            'user_id' => $this->user_id,
            'command_id' => $command_id,
            'command_text' => $command_id, // Use command_id as command_text for now
            'success' => $success,
            'context' => json_encode([
                'page' => $this->current_page,
                'role' => $this->user_role,
                'time' => current_time('mysql'),
                'hour' => (int) current_time('H')
            ]),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($wpdb->prefix . 'aicp_command_usage', $data);

        // Update local usage patterns
        $this->update_usage_patterns($command_id);
    }

    /**
     * Update local usage patterns
     */
    private function update_usage_patterns($command_id) {
        if (!isset($this->usage_patterns[$command_id])) {
            $this->usage_patterns[$command_id] = 0;
        }
        $this->usage_patterns[$command_id]++;

        // Keep only top 20 most used commands
        arsort($this->usage_patterns);
        $this->usage_patterns = array_slice($this->usage_patterns, 0, 20, true);
    }

    /**
     * Load usage patterns from database
     */
    private function load_usage_patterns() {
        global $wpdb;

        // Check if the table exists to prevent errors
        $table_name = $wpdb->prefix . 'aicp_command_usage';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            return; // Table doesn't exist yet, skip loading patterns
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT command_id, COUNT(*) as usage_count
             FROM {$wpdb->prefix}aicp_command_usage
             WHERE user_id = %d
             GROUP BY command_id
             ORDER BY usage_count DESC
             LIMIT 20",
            $this->user_id
        ));

        if ($results) {
            foreach ($results as $result) {
                $this->usage_patterns[$result->command_id] = (int) $result->usage_count;
            }
        }
    }

    /**
     * Get recently used commands (last 24 hours)
     */
    private function get_recent_commands() {
        global $wpdb;

        // Check if the table exists to prevent errors
        $table_name = $wpdb->prefix . 'aicp_command_usage';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            return []; // Table doesn't exist yet, return empty array
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT command_id
             FROM {$wpdb->prefix}aicp_command_usage
             WHERE user_id = %d
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY created_at DESC
             LIMIT 5",
            $this->user_id
        ));

        return $results ? array_column($results, 'command_id') : [];
    }

    /**
     * Get user role
     */
    private function get_user_role() {
        $user = wp_get_current_user();
        return $user->roles[0] ?? 'subscriber';
    }

    /**
     * Get current page context
     */
    private function get_current_page() {
        if (is_admin()) {
            // Check if get_current_screen function exists and is available
            if (function_exists('get_current_screen')) {
                $screen = get_current_screen();
                return $screen ? $screen->id : 'admin';
            } else {
                return 'admin';
            }
        } else {
            if (is_home()) return 'home';
            if (is_front_page()) return 'front_page';
            if (is_single()) return 'single';
            if (is_page()) return 'page';
            if (is_archive()) return 'archive';
            return 'frontend';
        }
    }

    /**
     * Get user context data for AI
     */
    public function get_context_data() {
        return [
            'user_id' => $this->user_id,
            'role' => $this->user_role,
            'current_page' => $this->current_page,
            'frequent_commands' => array_keys(array_slice($this->usage_patterns, 0, 5)),
            'time_of_day' => (int) current_time('H'),
            'day_of_week' => (int) current_time('w')
        ];
    }

    /**
     * Get usage statistics for the current user
     */
    public function get_usage_stats() {
        global $wpdb;

        // Check if the table exists to prevent errors
        $table_name = $wpdb->prefix . 'aicp_command_usage';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            return [
                'total_commands' => 0,
                'success_rate' => 0,
                'most_used_commands' => []
            ];
        }

        // Get total commands
        $total_commands = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aicp_command_usage WHERE user_id = %d",
            $this->user_id
        ));

        // Get successful commands
        $successful_commands = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aicp_command_usage WHERE user_id = %d AND success = 1",
            $this->user_id
        ));

        // Calculate success rate
        $success_rate = $total_commands > 0 ? round(($successful_commands / $total_commands) * 100, 1) : 0;

        // Get most used commands
        $most_used_commands = [];
        $command_results = $wpdb->get_results($wpdb->prepare(
            "SELECT command_id, COUNT(*) as count
             FROM {$wpdb->prefix}aicp_command_usage
             WHERE user_id = %d
             GROUP BY command_id
             ORDER BY count DESC
             LIMIT 10",
            $this->user_id
        ));

        if ($command_results) {
            foreach ($command_results as $result) {
                $most_used_commands[$result->command_id] = (int) $result->count;
            }
        }

        return [
            'total_commands' => (int) $total_commands,
            'success_rate' => $success_rate,
            'most_used_commands' => $most_used_commands
        ];
    }
}