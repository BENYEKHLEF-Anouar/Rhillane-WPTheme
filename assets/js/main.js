/* Front-end interactivity — vanilla JS, loaded deferred.
   Currently: the screenshot lightbox. Scroll frames are pure CSS. */
(function () {
	'use strict';

	var lightbox = null;
	var lightboxImg = null;
	var lastTrigger = null;

	function buildLightbox() {
		lightbox = document.createElement('div');
		lightbox.className = 'rmd-lightbox';
		lightbox.setAttribute('role', 'dialog');
		lightbox.setAttribute('aria-modal', 'true');
		lightbox.setAttribute('aria-label', 'Agrandissement de la capture');
		lightboxImg = document.createElement('img');
		lightboxImg.alt = '';
		lightbox.appendChild(lightboxImg);
		document.body.appendChild(lightbox);
		lightbox.addEventListener('click', closeLightbox);
	}

	function openLightbox(frame) {
		if (!lightbox) {
			buildLightbox();
		}
		var thumb = frame.querySelector('img');
		lightboxImg.src = frame.getAttribute('data-full') || (thumb ? thumb.currentSrc || thumb.src : '');
		lightboxImg.alt = thumb ? thumb.alt : '';
		lightbox.classList.add('open');
		document.documentElement.classList.add('rmd-noscroll');
		lastTrigger = frame;
	}

	function closeLightbox() {
		if (!lightbox || !lightbox.classList.contains('open')) {
			return;
		}
		lightbox.classList.remove('open');
		lightboxImg.src = '';
		document.documentElement.classList.remove('rmd-noscroll');
		if (lastTrigger && typeof lastTrigger.focus === 'function') {
			lastTrigger.focus();
		}
	}

	document.addEventListener('click', function (e) {
		var frame = e.target.closest ? e.target.closest('[data-rmd-zoom]') : null;
		if (!frame) {
			return;
		}
		// Only zoom when the click lands on the image itself, not the caption/bar.
		if (e.target.tagName === 'IMG' || e.target.closest('.shot-scroll')) {
			openLightbox(frame);
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			closeLightbox();
		}
	});
})();

/* Image loading effects (§12). The reveal is driven by an inline onload handler
   (so it works with no external JS), but a cached image can finish before that
   handler is wired — mark any already-complete image loaded, and attach a load
   listener as a fallback for the rest. Nothing here can leave an image hidden. */
(function () {
	'use strict';

	function reveal(wrap) {
		if (wrap && !wrap.classList.contains('is-loaded')) {
			wrap.classList.add('is-loaded');
		}
	}

	function init() {
		var wraps = document.querySelectorAll('.img-wrap');
		for (var i = 0; i < wraps.length; i++) {
			(function (wrap) {
				var img = wrap.querySelector('.img-main');
				if (!img) {
					reveal(wrap); // no image to wait on
					return;
				}
				if (img.complete) {
					// Finished loading — success OR failure (a broken cached image
					// is complete with naturalWidth 0; never leave it invisible).
					reveal(wrap);
				} else {
					img.addEventListener('load', function () { reveal(wrap); });
					img.addEventListener('error', function () { reveal(wrap); });
				}
			})(wraps[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

/* Site chrome — sticky-header shrink on scroll + mobile slide-menu.
   Null-guarded, so it's completely inert on pages without the RMD header. */
(function () {
	'use strict';

	var header = document.getElementById('site-header');
	if (header && header.classList.contains('is-sticky')) {
		var onScroll = function () {
			header.classList.toggle('scrolled', window.scrollY > 8);
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

	var panel = document.getElementById('mobile-menu-panel');
	var openBtn = document.getElementById('mobile-menu-btn');
	var closeBtn = document.getElementById('mobile-menu-close');
	if (panel && openBtn) {
		var setOpen = function (open) {
			panel.classList.toggle('open', open);
			panel.setAttribute('aria-hidden', open ? 'false' : 'true');
			openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
		};
		openBtn.addEventListener('click', function () { setOpen(true); });
		if (closeBtn) {
			closeBtn.addEventListener('click', function () { setOpen(false); });
		}
		// Close the panel when any link inside it is used (e.g. an anchor jump).
		panel.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('a')) { setOpen(false); }
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && panel.classList.contains('open')) { setOpen(false); }
		});
	}
})();

/* Country/language switcher dropdown — open/close only (active state + URLs
   are server-rendered in inc/locale.php). Null-guarded: inert when absent. */
(function () {
	'use strict';

	var switches = document.querySelectorAll('.rmd-locale-switch');
	if (!switches.length) { return; }

	switches.forEach(function (sw) {
		var btn = sw.querySelector('.rmd-locale-btn');
		if (!btn) { return; }
		btn.addEventListener('click', function (e) {
			e.stopPropagation();
			var open = sw.classList.toggle('open');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	});

	var closeAll = function () {
		switches.forEach(function (sw) {
			sw.classList.remove('open');
			var btn = sw.querySelector('.rmd-locale-btn');
			if (btn) { btn.setAttribute('aria-expanded', 'false'); }
		});
	};

	document.addEventListener('click', function (e) {
		if (!e.target.closest || !e.target.closest('.rmd-locale-switch')) { closeAll(); }
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { closeAll(); }
	});
})();
