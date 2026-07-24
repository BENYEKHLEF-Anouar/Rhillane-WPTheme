/**
 * Advanced link options — the "⚙ Options avancées" popup (spec §10.1).
 *
 * The ACF group holding rel/nofollow, target, download, aria-label, title, id,
 * classes and data-* rows is long and rarely touched, so inline it would drown
 * the two fields editors actually use (button text + link). This collapses it to
 * a button and shows the real fields in a modal.
 *
 * How it stays safe:
 *   - Only the group's INNER field list is touched. Its .acf-field wrapper, label,
 *     data-attributes and position never move — so ACF tabs, conditional logic and
 *     flexible-content row handling see exactly the markup they rendered.
 *   - That field list is MOVED into the dialog, never copied: one set of inputs,
 *     so ACF's save, validation and repeater controls keep working untouched.
 *   - The dialog is appended INSIDE the field's <form>. Outside it, the moved
 *     inputs would silently not be submitted.
 *   - No blanket stopPropagation inside the dialog: ACF's "add row" and friends
 *     are delegated from document and must keep receiving clicks.
 *   - Clicks are delegated, so a trigger copied by ACF when cloning a flexible
 *     row (which carries no listeners) still works.
 *   - Everything is wrapped defensively: a failure here must never take the
 *     editor down with it (see assets/admin/acf-collapse-guard.js).
 *
 * Config: window.rmdLinkOptions (inc/admin-link-options.php).
 */
