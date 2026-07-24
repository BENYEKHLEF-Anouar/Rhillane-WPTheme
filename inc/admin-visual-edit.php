<?php
/**
 * Visual inline editing for the section preview (admin-only).
 *
 * Lets the editor click text/images INSIDE the saved-row preview iframe and
 * edit them in place; every change is mirrored into the real ACF inputs behind
 * the modal and saved by the normal « Mettre à jour » button. No custom save
 * path — WordPress/ACF save exactly as if the fields were typed by hand.
 *
 * How: when the preview endpoint runs with edit=1 (saved-row mode only), the
 * rmd_get_sub_field() wrapper (inc/acf.php) marks WHITELISTED string values
 * with invisible Unicode markers that survive esc_html()/rmd_inline_html().
 * rmd_edit_annotate() then converts the markers into
 * <span class="rmd-edit" data-rmd-path data-rmd-mode> wrappers (and strips any
 * marker that landed inside a tag attribute). Whitelisted image arrays get an
 * `_rmd_edit_path` key that rmd_image() turns into data attributes on the <img>.
 *
 * ZERO front-end impact: the whitelist global is only ever set inside the
 * admin-ajax preview endpoint (nonce + edit_post capability), and this module
 * enqueues assets on post-edit screens only.
 */
defined('ABSPATH') || exit;

/** Marker delimiters — U+27E6/U+27E7 pass through esc_html() untouched. */
define('RMD_EDIT_MARK_OPEN', "\u{27E6}rmd:");   // ⟦rmd:PATH⟧value
define('RMD_EDIT_MARK_MID', "\u{27E7}");
define('RMD_EDIT_MARK_CLOSE', "\u{27E6}/rmd\u{27E7}");

/**
 * Editable fields per layout — the single source of truth.
 * path pattern => mode. `*` matches one repeater row index.
 *   'text'  → edited as plain text (template escapes with esc_html)
 *   'html'  → light inline HTML allowed (template renders via rmd_inline_html,
 *             which re-sanitizes on output — nothing unsafe can ever render)
 *   'image' → ACF image array; swapped via the media library
 * Fields used in template LOGIC (background, style, columns, accent, anchor…),
 * SVG icons, chart data points and the `steps.*.items` line-split textareas are
 * deliberately absent — they can never be marked, so nothing can break.
 */
function rmd_edit_map($layout) {
	static $maps = null;
	if (null === $maps) {
		$maps = array(
			'hero' => array(
				'eyebrow' => 'text', 'kicker' => 'text', 'heading' => 'text',
				'heading_accent' => 'text', 'heading_after' => 'text',
				'subheading' => 'html', 'badge' => 'text',
				'tags.*.label' => 'text',
				'stats.*.value' => 'html', 'stats.*.label' => 'text',
			),
			'stats_band' => array(
				'eyebrow' => 'text', 'heading' => 'html', 'subheading' => 'html',
				'items.*.tag' => 'text', 'items.*.value' => 'html', // rendered via rmd_inline_html (stat-cell.php)
				'items.*.value_note' => 'text', 'items.*.label' => 'text',
			),
			'stat_cards' => array(
				'eyebrow' => 'text', 'heading' => 'html', 'subheading' => 'html',
				'cards.*.value' => 'text', 'cards.*.label' => 'text', 'cards.*.body' => 'html',
			),
			'feature_cards' => array(
				'eyebrow' => 'text', 'heading' => 'html', 'subheading' => 'html',
				'cards_kicker' => 'text', 'cards.*.heading' => 'text', 'cards.*.body' => 'html',
				'tiles_kicker' => 'text', 'tiles.*.value' => 'text', 'tiles.*.label' => 'text',
				'highlight' => 'html',
			),
			'numbered_steps' => array(
				'eyebrow' => 'text', 'heading' => 'html', 'subheading' => 'html',
				'steps.*.heading' => 'text',
			),
			'screenshot_gallery' => array(
				'eyebrow' => 'text', 'heading' => 'html', 'subheading' => 'html',
				'items.*.label' => 'text', 'items.*.caption' => 'html',
				'items.*.source' => 'text', 'items.*.image' => 'image',
			),
			'line_chart' => array(
				'chart_title' => 'text', 'chart_note' => 'text',
			),
			'table_split' => array(
				'eyebrow' => 'text', 'heading' => 'html', 'subheading' => 'html',
				'comment' => 'html', 'table_columns.*.label' => 'text',
				'table_rows.*.cells.*.content' => 'text', // rendered via esc_html (data-table.php)
				'side_stats.*.tag' => 'text', 'side_stats.*.value' => 'html', 'side_stats.*.label' => 'text', // value via rmd_inline_html (stat-cell.php)
				'media_caption' => 'html', 'media_source' => 'text', 'media_image' => 'image',
			),
			'recap_band' => array(
				'heading' => 'text', 'pills.*.text' => 'html',
			),
			'cta' => array(
				'eyebrow' => 'text', 'heading' => 'text', 'heading_accent' => 'text',
				'heading_after' => 'text', 'subheading' => 'html',
				'button_label' => 'text', 'contact_line' => 'text',
			),
		);
	}
	return isset($maps[$layout]) ? $maps[$layout] : array();
}

