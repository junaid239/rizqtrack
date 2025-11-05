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
            'contribute_goal_transaction', 'generate_report', 'get_kpi_data'
        ];

        foreach ($endpoints as $endpoint) {
            add_action("wp_ajax_rizqtrack_{$endpoint}", [$this, "ajax_{$endpoint}"]);
        }
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
        $limit = 10; // Keep showing 10 transactions per page
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $offset = ($page - 1) * $limit;
        // --- END: Pagination Logic ---


        // Query for the transactions with pagination
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, c.name as category_name, c.emoji as category_emoji
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active'
            ORDER BY t.date DESC, t.created_at DESC
            LIMIT %d OFFSET %d", // <-- UPDATED
            $user_id,
            $limit,     // <-- NEW
            $offset     // <-- NEW
        ));

        // New query to get the total count for pagination
        $total_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$this->table_transactions}
            WHERE user_id = %d AND status = 'Active'",
            $user_id
        ));

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

        // Category breakdown (expenses only)
        $category_data = $wpdb->get_results($wpdb->prepare(
            "SELECT c.name, c.emoji, SUM(t.amount) as total
            FROM {$this->table_transactions} t
            LEFT JOIN {$this->table_categories} c ON t.category_id = c.id
            WHERE t.user_id = %d AND t.status = 'Active' AND t.type = 'expense'
            AND t.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            GROUP BY c.id, c.name, c.emoji
            ORDER BY total DESC",
            $user_id, $days
        ));

        // Income vs Expense
        $income_expense = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
            FROM {$this->table_transactions}
            WHERE user_id = %d AND status = 'Active'
            AND date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
            $user_id, $days
        ));

        if (!$income_expense) {
            $income_expense = (object) [
                'total_income' => 0,
                'total_expense' => 0
            ];
        }

        wp_send_json_success([
            'category_data' => $category_data,
            'income_expense' => $income_expense
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
