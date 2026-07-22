<?php
/**
 * Editor helpers — ported from the AMD project and adapted to RMD.
 *
 * The "library of sections" experience on the case_study editor:
 *   1. Live section preview (AMD §8) — the "Add Section" popup becomes a card
 *      grid, each card + each existing row gets an eye that opens a scaled-iframe
 *      preview. Demo mode renders example content; row mode renders saved values.
 *   2. Duplicate-section warning (AMD §7.2).
 *   3. Field hints — placeholders/instructions injected at runtime (acf/load_field)
 *      so every field guides the editor without hardcoding content.
 *
 * RMD adaptation of the AMD original: layout key === template file name (1:1, NO
 * "_section" suffix stripping); the field is `sections`; everything is prefixed
 * `rmd_`; all ACF access goes through the null-safe wrappers in inc/acf.php.
 *
 * Security (endpoint): nonce + capability (edit_post/edit_posts) + sanitize_key
 * + layout allowlist (path never built from raw input) + wp_ajax_ only (no nopriv)
 * + noindex + nocache. Enqueued ONLY on post.php / post-new.php when ACF is on.
 *
 * @package VaultChild
 */
defined('ABSPATH') || exit;

/* ─────────────────────────────────────────────────────────────────────────
 * 1. Layout registry — labels (from the ACF group), descriptions, allowlist.
 * ───────────────────────────────────────────────────────────────────────── */

/** One-line French description per section layout, shown under the preview. */
function rmd_layout_descriptions() {
	return array(
		'hero'               => 'Ouverture de l’étude de cas : titre, tags, badge, ligne contact et carte de stats (bloc navy).',
		'stats_band'         => 'Bandeau de chiffres clés sur fond navy — en bandeau pleine largeur (KPI) ou en carte arrondie.',
		'stat_cards'         => 'Grille de cartes chiffres — rouge pour le contexte/problème, vert pour les résultats.',
		'feature_cards'      => 'Panneau « insight » : cartes approche + tuiles de stats + bandeau preuve.',
		'numbered_steps'     => 'Méthode en étapes numérotées (01–04), puces une par ligne.',
		'screenshot_gallery' => 'Galerie de captures — cadre simple ou navigateur, 1 ou 2 colonnes, zoom lightbox.',
		'table_split'        => 'Tableau de données + colonne compagnon (stats latérales ou capture à gauche).',
		'line_chart'         => 'Courbe SVG rendue côté serveur à partir de points de données (ex. Domain Rating).',
		'recap_band'         => 'Bandeau récap sur fond navy avec une rangée de pills.',
		'cta'                => 'Appel à l’action final centré, avec bouton et ligne contact.',
	);
}

/**
 * layout name => { label, desc }. Labels read from the real ACF field group so
 * they never drift from the picker; falls back to a prettified name. Only layouts
 * with a real template file are exposed. Doubles as the security allowlist.
 */
function rmd_preview_layouts() {
	$desc   = rmd_layout_descriptions();
	$labels = array();

	if (function_exists('acf_get_field_group') && function_exists('acf_get_fields')) {
		$group = acf_get_field_group('group_rmd_case_study_sections');
		if ($group) {
			foreach ((array) acf_get_fields($group) as $field) {
				if (($field['name'] ?? '') === 'sections' && !empty($field['layouts'])) {
					foreach ((array) $field['layouts'] as $layout) {
						if (!empty($layout['name'])) {
							$labels[$layout['name']] = !empty($layout['label']) ? $layout['label'] : $layout['name'];
						}
					}
				}
			}
		}
	}

	$data  = array();
	$names = array_unique(array_merge(array_keys($desc), array_keys($labels)));
	foreach ($names as $name) {
		// 1:1 mapping — never build a path from raw input; must resolve to a file.
		if (!locate_template('template-parts/layouts/' . $name . '.php')) {
			continue;
		}
		$data[$name] = array(
			'label' => isset($labels[$name]) ? $labels[$name] : ucwords(str_replace('_', ' ', $name)),
			'desc'  => isset($desc[$name]) ? $desc[$name] : '',
		);
	}
	return $data;
}

