/**
 * SEO Forge Admin JavaScript
 * 
 * @package SEOForge
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    // Global SEO Forge Admin object
    window.SEOForgeAdmin = {
        init: function() {
            this.bindEvents();
            this.initComponents();
        },
        
        bindEvents: function() {
            // Meta box character counter
            this.initCharacterCounter();
            
            // Content analysis
            this.initContentAnalysis();
            
            // Settings form handling
            this.initSettingsForm();
            
            // Tab navigation
            this.initTabs();
            
            // Tooltips
            this.initTooltips();
            
            // Confirmation dialogs
            this.initConfirmations();
        },
        
        initComponents: function() {
            // Initialize any complex components
            this.initDashboardWidgets();
            this.initDataTables();
        },
        
        initCharacterCounter: function() {
            // Meta description counter
            $(document).on('input', '#seo_forge_meta_description', function() {
                var $this = $(this);
                var length = $this.val().length;
                var $counter = $('#meta-description-counter');
                
                if ($counter.length === 0) {
                    $counter = $('<span id="meta-description-counter">0/160</span>');
                    $this.after($counter);
                }
                
                $counter.text(length + '/160');
                
                // Color coding
                $counter.removeClass('good ok bad');
                if (length > 160) {
                    $counter.addClass('bad');
                } else if (length > 140) {
                    $counter.addClass('ok');
                } else if (length > 0) {
                    $counter.addClass('good');
                }
            });
            
            // Trigger on page load
            $('#seo_forge_meta_description').trigger('input');
        },
        
        initContentAnalysis: function() {
            var self = this;
            
            // Analyze content button
            $(document).on('click', '#seo-forge-analyze', function(e) {
                e.preventDefault();
                self.analyzeContent($(this));
            });
            
            // Auto-analyze on content change (debounced)
            var analyzeTimeout;
            $(document).on('input', '#content, #title', function() {
                clearTimeout(analyzeTimeout);
                analyzeTimeout = setTimeout(function() {
                    self.autoAnalyzeContent();
                }, 2000);
            });
        },
        
        analyzeContent: function($button) {
            var originalText = $button.text();
            $button.text(seoForgeAdmin.strings.saving).prop('disabled', true);
            
            // Get content from editor
            var content = this.getEditorContent();
            var title = $('#title').val() || '';
            var keyword = $('#seo_forge_focus_keyword').val() || '';
            
            $.ajax({
                url: seoForgeAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'seo_forge_analyze_content',
                    content: content,
                    title: title,
                    keyword: keyword,
                    _wpnonce: seoForgeAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SEOForgeAdmin.displayAnalysisResults(response.data);
                    } else {
                        SEOForgeAdmin.showNotice(response.data.message || seoForgeAdmin.strings.error, 'error');
                    }
                },
                error: function() {
                    SEOForgeAdmin.showNotice(seoForgeAdmin.strings.error, 'error');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },
        
        autoAnalyzeContent: function() {
            // Lightweight auto-analysis
            var content = this.getEditorContent();
            var keyword = $('#seo_forge_focus_keyword').val() || '';
            
            if (!content || !keyword) {
                return;
            }
            
            // Simple client-side analysis
            var analysis = this.performClientSideAnalysis(content, keyword);
            this.updateQuickAnalysis(analysis);
        },
        
        performClientSideAnalysis: function(content, keyword) {
            var wordCount = this.countWords(content);
            var keywordDensity = this.calculateKeywordDensity(content, keyword);
            var readabilityScore = this.calculateReadabilityScore(content);
            
            return {
                word_count: wordCount,
                keyword_density: keywordDensity,
                readability_score: readabilityScore,
                seo_score: this.calculateSEOScore(wordCount, keywordDensity, readabilityScore)
            };
        },
        
        countWords: function(text) {
            var cleanText = text.replace(/<[^>]*>/g, '').trim();
            return cleanText ? cleanText.split(/\s+/).length : 0;
        },
        
        calculateKeywordDensity: function(content, keyword) {
            if (!keyword) return 0;
            
            var cleanContent = content.replace(/<[^>]*>/g, '').toLowerCase();
            var keywordLower = keyword.toLowerCase();
            var matches = (cleanContent.match(new RegExp(keywordLower, 'g')) || []).length;
            var totalWords = this.countWords(content);
            
            return totalWords > 0 ? ((matches / totalWords) * 100).toFixed(2) : 0;
        },
        
        calculateReadabilityScore: function(content) {
            // Simplified Flesch Reading Ease calculation
            var cleanText = content.replace(/<[^>]*>/g, '');
            var sentences = cleanText.split(/[.!?]+/).filter(s => s.trim().length > 0).length;
            var words = this.countWords(content);
            var syllables = this.countSyllables(cleanText);
            
            if (sentences === 0 || words === 0) return 0;
            
            var score = 206.835 - (1.015 * (words / sentences)) - (84.6 * (syllables / words));
            return Math.max(0, Math.min(100, Math.round(score)));
        },
        
        countSyllables: function(text) {
            // Simplified syllable counting
            return text.toLowerCase()
                .replace(/[^a-z]/g, '')
                .replace(/[aeiouy]+/g, 'a')
                .replace(/a$/, '')
                .length || 1;
        },
        
        calculateSEOScore: function(wordCount, keywordDensity, readabilityScore) {
            var score = 0;
            
            // Word count scoring (ideal: 300-2000 words)
            if (wordCount >= 300 && wordCount <= 2000) {
                score += 30;
            } else if (wordCount >= 100) {
                score += 15;
            }
            
            // Keyword density scoring (ideal: 1-3%)
            if (keywordDensity >= 1 && keywordDensity <= 3) {
                score += 30;
            } else if (keywordDensity > 0 && keywordDensity < 5) {
                score += 15;
            }
            
            // Readability scoring
            if (readabilityScore >= 60) {
                score += 40;
            } else if (readabilityScore >= 30) {
                score += 20;
            }
            
            return Math.min(100, score);
        },
        
        updateQuickAnalysis: function(analysis) {
            var $quickAnalysis = $('#seo-forge-quick-analysis');
            if ($quickAnalysis.length === 0) {
                $quickAnalysis = $('<div id="seo-forge-quick-analysis" class="seo-forge-quick-analysis"></div>');
                $('#seo_forge_focus_keyword').closest('td').append($quickAnalysis);
            }
            
            var html = '<div class="quick-stats">';
            html += '<span class="stat">Words: ' + analysis.word_count + '</span>';
            html += '<span class="stat">Density: ' + analysis.keyword_density + '%</span>';
            html += '<span class="stat">SEO: ' + analysis.seo_score + '/100</span>';
            html += '</div>';
            
            $quickAnalysis.html(html);
        },
        
        displayAnalysisResults: function(data) {
            var $results = $('#seo-forge-analysis-results');
            if ($results.length === 0) {
                $results = $('<div id="seo-forge-analysis-results" class="analysis-results"></div>');
                $('.seo-forge-actions').after($results);
            }
            
            var html = '<h4>' + seoForgeAdmin.strings.analysisResults + '</h4>';
            
            html += '<div class="analysis-scores">';
            html += '<div class="score-item"><strong>SEO Score:</strong> ' + data.seo_score + '/100</div>';
            html += '<div class="score-item"><strong>Readability:</strong> ' + data.readability_score + '/100</div>';
            html += '<div class="score-item"><strong>Word Count:</strong> ' + data.word_count + '</div>';
            html += '<div class="score-item"><strong>Keyword Density:</strong> ' + data.keyword_density + '%</div>';
            html += '</div>';
            
            if (data.suggestions && data.suggestions.length > 0) {
                html += '<h5>Suggestions for Improvement:</h5>';
                html += '<ul>';
                data.suggestions.forEach(function(suggestion) {
                    html += '<li>' + suggestion + '</li>';
                });
                html += '</ul>';
            }
            
            $results.html(html).show();
        },
        
        getEditorContent: function() {
            var content = '';
            
            // Try TinyMCE first
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                content = tinyMCE.activeEditor.getContent();
            } else {
                // Fallback to textarea
                content = $('#content').val() || '';
            }
            
            return content;
        },
        
        initSettingsForm: function() {
            var self = this;
            
            // Settings form submission
            $(document).on('submit', '.seo-forge-settings-form', function(e) {
                e.preventDefault();
                self.saveSettings($(this));
            });
            
            // API key validation
            $(document).on('blur', 'input[name*="api_key"]', function() {
                self.validateApiKey($(this));
            });
        },
        
        saveSettings: function($form) {
            var $submitButton = $form.find('input[type="submit"]');
            var originalValue = $submitButton.val();
            
            $submitButton.val(seoForgeAdmin.strings.saving).prop('disabled', true);
            
            $.ajax({
                url: seoForgeAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=seo_forge_save_settings&_wpnonce=' + seoForgeAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        SEOForgeAdmin.showNotice(seoForgeAdmin.strings.saved, 'success');
                    } else {
                        SEOForgeAdmin.showNotice(response.data.message || seoForgeAdmin.strings.error, 'error');
                    }
                },
                error: function() {
                    SEOForgeAdmin.showNotice(seoForgeAdmin.strings.error, 'error');
                },
                complete: function() {
                    $submitButton.val(originalValue).prop('disabled', false);
                }
            });
        },
        
        validateApiKey: function($input) {
            var apiKey = $input.val().trim();
            if (!apiKey) return;
            
            var $status = $input.siblings('.api-key-status');
            if ($status.length === 0) {
                $status = $('<span class="api-key-status"></span>');
                $input.after($status);
            }
            
            $status.html('<span class="spinner is-active"></span> Validating...');
            
            $.ajax({
                url: seoForgeAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'seo_forge_validate_api_key',
                    api_key: apiKey,
                    _wpnonce: seoForgeAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Valid');
                    } else {
                        $status.html('<span class="dashicons dashicons-dismiss" style="color: red;"></span> Invalid');
                    }
                },
                error: function() {
                    $status.html('<span class="dashicons dashicons-warning" style="color: orange;"></span> Error');
                }
            });
        },
        
        initTabs: function() {
            $(document).on('click', '.seo-forge-tabs .nav-tab', function(e) {
                e.preventDefault();
                
                var $tab = $(this);
                var target = $tab.attr('href');
                
                // Update active tab
                $tab.siblings().removeClass('nav-tab-active');
                $tab.addClass('nav-tab-active');
                
                // Show target content
                $('.seo-forge-tab-content').hide();
                $(target).show();
            });
            
            // Show first tab by default
            $('.seo-forge-tabs .nav-tab:first').trigger('click');
        },
        
        initTooltips: function() {
            // Simple tooltip implementation
            $(document).on('mouseenter', '[data-tooltip]', function() {
                var $this = $(this);
                var text = $this.data('tooltip');
                
                var $tooltip = $('<div class="seo-forge-tooltip">' + text + '</div>');
                $('body').append($tooltip);
                
                var offset = $this.offset();
                $tooltip.css({
                    top: offset.top - $tooltip.outerHeight() - 5,
                    left: offset.left + ($this.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                });
            });
            
            $(document).on('mouseleave', '[data-tooltip]', function() {
                $('.seo-forge-tooltip').remove();
            });
        },
        
        initConfirmations: function() {
            $(document).on('click', '[data-confirm]', function(e) {
                var message = $(this).data('confirm') || seoForgeAdmin.strings.confirmDelete;
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        },
        
        initDashboardWidgets: function() {
            // Load dashboard data
            this.loadDashboardStats();
            this.loadRecentActivity();
        },
        
        loadDashboardStats: function() {
            $.ajax({
                url: seoForgeAdmin.restUrl + 'analytics',
                type: 'GET',
                headers: {
                    'X-WP-Nonce': seoForgeAdmin.nonce
                },
                success: function(response) {
                    // Update dashboard stats
                    SEOForgeAdmin.updateDashboardStats(response);
                }
            });
        },
        
        updateDashboardStats: function(data) {
            // Update stat numbers in dashboard
            if (data.content_count !== undefined) {
                $('.stat-item:contains("Content Generated") .stat-number').text(data.content_count);
            }
            if (data.keyword_count !== undefined) {
                $('.stat-item:contains("Keywords Tracked") .stat-number').text(data.keyword_count);
            }
            if (data.template_count !== undefined) {
                $('.stat-item:contains("Templates Created") .stat-number').text(data.template_count);
            }
        },
        
        loadRecentActivity: function() {
            // Load and display recent activity
            $.ajax({
                url: seoForgeAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'seo_forge_get_recent_activity',
                    _wpnonce: seoForgeAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        SEOForgeAdmin.displayRecentActivity(response.data);
                    }
                }
            });
        },
        
        displayRecentActivity: function(activities) {
            var $activityList = $('.activity-list');
            var html = '';
            
            activities.forEach(function(activity) {
                html += '<div class="activity-item">';
                html += '<span class="activity-time">' + activity.time + '</span>';
                html += '<span class="activity-text">' + activity.text + '</span>';
                html += '</div>';
            });
            
            $activityList.html(html);
        },
        
        initDataTables: function() {
            // Initialize data tables if DataTables is available
            if (typeof $.fn.DataTable !== 'undefined') {
                $('.seo-forge-data-table').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[0, 'desc']],
                    language: {
                        search: 'Search:',
                        lengthMenu: 'Show _MENU_ entries',
                        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                        paginate: {
                            first: 'First',
                            last: 'Last',
                            next: 'Next',
                            previous: 'Previous'
                        }
                    }
                });
            }
        },
        
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        // Utility functions
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },
        
        throttle: function(func, limit) {
            var inThrottle;
            return function() {
                var args = arguments;
                var context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(function() {
                        inThrottle = false;
                    }, limit);
                }
            };
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SEOForgeAdmin.init();
    });
    
    // Add some default strings if not provided
    if (typeof seoForgeAdmin === 'undefined') {
        window.seoForgeAdmin = {
            strings: {
                confirmDelete: 'Are you sure you want to delete this item?',
                saving: 'Saving...',
                saved: 'Saved!',
                error: 'An error occurred. Please try again.',
                analysisResults: 'SEO Analysis Results'
            }
        };
    }
    
})(jQuery);