/** Mode for one concrete path ('tags.2.label') against a layout map, or ''. */
function rmd_edit_path_mode($path, $map) {
	foreach ($map as $pattern => $mode) {
		$regex = '/^' . str_replace(array('\*', '\.'), array('\d+', '\.'), preg_quote($pattern, '/')) . '$/';
		if (preg_match($regex, $path)) {
			return $mode;
		}
	}
	return '';
}

/**
 * Mark a value fetched by rmd_get_sub_field() in edit mode. Strings matching a
 * text/html pattern get wrapped in markers; image arrays matching an image
 * pattern get an `_rmd_edit_path` key; repeater arrays are walked recursively.
 * Anything not whitelisted passes through UNTOUCHED (so template logic on
 * background/style/columns etc. can never break).
 *
 * Draft overlay: when the endpoint sets $GLOBALS['rmd_edit_drafts'] (this row's
 * unpublished « Enregistrer » drafts, path → value), the draft value replaces
 * the saved one before marking — so a reopened preview shows the draft, while
 * the live site keeps rendering the saved value.
 */
function rmd_edit_mark_value($value, $path, $map) {
	$drafts = (isset($GLOBALS['rmd_edit_drafts']) && is_array($GLOBALS['rmd_edit_drafts'])) ? $GLOBALS['rmd_edit_drafts'] : array();

	if (is_string($value) || null === $value || false === $value) {
		$mode = rmd_edit_path_mode($path, $map);
		if (array_key_exists($path, $drafts) && ('text' === $mode || 'html' === $mode)) {
			$value = (string) $drafts[$path];
		}
		if (!is_string($value)) {
			return $value;
		}
		if (('text' === $mode || 'html' === $mode) && '' !== trim($value)) {
			return RMD_EDIT_MARK_OPEN . $path . RMD_EDIT_MARK_MID . $value . RMD_EDIT_MARK_CLOSE;
		}
		return $value;
	}
	if (is_array($value)) {
		if ('image' === rmd_edit_path_mode($path, $map) && isset($value['ID'])) {
			// A drafted image replaces the saved one (minimal ACF-like array).
			if (isset($drafts[$path])) {
				$draft_id = absint($drafts[$path]);
				if ($draft_id) {
					$value = array(
						'ID'  => $draft_id,
						'url' => (string) wp_get_attachment_url($draft_id),
						'alt' => (string) get_post_meta($draft_id, '_wp_attachment_image_alt', true),
					);
				}
			}
			$value['_rmd_edit_path'] = $path;
			return $value;
		}
		foreach ($value as $k => $v) {
			$value[$k] = rmd_edit_mark_value($v, $path . '.' . $k, $map);
		}
		return $value;
	}
	return $value;
}

/**
 * Convert markers in the rendered section HTML into editable spans.
 * Pass 1 strips any marker that landed inside a tag's ATTRIBUTES (alt, aria…)
 * so markup can never be corrupted; pass 2 wraps the remaining marked values;
 * pass 3 removes any stray marker as a belt-and-braces cleanup.
 */
