<?php
namespace AICP\Core;

class Audit_Logger {
    private $user_id;
    private $session_id;

    public function __construct() {
        $this->user_id = get_current_user_id();
        $this->session_id = $this->get_session_id();

        // Hook into command execution
        add_action('aicp_command_executed', [$this, 'log_command_execution'], 10, 4);
        add_action('aicp_command_failed', [$this, 'log_command_failure'], 10, 4);
        add_action('aicp_ai_used', [$this, 'log_ai_usage'], 10, 3);
    }

    /**
     * Log command execution
     */
    public function log_command_execution($command_id, $command_text, $result, $execution_time = 0) {
        global $wpdb;

        $data = [
            'user_id' => $this->user_id,
            'session_id' => $this->session_id,
            'command_id' => $command_id,
            'command_text' => $command_text,
            'action_type' => 'execute',
            'status' => 'success',
            'result' => json_encode($result),
            'execution_time_ms' => $execution_time,
            'context' => json_encode($this->get_context()),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($wpdb->prefix . 'aicp_audit_log', $data);

        // Update usage statistics
        $this->update_usage_stats($command_id, $execution_time, true);
    }

    /**
     * Log command failure
     */
    public function log_command_failure($command_id, $command_text, $error, $execution_time = 0) {
        global $wpdb;

        $data = [
            'user_id' => $this->user_id,
            'session_id' => $this->session_id,
            'command_id' => $command_id,
            'command_text' => $command_text,
            'action_type' => 'execute',
            'status' => 'failed',
            'result' => json_encode(['error' => $error]),
            'execution_time_ms' => $execution_time,
            'context' => json_encode($this->get_context()),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($wpdb->prefix . 'aicp_audit_log', $data);

        // Update usage statistics
        $this->update_usage_stats($command_id, $execution_time, false);
    }

    /**
     * Log AI usage
     */
    public function log_ai_usage($provider, $query, $response_time = 0) {
        global $wpdb;

        $data = [
            'user_id' => $this->user_id,
            'session_id' => $this->session_id,
            'command_id' => 'ai_' . $provider,
            'command_text' => $query,
            'action_type' => 'ai_request',
            'status' => 'success',
            'result' => json_encode(['provider' => $provider, 'response_time' => $response_time]),
            'execution_time_ms' => $response_time,
            'context' => json_encode($this->get_context()),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($wpdb->prefix . 'aicp_audit_log', $data);
    }

    /**
     * Get comprehensive analytics
     */
    public function get_analytics($period = '30d', $user_id = null) {
        global $wpdb;

        $user_filter = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
        $date_filter = $this->get_date_filter($period);

        $analytics = [
            'total_commands' => 0,
            'success_rate' => 0,
            'avg_execution_time' => 0,
            'most_used_commands' => [],
            'ai_usage' => [],
            'usage_by_hour' => [],
            'usage_by_day' => [],
            'user_activity' => [],
            'error_analysis' => []
        ];

        // Total commands
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter",
            $date_filter
        ));
        $analytics['total_commands'] = intval($total);

        // Success rate
        if ($analytics['total_commands'] > 0) {
            $successful = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aicp_audit_log
                 WHERE action_type = 'execute'
                 AND status = 'success'
                 AND created_at >= %s
                 $user_filter",
                $date_filter
            ));
            $analytics['success_rate'] = round(($successful / $analytics['total_commands']) * 100, 2);
        }

