(function ($) {
	'use strict';

	var simpleJwtFunc = {
		navMenuSection: null,
		authKeySection: null,

		updateNavSec: function (width, target, parent) {
			var targetElement = $(target);
			var parentElement = $(parent);

			if ($(window).width() < width) {
				if (targetElement.length) {
					this.navMenuSection = targetElement.detach();
				}
			} else {
				if (this.navMenuSection && parentElement.find(target).length === 0) {
					parentElement.prepend(this.navMenuSection);
					this.navMenuSection = null;
				}
			}
		},

		updateAuthSec: function (checkbox, target, parent) {
			var targetElement = $(target);
			var parentElement = $(parent);

			if (checkbox.is(':checked')) {
				if (this.authKeySection && parentElement.find(target).length === 0) {
					parentElement.prepend(this.authKeySection);
					this.authKeySection.hide().fadeIn(600, 'linear', () => {
						this.authKeySection = null;
					});
				}
			} else {
				if (!this.authKeySection && parentElement.find(target).length > 0) {
					targetElement.fadeOut(600, 'linear', () => {
						this.authKeySection = targetElement.detach();
					});
				}
			}
		},

		appendChecked: function (target) {
			var checked = '<span class="simplejwt-saved"></span>';
			$(target).html(checked);
		},

		appendLoader: function (target) {
			var spinner = '<span class="simplejwt-spinner"></span>';
			$(target).html(spinner);
		},

		removeElement: function (target) {
			$(target).remove();
		}
	};

	$(function () {
		var targetWidth = 768;
		var targetClass = '.simplejwt-menu-area';
		var parentClass = '.simplejwt-navbar-wrapper';

		simpleJwtFunc.updateNavSec(targetWidth, targetClass, parentClass);
		$(window).resize(function () {
			simpleJwtFunc.updateNavSec(targetWidth, targetClass, parentClass);
		});
	});

	$(function () {
		var jwtCheckbox = $('#simplejwt_enable_auth');
		var parentClass = '.simplejwt-key-area';
		var targetClass = '.simplejwt-key-wrapper';

		simpleJwtFunc.updateAuthSec(jwtCheckbox, targetClass, parentClass);
		$('#simplejwt_enable_auth').on('change', function (e) {
			e.preventDefault();
			simpleJwtFunc.updateAuthSec(jwtCheckbox, targetClass, parentClass);
		});
	});

	$('#simplejwt_algorithm').on('change', function (e) {
		e.preventDefault();
		var getAlgorithm = $(this).val();
		var signatureHs = $('.simplejwt-signature-area.HS256');
		var signatureRs = $('.simplejwt-signature-area.RS256');

		if (getAlgorithm.startsWith('HS')) {
			signatureRs.hide();
			signatureHs.show();
		} else if (getAlgorithm.startsWith('RS') || getAlgorithm.startsWith('ES') || getAlgorithm.startsWith('PS')) {
			signatureHs.hide();
			signatureRs.show();
		}
	});

	$('.simplejwt-copy-btn').on('click', function () {
		var copyButton = $(this);
		var apiEndpoint = $(this).siblings('.simplejwt-endpoint-data');
		var endpointValue = apiEndpoint.val();
		apiEndpoint.select();
		if (endpointValue) {
			navigator.clipboard.writeText(endpointValue).then(function () {
				copyButton.addClass('simplejwt-active');
				setTimeout(function () {
					copyButton.removeClass('simplejwt-active');
				}, 2000);
			}).catch(function (error) {
				console.error('Failed to copy: ', error);
			});
		}
	});

	$('#simplejwt_submit_btn').on('click', function (e) {
		e.preventDefault();
		var submitButton = $(this);
		var buttonText = $(this).text().trim();
	
		submitButton.text('');
		simpleJwtFunc.appendLoader(submitButton);
	
		setTimeout(function () {
			simpleJwtFunc.removeElement('.simplejwt-spinner');
			submitButton.text(buttonText);
	
			// Submit the form after 2 seconds
			submitButton.closest('form').submit();
		}, 2000);
	});

	$('#simplejwt_drop_configs').on('change', function (e) {
		e.preventDefault();
		// Submit the form
		$(this).closest('form').submit();
	});

	$('#simplejwt_disable_xmlrpc').on('change', function (e) {
		e.preventDefault();
		// Submit the form
		$(this).closest('form').submit();
	});
})(jQuery);