function rmd_edit_annotate($html, $layout) {
	if (false === strpos($html, RMD_EDIT_MARK_OPEN)) {
		return $html;
	}
	$map = rmd_edit_map($layout);

	$o = preg_quote(RMD_EDIT_MARK_OPEN, '/');
	$m = preg_quote(RMD_EDIT_MARK_MID, '/');
	$c = preg_quote(RMD_EDIT_MARK_CLOSE, '/');

	// 1 · markers inside tags → keep the value text, drop the markers.
	$html = preg_replace_callback('/<[^>]+>/su', function ($tag) use ($o, $m, $c) {
		return preg_replace('/' . $o . '[^' . $m . ']*' . $m . '|' . $c . '/u', '', $tag[0]);
	}, $html);

	// 2 · marked text nodes → editable spans.
	$html = preg_replace_callback(
		'/' . $o . '([^' . $m . ']+)' . $m . '(.*?)' . $c . '/su',
		function ($hit) use ($map) {
			$mode = rmd_edit_path_mode($hit[1], $map);
			return '<span class="rmd-edit" data-rmd-path="' . esc_attr($hit[1]) . '" data-rmd-mode="' . esc_attr($mode ?: 'text') . '">' . $hit[2] . '</span>';
		},
		$html
	);

	// 3 · anything left over (unbalanced edge case) → plain text again.
	return preg_replace('/' . $o . '[^' . $m . ']*' . $m . '|' . $c . '/u', '', $html);
}

/* ─────────────────────────────────────────────────────────────────────────
 * Enqueue the inline editor — after the preview UI, same screens.
 * ───────────────────────────────────────────────────────────────────────── */
function rmd_visual_edit_assets($hook) {
	if ('post.php' !== $hook && 'post-new.php' !== $hook) {
		return;
	}
	if (!defined('RMD_ACF_ACTIVE') || !RMD_ACF_ACTIVE) {
		return;
	}

	wp_enqueue_media(); // media picker for image swaps

	$js = RMD_DIR . '/assets/admin/section-edit.js';
	wp_enqueue_script(
		'rmd-section-edit',
		RMD_URI . '/assets/admin/section-edit.js',
		array('rmd-section-preview'),
		file_exists($js) ? filemtime($js) : RMD_VERSION,
		true
	);

	// Existing (unpublished) drafts of this post — the JS prefills the ACF
	// fields with them on load, so the normal Update button publishes them.
	$edit_post_id = get_the_ID();
	if (!$edit_post_id && isset($_GET['post'])) {
		$edit_post_id = absint($_GET['post']);
	}
	$drafts = $edit_post_id ? get_post_meta($edit_post_id, '_rmd_section_drafts', true) : array();

	// Admin-language strings — FR admin → French, otherwise English (the same
	// rmd_is_fr() convention as the CPT labels; no .mo files needed).
	$fr = function_exists('rmd_is_fr') ? rmd_is_fr() : true;

	wp_localize_script('rmd-section-edit', 'rmdSectionEdit', array(
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonce'   => wp_create_nonce('rmd_section_save'),
		'drafts'  => is_array($drafts) ? $drafts : array(),
		'i18n' => array(
			'editNote'    => $fr ? 'Aperçu modifiable : cliquez sur un texte ou une image pour le modifier directement.'
								 : 'Editable preview: click any text or image to change it in place.',
			'editedHint'  => $fr ? 'Modifications non enregistrées — « Enregistrer » garde un brouillon, « Mettre à jour » publie sur le site. Fermer l’aperçu annule ces modifications.'
								 : 'Unsaved changes — "Save" keeps a draft, "Update" publishes to the live site. Closing the preview discards them.',
			'discardConfirm' => $fr ? "Cet aperçu contient des modifications non enregistrées.\n\nOK : fermer et annuler ces modifications.\nAnnuler : revenir à l’aperçu (puis cliquez sur « Enregistrer »)."
									: "This preview has unsaved changes.\n\nOK: close and discard them.\nCancel: go back to the preview (then click \"Save\").",
			'newSectionHint' => $fr ? 'Nouvelle section : le contenu d’exemple est déjà rempli — modifiez-le, puis « Enregistrer » l’ajoute à la page. Ensuite elle se modifie comme les autres sections.'
									: 'New section: the example content is already filled in — edit it, then "Save" adds it to the page. After that it edits like your other sections.',
			'savingSection' => $fr ? 'Ajout de la section à la page…' : 'Adding the section to the page…',
			'imageTitle'  => $fr ? 'Choisir une image' : 'Choose an image',
			'imageButton' => $fr ? 'Utiliser cette image' : 'Use this image',
			'save'        => $fr ? 'Enregistrer' : 'Save',
			'saving'      => $fr ? 'Enregistrement…' : 'Saving…',
			'saved'       => $fr ? 'Brouillon enregistré ✓' : 'Draft saved ✓',
			'draftSaved'  => $fr ? 'Brouillon enregistré — il ne sera publié sur le site qu’avec « Mettre à jour ».'
								 : 'Draft saved — it goes live only when you click "Update".',
			'saveError'   => $fr ? 'Échec de l’enregistrement — réessayez ou utilisez « Mettre à jour ».'
								 : 'Save failed — try again or use "Update".',
		),
	));
}
add_action('admin_enqueue_scripts', 'rmd_visual_edit_assets', 20);

