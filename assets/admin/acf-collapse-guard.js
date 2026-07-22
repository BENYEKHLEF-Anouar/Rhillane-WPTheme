/**
 * ACF flexible-content editor failsafes (editor-only, standalone, no deps).
 *
 * The case-study editor kept losing row collapse/expand while an unrelated
 * plugin/extension script (mistral-api.js) errors on the page. Three layers:
 *
 * 1. renderLayout() → permanent no-op. ACF Pro fires it on every collapse to
 *    fetch a dynamic "layout title" over AJAX; its first line throws when the
 *    row's [acf_fc_layout] input is missing. Our layouts have static labels,
 *    so the call is pure overhead + a throw surface. We keep a one-time warning
 *    when the input IS missing, because that's a symptom worth diagnosing.
 *
 * 2. Collapse failsafe. ACF's own handler adds/removes `-collapsed` — but if it
 *    never runs (broken init) or runs twice (double-bound handlers cancel out),
 *    the click looks dead. We observe every collapse click in the capture phase
 *    and 150 ms later verify the row's state actually changed; if not, we toggle
 *    it ourselves. When ACF is healthy the check passes and we do nothing.
 *
 * 3. One-shot console diagnostic ~2.5 s after load (and window.rmdAcfDiag()):
 *    ACF version, flexible-content instances, per-row input state, any script
 *    URL matching /mistral/i, and the page's captured JS errors. A screenshot
 *    of that block is enough to identify the real culprit.
 */
