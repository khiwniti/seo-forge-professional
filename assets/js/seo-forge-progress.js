/**
 * SEO-Forge Progress Tracking and Enhanced UI
 * Handles progress bars, language selection, and improved user experience
 */

(function($) {
    'use strict';

    // Progress tracking functionality
    window.SEOForgeProgress = {
        progressBar: null,
        progressText: null,
        intervalId: null,
        
        init: function() {
            this.createProgressBar();
            this.bindEvents();
        },
        
        createProgressBar: function() {
            if ($('#seo-forge-progress-container').length === 0) {
                const progressHTML = `
                    <div id="seo-forge-progress-container" style="display: none; margin: 20px 0;">
                        <div class="seo-forge-progress-wrapper">
                            <div class="seo-forge-progress-bar">
                                <div class="seo-forge-progress-fill" style="width: 0%;"></div>
                            </div>
                            <div class="seo-forge-progress-text">Ready</div>
                        </div>
                    </div>
                `;
                
                // Insert progress bar before content generation forms
                $('.seo-forge-content-generator, .seo-forge-form').first().prepend(progressHTML);
            }
            
            this.progressBar = $('.seo-forge-progress-fill');
            this.progressText = $('.seo-forge-progress-text');
        },
        
        show: function() {
            $('#seo-forge-progress-container').slideDown();
        },
        
        hide: function() {
            $('#seo-forge-progress-container').slideUp();
            this.stopTracking();
        },
        
        update: function(percentage, message) {
            if (this.progressBar) {
                this.progressBar.css('width', percentage + '%');
            }
            if (this.progressText && message) {
                this.progressText.text(message);
            }
        },
        
        startTracking: function() {
            this.show();
            this.intervalId = setInterval(() => {
                this.fetchProgress();
            }, 1000); // Check progress every second
        },
        
        stopTracking: function() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
        },
        
        fetchProgress: function() {
            $.ajax({
                url: seoForgeAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'seo_forge_get_progress',
                    nonce: seoForgeAjax.nonce
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.update(response.data.percentage, response.data.message);
                        
                        // Stop tracking when complete
                        if (response.data.percentage >= 100) {
                            setTimeout(() => {
                                this.hide();
                            }, 2000);
                        }
                    }
                },
                error: () => {
                    // Silently handle errors to avoid spam
                }
            });
        },
        
        bindEvents: function() {
            // Bind to content generation forms
            $(document).on('submit', '.seo-forge-content-form', (e) => {
                this.startTracking();
            });
            
            // Bind to generate buttons
            $(document).on('click', '.seo-forge-generate-btn', () => {
                this.startTracking();
            });
        }
    };

    // Language detection and support
    window.SEOForgeLanguage = {
        init: function() {
            this.addLanguageSelector();
            this.bindLanguageEvents();
        },
        
        addLanguageSelector: function() {
            if ($('.seo-forge-language-selector').length === 0) {
                const languageHTML = `
                    <div class="seo-forge-language-selector" style="margin: 10px 0;">
                        <label for="seo-forge-language">
                            <strong>Language / ภาษา:</strong>
                        </label>
                        <select id="seo-forge-language" name="language" class="regular-text">
                            <option value="auto">Auto Detect / ตรวจจับอัตโนมัติ</option>
                            <option value="en">English</option>
                            <option value="th">ไทย (Thai)</option>
                        </select>
                    </div>
                `;
                
                // Insert language selector in content generation forms
                $('.seo-forge-content-generator .form-table, .seo-forge-form .form-table').first().append(languageHTML);
            }
        },
        
        detectLanguage: function(text) {
            // Simple Thai character detection
            const thaiPattern = /[\u0E00-\u0E7F]/;
            return thaiPattern.test(text) ? 'th' : 'en';
        },
        
        bindLanguageEvents: function() {
            // Auto-detect language from topic input
            $(document).on('input', 'input[name="topic"], textarea[name="topic"]', function() {
                const text = $(this).val();
                if (text.length > 3) {
                    const detectedLang = SEOForgeLanguage.detectLanguage(text);
                    const currentSelection = $('#seo-forge-language').val();
                    
                    if (currentSelection === 'auto') {
                        $('#seo-forge-language').val(detectedLang);
                    }
                }
            });
        }
    };

    // Enhanced content generation with timeout handling
    window.SEOForgeContentGenerator = {
        init: function() {
            this.bindGenerationEvents();
            this.addTimeoutHandling();
        },
        
        bindGenerationEvents: function() {
            $(document).on('click', '.seo-forge-generate-content', (e) => {
                e.preventDefault();
                this.generateContent();
            });
            
            $(document).on('click', '.seo-forge-generate-image', (e) => {
                e.preventDefault();
                this.generateImage();
            });
            
            $(document).on('click', '.seo-forge-generate-blog-with-image', (e) => {
                e.preventDefault();
                this.generateBlogWithImage();
            });
        },
        
        generateContent: function() {
            const form = $('.seo-forge-content-form');
            const data = {
                action: 'seo_forge_generate_content',
                nonce: seoForgeAjax.nonce,
                topic: form.find('input[name="topic"]').val(),
                keywords: form.find('input[name="keywords"]').val(),
                length: form.find('select[name="length"]').val() || 500,
                type: form.find('select[name="type"]').val() || 'blog',
                language: form.find('select[name="language"]').val() || 'auto'
            };
            
            this.makeRequest(data, (response) => {
                if (response.success) {
                    this.displayContent(response.data);
                } else {
                    this.showError(response.data.message || 'Content generation failed');
                }
            });
        },
        
        generateImage: function() {
            const form = $('.seo-forge-image-form');
            const data = {
                action: 'seo_forge_generate_image',
                nonce: seoForgeAjax.nonce,
                prompt: form.find('input[name="prompt"]').val(),
                style: form.find('select[name="style"]').val() || 'realistic',
                size: form.find('select[name="size"]').val() || '1024x1024'
            };
            
            this.makeRequest(data, (response) => {
                if (response.success) {
                    this.displayImage(response.data);
                } else {
                    this.showError(response.data.message || 'Image generation failed');
                }
            });
        },
        
        generateBlogWithImage: function() {
            const form = $('.seo-forge-blog-image-form');
            const data = {
                action: 'seo_forge_generate_blog_with_image',
                nonce: seoForgeAjax.nonce,
                topic: form.find('input[name="topic"]').val(),
                keywords: form.find('input[name="keywords"]').val(),
                language: form.find('select[name="language"]').val() || 'auto',
                image_style: form.find('select[name="image_style"]').val() || 'realistic'
            };
            
            this.makeRequest(data, (response) => {
                if (response.success) {
                    this.displayBlogWithImage(response.data);
                } else {
                    this.showError(response.data.message || 'Blog with image generation failed');
                }
            });
        },
        
        makeRequest: function(data, callback) {
            SEOForgeProgress.startTracking();
            
            $.ajax({
                url: seoForgeAjax.ajaxurl,
                type: 'POST',
                data: data,
                timeout: 300000, // 5 minutes timeout
                success: callback,
                error: (xhr, status, error) => {
                    SEOForgeProgress.hide();
                    
                    if (status === 'timeout') {
                        this.showError('Request timed out. Please try again with a shorter content length.');
                    } else {
                        this.showError('Network error: ' + error);
                    }
                }
            });
        },
        
        displayContent: function(data) {
            const resultContainer = $('.seo-forge-result-container');
            if (resultContainer.length === 0) {
                $('.seo-forge-content-form').after('<div class="seo-forge-result-container"></div>');
            }
            
            const html = `
                <div class="seo-forge-content-result">
                    <h3>Generated Content</h3>
                    <div class="content-stats">
                        <span>Words: ${data.word_count || 0}</span>
                        <span>SEO Score: ${data.seo_score || 0}/100</span>
                        <span>Language: ${data.language || 'auto'}</span>
                    </div>
                    <div class="generated-content">${data.content}</div>
                    <button type="button" class="button button-primary seo-forge-copy-content">Copy Content</button>
                </div>
            `;
            
            $('.seo-forge-result-container').html(html);
        },
        
        displayImage: function(data) {
            const resultContainer = $('.seo-forge-image-result-container');
            if (resultContainer.length === 0) {
                $('.seo-forge-image-form').after('<div class="seo-forge-image-result-container"></div>');
            }
            
            const html = `
                <div class="seo-forge-image-result">
                    <h3>Generated Image</h3>
                    <img src="${data.image_url}" alt="Generated Image" style="max-width: 100%; height: auto;">
                    <div class="image-actions">
                        <button type="button" class="button button-primary seo-forge-download-image" data-url="${data.image_url}">Download Image</button>
                    </div>
                </div>
            `;
            
            $('.seo-forge-image-result-container').html(html);
        },
        
        displayBlogWithImage: function(data) {
            const resultContainer = $('.seo-forge-blog-image-result-container');
            if (resultContainer.length === 0) {
                $('.seo-forge-blog-image-form').after('<div class="seo-forge-blog-image-result-container"></div>');
            }
            
            const html = `
                <div class="seo-forge-blog-image-result">
                    <h3>Generated Blog with Image</h3>
                    <div class="blog-image">
                        <img src="${data.image_url}" alt="Blog Image" style="max-width: 100%; height: auto;">
                    </div>
                    <div class="blog-content">${data.content}</div>
                    <div class="blog-actions">
                        <button type="button" class="button button-primary seo-forge-copy-blog">Copy Blog Content</button>
                        <button type="button" class="button seo-forge-download-image" data-url="${data.image_url}">Download Image</button>
                    </div>
                </div>
            `;
            
            $('.seo-forge-blog-image-result-container').html(html);
        },
        
        showError: function(message) {
            const errorHTML = `
                <div class="notice notice-error is-dismissible">
                    <p><strong>SEO-Forge Error:</strong> ${message}</p>
                </div>
            `;
            
            $('.seo-forge-content-form, .seo-forge-image-form, .seo-forge-blog-image-form').first().prepend(errorHTML);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $('.notice-error').fadeOut();
            }, 5000);
        },
        
        addTimeoutHandling: function() {
            // Add retry functionality for failed requests
            $(document).on('click', '.seo-forge-retry-btn', (e) => {
                e.preventDefault();
                $('.notice-error').remove();
                
                // Retry the last action
                const lastAction = $(e.target).data('action');
                if (lastAction === 'content') {
                    this.generateContent();
                } else if (lastAction === 'image') {
                    this.generateImage();
                } else if (lastAction === 'blog-image') {
                    this.generateBlogWithImage();
                }
            });
        }
    };

    // Copy to clipboard functionality
    window.SEOForgeCopyHandler = {
        init: function() {
            this.bindCopyEvents();
        },
        
        bindCopyEvents: function() {
            $(document).on('click', '.seo-forge-copy-content, .seo-forge-copy-blog', (e) => {
                e.preventDefault();
                
                const content = $(e.target).siblings('.generated-content, .blog-content').text();
                this.copyToClipboard(content);
            });
        },
        
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    this.showCopySuccess();
                }).catch(() => {
                    this.fallbackCopy(text);
                });
            } else {
                this.fallbackCopy(text);
            }
        },
        
        fallbackCopy: function(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showCopySuccess();
            } catch (err) {
                this.showCopyError();
            }
            
            document.body.removeChild(textArea);
        },
        
        showCopySuccess: function() {
            const notice = $('<div class="notice notice-success is-dismissible"><p>Content copied to clipboard!</p></div>');
            $('.seo-forge-result-container').prepend(notice);
            
            setTimeout(() => {
                notice.fadeOut();
            }, 3000);
        },
        
        showCopyError: function() {
            const notice = $('<div class="notice notice-error is-dismissible"><p>Failed to copy content. Please select and copy manually.</p></div>');
            $('.seo-forge-result-container').prepend(notice);
            
            setTimeout(() => {
                notice.fadeOut();
            }, 5000);
        }
    };

    // Initialize all modules when document is ready
    $(document).ready(function() {
        SEOForgeProgress.init();
        SEOForgeLanguage.init();
        SEOForgeContentGenerator.init();
        SEOForgeCopyHandler.init();
        
        // Add CSS for progress bar and enhanced UI
        const css = `
            <style>
                .seo-forge-progress-wrapper {
                    background: #f1f1f1;
                    border-radius: 4px;
                    padding: 10px;
                    margin: 10px 0;
                }
                
                .seo-forge-progress-bar {
                    background: #e0e0e0;
                    border-radius: 3px;
                    height: 20px;
                    overflow: hidden;
                    margin-bottom: 5px;
                }
                
                .seo-forge-progress-fill {
                    background: linear-gradient(90deg, #0073aa, #00a0d2);
                    height: 100%;
                    transition: width 0.3s ease;
                    border-radius: 3px;
                }
                
                .seo-forge-progress-text {
                    font-size: 12px;
                    color: #666;
                    text-align: center;
                }
                
                .seo-forge-language-selector {
                    background: #f9f9f9;
                    padding: 10px;
                    border-radius: 4px;
                    margin: 10px 0;
                }
                
                .seo-forge-result-container {
                    margin-top: 20px;
                    padding: 15px;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                
                .content-stats {
                    margin: 10px 0;
                    padding: 10px;
                    background: #f0f0f1;
                    border-radius: 3px;
                }
                
                .content-stats span {
                    margin-right: 15px;
                    font-weight: bold;
                }
                
                .generated-content, .blog-content {
                    margin: 15px 0;
                    padding: 15px;
                    background: #fafafa;
                    border-left: 4px solid #0073aa;
                    white-space: pre-wrap;
                }
                
                .blog-image {
                    margin: 15px 0;
                    text-align: center;
                }
                
                .blog-actions, .image-actions {
                    margin-top: 15px;
                }
                
                .blog-actions button, .image-actions button {
                    margin-right: 10px;
                }
            </style>
        `;
        
        $('head').append(css);
    });

})(jQuery);