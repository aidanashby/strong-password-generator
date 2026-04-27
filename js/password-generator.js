(function ($) {
	$(document).ready(function () {
		var generateBtn      = $('#generate-password-btn');
		var resultsContainer = $('#password-results');

		generateBtn.on('click', function () {
			generateBtn.prop('disabled', true).attr('aria-busy', 'true');
			resultsContainer.empty().append(
				$('<p>').text(passwordGeneratorL10n.generating)
			);

			$.ajax({
				url:  passwordGenerator.ajaxUrl,
				type: 'POST',
				data: {
					action: 'generate_passwords',
					nonce:  passwordGenerator.nonce,
				},
				success: function (response) {
					if (response.success && response.data) {
						displayPasswords(response.data);
					} else {
						resultsContainer.empty().append(
							$('<p>').text(passwordGeneratorL10n.error)
						);
					}
				},
				error: function () {
					resultsContainer.empty().append(
						$('<p>').text(passwordGeneratorL10n.error)
					);
				},
				complete: function () {
					generateBtn.prop('disabled', false).removeAttr('aria-busy');
				},
			});
		});

		function displayPasswords(passwords) {
			var list = $('<ul>').addClass('password-list');

			passwords.forEach(function (password) {
				var copyIcon  = $('<span>').addClass('copy-icon').text('\uD83D\uDCCB');
				var checkIcon = $('<span>').addClass('check-icon').text('\u2713');

				var copyBtn = $('<button>')
					.addClass('copy-btn')
					.attr('aria-label', passwordGeneratorL10n.copy)
					.data('password', password)
					.append(copyIcon, checkIcon);

				var li = $('<li>').addClass('password-item').append(
					$('<span>').addClass('password-text').text(password),
					copyBtn
				);

				list.append(li);
			});

			resultsContainer.empty().append(list);

			list.on('click', '.copy-btn', function () {
				var btn      = $(this);
				var password = btn.data('password');

				navigator.clipboard.writeText(password).then(function () {
					btn.find('.copy-icon').hide();
					btn.find('.check-icon').show();
					btn.attr('aria-label', passwordGeneratorL10n.copied);

					setTimeout(function () {
						btn.find('.check-icon').hide();
						btn.find('.copy-icon').show();
						btn.attr('aria-label', passwordGeneratorL10n.copy);
					}, 2000);
				}).catch(function () {
					btn.find('.copy-icon').text('\u2717');
					setTimeout(function () {
						btn.find('.copy-icon').text('\uD83D\uDCCB');
					}, 2000);
				});
			});
		}
	});
}(jQuery));
