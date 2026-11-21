/**
 * هوش‌یار وردپرس — دستیار داخل ویرایشگر بلوک:
 *  - پنل کناری با اکشن‌ها: پیشنهاد عنوان، تولید محتوا، بهبود سئو، خلاصه، دستور دلخواه.
 *  - شناسایی همهٔ فیلدها (عنوان، محتوا، خلاصه، متاهای سئو و فیلدهای سفارشی).
 *  - بازنویسی بلوک انتخابی.
 *  - هر پیشنهاد قبل از اعمال باید توسط کاربر تأیید شود.
 * رشته‌ها از contentAssistantI18n (دوزبانه، هماهنگ با PHP) می‌آیند.
 */
(function () {
	'use strict';

	if (typeof wp === 'undefined' || !wp.plugins || !window.contentAssistantEditorConfig || !contentAssistantEditorConfig.isBlockEditor) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var useDispatch = wp.data.useDispatch;
	var useSelect = wp.data.useSelect;

	var I18N = (window.contentAssistantI18n && window.contentAssistantI18n.strings) || {};

	function t(key, fallback) {
		return I18N[key] || fallback || key;
	}

	/* ------------------------------------------------------------------ */
	/* سرویس مشترک درخواست‌ها                                              */
	/* ------------------------------------------------------------------ */

	function askAssistant(postId, action, params) {
		var data = Object.assign({ post_id: postId, action: action }, params || {});
		return wp.apiFetch({ path: '/content-assistant/v1/assistant', method: 'POST', data: data })
			.then(function (res) {
				if (res.error) {
					throw new Error(res.error);
				}
				return res;
			});
	}

	function applyField(postId, field, value) {
		return wp.apiFetch({ path: '/content-assistant/v1/apply-field', method: 'POST', data: { post_id: postId, field: field, value: value } });
	}

	function fetchFields(postId) {
		return wp.apiFetch({ path: '/content-assistant/v1/fields?post_id=' + postId, method: 'GET' })
			.then(function (res) { return res.fields || []; });
	}

	/* ------------------------------------------------------------------ */
	/* کارت نتیجه با دکمه‌های اعمال/رد                                     */
	/* ------------------------------------------------------------------ */

	function ProposalCard(props) {
		var r = props.result;
		if (!r) {
			return null;
		}
		var children = [];

		if (r.type === 'options') {
			children.push(el('p', { key: 't' }, t('js_pick_option', 'Pick an option:')));
			r.options.forEach(function (opt, i) {
				children.push(
					el('div', {
						key: 'opt' + i,
						className: 'content-assistant-title-option',
						onClick: function () { props.onApply('post_title', opt); }
					}, opt)
				);
			});
		} else if (r.type === 'replace_html' || r.type === 'replace_text') {
			var preview = String(r.value || '').replace(/<[^>]+>/g, ' ').substring(0, 400);
			children.push(el('p', { key: 'l' }, t('js_es_proposal', 'Suggestion (confirm to apply):')));
			children.push(el('div', { key: 'v', style: { whiteSpace: 'pre-wrap', background: '#fff', border: '1px solid var(--content-assistant-line, #e3e5ea)', padding: 8, borderRadius: 6, maxHeight: 200, overflow: 'auto' } }, preview + '…'));
			children.push(
				el('div', { key: 'a', className: 'content-assistant-result-apply' },
					el('button', {
						type: 'button',
						className: 'button button-primary button-small',
						disabled: props.busy,
						onClick: function () { props.onApply(r.field, r.value); }
					}, t('js_apply', 'Apply ✓')),
					el('button', {
						type: 'button',
						className: 'button button-small',
						onClick: props.onDismiss
					}, t('js_dismiss', 'Dismiss ✕'))
				)
			);
		} else if (r.type === 'seo_report') {
			if (r.score !== null && r.score !== undefined) {
				var cls = r.score >= 80 ? 'good' : (r.score >= 50 ? 'mid' : 'bad');
				children.push(el('p', { key: 's' },
					t('js_seo_score', 'SEO score:') + ' ',
					el('span', { className: 'content-assistant-seo-score ' + cls }, String(r.score))
				));
			}
			if (r.issues && r.issues.length) {
				children.push(el('div', { key: 'i' },
					el('b', {}, t('js_issues', 'Issues:')),
					el('ul', { className: 'content-assistant-suggestions' }, r.issues.map(function (x, i) { return el('li', { key: 'i' + i }, x); }))
				));
			}
			if (r.suggestions && r.suggestions.length) {
				children.push(el('div', { key: 'p' },
					el('b', {}, t('js_suggestions', 'Suggestions:')),
					el('ul', { className: 'content-assistant-suggestions' }, r.suggestions.map(function (x, i) { return el('li', { key: 'p' + i }, x); }))
				));
			}
			if (r.meta_description) {
				children.push(el('p', { key: 'm' }, el('b', {}, t('js_meta_desc_suggested', 'Suggested meta:')), el('br'), el('span', { dir: (window.contentAssistantI18n && contentAssistantI18n.dir) || 'rtl' }, r.meta_description)));
				children.push(
					el('button', {
						key: 'ma',
						type: 'button',
						className: 'button button-small',
						disabled: props.busy,
						onClick: function () { props.onApply('meta:_content_assistant_meta_description', r.meta_description); }
					}, t('js_apply_meta', '✅ Apply meta'))
				);
			}
			if (r.focus_keyword) {
				children.push(el('p', { key: 'f' }, el('b', {}, t('js_focus_keyword', 'Main keyword:')), ' ' + r.focus_keyword));
				children.push(
					el('button', {
						key: 'fa',
						type: 'button',
						className: 'button button-small',
						disabled: props.busy,
						onClick: function () { props.onApply('meta:_content_assistant_focus_keyword', r.focus_keyword); }
					}, t('js_apply_keyword', '✅ Apply keyword'))
				);
			}
			if (r.content_fixes_html) {
				children.push(el('p', { key: 'cf' }, t('js_content_fixes_note', 'The model also suggests content fixes:')));
				children.push(
					el('button', {
						key: 'cfa',
						type: 'button',
						className: 'button button-small',
						disabled: props.busy,
						onClick: function () { props.onApply('post_content', r.content_fixes_html); }
					}, t('js_apply_content_fix', '✅ Apply content fix'))
				);
			}
			children.push(
				el('button', { key: 'd', type: 'button', className: 'button button-small', onClick: props.onDismiss }, t('js_close', 'Close'))
			);
		} else if (r.type === 'suggestion') {
			children.push(el('p', { key: 'l' }, t('js_assistant_suggestion', 'Assistant suggestion:') + (r.field_label ? ' (' + r.field_label + ')' : '')));
			children.push(el('div', { key: 'v', style: { whiteSpace: 'pre-wrap', background: '#fff', border: '1px solid var(--content-assistant-line, #e3e5ea)', padding: 8, borderRadius: 6, maxHeight: 200, overflow: 'auto' } }, String(r.value || '')));
			if (r.advice) {
				children.push(el('p', { key: 'a2', style: { color: 'var(--content-assistant-muted, #6b7280)' } }, '💡 ' + r.advice));
			}
			if (r.field) {
				children.push(
					el('div', { key: 'a', className: 'content-assistant-result-apply' },
						el('button', {
							type: 'button',
							className: 'button button-primary button-small',
							disabled: props.busy,
							onClick: function () { props.onApply(r.field, r.value); }
						}, t('js_apply', 'Apply ✓')),
						el('button', { type: 'button', className: 'button button-small', onClick: props.onDismiss }, t('js_dismiss', 'Dismiss ✕'))
					)
				);
			}
		}

		return el('div', { className: 'content-assistant-result' }, children);
	}

	/* ------------------------------------------------------------------ */
	/* پنل اصلی                                                            */
	/* ------------------------------------------------------------------ */

	function AssistantPanel() {
		var postId = useSelect(function (select) {
			return select('core/editor').getCurrentPostId();
		}, []);
		var selectedBlock = useSelect(function (select) {
			return select('core/block-editor').getSelectedBlock();
		}, []);
		var editPost = useDispatch('core/editor').editPost;
		var replaceBlock = useDispatch('core/block-editor').replaceBlock;

		var _useState = useState(null);
		var result = _useState[0];
		var setResult = _useState[1];
		var _useState2 = useState(false);
		var busy = _useState2[0];
		var setBusy = _useState2[1];
		var _useState3 = useState('');
		var instruction = _useState3[0];
		var setInstruction = _useState3[1];
		var _useState4 = useState('');
		var fieldKey = _useState4[0];
		var setFieldKey = _useState4[1];
		var _useState5 = useState([]);
		var fields = _useState5[0];
		var setFields = _useState5[1];

		function run(action, params) {
			setBusy(true);
			setResult(null);
			askAssistant(postId, action, params)
				.then(function (res) { setResult(res); })
				.catch(function (err) { setResult({ type: 'error', value: err.message }); })
				.then(function () { setBusy(false); });
		}

		function onApply(field, value) {
			setBusy(true);

			if (field === 'block' && selectedBlock && (selectedBlock.name === 'core/paragraph' || selectedBlock.name === 'core/heading' || selectedBlock.name === 'core/list')) {
				var attrs = Object.assign({}, selectedBlock.attributes);
				attrs.content = value;
				replaceBlock(selectedBlock.clientId, Object.assign({}, selectedBlock, { attributes: attrs }));
				setBusy(false);
				setResult(null);
				return;
			}

			if (field === 'post_title') {
				editPost({ title: value });
				setBusy(false);
				setResult(null);
				return;
			}
			if (field === 'post_excerpt') {
				editPost({ excerpt: value });
				setBusy(false);
				setResult(null);
				return;
			}
			if (field === 'post_content') {
				editPost({ content: value });
				setBusy(false);
				setResult(null);
				return;
			}

			applyField(postId, field, value)
				.then(function (res) {
					if (res && res.error) {
						throw new Error(res.error);
					}
					setResult(null);
				})
				.catch(function (err) { alert(t('js_error', 'Error:') + ' ' + err.message); })
				.then(function () { setBusy(false); });
		}

		function loadFields() {
			fetchFields(postId).then(setFields).catch(function () {});
		}

		// آیکون دش‌آیکن وردپرس (به‌جای ایموجی رنگی).
		function icon(name) {
			return el('span', { className: 'dashicons ' + name, 'aria-hidden': 'true' });
		}

		// کاشیٔ اکشن با آیکن + برچسب خوانا.
		function tile(key, iconName, label, action) {
			return el('button', {
				key: key,
				type: 'button',
				className: 'content-assistant-es-tile',
				disabled: busy,
				onClick: action
			}, icon(iconName), el('span', { className: 'content-assistant-es-tile-label' }, label));
		}

		// شناسایی خودکار فیلدها به‌محض آماده شدن شناسهٔ پست.
		useEffect(function () {
			if (postId) {
				loadFields();
			}
		}, [postId]);

		var tiles = [
			tile('title', 'dashicons-awards', t('js_btn_title_suggest', 'Suggest titles'), function () { run('suggest_title', { count: 3, instruction: instruction }); }),
			tile('content', 'dashicons-edit', t('js_btn_write_content', 'Write content'), function () { run('write_content', { mode: 'replace', instruction: instruction }); }),
			tile('seo', 'dashicons-chart-bar', t('js_btn_seo', 'Improve SEO'), function () { run('improve_seo', { instruction: instruction }); }),
			tile('excerpt', 'dashicons-media-text', t('js_btn_excerpt', 'Suggest excerpt'), function () { run('improve_excerpt', { instruction: instruction }); })
		];

		if (selectedBlock) {
			tiles.push(
				tile('block', 'dashicons-controls-repeat', t('js_btn_rewrite_block', 'Rewrite selected block'), function () {
					var html = selectedBlock.attributes && selectedBlock.attributes.content ? selectedBlock.attributes.content : '';
					run('improve_block', { field: 'block', field_label: t('js_selected_block', 'Selected block'), field_value: html, instruction: instruction });
				})
			);
		}

		// اعمال مستقیم suggestion (بدون onApply در نتیجه) — اینجا همیشه فیلد مشخص است.
		if (result && result.type === 'suggestion' && !result.field) {
			result = Object.assign({}, result, { field: fieldKey || 'post_content' });
		}

		var selectedField = null;
		fields.forEach(function (x) { if (x.key === fieldKey) { selectedField = x; } });

		function runOnField() {
			run('custom', {
				field: fieldKey,
				field_label: selectedField ? selectedField.label : fieldKey,
				field_value: selectedField ? selectedField.value : '',
				instruction: instruction
			});
		}

		var resultBlock = null;
		if (busy) {
			resultBlock = el('div', { className: 'content-assistant-result content-assistant-loading content-assistant-es-loading', role: 'status', 'aria-live': 'polite' },
				el('span', { className: 'content-assistant-es-spinner', 'aria-hidden': 'true' }),
				t('js_applying', 'Working…')
			);
		} else if (result && result.type === 'error') {
			resultBlock = el('div', { className: 'content-assistant-result content-assistant-error-result', role: 'alert' },
				icon('dashicons-warning'), ' ' + result.value
			);
		} else if (result) {
			resultBlock = ProposalCard({
				result: result,
				busy: busy,
				onApply: onApply,
				onDismiss: function () { setResult(null); }
			});
		}

		return el('div', { className: 'content-assistant-sidebar-panel', dir: (window.contentAssistantI18n && contentAssistantI18n.dir) || 'rtl' },
			el('p', { className: 'content-assistant-es-intro' },
				icon('dashicons-info-outline'),
				el('span', {}, t('assistant_desc', 'Smart panel — get suggestions for any part of a post; nothing is applied without your confirmation.'))
			),

			el('section', { className: 'content-assistant-es-section' },
				el('h4', { className: 'content-assistant-es-title' }, icon('dashicons-superhero'), t('js_es_quick_actions', 'Quick actions')),
				el('div', { className: 'content-assistant-es-actions' }, tiles)
			),

			el('section', { className: 'content-assistant-es-section' },
				el('h4', { className: 'content-assistant-es-title' }, icon('dashicons-edit-large'), t('js_es_instruction_label', 'Special instruction')),
				el('textarea', {
					className: 'content-assistant-es-textarea',
					rows: 3,
					'aria-label': t('js_es_instruction_label', 'Special instruction'),
					placeholder: t('js_es_instruction_ph', 'e.g. “Make the tone more formal”…'),
					value: instruction,
					onChange: function (e) { setInstruction(e.target.value); }
				}),
				el('p', { className: 'content-assistant-es-hint' }, selectedBlock ? t('js_applies_to_block', 'Applied to the selected block.') : t('js_applies_to_post', 'Applied to the whole post.')),
				el('button', {
					type: 'button',
					className: 'button button-primary content-assistant-es-run',
					disabled: busy || !instruction.trim(),
					onClick: function () {
						if (selectedBlock && selectedBlock.attributes && selectedBlock.attributes.content) {
							run('custom', { field: 'block', field_label: t('js_selected_block', 'Selected block'), field_value: selectedBlock.attributes.content, instruction: instruction });
						} else {
							run('custom', { field: 'post_content', field_label: t('js_whole_post', 'Whole post'), instruction: instruction });
						}
					}
				}, icon('dashicons-play'), t('js_es_run_instruction', 'Run instruction'))
			),

			el('section', { className: 'content-assistant-es-section' },
				el('h4', { className: 'content-assistant-es-title' },
					icon('dashicons-admin-customizer'),
					t('js_es_fields_title', 'Detected post fields'),
					el('button', {
						type: 'button',
						className: 'button button-small content-assistant-es-refresh',
						disabled: busy,
						onClick: loadFields,
						title: t('js_es_refresh_fields', 'Refresh field list')
					}, icon('dashicons-update'))
				),
				fields.length
					? el('div', { className: 'content-assistant-es-fieldrow' },
						el('select', {
							className: 'content-assistant-es-select',
							'aria-label': t('js_es_fields_title', 'Detected post fields'),
							value: fieldKey,
							onChange: function (e) { setFieldKey(e.target.value); }
						}, fields.map(function (f) {
							return el('option', { key: f.key, value: f.key }, f.label);
						})),
						el('button', {
							type: 'button',
							className: 'button content-assistant-es-run-field',
							disabled: busy || !instruction.trim() || !fieldKey,
							onClick: runOnField
						}, t('js_es_run_on_selected', 'Run on selected field'))
					)
					: el('p', { className: 'content-assistant-es-hint' }, icon('dashicons-info-outline'), t('js_es_refresh_hint', 'Hit refresh to detect fields.'))
			),

			resultBlock
				? el('section', { className: 'content-assistant-es-section' },
					el('h4', { className: 'content-assistant-es-title' }, icon('dashicons-visibility'), t('js_es_result_title', 'Result')),
					resultBlock
				)
				: null
		);
	}

	/* ------------------------------------------------------------------ */
	/* ثبت افزونه و پنل کناری                                              */
	/* ------------------------------------------------------------------ */

	function Plugin() {
		return el(wp.element.Fragment, {},
			el(PluginSidebarMoreMenuItem, { target: 'content-assistant-assistant-panel', icon: 'lightbulb' }, t('js_es_panel_title', 'Hoshyar')),
			el(PluginSidebar, { name: 'content-assistant-assistant-panel', title: t('js_es_panel_title', 'Hoshyar'), icon: 'lightbulb' },
				el(AssistantPanel, {})
			)
		);
	}

	registerPlugin('content-assistant-assistant', { render: Plugin, icon: 'lightbulb' });

	// دکمهٔ نوار مدیریت در صفحهٔ ویرایش: باز کردن پنل.
	document.addEventListener('click', function (e) {
		var tgt = e.target.closest ? e.target.closest('.content-assistant-open-panel, #wpadminbar #wp-admin-bar-content-assistant-smart-panel > a') : null;
		if (tgt) {
			e.preventDefault();
			wp.data.dispatch('core/interface').openGeneralSidebar('content-assistant-assistant/content-assistant-assistant-panel');
		}
	});
})();