(function () {
	'use strict';

	var cfg   = window.rmdLinkOptions || {};
	var names = cfg.fields || [];
	var i18n  = cfg.i18n || {};
	if (!names.length) {
		return;
	}

	var SELECTOR = names.map(function (n) {
		return '.acf-field[data-name="' + n + '"]';
	}).join(', ');

	var overlays = [];   // one per <form>
	var open     = null; // { list, slot, trigger, overlay }

	function el(tag, cls) {
		var node = document.createElement(tag);
		if (cls) {
			node.className = cls;
		}
		return node;
	}

	/** The group's own .acf-input (never a nested sub-field's). */
	function inputOf(field) {
		var kid = field ? field.firstElementChild : null;
		while (kid) {
			if (kid.classList && kid.classList.contains('acf-input')) {
				return kid;
			}
			kid = kid.nextElementSibling;
		}
		return null;
	}

	/**
	 * The group's inner field list — the part that travels to the dialog. Returns
	 * null while the list is out on loan, which is what keeps decorate() from
	 * stashing our own trigger in its place.
	 */
	function listOf(field) {
		var input = inputOf(field);
		if (!input) {
			return null;
		}
		var list = input.querySelector('.acf-fields');
		if (list) {
			return list;
		}
		// Fallback for a non-block group layout — but never our own injected nodes.
		var kid = input.firstElementChild;
		while (kid) {
			if (kid.classList &&
				!kid.classList.contains('rmd-lo-trigger-wrap') &&
				!kid.classList.contains('rmd-lo-slot')) {
				return kid;
			}
			kid = kid.nextElementSibling;
		}
		return null;
	}

	/**
	 * Real repeater rows, ignoring ACF's hidden clone template. Both class spellings
	 * are tested: the rest of assets/admin looks for `.acf-clone`, while ACF's own
	 * repeater markup has used `-clone` — a miscount here would only show a wrong
	 * badge, but the check is free.
	 */
	function realRows(scope) {
		var count = 0;
		scope.querySelectorAll('.acf-row').forEach(function (row) {
			if (row.classList.contains('acf-clone') || row.classList.contains('-clone')) {
				return;
			}
			if (row.closest('.clones')) {
				return;
			}
			count++;
		});
		return count;
	}

	/**
	 * What is actually set, as short human labels — drives the badge count and the
	 * trigger's tooltip. Reads the DOM, not saved values, so it stays correct while
	 * the editor is still typing.
	 */
	function summarize(list) {
		var out = [];
		if (!list) {
			return out;
		}
		Array.prototype.forEach.call(list.children, function (sub) {
			if (!sub.classList || !sub.classList.contains('acf-field')) {
				return;
			}
			var label = sub.querySelector('.acf-label label');
			var text  = label ? label.textContent.trim() : (sub.getAttribute('data-name') || '');

			if (sub.classList.contains('acf-field-repeater')) {
				var rows = realRows(sub);
				if (rows) {
					out.push(text + ' × ' + rows);
				}
				return;
			}

			// Checkbox list (rel): show the ticked values themselves — "nofollow" is
			// far more useful in a tooltip than "rel attributes".
			if (sub.classList.contains('acf-field-checkbox')) {
				var picked = [];
				sub.querySelectorAll('input[type="checkbox"]:checked').forEach(function (box) {
					if (box.value) {
						picked.push(box.value);
					}
				});
				if (picked.length) {
					out.push(picked.join(', '));
				}
				return;
			}

			// true_false: ACF pairs the checkbox with a hidden "0" input.
			var toggle = sub.querySelector('input[type="checkbox"]');
			if (toggle) {
				if (toggle.checked) {
					out.push(text);
				}
				return;
			}

			var input = sub.querySelector('input[type="text"], input[type="url"], textarea');
			if (input && String(input.value || '').trim() !== '') {
				out.push(text);
			}
		});
		return out;
	}

	function refresh(list, trigger) {
		try {
			if (!trigger || !document.body.contains(trigger)) {
				return;
			}
			var parts = summarize(list);
			var badge = trigger.querySelector('.rmd-lo-badge');
			if (badge) {
				badge.textContent = String(parts.length);
				badge.hidden = parts.length === 0;
			}
			var summary = trigger.parentNode && trigger.parentNode.querySelector('.rmd-lo-summary');
			if (summary) {
				summary.textContent = parts.join(' · ');
			}
			trigger.title = parts.length ? parts.join(' · ') : (i18n.triggerEmpty || '');
		} catch (err) { /* a wrong badge must never break the editor */ }
	}

	function decorate(field) {
		try {
			var input = inputOf(field);
			var list  = listOf(field);
			if (!input || !list) {
				return;
			}
			// Idempotent: a row cloned by ACF arrives with the trigger already in it.
			var trigger = input.querySelector('.rmd-lo-trigger');
			if (!trigger) {
				var wrap = el('div', 'rmd-lo-trigger-wrap');
				trigger  = el('button', 'rmd-lo-trigger');
				trigger.type = 'button';

				var gear = el('span', 'dashicons dashicons-admin-generic');
				var text = el('span', 'rmd-lo-label');
				text.textContent = i18n.trigger || 'Advanced options';
				var badge = el('span', 'rmd-lo-badge');
				badge.hidden = true;

				trigger.appendChild(gear);
				trigger.appendChild(text);
				trigger.appendChild(badge);
				wrap.appendChild(trigger);
				wrap.appendChild(el('span', 'rmd-lo-summary'));
				input.appendChild(wrap);
			}

			// Only the inner list hides — the .acf-field wrapper stays exactly where
			// ACF rendered it (tabs, conditional logic, row cloning all untouched).
			if (!open || open.list !== list) {
				list.classList.add('rmd-lo-stash');
			}
			refresh(list, trigger);
		} catch (err) { /* never let decoration break the editor */ }
	}

	function overlayFor(form) {
		for (var i = 0; i < overlays.length; i++) {
			if (overlays[i].form === form && document.body.contains(overlays[i].root)) {
				return overlays[i];
			}
		}

		var root = el('div', 'rmd-lo-modal');
		root.setAttribute('aria-hidden', 'true');
		root.innerHTML =
			'<div class="rmd-lo-backdrop"></div>' +
			'<div class="rmd-lo-dialog" role="dialog" aria-modal="true">' +
				'<div class="rmd-lo-head">' +
					'<h2 class="rmd-lo-title"></h2>' +
					'<button type="button" class="rmd-lo-close">' +
						'<span class="dashicons dashicons-no-alt"></span>' +
					'</button>' +
				'</div>' +
				'<div class="rmd-lo-body"></div>' +
				'<div class="rmd-lo-foot">' +
					'<p class="rmd-lo-note"></p>' +
					'<button type="button" class="button button-primary rmd-lo-done"></button>' +
				'</div>' +
			'</div>';

		var overlay = {
			form:   form,
			root:   root,
			dialog: root.querySelector('.rmd-lo-dialog'),
			body:   root.querySelector('.rmd-lo-body'),
			title:  root.querySelector('.rmd-lo-title'),
			close:  root.querySelector('.rmd-lo-close'),
			done:   root.querySelector('.rmd-lo-done')
		};
		overlay.close.setAttribute('aria-label', i18n.close || 'Close');
		overlay.done.textContent = i18n.done || 'Done';
		root.querySelector('.rmd-lo-note').textContent = i18n.note || '';

		// Live badge while the popup is open, so closing never surprises.
		['input', 'change'].forEach(function (evt) {
			overlay.body.addEventListener(evt, function () {
				if (open && open.overlay === overlay) {
					refresh(open.list, open.trigger);
				}
			});
		});

		// Enter in a text field would submit #post (= publish). Not from a modal.
		overlay.dialog.addEventListener('keydown', function (e) {
			if ('Enter' === e.key && 'INPUT' === e.target.tagName && 'checkbox' !== e.target.type) {
				e.preventDefault();
			}
		});

		// INSIDE the form: the inputs we move in here must still be submitted.
		form.appendChild(root);
		overlays.push(overlay);
		return overlay;
	}

	function openPopup(field, trigger) {
		var list = listOf(field);
		if (!list) {
			return;
		}
		// No <form> to keep the inputs inside means anything moved would silently
		// not be submitted. Never risk that: reveal the fields inline instead.
		var form = field.closest('form');
		if (!form) {
			list.classList.remove('rmd-lo-stash');
			return;
		}
		var overlay = overlayFor(form);

		// A hidden stand-in marks the exact spot to put the list back.
		var slot = el('div', 'rmd-lo-slot');
		slot.hidden = true;
		list.parentNode.insertBefore(slot, list);

		list.classList.remove('rmd-lo-stash');
		overlay.body.appendChild(list);

		var label = field.querySelector('.acf-label label');
		overlay.title.textContent = (label && label.textContent.trim()) || i18n.title || 'Advanced options';

		overlay.root.classList.add('is-open');
		overlay.root.setAttribute('aria-hidden', 'false');
		document.body.classList.add('rmd-lo-open');

		open = { list: list, slot: slot, trigger: trigger, overlay: overlay };
		overlay.close.focus();
	}

	function closePopup() {
		if (!open) {
			return;
		}
		var o = open;
		open = null;

		try {
			if (o.slot && o.slot.parentNode) {
				o.list.classList.add('rmd-lo-stash');
				o.slot.parentNode.replaceChild(o.list, o.slot);
			} else if (o.list && o.list.parentNode === o.overlay.body) {
				// The row was deleted while the popup was open. Leaving the orphan in
				// the form would submit inputs for a row that no longer exists.
				o.list.parentNode.removeChild(o.list);
			}
		} catch (err) { /* fall through — the popup still has to close */ }

		o.overlay.root.classList.remove('is-open');
		o.overlay.root.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('rmd-lo-open');

		if (o.trigger && document.body.contains(o.trigger)) {
			refresh(o.list, o.trigger);
			o.trigger.focus();
		}
	}

	document.addEventListener('click', function (e) {
		if (!e.target || !e.target.closest) {
			return;
		}

		var trigger = e.target.closest('.rmd-lo-trigger');
		if (trigger) {
			// Stop here only: inside a flexible-content row a stray click can reach
			// ACF's row handlers and collapse the row under the popup.
			e.preventDefault();
			e.stopPropagation();
			var field = trigger.closest('.acf-field');
			if (field && !open) {
				openPopup(field, trigger);
			}
			return;
		}

		// Everything else inside the dialog bubbles on purpose — ACF's repeater
		// controls are delegated from document.
		if (open && e.target.closest('.rmd-lo-close, .rmd-lo-done, .rmd-lo-backdrop')) {
			e.preventDefault();
			closePopup();
		}
	});

	document.addEventListener('keydown', function (e) {
		if (!open) {
			return;
		}
		if ('Escape' === e.key) {
			// Both this and section-preview.js listen on document, so propagation is
			// already over — stopping it changes nothing there. It only shields any
			// handler bound above document (a plugin on window).
			e.stopPropagation();
			closePopup();
			return;
		}
		if ('Tab' !== e.key) {
			return;
		}
		// Minimal focus trap — the dialog is aria-modal.
		var focusable = open.overlay.dialog.querySelectorAll(
			'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])'
		);
		if (!focusable.length) {
			return;
		}
		var first = focusable[0];
		var last  = focusable[focusable.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	});

	function scan(root) {
		try {
			var node = (root && root.querySelectorAll) ? root : document;
			if (node.matches && node.matches(SELECTOR)) {
				decorate(node);
			}
			node.querySelectorAll(SELECTOR).forEach(decorate);
		} catch (err) { /* ignore malformed subtrees */ }
	}

	function init() {
		scan(document);

		new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				m.addedNodes.forEach(function (node) {
					if (1 === node.nodeType) {
						scan(node);
					}
				});
			});
		}).observe(document.body, { childList: true, subtree: true });
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
