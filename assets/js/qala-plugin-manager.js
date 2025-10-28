/**
 * Qala Plugin Manager - Combined JavaScript
 *
 * This file combines all JavaScript assets for better performance.
 * Loaded globally on all admin pages.
 *
 * @package QalaPluginManager
 */

/* ===== Global Helper Functions ===== */

/**
 * Global helper for body class detection
 *
 * These helper functions allow JavaScript code to check user permissions
 * and notice visibility state by inspecting body classes added by BodyClassManager.
 *
 * Benefits:
 * - No need to pass PHP data to JavaScript
 * - Consistent with CSS approach
 * - Easy to debug in browser inspector
 * - Performant (simple DOM class check)
 *
 * @package QalaPluginManager
 */
window.QalaPluginManager = window.QalaPluginManager || {};

/**
 * Check if current user has qala_full_access capability
 *
 * @return {boolean} True if user has full access, false otherwise
 */
window.QalaPluginManager.hasFullAccess = function() {
	return document.body.classList.contains('qala-has-full-access');
};

/**
 * Check if notices are currently hidden
 *
 * @return {boolean} True if notices are hidden, false if visible
 */
window.QalaPluginManager.noticesHidden = function() {
	return document.body.classList.contains('qala-notices-hidden');
};

/**
 * Check if notices are currently visible
 *
 * @return {boolean} True if notices are visible, false if hidden
 */
window.QalaPluginManager.noticesVisible = function() {
	return document.body.classList.contains('qala-notices-visible');
};

/* ===== Notice Content Matching (Allowlist) ===== */

/**
 * Notice Content Matcher
 *
 * This module checks notice text content against allowlist patterns and shows
 * matching notices even when they're hidden by CSS.
 *
 * Problem:
 * - Allowlist patterns match PHP callback names (e.g., "my_plugin_notice")
 * - Users want to match notice TEXT content (e.g., "Category added")
 * - Hook-based filtering can't match content, only callback names
 *
 * Solution:
 * - JavaScript checks actual notice DOM text against patterns
 * - Adds data-qala-show="true" attribute to matching notices
 * - CSS already has rules to show notices with this attribute
 *
 * @package QalaPluginManager
 */
