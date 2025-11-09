<?php
/**
 * Plugin Name: RizqTrack - Personal Finance Tracker
 * Plugin URI: https://thejunaid.in
 * Description: Premium zero-refresh personal finance management dashboard for WordPress
 * Version: 1.4.9
 * Author: Junaid Ahmed
 * Author URI: https://thejunaid.in
 * License: GPL v2 or later
 * Text Domain: rizqtrack
 */

if (!defined('ABSPATH')) exit;

class RizqTrack {
    private static $instance = null;
    private $table_transactions;
    private $table_categories;
    private $table_goals;
    private $table_achievements;
    private $table_challenges;
    private $table_budgets;
    private $table_subscriptions;
    private $table_cron_logs;

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new RizqTrack();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_transactions = $wpdb->prefix . 'rizqtrack_transactions';
        $this->table_categories = $wpdb->prefix . 'rizqtrack_categories';
        $this->table_goals = $wpdb->prefix . 'rizqtrack_goals';
        $this->table_achievements = $wpdb->prefix . 'rizqtrack_achievements';
        $this->table_challenges = $wpdb->prefix . 'rizqtrack_challenges';
        $this->table_budgets = $wpdb->prefix . 'rizqtrack_budgets';
        $this->table_subscriptions = $wpdb->prefix . 'rizqtrack_subscriptions';
        $this->table_cron_logs = $wpdb->prefix . 'rizqtrack_cron_logs';

        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_init', [$this, 'run_migrations']);

        // Shortcode
        add_shortcode('rizqtrack_dashboard', [$this, 'render_frontend_dashboard']);

        // AJAX endpoints
        $this->register_ajax_endpoints();

        // REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function run_migrations() {
        global $wpdb;

