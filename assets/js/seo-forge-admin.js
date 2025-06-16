/**
 * SEO Forge Professional - Admin JavaScript
 * Handles all admin functionality including content generation, analytics, and SEO analysis
 * Version: 2.0.0
 */

(function($) {
    'use strict';

    // Global SEO Forge object
    window.SEOForge = window.SEOForge || {};

    /**
     * Main SEO Forge Admin Class
     */
    class SEOForgeAdmin {
        constructor() {
            this.init();
        }

        init() {
            try {
                this.bindEvents();
                this.initCharacterCounters();
                this.initCharts();
                this.initTooltips();
                this.initGeneratorTabs();
                this.handleImageGeneration();
                this.handleSaveGeneratedImage();
                
                // Only load analytics data if we're on the analytics tab
                const currentTab = this.getCurrentTab();
                if (currentTab === 'analytics') {
                    this.loadAnalyticsData();
                }
            } catch (error) {
                console.error('SEO Forge Admin initialization error:', error);
            }
        }

        /**
         * Get current tab from URL
         */
        getCurrentTab() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('tab') || 'dashboard';
        }

        /**
         * Bind all event handlers
         */
        bindEvents() {
            // Content Generator
            $('#content-generator-form').on('submit', this.handleContentGeneration.bind(this));
            $('#regenerate-content').on('click', this.handleContentRegeneration.bind(this));
            $('#save-generated-content').on('click', this.handleSaveGeneratedContent.bind(this));

            // SEO Analysis
            $('#analyze-seo').on('click', this.handleSEOAnalysis.bind(this));

            // Keywords Management
            $('#add-keyword-form').on('submit', this.handleAddKeyword.bind(this));
            $('.check-ranking').on('click', this.handleCheckRanking.bind(this));
            $('.delete-keyword').on('click', this.handleDeleteKeyword.bind(this));

            // Analytics
            $('#date_range').on('change', this.handleDateRangeChange.bind(this));

            // API Testing
            $('#test-seo-forge-api').on('click', this.handleTestAPI.bind(this));

            // Character counters
            $('#seo_forge_meta_title').on('input', this.updateTitleCounter.bind(this));
            $('#seo_forge_meta_description').on('input', this.updateDescriptionCounter.bind(this));

            // Auto-save settings
            $('.seo-forge-settings-section input, .seo-forge-settings-section select').on('change', 
                this.debounce(this.autoSaveSettings.bind(this), 1000)
            );

            // Real-time SEO analysis
            if ($('#post').length) {
                $('#title, #content').on('input', 
                    this.debounce(this.performRealTimeSEOAnalysis.bind(this), 2000)
                );
            }
        }

        /**
         * Initialize character counters for meta fields
         */
        initCharacterCounters() {
            this.updateTitleCounter();
            this.updateDescriptionCounter();
        }

        /**
         * Update title character counter
         */
        updateTitleCounter() {
            const titleField = $('#seo_forge_meta_title');
            const counter = $('#title-counter');
            
            if (titleField.length && counter.length) {
                const title = titleField.val() || '';
                const length = title.length;
                
                counter.text(length);
                counter.parent().toggleClass('over-limit', length > 60);
            }
        }

        /**
         * Update description character counter
         */
        updateDescriptionCounter() {
            const descriptionField = $('#seo_forge_meta_description');
            const counter = $('#description-counter');
            
            if (descriptionField.length && counter.length) {
                const description = descriptionField.val() || '';
                const length = description.length;
                
                counter.text(length);
                counter.parent().toggleClass('over-limit', length > 160);
            }
        }

        /**
         * Handle content generation form submission
         */
        handleContentGeneration(e) {
            e.preventDefault();
            
            const form = $(e.target);
            const submitButton = form.find('button[type="submit"]');
            const formData = {
                action: 'seo_forge_generate_content',
                nonce: seoForgeAjax.nonce,
                topic: $('#content_topic').val(),
                keywords: $('#content_keywords').val(),
                length: $('#content_length').val(),
                type: $('#content_type').val()
            };

            // Validate form
            if (!formData.topic.trim()) {
                this.showNotice('Please enter a topic for content generation.', 'error');
                return;
            }

            // Show loading state
            this.setLoadingState(submitButton, true);
            
            $.post(seoForgeAjax.ajaxurl, formData)
                .done((response) => {
                    if (response.success) {
                        this.displayGeneratedContent(response.data);
                        this.showNotice('Content generated successfully!', 'success');
                    } else {
                        this.showNotice('Failed to generate content: ' + (response.data || 'Unknown error'), 'error');
                    }
                })
                .fail(() => {
                    this.showNotice('Network error occurred while generating content.', 'error');
                })
                .always(() => {
                    this.setLoadingState(submitButton, false);
                });
        }

        /**
         * Handle content regeneration
         */
        handleContentRegeneration(e) {
            e.preventDefault();
            $('#content-generator-form').trigger('submit');
        }

        /**
         * Handle saving generated content
         */
        handleSaveGeneratedContent(e) {
            e.preventDefault();
            
            const content = $('#content-preview').html();
            const title = $('#content_topic').val();
            
            if (!content || !title) {
                this.showNotice('No content to save.', 'error');
                return;
            }

            const button = $(e.target);
            this.setLoadingState(button, true);

            const data = {
                action: 'wp_ajax_inline_save',
                post_type: 'post',
                post_title: title,
                post_content: content,
                post_status: 'draft',
                _wpnonce: seoForgeAjax.nonce
            };

            $.post(seoForgeAjax.ajaxurl, data)
                .done((response) => {
                    if (response.success) {
                        this.showNotice('Content saved as draft successfully!', 'success');
                        // Optionally redirect to edit post
                        if (response.data && response.data.post_id) {
                            const editUrl = seoForgeAjax.adminUrl + 'post.php?post=' + response.data.post_id + '&action=edit';
                            this.showNotice('Content saved! <a href="' + editUrl + '">Edit post</a>', 'success');
                        }
                    } else {
                        this.showNotice('Failed to save content.', 'error');
                    }
                })
                .fail(() => {
                    this.showNotice('Network error occurred while saving content.', 'error');
                })
                .always(() => {
                    this.setLoadingState(button, false);
                });
        }

        /**
         * Display generated content
         */
        displayGeneratedContent(data) {
            const container = $('#generated-content');
            const preview = $('#content-preview');
            const stats = $('#content-stats');

            // Convert markdown-like content to HTML
            let htmlContent = data.content
                .replace(/^# (.*$)/gim, '<h1>$1</h1>')
                .replace(/^## (.*$)/gim, '<h2>$1</h2>')
                .replace(/^### (.*$)/gim, '<h3>$1</h3>')
                .replace(/^\* (.*$)/gim, '<li>$1</li>')
                .replace(/^\d+\. (.*$)/gim, '<li>$1</li>')
                .replace(/\n\n/g, '</p><p>')
                .replace(/^\s*<p>/, '<p>')
                .replace(/<\/p>\s*$/, '</p>');

            // Wrap orphaned list items
            htmlContent = htmlContent.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');
            
            preview.html(htmlContent);

            // Display stats
            const statsHtml = `
                <div class="content-stat">
                    <span class="content-stat-value">${data.word_count}</span>
                    <span class="content-stat-label">Words</span>
                </div>
                <div class="content-stat">
                    <span class="content-stat-value">${data.seo_score}%</span>
                    <span class="content-stat-label">SEO Score</span>
                </div>
                <div class="content-stat">
                    <span class="content-stat-value">${this.calculateReadingTime(data.word_count)}</span>
                    <span class="content-stat-label">Reading Time</span>
                </div>
            `;
            stats.html(statsHtml);

            // Show suggestions if available
            if (data.suggestions && data.suggestions.length > 0) {
                const suggestionsHtml = '<div class="seo-suggestions"><h4>SEO Suggestions:</h4><ul>' +
                    data.suggestions.map(s => '<li>' + s + '</li>').join('') +
                    '</ul></div>';
                stats.append(suggestionsHtml);
            }

            container.fadeIn();
        }

        /**
         * Handle SEO analysis
         */
        handleSEOAnalysis(e) {
            e.preventDefault();
            
            const button = $(e.target);
            const postId = $('#post_ID').val() || 0;
            const content = $('#content').val() || '';
            const keyword = $('#seo_forge_focus_keyword').val() || '';

            if (!content.trim()) {
                this.showNotice('No content to analyze.', 'error');
                return;
            }

            this.setLoadingState(button, true);

            const data = {
                action: 'seo_forge_analyze_content',
                nonce: seoForgeAjax.nonce,
                post_id: postId,
                content: content,
                keyword: keyword
            };

            $.post(seoForgeAjax.ajaxurl, data)
                .done((response) => {
                    if (response.success) {
                        this.displaySEOAnalysis(response.data);
                        this.showNotice('SEO analysis completed!', 'success');
                    } else {
                        this.showNotice('Failed to analyze content.', 'error');
                    }
                })
                .fail(() => {
                    this.showNotice('Network error occurred during analysis.', 'error');
                })
                .always(() => {
                    this.setLoadingState(button, false);
                });
        }

        /**
         * Display SEO analysis results
         */
        displaySEOAnalysis(analysis) {
            const container = $('#seo-analysis-results');
            
            let html = `<div class="seo-analysis-score">
                <h4>SEO Score: <span class="seo-score score-${Math.floor(analysis.score / 20)}">${analysis.score}%</span></h4>
            </div>`;

            if (analysis.metrics) {
                html += '<div class="seo-metrics">';
                html += '<h5>Metrics:</h5>';
                html += '<ul>';
                html += `<li>Word Count: ${analysis.metrics.word_count}</li>`;
                html += `<li>Keyword Density: ${analysis.metrics.keyword_density}%</li>`;
                html += `<li>Readability Score: ${analysis.metrics.readability_score}</li>`;
                html += `<li>Headings: ${analysis.metrics.headings_count}</li>`;
                html += `<li>Internal Links: ${analysis.metrics.internal_links}</li>`;
                html += `<li>External Links: ${analysis.metrics.external_links}</li>`;
                html += '</ul>';
                html += '</div>';
            }

            if (analysis.issues && analysis.issues.length > 0) {
                html += '<div class="seo-issues">';
                html += '<h5>Issues to Fix:</h5>';
                html += '<ul class="seo-issues-list">';
                analysis.issues.forEach(issue => {
                    html += `<li class="seo-issue-error">${issue}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }

            if (analysis.suggestions && analysis.suggestions.length > 0) {
                html += '<div class="seo-suggestions">';
                html += '<h5>Suggestions:</h5>';
                html += '<ul class="seo-suggestions-list">';
                analysis.suggestions.forEach(suggestion => {
                    html += `<li class="seo-suggestion">${suggestion}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }

            container.html(html);
        }

        /**
         * Handle adding new keyword
         */
        handleAddKeyword(e) {
            e.preventDefault();
            
            const form = $(e.target);
            const formData = {
                action: 'seo_forge_add_keyword',
                nonce: $('#seo_forge_keyword_nonce').val(),
                keyword: $('#keyword').val(),
                target_url: $('#target_url').val(),
                search_engine: $('#search_engine').val()
            };

            if (!formData.keyword.trim()) {
                this.showNotice('Please enter a keyword.', 'error');
                return;
            }

            const submitButton = form.find('button[type="submit"]');
            this.setLoadingState(submitButton, true);

            $.post(seoForgeAjax.ajaxurl, formData)
                .done((response) => {
                    if (response.success) {
                        this.showNotice('Keyword added successfully!', 'success');
                        form[0].reset();
                        // Reload keywords table
                        this.loadKeywordsTable();
                    } else {
                        this.showNotice('Failed to add keyword.', 'error');
                    }
                })
                .fail(() => {
                    this.showNotice('Network error occurred.', 'error');
                })
                .always(() => {
                    this.setLoadingState(submitButton, false);
                });
        }

        /**
         * Handle checking keyword ranking
         */
        handleCheckRanking(e) {
            e.preventDefault();
            
            const button = $(e.target);
            const keywordId = button.data('keyword-id');

            this.setLoadingState(button, true);

            const data = {
                action: 'seo_forge_check_ranking',
                nonce: seoForgeAjax.nonce,
                keyword_id: keywordId
            };

            $.post(seoForgeAjax.ajaxurl, data)
                .done((response) => {
                    if (response.success) {
                        this.showNotice('Ranking updated successfully!', 'success');
                        this.loadKeywordsTable();
                    } else {
                        this.showNotice('Failed to check ranking.', 'error');
                    }
                })
                .fail(() => {
                    this.showNotice('Network error occurred.', 'error');
                })
                .always(() => {
                    this.setLoadingState(button, false);
                });
        }

        /**
         * Handle deleting keyword
         */
        handleDeleteKeyword(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete this keyword?')) {
                return;
            }

            const button = $(e.target);
            const keywordId = button.data('keyword-id');

            this.setLoadingState(button, true);

            const data = {
                action: 'seo_forge_delete_keyword',
                nonce: seoForgeAjax.nonce,
                keyword_id: keywordId
            };

            $.post(seoForgeAjax.ajaxurl, data)
                .done((response) => {
                    if (response.success) {
                        this.showNotice('Keyword deleted successfully!', 'success');
                        button.closest('tr').fadeOut();
                    } else {
                        this.showNotice('Failed to delete keyword.', 'error');
                    }
                })
                .fail(() => {
                    this.showNotice('Network error occurred.', 'error');
                })
                .always(() => {
                    this.setLoadingState(button, false);
                });
        }

        /**
         * Handle date range change for analytics
         */
        handleDateRangeChange(e) {
            const days = $(e.target).val();
            this.loadAnalyticsData(days);
        }

        /**
         * Handle API testing
         */
        handleTestAPI(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $result = $('#api-test-result');
            
            // Show loading state
            $button.prop('disabled', true).text('Testing...');
            $result.html('<div class="notice notice-info"><p>Testing SEO-Forge API connection...</p></div>');
            
            const data = {
                action: 'seo_forge_test_api',
                nonce: seoForgeAjax.nonce
            };

            $.post(seoForgeAjax.ajaxurl, data)
                .done((response) => {
                    if (response.success) {
                        $result.html(`
                            <div class="notice notice-success">
                                <p><strong>${response.data.message}</strong></p>
                                ${response.data.sample_content ? `<p><em>Sample content:</em> ${response.data.sample_content}</p>` : ''}
                            </div>
                        `);
                    } else {
                        $result.html(`
                            <div class="notice notice-error">
                                <p><strong>${response.data.message}</strong></p>
                            </div>
                        `);
                    }
                })
                .fail((xhr, status, error) => {
                    $result.html(`
                        <div class="notice notice-error">
                            <p><strong>API test failed:</strong> ${error}</p>
                        </div>
                    `);
                })
                .always(() => {
                    $button.prop('disabled', false).text('Test API Connection');
                });
        }

        /**
         * Load analytics data
         */
        loadAnalyticsData(days = 30) {
            const data = {
                action: 'seo_forge_get_analytics',
                nonce: seoForgeAjax.nonce,
                days: days
            };

            $.post(seoForgeAjax.ajaxurl, data)
                .done((response) => {
                    if (response.success) {
                        this.updateCharts(response.data);
                    }
                })
                .fail(() => {
                    console.error('Failed to load analytics data');
                });
        }

        /**
         * Initialize charts
         */
        initCharts() {
            // Initialize Chart.js if available
            if (typeof Chart !== 'undefined') {
                this.initPageViewsChart();
                this.initSEOPerformanceChart();
            } else {
                // Fallback to simple charts using CSS
                this.initSimpleCharts();
            }
        }

        /**
         * Initialize page views chart
         */
        initPageViewsChart() {
            const ctx = document.getElementById('pageviews-chart');
            if (!ctx) return;

            this.pageViewsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Page Views',
                        data: [],
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        /**
         * Initialize SEO performance chart
         */
        initSEOPerformanceChart() {
            const ctx = document.getElementById('seo-performance-chart');
            if (!ctx) return;

            this.seoPerformanceChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Optimized', 'Needs Improvement', 'Poor'],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: ['#00a32a', '#dba617', '#d63638']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        /**
         * Initialize simple charts fallback
         */
        initSimpleCharts() {
            // Create simple bar charts using CSS
            $('.chart-container canvas').each(function() {
                const canvas = $(this);
                const container = canvas.parent();
                
                // Replace canvas with simple chart div
                const chartDiv = $('<div class="simple-chart"></div>');
                canvas.replaceWith(chartDiv);
                
                // Add sample data bars
                for (let i = 0; i < 7; i++) {
                    const height = Math.random() * 80 + 20;
                    const bar = $(`<div class="chart-bar" style="height: ${height}%"></div>`);
                    chartDiv.append(bar);
                }
            });
        }

        /**
         * Update charts with new data
         */
        updateCharts(data) {
            if (this.pageViewsChart && data.pageviews) {
                const labels = data.pageviews.map(item => item.date);
                const values = data.pageviews.map(item => item.views);
                
                this.pageViewsChart.data.labels = labels;
                this.pageViewsChart.data.datasets[0].data = values;
                this.pageViewsChart.update();
            }

            // Update other charts as needed
        }

        /**
         * Auto-save settings
         */
        autoSaveSettings() {
            const settings = {};
            $('.seo-forge-settings-section input, .seo-forge-settings-section select').each(function() {
                const input = $(this);
                const name = input.attr('name');
                let value = input.val();
                
                if (input.attr('type') === 'checkbox') {
                    value = input.is(':checked') ? 1 : 0;
                }
                
                if (name) {
                    settings[name] = value;
                }
            });

            const data = {
                action: 'seo_forge_save_settings',
                nonce: seoForgeAjax.nonce,
                settings: settings
            };

            $.post(seoForgeAjax.ajaxurl, data)
                .done((response) => {
                    if (response.success) {
                        this.showNotice('Settings saved automatically.', 'success', 2000);
                    }
                });
        }

        /**
         * Perform real-time SEO analysis
         */
        performRealTimeSEOAnalysis() {
            const title = $('#title').val() || '';
            const content = $('#content').val() || '';
            const keyword = $('#seo_forge_focus_keyword').val() || '';

            if (!content.trim()) return;

            // Simple client-side analysis
            const analysis = this.performClientSideAnalysis(title, content, keyword);
            this.displayRealTimeAnalysis(analysis);
        }

        /**
         * Perform client-side SEO analysis
         */
        performClientSideAnalysis(title, content, keyword) {
            const wordCount = content.trim().split(/\s+/).length;
            const keywordDensity = keyword ? (content.toLowerCase().split(keyword.toLowerCase()).length - 1) / wordCount * 100 : 0;
            
            let score = 0;
            const issues = [];
            const suggestions = [];

            // Word count check
            if (wordCount < 300) {
                issues.push('Content is too short. Aim for at least 300 words.');
            } else if (wordCount >= 300) {
                score += 25;
            }

            // Title check
            if (!title.trim()) {
                issues.push('Title is missing.');
            } else if (title.length < 30) {
                suggestions.push('Title could be longer for better SEO.');
                score += 15;
            } else if (title.length > 60) {
                issues.push('Title is too long. Keep it under 60 characters.');
                score += 10;
            } else {
                score += 25;
            }

            // Keyword density check
            if (keyword) {
                if (keywordDensity < 0.5) {
                    suggestions.push('Consider using the focus keyword more often.');
                    score += 10;
                } else if (keywordDensity > 3) {
                    issues.push('Keyword density is too high. Reduce keyword usage.');
                    score += 5;
                } else {
                    score += 25;
                }
            }

            // Heading check
            const headingCount = (content.match(/<h[1-6][^>]*>/gi) || []).length;
            if (headingCount === 0) {
                suggestions.push('Add headings to structure your content.');
                score += 5;
            } else {
                score += 15;
            }

            // Link check
            const linkCount = (content.match(/<a[^>]*>/gi) || []).length;
            if (linkCount === 0) {
                suggestions.push('Add some internal and external links.');
                score += 5;
            } else {
                score += 10;
            }

            return {
                score: Math.min(score, 100),
                issues: issues,
                suggestions: suggestions,
                metrics: {
                    word_count: wordCount,
                    keyword_density: keywordDensity.toFixed(2),
                    headings_count: headingCount,
                    links_count: linkCount
                }
            };
        }

        /**
         * Display real-time analysis
         */
        displayRealTimeAnalysis(analysis) {
            const container = $('#seo-analysis-results');
            if (!container.length) return;

            let html = `<div class="real-time-analysis">
                <h5>Real-time SEO Analysis</h5>
                <div class="seo-score score-${Math.floor(analysis.score / 20)}">${analysis.score}%</div>
            `;

            if (analysis.issues.length > 0) {
                html += '<div class="issues"><strong>Issues:</strong><ul>';
                analysis.issues.forEach(issue => {
                    html += `<li class="issue">${issue}</li>`;
                });
                html += '</ul></div>';
            }

            if (analysis.suggestions.length > 0) {
                html += '<div class="suggestions"><strong>Suggestions:</strong><ul>';
                analysis.suggestions.forEach(suggestion => {
                    html += `<li class="suggestion">${suggestion}</li>`;
                });
                html += '</ul></div>';
            }

            html += '</div>';
            container.html(html);
        }

        /**
         * Initialize tooltips
         */
        initTooltips() {
            // Simple tooltip implementation
            $('[data-tooltip]').hover(
                function() {
                    const tooltip = $('<div class="seo-forge-tooltip">' + $(this).data('tooltip') + '</div>');
                    $('body').append(tooltip);
                    
                    const offset = $(this).offset();
                    tooltip.css({
                        top: offset.top - tooltip.outerHeight() - 5,
                        left: offset.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2)
                    });
                },
                function() {
                    $('.seo-forge-tooltip').remove();
                }
            );
        }

        /**
         * Load keywords table
         */
        loadKeywordsTable() {
            // Reload the keywords table via AJAX
            const data = {
                action: 'seo_forge_load_keywords_table',
                nonce: seoForgeAjax.nonce
            };

            $.post(seoForgeAjax.ajaxurl, data)
                .done((response) => {
                    if (response.success) {
                        $('.seo-forge-keywords-list table tbody').html(response.data);
                        // Re-bind events for new elements
                        $('.check-ranking').off('click').on('click', this.handleCheckRanking.bind(this));
                        $('.delete-keyword').off('click').on('click', this.handleDeleteKeyword.bind(this));
                    }
                });
        }

        /**
         * Calculate reading time
         */
        calculateReadingTime(wordCount) {
            const wordsPerMinute = 200;
            const minutes = Math.ceil(wordCount / wordsPerMinute);
            return minutes + ' min';
        }

        /**
         * Set loading state for buttons
         */
        setLoadingState(button, loading) {
            if (loading) {
                button.prop('disabled', true)
                      .addClass('seo-forge-loading')
                      .data('original-text', button.text())
                      .text('Loading...');
            } else {
                button.prop('disabled', false)
                      .removeClass('seo-forge-loading')
                      .text(button.data('original-text') || button.text());
            }
        }

        /**
         * Show admin notice
         */
        showNotice(message, type = 'info', duration = 5000) {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible seo-forge-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            // Insert after the page title
            $('.wrap h1').first().after(notice);

            // Auto-dismiss after duration
            if (duration > 0) {
                setTimeout(() => {
                    notice.fadeOut(() => notice.remove());
                }, duration);
            }

            // Handle manual dismiss
            notice.find('.notice-dismiss').on('click', () => {
                notice.fadeOut(() => notice.remove());
            });
        }

        /**
         * Debounce function
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        /**
         * Initialize Generator Tabs
         */
        initGeneratorTabs() {
            // Generator tab switching
            $(document).on('click', '.generator-tab-btn', function(e) {
                e.preventDefault();
                
                const tabId = $(this).data('tab');
                
                // Update tab buttons
                $('.generator-tab-btn').removeClass('active');
                $(this).addClass('active');
                
                // Update tab content
                $('.generator-tab-content').removeClass('active');
                $('#' + tabId + '-generator-tab').addClass('active');
            });
        }

        /**
         * Handle Image Generation
         */
        handleImageGeneration() {
            $(document).on('submit', '#image-generator-form', (e) => {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const $form = $(e.target);
                const $submitBtn = $form.find('button[type="submit"]');
                const $preview = $('#image-preview');
                const $container = $('#generated-image');
                
                // Show loading state
                $submitBtn.prop('disabled', true).text('Generating...');
                $preview.html('<div class="image-loading">Generating your image...</div>');
                $container.show();
                
                // Prepare request data
                const requestData = {
                    action: 'seo_forge_generate_image',
                    nonce: formData.get('seo_forge_image_nonce'),
                    prompt: formData.get('prompt'),
                    style: formData.get('style'),
                    size: formData.get('size')
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: requestData,
                    timeout: 60000, // 60 seconds timeout
                    success: (response) => {
                        if (response.success && response.data.image_url) {
                            $preview.html(`
                                <img src="${response.data.image_url}" alt="Generated Image" />
                                <p><strong>Prompt:</strong> ${requestData.prompt}</p>
                            `);
                            
                            // Store image data for saving
                            $('#save-generated-image').data('image-data', response.data);
                        } else {
                            $preview.html(`
                                <div class="notice notice-error">
                                    <p>Error: ${response.data?.message || 'Failed to generate image'}</p>
                                </div>
                            `);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('Image generation error:', error);
                        $preview.html(`
                            <div class="notice notice-error">
                                <p>Error: Failed to generate image. Please try again.</p>
                            </div>
                        `);
                    },
                    complete: () => {
                        $submitBtn.prop('disabled', false).text('Generate Image');
                    }
                });
            });
        }

        /**
         * Handle Save Generated Image
         */
        handleSaveGeneratedImage() {
            $(document).on('click', '#save-generated-image', function() {
                const imageData = $(this).data('image-data');
                if (!imageData) {
                    alert('No image data to save');
                    return;
                }
                
                const $btn = $(this);
                $btn.prop('disabled', true).text('Saving...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'seo_forge_save_generated_image',
                        nonce: $('#seo_forge_image_nonce').val(),
                        image_data: imageData
                    },
                    success: (response) => {
                        if (response.success) {
                            alert('Image saved to media library successfully!');
                        } else {
                            alert('Error saving image: ' + (response.data?.message || 'Unknown error'));
                        }
                    },
                    error: () => {
                        alert('Error saving image. Please try again.');
                    },
                    complete: () => {
                        $btn.prop('disabled', false).text('Save to Media Library');
                    }
                });
            });
        }
    }

    /**
     * Utility functions
     */
    window.SEOForge.utils = {
        /**
         * Format number with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },

        /**
         * Truncate text
         */
        truncateText: function(text, length) {
            if (text.length <= length) return text;
            return text.substr(0, length) + '...';
        },

        /**
         * Get URL parameter
         */
        getUrlParameter: function(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            const results = regex.exec(location.search);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        },

        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        window.SEOForge.admin = new SEOForgeAdmin();
        
        // Add copy functionality to generated content
        $(document).on('click', '.copy-content', function() {
            const content = $('#content-preview').text();
            window.SEOForge.utils.copyToClipboard(content);
            window.SEOForge.admin.showNotice('Content copied to clipboard!', 'success', 2000);
        });

        // Add export functionality
        $(document).on('click', '.export-analytics', function() {
            // Simple CSV export
            const data = 'Date,Views\n' + 
                        'Sample data for CSV export\n' +
                        '2024-01-01,100\n' +
                        '2024-01-02,150\n';
            
            const blob = new Blob([data], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'seo-forge-analytics.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl+G for content generation
            if (e.ctrlKey && e.key === 'g') {
                e.preventDefault();
                const tab = window.SEOForge.utils.getUrlParameter('tab');
                if (tab === 'generator') {
                    $('#content-generator-form').trigger('submit');
                } else {
                    window.location.href = seoForgeAjax.adminUrl + 'admin.php?page=seo-forge&tab=generator';
                }
            }
            
            // Ctrl+S for save (in content generator)
            if (e.ctrlKey && e.key === 's') {
                const tab = window.SEOForge.utils.getUrlParameter('tab');
                if (tab === 'generator' && $('#generated-content').is(':visible')) {
                    e.preventDefault();
                    $('#save-generated-content').trigger('click');
                }
            }
        });

        // Auto-refresh analytics every 5 minutes
        if (window.SEOForge.utils.getUrlParameter('tab') === 'analytics') {
            setInterval(function() {
                window.SEOForge.admin.loadAnalyticsData();
            }, 300000); // 5 minutes
        }
    });

})(jQuery);