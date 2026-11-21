/**
 * هوش مصنوعی برای وردپرس — صفحهٔ تنظیمات:
 *  تست اتصال به API، نمایش/مخفی‌سازی کلید. رشته‌ها از contentAssistantI18n می‌آیند.
 */
(function () {
	'use strict';

	var I18N = (window.contentAssistantI18n && window.contentAssistantI18n.strings) || {};

	function t(key, fallback) {
		return I18N[key] || fallback || key;
	}

	// جایگزینی سادهٔ %s / %d / %1$d با آرگومان‌ها (به ترتیب).
	function fmt(key) {
		var text = t(key);
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return text.replace(/%(?:\d+\$)?[sd]/g, function () {
			var v = args[i];
			i++;
			return (v === undefined || v === null) ? '' : String(v);
		});
	}

	// ---- نمایش/مخفی کلید API ----
	document.querySelectorAll('.content-assistant-reveal').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var input = document.getElementById(btn.getAttribute('data-target'));
			if (!input) {
				return;
			}
			var isPw = input.type === 'password';
			input.type = isPw ? 'text' : 'password';
			// استفاده از dashicons برای چشم باز/بسته
			btn.innerHTML = isPw
				? '<span class="dashicons dashicons-visibility" aria-hidden="true"></span>'
				: '<span class="dashicons dashicons-hidden" aria-hidden="true"></span>';
		});
	});

	// ---- فعال‌سازی ویرایش کلید API روی کلیک ----
	var apiKeyInput = document.getElementById('content_assistant_api_key');
	if (apiKeyInput) {
		apiKeyInput.addEventListener('click', function () {
			if (this.readOnly) {
				this.readOnly = false;
				this.removeAttribute('readonly');
				this.setAttribute('name', 'content_assistant_settings[api_key]');
				this.placeholder = '';
				this.value = '';
				this.focus();
			}
		});
	}

	// ---- تست اتصال ----
	var testBtn = document.getElementById('content-assistant-test-connection');
	if (!testBtn) {
		return;
	}
	var out = document.getElementById('content-assistant-test-result');

	testBtn.addEventListener('click', function () {
		out.className = 'content-assistant-test-result';
		out.textContent = t('js_testing', 'Testing…');
		testBtn.disabled = true;

		wp.apiFetch({ path: '/content-assistant/v1/test-connection', method: 'POST' })
			.then(function (res) {
				testBtn.disabled = false;
				if (res && res.ok) {
					out.className = 'content-assistant-test-result content-assistant-ok';
					out.textContent = (res.message || t('test_ok')) + (res.model ? ' — ' + t('js_model_prefix', 'Model:') + ' ' + res.model : '');
				} else {
					out.className = 'content-assistant-test-result content-assistant-err';
					out.textContent = fmt('js_error', (res && res.message) ? res.message : t('js_unknown_error'));
				}
			})
			.catch(function (err) {
				testBtn.disabled = false;
				out.className = 'content-assistant-test-result content-assistant-err';
				out.textContent = fmt('js_error', (err && err.message) ? err.message : t('js_unknown_error'));
			});
	});
})();
