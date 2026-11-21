/**
 * هوش‌یار وردپرس — صفحهٔ تست سریع: تولید یک پست از موضوع/درخواست و انتشار آن.
 * رشته‌ها از contentAssistantI18n (دوزبانه) می‌آیند.
 */
(function () {
	'use strict';

	var I18N = (window.contentAssistantI18n && window.contentAssistantI18n.strings) || {};

	function t(key, fallback) {
		return I18N[key] || fallback || key;
	}

	function $(id) {
		return document.getElementById(id);
	}

	// فقط در صفحهٔ تست سریع المان‌ها وجود دارند.
	if (!$('qt-run')) {
		return;
	}

	function setStatus(text, isError) {
		var el = $('qt-status');
		el.textContent = text;
		el.style.color = isError ? '#dc2626' : 'var(--content-assistant-muted, #6b7280)';
	}

	function escapeHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function escapeAttr(s) {
		return String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;');
	}

	$('qt-run').addEventListener('click', function () {
		var btn = this;
		var runLabel = btn.textContent;
		var topic = $('qt_topic').value;
		var instruction = $('qt_instruction').value;
		var words = parseInt($('qt_words').value, 10) || 600;
		var publish = $('qt_publish').checked ? '1' : '0';

		if (!topic.trim()) {
			setStatus(t('err_topic_required', 'Enter a post topic.'), true);
			$('qt_topic').focus();
			return;
		}

		btn.disabled = true;
		btn.textContent = t('js_generating', 'Generating…');
		setStatus(t('js_quick_running', 'Generating…'), false);
		$('qt-result').innerHTML = '';

		wp.apiFetch({
			path: '/content-assistant/v1/quick-test',
			method: 'POST',
			data: {
				post_type: $('qt_post_type').value,
				topic: topic,
				instruction: instruction,
				words: words,
				publish: publish
			}
		}).then(function (res) {
			btn.disabled = false;
			btn.textContent = runLabel;

			if (res && res.error) {
				setStatus(res.error, true);
				return;
			}
			if (!res || !res.post_id) {
				setStatus(t('js_invalid_response', 'Invalid response.'), true);
				return;
			}

			var published = res.status === 'publish';
			setStatus(published ? t('js_post_published', 'Published.') : t('js_post_draft_created', 'Draft created.'), false);

			var html = '<div class="content-assistant-card" style="margin-top:12px;">' +
				'<h2 class="content-assistant-card-title" style="margin-top:0;">' + escapeHtml(res.title || t('untitled', '(no title)')) + '</h2>' +
				'<p class="content-assistant-actions">' +
				(res.url ? '<a class="button button-primary content-assistant-btn" target="_blank" rel="noopener noreferrer" href="' + escapeAttr(res.url) + '">' + t('js_view_post', 'View post') + '</a> ' : '') +
				(res.edit_link ? '<a class="button content-assistant-btn" href="' + escapeAttr(res.edit_link) + '">' + t('js_edit_in_wp', 'Edit in WordPress') + '</a>' : '') +
				'</p>' +
				'<p class="description">' + t('js_status_prefix', 'Status:') + ' ' + escapeHtml(res.status) + ' — ' + t('js_id_prefix', 'ID:') + ' ' + res.post_id + '</p>' +
				'</div>';
			$('qt-result').innerHTML = html;
		}).catch(function (err) {
			btn.disabled = false;
			btn.textContent = runLabel;
			var msg = (err && err.message) ? err.message : t('js_unknown_error', 'Unknown error.');
			setStatus(msg, true);
		});
	});
})();