/* ─────────────────────────────────────────────────────────────────────────
 * 2. Demo content — example values for the "Add Section" (demo) preview.
 *    Seeded into $GLOBALS['rmd_demo'], which rmd_get_sub_field() reads (inc/acf.php)
 *    so the REAL template renders a filled example. This is example/placeholder
 *    content, never a client's real data, and only ever runs in this endpoint.
 * ───────────────────────────────────────────────────────────────────────── */

/** A placeholder screenshot shaped like an ACF image array (url only, no ID). */
function rmd_demo_shot($alt = 'Aperçu de capture') {
	return array('ID' => 0, 'url' => RMD_URI . '/assets/admin/section-placeholder.svg', 'alt' => $alt);
}

/** Example sub-field values keyed by sub-field name, per layout. */
function rmd_section_demo($layout) {
	switch ($layout) {

		case 'hero':
			return array(
				'eyebrow'        => 'Étude de cas client · SEO',
				'kicker'         => 'Nom du client',
				'heading'        => 'Battre les géants en',
				'heading_accent' => 'première page de Google',
				'heading_after'  => '.',
				'subheading'     => 'Une phrase de contexte : le secteur, l’enjeu, et le résultat obtenu en une ligne.',
				'tags'           => array(
					array('icon' => '', 'label' => 'SEO'),
					array('icon' => '', 'label' => 'Contenu'),
					array('icon' => '', 'label' => 'Netlinking'),
					array('icon' => '', 'label' => 'SEO technique'),
				),
				'badge'          => 'Stratégie SEO · 2024 → 2026',
				'show_contact'   => true,
				'stats'          => array(
					array('value' => '#1', 'label' => 'sur votre requête cible'),
					array('value' => 'DR 25 <span class="unit">&rarr;</span> 55', 'label' => 'autorité de domaine ×2,2'),
					array('value' => '4,04M', 'label' => 'impressions Google · 13 mois'),
				),
			);

		case 'stats_band':
			return array(
				'style' => 'strip',
				'items' => array(
					array('value' => '60,4K', 'label' => 'Clics organiques (GSC)'),
					array('value' => '4,04M', 'label' => 'Impressions Google'),
					array('value' => '55', 'value_note' => '▲ vs 25', 'label' => 'Domain Rating'),
					array('value' => '157', 'label' => 'Mots-clés en Top 3'),
					array('value' => '58,9K€', 'label' => 'CA via Google organique', 'highlight' => true),
				),
			);

		case 'stat_cards':
			return array(
				'background' => 'light',
				'accent'     => 'negative',
				'eyebrow'    => 'Le contexte',
				'heading'    => 'Un marché dominé <span class="thin">par des géants</span>',
				'subheading' => 'Le point de départ : la difficulté, en une phrase.',
				'cards'      => array(
					array('icon' => '', 'value' => '#49', 'label' => 'Position au départ', 'body' => 'Sur une requête produit stratégique, le site était en <b>page 5 de Google</b>.'),
					array('icon' => '', 'value' => '25', 'label' => 'Domain Rating', 'body' => 'Une autorité de domaine <b>deux fois trop faible</b> pour rivaliser.'),
					array('icon' => '', 'value' => '4', 'label' => 'Géants en face', 'body' => 'Des concurrents aux <b>budgets marketing massifs</b> sur chaque requête.'),
				),
			);

		case 'feature_cards':
			return array(
				'eyebrow'      => 'L’insight',
				'heading'      => '« Prendre les requêtes <span class="thin">une par une »</span>',
				'subheading'   => 'L’angle stratégique qui a tout changé, expliqué simplement.',
				'cards_kicker' => 'Notre approche',
				'cards'        => array(
					array('icon' => '', 'heading' => 'Une page par requête', 'body' => 'Chaque famille de produits a sa page ciblée et optimisée.'),
					array('icon' => '', 'heading' => 'On-page & contenu', 'body' => 'Titres, maillage interne, textes qui répondent exactement à la recherche.'),
					array('icon' => '', 'heading' => 'Netlinking & autorité', 'body' => 'Des liens de qualité acquis dans la durée pour se classer plus vite.'),
				),
				'tiles_kicker' => 'Ce que ça a donné, chiffres à l’appui',
				'tiles'        => array(
					array('value' => '#49 → #1', 'label' => "requête cible\naoût 2024 → juin 2026"),
					array('value' => '157', 'label' => "mots-clés en Top 3\nsur les 250 plus porteurs"),
					array('value' => '×2,2', 'label' => "autorité de domaine\nDR 25 → 55"),
					array('value' => '99 %', 'label' => "des 250 mots-clés\nen première page"),
				),
				'highlight'    => 'Et à la clé : <b>58 884 € de CA via Google organique</b> sur 6 mois, sans publicité.',
			);

		case 'numbered_steps':
			return array(
				'background' => 'light',
				'eyebrow'    => 'Notre méthode',
				'heading'    => 'Une stratégie SEO <span class="thin">en 4 phases</span>',
				'steps'      => array(
					array('heading' => 'Audit technique & sémantique', 'items' => "Crawl, indexation, vitesse\nÉtude des mots-clés du marché\nBenchmark des concurrents\nPriorisation des requêtes"),
					array('heading' => 'On-page & pages collection', 'items' => "Une page par famille de produits\nMeta titles & descriptions\nMaillage interne\nStructure claire pour Google"),
					array('heading' => 'Contenu éditorial', 'items' => "Contenus ciblés sur les requêtes\nRéponses aux questions d’achat\nOptimisation continue\nRequêtes saisonnières"),
					array('heading' => 'Netlinking & autorité', 'items' => "Backlinks de qualité réguliers\nDomain Rating 25 → 55\nDomaines référents au pic\nSuivi mensuel des positions"),
				),
			);

		case 'screenshot_gallery':
			return array(
				'eyebrow'    => 'Résultats SEO',
				'heading'    => 'Première page <span class="thin">face aux géants</span>',
				'subheading' => 'Captures de résultats Google réels sur vos mots-clés business.',
				'columns'    => '2',
				'items'      => array(
					array('image' => rmd_demo_shot('SERP exemple'), 'style' => 'browser', 'label' => '« votre requête »', 'badge' => '#1', 'zoomable' => true),
					array('image' => rmd_demo_shot('SERP exemple'), 'style' => 'browser', 'label' => '« autre requête »', 'badge' => '#1', 'zoomable' => true),
				),
			);

		case 'table_split':
			return array(
				'background'    => 'light',
				'eyebrow'       => 'Positions Google',
				'heading'       => 'Les mots-clés <span class="thin">qui ramènent le trafic</span>',
				'subheading'    => 'Sur les 250 mots-clés les plus porteurs : 157 en Top 3.',
				'media_position' => 'none',
				'table_columns' => array(
					array('label' => 'Mot-clé', 'highlight' => false),
					array('label' => 'Volume', 'highlight' => false),
					array('label' => 'Avant', 'highlight' => false),
					array('label' => 'Après', 'highlight' => true),
				),
				'table_rows'    => array(
					array('cells' => array(array('content' => 'requête A'), array('content' => '200'), array('content' => '#49'), array('content' => '#1', 'is_win' => true))),
					array('cells' => array(array('content' => 'requête B'), array('content' => '2 300'), array('content' => '#9'), array('content' => '#1', 'is_win' => true))),
					array('cells' => array(array('content' => 'requête C'), array('content' => '800'), array('content' => 'n/a'), array('content' => '#7', 'is_win' => true))),
				),
				'side_stats'    => array(
					array('tag' => 'TOP 3', 'value' => '157', 'label' => 'mots-clés sur 250 en positions 1–3'),
					array('tag' => 'TOP 10', 'value' => '247', 'label' => 'soit 99 % en première page'),
				),
				'comment'       => '<b>De la page 5 à la position 1.</b> C’est le SEO qui transforme des pages en points d’entrée rentables.',
			);

		case 'line_chart':
			return array(
				'eyebrow'      => 'Autorité de domaine',
				'heading'      => 'Une autorité construite <span class="thin">dans la durée</span>',
				'chart_title'  => 'Domain Rating, juin 2024 → juin 2026',
				'chart_note'   => '(Ahrefs)',
				'value_prefix' => 'DR',
				'points'       => array(
					array('label' => 'juin 2024', 'value' => 25),
					array('label' => 'déc. 2024', 'value' => 34),
					array('label' => 'juin 2025', 'value' => 41),
					array('label' => 'déc. 2025', 'value' => 49),
					array('label' => 'juin 2026', 'value' => 55),
				),
			);

		case 'recap_band':
			return array(
				'heading' => 'Battre les géants, sans budget publicitaire.',
				'pills'   => array(
					array('text' => '<b>#1</b> sur la requête cible'),
					array('text' => 'Autorité : <b>DR 25 → 55</b>'),
					array('text' => '<b>157</b> mots-clés en Top 3'),
					array('text' => '<b>4,04M</b> impressions Google'),
				),
			);

		case 'cta':
			return array(
				'background'     => 'light',
				'eyebrow'        => 'Votre tour',
				'heading'        => 'Obtenez les',
				'heading_accent' => 'mêmes résultats',
				'heading_after'  => '.',
				'subheading'     => 'Votre marché aussi a ses géants. Parlons de votre visibilité Google.',
				'button_label'   => 'Discuter de mon projet',
				'button_url'     => '#',
				'contact_line'   => 'contact@exemple.com · +212 000-000000',
			);
	}
	return array();
}