(function() {
	'use strict';

	const NoticeContentMatcher = {

		/**
		 * Initialize content matching
		 */
		init: function() {
			// Only run if notices are hidden
			if (!window.QalaPluginManager.noticesHidden()) {
				console.log('Qala Content Matcher: Notices not hidden - skipping');
				return;
			}

			// Only run if allowlist patterns are available
			if (typeof qalaAllowlistPatterns === 'undefined' || !qalaAllowlistPatterns.patterns) {
				console.log('Qala Content Matcher: No allowlist patterns defined');
				return;
			}

			console.log('Qala Content Matcher: Initializing with', qalaAllowlistPatterns.patterns.length, 'patterns');
			this.processNotices();
		},

		/**
		 * Process all hidden notices and show matching ones
		 */
		processNotices: function() {
			const selectors = [
				'.notice',
				'.updated',
				'.update-nag',
				'.error',
				'div[id^="message"]',
				'div[class*="-notice"]',
				'#ajax-response .notice',
				'#ajax-response > div'
			];

			const notices = document.querySelectorAll(selectors.join(', '));
			console.log('Qala Content Matcher: Found', notices.length, 'notice elements');

			let matchedCount = 0;
			let alreadyProcessed = 0;

			notices.forEach((notice) => {
				// Skip if already processed
				if (notice.hasAttribute('data-qala-processed')) {
					alreadyProcessed++;
					return;
				}

				// Mark as processed to avoid reprocessing
				notice.setAttribute('data-qala-processed', 'true');

				const text = this.getNoticeText(notice);

				// Only log non-empty text
				if (text && text.trim()) {
					console.log('Qala Content Matcher: Checking notice -', text.substring(0, 80));
				}

				if (this.matchesAnyPattern(text)) {
					notice.setAttribute('data-qala-show', 'true');
					matchedCount++;
					console.log('Qala Content Matcher: ✓ MATCHED -', text.substring(0, 80));
				}
			});

			if (alreadyProcessed > 0) {
				console.log('Qala Content Matcher: Skipped', alreadyProcessed, 'already processed notices');
			}
			console.log('Qala Content Matcher: Matched', matchedCount, 'new notices');
		},

		/**
		 * Get text content from notice element
		 */
		getNoticeText: function(element) {
			return element.textContent || element.innerText || '';
		},

		/**
		 * Check if text matches any allowlist pattern
		 */
		matchesAnyPattern: function(text) {
			if (!text) {
				return false;
			}

			for (let i = 0; i < qalaAllowlistPatterns.patterns.length; i++) {
				const pattern = qalaAllowlistPatterns.patterns[i];
				console.log('Qala Content Matcher: Testing pattern', pattern.value, 'type:', pattern.type, 'against text:', text.substring(0, 80));
				const result = this.matchesPattern(text, pattern.value, pattern.type);
				console.log('Qala Content Matcher: Pattern match result:', result);
				if (result) {
					console.log('Qala Content Matcher: Pattern MATCHED!');
					return true;
				}
			}

			return false;
		},

		/**
		 * Check if text matches a specific pattern
		 */
		matchesPattern: function(text, patternValue, patternType) {
			switch (patternType) {
				case 'exact':
					return text === patternValue;

				case 'wildcard':
					return this.matchesWildcard(text, patternValue);

				case 'regex':
					return this.matchesRegex(text, patternValue);

				default:
					return false;
			}
		},

		/**
		 * Check if text matches wildcard pattern
		 *
		 * Converts wildcard pattern to regex.
		 * Example: "*Category added*" becomes /^.*Category added.*$/
		 */
		matchesWildcard: function(text, pattern) {
			// Escape special regex characters except asterisk
			const escaped = pattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&');

			// Convert asterisk to .*
			const regex = new RegExp('^' + escaped.replace(/\*/g, '.*') + '$', 'i');

			return regex.test(text);
		},

		/**
		 * Check if text matches regex pattern
		 *
		 * Handles patterns with or without delimiters:
		 * - With delimiters: /pattern/flags (e.g., /\b(added)\b/i)
		 * - Without delimiters: pattern (e.g., \b(added)\b)
		 */
		matchesRegex: function(text, pattern) {
			try {
				let regexPattern = pattern;
				let flags = 'i'; // Default to case-insensitive

				console.log('Qala Content Matcher: matchesRegex - Original pattern:', pattern);

				// Check if pattern has regex delimiters (starts and ends with /)
				const delimiterMatch = pattern.match(/^\/(.*)\/([gimsuvy]*)$/);

				if (delimiterMatch) {
					// Pattern has delimiters: extract pattern body and flags
					regexPattern = delimiterMatch[1];
					const patternFlags = delimiterMatch[2];

					console.log('Qala Content Matcher: Extracted pattern body:', regexPattern);
					console.log('Qala Content Matcher: Extracted flags:', patternFlags);

					// Combine user flags with default 'i' flag (case-insensitive)
					// Remove duplicates by converting to Set
					const combinedFlags = [...new Set((patternFlags + 'i').split(''))].join('');
					flags = combinedFlags;
					console.log('Qala Content Matcher: Combined flags:', flags);
				} else {
					console.log('Qala Content Matcher: No delimiters found, using pattern as-is');
				}

				const regex = new RegExp(regexPattern, flags);
				console.log('Qala Content Matcher: Created regex:', regex);
				const result = regex.test(text);
				console.log('Qala Content Matcher: Regex test result:', result);
				return result;
			} catch (e) {
				console.error('Qala Content Matcher: Invalid regex pattern', pattern, e);
				return false;
			}
		},

		/**
		 * Start MutationObserver to watch for new notices
		 *
		 * Uses MutationObserver API to detect when new notices are added to the DOM.
		 * This is the proper way to handle AJAX-injected notices in real-time.
		 */
		startObserver: function() {
			// Only run if notices are hidden and patterns exist
			if (!window.QalaPluginManager.noticesHidden()) {
				return;
			}

			if (typeof qalaAllowlistPatterns === 'undefined' || !qalaAllowlistPatterns.patterns) {
				return;
			}

			// Create observer instance
			const observer = new MutationObserver((mutations) => {
				let shouldProcess = false;

				// Check if any mutation added notice-related elements
				mutations.forEach((mutation) => {
					if (mutation.addedNodes.length > 0) {
						mutation.addedNodes.forEach((node) => {
							// Check if added node is a notice or contains notices
							if (node.nodeType === 1) { // Element node
								const isNotice = node.classList && (
									node.classList.contains('notice') ||
									node.classList.contains('updated') ||
									node.classList.contains('error') ||
									node.id === 'ajax-response'
								);

								const hasNotices = node.querySelector && (
									node.querySelector('.notice') ||
									node.querySelector('.updated') ||
									node.querySelector('.error') ||
									node.querySelector('[class*="-notice"]')
								);

								if (isNotice || hasNotices) {
									shouldProcess = true;
								}
							}
						});
					}
				});

				// Process notices if we detected new ones
				if (shouldProcess) {
					console.log('Qala Content Matcher: New notices detected via MutationObserver');
					// Small delay to ensure DOM is fully updated
					setTimeout(() => {
						this.processNotices();
					}, 100);
				}
			});

			// Start observing the document body for child list changes
			observer.observe(document.body, {
				childList: true,
				subtree: true
			});

			console.log('Qala Content Matcher: MutationObserver started - watching for AJAX notices');

			// Store observer for potential cleanup
			this.observer = observer;
		},

		/**
		 * Stop the MutationObserver
		 */
		stopObserver: function() {
			if (this.observer) {
				this.observer.disconnect();
				console.log('Qala Content Matcher: MutationObserver stopped');
			}
		}
	};

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			NoticeContentMatcher.init();
			NoticeContentMatcher.startObserver();
		});
	} else {
		// DOM already loaded
		NoticeContentMatcher.init();
		NoticeContentMatcher.startObserver();
	}

	// Also run after delays to catch AJAX-injected notices
	setTimeout(function() {
		NoticeContentMatcher.processNotices();
	}, 500);

	setTimeout(function() {
		NoticeContentMatcher.processNotices();
	}, 1500);

	setTimeout(function() {
		NoticeContentMatcher.processNotices();
	}, 3000);

	// Expose for manual triggering if needed
	window.QalaPluginManager.NoticeContentMatcher = NoticeContentMatcher;

})();

