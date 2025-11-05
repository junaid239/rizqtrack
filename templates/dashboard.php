<div class="wrap rizqtrack-dashboard">

    <div class="motivational-quote-card" id="motivation-card">
        <div class="quote-icon">💡</div>
        <div class="quote-content">
            <div class="quote-wrapper">
                <p class="quote-text" id="quote-text">Loading...</p>
                <p class="quote-source" id="quote-source"></p>
            </div>
            <button class="btn-refresh-quote" id="refresh-quote" title="Get new quote">🔄</button>
        </div>
    </div>

    <div class="kpi-section" id="kpi-container">
        <div class="kpi-card">
            <div class="kpi-icon income">⬆️</div>
            <div class="kpi-content">
                <span class="kpi-label">Total Income</span>
                <span class="kpi-value" id="kpi-income">Loading...</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon expense">⬇️</div>
            <div class="kpi-content">
                <span class="kpi-label">Total Expense</span>
                <span class="kpi-value" id="kpi-expense">Loading...</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon savings">💰</div>
            <div class="kpi-content">
                <span class="kpi-label">Net Savings</span>
                <span class="kpi-value" id="kpi-savings">Loading...</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon transaction-count">📊</div>
            <div class="kpi-content">
                <span class="kpi-label">Total Transactions</span>
                <span class="kpi-value" id="kpi-transaction-count">Loading...</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon avg-transaction">📈</div>
            <div class="kpi-content">
                <span class="kpi-label">Avg Transaction</span>
                <span class="kpi-value" id="kpi-avg-transaction">Loading...</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon top-spend">🎯</div>
            <div class="kpi-content">
                <span class="kpi-label">Top Category</span>
                <span class="kpi-value" id="kpi-top-category">Loading...</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon frequent-category">🔁</div>
            <div class="kpi-content">
                <span class="kpi-label">Most Frequent</span>
                <span class="kpi-value" id="kpi-most-frequent-category">Loading...</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon days-no-spend">💤</div>
            <div class="kpi-content">
                <span class="kpi-label">Days No Spending</span>
                <span class="kpi-value" id="kpi-days-without-spending">Loading...</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon busiest-day">🔥</div>
            <div class="kpi-content">
                <span class="kpi-label">Busiest Spending Day</span>
                <span class="kpi-value" id="kpi-busiest-day">Loading...</span>
            </div>
        </div>
    </div>

    <div class="rizqtrack-card transaction-form-card" style="margin-top: 32px;">
        <h2 class="section-title">Add Transaction</h2>
        <form id="transaction-form" class="transaction-form">
            <div class="form-row">
                <div class="form-group type-toggle">
                    <label>Transaction Type</label>
                    <div class="toggle-buttons">
                        <button type="button" class="toggle-btn active" data-type="expense">
                            ⬇️ Expense (Debit)
                        </button>
                        <button type="button" class="toggle-btn" data-type="income">
                            ⬆️ Income (Credit)
                        </button>
                    </div>
                    <input type="hidden" name="type" id="transaction-type" value="expense">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="amount">Amount <span class="required">*</span></label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label for="date">Date <span class="required">*</span></label>
                    <input type="date" id="date" name="date" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category">Category <span class="required">*</span></label>
                    <select id="category" name="category_id" required>
                        <option value="">Select Category</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="payment-method">Payment Method <span class="required">*</span></label>
                    <select id="payment-method" name="payment_method" required>
                        <option value="UPI">UPI</option>
                        <option value="Cash">Cash</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Debit Card">Debit Card</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group full-width">
                    <label for="description">Description (Optional)</label>
                    <textarea id="description" name="description" maxlength="255" rows="3" placeholder="Add a note about this transaction..."></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Transaction</button>
            </div>
        </form>
    </div>

    <div class="visualization-section" style="margin-top: 32px;">
        <h2 class="section-title">Financial Overview</h2>

        <!-- Unified Filters Section -->
        <div class="unified-filters">
            <div class="filter-group">
                <label class="filter-label">📅 Time Period:</label>
                <div class="chart-filters">
                    <button class="filter-btn" data-filter="7">7 Days</button>
                    <button class="filter-btn" data-filter="15">15 Days</button>
                    <button class="filter-btn active" data-filter="30">30 Days</button>
                    <button class="filter-btn" data-filter="60">2 Months</button>
                    <button class="filter-btn" data-filter="90">3 Months</button>
                    <button class="filter-btn" data-filter="120">4 Months</button>
                    <button class="filter-btn" data-filter="150">5 Months</button>
                    <button class="filter-btn" data-filter="180">6 Months</button>
                    <button class="filter-btn" data-filter="210">7 Months</button>
                    <button class="filter-btn" data-filter="240">8 Months</button>
                    <button class="filter-btn" data-filter="270">9 Months</button>
                    <button class="filter-btn" data-filter="300">10 Months</button>
                    <button class="filter-btn" data-filter="330">11 Months</button>
                    <button class="filter-btn" data-filter="365">1 Year</button>
                </div>
            </div>

            <div class="filter-group">
                <label class="filter-label">🏷️ Categories:</label>
                <div class="chart-slicer" id="category-slicers">
                    <!-- Power BI-style category filters will be rendered here -->
                </div>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-card">
                <h3>📊 Category Breakdown</h3>
                <div class="chart-wrapper">
                    <canvas id="category-chart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3>🔄 Most Frequent Categories</h3>
                <div class="chart-wrapper">
                    <canvas id="top-frequent-chart"></canvas>
                </div>
            </div>

            <div class="chart-card full-width-chart">
                <h3>📉 Spending Over Time</h3>
                <div class="chart-wrapper">
                    <canvas id="spending-trend-chart"></canvas>
                </div>
            </div>
        </div>

        <div class="rizqtrack-card transactions-card">
            <h3>All Transactions</h3>
            
            <div class="filter-bar" id="transaction-filter-bar">
                <div class="form-group filter-search">
                    <label for="filter-search">Search</label>
                    <input type="text" id="filter-search" placeholder="Search description...">
                </div>
                <div class="form-group">
                    <label for="filter-category">Category</label>
                    <select id="filter-category">
                        <option value="0">All Categories</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter-start-date">Start Date</label>
                    <input type="date" id="filter-start-date">
                </div>
                <div class="form-group">
                    <label for="filter-end-date">End Date</label>
                    <input type="date" id="filter-end-date">
                </div>
                <div class="filter-actions">
                    <button class="btn btn-secondary btn-sm" id="filter-apply">Apply</button>
                    <button class="btn btn-secondary btn-sm" id="filter-reset">Reset</button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="transactions-table" class="rizqtrack-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="transactions-tbody">
                        <tr class="loading-row">
                            <td colspan="6">Loading transactions...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pagination-controls" id="pagination-container"></div>
            
        </div>
    </div>

    <div class="goals-section">
        <div class="section-header">
            <h2 class="section-title">Financial Goals</h2>
            <button class="btn btn-secondary" id="add-goal-btn">➕ Add New Goal</button>
        </div>

        <div class="goals-overview-card">
            <div class="goals-overview-header">
                <h3>📊 Goals Overview</h3>
                <div class="goals-stats-badges">
                    <div class="stat-badge">
                        <span class="badge-label">Total Goals</span>
                        <span class="badge-value" id="total-goals-count">0</span>
                    </div>
                    <div class="stat-badge success">
                        <span class="badge-label">Completed</span>
                        <span class="badge-value" id="completed-goals-count">0</span>
                    </div>
                </div>
            </div>

            <div class="overall-progress-section">
                <div class="progress-header">
                    <div class="progress-amounts">
                        <div class="amount-item">
                            <span class="amount-label">Total Saved</span>
                            <span class="amount-value current" id="total-saved-amount">₹0.00</span>
                        </div>
                        <div class="amount-divider">/</div>
                        <div class="amount-item">
                            <span class="amount-label">Total Target</span>
                            <span class="amount-value target" id="total-target-amount">₹0.00</span>
                        </div>
                    </div>
                    <div class="progress-percentage" id="overall-progress-percentage">0%</div>
                </div>

                <div class="overall-progress-bar-container">
                    <div class="overall-progress-bar">
                        <div class="progress-bar-fill" id="overall-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="progress-markers">
                        <span class="marker" style="left: 25%">25%</span>
                        <span class="marker" style="left: 50%">50%</span>
                        <span class="marker" style="left: 75%">75%</span>
                    </div>
                </div>

                <div class="progress-insights">
                    <div class="insight-item">
                        <span class="insight-icon">💰</span>
                        <div class="insight-content">
                            <span class="insight-label">Remaining to Save</span>
                            <span class="insight-value" id="remaining-amount">₹0.00</span>
                        </div>
                    </div>
                    <div class="insight-item">
                        <span class="insight-icon">📈</span>
                        <div class="insight-content">
                            <span class="insight-label">Average Progress</span>
                            <span class="insight-value" id="average-progress">0%</span>
                        </div>
                    </div>
                    <div class="insight-item">
                        <span class="insight-icon">🎯</span>
                        <div class="insight-content">
                            <span class="insight-label">Goals On Track</span>
                            <span class="insight-value" id="goals-on-track">0/0</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="individual-goals-header">
            <h3>Individual Goals</h3>
            <div class="goals-filter">
                <button class="filter-chip active" data-filter="all">All</button>
                <button class="filter-chip" data-filter="active">Active</button>
                <button class="filter-chip" data-filter="near-complete">Near Complete (75%+)</button>
            </div>
        </div>

        <div id="goals-container" class="goals-grid">
            <div class="loading-message">Loading goals...</div>
        </div>
    </div>

    <div class="challenges-section" style="margin-top: 32px;">
        <div class="section-header">
            <h2 class="section-title">🎯 Savings Challenges</h2>
            <button class="btn btn-secondary" id="start-challenge-btn">➕ Start Challenge</button>
        </div>

        <div id="challenges-container" class="challenges-grid">
            <div class="loading-message">Loading challenges...</div>
        </div>

        <div class="challenge-templates-info" style="margin-top: 16px; padding: 16px; background: #f0f9ff; border-radius: 8px; border-left: 4px solid #0891b2;">
            <strong>💡 Available Challenges:</strong>
            <ul style="margin: 8px 0 0 20px; color: #1f2937;">
                <li><strong>52-Week Savings</strong> - Save incrementally each week (₹13,780 total)</li>
                <li><strong>30-Day No-Spend</strong> - Minimize unnecessary spending for a month</li>
                <li><strong>₹1000/Month Challenge</strong> - Save ₹1000 monthly for a year (₹12,000 total)</li>
                <li><strong>Emergency Fund</strong> - Build a 3-month emergency fund</li>
            </ul>
        </div>
    </div>

    <div class="budget-section">
        <div class="section-header">
            <h2 class="section-title">💰 Budget Management</h2>
            <button class="btn btn-secondary" id="add-budget-btn">➕ Set Budget</button>
        </div>

        <div id="budget-alerts-container" class="budget-alerts" style="display: none;">
            <!-- Budget alerts will appear here -->
        </div>

        <div id="budget-container" class="budget-grid">
            <div class="loading-message">Loading budgets...</div>
        </div>

        <div class="budget-info" style="margin-top: 16px; padding: 16px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
            <strong>💡 Budget Tips:</strong>
            <ul style="margin: 8px 0 0 20px; color: #1f2937;">
                <li>Set realistic budgets for each expense category</li>
                <li>Get alerts when spending reaches 80% of budget</li>
                <li>Track budget vs actual spending in real-time</li>
                <li>Monthly or yearly budget periods available</li>
            </ul>
        </div>
    </div>

    <div class="management-section">
        <h2 class="section-title">Data Management & Reports</h2>
        <div class="action-cards">
            <div class="action-card" id="manage-categories-card">
                <div class="action-icon">🏷️</div>
                <h3>Manage Categories</h3>
                <p>Add, edit, or delete transaction categories</p>
                <button class="btn btn-secondary">Manage Categories</button>
            </div>

            <div class="action-card" id="generate-report-card">
                <div class="action-icon">📋</div>
                <h3>Generate Report</h3>
                <p>Export your financial data to CSV</p>
                <button class="btn btn-secondary">Generate Report</button>
            </div>

            <div class="action-card" id="email-report-card">
                <div class="action-icon">📧</div>
                <h3>Email Reports</h3>
                <p>Configure automatic email reports</p>
                <button class="btn btn-secondary">Configure Email</button>
            </div>
        </div>
    </div>

    <div class="trash-section">
        <div class="collapsible-header" id="trash-header">
            <h2 class="section-title">🗑️ Trash</h2>
            <span class="toggle-icon">▼</span>
        </div>
        <div id="trash-content" class="trash-content" style="display: none;">
            <div class="table-responsive">
                <table id="trash-table" class="rizqtrack-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="trash-tbody">
                        <tr class="loading-row">
                            <td colspan="6">Loading trash...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="edit-transaction-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Transaction</h2>
            <span class="close">&times;</span>
        </div>
        <form id="edit-transaction-form">
            <input type="hidden" id="edit-transaction-id">

            <div class="form-group">
                <label for="edit-amount">Amount</label>
                <input type="number" id="edit-amount" step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label for="edit-date">Date</label>
                <input type="date" id="edit-date" required>
            </div>

            <div class="form-group">
                <label for="edit-category">Category</label>
                <select id="edit-category" required>
                    <option value="">Select Category</option>
                </select>
            </div>

            <div class="form-group">
                <label for="edit-payment-method">Payment Method</label>
                <select id="edit-payment-method" required>
                    <option value="UPI">UPI</option>
                    <option value="Cash">Cash</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="Debit Card">Debit Card</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="edit-description">Description</label>
                <textarea id="edit-description" maxlength="255" rows="3"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Transaction</button>
            </div>
        </form>
    </div>
