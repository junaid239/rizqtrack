(function($) {
    'use strict';

    Chart.register(ChartDataLabels); // Register the datalabels plugin

    const RizqTrackApp = {
        charts: {
            category: null,
            incomeExpense: null,
        },
        currentFilter: '30',
        selectedCategories: [], // Global state for selected categories
        editTransactionData: {},
        currentPage: 1, // For pagination
        currentFilters: {}, // Store current transaction filters
        quotes: [
            // Quran - More Verses on Wealth & Charity
            { text: "And whatever you spend in good, it will be repaid to you in full, and you shall not be wronged.", source: "Quran 2:272" },
            { text: "Wealth and children are the adornment of the life of this world.", source: "Quran 18:46" },
            { text: "And do not make your hand chained to your neck or extend it completely and become blamed and insolvent.", source: "Quran 17:29" },
            { text: "And establish prayer and give zakah, and whatever good you put forward for yourselves you will find it with Allah.", source: "Quran 2:110" },
            { text: "The example of those who spend their wealth in the way of Allah is like a seed which grows seven spikes; in each spike is a hundred grains.", source: "Quran 2:261" },
            { text: "Satan threatens you with poverty and orders you to immorality, while Allah promises you forgiveness and bounty.", source: "Quran 2:268" },
            { text: "O you who believe, spend from that which We have provided for you before a Day comes when there is no exchange.", source: "Quran 2:254" },
            { text: "And it is He who made you successors upon the earth and has raised some of you above others in degrees to test you in what He has given you.", source: "Quran 6:165" },
            { text: "Know that the life of this world is only play and amusement, pomp and mutual boasting among you, and rivalry in respect of wealth and children.", source: "Quran 57:20" },
            { text: "But seek, through that which Allah has given you, the home of the Hereafter; and do not forget your share of the world.", source: "Quran 28:77" },

            // Hadith - Prophet Muhammad's Teachings
            { text: "The upper hand is better than the lower hand. The upper hand is the one that gives, and the lower hand is the one that receives.", source: "Prophet Muhammad (Sahih Bukhari)" },
            { text: "Richness is not having many possessions, but richness is contentment of the soul.", source: "Prophet Muhammad (Sahih Muslim)" },
            { text: "Be in this world as though you are a stranger or a traveler.", source: "Prophet Muhammad (Sahih Bukhari)" },
            { text: "Whoever is satisfied with what Allah has given him will be the richest of people.", source: "Prophet Muhammad (At-Tirmidhi)" },
            { text: "The best charity is that given when one has little.", source: "Prophet Muhammad (Sahih Muslim)" },
            { text: "Wealth is not in having many possessions, but true wealth is the richness of the soul.", source: "Prophet Muhammad (Sahih Bukhari)" },
            { text: "The strong person is not the one who can wrestle someone else down, but the one who can control himself when he is angry.", source: "Prophet Muhammad (Sahih Bukhari)" },
            { text: "If you love Allah, then follow me, Allah will love you and forgive you your sins.", source: "Prophet Muhammad (Quran 3:31)" },

            // Islamic Scholars & Philosophers
            { text: "Seek knowledge from the cradle to the grave.", source: "Prophet Muhammad" },
            { text: "The ink of the scholar is more sacred than the blood of the martyr.", source: "Prophet Muhammad" },
            { text: "Capital is the foundation of prosperity, and prosperity is the foundation of civilization.", source: "Ibn Khaldun" },
            { text: "Injustice ruins civilization, and justice is the foundation of prosperity.", source: "Ibn Khaldun" },
            { text: "The dynasty that resorts to a policy of injustice falls into economic ruin and collapse.", source: "Ibn Khaldun" },
            { text: "Commerce is a natural means of livelihood, and most people are engaged in buying and selling.", source: "Ibn Khaldun (Muqaddimah)" },
            { text: "Know that this world is full of deceptions and illusions. Do not let it deceive you.", source: "Imam Al-Ghazali" },
            { text: "The heart that beats with gratitude is the one that is close to its Lord.", source: "Imam Al-Ghazali" },
            { text: "Knowledge without action is insanity, and action without knowledge is vanity.", source: "Imam Al-Ghazali" },
            { text: "Yesterday I was clever, so I wanted to change the world. Today I am wise, so I am changing myself.", source: "Rumi" },
            { text: "Don't grieve. Anything you lose comes round in another form.", source: "Rumi" },
            { text: "Take account of yourselves before you are taken to account.", source: "Umar ibn Al-Khattab" },
            { text: "He who does not economize will have to agonize.", source: "Confucius" },

            // Financial Experts - Expanded
            { text: "Do not save what is left after spending, but spend what is left after saving.", source: "Warren Buffett" },
            { text: "Someone is sitting in the shade today because someone planted a tree a long time ago.", source: "Warren Buffett" },
            { text: "Price is what you pay. Value is what you get.", source: "Warren Buffett" },
            { text: "Risk comes from not knowing what you are doing.", source: "Warren Buffett" },
            { text: "It's far better to buy a wonderful company at a fair price than a fair company at a wonderful price.", source: "Warren Buffett" },
            { text: "The stock market is a device for transferring money from the impatient to the patient.", source: "Warren Buffett" },
            { text: "Never invest in a business you cannot understand.", source: "Warren Buffett" },
            { text: "In the short run, the market is a voting machine but in the long run, it is a weighing machine.", source: "Benjamin Graham" },
            { text: "An investment in knowledge pays the best interest.", source: "Benjamin Franklin" },
            { text: "A penny saved is a penny earned.", source: "Benjamin Franklin" },
            { text: "Beware of little expenses; a small leak will sink a great ship.", source: "Benjamin Franklin" },
            { text: "Remember that time is money.", source: "Benjamin Franklin" },
            { text: "The best investment you can make is in yourself.", source: "Warren Buffett" },
            { text: "Wide diversification is only required when investors do not understand what they are doing.", source: "Warren Buffett" },
            { text: "Formal education will make you a living; self-education will make you a fortune.", source: "Jim Rohn" },
            { text: "You must gain control over your money or the lack of it will forever control you.", source: "Dave Ramsey" },
            { text: "Financial freedom is available to those who learn about it and work for it.", source: "Robert Kiyosaki" },
            { text: "It's not how much money you make, but how much money you keep.", source: "Robert Kiyosaki" },
            { text: "The philosophy of the rich and the poor is this: the rich invest their money and spend what is left. The poor spend their money and invest what is left.", source: "Robert Kiyosaki" },
            { text: "Compound interest is the eighth wonder of the world. He who understands it, earns it; he who doesn't, pays it.", source: "Albert Einstein" },
            { text: "The four most dangerous words in investing are: 'this time it's different.'", source: "Sir John Templeton" },
            { text: "Know what you own, and know why you own it.", source: "Peter Lynch" },
            { text: "Behind every stock is a company. Find out what it's doing.", source: "Peter Lynch" },
            { text: "He who loses money loses much; he who loses a friend loses much more; he who loses faith loses all.", source: "Eleanor Roosevelt" },
            { text: "Invest for the long haul. Don't get too greedy and don't get too scared.", source: "Shelby M.C. Davis" },
            { text: "Time in the market beats timing the market.", source: "Ken Fisher" },

            // Ancient Chinese Wisdom
            { text: "The best time to plant a tree was 20 years ago. The second best time is now.", source: "Chinese Proverb" },
            { text: "A journey of a thousand miles begins with a single step.", source: "Lao Tzu" },
            { text: "He who knows he has enough is rich.", source: "Lao Tzu" },
            { text: "When prosperity comes, do not use all of it.", source: "Confucius" },
            { text: "The man who moves a mountain begins by carrying away small stones.", source: "Confucius" },
            { text: "It does not matter how slowly you go as long as you do not stop.", source: "Confucius" },
            { text: "Without feelings of respect, what is there to distinguish men from beasts?", source: "Confucius" },
            { text: "Better a diamond with a flaw than a pebble without.", source: "Confucius" },
            { text: "A gem cannot be polished without friction, nor a man perfected without trials.", source: "Chinese Proverb" },
            { text: "To know the road ahead, ask those coming back.", source: "Chinese Proverb" },
            { text: "If you want happiness for an hour, take a nap. If you want happiness for a lifetime, help someone else.", source: "Chinese Proverb" },
            { text: "Opportunities multiply as they are seized.", source: "Sun Tzu" },
            { text: "In the midst of chaos, there is also opportunity.", source: "Sun Tzu" },

            // Jewish Wisdom - Torah & Talmud
            { text: "Who is rich? One who is satisfied with his lot.", source: "Pirkei Avot 4:1 (Talmud)" },
            { text: "If I am not for myself, who will be for me? But if I am only for myself, what am I?", source: "Hillel the Elder (Pirkei Avot 1:14)" },
            { text: "Make for yourself a teacher, acquire for yourself a friend, and judge every person favorably.", source: "Pirkei Avot 1:6" },
            { text: "A person who has 100 wants 200; when he has 200, he wants 400.", source: "Kohelet Rabbah 1:34" },
            { text: "Where there is no Torah, there is no proper conduct; where there is no proper conduct, there is no Torah.", source: "Pirkei Avot 3:17" },
            { text: "The reward for a mitzvah is a mitzvah, and the reward for a transgression is a transgression.", source: "Pirkei Avot 4:2" },
            { text: "Give me neither poverty nor riches; feed me with food that is my portion.", source: "Proverbs 30:8" },
            { text: "The borrower is slave to the lender.", source: "Proverbs 22:7" },
            { text: "Honor the Lord with your wealth and with the first fruits of all your produce.", source: "Proverbs 3:9" },
            { text: "Dishonest money dwindles away, but whoever gathers money little by little makes it grow.", source: "Proverbs 13:11" },
            { text: "Cast your bread upon the waters, for you will find it after many days.", source: "Ecclesiastes 11:1" },
            { text: "Give a portion to seven, or even to eight, for you know not what disaster may happen on earth.", source: "Ecclesiastes 11:2" },

            // Indian Wisdom - Vedas, Upanishads, & Chanakya
            { text: "Artha (wealth) is one of the four goals of human life, but it must be earned righteously.", source: "Hindu Philosophy" },
            { text: "Do your duty without attachment to the results, whether they be success or failure.", source: "Bhagavad Gita 2:47" },
            { text: "You have the right to work, but never to the fruit of work.", source: "Bhagavad Gita 2:47" },
            { text: "As a person puts on new garments, giving up old ones, the soul similarly accepts new material bodies, giving up the old and useless ones.", source: "Bhagavad Gita 2:22" },
            { text: "The wise work for the welfare of the world, without thought for themselves.", source: "Bhagavad Gita 3:25" },
            { text: "A person should not be too honest. Straight trees are cut first and honest people are screwed first.", source: "Chanakya" },
            { text: "As soon as the fear approaches near, attack and destroy it.", source: "Chanakya" },
            { text: "A person should not be too straightforward. Go and see the forest. The straight trees are cut down, the crooked ones are left standing.", source: "Chanakya" },
            { text: "The fragrance of flowers spreads only in the direction of the wind. But the goodness of a person spreads in all directions.", source: "Chanakya" },
            { text: "Education is the best friend. An educated person is respected everywhere.", source: "Chanakya" },
            { text: "Before you start some work, always ask yourself three questions: Why am I doing it? What might be the results? Will I be successful?", source: "Chanakya" },
            { text: "The biggest guru-mantra is: never share your secrets with anybody. It will destroy you.", source: "Chanakya" },
            { text: "Once you start working on something, don't be afraid of failure and don't abandon it.", source: "Chanakya" },

            // Buddhist Wisdom
            { text: "Wealth is the ability to fully experience life with peace of mind.", source: "Buddhist Teaching" },
            { text: "He who has few desires is satisfied with simple things.", source: "Buddha" },
            { text: "Health is the greatest gift, contentment the greatest wealth, faithfulness the best relationship.", source: "Buddha" },
            { text: "Do not dwell in the past, do not dream of the future, concentrate the mind on the present moment.", source: "Buddha" },

            // General Modern Wisdom
            { text: "Small daily improvements over time lead to stunning results.", source: "Robin Sharma" },
            { text: "The secret to getting ahead is getting started.", source: "Mark Twain" },
            { text: "Budget: Telling your money where to go instead of wondering where it went.", source: "John C. Maxwell" },
            { text: "The goal isn't more money. The goal is living life on your terms.", source: "Chris Brogan" },
            { text: "Wealth is not about having a lot of money; it's about having a lot of options.", source: "Chris Rock" },
            { text: "Track your expenses. Review them weekly. Improve monthly.", source: "Financial Wisdom" }
        ],

        init: function() {
            this.charts = {
                category: null,
                incomeExpense: null,
                goalsDonut: null
            };
            this.currentFilter = '30';
            this.selectedCategories = [];
            this.categorySlicersRendered = false;
            this.editTransactionData = {};

            this.setupEventListeners();
            this.setDefaultFormValues();
            this.showRandomQuote();
            this.loadCategories(); // This will trigger loadChartData() after categories are loaded
            this.loadKPIData();
            this.loadTransactions(1); // Load page 1
            this.loadGoals();
            this.loadBudgets();
            this.checkBudgetAlerts();
            this.initializeDateFilters();
        },

        setupEventListeners: function() {
            // Transaction Form
            $('#transaction-form').on('submit', this.handleAddTransaction.bind(this));
            $('.toggle-btn').on('click', this.handleTypeToggle.bind(this));

            // Category change to show/hide fuel fields
            $('#category').on('change', this.handleCategoryChange.bind(this));
            $('#edit-category').on('change', this.handleEditCategoryChange.bind(this));

            // Date Range Filters
            $('#filter-start-date, #filter-end-date').on('change', this.handleDateFilterChange.bind(this));

            // Transaction Actions
            $(document).on('click', '.edit-transaction', this.openEditModal.bind(this));
            $(document).on('click', '.delete-transaction', this.handleDeleteTransaction.bind(this));
            $('#edit-transaction-form').on('submit', this.handleUpdateTransaction.bind(this));
            
            // Pagination Listeners
            $(document).on('click', '#prev-page', () => this.loadTransactions(this.currentPage - 1, this.currentFilters));
            $(document).on('click', '#next-page', () => this.loadTransactions(this.currentPage + 1, this.currentFilters));

            // Transaction Filters
            $('#filter-apply').on('click', this.applyTransactionFilters.bind(this));
            $('#filter-reset').on('click', this.resetTransactionFilters.bind(this));
            $('#filter-search').on('keyup', this.debounce(this.applyTransactionFilters.bind(this), 500));

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

            // Email Reports
            $('#email-report-card').on('click', this.openEmailReportModal.bind(this));
            $('#email-report-form').on('submit', this.handleSaveEmailSettings.bind(this));

            // Modal Controls
            $('.close, .cancel-btn').on('click', this.closeModals.bind(this));
            $(window).on('click', this.handleModalBackdropClick.bind(this));

            // Motivational Quote
            $('#refresh-quote').on('click', this.showRandomQuote.bind(this));

            // Budgets
            $('#add-budget-btn').on('click', this.openBudgetModal.bind(this));
            $('#budget-form').on('submit', this.handleSaveBudget.bind(this));
            $(document).on('click', '.edit-budget-btn', this.handleEditBudget.bind(this));
            $(document).on('click', '.delete-budget-btn', this.handleDeleteBudget.bind(this));

            // Email Reports
            $('#send-email-now-btn').on('click', this.handleSendEmailNow.bind(this));
        },

        showRandomQuote: function() {
            const randomIndex = Math.floor(Math.random() * this.quotes.length);
            const quote = this.quotes[randomIndex];
            $('#quote-text').text(quote.text);
            $('#quote-source').text('— ' + quote.source);
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

        handleCategoryChange: function(e) {
            const selectedOption = $(e.currentTarget).find('option:selected');
            const categoryName = selectedOption.text();

            // Show fuel fields only if "Fuel" category is selected
            if (categoryName.includes('Fuel') || categoryName.includes('⛽')) {
                $('#fuel-fields').slideDown(300);
            } else {
                $('#fuel-fields').slideUp(300);
                // Clear fuel fields when hiding
                $('#odometer-reading, #fuel-liters').val('');
            }
        },

        handleEditCategoryChange: function(e) {
            const selectedOption = $(e.currentTarget).find('option:selected');
            const categoryName = selectedOption.text();

            // Show fuel fields only if "Fuel" category is selected
            if (categoryName.includes('Fuel') || categoryName.includes('⛽')) {
                $('#edit-fuel-fields').slideDown(300);
            } else {
                $('#edit-fuel-fields').slideUp(300);
            }
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
                        $('#kpi-most-frequent-category').text(data.most_frequent_category);
                        $('#kpi-days-without-spending').text(data.days_without_spending);
                        $('#kpi-busiest-day').text(data.busiest_day);
                        $('#kpi-avg-income-per-day').text('₹' + this.formatCurrency(data.avg_income_per_day));
                        $('#kpi-avg-expense-per-day').text('₹' + this.formatCurrency(data.avg_expense_per_day));
                        $('#kpi-vehicle-mileage').text(data.vehicle_mileage > 0 ? data.vehicle_mileage.toFixed(2) : 'N/A');
                    }
                },
                error: () => {
                    $('#kpi-income, #kpi-expense, #kpi-savings, #kpi-transaction-count, #kpi-avg-transaction, #kpi-top-category, #kpi-most-frequent-category, #kpi-days-without-spending, #kpi-busiest-day, #kpi-avg-income-per-day, #kpi-avg-expense-per-day, #kpi-vehicle-mileage').text('Error');
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
                        // Also render category slicers for chart filters
                        if (!this.categorySlicersRendered) {
                            this.renderCategorySlicers(response.data);
                            this.categorySlicersRendered = true;
                            // Load chart data after categories are initialized
                            this.loadChartData();
                        }
                    }
                }
            });
        },

        populateCategorySelects: function(categories) {
            const selects = ['#category', '#edit-category', '#filter-category'];

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

            // Add fuel-specific fields if present
            const odometerReading = $('#odometer-reading').val();
            const fuelLiters = $('#fuel-liters').val();

            if (odometerReading) formData.odometer_reading = odometerReading;
            if (fuelLiters) formData.fuel_liters = fuelLiters;

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
                        $('#fuel-fields').hide(); // Hide fuel fields after reset
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

        loadTransactions: function(page = 1, filters = {}) {
            this.currentPage = page; // Store the current page

            const data = {
                action: 'rizqtrack_get_recent_transactions',
                nonce: rizqtrack.nonce,
                page: this.currentPage
            };

            // Add filters if provided
            if (filters.search) data.search = filters.search;
            if (filters.category_id) data.category_id = filters.category_id;
            if (filters.start_date) data.start_date = filters.start_date;
            if (filters.end_date) data.end_date = filters.end_date;

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.renderTransactions(response.data.transactions);
                        this.renderPagination(response.data.total);
                    }
                }
            });
        },

        applyTransactionFilters: function() {
            const filters = {
                search: $('#filter-search').val().trim(),
                category_id: $('#filter-category').val(),
                start_date: $('#filter-start-date').val(),
                end_date: $('#filter-end-date').val()
            };

            this.currentFilters = filters; // Store filters
            this.loadTransactions(1, filters);
        },

        resetTransactionFilters: function() {
            $('#filter-search').val('');
            $('#filter-category').val('0');
            $('#filter-start-date').val('');
            $('#filter-end-date').val('');
            this.currentFilters = {}; // Clear stored filters
            this.loadTransactions(1);
        },

        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
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

                // Build description with fuel data if available
                let descriptionHtml = t.description || '-';
                if (t.odometer_reading || t.fuel_liters) {
                    const fuelDetails = [];
                    if (t.odometer_reading) fuelDetails.push(`📍 ${parseFloat(t.odometer_reading).toFixed(2)} km`);
                    if (t.fuel_liters) fuelDetails.push(`⛽ ${parseFloat(t.fuel_liters).toFixed(2)} L`);

                    if (fuelDetails.length > 0) {
                        const fuelInfo = `<div class="fuel-info" style="font-size: 0.85em; color: #6b7280; margin-top: 4px;">${fuelDetails.join(' • ')}</div>`;
                        descriptionHtml = (t.description ? t.description + fuelInfo : fuelInfo);
                    }
                }

                $tbody.append(`
                    <tr>
                        <td>${this.formatDate(t.date)}</td>
                        <td>${t.category_emoji} ${t.category_name}</td>
                        <td class="${amountClass}">${amountPrefix}${this.formatCurrency(t.amount)}</td>
                        <td>${t.payment_method}</td>
                        <td>${descriptionHtml}</td>
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

            const limit = 5;
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

                            // Populate fuel fields if present
                            $('#edit-odometer-reading').val(transaction.odometer_reading || '');
                            $('#edit-fuel-liters').val(transaction.fuel_liters || '');

                            // Show fuel fields if this is a fuel transaction
                            if (transaction.category_name && (transaction.category_name.includes('Fuel') || transaction.category_emoji === '⛽')) {
                                $('#edit-fuel-fields').show();
                            } else {
                                $('#edit-fuel-fields').hide();
                            }

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

            // Add fuel-specific fields
            formData.odometer_reading = $('#edit-odometer-reading').val() || '';
            formData.fuel_liters = $('#edit-fuel-liters').val() || '';

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
                        this.loadGoals(); // Update goal progress
                        this.loadTrash();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        initializeDateFilters: function() {
            // Set default dates: last 30 days
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 30);

            $('#filter-end-date').val(this.formatDateInput(endDate));
            $('#filter-start-date').val(this.formatDateInput(startDate));
        },

        formatDateInput: function(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        handleDateFilterChange: function() {
            this.loadChartData();
        },

        loadChartData: function() {
            const startDate = $('#filter-start-date').val();
            const endDate = $('#filter-end-date').val();

            const data = {
                action: 'rizqtrack_get_chart_data',
                nonce: rizqtrack.nonce,
                start_date: startDate,
                end_date: endDate
            };

            // Add selected categories if any
            if (this.selectedCategories.length > 0) {
                data.categories = this.selectedCategories.join(',');
            }

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.renderCategoryChart(response.data.category_data || []);
                        this.renderTopFrequentChart(response.data.top_frequent || []);
                        this.renderSpendingTrendChart(response.data.spending_trend || []);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Failed to load chart data:', error);
                    // Render empty charts
                    this.renderCategoryChart([]);
                    this.renderTopFrequentChart([]);
                    this.renderSpendingTrendChart([]);
                }
            });
        },

        renderCategoryChart: function(data, selectedCategories = []) {
            const ctx = document.getElementById('category-chart');

            if (this.charts.category) {
                this.charts.category.destroy();
            }

            // Store full data for filtering
            this.categoryChartData = data;

            // Filter data if categories are selected
            let filteredData = data;
            if (selectedCategories.length > 0) {
                filteredData = data.filter(d => selectedCategories.includes(d.name));
            }

            // Take top 10 for mobile, all for desktop
            const isMobile = window.innerWidth < 768;
            const displayData = isMobile ? filteredData.slice(0, 10) : filteredData;

            const labels = displayData.map(d => `${d.emoji} ${d.name}`);
            const values = displayData.map(d => parseFloat(d.total));
            const colors = this.generateColors(displayData.length);

            // Responsive font sizes
            const labelFontSize = isMobile ? 10 : 12;
            const dataLabelFontSize = isMobile ? 10 : 13;

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
                    indexAxis: 'y',
                    layout: {
                        padding: {
                            right: isMobile ? 60 : 80 // Extra space for labels
                        }
                    },
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const categoryName = displayData[index].name;
                            this.loadCategoryDetails(categoryName);
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            callbacks: {
                                label: (context) => {
                                    return ` Total: ₹${context.parsed.x.toLocaleString()}`;
                                }
                            }
                        },
                        datalabels: {
                            color: '#000',
                            anchor: 'end',
                            align: 'end',
                            offset: -6,
                            font: {
                                weight: 'bold',
                                size: dataLabelFontSize
                            },
                            formatter: (value, ctx) => {
                                if (isMobile && value < 1000) {
                                    return '₹' + value.toFixed(0);
                                }
                                return '₹' + (value / 1000).toFixed(value >= 1000 ? 1 : 0) + 'k';
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grace: '20%', // More space to prevent cutoff
                            ticks: {
                                callback: function(value) {
                                    if (isMobile && value >= 1000) {
                                        return '₹' + (value / 1000) + 'k';
                                    }
                                    return '₹' + value;
                                },
                                color: '#1f2937',
                                font: {
                                    size: labelFontSize
                                }
                            }
                        },
                        y: {
                            ticks: {
                                color: '#1f2937',
                                font: {
                                    size: labelFontSize
                                }
                            }
                        }
                    }
                }
            });

            // Render category slicers only once (first time)
            if (!this.categorySlicersRendered) {
                this.renderCategorySlicers(data);
                this.categorySlicersRendered = true;
            }
        },

        renderCategorySlicers: function(data) {
            const $container = $('#category-slicers');
            if (!$container.length) return;

            $container.empty();

            // If no categories selected, select all by default
            if (this.selectedCategories.length === 0) {
                this.selectedCategories = data.map(cat => cat.name);
            }

            // Add category chips (all active by default)
            data.forEach(cat => {
                const isActive = this.selectedCategories.includes(cat.name) ? 'active' : '';
                $container.append(`
                    <div class="slicer-chip ${isActive}" data-category="${cat.name}">
                        ${cat.emoji} ${cat.name}
                    </div>
                `);
            });

            // Handle chip clicks - Update global state and reload ALL charts
            $(document).off('click', '.slicer-chip').on('click', '.slicer-chip', (e) => {
                const $chip = $(e.currentTarget);
                const category = $chip.data('category');

                // Toggle individual category
                $chip.toggleClass('active');

                // Get all selected categories
                const selectedCategories = $('.slicer-chip.active')
                    .map(function() { return $(this).data('category'); })
                    .get();

                // Ensure at least one category is selected
                if (selectedCategories.length === 0) {
                    $chip.addClass('active'); // Re-add the active class to prevent empty selection
                    this.showNotification('At least one category must be selected', 'error');
                    return;
                }

                this.selectedCategories = selectedCategories;
                this.loadChartData(); // Reload ALL charts with selected categories
            });
        },

        renderTopFrequentChart: function(data) {
            const ctx = document.getElementById('top-frequent-chart');
            if (!ctx) return;

            if (this.charts.topFrequent) {
                this.charts.topFrequent.destroy();
            }

            if (!data || data.length === 0) {
                return;
            }

            // Sort by count descending (already sorted from backend but ensure it)
            const sortedData = data.sort((a, b) => parseInt(b.count) - parseInt(a.count));

            // Get top 10 most frequent categories
            const labels = sortedData.map(d => `${d.emoji} ${d.name}`);
            const counts = sortedData.map(d => parseInt(d.count) || 0);
            const amounts = sortedData.map(d => parseFloat(d.total_amount) || 0);

            // Generate vibrant colors
            const colors = sortedData.map((_, i) => {
                const hue = (i * 360 / sortedData.length) % 360;
                return `hsla(${hue}, 70%, 60%, 0.8)`;
            });

            this.charts.topFrequent = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Transaction Count',
                        data: counts,
                        backgroundColor: colors,
                        borderColor: colors.map(c => c.replace('0.8', '1')),
                        borderWidth: 2,
                        borderRadius: 8,
                        barThickness: 'flex',
                        maxBarThickness: 40
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.9)',
                            padding: 14,
                            titleFont: { size: 14, weight: 'bold' },
                            bodyFont: { size: 13 },
                            callbacks: {
                                label: (context) => {
                                    const index = context.dataIndex;
                                    const count = counts[index];
                                    const total = amounts[index];
                                    return [
                                        `Transactions: ${count}`,
                                        `Total Amount: ₹${total.toLocaleString()}`,
                                        `Average: ₹${(total / count).toLocaleString(undefined, {maximumFractionDigits: 0})}`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Transactions',
                                font: { size: 12, weight: 'bold' }
                            },
                            ticks: {
                                precision: 0,
                                font: { size: 11 }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Category',
                                font: { size: 12, weight: 'bold' }
                            },
                            ticks: {
                                font: { size: 11 },
                                callback: function(value, index) {
                                    const label = this.getLabelForValue(value);
                                    return label.length > 25 ? label.substring(0, 25) + '...' : label;
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Render treemap with the same data
            this.renderTreemap(sortedData);
        },

        renderTreemap: function(data) {
            const container = document.getElementById('treemap-container');
            if (!container || !data || data.length === 0) return;

            container.innerHTML = '';

            // Calculate total for percentage
            const total = data.reduce((sum, d) => sum + parseInt(d.count), 0);

            // Create treemap blocks
            data.forEach((item, index) => {
                const percentage = (parseInt(item.count) / total) * 100;
                const hue = (index * 360 / data.length) % 360;
                const color = `hsl(${hue}, 70%, 60%)`;

                const block = document.createElement('div');
                block.className = 'treemap-block';
                block.style.flex = item.count;
                block.style.background = color;
                block.setAttribute('data-count', item.count);
                block.setAttribute('data-amount', item.total_amount);
                block.setAttribute('data-name', item.name);

                block.innerHTML = `
                    <div class="treemap-content">
                        <div class="treemap-emoji">${item.emoji}</div>
                        <div class="treemap-label">${item.name}</div>
                        <div class="treemap-value">${item.count}</div>
                    </div>
                    <div class="treemap-tooltip">
                        <strong>${item.emoji} ${item.name}</strong><br>
                        Transactions: ${item.count}<br>
                        Total: ₹${parseFloat(item.total_amount).toLocaleString()}<br>
                        ${percentage.toFixed(1)}% of total
                    </div>
                `;

                container.appendChild(block);
            });
        },

        loadCategoryDetails: function(categoryName) {
            const data = {
                action: 'rizqtrack_get_category_details',
                nonce: rizqtrack.nonce,
                category: categoryName,
                filter: this.currentFilter
            };

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.renderCategoryDetails(categoryName, response.data);
                    }
                }
            });
        },

        renderCategoryDetails: function(categoryName, transactions) {
            const $container = $('#category-details-container');
            const $title = $('#category-details-title');

            $title.html(`📋 ${categoryName} - Transaction Details`);

            if (!transactions || transactions.length === 0) {
                $container.html('<div class="no-data">No transactions found for this category</div>');
                return;
            }

            let html = `
                <div class="category-details-table-wrapper">
                    <table class="category-details-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            transactions.forEach(tx => {
                const date = new Date(tx.date).toLocaleDateString('en-IN', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                });
                const typeClass = tx.type === 'income' ? 'income' : 'expense';
                const typeIcon = tx.type === 'income' ? '↗️' : '↘️';

                html += `
                    <tr>
                        <td>${date}</td>
                        <td class="description-cell">${tx.description || 'No description'}</td>
                        <td><span class="type-badge ${typeClass}">${typeIcon} ${tx.type}</span></td>
                        <td class="amount-cell ${typeClass}">₹${parseFloat(tx.amount).toLocaleString()}</td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                    <div class="category-summary">
                        <strong>Total Transactions:</strong> ${transactions.length} |
                        <strong>Total Amount:</strong> ₹${transactions.reduce((sum, tx) => sum + parseFloat(tx.amount), 0).toLocaleString()}
                    </div>
                </div>
            `;

            $container.html(html);
        },

        renderSpendingTrendChart: function(data) {
            const ctx = document.getElementById('spending-trend-chart');
            if (!ctx) return;

            if (this.charts.spendingTrend) {
                this.charts.spendingTrend.destroy();
            }

            if (!data || data.length === 0) {
                return;
            }

            const dates = data.map(d => d.date);
            const incomes = data.map(d => parseFloat(d.income) || 0);
            const expenses = data.map(d => parseFloat(d.expense) || 0);

            this.charts.spendingTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: 'Income',
                            data: incomes,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#10b981',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        },
                        {
                            label: 'Expense',
                            data: expenses,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#ef4444',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 13
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            callbacks: {
                                label: (context) => {
                                    return `${context.dataset.label}: ₹${context.parsed.y.toLocaleString()}`;
                                }
                            }
                        },
                        datalabels: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                },
                                color: '#1f2937'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#1f2937',
                                maxRotation: 45,
                                minRotation: 45
                            },
                            grid: {
                                display: false
                            }
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

                // Priority badge
                let priorityBadge = '';
                if (goal.priority) {
                    const priorityMap = {
                        'high': { emoji: '🔴', text: 'High', color: '#ef4444' },
                        'medium': { emoji: '🟡', text: 'Medium', color: '#f59e0b' },
                        'low': { emoji: '🟢', text: 'Low', color: '#10b981' }
                    };
                    const p = priorityMap[goal.priority];
                    if (p) {
                        priorityBadge = `<span style="background: ${p.color}15; color: ${p.color}; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">${p.emoji} ${p.text}</span>`;
                    }
                }

                // Category badge
                let categoryBadge = '';
                if (goal.category) {
                    const categoryMap = {
                        'savings': '💰 Savings',
                        'investment': '📈 Investment',
                        'purchase': '🛒 Purchase',
                        'emergency': '🚨 Emergency',
                        'education': '🎓 Education',
                        'travel': '✈️ Travel',
                        'home': '🏠 Home',
                        'vehicle': '🚗 Vehicle',
                        'other': '📌 Other'
                    };
                    const categoryText = categoryMap[goal.category] || goal.category;
                    categoryBadge = `<span style="background: #ecfeff; color: #0891b2; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">${categoryText}</span>`;
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

                // Notes preview (first 100 chars)
                let notesHtml = '';
                if (goal.notes && goal.notes.trim()) {
                    const notePreview = goal.notes.length > 100 ? goal.notes.substring(0, 100) + '...' : goal.notes;
                    notesHtml = `<div style="font-size: 13px; color: var(--text-gray); margin-bottom: 12px; font-style: italic;">📝 ${notePreview}</div>`;
                }

                $container.append(`
                    <div class="goal-card ${cardClass}" data-goal-id="${goal.id}" data-progress="${progressCapped}" style="animation-delay: ${index * 0.1}s">
                        <h4>
                            ${goal.name}
                            ${statusBadge}
                        </h4>
                        <div style="display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap;">
                            ${categoryBadge}
                            ${priorityBadge}
                        </div>
                        ${notesHtml}
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
                // Cap progress at 100% for average calculation to prevent over-funded goals from inflating the average
                totalProgress += Math.min(progress, 100);

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

            // Update progress percentage (cap at 100% for display)
            $('#overall-progress-percentage').text(Math.min(overallProgress, 100).toFixed(0) + '%');

            // Update progress bar with animation
            setTimeout(() => {
                $('#overall-progress-fill').css('width', Math.min(overallProgress, 100) + '%');
            }, 100);

            // Update insights (cap average progress at 100% for display)
            $('#average-progress').text(Math.min(averageProgress, 100).toFixed(0) + '%');
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
                $('#goal-category').val(goalData.category || '');
                $('#goal-priority').val(goalData.priority || '');
                $('#goal-start-date').val(goalData.start_date || '');
                $('#goal-notes').val(goalData.notes || '');
            } else {
                $('#goal-modal-title').text('Add New Goal');
                $('#goal-form')[0].reset();
                $('#goal-id').val('');
            }

            // Setup monthly savings calculator
            this.setupMonthlySavingsCalculator();

            $('#goal-modal').addClass('active');
        },

        setupMonthlySavingsCalculator: function() {
            const calculateMonthlySavings = () => {
                const targetAmount = parseFloat($('#goal-target').val()) || 0;
                const deadline = $('#goal-deadline').val();

                if (targetAmount > 0 && deadline) {
                    const today = new Date();
                    const deadlineDate = new Date(deadline);
                    const monthsDiff = (deadlineDate.getFullYear() - today.getFullYear()) * 12 +
                                      (deadlineDate.getMonth() - today.getMonth());

                    if (monthsDiff > 0) {
                        const monthlySavings = targetAmount / monthsDiff;
                        $('#monthly-savings-info')
                            .text(`💡 You need to save ₹${this.formatCurrency(monthlySavings)} per month to reach this goal`)
                            .css('color', '#0891b2')
                            .show();
                    } else if (monthsDiff === 0) {
                        $('#monthly-savings-info')
                            .text('⚠️ Deadline is this month or in the past')
                            .css('color', '#ef4444')
                            .show();
                    } else {
                        $('#monthly-savings-info')
                            .text('⚠️ Deadline is in the past')
                            .css('color', '#ef4444')
                            .show();
                    }
                } else {
                    $('#monthly-savings-info').hide();
                }
            };

            // Remove previous event listeners to avoid duplicates
            $('#goal-target, #goal-deadline').off('input change');

            // Add new event listeners
            $('#goal-target, #goal-deadline').on('input change', calculateMonthlySavings);

            // Calculate on modal open if values exist
            calculateMonthlySavings();
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
                deadline: $('#goal-deadline').val(),
                category: $('#goal-category').val(),
                priority: $('#goal-priority').val(),
                start_date: $('#goal-start-date').val(),
                notes: $('#goal-notes').val()
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
                        this.loadKPIData(); // Update KPIs
                        this.loadTransactions(this.currentPage); // Update transactions list
                        this.loadChartData(); // Update charts
                        this.loadTrash();
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
                        // Reload data after a short delay to ensure transaction is committed
                        setTimeout(() => {
                            this.loadGoals();
                            this.loadKPIData();
                            this.loadTransactions(this.currentPage);
                            this.loadChartData();
                        }, 100);
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
                        this.loadKPIData(); // Update KPIs
                        this.loadTransactions(1); // Reload page 1
                        this.loadChartData(); // Update charts
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
                        this.loadKPIData(); // Update KPIs
                        this.loadTransactions(1); // Reload page 1
                        this.loadChartData(); // Update charts
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
                        this.loadGoals(); // Update goal progress
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
                        this.loadKPIData(); // Update KPIs
                        this.loadTransactions(1); // Reload page 1
                        this.loadChartData(); // Update charts
                        this.loadGoals(); // Update goal progress (in case it was a contribution)
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

            // Populate category dropdown
            const $reportCategory = $('#report-category');
            $reportCategory.find('option:not(:first)').remove();

            // Get categories from the main category select
            $('#category option:not(:first)').each(function() {
                const $option = $(this);
                $reportCategory.append(
                    $('<option></option>')
                        .val($option.val())
                        .text($option.text())
                );
            });

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
            const category = $('#report-category').val();
            const type = $('#report-type').val();

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
            form.append($('<input>', { type: 'hidden', name: 'category', value: category }));
            form.append($('<input>', { type: 'hidden', name: 'type', value: type }));

            $('body').append(form);
            form.submit();
            form.remove();

            this.closeModals();
            this.showNotification('Report generated successfully!', 'success');
        },

        openEmailReportModal: function() {
            // Load current settings
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_email_settings',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#email-frequency').val(response.data.frequency || 'none');
                        $('#email-address').val(response.data.email || '');
                    }
                    $('#email-report-modal').addClass('active');
                }
            });
        },

        handleSaveEmailSettings: function(e) {
            e.preventDefault();

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_save_email_settings',
                    nonce: rizqtrack.nonce,
                    frequency: $('#email-frequency').val(),
                    email: $('#email-address').val()
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Email report settings saved!', 'success');
                        this.closeModals();
                    } else {
                        this.showNotification(response.data.message || 'Failed to save settings', 'error');
                    }
                },
                error: () => {
                    this.showNotification('Connection error', 'error');
                }
            });
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
        },

        // ===========================================
        // ACHIEVEMENTS SYSTEM
        // ===========================================
        loadAchievements: function() {
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_achievements',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderAchievements(response.data);
                    }
                }
            });
        },

        renderAchievements: function(achievements) {
            const $container = $('#achievements-container');
            $container.empty();

            // Update count
            $('#achievement-count').text(`${achievements.length} earned`);

            if (achievements.length === 0) {
                $container.append('<div class="no-data">No achievements yet. Keep tracking to unlock badges!</div>');
                return;
            }

            achievements.forEach(achievement => {
                const earnedDate = new Date(achievement.earned_date);
                const dateStr = earnedDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

                $container.append(`
                    <div class="achievement-badge" style="border-color: ${achievement.badge_color};">
                        <div class="achievement-icon">${achievement.badge_icon}</div>
                        <div class="achievement-name">${achievement.achievement_name}</div>
                        <div class="achievement-description">${achievement.achievement_description}</div>
                        <div class="achievement-date">Earned on ${dateStr}</div>
                    </div>
                `);
            });
        },

        checkAchievements: function() {
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_check_achievements',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success && response.data.new_achievements.length > 0) {
                        // Show popup for new achievements
                        this.showAchievementPopup(response.data.new_achievements);
                        // Reload achievements list
                        this.loadAchievements();
                    }
                }
            });
        },

        showAchievementPopup: function(achievements) {
            const achievement = achievements[0]; // Show first achievement

            $('#popup-achievement-details').html(`
                <div style="font-size: 64px; margin: 16px 0;">${achievement.icon}</div>
                <h4 style="font-size: 20px; font-weight: 700; color: ${achievement.color}; margin-bottom: 8px;">
                    ${achievement.name}
                </h4>
                <p style="color: #6b7280; font-size: 14px;">${achievement.description}</p>
            `);

            $('#new-achievement-popup').show();
        },

        closeAchievementPopup: function() {
            $('#new-achievement-popup').hide();
        },

        // Budget Management Functions
        loadBudgets: function() {
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_budget_vs_actual',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderBudgets(response.data);
                    }
                }
            });
        },

        renderBudgets: function(budgets) {
            const $container = $('#budget-container');
            $container.empty();

            if (budgets.length === 0) {
                $container.append('<div class="no-data">No budgets yet. Click "Set Budget" to start!</div>');
                return;
            }

            budgets.forEach(budget => {
                const progressCapped = Math.min(budget.percentage, 100);
                let statusClass = '';

                if (budget.is_over_budget) {
                    statusClass = 'danger';
                } else if (budget.is_warning) {
                    statusClass = 'warning';
                }

                const remainingText = budget.remaining >= 0
                    ? `₹${this.formatCurrency(budget.remaining)} remaining`
                    : `₹${this.formatCurrency(Math.abs(budget.remaining))} over budget`;

                $container.append(`
                    <div class="budget-card ${statusClass}">
                        <div class="budget-header">
                            <div class="budget-category">
                                <span class="budget-category-emoji">${budget.category_emoji}</span>
                                <div>
                                    <div class="budget-category-name">${budget.category_name}</div>
                                    <div class="budget-period">${budget.period}</div>
                                </div>
                            </div>
                        </div>
                        <div class="budget-amounts">
                            <span class="budget-spent">₹${this.formatCurrency(budget.actual_amount)}</span>
                            <span class="budget-limit">of ₹${this.formatCurrency(budget.budget_amount)}</span>
                        </div>
                        <div class="budget-progress-bar">
                            <div class="budget-progress-fill" style="width: ${progressCapped}%"></div>
                        </div>
                        <div class="budget-stats">
                            <span class="budget-remaining">${remainingText}</span>
                            <span class="budget-percentage">${budget.percentage.toFixed(1)}%</span>
                        </div>
                        <div class="budget-actions">
                            <button class="btn btn-sm btn-secondary edit-budget-btn"
                                    data-id="${budget.budget_id}"
                                    data-category="${budget.category_id}"
                                    data-amount="${budget.budget_amount}"
                                    data-period="${budget.period}"
                                    data-threshold="${budget.alert_threshold}">
                                ✏️ Edit
                            </button>
                            <button class="btn btn-sm btn-danger delete-budget-btn" data-id="${budget.budget_id}">
                                🗑️ Delete
                            </button>
                        </div>
                    </div>
                `);
            });
        },

        checkBudgetAlerts: function() {
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_check_budget_alerts',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success && response.data.alerts.length > 0) {
                        this.showBudgetAlerts(response.data.alerts);
                    } else {
                        $('#budget-alerts-container').hide();
                    }
                }
            });
        },

        showBudgetAlerts: function(alerts) {
            const $container = $('#budget-alerts-container');
            $container.empty();

            let alertHTML = `
                <div class="budget-alert-title">⚠️ Budget Alerts</div>
                <ul class="budget-alert-list">
            `;

            alerts.forEach(alert => {
                const percentage = parseFloat(alert.percentage).toFixed(1);
                const statusText = alert.is_over_budget
                    ? `${percentage}% - OVER BUDGET!`
                    : `${percentage}% spent`;

                alertHTML += `
                    <li class="budget-alert-item">
                        <span class="budget-alert-category">
                            ${alert.category_emoji} ${alert.category_name}
                        </span>
                        <span class="budget-alert-status">${statusText}</span>
                    </li>
                `;
            });

            alertHTML += '</ul>';
            $container.html(alertHTML).show();
        },

        openBudgetModal: function() {
            $('#budget-modal-title').text('💰 Set Budget');
            $('#budget-form')[0].reset();
            $('#budget-id').val('');

            // Load categories for budget dropdown
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_categories',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const $select = $('#budget-category');
                        $select.find('option:not(:first)').remove();

                        // Only show expense categories
                        const expenseCategories = response.data.filter(cat =>
                            cat.type === 'expense' || cat.type === 'both'
                        );

                        expenseCategories.forEach(category => {
                            $select.append(`<option value="${category.id}">${category.emoji} ${category.name}</option>`);
                        });
                    }
                }
            });

            $('#budget-modal').addClass('active');
        },

        handleSaveBudget: function(e) {
            e.preventDefault();

            const budgetId = $('#budget-id').val();
            const action = budgetId ? 'rizqtrack_update_budget' : 'rizqtrack_add_budget';

            const data = {
                action: action,
                nonce: rizqtrack.nonce,
                category_id: $('#budget-category').val(),
                amount: $('#budget-amount').val(),
                period: $('#budget-period').val(),
                alert_threshold: $('#budget-threshold').val(),
                rollover: $('#budget-rollover').is(':checked') ? 1 : 0,
                start_date: new Date().toISOString().split('T')[0]
            };

            if (budgetId) {
                data.budget_id = budgetId;
            }

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showNotification(response.data.message, 'success');
                        this.closeModals();
                        this.loadBudgets();
                        this.checkBudgetAlerts();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        handleEditBudget: function(e) {
            const $btn = $(e.currentTarget);

            $('#budget-modal-title').text('✏️ Edit Budget');
            $('#budget-id').val($btn.data('id'));
            $('#budget-category').val($btn.data('category')).prop('disabled', true);
            $('#budget-amount').val($btn.data('amount'));
            $('#budget-period').val($btn.data('period'));
            $('#budget-threshold').val($btn.data('threshold'));

            // Load categories
            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_get_categories',
                    nonce: rizqtrack.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const $select = $('#budget-category');
                        $select.find('option:not(:first)').remove();

                        const expenseCategories = response.data.filter(cat =>
                            cat.type === 'expense' || cat.type === 'both'
                        );

                        expenseCategories.forEach(category => {
                            $select.append(`<option value="${category.id}">${category.emoji} ${category.name}</option>`);
                        });

                        $select.val($btn.data('category'));
                    }
                }
            });

            $('#budget-modal').addClass('active');
        },

        handleDeleteBudget: function(e) {
            const budgetId = $(e.currentTarget).data('id');

            if (!confirm('Are you sure you want to delete this budget?')) {
                return;
            }

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_delete_budget',
                    nonce: rizqtrack.nonce,
                    budget_id: budgetId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification(response.data.message, 'success');
                        this.loadBudgets();
                        this.checkBudgetAlerts();
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                }
            });
        },

        // ===========================================
        // EMAIL REPORT FUNCTIONS
        // ===========================================
        handleSendEmailNow: function() {
            const emailAddress = $('#email-address').val();
            const startDate = $('#email-start-date').val();
            const endDate = $('#email-end-date').val();

            if (!emailAddress) {
                this.showNotification('Please enter an email address', 'error');
                return;
            }

            if (!startDate || !endDate) {
                this.showNotification('Please select both start and end dates', 'error');
                return;
            }

            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailAddress)) {
                this.showNotification('Please enter a valid email address', 'error');
                return;
            }

            // Date validation
            if (new Date(startDate) > new Date(endDate)) {
                this.showNotification('Start date must be before end date', 'error');
                return;
            }

            if (!confirm('Send a financial report to ' + emailAddress + ' for the period ' + startDate + ' to ' + endDate + '?')) {
                return;
            }

            this.showNotification('Generating and sending comprehensive report...', 'info');

            $.ajax({
                url: rizqtrack.ajax_url,
                type: 'POST',
                data: {
                    action: 'rizqtrack_send_email_now',
                    nonce: rizqtrack.nonce,
                    email: emailAddress,
                    start_date: startDate,
                    end_date: endDate
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification(response.data.message, 'success');
                    } else {
                        this.showNotification(response.data.message, 'error');
                    }
                },
                error: () => {
                    this.showNotification('Failed to send report. Please try again.', 'error');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RizqTrackApp.init();
    });

})(jQuery);
