/**
 * SEO Forge Professional - Frontend JavaScript
 * Handles frontend tracking and user interactions
 * Version: 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Frontend SEO Forge Class
     */
    class SEOForgeFrontend {
        constructor() {
            this.init();
        }

        init() {
            if (typeof seoForge !== 'undefined' && seoForge.trackingEnabled) {
                this.trackPageView();
                this.bindTrackingEvents();
            }
        }

        /**
         * Track page view
         */
        trackPageView() {
            const data = {
                action: 'seo_forge_track_pageview',
                nonce: seoForge.nonce,
                url: window.location.href,
                title: document.title,
                referrer: document.referrer
            };

            $.post(seoForge.ajaxurl, data);
        }

        /**
         * Bind tracking events
         */
        bindTrackingEvents() {
            // Track link clicks
            $('a[href^="http"]').not('[href*="' + window.location.hostname + '"]').on('click', (e) => {
                this.trackEvent('external_link_click', {
                    url: e.target.href,
                    text: e.target.textContent
                });
            });

            // Track form submissions
            $('form').on('submit', (e) => {
                this.trackEvent('form_submission', {
                    form_id: e.target.id || 'unknown',
                    form_action: e.target.action || window.location.href
                });
            });

            // Track scroll depth
            this.trackScrollDepth();

            // Track time on page
            this.trackTimeOnPage();
        }

        /**
         * Track custom events
         */
        trackEvent(eventType, eventData = {}) {
            const data = {
                action: 'seo_forge_track_event',
                nonce: seoForge.nonce,
                event_type: eventType,
                event_data: JSON.stringify(eventData),
                url: window.location.href
            };

            $.post(seoForge.ajaxurl, data);
        }

        /**
         * Track scroll depth
         */
        trackScrollDepth() {
            let maxScroll = 0;
            const milestones = [25, 50, 75, 100];
            const tracked = [];

            $(window).on('scroll', () => {
                const scrollTop = $(window).scrollTop();
                const docHeight = $(document).height() - $(window).height();
                const scrollPercent = Math.round((scrollTop / docHeight) * 100);

                if (scrollPercent > maxScroll) {
                    maxScroll = scrollPercent;
                    
                    milestones.forEach(milestone => {
                        if (scrollPercent >= milestone && !tracked.includes(milestone)) {
                            tracked.push(milestone);
                            this.trackEvent('scroll_depth', { depth: milestone });
                        }
                    });
                }
            });
        }

        /**
         * Track time on page
         */
        trackTimeOnPage() {
            const startTime = Date.now();
            
            // Track when user leaves the page
            $(window).on('beforeunload', () => {
                const timeSpent = Math.round((Date.now() - startTime) / 1000);
                this.trackEvent('time_on_page', { seconds: timeSpent });
            });

            // Track at intervals for active users
            setInterval(() => {
                const timeSpent = Math.round((Date.now() - startTime) / 1000);
                if (timeSpent % 30 === 0) { // Every 30 seconds
                    this.trackEvent('time_milestone', { seconds: timeSpent });
                }
            }, 1000);
        }
    }

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        new SEOForgeFrontend();
    });

})(jQuery);