/* ─────────────────────────────────────────────────────────────────────────
 * 3. Rendering — one saved row, or a demo, into a standalone document.
 * ───────────────────────────────────────────────────────────────────────── */

/**
 * Render an existing `sections` row with its SAVED values. Walks the flexible
 * rows to $row_index and renders inside that row's ACF context so the layout's
 * sub-field reads resolve. Guards get_row_layout() === $layout so a reordered-
 * but-unsaved field renders nothing (→ caller shows "save first") instead of the
 * wrong section. Returns '' when the row can't be resolved.
 */
function rmd_render_saved_section_row($post_id, $row_index, $layout) {
	if (!$post_id || !defined('RMD_ACF_ACTIVE') || !RMD_ACF_ACTIVE) {
		return '';
	}

	$html  = '';
	$index = 0;

	if (rmd_have_rows('sections', $post_id)) {
		while (rmd_have_rows('sections', $post_id)) {
			rmd_the_row();
			if ($index === $row_index) {
				if (rmd_get_row_layout() === $layout) {
					ob_start();
					// Pass the real row index so the preview's eager/lazy loading
					// matches the live page (a section-0 preview loads eager).
					get_template_part('template-parts/layouts/' . $layout, null, array('index' => $row_index));
					$html = trim(ob_get_clean());
				}
				break;
			}
			$index++;
		}
	}

	if (function_exists('reset_rows')) {
		reset_rows();
	}
	return $html;
}

