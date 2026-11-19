/* NexForms front-end: validation, phone country picker, Turnstile and AJAX submit. No dependencies. */
(function () {
	'use strict';

	var t = window.nexformsI18n || {};
	var msg = function (key, arg) {
		var s = t[key] || key;
		return arg === undefined ? s : s.replace('%s', arg);
	};

	/* ---------- Turnstile (explicit render) ---------- */
	function renderTurnstile(root) {
		if (!window.turnstile) return;
		(root || document).querySelectorAll('.nf-turnstile:not([data-wid])').forEach(function (el) {
			var wid = window.turnstile.render(el, {
				sitekey: el.getAttribute('data-sitekey'),
				action: 'nexforms',
				theme: 'auto',
				size: 'flexible',
				appearance: 'interaction-only',
				'response-field-name': 'cf-turnstile-response',
				callback: function () { clearStatus(el.closest('form')); }
			});
			el.setAttribute('data-wid', wid);
		});
	}
	window.nexformsTurnstileReady = function () { renderTurnstile(); };

	function resetTurnstile(form) {
		var el = form.querySelector('.nf-turnstile[data-wid]');
		if (el && window.turnstile) window.turnstile.reset(el.getAttribute('data-wid'));
	}

	/* ---------- Validation ---------- */
	function control(wrap) {
		return wrap.querySelector('.nf-input, input[type="checkbox"], input[type="radio"]');
	}

	function setError(wrap, text) {
		var err = wrap.querySelector('.nf-error');
		var input = control(wrap);
		if (text) {
			wrap.classList.add('nf-invalid');
			if (err) { err.textContent = text; err.hidden = false; }
			if (input) input.setAttribute('aria-invalid', 'true');
		} else {
			wrap.classList.remove('nf-invalid');
			if (err) { err.textContent = ''; err.hidden = true; }
			if (input) input.removeAttribute('aria-invalid');
		}
	}

	function phoneValid(value) {
		var v = value.trim();
		if (!/^\+?[0-9\s().\-\/]+$/.test(v)) return false;
		var digits = v.replace(/\D/g, '');
		return digits.length >= 6 && digits.length <= 15;
	}

	function check(wrap) {
		var type = wrap.getAttribute('data-type');
		var el, v;

		if (type === 'radio' || type === 'checkboxes') {
			var fs = wrap.querySelector('.nf-fieldset');
			var any = wrap.querySelector('input:checked');
			if (fs && fs.hasAttribute('data-required') && !any) return msg(type === 'radio' ? 'choose' : 'required');
			return '';
		}
		if (type === 'consent') {
			el = wrap.querySelector('input');
			return el.required && !el.checked ? msg('required') : '';
		}

		el = wrap.querySelector('.nf-input');
		if (!el) return '';
		v = el.value.trim();
		if (!v) return el.required ? msg(type === 'select' ? 'choose' : 'required') : '';

		if (type === 'phone') return phoneValid(v) ? '' : msg('phone');

		var vs = el.validity || {};
		if (vs.typeMismatch) return msg(type === 'email' ? 'email' : 'url');
		if (type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return msg('email');
		if (vs.badInput) return msg('number');
		if (vs.rangeUnderflow) return msg('min', el.min);
		if (vs.rangeOverflow) return msg('max', el.max);
		var len = Array.from(v).length;
		if (el.minLength > 0 && len < el.minLength) return msg('minlength', el.minLength);
		if (el.maxLength > 0 && len > el.maxLength) return msg('maxlength', el.maxLength);
		if (vs.patternMismatch) return el.getAttribute('data-pattern-message') || msg('pattern');
		return '';
	}

	function validateAll(form) {
		var first = null;
		form.querySelectorAll('.nf-field').forEach(function (wrap) {
			var e = check(wrap);
			setError(wrap, e);
			if (e && !first) first = wrap;
		});
		return first;
	}

	/* ---------- Status ---------- */
	function setStatus(form, text, kind, html) {
		var box = form.querySelector('.nf-status');
		if (!box) return;
		box.className = 'nf-status is-' + kind;
		if (html) box.innerHTML = text; else box.textContent = text;
		box.hidden = false;
		return box;
	}
	function clearStatus(form) {
		var box = form && form.querySelector('.nf-status');
		if (box && box.classList.contains('is-error')) { box.hidden = true; box.textContent = ''; }
	}

	/* ---------- Init ---------- */
	function init(form) {
		if (form.__nf) return;
		form.__nf = true;
		var cfg = {};
		try { cfg = JSON.parse(form.getAttribute('data-nf')) || {}; } catch (e) {}
		var started = Date.now();
		var busy = false;

		form.querySelectorAll('.nf-cc-select').forEach(function (sel) {
			var face = sel.parentNode.querySelector('.nf-cc-face');
			var sync = function () {
				var o = sel.options[sel.selectedIndex];
				if (o && face) face.textContent = o.getAttribute('data-face') || '';
			};
			sel.addEventListener('change', function () { sync(); var n = sel.closest('.nf-phone').querySelector('.nf-input'); if (n) n.focus(); });
			sync();
		});

		// Validate on blur once a field was touched, re-validate on input while invalid.
		form.addEventListener('focusout', function (e) {
			var wrap = e.target.closest && e.target.closest('.nf-field');
			if (!wrap || wrap.contains(e.relatedTarget)) return;
			if (e.target.value || wrap.classList.contains('nf-invalid')) setError(wrap, check(wrap));
		});
		var live = function (e) {
			var wrap = e.target.closest && e.target.closest('.nf-field');
			if (wrap && wrap.classList.contains('nf-invalid')) setError(wrap, check(wrap));
		};
		form.addEventListener('input', live);
		form.addEventListener('change', live);

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (busy) return;

			var bad = validateAll(form);
			if (bad) {
				setStatus(form, msg('fix'), 'error');
				var c = control(bad);
				if (c) c.focus();
				return;
			}
			var ts = form.querySelector('.nf-turnstile');
			if (ts && ts.hasAttribute('data-wid')) {
				var tok = form.querySelector('[name="cf-turnstile-response"]');
				if (!tok || !tok.value) { setStatus(form, msg('captcha'), 'error'); return; }
			}

			var data = new FormData(form);
			data.append('nf_t', String(Date.now() - started));
			data.append('nf_page', window.location.href.split('#')[0]);

			busy = true;
			form.classList.add('nf-busy');
			var btn = form.querySelector('.nf-submit');
			if (btn) btn.disabled = true;
			setStatus(form, msg('sending'), 'info');

			fetch(cfg.endpoint || form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } })
				.then(function (r) { return r.json().catch(function () { return { ok: false, message: msg('network') }; }); })
				.then(function (res) {
					if (res && res.ok) {
						if (res.redirect) { window.location.href = res.redirect; return; }
						form.classList.add('nf-done');
						var box = setStatus(form, res.message || '', 'success', true);
						box.setAttribute('tabindex', '-1');
						box.focus();
						form.dispatchEvent(new CustomEvent('nexforms:sent', { bubbles: true }));
						return;
					}
					if (res && res.errors) {
						var firstWrap = null;
						Object.keys(res.errors).forEach(function (name) {
							var wrap = form.querySelector('.nf-field[data-name="' + (window.CSS && CSS.escape ? CSS.escape(name) : name) + '"]');
							if (wrap) { setError(wrap, res.errors[name]); if (!firstWrap) firstWrap = wrap; }
						});
						if (firstWrap && control(firstWrap)) control(firstWrap).focus();
					}
					if (res && res.code === 'too_fast') started = Date.now() - (cfg.min || 0) * 1000;
					setStatus(form, (res && res.message) || msg('network'), 'error');
					resetTurnstile(form);
				})
				.catch(function () {
					setStatus(form, msg('network'), 'error');
					resetTurnstile(form);
				})
				.then(function () {
					busy = false;
					form.classList.remove('nf-busy');
					if (btn) btn.disabled = false;
				});
		});
	}

	function boot() {
		document.querySelectorAll('form.nf-form[data-nf]').forEach(init);
		renderTurnstile();
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
	window.nexforms = { init: init, boot: boot };
})();
