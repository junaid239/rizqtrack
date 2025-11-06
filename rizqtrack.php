<?php
/**
 * Plugin Name: RizqTrack - Personal Finance Tracker
 * Plugin URI: https://thejunaid.in
 * Description: Premium zero-refresh personal finance management dashboard for WordPress
 * Version: 1.0.1
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

        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_init', [$this, 'run_migrations']);

        // Shortcode
        add_shortcode('rizqtrack_dashboard', [$this, 'render_frontend_dashboard']);

        // AJAX endpoints
        $this->register_ajax_endpoints();
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_transactions);
        dbDelta($sql_categories);
        dbDelta($sql_goals);
        dbDelta($sql_achievements);
        dbDelta($sql_challenges);
        dbDelta($sql_budgets);

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
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_rizqtrack') return;

        wp_enqueue_style('rizqtrack-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.0.1');
        wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap');

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true);
        
        // MODIFIED: Added Datalabels plugin
        wp_enqueue_script('chart-js-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js', ['chart-js'], '2.2.0', true);
        
        // MODIFIED: Added 'chart-js-datalabels' as a dependency
        wp_enqueue_script('rizqtrack-script', plugin_dir_url(__FILE__) . 'assets/js/app.js', ['jquery', 'chart-js', 'chart-js-datalabels'], '1.0.1', true);

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

        wp_enqueue_style('rizqtrack-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.0.1');
        wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap');

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true);
        
        // MODIFIED: Added Datalabels plugin
        wp_enqueue_script('chart-js-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js', ['chart-js'], '2.2.0', true);

        // MODIFIED: Added 'chart-js-datalabels' as a dependency
        wp_enqueue_script('rizqtrack-script', plugin_dir_url(__FILE__) . 'assets/js/app.js', ['jquery', 'chart-js', 'chart-js-datalabels'], '1.0.1', true);

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
            'get_budgets', 'add_budget', 'update_budget', 'delete_budget', 'check_budget_alerts', 'get_budget_vs_actual'
        ];

        foreach ($endpoints as $endpoint) {
            add_action("wp_ajax_rizqtrack_{$endpoint}", [$this, "ajax_{$endpoint}"]);
        }

        // Register cron hooks
        add_action('rizqtrack_send_weekly_email', [$this, 'send_weekly_report']);
        add_action('rizqtrack_send_monthly_email', [$this, 'send_monthly_report']);
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

        wp_send_json_success([
            'category_data' => $category_data,
            'top_frequent' => $top_frequent,
            'spending_trend' => $spending_trend
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

        wp_send_json_success([
            'transactions' => $transactions,
            'goals' => $goals
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
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rizqtrack_report_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        fputcsv($output, ['RizqTrack Financial Report']);
        fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
        fputcsv($output, []);
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
            'frequency' => get_user_meta($user_id, 'rizqtrack_email_frequency', true),
            'email' => get_user_meta($user_id, 'rizqtrack_email_address', true) ?: wp_get_current_user()->user_email
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

        $frequency = sanitize_text_field($_POST['frequency']);
        $email = sanitize_email($_POST['email']);

        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address']);
            wp_die();
        }

        update_user_meta($user_id, 'rizqtrack_email_frequency', $frequency);
        update_user_meta($user_id, 'rizqtrack_email_address', $email);

        // Schedule/unschedule cron jobs
        $this->update_user_cron($user_id, $frequency);

        wp_send_json_success(['message' => 'Settings saved successfully']);
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
        $this->send_email_report($user_id, 'monthly');
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

        $subject = sprintf('RizqTrack Report - %s', $period_label);
        $message = $this->generate_email_html($summary, $top_categories, $goals, $period_label);

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($email, $subject, $message, $headers);
    }

    private function generate_email_html($summary, $top_categories, $goals, $period) {
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
}

RizqTrack::get_instance();