/* ===== Admin Bar Toggle ===== */

/**
 * Admin Bar Toggle - AJAX Handler
 *
 * Handles the admin bar notice visibility toggle functionality.
 * Provides smooth AJAX toggling with visual feedback and error handling.
 *
 * Features:
 * - Click handler for admin bar menu item
 * - AJAX request to toggle endpoint
 * - Loading state indicator
 * - Success/error feedback
 * - Automatic title update on success
 * - Page reload after successful toggle
 *
 * @package QalaPluginManager
 * @since 1.0.0
 */

(function ($) {
	'use strict';

	/**
	 * AdminBarToggle object
	 *
	 * Encapsulates all toggle functionality
	 */
	const AdminBarToggle = {
		/**
		 * Initialize the toggle functionality
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			// Handle click on admin bar toggle item
			$('#wp-admin-bar-qala-notice-toggle a').on('click', this.handleClick.bind(this));
		},

		/**
		 * Handle click on toggle item
		 *
		 * @param {Event} e Click event
		 */
		handleClick: function (e) {
			e.preventDefault();

			// Don't allow multiple simultaneous toggles
			if (this.isToggling) {
				return;
			}

			this.toggleNoticeVisibility();
		},

		/**
		 * Toggle notice visibility via AJAX
		 */
		toggleNoticeVisibility: function () {
			const self = this;
			const $menuItem = $('#wp-admin-bar-qala-notice-toggle');
			const $title = $menuItem.find('.qala-notice-toggle-wrapper');
			const originalTitle = $title.html();

			// Set loading state
			this.isToggling = true;
			this.setLoadingState($title);

			// Send AJAX request
			$.ajax({
				url: qalaAdminBarToggle.ajaxUrl,
				type: 'POST',
				data: {
					action: qalaAdminBarToggle.action,
					nonce: qalaAdminBarToggle.nonce
				},
				dataType: 'json',
				success: function (response) {
					if (response.success) {
						self.handleSuccess(response.data, $title);
					} else {
						self.handleError(response.data, $title, originalTitle);
					}
				},
				error: function (xhr, status, error) {
					self.handleError(
						{ message: qalaAdminBarToggle.strings.error },
						$title,
						originalTitle
					);
				},
				complete: function () {
					self.isToggling = false;
				}
			});
		},

		/**
		 * Set loading state on menu item
		 *
		 * @param {jQuery} $title Title element
		 */
		setLoadingState: function ($title) {
			$title.addClass('qala-loading');
			$title.find('.qala-toggle-state').text(qalaAdminBarToggle.strings.loading);
		},

		/**
		 * Handle successful toggle
		 *
		 * @param {Object} data Response data
		 * @param {jQuery} $title Title element
		 */
		handleSuccess: function (data, $title) {
			// Update title with new state
			if (data.new_title) {
				$title.html(data.new_title);
			}

			// Show success message briefly
			this.showNotice(data.message, 'success');

			// Reload page after short delay to reflect new state
			setTimeout(function () {
				window.location.reload();
			}, 500);
		},

		/**
		 * Handle toggle error
		 *
		 * @param {Object} data Error data
		 * @param {jQuery} $title Title element
		 * @param {string} originalTitle Original title HTML
		 */
		handleError: function (data, $title, originalTitle) {
			// Remove loading state
			$title.removeClass('qala-loading');

			// Restore original title
			$title.html(originalTitle);

			// Show error message
			const errorMessage = data && data.message
				? data.message
				: qalaAdminBarToggle.strings.error;

			this.showNotice(errorMessage, 'error');
		},

		/**
		 * Show admin notice
		 *
		 * @param {string} message Message text
		 * @param {string} type Notice type ('success' or 'error')
		 */
		showNotice: function (message, type) {
			// Create notice element
			const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
			const $notice = $('<div>', {
				class: 'notice qala-ajax-notice ' + noticeClass + ' is-dismissible',
				html: '<p>' + message + '</p>'
			});

			// Insert notice at top of page
			if ($('.wrap').length) {
				$('.wrap').first().prepend($notice);
			} else {
				$('#wpbody-content').prepend($notice);
			}

			// Auto-dismiss after 3 seconds
			setTimeout(function () {
				$notice.fadeOut(300, function () {
					$(this).remove();
				});
			}, 3000);
		},

		/**
		 * Flag indicating if toggle is in progress
		 */
		isToggling: false
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function () {
		// Only initialize if admin bar toggle exists
		if ($('#wp-admin-bar-qala-notice-toggle').length) {
			AdminBarToggle.init();
		}
	});

})(jQuery);

