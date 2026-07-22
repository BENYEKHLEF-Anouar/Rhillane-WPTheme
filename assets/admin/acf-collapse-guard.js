/**
 * ACF flexible-content collapse guard (editor-only).
 *
 * When you collapse a layout row, ACF Pro's renderLayout() runs. Its first line is:
 *
 *     row.children('input').attr('name').replace('[acf_fc_layout]', '')
 *
 * i.e. it reads the row's hidden [acf_fc_layout] input to build the AJAX request
 * for a dynamic "layout title". If that input is missing or unnamed at click time
 * — which happens when ANOTHER plugin's JS error interrupts ACF's field init
 * (e.g. a broken mistral-api.js on the page) — .attr('name') is undefined,
 * .replace() throws, and the collapse/expand silently dies.
 *
 * We do NOT use dynamic layout titles (our layouts have plain static labels), so
 * that AJAX call is pointless here. This guard wraps renderLayout so it:
 *   • becomes a safe no-op when the [acf_fc_layout] input isn't usable, and
 *   • never throws (try/catch),
 * which lets collapse/expand work no matter how other plugins misbehave. When the
 * input IS present (the normal case) it defers to ACF's original untouched.
 *
 * Feature-detected, idempotent, and inert if ACF is off or changes its internals.
 */
(function () {
	'use strict';

	var warned = false;

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

		var original = FC.prototype.renderLayout;

		FC.prototype.renderLayout = function ($layout) {
			try {
				var $input = ($layout && typeof $layout.children === 'function')
					? $layout.children('input')
					: null;

				// No usable [acf_fc_layout] input → ACF's original would call
				// .replace() on undefined and throw, killing the collapse. Skip the
				// (unused) dynamic-title AJAX and let the collapse proceed cleanly.
				if (!$input || !$input.length || !$input.attr('name')) {
					if (!warned && window.console && console.warn) {
						warned = true;
						console.warn('[RMD] Skipped an ACF layout-title render with no ' +
							'[acf_fc_layout] input — collapse/expand kept working. This ' +
							'usually means another plugin errored during page load and ' +
							'interrupted ACF init (check the console for other errors).');
					}
					return;
				}

				return original.apply(this, arguments);
			} catch (e) {
				// Rendering the collapsed title must never break collapse/expand.
				return;
			}
		};

		FC.prototype.rmdCollapseGuard = true;
		return true;
	}

	// Apply as early as possible, then retry on ACF's lifecycle hooks and DOM ready
	// so the wrapper is always in place well before any collapse click.
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
})();