</div>

<div id="categories-modal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2>🏷️ Manage Categories</h2>
            <span class="close">&times;</span>
        </div>

        <div class="modal-body">
            <div class="add-category-section">
                <h3>Add New Category</h3>
                <form id="add-category-form" class="inline-form">
                    <div class="form-row">
                        <input type="text" id="new-category-name" placeholder="Category Name" required>
                        <select id="new-category-type" required>
                            <option value="">Type</option>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                            <option value="both">Both</option>
                        </select>
                        <input type="text" id="new-category-emoji" placeholder="📌" maxlength="10">
                        <button type="submit" class="btn btn-primary">Add</button>
                    </div>
                </form>
            </div>

            <div class="categories-list-section">
                <h3>Your Categories</h3>
                <div id="categories-list" class="categories-list">
                    <div class="loading-message">Loading categories...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="goal-modal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 id="goal-modal-title">Add New Goal</h2>
            <span class="close">&times;</span>
        </div>
        <form id="goal-form">
            <input type="hidden" id="goal-id">

            <div class="form-row">
                <div class="form-group">
                    <label for="goal-name">Goal Name <span class="required">*</span></label>
                    <input type="text" id="goal-name" maxlength="200" required placeholder="e.g., Emergency Fund, Vacation">
                </div>

                <div class="form-group">
                    <label for="goal-category">Category <span class="required">*</span></label>
                    <select id="goal-category" required>
                        <option value="">Select Category</option>
                        <option value="savings">💰 Savings</option>
                        <option value="investment">📈 Investment</option>
                        <option value="purchase">🛒 Purchase</option>
                        <option value="emergency">🚨 Emergency Fund</option>
                        <option value="education">🎓 Education</option>
                        <option value="travel">✈️ Travel</option>
                        <option value="home">🏠 Home</option>
                        <option value="vehicle">🚗 Vehicle</option>
                        <option value="other">📌 Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="goal-target">Target Amount <span class="required">*</span></label>
                    <input type="number" id="goal-target" step="0.01" min="0" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label for="goal-priority">Priority <span class="required">*</span></label>
                    <select id="goal-priority" required>
                        <option value="">Select Priority</option>
                        <option value="high">🔴 High Priority</option>
                        <option value="medium">🟡 Medium Priority</option>
                        <option value="low">🟢 Low Priority</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="goal-deadline">Target Deadline (Optional)</label>
                    <input type="date" id="goal-deadline">
                    <small id="monthly-savings-info" style="color: var(--text-gray); display: none; margin-top: 8px;"></small>
                </div>

                <div class="form-group">
                    <label for="goal-start-date">Start Date (Optional)</label>
                    <input type="date" id="goal-start-date">
                </div>
            </div>

            <div class="form-group">
                <label for="goal-notes">Notes / Description (Optional)</label>
                <textarea id="goal-notes" rows="3" maxlength="500" placeholder="Add any notes or details about this goal..."></textarea>
                <small style="color: var(--text-gray);">500 characters maximum</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Goal</button>
            </div>
        </form>
    </div>