/* ─────────────────────────────────────────────────────────────────────────
 * AJAX: save the edited fields of ONE section row — AS A DRAFT.
 *
 * Nothing here ever touches the live `sections_*` meta: the values land in the
 * hidden `_rmd_section_drafts` meta (keyed "<row>.<path>"), which only the
 * admin preview and the edit screen read. The live site changes ONLY when the
 * editor clicks the real « Mettre à jour » / Update button (the edit screen
 * prefills the ACF fields from the drafts, so a normal save publishes them —
 * and rmd_clear_section_drafts() then deletes the draft store).
 *
 * Three locks: (1) nonce + edit_post capability; (2) the path must be
 * whitelisted in rmd_edit_map() for the row's REAL layout (read from the saved
 * sections meta, never from the client); (3) only fields whose ACF key
 * reference already exists can be drafted. Values are sanitized by mode
 * (text → tags stripped, html → the same kses allowlist the front end renders
 * with, image → attachment ID).
 * ───────────────────────────────────────────────────────────────────────── */
function rmd_section_save() {
	check_ajax_referer('rmd_section_save');

	$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
	$row     = isset($_POST['row']) ? (int) $_POST['row'] : -1;
	if (!$post_id || $row < 0 || !current_user_can('edit_post', $post_id)) {
		wp_send_json_error(array('message' => 'forbidden'), 403);
	}
	if (!defined('RMD_ACF_ACTIVE') || !RMD_ACF_ACTIVE) {
		wp_send_json_error(array('message' => 'acf-off'), 400);
	}

	$changes = json_decode(wp_unslash(isset($_POST['changes']) ? $_POST['changes'] : ''), true);
	if (!is_array($changes) || !$changes) {
		wp_send_json_error(array('message' => 'no-changes'), 400);
	}

	// The row's real layout comes from the saved sections meta — never the client.
	$layouts = get_post_meta($post_id, 'sections', true);
	$layouts = is_array($layouts) ? array_values($layouts) : array();
	if (!isset($layouts[$row])) {
		wp_send_json_error(array('message' => 'row-not-found'), 400);
	}
	$map = rmd_edit_map((string) $layouts[$row]);

	$drafts = get_post_meta($post_id, '_rmd_section_drafts', true);
	$drafts = is_array($drafts) ? $drafts : array();

	$saved = 0;
	foreach ($changes as $path => $value) {
		$path = (string) $path;
		if (!preg_match('/^[a-z0-9_]+(?:\.[a-z0-9_]+)*$/', $path)) {
			continue;
		}
		$mode = rmd_edit_path_mode($path, $map);
		if ('' === $mode) {
			continue; // not editable for this layout
		}
		if ('image' === $mode) {
			$value = absint($value);
			if (!$value || 'attachment' !== get_post_type($value)) {
				continue;
			}
		} elseif ('html' === $mode) {
			$value = function_exists('rmd_inline_html') ? rmd_inline_html((string) $value) : wp_kses((string) $value, array('b' => array(), 'strong' => array(), 'br' => array(), 'span' => array('class' => true)));
		} else {
			$value = wp_strip_all_tags((string) $value);
		}

		// Lock 3: the field must already exist on this row (its ACF key reference
		// is saved) — a draft can never point at a field the row doesn't have.
		$meta_key = 'sections_' . $row . '_' . str_replace('.', '_', $path);
		if ('' === (string) get_post_meta($post_id, '_' . $meta_key, true)) {
			continue;
		}

		$drafts[$row . '.' . $path] = $value;
		$saved++;
	}

	if ($saved) {
		update_post_meta($post_id, '_rmd_section_drafts', $drafts);
	}
	wp_send_json_success(array('saved' => $saved));
}
add_action('wp_ajax_rmd_section_save', 'rmd_section_save');

/**
 * A real save (the Update button) publishes the drafts: the edit screen
 * prefilled the ACF fields from them, so WordPress just saved those values as
 * the live content — the draft store is now redundant. Delete it.
 * (Known edge, accepted: a save that bypasses the edit screen JS — quick edit,
 * WP-CLI — clears drafts without publishing them.)
 */
function rmd_clear_section_drafts($post_id) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	delete_post_meta($post_id, '_rmd_section_drafts');
}
add_action('save_post_case_study', 'rmd_clear_section_drafts');
