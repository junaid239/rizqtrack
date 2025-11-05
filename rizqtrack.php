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

        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // Shortcode
        add_shortcode('rizqtrack_dashboard', [$this, 'render_frontend_dashboard']);

        // AJAX endpoints
        $this->register_ajax_endpoints();
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
            status enum('Active','Trash') DEFAULT 'Active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY date (date)
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
            status enum('active','completed','archived','Trash') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_transactions);
        dbDelta($sql_categories);
        dbDelta($sql_goals);
    }

    private function create_default_categories() {
        global $wpdb;

        $categories = [
            ['Housing/Rent', 'expense', '🏠'],
            ['Transportation', 'expense', '🚗'],
            ['Food & Groceries', 'expense', '🛒'],
            ['Utilities & Bills', 'expense', '💡'],
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
    }

    private function register_ajax_endpoints() {
        $endpoints = [
            'add_transaction', 'update_transaction', 'delete_transaction',
            'restore_transaction', 'permanent_delete', 'get_recent_transactions',
            'get_chart_data', 'get_categories', 'get_goals', 'get_trash',
            'add_category', 'update_category', 'delete_category',
            'add_goal', 'update_goal', 'delete_goal', 'restore_goal', 'permanent_delete_goal',
            'contribute_goal_transaction', 'generate_report', 'get_kpi_data',
            'get_email_settings', 'save_email_settings'
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

        $result = $wpdb->update(
            $this->table_transactions,
            ['status' => 'Trash'],
            ['id' => $id, 'user_id' => $user_id]
        );

        if ($result) {
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

        $result = $wpdb->update(
            $this->table_transactions,
            ['status' => 'Active'],
            ['id' => $id, 'user_id' => $user_id]
        );

        if ($result) {
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

        $result = $wpdb->delete(
            $this->table_transactions,
            ['id' => $id, 'user_id' => $user_id, 'status' => 'Trash']
        );

        if ($result) {
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

        $filter = sanitize_text_field($_POST['filter'] ?? '30');
        $days_map = ['7' => 7, '30' => 30, '90' => 90, '180' => 180, '365' => 365];
        $days = $days_map[$filter] ?? 30;

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
            AND t.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            $category_filter_sql
            GROUP BY c.id, c.name, c.emoji
            ORDER BY total DESC";

        $category_params = array_merge([$user_id, $days], $category_filter_params);
        $category_data = $wpdb->get_results($wpdb->prepare($category_query, $category_params));

        // Income vs Expense (with category filter)
        $income_expense_query = "SELECT
                COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active'
            AND t.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            $category_filter_sql";

        $income_expense_params = array_merge([$user_id, $days], $category_filter_params);
        $income_expense = $wpdb->get_row($wpdb->prepare($income_expense_query, $income_expense_params));

        if (!$income_expense) {
            $income_expense = (object) [
                'total_income' => 0,
                'total_expense' => 0
            ];
        }

        // Spending trend over time (with category filter)
        $spending_trend_query = "SELECT
                DATE(t.date) as date,
                COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as expense
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active'
            AND t.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            $category_filter_sql
            GROUP BY DATE(t.date)
            ORDER BY date ASC";

        $spending_trend_params = array_merge([$user_id, $days], $category_filter_params);
        $spending_trend = $wpdb->get_results($wpdb->prepare($spending_trend_query, $spending_trend_params));

        wp_send_json_success([
            'category_data' => $category_data,
            'income_expense' => $income_expense,
            'spending_trend' => $spending_trend
        ]);
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

        // Get top spending category
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

        $kpi_data = [
            'total_income' => floatval($summary->total_income),
            'total_expense' => floatval($summary->total_expense),
            'net_savings' => floatval($summary->total_income) - floatval($summary->total_expense),
            'transaction_count' => intval($summary->transaction_count),
            'avg_transaction' => floatval($summary->avg_transaction),
            'top_category' => $top_category ? $top_category->emoji . ' ' . $top_category->name : 'N/A'
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
            'status' => 'active'
        ];

        $result = $wpdb->insert($this->table_goals, $data);

        if ($result) {
            wp_send_json_success(['message' => 'Goal added successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to add goal']);
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
            'deadline' => sanitize_text_field($_POST['deadline'] ?? null)
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

        // Create transaction
        $wpdb->insert($this->table_transactions, [
            'user_id' => $user_id,
            'type' => 'expense',
            'amount' => $amount,
            'date' => current_time('Y-m-d'),
            'category_id' => $category_id,
            'payment_method' => 'Bank Transfer',
            'description' => 'Contribution to: ' . $goal->name,
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

        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, c.name as category_name, c.emoji as category_emoji
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active'
            AND t.date BETWEEN %s AND %s
            ORDER BY t.date DESC",
            $user_id, $start_date, $end_date
        ));

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
        fputcsv($output, ['Date', 'Type', 'Category', 'Amount', 'Payment Method', 'Description']);

        foreach ($transactions as $t) {
            fputcsv($output, [
                $t->date,
                ucfirst($t->type),
                $t->category_name,
                number_format($t->amount, 2),
                $t->payment_method,
                $t->description
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

    private function send_email_report($user_id, $period) {
        global $wpdb;

        $email = get_user_meta($user_id, 'rizqtrack_email_address', true);
        if (!$email) return;

        $days = ($period === 'weekly') ? 7 : 30;

        // Get data
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
                COUNT(*) as transaction_count
            FROM {$this->table_transactions}
            WHERE user_id = %d AND status = 'Active'
            AND date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
            $user_id, $days
        ));

        $top_categories = $wpdb->get_results($wpdb->prepare(
            "SELECT c.name, c.emoji, SUM(t.amount) as total
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active' AND t.type = 'expense'
            AND t.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            GROUP BY c.id
            ORDER BY total DESC
            LIMIT 5",
            $user_id, $days
        ));

        $subject = sprintf('RizqTrack %s Report - %s', ucfirst($period), date('F j, Y'));
        $message = $this->generate_email_html($summary, $top_categories, $period);

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($email, $subject, $message, $headers);
    }

    private function generate_email_html($summary, $top_categories, $period) {
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
}

RizqTrack::get_instance();
