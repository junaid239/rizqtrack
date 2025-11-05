(function($) {
    'use strict';

    Chart.register(ChartDataLabels); // Register the datalabels plugin

    const RizqTrackApp = {
        charts: {
            category: null,
            incomeExpense: null,
        },
        currentFilter: '30',
        editTransactionData: {},
        currentPage: 1, // For pagination

        init: function() {
            this.charts = {
                category: null,
                incomeExpense: null,
                goalsDonut: null
            };
            this.currentFilter = '30';
            this.editTransactionData = {};

            this.setupEventListeners();
            this.setDefaultFormValues();
            this.loadCategories();
            this.loadKPIData();
            this.loadTransactions(1); // Load page 1
            this.loadChartData();
            this.loadGoals();
        },

        setupEventListeners: function() {
            // Transaction Form
            $('#transaction-form').on('submit', this.handleAddTransaction.bind(this));
            $('.toggle-btn').on('click', this.handleTypeToggle.bind(this));

            // Chart Filters
            $('.filter-btn').on('click', this.handleFilterChange.bind(this));

            // Transaction Actions
            $(document).on('click', '.edit-transaction', this.openEditModal.bind(this));
            $(document).on('click', '.delete-transaction', this.handleDeleteTransaction.bind(this));
            $('#edit-transaction-form').on('submit', this.handleUpdateTransaction.bind(this));
            
            // Pagination Listeners
            $(document).on('click', '#prev-page', () => this.loadTransactions(this.currentPage - 1));
            $(document).on('click', '#next-page', () => this.loadTransactions(this.currentPage + 1));

            // Trash Actions
            $('#trash-header').on('click', this.toggleTrash.bind(this));
            $(document).on('click', '.restore-transaction', this.handleRestoreTransaction.bind(this));
            $(document).on('click', '.permanent-delete', this.handlePermanentDelete.bind(this));
            $(document).on('click', '.restore-goal', this.handleRestoreGoal.bind(this));
            $(document).on('click', '.permanent-delete-goal', this.handlePermanentDeleteGoal.bind(this));

            // Categories
            $('#manage-categories-card').on('click', this.openCategoriesModal.bind(this));
            $('#add-category-form').on('submit', this.handleAddCategory.bind(this));
            $(document).on('click', '.delete-category', this.handleDeleteCategory.bind(this));

            // Goals
            $('#add-goal-btn').on('click', () => this.openGoalModal());
            $('#goal-form').on('submit', this.handleSaveGoal.bind(this));
            $(document).on('click', '.edit-goal', this.handleEditGoal.bind(this));
            $(document).on('click', '.delete-goal', this.handleDeleteGoal.bind(this));
            $(document).on('click', '.contribute-goal', this.openContributeModal.bind(this));
            $('#contribute-form').on('submit', this.handleContribute.bind(this));

            // Report
            $('#generate-report-card').on('click', this.openReportModal.bind(this));
            $('#report-form').on('submit', this.handleGenerateReport.bind(this));
            $('#report-timeframe').on('change', this.handleReportTimeframeChange.bind(this));

            // Modal Controls
            $('.close, .cancel-btn').on('click', this.closeModals.bind(this));
            $(window).on('click', this.handleModalBackdropClick.bind(this));
        },

        setDefaultFormValues: function() {
            const today = new Date().toISOString().split('T')[0];
            $('#date').val(today);
            $('#payment-method').val('UPI');
        },

        handleTypeToggle: function(e) {
            const $btn = $(e.currentTarget);
            const type = $btn.data('type');

            $('.toggle-btn').removeClass('active');
            $btn.addClass('active');
            $('#transaction-type').val(type);

            this.filterCategoriesByType(type);
        },

        filterCategoriesByType: function(type) {
            const $select = $('#category');
            $select.find('option').each(function() {
                const $option = $(this);
                const categoryType = $option.data('type');

                if (!categoryType) {
                    return;
                }

                if (categoryType === 'both' || categoryType === type) {
                    $option.show();
                } else {
                    $option.hide();
                }
            });

            $select.val('');
        },

        loadKPIData: function() {
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_kpi_data',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        $('#kpi-income').text('₹' + this.formatCurrency(data.total_income));
                        $('#kpi-expense').text('₹' + this.formatCurrency(data.total_expense));
                        $('#kpi-savings').text('₹' + this.formatCurrency(data.net_savings));
                        $('#kpi-transaction-count').text(data.transaction_count);
                        $('#kpi-avg-transaction').text('₹' + this.formatCurrency(data.avg_transaction));
                        $('#kpi-top-category').text(data.top_category);
                    }
                },
                error: () => {
                    $('#kpi-income, #kpi-expense, #kpi-savings, #kpi-transaction-count, #kpi-avg-transaction, #kpi-top-category').text('Error');
                }
            });
        },

        loadCategories: function() {
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_categories',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.populateCategorySelects(response.data);
                        this.renderCategoriesList(response.data);
                    }
                }
            });
        },

        populateCategorySelects: function(categories) {
            const selects = ['#category', '#edit-category'];

            selects.forEach(selector => {
                const $select = $(selector);
                $select.find('option:not(:first)').remove();

                categories.forEach(cat => {
                    $select.append(
                        $('<option></option>')
                            .val(cat.id)
                            .text(`${cat.emoji} ${cat.name}`)
                            .data('type', cat.type)
                    );
                });
            });

            this.filterCategoriesByType('expense');
        },

        renderCategoriesList: function(categories) {
            const $list = $('#categories-list');
            $list.empty();

            if (categories.length === 0) {
                $list.html('<div class="loading-message">No categories found</div>');
                return;
            }

            categories.forEach(cat => {
                const isDefault = cat.user_id == 0;
                const badgeHtml = isDefault ? '<span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 8px;">DEFAULT</span>' : '';

                $list.append(`
                    <div class="category-item">
                        <div class="category-info">
                            <span class="category-emoji">${cat.emoji}</span>
                            <div class="category-details">
                                <span class="category-name">${cat.name}${badgeHtml}</span>
                                <span class="category-type">${cat.type}</span>
                            </div>
                        </div>
                        <div class="category-actions">
                            <button class="btn btn-danger btn-sm delete-category" data-id="${cat.id}">Delete</button>
                        </div>
                    </div>
                `);
            });
        },

        handleAddTransaction: function(e) {
            e.preventDefault();

            // Validate form
            const amount = $('#amount').val();
            const date = $('#date').val();
            const categoryId = $('#category').val();
            const paymentMethod = $('#payment-method').val();

            if (!amount || !date || !categoryId || !paymentMethod) {
                this.showNotification('Please fill in all required fields', 'error');
                return;
            }

            const formData = {
                action: 'rizqtrack_add_transaction',
                nonce: rizqtrack.nonce,
                type: $('#transaction-type').val(),
                amount: amount,
                date: date,
                category_id: categoryId,
                payment_method: paymentMethod,
                description: $('#description').val()
            };

            console.log('Submitting transaction:', formData); // Debug log

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    console.log('Response:', response); // Debug log
                    if (response.success) {
                        this.showNotification('Transaction added successfully!', 'success');
                        $('#transaction-form')[0].reset();
                        this.setDefaultFormValues();
                        this.loadKPIData();
                        this.loadTransactions(this.currentPage); // Reload current page
                        this.loadChartData();
                    } else {
                        this.showNotification(response.data.message || 'Failed to add transaction', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', xhr, status, error); // Debug log
                    this.showNotification('Connection error: ' + error, 'error');
                }
            });
        },

        loadTransactions: function(page = 1) {
            this.currentPage = page; // Store the current page

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_recent_transactions',
                    nonce: rizqtrack.nonce,
                    page: this.currentPage // Send the page number
                },
                success: (response) => {
                    if (response.success) {
                        this.renderTransactions(response.data.transactions);
                        // Call the new pagination renderer
                        this.renderPagination(response.data.total); 
                    }
                }
            });
        },

        renderTransactions: function(transactions) {
            const $tbody = $('#transactions-tbody');
            $tbody.empty();

            if (transactions.length === 0) {
                $tbody.html('<tr class="loading-row"><td colspan="6">No transactions found</td></tr>');
                return;
            }

            transactions.forEach(t => {
                const amountClass = t.type === 'income' ? 'amount-positive' : 'amount-negative';
                const amountPrefix = t.type === 'income' ? '+' : '-';

                $tbody.append(`
                    <tr>
                        <td>${this.formatDate(t.date)}</td>
                        <td>${t.category_emoji} ${t.category_name}</td>
                        <td class="${amountClass}">${amountPrefix}${this.formatCurrency(t.amount)}</td>
                        <td>${t.payment_method}</td>
                        <td>${t.description || '-'}</td>
                        <td>
                            <div class="action-btns">
                                <button class="icon-btn edit-transaction" data-id="${t.id}" title="Edit">✏️</button>
                                <button class="icon-btn delete-transaction" data-id="${t.id}" title="Delete">🗑️</button>
                            </div>
                        </td>
                    </tr>
                `);
            });
        },

        renderPagination: function(totalTransactions) {
            const $container = $('#pagination-container');
            $container.empty();

            const limit = 10;
            const totalPages = Math.ceil(totalTransactions / limit);

            if (totalPages <= 1) {
                return; // Don't show pagination if there's only one page
            }

            // "Previous" Button
            if (this.currentPage > 1) {
                $container.append('<button class="btn btn-secondary btn-sm" id="prev-page">⬅️ Previous</button>');
            } else {
                // Add a disabled placeholder to keep layout
                $container.append('<span style="width: 100px;"></span>'); 
            }

            // "Page X of Y" Text
            $container.append(`<span>Page ${this.currentPage} of ${totalPages}</span>`);

            // "Next" Button
            if (this.currentPage < totalPages) {
                $container.append('<button class="btn btn-secondary btn-sm" id="next-page">Next ➡️</button>');
            } else {
                // Add a disabled placeholder
                $container.append('<span style="width: 100px;"></span>'); 
            }
        },

        openEditModal: function(e) {
            const transactionId = $(e.currentTarget).data('id');

            // Find the transaction data from the already loaded transactions if possible
            // This is just a fallback; a better implementation would fetch the single transaction
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_recent_transactions',
                    nonce: rizqtrack.nonce,
                    page: this.currentPage // Check the current page
                },
                success: (response) => {
                    if (response.success) {
                        const transaction = response.data.transactions.find(t => t.id == transactionId);
                        if (transaction) {
                            $('#edit-transaction-id').val(transaction.id);
                            $('#edit-amount').val(transaction.amount);
                            $('#edit-date').val(transaction.date);
                            $('#edit-category').val(transaction.category_id);
                            $('#edit-payment-method').val(transaction.payment_method);
                            $('#edit-description').val(transaction.description);

                            $('#edit-transaction-modal').addClass('active');
                        }
                    }
                }
            });
        },

        handleUpdateTransaction: function(e) {
            e.preventDefault();

            // Find the category type from the selected option
            const categoryType = $('#edit-category option:selected').data('type');

            const formData = {
                action: 'rizqtrack_update_transaction',
                nonce: rizqtrack.nonce,
                id: $('#edit-transaction-id').val(),
                // Set type based on category, default to 'expense'
                type: (categoryType === 'income' || categoryType === 'expense') ? categoryType : 'expense', 
                amount: $('#edit-amount').val(),
                date: $('#edit-date').val(),
                category_id: $('#edit-category').val(),
                payment_method: $('#edit-payment-method').val(),
                description: $('#edit-description').val()
            };

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Transaction updated successfully!', 'success');
                        this.closeModals();
                        this.loadKPIData();
                        this.loadTransactions(this.currentPage); // Reload current page
                        this.loadChartData();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        handleDeleteTransaction: function(e) {
            if (!confirm('Move this transaction to trash?')) return;

            const transactionId = $(e.currentTarget).data('id');

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_delete_transaction',
                    nonce: rizqtrack.nonce,
                    id: transactionId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Transaction moved to trash', 'success');
                        this.loadKPIData();
                        this.loadTransactions(this.currentPage); // Reload current page
                        this.loadChartData();
                        this.loadTrash();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        handleFilterChange: function(e) {
            const $btn = $(e.currentTarget);
            const filter = $btn.data('filter');

            $('.filter-btn').removeClass('active');
            $btn.addClass('active');

            this.currentFilter = filter;
            this.loadChartData();
        },

        loadChartData: function() {
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_chart_data',
                    nonce: rizqtrack.nonce,
                    filter: this.currentFilter
                },
                success: (response) => {
                    if (response.success) {
                        this.renderCategoryChart(response.data.category_data);
                        this.renderIncomeExpenseChart(response.data.income_expense);
                    }
                }
            });
        },

        renderCategoryChart: function(data) {
            const ctx = document.getElementById('category-chart');

            if (this.charts.category) {
                this.charts.category.destroy();
            }

            const labels = data.map(d => `${d.emoji} ${d.name}`);
            const values = data.map(d => parseFloat(d.total));
            const colors = this.generateColors(data.length);

            this.charts.category = new Chart(ctx, {
                type: 'bar', 
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 0 
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y', // This makes it a horizontal bar chart
                    plugins: {
                        legend: {
                            display: false // The legend is redundant with the Y-axis
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    // Format tooltip to show currency
                                    return ` Total: ₹${context.parsed.x.toFixed(2)}`;
                                }
                            }
                        },
                        
                        datalabels: {
                            color: '#000', // Set text color to black
                            anchor: 'end',   // Position label at the end of the bar
                            align: 'end',    // Align text to the end of the bar
                            offset: -8,      // Add some padding from the end
                            font: {
                                weight: 'bold',
                                size: 13
                            },
                            formatter: (value, ctx) => {
                                // Format the number
                                return '₹' + value.toFixed(0); 
                            }
                        }
                        
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            /********************************/
                            /* START: MODIFICATION HERE     */
                            /********************************/
                            grace: '10%', // ADDED: Extends axis 10% beyond max value
                            /********************************/
                            /* END: MODIFICATION HERE       */
                            /********************************/
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value; 
                                },
                                color: '#1f2937' // Sets X-axis text to dark
                            }
                        },
                        y: { 
                            ticks: {
                                color: '#1f2937' // Sets Y-axis (category) text to dark
                            }
                        }
                    }
                }
            });
        },

        renderIncomeExpenseChart: function(data) {
            const ctx = document.getElementById('income-expense-chart');

            if (this.charts.incomeExpense) {
                this.charts.incomeExpense.destroy();
            }

            const income = parseFloat(data.total_income) || 0;
            const expense = parseFloat(data.total_expense) || 0;

            this.charts.incomeExpense = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Income', 'Expense'],
                    datasets: [{
                        data: [income, expense],
                        backgroundColor: ['#10b981', '#ef4444'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            onClick: (e, legendItem, legend) => {
                                const index = legendItem.index;
                                const ci = legend.chart;
                                const meta = ci.getDatasetMeta(0);
                                meta.data[index].hidden = !meta.data[index].hidden;
                                ci.update();
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    return `${context.label}: ₹${context.parsed.toFixed(2)}`;
                                }
                            }
                        },
                        datalabels: {
                            formatter: (value, ctx) => {
                                // Calculate percentage
                                let sum = 0;
                                let dataArr = ctx.chart.data.datasets[0].data;
                                dataArr.map(data => {
                                    sum += data;
                                });
                                let percentage = (value * 100 / sum).toFixed(1) + '%';
                                return percentage;
                            },
                            color: '#fff', // White text
                            font: {
                                weight: 'bold',
                                size: 14,
                            },
                            // Add a little shadow for readability
                            textShadowBlur: 2,
                            textShadowColor: 'rgba(0, 0, 0, 0.5)' // Black shadow
                        }
                    }
                }
            });
        },

        generateColors: function(count) {
            const colors = [
                '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                '#ec4899', '#14b8a6', '#f97316', '#06b6d4', '#84cc16'
            ];

            const result = [];
            for (let i = 0; i < count; i++) {
                result.push(colors[i % colors.length]);
            }
            return result;
        },


        loadGoals: function() {
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_goals',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderGoals(response.data);
                        this.renderGoalsOverview(response.data);
                    }
                }
            });
        },

        renderGoals: function(goals) {
            const $container = $('#goals-container');
            $container.empty();

            if (goals.length === 0) {
                $container.html(`
                    <div class="loading-message" style="grid-column: 1/-1;">
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 64px; margin-bottom: 16px;">🎯</div>
                            <h3 style="margin: 0 0 8px 0; color: var(--text-dark);">No Goals Yet</h3>
                            <p style="color: var(--text-gray); margin: 0;">Create your first financial goal to start tracking your progress!</p>
                        </div>
                    </div>
                `);
                return;
            }

            goals.forEach((goal, index) => {
                const current = parseFloat(goal.current_amount);
                const target = parseFloat(goal.target_amount);
                const progress = (current / target * 100);
                const progressCapped = Math.min(progress, 100).toFixed(1);

                // Determine status
                let statusBadge = '';
                let progressClass = 'low';
                let cardClass = '';

                if (progress >= 100) {
                    statusBadge = '<span class="goal-status-badge complete">✓ Complete</span>';
                    progressClass = 'complete';
                    cardClass = 'completed';
                } else if (progress >= 75) {
                    statusBadge = '<span class="goal-status-badge on-track">Almost There!</span>';
                    progressClass = 'high';
                    cardClass = 'near-complete';
                } else if (progress >= 50) {
                    statusBadge = '<span class="goal-status-badge on-track">On Track</span>';
                    progressClass = 'medium';
                } else {
                    statusBadge = '<span class="goal-status-badge needs-attention">Needs Attention</span>';
                    progressClass = 'low';
                }

                // Calculate deadline info
                let deadlineHtml = '';
                if (goal.deadline && goal.deadline !== '0000-00-00') {
                    const deadlineDate = new Date(goal.deadline);
                    const today = new Date();
                    const daysLeft = Math.ceil((deadlineDate - today) / (1000 * 60 * 60 * 24));

                    if (daysLeft < 0) {
                        deadlineHtml = `<div class="goal-deadline-info urgent">⚠️ Deadline passed ${Math.abs(daysLeft)} days ago</div>`;
                    } else if (daysLeft <= 30) {
                        deadlineHtml = `<div class="goal-deadline-info urgent">⏰ ${daysLeft} days left until ${this.formatDate(goal.deadline)}</div>`;
                    } else {
                        deadlineHtml = `<div class="goal-deadline-info">📅 Deadline: ${this.formatDate(goal.deadline)}</div>`;
                    }
                }

                $container.append(`
                    <div class="goal-card ${cardClass}" data-goal-id="${goal.id}" data-progress="${progressCapped}" style="animation-delay: ${index * 0.1}s">
                        <h4>
                            ${goal.name}
                            ${statusBadge}
                        </h4>
                        <div class="goal-amount">
                            <div>
                                <strong>₹${this.formatCurrency(current)}</strong>
                                <span style="color: var(--text-gray);"> / ₹${this.formatCurrency(target)}</span>
                            </div>
                            <span class="goal-percentage-label">${progressCapped}%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar ${progressClass}" style="width: ${progressCapped}%"></div>
                        </div>
                        ${deadlineHtml}
                        <div class="goal-actions">
                            <button class="btn btn-primary btn-sm contribute-goal" data-id="${goal.id}" data-name="${goal.name}">💰 Contribute</button>
                            <button class="btn btn-secondary btn-sm edit-goal" data-id="${goal.id}">✏️ Edit</button>
                            <button class="btn btn-danger btn-sm delete-goal" data-id="${goal.id}">🗑️ Delete</button>
                        </div>
                    </div>
                `);
            });

            // Setup filter functionality
            this.setupGoalsFilter();
        },

        setupGoalsFilter: function() {
            $('.filter-chip').off('click').on('click', function() {
                const filter = $(this).data('filter');

                $('.filter-chip').removeClass('active');
                $(this).addClass('active');

                $('.goal-card').each(function() {
                    const $card = $(this);
                    const progress = parseFloat($card.data('progress'));

                    let show = false;

                    switch (filter) {
                        case 'all':
                            show = true;
                            break;
                        case 'active':
                            show = progress < 100;
                            break;
                        case 'near-complete':
                            show = progress >= 75 && progress < 100;
                            break;
                    }

                    if (show) {
                        $card.fadeIn(300);
                    } else {
                        $card.fadeOut(300);
                    }
                });
            });
        },

        renderGoalsOverview: function(goals) {
            if (goals.length === 0) {
                // Hide overview if no goals
                $('.goals-overview-card').hide();
                return;
            }

            $('.goals-overview-card').show();

            // Calculate totals
            let totalSaved = 0;
            let totalTarget = 0;
            let completedCount = 0;
            let onTrackCount = 0;
            let totalProgress = 0;

            goals.forEach(goal => {
                const current = parseFloat(goal.current_amount);
                const target = parseFloat(goal.target_amount);
                const progress = (current / target) * 100;

                totalSaved += current;
                totalTarget += target;
                totalProgress += progress;

                if (progress >= 100) {
                    completedCount++;
                    onTrackCount++;
                } else if (progress >= 50) {
                    onTrackCount++;
                }
            });

            const overallProgress = totalTarget > 0 ? (totalSaved / totalTarget) * 100 : 0;
            const averageProgress = goals.length > 0 ? totalProgress / goals.length : 0;
            const remaining = totalTarget - totalSaved;

            // Update badges
            $('#total-goals-count').text(goals.length);
            $('#completed-goals-count').text(completedCount);

            // Update amounts
            $('#total-saved-amount').text('₹' + this.formatCurrency(totalSaved));
            $('#total-target-amount').text('₹' + this.formatCurrency(totalTarget));
            $('#remaining-amount').text('₹' + this.formatCurrency(remaining));

            // Update progress percentage
            $('#overall-progress-percentage').text(overallProgress.toFixed(0) + '%');

            // Update progress bar with animation
            setTimeout(() => {
                $('#overall-progress-fill').css('width', Math.min(overallProgress, 100) + '%');
            }, 100);

            // Update insights
            $('#average-progress').text(averageProgress.toFixed(0) + '%');
            $('#goals-on-track').text(onTrackCount + '/' + goals.length);

            // Add color coding to progress bar
            const $progressFill = $('#overall-progress-fill');
            $progressFill.removeClass('low medium high complete');

            if (overallProgress >= 100) {
                $progressFill.addClass('complete');
            } else if (overallProgress >= 75) {
                $progressFill.addClass('high');
            } else if (overallProgress >= 50) {
                $progressFill.addClass('medium');
            } else {
                $progressFill.addClass('low');
            }
        },



        openGoalModal: function(goalData = null) {
            if (goalData) {
                $('#goal-modal-title').text('Edit Goal');
                $('#goal-id').val(goalData.id);
                $('#goal-name').val(goalData.name);
                $('#goal-target').val(goalData.target_amount);
                $('#goal-deadline').val(goalData.deadline || '');
            } else {
                $('#goal-modal-title').text('Add New Goal');
                $('#goal-form')[0].reset();
                $('#goal-id').val('');
            }

            $('#goal-modal').addClass('active');
        },

        handleSaveGoal: function(e) {
            e.preventDefault();

            const goalId = $('#goal-id').val();
            const action = goalId ? 'rizqtrack_update_goal' : 'rizqtrack_add_goal';

            const formData = {
                action: action,
                nonce: rizqtrack.nonce,
                name: $('#goal-name').val(),
                target_amount: $('#goal-target').val(),
                deadline: $('#goal-deadline').val()
            };

            if (goalId) {
                formData.id = goalId;
            }

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        this.showNotification(response.data.message, 'success');
                        this.closeModals();
                        this.loadGoals();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        handleEditGoal: function(e) {
            const goalId = $(e.currentTarget).data('id');

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_goals',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const goal = response.data.find(g => g.id == goalId);
                        if (goal) {
                            this.openGoalModal(goal);
                        }
                    }
                }
            });
        },

        handleDeleteGoal: function(e) {
            if (!confirm('Move this goal to trash?')) return;

            const goalId = $(e.currentTarget).data('id');

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_delete_goal',
                    nonce: rizqtrack.nonce,
                    id: goalId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Goal moved to trash', 'success');
                        this.loadGoals();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        openContributeModal: function(e) {
            const goalId = $(e.currentTarget).data('id');
            const goalName = $(e.currentTarget).data('name');

            $('#contribute-goal-id').val(goalId);
            $('#contribute-goal-name').text(`Contributing to: ${goalName}`);
            $('#contribute-amount').val('');
            $('#contribute-modal').addClass('active');
        },

        handleContribute: function(e) {
            e.preventDefault();

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_contribute_goal_transaction',
                    nonce: rizqtrack.nonce,
                    goal_id: $('#contribute-goal-id').val(),
                    amount: $('#contribute-amount').val()
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Contribution added successfully!', 'success');
                        this.closeModals();
                        this.loadGoals();
                        this.loadKPIData();
                        this.loadTransactions(this.currentPage); // Reload current page
                        this.loadChartData();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        openCategoriesModal: function() {
            $('#categories-modal').addClass('active');
        },

        handleAddCategory: function(e) {
            e.preventDefault();

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_add_category',
                    nonce: rizqtrack.nonce,
                    name: $('#new-category-name').val(),
                    type: $('#new-category-type').val(),
                    emoji: $('#new-category-emoji').val() || '📌'
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Category added successfully!', 'success');
                        $('#add-category-form')[0].reset();
                        this.loadCategories();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        handleDeleteCategory: function(e) {
            const categoryId = $(e.currentTarget).data('id');

            if (!confirm('Delete this category? This will only work if no transactions use it.')) return;

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_delete_category',
                    nonce: rizqtrack.nonce,
                    id: categoryId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Category deleted successfully', 'success');
                        this.loadCategories();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Delete category error:', xhr, status, error);
                    this.showNotification('Error deleting category', 'error');
                }
            });
        },

        toggleTrash: function() {
            const $content = $('#trash-content');
            const $icon = $('.toggle-icon');

            if ($content.is(':visible')) {
                $content.slideUp();
                $icon.removeClass('open');
            } else {
                $content.slideDown();
                $icon.addClass('open');
                this.loadTrash();
            }
        },

        loadTrash: function() {
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_trash',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderTrash(response.data.transactions, response.data.goals);
                    }
                }
            });
        },

        renderTrash: function(transactions, goals) {
            const $tbody = $('#trash-tbody');
            $tbody.empty();

            if (!transactions) transactions = [];
            if (!goals) goals = [];

            if (transactions.length === 0 && goals.length === 0) {
                $tbody.html('<tr class="loading-row"><td colspan="6">Trash is empty</td></tr>');
                return;
            }

            // Render transactions
            transactions.forEach(t => {
                const amountClass = t.type === 'income' ? 'amount-positive' : 'amount-negative';
                const amountPrefix = t.type === 'income' ? '+' : '-';

                $tbody.append(`
                    <tr>
                        <td>${this.formatDate(t.date)}</td>
                        <td>${t.category_emoji} ${t.category_name}</td>
                        <td class="${amountClass}">${amountPrefix}${this.formatCurrency(t.amount)}</td>
                        <td>${t.payment_method}</td>
                        <td>${t.description || '-'}</td>
                        <td>
                            <div class="action-btns">
                                <button class="icon-btn restore-transaction" data-id="${t.id}" title="Restore">↩️</button>
                                <button class="icon-btn permanent-delete" data-id="${t.id}" title="Delete Forever">💥</button>
                            </div>
                        </td>
                    </tr>
                `);
            });

            // Render goals
            goals.forEach(g => {
                $tbody.append(`
                    <tr style="background: #fff9e6;">
                        <td colspan="2"><strong>🎯 GOAL:</strong> ${g.name}</td>
                        <td>${this.formatCurrency(g.current_amount)} / ${this.formatCurrency(g.target_amount)}</td>
                        <td colspan="2">-</td>
                        <td>
                            <div class="action-btns">
                                <button class="icon-btn restore-goal" data-id="${g.id}" title="Restore">↩️</button>
                                <button class="icon-btn permanent-delete-goal" data-id="${g.id}" title="Delete Forever">💥</button>
                            </div>
                        </td>
                    </tr>
                `);
            });
        },

        handleRestoreGoal: function(e) {
            const goalId = $(e.currentTarget).data('id');

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_restore_goal',
                    nonce: rizqtrack.nonce,
                    id: goalId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Goal restored', 'success');
                        this.loadTrash();
                        this.loadGoals();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        handlePermanentDeleteGoal: function(e) {
            if (!confirm('Permanently delete this goal? This cannot be undone!')) return;

            const goalId = $(e.currentTarget).data('id');

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_permanent_delete_goal',
                    nonce: rizqtrack.nonce,
                    id: goalId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Goal permanently deleted', 'success');
                        this.loadTrash();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        handleRestoreTransaction: function(e) {
            const transactionId = $(e.currentTarget).data('id');

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_restore_transaction',
                    nonce: rizqtrack.nonce,
                    id: transactionId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Transaction restored', 'success');
                        this.loadTrash();
                        this.loadKPIData();
                        this.loadTransactions(1); // Reload page 1
                        this.loadChartData();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        handlePermanentDelete: function(e) {
            if (!confirm('Permanently delete this transaction? This cannot be undone!')) return;

            const transactionId = $(e.currentTarget).data('id');

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_permanent_delete',
                    nonce: rizqtrack.nonce,
                    id: transactionId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Transaction permanently deleted', 'success');
                        this.loadTrash();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        openReportModal: function() {
            const today = new Date().toISOString().split('T')[0];
            const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

            $('#report-start-date').val(thirtyDaysAgo);
            $('#report-end-date').val(today);
            $('#report-timeframe').val('30');
            $('#custom-date-range').hide();

            $('#report-modal').addClass('active');
        },

        handleReportTimeframeChange: function(e) {
            const value = $(e.currentTarget).val();

            if (value === 'custom') {
                $('#custom-date-range').show();
            } else {
                $('#custom-date-range').hide();

                const today = new Date();
                const endDate = today.toISOString().split('T')[0];
                const days = parseInt(value);
                const startDate = new Date(today.getTime() - days * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

                $('#report-start-date').val(startDate);
                $('#report-end-date').val(endDate);
            }
        },

        handleGenerateReport: function(e) {
            e.preventDefault();

            const startDate = $('#report-start-date').val();
            const endDate = $('#report-end-date').val();
            const format = $('#report-format').val();

            if (!startDate || !endDate) {
                this.showNotification('Please select date range', 'error');
                return;
            }

            // Create form and submit
            const form = $('<form>', {
                method: 'POST',
                action: rizqtrack.ajax_url
            });

            form.append($('<input>', { type: 'hidden', name: 'action', value: 'rizqtrack_generate_report' }));
            form.append($('<input>', { type: 'hidden', name: 'nonce', value: rizqtrack.nonce }));
            form.append($('<input>', { type: 'hidden', name: 'start_date', value: startDate }));
            form.append($('<input>', { type: 'hidden', name: 'end_date', value: endDate }));
            form.append($('<input>', { type: 'hidden', name: 'format', value: format }));

            $('body').append(form);
            form.submit();
            form.remove();

            this.closeModals();
            this.showNotification('Report generated successfully!', 'success');
        },

        closeModals: function() {
            $('.modal').removeClass('active');
        },

        handleModalBackdropClick: function(e) {
            if ($(e.target).hasClass('modal')) {
                this.closeModals();
            }
        },

        showNotification: function(message, type = 'success') {
            const bgColor = type === 'success' ? '#10b981' : '#ef4444';

            // Remove existing notifications
            $('.rizqtrack-notification').remove();

            const $notification = $(`
                <div class="rizqtrack-notification" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${bgColor};
                    color: white;
                    padding: 16px 24px;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    z-index: 10000;
                    font-weight: 500;
                    animation: slideInRight 0.3s ease;
                ">
                    ${message}
                </div>
            `);

            $('body').append($notification);

            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        formatCurrency: function(amount) {
            return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        formatDate: function(dateString) {
            if (!dateString || dateString === '0000-00-00' || dateString === 'Invalid Date' || dateString === '') {
                return 'No deadline';
            }
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                return 'No deadline';
            }
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RizqTrackApp.init();
    });

})(jQuery);
