<?php
/**
 * Component: data table in a card.
 * $args: columns = [ ['label','highlight'] ], rows = [ ['cells' => [ ['content','is_win'] ]] ].
 */
defined('ABSPATH') || exit;

$columns = $args['columns'] ?? array();
$rows    = $args['rows'] ?? array();

if (!$rows) {
	return;
}

// Which column indexes carry the green "win" background.
$win_cols = array();
foreach ($columns as $i => $col) {
	if (!empty($col['highlight'])) {
		$win_cols[] = $i;
	}
}
?>
<div class="card tbl-card">
	<table>
		<?php if ($columns) : ?>
		<thead><tr>
			<?php foreach ($columns as $i => $col) : ?>
				<th<?php echo in_array($i, $win_cols, true) ? ' class="win-col"' : ''; ?>><?php echo esc_html($col['label'] ?? ''); ?></th>
			<?php endforeach; ?>
		</tr></thead>
		<?php endif; ?>
		<tbody>
			<?php foreach ($rows as $row) : ?>
			<tr>
				<?php foreach ((array) ($row['cells'] ?? array()) as $i => $cell) :
					$classes = array();
					if (in_array($i, $win_cols, true)) {
						$classes[] = 'win-col';
					}
					if (!empty($cell['is_win'])) {
						$classes[] = 'win';
					}
				?>
					<td<?php echo $classes ? ' class="' . esc_attr(implode(' ', $classes)) . '"' : ''; ?>><?php echo esc_html($cell['content'] ?? ''); ?></td>
				<?php endforeach; ?>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
