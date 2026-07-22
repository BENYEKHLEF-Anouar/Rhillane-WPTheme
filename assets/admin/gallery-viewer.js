/**
 * In-field image gallery viewer (spec §9). For every flexible-content row that
 * holds images (ACF Gallery field, or a repeater with image sub-fields), adds a
 * thumbnail strip + count to the row and a "Voir la galerie" button that opens a
 * lightbox (grid → full view, prev/next, keyboard, Esc, "edit in media library").
 *
 * Read-first and additive: reads image data straight from ACF's own DOM, never
 * mutates a field. Native DOM + a debounced MutationObserver; decorators are
 * idempotent and null-safe (a row with no images gets no strip/button).
 */
(function () {
	'use strict';

	var cfg = window.rmdGalleryViewer || {};
	var i18n = cfg.i18n || {};

	var modal = null;
	var state = { images: [], index: 0 };
	var lastFocused = null;

	// ── Collect images in a row (image-field previews + gallery attachments) ──
	function collectImages(row) {
		var out = [];
		var seen = {};

		function push(src, alt, id) {
			if (!src) return;
			var key = (id || src);
			if (seen[key]) return;
			seen[key] = true;
			out.push({ src: src, alt: alt || '', id: id || 0 });
		}

		// ACF image fields (skip ones belonging to a deeper nested .layout).
		row.querySelectorAll('.acf-image-uploader').forEach(function (up) {
			if (up.closest('.layout') !== row) return;
			var img = up.querySelector('.image-wrap img, img.acf-image-uploader-image, img');
			if (!img) return;
			var src = img.getAttribute('src');
			if (!src) return;
			var hidden = up.querySelector('input[type="hidden"]');
			push(src, img.getAttribute('alt') || '', hidden && hidden.value ? parseInt(hidden.value, 10) : 0);
		});

		// ACF gallery fields.
		row.querySelectorAll('.acf-gallery-attachment').forEach(function (att) {
			if (att.closest('.layout') !== row) return;
			var img = att.querySelector('img');
			if (!img) return;
			push(img.getAttribute('src'), img.getAttribute('alt') || '', parseInt(att.getAttribute('data-id'), 10) || 0);
		});

		return out;
	}

	// ── Row decoration ────────────────────────────────────────────────────────
	function isRealRow(row) {
		return !!row && row.classList &&
			row.classList.contains('layout') &&
			!row.classList.contains('acf-clone') &&
			!(row.closest && row.closest('.clones'));
	}

	function buildRow(row) {
		if (!isRealRow(row)) return;

		// Rebuild from scratch so add/remove of images stays in sync.
		var old = row.querySelector(':scope > .rmd-gv-bar');
		if (old) old.parentNode.removeChild(old);

		var images = collectImages(row);
		if (!images.length) {
			row.removeAttribute('data-rmd-gv');
			return;
		}
		row.setAttribute('data-rmd-gv', '1');

		var handle = row.querySelector(':scope > .acf-fc-layout-handle');

		var bar = document.createElement('div');
		bar.className = 'rmd-gv-bar';

		var strip = document.createElement('div');
		strip.className = 'rmd-gv-strip';
		var max = 6;
		images.slice(0, max).forEach(function (im) {
			var t = document.createElement('img');
			t.className = 'rmd-gv-thumb';
			t.src = im.src;
			t.alt = '';
			t.loading = 'lazy';
			strip.appendChild(t);
		});
		if (images.length > max) {
			var more = document.createElement('span');
			more.className = 'rmd-gv-more';
			more.textContent = '+' + (images.length - max);
			strip.appendChild(more);
		}

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'rmd-gv-btn';
		btn.innerHTML = '<span class="dashicons dashicons-format-gallery" aria-hidden="true"></span><span>' +
			esc(i18n.view || 'Voir la galerie') + ' (' + images.length + ')</span>';
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			openGallery(images, 0);
		});

		strip.appendChild(btn);
		bar.appendChild(strip);

		if (handle && handle.parentNode === row) {
			handle.insertAdjacentElement('afterend', bar);
		} else {
			row.insertBefore(bar, row.firstChild);
		}
	}

	function esc(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : s;
		return d.innerHTML;
	}

	// ── Lightbox ──────────────────────────────────────────────────────────────
	function buildModal() {
		if (modal) return modal;
		modal = document.createElement('div');
		modal.className = 'rmd-gv-modal';
		modal.setAttribute('aria-hidden', 'true');
		modal.innerHTML =
			'<div class="rmd-gv-backdrop" data-close></div>' +
			'<div class="rmd-gv-dialog" role="dialog" aria-modal="true" aria-label="' + esc(i18n.title || 'Galerie') + '">' +
			'  <header class="rmd-gv-head">' +
			'    <h2 class="rmd-gv-title">' + esc(i18n.title || 'Galerie') + '</h2>' +
			'    <span class="rmd-gv-count"></span>' +
			'    <button type="button" class="rmd-gv-close" data-close aria-label="' + esc(i18n.close || 'Fermer') + '"><span class="dashicons dashicons-no-alt"></span></button>' +
			'  </header>' +
			'  <div class="rmd-gv-body">' +
			'    <div class="rmd-gv-grid"></div>' +
			'    <div class="rmd-gv-full">' +
			'      <div class="rmd-gv-stage">' +
			'        <button type="button" class="rmd-gv-nav rmd-gv-prev" data-prev aria-label="' + esc(i18n.prev || 'Précédente') + '"><span class="dashicons dashicons-arrow-left-alt2"></span></button>' +
			'        <img alt="">' +
			'        <button type="button" class="rmd-gv-nav rmd-gv-next" data-next aria-label="' + esc(i18n.next || 'Suivante') + '"><span class="dashicons dashicons-arrow-right-alt2"></span></button>' +
			'      </div>' +
			'      <p class="rmd-gv-cap"></p>' +
			'    </div>' +
			'  </div>' +
			'  <footer class="rmd-gv-foot">' +
			'    <button type="button" class="button" data-grid>' + esc(i18n.close || 'Fermer') + '</button>' +
			'    <button type="button" class="button button-primary" data-edit style="display:none">' + esc(i18n.edit || 'Modifier dans la médiathèque') + '</button>' +
			'  </footer>' +
			'</div>';

		modal.addEventListener('click', function (e) {
			e.stopPropagation();
			if (e.target.closest('[data-close]')) {
				closeModal();
			} else if (e.target.closest('[data-prev]')) {
				show(state.index - 1);
			} else if (e.target.closest('[data-next]')) {
				show(state.index + 1);
			} else if (e.target.closest('[data-grid]')) {
				if (modal.classList.contains('is-full')) { modal.classList.remove('is-full'); } else { closeModal(); }
			} else if (e.target.closest('[data-edit]')) {
				editInLibrary(state.images[state.index] && state.images[state.index].id);
			} else {
				var cell = e.target.closest('.rmd-gv-cell');
				if (cell) show(parseInt(cell.getAttribute('data-i'), 10) || 0);
			}
		});

		document.body.appendChild(modal);
		return modal;
	}

	function openGallery(images, startIndex) {
		state.images = images;
		state.index = startIndex || 0;
		lastFocused = document.activeElement;
		buildModal();

		var grid = modal.querySelector('.rmd-gv-grid');
		grid.innerHTML = '';
		images.forEach(function (im, i) {
			var cell = document.createElement('button');
			cell.type = 'button';
			cell.className = 'rmd-gv-cell';
			cell.setAttribute('data-i', i);
			var img = document.createElement('img');
			img.src = im.src;
			img.alt = im.alt;
			img.loading = 'lazy';
			cell.appendChild(img);
			grid.appendChild(cell);
		});
		modal.querySelector('.rmd-gv-count').textContent =
			images.length + ' ' + (images.length > 1 ? (i18n.images || 'images') : (i18n.image || 'image'));

		modal.classList.remove('is-full');
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.documentElement.classList.add('rmd-noscroll');
		modal.querySelector('.rmd-gv-close').focus();
	}

	function show(i) {
		var n = state.images.length;
		if (!n) return;
		state.index = (i + n) % n; // wrap
		var im = state.images[state.index];
		var stageImg = modal.querySelector('.rmd-gv-stage img');
		stageImg.src = im.src;
		stageImg.alt = im.alt;
		modal.querySelector('.rmd-gv-cap').textContent = im.alt || '';
		var edit = modal.querySelector('[data-edit]');
		edit.style.display = (im.id && window.wp && window.wp.media) ? '' : 'none';
		modal.classList.add('is-full');
	}

	function closeModal() {
		if (!modal || !modal.classList.contains('is-open')) return;
		modal.classList.remove('is-open', 'is-full');
		modal.setAttribute('aria-hidden', 'true');
		document.documentElement.classList.remove('rmd-noscroll');
		var stageImg = modal.querySelector('.rmd-gv-stage img');
		if (stageImg) stageImg.src = '';
		if (lastFocused && document.body.contains(lastFocused)) lastFocused.focus();
	}

	function editInLibrary(id) {
		if (!id || !window.wp || !window.wp.media) return;
		// Our lightbox (z-index 999999) sits above wp.media (~160000); close it
		// first, or the media frame opens behind our opaque backdrop.
		closeModal();
		var frame = window.wp.media({ frame: 'select', multiple: false });
		frame.on('open', function () {
			var selection = frame.state().get('selection');
			var att = window.wp.media.attachment(id);
			att.fetch();
			selection.add([att]);
		});
		frame.open();
	}

	document.addEventListener('keydown', function (e) {
		if (!modal || !modal.classList.contains('is-open')) return;
		if (e.key === 'Escape') {
			e.stopPropagation();
			if (modal.classList.contains('is-full')) { modal.classList.remove('is-full'); } else { closeModal(); }
		} else if (modal.classList.contains('is-full')) {
			if (e.key === 'ArrowLeft') show(state.index - 1);
			else if (e.key === 'ArrowRight') show(state.index + 1);
		}
	});

	// ── Scan + live refresh ───────────────────────────────────────────────────
	function scan(root) {
		var scope = root && root.querySelectorAll ? root : document;
		scope.querySelectorAll('.acf-field-flexible-content .layout').forEach(buildRow);
	}

	var refreshTimer = null;
	function scheduleRefresh() {
		if (refreshTimer) clearTimeout(refreshTimer);
		refreshTimer = setTimeout(function () { scan(document); }, 250);
	}

	// True when a mutation is one WE caused (adding/removing our own bar). For a
	// childList mutation m.target is the PARENT (the .layout), so checking the
	// added/removed nodes — not the target — is what actually breaks the loop:
	// buildRow removes + re-adds a .rmd-gv-bar on every run, and without this the
	// observer would re-fire forever.
	function isOwnMutation(m) {
		var nodes = [].slice.call(m.addedNodes).concat([].slice.call(m.removedNodes));
		if (!nodes.length) return false;
		return nodes.every(function (n) {
			return n.nodeType === 1 && n.classList && n.classList.contains('rmd-gv-bar');
		});
	}

	function init() {
		scan(document);

		// ACF adds rows and swaps image previews on select/remove; a debounced
		// observer re-derives the strips. buildRow is idempotent, so re-running
		// on any real mutation is safe.
		new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var m = mutations[i];
				if (isOwnMutation(m)) continue;
				if (m.target && m.target.closest && m.target.closest('.rmd-gv-bar')) continue;
				scheduleRefresh();
				break;
			}
		}).observe(document.body, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
