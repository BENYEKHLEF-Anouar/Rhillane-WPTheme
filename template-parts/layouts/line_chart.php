<?php
/**
 * Section: line_chart — server-rendered SVG curve from editor data points.
 * No JS: the polyline/area/annotations are computed here in PHP.
 */
defined('ABSPATH') || exit;

$points = rmd_get_sub_field('points');

if (!$points || count($points) < 2) {
	return;
}

$chart_title = rmd_get_sub_field('chart_title');
$chart_note  = rmd_get_sub_field('chart_note');
$prefix      = rmd_get_sub_field('value_prefix');

// ── chart math (viewBox 620×230, plot x 10→610, y 210→40) ──
$values = array_map(static fn($p) => (float) ($p['value'] ?? 0), $points);
$min    = min($values);
$max    = max($values);
$pad    = max(($max - $min) * 0.1, 1);
$lo     = $min - $pad;
$hi     = $max + $pad;
$n      = count($values);

$coords = array();
foreach ($values as $i => $v) {
	$x        = 10 + ($n > 1 ? $i * (600 / ($n - 1)) : 0);
	$y        = 210 - (($v - $lo) / ($hi - $lo)) * 170;
	$coords[] = round($x, 1) . ',' . round($y, 1);
}
$line = implode(' ', $coords);
$area = $line . ' 610,220 10,220';

list($x0, $y0) = array_map('floatval', explode(',', $coords[0]));
list($xn, $yn) = array_map('floatval', explode(',', $coords[$n - 1]));

$first_label = trim(($prefix ? $prefix . ' ' : '') . rtrim(rtrim(number_format($values[0], 1, ',', ' '), '0'), ','));
$last_label  = trim(($prefix ? $prefix . ' ' : '') . rtrim(rtrim(number_format($values[$n - 1], 1, ',', ' '), '0'), ','));
$aria        = sprintf('Courbe de %s à %s', $first_label, $last_label);

rmd_section_open();
get_template_part('template-parts/components/section-header', null, array(
	'eyebrow'    => rmd_get_sub_field('eyebrow'),
	'heading'    => rmd_get_sub_field('heading'),
	'subheading' => rmd_get_sub_field('subheading'),
));
?>
<div class="card chartcard mt-6">
	<?php if ($chart_title) : ?>
	<div class="text-sm font-semibold"><?php echo esc_html($chart_title); ?><?php if ($chart_note) : ?> <span class="text-slight font-normal"><?php echo esc_html($chart_note); ?></span><?php endif; ?></div>
	<?php endif; ?>
	<svg viewBox="0 0 620 230" class="mt-4 w-full" role="img" aria-label="<?php echo esc_attr($aria); ?>">
		<polyline points="<?php echo esc_attr($line); ?>" fill="none" stroke="#3943FF" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
		<polygon points="<?php echo esc_attr($area); ?>" fill="rgba(57,67,255,0.06)" stroke="none"/>
		<circle cx="<?php echo esc_attr($x0); ?>" cy="<?php echo esc_attr($y0); ?>" r="4" fill="#3943FF"/>
		<circle cx="<?php echo esc_attr($xn); ?>" cy="<?php echo esc_attr($yn); ?>" r="4" fill="#16B364"/>
		<text x="<?php echo esc_attr($x0 + 4); ?>" y="<?php echo esc_attr(max($y0 - 10, 14)); ?>" font-size="13" font-weight="600" fill="#596980"><?php echo esc_html($first_label); ?></text>
		<text x="<?php echo esc_attr($xn); ?>" y="<?php echo esc_attr(max($yn - 10, 14)); ?>" text-anchor="end" font-size="13" font-weight="700" fill="#16B364"><?php echo esc_html($last_label); ?></text>
	</svg>
	<div class="cl">
		<span><?php echo esc_html($points[0]['label'] ?? ''); ?></span>
		<span><?php echo esc_html($points[$n - 1]['label'] ?? ''); ?></span>
	</div>
</div>
<?php
rmd_section_close();
