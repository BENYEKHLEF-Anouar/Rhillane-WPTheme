/**
 * Case-study page builder — section picker modal + live section preview.
 * Ported from the AMD project and simplified: one CPT, so no per-page category
 * grouping.
 *
 *  1. "Add Section" → ACF's cramped fc-popup is hidden and replaced by our own
 *     centered card-grid modal (title + description + preview eye per layout).
 *     Picking a card replays the hidden ACF anchor's click, so insert position
 *     and min/max rules stay ACF's. The eye previews a generic DEMO (no row
 *     exists yet → the server seeds example content) with an "Insérer" button.
 *  2. An existing row's toolbar — an eye previews THAT row with its SAVED values.
 *     Unsaved / never-saved rows show an amber warning, never stale content.
 *
 * Sections are designed around 1280px, so the iframe renders at that width and
 * is transform:scale()d down to the modal. Config: window.rmdSectionPreview
 * (localised in inc/admin-ux.php). No jQuery; native DOM + MutationObserver.
 */
(function () {
	'use strict';

	var cfg = window.rmdSectionPreview || {};
	var layouts = cfg.layouts || {};
	var i18n = cfg.i18n || {};

	var RENDER_WIDTH = 1280;

	var modal = null;
	var frame = null;
	var stage = null;
	var current = null;      // { layout, anchor, rowIndex, isRow, hint }
	var lastFocused = null;
	var lastFieldKey = '';
	var lastAddBtn = null;   // the "+ Ajouter une section" button behind the open picker
	var lastAddAt = 0;       // when it was clicked — gates the popup re-anchor failsafe

	// ── Insert path (Add Section popup only) ─────────────────────────────────
	document.addEventListener('click', function (e) {
		var addBtn = e.target.closest('[data-name="add-layout"], .acf-fc-add');
		if (!addBtn) return;
		var field = addBtn.closest('.acf-field-flexible-content');
		lastFieldKey = field ? field.getAttribute('data-key') || '' : '';
		lastAddBtn = addBtn;
		lastAddAt = Date.now();
	}, true);

	function insertLayout(layoutName, anchor) {
		// Preferred: replay the click on ACF's own anchor — it handles insert
		// position and the min/max row rules.
		if (anchor && document.body.contains(anchor)) {
			anchor.click();
			return;
		}
		if (window.acf && lastFieldKey && typeof window.acf.getField === 'function') {
			var field = window.acf.getField(lastFieldKey);
			if (field && typeof field.add === 'function') field.add(layoutName);
		}
	}

	// ── Helpers ──────────────────────────────────────────────────────────────
	function esc(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : s;
		return d.innerHTML;
	}

	/** The post being edited, resolved at runtime (get_the_ID() can be 0). */
	function currentPostId() {
		if (cfg.postId) return cfg.postId;
		try {
			if (window.wp && wp.data && wp.data.select('core/editor')) {
				var id = wp.data.select('core/editor').getCurrentPostId();
				if (id) return id;
			}
		} catch (e) { /* not the block editor */ }
		var el = document.getElementById('post_ID');
		if (el && el.value) return parseInt(el.value, 10) || 0;
		return 0;
	}

	function previewUrl(layoutName, rowIndex) {
		var params = new URLSearchParams({
			action: 'rmd_section_preview',
			layout: layoutName,
			_wpnonce: cfg.nonce || ''
		});
		var postId = currentPostId();
		if (postId) params.set('post_id', postId);
		if (typeof rowIndex === 'number' && rowIndex >= 0) params.set('row', String(rowIndex));
		params.set('_ts', String(Date.now())); // cache-bust so Rafraîchir refetches
		return (cfg.ajaxUrl || '') + '?' + params.toString();
	}

	/** A real, saved/insertable row — never one of ACF's hidden clone templates. */
	function isRealRow(row) {
		return !!row && row.classList &&
			row.classList.contains('layout') &&
			!row.classList.contains('acf-clone') &&
			!(row.closest && row.closest('.clones'));
	}

	function rowsIn(container) {
		if (!container) return [];
		return Array.prototype.filter.call(container.children, isRealRow);
	}

	function rowContainerOf(field) {
		var all = field.querySelectorAll('.layout');
		for (var i = 0; i < all.length; i++) {
			var el = all[i];
			if (!isRealRow(el)) continue;
			if (el.closest('.acf-field-flexible-content') !== field) continue; // skip nested
			return el.parentNode;
		}
		return null;
	}

	function rowsOf(field) {
		return rowsIn(rowContainerOf(field));
	}

	/** A row's index = its position among its OWN sibling rows (never -1). */
	function rowIndexOf(row) {
		return rowsIn(row.parentNode).indexOf(row);
	}

	function savedRowCount(field) {
		var n = field.getAttribute('data-rmd-saved-rows');
		return n === null ? null : parseInt(n, 10);
	}

	/** Unsaved edits? Read the block editor's own dirty flag — no heuristics. */
	function postIsDirty() {
		try {
			var ed = window.wp && wp.data && wp.data.select('core/editor');
			if (ed && typeof ed.isEditedPostDirty === 'function') return !!ed.isEditedPostDirty();
		} catch (e) { /* not the block editor */ }
		return false;
	}

	// ── Modal ────────────────────────────────────────────────────────────────
	function buildModal() {
		if (modal) return modal;

		modal = document.createElement('div');
		modal.className = 'rmd-sp-modal';
		modal.setAttribute('aria-hidden', 'true');
		modal.innerHTML =
			'<div class="rmd-sp-backdrop" data-close></div>' +
			'<div class="rmd-sp-dialog" role="dialog" aria-modal="true" aria-labelledby="rmd-sp-title">' +
			'  <header class="rmd-sp-head">' +
			'    <h2 class="rmd-sp-title" id="rmd-sp-title"></h2>' +
			'    <button type="button" class="rmd-sp-close" data-close aria-label="' + esc(i18n.close || 'Fermer') + '">' +
			'      <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>' +
			'    </button>' +
			'  </header>' +
			'  <div class="rmd-sp-stage">' +
			'    <div class="rmd-sp-status"></div>' +
			'    <div class="rmd-sp-clip">' +
			'      <div class="rmd-sp-scaler"><iframe title="' + esc(i18n.previewTitle || 'Aperçu') + '" scrolling="no"></iframe></div>' +
			'    </div>' +
			'  </div>' +
			'  <div class="rmd-sp-body">' +
			'    <p class="rmd-sp-desc"></p>' +
			'    <p class="rmd-sp-note"></p>' +
			'    <p class="rmd-sp-hint"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <span class="rmd-sp-hint-text"></span></p>' +
			'  </div>' +
			'  <footer class="rmd-sp-foot">' +
			'    <button type="button" class="button" data-close>' + esc(i18n.close || 'Fermer') + '</button>' +
			'    <button type="button" class="button" data-refresh>' + esc(i18n.refresh || 'Rafraîchir') + '</button>' +
			'    <button type="button" class="button button-primary" data-insert>' + esc(i18n.insert || 'Insérer') + '</button>' +
			'  </footer>' +
			'</div>';

		// Clicks inside the modal must never reach ACF's document-level "close the
		// popup" handler — that destroys the anchor we insert with.
		modal.addEventListener('click', function (e) {
			e.stopPropagation();
			if (e.target.closest('[data-close]')) {
				closeModal();
			} else if (e.target.closest('[data-refresh]')) {
				reload();
			} else if (e.target.closest('[data-insert]')) {
				var layout = current ? current.layout : '';
				var anchor = current ? current.anchor : null; // captured: closeModal() clears it
				closeModal({ keepFocus: true });
				insertLayout(layout, anchor);
			}
		});

		document.body.appendChild(modal);
		frame = modal.querySelector('iframe');
		stage = modal.querySelector('.rmd-sp-stage');

		frame.addEventListener('load', onFrameLoad);
		frame.addEventListener('error', function () { setStatus(i18n.error || 'Erreur', true); });

		window.addEventListener('resize', fitFrame);
		return modal;
	}

	function setStatus(text, isError) {
		var el = modal.querySelector('.rmd-sp-status');
		el.textContent = text || '';
		el.classList.toggle('is-error', !!isError);
		el.style.display = text ? '' : 'none';
		modal.querySelector('.rmd-sp-clip').style.visibility = text ? 'hidden' : 'visible';
	}

	/** Scale the 1280px-wide render down to the modal's width. */
	function fitFrame() {
		if (!frame || !stage || !modal.classList.contains('is-open')) return;

		var clip = modal.querySelector('.rmd-sp-clip');
		var scaler = modal.querySelector('.rmd-sp-scaler');

		var available = clip.clientWidth || stage.clientWidth;
		var scale = Math.min(1, available / RENDER_WIDTH);

		scaler.style.transform = 'scale(' + scale + ')';
		scaler.style.width = RENDER_WIDTH + 'px';

		var h = parseInt(frame.dataset.contentHeight || '0', 10);
		if (h > 0) {
			frame.style.height = h + 'px';
			scaler.style.height = h + 'px';
			clip.style.height = Math.ceil(h * scale) + 'px';
		}
	}

	function onFrameLoad() {
		var doc;
		try {
			doc = frame.contentDocument; // same-origin
		} catch (err) {
			doc = null;
		}
		if (!doc || !doc.body) {
			setStatus(i18n.error || 'Erreur', true);
			return;
		}

		var measure = function () {
			// Measure the BODY's content box, not <html>: documentElement always
			// fills at least the iframe viewport, over-reporting for short sections.
			var h = doc.body.scrollHeight
				|| Math.ceil(doc.body.getBoundingClientRect().height)
				|| (doc.documentElement ? doc.documentElement.scrollHeight : 0);
			frame.dataset.contentHeight = String(h || 400);
			setStatus('');
			fitFrame();
		};

		measure();
		if (frame.contentWindow) {
			frame.contentWindow.addEventListener('load', measure);
		}
		setTimeout(measure, 350);
		setTimeout(measure, 1200);
	}

	function reload() {
		if (!current) return;
		frame.dataset.contentHeight = '';
		setStatus(i18n.loading || 'Chargement…');
		frame.src = previewUrl(current.layout, current.rowIndex);
	}

	function openModal(opts) {
		var info = layouts[opts.layout];
		if (!info) return;

		var isRow = !!opts.isRow;
		var rowIndex = typeof opts.rowIndex === 'number' ? opts.rowIndex : -1;

		current = {
			layout: opts.layout,
			anchor: opts.anchor || null,
			rowIndex: rowIndex,
			isRow: isRow,
			hint: opts.hint || ''
		};
		lastFocused = document.activeElement;
		buildModal();

		modal.querySelector('.rmd-sp-title').textContent = info.label || opts.layout;
		modal.querySelector('.rmd-sp-desc').textContent = info.desc || '';

		var note = modal.querySelector('.rmd-sp-note');
		note.textContent = isRow ? (i18n.rowPreview || '') : (i18n.demoNotice || '');

		var hint = modal.querySelector('.rmd-sp-hint');
		hint.querySelector('.rmd-sp-hint-text').textContent = current.hint;
		hint.style.display = current.hint ? 'flex' : 'none';

		// Nothing to insert when previewing a row that already exists.
		modal.querySelector('[data-insert]').style.display = isRow ? 'none' : '';
		modal.querySelector('[data-refresh]').style.display = isRow ? '' : 'none';

		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('rmd-sp-modal-open');

		modal.querySelector('.rmd-sp-close').focus();

		// A row preview needs a valid index; if we couldn't locate it, say so
		// plainly rather than loading a misleading demo.
		if (isRow && rowIndex < 0) {
			setStatus((i18n.error || 'Erreur') + ' — position introuvable (index -1)', true);
			return;
		}

		frame.dataset.contentHeight = '';
		frame.style.height = '400px';
		setStatus(i18n.loading || 'Chargement…');
		frame.src = previewUrl(current.layout, rowIndex);
	}

	function closeModal(opts) {
		if (!modal || !modal.classList.contains('is-open')) return;
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('rmd-sp-modal-open');
		frame.src = 'about:blank';

		if (!opts || !opts.keepFocus) {
			if (lastFocused && document.body.contains(lastFocused)) lastFocused.focus();
		}
		current = null;
		// A picker-origin preview holds ACF's hidden popup for its Insérer anchor;
		// the insert click (if any) fires synchronously before this delayed sweep.
		removeBackingPopup();
	}

	function makeEyeButton(labelText) {
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'rmd-sp-eye';
		btn.title = i18n.previewTitle || 'Aperçu';
		btn.setAttribute('aria-label', (i18n.previewTitle || 'Aperçu') + (labelText ? ' : ' + labelText : ''));
		btn.innerHTML = '<span class="dashicons dashicons-visibility" aria-hidden="true"></span>';
		return btn;
	}

	function makeRowEye(labelText) {
		var a = document.createElement('a');
		a.href = '#';
		// NO data-name and NO acf-js-tooltip: those pull the element into ACF's
		// own control-click delegation + tooltip system, which was breaking the
		// row collapse/expand. Our eye is a plain element with its own click
		// handler — ACF never sees it.
		a.className = 'rmd-sp-eye--row';
		a.title = i18n.previewTitle || 'Aperçu';
		a.setAttribute('aria-label', (i18n.previewTitle || 'Aperçu') + (labelText ? ' : ' + labelText : ''));
		a.innerHTML = '<span class="dashicons dashicons-visibility" aria-hidden="true"></span>';
		return a;
	}

	// ── Section picker modal ─────────────────────────────────────────────────
	// ACF's fc-popup is a cramped tooltip that lands wherever ACF drops it and
	// fights any restyling (seen live on 6.8: stuck corners, styles overridden).
	// So we don't decorate it anymore — we HIDE it and open our own centered
	// modal built from its anchors. Inserting replays the hidden anchor's click,
	// so insert position and ACF's min/max rules stay ACF's business.

	var picker = null;       // our modal
	var pickerPopup = null;  // the hidden ACF popup backing the open picker

	function pickerAnchor(name) {
		if (!pickerPopup || !document.body.contains(pickerPopup)) return null;
		return pickerPopup.querySelector('a[data-layout="' + name + '"]');
	}

	/** Sweep the hidden ACF popup once it's no longer needed. Delayed so ACF's
	    own insert/close handling (which also removes it) always wins the race. */
	function removeBackingPopup() {
		if (!pickerPopup) return;
		var pos = (pickerPopup.closest && pickerPopup.closest('.acf-tooltip')) || pickerPopup;
		pickerPopup = null;
		setTimeout(function () {
			if (pos.parentNode) pos.parentNode.removeChild(pos);
		}, 150);
	}

	function buildPicker() {
		if (picker) return picker;

		picker = document.createElement('div');
		picker.className = 'rmd-sp-picker';
		picker.setAttribute('aria-hidden', 'true');
		picker.innerHTML =
			'<div class="rmd-sp-backdrop" data-close></div>' +
			'<div class="rmd-sp-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="rmd-sp-picker-title">' +
			'  <header class="rmd-sp-head">' +
			'    <h2 class="rmd-sp-title" id="rmd-sp-picker-title"></h2>' +
			'    <button type="button" class="rmd-sp-close" data-close aria-label="' + esc(i18n.close || 'Fermer') + '">' +
			'      <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>' +
			'    </button>' +
			'  </header>' +
			'  <div class="rmd-sp-picker-grid"></div>' +
			'</div>';

		picker.addEventListener('click', function (e) {
			// Keep clicks away from ACF's document-level close handler and from
			// closeStalePopups — the hidden popup must survive while we're open.
			e.stopPropagation();

			if (e.target.closest('[data-close]')) {
				closePicker();
				return;
			}
			var eye = e.target.closest('.rmd-sp-eye');
			if (eye) {
				var name = eye.getAttribute('data-layout');
				closePicker({ keepPopup: true, keepFocus: true });
				openModal({ layout: name, anchor: pickerAnchor(name), isRow: false });
				return;
			}
			var card = e.target.closest('.rmd-sp-pick');
			if (card && !card.disabled) {
				var anchor = pickerAnchor(card.getAttribute('data-layout'));
				closePicker({ keepPopup: true, keepFocus: true });
				if (anchor) anchor.click(); // ACF inserts and closes its popup
				removeBackingPopup();       // …and we sweep if it didn't
			}
		});

		document.body.appendChild(picker);
		return picker;
	}

	function openPicker(popup) {
		pickerPopup = popup;
		buildPicker();

		// Title = the button that was clicked ("+ Ajouter une section" / "+ Add a
		// section"), falling back to the localized string for label-less buttons
		// (the small per-row "+").
		var title = (lastAddBtn && lastAddBtn.textContent.trim()) || i18n.addSection || 'Ajouter une section';
		picker.querySelector('.rmd-sp-title').textContent = title.replace(/^\+\s*/, '');

		var grid = picker.querySelector('.rmd-sp-picker-grid');
		grid.innerHTML = '';

		popup.querySelectorAll('a[data-layout]').forEach(function (anchor) {
			var name = anchor.getAttribute('data-layout');
			var info = layouts[name] || {};
			var label = (info.label || anchor.textContent || name).trim();
			var disabled = anchor.classList.contains('disabled');

			var wrap = document.createElement('div');
			wrap.className = 'rmd-sp-pick-wrap';

			var card = document.createElement('button');
			card.type = 'button';
			card.className = 'rmd-sp-pick';
			card.setAttribute('data-layout', name);
			if (disabled) {
				card.disabled = true; // min/max reached — mirror ACF's state
				card.title = anchor.getAttribute('title') || '';
			}
			card.innerHTML =
				'<span class="rmd-sp-pick-title">' + esc(label) + '</span>' +
				(info.desc ? '<span class="rmd-sp-pick-desc">' + esc(info.desc) + '</span>' : '');
			wrap.appendChild(card);

			if (layouts[name]) { // only known layouts have a preview endpoint
				var eye = makeEyeButton(label);
				eye.setAttribute('data-layout', name);
				wrap.appendChild(eye);
			}
			grid.appendChild(wrap);
		});

		picker.classList.add('is-open');
		picker.setAttribute('aria-hidden', 'false');
		var first = picker.querySelector('.rmd-sp-pick:not([disabled])');
		if (first) first.focus();
	}

	function closePicker(opts) {
		if (!picker || !picker.classList.contains('is-open')) return;
		picker.classList.remove('is-open');
		picker.setAttribute('aria-hidden', 'true');
		if (!opts || !opts.keepPopup) removeBackingPopup();
		if ((!opts || !opts.keepFocus) && lastAddBtn && document.body.contains(lastAddBtn)) {
			lastAddBtn.focus();
		}
	}

	/** An ACF layout picker appeared → hide it and show our modal instead.
	    The "more layout actions" menu (same .acf-fc-popup class, no data-layout
	    anchors) is left untouched. */
	function hijackPopup(popup) {
		if (!popup.querySelector('a[data-layout]')) return;
		if (popup.dataset.rmdHijacked) return;
		popup.dataset.rmdHijacked = '1';
		var pos = (popup.closest && popup.closest('.acf-tooltip')) || popup;
		pos.classList.add('rmd-sp-hijacked');
		openPicker(popup);
	}

	/** ACF normally closes the popup on any outside click. If its handler is
	    broken the popup stays stuck on screen — give ACF 150 ms, then remove it
	    ourselves. Clicks inside the popup/modal/add-button never close it, and a
	    popup deliberately kept behind the preview modal is left alone. */
	function closeStalePopups(target) {
		document.querySelectorAll('.acf-fc-popup').forEach(function (popup) {
			var pos = (popup.closest && popup.closest('.acf-tooltip')) || popup;
			// A hijacked popup backs our open picker/preview — never sweep it here.
			if (popup === pickerPopup) return;
			if (target && (pos.contains(target) ||
				(lastAddBtn && lastAddBtn.contains(target)))) return;
			setTimeout(function () {
				if (!pos.parentNode) return; // ACF already closed it
				if (popup === pickerPopup) return;
				if (document.body.classList.contains('rmd-sp-modal-open')) return;
				pos.parentNode.removeChild(pos);
			}, 150);
		});
	}

	document.addEventListener('click', function (e) {
		closeStalePopups(e.target);
	});

	// ── Existing rows ────────────────────────────────────────────────────────
	// The row eye (preview a saved section). Kept, but made ACF-inert: the eye
	// carries no data-name / acf-js-tooltip (see makeRowEye), so it can't disturb
	// ACF's control-click delegation or the row collapse/expand. Wrapped in
	// try/catch so a row-structure edge case can never break the editor.
	function decorateRow(row) {
		try {
			if (!row || row.dataset.rmdPreview) return;
			if (!isRealRow(row)) return; // never decorate a clone template

			var name = row.getAttribute('data-layout');
			if (!name || !layouts[name]) return;

			var controls = row.querySelector('.acf-fc-layout-controls') || row.querySelector('.acf-fc-layout-handle');
			if (!controls) return;

			row.dataset.rmdPreview = '1';

			var btn = makeRowEye(layouts[name].label);
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();

				var index = rowIndexOf(row);
				var field = row.closest('.acf-field-flexible-content');
				var saved = field ? savedRowCount(field) : null;

				var hint = '';
				if (saved !== null && index >= saved) {
					hint = i18n.newRowHint || '';   // row added, never saved
				} else if (postIsDirty()) {
					hint = i18n.dirtyHint || '';    // page has unsaved edits
				}

				openModal({ layout: name, rowIndex: index, hint: hint, isRow: true });
			});

			controls.insertBefore(btn, controls.firstChild);
		} catch (err) { /* never let row decoration break the editor */ }
	}

	/** The post was saved: re-baseline the saved-row count and refresh an open
	    row preview so the warning clears and the frame shows what was saved. */
	function onPostSaved() {
		document.querySelectorAll('.acf-field-flexible-content').forEach(function (field) {
			field.setAttribute('data-rmd-saved-rows', String(rowsOf(field).length));
		});

		if (modal && modal.classList.contains('is-open') && current && current.rowIndex >= 0) {
			current.hint = '';
			var hint = modal.querySelector('.rmd-sp-hint');
			hint.querySelector('.rmd-sp-hint-text').textContent = '';
			hint.style.display = 'none';
			reload();
		}
	}

	function watchSaves() {
		if (window.wp && wp.data && typeof wp.data.subscribe === 'function') {
			var wasSaving = false;
			wp.data.subscribe(function () {
				var editor = wp.data.select('core/editor');
				if (!editor || typeof editor.isSavingPost !== 'function') return;

				var saving = editor.isSavingPost() && !editor.isAutosavingPost();
				if (wasSaving && !saving) {
					var failed = typeof editor.didPostSaveRequestFail === 'function' && editor.didPostSaveRequestFail();
					if (!failed) onPostSaved();
				}
				wasSaving = saving;
			});
		}
		// Classic editor reloads on save; flags reset naturally.
	}

	function scan(root) {
		(root.querySelectorAll ? root : document).querySelectorAll('.acf-fc-popup').forEach(hijackPopup);
		(root.querySelectorAll ? root : document).querySelectorAll('.acf-field-flexible-content .layout').forEach(decorateRow);
	}

	function init() {
		document.querySelectorAll('.acf-field-flexible-content').forEach(function (field) {
			if (!field.hasAttribute('data-rmd-saved-rows')) {
				field.setAttribute('data-rmd-saved-rows', String(rowsOf(field).length));
			}
		});

		scan(document);
		watchSaves();

		new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				m.addedNodes.forEach(function (node) {
					if (node.nodeType !== 1) return;
					if (node.classList && node.classList.contains('acf-fc-popup')) {
						hijackPopup(node);
					} else if (node.classList && node.classList.contains('layout')) {
						decorateRow(node);
					} else if (node.querySelector) {
						scan(node);
					}
				});
			});
		}).observe(document.body, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		if (modal && modal.classList.contains('is-open')) {
			e.stopPropagation();
			closeModal();
		} else if (picker && picker.classList.contains('is-open')) {
			e.stopPropagation();
			closePicker();
		} else {
			closeStalePopups(null); // a stuck ACF popup should also close on Esc
		}
	});
})();