/* ===== Admin Page ===== */

/**
 * Qala Admin Page JavaScript
 *
 * Handles AJAX interactions for the Hide Notices settings page.
 * Features:
 * - Add pattern to allowlist
 * - Remove pattern from allowlist
 * - Loading states
 * - Error handling
 * - Success messages
 *
 * @package QalaPluginManager
 */

(function ($) {
	'use strict';

	/**
	 * Main admin page object
	 */
	const QalaAdminPage = {

		/**
		 * Initialize
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			console.log('Qala Admin Page: Binding events...');

			// Add pattern button click
			$('#qala-add-pattern-btn').on('click', this.handleAddPattern.bind(this));
			console.log('Qala Admin Page: Bound click handler to #qala-add-pattern-btn');

			// Add pattern on Enter key
			$('#qala-new-pattern').on('keypress', function (e) {
				if (e.which === 13) {
					e.preventDefault();
					QalaAdminPage.handleAddPattern();
				}
			});

			// Add to allowlist from notice log
			$(document).on('click', '.qala-add-to-allowlist', this.handleAddFromLog.bind(this));

			// Remove from allowlist
			$(document).on('click', '.qala-remove-from-allowlist', this.handleRemovePattern.bind(this));

			// Clear all patterns
			$('#qala-clear-all-patterns-btn').on('click', this.handleClearAllPatterns.bind(this));

			console.log('Qala Admin Page: All events bound');
		},

		/**
		 * Handle add pattern button click
		 */
		handleAddPattern: function (e) {
			console.log('Qala Admin Page: handleAddPattern called', e);

			if (e) {
				e.preventDefault();
			}

			const pattern = $('#qala-new-pattern').val().trim();
			const patternType = $('#qala-pattern-type').val();
			const $button = $('#qala-add-pattern-btn');
			const $message = $('#qala-add-pattern-message');

			console.log('Qala Admin Page: Pattern:', pattern, 'Type:', patternType);

			// Validate input
			if (!pattern) {
				console.log('Qala Admin Page: Empty pattern - showing error');
				this.showMessage($message, qalaAdminPage.strings.emptyPattern, 'error');
				return;
			}

			// Disable button and show loading state
			$button.prop('disabled', true).addClass('qala-loading');
			$message.hide();

			// Send AJAX request
			$.ajax({
				url: qalaAdminPage.ajaxUrl,
				type: 'POST',
				data: {
					action: 'qala_add_allowlist_pattern',
					nonce: qalaAdminPage.nonces.addPattern,
					pattern: pattern,
					pattern_type: patternType
				},
				success: function (response) {
					if (response.success) {
						QalaAdminPage.showMessage($message, qalaAdminPage.strings.addSuccess, 'success');
						$('#qala-new-pattern').val('');

						// Reload page after short delay to show updated list
						setTimeout(function () {
							location.reload();
						}, 1000);
					} else {
						const errorMessage = response.data && response.data.message
							? response.data.message
							: qalaAdminPage.strings.addError;
						QalaAdminPage.showMessage($message, errorMessage, 'error');
					}
				},
				error: function (xhr, status, error) {
					console.error('AJAX error:', error);
					QalaAdminPage.showMessage($message, qalaAdminPage.strings.addError, 'error');
				},
				complete: function () {
					$button.prop('disabled', false).removeClass('qala-loading');
				}
			});
		},

		/**
		 * Handle add to allowlist from notice log
		 */
		handleAddFromLog: function (e) {
			e.preventDefault();

			const $link = $(e.currentTarget);
			const pattern = $link.data('pattern');
			const patternType = $link.data('pattern-type') || 'exact';
			const $row = $link.closest('tr');

			// Disable link and show loading state
			$link.prop('disabled', true).addClass('qala-loading');

			// Send AJAX request
			$.ajax({
				url: qalaAdminPage.ajaxUrl,
				type: 'POST',
				data: {
					action: 'qala_add_allowlist_pattern',
					nonce: qalaAdminPage.nonces.addPattern,
					pattern: pattern,
					pattern_type: patternType
				},
				success: function (response) {
					if (response.success) {
						// Highlight row and reload after delay
						$row.css('background-color', '#d4edda');
						setTimeout(function () {
							location.reload();
						}, 1000);
					} else {
						const errorMessage = response.data && response.data.message
							? response.data.message
							: qalaAdminPage.strings.addError;
						alert(errorMessage);
						$link.prop('disabled', false).removeClass('qala-loading');
					}
				},
				error: function (xhr, status, error) {
					console.error('AJAX error:', error);
					alert(qalaAdminPage.strings.addError);
					$link.prop('disabled', false).removeClass('qala-loading');
				}
			});
		},

		/**
		 * Handle remove pattern from allowlist
		 */
		handleRemovePattern: function (e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const pattern = $button.data('pattern');
			const $row = $button.closest('tr');

			// Confirm removal
			if (!confirm(qalaAdminPage.strings.confirmRemove)) {
				return;
			}

			// Disable button and show loading state
			$button.prop('disabled', true).addClass('qala-loading');

			// Send AJAX request
			$.ajax({
				url: qalaAdminPage.ajaxUrl,
				type: 'POST',
				data: {
					action: 'qala_remove_allowlist_pattern',
					nonce: qalaAdminPage.nonces.removePattern,
					pattern: pattern
				},
				success: function (response) {
					if (response.success) {
						// Fade out row and remove it
						$row.fadeOut(300, function () {
							$(this).remove();

							// Show empty state if no more patterns
							const $tbody = $row.closest('tbody');
							if ($tbody.find('tr').length === 0) {
								location.reload();
							}
						});
					} else {
						const errorMessage = response.data && response.data.message
							? response.data.message
							: qalaAdminPage.strings.removeError;
						alert(errorMessage);
						$button.prop('disabled', false).removeClass('qala-loading');
					}
				},
				error: function (xhr, status, error) {
					console.error('AJAX error:', error);
					alert(qalaAdminPage.strings.removeError);
					$button.prop('disabled', false).removeClass('qala-loading');
				}
			});
		},

		/**
		 * Handle clear all patterns from allowlist
		 */
		handleClearAllPatterns: function (e) {
			e.preventDefault();

			const $button = $(e.currentTarget);

			// Confirm removal
			if (!confirm(qalaAdminPage.strings.confirmClearAll)) {
				return;
			}

			// Disable button and show loading state
			$button.prop('disabled', true).addClass('qala-loading');

			// Send AJAX request
			$.ajax({
				url: qalaAdminPage.ajaxUrl,
				type: 'POST',
				data: {
					action: 'qala_clear_all_patterns',
					nonce: qalaAdminPage.nonces.clearAllPatterns
				},
				success: function (response) {
					if (response.success) {
						// Reload page to show empty state
						location.reload();
					} else {
						const errorMessage = response.data && response.data.message
							? response.data.message
							: qalaAdminPage.strings.clearAllError;
						alert(errorMessage);
						$button.prop('disabled', false).removeClass('qala-loading');
					}
				},
				error: function (xhr, status, error) {
					console.error('AJAX error:', error);
					alert(qalaAdminPage.strings.clearAllError);
					$button.prop('disabled', false).removeClass('qala-loading');
				}
			});
		},

		/**
		 * Show message in message container
		 */
		showMessage: function ($container, message, type) {
			$container
				.removeClass('success error info')
				.addClass(type)
				.html(message)
				.fadeIn(200);

			// Auto-hide success messages after 5 seconds
			if (type === 'success') {
				setTimeout(function () {
					$container.fadeOut(200);
				}, 5000);
			}
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function () {
		// Debug logging
		console.log('Qala Admin Page: Document ready');
		console.log('Qala Admin Page: qalaAdminPage defined?', typeof qalaAdminPage !== 'undefined');
		console.log('Qala Admin Page: Add button exists?', $('#qala-add-pattern-btn').length > 0);

		// Only initialize if we're on the admin page
		if (typeof qalaAdminPage !== 'undefined') {
			console.log('Qala Admin Page: Initializing...');
			QalaAdminPage.init();
			console.log('Qala Admin Page: Initialized successfully');
		} else {
			console.log('Qala Admin Page: qalaAdminPage not defined - skipping initialization');
		}
	});

})(jQuery);
