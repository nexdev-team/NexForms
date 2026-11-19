/* NexForms admin builder — React from WordPress core (wp.element), no build step. */
(function (wp) {
	'use strict';

	var el = wp.element;
	var h = el.createElement;
	var Fragment = el.Fragment;
	var useState = el.useState;
	var useEffect = el.useEffect;
	var useRef = el.useRef;
	var useCallback = el.useCallback;
	var C = wp.components;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var apiFetch = wp.apiFetch;
	var BOOT = window.nexformsBoot || {};
	var API = '/nexforms/v1';

	/* ---------- Static data ---------- */

	var TYPES = [
		{ type: 'text', label: __('Text', 'nex-forms'), icon: 'editor-textcolor' },
		{ type: 'email', label: __('Email', 'nex-forms'), icon: 'email' },
		{ type: 'phone', label: __('Phone', 'nex-forms'), icon: 'phone' },
		{ type: 'textarea', label: __('Paragraph', 'nex-forms'), icon: 'editor-paragraph' },
		{ type: 'select', label: __('Dropdown', 'nex-forms'), icon: 'arrow-down-alt2' },
		{ type: 'radio', label: __('Radio buttons', 'nex-forms'), icon: 'marker' },
		{ type: 'checkboxes', label: __('Checkboxes', 'nex-forms'), icon: 'yes-alt' },
		{ type: 'consent', label: __('Consent', 'nex-forms'), icon: 'privacy' },
		{ type: 'number', label: __('Number', 'nex-forms'), icon: 'calculator' },
		{ type: 'date', label: __('Date', 'nex-forms'), icon: 'calendar-alt' },
		{ type: 'url', label: __('Website', 'nex-forms'), icon: 'admin-links' },
		{ type: 'hidden', label: __('Hidden', 'nex-forms'), icon: 'hidden' }
	];
	var TYPE_MAP = {};
	TYPES.forEach(function (t) { TYPE_MAP[t.type] = t; });

	var DEFAULT_LABELS = {
		text: __('Text', 'nex-forms'), email: __('Email', 'nex-forms'), phone: __('Phone', 'nex-forms'),
		textarea: __('Message', 'nex-forms'), select: __('Choose one', 'nex-forms'), radio: __('Choose one', 'nex-forms'),
		checkboxes: __('Choose any', 'nex-forms'), consent: __('I agree to the privacy policy', 'nex-forms'),
		number: __('Number', 'nex-forms'), date: __('Date', 'nex-forms'), url: __('Website', 'nex-forms'), hidden: __('Hidden value', 'nex-forms')
	};

	var PRESETS = [
		{ id: 'clean', label: __('Clean', 'nex-forms') },
		{ id: 'rounded', label: __('Rounded', 'nex-forms') },
		{ id: 'glossy', label: __('Glossy', 'nex-forms') },
		{ id: 'outline', label: __('Outline', 'nex-forms') },
		{ id: 'underline', label: __('Underline', 'nex-forms') },
		{ id: 'theme', label: __('Theme', 'nex-forms') }
	];

	var COLORS = [
		{ name: 'Blue', color: '#2563eb' }, { name: 'Teal', color: '#0d9488' }, { name: 'Green', color: '#16a34a' },
		{ name: 'Violet', color: '#7c3aed' }, { name: 'Pink', color: '#db2777' }, { name: 'Orange', color: '#ea580c' },
		{ name: 'Red', color: '#dc2626' }, { name: 'Slate', color: '#334155' }, { name: 'Black', color: '#111111' }
	];

	var AUTOCOMPLETE = [
		{ value: '', label: __('Off / automatic', 'nex-forms') },
		{ value: 'name', label: __('Full name', 'nex-forms') },
		{ value: 'given-name', label: __('First name', 'nex-forms') },
		{ value: 'family-name', label: __('Last name', 'nex-forms') },
		{ value: 'organization', label: __('Company', 'nex-forms') },
		{ value: 'street-address', label: __('Street address', 'nex-forms') },
		{ value: 'postal-code', label: __('Postal code', 'nex-forms') },
		{ value: 'address-level2', label: __('City', 'nex-forms') },
		{ value: 'country-name', label: __('Country', 'nex-forms') }
	];

	var CYRILLIC = { 'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e', 'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sht', 'ъ': 'a', 'ы': 'y', 'ь': 'y', 'э': 'e', 'ю': 'yu', 'я': 'ya' };
	var GREEK = { 'α': 'a', 'ά': 'a', 'β': 'v', 'γ': 'g', 'δ': 'd', 'ε': 'e', 'έ': 'e', 'ζ': 'z', 'η': 'i', 'ή': 'i', 'θ': 'th', 'ι': 'i', 'ί': 'i', 'ϊ': 'i', 'ΐ': 'i', 'κ': 'k', 'λ': 'l', 'μ': 'm', 'ν': 'n', 'ξ': 'x', 'ο': 'o', 'ό': 'o', 'π': 'p', 'ρ': 'r', 'σ': 's', 'ς': 's', 'τ': 't', 'υ': 'y', 'ύ': 'y', 'ϋ': 'y', 'ΰ': 'y', 'φ': 'f', 'χ': 'ch', 'ψ': 'ps', 'ω': 'o', 'ώ': 'o' };

	/* ---------- Helpers ---------- */

	function slug(s, sep) {
		sep = sep || '_';
		s = String(s || '').replace(/<[^>]*>/g, '').toLowerCase();
		s = s.replace(/[\u0370-\u03ff\u1f00-\u1fff]/g, function (c) { return GREEK[c] || ''; });
		s = s.replace(/[\u0400-\u04ff]/g, function (c) { return CYRILLIC[c] || ''; });
		s = s.normalize ? s.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : s;
		return s.replace(/[^a-z0-9]+/g, sep).replace(new RegExp('^' + sep + '+|' + sep + '+$', 'g'), '').slice(0, sep === '-' ? 60 : 40);
	}

	function uid() { return 'f' + Math.random().toString(36).slice(2, 10); }

	function clone(o) { return JSON.parse(JSON.stringify(o)); }

	function uniqueName(fields, base, selfId) {
		base = base || 'field';
		var taken = {};
		fields.forEach(function (f) { if (f.id !== selfId) taken[f.name] = true; });
		var name = base, i = 2;
		while (taken[name]) name = base + '_' + i++;
		return name;
	}

	function newField(type, fields) {
		var f = clone((BOOT.newField || {})[type] || { type: type });
		f.id = uid();
		f.label = DEFAULT_LABELS[type] || '';
		f.name = uniqueName(fields, slug(f.label) || type);
		if (type === 'consent') f.required = true;
		return f;
	}

	function go(params) {
		var url = new URL(window.location.href);
		url.searchParams.delete('form');
		Object.keys(params || {}).forEach(function (k) { url.searchParams.set(k, params[k]); });
		window.history.pushState({}, '', url.toString());
		window.dispatchEvent(new Event('popstate'));
	}

	function formParam() { return new URL(window.location.href).searchParams.get('form'); }

	function shortcode(form) { return form.slug ? '[nexform name="' + form.slug + '"]' : '[nexform id="' + form.id + '"]'; }

	function copy(text, done) {
		var ok = function () { if (done) done(); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(ok, ok);
		} else {
			var ta = document.createElement('textarea');
			ta.value = text; document.body.appendChild(ta); ta.select();
			try { document.execCommand('copy'); } catch (e) {}
			document.body.removeChild(ta); ok();
		}
	}

	function errorText(err) {
		return (err && err.message) || __('Something went wrong.', 'nex-forms');
	}

	function useNotice() {
		var s = useState(null);
		var notice = s[0], setNotice = s[1];
		var timer = useRef();
		var show = useCallback(function (text, status) {
			clearTimeout(timer.current);
			setNotice({ text: text, status: status || 'success' });
			timer.current = setTimeout(function () { setNotice(null); }, status === 'error' ? 8000 : 3000);
		}, []);
		var node = notice ? h('div', { className: 'nfb-toast is-' + notice.status, role: 'status' }, notice.text) : null;
		return [node, show];
	}

	function CopyShortcode(props) {
		var s = useState(false);
		return h('button', {
			type: 'button',
			className: 'nfb-code' + (s[0] ? ' is-copied' : ''),
			title: __('Copy shortcode', 'nex-forms'),
			onClick: function () { copy(shortcode(props.form), function () { s[1](true); setTimeout(function () { s[1](false); }, 1500); }); }
		}, h('code', null, shortcode(props.form)), h('span', { className: 'nfb-code-hint' }, s[0] ? __('Copied', 'nex-forms') : __('Copy', 'nex-forms')));
	}

	function Dashicon(props) { return h('span', { className: 'dashicons dashicons-' + props.icon, 'aria-hidden': 'true' }); }

	/* ---------- Forms list ---------- */

	function FormsList() {
		var st = useState(null), forms = st[0], setForms = st[1];
		var bs = useState(false), busy = bs[0], setBusy = bs[1];
		var n = useNotice(), toast = n[0], notify = n[1];

		var load = useCallback(function () {
			apiFetch({ path: API + '/forms' }).then(setForms, function (e) { setForms([]); notify(errorText(e), 'error'); });
		}, [notify]);
		useEffect(load, [load]);

		function create() {
			setBusy(true);
			apiFetch({ path: API + '/forms', method: 'POST', data: {} })
				.then(function (f) { go({ form: f.id }); })
				.catch(function (e) { setBusy(false); notify(errorText(e), 'error'); });
		}
		function duplicate(f) {
			apiFetch({ path: API + '/forms/' + f.id + '/duplicate', method: 'POST' }).then(function () { notify(__('Form duplicated.', 'nex-forms')); load(); }, function (e) { notify(errorText(e), 'error'); });
		}
		function remove(f) {
			// eslint-disable-next-line no-alert
			if (!window.confirm(sprintf(__('Delete “%s”? Pages using its shortcode will show nothing.', 'nex-forms'), f.title))) return;
			apiFetch({ path: API + '/forms/' + f.id, method: 'DELETE' }).then(function () { notify(__('Form deleted.', 'nex-forms')); load(); }, function (e) { notify(errorText(e), 'error'); });
		}

		var body;
		if (forms === null) {
			body = h('div', { className: 'nfb-empty' }, h(C.Spinner));
		} else if (!forms.length) {
			body = h('div', { className: 'nfb-empty' },
				h('div', { className: 'nfb-empty-icon' }, h(Dashicon, { icon: 'feedback' })),
				h('h2', null, __('No forms yet', 'nex-forms')),
				h('p', null, __('Create your first form. It starts with name, email, phone and message, which you can change.', 'nex-forms')),
				h(C.Button, { variant: 'primary', onClick: create, isBusy: busy, disabled: busy }, __('Create form', 'nex-forms'))
			);
		} else {
			body = h('table', { className: 'nfb-table' },
				h('thead', null, h('tr', null,
					h('th', null, __('Form', 'nex-forms')),
					h('th', null, __('Shortcode', 'nex-forms')),
					h('th', { className: 'nfb-num' }, __('Fields', 'nex-forms')),
					h('th', null, __('Updated', 'nex-forms')),
					h('th', { className: 'nfb-actions-col' }, h('span', { className: 'screen-reader-text' }, __('Actions', 'nex-forms')))
				)),
				h('tbody', null, forms.map(function (f) {
					return h('tr', { key: f.id },
						h('td', null,
							h('a', { className: 'nfb-title', href: '#', onClick: function (e) { e.preventDefault(); go({ form: f.id }); } }, f.title),
							f.status === 'draft' ? h('span', { className: 'nfb-badge' }, __('Draft', 'nex-forms')) : null
						),
						h('td', null, h(CopyShortcode, { form: f })),
						h('td', { className: 'nfb-num' }, f.fields),
						h('td', { className: 'nfb-muted' }, new Date(f.modified).toLocaleString()),
						h('td', { className: 'nfb-row-actions' },
							h(C.Button, { size: 'compact', variant: 'secondary', onClick: function () { go({ form: f.id }); } }, __('Edit', 'nex-forms')),
							h(C.Button, { size: 'compact', icon: 'admin-page', label: __('Duplicate', 'nex-forms'), onClick: function () { duplicate(f); } }),
							h(C.Button, { size: 'compact', icon: 'trash', isDestructive: true, label: __('Delete', 'nex-forms'), onClick: function () { remove(f); } })
						)
					);
				}))
			);
		}

		return h('div', { className: 'nfb-page' },
			h('div', { className: 'nfb-pagehead' },
				h('h1', null, __('Forms', 'nex-forms')),
				forms && forms.length ? h(C.Button, { variant: 'primary', onClick: create, isBusy: busy, disabled: busy }, __('Add new form', 'nex-forms')) : null
			),
			h('div', { className: 'nfb-card' }, body),
			toast
		);
	}

	/* ---------- Preview of fields (mirrors the PHP markup classes) ---------- */

	function PreviewControl(props) {
		var f = props.field;
		var req = f.required ? h('span', { className: 'nf-req' }, '*') : null;
		var label = h('label', { className: 'nf-label' }, f.label || h('em', { className: 'nfb-muted' }, __('(no label)', 'nex-forms')), req);
		var help = f.help ? h('p', { className: 'nf-help', dangerouslySetInnerHTML: { __html: f.help } }) : null;
		var ph = f.placeholder || undefined;

		switch (f.type) {
			case 'textarea':
				return h(Fragment, null, label, h('textarea', { className: 'nf-input nf-textarea', rows: f.rows || 5, placeholder: ph, readOnly: true, tabIndex: -1, value: f.default || '' }), help);
			case 'select':
				return h(Fragment, null, label, h('span', { className: 'nf-select-wrap' },
					h('select', { className: 'nf-input nf-select', tabIndex: -1, value: f.default || '', onChange: function () {} },
						h('option', { value: '' }, f.placeholder || __('— Select —', 'nex-forms')),
						(f.options || []).map(function (o, i) { return h('option', { key: i, value: o.value }, o.label); })
					)), help);
			case 'radio':
			case 'checkboxes':
				return h('div', { className: 'nf-fieldset' },
					h('div', { className: 'nf-label' }, f.label, req),
					h('div', { className: 'nf-choices' }, (f.options || []).map(function (o, i) {
						return h('label', { key: i, className: 'nf-choice' }, h('input', { type: f.type === 'radio' ? 'radio' : 'checkbox', tabIndex: -1, readOnly: true, checked: f.default === o.value, onChange: function () {} }), h('span', null, o.label));
					})), help);
			case 'consent':
				return h(Fragment, null, h('label', { className: 'nf-choice nf-consent' }, h('input', { type: 'checkbox', tabIndex: -1 }), h('span', null, h('span', { dangerouslySetInnerHTML: { __html: f.label } }), req)), help);
			case 'phone':
				var c = (BOOT.countries || []).find(function (x) { return x[0] === f.country; }) || ['GR', 'Greece', '🇬🇷', '30'];
				return h(Fragment, null, label, h('div', { className: 'nf-phone' },
					h('span', { className: 'nf-cc' }, h('span', { className: 'nf-cc-face' }, c[2] + ' +' + c[3])),
					h('input', { className: 'nf-input nf-phone-num', type: 'tel', placeholder: ph, readOnly: true, tabIndex: -1 })
				), help);
			case 'hidden':
				return h('div', { className: 'nfb-hidden-field' }, h(Dashicon, { icon: 'hidden' }), h('span', null, f.label || f.name), h('code', null, f.name + ' = ' + (f.default || '∅')));
			default:
				return h(Fragment, null, label, h('input', { className: 'nf-input', type: f.type === 'number' || f.type === 'date' ? f.type : 'text', placeholder: ph, readOnly: true, tabIndex: -1, value: f.default || '' }), help);
		}
	}

	/* ---------- Canvas ---------- */

	function Canvas(props) {
		var form = props.form;
		var d = form.design;
		var drag = useRef(null);
		var ov = useState(null), over = ov[0], setOver = ov[1];
		var dk = useState(false), dark = dk[0], setDark = dk[1];

		var style = {};
		if (d.accent) style['--nf-accent'] = d.accent;
		if (d.radius !== null && d.radius !== '' && d.radius !== undefined) style['--nf-radius'] = d.radius + 'px';

		function onDragOver(e, index) {
			e.preventDefault();
			e.stopPropagation();
			var rect = e.currentTarget.getBoundingClientRect();
			var after = e.clientY > rect.top + rect.height / 2;
			setOver(index + (after ? 1 : 0));
		}
		function onDrop(e) {
			e.preventDefault();
			var target = over === null ? form.fields.length : over;
			var newType = e.dataTransfer.getData('application/x-nexforms-type');
			if (newType) props.onAdd(newType, target);
			else if (drag.current !== null) props.onMove(drag.current, target);
			drag.current = null;
			setOver(null);
		}

		var items = form.fields.map(function (f, i) {
			var selected = f.id === props.selected;
			return h(Fragment, { key: f.id },
				over === i ? h('div', { className: 'nfb-drop nf-w-full' }) : null,
				h('div', {
					className: 'nf-field nf-field--' + f.type + ' nf-w-' + (f.width || 'full') + ' nfb-item' + (selected ? ' is-selected' : ''),
					draggable: true,
					tabIndex: 0,
					role: 'button',
					'aria-pressed': selected,
					'aria-label': sprintf(__('%1$s field: %2$s', 'nex-forms'), (TYPE_MAP[f.type] || {}).label || f.type, f.label.replace(/<[^>]*>/g, '')),
					onClick: function () { props.onSelect(f.id); },
					onKeyDown: function (e) {
						if (e.target !== e.currentTarget) return;
						if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); props.onSelect(f.id); }
						if (e.altKey && e.key === 'ArrowUp') { e.preventDefault(); props.onMove(i, i - 1); }
						if (e.altKey && e.key === 'ArrowDown') { e.preventDefault(); props.onMove(i, i + 2); }
					},
					onDragStart: function (e) { drag.current = i; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', f.id); },
					onDragEnd: function () { drag.current = null; setOver(null); },
					onDragOver: function (e) { onDragOver(e, i); }
				},
				h('div', { className: 'nfb-item-tools', onClick: function (e) { e.stopPropagation(); } },
					h('span', { className: 'nfb-item-type' }, h(Dashicon, { icon: (TYPE_MAP[f.type] || {}).icon || 'admin-generic' }), (TYPE_MAP[f.type] || {}).label),
					h(C.Button, { size: 'small', icon: 'arrow-up-alt2', label: __('Move up', 'nex-forms'), disabled: i === 0, onClick: function () { props.onMove(i, i - 1); } }),
					h(C.Button, { size: 'small', icon: 'arrow-down-alt2', label: __('Move down', 'nex-forms'), disabled: i === form.fields.length - 1, onClick: function () { props.onMove(i, i + 2); } }),
					h(C.Button, { size: 'small', icon: 'admin-page', label: __('Duplicate', 'nex-forms'), onClick: function () { props.onDuplicate(i); } }),
					h(C.Button, { size: 'small', icon: 'trash', isDestructive: true, label: __('Delete', 'nex-forms'), onClick: function () { props.onDelete(i); } })
				),
				h(PreviewControl, { field: f })
				)
			);
		});

		return h('div', { className: 'nfb-canvas-wrap' + (dark ? ' is-dark' : '') },
			h('div', { className: 'nfb-canvas-bar' },
				h('span', null, __('Live preview', 'nex-forms')),
				h('div', { className: 'nfb-seg', role: 'group', 'aria-label': __('Preview background', 'nex-forms') },
					h('button', { type: 'button', className: !dark ? 'is-active' : '', onClick: function () { setDark(false); } }, __('Light', 'nex-forms')),
					h('button', { type: 'button', className: dark ? 'is-active' : '', onClick: function () { setDark(true); } }, __('Dark', 'nex-forms'))
				)
			),
			h('div', {
				className: 'nfb-canvas',
				onDragOver: function (e) { e.preventDefault(); if (e.target === e.currentTarget) setOver(form.fields.length); },
				onDragLeave: function (e) { if (!e.currentTarget.contains(e.relatedTarget)) setOver(null); },
				onDrop: onDrop,
				onClick: function (e) { if (e.target === e.currentTarget) props.onSelect(null); }
			},
				h('div', { className: 'nf nf--' + d.preset + ' nf--' + d.size + (d.button_full ? ' nf--btn-full' : ''), style: style },
					form.fields.length
						? h('div', { className: 'nf-grid' }, items, over === form.fields.length ? h('div', { className: 'nfb-drop nf-w-full' }) : null)
						: h('div', { className: 'nfb-canvas-empty' }, __('Drag a field here or click one on the left.', 'nex-forms')),
					form.settings.turnstile ? h('div', { className: 'nfb-turnstile-ph' + (BOOT.turnstileReady ? '' : ' is-off') }, h(Dashicon, { icon: 'shield' }), BOOT.turnstileReady ? __('Cloudflare Turnstile (shows only when a check is needed)', 'nex-forms') : __('Turnstile is on for this form but keys are missing in Settings.', 'nex-forms')) : null,
					h('div', { className: 'nf-actions' }, h('button', { type: 'button', className: 'nf-submit', tabIndex: -1 }, form.settings.submit_label || __('Send', 'nex-forms')))
				)
			)
		);
	}

	/* ---------- Inspector: field ---------- */

	function OptionsEditor(props) {
		var options = props.options || [];
		function set(i, key, v) {
			var next = options.map(function (o, j) { return j === i ? Object.assign({}, o, (function () { var x = {}; x[key] = v; return x; })()) : o; });
			props.onChange(next);
		}
		function move(i, d) {
			var next = options.slice();
			var j = i + d;
			if (j < 0 || j >= next.length) return;
			var tmp = next[i]; next[i] = next[j]; next[j] = tmp;
			props.onChange(next);
		}
		return h('div', { className: 'nfb-options' },
			h('div', { className: 'nfb-options-head' }, h('span', null, __('Label', 'nex-forms')), h('span', null, __('Value', 'nex-forms'))),
			options.map(function (o, i) {
				return h('div', { className: 'nfb-option', key: i },
					h('input', { type: 'text', value: o.label, 'aria-label': __('Option label', 'nex-forms'), onChange: function (e) {
						var label = e.target.value;
						var auto = !o.value || o.value === slug(o.label).replace(/_/g, '-');
						var next = options.slice();
						next[i] = { label: label, value: auto ? slug(label).replace(/_/g, '-') : o.value };
						props.onChange(next);
					} }),
					h('input', { type: 'text', value: o.value, 'aria-label': __('Option value', 'nex-forms'), onChange: function (e) { set(i, 'value', e.target.value); } }),
					h(C.Button, { size: 'small', icon: 'arrow-up-alt2', label: __('Move up', 'nex-forms'), disabled: i === 0, onClick: function () { move(i, -1); } }),
					h(C.Button, { size: 'small', icon: 'no-alt', label: __('Remove option', 'nex-forms'), onClick: function () { props.onChange(options.filter(function (_, j) { return j !== i; })); } })
				);
			}),
			h(C.Button, { variant: 'secondary', size: 'compact', icon: 'plus-alt2', onClick: function () {
				var n = options.length + 1;
				props.onChange(options.concat([{ label: sprintf(__('Option %d', 'nex-forms'), n), value: 'option-' + n }]));
			} }, __('Add option', 'nex-forms')),
			h('p', { className: 'nfb-hint' }, __('The label is shown to visitors and used in the email. The value is what is submitted.', 'nex-forms'))
		);
	}

	function num(v) { return v === null || v === undefined ? '' : String(v); }

	function FieldInspector(props) {
		var f = props.field;
		var set = function (patch) { props.onChange(Object.assign({}, f, patch)); };
		var t = f.type;
		var textual = ['text', 'email', 'url', 'textarea', 'phone', 'number'].indexOf(t) >= 0;
		var hasOptions = ['select', 'radio', 'checkboxes'].indexOf(t) >= 0;

		var rows = [];
		if (t === 'consent') {
			rows.push(h(C.TextareaControl, { key: 'label', label: __('Consent text', 'nex-forms'), help: __('Links allowed, e.g. <a href="/privacy">privacy policy</a>.', 'nex-forms'), value: f.label, rows: 3, onChange: function (v) { set({ label: v }); } }));
		} else {
			rows.push(h(C.TextControl, { key: 'label', label: __('Label', 'nex-forms'), value: f.label, onChange: function (v) {
				var patch = { label: v };
				if (!f._nameLocked) patch.name = uniqueName(props.fields, slug(v) || t, f.id);
				set(patch);
			} }));
		}
		rows.push(h(C.TextControl, { key: 'name', label: __('Field key', 'nex-forms'), help: __('Used in the email subject as {key} and in integrations. Letters, numbers and _ only.', 'nex-forms'), value: f.name, onChange: function (v) {
			set({ name: v.toLowerCase().replace(/[^a-z0-9_]/g, '_').slice(0, 40), _nameLocked: true });
		} }));
		if (textual || t === 'select') {
			rows.push(h(C.TextControl, { key: 'ph', label: t === 'select' ? __('Empty choice text', 'nex-forms') : __('Placeholder', 'nex-forms'), value: f.placeholder, onChange: function (v) { set({ placeholder: v }); } }));
		}
		if (t !== 'hidden') {
			rows.push(h(C.TextControl, { key: 'help', label: __('Help text', 'nex-forms'), value: f.help, onChange: function (v) { set({ help: v }); } }));
			rows.push(h(C.ToggleControl, { key: 'req', label: __('Required', 'nex-forms'), checked: !!f.required, onChange: function (v) { set({ required: v }); } }));
			rows.push(h('div', { key: 'w', className: 'nfb-control' },
				h('span', { className: 'nfb-control-label' }, __('Width', 'nex-forms')),
				h('div', { className: 'nfb-seg is-wide' }, [['full', __('Full', 'nex-forms')], ['half', '1/2'], ['third', '1/3']].map(function (w) {
					return h('button', { key: w[0], type: 'button', className: f.width === w[0] ? 'is-active' : '', onClick: function () { set({ width: w[0] }); } }, w[1]);
				})),
				h('p', { className: 'nfb-hint' }, __('Fields stack to full width on phones.', 'nex-forms'))
			));
		}
		if (hasOptions) {
			rows.push(h('div', { key: 'opts', className: 'nfb-control' }, h('span', { className: 'nfb-control-label' }, __('Options', 'nex-forms')), h(OptionsEditor, { options: f.options, onChange: function (o) { set({ options: o }); } })));
			rows.push(h(C.SelectControl, { key: 'def', label: __('Preselected', 'nex-forms'), value: f.default, options: [{ value: '', label: __('None', 'nex-forms') }].concat((f.options || []).map(function (o) { return { value: o.value, label: o.label }; })), onChange: function (v) { set({ default: v }); } }));
		}
		if (t === 'phone') {
			rows.push(h(C.SelectControl, { key: 'cc', label: __('Default country', 'nex-forms'), value: f.country, options: (BOOT.countries || []).map(function (c) { return { value: c[0], label: c[2] + ' ' + c[1] + ' (+' + c[3] + ')' }; }), onChange: function (v) { set({ country: v }); }, help: __('Visitors can pick another country. Numbers are sent in international format (+30…).', 'nex-forms') }));
		}
		if (t === 'text') {
			rows.push(h(C.SelectControl, { key: 'ac', label: __('Browser autofill', 'nex-forms'), value: f.autocomplete || '', options: AUTOCOMPLETE, onChange: function (v) { set({ autocomplete: v }); } }));
		}
		if (t === 'hidden' || t === 'text' || t === 'number' || t === 'date' || t === 'url') {
			rows.push(h(C.TextControl, { key: 'default', label: t === 'hidden' ? __('Value', 'nex-forms') : __('Default value', 'nex-forms'), type: t === 'date' ? 'date' : 'text', value: f.default, onChange: function (v) { set({ default: v }); }, help: t === 'hidden' ? __('Always sent with the form. Visitors cannot change it.', 'nex-forms') : undefined }));
		}
		if (t === 'textarea') {
			rows.push(h(C.RangeControl, { key: 'rows', label: __('Height (rows)', 'nex-forms'), min: 2, max: 20, value: f.rows || 5, onChange: function (v) { set({ rows: v }); } }));
		}

		var validation = [];
		if (['text', 'textarea', 'email', 'url'].indexOf(t) >= 0) {
			validation.push(h('div', { key: 'len', className: 'nfb-row2' },
				h(C.TextControl, { label: __('Min. characters', 'nex-forms'), type: 'number', min: 0, value: num(f.minlength), onChange: function (v) { set({ minlength: v === '' ? null : parseInt(v, 10) }); } }),
				h(C.TextControl, { label: __('Max. characters', 'nex-forms'), type: 'number', min: 1, value: num(f.maxlength), onChange: function (v) { set({ maxlength: v === '' ? null : parseInt(v, 10) }); } })
			));
		}
		if (t === 'number' || t === 'date') {
			validation.push(h('div', { key: 'range', className: 'nfb-row2' },
				h(C.TextControl, { label: __('Minimum', 'nex-forms'), type: t, value: num(f.min), onChange: function (v) { set({ min: v === '' ? null : v }); } }),
				h(C.TextControl, { label: __('Maximum', 'nex-forms'), type: t, value: num(f.max), onChange: function (v) { set({ max: v === '' ? null : v }); } })
			));
		}
		if (t === 'text') {
			var bad = false;
			if (f.pattern) { try { new RegExp('^(?:' + f.pattern + ')$', 'u'); } catch (e) { bad = true; } }
			validation.push(h(C.TextControl, { key: 'pat', label: __('Pattern (regular expression)', 'nex-forms'), value: f.pattern, onChange: function (v) { set({ pattern: v }); }, help: bad ? __('This pattern is not valid and will be removed on save.', 'nex-forms') : __('Optional. Example for a Greek VAT number: [0-9]{9}', 'nex-forms'), className: bad ? 'nfb-invalid' : '' }));
			if (f.pattern) validation.push(h(C.TextControl, { key: 'patmsg', label: __('Message when the pattern does not match', 'nex-forms'), value: f.pattern_message, onChange: function (v) { set({ pattern_message: v }); } }));
		}

		return h('div', { className: 'nfb-inspector-body' },
			h('div', { className: 'nfb-inspector-title' }, h(Dashicon, { icon: (TYPE_MAP[t] || {}).icon }), (TYPE_MAP[t] || {}).label),
			rows,
			validation.length ? h('div', { className: 'nfb-section' }, h('h3', null, __('Validation', 'nex-forms')), validation) : null
		);
	}

	/* ---------- Inspector: form / email / design ---------- */

	function FormSettings(props) {
		var s = props.form.settings;
		var set = function (patch) { props.onChange(Object.assign({}, s, patch)); };
		var form = props.form;
		return h('div', { className: 'nfb-inspector-body' },
			h(C.TextControl, {
				label: __('Form name (used in the shortcode)', 'nex-forms'),
				value: form.slug || '',
				placeholder: slug(form.title, '-') || 'contact',
				// Live: transliterate and allow a trailing hyphen while typing; the server trims on save.
				onChange: function (v) { props.onSlug((slug(v + 'x', '-').slice(0, -1) + (/[\s_-]$/.test(v) ? '-' : '')).replace(/-+$/, /[\s_-]$/.test(v) ? '-' : '').replace(/^-/, '')); },
				help: sprintf(__('Shortcode: [nexform name="%s"]. Latin letters, numbers and hyphens; must be unique. Old [nexform id="…"] shortcodes keep working.', 'nex-forms'), form.slug || slug(form.title, '-') || '…')
			}),
			h(C.TextControl, { label: __('Button text', 'nex-forms'), value: s.submit_label, onChange: function (v) { set({ submit_label: v }); } }),
			h(C.TextareaControl, { label: __('Success message', 'nex-forms'), value: s.success_message, rows: 3, onChange: function (v) { set({ success_message: v }); }, help: __('Shown in place of the form after sending. Basic HTML allowed.', 'nex-forms') }),
			h(C.TextControl, { label: __('Redirect after sending (optional)', 'nex-forms'), type: 'url', placeholder: 'https://', value: s.redirect_url, onChange: function (v) { set({ redirect_url: v }); }, help: __('Leave empty to show the success message.', 'nex-forms') }),
			h('div', { className: 'nfb-section' },
				h('h3', null, __('Spam protection', 'nex-forms')),
				h(C.ToggleControl, { label: __('Cloudflare Turnstile', 'nex-forms'), checked: !!s.turnstile, onChange: function (v) { set({ turnstile: v }); }, help: BOOT.turnstileReady ? __('Invisible for most visitors; a check appears only when needed.', 'nex-forms') : h('span', null, __('Add your site and secret keys first in ', 'nex-forms'), h('a', { href: BOOT.adminUrl + '?page=nexforms-settings' }, __('Settings', 'nex-forms')), '.') }),
				h('p', { className: 'nfb-hint' }, __('A hidden honeypot, a minimum fill time and a per-visitor rate limit are always on.', 'nex-forms'))
			)
		);
	}

	function EmailSettings(props) {
		var s = props.form.settings;
		var set = function (patch) { props.onChange(Object.assign({}, s, patch)); };
		var emailFields = props.form.fields.filter(function (f) { return f.type === 'email'; });
		var ts = useState(false), testing = ts[0], setTesting = ts[1];
		var tokens = ['{site_name}', '{form_title}'].concat(props.form.fields.filter(function (f) { return f.type !== 'consent'; }).map(function (f) { return '{' + f.name + '}'; }));

		return h('div', { className: 'nfb-inspector-body' },
			h(C.TextControl, { label: __('Send to', 'nex-forms'), value: s.email_to, placeholder: BOOT.adminEmail, onChange: function (v) { set({ email_to: v }); }, help: __('Separate several addresses with commas. Empty uses the site admin email.', 'nex-forms') }),
			h(C.TextControl, { label: __('Subject', 'nex-forms'), value: s.email_subject, onChange: function (v) { set({ email_subject: v }); } }),
			h('div', { className: 'nfb-tokens' }, tokens.map(function (tk) {
				return h('button', { type: 'button', key: tk, onClick: function () { set({ email_subject: (s.email_subject || '') + (s.email_subject ? ' ' : '') + tk }); } }, tk);
			})),
			h(C.SelectControl, { label: __('Reply-To', 'nex-forms'), value: s.reply_to_field, options: [{ value: '', label: emailFields.length ? __('First email field (automatic)', 'nex-forms') : __('No email field in this form', 'nex-forms') }].concat(emailFields.map(function (f) { return { value: f.name, label: f.label }; })), onChange: function (v) { set({ reply_to_field: v }); }, help: __('Hitting “Reply” in your inbox answers the visitor directly.', 'nex-forms') }),
			h('div', { className: 'nfb-section' },
				h('h3', null, __('Delivery', 'nex-forms')),
				h('p', { className: 'nfb-hint' }, __('Emails go through WordPress’ mailer, so FluentSMTP (or any SMTP plugin) handles sending and the From address. Submissions are not stored on the site.', 'nex-forms')),
				h(C.Button, { variant: 'secondary', isBusy: testing, disabled: testing, onClick: function () {
					setTesting(true);
					apiFetch({ path: API + '/test-email', method: 'POST', data: { to: s.email_to } })
						.then(function (r) { props.notify(r.sent ? sprintf(__('Test email accepted for %s.', 'nex-forms'), r.to) : __('The mailer rejected the test email. Check FluentSMTP logs.', 'nex-forms'), r.sent ? 'success' : 'error'); })
						.catch(function (e) { props.notify(errorText(e), 'error'); })
						.then(function () { setTesting(false); });
				} }, __('Send test email', 'nex-forms'))
			)
		);
	}

	function DesignSettings(props) {
		var d = props.form.design;
		var set = function (patch) { props.onChange(Object.assign({}, d, patch)); };
		return h('div', { className: 'nfb-inspector-body' },
			h('div', { className: 'nfb-control' },
				h('span', { className: 'nfb-control-label' }, __('Style', 'nex-forms')),
				h('div', { className: 'nfb-presets' }, PRESETS.map(function (p) {
					return h('button', { key: p.id, type: 'button', className: 'nfb-preset' + (d.preset === p.id ? ' is-active' : ''), 'aria-pressed': d.preset === p.id, onClick: function () { set({ preset: p.id }); } },
						h('span', { className: 'nfb-preset-demo nf nf--' + p.id, style: d.accent ? { '--nf-accent': d.accent } : undefined },
							h('span', { className: 'nf-input' }), h('span', { className: 'nf-submit' })
						),
						h('span', { className: 'nfb-preset-name' }, p.label)
					);
				})),
				d.preset === 'theme' ? h('p', { className: 'nfb-hint' }, __('Uses your theme’s own input and button styles. Best when the theme already styles forms well.', 'nex-forms')) : null
			),
			h('div', { className: 'nfb-control' },
				h('span', { className: 'nfb-control-label' }, __('Accent colour', 'nex-forms')),
				h('div', { className: 'nfb-swatches' },
					COLORS.map(function (c) {
						return h('button', { key: c.color, type: 'button', className: 'nfb-swatch' + ((d.accent || '').toLowerCase() === c.color ? ' is-active' : ''), style: { background: c.color }, 'aria-label': c.name, 'aria-pressed': (d.accent || '').toLowerCase() === c.color, onClick: function () { set({ accent: c.color }); } });
					}),
					h('label', { className: 'nfb-swatch nfb-swatch-custom', title: __('Custom colour', 'nex-forms'), style: d.accent && !COLORS.some(function (c) { return c.color === (d.accent || '').toLowerCase(); }) ? { background: d.accent } : undefined },
						h('input', { type: 'color', value: d.accent || '#2563eb', 'aria-label': __('Custom colour', 'nex-forms'), onChange: function (e) { set({ accent: e.target.value }); } })
					),
					d.accent ? h(C.Button, { size: 'small', variant: 'tertiary', onClick: function () { set({ accent: '' }); } }, __('Use theme colour', 'nex-forms')) : null
				),
				h('p', { className: 'nfb-hint' }, __('Empty follows the theme’s primary colour.', 'nex-forms'))
			),
			h(C.RangeControl, { label: __('Corner radius', 'nex-forms'), min: 0, max: 40, value: d.radius === null || d.radius === undefined || d.radius === '' ? undefined : Number(d.radius), allowReset: true, resetFallbackValue: undefined, onChange: function (v) { set({ radius: v === undefined ? null : v }); }, help: __('Reset to use the style’s default.', 'nex-forms') }),
			h('div', { className: 'nfb-control' },
				h('span', { className: 'nfb-control-label' }, __('Size', 'nex-forms')),
				h('div', { className: 'nfb-seg is-wide' }, [['sm', __('Compact', 'nex-forms')], ['md', __('Normal', 'nex-forms')], ['lg', __('Large', 'nex-forms')]].map(function (s) {
					return h('button', { key: s[0], type: 'button', className: d.size === s[0] ? 'is-active' : '', onClick: function () { set({ size: s[0] }); } }, s[1]);
				}))
			),
			h(C.ToggleControl, { label: __('Full-width button', 'nex-forms'), checked: !!d.button_full, onChange: function (v) { set({ button_full: v }); } }),
			h('p', { className: 'nfb-hint' }, __('Font and text colour always come from your theme, so the form fits each site.', 'nex-forms'))
		);
	}

	/* ---------- Editor ---------- */

	function Editor(props) {
		var fs = useState(null), form = fs[0], setForm = fs[1];
		var ss = useState(null), selected = ss[0], setSelected = ss[1];
		var ts = useState('form'), tab = ts[0], setTab = ts[1];
		var ds = useState(false), dirty = ds[0], setDirty = ds[1];
		var vs = useState(false), saving = vs[0], setSaving = vs[1];
		var ls = useState(null), loadError = ls[0], setLoadError = ls[1];
		var n = useNotice(), toast = n[0], notify = n[1];
		var formRef = useRef(null);
		var savedRef = useRef(null);
		formRef.current = form;

		useEffect(function () {
			apiFetch({ path: API + '/forms/' + props.id }).then(function (f) {
				f.fields.forEach(function (x) { x._nameLocked = true; });
				savedRef.current = { id: f.id, slug: f.slug };
				setForm(f);
			}, function (e) { setLoadError(errorText(e)); });
		}, [props.id]);

		useEffect(function () {
			var warn = function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } };
			window.addEventListener('beforeunload', warn);
			return function () { window.removeEventListener('beforeunload', warn); };
		}, [dirty]);

		var save = useCallback(function () {
			var current = formRef.current;
			if (!current) return;
			setSaving(true);
			apiFetch({ path: API + '/forms/' + props.id, method: 'PUT', data: current })
				.then(function (f) {
					f.fields.forEach(function (x) { x._nameLocked = true; });
					savedRef.current = { id: f.id, slug: f.slug };
					var wanted = current.slug;
					setForm(f);
					setDirty(false);
					notify(wanted && wanted !== f.slug ? sprintf(__('Form saved. The name “%1$s” was taken, so it is now “%2$s”.', 'nex-forms'), wanted, f.slug) : __('Form saved.', 'nex-forms'));
				})
				.catch(function (e) { notify(errorText(e), 'error'); })
				.then(function () { setSaving(false); });
		}, [props.id, notify]);

		useEffect(function () {
			var key = function (e) {
				if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); }
			};
			document.addEventListener('keydown', key);
			return function () { document.removeEventListener('keydown', key); };
		}, [save]);

		if (loadError) return h('div', { className: 'nfb-page' }, h(C.Notice, { status: 'error', isDismissible: false }, loadError), h(C.Button, { variant: 'link', onClick: function () { go({}); } }, __('← All forms', 'nex-forms')));
		if (!form) return h('div', { className: 'nfb-empty' }, h(C.Spinner));

		function update(patch) { setForm(Object.assign({}, form, patch)); setDirty(true); }
		function setFields(fields) { update({ fields: fields }); }

		function addField(type, index) {
			var f = newField(type, form.fields);
			var fields = form.fields.slice();
			if (index === undefined) {
				var sel = fields.findIndex(function (x) { return x.id === selected; });
				index = sel >= 0 ? sel + 1 : fields.length;
			}
			fields.splice(index, 0, f);
			setFields(fields);
			setSelected(f.id);
			setTab('field');
		}
		function moveField(from, to) {
			if (to < 0 || to > form.fields.length) return;
			var fields = form.fields.slice();
			var item = fields.splice(from, 1)[0];
			if (to > from) to--;
			fields.splice(to, 0, item);
			setFields(fields);
		}
		function duplicateField(i) {
			var f = clone(form.fields[i]);
			f.id = uid();
			f.name = uniqueName(form.fields, f.name);
			f._nameLocked = true;
			var fields = form.fields.slice();
			fields.splice(i + 1, 0, f);
			setFields(fields);
			setSelected(f.id);
		}
		function deleteField(i) {
			var fields = form.fields.filter(function (_, j) { return j !== i; });
			if (form.fields[i].id === selected) setSelected(null);
			setFields(fields);
		}

		var selField = form.fields.find(function (f) { return f.id === selected; });
		var activeTab = tab === 'field' && !selField ? 'form' : tab;

		var tabs = [
			['field', __('Field', 'nex-forms')],
			['form', __('Form', 'nex-forms')],
			['email', __('Email', 'nex-forms')],
			['design', __('Design', 'nex-forms')]
		];

		var inspector;
		if (activeTab === 'field') {
			inspector = h(FieldInspector, { key: selField.id, field: selField, fields: form.fields, onChange: function (nf) {
				setFields(form.fields.map(function (f) { return f.id === nf.id ? nf : f; }));
			} });
		} else if (activeTab === 'email') {
			inspector = h(EmailSettings, { form: form, notify: notify, onChange: function (s) { update({ settings: s }); } });
		} else if (activeTab === 'design') {
			inspector = h(DesignSettings, { form: form, onChange: function (d) { update({ design: d }); } });
		} else {
			inspector = h(FormSettings, { form: form, onChange: function (s) { update({ settings: s }); }, onSlug: function (v) { update({ slug: v }); } });
		}

		return h('div', { className: 'nfb-editor' },
			h('div', { className: 'nfb-topbar' },
				h(C.Button, { icon: 'arrow-left-alt2', label: __('All forms', 'nex-forms'), onClick: function () {
					// eslint-disable-next-line no-alert
					if (dirty && !window.confirm(__('You have unsaved changes. Leave anyway?', 'nex-forms'))) return;
					go({});
				} }),
				h('input', { className: 'nfb-title-input', value: form.title, 'aria-label': __('Form name', 'nex-forms'), onChange: function (e) { update({ title: e.target.value }); } }),
				h(CopyShortcode, { form: savedRef.current || form }),
				h('div', { className: 'nfb-topbar-spacer' }),
				h(C.ToggleControl, { className: 'nfb-publish', label: form.status === 'publish' ? __('Live', 'nex-forms') : __('Draft', 'nex-forms'), checked: form.status === 'publish', onChange: function (v) { update({ status: v ? 'publish' : 'draft' }); }, __nextHasNoMarginBottom: true }),
				h(C.Button, { variant: 'primary', onClick: save, isBusy: saving, disabled: saving || !dirty }, dirty ? __('Save', 'nex-forms') : __('Saved', 'nex-forms'))
			),
			h('div', { className: 'nfb-body' },
				h('aside', { className: 'nfb-palette', 'aria-label': __('Add field', 'nex-forms') },
					h('h2', null, __('Add field', 'nex-forms')),
					h('div', { className: 'nfb-palette-grid' }, TYPES.map(function (tp) {
						return h('button', {
							key: tp.type,
							type: 'button',
							className: 'nfb-palette-item',
							draggable: true,
							onDragStart: function (e) { e.dataTransfer.effectAllowed = 'copy'; e.dataTransfer.setData('application/x-nexforms-type', tp.type); },
							onClick: function () { addField(tp.type); }
						}, h(Dashicon, { icon: tp.icon }), h('span', null, tp.label));
					}))
				),
				h('main', { className: 'nfb-main' },
					h(Canvas, {
						form: form,
						selected: selected,
						onSelect: function (id) { setSelected(id); if (id) setTab('field'); },
						onAdd: addField,
						onMove: moveField,
						onDuplicate: duplicateField,
						onDelete: deleteField
					})
				),
				h('aside', { className: 'nfb-inspector' },
					h('div', { className: 'nfb-tabs', role: 'tablist' }, tabs.map(function (tb) {
						var disabled = tb[0] === 'field' && !selField;
						return h('button', { key: tb[0], type: 'button', role: 'tab', 'aria-selected': activeTab === tb[0], disabled: disabled, className: activeTab === tb[0] ? 'is-active' : '', onClick: function () { setTab(tb[0]); } }, tb[1]);
					})),
					h('div', { role: 'tabpanel' }, inspector)
				)
			),
			toast
		);
	}

	/* ---------- Settings screen ---------- */

	function SettingsScreen() {
		var ss = useState(null), s = ss[0], setS = ss[1];
		var sec = useState(''), secret = sec[0], setSecret = sec[1];
		var cl = useState(false), clear = cl[0], setClear = cl[1];
		var sv = useState(false), saving = sv[0], setSaving = sv[1];
		var tt = useState(''), testTo = tt[0], setTestTo = tt[1];
		var tg = useState(false), testing = tg[0], setTesting = tg[1];
		var n = useNotice(), toast = n[0], notify = n[1];

		useEffect(function () { apiFetch({ path: API + '/settings' }).then(setS, function (e) { notify(errorText(e), 'error'); }); }, [notify]);
		if (!s) return h('div', { className: 'nfb-empty' }, h(C.Spinner));

		var set = function (patch) { setS(Object.assign({}, s, patch)); };
		var ready = s.turnstile_site_key && (s.turnstile_secret_set || secret) && !clear;

		function save() {
			setSaving(true);
			var data = Object.assign({}, s, { turnstile_secret: secret, turnstile_clear_secret: clear });
			apiFetch({ path: API + '/settings', method: 'POST', data: data })
				.then(function (r) { setS(r); setSecret(''); setClear(false); BOOT.turnstileReady = !!(r.turnstile_site_key && r.turnstile_secret_set); notify(__('Settings saved.', 'nex-forms')); })
				.catch(function (e) { notify(errorText(e), 'error'); })
				.then(function () { setSaving(false); });
		}

		return h('div', { className: 'nfb-page nfb-settings' },
			h('div', { className: 'nfb-pagehead' }, h('h1', null, __('Settings', 'nex-forms'))),

			h('section', { className: 'nfb-card nfb-card-pad' },
				h('div', { className: 'nfb-card-head' },
					h('h2', null, __('Cloudflare Turnstile', 'nex-forms')),
					h('span', { className: 'nfb-pill' + (ready ? ' is-on' : '') }, ready ? __('Ready', 'nex-forms') : __('Not configured', 'nex-forms'))
				),
				h('p', { className: 'nfb-hint' }, __('Create a widget in Cloudflare → Turnstile, allow this site’s hostname and paste both keys here. Turnstile can then be switched on per form.', 'nex-forms')),
				h('div', { className: 'nfb-row2' },
					h(C.TextControl, { label: __('Site key', 'nex-forms'), value: s.turnstile_site_key, onChange: function (v) { set({ turnstile_site_key: v.trim() }); } }),
					h(C.TextControl, { label: __('Secret key', 'nex-forms'), type: 'password', autoComplete: 'new-password', value: secret, placeholder: s.turnstile_secret_set ? __('Saved — leave empty to keep', 'nex-forms') : '', onChange: function (v) { setSecret(v.trim()); setClear(false); } })
				),
				s.turnstile_secret_set ? h(C.CheckboxControl, { label: __('Remove the saved secret key', 'nex-forms'), checked: clear, onChange: setClear }) : null
			),

			h('section', { className: 'nfb-card nfb-card-pad' },
				h('h2', null, __('Spam limits', 'nex-forms')),
				h('div', { className: 'nfb-row3' },
					h(C.TextControl, { label: __('Max. submissions per visitor', 'nex-forms'), type: 'number', min: 1, max: 100, value: String(s.rate_limit), onChange: function (v) { set({ rate_limit: v }); } }),
					h(C.TextControl, { label: __('…within minutes', 'nex-forms'), type: 'number', min: 1, max: 1440, value: String(s.rate_window), onChange: function (v) { set({ rate_window: v }); } }),
					h(C.TextControl, { label: __('Minimum seconds to fill', 'nex-forms'), type: 'number', min: 0, max: 30, value: String(s.min_seconds), onChange: function (v) { set({ min_seconds: v }); } })
				),
				h(C.ToggleControl, { label: __('Site is behind Cloudflare (use the visitor IP from CF-Connecting-IP)', 'nex-forms'), checked: !!s.trust_cloudflare, onChange: function (v) { set({ trust_cloudflare: v }); }, help: __('The header is only trusted when the request really comes from a Cloudflare address, so this is safe to leave on.', 'nex-forms') })
			),

			h('section', { className: 'nfb-card nfb-card-pad' },
				h('h2', null, __('Phone field', 'nex-forms')),
				h(C.TextareaControl, { label: __('Countries listed first', 'nex-forms'), rows: 3, value: (s.preferred_countries || []).join(', '), onChange: function (v) { set({ preferred_countries: v.toUpperCase().split(/[\s,]+/).filter(Boolean) }); }, help: __('Two-letter codes separated by commas, in the order they should appear. The field’s default country is always first; all other countries follow A–Z.', 'nex-forms') }),
				h('div', { className: 'nfb-inline-buttons' },
					h(C.Button, { variant: 'secondary', size: 'compact', onClick: function () { set({ preferred_countries: (BOOT.europe || []).slice() }); } }, sprintf(__('All European countries (%d)', 'nex-forms'), (BOOT.europe || []).length)),
					h(C.Button, { variant: 'tertiary', size: 'compact', onClick: function () { set({ preferred_countries: [] }); } }, __('None (A–Z only)', 'nex-forms'))
				)
			),

			h('section', { className: 'nfb-card nfb-card-pad' },
				h('h2', null, __('Email delivery', 'nex-forms')),
				h('p', { className: 'nfb-hint' }, __('NexForms sends with wp_mail(), so FluentSMTP decides the mail server and From address. Use this to check delivery.', 'nex-forms')),
				h('div', { className: 'nfb-inline' },
					h(C.TextControl, { label: __('Send a test to', 'nex-forms'), type: 'email', placeholder: BOOT.adminEmail, value: testTo, onChange: setTestTo, __nextHasNoMarginBottom: true }),
					h(C.Button, { variant: 'secondary', isBusy: testing, disabled: testing, onClick: function () {
						setTesting(true);
						apiFetch({ path: API + '/test-email', method: 'POST', data: { to: testTo } })
							.then(function (r) { notify(r.sent ? sprintf(__('Test email accepted for %s.', 'nex-forms'), r.to) : __('The mailer rejected the test email. Check FluentSMTP logs.', 'nex-forms'), r.sent ? 'success' : 'error'); })
							.catch(function (e) { notify(errorText(e), 'error'); })
							.then(function () { setTesting(false); });
					} }, __('Send test', 'nex-forms'))
				)
			),

			h('div', { className: 'nfb-savebar' }, h(C.Button, { variant: 'primary', onClick: save, isBusy: saving, disabled: saving }, __('Save settings', 'nex-forms'))),
			toast
		);
	}

	/* ---------- App ---------- */

	function App(props) {
		var rs = useState(formParam()), formId = rs[0], setFormId = rs[1];
		useEffect(function () {
			var on = function () { setFormId(formParam()); };
			window.addEventListener('popstate', on);
			return function () { window.removeEventListener('popstate', on); };
		}, []);
		if (props.view === 'settings') return h(SettingsScreen);
		return formId ? h(Editor, { key: formId, id: parseInt(formId, 10) }) : h(FormsList);
	}

	function mount() {
		var root = document.getElementById('nexforms-app');
		if (!root) return;
		var app = h(App, { view: root.getAttribute('data-view') });
		if (el.createRoot) el.createRoot(root).render(app);
		else el.render(app, root);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
	else mount();
})(window.wp);
