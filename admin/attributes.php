<?php
/* ==========================================================================
	Admin: Characteristics (details of listings: attrs.php, migration 036).
	Admins only.
	Two tabs:
	  fields (default)  the list of every characteristic + its form:
	                    GET edit = id | new
	  categories        the tree of subcategories + the characteristics of one:
	                    GET cat = slug of a subcategory
	POST here (csrf), action:
	  attr_save    id (empty = new); code, type (new only: never change — the
	               values in listings.attrs depend on them); name_en, name_srl,
	               name_src (empty = from the Latin); sort, in_filter; by type:
	                 number  min, max, max_next_year, step, unit_en/srl/src
	                 text    max, raw (a name: never put into Cyrillic)
	                 bool    switch, row_en/srl/src + off_en/srl/src (both or none)
	                 select  options[n][key (existing), en, srl, src, sort, hidden, delete]
	  attr_delete  id — only when no listing holds a value of it
	  cat_save     category_id, condition; rows[attribute id][sort, deals[],
	               options[], remove]; then, by the button: add (attribute id)
	               or copy (slug of another subcategory: its missing ones)
	Listings keep their values: a characteristic taken out of a category or an
	option hidden still shows on the listings that have it.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

if (!is_admin()) {
	flash('danger', at('attr.admins_only'));
	redirect('index.php');
}

$pageTitle = at('attr.title');
$activeNav = 'attributes';

const ATTR_TYPES = ['bool', 'select', 'number', 'text'];

// Names from the form: prefix_en, prefix_srl, prefix_src (empty Cyrillic = from the Latin)
function attr_post_names(string $prefix, int $max = 100): array
{
	$clean = fn(string $key) => trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($_POST[$key] ?? '')), 0, $max));
	$names = ['en' => $clean($prefix . '_en'), 'sr-latn' => $clean($prefix . '_srl'), 'sr-cyrl' => $clean($prefix . '_src')];
	$names['sr-cyrl'] = $names['sr-cyrl'] !== '' ? $names['sr-cyrl'] : sr_cyr($names['sr-latn']);

	return $names;
}

// Code from a name: "Fixed price" → fixed_price
function attr_code_from(string $text): string
{
	$code = substr(str_replace('-', '_', slugify($text)), 0, 32);

	return preg_match('/^[a-z]/', $code) ? rtrim($code, '_') : '';
}

// "2,5" → 2.5, "" → null, "abc" → false
function attr_post_number(string $key): int|float|false|null
{
	$raw = str_replace([',', ' '], ['.', ''], trim((string) ($_POST[$key] ?? '')));
	if ($raw === '') {
		return null;
	}
	if (!preg_match('/^-?\d{1,9}(\.\d{1,2})?$/', $raw)) {
		return false;
	}

	return $raw == (int) $raw ? (int) $raw : (float) $raw;
}

function attr_by_id(int $id): ?array
{
	foreach (attr_fields() as $field) {
		if ($field['id'] === $id) {
			return $field;
		}
	}

	return null;
}

// Subcategory by id: [parent, child] or [null, null]
function attr_subcategory(int $id): array
{
	foreach (categories() as $parent) {
		foreach ($parent['children'] as $child) {
			if ($child['id'] === $id) {
				return [$parent, $child];
			}
		}
	}

	return [null, null];
}

// Characteristic form → [record for save_attribute(), error]. $existing: the field (attr_fields()) or null
function attr_from_post(?array $existing): array
{
	$type = $existing['type'] ?? (string) ($_POST['type'] ?? '');
	$names = attr_post_names('name');
	$code = $existing['key'] ?? (strtolower(trim((string) ($_POST['code'] ?? ''))) ?: attr_code_from($names['en']));
	$f = [
		'code' => $code, 'type' => $type, 'names' => $names, 'config' => [],
		'filter' => $type !== 'text' && !empty($_POST['in_filter']),
		'sort' => max(0, min(9999, (int) ($_POST['sort'] ?? 0))),
	];

	if (!in_array($type, ATTR_TYPES, true)) {
		return [$f, at('attr.err_type')];
	}
	if (mb_strlen($names['en']) < 2 || mb_strlen($names['sr-latn']) < 2) {
		return [$f, at('attr.err_names')];
	}
	if (!$existing) {
		if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $code) || in_array($code, ATTR_RESERVED, true)) {
			return [$f, at('attr.err_code')];
		}
		if (isset(attr_fields()[$code])) {
			return [$f, at('attr.err_code_taken', ['code' => $code])];
		}
	}

	$c = [];
	if ($type === 'number') {
		[$min, $max, $step] = [attr_post_number('min'), attr_post_number('max'), attr_post_number('step')];
		$nextYear = !empty($_POST['max_next_year']);
		$top = $nextYear ? (int) date('Y') + 1 : $max;
		if ($min === null || $min === false || $max === false || $step === false || (!$nextYear && $max === null)) {
			return [$f, at('attr.err_number')];
		}
		if ($top <= $min || ($step !== null && ($step <= 0 || $step > $top - $min))) {
			return [$f, at('attr.err_range')];
		}
		$c = ['min' => $min] + ($nextYear ? ['max_next_year' => true] : ['max' => $max]) + ($step !== null && $step != 1 ? ['step' => $step] : []);
		$unit = attr_post_names('unit', 20);
		if ($unit['en'] !== '' || $unit['sr-latn'] !== '') {
			$c['unit'] = ['en' => $unit['en'] ?: $unit['sr-latn'], 'sr-latn' => $unit['sr-latn'] ?: $unit['en'], 'sr-cyrl' => $unit['sr-cyrl'] ?: $unit['en']];
		}
	} elseif ($type === 'text') {
		$max = (int) ($_POST['max'] ?? 0);
		if ($max < 1 || $max > 200) {
			return [$f, at('attr.err_text_max')];
		}
		$c = ['max' => $max] + (!empty($_POST['raw']) ? ['raw' => true] : []);
	} elseif ($type === 'bool') {
		$row = attr_post_names('row');
		$off = attr_post_names('off');
		$filled = array_filter([$row['en'], $row['sr-latn'], $off['en'], $off['sr-latn']], fn($v) => $v !== '');
		if ($filled && count($filled) < 4) {
			return [$f, at('attr.err_two_sided')];
		}
		$c = (!empty($_POST['switch']) ? ['switch' => true] : []) + ($filled ? ['row' => $row, 'off' => $off] : []);
	} else {
		// A list: the rows in their order; an existing option keeps its key, a new one gets it from the name
		$raw = $existing['raw'] ?? false;
		$was = $existing['options'] ?? [];
		$used = $existing ? attribute_value_counts($existing['key']) : [];
		$options = [];
		$rows = array_filter((array) ($_POST['options'] ?? []), 'is_array');
		uasort($rows, fn($a, $b) => (int) (is_scalar($a['sort'] ?? null) ? $a['sort'] : 0) <=> (int) (is_scalar($b['sort'] ?? null) ? $b['sort'] : 0));

		foreach ($rows as $row) {
			$clean = fn(string $key) => trim(mb_substr(preg_replace('/\s+/u', ' ', is_scalar($row[$key] ?? null) ? (string) $row[$key] : ''), 0, 100));
			$names = ['en' => $clean('en'), 'sr-latn' => $clean('srl'), 'sr-cyrl' => $clean('src')];
			$key = is_scalar($row['key'] ?? null) ? (string) $row['key'] : '';
			$isOld = $key !== '' && isset($was[$key]);

			if ($isOld && !empty($row['delete'])) {
				if ($n = (int) ($used[$raw ? $key : 'v.' . $key] ?? 0)) {
					return [$f, at('attr.err_option_used', ['name' => $was[$key]['names']['en'], 'n' => $n])];
				}
				continue;
			}
			if (!$isOld) {
				if ($names['en'] === '' && $names['sr-latn'] === '') {
					continue;
				}
				$key = $raw ? $names['en'] : attr_code_from($names['en'] ?: $names['sr-latn']);
				if ($key === '' || !preg_match($raw ? '/^[A-Za-z0-9+\-.,\/]{1,32}$/' : '/^[a-z][a-z0-9_]{0,31}$/', $key)) {
					return [$f, at('attr.err_option_key', ['name' => $names['en'] ?: $names['sr-latn']])];
				}
			}
			if ($names['en'] === '' || $names['sr-latn'] === '') {
				return [$f, at('attr.err_option_names')];
			}
			if (isset($options[$key])) {
				return [$f, at('attr.err_option_twice', ['name' => $names['en']])];
			}
			$names['sr-cyrl'] = $names['sr-cyrl'] !== '' ? $names['sr-cyrl'] : sr_cyr($names['sr-latn']);
			$options[$key] = ['key' => $key, 'names' => $names] + (!empty($row['hidden']) ? ['hidden' => true] : []);
		}
		if (!$options) {
			return [$f, at('attr.err_no_options')];
		}
		$c = ($raw ? ['raw' => true] : []) + ['options' => array_values($options)];
	}
	$f['config'] = $c;

	return [$f, ''];
}

$error = '';
$posted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = (string) ($_POST['action'] ?? '');

	if (!csrf_valid()) {
		flash('danger', at('common.form_expired'));
		redirect('attributes.php');
	}

	if ($action === 'attr_save' || $action === 'attr_delete') {
		$id = (int) ($_POST['id'] ?? 0) ?: null;
		$existing = $id ? attr_by_id($id) : null;
		if ($id && !$existing) {
			flash('danger', at('attr.gone'));
			redirect('attributes.php');
		}

		if ($action === 'attr_delete') {
			if (delete_attribute($existing['id'], $existing['key'])) {
				log_action('admin.attribute_delete', ['attribute_id' => $existing['id'], 'code' => $existing['key']]);
				flash('success', at('attr.deleted', ['name' => $existing['names']['en']]));
				redirect('attributes.php');
			}
			flash('danger', at('attr.delete_blocked', ['name' => $existing['names']['en'], 'n' => attribute_listing_count($existing['key'])]));
			redirect('attributes.php?edit=' . $existing['id'] . '#attr-form');
		}

		[$f, $error] = attr_from_post($existing);
		if ($error === '') {
			$savedId = save_attribute($id, $f);
			$changed = $existing ? array_keys(array_filter([
				'names' => $f['names'] != $existing['names'], 'settings' => $f['config'] != $existing['config'],
				'filter' => $f['filter'] !== $existing['filter'], 'sort' => $f['sort'] !== $existing['sort'],
			])) : [];
			log_action($existing ? 'admin.attribute_update' : 'admin.attribute_create', ['attribute_id' => $savedId, 'code' => $f['code']] + ($changed ? ['fields' => $changed] : []));
			flash('success', $existing ? ($changed ? at('common.saved_fields', ['fields' => field_labels($changed)]) : at('common.nothing_changed')) : at('attr.created', ['name' => $f['names']['en']]));
			redirect('attributes.php?edit=' . $savedId . '#attr-form');
		}
		// The form opens again with what was sent
		$posted = ['id' => $id, 'f' => $f, 'options' => array_values(array_filter((array) ($_POST['options'] ?? []), 'is_array'))];
	}

	if ($action === 'cat_save') {
		[$parentRow, $child] = attr_subcategory((int) ($_POST['category_id'] ?? 0));
		if (!$child) {
			flash('danger', at('cat.gone'));
			redirect('attributes.php?tab=categories');
		}
		$fields = attr_fields();
		$byId = array_column($fields, null, 'id');
		$bound = attr_bindings()[$child['slug']] ?? [];
		$deals = category_deals($child['slug']);
		$rows = [];
		$order = 0;

		// The rows as edited: sort, deals, options; "remove" takes one out
		$posted = array_filter((array) ($_POST['rows'] ?? []), 'is_array');
		uasort($posted, fn($a, $b) => (int) (is_scalar($a['sort'] ?? null) ? $a['sort'] : 0) <=> (int) (is_scalar($b['sort'] ?? null) ? $b['sort'] : 0));
		foreach ($posted as $attrId => $r) {
			$field = $byId[(int) $attrId] ?? null;
			if (!$field || !isset($bound[$field['key']]) || !empty($r['remove'])) {
				continue;
			}
			$list = fn(string $key) => array_map('strval', array_filter((array) ($r[$key] ?? []), 'is_scalar'));
			$chosenDeals = array_values(array_intersect($deals, $list('deals')));
			$chosenOptions = $field['type'] === 'select' ? array_values(array_intersect(array_map('strval', array_keys($field['options'])), $list('options'))) : [];
			$rows[$field['id']] = [
				'attribute_id' => $field['id'], 'sort' => $order += 10,
				// All or none checked = any deal / the whole list
				'deals' => count($chosenDeals) === count($deals) ? [] : $chosenDeals,
				'options' => !$chosenOptions || count($chosenOptions) === count($field['options']) ? null : $chosenOptions,
			];
		}

		// Added by the button: one from the list, or the missing ones of another subcategory
		$add = [];
		if (($_POST['do'] ?? '') === 'add' && ($field = $byId[(int) ($_POST['add'] ?? 0)] ?? null)) {
			$add[] = $field['id'];
		}
		if (($_POST['do'] ?? '') === 'copy' && ($from = attr_bindings()[(string) ($_POST['copy'] ?? '')] ?? null)) {
			foreach (array_keys($from) as $code) {
				if (isset($fields[$code])) {
					$add[] = $fields[$code]['id'];
				}
			}
		}
		foreach ($add as $attrId) {
			if (!isset($rows[$attrId])) {
				$rows[$attrId] = ['attribute_id' => $attrId, 'sort' => $order += 10, 'deals' => [], 'options' => null];
			}
		}

		$condition = !empty($_POST['condition']);
		$before = array_keys($bound);
		$after = array_map(fn($r) => $byId[$r['attribute_id']]['key'], array_values($rows));
		$was = array_map(fn($code) => [$code, $bound[$code]['deals'], $bound[$code]['options']], $before);
		$now = array_map(fn($r) => [$byId[$r['attribute_id']]['key'], $r['deals'], $r['options']], array_values($rows));

		if ($was !== $now) {
			save_category_attributes($child['id'], array_values($rows));
		}
		if ($condition !== $child['condition']) {
			set_category_condition($child['id'], $condition);
		}
		$changes = array_filter([
			'added' => array_values(array_diff($after, $before)), 'removed' => array_values(array_diff($before, $after)),
			'changed' => $was !== $now && !array_diff($after, $before) && !array_diff($before, $after),
			'condition' => $condition !== $child['condition'] ? $condition : null,
		], fn($v) => $v !== [] && $v !== false && $v !== null);
		if ($changes) {
			log_action('admin.category_attributes', ['category_id' => $child['id'], 'slug' => $child['slug']] + $changes);
		}
		flash('success', $changes ? at('attr.cat_saved', ['name' => $child['names']['en']]) : at('common.nothing_changed'));
		redirect('attributes.php?tab=categories&cat=' . urlencode($child['slug']) . '#attr-cat');
	}
}

$tab = ($_GET['tab'] ?? '') === 'categories' ? 'categories' : 'fields';
$fields = attr_fields();
$bindings = attr_bindings();
$typeNames = array_combine(ATTR_TYPES, array_map(fn($type) => at('attr.type_' . $type), ATTR_TYPES));

// Subcategories using each characteristic: code => [[parent, child]…]
$usedIn = [];
foreach (categories() as $parent) {
	foreach ($parent['children'] as $child) {
		foreach (array_keys($bindings[$child['slug']] ?? []) as $code) {
			$usedIn[$code][] = [$parent, $child];
		}
	}
}

// One line about a characteristic's settings: "1900 – 2027, step 0.5, km" / "5 options" / "switch"
$summary = function (array $field) use ($typeNames): string {
	$parts = [$typeNames[$field['type']]];
	if ($field['type'] === 'number') {
		$parts[] = attr_range_text($field) . (isset($field['step']) ? ' (' . at('attr.step_short', ['step' => attr_limit_text($field['step'])]) . ')' : '');
		if (!empty($field['unit'])) {
			$parts[] = $field['unit']['en'];
		}
	} elseif ($field['type'] === 'select') {
		$parts[] = atp('attr.options_count', count($field['options']));
	} elseif ($field['type'] === 'text') {
		$parts[] = at('attr.max_short', ['n' => $field['max']]);
	} elseif ($field['type'] === 'bool' && ($field['switch'] || $field['row'])) {
		$parts[] = $field['row'] ? $field['row']['en'] . ': ' . $field['names']['en'] . ' / ' . $field['off']['en'] : at('attr.style_switch');
	}

	return implode(' · ', $parts);
};

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title"><?= e(at('attr.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('attr.sub')) ?></p>
	</div>
	<?php if ($tab === 'fields'): ?>
	<div class="page-head__actions">
		<a class="btn btn--primary" href="attributes.php?edit=new#attr-form"><?= icon('plus', 18) ?><?= e(at('attr.add')) ?></a>
	</div>
	<?php endif; ?>
</div>

<?php if ($error): ?>
<p class="notice notice--flash notice--danger" role="alert"><?= icon('alert', 20) ?><span class="notice__text"><?= e($error) ?></span></p>
<?php endif; ?>

<nav class="tabs__list tabs__list--flush attr-tabs" aria-label="<?= e(at('attr.title')) ?>">
	<a class="tabs__tab" href="attributes.php"<?= $tab === 'fields' ? ' aria-current="page"' : '' ?>><?= e(at('attr.tab_fields')) ?><span class="tabs__count"><?= count($fields) ?></span></a>
	<a class="tabs__tab" href="attributes.php?tab=categories"<?= $tab === 'categories' ? ' aria-current="page"' : '' ?>><?= e(at('attr.tab_categories')) ?></a>
</nav>

<?php if ($tab === 'fields'):
	// The form: what was sent with an error, ?edit=id, else a new one
	$editId = $posted['id'] ?? (int) ($_GET['edit'] ?? 0);
	$current = $editId ? attr_by_id($editId) : null;
	$isNew = !$current;
	$f = $posted['f'] ?? ($current ? ['code' => $current['key'], 'type' => $current['type'], 'names' => $current['names'], 'config' => $current['config'], 'filter' => $current['filter'], 'sort' => $current['sort']]
		: ['code' => '', 'type' => 'bool', 'names' => ['en' => '', 'sr-latn' => '', 'sr-cyrl' => ''], 'config' => [], 'filter' => true, 'sort' => (max(array_column($fields, 'sort') ?: [0]) + 10)]);
	$c = $f['config'];
	$type = $f['type'];
	// After an error the settings show what was sent
	$sent = $posted !== null;
	$sentValue = fn(string $name, mixed $stored): string => $sent ? (is_scalar($_POST[$name] ?? null) ? (string) $_POST[$name] : '') : str_replace('.', ',', (string) ($stored ?? ''));
	$sentOn = fn(string $name, mixed $stored): bool => $sent ? !empty($_POST[$name]) : !empty($stored);
	$sentNames = fn(string $prefix, ?array $stored): ?array => $sent ? ['en' => $sentValue($prefix . '_en', ''), 'sr-latn' => $sentValue($prefix . '_srl', ''), 'sr-cyrl' => $sentValue($prefix . '_src', '')] : $stored;
	// Option rows: as sent, or the stored ones, plus empty rows for new options
	$optionRows = $posted['options'] ?? array_map(fn($o, $i) => ['key' => $o['key'], 'en' => $o['names']['en'], 'srl' => $o['names']['sr-latn'], 'src' => $o['names']['sr-cyrl'], 'sort' => ($i + 1) * 10, 'hidden' => !empty($o['hidden'])], $c['options'] ?? [], array_keys($c['options'] ?? []));
	$nextSort = (count($optionRows) + 1) * 10;
	for ($i = 0; $i < 3; $i++) {
		$optionRows[] = ['key' => '', 'en' => '', 'srl' => '', 'src' => '', 'sort' => $nextSort + $i * 10, 'hidden' => false];
	}
	$used = $current && $current['type'] === 'select' ? attribute_value_counts($current['key']) : [];
	$listingCount = $current ? attribute_listing_count($current['key']) : 0;
	$names3 = fn(string $prefix, ?array $values, string $label, int $max = 100, string $hint = '') => '
		<fieldset class="fieldset field">
			<legend class="field__label">' . e($label) . '</legend>
			<div class="form-grid attr-names">
				<input class="input" name="' . $prefix . '_en" type="text" lang="en" maxlength="' . $max . '" value="' . e($values['en'] ?? '') . '" aria-label="' . e($label) . ' — EN" placeholder="EN">
				<input class="input" name="' . $prefix . '_srl" type="text" lang="sr-Latn" maxlength="' . $max . '" value="' . e($values['sr-latn'] ?? '') . '" aria-label="' . e($label) . ' — SR" placeholder="SR (' . e(at('common.latin')) . ')">
				<input class="input" name="' . $prefix . '_src" type="text" lang="sr-Cyrl" maxlength="' . $max . '" value="' . e($values['sr-cyrl'] ?? '') . '" aria-label="' . e($label) . ' — СР" placeholder="СР (' . e(at('common.cyrillic')) . ')">
			</div>' . ($hint !== '' ? '<span class="field__hint">' . e($hint) . '</span>' : '') . '
		</fieldset>';
?>
<div class="grid grid--tree-form mt-4">
	<!-- List -->
	<section class="card" aria-labelledby="attr-list-title">
		<div class="card__head">
			<h2 class="card__title" id="attr-list-title"><?= e(at('attr.list')) ?></h2>
		</div>
		<div class="table-wrap">
			<table class="table table--compact">
				<thead>
					<tr>
						<th><?= e(at('attr.col_name')) ?></th>
						<th><?= e(at('attr.col_type')) ?></th>
						<th><?= e(at('attr.col_categories')) ?></th>
						<th><?= e(at('attr.col_filter')) ?></th>
						<th class="col-actions"><span class="visually-hidden"><?= e(at('common.actions')) ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($fields as $code => $field): $in = $usedIn[$code] ?? []; ?>
					<tr<?= $current && $current['id'] === $field['id'] ? ' class="is-selected"' : '' ?>>
						<td>
							<div class="cell-main__text">
								<a class="cell-main__title" href="attributes.php?edit=<?= (int) $field['id'] ?>#attr-form"><?= e($field['names']['en']) ?></a>
								<div class="cell-main__sub"><?= e($field['names']['sr-latn']) ?> · <?= e($field['names']['sr-cyrl']) ?> · <code><?= e($code) ?></code></div>
							</div>
						</td>
						<td class="text-sm"><?= e($summary($field)) ?></td>
						<td class="text-sm"><?php if ($in): ?><span title="<?= e(implode(', ', array_map(fn($pc) => $pc[1]['names']['en'], $in))) ?>"><?= e(atp('attr.in_categories', count($in))) ?></span><?php else: ?><span class="muted"><?= e(at('attr.in_none')) ?></span><?php endif; ?></td>
						<td><?= $field['filter'] ? '<span class="badge badge--success">' . e(at('attr.filter_yes')) . '</span>' : '<span class="muted">—</span>' ?></td>
						<td class="col-actions"><a class="btn btn--ghost btn--icon btn--sm" href="attributes.php?edit=<?= (int) $field['id'] ?>#attr-form" aria-label="<?= e(at('common.edit_name', ['name' => $field['names']['en']])) ?>"><?= icon('edit', 18) ?></a></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<!-- Form -->
	<section class="card sticky-side" id="attr-form" aria-labelledby="attr-form-title">
		<div class="card__head">
			<h2 class="card__title" id="attr-form-title"><?= e($isNew ? at('attr.form_new') : at('attr.form_edit', ['name' => $current['names']['en']])) ?></h2>
		</div>

		<form class="card__body" method="post" action="attributes.php" data-attr-form>
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="attr_save">
			<input type="hidden" name="id" value="<?= $current ? (int) $current['id'] : '' ?>">

			<div class="form-section">
				<div class="form-list">
					<?= $names3('name', $f['names'], at('attr.name'), 100, at('attr.name_hint')) ?>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="a-type"><?= e(at('attr.type')) ?></label>
							<?php if ($isNew): ?>
							<select class="select" id="a-type" name="type" data-attr-type><?= options($typeNames, $type) ?></select>
							<?php else: ?>
							<input class="input" id="a-type" type="text" value="<?= e($typeNames[$type]) ?>" readonly>
							<?php endif; ?>
						</div>
						<div class="field">
							<label class="field__label" for="a-code"><?= e(at('attr.code')) ?></label>
							<?php if ($isNew): ?>
							<input class="input" id="a-code" name="code" type="text" maxlength="32" pattern="[a-z][a-z0-9_]*" value="<?= e($f['code']) ?>" placeholder="<?= e(at('attr.code_auto')) ?>">
							<?php else: ?>
							<input class="input" id="a-code" type="text" value="<?= e($f['code']) ?>" readonly>
							<?php endif; ?>
						</div>
					</div>
					<p class="field__hint"><?= e($isNew ? at('attr.fixed_hint') : at('attr.fixed_hint_saved')) ?></p>
				</div>
			</div>

			<!-- Settings of each type (a new one: the chosen type's block, admin.js) -->
			<fieldset class="fieldset form-section" data-type-settings="number"<?= $type === 'number' ? '' : ' hidden disabled' ?>>
				<legend class="form-section__title"><?= e(at('attr.type_number')) ?></legend>
				<div class="form-list">
					<div class="form-grid attr-names">
						<div class="field">
							<label class="field__label" for="a-min"><?= e(at('attr.min')) ?></label>
							<input class="input" id="a-min" name="min" type="text" inputmode="decimal" value="<?= e($sentValue('min', $c['min'] ?? null)) ?>">
						</div>
						<div class="field">
							<label class="field__label" for="a-max"><?= e(at('attr.max')) ?></label>
							<input class="input" id="a-max" name="max" type="text" inputmode="decimal" value="<?= e($type === 'number' ? $sentValue('max', $c['max'] ?? null) : '') ?>">
						</div>
						<div class="field">
							<label class="field__label" for="a-step"><?= e(at('attr.step')) ?></label>
							<input class="input" id="a-step" name="step" type="text" inputmode="decimal" value="<?= e($sentValue('step', $c['step'] ?? null)) ?>" placeholder="1">
						</div>
					</div>
					<label class="check"><input type="checkbox" name="max_next_year" value="1"<?= $sentOn('max_next_year', $c['max_next_year'] ?? null) ? ' checked' : '' ?>><span><?= e(at('attr.max_next_year')) ?></span></label>
					<?= $names3('unit', $sentNames('unit', $c['unit'] ?? null), at('attr.unit'), 20, at('attr.unit_hint')) ?>
				</div>
			</fieldset>

			<fieldset class="fieldset form-section" data-type-settings="text"<?= $type === 'text' ? '' : ' hidden disabled' ?>>
				<legend class="form-section__title"><?= e(at('attr.type_text')) ?></legend>
				<div class="form-list">
					<div class="field">
						<label class="field__label" for="a-max-length"><?= e(at('attr.max_length')) ?></label>
						<input class="input input--narrow" id="a-max-length" name="max" type="number" min="1" max="200" value="<?= e($type === 'text' ? ($sentValue('max', $c['max'] ?? null) ?: '40') : '40') ?>">
					</div>
					<label class="check"><input type="checkbox" name="raw" value="1"<?= $sentOn('raw', $c['raw'] ?? null) ? ' checked' : '' ?>><span><?= e(at('attr.raw_text')) ?></span></label>
				</div>
			</fieldset>

			<fieldset class="fieldset form-section" data-type-settings="bool"<?= $type === 'bool' ? '' : ' hidden disabled' ?>>
				<legend class="form-section__title"><?= e(at('attr.type_bool')) ?></legend>
				<div class="form-list">
					<label class="check"><input type="checkbox" name="switch" value="1"<?= $sentOn('switch', $c['switch'] ?? null) ? ' checked' : '' ?>><span><?= e(at('attr.style_switch_label')) ?></span></label>
					<p class="field__hint"><?= e(at('attr.two_sided_hint')) ?></p>
					<?= $names3('row', $sentNames('row', $c['row'] ?? null), at('attr.row')) ?>
					<?= $names3('off', $sentNames('off', $c['off'] ?? null), at('attr.off')) ?>
				</div>
			</fieldset>

			<fieldset class="fieldset form-section" data-type-settings="select"<?= $type === 'select' ? '' : ' hidden disabled' ?>>
				<legend class="form-section__title"><?= e(at('attr.options')) ?></legend>
				<p class="field__hint"><?= e(at('attr.options_hint')) ?><?= !empty($c['raw']) ? ' ' . e(at('attr.raw_options_hint')) : '' ?></p>
				<div class="table-wrap mt-2">
					<table class="table table--compact attr-options">
						<thead>
							<tr>
								<th class="num"><?= e(at('attr.col_order')) ?></th>
								<th>EN</th>
								<th>SR</th>
								<th>СР</th>
								<th><?= e(at('attr.col_hidden')) ?></th>
								<th><?= e(at('common.delete')) ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($optionRows as $i => $row): $key = (string) ($row['key'] ?? ''); $n = $key !== '' ? (int) ($used[!empty($c['raw']) ? $key : 'v.' . $key] ?? 0) : 0; ?>
							<tr>
								<td class="num">
									<input type="hidden" name="options[<?= $i ?>][key]" value="<?= e($key) ?>">
									<input class="input input--sm attr-options__sort" name="options[<?= $i ?>][sort]" type="number" min="0" max="9999" value="<?= (int) ($row['sort'] ?? 0) ?>" aria-label="<?= e(at('attr.col_order')) ?>">
								</td>
								<td><input class="input input--sm" name="options[<?= $i ?>][en]" type="text" maxlength="100" lang="en" value="<?= e($row['en'] ?? '') ?>" aria-label="EN"<?= $key === '' ? ' placeholder="' . e(at('attr.option_new')) . '"' : '' ?>></td>
								<td><input class="input input--sm" name="options[<?= $i ?>][srl]" type="text" maxlength="100" lang="sr-Latn" value="<?= e($row['srl'] ?? '') ?>" aria-label="SR"></td>
								<td><input class="input input--sm" name="options[<?= $i ?>][src]" type="text" maxlength="100" lang="sr-Cyrl" value="<?= e($row['src'] ?? '') ?>" aria-label="СР" placeholder="<?= e(at('attr.from_latin')) ?>"></td>
								<td><?php if ($key !== ''): ?><label class="check"><input type="checkbox" name="options[<?= $i ?>][hidden]" value="1"<?= !empty($row['hidden']) ? ' checked' : '' ?>><span class="visually-hidden"><?= e(at('attr.col_hidden')) ?></span></label><?php endif; ?></td>
								<td class="text-sm">
									<?php if ($key !== '' && $n === 0): ?>
									<label class="check"><input type="checkbox" name="options[<?= $i ?>][delete]" value="1"<?= !empty($row['delete']) ? ' checked' : '' ?>><span class="visually-hidden"><?= e(at('common.delete')) ?></span></label>
									<?php elseif ($key !== ''): ?>
									<span class="muted nowrap" title="<?= e(at('attr.option_in_use') . ': ' . atp('common.listings', $n)) ?>"><?= icon('lock', 16) ?> <?= $n ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</fieldset>

			<div class="form-section">
				<div class="form-list">
					<label class="switch" data-filter-switch<?= $type === 'text' ? ' hidden' : '' ?>>
						<input type="checkbox" role="switch" name="in_filter" value="1"<?= $f['filter'] ? ' checked' : '' ?>>
						<span class="switch__text"><span class="switch__title"><?= e(at('attr.in_filter')) ?></span><span class="switch__hint"><?= e(at('attr.in_filter_hint')) ?></span></span>
					</label>
					<div class="field">
						<label class="field__label" for="a-sort"><?= e(at('cat.sort')) ?></label>
						<input class="input input--narrow" id="a-sort" name="sort" type="number" min="0" max="9999" value="<?= (int) $f['sort'] ?>">
						<span class="field__hint"><?= e(at('attr.sort_hint')) ?></span>
					</div>
					<?php if ($current): $in = $usedIn[$current['key']] ?? []; ?>
					<p class="field__hint">
						<?= e(atp('attr.in_categories', count($in))) ?><?= $in ? ': ' : '' ?>
						<?php foreach ($in as $k => [$p, $ch]): ?><?= $k ? ', ' : '' ?><a href="attributes.php?tab=categories&amp;cat=<?= e(urlencode($ch['slug'])) ?>#attr-cat"><?= e($ch['names']['en']) ?></a><?php endforeach; ?>.
						<?= e(atp('attr.listings_have', $listingCount)) ?>
					</p>
					<?php endif; ?>
				</div>
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?><?= e(at($isNew ? 'attr.create' : 'common.save_changes')) ?></button>
				<?php if ($current && $listingCount === 0): ?>
				<button type="submit" class="btn btn--danger-ghost" name="action" value="attr_delete" formnovalidate data-confirm="<?= e(at('attr.delete_q', ['name' => $current['names']['en'], 'n' => count($usedIn[$current['key']] ?? [])])) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 18) ?><?= e(at('common.delete')) ?></button>
				<?php endif; ?>
			</div>
		</form>
	</section>
</div>

<?php else:
	// Categories tab: the subcategory from ?cat=, else the first one
	$subs = array_merge(...array_map(fn($p) => array_map(fn($ch) => [$p, $ch], $p['children']), categories()));
	[$catParent, $cat] = [null, null];
	foreach ($subs as [$p, $ch]) {
		if ($ch['slug'] === (string) ($_GET['cat'] ?? '') || $cat === null) {
			[$catParent, $cat] = [$p, $ch];
			if ($ch['slug'] === (string) ($_GET['cat'] ?? '')) {
				break;
			}
		}
	}
	$catFields = $cat ? category_attr_fields($cat['slug']) : [];
	$catDeals = $cat ? category_deals($cat['slug']) : [];
	$bound = $cat ? $bindings[$cat['slug']] ?? [] : [];
?>
<div class="grid grid--tree-form mt-4">
	<!-- Tree of subcategories -->
	<section class="card" aria-labelledby="attr-tree-title">
		<div class="card__head">
			<h2 class="card__title" id="attr-tree-title"><?= e(at('cat.tree')) ?></h2>
			<div class="card__actions">
				<button type="button" class="btn btn--ghost btn--sm" data-tree-all="expand"><?= e(at('cat.expand')) ?></button>
				<button type="button" class="btn btn--ghost btn--sm" data-tree-all="collapse"><?= e(at('cat.collapse')) ?></button>
			</div>
		</div>
		<ul class="tree">
			<?php foreach (categories() as $parent): if (!$parent['children']) continue;
				$childrenId = 'attr-tree-' . $parent['id'];
				$open = $catParent && $catParent['id'] === $parent['id']; ?>
			<li class="tree__item">
				<div class="tree__row<?= $parent['visible'] ? '' : ' is-hidden' ?>">
					<button type="button" class="btn btn--ghost btn--icon btn--sm tree__toggle" data-tree-toggle aria-controls="<?= e($childrenId) ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-label="<?= e(at('cat.show_children', ['name' => $parent['names']['en']])) ?>"><?= icon('chevron-right', 18) ?></button>
					<span class="tree__icon"><?= icon($parent['icon'] ?? 'layers', 18) ?></span>
					<span class="tree__name"><?= e($parent['names']['en']) ?><span class="tree__sub"><?= e($parent['names']['sr-latn']) ?></span></span>
				</div>
				<ul class="tree__children" id="<?= e($childrenId) ?>"<?= $open ? '' : ' hidden' ?>>
					<?php foreach ($parent['children'] as $child): $n = count($bindings[$child['slug']] ?? []); ?>
					<li class="tree__item">
						<div class="tree__row<?= $cat && $cat['id'] === $child['id'] ? ' is-current' : '' ?><?= $child['visible'] ? '' : ' is-hidden' ?>">
							<span class="tree__name">
								<a href="attributes.php?tab=categories&amp;cat=<?= e(urlencode($child['slug'])) ?>#attr-cat"<?= $cat && $cat['id'] === $child['id'] ? ' aria-current="true"' : '' ?>><?= e($child['names']['en']) ?></a>
								<span class="tree__sub"><?= e($child['names']['sr-latn']) ?></span>
							</span>
							<span class="tree__meta"><?= $n ? e(atp('attr.count', $n)) : '<span class="muted">' . e(at('attr.none_short')) . '</span>' ?></span>
						</div>
					</li>
					<?php endforeach; ?>
				</ul>
			</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<!-- Characteristics of the subcategory -->
	<section class="card sticky-side" id="attr-cat" aria-labelledby="attr-cat-title">
		<?php if (!$cat): ?>
		<div class="card__body"><p class="muted"><?= e(at('attr.no_subcategories')) ?></p></div>
		<?php else: ?>
		<div class="card__head">
			<h2 class="card__title" id="attr-cat-title"><?= e($catParent['names']['en'] . ' → ' . $cat['names']['en']) ?></h2>
		</div>
		<form class="card__body" method="post" action="attributes.php">
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="cat_save">
			<input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">

			<div class="form-section">
				<label class="switch">
					<input type="checkbox" role="switch" name="condition" value="1"<?= $cat['condition'] ? ' checked' : '' ?>>
					<span class="switch__text"><span class="switch__title"><?= e(at('attr.condition')) ?></span><span class="switch__hint"><?= e(at('attr.condition_hint')) ?></span></span>
				</label>
			</div>

			<div class="form-section">
				<h3 class="form-section__title"><?= e(at('attr.cat_fields')) ?></h3>
				<?php if (!$catFields): ?>
				<p class="muted"><?= e(at('attr.cat_none')) ?></p>
				<?php else: ?>
				<ul class="list attr-bound">
					<?php $i = 0; foreach ($catFields as $code => $field): $i++; $all = attr_fields()[$code]; $b = $bound[$code]; ?>
					<li class="list__item attr-bound__item">
						<div class="attr-bound__head">
							<input class="input input--sm attr-options__sort" name="rows[<?= (int) $field['id'] ?>][sort]" type="number" min="0" max="9999" value="<?= $i * 10 ?>" aria-label="<?= e(at('attr.col_order') . ': ' . $field['names']['en']) ?>">
							<div class="list__main">
								<a class="list__title" href="attributes.php?edit=<?= (int) $field['id'] ?>#attr-form"><?= e($field['names']['en']) ?></a>
								<div class="list__meta"><?= e($field['names']['sr-latn']) ?> · <?= e($summary($all)) ?><?= $all['filter'] ? ' · ' . e(at('attr.filter_yes')) : '' ?></div>
							</div>
							<label class="check"><input type="checkbox" name="rows[<?= (int) $field['id'] ?>][remove]" value="1"><span><?= e(at('attr.remove')) ?></span></label>
						</div>
						<?php if ($catDeals): ?>
						<fieldset class="fieldset attr-bound__part">
							<legend class="text-sm muted"><?= e(at('attr.deals')) ?></legend>
							<div class="row attr-bound__checks">
								<?php foreach ($catDeals as $deal): ?>
								<label class="check"><input type="checkbox" name="rows[<?= (int) $field['id'] ?>][deals][]" value="<?= e($deal) ?>"<?= !$b['deals'] || in_array($deal, $b['deals'], true) ? ' checked' : '' ?>><span><?= e(at('deal.' . $deal)) ?></span></label>
								<?php endforeach; ?>
							</div>
						</fieldset>
						<?php endif; ?>
						<?php if ($all['type'] === 'select'): ?>
						<fieldset class="fieldset attr-bound__part">
							<legend class="text-sm muted"><?= e(at('attr.cat_options')) ?></legend>
							<div class="row attr-bound__checks">
								<?php foreach ($all['options'] as $option => $item): ?>
								<label class="check"><input type="checkbox" name="rows[<?= (int) $field['id'] ?>][options][]" value="<?= e((string) $option) ?>"<?= $b['options'] === null || in_array((string) $option, array_map('strval', $b['options']), true) ? ' checked' : '' ?>><span><?= e($item['names']['en']) ?><?= $item['hidden'] ? ' (' . e(at('attr.col_hidden')) . ')' : '' ?></span></label>
								<?php endforeach; ?>
							</div>
						</fieldset>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
				<p class="field__hint mt-2"><?= e(at('attr.cat_hint')) ?></p>
				<?php endif; ?>
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?><?= e(at('common.save_changes')) ?></button>
			</div>

			<!-- Add one from the list, or copy the set of another subcategory (saves the changes above too) -->
			<div class="form-section">
				<h3 class="form-section__title"><?= e(at('attr.add_to_cat')) ?></h3>
				<div class="form-list">
					<div class="field__row attr-add">
						<label class="visually-hidden" for="attr-add"><?= e(at('attr.add_to_cat')) ?></label>
						<select class="select" id="attr-add" name="add">
							<?php foreach (attr_fields() as $code => $field): if (isset($catFields[$code])) continue; ?>
							<option value="<?= (int) $field['id'] ?>"><?= e($field['names']['en'] . ' — ' . $typeNames[$field['type']]) ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="btn btn--secondary" name="do" value="add"><?= icon('plus', 18) ?><?= e(at('attr.add_button')) ?></button>
					</div>
					<div class="field__row attr-add">
						<label class="visually-hidden" for="attr-copy"><?= e(at('attr.copy_from')) ?></label>
						<select class="select" id="attr-copy" name="copy">
							<?php foreach ($subs as [$p, $ch]): if ($ch['id'] === $cat['id'] || empty($bindings[$ch['slug']])) continue; ?>
							<option value="<?= e($ch['slug']) ?>"><?= e($p['names']['en'] . ' → ' . $ch['names']['en'] . ' (' . count($bindings[$ch['slug']]) . ')') ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="btn btn--secondary" name="do" value="copy"><?= icon('layers', 18) ?><?= e(at('attr.copy_button')) ?></button>
					</div>
					<p class="field__hint"><?= e(at('attr.add_hint')) ?> <a href="attributes.php?edit=new#attr-form"><?= e(at('attr.add')) ?></a>.</p>
				</div>
			</div>
		</form>
		<?php endif; ?>
	</section>
</div>
<?php endif; ?>

<?php require ADMIN_INC . '/footer.php'; ?>
