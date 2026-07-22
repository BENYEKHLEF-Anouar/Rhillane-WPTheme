<?php
/**
 * Editor UX — live section preview, ported from the AMD project
 * (Agence-Marketing-Digital-WPTheme). No ACF Extended: this is our own code.
 *
 * How it works: every layout in ACF's "Ajouter une section" popup and every
 * inserted row gets an eye button. Clicking it opens a modal with a scaled
 * same-origin iframe rendering the REAL section template via admin-ajax —
 * the preview can never go stale. Existing rows preview their saved values;
 * popup previews render a generic demo (sections that render nothing until
 * configured show a notice instead).
 *
 * RMD adaptations vs AMD: layout key === template file 1:1 (no "_section"
 * suffix strip) · field name `sections` · group group_rmd_case_study_sections ·
 * output wrapped in <main class="rmd-case"> so the scoped CSS applies ·
 * no GSAP/Lucide (this design uses neither).
 */
defined('ABSPATH') || exit;

/** Human descriptions for each layout, shown on the picker cards. */
function rmd_layout_descriptions() {
	return array(
		'hero'               => 'Hero d\'ouverture : titre avec dégradé, tags, badge et carte de stats navy.',
		'stats_band'         => 'Bandeau de chiffres clés — pleine largeur (KPI) ou carte arrondie (statduo).',
		'stat_cards'         => 'Cartes chiffres avec icônes — contexte (rouge) ou résultats (vert).',
		'feature_cards'      => 'Panneau insight : cartes d\'approche, tuiles de stats et bandeau preuve.',
		'numbered_steps'     => 'Méthode en étapes numérotées 01–04, listes à puces.',
		'screenshot_gallery' => 'Galerie de captures : cadres navigateur, défilement interne, zoom lightbox.',
		'table_split'        => 'Tableau de données + stats latérales ou capture, avec commentaire.',
		'line_chart'         => 'Courbe SVG (ex. Domain Rating) tracée depuis des points de données.',
		'recap_band'         => 'Bandeau récap sombre avec rangée de pills.',
		'cta'                => 'Appel à l\'action final centré avec bouton dégradé.',
	);
}

/**
 * layout name => {label, desc}. Labels are read from the real ACF field group
 * so they never drift from the picker. Doubles as the security allowlist.
 */
function rmd_layout_preview_data() {
	$descriptions = rmd_layout_descriptions();
	$labels       = array();

	if (function_exists('acf_get_field_group') && function_exists('acf_get_fields')) {
		$group = acf_get_field_group('group_rmd_case_study_sections');
		if ($group) {
			foreach ((array) acf_get_fields($group) as $field) {
				if (isset($field['name'], $field['layouts']) && 'sections' === $field['name']) {
					foreach ((array) $field['layouts'] as $layout) {
						if (!empty($layout['name'])) {
							$labels[$layout['name']] = !empty($layout['label']) ? $layout['label'] : $layout['name'];
						}
					}
				}
			}
		}
	}

	$data = array();
	foreach (array_unique(array_merge(array_keys($descriptions), array_keys($labels))) as $name) {
		// Only expose layouts that have a real template file behind them.
		// RMD convention: layout key === file name, no transform.
		if (!locate_template('template-parts/layouts/' . $name . '.php')) {
			continue;
		}
		$data[$name] = array(
			'label' => isset($labels[$name]) ? $labels[$name] : ucwords(str_replace('_', ' ', $name)),
			'desc'  => isset($descriptions[$name]) ? $descriptions[$name] : '',
		);
	}
	return $data;
}

/**
 * Render an existing `sections` row with its SAVED values, inside that row's
 * ACF context so the layout's rmd_get_sub_field() calls resolve.
 * Returns '' when the row can't be resolved (unsaved row, reorder drift).
 */
function rmd_render_saved_row($post_id, $row_index, $layout) {
	if (!$post_id || !RMD_ACF_ACTIVE) {
		return '';
	}

	$html  = '';
	$index = 0;

	if (have_rows('sections', $post_id)) {
		while (have_rows('sections', $post_id)) {
			the_row();

			if ($index === $row_index) {
				// Guard against drift between the DOM order and saved order.
				if (get_row_layout() === $layout) {
					ob_start();
					get_template_part('template-parts/layouts/' . $layout, null, array('index' => $row_index));
					$html = trim(ob_get_clean());
				}
				break;
			}
			$index++;
		}
	}

	// Leave ACF's row stack clean for anything that runs after us.
	if (function_exists('reset_rows')) {
		reset_rows();
	}

	return $html;
}

