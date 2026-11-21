/**
 * هوش‌یار وردپرس — دستیار ویرایشگر کلاسیک (متا‌باکس):
 *  شناسایی فیلدها، پیشنهاد با تأیید کاربر. رشته‌ها از contentAssistantI18n (دوزبانه).
 */
(function () {
	'use strict';

	var root = document.getElementById('content-assistant-classic-root');
	if (!root) {
		return;
	}

	var I18N = (window.contentAssistantI18n && window.contentAssistantI18n.strings) || {};

	function t(key, fallback) {
		return I18N[key] || fallback || key;
	}

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

	var postId = parseInt(document.querySelector('input#post_ID') ? document.querySelector('input#post_ID').value : '0', 10);
	var busy = false;

	/* ---- ابزارک ---- */

	function el(tag, props, children) {
		var node = document.createElement(tag);
		if (props) {
			Object.keys(props).forEach(function (k) {
				if (k === 'style') {
					Object.assign(node.style, props[k]);
				} else if (k === 'className') {
					node.className = props[k];
				} else if (k.indexOf('on') === 0 && typeof props[k] === 'function') {
					node.addEventListener(k.slice(2).toLowerCase(), props[k]);
				} else {
					node.setAttribute(k, props[k]);
				}
			});
		}
		var kids = children;
		if (kids === null || kids === undefined) {
			kids = [];
		} else if (typeof kids === 'string' || kids.nodeType) {
			kids = [kids];
		}
		Array.prototype.forEach.call(kids, function (c) {
			if (c === null || c === undefined) {
				return;
			}
			node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
		});
		return node;
	}

	function setBusy(v) {
		busy = v;
		root.querySelectorAll('button').forEach(function (b) { b.disabled = v; });
		status.textContent = v ? t('js_applying', 'Working…') : '';
		status.style.color = 'var(--content-assistant-muted, #6b7280)';
	}

	/* ---- خواندن/نوشتن فیلدهای ویرایشگر کلاسیک ---- */

	function getContent() {
		if (typeof tinymce !== 'undefined' && tinymce.get('content') && !tinymce.get('content').isHidden()) {
			return tinymce.get('content').getContent();
		}
		var ta = document.getElementById('content');
		return ta ? ta.value : '';
	}

	function setContent(html) {
		if (typeof tinymce !== 'undefined' && tinymce.get('content') && !tinymce.get('content').isHidden()) {
			tinymce.get('content').setContent(html);
			return;
		}
		var ta = document.getElementById('content');
		if (ta) {
			ta.value = html;
			ta.dispatchEvent(new Event('change', { bubbles: true }));
		}
	}

	function getTitle() {
		var tEl = document.getElementById('title');
		return tEl ? tEl.value : '';
	}

	function getExcerpt() {
		var e = document.getElementById('excerpt');
		return e ? e.value : '';
	}

	// فیلدهای سفارشی (جعبهٔ اطلاعات برگزیده و متاهای عمومی).
	function getCustomFields() {
		var out = [];
		document.querySelectorAll('#postcustomstuff input[name^="meta["], #postcustomstuff textarea[name^="meta["]').forEach(function (input) {
			var m = input.name.match(/^meta\[(\d+)\]\[([^\]]+)\]$/);
			var keyInput = input.name.replace(/\[[^\]]+\]$/, '[key]');
			var keyEl = document.querySelector('#postcustomstuff input[name="' + keyInput + '"]');
			var key = keyEl ? keyEl.value : (m ? m[2] : '');
			if (key) {
				out.push({ key: key, value: input.value, input: input });
			}
		});
		return out;
	}

	/* ---- درخواست‌ها ---- */

	function ask(action, params) {
		var data = Object.assign({ post_id: postId, action: action }, params || {});
		return wp.apiFetch({ path: '/content-assistant/v1/assistant', method: 'POST', data: data })
			.then(function (res) {
				if (res.error) {
					throw new Error(res.error);
				}
				return res;
			});
	}

	function applyField(field, value) {
		return wp.apiFetch({ path: '/content-assistant/v1/apply-field', method: 'POST', data: { post_id: postId, field: field, value: value } });
	}

	/* ---- رابط ---- */

	var resultBox = el('div', { className: 'content-assistant-result', style: { display: 'none' } });
	var status = el('p', { className: 'content-assistant-status' });

	function icon(name) {
		return el('span', { className: 'dashicons ' + name, 'aria-hidden': 'true' });
	}

	function showLoading() {
		resultBox.className = 'content-assistant-result content-assistant-loading content-assistant-es-loading';
		resultBox.style.display = 'block';
		resultBox.innerHTML = '';
		resultBox.appendChild(el('span', { className: 'content-assistant-es-spinner', 'aria-hidden': 'true' }));
		resultBox.appendChild(document.createTextNode(t('js_applying', 'Working…')));
	}

	function showError(msg) {
		resultBox.className = 'content-assistant-result content-assistant-error-result';
		resultBox.style.display = 'block';
		resultBox.innerHTML = '';
		resultBox.appendChild(icon('dashicons-warning'));
		resultBox.appendChild(document.createTextNode(' ' + msg));
	}

	function clearResult() {
		resultBox.style.display = 'none';
		resultBox.innerHTML = '';
	}

	// اعمال نتیجه با تأیید کاربر.
	function offerApply(field, value, label, localApply) {
		resultBox.className = 'content-assistant-result';
		resultBox.innerHTML = '';
		resultBox.appendChild(el('p', {}, label));
		resultBox.appendChild(el('div', { className: 'content-assistant-es-preview' }, String(value).replace(/<[^>]+>/g, ' ').substring(0, 600) + '…'));

		var applyBtn = el('button', {
			type: 'button',
			className: 'button button-primary button-small',
			onClick: function () {
				if (!window.confirm(t('js_confirm_apply', 'Apply this suggestion?'))) {
					return;
				}
				setBusy(true);
				var p;
				if (localApply) {
					localApply(value);
					p = Promise.resolve({ ok: true });
				} else {
					p = applyField(field, value);
				}
				p.then(function (res) {
					if (res && res.error) {
						throw new Error(res.error);
					}
					clearResult();
					resultBox.appendChild(el('p', { style: { color: 'var(--content-assistant-ok, #16a34a)' } }, t('js_applied_ok', 'Applied.')));
					resultBox.style.display = 'block';
				}).catch(function (err) {
					showError(err.message);
				}).then(function () { setBusy(false); });
			}
		}, t('js_apply_confirm', 'Apply with my confirmation'));

		var dismissBtn = el('button', { type: 'button', className: 'button button-small', onClick: clearResult }, t('js_dismiss', 'Dismiss suggestion'));

		resultBox.appendChild(el('div', { className: 'content-assistant-result-apply' }, [applyBtn, dismissBtn]));
	}

	// انتخاب فیلد برای دستور دلخواه.
	var fieldSelect = el('select', { className: 'content-assistant-es-select', 'aria-label': t('js_target_field', 'Target field + special instruction') });
	fieldSelect.appendChild(el('option', { value: 'post_title' }, t('field_title', 'Title')));
	fieldSelect.appendChild(el('option', { value: 'post_content' }, t('js_opt_main_content', 'Main content')));
	fieldSelect.appendChild(el('option', { value: 'post_excerpt' }, t('field_excerpt', 'Excerpt')));
	fieldSelect.appendChild(el('option', { value: 'meta:_content_assistant_meta_description' }, t('field_meta_desc', 'Meta description')));
	fieldSelect.appendChild(el('option', { value: 'meta:_content_assistant_focus_keyword' }, t('field_focus_keyword', 'Main SEO keyword')));

	function refreshCustomFieldOptions() {
		Array.prototype.slice.call(fieldSelect.querySelectorAll('option[data-custom]')).forEach(function (o) { o.remove(); });
		getCustomFields().slice(0, 20).forEach(function (f) {
			var opt = el('option', { value: 'cf:' + f.key, 'data-custom': '1' }, fmt('field_meta', f.key));
			fieldSelect.appendChild(opt);
		});
	}

	var instruction = el('textarea', { className: 'content-assistant-es-textarea', rows: '3', placeholder: t('js_instruction_ph_classic', 'e.g. “Make the title catchier”…') });

	function selectedFieldValue(key) {
		if (key === 'post_title') {
			return getTitle();
		}
		if (key === 'post_content') {
			return getContent();
		}
		if (key === 'post_excerpt') {
			return getExcerpt();
		}
		if (key.indexOf('meta:') === 0) {
			var name = key.slice(5);
			var found = '';
			getCustomFields().forEach(function (f) {
				if (f.key === name) {
					found = f.value;
				}
			});
			return found;
		}
		if (key.indexOf('cf:') === 0) {
			var k = key.slice(3);
			var v = '';
			getCustomFields().forEach(function (f) {
				if (f.key === k) {
					v = f.value;
				}
			});
			return v;
		}
		return '';
	}

	function applyToFieldLocally(key, value) {
		if (key === 'post_title') {
			var tEl = document.getElementById('title');
			if (tEl) { tEl.value = value; }
			return;
		}
		if (key === 'post_content') {
			setContent(value);
			return;
		}
		if (key === 'post_excerpt') {
			var e = document.getElementById('excerpt');
			if (e) { e.value = value; }
			return;
		}
		var name = key.indexOf('meta:') === 0 ? key.slice(5) : (key.indexOf('cf:') === 0 ? key.slice(3) : '');
		if (name) {
			var done = false;
			getCustomFields().forEach(function (f) {
				if (f.key === name) {
					f.input.value = value;
					f.input.dispatchEvent(new Event('change', { bubbles: true }));
					done = true;
				}
			});
			if (!done) {
				applyField('meta:' + name, value);
			}
		}
	}

	/* ---- اکشن‌ها ---- */

	function doAction(action) {
		if (busy) {
			return;
		}
		var key = fieldSelect.value;
		var params = {
			instruction: instruction.value,
			field: key.indexOf('cf:') === 0 ? 'meta:' + key.slice(3) : key,
			field_label: fieldSelect.options[fieldSelect.selectedIndex].text,
			field_value: selectedFieldValue(key)
		};
		if (action === 'suggest_title') {
			params.count = 3;
		}
		if (action === 'write_content') {
			params.mode = 'replace';
		}

		setBusy(true);
		showLoading();
		ask(action, params).then(function (res) {
			setBusy(false);
			if (res.type === 'options') {
				resultBox.className = 'content-assistant-result';
				resultBox.innerHTML = '';
				resultBox.appendChild(el('p', {}, t('js_pick_option', 'Pick an option:')));
				res.options.forEach(function (opt) {
					resultBox.appendChild(el('div', {
						className: 'content-assistant-title-option',
						onClick: function () {
							if (window.confirm(fmt('js_confirm_title', opt))) {
								applyToFieldLocally('post_title', opt);
								clearResult();
							}
						}
					}, [opt]));
				});
				resultBox.appendChild(el('button', { type: 'button', className: 'button button-small', onClick: clearResult }, t('js_close', 'Close')));
			} else if (res.type === 'replace_html' || res.type === 'replace_text') {
				var target = res.field === 'block' ? 'post_content' : res.field;
				offerApply(target, res.value, t('js_ready_to_apply', 'Ready — confirm to apply:'), function (v) {
					applyToFieldLocally(target, v);
				});
			} else if (res.type === 'seo_report') {
				resultBox.className = 'content-assistant-result';
				resultBox.innerHTML = '';
				if (res.score !== null && res.score !== undefined) {
					var cls = res.score >= 80 ? 'good' : (res.score >= 50 ? 'mid' : 'bad');
					resultBox.appendChild(el('p', {}, [t('js_seo_score', 'SEO score:') + ' ', el('span', { className: 'content-assistant-seo-score ' + cls }, String(res.score))]));
				}
				if (res.issues && res.issues.length) {
					var ul1 = el('ul', { className: 'content-assistant-suggestions' }, res.issues.map(function (x) { return el('li', {}, [x]); }));
					resultBox.appendChild(el('div', {}, [el('b', {}, t('js_issues', 'Issues:')), ul1]));
				}
				if (res.suggestions && res.suggestions.length) {
					var ul2 = el('ul', { className: 'content-assistant-suggestions' }, res.suggestions.map(function (x) { return el('li', {}, [x]); }));
					resultBox.appendChild(el('div', {}, [el('b', {}, t('js_suggestions', 'Suggestions:')), ul2]));
				}
				if (res.meta_description) {
					resultBox.appendChild(el('p', {}, [el('b', {}, t('js_meta_desc_suggested', 'Suggested meta:')), el('br'), res.meta_description]));
					resultBox.appendChild(el('button', {
						type: 'button', className: 'button button-small',
						onClick: function () {
							if (window.confirm(t('js_confirm_meta', 'Apply the meta description?'))) {
								applyToFieldLocally('meta:_content_assistant_meta_description', res.meta_description);
							}
						}
					}, t('js_apply_meta', '✅ Apply meta')));
				}
				if (res.focus_keyword) {
					resultBox.appendChild(el('p', {}, t('js_focus_keyword', 'Main keyword:') + ' ' + res.focus_keyword));
					resultBox.appendChild(el('button', {
						type: 'button', className: 'button button-small',
						onClick: function () {
							if (window.confirm(t('js_confirm_keyword', 'Apply the keyword?'))) {
								applyToFieldLocally('meta:_content_assistant_focus_keyword', res.focus_keyword);
							}
						}
					}, t('js_apply_keyword', '✅ Apply keyword')));
				}
				if (res.content_fixes_html) {
					resultBox.appendChild(el('button', {
						type: 'button', className: 'button button-small',
						onClick: function () {
							if (window.confirm(t('js_confirm_content_fix', 'Apply the content fix?'))) {
								applyToFieldLocally('post_content', res.content_fixes_html);
							}
						}
					}, t('js_apply_content_fix', '✅ Apply content fix')));
				}
				resultBox.appendChild(el('button', { type: 'button', className: 'button button-small', style: { marginInlineStart: '6px' }, onClick: clearResult }, t('js_close', 'Close')));
			} else if (res.type === 'suggestion') {
				offerApply(res.field || (fieldSelect.value.indexOf('cf:') === 0 ? 'meta:' + fieldSelect.value.slice(3) : fieldSelect.value), res.value,
					t('js_assistant_suggestion', 'Hoshyar suggestion:') + (res.advice ? ' — ' + res.advice : ''),
					function (v) { applyToFieldLocally(fieldSelect.value, v); });
			}
		}).catch(function (err) {
			setBusy(false);
			showError(err.message);
		});
	}

	/* ---- مونتاژ UI ---- */

	function section(titleKey, iconName, children) {
		var h = el('h4', { className: 'content-assistant-es-title' }, [icon(iconName), t(titleKey)]);
		var kids = (children || []).slice();
		kids.unshift(h);
		return el('section', { className: 'content-assistant-es-section' }, kids);
	}

	function tile(iconName, label, onClick) {
		return el('button', { type: 'button', className: 'content-assistant-es-tile', onClick: onClick },
			[icon(iconName), el('span', { className: 'content-assistant-es-tile-label' }, label)]);
	}

	var actions = el('div', { className: 'content-assistant-es-actions' }, [
		tile('dashicons-awards', t('js_btn_title_suggest', 'Suggest titles'), function () { doAction('suggest_title'); }),
		tile('dashicons-edit', t('js_btn_write_content', 'Write content'), function () { doAction('write_content'); }),
		tile('dashicons-media-text', t('js_btn_excerpt', 'Suggest excerpt'), function () { doAction('improve_excerpt'); }),
		tile('dashicons-chart-bar', t('js_btn_seo', 'Improve SEO'), function () { doAction('improve_seo'); })
	]);

	var runFieldBtn = el('button', {
		type: 'button', className: 'button button-primary content-assistant-es-run',
		onClick: function () {
			var key = fieldSelect.value;
			if (key === 'post_content') {
				setBusy(true);
				showLoading();
				ask('improve_block', {
					instruction: instruction.value,
					field: 'post_content',
					field_label: t('js_opt_main_content', 'Main content'),
					field_value: getContent()
				}).then(function (res) {
					setBusy(false);
					offerApply('post_content', res.value, t('js_ready_to_apply', 'Ready — confirm to apply:'), function (v) { applyToFieldLocally('post_content', v); });
				}).catch(function (err) {
					setBusy(false);
					showError(err.message);
				});
				return;
			}
			doAction('custom');
		}
	}, [icon('dashicons-play'), t('js_run_on_field', 'Run instruction on selected field')]);

	root.innerHTML = '';
	root.appendChild(el('p', { className: 'content-assistant-es-intro' }, [
		icon('dashicons-info-outline'),
		el('span', {}, t('assistant_desc', 'Smart panel — get suggestions for any part of a post; nothing is applied without your confirmation.'))
	]));
	root.appendChild(section('js_es_quick_actions', 'dashicons-superhero', [actions]));
	root.appendChild(section('js_target_field', 'dashicons-edit-large', [
		fieldSelect,
		instruction,
		el('div', { className: 'content-assistant-es-fieldrow' }, [runFieldBtn])
	]));
	root.appendChild(status);
	root.appendChild(resultBox);

	refreshCustomFieldOptions();

	// دکمهٔ نوار مدیریت در ویرایشگر کلاسیک: پیمایش به متا‌باکس دستیار.
	document.addEventListener('click', function (e) {
		var tgt = e.target.closest ? e.target.closest('#wpadminbar #wp-admin-bar-content-assistant-smart-panel a') : null;
		if (tgt) {
			e.preventDefault();
			var box = document.getElementById('content-assistant-assistant-box');
			if (box) {
				box.scrollIntoView({ behavior: 'smooth', block: 'center' });
				box.style.outline = '2px solid var(--content-assistant-brand, #6d28d9)';
				setTimeout(function () { box.style.outline = ''; }, 2000);
			}
		}
	});
})();