(function () {
	'use strict';

	var warned = {};
	function warnOnce(key, msg) {
		if (warned[key] || !window.console || !console.warn) return;
		warned[key] = true;
		console.warn('[RMD] ' + msg);
	}

	/* ── 0. Error collector — must be registered before other scripts fail. ── */
	var pageErrors = [];
	window.addEventListener('error', function (e) {
		if (pageErrors.length >= 15) return;
		pageErrors.push({
			message: String(e.message || ''),
			source: (e.filename || '') + ':' + (e.lineno || 0)
		});
	});

	/* ── 1. renderLayout → no-op (static labels, no dynamic-title AJAX). ───── */
	function applyGuard() {
		if (!window.acf || typeof acf.getFieldType !== 'function') {
			return false;
		}

		var FC = acf.getFieldType('flexible_content') ||
			(acf.models && acf.models.FlexibleContentField);

		if (!FC || !FC.prototype || typeof FC.prototype.renderLayout !== 'function') {
			return false;
		}
		if (FC.prototype.rmdCollapseGuard) {
			return true; // already wrapped
		}

		FC.prototype.renderLayout = function ($layout) {
			// Diagnosis only — a row without a usable [acf_fc_layout] input means
			// something reorganised ACF's row markup; report it, never throw.
			try {
				var $input = ($layout && typeof $layout.children === 'function')
					? $layout.children('input')
					: null;
				if (!$input || !$input.length || !$input.attr('name')) {
					warnOnce('fc-input', 'A section row has no usable [acf_fc_layout] ' +
						'input — run rmdAcfDiag() in this console and report the output.');
				}
			} catch (e) { /* never break collapse */ }
		};

		FC.prototype.rmdCollapseGuard = true;
		return true;
	}

	if (!applyGuard()) {
		if (window.acf && typeof acf.addAction === 'function') {
			acf.addAction('prepare', applyGuard);
			acf.addAction('ready', applyGuard);
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', applyGuard);
		} else {
			applyGuard();
		}
	}

	/* ── 1.5 [acf_fc_layout] self-heal. ──────────────────────────────────────
	 * Live diagnosis showed rows whose hidden [acf_fc_layout] input is not a
	 * direct child anymore (something on the page restructures ACF's row markup).
	 * Without that input, saving the post silently corrupts the sections value.
	 * Heal: move a strayed input back to the row root, or rebuild it from the
	 * row's own sub-field names. Re-run before every save. */

	var healLog = [];

	function fcInput(row) {
		for (var i = 0; i < row.children.length; i++) {
			var c = row.children[i];
			if (c.tagName === 'INPUT' && /\[acf_fc_layout\]$/.test(c.getAttribute('name') || '')) {
				return c;
			}
		}
		return null;
	}

	function healRow(row) {
		if (!row || row.closest('.clones') || fcInput(row)) return false;

		// The input exists but was wrapped/moved deeper → move it back to the root.
		var stray = row.querySelector('input[name$="[acf_fc_layout]"]');
		if (stray) {
			row.insertBefore(stray, row.firstChild);
			healLog.push({ row: row.getAttribute('data-id'), how: 'moved-back-from-deeper' });
			return true;
		}

		// Fully gone → rebuild it. The name prefix comes from any sub-field input
		// of this same row (…[row-N]…), the value from the row's data-layout.
		var id = row.getAttribute('data-id');
		var layout = row.getAttribute('data-layout');
		if (!id || !layout) return false;

		var marker = '[' + id + ']';
		var els = row.querySelectorAll('input[name], textarea[name], select[name]');
		for (var j = 0; j < els.length; j++) {
			var n = els[j].getAttribute('name') || '';
			var at = n.indexOf(marker);
			if (at > 0) {
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = n.slice(0, at + marker.length) + '[acf_fc_layout]';
				input.value = layout;
				row.insertBefore(input, row.firstChild);
				healLog.push({ row: id, how: 'rebuilt', name: input.name });
				return true;
			}
		}
		healLog.push({ row: id, how: 'FAILED — no sub-field input to derive the name from' });
		return false;
	}

	function healAll() {
		var healed = 0;
		Array.prototype.forEach.call(
			document.querySelectorAll('.acf-field-flexible-content .acf-flexible-content > .values > .layout'),
			function (row) { if (healRow(row)) healed++; }
		);
		if (healed) {
			warnOnce('healed', healed + ' section row(s) were missing their [acf_fc_layout] ' +
				'input — restored so collapse and SAVING work. Something on this page is ' +
				'restructuring ACF rows; run rmdAcfDiag() and report the output.');
		}
		return healed;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			healAll();
			setTimeout(healAll, 1500); // catch late restructuring too
		});
	} else {
		healAll();
		setTimeout(healAll, 1500);
	}

	// A save with rows un-healed would drop their layout key — heal first, always.
	document.addEventListener('submit', function () { healAll(); }, true);

	/* ── 2. Collapse/expand failsafe. ────────────────────────────────────────
	 * Capture phase: we always see the click, even if another handler stops
	 * propagation. We never preventDefault and never act immediately — ACF gets
	 * 150 ms to do its job; only a no-op click is corrected. */

	function toggleRow(row, force) {
		var on = typeof force === 'boolean' ? force : !row.classList.contains('-collapsed');
		row.classList.toggle('-collapsed', on);
		// Let dependent UIs (tinymce/select2 sizing) react like a real collapse.
		try {
			if (window.acf && window.jQuery && typeof acf.doAction === 'function') {
				acf.doAction(on ? 'hide' : 'show', jQuery(row), 'collapse');
			}
		} catch (e) { /* cosmetic only */ }
	}

	document.addEventListener('click', function (e) {
		if (!e.target || !e.target.closest) return;

		var ctl = e.target.closest('[data-name="collapse-layout"]');
		if (ctl) {
			var row = ctl.closest('.layout');
			if (!row || row.closest('.clones')) return;
			healRow(row); // ACF's own handler runs after us — give it a sane row
			var before = row.classList.contains('-collapsed');
			setTimeout(function () {
				if (row.classList.contains('-collapsed') === before) {
					toggleRow(row);
					warnOnce('collapse-failsafe', 'ACF did not react to a collapse ' +
						'click — failsafe toggled the row. Run rmdAcfDiag() for details.');
				}
			}, 150);
			return;
		}

		var all = e.target.closest('.acf-fc-expand-all, .acf-fc-collapse-all');
		if (all) {
			var expand = all.classList.contains('acf-fc-expand-all');
			var scope = all.closest('.acf-field-flexible-content') || document;
			var rows = scope.querySelectorAll('.acf-flexible-content > .values > .layout');
			var state = function () {
				return Array.prototype.map.call(rows, function (r) {
					return r.classList.contains('-collapsed') ? 1 : 0;
				}).join('');
			};
			var beforeAll = state();
			setTimeout(function () {
				if (state() !== beforeAll) return; // ACF handled it
				var changed = false;
				Array.prototype.forEach.call(rows, function (r) {
					if (r.classList.contains('-collapsed') === expand) {
						toggleRow(r, !expand);
						changed = true;
					}
				});
				if (changed) {
					warnOnce('collapse-failsafe', 'ACF did not react to expand/collapse ' +
						'all — failsafe applied it. Run rmdAcfDiag() for details.');
				}
			}, 150);
		}
	}, true);

	/* ── 3. Diagnostic — window.rmdAcfDiag() + one shot after load. ────────── */
	function diag() {
		var rep = {
			acfLoaded: !!window.acf,
			acfVersion: (window.acf && acf.get) ? (acf.get('acf_version') || acf.get('version') || '?') : null,
			guardApplied: !!(window.acf && acf.getFieldType && acf.getFieldType('flexible_content') &&
				acf.getFieldType('flexible_content').prototype.rmdCollapseGuard),
			fcInstances: null,
			fcClickHandlers: null,
			rows: [],
			healLog: healLog,
			mistralScripts: [],
			pageErrors: pageErrors
		};

		try {
			if (window.acf && typeof acf.getFields === 'function') {
				rep.fcInstances = acf.getFields({ type: 'flexible_content' }).length;
			}
		} catch (e) { rep.fcInstances = 'error: ' + e.message; }

		try {
			var fieldEl = document.querySelector('.acf-field-flexible-content');
			if (fieldEl && window.jQuery && jQuery._data) {
				var ev = jQuery._data(fieldEl, 'events');
				rep.fcClickHandlers = ev && ev.click ? ev.click.length : 0;
			}
		} catch (e) { rep.fcClickHandlers = 'error: ' + e.message; }

		Array.prototype.forEach.call(
			document.querySelectorAll('.acf-field-flexible-content .acf-flexible-content > .values > .layout'),
			function (row) {
				var input = null;
				for (var i = 0; i < row.children.length; i++) {
					if (row.children[i].tagName === 'INPUT') { input = row.children[i]; break; }
				}
				rep.rows.push({
					layout: row.getAttribute('data-layout'),
					id: row.getAttribute('data-id'),
					firstChildInputName: input ? (input.getAttribute('name') || '(no name!)') : '(no direct-child input!)',
					collapsed: row.classList.contains('-collapsed'),
					// Direct children reveal WHO restructured the row (foreign wrappers
					// break both the fc_layout input and ACF's `>` collapse CSS).
					children: Array.prototype.map.call(row.children, function (c) {
						return c.tagName.toLowerCase() +
							(c.className ? '.' + String(c.className).trim().split(/\s+/).slice(0, 3).join('.') : '');
					}),
					htmlStart: row.outerHTML.slice(0, 400)
				});
			}
		);

		Array.prototype.forEach.call(document.scripts, function (s) {
			if (s.src && /mistral/i.test(s.src)) rep.mistralScripts.push(s.src);
		});
		if (!rep.mistralScripts.length) {
			rep.mistralScripts = '(none in the page — the mistral-api.js error likely ' +
				'comes from a BROWSER EXTENSION; retry in a private window with extensions off)';
		}

		return rep;
	}

	window.rmdAcfDiag = function () {
		var rep = diag();
		console.info('[RMD DIAG] ——— copy or screenshot everything below ———');
		console.info(JSON.stringify(rep, null, 2));
		return rep;
	};

	function autoDiag() { setTimeout(function () { window.rmdAcfDiag(); }, 2500); }
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', autoDiag);
	} else {
		autoDiag();
	}
})();