/**
 * AJAX (logged-in only): output a standalone HTML document rendering one
 * section, for the preview iframe.
 */
function rmd_render_section_preview() {
	check_ajax_referer('rmd_section_preview');

	$layout    = isset($_GET['layout']) ? sanitize_key(wp_unslash($_GET['layout'])) : '';
	$post_id   = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
	$row_index = isset($_GET['row']) && '' !== $_GET['row'] ? (int) $_GET['row'] : -1;

	// Capability: tied to the post being edited when we know it.
	$allowed_user = $post_id ? current_user_can('edit_post', $post_id) : current_user_can('edit_posts');
	if (!$allowed_user) {
		status_header(403);
		wp_die(esc_html__('Accès refusé.', 'vault-child'), '', array('response' => 403));
	}

	// Allowlist — never build a template path from raw input.
	$layouts = rmd_layout_preview_data();
	if (!isset($layouts[$layout])) {
		status_header(400);
		wp_die(esc_html__('Section inconnue.', 'vault-child'), '', array('response' => 400));
	}

	// Give the layout a post context: templates may call get_the_ID() etc.
	if ($post_id) {
		$preview_post = get_post($post_id);
		if ($preview_post) {
			global $post;
			$post = $preview_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata($post);
		}
	}

	if ($row_index >= 0) {
		$section_html = rmd_render_saved_row($post_id, $row_index, $layout);
	} else {
		// "Add Row" popup: no row exists yet — sections that render nothing
		// until configured will fall through to the notice below.
		ob_start();
		get_template_part('template-parts/layouts/' . $layout, null, array('index' => 0));
		$section_html = trim(ob_get_clean());
	}

	wp_reset_postdata();

	// A non-empty string may still be invisible (only <style>/<script>).
	// Probe for something actually renderable before trusting it.
	$probe               = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', '', $section_html);
	$probe               = preg_replace('#<!--.*?-->#s', '', (string) $probe);
	$probe               = trim(strip_tags((string) $probe, '<img><svg><iframe><input><button><video>'));
	$has_visible_content = ('' !== $probe);

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
	<link rel="stylesheet" href="<?php echo esc_url(RMD_URI . '/style.css?ver=' . rmd_asset_ver('style.css')); ?>">
	<link rel="stylesheet" href="<?php echo esc_url(RMD_URI . '/assets/css/main.css?ver=' . rmd_asset_ver('assets/css/main.css')); ?>">
	<style>
		html, body { margin: 0; padding: 0; background: #fff; }
		/* A section previewed in isolation must not tuck under a section that
		   isn't there — cancel any leading negative top margin. */
		body > main > *:first-child { margin-top: 0 !important; }
		body { padding-top: 1px; }
		.rmd-preview-empty {
			font-family: Poppins, system-ui, sans-serif;
			color: #596980;
			font-size: 14px;
			text-align: center;
			padding: 80px 24px;
			line-height: 1.6;
		}
		.rmd-preview-empty strong { display: block; color: #041135; margin-bottom: 6px; font-size: 15px; }
		/* Nothing in a preview should navigate. */
		a { pointer-events: none; }
	</style>
</head>
<body class="antialiased">
<?php if ($has_visible_content) : ?>
	<main class="rmd-case"><?php echo $section_html; // phpcs:ignore WordPress.Security.EscapeOutput — theme template output ?></main>
<?php elseif ($row_index >= 0) : ?>
	<div class="rmd-preview-empty">
		<strong><?php echo esc_html__('Section non encore enregistrée', 'vault-child'); ?></strong>
		<?php echo esc_html__('L\'aperçu affiche le contenu enregistré. Cliquez sur « Mettre à jour » puis rouvrez l\'aperçu.', 'vault-child'); ?>
	</div>
<?php else : ?>
	<div class="rmd-preview-empty">
		<strong><?php echo esc_html__('Rien à afficher pour le moment', 'vault-child'); ?></strong>
		<?php echo esc_html__('Cette section n\'affiche du contenu qu\'une fois configurée. Insérez-la, remplissez ses champs, enregistrez — l\'aperçu montrera alors son rendu réel.', 'vault-child'); ?>
	</div>
<?php endif; ?>
<script src="<?php echo esc_url(RMD_URI . '/assets/js/main.js?ver=' . rmd_asset_ver('assets/js/main.js')); ?>" defer></script>
</body>
</html>
	<?php
	exit;
}
add_action('wp_ajax_rmd_section_preview', 'rmd_render_section_preview');

/**
 * Page-builder category for the picker's default grouping. Our layout labels
 * carry no "[Catégorie]" prefix yet, so '' = show every section. Filterable
 * for when more CPTs share the library and grouping becomes useful.
 */
function rmd_page_builder_category($post_id) {
	return apply_filters('rmd_page_builder_category', '', $post_id);
}

/** Load the preview modal assets on the post edit screens only. */
function rmd_admin_section_preview_assets($hook) {
	if ('post.php' !== $hook && 'post-new.php' !== $hook) {
		return;
	}
	if (!RMD_ACF_ACTIVE) {
		return;
	}

	wp_enqueue_style(
		'rmd-section-preview',
		RMD_URI . '/assets/admin/section-preview.css',
		array('dashicons'),
		rmd_asset_ver('assets/admin/section-preview.css')
	);

	wp_enqueue_script(
		'rmd-section-preview',
		RMD_URI . '/assets/admin/section-preview.js',
		array(),
		rmd_asset_ver('assets/admin/section-preview.js'),
		true
	);

	// Post context for the preview. get_the_ID() is unreliable on
	// post-new.php, so fall back to the ?post= query arg.
	$preview_post_id = get_the_ID();
	if (!$preview_post_id && isset($_GET['post'])) {
		$preview_post_id = absint($_GET['post']);
	}

	wp_localize_script('rmd-section-preview', 'rmdSectionPreview', array(
		'ajaxUrl'      => admin_url('admin-ajax.php'),
		'nonce'        => wp_create_nonce('rmd_section_preview'),
		'postId'       => $preview_post_id ? (int) $preview_post_id : 0,
		'layouts'      => rmd_layout_preview_data(),
		'pageCategory' => rmd_page_builder_category($preview_post_id),
		'i18n'         => array(
			'previewTitle'     => __('Aperçu de la section', 'vault-child'),
			'insert'           => __('Insérer cette section', 'vault-child'),
			'close'            => __('Fermer', 'vault-child'),
			'loading'          => __('Chargement de l\'aperçu…', 'vault-child'),
			'error'            => __('Impossible de charger l\'aperçu.', 'vault-child'),
			'demoNotice'       => __('Aperçu de démonstration — le contenu réel dépendra de vos réglages.', 'vault-child'),
			'rowPreview'       => __('Aperçu de cette section avec son contenu enregistré.', 'vault-child'),
			'dirtyHint'        => __('Modifications non enregistrées : l\'aperçu montre la dernière version enregistrée. Cliquez sur « Mettre à jour » pour les voir.', 'vault-child'),
			'newRowHint'       => __('Cette section n\'a jamais été enregistrée. Cliquez sur « Mettre à jour » puis rouvrez l\'aperçu.', 'vault-child'),
			'refresh'          => __('Rafraîchir', 'vault-child'),
			'showOther'        => __('Afficher les autres sections', 'vault-child'),
			'hideOther'        => __('Masquer les autres sections', 'vault-child'),
			'groupPage'        => __('Sections de cette page', 'vault-child'),
			'groupCommon'      => __('Sections communes', 'vault-child'),
			'groupOtherCommon' => __('Autres sections communes', 'vault-child'),
			'groupOther'       => __('Sections des autres pages', 'vault-child'),
			'used'             => __('Utilisée', 'vault-child'),
			/* translators: %d: how many times the section already appears on this page. */
			'usedTimes'        => __('Déjà utilisée %d fois sur cette page', 'vault-child'),
			'usedOnce'         => __('Déjà utilisée sur cette page', 'vault-child'),
		),
	));
}
add_action('admin_enqueue_scripts', 'rmd_admin_section_preview_assets');
