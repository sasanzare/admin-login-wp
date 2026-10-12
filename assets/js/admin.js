(function () {
	'use strict';

	function copyValue(input) {
		input.focus();
		input.select();
		input.setSelectionRange(0, input.value.length);
		return document.execCommand('copy');
	}

	document.addEventListener('DOMContentLoaded', function () {
		var selectButton = document.getElementById('saza-login-select-logo');
		var removeButton = document.getElementById('saza-login-remove-logo');
		var logoInput = document.getElementById('saza-login-logo-id');
		var preview = document.querySelector('.saza-login-media-preview');
		var frame;

		if (selectButton && window.wp && window.wp.media) {
			selectButton.addEventListener('click', function (event) {
				event.preventDefault();
				if (!frame) {
					frame = window.wp.media({
						title: SazaLoginAdmin.mediaTitle,
						button: { text: SazaLoginAdmin.mediaButton },
						multiple: false,
						library: { type: 'image' }
					});
					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						logoInput.value = attachment.id;
						preview.innerHTML = '';
						var image = document.createElement('img');
						image.src = attachment.url;
						image.alt = '';
						preview.appendChild(image);
					});
				}
				frame.open();
			});
		}

		if (removeButton && logoInput && preview) {
			removeButton.addEventListener('click', function (event) {
				event.preventDefault();
				logoInput.value = '0';
				preview.textContent = SazaLoginAdmin.noLogo;
			});
		}

		document.querySelectorAll('[data-copy-url]').forEach(function (button) {
			button.addEventListener('click', function () {
				var input = document.getElementById(button.getAttribute('data-copy-url'));
				var status = document.getElementById('saza-login-copy-status');
				var copied = false;
				if (navigator.clipboard && window.isSecureContext) {
					navigator.clipboard.writeText(input.value).then(function () {
						status.textContent = SazaLoginAdmin.copied;
					}).catch(function () {
						status.textContent = SazaLoginAdmin.copyFailed;
					});
					return;
				}
				copied = copyValue(input);
				status.textContent = copied ? SazaLoginAdmin.copied : SazaLoginAdmin.copyFailed;
			});
		});
	});
}());