</div>

<div id="contribute-modal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header">
            <h2>💰 Add Contribution</h2>
            <span class="close">&times;</span>
        </div>
        <form id="contribute-form">
            <input type="hidden" id="contribute-goal-id">

            <p id="contribute-goal-name" class="goal-name-display"></p>

            <div class="form-group">
                <label for="contribute-amount">Contribution Amount</label>
                <input type="number" id="contribute-amount" step="0.01" min="0.01" required>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Contribution</button>
            </div>
        </form>
    </div>
</div>

<div id="report-modal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2>📋 Generate Financial Report</h2>
            <span class="close">&times;</span>
        </div>
        <form id="report-form">
            <div class="form-group">
                <label>Select Timeframe</label>
                <select id="report-timeframe">
                    <option value="custom">Custom Date Range</option>
                    <option value="30">Last 30 Days</option>
                    <option value="90">Last 3 Months</option>
                    <option value="180">Last 6 Months</option>
                    <option value="365">Year to Date</option>
                </select>
            </div>

            <div id="custom-date-range" class="form-row">
                <div class="form-group">
                    <label for="report-start-date">Start Date</label>
                    <input type="date" id="report-start-date">
                </div>

                <div class="form-group">
                    <label for="report-end-date">End Date</label>
                    <input type="date" id="report-end-date">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="report-category">Filter by Category (Optional)</label>
                    <select id="report-category">
                        <option value="all">All Categories</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="report-type">Transaction Type (Optional)</label>
                    <select id="report-type">
                        <option value="all">All Transactions</option>
                        <option value="income">Income Only</option>
                        <option value="expense">Expense Only</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Output Format</label>
                <select id="report-format">
                    <option value="csv">CSV (Excel Compatible)</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate Report</button>
            </div>
        </form>
    </div>
