/**
 * هوش‌یار وردپرس — صفحهٔ ویرایش گروهی: اسکن فیلتر، پردازش تک‌تک با تأخیر، نوار پیشرفت.
 * رشته‌ها از contentAssistantI18n (دوزبانه) می‌آیند.
 */
(function () {
	'use strict';

	var I18N = (window.contentAssistantI18n && window.contentAssistantI18n.strings) || {};

	function t(key, fallback) {
		return I18N[key] || fallback || key;
	}

	// جایگزینی %s / %d / %1$d با آرگومان‌ها (به ترتیب).
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

	function $(id) {
		return document.getElementById(id);
	}

	// فقط در صفحهٔ ویرایش گروهی المان‌ها وجود دارند.
	if (!$('gb-scan')) {
		return;
	}

	var selectedIds = [];
	var cancelFlag = false;

	function logLine(cls, text) {
		var log = $('gb-log');
		log.style.display = 'block';
		var line = document.createElement('div');
		line.className = 'content-assistant-log-line ' + cls;
		line.textContent = text;
		log.appendChild(line);
		log.scrollTop = log.scrollHeight;
	}

	function sleep(ms) {
		return new Promise(function (resolve) {
			setTimeout(resolve, ms);
		});
	}

	// ---- ۱) اسکن ----
	$('gb-scan').addEventListener('click', function () {
		var btn = this;
		var scanLabel = btn.textContent;
		btn.disabled = true;
		btn.textContent = t('js_searching', 'Searching…');
		$('gb-scan-result').innerHTML = '';
		$('gb-run').disabled = true;

		wp.apiFetch({
			path: '/content-assistant/v1/bulk-scan',
			method: 'POST',
			data: {
				post_type: $('gb_post_type').value,
				filter: $('gb_filter').value,
				limit: $('gb_limit').value,
				search: $('gb_search').value
			}
		}).then(function (res) {
			btn.disabled = false;
			btn.textContent = scanLabel;

			if (!res.items || !res.items.length) {
				$('gb-scan-result').innerHTML = '<p>' + t('js_no_posts_found', 'No posts found.') + '</p>';
				return;
			}

			selectedIds = res.items.map(function (i) { return i.id; });

			var html = '<p><b>' + fmt('js_posts_found', res.items.length) + '</b></p>' +
				'<table class="widefat striped"><thead><tr>' +
				'<th>' + t('col_id', 'ID') + '</th>' +
				'<th>' + t('field_title', 'Title') + '</th>' +
				'<th>' + t('col_status', 'Status') + '</th>' +
				'</tr></thead><tbody>';
			res.items.forEach(function (i) {
				html += '<tr><td>' + i.id + '</td><td><a href="' + i.edit_link + '" target="_blank" rel="noopener noreferrer">' +
					i.title.replace(/</g, '&lt;') + '</a></td><td>' + i.status + '</td></tr>';
			});
			html += '</tbody></table>';
			$('gb-scan-result').innerHTML = html;
			$('gb-run').disabled = false;
		}).catch(function (err) {
			btn.disabled = false;
			btn.textContent = scanLabel;
			$('gb-scan-result').innerHTML = '<p style="color:#dc2626">' + fmt('js_error', (err && err.message) ? err.message : t('js_unknown_error')) + '</p>';
		});
	});

	// ---- ۲) پردازش ----
	$('gb-run').addEventListener('click', function () {
		if (!selectedIds.length) {
			return;
		}
		cancelFlag = false;
		var btn = this;
		var runLabel = btn.textContent;
		btn.disabled = true;
		$('gb-cancel').disabled = false;
		$('gb-scan').disabled = true;

		var op = $('gb_op').value;
		var modeEl = document.querySelector('input[name="gb_mode"]:checked');
		var mode = modeEl ? modeEl.value : 'replace';
		var instruction = $('gb_instruction').value;

		var bar = document.querySelector('.content-assistant-progress-bar');
		bar.style.display = 'block';
		$('gb-log').innerHTML = '';
		$('gb-log').style.display = 'block';

		var total = selectedIds.length;
		var done = 0;
		var ok = 0;
		var failed = 0;

		var chain = Promise.resolve();
		selectedIds.forEach(function (postId) {
			chain = chain.then(function () {
				if (cancelFlag) {
					return;
				}
				return wp.apiFetch({
					path: '/content-assistant/v1/bulk-process',
					method: 'POST',
					data: {
						post_id: postId,
						op: op,
						mode: mode,
						instruction: instruction
					}
				}).then(function (res) {
					if (res.error) {
						failed++;
						logLine('content-assistant-error', '#' + postId + ' — ' + res.error);
					} else {
						ok++;
						logLine('content-assistant-success', fmt('js_log_success',
							postId,
							(res.title || ''),
							(res.applied && res.applied.length ? res.applied.join(', ') : t('js_no_changes', 'no changes'))
						));
					}
				}).catch(function (err) {
					failed++;
					logLine('content-assistant-error', fmt('js_connection_error', (err && err.message) ? err.message : t('js_unknown_error')));
				}).then(function () {
					done++;
					$('gb-progress-fill').style.width = Math.round((done / total) * 100) + '%';
					$('gb-progress').textContent = fmt('js_progress', done, total, ok, failed);
					return sleep(window.contentAssistantBulkConfig ? window.contentAssistantBulkConfig.delay : 2000);
				});
			});
		});

		chain.then(function () {
			btn.disabled = false;
			btn.textContent = runLabel;
			$('gb-cancel').disabled = true;
			$('gb-scan').disabled = false;
			var summary = cancelFlag ? t('js_stopped', 'Stopped. ') : t('js_done', 'Done. ');
			logLine('', summary + fmt('js_summary', ok, failed));
		});
	});

	$('gb-cancel').addEventListener('click', function () {
		cancelFlag = true;
	});
})();
