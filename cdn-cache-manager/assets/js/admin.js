(function ($) {
	'use strict';

	function updateProviderFields(provider) {
		$('.ccm-provider-row').hide();
		$('.ccm-provider-row[data-provider="' + provider + '"]').show();
	}

	function showFeedback(type, message) {
		var $box = $('#ccm-ajax-feedback');
		$box.removeClass('notice-success notice-error').addClass(type === 'success' ? 'notice-success' : 'notice-error');
		$box.find('p').text((type === 'success' ? CDNCacheManagerAdmin.successLabel : CDNCacheManagerAdmin.errorLabel) + ' ' + message);
		$box.show();
	}

	function showUrlError(message) {
		var $error = $('#ccm-url-error');
		var $text = $error.find('.ccm-url-error-text');
		if (!message) {
			$text.text('');
			$error.hide();
			return;
		}
		$text.text(message);
		$error.show();
	}

	function setPurgeAllStatus(type, message) {
		var $status = $('#ccm-purge-all-status');
		if (!message) {
			$status.hide().removeClass('is-loading is-success is-error').text('');
			return;
		}
		$status.removeClass('is-loading is-success is-error').addClass(type).text(message).show();
	}

	function validateSpecificUrl(url) {
		if (!url) {
			return 'Please enter a URL to purge.';
		}

		var parsed;
		try {
			parsed = new URL(url);
		} catch (e) {
			return 'Please enter a valid URL.';
		}

		if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
			return 'Only http:// or https:// URLs are allowed.';
		}

		if (parsed.hostname.toLowerCase() !== String(CDNCacheManagerAdmin.siteHost || '').toLowerCase()) {
			return 'URL hostname must match this site domain.';
		}

		return '';
	}

	function request(action, data) {
		return $.post(CDNCacheManagerAdmin.ajaxUrl, $.extend({
			action: action,
			nonce: CDNCacheManagerAdmin.nonce
		}, data || {}));
	}

	$(document).on('click', '#ccm-purge-all-button', function () {
		var $button = $(this);
		if (!window.confirm(CDNCacheManagerAdmin.confirmPurgeAll)) {
			return;
		}

		$button.prop('disabled', true);
		setPurgeAllStatus('is-loading', CDNCacheManagerAdmin.purgingLabel);
		request('cdn_cache_manager_purge_all').done(function (response) {
			if (response && response.success) {
				setPurgeAllStatus('is-success', CDNCacheManagerAdmin.purgedLabel);
				showFeedback('success', response.data.message);
				window.setTimeout(function () {
					window.location.reload();
				}, 1000);
				return;
			}
			setPurgeAllStatus('is-error', response && response.data ? response.data.message : 'Request failed.');
			showFeedback('error', response && response.data ? response.data.message : 'Request failed.');
		}).fail(function () {
			setPurgeAllStatus('is-error', 'Request failed.');
			showFeedback('error', 'Request failed.');
		}).always(function () {
			$button.prop('disabled', false);
		});
	});

	$(document).on('click', '#ccm-purge-url-button', function () {
		var url = $('#ccm-specific-url').val();
		var validationError = validateSpecificUrl(url);
		if (validationError) {
			showUrlError(validationError);
			return;
		}

		showUrlError('');
		request('cdn_cache_manager_purge_url', { url: url }).done(function (response) {
			if (response && response.success) {
				showFeedback('success', response.data.message);
				window.setTimeout(function () {
					window.location.reload();
				}, 700);
				return;
			}

			if (response && response.data && (response.data.code === 'invalid_url' || response.data.code === 'invalid_url_scheme' || response.data.code === 'invalid_url_host')) {
				showUrlError(response.data.message);
			}
			showFeedback('error', response && response.data ? response.data.message : 'Request failed.');
		}).fail(function () {
			showFeedback('error', 'Request failed.');
		});
	});

	$(document).on('input', '#ccm-specific-url', function () {
		showUrlError('');
	});

	$(document).on('click', '.ccm-url-error-close', function () {
		showUrlError('');
	});

	$(document).on('change', '#ccm_provider', function () {
		updateProviderFields($(this).val());
	});

	updateProviderFields(CDNCacheManagerAdmin.provider || 'imperva');
})(jQuery);
