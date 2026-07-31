/**
 * AI Course Format - Enhanced Course Navigation
 * Adds keyboard navigation, current activity highlighting, and icon picker
 * 
 * @module     format_aicourse/courseformat
 * @package    format_aicourse
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/ajax', 'core/notification'], function($, Str, Ajax, Notification) {
    
    return {
        init: function() {
            // BUG-ACF-SCROLL-DELAY (v1.7.40): scrollToTop() was called here on every page
            // load, causing up to 1 second of smooth-scrolling during which all click
            // targets moved under the user's cursor  -  causing missed clicks on edit controls.
            // REMOVED: this.scrollToTop()
            this.initKeyboardNav();
            this.highlightCurrentActivity();
            this.enhanceCourseIndex();
            this.animateProgress();
            this.initIconPicker();
            this.initCompletionToggle();
            this.initAITutor();
            this.initSectionDuplicate();
            this.initSectionDelete();
            this.initAddSection();
            this.initGenerateBanner();
            this.initDeleteBanner();
        },
        
        /**
         * Moodle 4.0-4.2 fallback for hero injection.
         * Hook API doesn't exist in these versions, so we inject via JS.
         * The hero HTML is exposed via window.AICOURSE_HERO_HTML from extend_navigation_course().
         * Uses retry mechanism since extend_navigation_course() may run after DOMContentLoaded.
         */
        injectHeroFallback: function() {
            var maxRetries = 20;
            var retryDelay = 100; // ms
            var retryCount = 0;
            
            function attemptInjection() {
                // Prevent duplicate injection - check both possible wrapper classes
                if (document.querySelector('.aicourse-hero-sticky-wrap') || 
                    document.querySelector('[data-aicourse-hero]')) {
                    return;
                }
                
                // Only inject on aicourse format pages - check body class OR pagetype
                var isAiCourse = document.body.classList.contains('format-aicourse') ||
                                 document.body.className.indexOf('format-aicourse') !== -1 ||
                                 document.body.className.indexOf('course-view-aicourse') !== -1;
                
                if (!isAiCourse) {
                    return;
                }
                
                // Hero HTML must be exposed via window.AICOURSE_HERO_HTML
                // If not available yet, retry after delay
                if (!window.AICOURSE_HERO_HTML) {
                    retryCount++;
                    if (retryCount < maxRetries) {
                        setTimeout(attemptInjection, retryDelay);
                    }
                    return;
                }
                
                var wrapper = document.createElement('div');
                wrapper.innerHTML = window.AICOURSE_HERO_HTML;
                
                if (!wrapper.firstElementChild) {
                    return;
                }
                
                // Insert at start of #region-main for consistent positioning
                var target = document.getElementById('region-main') ||
                             document.querySelector('main') ||
                             document.querySelector('#page-content');
                
                if (target) {
                    // Insert ALL children (hero + chatbox) - chatbox is a sibling of hero
                    // Collect children first to maintain order
                    var children = [];
                    while (wrapper.firstElementChild) {
                        children.push(wrapper.firstElementChild);
                        wrapper.removeChild(wrapper.firstElementChild);
                    }
                    // Insert in REVERSE order at firstChild so they end up in original order
                    for (var i = children.length - 1; i >= 0; i--) {
                        var child = children[i];
                        // Add data attribute to hero for reliable detection
                        if (child.classList.contains('aicourse-hero-sticky-wrap')) {
                            child.setAttribute('data-aicourse-hero', '1');
                        }
                        target.insertBefore(child, target.firstChild);
                    }
                }
            }
            
            // Start injection attempts after DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', attemptInjection);
            } else {
                attemptInjection();
            }
        },
        
        scrollToTop: function() {
            // Scroll to hero banner on page load to maximize visible content
            // Uses retry approach since hero might be injected via hook after AMD init
            var maxAttempts = 10;
            var attemptCount = 0;
            var retryDelay = 100;
            
            function attemptScroll() {
                var hero = document.querySelector('.aicourse-hero-sticky-wrap') ||
                           document.querySelector('[data-aicourse-hero]');
                
                if (!hero) {
                    attemptCount++;
                    if (attemptCount < maxAttempts) {
                        setTimeout(attemptScroll, retryDelay);
                    }
                    return;
                }
                
                // Prevent repeat scrolling
                if (hero.dataset.scrolled === '1') return;
                hero.dataset.scrolled = '1';
                
                // Always scroll hero to top of viewport (below navbar)
                var rect = hero.getBoundingClientRect();
                var navbarHeight = 60;
                var offset = window.pageYOffset + rect.top - navbarHeight - 10;
                if (offset < 0) offset = 0;
                
                window.scrollTo({
                    top: offset,
                    behavior: 'smooth'
                });
            }
            
            // Start with a small delay to let DOM settle
            window.requestAnimationFrame(function() {
                setTimeout(attemptScroll, 50);
            });
        },
        
        initAITutor: function() {
            // AI Tutor toggle is handled by inline script in lib.php
            // This AMD module only needs to ensure button states are synced
            // No duplicate handlers here to avoid double-toggle issues
        },
        
        lastPercentage: null,
        
        animateProgress: function() {
            var self = this;
            var maxRetries = 5;
            var retryDelay = 200;
            var retryCount = 0;
            
            function attemptAnimate() {
                var $container = $('.aicourse-progress-ring-container');
                
                if (!$container.length) {
                    retryCount++;
                    if (retryCount < maxRetries) {
                        setTimeout(attemptAnimate, retryDelay);
                    }
                    return;
                }
                
                // Prevent duplicate animation
                if ($container.data('animated')) {
                    return;
                }
                $container.data('animated', true);
                
                var targetPercentage = parseInt($container.data('percentage'), 10) || 0;
                var courseid = $container.data('courseid');
                
                // Animate to initial value
                self.animateRing($container, targetPercentage, false);
                
                // BUG-ACF-LISTENER-STACK (v1.7.40): namespaced so re-init doesn't stack
                // multiple AJAX calls per completion event.
                $(document).off('completionchange.aicourse').on('completionchange.aicourse', function() {
                    self.fetchAndUpdateProgress(courseid, $container);
                });
                $(document).off('aicourse:completion_updated').on('aicourse:completion_updated', function() {
                    self.fetchAndUpdateProgress(courseid, $container);
                });
                
                // Animate horizontal progress bar (if present)
                var progressBar = $('.aicourse-progress-bar-fill');
                if (progressBar.length && !progressBar.data('animated')) {
                    progressBar.data('animated', true);
                    var targetWidth = progressBar.data('percentage') + '%';
                    progressBar.css('width', '0%');
                    
                    setTimeout(function() {
                        progressBar.css({
                            'transition': 'width 1s ease-out',
                            'width': targetWidth
                        });
                    }, 100);
                }
            }
            
            // Start with slight delay
            setTimeout(attemptAnimate, 50);
        },
        
        animateRing: function($container, newPercentage, triggerPulse) {
            var self = this;
            var $circle = $container.find('.aicourse-progress-ring-fill');
            var $text = $container.find('.aicourse-progress-ring-text');
            
            var radius = $container.data('radius') || 90;
            var circumference = 2 * Math.PI * radius;
            var targetOffset = circumference - (newPercentage / 100) * circumference;
            
            // Detect increase for pulse effect
            var increased = triggerPulse && self.lastPercentage !== null && newPercentage > self.lastPercentage;
            
            // Animate SVG stroke - use inline style to override HTML attribute
            $circle[0].style.transition = 'stroke-dashoffset 0.8s ease-out';
            $circle[0].style.strokeDashoffset = targetOffset + 'px';
            
            // Glow + pulse on increase
            if (increased) {
                $container.addClass('pulse');
                $circle.addClass('glow');
                
                setTimeout(function() {
                    $container.removeClass('pulse');
                    $circle.removeClass('glow');
                }, 900);
            }
            
            // Completed state at 100% - show green tick instead of text
            if (newPercentage >= 100) {
                $container.addClass('completed');
                $text.html('<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#357a32" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>');
                self.lastPercentage = newPercentage;
                return;
            }
            
            // Animate number counter
            var start = parseInt($text.text(), 10) || 0;
            var duration = 800;
            var startTime = performance.now();
            
            function update(now) {
                var progress = Math.min((now - startTime) / duration, 1);
                var value = Math.round(start + (newPercentage - start) * progress);
                $text.text(value + '%');
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                }
            }
            
            requestAnimationFrame(update);
            self.lastPercentage = newPercentage;
        },
        
        fetchAndUpdateProgress: function(courseid, $container) {
            var self = this;
            
            $.ajax({
                url: M.cfg.wwwroot + '/course/format/aicourse/ajax.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'getprogress',
                    courseid: courseid,
                    sesskey: M.cfg.sesskey
                }
            }).done(function(response) {
                if (response.success && typeof response.percentage !== 'undefined') {
                    self.animateRing($container, response.percentage, true);
                }
            });
        },
        
        initKeyboardNav: function() {
            // BUG-ACF-KEYNAV-STACK (v1.7.40): Added namespace so re-init doesn't stack handlers.
            // BUG-ACF-KEYNAV-DROPDOWN (v1.7.40): Added checks so arrow keys don't fire nav
            // when a Moodle dropdown/action-menu/modal is open or focus is in a focusable control.
            $(document).off('keydown.aicourse-keynav').on('keydown.aicourse-keynav', function(e) {
                // Never intercept when focus is inside any interactive control
                if ($(e.target).is('input, textarea, select, button, a, [contenteditable]')) {
                    return;
                }
                // Never intercept when a Moodle dropdown, modal, or dialog is open
                if ($('.dropdown-menu.show, .moodle-dialogue, [role="dialog"], [aria-expanded="true"]').length) {
                    return;
                }
                // Left arrow - previous activity
                if (e.keyCode === 37) {
                    var prevBtn = $('.aicourse-nav-prev');
                    if (prevBtn.length) {
                        prevBtn[0].click();
                    }
                }
                // Right arrow - next activity
                if (e.keyCode === 39) {
                    var nextBtn = $('.aicourse-nav-next');
                    if (nextBtn.length) {
                        nextBtn[0].click();
                    }
                }
            });
        },
        
        highlightCurrentActivity: function() {
            var currentUrl = window.location.href;
            
            // Find and highlight the current activity in the course index
            $('.courseindex-link, [data-for="cm"] a').each(function() {
                var linkUrl = $(this).attr('href');
                if (linkUrl && currentUrl.indexOf(linkUrl) !== -1) {
                    $(this).addClass('active');
                    $(this).closest('[data-for="cm"]').addClass('active');
                    $(this).attr('aria-current', 'page');
                    
                    // Scroll into view in course index
                    var container = $(this).closest('.courseindex, [data-region="courseindex"]');
                    if (container.length) {
                        var offset = $(this).position().top - container.height() / 2;
                        container.scrollTop(container.scrollTop() + offset);
                    }
                }
            });
        },
        
        enhanceCourseIndex: function() {
            // Add smooth transitions to all course index items
            $('.courseindex-item, [data-for="cm"]').each(function(index) {
                $(this).css({
                    'animation-delay': (index * 20) + 'ms'
                });
            });
            
            // Add focus states for accessibility
            $('.courseindex-link, [data-for="cm"] a').on('focus', function() {
                $(this).addClass('focused');
            }).on('blur', function() {
                $(this).removeClass('focused');
            });
            
            // Chevron directions are handled natively by Moodle
            // .collapsed-icon has fa-chevron-right (correct)
            // .expanded-icon has fa-chevron-down (correct)
            // Moodle toggles visibility based on aria-expanded
        },
        
        /**
         * Initialize the icon picker modal using Moodle core/ajax.
         * ICON-UX-v1.7.46: click target is now .aicourse-icon-col (outer column wrapper
         * that carries data-courseid/data-sectionid) rather than the inner icon-wrap.
         * The col also renders an always-visible "Add/Change icon" label below the box.
         */
        initIconPicker: function() {
            var iconPicker = $('#aicourse-icon-picker');
            var currentIconCol = null;

            if (!iconPicker.length) {
                return;
            }

            // Open icon picker when the col wrapper (icon + label) is clicked
            $(document).on('click', '.aicourse-icon-col.aicourse-card-icon-editable', function(e) {
                e.preventDefault();
                e.stopPropagation();
                currentIconCol = $(this);
                // Reset search and show all icons
                iconPicker.find('.aicourse-icon-search-input').val('');
                iconPicker.find('.aicourse-icon-picker-item').show();
                iconPicker.find('.aicourse-icon-picker-category').show();
                iconPicker.css('display', 'flex');
                $('body').css('overflow', 'hidden');
                // Focus search input
                setTimeout(function() {
                    iconPicker.find('.aicourse-icon-search-input').trigger('focus');
                }, 50);
            });

            // Close icon picker
            var closeIconPicker = function() {
                iconPicker.css('display', 'none');
                $('body').css('overflow', '');
                currentIconCol = null;
            };

            iconPicker.find('.aicourse-icon-picker-close').on('click', closeIconPicker);
            iconPicker.find('.aicourse-icon-picker-backdrop').on('click', closeIconPicker);

            // BUG-ACF-KEYNAV-STACK (v1.7.40): namespaced so re-init doesn't stack Escape handlers
            $(document).off('keydown.aicourse-iconpicker').on('keydown.aicourse-iconpicker', function(e) {
                if (e.key === 'Escape' && iconPicker.css('display') !== 'none') {
                    closeIconPicker();
                }
            });

            // Live search filter — show/hide icons and category headings
            iconPicker.find('.aicourse-icon-search-input').on('input', function() {
                var query = $(this).val().toLowerCase().trim();
                if (!query) {
                    iconPicker.find('.aicourse-icon-picker-item').show();
                    iconPicker.find('.aicourse-icon-picker-category').show();
                    return;
                }
                iconPicker.find('.aicourse-icon-picker-category').each(function() {
                    var $cat = $(this);
                    if ($cat.attr('data-category') === '__remove__') {
                        $cat.hide();
                        return;
                    }
                    var anyVisible = false;
                    $cat.find('.aicourse-icon-picker-item').each(function() {
                        var key   = ($(this).attr('data-icon') || '').toLowerCase();
                        var label = $(this).find('.aicourse-icon-picker-label').text().toLowerCase();
                        var vis   = key.indexOf(query) !== -1 || label.indexOf(query) !== -1;
                        $(this).toggle(vis);
                        if (vis) { anyVisible = true; }
                    });
                    $cat.toggle(anyVisible);
                });
            });

            // Handle icon selection
            iconPicker.find('.aicourse-icon-picker-item').on('click', function() {
                if (!currentIconCol) {
                    return;
                }

                var iconKey  = $(this).attr('data-icon');
                var courseId = currentIconCol.attr('data-courseid');
                var sectionId = currentIconCol.attr('data-sectionid');

                var iconDiv  = currentIconCol.find('.aicourse-card-icon');
                var wrapDiv  = currentIconCol.find('.aicourse-card-icon-wrap');
                var labelEl  = currentIconCol.find('.aicourse-icon-change-label');

                if (iconKey === '__clear__') {
                    // Remove icon: show pencil placeholder, update state + label
                    iconDiv.html('<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>');
                    wrapDiv.removeClass('aicourse-icon-selected').addClass('aicourse-icon-empty');
                    labelEl.text('Add icon');
                } else {
                    // Set icon: extract just the SVG element (not the label span)
                    var iconSvg = $(this).find('svg').prop('outerHTML') || '';
                    iconDiv.html(iconSvg);
                    wrapDiv.removeClass('aicourse-icon-empty').addClass('aicourse-icon-selected');
                    labelEl.text('Change icon');
                }

                // Save via AJAX
                $.ajax({
                    url: M.cfg.wwwroot + '/course/format/aicourse/ajax.php',
                    type: 'POST',
                    data: {
                        action: 'saveicon',
                        courseid: courseId,
                        sectionid: sectionId,
                        icon: iconKey === '__clear__' ? '' : iconKey,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done(function(response) {
                    if (!response.success) {
                        Notification.addNotification({
                            message: response.error || 'Failed to save icon',
                            type: 'error'
                        });
                    }
                }).fail(function() {
                    Notification.addNotification({
                        message: 'Failed to save icon',
                        type: 'error'
                    });
                });

                closeIconPicker();
            });
        },
        
        initCompletionToggle: function() {
            // Handle manual completion toggle clicks on activity cards
            $(document).on('click', '.aicourse-completion-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var btn = $(this);
                var cmid = btn.attr('data-cmid');
                var isCompleted = btn.attr('data-completed') === '1';
                var newState = isCompleted ? 0 : 1; // Toggle state
                
                // Add loading state
                btn.addClass('aicourse-loading');
                
                // Call Moodle's completion toggle
                Ajax.call([{
                    methodname: 'core_completion_update_activity_completion_status_manually',
                    args: {
                        cmid: parseInt(cmid),
                        completed: newState === 1
                    }
                }])[0].done(function() {
                    // Update button state
                    btn.removeClass('aicourse-loading');
                    btn.attr('data-completed', newState === 1 ? '1' : '0');
                    
                    if (newState === 1) {
                        btn.removeClass('aicourse-completion-toggle-pending').addClass('aicourse-completion-toggle-done');
                        btn.find('.aicourse-toggle-check').html('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>');
                        btn.find('.aicourse-toggle-label').text('Completed');
                        btn.closest('.aicourse-activity-card').removeClass('aicourse-activity-status-not_started aicourse-activity-status-in_progress').addClass('aicourse-activity-status-completed');
                    } else {
                        btn.removeClass('aicourse-completion-toggle-done').addClass('aicourse-completion-toggle-pending');
                        btn.find('.aicourse-toggle-check').html('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/></svg>');
                        btn.find('.aicourse-toggle-label').text('Mark as done');
                        btn.closest('.aicourse-activity-card').removeClass('aicourse-activity-status-completed').addClass('aicourse-activity-status-not_started');
                    }
                    
                    // Trigger progress update
                    $(document).trigger('aicourse:completion_updated');
                }).fail(function(error) {
                    btn.removeClass('aicourse-loading');
                    Notification.addNotification({
                        message: 'Failed to update completion status',
                        type: 'error'
                    });
                });
            });
            
            // Handle hero banner completion toggle clicks
            $(document).on('click', '.aicourse-hero-completion-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var btn = $(this);
                var cmid = btn.attr('data-cmid');
                var isCompleted = btn.attr('data-completed') === '1';
                var newState = isCompleted ? 0 : 1;
                
                // Add loading state
                btn.addClass('aicourse-loading');
                
                // Call Moodle's completion toggle
                Ajax.call([{
                    methodname: 'core_completion_update_activity_completion_status_manually',
                    args: {
                        cmid: parseInt(cmid),
                        completed: newState === 1
                    }
                }])[0].done(function() {
                    btn.removeClass('aicourse-loading');
                    btn.attr('data-completed', newState === 1 ? '1' : '0');
                    
                    // Update the SVG ring and text
                    var ringContainer = btn.find('.aicourse-completion-ring-container');
                    var labelEl = btn.closest('.aicourse-hero-progress').find('.aicourse-completion-label');
                    
                    if (newState === 1) {
                        btn.removeClass('aicourse-hero-completion-pending').addClass('aicourse-hero-completion-done');
                        ringContainer.html('<svg class="aicourse-completion-ring aicourse-completion-done" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke="#357a32" stroke-width="4"/><path d="M17 25 L22 30 L33 19" fill="none" stroke="#357a32" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>');
                        labelEl.text('Completed');
                    } else {
                        btn.removeClass('aicourse-hero-completion-done').addClass('aicourse-hero-completion-pending');
                        ringContainer.html('<svg class="aicourse-completion-ring aicourse-completion-pending" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="4"/><path d="M17 25 L22 30 L33 19" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>');
                        labelEl.text('Mark as done');
                    }
                    
                    // Trigger progress update
                    $(document).trigger('aicourse:completion_updated');
                }).fail(function(error) {
                    btn.removeClass('aicourse-loading');
                    Notification.addNotification({
                        message: 'Failed to update completion status',
                        type: 'error'
                    });
                });
            });
        },
        
        initSectionDuplicate: function() {
            // Handle section duplicate button clicks - AJAX to stay on same page
            $(document).on('click', '.aicourse-card-duplicate', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var btn = $(this);
                var card = btn.closest('.aicourse-card');
                var sectionId = btn.attr('data-sectionid');
                var courseId = btn.attr('data-courseid') || 
                               $('[data-courseid]').first().attr('data-courseid') || 
                               $('.aicourse-card-icon-wrap').first().attr('data-courseid');
                
                // Disable button and show loading state
                btn.prop('disabled', true);
                btn.css('opacity', '0.5');
                
                $.ajax({
                    url: M.cfg.wwwroot + '/course/format/aicourse/ajax.php',
                    method: 'POST',
                    data: {
                        action: 'duplicatesection',
                        courseid: courseId,
                        sectionid: sectionId,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done(function(response) {
                    btn.prop('disabled', false);
                    btn.css('opacity', '1');
                    
                    if (response.success) {
                        // Reload the page to show the new section
                        Notification.addNotification({
                            message: 'Section duplicated successfully',
                            type: 'success'
                        });
                        // Brief delay to show the notification, then reload
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        Notification.addNotification({
                            message: response.error || 'Failed to duplicate section',
                            type: 'error'
                        });
                    }
                }).fail(function() {
                    btn.prop('disabled', false);
                    btn.css('opacity', '1');
                    Notification.addNotification({
                        message: 'Failed to duplicate section',
                        type: 'error'
                    });
                });
            });
        },
        
        initAddSection: function() {
            // Handle "Add section" card click  -  AJAX then reload
            $(document).on('click', '.aicourse-add-section-card', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var btn = $(this);
                var courseId = btn.attr('data-courseid');

                btn.addClass('aicourse-add-section-loading');

                $.ajax({
                    url: M.cfg.wwwroot + '/course/format/aicourse/ajax.php',
                    method: 'POST',
                    data: {
                        action: 'addsection',
                        courseid: courseId,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done(function(response) {
                    if (response.success) {
                        Notification.addNotification({
                            message: 'Section added',
                            type: 'success'
                        });
                        setTimeout(function() {
                            window.location.reload();
                        }, 400);
                    } else {
                        btn.removeClass('aicourse-add-section-loading');
                        Notification.addNotification({
                            message: response.error || 'Failed to add section',
                            type: 'error'
                        });
                    }
                }).fail(function() {
                    btn.removeClass('aicourse-add-section-loading');
                    Notification.addNotification({
                        message: 'Failed to add section',
                        type: 'error'
                    });
                });
            });
        },

        initSectionDelete: function() {
            // Handle section delete button clicks
            $(document).on('click', '.aicourse-card-delete', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var btn = $(this);
                var card = btn.closest('.aicourse-card');
                var sectionId = btn.attr('data-sectionid');
                var courseId = $('[data-courseid]').first().attr('data-courseid') || 
                               $('.aicourse-card-icon-wrap').first().attr('data-courseid');
                
                // Show confirmation dialog
                Str.get_strings([
                    {key: 'deletesection', component: 'format_aicourse'},
                    {key: 'deletesectionconfirm', component: 'format_aicourse'},
                    {key: 'delete'},
                    {key: 'cancel'}
                ]).done(function(strings) {
                    Notification.confirm(
                        strings[0], // Title: Delete section
                        strings[1], // Message: Are you sure...
                        strings[2], // Delete button
                        strings[3], // Cancel button
                        function() {
                            // User confirmed - use AJAX to delete section
                            btn.prop('disabled', true);
                            card.css('opacity', '0.5');
                            
                            $.ajax({
                                url: M.cfg.wwwroot + '/course/format/aicourse/ajax.php',
                                method: 'POST',
                                data: {
                                    action: 'deletesection',
                                    courseid: courseId,
                                    sectionid: sectionId,
                                    sesskey: M.cfg.sesskey
                                },
                                dataType: 'json'
                            }).done(function(response) {
                                if (response.success) {
                                    // Animate card removal
                                    card.animate({opacity: 0, height: 0}, 300, function() {
                                        card.remove();
                                    });
                                    Notification.addNotification({
                                        message: 'Section deleted successfully',
                                        type: 'success'
                                    });
                                } else {
                                    card.css('opacity', '1');
                                    btn.prop('disabled', false);
                                    Notification.addNotification({
                                        message: response.error || 'Failed to delete section',
                                        type: 'error'
                                    });
                                }
                            }).fail(function() {
                                card.css('opacity', '1');
                                btn.prop('disabled', false);
                                Notification.addNotification({
                                    message: 'Failed to delete section',
                                    type: 'error'
                                });
                            });
                        }
                    );
                });
            });
        },

        /**
         * AI Banner Image Generation
         * Shows a cost-confirmation modal then calls ajax.php  ->  API  ->  Moodle file storage.
         */
        initGenerateBanner: function() {
            var self = this;

            // Build modal HTML (injected once into the page)
            var modalId = 'aicourse-banner-gen-modal';
            if (!document.getElementById(modalId)) {
                var modalHtml = '<div id="' + modalId + '" class="aicourse-bgen-overlay" style="display:none" aria-modal="true" role="dialog">'
                    + '<div class="aicourse-bgen-card">'
                    +   '<div class="aicourse-bgen-header">'
                    +     '<div class="aicourse-bgen-header-icon">'
                    +       '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
                    +         '<path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/>'
                    +         '<path d="M17.8 11.8 19 13"/><path d="M15 9h.01"/><path d="M17.8 6.2 19 5"/>'
                    +         '<path d="m3 21 9-9"/><path d="M12.2 6.2 11 5"/>'
                    +       '</svg>'
                    +     '</div>'
                    +     '<div class="aicourse-bgen-header-text">'
                    +       '<h2 class="aicourse-bgen-title">Generate AI Banner</h2>'
                    +       '<p class="aicourse-bgen-subtitle">Powered by Google Imagen 4 Ultra</p>'
                    +     '</div>'
                    +     '<button class="aicourse-bgen-close" aria-label="Close">'
                    +       '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
                    +     '</button>'
                    +   '</div>'
                    +   '<div class="aicourse-bgen-body">'

                    // State: confirm
                    +   '<div id="aicourse-bgen-confirm">'
                    +     '<div class="aicourse-bgen-course-name" id="aicourse-bgen-cname"></div>'
                    +     '<p class="aicourse-bgen-desc">AI reads your course name and generates a cinematic, full-width banner image tailored to your course subject. The image is automatically cropped and optimised for your course header, then saved directly to your course.</p>'
                    +     '<div class="aicourse-bgen-cost-box">'
                    +       '<div class="aicourse-bgen-cost-amount">5 credits</div>'
                    +       '<div class="aicourse-bgen-cost-detail">~50&cent; AUD &bull; One-time generation cost</div>'
                    +     '</div>'
                    +     '<div class="aicourse-bgen-actions">'
                    +       '<button class="aicourse-bgen-btn aicourse-bgen-btn-cancel">Cancel</button>'
                    +       '<button class="aicourse-bgen-btn aicourse-bgen-btn-generate">'
                    +         '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/><path d="M17.8 11.8 19 13"/><path d="M15 9h.01"/><path d="M17.8 6.2 19 5"/><path d="m3 21 9-9"/><path d="M12.2 6.2 11 5"/></svg>'
                    +         ' Generate Banner'
                    +       '</button>'
                    +     '</div>'
                    +   '</div>'

                    // State: loading
                    +   '<div id="aicourse-bgen-loading" style="display:none" class="aicourse-bgen-state-center">'
                    +     '<div class="aicourse-bgen-spinner"></div>'
                    +     '<p class="aicourse-bgen-loading-title">Generating with Imagen 4 Ultra&hellip;</p>'
                    +     '<p class="aicourse-bgen-loading-sub">AI is crafting a cinematic banner for your course.<br>This takes 15&ndash;40 seconds &mdash; please wait.</p>'
                    +   '</div>'

                    // State: success
                    +   '<div id="aicourse-bgen-success" style="display:none">'
                    +     '<div class="aicourse-bgen-preview-wrap">'
                    +       '<img id="aicourse-bgen-preview-img" src="" alt="Generated course banner" class="aicourse-bgen-preview-img" />'
                    +       '<div class="aicourse-bgen-preview-badge">'
                    +         '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>'
                    +         ' Applied to your course'
                    +       '</div>'
                    +     '</div>'
                    +     '<p class="aicourse-bgen-success-msg">Your AI banner has been saved to the course. The page will refresh to show the new banner.</p>'
                    +     '<div class="aicourse-bgen-actions">'
                    +       '<button class="aicourse-bgen-btn aicourse-bgen-btn-done">Done</button>'
                    +     '</div>'
                    +   '</div>'

                    // State: error
                    +   '<div id="aicourse-bgen-error" style="display:none" class="aicourse-bgen-state-center">'
                    +     '<div class="aicourse-bgen-error-icon">'
                    +       '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                    +     '</div>'
                    +     '<p class="aicourse-bgen-error-title">Generation Failed</p>'
                    +     '<p class="aicourse-bgen-error-msg" id="aicourse-bgen-errmsg"></p>'
                    +     '<div class="aicourse-bgen-actions">'
                    +       '<button class="aicourse-bgen-btn aicourse-bgen-btn-cancel">Close</button>'
                    +       '<button class="aicourse-bgen-btn aicourse-bgen-btn-retry">Try Again</button>'
                    +     '</div>'
                    +   '</div>'

                    +   '</div>' // .aicourse-bgen-body
                    + '</div>'   // .aicourse-bgen-card
                    + '</div>';  // .aicourse-bgen-overlay

                $(document.body).append(modalHtml);
            }

            var overlay = $('#' + modalId);
            var currentBtn = null;

            function setState(state) {
                $('#aicourse-bgen-confirm, #aicourse-bgen-loading, #aicourse-bgen-success, #aicourse-bgen-error').hide();
                $('#aicourse-bgen-' + state).show();
            }

            function openModal(btn) {
                currentBtn = btn;
                var courseName = btn.data('coursename') || '';
                $('#aicourse-bgen-cname').text(courseName);
                setState('confirm');
                overlay.fadeIn(180);
            }

            function closeModal() {
                overlay.fadeOut(180);
                currentBtn = null;
            }

            /**
             * Extract a clean human-readable error message from a server error string.
             * The Gemini SDK sometimes propagates raw JSON blobs (e.g. quota exhaustion)
             * as the error value — this helper unwraps the inner message string so the
             * modal never shows a raw JSON blob to the course admin.
             * @param {string|null} raw
             * @returns {string}
             */
            function extractBannerError(raw) {
                if (!raw) { return 'Generation failed. Please try again.'; }
                try {
                    var parsed = JSON.parse(raw);
                    var inner = (parsed && parsed.error && parsed.error.message) ||
                                (parsed && parsed.message);
                    if (inner && typeof inner === 'string') { return inner; }
                } catch (ignore) {}
                return raw;
            }

            function doGenerate() {
                if (!currentBtn) { return; }
                var courseid = currentBtn.data('courseid');

                setState('loading');

                $.ajax({
                    url: M.cfg.wwwroot + '/course/format/aicourse/ajax.php',
                    method: 'POST',
                    data: {
                        action:   'generate_banner_image',
                        courseid: courseid,
                        sesskey:  M.cfg.sesskey
                    },
                    dataType: 'json',
                    timeout:  95000
                }).done(function(response) {
                    if (response && response.success && response.imageUrl) {
                        $('#aicourse-bgen-preview-img').attr('src', response.imageUrl);
                        setState('success');
                        // Refresh hero background in-page immediately
                        var newUrl = 'url("' + response.imageUrl + '?t=' + Date.now() + '")';
                        $('.aicourse-hero-bg-img').css('background-image', newUrl);
                        $('.aicourse-hero').addClass('aicourse-hero-has-image');
                    } else {
                        $('#aicourse-bgen-errmsg').text(extractBannerError(response && response.error));
                        setState('error');
                    }
                }).fail(function(xhr) {
                    var msg = 'Generation failed. Please try again.';
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r && r.error) { msg = extractBannerError(r.error); }
                    } catch (ignore) {}
                    $('#aicourse-bgen-errmsg').text(msg);
                    setState('error');
                });
            }

            // --- Event delegation ---

            // Open modal when clicking the wand button
            $(document).on('click', '.aicourse-ai-generate-banner', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openModal($(this));
            });

            // Close button
            overlay.on('click', '.aicourse-bgen-close, .aicourse-bgen-btn-cancel', function() {
                closeModal();
            });

            // Click outside card to close
            overlay.on('click', function(e) {
                if ($(e.target).is(overlay)) {
                    closeModal();
                }
            });

            // Generate button
            overlay.on('click', '.aicourse-bgen-btn-generate', function() {
                doGenerate();
            });

            // Retry button
            overlay.on('click', '.aicourse-bgen-btn-retry', function() {
                setState('confirm');
            });

            // Done  -  refresh the page to fully update the banner
            overlay.on('click', '.aicourse-bgen-btn-done', function() {
                closeModal();
                window.location.reload();
            });

            // ESC key
            $(document).on('keydown.aicourse-bgen', function(e) {
                if (e.key === 'Escape' && overlay.is(':visible')) {
                    closeModal();
                }
            });
        },

        /**
         * Delete Banner Image
         * Confirms then calls ajax.php  ->  deletes bannerimage filearea  ->  updates page in-place.
         */
        initDeleteBanner: function() {
            var modalId = 'aicourse-bdel-modal';
            if (!document.getElementById(modalId)) {
                var modalHtml = '<div id="' + modalId + '" class="aicourse-bdel-overlay" style="display:none" aria-modal="true" role="dialog">'
                    + '<div class="aicourse-bdel-card">'
                    +   '<div class="aicourse-bdel-icon">'
                    +     '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
                    +       '<polyline points="3 6 5 6 21 6"/>'
                    +       '<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>'
                    +       '<path d="M10 11v6"/><path d="M14 11v6"/>'
                    +       '<path d="M9 6V4h6v2"/>'
                    +     '</svg>'
                    +   '</div>'
                    +   '<h2 class="aicourse-bdel-title">Remove Banner Image?</h2>'
                    +   '<p class="aicourse-bdel-desc">The banner image will be permanently removed from this course. You can generate or upload a new one at any time.</p>'
                    +   '<div id="aicourse-bdel-error" class="aicourse-bdel-errmsg" style="display:none"></div>'
                    +   '<div class="aicourse-bdel-actions">'
                    +     '<button class="aicourse-bgen-btn aicourse-bgen-btn-cancel aicourse-bdel-btn-cancel">Cancel</button>'
                    +     '<button class="aicourse-bgen-btn aicourse-bdel-btn-confirm">Remove Image</button>'
                    +   '</div>'
                    + '</div>'
                    + '</div>';
                $(document.body).append(modalHtml);
            }

            var delOverlay = $('#' + modalId);
            var currentDeleteBtn = null;

            function openDeleteModal(btn) {
                currentDeleteBtn = btn;
                $('#aicourse-bdel-error').hide().text('');
                delOverlay.find('.aicourse-bdel-btn-confirm').prop('disabled', false).text('Remove Image');
                delOverlay.fadeIn(180);
            }

            function closeDeleteModal() {
                delOverlay.fadeOut(180);
                currentDeleteBtn = null;
            }

            // Open modal when clicking the trash button
            $(document).on('click', '.aicourse-ai-delete-banner', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openDeleteModal($(this));
            });

            // Cancel / click outside
            delOverlay.on('click', '.aicourse-bdel-btn-cancel', function() {
                closeDeleteModal();
            });
            delOverlay.on('click', function(e) {
                if ($(e.target).is(delOverlay)) {
                    closeDeleteModal();
                }
            });

            // Confirm delete
            delOverlay.on('click', '.aicourse-bdel-btn-confirm', function() {
                if (!currentDeleteBtn) { return; }
                var courseid = currentDeleteBtn.data('courseid');
                var confirmBtn = $(this);

                confirmBtn.prop('disabled', true).text('Removing\u2026');
                $('#aicourse-bdel-error').hide().text('');

                $.ajax({
                    url: M.cfg.wwwroot + '/course/format/aicourse/ajax.php',
                    method: 'POST',
                    data: {
                        action:   'delete_banner_image',
                        courseid: courseid,
                        sesskey:  M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done(function(response) {
                    if (response && response.success) {
                        // Remove background image from hero in-place
                        $('.aicourse-hero-bg-img').css('background-image', 'none').hide();
                        $('.aicourse-hero-banner').removeClass('aicourse-hero-has-image');
                        // Hide the delete button(s)  -  they are no longer relevant
                        $('.aicourse-ai-delete-banner').hide();
                        closeDeleteModal();
                    } else {
                        var msg = (response && response.error) || 'Failed to remove banner. Please try again.';
                        $('#aicourse-bdel-error').text(msg).show();
                        confirmBtn.prop('disabled', false).text('Remove Image');
                    }
                }).fail(function() {
                    $('#aicourse-bdel-error').text('Failed to remove banner. Please try again.').show();
                    confirmBtn.prop('disabled', false).text('Remove Image');
                });
            });

            // ESC key
            $(document).on('keydown.aicourse-bdel', function(e) {
                if (e.key === 'Escape' && delOverlay.is(':visible')) {
                    closeDeleteModal();
                }
            });
        }
    };
});
