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
