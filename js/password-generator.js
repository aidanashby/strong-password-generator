(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var generateBtn      = document.getElementById('generate-password-btn');
		var resultsContainer = document.getElementById('password-results');

		generateBtn.addEventListener('click', function () {
			generateBtn.disabled = true;
			generateBtn.setAttribute('aria-busy', 'true');
			resultsContainer.innerHTML = '';
			resultsContainer.appendChild(
				createElement('p', {}, passwordGeneratorL10n.generating)
			);

			var body = new URLSearchParams();
			body.append('action', 'generate_passwords');
			body.append('nonce', passwordGenerator.nonce);

			fetch(passwordGenerator.ajaxUrl, {
				method:  'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:    body.toString(),
			})
				.then(function (response) { return response.json(); })
				.then(function (response) {
					if (response.success && response.data) {
						displayPasswords(response.data);
					} else {
						showError();
					}
				})
				.catch(showError)
				.finally(function () {
					generateBtn.disabled = false;
					generateBtn.removeAttribute('aria-busy');
				});
		});

		function showError() {
			resultsContainer.innerHTML = '';
			resultsContainer.appendChild(
				createElement('p', {}, passwordGeneratorL10n.error)
			);
		}

		function displayPasswords(passwords) {
			var list = createElement('ul', { className: 'password-list' });

			passwords.forEach(function (password) {
				var copyIcon  = createElement('span', { className: 'copy-icon' }, '📋');
				var checkIcon = createElement('span', { className: 'check-icon' }, '✓');

				var copyBtn = createElement('button', {
					className:  'copy-btn',
					ariaLabel:  passwordGeneratorL10n.copy,
				});
				copyBtn.appendChild(copyIcon);
				copyBtn.appendChild(checkIcon);

				copyBtn.addEventListener('click', function () {
					navigator.clipboard.writeText(password).then(function () {
						copyIcon.style.display = 'none';
						checkIcon.style.display = '';
						copyBtn.setAttribute('aria-label', passwordGeneratorL10n.copied);

						setTimeout(function () {
							checkIcon.style.display = 'none';
							copyIcon.style.display = '';
							copyBtn.setAttribute('aria-label', passwordGeneratorL10n.copy);
						}, 2000);
					}).catch(function () {
						copyIcon.textContent = '✗';
						setTimeout(function () {
							copyIcon.textContent = '📋';
						}, 2000);
					});
				});

				var li = createElement('li', { className: 'password-item' });
				li.appendChild(createElement('span', { className: 'password-text' }, password));
				li.appendChild(copyBtn);
				list.appendChild(li);
			});

			resultsContainer.innerHTML = '';
			resultsContainer.appendChild(list);
		}

		function createElement(tag, props, text) {
			var el = document.createElement(tag);
			if (props.className) el.className = props.className;
			if (props.ariaLabel) el.setAttribute('aria-label', props.ariaLabel);
			if (text !== undefined) el.textContent = text;
			return el;
		}
	});
}());
