/**
 * Visual inline editing inside the section preview.
 * Progressive enhancement over section-preview.js: listens for its
 * 'rmd:preview-loaded' event and, on editable row previews, makes the
 * server-annotated .rmd-edit spans contenteditable and mirrors every change
 * into the real ACF inputs behind the modal. Images open the media library.
 *
 * Identity: values are written into the row ELEMENT handed over by the event
 * (detail.rowEl) — never into "the row at index N", which points at the wrong
 * section as soon as an unsaved row is inserted above. detail.rowIndex is the
 * row's true DB index and is used ONLY for the draft save (-1 for new rows).
 *
 * Closing the modal is a CANCEL: values written into the fields since the last
 * save are rolled back (after a confirm), so an Update clicked later can never
 * publish an edit the editor didn't keep. « Enregistrer » sets the new baseline.
 *
 * NEW rows preview as an editable demo placeholder; its values (#rmd-demo-fill)
 * are copied once into the row's empty fields so the form matches the preview
 * and the normal « Mettre à jour » button publishes exactly what's on screen.
 * Picker demos carry no annotations and are untouched. If this file fails to
 * load, the preview simply stays read-only.
 */
(function () {
	'use strict';

	var cfg = window.rmdSectionEdit || {};
	var i18n = cfg.i18n || {};

	// Per-preview edit state: the row element being edited, its DB index (for
	// the draft save only), the values changed since load/save (dirty) and what
	// those fields held BEFORE the first edit (before) — closing the preview
	// without saving restores them.
	// fillMode: this preview should CREATE + fill the row's fields and Save via a
	// full page save — true for a brand-new row AND a saved-but-empty row (both
	// carry #rmd-demo-fill). isNew stays "unstamped row / no DB index".
	var state = { rowEl: null, dbRow: -1, dirty: {}, before: {}, saveBtn: null, isNew: false, fillMode: false };

	// Parent-page style: the preview modal steps fully aside while the media
	// library is open (our modal is z-index 999999, above WP media's 160000).
	(function injectParentStyle() {
		if (document.getElementById('rmd-edit-parent-style')) return;
		var s = document.createElement('style');
		s.id = 'rmd-edit-parent-style';
		s.textContent = '.rmd-sp-modal.rmd-sp-picking{display:none!important;}';
		(document.head || document.documentElement).appendChild(s);
	})();

	// Styles injected INTO the preview iframe (never the parent page).
	var IFRAME_STYLE = [
		/* the preview disables links (a{pointer-events:none}) — editable spans
		   inside links (e.g. the CTA label) must stay clickable */
		/* caret-color: gradient text (.grad-attention) paints its text transparent,
		   which makes the default caret invisible — force the editing blue so the
		   blinking cursor shows on EVERY surface (gradient, white, dark navy). */
		'.rmd-edit { pointer-events: auto; cursor: text; border-radius: 3px; box-shadow: none; transition: box-shadow .15s ease; caret-color: #3943FF; }',
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
	 * Walk 'tags.2.label' from the section row ELEMENT down to the concrete ACF
	 * input. Name segment → sub-field; numeric segment → repeater row. The row
	 * element (not an index) is the identity — an unsaved insert or reorder
	 * elsewhere in the list can never point this at another section's fields.
	 */
	function resolveInput(rowEl, path) {
		if (!rowEl || !document.body.contains(rowEl)) return null;

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
		// Two passes so an ACF true_false resolves to its CHECKBOX, not the
		// hidden "0" input rendered before it; image fields still fall back to
		// their hidden attachment-ID input.
		var input = cur.querySelector('input[type="text"], textarea, select, input[type="checkbox"], input[type="radio"], input[type="number"], input[type="url"], input[type="email"]');
		return input || cur.querySelector('input[type="hidden"]');
	}

	function writeBack(rowEl, path, value) {
		var input = resolveInput(rowEl, path);
		if (!input) return false;
		if ('checkbox' === input.type) {
			input.checked = !!parseInt(value, 10);
		} else if ('radio' === input.type) {
			var group = input.closest('.acf-input') || input.parentNode;
			var match = null;
			group.querySelectorAll('input[type="radio"]').forEach(function (r) {
				if (String(r.value) === String(value)) match = r;
			});
			if (!match) return false;
			input = match;
			input.checked = true;
		} else {
			input.value = value;
		}
		// Native bubbling events: ACF (jQuery-delegated) sees them and marks dirty.
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
		return true;
	}

	/** Add ONE row to a repeater field element. Prefers ACF's own JS API
	    (acf.getField(...).add() — reliable naming/init and honours min/max);
	    falls back to clicking the footer "add row" button. Returns true if it
	    attempted an add. */
	function addRepeaterRow(repeaterFieldEl) {
		if (window.acf && window.jQuery && typeof acf.getField === 'function') {
			try {
				var f = acf.getField(window.jQuery(repeaterFieldEl));
				if (f && typeof f.add === 'function') { f.add(); return true; }
			} catch (e) { /* fall through to the button */ }
		}
		var buttons = repeaterFieldEl.querySelectorAll('.acf-actions [data-event="add-row"]');
		for (var b = 0; b < buttons.length; b++) {
			if (buttons[b].closest('.acf-field') === repeaterFieldEl) { buttons[b].click(); return true; }
		}
		return false;
	}

	/** Create missing repeater rows along 'steps.2.heading' so a demo with 4
	    steps fills all four (a fresh section starts at the field's min — often
	    0 or 1 row). Min/max rules and row init come from ACF. Gives up quietly
	    at the max or if a click made no progress. */
	function ensureRepeaterRows(rowEl, path) {
		var ctx = rowEl;
		var cur = null;
		var segs = String(path).split('.');
		for (var i = 0; i < segs.length; i++) {
			if (/^\d+$/.test(segs[i])) {
				if (!cur) return;
				var want = parseInt(segs[i], 10);
				var guard = 30;
				while (repeaterRows(cur).length <= want && guard-- > 0) {
					var before = repeaterRows(cur).length;
					if (!addRepeaterRow(cur)) return;          // no add mechanism
					if (repeaterRows(cur).length <= before) return; // hit max / no progress
				}
				ctx = repeaterRows(cur)[want];
				if (!ctx) return;
			} else {
				cur = subFieldIn(ctx, segs[i]);
				if (!cur) return;
			}
		}
	}

	// ── Modal messaging (reuses the preview modal's own slots) ───────────────
	function showEditedHint() {
		// A new section publishes via Update (its fields aren't in the DB yet, so
		// the per-section draft save can't target them); a saved row can draft.
		setHintText(state.fillMode ? (i18n.newSectionHint || '') : (i18n.editedHint || ''));
	}

	function setHintText(text) {
		var hint = document.querySelector('.rmd-sp-modal .rmd-sp-hint');
		if (!hint) return;
		var t = hint.querySelector('.rmd-sp-hint-text');
		if (t) t.textContent = text || '';
		hint.style.display = text ? 'flex' : 'none';
	}

	// ── Per-section save button (next to Fermer / Rafraîchir) ────────────────
	function currentPostId() {
		var sp = window.rmdSectionPreview || {};
		if (sp.postId) return sp.postId;
		var el = document.getElementById('post_ID');
		return el && el.value ? (parseInt(el.value, 10) || 0) : 0;
	}

	/** Remember a field's value the FIRST time this preview touches it, so the
	    edit can be undone. Later edits of the same path keep the original. */
	function rememberOriginal(rowEl, path) {
		if (!path || Object.prototype.hasOwnProperty.call(state.before, path)) return;
		var input = resolveInput(rowEl, path);
		if (!input) return;
		state.before[path] = ('checkbox' === input.type)
			? (input.checked ? '1' : '0')
			: String(null == input.value ? '' : input.value);
	}

	/** Undo every edit made since the preview opened (or since the last save):
	    the ACF fields go back to what they held, so nothing reaches the site. */
	function revertEdits() {
		if (state.rowEl && document.body.contains(state.rowEl)) {
			Object.keys(state.before).forEach(function (path) {
				try { writeBack(state.rowEl, path, state.before[path]); } catch (err) { /* field gone — skip */ }
			});
		}
		state.before = {};
		state.dirty = {};
		if (state.saveBtn) {
			state.saveBtn.disabled = true;
			state.saveBtn.textContent = i18n.save || 'Enregistrer';
		}
	}

	function markDirty(path, value) {
		state.dirty[path] = value;
		if (state.saveBtn) {
			state.saveBtn.disabled = false;
			state.saveBtn.textContent = i18n.save || 'Enregistrer';
		}
	}

	function ensureSaveButton() {
		if (state.saveBtn && document.body.contains(state.saveBtn)) return state.saveBtn;
		var foot = document.querySelector('.rmd-sp-modal .rmd-sp-foot');
		if (!foot) return null;
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'button button-primary rmd-sp-save';
		btn.textContent = i18n.save || 'Enregistrer';
		btn.disabled = true;
		btn.style.display = 'none';
		btn.addEventListener('click', doSave);
		// Between « Rafraîchir » and « Insérer » (the latter is hidden on rows).
		foot.insertBefore(btn, foot.querySelector('[data-insert]'));
		state.saveBtn = btn;
		return btn;
	}

	/** Save the whole post (block editor — case_study is show_in_rest). Classic
	    editor: click the real Publish/Update button. Returns true if triggered. */
	function savePostFromModal() {
		try {
			var d = window.wp && wp.data && wp.data.dispatch('core/editor');
			if (d && typeof d.savePost === 'function') { d.savePost(); return true; }
		} catch (e) { /* not the block editor */ }
		var btn = document.getElementById('publish') || document.getElementById('save-post');
		if (btn) { btn.click(); return true; }
		return false;
	}

	/** Call cb(true) once the post — AND its ACF metaboxes — finish saving, or
	    cb(false) if the save request failed. Falls back to cb(true) when the
	    block-editor store isn't present. */
	function watchSaveResult(cb) {
		if (!(window.wp && wp.data && typeof wp.data.subscribe === 'function')) { cb(true); return; }
		var ed = wp.data.select('core/editor');
		if (!ed || typeof ed.isSavingPost !== 'function') { cb(true); return; }
		var was = false, unsub;
		var busy = function () {
			var e = wp.data.select('core/editor');
			var post = e.isSavingPost() && !e.isAutosavingPost();
			var ep = wp.data.select('core/edit-post');
			var meta = ep && typeof ep.isSavingMetaBoxes === 'function' && ep.isSavingMetaBoxes();
			return !!(post || meta);
		};
		unsub = wp.data.subscribe(function () {
			var now = busy();
			if (was && !now) {
				var e = wp.data.select('core/editor');
				var failed = typeof e.didPostSaveRequestFail === 'function' && e.didPostSaveRequestFail();
				if (unsub) unsub();
				cb(!failed);
			}
			was = now;
		});
	}

	function doSave() {
		var btn = state.saveBtn;
		if (!btn) return;
		var postId = currentPostId();
		if (!postId) return;

		// Fill mode = a new section OR a saved-but-empty one. Its new repeater rows
		// aren't in the database and a lightweight draft can't add them, so "Save"
		// here saves the page (same as Update). Once saved it becomes a normal
		// section: the per-section draft save below takes over on the next open.
		if (state.fillMode) {
			btn.disabled = true;
			btn.textContent = i18n.saving || '…';
			setHintText(i18n.savingSection || i18n.saving || '');
			if (!savePostFromModal()) {
				btn.disabled = false;
				btn.textContent = i18n.save || 'Enregistrer';
				setHintText(i18n.saveError || '');
				return;
			}
			watchSaveResult(function (ok) {
				// On success section-preview.js re-baselines the row and reloads the
				// preview → this handler re-runs and resets the button. The edits are
				// published now, so there is nothing left to undo.
				if (ok) { state.dirty = {}; state.before = {}; return; }
				btn.disabled = false;
				btn.textContent = i18n.save || 'Enregistrer';
				setHintText(i18n.saveError || '');
			});
			return;
		}

		var paths = Object.keys(state.dirty);
		// dbRow is the row's TRUE index among saved rows — never its on-screen
		// position — so the draft can't land on another section's row.
		if (!paths.length || state.dbRow < 0) return;

		btn.disabled = true;
		btn.textContent = i18n.saving || '…';

		var body = new URLSearchParams({
			action: 'rmd_section_save',
			_wpnonce: cfg.nonce || '',
			post_id: String(postId),
			row: String(state.dbRow),
			changes: JSON.stringify(state.dirty)
		});

		fetch(cfg.ajaxUrl || window.ajaxurl || '', { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success && res.data && res.data.saved > 0) {
					// Kept as a draft → this is the new baseline; nothing to undo.
					state.dirty = {};
					state.before = {};
					btn.textContent = i18n.saved || 'OK';
					btn.disabled = true;
					setHintText(i18n.draftSaved || '');
				} else if (res && res.success) {
					// Nothing drafted (e.g. fields not in the DB yet) → use Update.
					btn.textContent = i18n.save || 'Save';
					btn.disabled = false;
					setHintText(i18n.newSectionHint || i18n.saveError || '');
				} else {
					btn.textContent = i18n.save || 'Enregistrer';
					btn.disabled = false;
					setHintText(i18n.saveError || 'Erreur');
				}
			})
			.catch(function () {
				btn.textContent = i18n.save || 'Enregistrer';
				btn.disabled = false;
				setHintText(i18n.saveError || 'Erreur');
			});
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

		rememberOriginal(ctx.rowEl, path); // before anything is typed — for the undo
		span.setAttribute('contenteditable', 'true');
		span.classList.add('is-editing');
		span.focus();

		var sync = function () {
			var value = mode === 'html' ? span.innerHTML : span.textContent;
			// Only record the edit if it actually landed in a field — never mark a
			// path dirty when there was nowhere to write it (keeps the dirty state
			// and the close-discard honest).
			if (writeBack(ctx.rowEl, path, value)) markDirty(path, value);
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

		// Our modal sits at z-index 999999 — above WP's media library (160000).
		// Rather than fight z-index, the preview steps aside while you pick, then
		// comes back with the new image. Clean, focused, no stacking surprises.
		var spModal = document.querySelector('.rmd-sp-modal');
		var stepAside = function () { if (spModal) spModal.classList.add('rmd-sp-picking'); };
		var comeBack = function () { if (spModal) spModal.classList.remove('rmd-sp-picking'); };

		var picker = wp.media({
			title: i18n.imageTitle || 'Image',
			button: { text: i18n.imageButton || 'OK' },
			library: { type: 'image' },
			multiple: false
		});

		// Preselect the current image so the library opens on it (a real workflow).
		picker.on('open', function () {
			stepAside();
			var currentId = parseInt(img.getAttribute('data-rmd-id') || '0', 10);
			if (currentId && wp.media.attachment) {
				var sel = picker.state().get('selection');
				var att = wp.media.attachment(currentId);
				att.fetch();
				sel.reset(att ? [att] : []);
			}
		});
		picker.on('close', comeBack);

		picker.on('select', function () {
			var att = picker.state().get('selection').first();
			if (!att) return;
			var a = att.toJSON();
			rememberOriginal(ctx.rowEl, img.getAttribute('data-rmd-path'));
			if (!writeBack(ctx.rowEl, img.getAttribute('data-rmd-path'), String(a.id))) return;
			markDirty(img.getAttribute('data-rmd-path'), String(a.id));

			// Live-swap the preview image (saved render still holds the old one).
			var size = a.sizes && (a.sizes.large || a.sizes.medium_large || a.sizes.full);
			var url = (size && size.url) || a.url;
			if (url) {
				img.removeAttribute('srcset');
				img.removeAttribute('sizes');
				img.src = url;
			}
			img.setAttribute('data-rmd-id', String(a.id)); // remember for next open
			showEditedHint();
		});

		picker.open();
	}

	// ── Prefill the ACF form with unpublished drafts on page load ────────────
	// « Enregistrer » stores drafts server-side ("row.path" → value). Filling
	// the real fields with them means the normal Update button publishes them —
	// no separate publish path. At page load the DOM order IS the DB order, so
	// the draft's row index maps safely to a row element here (and only here).
	function prefillDrafts() {
		var drafts = cfg.drafts || {};
		var field = sectionsField();
		if (!field) return;
		var rows = sectionRows(field);
		Object.keys(drafts).forEach(function (key) {
			var dot = key.indexOf('.');
			if (dot < 1) return;
			var row = parseInt(key.slice(0, dot), 10);
			var path = key.slice(dot + 1);
			if (isNaN(row) || !path || !rows[row]) return;
			try { writeBack(rows[row], path, String(drafts[key])); } catch (err) { /* field gone — skip */ }
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', prefillDrafts);
	} else {
		prefillDrafts();
	}

	// ── New-row placeholder → real field values ──────────────────────────────
	// The server emits #rmd-demo-fill ("path" → value) with the SAME values the
	// placeholder preview rendered — including the ones not editable in place
	// (bullet-list textareas, background/style selects) — so what the editor
	// sees is exactly what « Mettre à jour » will save. Runs ONCE per row: after
	// that the form is the source of truth and syncPreviewFromForm() pushes it
	// back into every reopened preview.
	/** True if the row already carries real content — any typed text/URL/number
	    or textarea value (ignoring ACF's clone templates). A blank picker-added
	    row is empty here; a DUPLICATE of a saved row is not. Guards prefillDemo
	    so a copy is never overwritten with placeholder values. */
	function rowHasContent(rowEl) {
		if (!rowEl) return false;
		var sel = 'input[type="text"],textarea,input[type="url"],input[type="email"],input[type="number"]';
		var inputs = rowEl.querySelectorAll(sel);
		for (var i = 0; i < inputs.length; i++) {
			if (inputs[i].closest('.acf-clone')) continue;
			if ('' !== String(inputs[i].value || '').trim()) return true;
		}
		// A set image counts as content too (its value is an attachment ID in the
		// field's hidden input) — so an "empty" row that only holds an image is
		// never demo-prefilled over.
		var imgs = rowEl.querySelectorAll('.acf-field[data-type="image"] input[type="hidden"]');
		for (var j = 0; j < imgs.length; j++) {
			if (imgs[j].closest('.acf-clone')) continue;
			if (parseInt(imgs[j].value, 10) > 0) return true;
		}
		return false;
	}

	function prefillDemo(doc, rowEl) {
		var holder = doc.getElementById('rmd-demo-fill');
		if (!holder) return;
		var map;
		try { map = JSON.parse(holder.textContent || '{}'); } catch (err) { return; }
		if (!map || 'object' !== typeof map) return;

		// Sorted keys create repeater rows in order (demo repeaters stay < 10).
		Object.keys(map).sort().forEach(function (path) {
			try {
				ensureRepeaterRows(rowEl, path);
				var input = resolveInput(rowEl, path);
				if (!input) return;
				// Styling/logic choices (select, true_false, radio) only ever hold
				// an ACF default here, so the demo's value always applies — that's
				// what makes the saved section match the preview (background=light,
				// columns=2…). A text field the editor already typed into wins.
				var isChoice = 'checkbox' === input.type || 'radio' === input.type || 'SELECT' === input.tagName;
				if (!isChoice && '' !== String(input.value || '').trim()) return;
				writeBack(rowEl, path, String(map[path]));
			} catch (err) { /* one odd field must never break the rest */ }
		});
	}

	/** Client-side mirror of rmd_inline_html(): keep only <b> <strong> <br>
	    <span class="…">, everything else becomes plain text. Field values may
	    have been typed by ANOTHER author — they must never execute here. */
	function sanitizeInline(html) {
		var tpl = document.createElement('template');
		tpl.innerHTML = String(html);
		var ALLOWED = { B: 1, STRONG: 1, BR: 1, SPAN: 1 };
		(function walk(node) {
			Array.prototype.slice.call(node.childNodes).forEach(function (child) {
				if (child.nodeType === 3) return;
				if (child.nodeType !== 1 || !ALLOWED[child.tagName]) {
					child.replaceWith(document.createTextNode(child.textContent || ''));
					return;
				}
				Array.prototype.slice.call(child.attributes).forEach(function (attr) {
					if (!('SPAN' === child.tagName && 'class' === attr.name)) child.removeAttribute(attr.name);
				});
				walk(child);
			});
		})(tpl.content);
		var div = document.createElement('div');
		div.appendChild(tpl.content);
		return div.innerHTML;
	}

	/** Push the row's CURRENT form values into the freshly loaded preview, so
	    the frame always reflects the form: typed edits, drafts prefilled at
	    load, a new row's placeholder after the editor changed some fields. */
	function syncPreviewFromForm(doc, rowEl) {
		doc.querySelectorAll('.rmd-edit').forEach(function (span) {
			var input = resolveInput(rowEl, span.getAttribute('data-rmd-path') || '');
			if (!input || 'checkbox' === input.type || 'radio' === input.type) return;
			var value = String(input.value || '');
			if ('' === value.trim()) return;
			if ('html' === span.getAttribute('data-rmd-mode')) {
				var clean = sanitizeInline(value);
				if (clean !== span.innerHTML) span.innerHTML = clean;
			} else if (value !== span.textContent) {
				span.textContent = value;
			}
		});
		doc.querySelectorAll('img[data-rmd-mode="image"]').forEach(function (img) {
			var input = resolveInput(rowEl, img.getAttribute('data-rmd-path') || '');
			if (!input) return;
			var id = parseInt(input.value, 10) || 0;
			if (!id || String(id) === img.getAttribute('data-rmd-id')) return;
			img.setAttribute('data-rmd-id', String(id));
			if (window.wp && wp.media && wp.media.attachment) {
				try {
					var att = wp.media.attachment(id);
					att.fetch().then(function () {
						var sizes = att.get('sizes');
						var size = sizes && (sizes.large || sizes.medium_large || sizes.full);
						var url = (size && size.url) || att.get('url');
						if (url) {
							img.removeAttribute('srcset');
							img.removeAttribute('sizes');
							img.src = url;
						}
					});
				} catch (err) { /* preview keeps the rendered image */ }
			}
		});
	}

	// ── Wire-up per preview load ─────────────────────────────────────────────
	document.addEventListener('rmd:preview-loaded', function (e) {
		var d = e.detail || {};

		// A load of ANOTHER row resets the edit state. Reloading the SAME row
		// (« Rafraîchir ») keeps it: those edits are still pending in the fields,
		// so they must stay undoable — and the save button must stay live.
		var sameRow = !!(state.rowEl && d.rowEl === state.rowEl);
		state.rowEl = d.rowEl || null;
		state.dbRow = typeof d.rowIndex === 'number' ? d.rowIndex : -1;
		if (!sameRow) {
			state.dirty = {};
			state.before = {};
		}
		state.isNew = !!d.isNewRow;
		state.fillMode = state.isNew; // refined from #rmd-demo-fill once the doc loads
		var hasEdits = Object.keys(state.dirty).length > 0;
		var saveBtn = ensureSaveButton();
		if (saveBtn) {
			saveBtn.style.display = 'none';
			saveBtn.disabled = !hasEdits;
			saveBtn.textContent = i18n.save || 'Save';
		}

		if (!d.isRow || !d.frame || !d.editable || !state.rowEl) return;

		var doc;
		try { doc = d.frame.contentDocument; } catch (err) { doc = null; }
		if (!doc || !doc.body) return;
		if (doc.getElementById('rmd-edit-style')) return; // this load is already wired
		if (!doc.querySelector('.rmd-edit, img[data-rmd-mode="image"]')) return;

		// #rmd-demo-fill is emitted for a NEW row and for a saved-but-EMPTY row —
		// both need their fields created + filled and Saved via a full page save
		// (a draft can't add repeater rows). Opt those into fill mode.
		if (doc.getElementById('rmd-demo-fill')) state.fillMode = true;

		// Fill mode: copy the placeholder into the row's empty fields (once), so the
		// preview and the form agree and Update publishes what's on screen.
		// A DUPLICATE — or any row that already holds content — is left as-is:
		// syncPreviewFromForm still shows its real values in the frame.
		if (state.fillMode && !state.rowEl.dataset.rmdPrefilled &&
			!state.rowEl.dataset.rmdDuplicate && !rowHasContent(state.rowEl)) {
			prefillDemo(doc, state.rowEl);
			state.rowEl.dataset.rmdPrefilled = '1';
		}
		syncPreviewFromForm(doc, state.rowEl);

		// Save button:
		//  • NEW row  → shown and ENABLED (the prefilled demo is ready to save;
		//    "Save" saves the page, which is the only way to keep a new section).
		//  • saved row → shown, enabled on the first edit (markDirty).
		//  • no DB index & not new (shouldn't happen) → hidden.
		if (saveBtn) {
			if (state.fillMode) {
				saveBtn.style.display = '';
				saveBtn.disabled = false;
				saveBtn.textContent = i18n.save || 'Enregistrer';
				setHintText(i18n.newSectionHint || '');
			} else {
				saveBtn.style.display = state.dbRow < 0 ? 'none' : '';
				if (hasEdits) { saveBtn.disabled = false; showEditedHint(); }
			}
		}

		var style = doc.createElement('style');
		style.id = 'rmd-edit-style';
		style.textContent = IFRAME_STYLE;
		doc.head.appendChild(style);

		showEditNote();

		var ctx = { doc: doc, rowEl: state.rowEl };
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

	// ── Closing the preview = cancel ─────────────────────────────────────────
	// Edits land in the ACF fields as you type (that's what makes « Mettre à
	// jour » publish exactly what the preview shows) — so closing without saving
	// has to put those fields BACK, or an unrelated Update later would publish
	// changes the editor never confirmed. section-preview.js fires this event
	// before it closes and honours preventDefault().
	document.addEventListener('rmd:preview-close', function (e) {
		if (!state.rowEl || !Object.keys(state.dirty).length) return;
		if (!window.confirm(i18n.discardConfirm || 'Discard your unsaved changes?')) {
			e.preventDefault(); // stay open — the editor wants to save first
			return;
		}
		revertEdits();
		setHintText('');
	});
})();
