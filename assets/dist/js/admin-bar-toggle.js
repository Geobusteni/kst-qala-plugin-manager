/**
 * Qala Plugin Manager - admin-bar-toggle.js
 * Version: 1.0.11
 * Built: 2025-10-27 09:33:46
 */
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
$('#wp-admin-bar-qala-notice-toggle a').on('click', this.handleClick.bind(this));
},
/**
* Handle click on toggle item
*
* @param {Event} e Click event
*/
handleClick: function (e) {
e.preventDefault();
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
this.isToggling = true;
this.setLoadingState($title);
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
if (data.new_title) {
$title.html(data.new_title);
}
this.showNotice(data.message, 'success');
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
$title.removeClass('qala-loading');
$title.html(originalTitle);
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
const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
const $notice = $('<div>', {
class: 'notice qala-ajax-notice ' + noticeClass + ' is-dismissible',
html: '<p>' + message + '</p>'
});
if ($('.wrap').length) {
$('.wrap').first().prepend($notice);
} else {
$('#wpbody-content').prepend($notice);
}
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
if ($('#wp-admin-bar-qala-notice-toggle').length) {
AdminBarToggle.init();
}
});
})(jQuery);
