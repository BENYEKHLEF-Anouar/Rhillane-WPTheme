/**
 * Visual inline editing inside the section preview.
 * Progressive enhancement over section-preview.js: listens for its
 * 'rmd:preview-loaded' event and — for SAVED-row previews only — makes the
 * server-annotated .rmd-edit spans contenteditable and mirrors every change
 * into the real ACF inputs behind the modal. Images open the media library.
 * Saving stays 100% native: the user clicks « Mettre à jour ». Demo previews
 * carry no annotations and are untouched. If this file fails to load, the
 * preview simply stays read-only.
 */
(function () {
	'use strict';

	var cfg = window.rmdSectionEdit || {};
	var i18n = cfg.i18n || {};

	// Styles injected INTO the preview iframe (never the parent page).
	var IFRAME_STYLE = [
		/* the preview disables links (a{pointer-events:none}) — editable spans
		   inside links (e.g. the CTA label) must stay clickable */
		'.rmd-edit { pointer-events: auto; cursor: text; border-radius: 3px; box-shadow: none; transition: box-shadow .15s ease; }',
		'.rmd-edit:hover { box-shadow: 0 0 0 2px rgba(57, 67, 255, .35); }',
		'.rmd-edit.is-editing { outline: none; box-shadow: 0 0 0 2px #3943FF; background: rgba(57, 67, 255, .06); }',
		'img[data-rmd-mode="image"] { cursor: pointer; }',
		'img[data-rmd-mode="image"]:hover { box-shadow: 0 0 0 3px rgba(57, 67, 255, .5); }'
	].join('\n');

	// ── ACF DOM resolution (parent page) ─────────────────────────────────────
	function isRealRow(el) {
		return !!el && el.classList &&
			el.classList.contains('layout') &&
			!el.classList.contains('acf-clone') &&
			!(el.closest && el.closest('.clones'));
	}

	function sectionsField() {
		return document.querySelector('.acf-field-flexible-content[data-name="sections"]') ||
			document.querySelector('.acf-field-flexible-content');
	}

	/** Top-level section rows of the flexible field (never nested, never clones). */
	function sectionRows(field) {
		var out = [];
		field.querySelectorAll('.layout').forEach(function (el) {
			if (!isRealRow(el)) return;
			if (el.closest('.acf-field-flexible-content') !== field) return;
			out.push(el);
		});
		return out;
	}

	/** Sub-field by name belonging DIRECTLY to this row context (not nested rows). */
	function subFieldIn(rowEl, name) {
		var nodes = rowEl.querySelectorAll('.acf-field[data-name="' + name + '"]');
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].closest('.layout, .acf-row') === rowEl) return nodes[i];
		}
		return null;
	}

	/** Real rows of a repeater field element (its own rows, not nested ones). */
	function repeaterRows(fieldEl) {
		var out = [];
		fieldEl.querySelectorAll('.acf-row').forEach(function (r) {
			if (r.classList.contains('acf-clone')) return;
			if (r.closest('.acf-field') !== fieldEl) return;
			out.push(r);
		});
		return out;
	}

	/**
	 * Walk 'tags.2.label' from a section row down to the concrete ACF input.
	 * Name segment → sub-field; numeric segment → repeater row.
	 */
	function resolveInput(rowIndex, path) {
		var field = sectionsField();
		if (!field) return null;
		var rowEl = sectionRows(field)[rowIndex];
		if (!rowEl) return null;

		var ctx = rowEl;        // current row context (.layout or .acf-row)
		var cur = null;         // current .acf-field
		var segs = String(path).split('.');
		for (var i = 0; i < segs.length; i++) {
			if (/^\d+$/.test(segs[i])) {
				if (!cur) return null;
				ctx = repeaterRows(cur)[parseInt(segs[i], 10)];
				if (!ctx) return null;
			} else {
				cur = subFieldIn(ctx, segs[i]);
				if (!cur) return null;
			}
		}
		if (!cur) return null;
		return cur.querySelector('input[type="text"], textarea, input[type="hidden"], input[type="number"], input[type="url"], input[type="email"]');
	}

	function writeBack(rowIndex, path, value) {
		var input = resolveInput(rowIndex, path);
		if (!input) return false;
		input.value = value;
		// Native bubbling events: ACF (jQuery-delegated) sees them and marks dirty.
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
		return true;
	}

	// ── Modal messaging (reuses the preview modal's own slots) ───────────────
	function showEditedHint() {
		var hint = document.querySelector('.rmd-sp-modal .rmd-sp-hint');
		if (!hint) return;
		var t = hint.querySelector('.rmd-sp-hint-text');
		if (t && i18n.editedHint) t.textContent = i18n.editedHint;
		hint.style.display = 'flex';
	}

	function showEditNote() {
		var note = document.querySelector('.rmd-sp-modal .rmd-sp-note');
		if (note && i18n.editNote) note.textContent = i18n.editNote;
	}

	// ── Text editing ─────────────────────────────────────────────────────────
	function insertLineBreak(doc) {
		var sel = doc.getSelection();
		if (!sel || !sel.rangeCount) return;
		var range = sel.getRangeAt(0);
		range.deleteContents();
		var br = doc.createElement('br');
		range.insertNode(br);
		range.setStartAfter(br);
		range.collapse(true);
		sel.removeAllRanges();
		sel.addRange(range);
	}

	/** Update the other spans carrying the same path (a value shown twice). */
	function mirror(doc, span) {
		var path = span.getAttribute('data-rmd-path');
		var mode = span.getAttribute('data-rmd-mode');
		doc.querySelectorAll('.rmd-edit[data-rmd-path="' + path + '"]').forEach(function (other) {
			if (other === span) return;
			if (mode === 'html') { other.innerHTML = span.innerHTML; } else { other.textContent = span.textContent; }
		});
	}

	function startEdit(span, ctx) {
		if (span.isContentEditable) return;
		var mode = span.getAttribute('data-rmd-mode') || 'text';
		var path = span.getAttribute('data-rmd-path') || '';

		span.setAttribute('contenteditable', 'true');
		span.classList.add('is-editing');
		span.focus();

		var sync = function () {
			var value = mode === 'html' ? span.innerHTML : span.textContent;
			writeBack(ctx.rowIndex, path, value);
			mirror(ctx.doc, span);
			showEditedHint();
		};
		var onKey = function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				if (mode === 'html') { insertLineBreak(ctx.doc); sync(); } else { span.blur(); }
			} else if (e.key === 'Escape') {
				e.stopPropagation(); // finish the edit; don't close the modal
				span.blur();
			}
		};
		var onInput = function () { sync(); };
		var onBlur = function () {
			span.removeEventListener('keydown', onKey);
			span.removeEventListener('input', onInput);
			span.removeEventListener('blur', onBlur);
			span.removeAttribute('contenteditable');
			span.classList.remove('is-editing');
			sync();
		};
		span.addEventListener('keydown', onKey);
		span.addEventListener('input', onInput);
		span.addEventListener('blur', onBlur);
	}

	// ── Image swapping ───────────────────────────────────────────────────────
	function pickImage(img, ctx) {
		if (!window.wp || !wp.media) return;
		var picker = wp.media({
			title: i18n.imageTitle || 'Image',
			button: { text: i18n.imageButton || 'OK' },
			library: { type: 'image' },
			multiple: false
		});
		picker.on('select', function () {
			var att = picker.state().get('selection').first();
			if (!att) return;
			var a = att.toJSON();
			if (!writeBack(ctx.rowIndex, img.getAttribute('data-rmd-path'), String(a.id))) return;

			// Live-swap the preview image (saved render still holds the old one).
			var size = a.sizes && (a.sizes.large || a.sizes.medium_large || a.sizes.full);
			var url = (size && size.url) || a.url;
			if (url) {
				img.removeAttribute('srcset');
				img.removeAttribute('sizes');
				img.src = url;
			}
			showEditedHint();
		});
		picker.open();
	}

	// ── Wire-up per preview load ─────────────────────────────────────────────
	document.addEventListener('rmd:preview-loaded', function (e) {
		var d = e.detail || {};
		if (!d.isRow || !d.frame) return;

		var doc;
		try { doc = d.frame.contentDocument; } catch (err) { doc = null; }
		if (!doc || !doc.body) return;
		if (doc.getElementById('rmd-edit-style')) return; // this load is already wired
		if (!doc.querySelector('.rmd-edit, img[data-rmd-mode="image"]')) return;

		var style = doc.createElement('style');
		style.id = 'rmd-edit-style';
		style.textContent = IFRAME_STYLE;
		doc.head.appendChild(style);

		showEditNote();

		var ctx = { doc: doc, rowIndex: d.rowIndex };
		// Capture phase: beat the theme's own lightbox/zoom handlers in main.js.
		doc.addEventListener('click', function (ev) {
			var span = ev.target.closest ? ev.target.closest('.rmd-edit') : null;
			if (span) {
				ev.preventDefault();
				ev.stopPropagation();
				startEdit(span, ctx);
				return;
			}
			var img = ev.target.closest ? ev.target.closest('img[data-rmd-mode="image"]') : null;
			if (img) {
				ev.preventDefault();
				ev.stopPropagation();
				pickImage(img, ctx);
			}
		}, true);
	});
})();
