<?php
namespace AICP\Core;

class Advanced_Analytics {
    private $audit_logger;
    private $context_engine;

    public function __construct() {
        $this->audit_logger = new Audit_Logger();
        $this->context_engine = new Context_Engine();

        // Initialize analytics hooks
        add_action('aicp_command_executed', [$this, 'track_command_analytics'], 10, 4);
        add_action('aicp_ai_used', [$this, 'track_ai_analytics'], 10, 3);
    }

    /**
     * Get comprehensive analytics dashboard data
     */
    public function get_dashboard_data($period = '30d', $user_id = null) {
        $data = [
            'overview' => $this->get_overview_metrics($period, $user_id),
            'usage_patterns' => $this->get_usage_patterns($period, $user_id),
            'performance_metrics' => $this->get_performance_metrics($period, $user_id),
            'ai_analytics' => $this->get_ai_analytics($period, $user_id),
            'user_insights' => $this->get_user_insights($period, $user_id),
            'business_intelligence' => $this->get_business_intelligence($period, $user_id),
            'trends' => $this->get_trends($period, $user_id),
            'recommendations' => $this->get_recommendations($period, $user_id)
        ];

        return $data;
    }

    /**
     * Get overview metrics
     */
    private function get_overview_metrics($period, $user_id) {
        global $wpdb;

        $user_filter = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
        $date_filter = $this->get_date_filter($period);

        $metrics = [
            'total_commands' => 0,
            'unique_users' => 0,
            'success_rate' => 0,
            'avg_response_time' => 0,
            'ai_usage_percentage' => 0,
            'most_active_hour' => 0,
            'most_active_day' => 0,
            'command_complexity_score' => 0
        ];

        // Total commands
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter",
            $date_filter
        ));
        $metrics['total_commands'] = intval($total);

        // Unique users
        $unique_users = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter",
            $date_filter
        ));
        $metrics['unique_users'] = intval($unique_users);

        // Success rate
        if ($metrics['total_commands'] > 0) {
            $successful = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aicp_audit_log
                 WHERE action_type = 'execute'
                 AND status = 'success'
                 AND created_at >= %s
                 $user_filter",
                $date_filter
            ));
            $metrics['success_rate'] = round(($successful / $metrics['total_commands']) * 100, 2);
        }

        // Average response time
        $avg_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(execution_time_ms) FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter",
            $date_filter
        ));
        $metrics['avg_response_time'] = round($avg_time, 2);

        // AI usage percentage
        if ($metrics['total_commands'] > 0) {
            $ai_commands = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aicp_audit_log
                 WHERE action_type = 'ai_request'
                 AND created_at >= %s
                 $user_filter",
                $date_filter
            ));
            $metrics['ai_usage_percentage'] = round(($ai_commands / $metrics['total_commands']) * 100, 2);
        }

        // Most active hour
        $active_hour = $wpdb->get_var($wpdb->prepare(
            "SELECT HOUR(created_at) as hour
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY HOUR(created_at)
             ORDER BY COUNT(*) DESC
             LIMIT 1",
            $date_filter
        ));
        $metrics['most_active_hour'] = intval($active_hour);

        // Most active day
        $active_day = $wpdb->get_var($wpdb->prepare(
            "SELECT DAYOFWEEK(created_at) as day
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY DAYOFWEEK(created_at)
             ORDER BY COUNT(*) DESC
             LIMIT 1",
            $date_filter
        ));
        $metrics['most_active_day'] = intval($active_day);

        // Command complexity score
        $complexity_score = $this->calculate_complexity_score($period, $user_id);
        $metrics['command_complexity_score'] = $complexity_score;

        return $metrics;
    }

    /**
     * Get usage patterns
     */
    private function get_usage_patterns($period, $user_id) {
        global $wpdb;

        $user_filter = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
        $date_filter = $this->get_date_filter($period);

        $patterns = [
            'hourly_distribution' => [],
            'daily_distribution' => [],
            'weekly_distribution' => [],
            'command_categories' => [],
            'user_roles' => [],
            'session_patterns' => []
        ];

        // Hourly distribution
        $hourly = $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY HOUR(created_at)
             ORDER BY hour",
            $date_filter
        ));
        $patterns['hourly_distribution'] = $hourly;

        // Daily distribution
        $daily = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY DATE(created_at)
             ORDER BY date",
            $date_filter
        ));
        $patterns['daily_distribution'] = $daily;

        // Weekly distribution
        $weekly = $wpdb->get_results($wpdb->prepare(
            "SELECT YEARWEEK(created_at) as week, COUNT(*) as count
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY YEARWEEK(created_at)
             ORDER BY week",
            $date_filter
        ));
        $patterns['weekly_distribution'] = $weekly;

        // Command categories
        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT
                CASE
                    WHEN command_id LIKE 'create_%' THEN 'content_creation'
                    WHEN command_id LIKE 'edit_%' THEN 'content_editing'
                    WHEN command_id LIKE 'delete_%' THEN 'content_deletion'
                    WHEN command_id LIKE 'view_%' THEN 'content_viewing'
                    WHEN command_id LIKE 'plugin_%' THEN 'plugin_management'
                    WHEN command_id LIKE 'theme_%' THEN 'theme_management'
                    WHEN command_id LIKE 'user_%' THEN 'user_management'
                    WHEN command_id LIKE 'ai_%' THEN 'ai_commands'
                    ELSE 'other'
                END as category,
                COUNT(*) as count
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY category
             ORDER BY count DESC",
            $date_filter
        ));
        $patterns['command_categories'] = $categories;

        // User roles
        $roles = $wpdb->get_results($wpdb->prepare(
            "SELECT
                JSON_EXTRACT(context, '$.user_role') as role,
                COUNT(*) as count
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY role
             ORDER BY count DESC",
            $date_filter
        ));
        $patterns['user_roles'] = $roles;

        // Session patterns
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT
                session_id,
                COUNT(*) as commands_per_session,
                AVG(execution_time_ms) as avg_session_time,
                MIN(created_at) as session_start,
                MAX(created_at) as session_end
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY session_id
             ORDER BY commands_per_session DESC
             LIMIT 20",
            $date_filter
        ));
        $patterns['session_patterns'] = $sessions;

        return $patterns;
    }

    /**
     * Get performance metrics
     */
    private function get_performance_metrics($period, $user_id) {
        global $wpdb;

        $user_filter = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
        $date_filter = $this->get_date_filter($period);

        $metrics = [
            'response_times' => [],
            'error_rates' => [],
            'command_performance' => [],
            'ai_performance' => [],
            'bottlenecks' => []
        ];

        // Response time percentiles
        $response_times = $wpdb->get_results($wpdb->prepare(
            "SELECT
                AVG(execution_time_ms) as avg_time,
                MIN(execution_time_ms) as min_time,
                MAX(execution_time_ms) as max_time,
                STDDEV(execution_time_ms) as std_dev,
                COUNT(*) as total_commands
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter",
            $date_filter
        ));
        $metrics['response_times'] = $response_times[0] ?? [];

        // Error rates by command
        $error_rates = $wpdb->get_results($wpdb->prepare(
            "SELECT
                command_id,
                COUNT(*) as total_executions,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_executions,
                (SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as error_rate
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY command_id
             HAVING total_executions > 5
             ORDER BY error_rate DESC
             LIMIT 10",
            $date_filter
        ));
        $metrics['error_rates'] = $error_rates;

        // Command performance
        $command_performance = $wpdb->get_results($wpdb->prepare(
            "SELECT
                command_id,
                COUNT(*) as total_executions,
                AVG(execution_time_ms) as avg_execution_time,
                SUM(execution_time_ms) as total_time,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_executions
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY command_id
             ORDER BY total_time DESC
             LIMIT 20",
            $date_filter
        ));
        $metrics['command_performance'] = $command_performance;

        // AI performance
        $ai_performance = $wpdb->get_results($wpdb->prepare(
            "SELECT
                command_id,
                COUNT(*) as total_requests,
                AVG(execution_time_ms) as avg_response_time,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_requests,
                (SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as success_rate
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'ai_request'
             AND created_at >= %s
             $user_filter
             GROUP BY command_id",
            $date_filter
        ));
        $metrics['ai_performance'] = $ai_performance;

        // Performance bottlenecks
        $bottlenecks = $wpdb->get_results($wpdb->prepare(
            "SELECT
                command_id,
                AVG(execution_time_ms) as avg_time,
                COUNT(*) as frequency
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND execution_time_ms > 1000
             AND created_at >= %s
             $user_filter
             GROUP BY command_id
             ORDER BY avg_time DESC
             LIMIT 10",
            $date_filter
        ));
        $metrics['bottlenecks'] = $bottlenecks;

        return $metrics;
    }

    /**
     * Get AI analytics
     */
    private function get_ai_analytics($period, $user_id) {
        global $wpdb;

        $user_filter = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
        $date_filter = $this->get_date_filter($period);

        $analytics = [
            'usage_trends' => [],
            'accuracy_metrics' => [],
            'popular_queries' => [],
            'ai_providers' => [],
            'cost_analysis' => []
        ];

        // AI usage trends
        $usage_trends = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                COUNT(*) as ai_requests,
                AVG(execution_time_ms) as avg_response_time
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'ai_request'
             AND created_at >= %s
             $user_filter
             GROUP BY DATE(created_at)
             ORDER BY date",
            $date_filter
        ));
        $analytics['usage_trends'] = $usage_trends;

        // AI accuracy metrics
        $accuracy_metrics = $wpdb->get_results($wpdb->prepare(
            "SELECT
                command_id,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_requests,
                (SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as accuracy_rate
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'ai_request'
             AND created_at >= %s
             $user_filter
             GROUP BY command_id",
            $date_filter
        ));
        $analytics['accuracy_metrics'] = $accuracy_metrics;

        // Popular AI queries
        $popular_queries = $wpdb->get_results($wpdb->prepare(
            "SELECT
                command_text,
                COUNT(*) as frequency,
                AVG(execution_time_ms) as avg_time
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'ai_request'
             AND created_at >= %s
             $user_filter
             GROUP BY command_text
             ORDER BY frequency DESC
             LIMIT 20",
            $date_filter
        ));
        $analytics['popular_queries'] = $popular_queries;

        // AI providers analysis
        $ai_providers = $wpdb->get_results($wpdb->prepare(
            "SELECT
                JSON_EXTRACT(result, '$.provider') as provider,
                COUNT(*) as requests,
                AVG(execution_time_ms) as avg_response_time,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_requests
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'ai_request'
             AND created_at >= %s
             $user_filter
             GROUP BY provider",
            $date_filter
        ));
        $analytics['ai_providers'] = $ai_providers;

        // Cost analysis (estimated)
        $cost_analysis = $this->estimate_ai_costs($period, $user_id);
        $analytics['cost_analysis'] = $cost_analysis;

        return $analytics;
    }

    /**
     * Get user insights
     */
    private function get_user_insights($period, $user_id) {
        global $wpdb;

        $user_filter = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
        $date_filter = $this->get_date_filter($period);

        $insights = [
            'user_segments' => [],
            'power_users' => [],
            'new_users' => [],
            'user_journey' => [],
            'adoption_metrics' => []
        ];

        // User segments
        $user_segments = $wpdb->get_results($wpdb->prepare(
            "SELECT
                user_id,
                COUNT(*) as total_commands,
                AVG(execution_time_ms) as avg_time,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_commands,
                MAX(created_at) as last_activity
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY user_id
             ORDER BY total_commands DESC",
            $date_filter
        ));
        $insights['user_segments'] = $user_segments;

        // Power users (top 10%)
        $power_users = array_slice($user_segments, 0, ceil(count($user_segments) * 0.1));
        $insights['power_users'] = $power_users;

        // New users (first time in period)
        $new_users = $wpdb->get_results($wpdb->prepare(
            "SELECT
                user_id,
                MIN(created_at) as first_command,
                COUNT(*) as commands_in_period
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY user_id
             HAVING MIN(created_at) >= %s
             ORDER BY first_command DESC",
            $date_filter, $date_filter
        ));
        $insights['new_users'] = $new_users;

        // User journey analysis
        $user_journey = $wpdb->get_results($wpdb->prepare(
            "SELECT
                user_id,
                command_id,
                created_at,
                ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at) as command_sequence
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             ORDER BY user_id, created_at",
            $date_filter
        ));
        $insights['user_journey'] = $user_journey;

        // Adoption metrics
        $adoption_metrics = [
            'total_users' => count($user_segments),
            'active_users' => count(array_filter($user_segments, function($user) {
                return strtotime($user->last_activity) > strtotime('-7 days');
            })),
            'new_users' => count($new_users),
            'power_users' => count($power_users),
            'avg_commands_per_user' => array_sum(array_column($user_segments, 'total_commands')) / count($user_segments)
        ];
        $insights['adoption_metrics'] = $adoption_metrics;

        return $insights;
    }

    /**
     * Get business intelligence
     */
    private function get_business_intelligence($period, $user_id) {
        $bi = [
            'productivity_gains' => $this->calculate_productivity_gains($period, $user_id),
            'time_savings' => $this->calculate_time_savings($period, $user_id),
            'efficiency_metrics' => $this->calculate_efficiency_metrics($period, $user_id),
            'roi_analysis' => $this->calculate_roi($period, $user_id),
            'competitive_advantages' => $this->get_competitive_advantages($period, $user_id)
        ];

        return $bi;
    }

    /**
     * Get trends analysis
     */
    private function get_trends($period, $user_id) {
        global $wpdb;

        $user_filter = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
        $date_filter = $this->get_date_filter($period);

        $trends = [
            'command_growth' => [],
            'ai_adoption' => [],
            'performance_trends' => [],
            'user_growth' => [],
            'seasonal_patterns' => []
        ];

        // Command growth trend
        $command_growth = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                COUNT(*) as commands,
                COUNT(DISTINCT user_id) as unique_users
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY DATE(created_at)
             ORDER BY date",
            $date_filter
        ));
        $trends['command_growth'] = $command_growth;

        // AI adoption trend
        $ai_adoption = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                COUNT(*) as ai_requests,
                COUNT(DISTINCT user_id) as ai_users
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'ai_request'
             AND created_at >= %s
             $user_filter
             GROUP BY DATE(created_at)
             ORDER BY date",
            $date_filter
        ));
        $trends['ai_adoption'] = $ai_adoption;

        // Performance trends
        $performance_trends = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                AVG(execution_time_ms) as avg_response_time,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*) * 100 as success_rate
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY DATE(created_at)
             ORDER BY date",
            $date_filter
        ));
        $trends['performance_trends'] = $performance_trends;

        // User growth trend
        $user_growth = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                COUNT(DISTINCT user_id) as new_users
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             AND user_id NOT IN (
                 SELECT DISTINCT user_id
                 FROM {$wpdb->prefix}aicp_audit_log
                 WHERE action_type = 'execute'
                 AND created_at < %s
             )
             GROUP BY DATE(created_at)
             ORDER BY date",
            $date_filter, $date_filter
        ));
        $trends['user_growth'] = $user_growth;

        // Seasonal patterns
        $seasonal_patterns = $wpdb->get_results($wpdb->prepare(
            "SELECT
                HOUR(created_at) as hour,
                DAYOFWEEK(created_at) as day_of_week,
                COUNT(*) as command_count
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY HOUR(created_at), DAYOFWEEK(created_at)
             ORDER BY day_of_week, hour",
            $date_filter
        ));
        $trends['seasonal_patterns'] = $seasonal_patterns;

        return $trends;
    }

    /**
     * Get recommendations
     */
    private function get_recommendations($period, $user_id) {
        $recommendations = [];

        // Get performance data
        $performance = $this->get_performance_metrics($period, $user_id);
        $usage_patterns = $this->get_usage_patterns($period, $user_id);

        // Performance recommendations
        if (!empty($performance['bottlenecks'])) {
            $recommendations[] = [
                'type' => 'performance',
                'title' => __('Optimize Slow Commands', 'ai-command-palette'),
                'description' => __('Some commands are taking longer than expected to execute.', 'ai-command-palette'),
                'priority' => 'high',
                'actions' => ['optimize_commands', 'cache_results']
            ];
        }

        // Usage recommendations
        if (!empty($usage_patterns['command_categories'])) {
            $content_creation = array_filter($usage_patterns['command_categories'], function($cat) {
                return $cat->category === 'content_creation';
            });

            if (empty($content_creation)) {
                $recommendations[] = [
                    'type' => 'usage',
                    'title' => __('Explore Content Creation', 'ai-command-palette'),
                    'description' => __('Try using AI-powered content creation commands to save time.', 'ai-command-palette'),
                    'priority' => 'medium',
                    'actions' => ['show_tutorial', 'suggest_commands']
                ];
            }
        }

        // AI adoption recommendations
        $ai_analytics = $this->get_ai_analytics($period, $user_id);
        if (!empty($ai_analytics['usage_trends'])) {
            $recent_ai_usage = array_slice($ai_analytics['usage_trends'], -7);
            $avg_ai_usage = array_sum(array_column($recent_ai_usage, 'ai_requests')) / count($recent_ai_usage);

            if ($avg_ai_usage < 5) {
                $recommendations[] = [
                    'type' => 'ai_adoption',
                    'title' => __('Increase AI Usage', 'ai-command-palette'),
                    'description' => __('AI commands can help automate complex tasks and improve productivity.', 'ai-command-palette'),
                    'priority' => 'medium',
                    'actions' => ['ai_tutorial', 'feature_showcase']
                ];
            }
        }

        // User engagement recommendations
        $user_insights = $this->get_user_insights($period, $user_id);
        if (!empty($user_insights['adoption_metrics'])) {
            $metrics = $user_insights['adoption_metrics'];
            if ($metrics['active_users'] / $metrics['total_users'] < 0.5) {
                $recommendations[] = [
                    'type' => 'engagement',
                    'title' => __('Improve User Engagement', 'ai-command-palette'),
                    'description' => __('Many users are not actively using the command palette.', 'ai-command-palette'),
                    'priority' => 'high',
                    'actions' => ['user_training', 'feature_announcement']
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Calculate complexity score
     */
    private function calculate_complexity_score($period, $user_id) {
        global $wpdb;

        $user_filter = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
        $date_filter = $this->get_date_filter($period);

        // Get command complexity data
        $complexity_data = $wpdb->get_results($wpdb->prepare(
            "SELECT
                command_id,
                AVG(execution_time_ms) as avg_time,
                COUNT(*) as frequency,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*) as success_rate
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY command_id",
            $date_filter
        ));

        if (empty($complexity_data)) {
            return 0;
        }

        // Calculate weighted complexity score
        $total_commands = array_sum(array_column($complexity_data, 'frequency'));
        $complexity_score = 0;

        foreach ($complexity_data as $command) {
            $weight = $command->frequency / $total_commands;
            $time_factor = min($command->avg_time / 1000, 1); // Normalize to 0-1
            $complexity_factor = $time_factor * $command->success_rate;
            $complexity_score += $complexity_factor * $weight;
        }

        return round($complexity_score * 100, 2);
    }

    /**
     * Estimate AI costs
     */
    private function estimate_ai_costs($period, $user_id) {
        global $wpdb;

        $user_filter = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
        $date_filter = $this->get_date_filter($period);

        // Get AI usage data
        $ai_usage = $wpdb->get_results($wpdb->prepare(
            "SELECT
                JSON_EXTRACT(result, '$.provider') as provider,
                COUNT(*) as requests,
                AVG(execution_time_ms) as avg_response_time
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'ai_request'
             AND created_at >= %s
             $user_filter
             GROUP BY provider",
            $date_filter
        ));

        $cost_analysis = [
            'total_requests' => 0,
            'estimated_cost' => 0,
            'cost_per_request' => 0,
            'provider_breakdown' => []
        ];

        foreach ($ai_usage as $usage) {
            $requests = $usage->requests;
            $cost_analysis['total_requests'] += $requests;

            // Estimate cost based on provider and response time
            $cost_per_request = 0;
            switch ($usage->provider) {
                case 'openai':
                    $cost_per_request = 0.002; // Estimated cost per request
                    break;
                case 'anthropic':
                    $cost_per_request = 0.003; // Estimated cost per request
                    break;
                case 'client_side':
                    $cost_per_request = 0; // No cost for client-side AI
                    break;
                default:
                    $cost_per_request = 0.002; // Default estimate
            }

            $provider_cost = $requests * $cost_per_request;
            $cost_analysis['estimated_cost'] += $provider_cost;

            $cost_analysis['provider_breakdown'][] = [
                'provider' => $usage->provider,
                'requests' => $requests,
                'cost' => $provider_cost,
                'avg_response_time' => $usage->avg_response_time
            ];
        }

        if ($cost_analysis['total_requests'] > 0) {
            $cost_analysis['cost_per_request'] = $cost_analysis['estimated_cost'] / $cost_analysis['total_requests'];
        }

        return $cost_analysis;
    }

    /**
     * Calculate productivity gains
     */
    private function calculate_productivity_gains($period, $user_id) {
        // This would require baseline data to compare against
        // For now, return estimated gains based on command complexity and time savings
        $complexity_score = $this->calculate_complexity_score($period, $user_id);
        $time_savings = $this->calculate_time_savings($period, $user_id);

        return [
            'estimated_gains' => round($complexity_score * 0.1, 2), // 10% of complexity score
            'time_saved_hours' => $time_savings['total_hours_saved'],
            'efficiency_improvement' => round($complexity_score * 0.05, 2) // 5% of complexity score
        ];
    }

    /**
     * Calculate time savings
     */
    private function calculate_time_savings($period, $user_id) {
        global $wpdb;

        $user_filter = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
        $date_filter = $this->get_date_filter($period);

        // Get total execution time
        $total_time = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(execution_time_ms)
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter",
            $date_filter
        ));

        $total_time_ms = intval($total_time);
        $total_time_hours = $total_time_ms / (1000 * 60 * 60);

        // Estimate traditional time (assuming 3x longer without command palette)
        $traditional_time_hours = $total_time_hours * 3;
        $time_saved_hours = $traditional_time_hours - $total_time_hours;

        return [
            'total_execution_time_hours' => round($total_time_hours, 2),
            'estimated_traditional_time_hours' => round($traditional_time_hours, 2),
            'total_hours_saved' => round($time_saved_hours, 2),
            'time_savings_percentage' => round(($time_saved_hours / $traditional_time_hours) * 100, 2)
        ];
    }

    /**
     * Calculate efficiency metrics
     */
    private function calculate_efficiency_metrics($period, $user_id) {
        $performance = $this->get_performance_metrics($period, $user_id);
        $usage_patterns = $this->get_usage_patterns($period, $user_id);

        $efficiency = [
            'commands_per_session' => 0,
            'success_rate' => 0,
            'avg_response_time' => 0,
            'user_satisfaction_score' => 0
        ];

        if (!empty($performance['response_times'])) {
            $efficiency['avg_response_time'] = $performance['response_times']->avg_time ?? 0;
        }

        if (!empty($usage_patterns['session_patterns'])) {
            $efficiency['commands_per_session'] = array_sum(array_column($usage_patterns['session_patterns'], 'commands_per_session')) / count($usage_patterns['session_patterns']);
        }

        // Calculate success rate
        $overview = $this->get_overview_metrics($period, $user_id);
        $efficiency['success_rate'] = $overview['success_rate'];

        // Estimate user satisfaction based on success rate and response time
        $satisfaction_score = ($efficiency['success_rate'] * 0.7) + (max(0, 100 - $efficiency['avg_response_time'] / 10) * 0.3);
        $efficiency['user_satisfaction_score'] = round($satisfaction_score, 2);

        return $efficiency;
    }

    /**
     * Calculate ROI
     */
    private function calculate_roi($period, $user_id) {
        $time_savings = $this->calculate_time_savings($period, $user_id);
        $ai_costs = $this->estimate_ai_costs($period, $user_id);

        // Assume $50/hour average user cost
        $hourly_rate = 50;
        $time_value = $time_savings['total_hours_saved'] * $hourly_rate;
        $ai_cost = $ai_costs['estimated_cost'];

        $roi = [
            'time_value_saved' => round($time_value, 2),
            'ai_costs' => round($ai_cost, 2),
            'net_savings' => round($time_value - $ai_cost, 2),
            'roi_percentage' => $ai_cost > 0 ? round((($time_value - $ai_cost) / $ai_cost) * 100, 2) : 0,
            'break_even_point' => $ai_cost > 0 ? round($ai_cost / $hourly_rate, 2) : 0
        ];

        return $roi;
    }

    /**
     * Get competitive advantages
     */
    private function get_competitive_advantages($period, $user_id) {
        $advantages = [
            'faster_task_completion' => true,
            'reduced_learning_curve' => true,
            'ai_powered_automation' => true,
            'improved_user_experience' => true,
            'reduced_errors' => true,
            'increased_productivity' => true
        ];

        // Add quantitative data
        $performance = $this->get_performance_metrics($period, $user_id);
        $overview = $this->get_overview_metrics($period, $user_id);

        if (!empty($performance['response_times'])) {
            $advantages['avg_response_time_ms'] = $performance['response_times']->avg_time ?? 0;
        }

        $advantages['success_rate_percentage'] = $overview['success_rate'];
        $advantages['ai_usage_percentage'] = $overview['ai_usage_percentage'];

        return $advantages;
    }

    /**
     * Get date filter for period
     */
    private function get_date_filter($period) {
        switch ($period) {
            case '1d':
                return date('Y-m-d H:i:s', strtotime('-1 day'));
            case '7d':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case '30d':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            case '90d':
                return date('Y-m-d H:i:s', strtotime('-90 days'));
            default:
                return date('Y-m-d H:i:s', strtotime('-30 days'));
        }
    }

    /**
     * Track command analytics
     */
    public function track_command_analytics($command_id, $command_text, $result, $execution_time) {
        // Additional analytics tracking can be added here
        do_action('aicp_analytics_command_tracked', $command_id, $command_text, $result, $execution_time);
    }

    /**
     * Track AI analytics
     */
    public function track_ai_analytics($provider, $query, $response_time) {
        // Additional AI analytics tracking can be added here
        do_action('aicp_analytics_ai_tracked', $provider, $query, $response_time);
    }
}