</div>

<div id="email-report-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>📧 Configure Email Reports</h2>
            <span class="close">&times;</span>
        </div>
        <form id="email-report-form">
            <div class="form-group">
                <label for="email-frequency">Report Frequency</label>
                <select id="email-frequency" required>
                    <option value="none">Disabled</option>
                    <option value="weekly">Weekly (Every Monday)</option>
                    <option value="monthly">Monthly (1st of Month)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email-address">Email Address</label>
                <input type="email" id="email-address" placeholder="your@email.com" required>
                <small>Reports will be sent to this email address</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email-start-date">Report Start Date</label>
                    <input type="date" id="email-start-date" required>
                </div>
                <div class="form-group">
                    <label for="email-end-date">Report End Date</label>
                    <input type="date" id="email-end-date" required>
                </div>
            </div>

            <div class="info-box">
                <strong>📊 Report will include:</strong>
                <ul>
                    <li>📈 All financial charts (Category Breakdown, Frequent Categories, Spending Trend)</li>
                    <li>💰 Income vs Expense summary with date range</li>
                    <li>🎯 Financial goals progress</li>
                    <li>💪 Active savings challenges</li>
                    <li>📊 Budget status and alerts</li>
                    <li>🏆 Top spending categories</li>
                </ul>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary cancel-btn">Cancel</button>
                <button type="button" class="btn btn-success" id="send-email-now-btn">📨 Send Now</button>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<div id="challenge-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>🎯 Start a Savings Challenge</h2>
            <span class="close">&times;</span>
        </div>
        <form id="challenge-form">
            <div class="form-group">
                <label for="challenge-type">Select Challenge <span class="required">*</span></label>
                <select id="challenge-type" required>
                    <option value="">Choose a challenge...</option>
                    <option value="52_week">💰 52-Week Savings Challenge (₹13,780)</option>
                    <option value="no_spend">🚫 30-Day No-Spend Challenge</option>
                    <option value="1000_month">📊 Save ₹1000/Month for a Year (₹12,000)</option>
                    <option value="emergency_fund">🚨 3-Month Emergency Fund</option>
                    <option value="custom">🎯 Custom Challenge</option>
                </select>
            </div>

            <div id="emergency-fund-amount" style="display: none;">
                <div class="form-group">
                    <label for="challenge-target">Target Amount (₹)</label>
                    <input type="number" id="challenge-target" step="any" min="1" placeholder="30000">
                    <small>Enter your 3-month expense amount</small>
                </div>
            </div>

            <div id="custom-challenge-fields" style="display: none;">
                <div class="form-group">
                    <label for="custom-challenge-name">Challenge Name <span class="required">*</span></label>
                    <input type="text" id="custom-challenge-name" placeholder="My Savings Goal" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="custom-challenge-amount">Target Amount (₹) <span class="required">*</span></label>
                    <input type="number" id="custom-challenge-amount" step="any" min="1" placeholder="10000">
                </div>
                <div class="form-group">
                    <label for="custom-challenge-weeks">Duration (weeks) <span class="required">*</span></label>
                    <input type="number" id="custom-challenge-weeks" step="1" min="1" max="104" placeholder="12">
                    <small>Choose duration between 1-104 weeks (up to 2 years)</small>
                </div>
            </div>

            <div class="info-box" id="challenge-description">
                <p style="margin: 0; color: #6b7280;">Select a challenge to see details</p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary">Start Challenge</button>
            </div>
        </form>
    </div>
</div>

<div id="budget-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="budget-modal-title">💰 Set Budget</h2>
            <span class="close">&times;</span>
        </div>
        <form id="budget-form">
            <input type="hidden" id="budget-id">

            <div class="form-group">
                <label for="budget-category">Category <span class="required">*</span></label>
                <select id="budget-category" required>
                    <option value="">Choose a category...</option>
                    <!-- Categories will be loaded dynamically -->
                </select>
            </div>

            <div class="form-group">
                <label for="budget-amount">Budget Amount (₹) <span class="required">*</span></label>
                <input type="number" id="budget-amount" step="any" min="1" required placeholder="5000">
            </div>

            <div class="form-group">
                <label for="budget-period">Period <span class="required">*</span></label>
                <select id="budget-period" required>
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </div>

            <div class="form-group">
                <label for="budget-threshold">Alert Threshold (%)</label>
                <input type="number" id="budget-threshold" min="50" max="100" value="80" step="5">
                <small>Get notified when spending reaches this percentage</small>
            </div>

            <div class="form-group checkbox-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="budget-rollover">
                    <span class="checkbox-text">Rollover unused budget to next period</span>
                </label>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Budget</button>
            </div>
        </form>
    </div>
</div>
