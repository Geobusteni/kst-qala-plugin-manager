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
			// Add pattern button click
			$('#qala-add-pattern-btn').on('click', this.handleAddPattern.bind(this));

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
		},

		/**
		 * Handle add pattern button click
		 */
		handleAddPattern: function (e) {
			if (e) {
				e.preventDefault();
			}

			const pattern = $('#qala-new-pattern').val().trim();
			const patternType = $('#qala-pattern-type').val();
			const $button = $('#qala-add-pattern-btn');
			const $message = $('#qala-add-pattern-message');

			// Validate input
			if (!pattern) {
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
		// Only initialize if we're on the admin page
		if (typeof qalaAdminPage !== 'undefined') {
			QalaAdminPage.init();
		}
	});

})(jQuery);
