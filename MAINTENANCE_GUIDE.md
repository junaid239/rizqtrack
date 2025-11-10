# RizqTrack Maintenance Guide

**Version:** 1.6.0
**Last Updated:** 2025-11-10
**Note:** Gamification features (achievements and challenges) have been removed from the codebase.
**Author:** Generated for RizqTrack Development Team

---

## Table of Contents

1. [Feature Mapping Table](#feature-mapping-table)
2. [Modification Playbook](#modification-playbook)
3. [Quick Reference](#quick-reference)

---

## Feature Mapping Table

This table maps every major feature of RizqTrack to the specific files and key functions responsible for its implementation.

| Feature/Usecase | Description | Associated Files & Key Functions |
|----------------|-------------|----------------------------------|
| **Transaction Management** | Add, edit, delete, and restore income/expense transactions with soft delete (trash) functionality | **Backend:** `rizqtrack.php:777-1002`<br>- `ajax_add_transaction()` (Line 777)<br>- `ajax_update_transaction()` (Line 842)<br>- `ajax_delete_transaction()` (Line 900)<br>- `ajax_restore_transaction()` (Line 936)<br>- `ajax_permanent_delete()` (Line 972)<br>- `ajax_get_recent_transactions()` (Line 1002)<br>**Frontend:** `templates/dashboard.php:135-219` (Transaction form UI)<br>`assets/js/app.js:172-199` (Form handling) |
| **Fuel/Vehicle Tracking** | Track odometer readings, fuel consumption, calculate mileage (km/L), and monitor full tank status | **Backend:** `rizqtrack.php:777-842` (Integrated in transaction system)<br>**Database:** `wp_rizqtrack_transactions` table (odometer_reading, fuel_liters, fuel_amount, is_full_tank columns)<br>**Frontend:** `templates/dashboard.php:180-210` (Fuel tracking fields)<br>`assets/js/app.js:172-199` (Fuel form logic) |
| **Category Management** | Create, edit, delete custom categories with emoji support for income/expense/both types | **Backend:** `rizqtrack.php:1400-1580`<br>- `ajax_get_categories()` (Line 1400)<br>- `ajax_add_category()` (Line 1493)<br>- `ajax_update_category()` (Line 1521)<br>- `ajax_delete_category()` (Line 1548)<br>**Frontend:** `templates/dashboard.php:687-720` (Category management modal)<br>`assets/js/app.js` (Category CRUD operations) |
| **Financial Goals** | Set savings/investment goals with target amounts, deadlines, priority levels, and track contributions | **Backend:** `rizqtrack.php:1422-1803`<br>- `ajax_get_goals()` (Line 1422)<br>- `ajax_add_goal()` (Line 1581)<br>- `ajax_update_goal()` (Line 1620)<br>- `ajax_delete_goal()` (Line 1651)<br>- `ajax_contribute_goal_transaction()` (Line 1734)<br>**Frontend:** `templates/dashboard.php:386-472` (Goals overview section)<br>`templates/dashboard.php:722-796` (Goal modal)<br>`templates/dashboard.php:798-820` (Contribution modal) |
| **Budget Management** | Create category-based budgets with monthly/yearly periods, alert thresholds, and rollover options | **Backend:** `rizqtrack.php:3134-3391`<br>- `ajax_get_budgets()` (Line 3134)<br>- `ajax_add_budget()` (Line 3153)<br>- `ajax_update_budget()` (Line 3209)<br>- `ajax_delete_budget()` (Line 3246)<br>- `ajax_get_budget_vs_actual()` (Line 3267)<br>- `ajax_check_budget_alerts()` (Line 3334)<br>**Frontend:** `templates/dashboard.php:474-487` (Budget grid)<br>`templates/dashboard.php:972-1021` (Budget modal) |
| **Subscription Management** | Track recurring payments with multiple billing cycles, renewal tracking, and payment history | **Backend:** `rizqtrack.php:3395-3800`<br>- `ajax_get_subscriptions()` (Line 3395)<br>- `ajax_add_subscription()` (Line 3469)<br>- `ajax_update_subscription()` (Line 3534)<br>- `ajax_renew_subscription()` (Line 3584)<br>- `ajax_reactivate_subscription()` (Line 3660)<br>- `ajax_deactivate_subscription()` (Line 3731)<br>- `ajax_undo_payment()` (Line 3755)<br>**Frontend:** `templates/dashboard.php:489-561` (Subscription overview)<br>`templates/dashboard.php:1023-1122` (Subscription modal) |
| **KPI Dashboard** | Display 12 key performance indicators including income, expenses, savings, and vehicle mileage | **Backend:** `rizqtrack.php:1226-1398`<br>- `ajax_get_kpi_data()` (Line 1226)<br>**Frontend:** `templates/dashboard.php:48-133` (KPI cards section)<br>`assets/js/app.js` (KPI refresh logic) |
| **Charts & Analytics** | Advanced visualizations including pie charts, Pareto analysis, treemaps, and period comparisons | **Backend:** `rizqtrack.php:1079-1225`<br>- `ajax_get_chart_data()` (Line 1079)<br>- `ajax_get_category_details()` (Line 1189)<br>**Frontend:** `templates/dashboard.php:221-330` (Chart containers and filters)<br>`assets/js/app.js:900-1800` (Chart rendering with Chart.js) |
| **Report Generation** | Generate CSV reports with custom filters, date ranges, and category selections | **Backend:** `rizqtrack.php:1804-1917`<br>- `ajax_generate_report()` (Line 1804)<br>**Frontend:** `templates/dashboard.php:822-883` (Report configuration modal)<br>`assets/js/app.js` (Report generation logic) |
| **Email Reports** | Automated weekly/monthly email reports with customizable schedules and manual sending | **Backend:** `rizqtrack.php:1918-2119, 2120-2318`<br>- `ajax_get_email_settings()` (Line 1918)<br>- `ajax_save_email_settings()` (Line 1940)<br>- `ajax_send_email_now()` (Line 2120)<br>- `send_weekly_report()` (Cron job)<br>- `send_monthly_report()` (Cron job)<br>**Frontend:** `templates/dashboard.php:885-970` (Email settings modal)<br>**API:** `/wp-json/rizqtrack/v1/cron/weekly` (REST endpoint) |
| **Quick Navigation** | Hamburger menu for quick section navigation and FAB for quick transaction entry | **Frontend:** `templates/dashboard.php:3-25` (Nav bar and FAB)<br>`assets/css/style.css` (Navigation styling)<br>`assets/js/app.js` (Navigation event handlers) |
| **Section Navigation** | Up/Down arrow buttons to navigate between major dashboard sections | **Frontend:** `templates/dashboard.php:27-35` (Section arrows)<br>`assets/js/app.js` (Scroll logic) |
| **Motivational Quotes** | Display rotating multi-religious wisdom and financial quotes | **Frontend:** `templates/dashboard.php:37-46` (Quote card)<br>`assets/js/app.js` (Quote rotation logic) |
| **Trash Management** | View and restore deleted transactions, goals, and subscriptions with permanent delete option | **Backend:** `rizqtrack.php:936-1001` (Restore/Permanent delete functions)<br>**Frontend:** `templates/dashboard.php:589-616` (Trash section)<br>`assets/js/app.js` (Trash operations) |
| **Advanced Filtering** | Multi-select category filters, date ranges, transaction type filters, and saved preferences | **Backend:** `rizqtrack.php:1002-1078`<br>- `ajax_get_category_filters()` (Line 1053)<br>- `ajax_save_category_filters()` (Line 1062)<br>**Frontend:** `templates/dashboard.php:221-270` (Filter controls)<br>`assets/js/app.js:3200-3600` (Filter handling) |
| **Period Comparison** | Compare current period vs previous month or custom date ranges in KPIs and charts | **Backend:** `rizqtrack.php:1226-1398` (Integrated in KPI data)<br>**Frontend:** `templates/dashboard.php:221-330` (Comparison controls)<br>`assets/js/app.js` (Comparison logic) |
| **Progressive Web App** | Offline support, installable app, background sync, and push notifications | **Files:** `assets/sw.js` (Service Worker - 174 lines)<br>`assets/manifest.json` (PWA manifest)<br>`assets/.htaccess` (Cache control headers) |
| **User Authentication** | WordPress-based authentication with user data isolation and capability checks | **Backend:** `rizqtrack.php:33-775` (Constructor and security checks)<br>All AJAX functions include: `check_ajax_referer('rizqtrack_nonce', 'nonce')` |
| **Database Schema** | 6 custom tables for transactions, categories, goals, budgets, subscriptions, and cron logs | **Migration:** `rizqtrack.php` (activate() method)<br>**Tables:** wp_rizqtrack_transactions, wp_rizqtrack_categories, wp_rizqtrack_goals, wp_rizqtrack_budgets, wp_rizqtrack_subscriptions, wp_rizqtrack_cron_logs |
| **Login Page Design** | Modern, animated login page with gradient effects and responsive design | **Styling:** `assets/css/style.css:1-200` (Login page styles)<br>Includes animations, gradient backgrounds, and form styling |

---

## Modification Playbook

This section provides step-by-step instructions for safely making common modifications to each feature.

---

### 1. Transaction Management

#### **Change Transaction Form Field Labels**
1. Open `templates/dashboard.php`
2. Locate the transaction form section (Lines 135-219)
3. Find the label you want to modify (e.g., Line 143 for "Amount")
4. Update the text within the `<label>` tag
5. Save the file
6. **Test:** Refresh the dashboard and verify the label appears correctly

#### **Add New Payment Method**
1. Open `assets/js/app.js`
2. Search for the payment method dropdown population logic
3. Add your new payment method to the options array
4. Save the file
5. **Test:** Open the transaction form and verify your new payment method appears in the dropdown

#### **Modify Transaction Validation Rules**
1. Open `assets/js/app.js`
2. Locate the transaction form validation logic (around Lines 172-199)
3. Update validation conditions (e.g., minimum amount, required fields)
4. Save the file
5. **Test:** Try submitting invalid data to ensure validation works correctly

---

### 2. Fuel/Vehicle Tracking

#### **Change Mileage Calculation Formula**
1. Open `rizqtrack.php`
2. Navigate to the `ajax_get_kpi_data()` function (Line 1226)
3. Find the mileage calculation logic (search for "km/L")
4. Modify the formula as needed (e.g., change to MPG: distance_km / fuel_liters * 3.785)
5. Update the label in `templates/dashboard.php` KPI section if needed
6. Save both files
7. **Test:** Add a fuel transaction and verify the mileage displays correctly

#### **Add Additional Fuel Tracking Fields**
1. Open `rizqtrack.php` and add a database migration to add new column(s) in the `activate()` method
2. Open `templates/dashboard.php` (Lines 180-210)
3. Add new input fields in the fuel tracking section
4. Open `assets/js/app.js` (Lines 172-199)
5. Update the form submission logic to include new field values
6. Update the `ajax_add_transaction()` function in `rizqtrack.php` (Line 777) to save new fields
7. **Test:** Add a fuel transaction and verify new fields are saved and displayed

---

### 3. Category Management

#### **Change Category Emoji Picker**
1. Open `templates/dashboard.php`
2. Locate the category modal (Lines 687-720)
3. Find the emoji input field
4. Modify the input type or add a custom emoji picker library
5. Update `assets/js/app.js` to handle the new emoji picker if needed
6. Save files
7. **Test:** Open category modal and verify emoji selection works

#### **Add Category Icon/Color Support**
1. Open `rizqtrack.php` and add database migration to add `color` column to categories table
2. Update `ajax_add_category()` (Line 1493) to save color value
3. Update `ajax_update_category()` (Line 1521) to update color value
4. Update `ajax_get_categories()` (Line 1400) to return color value
5. Open `templates/dashboard.php` (Lines 687-720) and add color picker input
6. Update `assets/js/app.js` to handle color selection
7. Update `assets/css/style.css` to apply category colors in UI
8. **Test:** Create/edit categories with colors and verify they display correctly

---

### 4. Financial Goals

#### **Change Goal Priority Options**
1. Open `templates/dashboard.php`
2. Find the goal modal (Lines 722-796)
3. Locate the priority dropdown (search for "priority")
4. Add/remove/modify `<option>` elements
5. Save the file
6. **Test:** Open goal modal and verify new priority options appear

#### **Modify Goal Progress Calculation**
1. Open `rizqtrack.php`
2. Navigate to `ajax_get_goals()` function (Line 1422)
3. Find the progress percentage calculation logic
4. Modify the formula (e.g., current_amount / target_amount * 100)
5. Save the file
6. **Test:** View goals section and verify progress bars display correctly

#### **Change Goal Categories**
1. Open `templates/dashboard.php`
2. Find the goal modal (Lines 722-796)
3. Locate the category dropdown
4. Add/remove/modify `<option>` elements (e.g., add "Real Estate" category)
5. Save the file
6. **Test:** Create a goal and verify new categories are available

---

### 5. Budget Management

#### **Change Default Budget Alert Threshold**
1. Open `templates/dashboard.php`
2. Find the budget modal (Lines 972-1021)
3. Locate the alert threshold input field
4. Change the `value` attribute (e.g., from 80 to 90)
5. Save the file
6. **Test:** Create a new budget and verify the default threshold is set correctly

#### **Modify Budget Alert Logic**
1. Open `rizqtrack.php`
2. Navigate to `ajax_check_budget_alerts()` function (Line 3334)
3. Find the alert condition logic
4. Modify the condition (e.g., trigger alerts at different percentages)
5. Update alert messages if needed
6. Save the file
7. **Test:** Create transactions that exceed budget threshold and verify alerts appear

#### **Add Custom Budget Periods**
1. Open `templates/dashboard.php`
2. Find the budget modal (Lines 972-1021)
3. Locate the period dropdown
4. Add new `<option>` elements (e.g., "Quarterly", "Bi-Weekly")
5. Open `rizqtrack.php` and update `ajax_add_budget()` (Line 3153) to handle new periods
6. Update `ajax_get_budget_vs_actual()` (Line 3267) to calculate for new periods
7. **Test:** Create budgets with new periods and verify calculations are correct

---

### 6. Subscription Management

#### **Add Custom Billing Cycles**
1. Open `templates/dashboard.php`
2. Find the subscription modal (Lines 1023-1122)
3. Locate the billing cycle dropdown
4. Add new `<option>` elements (e.g., "Bi-Annual", "Every 3 months")
5. Open `rizqtrack.php` and update `ajax_add_subscription()` (Line 3469)
6. Update the next billing date calculation logic to handle new cycles
7. Save both files
8. **Test:** Create subscriptions with new billing cycles and verify renewal dates

#### **Change Subscription Reminder Days**
1. Open `templates/dashboard.php`
2. Find the subscription modal (Lines 1023-1122)
3. Locate the reminder days input field
4. Change the default `value` attribute
5. Save the file
6. **Test:** Create a subscription and verify default reminder days are set

#### **Modify Subscription Renewal Logic**
1. Open `rizqtrack.php`
2. Navigate to `ajax_renew_subscription()` function (Line 3584)
3. Find the next billing date calculation
4. Modify the logic as needed (e.g., change how dates are calculated)
5. Save the file
6. **Test:** Renew a subscription and verify next billing date is correct

---

### 7. KPI Dashboard

#### **Add New KPI Metric**
1. Open `rizqtrack.php`
2. Navigate to `ajax_get_kpi_data()` function (Line 1226)
3. Add your new KPI calculation logic
4. Add the new KPI to the returned data array
5. Open `templates/dashboard.php` (Lines 48-133)
6. Add a new KPI card with appropriate icon and structure
7. Open `assets/js/app.js`
8. Update the KPI rendering logic to display your new metric
9. **Test:** Refresh dashboard and verify new KPI displays correctly

#### **Change KPI Icons**
1. Open `templates/dashboard.php`
2. Find the KPI cards section (Lines 48-133)
3. Locate the icon element (e.g., Line 50: `<div class="kpi-icon income">⬆️</div>`)
4. Replace the emoji with your desired icon
5. Save the file
6. **Test:** Refresh dashboard and verify new icon appears

#### **Modify KPI Colors/Styling**
1. Open `assets/css/style.css`
2. Search for "kpi" to find KPI-related styles
3. Locate the KPI card styling section
4. Modify colors, backgrounds, borders as needed
5. Save the file
6. **Test:** Refresh dashboard and verify styling changes

---

### 8. Charts & Analytics

#### **Change Chart Colors**
1. Open `assets/js/app.js`
2. Search for Chart.js configuration (around Lines 900-1800)
3. Find the color arrays (e.g., `backgroundColor`, `borderColor`)
4. Replace colors with your desired hex/rgb values
5. Save the file
6. **Test:** Refresh dashboard and verify chart colors have changed

#### **Add New Chart Type**
1. Open `templates/dashboard.php`
2. Add a new chart container in the financial overview section (Lines 221-330)
3. Open `assets/js/app.js`
4. Add a new chart rendering function using Chart.js API
5. Update `ajax_get_chart_data()` in `rizqtrack.php` (Line 1079) if new data is needed
6. Call your new chart function on page load and data refresh
7. **Test:** Refresh dashboard and verify new chart displays correctly

#### **Modify Pareto Analysis Threshold**
1. Open `assets/js/app.js`
2. Search for "Pareto" or "80/20"
3. Find the threshold calculation logic
4. Change the percentage (e.g., from 80% to 70%)
5. Save the file
6. **Test:** View Pareto chart and verify threshold line has moved

---

### 9. Report Generation

#### **Change CSV Export Columns**
1. Open `rizqtrack.php`
2. Navigate to `ajax_generate_report()` function (Line 1804)
3. Find the CSV header array and data array
4. Add/remove columns as needed
5. Update the data fetching query if new columns require new data
6. Save the file
7. **Test:** Generate a report and verify CSV contains your changes

#### **Add Custom Report Filters**
1. Open `templates/dashboard.php`
2. Find the report modal (Lines 822-883)
3. Add new filter input fields (e.g., payment method filter)
4. Open `assets/js/app.js`
5. Update the report generation logic to pass new filter values
6. Update `ajax_generate_report()` in `rizqtrack.php` (Line 1804) to apply new filters
7. **Test:** Generate reports with new filters and verify results

---

### 10. Email Reports

#### **Change Email Report Schedule**
1. Open `templates/dashboard.php`
2. Find the email settings modal (Lines 885-970)
3. Locate the schedule dropdown options
4. Add/modify schedule options (e.g., "Bi-Weekly", "Quarterly")
5. Open `rizqtrack.php`
6. Update cron job registration logic in the constructor
7. Update `send_weekly_report()` and `send_monthly_report()` functions as needed
8. **Test:** Save email settings and verify cron jobs are scheduled correctly

#### **Customize Email Template**
1. Open `rizqtrack.php`
2. Find email generation logic in `ajax_send_email_now()` (Line 2120)
3. Locate the email body HTML construction
4. Modify HTML structure, styling, and content
5. Save the file
6. **Test:** Send a test email and verify formatting appears correctly

#### **Add Email Recipients**
1. Open `templates/dashboard.php`
2. Find the email settings modal (Lines 885-970)
3. Add additional email input fields if needed
4. Open `rizqtrack.php` and update `ajax_save_email_settings()` (Line 1940)
5. Update email sending logic to include multiple recipients
6. **Test:** Send email and verify all recipients receive it

---

### 11. Styling & UI

#### **Change Login Page Colors**
1. Open `assets/css/style.css`
2. Find the login page styles section (Lines 1-200)
3. Locate gradient colors (Line 27: `background: linear-gradient(...)`)
4. Replace color values with your desired colors
5. Update accent colors (Line 45: `background: linear-gradient(90deg, #0891b2...`)
6. Save the file
7. **Test:** View login page and verify color changes

#### **Modify Dashboard Layout**
1. Open `assets/css/style.css`
2. Search for dashboard layout styles
3. Modify grid layouts, spacing, widths as needed
4. For major structural changes, also update `templates/dashboard.php`
5. Save files
6. **Test:** View dashboard on different screen sizes to ensure responsiveness

#### **Change Button Styles**
1. Open `assets/css/style.css`
2. Search for button styles (around Lines 2100-2300)
3. Modify colors, borders, hover effects as needed
4. Save the file
5. **Test:** Interact with buttons across the dashboard to verify changes

#### **Update Icons/Emojis**
1. Open `templates/dashboard.php`
2. Search for the emoji/icon you want to change
3. Replace with your desired emoji or icon
4. If using icon library (e.g., Font Awesome), ensure it's enqueued in `rizqtrack.php`
5. Save files
6. **Test:** View dashboard and verify new icons appear

---

### 12. Navigation & UX

#### **Change Quick Nav Menu Items**
1. Open `templates/dashboard.php`
2. Find the quick navigation section (Lines 3-20)
3. Add/remove/modify `<a>` elements with appropriate href and icons
4. Save the file
5. **Test:** Open nav menu and verify items navigate correctly

#### **Modify Floating Action Button (FAB) Behavior**
1. Open `assets/js/app.js`
2. Search for FAB click event handler
3. Modify the action (e.g., open different modal, scroll to different section)
4. Save the file
5. **Test:** Click FAB and verify new behavior

#### **Change Section Navigation Arrow Behavior**
1. Open `assets/js/app.js`
2. Search for section navigation arrow event handlers
3. Modify scroll behavior, target sections, or animation speed
4. Save the file
5. **Test:** Click arrows and verify scrolling behavior

---

### 13. Motivational Quotes

#### **Add Custom Quotes**
1. Open `assets/js/app.js`
2. Search for the quotes array
3. Add new quote objects with `text` and `source` properties
4. Save the file
5. **Test:** Click refresh quote button and verify new quotes appear

#### **Change Quote Rotation Timing**
1. Open `assets/js/app.js`
2. Search for quote rotation timer/interval
3. Modify the interval duration (milliseconds)
4. Save the file
5. **Test:** Wait and observe quote rotation timing

---

### 14. Progressive Web App (PWA)

#### **Modify Cache Strategy**
1. Open `assets/sw.js`
2. Find the fetch event listener
3. Modify the caching strategy (e.g., cache-first, network-first, stale-while-revalidate)
4. Update cache name and version if needed
5. Save the file
6. **Test:** Clear cache, install PWA, and verify offline behavior

#### **Change App Icons**
1. Create new icon images in various sizes (192x192, 512x512, etc.)
2. Save icons in `assets/` directory
3. Open `assets/manifest.json`
4. Update icon paths in the `icons` array
5. Save the file
6. **Test:** Reinstall PWA and verify new icons appear

#### **Update App Name/Description**
1. Open `assets/manifest.json`
2. Modify `name`, `short_name`, and `description` properties
3. Save the file
4. **Test:** Reinstall PWA and verify new name/description in app info

---

### 15. Database & Backend

#### **Add New Database Column**
1. Open `rizqtrack.php`
2. Find the `activate()` method
3. Add a new database migration to alter the table and add your column
4. Increment the plugin version number in the header (Line 6)
5. Save the file
6. Deactivate and reactivate the plugin to run migration
7. Update CRUD functions to handle the new column
8. **Test:** Add/edit records and verify new column is saved/retrieved

#### **Modify SQL Queries**
1. Open `rizqtrack.php`
2. Find the function containing the query you want to modify
3. Update the SQL query using WordPress $wpdb methods
4. Test the query for SQL injection vulnerabilities
5. Save the file
6. **Test:** Perform actions that trigger the query and verify results

#### **Add New AJAX Endpoint**
1. Open `rizqtrack.php`
2. In the constructor (Line 33), add new action hook:
   ```php
   add_action('wp_ajax_rizqtrack_your_endpoint', [$this, 'ajax_your_endpoint']);
   ```
3. Create a new method `ajax_your_endpoint()` in the class
4. Add nonce verification and capability checks
5. Implement your logic and return JSON response
6. Open `assets/js/app.js` and add AJAX call to your new endpoint
7. **Test:** Trigger the action and verify AJAX call succeeds

---

### 16. Security

#### **Change Nonce Verification**
1. Open `rizqtrack.php`
2. Find nonce generation in `enqueue_assets()` or `enqueue_frontend_assets()`
3. Verify all AJAX functions include:
   ```php
   check_ajax_referer('rizqtrack_nonce', 'nonce');
   ```
4. **Note:** Do not remove or weaken nonce checks
5. **Test:** Perform actions and verify nonce validation works

#### **Add Capability Checks**
1. Open `rizqtrack.php`
2. Find the AJAX function you want to protect
3. Add capability check after nonce verification:
   ```php
   if (!current_user_can('read')) {
       wp_send_json_error('Insufficient permissions');
   }
   ```
4. Save the file
5. **Test:** Test with users of different roles to verify permissions

---

### 17. Cron Jobs & Automation

#### **Modify Cron Schedule**
1. Open `rizqtrack.php`
2. Find cron job registration in constructor or REST API registration
3. Modify the schedule (e.g., from weekly to daily)
4. Update WordPress cron schedule
5. Save the file
6. Clear WordPress cron cache
7. **Test:** Wait for scheduled time or trigger manually and verify execution

#### **Add Cron Logging**
1. Cron logging is already implemented in `wp_rizqtrack_cron_logs` table
2. Open `rizqtrack.php`
3. Find email report functions (Line 2120+)
4. Review existing logging logic
5. Add additional log entries as needed
6. **Test:** Run cron jobs and check `wp_rizqtrack_cron_logs` table

---

### 18. Trash Management

#### **Change Trash Retention Period**
1. Currently, trash items are permanent until manually deleted
2. To add auto-deletion after X days:
3. Open `rizqtrack.php`
4. Create a new cron job to check trash items older than X days
5. Add deletion logic in the cron function
6. Register the cron job in the constructor
7. **Test:** Add items to trash, wait for retention period, verify auto-deletion

#### **Modify Trash Display**
1. Open `templates/dashboard.php`
2. Find trash section (Lines 589-616)
3. Modify table columns, layout, or styling
4. Update corresponding JavaScript in `assets/js/app.js`
5. Save files
6. **Test:** View trash section and verify display changes

---

### 19. Advanced Filtering

#### **Add Custom Filter Options**
1. Open `templates/dashboard.php`
2. Find filter controls (Lines 221-270)
3. Add new filter input fields (e.g., payment method, amount range)
4. Open `assets/js/app.js`
5. Update filter handling logic to process new filters
6. Update data fetching functions to apply new filters
7. **Test:** Apply filters and verify results are filtered correctly

#### **Modify Filter Persistence**
1. Open `rizqtrack.php`
2. Navigate to `ajax_save_category_filters()` (Line 1062)
3. Modify what filter preferences are saved
4. Update `ajax_get_category_filters()` (Line 1053) to return saved preferences
5. **Test:** Apply filters, refresh page, and verify filters persist

---

## Quick Reference

### Key File Locations

| File | Purpose | Lines |
|------|---------|-------|
| `rizqtrack.php` | Main plugin file with all backend logic | 3,948 |
| `templates/dashboard.php` | Main dashboard HTML template | 1,149 |
| `assets/js/app.js` | Main JavaScript application logic | 4,048 |
| `assets/css/style.css` | Complete styling for the application | 4,456 |
| `assets/sw.js` | Service Worker for PWA functionality | 174 |
| `assets/manifest.json` | PWA manifest configuration | ~50 |
| `assets/.htaccess` | Cache control headers | ~20 |

### Common Code Patterns

#### **Adding a New AJAX Endpoint**
```php
// 1. Register action in constructor
add_action('wp_ajax_rizqtrack_my_action', [$this, 'ajax_my_action']);

// 2. Create method
public function ajax_my_action() {
    check_ajax_referer('rizqtrack_nonce', 'nonce');

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('Not authenticated');
    }

    // Your logic here

    wp_send_json_success($data);
}
```

#### **Making an AJAX Call from Frontend**
```javascript
jQuery.ajax({
    url: rizqtrackData.ajaxUrl,
    type: 'POST',
    data: {
        action: 'rizqtrack_my_action',
        nonce: rizqtrackData.nonce,
        // your data
    },
    success: function(response) {
        if (response.success) {
            // Handle success
        }
    }
});
```

#### **Adding a Database Migration**
```php
// In activate() method
global $wpdb;
$table_name = $wpdb->prefix . 'rizqtrack_tablename';
$wpdb->query("ALTER TABLE $table_name ADD COLUMN new_column VARCHAR(255)");
```

### Testing Checklist

After making any modification, always test:
- [ ] Functionality works as expected
- [ ] No JavaScript console errors
- [ ] No PHP errors in debug.log
- [ ] Responsive design on mobile/tablet/desktop
- [ ] User data isolation (test with multiple users)
- [ ] Security (nonce verification, capability checks)
- [ ] Database changes applied correctly
- [ ] Browser cache cleared for CSS/JS changes

### Common Gotchas

1. **Cache Issues:** After modifying JS/CSS, clear browser cache or increment version in `enqueue_assets()`
2. **Database Changes:** Always create migrations, don't modify database manually
3. **User Data:** All queries must filter by `user_id` for data isolation
4. **Nonces:** Never remove nonce verification from AJAX endpoints
5. **Chart.js Updates:** Charts require data in specific formats; check Chart.js documentation
6. **Responsive Design:** Always test on multiple screen sizes after CSS changes
7. **Cron Jobs:** Use REST API endpoints for cron, not wp-cron (unreliable)

---

## Support & Resources

- **Chart.js Documentation:** https://www.chartjs.org/docs/
- **WordPress Developer Resources:** https://developer.wordpress.org/
- **WordPress Coding Standards:** https://developer.wordpress.org/coding-standards/
- **Service Worker API:** https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API

---

**End of Maintenance Guide**