        // Migration: Add Fuel category if it doesn't exist
        $fuel_exists = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_categories} WHERE name = 'Fuel' AND user_id = 0"
        );

        if ($fuel_exists == 0) {
            $wpdb->insert($this->table_categories, [
                'user_id' => 0,
                'name' => 'Fuel',
                'type' => 'expense',
                'emoji' => '⛽'
            ]);
        }

        // Migration: Add fuel-related columns if they don't exist
        $fuel_columns = ['odometer_reading', 'fuel_liters', 'fuel_amount'];
        foreach ($fuel_columns as $column) {
            $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$this->table_transactions}' AND column_name = '{$column}'");
            if (empty($row)) {
                $wpdb->query("ALTER TABLE {$this->table_transactions} ADD COLUMN {$column} decimal(10,2) DEFAULT NULL");
            }
        }

        // Migration: Add is_full_tank column if it doesn't exist
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$this->table_transactions}' AND column_name = 'is_full_tank'");
        if (empty($row)) {
            $wpdb->query("ALTER TABLE {$this->table_transactions} ADD COLUMN is_full_tank tinyint(1) DEFAULT 0");
        }

        // Migration: Add end_date column to subscriptions table if it doesn't exist
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$this->table_subscriptions}' AND column_name = 'end_date'");
        if (empty($row)) {
            $wpdb->query("ALTER TABLE {$this->table_subscriptions} ADD COLUMN end_date date DEFAULT NULL AFTER last_renewed_date");
        }

        // Migration: Update billing_cycle enum to include '5year' and 'one-time'
        $wpdb->query("ALTER TABLE {$this->table_subscriptions} MODIFY COLUMN billing_cycle enum('monthly','quarterly','yearly','5year','one-time') DEFAULT 'monthly'");

        // Migration: Create cron logs table if it doesn't exist
        $this->create_cron_logs_table();
    }

    public function activate() {
        $this->create_tables();
        $this->create_default_categories();
    }

    private function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_transactions = "CREATE TABLE IF NOT EXISTS {$this->table_transactions} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type enum('income','expense') NOT NULL,
            amount decimal(10,2) NOT NULL,
            date date NOT NULL,
            category_id bigint(20) NOT NULL,
            payment_method varchar(50) NOT NULL,
            description text,
            goal_id bigint(20) DEFAULT NULL,
            odometer_reading decimal(10,2) DEFAULT NULL,
            fuel_liters decimal(10,2) DEFAULT NULL,
            fuel_amount decimal(10,2) DEFAULT NULL,
            is_full_tank tinyint(1) DEFAULT 0,
            status enum('Active','Trash') DEFAULT 'Active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY date (date),
            KEY goal_id (goal_id),
            KEY category_id (category_id)
        ) $charset;";

        $sql_categories = "CREATE TABLE IF NOT EXISTS {$this->table_categories} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(100) NOT NULL,
            type enum('income','expense','both') NOT NULL,
            emoji varchar(10) DEFAULT '📌',
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset;";

        $sql_goals = "CREATE TABLE IF NOT EXISTS {$this->table_goals} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(200) NOT NULL,
            target_amount decimal(10,2) NOT NULL,
            current_amount decimal(10,2) DEFAULT 0,
            deadline date,
            category varchar(50),
            priority varchar(20),
            start_date date,
            notes text,
            status enum('active','completed','archived','Trash') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset;";

        $sql_achievements = "CREATE TABLE IF NOT EXISTS {$this->table_achievements} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            achievement_key varchar(50) NOT NULL,
            achievement_name varchar(200) NOT NULL,
            achievement_description text,
            badge_icon varchar(20) NOT NULL,
            badge_color varchar(20) DEFAULT '#0891b2',
            earned_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            UNIQUE KEY user_achievement (user_id, achievement_key)
        ) $charset;";

        $sql_challenges = "CREATE TABLE IF NOT EXISTS {$this->table_challenges} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            challenge_type varchar(50) NOT NULL,
            challenge_name varchar(200) NOT NULL,
            target_amount decimal(10,2) NOT NULL,
            current_amount decimal(10,2) DEFAULT 0,
            start_date date NOT NULL,
            end_date date NOT NULL,
            frequency varchar(20) DEFAULT 'weekly',
            status enum('active','completed','paused','failed','deleted') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset;";

        $sql_budgets = "CREATE TABLE IF NOT EXISTS {$this->table_budgets} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            category_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            period enum('monthly','yearly') DEFAULT 'monthly',
            start_date date NOT NULL,
            rollover tinyint(1) DEFAULT 0,
            alert_threshold int DEFAULT 80,
            status enum('active','deleted') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY category_id (category_id),
            KEY status (status)
        ) $charset;";

        $sql_subscriptions = "CREATE TABLE IF NOT EXISTS {$this->table_subscriptions} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(200) NOT NULL,
            amount decimal(10,2) NOT NULL,
            category_id bigint(20) NOT NULL,
            billing_cycle enum('monthly','quarterly','yearly','5year','one-time') DEFAULT 'monthly',
            custom_cycle_days int DEFAULT NULL,
            start_date date NOT NULL,
            next_billing_date date NOT NULL,
            last_renewed_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            payment_method varchar(50) NOT NULL,
            auto_renew tinyint(1) DEFAULT 0,
            reminder_days int DEFAULT 7,
            notes text DEFAULT NULL,
            status enum('Active','Inactive','Trash') DEFAULT 'Active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY category_id (category_id),
            KEY status (status),
            KEY next_billing_date (next_billing_date)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_transactions);
        dbDelta($sql_categories);
        dbDelta($sql_goals);
        dbDelta($sql_achievements);
        dbDelta($sql_challenges);
        dbDelta($sql_budgets);
        dbDelta($sql_subscriptions);

        // Create cron logs table
        $this->create_cron_logs_table();

        // Migration: Add goal_id column if it doesn't exist
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$this->table_transactions}' AND column_name = 'goal_id'");
        if (empty($row)) {
            $wpdb->query("ALTER TABLE {$this->table_transactions} ADD COLUMN goal_id bigint(20) DEFAULT NULL AFTER description, ADD KEY goal_id (goal_id)");
        }

        // Migration: Add fuel-related columns if they don't exist
        $fuel_columns = ['odometer_reading', 'fuel_liters', 'fuel_amount'];
        foreach ($fuel_columns as $column) {
            $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$this->table_transactions}' AND column_name = '{$column}'");
            if (empty($row)) {
                $wpdb->query("ALTER TABLE {$this->table_transactions} ADD COLUMN {$column} decimal(10,2) DEFAULT NULL");
            }
        }
    }

    private function create_default_categories() {
        global $wpdb;

        $categories = [
            ['Housing/Rent', 'expense', '🏠'],
            ['Transportation', 'expense', '🚗'],
            ['Food & Groceries', 'expense', '🛒'],
            ['Utilities & Bills', 'expense', '💡'],
            ['Fuel', 'expense', '⛽'],
            ['Salary/Wages', 'income', '💰'],
            ['Investment/Business', 'income', '📈'],
            ['Miscellaneous', 'expense', '✨']
        ];

        // Only insert if no default (user_id=0) categories exist
        $default_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories} WHERE user_id = 0");
        if ($default_count == 0) {
            foreach ($categories as $cat) {
                $wpdb->insert($this->table_categories, [
                    'user_id' => 0,
                    'name' => $cat[0],
                    'type' => $cat[1],
                    'emoji' => $cat[2]
                ]);
            }
        }
    }

    private function create_cron_logs_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_cron_logs} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            job_type varchar(50) NOT NULL,
            status enum('success','error') NOT NULL,
            execution_time datetime DEFAULT CURRENT_TIMESTAMP,
            duration_ms int DEFAULT NULL,
            users_processed int DEFAULT 0,
            emails_sent int DEFAULT 0,
            errors_count int DEFAULT 0,
            error_message text DEFAULT NULL,
            request_ip varchar(45) DEFAULT NULL,
            request_user_agent text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY job_type (job_type),
            KEY status (status),
            KEY execution_time (execution_time)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_menu() {
        add_menu_page(
            'RizqTrack Dashboard',
            'RizqTrack',
            'read',
            'rizqtrack',
            [$this, 'render_dashboard'],
            'dashicons-chart-pie',
            30
        );

        // Add submenu for Admin Dashboard (admin only)
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'rizqtrack',
                'Admin Dashboard',
                '👤 Admin Dashboard',
                'manage_options',
                'rizqtrack-admin',
                [$this, 'render_admin_dashboard']
            );

            add_submenu_page(
                'rizqtrack',
                'Cron Logs',
                '⏰ Cron Logs',
                'manage_options',
                'rizqtrack-cron-logs',
                [$this, 'render_cron_logs_page']
            );
        }
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_rizqtrack' && $hook !== 'rizqtrack_page_rizqtrack-cron-logs' && $hook !== 'rizqtrack_page_rizqtrack-admin') return;

        $version = '1.4.9'; // Updated version for cache busting
        wp_enqueue_style('rizqtrack-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], $version);
        wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap');

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true);

        // MODIFIED: Added Datalabels plugin
        wp_enqueue_script('chart-js-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js', ['chart-js'], '2.2.0', true);

        // MODIFIED: Added 'chart-js-datalabels' as a dependency
        wp_enqueue_script('rizqtrack-script', plugin_dir_url(__FILE__) . 'assets/js/app.js', ['jquery', 'chart-js', 'chart-js-datalabels'], $version, true);

        wp_localize_script('rizqtrack-script', 'rizqtrack', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rizqtrack_nonce')
        ]);
    }

    public function enqueue_frontend_assets() {
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'rizqtrack_dashboard')) {
            return;
        }

        $version = '1.4.9'; // Updated version for cache busting
        wp_enqueue_style('rizqtrack-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], $version);
        wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap');

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true);

        // MODIFIED: Added Datalabels plugin
        wp_enqueue_script('chart-js-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js', ['chart-js'], '2.2.0', true);

        // MODIFIED: Added 'chart-js-datalabels' as a dependency
        wp_enqueue_script('rizqtrack-script', plugin_dir_url(__FILE__) . 'assets/js/app.js', ['jquery', 'chart-js', 'chart-js-datalabels'], $version, true);

        wp_localize_script('rizqtrack-script', 'rizqtrack', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rizqtrack_nonce')
        ]);

        // PWA Support - Add manifest link and service worker
        add_action('wp_head', function() {
            echo '<link rel="manifest" href="' . plugin_dir_url(__FILE__) . 'assets/manifest.json">';
            echo '<meta name="theme-color" content="#0891b2">';
            echo '<meta name="apple-mobile-web-app-capable" content="yes">';
            echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
            echo '<meta name="apple-mobile-web-app-title" content="RizqTrack">';
            echo '<link rel="apple-touch-icon" href="' . plugin_dir_url(__FILE__) . 'assets/icons/icon-192x192.png">';
        });

        // Register service worker inline script
        wp_add_inline_script('rizqtrack-script', "
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('" . plugin_dir_url(__FILE__) . "assets/sw.js')
                        .then(function(registration) {
                            console.log('[RizqTrack] Service Worker registered:', registration.scope);
                        })
                        .catch(function(error) {
                            console.log('[RizqTrack] Service Worker registration failed:', error);
                        });
                });
            }
        ", 'after');
    }

    private function register_ajax_endpoints() {
        $endpoints = [
            'add_transaction', 'update_transaction', 'delete_transaction',
            'restore_transaction', 'permanent_delete', 'get_recent_transactions',
            'get_chart_data', 'get_category_details', 'get_categories', 'get_goals', 'get_trash',
            'add_category', 'update_category', 'delete_category',
            'add_goal', 'update_goal', 'delete_goal', 'restore_goal', 'permanent_delete_goal',
            'contribute_goal_transaction', 'generate_report', 'get_kpi_data',
            'get_email_settings', 'save_email_settings', 'test_email', 'send_email_now',
            'get_achievements', 'check_achievements',
            'get_challenges', 'start_challenge', 'update_challenge', 'complete_challenge', 'delete_challenge',
            'get_budgets', 'add_budget', 'update_budget', 'delete_budget', 'check_budget_alerts', 'get_budget_vs_actual',
            'get_subscriptions', 'add_subscription', 'update_subscription', 'delete_subscription',
            'restore_subscription', 'permanent_delete_subscription', 'renew_subscription', 'reactivate_subscription',
            'deactivate_subscription', 'undo_payment'
        ];

        foreach ($endpoints as $endpoint) {
            add_action("wp_ajax_rizqtrack_{$endpoint}", [$this, "ajax_{$endpoint}"]);
        }

        // Register cron hooks
        add_action('rizqtrack_send_weekly_email', [$this, 'send_weekly_report']);
        add_action('rizqtrack_send_monthly_email', [$this, 'send_monthly_report']);
    }

    public function register_rest_routes() {
        // Weekly cron endpoint
        register_rest_route('rizqtrack/v1', '/cron/weekly', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_cron_weekly'],
            'permission_callback' => [$this, 'verify_cron_request']
        ]);

        // Monthly cron endpoint
        register_rest_route('rizqtrack/v1', '/cron/monthly', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_cron_monthly'],
            'permission_callback' => [$this, 'verify_cron_request']
        ]);

        // Cron logs endpoint (admin only)
        register_rest_route('rizqtrack/v1', '/cron/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_cron_logs'],
            'permission_callback' => [$this, 'verify_admin_request']
        ]);
    }

    public function verify_cron_request($request) {
        // Get the cron-job.org API key from settings
        $cronjob_api_key = trim(get_option('rizqtrack_cronjob_api_key'));

        // If no API key is set, allow localhost for testing
        if (empty($cronjob_api_key)) {
            $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (in_array($remote_ip, ['127.0.0.1', '::1'])) {
                return true;
            }
            return new WP_Error('no_api_key', 'Cron-job.org API key not configured. Please configure it in RizqTrack → Cron Logs.', ['status' => 401]);
        }

        // Get the provided key from URL parameter
        $provided_key = trim($request->get_param('key'));

        // Check if key matches
        if ($provided_key === $cronjob_api_key) {
            return true;
        }

        // For testing: allow from localhost
        $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (in_array($remote_ip, ['127.0.0.1', '::1'])) {
            return true;
        }

        return new WP_Error('invalid_key', 'Invalid API key', ['status' => 403]);
    }

    public function verify_admin_request($request) {
        return current_user_can('manage_options');
    }

    public function rest_cron_weekly($request) {
        $start_time = microtime(true);
        $users_processed = 0;
        $emails_sent = 0;
        $errors = [];

        try {
            // Get all users who have weekly email enabled
            $users = get_users(['meta_key' => 'rizqtrack_email_frequency', 'meta_value' => 'weekly']);

            foreach ($users as $user) {
                try {
                    $users_processed++;

                    // Check if auto-send is enabled
                    $auto_send = get_user_meta($user->ID, 'rizqtrack_auto_send', true);
                    if ($auto_send == 1) {
                        $this->send_weekly_report($user->ID);
                        $emails_sent++;
                    }
                } catch (Exception $e) {
                    $errors[] = "User {$user->ID}: " . $e->getMessage();
                }
            }

            $duration_ms = round((microtime(true) - $start_time) * 1000);

            // Log success
            $this->log_cron_execution('weekly', 'success', $duration_ms, $users_processed, $emails_sent, count($errors), implode('; ', $errors), $request);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Weekly cron job completed successfully',
                'stats' => [
                    'users_processed' => $users_processed,
                    'emails_sent' => $emails_sent,
                    'errors' => count($errors),
                    'duration_ms' => $duration_ms
                ],
                'errors' => $errors
            ], 200);

        } catch (Exception $e) {
            $duration_ms = round((microtime(true) - $start_time) * 1000);

            // Log error
            $this->log_cron_execution('weekly', 'error', $duration_ms, $users_processed, $emails_sent, 1, $e->getMessage(), $request);

            return new WP_REST_Response([
                'success' => false,
                'message' => 'Weekly cron job failed: ' . $e->getMessage(),
                'stats' => [
                    'users_processed' => $users_processed,
                    'emails_sent' => $emails_sent,
                    'duration_ms' => $duration_ms
                ]
            ], 500);
        }
    }

    public function rest_cron_monthly($request) {
        $start_time = microtime(true);
        $users_processed = 0;
        $emails_sent = 0;
        $errors = [];

        try {
            // Get all users who have monthly email enabled
            $users = get_users(['meta_key' => 'rizqtrack_email_frequency', 'meta_value' => 'monthly']);

            foreach ($users as $user) {
                try {
                    $users_processed++;

                    // The send_monthly_report function already checks auto_send and send_day
                    $this->send_monthly_report($user->ID);

                    // Count as sent if auto-send is enabled
                    $auto_send = get_user_meta($user->ID, 'rizqtrack_auto_send', true);
                    if ($auto_send == 1) {
                        $emails_sent++;
                    }
                } catch (Exception $e) {
                    $errors[] = "User {$user->ID}: " . $e->getMessage();
                }
            }

            $duration_ms = round((microtime(true) - $start_time) * 1000);

            // Log success
            $this->log_cron_execution('monthly', 'success', $duration_ms, $users_processed, $emails_sent, count($errors), implode('; ', $errors), $request);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Monthly cron job completed successfully',
                'stats' => [
                    'users_processed' => $users_processed,
                    'emails_sent' => $emails_sent,
                    'errors' => count($errors),
                    'duration_ms' => $duration_ms
                ],
                'errors' => $errors
            ], 200);

        } catch (Exception $e) {
            $duration_ms = round((microtime(true) - $start_time) * 1000);

            // Log error
            $this->log_cron_execution('monthly', 'error', $duration_ms, $users_processed, $emails_sent, 1, $e->getMessage(), $request);

            return new WP_REST_Response([
                'success' => false,
                'message' => 'Monthly cron job failed: ' . $e->getMessage(),
                'stats' => [
                    'users_processed' => $users_processed,
                    'emails_sent' => $emails_sent,
                    'duration_ms' => $duration_ms
                ]
            ], 500);
        }
    }

    public function rest_get_cron_logs($request) {
        global $wpdb;

        $limit = $request->get_param('limit') ?: 50;
        $offset = $request->get_param('offset') ?: 0;
        $job_type = $request->get_param('job_type');

        $where = '';
        if ($job_type && in_array($job_type, ['weekly', 'monthly'])) {
            $where = $wpdb->prepare(" WHERE job_type = %s", $job_type);
        }

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_cron_logs}
             {$where}
             ORDER BY execution_time DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_cron_logs} {$where}");

        return new WP_REST_Response([
            'success' => true,
            'logs' => $logs,
            'total' => (int) $total,
            'limit' => (int) $limit,
            'offset' => (int) $offset
        ], 200);
    }

    private function log_cron_execution($job_type, $status, $duration_ms, $users_processed, $emails_sent, $errors_count, $error_message, $request) {
        global $wpdb;

        $wpdb->insert($this->table_cron_logs, [
            'job_type' => $job_type,
            'status' => $status,
            'duration_ms' => $duration_ms,
            'users_processed' => $users_processed,
            'emails_sent' => $emails_sent,
            'errors_count' => $errors_count,
            'error_message' => !empty($error_message) ? $error_message : null,
            'request_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'request_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    // Transaction AJAX Handlers
    public function ajax_add_transaction() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to add transactions']);
            return;
        }

        if (empty($_POST['amount']) || empty($_POST['date']) || empty($_POST['category_id']) || empty($_POST['payment_method'])) {
            wp_send_json_error(['message' => 'Please fill in all required fields']);
            return;
        }

        // Validate amount
        $amount = floatval($_POST['amount']);
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Amount must be greater than 0']);
            return;
        }

        // Validate date (not in future)
        $date = sanitize_text_field($_POST['date']);
        $selected_date = strtotime($date);
        $today = strtotime(date('Y-m-d'));
        
        if ($selected_date > $today) {
            wp_send_json_error(['message' => 'Cannot add transactions for future dates']);
            return;
        }

        $data = [
            'user_id' => $user_id,
            'type' => sanitize_text_field($_POST['type']),
            'amount' => $amount,
            'date' => $date,
            'category_id' => intval($_POST['category_id']),
            'payment_method' => sanitize_text_field($_POST['payment_method']),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'status' => 'Active'
        ];

        // Add fuel-specific fields if provided
        if (!empty($_POST['odometer_reading'])) {
            $data['odometer_reading'] = floatval($_POST['odometer_reading']);
        }
        if (!empty($_POST['fuel_liters'])) {
            $data['fuel_liters'] = floatval($_POST['fuel_liters']);
        }
        if (isset($_POST['is_full_tank'])) {
            $data['is_full_tank'] = intval($_POST['is_full_tank']);
        }

        $result = $wpdb->insert($this->table_transactions, $data);

        if ($result) {
            wp_send_json_success(['message' => 'Transaction added successfully']);
        } else {
            wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);
        }
        wp_die();
    }

    public function ajax_update_transaction() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        // Validate amount
        $amount = floatval($_POST['amount']);
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Amount must be greater than 0']);
            return;
        }

        // Validate date (not in future)
        $date = sanitize_text_field($_POST['date']);
        $selected_date = strtotime($date);
        $today = strtotime(date('Y-m-d'));
        
        if ($selected_date > $today) {
            wp_send_json_error(['message' => 'Cannot set transaction date in the future']);
            return;
        }

        $data = [
            'type' => sanitize_text_field($_POST['type']),
            'amount' => $amount,
            'date' => $date,
            'category_id' => intval($_POST['category_id']),
            'payment_method' => sanitize_text_field($_POST['payment_method']),
            'description' => sanitize_textarea_field($_POST['description'] ?? '')
        ];

        // Add fuel-specific fields if provided (or set to NULL if empty)
        if (isset($_POST['odometer_reading'])) {
            $data['odometer_reading'] = !empty($_POST['odometer_reading']) ? floatval($_POST['odometer_reading']) : null;
        }
        if (isset($_POST['fuel_liters'])) {
            $data['fuel_liters'] = !empty($_POST['fuel_liters']) ? floatval($_POST['fuel_liters']) : null;
        }
        if (isset($_POST['is_full_tank'])) {
            $data['is_full_tank'] = intval($_POST['is_full_tank']);
        }

        $result = $wpdb->update(
            $this->table_transactions,
            $data,
            ['id' => $id, 'user_id' => $user_id]
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Transaction updated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to update transaction']);
        }
        wp_die();
    }

    public function ajax_delete_transaction() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        // Get transaction data before deleting to check for goal_id
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT goal_id, amount FROM {$this->table_transactions} WHERE id = %d AND user_id = %d",
            $id, $user_id
        ));

        $result = $wpdb->update(
            $this->table_transactions,
            ['status' => 'Trash'],
            ['id' => $id, 'user_id' => $user_id]
        );

        if ($result) {
            // If transaction was linked to a goal, reduce the goal's current_amount
            if ($transaction && $transaction->goal_id) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table_goals}
                    SET current_amount = GREATEST(0, current_amount - %f)
                    WHERE id = %d AND user_id = %d",
                    $transaction->amount, $transaction->goal_id, $user_id
                ));
            }
            wp_send_json_success(['message' => 'Transaction moved to trash']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete transaction']);
        }
        wp_die();
    }

    public function ajax_restore_transaction() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        // Get transaction data before restoring to check for goal_id
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT goal_id, amount FROM {$this->table_transactions} WHERE id = %d AND user_id = %d",
            $id, $user_id
        ));

        $result = $wpdb->update(
            $this->table_transactions,
            ['status' => 'Active'],
            ['id' => $id, 'user_id' => $user_id]
        );

        if ($result) {
            // If transaction was linked to a goal, add the amount back to goal's current_amount
            if ($transaction && $transaction->goal_id) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table_goals}
                    SET current_amount = current_amount + %f
                    WHERE id = %d AND user_id = %d",
                    $transaction->amount, $transaction->goal_id, $user_id
                ));
            }
            wp_send_json_success(['message' => 'Transaction restored']);
        } else {
            wp_send_json_error(['message' => 'Failed to restore transaction']);
        }
        wp_die();
    }

    public function ajax_permanent_delete() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        // Get transaction data before deleting to check for goal_id
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT goal_id, amount FROM {$this->table_transactions} WHERE id = %d AND user_id = %d AND status = 'Trash'",
            $id, $user_id
        ));

        $result = $wpdb->delete(
            $this->table_transactions,
            ['id' => $id, 'user_id' => $user_id, 'status' => 'Trash']
        );

        if ($result) {
            // If transaction was linked to a goal, reduce the goal's current_amount (only if not already reduced)
            // Since this is permanent delete from trash, the amount was already reduced when moved to trash
            // So we don't need to reduce again
            wp_send_json_success(['message' => 'Transaction permanently deleted']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete transaction']);
        }
        wp_die();
    }

    // --- MODIFIED: ajax_get_recent_transactions function ---
    public function ajax_get_recent_transactions() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to view data.']);
            wp_die();
        }

        // --- START: Pagination Logic ---
        $limit = 5; // Show 5 transactions per page
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $offset = ($page - 1) * $limit;
        // --- END: Pagination Logic ---

        // --- START: Filter Logic ---
        $where_clauses = ["t.user_id = %d", "t.status = 'Active'"];
        $prepare_values = [$user_id];

        // Search filter
        if (!empty($_POST['search'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_POST['search'])) . '%';
            $where_clauses[] = "t.description LIKE %s";
            $prepare_values[] = $search;
        }

        // Category filter
        if (!empty($_POST['category_id']) && $_POST['category_id'] != '0') {
            $where_clauses[] = "t.category_id = %d";
            $prepare_values[] = intval($_POST['category_id']);
        }

        // Date range filter
        if (!empty($_POST['start_date'])) {
            $where_clauses[] = "t.date >= %s";
            $prepare_values[] = sanitize_text_field($_POST['start_date']);
        }

        if (!empty($_POST['end_date'])) {
            $where_clauses[] = "t.date <= %s";
            $prepare_values[] = sanitize_text_field($_POST['end_date']);
        }

        $where_sql = implode(' AND ', $where_clauses);
        // --- END: Filter Logic ---

        // Query for the transactions with pagination and filters
        $query = "SELECT t.*, c.name as category_name, c.emoji as category_emoji
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE {$where_sql}
            ORDER BY t.date DESC, t.created_at DESC
            LIMIT %d OFFSET %d";

        $prepare_values[] = $limit;
        $prepare_values[] = $offset;

        $transactions = $wpdb->get_results($wpdb->prepare($query, $prepare_values));

        // Get total count with same filters
        $count_query = "SELECT COUNT(*)
            FROM {$this->table_transactions} t
            WHERE {$where_sql}";

        $total_count = $wpdb->get_var($wpdb->prepare($count_query, array_slice($prepare_values, 0, -2)));

        // Return both transactions and total count
        wp_send_json_success([
            'transactions' => $transactions,
            'total' => $total_count
        ]);

        wp_die();
    }

    public function ajax_get_chart_data() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to view data.']);
            wp_die();
        }

        // Get date range from request
        $end_date = sanitize_text_field($_POST['end_date'] ?? date('Y-m-d'));
        $start_date = sanitize_text_field($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')));

        // Get selected categories if provided
        $selected_categories = [];
        $category_filter_sql = '';
        $category_filter_params = [];

        if (!empty($_POST['categories'])) {
            $categories_string = sanitize_text_field($_POST['categories']);
            $selected_categories = explode(',', $categories_string);
            $selected_categories = array_map('trim', $selected_categories);

            // Build SQL for category filtering
            $placeholders = implode(',', array_fill(0, count($selected_categories), '%s'));
            $category_filter_sql = " AND c.name IN ($placeholders)";
            $category_filter_params = $selected_categories;
        }

        // Category breakdown (expenses only)
        $category_query = "SELECT c.name, c.emoji, SUM(t.amount) as total
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active' AND t.type = 'expense'
            AND t.date >= %s AND t.date <= %s
            $category_filter_sql
            GROUP BY c.id, c.name, c.emoji
            ORDER BY total DESC";

        $category_params = array_merge([$user_id, $start_date, $end_date], $category_filter_params);
        $category_data = $wpdb->get_results($wpdb->prepare($category_query, $category_params));

        // Top Frequent Categories (with category filter)
        $top_frequent_query = "SELECT c.name, c.emoji, COUNT(*) as count, SUM(t.amount) as total_amount
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active' AND t.type = 'expense'
            AND t.date >= %s AND t.date <= %s
            $category_filter_sql
            GROUP BY c.id, c.name, c.emoji
            ORDER BY count DESC, total_amount DESC
            LIMIT 10";

        $top_frequent_params = array_merge([$user_id, $start_date, $end_date], $category_filter_params);
        $top_frequent = $wpdb->get_results($wpdb->prepare($top_frequent_query, $top_frequent_params));

        // Spending trend over time (with category filter)
        $spending_trend_query = "SELECT
                DATE(t.date) as date,
                COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as expense
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active'
            AND t.date >= %s AND t.date <= %s
            $category_filter_sql
            GROUP BY DATE(t.date)
            ORDER BY date ASC";

        $spending_trend_params = array_merge([$user_id, $start_date, $end_date], $category_filter_params);
        $spending_trend = $wpdb->get_results($wpdb->prepare($spending_trend_query, $spending_trend_params));

        // Get total transaction count for the period
        $transaction_count_query = "SELECT COUNT(*) as total_count
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active'
            AND t.date >= %s AND t.date <= %s
            $category_filter_sql";

        $transaction_count_params = array_merge([$user_id, $start_date, $end_date], $category_filter_params);
        $transaction_count_result = $wpdb->get_row($wpdb->prepare($transaction_count_query, $transaction_count_params));
        $transaction_count = $transaction_count_result ? intval($transaction_count_result->total_count) : 0;

        // Calculate total income and expense for the period
        $totals_query = "SELECT
                COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active'
            AND t.date >= %s AND t.date <= %s
            $category_filter_sql";

        $totals_params = array_merge([$user_id, $start_date, $end_date], $category_filter_params);
        $totals = $wpdb->get_row($wpdb->prepare($totals_query, $totals_params));

        wp_send_json_success([
            'category_data' => $category_data,
            'top_frequent' => $top_frequent,
            'spending_trend' => $spending_trend,
            'transaction_count' => $transaction_count,
            'total_income' => floatval($totals->total_income ?? 0),
            'total_expense' => floatval($totals->total_expense ?? 0)
        ]);
        wp_die();
    }

    public function ajax_get_category_details() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to view data.']);
            wp_die();
        }

        $category_name = sanitize_text_field($_POST['category'] ?? '');
        $filter = sanitize_text_field($_POST['filter'] ?? '30');

        $days_map = [
            '7' => 7, '15' => 15, '30' => 30, '60' => 60, '90' => 90,
            '120' => 120, '150' => 150, '180' => 180, '210' => 210,
            '240' => 240, '270' => 270, '300' => 300, '330' => 330, '365' => 365
        ];
        $days = $days_map[$filter] ?? 30;

        // Get transactions for specific category
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT t.date, t.description, t.amount, t.type
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active'
            AND c.name = %s
            AND t.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            ORDER BY t.date DESC",
            $user_id, $category_name, $days
        ));

        wp_send_json_success($transactions);
        wp_die();
    }

    public function ajax_get_kpi_data() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to view data.']);
            wp_die();
        }

        // Get all-time income, expense, and transaction count
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
                COUNT(*) as transaction_count,
                COALESCE(AVG(amount), 0) as avg_transaction
            FROM {$this->table_transactions}
            WHERE user_id = %d AND status = 'Active'",
            $user_id
        ));

        // Get top spending category (by amount)
        $top_category = $wpdb->get_row($wpdb->prepare(
            "SELECT c.name, c.emoji, SUM(t.amount) as total
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active' AND t.type = 'expense'
            GROUP BY c.id, c.name, c.emoji
            ORDER BY total DESC
            LIMIT 1",
            $user_id
        ));

        // Get most frequent expense category (by transaction count)
        $most_frequent_category = $wpdb->get_row($wpdb->prepare(
            "SELECT c.name, c.emoji, COUNT(*) as count
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active' AND t.type = 'expense'
            GROUP BY c.id, c.name, c.emoji
            ORDER BY count DESC
            LIMIT 1",
            $user_id
        ));

        // Get days without spending (days since last expense)
        $last_expense = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(date) FROM {$this->table_transactions}
            WHERE user_id = %d AND status = 'Active' AND type = 'expense'",
            $user_id
        ));

        $days_without_spending = 0;
        if ($last_expense) {
            $last_expense_date = new DateTime($last_expense);
            $today = new DateTime();
            $interval = $today->diff($last_expense_date);
            $days_without_spending = $interval->days;
        }

        // Get busiest spending day (exact date with most expense transactions)
        $busiest_day = $wpdb->get_row($wpdb->prepare(
            "SELECT date, COUNT(*) as count
            FROM {$this->table_transactions}
            WHERE user_id = %d AND status = 'Active' AND type = 'expense'
            GROUP BY date
            ORDER BY count DESC, date DESC
            LIMIT 1",
            $user_id
        ));

        $busiest_day_formatted = 'N/A';
        if ($busiest_day && $busiest_day->date) {
            $date = new DateTime($busiest_day->date);
            $busiest_day_formatted = $date->format('d M Y');
        }

        // Calculate Average Income Per Day (current month)
        $current_month_start = date('Y-m-01');
        $current_month_end = date('Y-m-t');
        $days_in_month = date('t');

        $month_income = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0)
            FROM {$this->table_transactions}
            WHERE user_id = %d AND status = 'Active' AND type = 'income'
            AND date >= %s AND date <= %s",
            $user_id, $current_month_start, $current_month_end
        ));

        // Calculate Average Expense Per Day (current month)
        $month_expense = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0)
            FROM {$this->table_transactions}
            WHERE user_id = %d AND status = 'Active' AND type = 'expense'
            AND date >= %s AND date <= %s",
            $user_id, $current_month_start, $current_month_end
        ));

        $avg_income_per_day = floatval($month_income) / $days_in_month;
        $avg_expense_per_day = floatval($month_expense) / $days_in_month;

        // Calculate Vehicle Mileage (all time)
        // Get fuel category ID
        $fuel_category = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_categories}
            WHERE name = 'Fuel' AND (user_id = 0 OR user_id = %d)
            LIMIT 1",
            $user_id
        ));

        $vehicle_mileage = 0;
        if ($fuel_category) {
            // Get ONLY full tank fuel transactions ordered by odometer reading
            $fuel_transactions = $wpdb->get_results($wpdb->prepare(
                "SELECT odometer_reading, fuel_liters
                FROM {$this->table_transactions}
                WHERE user_id = %d AND status = 'Active'
                AND category_id = %d
                AND odometer_reading IS NOT NULL
                AND fuel_liters IS NOT NULL AND fuel_liters > 0
                AND is_full_tank = 1
                ORDER BY odometer_reading ASC",
                $user_id, $fuel_category
            ));

            // Calculate mileage between consecutive full tank fills
            // Distance = difference in odometer readings
            // Fuel = sum of fuel between those two readings (including current fill)
            $total_distance = 0;
            $total_fuel = 0;
            $prev_odometer = null;

            foreach ($fuel_transactions as $transaction) {
                $current_odometer = floatval($transaction->odometer_reading);
                $current_fuel = floatval($transaction->fuel_liters);

                if ($prev_odometer !== null) {
                    $distance = $current_odometer - $prev_odometer;
                    // Only add if distance is positive (odometer should always increase)
                    if ($distance > 0) {
                        $total_distance += $distance;
                        $total_fuel += $current_fuel;
                    }
                }
                $prev_odometer = $current_odometer;
            }

            if ($total_fuel > 0) {
                $vehicle_mileage = $total_distance / $total_fuel;
            }
        }

        $kpi_data = [
            'total_income' => floatval($summary->total_income),
            'total_expense' => floatval($summary->total_expense),
            'net_savings' => floatval($summary->total_income) - floatval($summary->total_expense),
            'transaction_count' => intval($summary->transaction_count),
            'avg_transaction' => floatval($summary->avg_transaction),
            'top_category' => $top_category ? $top_category->emoji . ' ' . $top_category->name : 'N/A',
            'most_frequent_category' => $most_frequent_category ? $most_frequent_category->emoji . ' ' . $most_frequent_category->name : 'N/A',
            'days_without_spending' => intval($days_without_spending),
            'busiest_day' => $busiest_day_formatted,
            'avg_income_per_day' => $avg_income_per_day,
            'avg_expense_per_day' => $avg_expense_per_day,
            'vehicle_mileage' => $vehicle_mileage
        ];

        wp_send_json_success($kpi_data);
        wp_die();
    }

    public function ajax_get_categories() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to view data.']);
            wp_die();
        }

        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_categories}
            WHERE user_id = %d OR user_id = 0
            ORDER BY name ASC",
            $user_id
        ));

        wp_send_json_success($categories);
        wp_die();
    }

    public function ajax_get_goals() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to view data.']);
            wp_die();
        }

        $goals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_goals}
            WHERE user_id = %d AND status = 'active'
            ORDER BY created_at DESC",
            $user_id
        ));

        wp_send_json_success($goals);
        wp_die();
    }

    public function ajax_get_trash() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to view data.']);
            wp_die();
        }

        // Get trashed transactions
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, c.name as category_name, c.emoji as category_emoji, 'transaction' as item_type
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Trash'
            ORDER BY t.created_at DESC",
            $user_id
        ));

        // Get trashed goals
        $goals = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, target_amount, current_amount, 'goal' as item_type, created_at
            FROM {$this->table_goals}
            WHERE user_id = %d AND status = 'Trash'
            ORDER BY created_at DESC",
            $user_id
        ));

        // Get trashed subscriptions
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, c.name as category_name, c.emoji as category_emoji, 'subscription' as item_type
            FROM {$this->table_subscriptions} s
            LEFT JOIN {$this->table_categories} c ON s.category_id = c.id
            WHERE s.user_id = %d AND s.status = 'Trash'
            ORDER BY s.created_at DESC",
            $user_id
        ));

        wp_send_json_success([
            'transactions' => $transactions,
            'goals' => $goals,
            'subscriptions' => $subscriptions
        ]);
        wp_die();
    }

    // Category Management
    public function ajax_add_category() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in']);
            return;
        }

        $data = [
            'user_id' => $user_id,
            'name' => sanitize_text_field($_POST['name']),
            'type' => sanitize_text_field($_POST['type']),
            'emoji' => sanitize_text_field($_POST['emoji'] ?? '📌')
        ];

        $result = $wpdb->insert($this->table_categories, $data);

        if ($result) {
            wp_send_json_success(['message' => 'Category added successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to add category']);
        }
        wp_die();
    }

    public function ajax_update_category() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'type' => sanitize_text_field($_POST['type']),
            'emoji' => sanitize_text_field($_POST['emoji'] ?? '📌')
        ];

        $result = $wpdb->update(
            $this->table_categories,
            $data,
            ['id' => $id, 'user_id' => $user_id]
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Category updated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to update category']);
        }
        wp_die();
    }

    public function ajax_delete_category() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        // Check if category has transactions from ANY user
        $has_transactions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_transactions}
            WHERE category_id = %d",
            $id
        ));

        if ($has_transactions > 0) {
            wp_send_json_error(['message' => 'Cannot delete category with existing transactions. Found ' . $has_transactions . ' transaction(s) using this category.']);
            return;
        }

        $result = $wpdb->delete(
            $this->table_categories,
            ['id' => $id]
        );

        if ($result) {
            wp_send_json_success(['message' => 'Category deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete category']);
        }
        wp_die();
    }

    // Goal Management
    public function ajax_add_goal() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in']);
            return;
        }

        $data = [
            'user_id' => $user_id,
            'name' => sanitize_text_field($_POST['name']),
            'target_amount' => floatval($_POST['target_amount']),
            'current_amount' => 0,
            'deadline' => sanitize_text_field($_POST['deadline'] ?? null),
            'category' => sanitize_text_field($_POST['category'] ?? null),
            'priority' => sanitize_text_field($_POST['priority'] ?? null),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? null),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'status' => 'active'
        ];

        $result = $wpdb->insert($this->table_goals, $data);

        if ($result) {
            wp_send_json_success(['message' => 'Goal added successfully']);
        } else {
            // Show the actual database error for debugging
            $error_msg = 'Failed to add goal';
            if (!empty($wpdb->last_error)) {
                $error_msg .= ': ' . $wpdb->last_error;
            }
            wp_send_json_error(['message' => $error_msg]);
        }
        wp_die();
    }

    public function ajax_update_goal() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'target_amount' => floatval($_POST['target_amount']),
            'deadline' => sanitize_text_field($_POST['deadline'] ?? null),
            'category' => sanitize_text_field($_POST['category'] ?? null),
            'priority' => sanitize_text_field($_POST['priority'] ?? null),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? null),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        ];

        $result = $wpdb->update(
            $this->table_goals,
            $data,
            ['id' => $id, 'user_id' => $user_id]
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Goal updated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to update goal']);
        }
        wp_die();
    }

    public function ajax_delete_goal() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        $result = $wpdb->update(
            $this->table_goals,
            ['status' => 'Trash'],
            ['id' => $id, 'user_id' => $user_id]
        );

        if ($result) {
            // Move all related transactions to trash
            $wpdb->update(
                $this->table_transactions,
                ['status' => 'Trash'],
                ['goal_id' => $id, 'user_id' => $user_id, 'status' => 'Active']
            );

            wp_send_json_success(['message' => 'Goal moved to trash']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete goal']);
        }
        wp_die();
    }

    public function ajax_restore_goal() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        $result = $wpdb->update(
            $this->table_goals,
            ['status' => 'active'],
            ['id' => $id, 'user_id' => $user_id]
        );

        if ($result) {
            // Restore all related transactions from trash
            $wpdb->update(
                $this->table_transactions,
                ['status' => 'Active'],
                ['goal_id' => $id, 'user_id' => $user_id, 'status' => 'Trash']
            );

            wp_send_json_success(['message' => 'Goal restored']);
        } else {
            wp_send_json_error(['message' => 'Failed to restore goal']);
        }
        wp_die();
    }

    public function ajax_permanent_delete_goal() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        // First, permanently delete all related transactions
        $wpdb->delete(
            $this->table_transactions,
            ['goal_id' => $id, 'user_id' => $user_id]
        );

        // Then delete the goal
        $result = $wpdb->delete(
            $this->table_goals,
            ['id' => $id, 'user_id' => $user_id, 'status' => 'Trash']
        );

        if ($result) {
            wp_send_json_success(['message' => 'Goal permanently deleted']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete goal']);
        }
        wp_die();
    }

    public function ajax_contribute_goal_transaction() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $goal_id = intval($_POST['goal_id']);
        $amount = floatval($_POST['amount']);

        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Contribution amount must be greater than 0']);
            return;
        }

        // Check if "Savings Goal" category exists, create if not
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_categories}
            WHERE name = 'Savings Goal' AND (user_id = %d OR user_id = 0)
            LIMIT 1",
            $user_id
        ));

        if (!$category) {
            $wpdb->insert($this->table_categories, [
                'user_id' => 0,
                'name' => 'Savings Goal',
                'type' => 'expense',
                'emoji' => '🎯'
            ]);
            $category_id = $wpdb->insert_id;
        } else {
            $category_id = $category->id;
        }

        // Get goal name for description
        $goal = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$this->table_goals}
            WHERE id = %d AND user_id = %d",
            $goal_id, $user_id
        ));

        if (!$goal) {
            wp_send_json_error(['message' => 'Goal not found.']);
            wp_die();
        }

        // Create transaction with goal_id link
        $wpdb->insert($this->table_transactions, [
            'user_id' => $user_id,
            'type' => 'expense',
            'amount' => $amount,
            'date' => current_time('Y-m-d'),
            'category_id' => $category_id,
            'payment_method' => 'Bank Transfer',
            'description' => 'Contribution to: ' . $goal->name,
            'goal_id' => $goal_id,
            'status' => 'Active'
        ]);

        // Update goal current_amount
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_goals}
            SET current_amount = current_amount + %f
            WHERE id = %d AND user_id = %d",
            $amount, $goal_id, $user_id
        ));

        wp_send_json_success(['message' => 'Contribution added successfully']);
        wp_die();
    }

    public function ajax_generate_report() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to generate a report.']);
            wp_die();
        }

        $format = sanitize_text_field($_POST['format']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $category = sanitize_text_field($_POST['category'] ?? 'all');
        $type = sanitize_text_field($_POST['type'] ?? 'all');

        // Build dynamic query with filters
        $where_clauses = ["t.user_id = %d", "t.status = 'Active'", "t.date BETWEEN %s AND %s"];
        $params = [$user_id, $start_date, $end_date];

        // Category filter
        if ($category !== 'all' && !empty($category)) {
            $where_clauses[] = "t.category_id = %d";
            $params[] = intval($category);
        }

        // Type filter
        if ($type !== 'all' && !empty($type)) {
            $where_clauses[] = "t.type = %s";
            $params[] = $type;
        }

        $where_sql = implode(' AND ', $where_clauses);

        $query = "SELECT t.*, c.name as category_name, c.emoji as category_emoji
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE {$where_sql}
            ORDER BY t.date DESC";

        $transactions = $wpdb->get_results($wpdb->prepare($query, $params));

        if ($format === 'csv') {
            $this->generate_csv_report($transactions, $start_date, $end_date);
        }

        wp_send_json_success(['message' => 'Report generated']);
        wp_die();
    }

    private function generate_csv_report($transactions, $start_date, $end_date) {
        global $wpdb;
        $user_id = get_current_user_id();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rizqtrack_report_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        fputcsv($output, ['RizqTrack Financial Report']);
        fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
        fputcsv($output, []);

        // Transactions section
        fputcsv($output, ['TRANSACTIONS']);
        fputcsv($output, ['Date', 'Type', 'Category', 'Amount', 'Payment Method', 'Description', 'Odometer (km)', 'Fuel (L)']);

        foreach ($transactions as $t) {
            fputcsv($output, [
                $t->date,
                ucfirst($t->type),
                $t->category_name,
                number_format($t->amount, 2),
                $t->payment_method,
                $t->description,
                $t->odometer_reading ? number_format($t->odometer_reading, 2) : '',
                $t->fuel_liters ? number_format($t->fuel_liters, 2) : ''
            ]);
        }

        // Subscriptions section
        fputcsv($output, []);
        fputcsv($output, []);
        fputcsv($output, ['ACTIVE SUBSCRIPTIONS']);
        fputcsv($output, ['Name', 'Amount', 'Billing Cycle', 'Category', 'Payment Method', 'Next Billing Date', 'Auto-Renew', 'Start Date', 'Notes']);

        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, c.name as category_name
            FROM {$this->table_subscriptions} s
            LEFT JOIN {$this->table_categories} c ON s.category_id = c.id
            WHERE s.user_id = %d AND s.status = 'Active'
            ORDER BY s.next_billing_date ASC",
            $user_id
        ));

        foreach ($subscriptions as $s) {
            fputcsv($output, [
                $s->name,
                number_format($s->amount, 2),
                ucfirst($s->billing_cycle) . ($s->billing_cycle === 'custom' && $s->custom_cycle_days ? ' (' . $s->custom_cycle_days . ' days)' : ''),
                $s->category_name,
                $s->payment_method,
                $s->next_billing_date,
                $s->auto_renew ? 'Yes' : 'No',
                $s->start_date,
                $s->notes
            ]);
        }

        fclose($output);
        exit;
    }

    public function ajax_get_email_settings() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in.']);
            wp_die();
        }

        $settings = [
            'email' => get_user_meta($user_id, 'rizqtrack_email_address', true) ?: wp_get_current_user()->user_email,
            'auto_send' => (int) get_user_meta($user_id, 'rizqtrack_auto_send', true),
            'frequency' => get_user_meta($user_id, 'rizqtrack_email_frequency', true) ?: 'monthly',
            'weekly_enabled' => (int) get_user_meta($user_id, 'rizqtrack_weekly_enabled', true),
            'monthly_enabled' => (int) get_user_meta($user_id, 'rizqtrack_monthly_enabled', true),
            'send_day' => (int) (get_user_meta($user_id, 'rizqtrack_send_day', true) ?: -1)
        ];

        wp_send_json_success($settings);
        wp_die();
    }

    public function ajax_save_email_settings() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in.']);
            wp_die();
        }

        $email = sanitize_email($_POST['email']);
        $auto_send = isset($_POST['auto_send']) ? intval($_POST['auto_send']) : 0;
        $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'monthly';
        $weekly_enabled = isset($_POST['weekly_enabled']) ? intval($_POST['weekly_enabled']) : 0;
        $monthly_enabled = isset($_POST['monthly_enabled']) ? intval($_POST['monthly_enabled']) : 0;
        $send_day = isset($_POST['send_day']) ? intval($_POST['send_day']) : -1;

        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address']);
            wp_die();
        }

        update_user_meta($user_id, 'rizqtrack_email_address', $email);
        update_user_meta($user_id, 'rizqtrack_auto_send', $auto_send);
        update_user_meta($user_id, 'rizqtrack_email_frequency', $frequency);
        update_user_meta($user_id, 'rizqtrack_weekly_enabled', $weekly_enabled);
        update_user_meta($user_id, 'rizqtrack_monthly_enabled', $monthly_enabled);
        update_user_meta($user_id, 'rizqtrack_send_day', $send_day);

        // Schedule/unschedule cron jobs based on auto_send and individual frequencies
        if ($auto_send == 1) {
            // Handle weekly scheduling
            if ($weekly_enabled) {
                $this->update_user_cron($user_id, 'weekly');
            } else {
                wp_clear_scheduled_hook('rizqtrack_send_weekly_email', [$user_id]);
            }

            // Handle monthly scheduling
            if ($monthly_enabled) {
                if (!wp_next_scheduled('rizqtrack_send_monthly_email', [$user_id])) {
                    wp_schedule_event(time(), 'daily', 'rizqtrack_send_monthly_email', [$user_id]);
                }
            } else {
                wp_clear_scheduled_hook('rizqtrack_send_monthly_email', [$user_id]);
            }
        } else {
            // Unschedule both if auto-send is disabled
            wp_clear_scheduled_hook('rizqtrack_send_weekly_email', [$user_id]);
            wp_clear_scheduled_hook('rizqtrack_send_monthly_email', [$user_id]);
        }

        wp_send_json_success(['message' => 'Email settings saved successfully!']);
        wp_die();
    }

    public function ajax_test_email() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in.']);
            wp_die();
        }

        $email = sanitize_email($_POST['email']);

        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address']);
            wp_die();
        }

        $subject = 'RizqTrack Test Email - ' . date('F j, Y g:i A');
        $message = '
        <html>
        <head>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 12px; }
                .content { background: #ffffff; padding: 30px; margin-top: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .footer { text-align: center; color: #6b7280; padding: 20px; font-size: 14px; }
                .success-icon { font-size: 48px; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="success-icon">✅</div>
                    <h1 style="margin: 0; font-size: 24px;">Test Email Successful!</h1>
                </div>
                <div class="content">
                    <h2 style="color: #1f2937;">Hello from RizqTrack! 👋</h2>
                    <p style="color: #4b5563; line-height: 1.6;">
                        This is a test email to confirm that your email settings are working correctly.
                        If you\'re reading this, everything is configured properly!
                    </p>
                    <p style="color: #4b5563; line-height: 1.6;">
                        You\'ll receive financial reports at this email address based on your selected frequency:
                    </p>
                    <ul style="color: #4b5563; line-height: 1.8;">
                        <li><strong>Weekly:</strong> Every Monday at 9:00 AM</li>
                        <li><strong>Monthly:</strong> First day of each month at 9:00 AM</li>
                    </ul>
                    <p style="color: #4b5563; line-height: 1.6;">
                        Your reports will include:
                    </p>
                    <ul style="color: #4b5563; line-height: 1.8;">
                        <li>📊 Income vs Expense summary</li>
                        <li>💰 Top spending categories</li>
                        <li>🎯 Financial goals progress</li>
                        <li>📈 Transaction highlights</li>
                    </ul>
                </div>
                <div class="footer">
                    <p>Sent by RizqTrack - Your Personal Finance Tracker</p>
                    <p style="font-size: 12px;">Generated on ' . date('F j, Y \a\t g:i A') . '</p>
                </div>
            </div>
        </body>
        </html>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail($email, $subject, $message, $headers);

        if ($sent) {
            wp_send_json_success(['message' => '✅ Test email sent successfully! Check your inbox.']);
        } else {
            wp_send_json_error(['message' => '❌ Failed to send email. Please check your email settings.']);
        }
        wp_die();
    }

    public function ajax_send_email_now() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in.']);
            wp_die();
        }

        $email = sanitize_email($_POST['email']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);

        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address']);
            wp_die();
        }

        if (!$start_date || !$end_date) {
            wp_send_json_error(['message' => 'Please select both start and end dates']);
            wp_die();
        }

        // Temporarily save email to user meta so send_email_report can use it
        $original_email = get_user_meta($user_id, 'rizqtrack_email_address', true);
        update_user_meta($user_id, 'rizqtrack_email_address', $email);

        // Send the report with custom date range
        $this->send_email_report($user_id, null, $start_date, $end_date);

        // Restore original email if different
        if ($original_email && $original_email !== $email) {
            update_user_meta($user_id, 'rizqtrack_email_address', $original_email);
        }

        wp_send_json_success(['message' => '📨 Financial report sent successfully! Check your inbox.']);
        wp_die();
    }

    private function update_user_cron($user_id, $frequency) {
        // Remove existing schedules
        wp_clear_scheduled_hook('rizqtrack_send_weekly_email', [$user_id]);
        wp_clear_scheduled_hook('rizqtrack_send_monthly_email', [$user_id]);

        // Schedule new ones
        if ($frequency === 'weekly') {
            $next_monday = strtotime('next Monday 9:00');
            wp_schedule_event($next_monday, 'weekly', 'rizqtrack_send_weekly_email', [$user_id]);
        } elseif ($frequency === 'monthly') {
            $next_month = strtotime('first day of next month 9:00');
            wp_schedule_event($next_month, 'monthly', 'rizqtrack_send_monthly_email', [$user_id]);
        }
    }

    public function send_weekly_report($user_id) {
        $this->send_email_report($user_id, 'weekly');
    }

    public function send_monthly_report($user_id) {
        // Check if user has auto-send enabled
        $auto_send = get_user_meta($user_id, 'rizqtrack_auto_send', true);
        if ($auto_send != 1) {
            return; // Auto-send is disabled
        }

        // Get the user's preferred send day
        $send_day = get_user_meta($user_id, 'rizqtrack_send_day', true) ?: -1;
        $today = date('j'); // Current day of month
        $last_day = date('t'); // Last day of current month

        // Check if today matches the send day
        if ($send_day == -1) {
            // Send on last day of month
            if ($today != $last_day) {
                return; // Not the last day yet
            }
        } else {
            // Send on specific day
            if ($today != $send_day) {
                return; // Not the right day
            }
        }

        // Send the report for the current month (1st to today)
        $start_date = date('Y-m-01'); // First day of current month
        $end_date = date('Y-m-d'); // Today

        $this->send_email_report($user_id, 'monthly', $start_date, $end_date);
    }

    private function send_email_report($user_id, $period = null, $start_date = null, $end_date = null) {
        global $wpdb;

        $email = get_user_meta($user_id, 'rizqtrack_email_address', true);
        if (!$email) return;

        // If period is provided (for scheduled emails), calculate dates
        if ($period && !$start_date && !$end_date) {
            $days = ($period === 'weekly') ? 7 : 30;
            $end_date = date('Y-m-d');
            $start_date = date('Y-m-d', strtotime("-{$days} days"));
            $period_label = ucfirst($period);
        } else {
            // Custom date range from user
            $period_label = date('M j', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
        }

        // Get transaction summary
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
                COUNT(*) as transaction_count
            FROM {$this->table_transactions}
            WHERE user_id = %d AND status = 'Active'
            AND date >= %s AND date <= %s",
            $user_id, $start_date, $end_date
        ));

        // Get top spending categories
        $top_categories = $wpdb->get_results($wpdb->prepare(
            "SELECT c.name, c.emoji, SUM(t.amount) as total
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active' AND t.type = 'expense'
            AND t.date >= %s AND t.date <= %s
            GROUP BY c.id
            ORDER BY total DESC
            LIMIT 5",
            $user_id, $start_date, $end_date
        ));

        // Get active goals
        $goals = $wpdb->get_results($wpdb->prepare(
            "SELECT name, target_amount, current_amount, deadline, category, priority
            FROM {$this->table_goals}
            WHERE user_id = %d AND status = 'active'
            ORDER BY priority DESC, created_at DESC
            LIMIT 5",
            $user_id
        ));

        // Get expiring subscriptions (within 7 days)
        $expiring_subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, c.name as category_name, c.emoji as category_emoji
            FROM {$this->table_subscriptions} s
            LEFT JOIN {$this->table_categories} c ON s.category_id = c.id
            WHERE s.user_id = %d AND s.status = 'Active'
            AND s.end_date IS NOT NULL
            AND DATEDIFF(s.end_date, CURDATE()) BETWEEN 0 AND 7
            ORDER BY s.end_date ASC",
            $user_id
        ));

        // Get budget status
        $budgets = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.name as category_name, c.emoji as category_emoji,
            COALESCE((SELECT SUM(amount) FROM {$this->table_transactions} t
                WHERE t.category_id = b.category_id AND t.type = 'expense'
                AND t.user_id = %d AND t.status = 'Active'
                AND t.date >= %s AND t.date <= %s), 0) as spent
            FROM {$this->table_budgets} b
            LEFT JOIN {$this->table_categories} c ON b.category_id = c.id
            WHERE b.user_id = %d AND b.period = 'monthly'
            ORDER BY (spent / b.amount) DESC",
            $user_id, $start_date, $end_date, $user_id
        ));

        // Calculate KPIs
        $kpis = [
            'savings_rate' => $summary->total_income > 0 ? round((($summary->total_income - $summary->total_expense) / $summary->total_income) * 100, 1) : 0,
            'transaction_count' => $summary->transaction_count,
            'avg_transaction' => $summary->transaction_count > 0 ? round($summary->total_expense / $summary->transaction_count, 2) : 0,
            'top_category' => !empty($top_categories) ? $top_categories[0]->name : 'N/A'
        ];

        $subject = sprintf('RizqTrack Report - %s', $period_label);
        $message = $this->generate_email_html($summary, $top_categories, $goals, $expiring_subscriptions, $budgets, $kpis, $period_label);

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($email, $subject, $message, $headers);
    }

    private function generate_email_html($summary, $top_categories, $goals, $expiring_subscriptions, $budgets, $kpis, $period) {
        $income = number_format($summary->total_income, 2);
        $expense = number_format($summary->total_expense, 2);
        $savings = number_format($summary->total_income - $summary->total_expense, 2);
        $savings_color = ($summary->total_income - $summary->total_expense >= 0) ? '#10b981' : '#ef4444';

        $categories_html = '';
        foreach ($top_categories as $cat) {
            $categories_html .= sprintf(
                '<tr><td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">%s %s</td><td style="padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right;">₹%s</td></tr>',
                $cat->emoji,
                $cat->name,
                number_format($cat->total, 2)
            );
        }

        // Generate KPIs HTML
        $kpis_html = sprintf('
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 30px;">
                <div style="padding: 15px; background: #f0f9ff; border-radius: 8px; text-align: center;">
                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Savings Rate</div>
                    <div style="font-size: 22px; font-weight: bold; color: #0891b2;">%s%%</div>
                </div>
                <div style="padding: 15px; background: #f0fdf4; border-radius: 8px; text-align: center;">
                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Transactions</div>
                    <div style="font-size: 22px; font-weight: bold; color: #10b981;">%d</div>
                </div>
                <div style="padding: 15px; background: #fef3c7; border-radius: 8px; text-align: center;">
                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Avg/Transaction</div>
                    <div style="font-size: 22px; font-weight: bold; color: #f59e0b;">₹%s</div>
                </div>
                <div style="padding: 15px; background: #f3f4f6; border-radius: 8px; text-align: center;">
                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Top Category</div>
                    <div style="font-size: 16px; font-weight: bold; color: #1f2937;">%s</div>
                </div>
            </div>
        ',
            $kpis['savings_rate'],
            $kpis['transaction_count'],
            number_format($kpis['avg_transaction'], 2),
            $kpis['top_category']
        );

        // Generate subscription alerts HTML
        $subscriptions_html = '';
        if (!empty($expiring_subscriptions)) {
            foreach ($expiring_subscriptions as $sub) {
                $days_until = max(0, floor((strtotime($sub->end_date) - time()) / (60 * 60 * 24)));
                $urgency_color = $days_until <= 3 ? '#ef4444' : '#f59e0b';
                $urgency_bg = $days_until <= 3 ? '#fef2f2' : '#fef3c7';

                $subscriptions_html .= sprintf('
                    <div style="margin-bottom: 12px; padding: 12px; background: %s; border-left: 4px solid %s; border-radius: 6px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="color: #1f2937; font-size: 14px;">%s %s</strong>
                                <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                    Expires: %s (%d days)
                                </div>
                            </div>
                            <div style="background: white; padding: 6px 12px; border-radius: 6px; font-weight: bold; color: %s; font-size: 13px;">
                                %d days
                            </div>
                        </div>
                    </div>
                ',
                    $urgency_bg,
                    $urgency_color,
                    $sub->category_emoji,
                    $sub->name,
                    date('M d, Y', strtotime($sub->end_date)),
                    $days_until,
                    $urgency_color,
                    $days_until
                );
            }
        } else {
            $subscriptions_html = '<p style="color: #6b7280; font-size: 14px; text-align: center; padding: 20px;">No subscriptions expiring soon. You\'re all set! ✅</p>';
        }

        // Generate budget status HTML
        $budgets_html = '';
        if (!empty($budgets)) {
            foreach ($budgets as $budget) {
                $spent_amount = floatval($budget->spent);
                $budget_amount = floatval($budget->amount);
                $percentage = $budget_amount > 0 ? ($spent_amount / $budget_amount * 100) : 0;
                $percentage_rounded = round($percentage, 1);
                $progress_width = min($percentage, 100);

                // Determine color based on spending
                $status_color = '#10b981'; // Green - under budget
                $status_bg = '#ecfdf5';
                $status_text = 'On Track';

                if ($percentage >= 100) {
                    $status_color = '#ef4444'; // Red - over budget
                    $status_bg = '#fef2f2';
                    $status_text = 'Over Budget!';
                } elseif ($percentage >= 80) {
                    $status_color = '#f59e0b'; // Orange - warning
                    $status_bg = '#fef3c7';
                    $status_text = 'Warning';
                }

                $budgets_html .= sprintf('
                    <div style="margin-bottom: 16px; padding: 12px; background: #f9fafb; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong style="color: #1f2937; font-size: 14px;">%s %s</strong>
                            <span style="background: %s; color: %s; padding: 3px 8px; border-radius: 8px; font-size: 10px; font-weight: 700;">%s</span>
                        </div>
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 8px;">
                            ₹%s / ₹%s (%s%%)
                        </div>
                        <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="background: %s; height: 100%%; width: %s%%;"></div>
                        </div>
                    </div>
                ',
                    $budget->category_emoji,
                    $budget->category_name,
                    $status_bg,
                    $status_color,
                    $status_text,
                    number_format($spent_amount, 2),
                    number_format($budget_amount, 2),
                    $percentage_rounded,
                    $status_color,
                    $progress_width
                );
            }
        } else {
            $budgets_html = '<p style="color: #6b7280; font-size: 14px; text-align: center; padding: 20px;">No budgets set. Create budgets to track your spending!</p>';
        }

        // Generate goals HTML
        $goals_html = '';
        if (!empty($goals)) {
            foreach ($goals as $goal) {
                $progress = ($goal->target_amount > 0) ? ($goal->current_amount / $goal->target_amount * 100) : 0;
                $progress_rounded = round($progress, 1);
                $progress_width = min($progress, 100);

                $priority_badge = '';
                if ($goal->priority === 'high') {
                    $priority_badge = '<span style="background: #fef2f2; color: #ef4444; padding: 3px 8px; border-radius: 8px; font-size: 10px; font-weight: 700;">🔴 HIGH</span>';
                } elseif ($goal->priority === 'medium') {
                    $priority_badge = '<span style="background: #fef3c7; color: #f59e0b; padding: 3px 8px; border-radius: 8px; font-size: 10px; font-weight: 700;">🟡 MEDIUM</span>';
                } elseif ($goal->priority === 'low') {
                    $priority_badge = '<span style="background: #ecfdf5; color: #10b981; padding: 3px 8px; border-radius: 8px; font-size: 10px; font-weight: 700;">🟢 LOW</span>';
                }

                $goals_html .= sprintf('
                    <div style="margin-bottom: 16px; padding: 12px; background: #f9fafb; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong style="color: #1f2937; font-size: 14px;">%s</strong>
                            %s
                        </div>
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 8px;">
                            ₹%s / ₹%s (%s%%)
                        </div>
                        <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="background: #0891b2; height: 100%%; width: %s%%;"></div>
                        </div>
                    </div>
                ',
                    $goal->name,
                    $priority_badge,
                    number_format($goal->current_amount, 2),
                    number_format($goal->target_amount, 2),
                    $progress_rounded,
                    $progress_width
                );
            }
        } else {
            $goals_html = '<p style="color: #6b7280; font-size: 14px; text-align: center; padding: 20px;">No active goals. Set goals to track your savings progress!</p>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f9fafb; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); color: white; padding: 30px; text-align: center;">
            <h1 style="margin: 0; font-size: 28px;">💰 RizqTrack</h1>
            <p style="margin: 10px 0 0 0; font-size: 16px;">Your {$period} Financial Report</p>
        </div>

        <div style="padding: 30px;">
            <h2 style="color: #1f2937; margin-top: 0;">Summary</h2>

            <table style="width: 100%; margin-bottom: 30px;">
                <tr>
                    <td style="padding: 15px; background: #ecfdf5; border-radius: 8px; margin-bottom: 10px;">
                        <div style="font-size: 14px; color: #6b7280;">Total Income</div>
                        <div style="font-size: 24px; font-weight: bold; color: #10b981;">₹{$income}</div>
                    </td>
                </tr>
                <tr><td style="height: 10px;"></td></tr>
                <tr>
                    <td style="padding: 15px; background: #fef2f2; border-radius: 8px;">
                        <div style="font-size: 14px; color: #6b7280;">Total Expense</div>
                        <div style="font-size: 24px; font-weight: bold; color: #ef4444;">₹{$expense}</div>
                    </td>
                </tr>
                <tr><td style="height: 10px;"></td></tr>
                <tr>
                    <td style="padding: 15px; background: #f0f9ff; border-radius: 8px;">
                        <div style="font-size: 14px; color: #6b7280;">Net Savings</div>
                        <div style="font-size: 24px; font-weight: bold; color: {$savings_color};">₹{$savings}</div>
                    </td>
                </tr>
            </table>

            <h2 style="color: #1f2937;">📊 Key Performance Indicators</h2>
            {$kpis_html}

            <h2 style="color: #1f2937;">⚠️ Subscription Alerts</h2>
            <div style="margin-bottom: 30px;">
                {$subscriptions_html}
            </div>

            <h2 style="color: #1f2937;">💳 Budget Status</h2>
            <div style="margin-bottom: 30px;">
                {$budgets_html}
            </div>

            <h2 style="color: #1f2937;">Top Spending Categories</h2>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                {$categories_html}
            </table>

            <h2 style="color: #1f2937;">🎯 Your Goals Progress</h2>
            <div style="margin-bottom: 30px;">
                {$goals_html}
            </div>

            <div style="background: #ecfeff; border-left: 4px solid #0891b2; padding: 15px; border-radius: 5px;">
                <p style="margin: 0; font-size: 14px; color: #1f2937;">
                    <strong>💡 Tip:</strong> Track your expenses daily to stay on top of your finances!
                </p>
            </div>
        </div>

        <div style="background: #f9fafb; padding: 20px; text-align: center; color: #6b7280; font-size: 12px;">
            <p style="margin: 0;">This is an automated report from RizqTrack</p>
            <p style="margin: 5px 0 0 0;">© 2024 RizqTrack. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    public function render_dashboard() {
        include plugin_dir_path(__FILE__) . 'templates/dashboard.php';
    }

    public function render_cron_logs_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        global $wpdb;

        // Handle API key save
        if (isset($_POST['save_cronjob_api_key']) && check_admin_referer('rizqtrack_save_cronjob_key')) {
            $api_key = trim(sanitize_text_field($_POST['cronjob_api_key']));
            update_option('rizqtrack_cronjob_api_key', $api_key);
            echo '<div class="notice notice-success is-dismissible"><p>Cron-job.org API key saved successfully!</p></div>';
        }

        // Get cron-job.org API key
        $cronjob_api_key = get_option('rizqtrack_cronjob_api_key', '');

        // Get filter
        $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';

        // Build query
        $where = '';
        if ($filter_type === 'weekly' || $filter_type === 'monthly') {
            $where = $wpdb->prepare(" WHERE job_type = %s", $filter_type);
        }

        // Get logs
        $logs = $wpdb->get_results(
            "SELECT * FROM {$this->table_cron_logs}
             {$where}
             ORDER BY execution_time DESC
             LIMIT 100"
        );

        // Get statistics
        $stats = $wpdb->get_results(
            "SELECT
                job_type,
                COUNT(*) as total_runs,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_runs,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_runs,
                AVG(duration_ms) as avg_duration_ms,
                SUM(users_processed) as total_users_processed,
                SUM(emails_sent) as total_emails_sent
             FROM {$this->table_cron_logs}
             GROUP BY job_type"
        );

        ?>
        <div class="wrap">
            <h1>RizqTrack - Cron Job Logs</h1>

            <div class="notice notice-info" style="margin: 20px 0; padding: 15px;">
                <h2>Cron-job.org Configuration</h2>

                <form method="post" action="" style="margin: 15px 0;">
                    <?php wp_nonce_field('rizqtrack_save_cronjob_key'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cronjob_api_key">Cron-job.org API Key</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="cronjob_api_key"
                                       name="cronjob_api_key"
                                       value="<?php echo esc_attr($cronjob_api_key); ?>"
                                       class="regular-text"
                                       placeholder="Enter your cron-job.org API key" />
                                <p class="description">
                                    Get your API key from <a href="https://console.cron-job.org/settings" target="_blank">cron-job.org settings</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="save_cronjob_api_key" class="button button-primary" value="Save API Key" />
                    </p>
                </form>

                <?php if (!empty($cronjob_api_key)): ?>
                    <hr style="margin: 20px 0;">
                    <h3>Your Cron Endpoint URLs</h3>
                    <p><strong>Weekly Cron:</strong></p>
                    <input type="text" readonly value="<?php echo esc_attr(site_url('/wp-json/rizqtrack/v1/cron/weekly?key=' . urlencode($cronjob_api_key))); ?>" class="large-text" onclick="this.select();" style="font-family: monospace; margin-bottom: 10px;" />

                    <p style="margin-top: 15px;"><strong>Monthly Cron:</strong></p>
                    <input type="text" readonly value="<?php echo esc_attr(site_url('/wp-json/rizqtrack/v1/cron/monthly?key=' . urlencode($cronjob_api_key))); ?>" class="large-text" onclick="this.select();" style="font-family: monospace; margin-bottom: 10px;" />

                    <p style="margin-top: 15px;">
                        <strong>How to set up on cron-job.org:</strong><br>
                        1. Go to <a href="https://console.cron-job.org/jobs" target="_blank">cron-job.org</a><br>
                        2. Create a new cron job<br>
                        3. Copy the URL above and paste it as the endpoint<br>
                        4. Set your desired schedule (e.g., weekly on Monday, monthly on 1st day)<br>
                        5. Save and enable the job
                    </p>
                <?php else: ?>
                    <p style="color: #d63638; margin-top: 15px;">
                        <strong>⚠️ Please enter your cron-job.org API key above to generate your endpoint URLs.</strong>
                    </p>
                <?php endif; ?>
            </div>

            <?php if (!empty($stats)): ?>
            <h2>Statistics</h2>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th>Job Type</th>
                        <th>Total Runs</th>
                        <th>Successful</th>
                        <th>Failed</th>
                        <th>Avg Duration (ms)</th>
                        <th>Total Users Processed</th>
                        <th>Total Emails Sent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $stat): ?>
                    <tr>
                        <td><strong><?php echo esc_html(ucfirst($stat->job_type)); ?></strong></td>
                        <td><?php echo esc_html($stat->total_runs); ?></td>
                        <td style="color: green;"><?php echo esc_html($stat->successful_runs); ?></td>
                        <td style="color: red;"><?php echo esc_html($stat->failed_runs); ?></td>
                        <td><?php echo esc_html(round($stat->avg_duration_ms, 2)); ?></td>
                        <td><?php echo esc_html($stat->total_users_processed); ?></td>
                        <td><?php echo esc_html($stat->total_emails_sent); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <h2>Recent Executions</h2>

            <div style="margin-bottom: 15px;">
                <a href="?page=rizqtrack-cron-logs&filter_type=all" class="button <?php echo $filter_type === 'all' ? 'button-primary' : ''; ?>">All</a>
                <a href="?page=rizqtrack-cron-logs&filter_type=weekly" class="button <?php echo $filter_type === 'weekly' ? 'button-primary' : ''; ?>">Weekly</a>
                <a href="?page=rizqtrack-cron-logs&filter_type=monthly" class="button <?php echo $filter_type === 'monthly' ? 'button-primary' : ''; ?>">Monthly</a>
            </div>

            <?php if (empty($logs)): ?>
                <p>No cron executions logged yet.</p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 100px;">Job Type</th>
                        <th style="width: 80px;">Status</th>
                        <th style="width: 150px;">Execution Time</th>
                        <th style="width: 100px;">Duration (ms)</th>
                        <th style="width: 80px;">Users</th>
                        <th style="width: 80px;">Emails</th>
                        <th style="width: 80px;">Errors</th>
                        <th>Error Message</th>
                        <th style="width: 120px;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td><strong><?php echo esc_html(ucfirst($log->job_type)); ?></strong></td>
                        <td>
                            <span style="color: <?php echo $log->status === 'success' ? 'green' : 'red'; ?>;">
                                <?php echo esc_html(ucfirst($log->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log->execution_time); ?></td>
                        <td><?php echo esc_html($log->duration_ms); ?></td>
                        <td><?php echo esc_html($log->users_processed); ?></td>
                        <td><?php echo esc_html($log->emails_sent); ?></td>
                        <td><?php echo esc_html($log->errors_count); ?></td>
                        <td><?php echo esc_html($log->error_message ?: '-'); ?></td>
                        <td><?php echo esc_html($log->request_ip ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if (!empty($cronjob_api_key)): ?>
            <div style="margin-top: 20px;">
                <h2>Test Endpoints</h2>
                <p>Click the buttons below to manually trigger the cron jobs for testing:</p>
                <button class="button" onclick="testCron('weekly')">Test Weekly Cron</button>
                <button class="button" onclick="testCron('monthly')">Test Monthly Cron</button>
                <div id="test-result" style="margin-top: 15px;"></div>
            </div>

            <script>
            function testCron(type) {
                const resultDiv = document.getElementById('test-result');
                resultDiv.innerHTML = '<p>Running ' + type + ' cron job...</p>';

                const apiKey = encodeURIComponent('<?php echo esc_js($cronjob_api_key); ?>');
                fetch('<?php echo site_url('/wp-json/rizqtrack/v1/cron/'); ?>' + type + '?key=' + apiKey)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            resultDiv.innerHTML = '<div class="notice notice-success"><p><strong>Success!</strong><br>' +
                                'Users processed: ' + data.stats.users_processed + '<br>' +
                                'Emails sent: ' + data.stats.emails_sent + '<br>' +
                                'Errors: ' + data.stats.errors + '<br>' +
                                'Duration: ' + data.stats.duration_ms + 'ms</p></div>';
                        } else {
                            resultDiv.innerHTML = '<div class="notice notice-error"><p><strong>Error!</strong><br>' +
                                data.message + '</p></div>';
                        }
                        setTimeout(() => location.reload(), 3000);
                    })
                    .catch(error => {
                        resultDiv.innerHTML = '<div class="notice notice-error"><p><strong>Error!</strong><br>' +
                            error.message + '</p></div>';
                    });
            }
            </script>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_admin_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        global $wpdb;

        // Get overview statistics (privacy-focused)
        $total_users = count(get_users());
        $active_users_30d = $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id)
            FROM {$this->table_transactions}
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $active_users_7d = $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id)
            FROM {$this->table_transactions}
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");

        // New users this week
        $new_users_7d = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->users}
            WHERE user_registered >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");

        $total_transactions = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_transactions} WHERE status = 'Active'");

        // Transactions growth (last 7 days vs previous 7 days)
        $transactions_7d = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$this->table_transactions}
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND status = 'Active'
        ");
        $transactions_prev_7d = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$this->table_transactions}
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            AND date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND status = 'Active'
        ");
        $transaction_growth = 0;
        if ($transactions_prev_7d > 0) {
            $transaction_growth = round((($transactions_7d - $transactions_prev_7d) / $transactions_prev_7d) * 100, 1);
        }

        // Feature adoption metrics
        $users_with_goals = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$this->table_goals} WHERE status = 'active'");
        $users_with_budgets = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$this->table_budgets} WHERE status = 'active'");
        $users_with_subscriptions = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$this->table_subscriptions} WHERE status = 'Active'");
        $users_with_email = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'rizqtrack_auto_send' AND meta_value = '1'");

        // Total feature counts
        $total_goals = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_goals} WHERE status = 'active'");
        $total_budgets = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_budgets} WHERE status = 'active'");
        $total_subscriptions = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_subscriptions} WHERE status = 'Active'");
        $total_categories = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories}");

        // Engagement metrics
        $avg_transactions_per_user = $wpdb->get_var("
            SELECT ROUND(AVG(transaction_count), 1)
            FROM (
                SELECT COUNT(*) as transaction_count
                FROM {$this->table_transactions}
                WHERE status = 'Active'
                GROUP BY user_id
            ) as user_counts
        ");

        // Get all users with their statistics (NO financial amounts)
        $users_stats = $wpdb->get_results("
            SELECT
                u.ID,
                u.user_login,
                u.user_email,
                u.user_registered,
                COUNT(DISTINCT t.id) as transaction_count,
                MAX(t.date) as last_transaction_date,
                (SELECT COUNT(*) FROM {$this->table_goals} WHERE user_id = u.ID AND status = 'active') as active_goals,
                (SELECT COUNT(*) FROM {$this->table_budgets} WHERE user_id = u.ID AND status = 'active') as active_budgets,
                (SELECT COUNT(*) FROM {$this->table_subscriptions} WHERE user_id = u.ID AND status = 'Active') as active_subscriptions,
                (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'rizqtrack_auto_send') as auto_send,
                (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'rizqtrack_email_frequency') as email_frequency
            FROM {$wpdb->users} u
            LEFT JOIN {$this->table_transactions} t ON u.ID = t.user_id AND t.status = 'Active'
            GROUP BY u.ID
            ORDER BY transaction_count DESC
        ");

        // User retention (users who were active in both previous and current week)
        $retention_rate = $wpdb->get_var("
            SELECT ROUND(
                COUNT(DISTINCT CASE
                    WHEN t1.user_id IS NOT NULL AND t2.user_id IS NOT NULL
                    THEN t1.user_id
                END) * 100.0 / NULLIF(COUNT(DISTINCT t1.user_id), 0),
            1)
            FROM (
                SELECT DISTINCT user_id
                FROM {$this->table_transactions}
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                AND date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ) t1
            LEFT JOIN (
                SELECT DISTINCT user_id
                FROM {$this->table_transactions}
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ) t2 ON t1.user_id = t2.user_id
        ");

        ?>
        <div class="wrap" style="max-width: 1400px;">
            <h1>👤 Admin Dashboard</h1>
            <p style="color: #666; margin-bottom: 30px;">Overview of all RizqTrack users and system statistics</p>

            <!-- Overview Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <!-- Total Users Card -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(102,126,234,0.3); transition: transform 0.2s;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div style="font-size: 14px; opacity: 0.9; font-weight: 500;">👥 Total Users</div>
                        <?php if ($new_users_7d > 0): ?>
                            <span style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 11px;">+<?php echo $new_users_7d; ?> this week</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;"><?php echo number_format($total_users); ?></div>
                    <div style="font-size: 13px; opacity: 0.85;">
                        <strong><?php echo $active_users_7d; ?></strong> active (7d) •
                        <strong><?php echo $active_users_30d; ?></strong> active (30d)
                    </div>
                </div>

                <!-- Total Transactions Card -->
                <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(240,147,251,0.3); transition: transform 0.2s;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div style="font-size: 14px; opacity: 0.9; font-weight: 500;">📊 Transactions</div>
                        <?php if ($transaction_growth != 0): ?>
                            <span style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 11px;">
                                <?php echo $transaction_growth > 0 ? '↗' : '↘'; ?> <?php echo abs($transaction_growth); ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;"><?php echo number_format($total_transactions); ?></div>
                    <div style="font-size: 13px; opacity: 0.85;">
                        <strong><?php echo number_format($transactions_7d); ?></strong> this week •
                        <strong><?php echo $total_categories; ?></strong> categories
                    </div>
                </div>

                <!-- Average Engagement Card -->
                <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(79,172,254,0.3); transition: transform 0.2s;">
                    <div style="font-size: 14px; opacity: 0.9; font-weight: 500; margin-bottom: 12px;">📈 Avg Engagement</div>
                    <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;"><?php echo $avg_transactions_per_user ?: '0'; ?></div>
                    <div style="font-size: 13px; opacity: 0.85;">
                        Transactions per user •
                        <?php
                        $engagement_rate = $total_users > 0 ? round(($active_users_7d / $total_users) * 100, 1) : 0;
                        echo $engagement_rate;
                        ?>% active (7d)
                    </div>
                </div>

                <!-- Retention Card -->
                <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(67,233,123,0.3); transition: transform 0.2s;">
                    <div style="font-size: 14px; opacity: 0.9; font-weight: 500; margin-bottom: 12px;">🔄 User Retention</div>
                    <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;"><?php echo $retention_rate ?: '0'; ?>%</div>
                    <div style="font-size: 13px; opacity: 0.85;">
                        Week-over-week retention rate
                    </div>
                </div>
            </div>

            <!-- Feature Adoption -->
            <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
                <h2 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: #1e293b;">📊 Feature Adoption</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <!-- Goals Feature -->
                    <div style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; color: white; box-shadow: 0 4px 12px rgba(102,126,234,0.25);">
                        <div style="font-size: 13px; opacity: 0.9; margin-bottom: 10px; font-weight: 500;">🎯 Goals Feature</div>
                        <div style="font-size: 36px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $total_users > 0 ? round(($users_with_goals / $total_users) * 100, 1) : 0; ?>%
                        </div>
                        <div style="font-size: 12px; opacity: 0.85;">
                            <strong><?php echo $users_with_goals; ?></strong> of <strong><?php echo $total_users; ?></strong> users •
                            <strong><?php echo number_format($total_goals); ?></strong> total goals
                        </div>
                    </div>

                    <!-- Budgets Feature -->
                    <div style="padding: 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 10px; color: white; box-shadow: 0 4px 12px rgba(240,147,251,0.25);">
                        <div style="font-size: 13px; opacity: 0.9; margin-bottom: 10px; font-weight: 500;">💰 Budgets Feature</div>
                        <div style="font-size: 36px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $total_users > 0 ? round(($users_with_budgets / $total_users) * 100, 1) : 0; ?>%
                        </div>
                        <div style="font-size: 12px; opacity: 0.85;">
                            <strong><?php echo $users_with_budgets; ?></strong> of <strong><?php echo $total_users; ?></strong> users •
                            <strong><?php echo number_format($total_budgets); ?></strong> total budgets
                        </div>
                    </div>

                    <!-- Subscriptions Feature -->
                    <div style="padding: 20px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 10px; color: white; box-shadow: 0 4px 12px rgba(79,172,254,0.25);">
                        <div style="font-size: 13px; opacity: 0.9; margin-bottom: 10px; font-weight: 500;">🔄 Subscriptions</div>
                        <div style="font-size: 36px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $total_users > 0 ? round(($users_with_subscriptions / $total_users) * 100, 1) : 0; ?>%
                        </div>
                        <div style="font-size: 12px; opacity: 0.85;">
                            <strong><?php echo $users_with_subscriptions; ?></strong> of <strong><?php echo $total_users; ?></strong> users •
                            <strong><?php echo number_format($total_subscriptions); ?></strong> active
                        </div>
                    </div>

                    <!-- Email Reports -->
                    <div style="padding: 20px; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); border-radius: 10px; color: white; box-shadow: 0 4px 12px rgba(67,233,123,0.25);">
                        <div style="font-size: 13px; opacity: 0.9; margin-bottom: 10px; font-weight: 500;">📧 Email Reports</div>
                        <div style="font-size: 36px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $total_users > 0 ? round(($users_with_email / $total_users) * 100, 1) : 0; ?>%
                        </div>
                        <div style="font-size: 12px; opacity: 0.85;">
                            <strong><?php echo $users_with_email; ?></strong> of <strong><?php echo $total_users; ?></strong> users •
                            Auto-send enabled
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Management -->
            <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
                <h2 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: #1e293b;">👥 User Management</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Registered</th>
                            <th>Transactions</th>
                            <th>Goals</th>
                            <th>Budgets</th>
                            <th>Subscriptions</th>
                            <th>Email Reports</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users_stats)): ?>
                            <?php foreach ($users_stats as $user): ?>
                            <tr>
                                <td><?php echo $user->ID; ?></td>
                                <td><strong><?php echo esc_html($user->user_login); ?></strong></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user->user_registered)); ?></td>
                                <td><?php echo number_format($user->transaction_count); ?></td>
                                <td><?php echo $user->active_goals; ?></td>
                                <td><?php echo $user->active_budgets; ?></td>
                                <td><?php echo $user->active_subscriptions; ?></td>
                                <td>
                                    <?php if ($user->auto_send == 1): ?>
                                        <span style="color: #10b981; font-weight: 600;">✓ <?php echo ucfirst($user->email_frequency); ?></span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user->last_transaction_date): ?>
                                        <?php echo date('M d, Y', strtotime($user->last_transaction_date)); ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">Never</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10">No users found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- System Health -->
            <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: #1e293b;">⚙️ System Health</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <?php
                    $last_cron_run = $wpdb->get_row("SELECT * FROM {$this->table_cron_logs} ORDER BY execution_time DESC LIMIT 1");
                    $total_cron_runs = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_cron_logs}");
                    $cron_success_rate = $wpdb->get_var("
                        SELECT ROUND(
                            (SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1
                        )
                        FROM {$this->table_cron_logs}
                    ");
                    $total_cron_success = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_cron_logs} WHERE status = 'success'");
                    $table_size = $wpdb->get_var("
                        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
                        FROM information_schema.TABLES
                        WHERE table_schema = DATABASE()
                        AND table_name LIKE '{$wpdb->prefix}rizqtrack_%'
                    ");
                    ?>

                    <!-- Last Cron Run -->
                    <div style="padding: 20px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); border-radius: 10px; color: white; box-shadow: 0 4px 12px rgba(250,112,154,0.25);">
                        <div style="font-size: 13px; opacity: 0.9; margin-bottom: 10px; font-weight: 500;">⏰ Last Cron Run</div>
                        <div style="font-size: 16px; font-weight: 700; margin-bottom: 6px;">
                            <?php echo $last_cron_run ? date('M d, g:i A', strtotime($last_cron_run->execution_time)) : 'Never'; ?>
                        </div>
                        <div style="font-size: 12px; opacity: 0.85;">
                            <?php if ($last_cron_run): ?>
                                <strong><?php echo ucfirst($last_cron_run->job_type); ?></strong> •
                                <?php echo $last_cron_run->emails_sent; ?> emails sent
                            <?php else: ?>
                                No cron jobs executed yet
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Cron Success Rate -->
                    <div style="padding: 20px; background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); border-radius: 10px; color: white; box-shadow: 0 4px 12px rgba(48,207,208,0.25);">
                        <div style="font-size: 13px; opacity: 0.9; margin-bottom: 10px; font-weight: 500;">✅ Cron Success Rate</div>
                        <div style="font-size: 36px; font-weight: 700; margin-bottom: 6px;">
                            <?php echo $cron_success_rate ?: '0'; ?>%
                        </div>
                        <div style="font-size: 12px; opacity: 0.85;">
                            <strong><?php echo number_format($total_cron_success); ?></strong> of <strong><?php echo number_format($total_cron_runs); ?></strong> jobs successful
                        </div>
                    </div>

                    <!-- Database Size -->
                    <div style="padding: 20px; background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border-radius: 10px; color: #1e293b; box-shadow: 0 4px 12px rgba(168,237,234,0.25);">
                        <div style="font-size: 13px; opacity: 0.8; margin-bottom: 10px; font-weight: 600;">💾 Database Size</div>
                        <div style="font-size: 36px; font-weight: 700; margin-bottom: 6px;">
                            <?php echo $table_size ? $table_size : '0'; ?> MB
                        </div>
                        <div style="font-size: 12px; opacity: 0.7; font-weight: 500;">
                            RizqTrack plugin tables
                        </div>
                    </div>

                    <!-- WordPress Version -->
                    <div style="padding: 20px; background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border-radius: 10px; color: #1e293b; box-shadow: 0 4px 12px rgba(255,236,210,0.25);">
                        <div style="font-size: 13px; opacity: 0.8; margin-bottom: 10px; font-weight: 600;">🔧 WordPress</div>
                        <div style="font-size: 24px; font-weight: 700; margin-bottom: 6px;">
                            v<?php echo get_bloginfo('version'); ?>
                        </div>
                        <div style="font-size: 12px; opacity: 0.7; font-weight: 500;">
                            PHP <?php echo PHP_VERSION; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renders the frontend dashboard or redirects directly to login
     */
    public function render_frontend_dashboard($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            // Get the current URL for redirect after login
            $current_url = home_url(add_query_arg(null, null));
            
            // Define your Ultimate Member login page URL
            $login_page_url = home_url('/login/');
            
            // Append the 'redirect_to' parameter
            $login_with_redirect = add_query_arg('redirect_to', urlencode($current_url), $login_page_url);
            
            // Direct redirect to login page
            wp_redirect($login_with_redirect);
            exit;
        }

        // User is logged in - show the dashboard
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/dashboard.php';
        return ob_get_clean();
    }

    // Achievement System
    public function ajax_get_achievements() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in.']);
            wp_die();
        }

        $achievements = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_achievements}
            WHERE user_id = %d
            ORDER BY earned_date DESC",
            $user_id
        ));

        wp_send_json_success($achievements);
        wp_die();
    }

    public function ajax_check_achievements() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in.']);
            wp_die();
        }

        $new_achievements = [];

        // Achievement definitions
        $achievement_checks = [
            // Transaction milestones
            ['key' => 'first_transaction', 'name' => 'Getting Started', 'description' => 'Added your first transaction', 'icon' => '🎯', 'color' => '#10b981', 'check' =>
                "SELECT COUNT(*) FROM {$this->table_transactions} WHERE user_id = %d AND status = 'Active'", 'threshold' => 1],

            ['key' => '10_transactions', 'name' => 'Building Momentum', 'description' => 'Tracked 10 transactions', 'icon' => '💪', 'color' => '#3b82f6', 'check' =>
                "SELECT COUNT(*) FROM {$this->table_transactions} WHERE user_id = %d AND status = 'Active'", 'threshold' => 10],

            ['key' => '50_transactions', 'name' => 'Committed Tracker', 'description' => 'Tracked 50 transactions', 'icon' => '🏆', 'color' => '#f59e0b', 'check' =>
                "SELECT COUNT(*) FROM {$this->table_transactions} WHERE user_id = %d AND status = 'Active'", 'threshold' => 50],

            ['key' => '100_transactions', 'name' => 'Transaction Master', 'description' => 'Tracked 100 transactions', 'icon' => '👑', 'color' => '#8b5cf6', 'check' =>
                "SELECT COUNT(*) FROM {$this->table_transactions} WHERE user_id = %d AND status = 'Active'", 'threshold' => 100],

            // Goal achievements
            ['key' => 'first_goal', 'name' => 'Dream Planner', 'description' => 'Created your first financial goal', 'icon' => '🎯', 'color' => '#ec4899', 'check' =>
                "SELECT COUNT(*) FROM {$this->table_goals} WHERE user_id = %d", 'threshold' => 1],

            ['key' => 'first_goal_complete', 'name' => 'Goal Achiever', 'description' => 'Completed your first financial goal', 'icon' => '✨', 'color' => '#10b981', 'check' =>
                "SELECT COUNT(*) FROM {$this->table_goals} WHERE user_id = %d AND status = 'completed'", 'threshold' => 1],

            // Savings achievements
            ['key' => 'positive_savings', 'name' => 'Saving Smart', 'description' => 'Maintained positive net savings', 'icon' => '💰', 'color' => '#10b981', 'check' =>
                "SELECT (COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0)) FROM {$this->table_transactions} WHERE user_id = %d AND status = 'Active'", 'threshold' => 1],

            // Category usage
            ['key' => '5_categories', 'name' => 'Category Explorer', 'description' => 'Used 5 different categories', 'icon' => '🏷️', 'color' => '#06b6d4', 'check' =>
                "SELECT COUNT(DISTINCT category_id) FROM {$this->table_transactions} WHERE user_id = %d AND status = 'Active'", 'threshold' => 5],
        ];

        foreach ($achievement_checks as $achievement) {
            // Check if user already has this achievement
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_achievements} WHERE user_id = %d AND achievement_key = %s",
                $user_id, $achievement['key']
            ));

            if ($existing > 0) {
                continue; // Already earned
            }

            // Check if user meets criteria
            $count = $wpdb->get_var($wpdb->prepare($achievement['check'], $user_id));

            if ($count >= $achievement['threshold']) {
                // Award achievement
                $wpdb->insert($this->table_achievements, [
                    'user_id' => $user_id,
                    'achievement_key' => $achievement['key'],
                    'achievement_name' => $achievement['name'],
                    'achievement_description' => $achievement['description'],
                    'badge_icon' => $achievement['icon'],
                    'badge_color' => $achievement['color']
                ]);

                $new_achievements[] = $achievement;
            }
        }

        wp_send_json_success(['new_achievements' => $new_achievements]);
        wp_die();
    }

    // Challenges System
    public function ajax_get_challenges() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in.']);
            wp_die();
        }

        $challenges = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_challenges}
            WHERE user_id = %d
            ORDER BY created_at DESC",
            $user_id
        ));

        wp_send_json_success($challenges);
        wp_die();
    }

    public function ajax_start_challenge() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in.']);
            return;
        }

        $challenge_type = sanitize_text_field($_POST['challenge_type']);

        // Pre-defined challenge templates
        $challenge_templates = [
            '52_week' => [
                'name' => '52-Week Savings Challenge',
                'target' => 13780, // ₹1 + ₹2 + ... + ₹52 = ₹1378 (adjusted for INR)
                'weeks' => 52,
                'description' => 'Save incrementally each week for a year'
            ],
            'no_spend' => [
                'name' => '30-Day No-Spend Challenge',
                'target' => 0,
                'weeks' => 4,
                'description' => 'Minimize unnecessary spending for 30 days'
            ],
            '1000_month' => [
                'name' => 'Save ₹1000/Month Challenge',
                'target' => 12000,
                'weeks' => 52,
                'description' => 'Save ₹1000 every month for a year'
            ],
            'emergency_fund' => [
                'name' => '3-Month Emergency Fund Challenge',
                'target' => floatval($_POST['target_amount'] ?? 30000),
                'weeks' => 26, // 6 months to build
                'description' => 'Build an emergency fund covering 3 months of expenses'
            ],
            'custom' => [
                'name' => sanitize_text_field($_POST['custom_name'] ?? 'Custom Challenge'),
                'target' => floatval($_POST['target_amount'] ?? 10000),
                'weeks' => intval($_POST['custom_weeks'] ?? 12),
                'description' => 'Custom savings challenge'
            ]
        ];

        if (!isset($challenge_templates[$challenge_type])) {
            wp_send_json_error(['message' => 'Invalid challenge type']);
            return;
        }

        $template = $challenge_templates[$challenge_type];
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$template['weeks']} weeks"));

        $result = $wpdb->insert($this->table_challenges, [
            'user_id' => $user_id,
            'challenge_type' => $challenge_type,
            'challenge_name' => $template['name'],
            'target_amount' => $template['target'],
            'current_amount' => 0,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'frequency' => 'weekly',
            'status' => 'active'
        ]);

        if ($result) {
            wp_send_json_success(['message' => 'Challenge started!', 'challenge_id' => $wpdb->insert_id]);
        } else {
            wp_send_json_error(['message' => 'Failed to start challenge']);
        }
        wp_die();
    }

    public function ajax_update_challenge() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $challenge_id = intval($_POST['challenge_id']);
        $amount = floatval($_POST['amount']);

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_challenges}
            SET current_amount = current_amount + %f
            WHERE id = %d AND user_id = %d",
            $amount, $challenge_id, $user_id
        ));

        // Check if challenge is complete
        $challenge = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_challenges}
            WHERE id = %d AND user_id = %d",
            $challenge_id, $user_id
        ));

        if ($challenge && $challenge->current_amount >= $challenge->target_amount) {
            $wpdb->update(
                $this->table_challenges,
                ['status' => 'completed'],
                ['id' => $challenge_id, 'user_id' => $user_id]
            );
        }

        if ($result !== false) {
            wp_send_json_success(['message' => 'Challenge updated!']);
        } else {
            wp_send_json_error(['message' => 'Failed to update challenge']);
        }
        wp_die();
    }

    public function ajax_complete_challenge() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $challenge_id = intval($_POST['challenge_id']);

        $result = $wpdb->update(
            $this->table_challenges,
            ['status' => 'completed'],
            ['id' => $challenge_id, 'user_id' => $user_id]
        );

        if ($result) {
            wp_send_json_success(['message' => 'Challenge completed!']);
        } else {
            wp_send_json_error(['message' => 'Failed to complete challenge']);
        }
        wp_die();
    }

    public function ajax_delete_challenge() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $challenge_id = intval($_POST['challenge_id']);

        $result = $wpdb->update(
            $this->table_challenges,
            ['status' => 'deleted'],
            ['id' => $challenge_id, 'user_id' => $user_id]
        );

        if ($result) {
            wp_send_json_success(['message' => 'Challenge moved to trash successfully!']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete challenge']);
        }
        wp_die();
    }

    // Budget Management Functions
    public function ajax_get_budgets() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        $budgets = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.name as category_name, c.emoji as category_emoji
            FROM {$this->table_budgets} b
            LEFT JOIN {$this->table_categories} c ON b.category_id = c.id
            WHERE b.user_id = %d AND b.status = 'active'
            ORDER BY c.name",
            $user_id
        ));

        wp_send_json_success($budgets);
        wp_die();
    }

    public function ajax_add_budget() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (empty($_POST['category_id']) || empty($_POST['amount'])) {
            wp_send_json_error(['message' => 'Please fill in all required fields']);
            wp_die();
        }

        $category_id = intval($_POST['category_id']);
        $amount = floatval($_POST['amount']);
        $period = sanitize_text_field($_POST['period'] ?? 'monthly');

        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Budget amount must be greater than 0']);
            wp_die();
        }

        // Check if budget already exists for this category and period
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_budgets}
            WHERE user_id = %d AND category_id = %d AND period = %s AND status = 'active'",
            $user_id, $category_id, $period
        ));

        if ($existing) {
            wp_send_json_error(['message' => 'A budget already exists for this category. Please edit the existing budget or delete it first.']);
            wp_die();
        }

        $result = $wpdb->insert($this->table_budgets, [
            'user_id' => $user_id,
            'category_id' => $category_id,
            'amount' => $amount,
            'period' => $period,
            'start_date' => sanitize_text_field($_POST['start_date'] ?? date('Y-m-d')),
            'rollover' => isset($_POST['rollover']) ? 1 : 0,
            'alert_threshold' => intval($_POST['alert_threshold'] ?? 80),
            'status' => 'active'
        ]);

        if ($result) {
            wp_send_json_success(['message' => 'Budget added successfully!']);
        } else {
            // Show the actual database error for debugging
            $error_msg = 'Failed to add budget';
            if (!empty($wpdb->last_error)) {
                $error_msg .= ': ' . $wpdb->last_error;
            }
            wp_send_json_error(['message' => $error_msg]);
        }
        wp_die();
    }

    public function ajax_update_budget() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $budget_id = intval($_POST['budget_id']);

        if (empty($_POST['amount'])) {
            wp_send_json_error(['message' => 'Budget amount is required']);
            return;
        }

        $amount = floatval($_POST['amount']);
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Budget amount must be greater than 0']);
            return;
        }

        $result = $wpdb->update(
            $this->table_budgets,
            [
                'amount' => $amount,
                'period' => sanitize_text_field($_POST['period'] ?? 'monthly'),
                'rollover' => isset($_POST['rollover']) ? 1 : 0,
                'alert_threshold' => intval($_POST['alert_threshold'] ?? 80)
            ],
            ['id' => $budget_id, 'user_id' => $user_id]
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Budget updated successfully!']);
        } else {
            wp_send_json_error(['message' => 'Failed to update budget']);
        }
        wp_die();
    }

    public function ajax_delete_budget() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $budget_id = intval($_POST['budget_id']);

        // Permanently delete the budget from database
        $result = $wpdb->delete(
            $this->table_budgets,
            ['id' => $budget_id, 'user_id' => $user_id]
        );

        if ($result) {
            wp_send_json_success(['message' => 'Budget deleted successfully!']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete budget']);
        }
        wp_die();
    }

    public function ajax_get_budget_vs_actual() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        // Get all active budgets
        $budgets = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.name as category_name, c.emoji as category_emoji
            FROM {$this->table_budgets} b
            LEFT JOIN {$this->table_categories} c ON b.category_id = c.id
            WHERE b.user_id = %d AND b.status = 'active'",
            $user_id
        ));

        $results = [];

        foreach ($budgets as $budget) {
            // Calculate date range based on period
            if ($budget->period === 'monthly') {
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-t');
            } else { // yearly
                $start_date = date('Y-01-01');
                $end_date = date('Y-12-31');
            }

            // Get actual spending for this category
            $actual = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0)
                FROM {$this->table_transactions}
                WHERE user_id = %d
                AND category_id = %d
                AND type = 'expense'
                AND status = 'Active'
                AND date >= %s
                AND date <= %s",
                $user_id, $budget->category_id, $start_date, $end_date
            ));

            $percentage = ($budget->amount > 0) ? ($actual / $budget->amount) * 100 : 0;
            $remaining = $budget->amount - $actual;
            $is_over_budget = $actual > $budget->amount;

            // Only show warning if money has been spent, threshold reached, and not over budget
            $is_warning = $actual > 0 && !$is_over_budget && $percentage >= $budget->alert_threshold;

            $results[] = [
                'budget_id' => $budget->id,
                'category_id' => $budget->category_id,
                'category_name' => $budget->category_name,
                'category_emoji' => $budget->category_emoji,
                'budget_amount' => floatval($budget->amount),
                'actual_amount' => floatval($actual),
                'remaining' => floatval($remaining),
                'percentage' => round($percentage, 1),
                'period' => $budget->period,
                'alert_threshold' => $budget->alert_threshold,
                'is_over_budget' => $is_over_budget,
                'is_warning' => $is_warning
            ];
        }

        wp_send_json_success($results);
        wp_die();
    }

    public function ajax_check_budget_alerts() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        // Get budget vs actual data
        $budgets = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.name as category_name, c.emoji as category_emoji
            FROM {$this->table_budgets} b
            LEFT JOIN {$this->table_categories} c ON b.category_id = c.id
            WHERE b.user_id = %d AND b.status = 'active'",
            $user_id
        ));

        $alerts = [];

        foreach ($budgets as $budget) {
            // Calculate date range
            if ($budget->period === 'monthly') {
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-t');
            } else {
                $start_date = date('Y-01-01');
                $end_date = date('Y-12-31');
            }

            // Get actual spending
            $actual = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0)
                FROM {$this->table_transactions}
                WHERE user_id = %d
                AND category_id = %d
                AND type = 'expense'
                AND status = 'Active'
                AND date >= %s
                AND date <= %s",
                $user_id, $budget->category_id, $start_date, $end_date
            ));

            $percentage = ($budget->amount > 0) ? ($actual / $budget->amount) * 100 : 0;
            $is_over_budget = $actual > $budget->amount;

            // Only alert if money has been spent and threshold reached
            if ($actual > 0 && $percentage >= $budget->alert_threshold) {
                $alerts[] = [
                    'category_name' => $budget->category_name,
                    'category_emoji' => $budget->category_emoji,
                    'budget_amount' => floatval($budget->amount),
                    'actual_amount' => floatval($actual),
                    'percentage' => round($percentage, 1),
                    'is_over_budget' => $is_over_budget
                ];
            }
        }

        wp_send_json_success(['alerts' => $alerts]);
        wp_die();
    }

    // Subscription Management
    public function ajax_get_subscriptions() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, c.name as category_name, c.emoji as category_emoji
            FROM {$this->table_subscriptions} s
            LEFT JOIN {$this->table_categories} c ON s.category_id = c.id
            WHERE s.user_id = %d AND s.status != 'Trash'
            ORDER BY s.next_billing_date ASC",
            $user_id
        ));

        // Calculate days until expiry and update status for expired subscriptions
        $today = date('Y-m-d');
        foreach ($subscriptions as $subscription) {
            $today_timestamp = strtotime($today);
            $should_mark_inactive = false;

            // Calculate days until expiry based on END DATE, not next billing date
            if (!empty($subscription->end_date)) {
                $end_date_timestamp = strtotime($subscription->end_date);
                $days_diff = ($end_date_timestamp - $today_timestamp) / (60 * 60 * 24);
                $subscription->days_until_expiry = ceil($days_diff);

                // Mark as inactive if past end_date
                if ($days_diff < 0 && $subscription->status === 'Active') {
                    $should_mark_inactive = true;
                    $subscription->days_since_expiry = abs(ceil($days_diff));
                }
            } else {
                // No end date means subscription doesn't expire
                // Calculate days until next billing for display purposes
                $next_billing = strtotime($subscription->next_billing_date);
                $days_diff = ($next_billing - $today_timestamp) / (60 * 60 * 24);
                $subscription->days_until_expiry = ceil($days_diff);
            }

            // Also check if next_billing_date has passed without payment (for subscriptions without end_date)
            if (empty($subscription->end_date) && !empty($subscription->next_billing_date)) {
                $next_billing_timestamp = strtotime($subscription->next_billing_date);
                $days_since_billing = ($today_timestamp - $next_billing_timestamp) / (60 * 60 * 24);

                // If next billing date has passed by more than 3 days and auto_renew is off, mark as inactive
                if ($days_since_billing > 3 && $subscription->auto_renew == 0 && $subscription->status === 'Active') {
                    $should_mark_inactive = true;
                }
            }

            // Update status to Inactive if needed
            if ($should_mark_inactive) {
                $wpdb->update(
                    $this->table_subscriptions,
                    ['status' => 'Inactive'],
                    ['id' => $subscription->id],
                    ['%s'],
                    ['%d']
                );
                $subscription->status = 'Inactive';
            }

            // Explicitly cast auto_renew to integer for consistency
            $subscription->auto_renew = intval($subscription->auto_renew);

            // Map last_renewed_date to last_paid_date for frontend
            $subscription->last_paid_date = $subscription->last_renewed_date;
        }

        wp_send_json_success(['subscriptions' => $subscriptions]);
        wp_die();
    }

    public function ajax_add_subscription() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in']);
            return;
        }

        $amount = floatval($_POST['amount']);
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Amount must be greater than 0']);
            return;
        }

        // Calculate next billing date based on cycle
        $start_date = sanitize_text_field($_POST['start_date']);
        $billing_cycle = sanitize_text_field($_POST['billing_cycle']);
        $next_billing_date = $this->calculate_next_billing_date($start_date, $billing_cycle, $_POST['custom_cycle_days'] ?? null);

        $data = [
            'user_id' => $user_id,
            'name' => sanitize_text_field($_POST['name']),
            'amount' => $amount,
            'category_id' => intval($_POST['category_id']),
            'billing_cycle' => $billing_cycle,
            'custom_cycle_days' => !empty($_POST['custom_cycle_days']) ? intval($_POST['custom_cycle_days']) : null,
            'start_date' => $start_date,
            'next_billing_date' => $next_billing_date,
            'end_date' => !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null,
            'payment_method' => sanitize_text_field($_POST['payment_method']),
            'auto_renew' => intval($_POST['auto_renew'] ?? 0),
            'reminder_days' => intval($_POST['reminder_days'] ?? 7),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'status' => 'Active'
        ];

        $result = $wpdb->insert($this->table_subscriptions, $data);

        if ($result) {
            // If "add_as_transaction" is checked, create initial transaction
            if (isset($_POST['add_as_transaction']) && $_POST['add_as_transaction']) {
                $wpdb->insert($this->table_transactions, [
                    'user_id' => $user_id,
                    'type' => 'expense',
                    'amount' => $amount,
                    'date' => $start_date,
                    'category_id' => intval($_POST['category_id']),
                    'payment_method' => sanitize_text_field($_POST['payment_method']),
                    'description' => 'Subscription: ' . sanitize_text_field($_POST['name']),
                    'status' => 'Active'
                ]);
            }

            wp_send_json_success(['message' => 'Subscription added successfully']);
        } else {
            $error_msg = $wpdb->last_error ? $wpdb->last_error : 'Failed to add subscription';
            error_log('RizqTrack Subscription Error: ' . $error_msg);
            wp_send_json_error(['message' => 'Database error: ' . $error_msg]);
        }
        wp_die();
    }

    public function ajax_update_subscription() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        $amount = floatval($_POST['amount']);
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Amount must be greater than 0']);
            return;
        }

        // Recalculate next billing date based on updated start_date and billing_cycle
        $start_date = !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-d');
        $billing_cycle = sanitize_text_field($_POST['billing_cycle']);
        $custom_cycle_days = !empty($_POST['custom_cycle_days']) ? intval($_POST['custom_cycle_days']) : null;
        $next_billing_date = $this->calculate_next_billing_date($start_date, $billing_cycle, $custom_cycle_days);

        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'amount' => $amount,
            'category_id' => intval($_POST['category_id']),
            'billing_cycle' => $billing_cycle,
            'start_date' => $start_date,
            'custom_cycle_days' => $custom_cycle_days,
            'end_date' => !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null,
            'payment_method' => sanitize_text_field($_POST['payment_method']),
            'auto_renew' => intval($_POST['auto_renew'] ?? 0),
            'reminder_days' => intval($_POST['reminder_days'] ?? 7),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'next_billing_date' => $next_billing_date
        ];

        $result = $wpdb->update(
            $this->table_subscriptions,
            $data,
            ['id' => $id, 'user_id' => $user_id],
            ['%s', '%f', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s'],
            ['%d', '%d']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Subscription updated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to update subscription']);
        }
        wp_die();
    }

    public function ajax_renew_subscription() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);
        $add_as_transaction = isset($_POST['add_as_transaction']) && $_POST['add_as_transaction'];

        // Get subscription details
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_subscriptions} WHERE id = %d AND user_id = %d",
            $id, $user_id
        ));

        if (!$subscription) {
            wp_send_json_error(['message' => 'Subscription not found']);
            return;
        }

        $today = date('Y-m-d');

        // Create transaction for renewal only if checkbox is checked
        if ($add_as_transaction) {
            $result = $wpdb->insert($this->table_transactions, [
                'user_id' => $user_id,
                'type' => 'expense',
                'amount' => $subscription->amount,
                'date' => $today,
                'category_id' => $subscription->category_id,
                'payment_method' => $subscription->payment_method,
                'description' => 'Subscription Renewal: ' . $subscription->name,
                'status' => 'Active'
            ]);

            if (!$result) {
                wp_send_json_error(['message' => 'Failed to create transaction']);
                return;
            }
        }

        // Calculate new next billing date
        $new_next_billing = $this->calculate_next_billing_date(
            $today,
            $subscription->billing_cycle,
            $subscription->custom_cycle_days
        );

        // Calculate new end date (coverage period)
        // End date should be the day before the next billing date
        $new_end_date = date('Y-m-d', strtotime($new_next_billing . ' -1 day'));

        // Update subscription
        $wpdb->update(
            $this->table_subscriptions,
            [
                'next_billing_date' => $new_next_billing,
                'end_date' => $new_end_date,
                'last_renewed_date' => $today,
                'status' => 'Active'
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        $message = $add_as_transaction
            ? 'Subscription renewed and transaction created successfully'
            : 'Subscription renewed successfully (no transaction created)';

        wp_send_json_success([
            'message' => $message,
            'next_billing_date' => $new_next_billing
        ]);
        wp_die();
    }

    public function ajax_reactivate_subscription() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        // Get subscription details
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_subscriptions} WHERE id = %d AND user_id = %d",
            $id, $user_id
        ));

        if (!$subscription) {
            wp_send_json_error(['message' => 'Subscription not found']);
            return;
        }

        $today = date('Y-m-d');

        // For one-time and 5-year subscriptions, calculate based on original coverage period
        if ($subscription->billing_cycle === 'one-time' || $subscription->billing_cycle === '5year') {
            // Calculate original coverage period
            if ($subscription->start_date && $subscription->end_date) {
                $start_timestamp = strtotime($subscription->start_date);
                $original_end_timestamp = strtotime($subscription->end_date);
                $coverage_days = ($original_end_timestamp - $start_timestamp) / (60 * 60 * 24);

                // Extend from today by the same coverage period
                $new_end_date = date('Y-m-d', strtotime($today . " +{$coverage_days} days"));
                $new_next_billing = date('Y-m-d', strtotime($new_end_date . ' +1 day'));
            } else {
                // Default: 1 year coverage for one-time, 5 years for 5-year
                $years = $subscription->billing_cycle === '5year' ? 5 : 1;
                $new_end_date = date('Y-m-d', strtotime($today . " +{$years} years"));
                $new_next_billing = date('Y-m-d', strtotime($new_end_date . ' +1 day'));
            }
        } else {
            // For recurring subscriptions (monthly, yearly, etc.)
            $new_next_billing = $this->calculate_next_billing_date(
                $today,
                $subscription->billing_cycle,
                $subscription->custom_cycle_days
            );
            $new_end_date = date('Y-m-d', strtotime($new_next_billing . ' -1 day'));
        }

        // Update subscription
        $result = $wpdb->update(
            $this->table_subscriptions,
            [
                'next_billing_date' => $new_next_billing,
                'end_date' => $new_end_date,
                'status' => 'Active'
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success([
                'message' => 'Subscription reactivated successfully',
                'next_billing_date' => $new_next_billing
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to reactivate subscription']);
        }
        wp_die();
    }

    public function ajax_deactivate_subscription() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        // Update subscription status to Inactive
        $result = $wpdb->update(
            $this->table_subscriptions,
            ['status' => 'Inactive'],
            ['id' => $id, 'user_id' => $user_id],
            ['%s'],
            ['%d', '%d']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Subscription deactivated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to deactivate subscription']);
        }
        wp_die();
    }

    public function ajax_undo_payment() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        // Get subscription details
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_subscriptions} WHERE id = %d AND user_id = %d",
            $id, $user_id
        ));

        if (!$subscription) {
            wp_send_json_error(['message' => 'Subscription not found']);
            return;
        }

        if (!$subscription->last_renewed_date) {
            wp_send_json_error(['message' => 'No payment to undo']);
            return;
        }

        // Calculate previous next billing date (rollback one cycle)
        $current_billing = strtotime($subscription->next_billing_date);

        switch ($subscription->billing_cycle) {
            case 'monthly':
                $previous_billing = strtotime('-1 month', $current_billing);
                break;
            case 'quarterly':
                $previous_billing = strtotime('-3 months', $current_billing);
                break;
            case 'yearly':
                $previous_billing = strtotime('-1 year', $current_billing);
                break;
            case '5year':
                $previous_billing = strtotime('-5 years', $current_billing);
                break;
            case 'custom':
                $days = intval($subscription->custom_cycle_days ?? 30);
                $previous_billing = strtotime("-{$days} days", $current_billing);
                break;
            default:
                $previous_billing = strtotime('-1 month', $current_billing);
        }

        $previous_billing_date = date('Y-m-d', $previous_billing);

        // Delete the most recent transaction for this subscription (created on last_renewed_date)
        $wpdb->delete(
            $this->table_transactions,
            [
                'user_id' => $user_id,
                'date' => $subscription->last_renewed_date,
                'description' => 'Subscription Renewal: ' . $subscription->name
            ],
            ['%d', '%s', '%s']
        );

        // Update subscription: clear last_renewed_date and rollback next_billing_date
        // DON'T rollback end_date as it may go into the past and cause subscription to become inactive
        $result = $wpdb->update(
            $this->table_subscriptions,
            [
                'next_billing_date' => $previous_billing_date,
                'last_renewed_date' => null
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success([
                'message' => 'Payment undone successfully',
                'next_billing_date' => $previous_billing_date
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to undo payment']);
        }
        wp_die();
    }

    public function ajax_delete_subscription() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        $result = $wpdb->update(
            $this->table_subscriptions,
            ['status' => 'Trash'],
            ['id' => $id, 'user_id' => $user_id],
            ['%s'],
            ['%d', '%d']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Subscription moved to trash']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete subscription']);
        }
        wp_die();
    }

    public function ajax_restore_subscription() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        $result = $wpdb->update(
            $this->table_subscriptions,
            ['status' => 'Active'],
            ['id' => $id, 'user_id' => $user_id],
            ['%s'],
            ['%d', '%d']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Subscription restored successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to restore subscription']);
        }
        wp_die();
    }

    public function ajax_permanent_delete_subscription() {
        check_ajax_referer('rizqtrack_nonce', 'nonce');
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval($_POST['id']);

        $result = $wpdb->delete(
            $this->table_subscriptions,
            ['id' => $id, 'user_id' => $user_id, 'status' => 'Trash'],
            ['%d', '%d', '%s']
        );

        if ($result) {
            wp_send_json_success(['message' => 'Subscription permanently deleted']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete subscription permanently']);
        }
        wp_die();
    }

    // Helper function to calculate next billing date
    private function calculate_next_billing_date($from_date, $billing_cycle, $custom_days = null) {
        $timestamp = strtotime($from_date);
        $today = strtotime(date('Y-m-d'));

        // Determine the interval based on billing cycle
        switch ($billing_cycle) {
            case 'monthly':
                $interval = '+1 month';
                break;
            case 'quarterly':
                $interval = '+3 months';
                break;
            case 'yearly':
                $interval = '+1 year';
                break;
            case '5year':
                $interval = '+5 years';
                break;
            case 'one-time':
                // One-time payment: next billing is the same as start date (no recurrence)
                return date('Y-m-d', $timestamp);
            case 'custom':
                $days = intval($custom_days ?? 30);
                $interval = "+{$days} days";
                break;
            default:
                $interval = '+1 month';
        }

        // Calculate next billing date
        $next_billing = strtotime($interval, $timestamp);

        // If the next billing date is in the past, keep adding intervals until we get a future date
        while ($next_billing < $today) {
            $next_billing = strtotime($interval, $next_billing);
        }

        return date('Y-m-d', $next_billing);
    }
}

RizqTrack::get_instance();
