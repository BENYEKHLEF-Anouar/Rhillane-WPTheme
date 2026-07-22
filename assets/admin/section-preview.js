/**
 * Page builder — live section preview.
 *
 * Two entry points, both opening the same modal:
 *
 *  1. "Add Row" popup — an eye button on each entry previews that layout as a
 *     generic demo (no ACF row exists yet, so layouts fall back to their
 *     defaults; CPT-driven ones show real database content). Offers "Insérer".
 *
 *  2. An existing row's toolbar — an eye button previews THAT row with its
 *     saved values. If the row has unsaved edits (or was never saved), the
 *     modal says so rather than passing off stale content as current.
 *
 * The section renders at desktop width (1280px) in an iframe and is scaled down
 * to the modal, otherwise every layout would render at its mobile breakpoint.
 *
 * Config comes from rmdSectionPreview (localized in functions.php).
 */
(function () {
    'use strict';

    var cfg = window.rmdSectionPreview || {};
    var layouts = cfg.layouts || {};
    var i18n = cfg.i18n || {};

    var RENDER_WIDTH = 1280; // the width layouts are designed around

    var modal = null;
    var frame = null;
    var stage = null;
    var current = null; // { layout, anchor, rowIndex, hint }
    var lastFocused = null;
    var lastFieldKey = '';

    // ── Insert path (Add Row popup only) ─────────────────────────────────────
    document.addEventListener('click', function (e) {
        var addBtn = e.target.closest('[data-name="add-layout"], .acf-fc-add');
        if (!addBtn) return;
        var field = addBtn.closest('.acf-field-flexible-content');
        lastFieldKey = field ? field.getAttribute('data-key') || '' : '';
    }, true);

    /**
     * @param {string} layoutName
     * @param {HTMLElement|null} anchor  Captured before closeModal(), which clears it.
     */
    function insertLayout(layoutName, anchor) {
        // Preferred: replay the click on ACF's own anchor — it handles insert
        // position and the min/max row rules.
        if (anchor && document.body.contains(anchor)) {
            anchor.click();
            return;
        }
        // Fallback: drive the field through ACF's JS API.
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

    /**
     * The post being edited. Resolved at runtime with fallbacks rather than
     * trusting the single value PHP computed at enqueue time: get_the_ID() can
     * return 0 on some edit screens, and a saved-row preview with post_id=0
     * renders empty (→ misleading "not saved" for a saved section).
     */
    function currentPostId() {
        if (cfg.postId) return cfg.postId;
        try {
            if (window.wp && wp.data && wp.data.select('core/editor')) {
                var id = wp.data.select('core/editor').getCurrentPostId();
                if (id) return id;
            }
        } catch (e) { /* not the block editor */ }
        var el = document.getElementById('post_ID'); // classic editor
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
        // Cache-bust so "Rafraîchir" after saving actually refetches.
        params.set('_ts', String(Date.now()));
        return (cfg.ajaxUrl || '') + '?' + params.toString();
    }

    /**
     * The `.values` container that holds THIS field's real rows. ACF also keeps
     * a `.clones` container with one hidden `.layout.acf-clone` template PER
     * layout type — those must never be counted as rows, or every saved-row
     * index shifts and the server renders the wrong section. Scoping to
     * `.values` direct children excludes clone templates by construction, and
     * also excludes rows belonging to a nested flexible-content field.
     */
    /**
     * A real, saved/insertable row — a `.layout` that is NOT one of ACF's
     * hidden clone templates. Clone templates carry `.acf-clone` and/or live in
     * a `.clones` container; we exclude both. Deliberately does NOT require a
     * specific parent class (`.values`): ACF's container markup varies by
     * version, and requiring it made `rowIndexOf` return -1 on the real editor.
     */
    function isRealRow(row) {
        return !!row && row.classList &&
            row.classList.contains('layout') &&
            !row.classList.contains('acf-clone') &&
            !(row.closest && row.closest('.clones'));
    }

    /** The real rows sharing `container` as their parent, in DOM/saved order. */
    function rowsIn(container) {
        if (!container) return [];
        return Array.prototype.filter.call(container.children, isRealRow);
    }

    /** The container holding this field's real rows (parent of its first row). */
    function rowContainerOf(field) {
        var all = field.querySelectorAll('.layout');
        for (var i = 0; i < all.length; i++) {
            var el = all[i];
            if (!isRealRow(el)) continue;
            if (el.closest('.acf-field-flexible-content') !== field) continue; // skip nested field rows
            return el.parentNode;
        }
        return null;
    }

    function rowsOf(field) {
        return rowsIn(rowContainerOf(field));
    }

    /**
     * A row's index = its position among its OWN sibling rows. Robust: a real
     * row is always present in its parent's children, so this can't be -1 the
     * way a container-class lookup could.
     */
    function rowIndexOf(row) {
        return rowsIn(row.parentNode).indexOf(row);
    }

    /** Rows present at page load are the saved ones; anything added later is new. */
    function savedRowCount(field) {
        var n = field.getAttribute('data-rmd-saved-rows');
        return n === null ? null : parseInt(n, 10);
    }

    /**
     * Does the post have unsaved edits? Uses the block editor's own dirty flag
     * — the single source of truth — instead of per-row heuristics (input
     * listeners, MutationObservers, isTrusted). Those tried to detect edits
     * themselves and produced false positives on load (ACF fires programmatic
     * change events during init) and false negatives (WYSIWYG/select2 fire
     * synthetic events on real edits). isEditedPostDirty() is correct in every
     * case: false on fresh load, true after any change, false again after save.
     */
    function postIsDirty() {
        try {
            var ed = window.wp && wp.data && wp.data.select('core/editor');
            if (ed && typeof ed.isEditedPostDirty === 'function') return !!ed.isEditedPostDirty();
        } catch (e) { /* not the block editor */ }
        return false; // classic editor reloads on save; don't guess
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
            // .rmd-sp-clip absorbs the overflow: transform:scale() shrinks the
            // render visually but the scaler keeps its 1280px layout box.
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

        // Clicks inside the modal must never reach ACF's document-level "close
        // the popup" handler — that destroys the anchor we insert with.
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

        // Measure against the clip (stage may show a vertical scrollbar).
        var available = clip.clientWidth || stage.clientWidth;
        var scale = Math.min(1, available / RENDER_WIDTH);

        scaler.style.transform = 'scale(' + scale + ')';
        scaler.style.width = RENDER_WIDTH + 'px';

        var h = parseInt(frame.dataset.contentHeight || '0', 10);
        if (h > 0) {
            frame.style.height = h + 'px';
            scaler.style.height = h + 'px';
            // The clip carries the *scaled* height so no phantom space remains.
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
            // Measure the BODY's content box, not <html>: documentElement
            // .scrollHeight always fills at least the iframe's viewport height,
            // so a short section (e.g. the ~107px stats band in a 400px+ frame)
            // would otherwise be reported as full-height and leave a huge blank.
            // Fall back to the content's own bounding box, then documentElement,
            // only if body reports nothing.
            var h = doc.body.scrollHeight
                || Math.ceil(doc.body.getBoundingClientRect().height)
                || (doc.documentElement ? doc.documentElement.scrollHeight : 0);
            frame.dataset.contentHeight = String(h || 400);
            setStatus('');
            fitFrame();
        };

        measure();
        // Webfonts/images settle after load; re-measure once they do.
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

    /**
     * @param {{layout:string, isRow:boolean, anchor?:HTMLElement, rowIndex?:number, hint?:string}} opts
     *   isRow is EXPLICIT: the row eye is always a row preview, the popup eye is
     *   always a demo. Deriving it from `rowIndex >= 0` let a failed index
     *   computation silently degrade a row preview into a demo.
     */
    /** Render the modal heading as a coloured category chip + the section title,
        mirroring the picker cards, instead of printing the raw
        "[Commun] Bandeau — logos partenaires" label with its brackets. The chip
        carries data-cat so it picks up the same colour theme as the cards.

        The <h2> keeps its id, so aria-labelledby still names the dialog — and
        it now reads "Commun Bandeau — logos partenaires", which is the natural
        spoken form of what's on screen. */
    function setModalTitle(full) {
        var h = modal.querySelector('.rmd-sp-title');
        var parts = splitLabel(String(full || '').trim());

        h.textContent = '';
        if (parts.cat) {
            var chip = document.createElement('span');
            chip.className = 'rmd-sp-title-cat';
            chip.setAttribute('data-cat', catSlug(parts.cat));
            chip.textContent = parts.cat;
            h.appendChild(chip);
            // The gap between chip and title is pure CSS, so the accessible name
            // would otherwise run together as "CommunBandeau — …". A whitespace
            // text node separates them for assistive tech; flexbox does not
            // render whitespace-only anonymous items, so nothing moves visually.
            h.appendChild(document.createTextNode(' '));
        }
        var text = document.createElement('span');
        text.className = 'rmd-sp-title-text';
        text.textContent = parts.title;
        h.appendChild(text);
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

        setModalTitle(info.label || opts.layout);
        modal.querySelector('.rmd-sp-desc').textContent = info.desc || '';

        // Demo caption only makes sense for the Add-Row preview.
        var note = modal.querySelector('.rmd-sp-note');
        note.textContent = isRow ? (i18n.rowPreview || '') : (i18n.demoNotice || '');

        var hint = modal.querySelector('.rmd-sp-hint');
        hint.querySelector('.rmd-sp-hint-text').textContent = current.hint;
        hint.style.display = current.hint ? '' : 'none';

        // Nothing to insert when previewing a row that already exists.
        modal.querySelector('[data-insert]').style.display = isRow ? 'none' : '';
        modal.querySelector('[data-refresh]').style.display = isRow ? '' : 'none';

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        // Visually hide ACF's popup (it renders above everything). `visibility`
        // rather than `display`, so its anchors stay clickable for insertion.
        document.body.classList.add('rmd-sp-modal-open');

        modal.querySelector('.rmd-sp-close').focus();

        // A row preview needs a valid index. If we couldn't locate the row in
        // its `.values` container, say so plainly instead of loading a
        // misleading demo.
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
        frame.src = 'about:blank'; // stop work, free memory

        if (!opts || !opts.keepFocus) {
            if (lastFocused && document.body.contains(lastFocused)) lastFocused.focus();
        }
        current = null;
    }

    /** Eye button for the "Add Row" popup (a plain button in a light list). */
    function makeEyeButton(labelText) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rmd-sp-eye';
        btn.title = i18n.previewTitle || 'Aperçu';
        btn.setAttribute('aria-label', (i18n.previewTitle || 'Aperçu') + (labelText ? ' : ' + labelText : ''));
        btn.innerHTML = '<span class="dashicons dashicons-visibility" aria-hidden="true"></span>';
        return btn;
    }

    /**
     * Eye icon for a row toolbar. Styled from scratch to match the native
     * +/duplicate/trash icons on the row's header bar. Deliberately NOT
     * `.acf-icon` — that class defaults to a filled circle, which made the eye
     * look different from its neighbours. `acf-js-tooltip` is kept for ACF's
     * native tooltip only.
     */
    function makeRowEye(labelText) {
        var a = document.createElement('a');
        a.href = '#';
        a.className = 'acf-js-tooltip rmd-sp-eye--row';
        a.setAttribute('data-name', 'rmd-preview-layout');
        a.title = i18n.previewTitle || 'Aperçu';
        a.setAttribute('aria-label', (i18n.previewTitle || 'Aperçu') + (labelText ? ' : ' + labelText : ''));
        a.innerHTML = '<span class="dashicons dashicons-visibility" aria-hidden="true"></span>';
        return a;
    }

    // ── 1. "Add Row" popup entries → card grid ───────────────────────────────

    /** Split an ACF label — "[Catégorie] Titre — sous-titre" — into its chip and
        title parts. Shared by the picker cards and the preview modal header so
        both render the category the same way. Falls back to the whole string as
        the title when there is no "[…]" prefix. */
    function splitLabel(full) {
        var out = { cat: '', title: full };
        var m = /^\[([^\]]+)\]\s*(.*)$/.exec(full);
        if (m) {
            out.cat = m[1].trim();
            out.title = m[2].trim() || full;
        }
        return out;
    }

    /** Map the "[Catégorie]" label prefix to a colour-theme slug. */
    function catSlug(cat) {
        var c = (cat || '').toLowerCase();
        if (c.indexOf('accueil') >= 0)  return 'accueil';
        if (c.indexOf('commun') >= 0)   return 'commun';
        if (c.indexOf('blog') >= 0)     return 'blog';
        if (c.indexOf('contact') >= 0)  return 'contact';
        if (c.indexOf('thodo') >= 0)    return 'methodo';  // méthodologie
        if (c.indexOf('propos') >= 0)   return 'propos';
        if (c.indexOf('formulaire') >= 0) return 'form';
        return 'default';
    }

    /** Widened grid can overflow the right edge — nudge it back into view.
        Repositions whichever element ACF actually positions (the popup or a
        .acf-tooltip wrapper around it). Runs on the next frame so the widened
        grid has been laid out before we measure. */
    function clampPopup(popup) {
        var pos = (popup.closest && popup.closest('.acf-tooltip')) || popup;
        requestAnimationFrame(function () {
            var r = pos.getBoundingClientRect();
            var over = r.right - (window.innerWidth - 10);
            if (over > 0) {
                var left = parseFloat(getComputedStyle(pos).left);
                if (isNaN(left)) left = r.left;
                pos.style.right = 'auto';
                pos.style.left = Math.max(10, left - over) + 'px';
            }
        });
    }

    /** Categories that belong on ANY page (they have no page of their own), so
        they sit in their own always-visible group rather than behind the
        toggle. Without this, `common_hero` — the first section on every
        non-home page — would be hidden by default. */
    var COMMON_CATS = ['commun', 'form'];

    /** The grid cell a card occupies (ACF wraps each anchor in an <li>), so we
        hide the cell rather than the anchor — hiding the anchor alone would
        leave an empty cell holding a gap in the grid. */
    function cardCell(card) {
        return card.closest('li') || card;
    }

    /** The flexible-content field the popup was opened from. `lastFieldKey` is
        set when the "Add" button is clicked; fall back to the only such field
        on the screen. */
    function builderField() {
        if (lastFieldKey) {
            var f = document.querySelector('.acf-field-flexible-content[data-key="' + lastFieldKey + '"]');
            if (f) return f;
        }
        return document.querySelector('.acf-field-flexible-content');
    }

    /** How many times each layout is currently on the page, as a {name: count}
        map. Read live from the DOM rather than from the server, so rows added
        (or removed) since the last save are reflected too. Clone templates are
        excluded via isRealRow, and nested flexible fields via the closest()
        check — otherwise a nested row would count toward the outer field. */
    function usedLayoutCounts() {
        var field = builderField();
        var used = {};
        if (!field) return used;
        field.querySelectorAll('.layout[data-layout]').forEach(function (row) {
            if (!isRealRow(row)) return;
            if (row.closest('.acf-field-flexible-content') !== field) return;
            var n = row.getAttribute('data-layout');
            used[n] = (used[n] || 0) + 1;
        });
        return used;
    }

    /** A full-width row in the card grid (group header or the toggle). */
    function fullWidthCell(className) {
        var li = document.createElement('li');
        li.className = className;
        return li;
    }

    function groupHeader(text) {
        var li = fullWidthCell('rmd-sp-group');
        li.setAttribute('role', 'presentation');
        li.textContent = text;
        return li;
    }

    /** Mark a card whose layout is already on the page. Purely informational —
        the card stays fully clickable, since most sections may legitimately
        repeat. Complements the after-the-fact duplicate warning in
        functions.php. */
    function markUsed(card, count) {
        var head = card.querySelector('.rmd-sp-card-head');
        var eye = card.querySelector('.rmd-sp-card-eye');
        if (!head || head.querySelector('.rmd-sp-card-used')) return;

        var badge = document.createElement('span');
        badge.className = 'rmd-sp-card-used';
        badge.textContent = i18n.used || 'Utilisée';
        badge.title = count > 1
            ? (i18n.usedTimes || 'Déjà utilisée %d fois sur cette page').replace('%d', count)
            : (i18n.usedOnce || 'Déjà utilisée sur cette page');

        // Before the eye, which is pinned right by `margin-left: auto`.
        if (eye) head.insertBefore(badge, eye); else head.appendChild(badge);
    }

    /** Reorganise the picker into, visible by default:
          "Sections de cette page"   → the page's own category
          "Sections communes"        → ONLY the commun/formulaire sections the
                                       page actually uses (omitted if none)
        and, behind the toggle:
          "Autres sections communes" → the commun/formulaire ones not in use
          "Sections des autres pages" → every other category

        Every card whose layout is already on the page — in any group — gets a
        "utilisée" badge.

        No-ops (leaving every card visible, ungrouped, no toggle) when the page
        has no category or nothing matches it — an empty picker would be worse
        than an unfiltered one. */
    function applyCategoryFilter(popup) {
        var pageCat = cfg.pageCategory || '';
        if (!pageCat) return;

        var list = popup.querySelector('ul');
        if (!list || list.dataset.rmdFiltered) return;

        var cards = Array.prototype.slice.call(popup.querySelectorAll('a.rmd-sp-card'));
        if (!cards.length) return;

        var used = usedLayoutCounts();
        var isUsed = function (c) { return !!used[c.getAttribute('data-layout')]; };

        var pageCards = [], usedCommon = [], unusedCommon = [], otherCards = [];
        cards.forEach(function (c) {
            var cat = c.getAttribute('data-cat');
            if (cat === pageCat) pageCards.push(c);
            else if (COMMON_CATS.indexOf(cat) !== -1) (isUsed(c) ? usedCommon : unusedCommon).push(c);
            else otherCards.push(c);
        });

        // Never leave the editor with an empty picker.
        if (!pageCards.length) return;
        var hiddenCount = unusedCommon.length + otherCards.length;
        // Only one group and nothing to reveal → grouping adds noise, not signal.
        if (!usedCommon.length && !hiddenCount) return;

        list.dataset.rmdFiltered = '1';

        // Badge every section already on the page, whichever group it lands in.
        cards.forEach(function (c) {
            if (isUsed(c)) markUsed(c, used[c.getAttribute('data-layout')]);
        });

        // Rebuild the grid in group order. Moving <li> nodes is safe: ACF binds
        // insertion by delegation on `[data-layout]`, not on element identity.
        list.appendChild(groupHeader(i18n.groupPage || 'Sections de cette page'));
        pageCards.forEach(function (c) { list.appendChild(cardCell(c)); });

        // Only the commun sections in use — an unused one is not "this page's".
        if (usedCommon.length) {
            list.appendChild(groupHeader(i18n.groupCommon || 'Sections communes'));
            usedCommon.forEach(function (c) { list.appendChild(cardCell(c)); });
        }

        if (!hiddenCount) return; // nothing to reveal, so no toggle

        var toggleLi = fullWidthCell('rmd-sp-more-li');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rmd-sp-more';
        btn.textContent = i18n.showOther || 'Afficher les autres sections';
        btn.setAttribute('aria-expanded', 'false');
        toggleLi.appendChild(btn);
        list.appendChild(toggleLi);

        // Everything below the toggle, collapsed together. Kept as two labelled
        // sub-groups: an unused newsletter CTA is not "another page's" section.
        var hidden = [];
        var addGroup = function (label, group) {
            if (!group.length) return;
            var h = groupHeader(label);
            list.appendChild(h);
            hidden.push(h);
            group.forEach(function (c) {
                var cell = cardCell(c);
                list.appendChild(cell);
                hidden.push(cell);
            });
        };
        addGroup(i18n.groupOtherCommon || 'Autres sections communes', unusedCommon);
        addGroup(i18n.groupOther || 'Sections des autres pages', otherCards);

        var hide = function (on) {
            hidden.forEach(function (el) { el.classList.toggle('rmd-sp-hidden', on); });
        };
        hide(true);

        btn.addEventListener('click', function (e) {
            // ACF closes the popup on outside clicks and inserts on anchor
            // clicks — this button is neither.
            e.preventDefault();
            e.stopPropagation();

            var expanded = btn.getAttribute('aria-expanded') === 'true';
            hide(expanded);
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            btn.textContent = expanded
                ? (i18n.showOther || 'Afficher les autres sections')
                : (i18n.hideOther || 'Masquer les autres sections');

            clampPopup(popup); // the grid just grew or shrank
        });
    }

    function decoratePopup(popup) {
        popup.querySelectorAll('a[data-layout]').forEach(function (anchor) {
            if (anchor.dataset.rmdPreview) return;
            anchor.dataset.rmdPreview = '1';

            var name = anchor.getAttribute('data-layout');
            var info = layouts[name];
            if (!info) return;

            var full = (info.label || anchor.textContent || name).trim();

            var parts = splitLabel(full);
            var cat = parts.cat, title = parts.title;

            anchor.title = full;                 // full text on hover
            anchor.textContent = '';             // rebuild as a card
            anchor.classList.add('rmd-sp-card');
            anchor.setAttribute('data-cat', catSlug(cat));

            var head = document.createElement('div');
            head.className = 'rmd-sp-card-head';

            if (cat) {
                var chip = document.createElement('span');
                chip.className = 'rmd-sp-card-cat';
                chip.textContent = cat;
                head.appendChild(chip);
            }

            var eye = makeEyeButton(info.label);
            eye.classList.add('rmd-sp-card-eye');
            eye.addEventListener('click', function (e) {
                // Never reach ACF's anchor (inserts) nor the document (closes).
                e.preventDefault();
                e.stopPropagation();
                openModal({ layout: name, anchor: anchor, isRow: false });
            });
            head.appendChild(eye);
            anchor.appendChild(head);

            var t = document.createElement('span');
            t.className = 'rmd-sp-card-title';
            t.textContent = title;
            anchor.appendChild(t);

            if (info.desc) {
                var d = document.createElement('span');
                d.className = 'rmd-sp-card-desc';
                d.textContent = info.desc;
                anchor.appendChild(d);
            }
        });

        // After the cards exist (they carry the data-cat this filters on).
        applyCategoryFilter(popup);

        clampPopup(popup);
    }

    // ── 2. Existing rows ─────────────────────────────────────────────────────
    function decorateRow(row) {
        if (row.dataset.rmdPreview) return;
        // Only real rows (direct children of `.values`), never clone templates
        // in `.clones` — decorating a clone would attach a broken eye and, worse,
        // its later insertion as a real row would carry a stale index.
        if (!isRealRow(row)) return;

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
                hint = i18n.newRowHint || '';       // row added, never saved
            } else if (postIsDirty()) {
                hint = i18n.dirtyHint || '';        // page has unsaved edits
            }

            openModal({ layout: name, rowIndex: index, hint: hint, isRow: true });
        });

        controls.insertBefore(btn, controls.firstChild);
    }

    /**
     * The post was saved: everything on screen is now the saved state.
     * Re-baseline the saved-row count (rows added this session now exist in the
     * database, so they should no longer read as "never saved"), and refresh an
     * open row preview so the warning clears and the iframe shows what was saved.
     */
    function onPostSaved() {
        document.querySelectorAll('.acf-field-flexible-content').forEach(function (field) {
            field.setAttribute('data-rmd-saved-rows', String(rowsOf(field).length));
        });

        if (modal && modal.classList.contains('is-open') && current && current.rowIndex >= 0) {
            current.hint = '';
            var hint = modal.querySelector('.rmd-sp-hint');
            hint.querySelector('.rmd-sp-hint-text').textContent = '';
            hint.style.display = 'none';
            reload(); // show what was just saved
        }
    }

    /** Detect a completed save in the block editor, and in the classic editor. */
    function watchSaves() {
        // Block editor (Gutenberg): watch the save lifecycle, ignoring autosaves.
        if (window.wp && wp.data && typeof wp.data.subscribe === 'function') {
            var wasSaving = false;
            wp.data.subscribe(function () {
                var editor = wp.data.select('core/editor');
                if (!editor || typeof editor.isSavingPost !== 'function') return;

                var saving = editor.isSavingPost() && !editor.isAutosavingPost();
                if (wasSaving && !saving) {
                    // Save finished. Bail if it failed, so we don't clear a
                    // warning that is still true.
                    var failed = typeof editor.didPostSaveRequestFail === 'function' && editor.didPostSaveRequestFail();
                    if (!failed) onPostSaved();
                }
                wasSaving = saving;
            });
        }

        // Classic editor / ACF's own AJAX save: the page reloads on submit, so
        // the flags reset naturally. Nothing to do.
    }

    function scan(root) {
        (root.querySelectorAll ? root : document).querySelectorAll('.acf-fc-popup').forEach(decoratePopup);
        (root.querySelectorAll ? root : document).querySelectorAll('.acf-field-flexible-content .layout').forEach(decorateRow);
    }

    function init() {
        // Rows present now are the saved ones; anything added later is new.
        // (Dirtiness itself is read live from the block editor via postIsDirty();
        // no per-row edit tracking, so nothing here can go stale or false-positive.)
        document.querySelectorAll('.acf-field-flexible-content').forEach(function (field) {
            if (!field.hasAttribute('data-rmd-saved-rows')) {
                field.setAttribute('data-rmd-saved-rows', String(rowsOf(field).length));
            }
        });

        scan(document);
        watchSaves();

        // ACF injects the popup on demand and rows on insert/duplicate; decorate
        // them as they appear. decorateRow/decoratePopup are idempotent and
        // decorateRow ignores clone templates (isRealRow).
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.classList && node.classList.contains('acf-fc-popup')) {
                        decoratePopup(node);
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
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            e.stopPropagation();
            closeModal();
        }
    });
})();
