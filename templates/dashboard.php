<div class="wrap rizqtrack-dashboard">

    <div class="motivational-quote-card" id="motivation-card">
        <div class="quote-icon">💡</div>
        <div class="quote-content">
            <p class="quote-text" id="quote-text">Loading...</p>
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
                <span class="kpi-value small" id="kpi-top-category">Loading...</span>
            </div>
        </div>
    </div>

    <div class="rizqtrack-card transaction-form-card">
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
                <button type="submit" class="btn btn-primary">✅ Save Transaction</button>
            </div>
        </form>
    </div>

    <div class="visualization-section">
        <h2 class="section-title">Financial Overview</h2>

        <div class="chart-filters">
            <button class="filter-btn" data-filter="7">7 Days</button>
            <button class="filter-btn active" data-filter="30">30 Days</button>
            <button class="filter-btn" data-filter="90">3 Months</button>
            <button class="filter-btn" data-filter="180">6 Months</button>
            <button class="filter-btn" data-filter="365">1 Year</button>
        </div>

        <div class="charts-container">
            <div class="chart-card">
                <h3>📊 Category Breakdown</h3>
                <div class="chart-wrapper">
                    <canvas id="category-chart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3>📈 Expense vs. Income</h3>
                <div class="chart-wrapper">
                    <canvas id="income-expense-chart"></canvas>
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
                    <input type="text" id="filter-search" placeholder="Search description...">
                </div>
                <div class="form-group">
                    <select id="filter-category">
                        <option value="0">All Categories</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="date" id="filter-start-date">
                </div>
                <div class="form-group">
                    <input type="date" id="filter-end-date">
                </div>
                <button class="btn btn-secondary btn-sm" id="filter-apply">Apply</button>
                <button class="btn btn-secondary btn-sm" id="filter-reset">Reset</button>
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
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="goal-modal-title">Add New Goal</h2>
            <span class="close">&times;</span>
        </div>
        <form id="goal-form">
            <input type="hidden" id="goal-id">

            <div class="form-group">
                <label for="goal-name">Goal Name</label>
                <input type="text" id="goal-name" maxlength="200" required placeholder="e.g., Emergency Fund, Vacation">
            </div>

            <div class="form-group">
                <label for="goal-target">Target Amount</label>
                <input type="number" id="goal-target" step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label for="goal-deadline">Deadline (Optional)</label>
                <input type="date" id="goal-deadline">
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
    <div class="modal-content">
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

            <div class="info-box">
                <strong>📊 Report will include:</strong>
                <ul>
                    <li>Income vs Expense summary</li>
                    <li>Top spending categories</li>
                    <li>Financial goals progress</li>
                    <li>Transaction highlights</li>
                </ul>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>
