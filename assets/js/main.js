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