/**
 * AJAX (logged-in only): output a standalone HTML document rendering one section
 * for the preview iframe.
 */
function rmd_render_section_preview() {
	check_ajax_referer('rmd_section_preview');

	$layout    = isset($_GET['layout']) ? sanitize_key(wp_unslash($_GET['layout'])) : '';
	$post_id   = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
	$row_index = isset($_GET['row']) && $_GET['row'] !== '' ? (int) $_GET['row'] : -1;

	$allowed = $post_id ? current_user_can('edit_post', $post_id) : current_user_can('edit_posts');
	if (!$allowed) {
		status_header(403);
		wp_die(esc_html__('Accès refusé.', 'vault-child'), '', array('response' => 403));
	}

	// Allowlist — the template path is never built from raw input.
	$layouts = rmd_preview_layouts();
	if (!isset($layouts[$layout])) {
		status_header(400);
		wp_die(esc_html__('Section inconnue.', 'vault-child'), '', array('response' => 400));
	}

	// Post context: hero/cta read per-site contact options; some may use the ID.
	if ($post_id) {
		$preview_post = get_post($post_id);
		if ($preview_post) {
			global $post;
			$post = $preview_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata($post);
		}
	}

	if ($row_index >= 0) {
		$section_html = rmd_render_saved_section_row($post_id, $row_index, $layout);
	} else {
		// Demo: no row exists yet — seed example content through the sub-field
		// wrapper so the real template renders a filled example.
		$GLOBALS['rmd_demo'] = rmd_section_demo($layout);
		ob_start();
		get_template_part('template-parts/layouts/' . $layout, null, array('index' => 0));
		$section_html = trim(ob_get_clean());
		unset($GLOBALS['rmd_demo']);
	}

	wp_reset_postdata();

	// A layout that renders nothing still emits whitespace; probe for real content.
	$probe = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', '', (string) $section_html);
	$probe = preg_replace('#<!--.*?-->#s', '', (string) $probe);
	$probe = trim(strip_tags((string) $probe, '<img><svg><iframe><input><button><video>'));
	$has_visible = ('' !== $probe);

	$main_css = RMD_DIR . '/assets/css/main.css';
	$main_js  = RMD_DIR . '/assets/js/main.js';
	$css_ver  = file_exists($main_css) ? filemtime($main_css) : RMD_VERSION;
	$js_ver   = file_exists($main_js) ? filemtime($main_js) : RMD_VERSION;

	nocache_headers();
	header('Content-Type: text/html; charset=utf-8');
	header('X-Frame-Options: SAMEORIGIN');
	header('X-Robots-Tag: noindex, nofollow');
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
	<?php if (file_exists($main_css)) : ?>
	<link rel="stylesheet" href="<?php echo esc_url(RMD_URI . '/assets/css/main.css?ver=' . $css_ver); ?>">
	<?php endif; ?>
	<style>
		html, body { margin: 0; padding: 0; background: #fff; }
		/* An isolated section has nothing above it; cancel a leading negative top
		   margin so it can't render off the top of the frame. The 1px padding also
		   blocks a child's top margin from collapsing through <body> (from main). */
		body > * > *:first-child { margin-top: 0 !important; }
		body { padding-top: 1px; }
		/* Nothing in a preview should navigate. */
		a { pointer-events: none; }
		.rmd-preview-empty { font-family: Poppins, system-ui, sans-serif; color: #596980; font-size: 14px; text-align: center; padding: 80px 24px; line-height: 1.6; }
		.rmd-preview-empty strong { display: block; color: #041135; margin-bottom: 6px; font-size: 15px; }
	</style>
</head>
<body>
<?php if ($has_visible) : ?>
	<main class="rmd-case"><?php echo $section_html; // phpcs:ignore WordPress.Security.EscapeOutput — theme template output ?></main>
<?php elseif ($row_index >= 0) : ?>
	<div class="rmd-preview-empty">
		<strong><?php echo esc_html__('Section non encore enregistrée', 'vault-child'); ?></strong>
		<?php echo esc_html__('L’aperçu affiche le contenu enregistré. Cliquez sur « Mettre à jour » puis rouvrez l’aperçu.', 'vault-child'); ?>
	</div>
<?php else : ?>
	<div class="rmd-preview-empty">
		<strong><?php echo esc_html__('Rien à afficher', 'vault-child'); ?></strong>
		<?php echo esc_html__('Cette section n’affiche du contenu qu’une fois ses champs remplis.', 'vault-child'); ?>
	</div>
<?php endif; ?>
	<?php if (file_exists($main_js)) : ?>
	<script src="<?php echo esc_url(RMD_URI . '/assets/js/main.js?ver=' . $js_ver); ?>" defer></script>
	<?php endif; ?>
</body>
</html>
	<?php
	exit;
}
add_action('wp_ajax_rmd_section_preview', 'rmd_render_section_preview');

/* ─────────────────────────────────────────────────────────────────────────
 * 4. Enqueue the preview UI — post.php / post-new.php only, ACF active.
 * ───────────────────────────────────────────────────────────────────────── */
function rmd_section_preview_assets($hook) {
	if ('post.php' !== $hook && 'post-new.php' !== $hook) {
		return;
	}
	if (!defined('RMD_ACF_ACTIVE') || !RMD_ACF_ACTIVE) {
		return;
	}

	$css = RMD_DIR . '/assets/admin/section-preview.css';
	$js  = RMD_DIR . '/assets/admin/section-preview.js';

	wp_enqueue_style('rmd-section-preview', RMD_URI . '/assets/admin/section-preview.css', array('dashicons'), file_exists($css) ? filemtime($css) : RMD_VERSION);
	wp_enqueue_script('rmd-section-preview', RMD_URI . '/assets/admin/section-preview.js', array(), file_exists($js) ? filemtime($js) : RMD_VERSION, true);

	$preview_post_id = get_the_ID();
	if (!$preview_post_id && isset($_GET['post'])) {
		$preview_post_id = absint($_GET['post']);
	}

	wp_localize_script('rmd-section-preview', 'rmdSectionPreview', array(
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonce'   => wp_create_nonce('rmd_section_preview'),
		'postId'  => $preview_post_id ? (int) $preview_post_id : 0,
		'layouts' => rmd_preview_layouts(),
		'i18n'    => array(
			'previewTitle' => __('Aperçu de la section', 'vault-child'),
			'insert'       => __('Insérer', 'vault-child'),
			'close'        => __('Fermer', 'vault-child'),
			'refresh'      => __('Rafraîchir', 'vault-child'),
			'loading'      => __('Chargement de l’aperçu…', 'vault-child'),
			'error'        => __('Impossible de charger l’aperçu.', 'vault-child'),
			'demoNotice'   => __('Aperçu de démonstration — le contenu réel dépendra de vos réglages.', 'vault-child'),
			'rowPreview'   => __('Aperçu de cette section avec son contenu enregistré.', 'vault-child'),
			'dirtyHint'    => __('Modifications non enregistrées : l’aperçu montre la dernière version enregistrée. Cliquez sur « Mettre à jour ».', 'vault-child'),
			'newRowHint'   => __('Cette section n’a jamais été enregistrée. Cliquez sur « Mettre à jour » puis rouvrez l’aperçu.', 'vault-child'),
		),
	));
}
add_action('admin_enqueue_scripts', 'rmd_section_preview_assets');

/* ─────────────────────────────────────────────────────────────────────────
 * 5. Duplicate-section warning (AMD §7.2) — non-blocking, dismissible.
 * ───────────────────────────────────────────────────────────────────────── */
function rmd_section_duplicate_notice() {
	?>
	<script>
	(function ($) {
		if (typeof acf === 'undefined') return;

		function scan(field) {
			var $rows = field.$el.find('.acf-flexible-content .values > .layout')
				.not('[data-id="acfcloneindex"]').not('.acf-clone');

			field.$el.find('.rmd-dup-note').remove();

			var seen = {};
			$rows.each(function () {
				var $row = $(this);
				var name = $row.attr('data-layout');
				if (!name) return;
				if (!seen[name]) { seen[name] = true; return; }
				if ($row.attr('data-rmd-dup-dismissed') === '1') return;

				var label = $row.attr('data-label') || name;
				var $note = $('<div class="rmd-dup-note" style="display:flex;align-items:flex-start;gap:10px;margin:8px 12px;padding:9px 12px;border-radius:6px;background:#fef3c7;border:1px solid #fde68a;color:#92400e;font-size:12.5px;font-weight:600;line-height:1.5;">' +
					'<span style="flex:1;">⚠ La section « ' + label + ' » est déjà utilisée sur cette page. Vous pouvez continuer, mais vérifiez que c\'est intentionnel.</span>' +
					'<button type="button" class="rmd-dup-dismiss" aria-label="Ignorer" style="border:0;background:none;color:#92400e;cursor:pointer;font-size:15px;line-height:1;padding:0 2px;">×</button>' +
					'</div>');

				$note.find('.rmd-dup-dismiss').on('click', function () {
					$row.attr('data-rmd-dup-dismissed', '1');
					$note.remove();
				});

				var $handle = $row.children('.acf-fc-layout-handle');
				if ($handle.length) { $handle.after($note); } else { $row.prepend($note); }
			});
		}

		function bind(field) {
			field.on('change', function () { scan(field); });
			scan(field);
		}

		acf.addAction('ready_field/key=field_rmd_cs_sections', bind);
		acf.addAction('append_field/key=field_rmd_cs_sections', bind);
	})(jQuery);
	</script>
	<?php
}
add_action('acf/input/admin_footer', 'rmd_section_duplicate_notice');

/* ─────────────────────────────────────────────────────────────────────────
 * 6. Field hints — placeholders/instructions injected at runtime.
 *    Fills ONLY empty hints (never overrides what the JSON already carries), so
 *    the JSON stays the source of truth and this just enriches the editor UX
 *    without a regenerate. Keyed by the field keys in acf-json (field_rmd_cs_*).
 * ───────────────────────────────────────────────────────────────────────── */
function rmd_field_hints() {
	// key => array('placeholder' => …, 'instructions' => …)
	return array(
		// The sections field itself — ownership note (spec §11.1).
		'field_rmd_cs_sections' => array('instructions' => 'Composez la page en ajoutant des sections. Ces sections appartiennent à cette étude de cas uniquement — les modifier ici n’affecte aucune autre page.'),
		// hero
		'field_rmd_cs_hero_kicker'          => array('placeholder' => 'Nom du client (affiché en rouge)'),
		'field_rmd_cs_hero_heading'         => array('placeholder' => 'Battre les géants en'),
		'field_rmd_cs_hero_heading_accent'  => array('placeholder' => 'première page de Google', 'instructions' => 'Partie du titre en dégradé (rouge → orange).'),
		'field_rmd_cs_hero_subheading'      => array('placeholder' => 'Le secteur, l’enjeu et le résultat, en une phrase.'),
		'field_rmd_cs_hero_stats_value'     => array('placeholder' => '#1', 'instructions' => 'HTML léger : <span class="unit">→</span> pour une flèche verte.'),
		'field_rmd_cs_hero_stats_label'     => array('placeholder' => 'sur « votre requête cible »'),
		// stats_band
		'field_rmd_cs_stats_band_items_value'      => array('placeholder' => '4,04M'),
		'field_rmd_cs_stats_band_items_label'      => array('placeholder' => 'Impressions Google (GSC)'),
		// stat_cards
		'field_rmd_cs_stat_cards_cards_value' => array('placeholder' => '#49'),
		'field_rmd_cs_stat_cards_cards_label' => array('placeholder' => 'POSITION AU DÉPART'),
		'field_rmd_cs_stat_cards_cards_body'  => array('placeholder' => 'Une phrase de contexte sur ce chiffre.'),
		// feature_cards
		'field_rmd_cs_feature_cards_cards_heading' => array('placeholder' => 'Une page par requête'),
		'field_rmd_cs_feature_cards_tiles_value'   => array('placeholder' => '#49 → #1'),
		'field_rmd_cs_feature_cards_highlight'     => array('placeholder' => 'Le résultat business en une phrase (bandeau vert).'),
		// numbered_steps
		'field_rmd_cs_numbered_steps_steps_heading' => array('placeholder' => 'Audit technique & sémantique'),
		'field_rmd_cs_numbered_steps_steps_items'   => array('instructions' => 'Une puce par ligne.'),
		// screenshot_gallery
		'field_rmd_cs_screenshot_gallery_items_image'   => array('instructions' => 'Capture PNG/JPG — SERP, Search Console, Ahrefs, Semrush…'),
		'field_rmd_cs_screenshot_gallery_items_label'   => array('placeholder' => '« votre requête »'),
		'field_rmd_cs_screenshot_gallery_items_caption' => array('placeholder' => 'Ce que montre la capture, en une ligne.'),
		// table_split
		'field_rmd_cs_table_split_table_columns_label'   => array('placeholder' => 'Mot-clé'),
		'field_rmd_cs_table_split_table_rows_cells_content' => array('placeholder' => '#1'),
		'field_rmd_cs_table_split_comment'               => array('placeholder' => 'Le commentaire qui interprète le tableau.'),
		'field_rmd_cs_table_split_media_image'           => array('instructions' => 'Capture affichée à gauche du tableau.'),
		// line_chart
		'field_rmd_cs_line_chart_points_label' => array('placeholder' => 'juin 2024'),
		'field_rmd_cs_line_chart_points_value' => array('placeholder' => '25'),
		// recap_band
		'field_rmd_cs_recap_band_heading'    => array('placeholder' => 'La promesse tenue, en une phrase.'),
		'field_rmd_cs_recap_band_pills_text' => array('placeholder' => '<b>#1</b> sur la requête cible'),
		// cta
		'field_rmd_cs_cta_heading'        => array('placeholder' => 'Obtenez les'),
		'field_rmd_cs_cta_heading_accent' => array('placeholder' => 'mêmes résultats'),
		'field_rmd_cs_cta_subheading'     => array('placeholder' => 'Une phrase qui invite à la prise de contact.'),
	);
}

add_filter('acf/load_field', function ($field) {
	// Placeholders/instructions are editor-only — skip the work on the front end.
	if (!is_admin() || empty($field['key'])) {
		return $field;
	}
	// acf/load_field fires for every field on every load — cache the map once.
	static $hints = null;
	if (null === $hints) {
		$hints = rmd_field_hints();
	}
	if (!isset($hints[$field['key']])) {
		return $field;
	}
	$hint = $hints[$field['key']];
	// Fill only what the JSON left empty — never override authored hints.
	if (!empty($hint['placeholder']) && empty($field['placeholder'])) {
		$field['placeholder'] = $hint['placeholder'];
	}
	if (!empty($hint['instructions']) && empty($field['instructions'])) {
		$field['instructions'] = $hint['instructions'];
	}
	return $field;
});
