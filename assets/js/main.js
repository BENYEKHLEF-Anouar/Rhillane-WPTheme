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