        // Average execution time
        $avg_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(execution_time_ms) FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter",
            $date_filter
        ));
        $analytics['avg_execution_time'] = round($avg_time, 2);

        // Most used commands
        $most_used = $wpdb->get_results($wpdb->prepare(
            "SELECT command_id, COUNT(*) as usage_count
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY command_id
             ORDER BY usage_count DESC
             LIMIT 10",
            $date_filter
        ));
        $analytics['most_used_commands'] = $most_used;

        // AI usage
        $ai_usage = $wpdb->get_results($wpdb->prepare(
            "SELECT command_id, COUNT(*) as usage_count, AVG(execution_time_ms) as avg_time
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'ai_request'
             AND created_at >= %s
             $user_filter
             GROUP BY command_id",
            $date_filter
        ));
        $analytics['ai_usage'] = $ai_usage;

        // Usage by hour
        $usage_by_hour = $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY HOUR(created_at)
             ORDER BY hour",
            $date_filter
        ));
        $analytics['usage_by_hour'] = $usage_by_hour;

        // Usage by day
        $usage_by_day = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             $user_filter
             GROUP BY DATE(created_at)
             ORDER BY date DESC
             LIMIT 30",
            $date_filter
        ));
        $analytics['usage_by_day'] = $usage_by_day;

        // User activity (if not filtered by user)
        if (!$user_id) {
            $user_activity = $wpdb->get_results($wpdb->prepare(
                "SELECT user_id, COUNT(*) as command_count,
                        MAX(created_at) as last_activity
                 FROM {$wpdb->prefix}aicp_audit_log
                 WHERE action_type = 'execute'
                 AND created_at >= %s
                 GROUP BY user_id
                 ORDER BY command_count DESC
                 LIMIT 20",
                $date_filter
            ));
            $analytics['user_activity'] = $user_activity;
        }

        // Error analysis
        $errors = $wpdb->get_results($wpdb->prepare(
            "SELECT command_id, COUNT(*) as error_count,
                    GROUP_CONCAT(DISTINCT result) as error_messages
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND status = 'failed'
             AND created_at >= %s
             $user_filter
             GROUP BY command_id
             ORDER BY error_count DESC
             LIMIT 10",
            $date_filter
        ));
        $analytics['error_analysis'] = $errors;

        return $analytics;
    }

    /**
     * Get user-specific analytics
     */
    public function get_user_analytics($user_id = null) {
        $user_id = $user_id ?: $this->user_id;
        return $this->get_analytics('30d', $user_id);
    }

    /**
     * Get performance metrics
     */
    public function get_performance_metrics($period = '7d') {
        global $wpdb;

        $date_filter = $this->get_date_filter($period);

        $metrics = [
            'response_times' => [],
            'ai_performance' => [],
            'command_performance' => []
        ];

        // Response time percentiles
        $response_times = $wpdb->get_results($wpdb->prepare(
            "SELECT
                AVG(execution_time_ms) as avg_time,
                MIN(execution_time_ms) as min_time,
                MAX(execution_time_ms) as max_time,
                COUNT(*) as total_commands
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s",
            $date_filter
        ));
        $metrics['response_times'] = $response_times[0] ?? [];

        // AI performance
        $ai_performance = $wpdb->get_results($wpdb->prepare(
            "SELECT
                command_id,
                AVG(execution_time_ms) as avg_response_time,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_requests
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'ai_request'
             AND created_at >= %s
             GROUP BY command_id",
            $date_filter
        ));
        $metrics['ai_performance'] = $ai_performance;

        // Command performance
        $command_performance = $wpdb->get_results($wpdb->prepare(
            "SELECT
                command_id,
                AVG(execution_time_ms) as avg_execution_time,
                COUNT(*) as total_executions,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_executions
             FROM {$wpdb->prefix}aicp_audit_log
             WHERE action_type = 'execute'
             AND created_at >= %s
             GROUP BY command_id
             ORDER BY total_executions DESC
             LIMIT 20",
            $date_filter
        ));
        $metrics['command_performance'] = $command_performance;

        return $metrics;
    }

    /**
     * Export audit log
     */
    public function export_audit_log($format = 'csv', $period = '30d', $user_id = null) {
        global $wpdb;

        $user_filter = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
        $date_filter = $this->get_date_filter($period);

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aicp_audit_log
             WHERE created_at >= %s
             $user_filter
             ORDER BY created_at DESC",
            $date_filter
        ));

        if ($format === 'csv') {
            return $this->export_to_csv($logs);
        } elseif ($format === 'json') {
            return json_encode($logs);
        }

        return $logs;
    }

    /**
     * Export to CSV
     */
    private function export_to_csv($logs) {
        $filename = 'aicp_audit_log_' . date('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'ID', 'User ID', 'Session ID', 'Command ID', 'Command Text',
            'Action Type', 'Status', 'Result', 'Execution Time (ms)',
            'Context', 'Created At'
        ]);

        // CSV data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log->id,
                $log->user_id,
                $log->session_id,
                $log->command_id,
                $log->command_text,
                $log->action_type,
                $log->status,
                $log->result,
                $log->execution_time_ms,
                $log->context,
                $log->created_at
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Clean old audit logs
     */
    public function clean_old_logs($days = 90) {
        global $wpdb;

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}aicp_audit_log
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        return $deleted;
    }

    /**
     * Update usage statistics
     */
    private function update_usage_stats($command_id, $execution_time, $success) {
        global $wpdb;

        $table = $wpdb->prefix . 'aicp_usage_stats';

        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE command_id = %s AND user_id = %d",
            $command_id, $this->user_id
        ));

        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table,
                [
                    'total_executions' => $existing->total_executions + 1,
                    'successful_executions' => $existing->successful_executions + ($success ? 1 : 0),
                    'total_execution_time' => $existing->total_execution_time + $execution_time,
                    'avg_execution_time' => ($existing->total_execution_time + $execution_time) / ($existing->total_executions + 1),
                    'last_executed' => current_time('mysql')
                ],
                ['command_id' => $command_id, 'user_id' => $this->user_id]
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $table,
                [
                    'command_id' => $command_id,
                    'user_id' => $this->user_id,
                    'total_executions' => 1,
                    'successful_executions' => $success ? 1 : 0,
                    'total_execution_time' => $execution_time,
                    'avg_execution_time' => $execution_time,
                    'first_executed' => current_time('mysql'),
                    'last_executed' => current_time('mysql')
                ]
            );
        }
    }

    /**
     * Get session ID
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }

    /**
     * Get context data
     */
    private function get_context() {
        return [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_client_ip(),
            'page_url' => $_SERVER['REQUEST_URI'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_role' => $this->get_user_role(),
            'is_admin' => is_admin(),
            'is_frontend' => !is_admin()
        ];
    }

    /**
     * Get client IP
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Get user role
     */
    private function get_user_role() {
        $user = wp_get_current_user();
        return $user->roles[0] ?? 'subscriber';
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
}