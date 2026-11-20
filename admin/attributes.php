<?php
/* ==========================================================================
	Admin: Characteristics (details of listings: attrs.php, migration 036).
	Admins only. Reworked 20.11 (evening) for people, not developers: one working area,
	every action saves at once, adding happens inside the category.
	Two tabs:
	  by category (default)  GET cat = slug of a subcategory: its characteristics
	                         in order (↑ ↓, Edit, Remove), "Ask condition",
	                         "Add characteristic" (a new one or one made before)
	  all (tab=all)          every characteristic: search, Edit (GET edit = id
	                         opens its window), New; delete — in its window
	Edit and New are windows (<dialog>); with an error the page opens again with
	that window open and what was sent.
	POST here (csrf), action (cat = category id where it applies):
	  attr_save      id (empty = new); type, code (new only: never change — the
	                 values in listings.attrs depend on them); name_en, name_srl,
	                 name_src (one of English and Serbian is enough, empty
	                 Cyrillic = from the Latin); in_filter; by type:
	                   number  unit_srl (+ unit_en, unit_src), min, max,
	                           max_next_year, step
	                   text    max, raw (a name: never put into Cyrillic)
	                   bool    switch, row_* + off_* (both or none)
	                   select  options[n][key (existing), en, srl, src, hidden,
	                           delete, here] in their order
	                 with cat: deals[] and the options "here" of that category;
	                 a new one is added to it
	  attr_delete    id — only when no listing holds a value of it
	  cat_add        cat, id — one made before, at the end
	  cat_remove     cat, id
	  cat_move       cat, id, dir (up | down)
	  cat_condition  cat, condition — ask new / used or not
	  cat_copy       cat, from (slug) — the missing ones of another subcategory
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

// Names from the form: prefix_en, prefix_srl, prefix_src (one of English and Serbian is enough:
// the other one copies it; empty Cyrillic = from the Latin)
function attr_post_names(string $prefix, int $max = 100): array
{
	$clean = fn(string $key) => trim(mb_substr(preg_replace('/\s+/u', ' ', is_scalar($_POST[$key] ?? null) ? (string) $_POST[$key] : ''), 0, $max));
	$names = ['en' => $clean($prefix . '_en'), 'sr-latn' => $clean($prefix . '_srl'), 'sr-cyrl' => $clean($prefix . '_src')];
	$names['en'] = $names['en'] !== '' ? $names['en'] : $names['sr-latn'];
	$names['sr-latn'] = $names['sr-latn'] !== '' ? $names['sr-latn'] : $names['en'];
	$names['sr-cyrl'] = $names['sr-cyrl'] !== '' ? $names['sr-cyrl'] : sr_cyr($names['sr-latn']);

	return $names;
}

// Code from a name: "Fixed price" → fixed_price, "Grejanje na struju" → grejanje_na_struju
function attr_code_from(string $text): string
{
	$code = substr(str_replace('-', '_', slugify($text)), 0, 32);

	return preg_match('/^[a-z]/', $code) ? rtrim($code, '_') : '';
}

// "2,5" → 2.5, "" → null, "abc" → false
function attr_post_number(string $key): int|float|false|null
{
	$raw = str_replace([',', ' '], ['.', ''], trim(is_scalar($_POST[$key] ?? null) ? (string) $_POST[$key] : ''));
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

// Subcategory by id or slug: [parent, child] or [null, null]
function attr_subcategory(int|string $idOrSlug): array
{
	foreach (categories() as $parent) {
		foreach ($parent['children'] as $child) {
			if ($child['id'] === $idOrSlug || $child['slug'] === $idOrSlug) {
				return [$parent, $child];
			}
		}
	}

	return [null, null];
}

// The set of a subcategory as rows of save_category_attributes(), code => row, in order
function attr_cat_rows(string $slug): array
{
	$fields = attr_fields();
	$rows = [];

	foreach (attr_bindings()[$slug] ?? [] as $code => $b) {
		if (isset($fields[$code])) {
			$rows[$code] = ['attribute_id' => $fields[$code]['id'], 'sort' => 0, 'deals' => $b['deals'], 'options' => $b['options']];
		}
	}

	return $rows;
}

function attr_cat_save(array $child, array $rows): void
{
	$sort = 0;
	foreach ($rows as &$row) {
		$row['sort'] = $sort += 10;
	}
	unset($row);
	save_category_attributes($child['id'], array_values($rows));
}

// A list field of the form: strings only
function attr_post_list(array $src, string $key): array
{
	return array_values(array_map('strval', array_filter((array) ($src[$key] ?? []), 'is_scalar')));
}

// Characteristic form → [record for save_attribute(), error, option keys checked "here"].
// $existing: the field (attr_fields()) or null
function attr_from_post(?array $existing): array
{
	$type = $existing['type'] ?? (string) ($_POST['type'] ?? '');
	$names = attr_post_names('name');
	$code = $existing['key'] ?? (strtolower(trim((string) ($_POST['code'] ?? ''))) ?: attr_code_from($names['en']) ?: attr_code_from($names['sr-latn']));
	$f = [
		'code' => $code, 'type' => $type, 'names' => $names, 'config' => [],
		'filter' => $type !== 'text' && !empty($_POST['in_filter']),
		'sort' => $existing['sort'] ?? (max(array_column(attr_fields(), 'sort') ?: [0]) + 10),
	];
	$here = [];

	if (!in_array($type, ATTR_TYPES, true)) {
		return [$f, at('attr.err_type'), $here];
	}
	if (mb_strlen($names['sr-latn']) < 2) {
		return [$f, at('attr.err_names'), $here];
	}
	if (!$existing) {
		if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $code) || in_array($code, ATTR_RESERVED, true)) {
			return [$f, at('attr.err_code'), $here];
		}
		if (isset(attr_fields()[$code])) {
			return [$f, at('attr.err_code_taken', ['code' => $code]), $here];
		}
	}

	$c = [];
	if ($type === 'number') {
		[$min, $max, $step] = [attr_post_number('min'), attr_post_number('max'), attr_post_number('step')];
		$nextYear = !empty($_POST['max_next_year']);
		$min ??= 0;
		$top = $nextYear ? (int) date('Y') + 1 : $max;
		if ($min === false || $max === false || $step === false || (!$nextYear && $max === null)) {
			return [$f, at('attr.err_number'), $here];
		}
		if ($top <= $min || ($step !== null && ($step <= 0 || $step > $top - $min))) {
			return [$f, at('attr.err_range'), $here];
		}
		$c = ['min' => $min] + ($nextYear ? ['max_next_year' => true] : ['max' => $max]) + ($step !== null && $step != 1 ? ['step' => $step] : []);
		$unit = attr_post_names('unit', 20);
		if ($unit['sr-latn'] !== '') {
			// A unit like km, m² stays as typed on the Cyrillic page unless written there
			$c['unit'] = ['en' => $unit['en'], 'sr-latn' => $unit['sr-latn'], 'sr-cyrl' => trim(is_scalar($_POST['unit_src'] ?? null) ? (string) $_POST['unit_src'] : '') ?: $unit['sr-latn']];
		}
	} elseif ($type === 'text') {
		$max = (int) ($_POST['max'] ?? 0);
		if ($max < 1 || $max > 200) {
			return [$f, at('attr.err_text_max'), $here];
		}
		$c = ['max' => $max] + (!empty($_POST['raw']) ? ['raw' => true] : []);
	} elseif ($type === 'bool') {
		$row = attr_post_names('row');
		$off = attr_post_names('off');
		$filled = array_filter([$row['sr-latn'], $off['sr-latn']], fn($v) => $v !== '');
		if ($filled && count($filled) < 2) {
			return [$f, at('attr.err_two_sided'), $here];
		}
		$c = (!empty($_POST['switch']) ? ['switch' => true] : []) + ($filled ? ['row' => $row, 'off' => $off] : []);
	} else {
		// A list: the rows in the order of the form; an existing option keeps its key, a new one gets it from the name
		$raw = $existing['raw'] ?? false;
		$was = $existing['options'] ?? [];
		$used = $existing ? attribute_value_counts($existing['key']) : [];
		$options = [];

		foreach (array_filter((array) ($_POST['options'] ?? []), 'is_array') as $row) {
			$clean = fn(string $key) => trim(mb_substr(preg_replace('/\s+/u', ' ', is_scalar($row[$key] ?? null) ? (string) $row[$key] : ''), 0, 100));
			$optionNames = ['en' => $clean('en'), 'sr-latn' => $clean('srl'), 'sr-cyrl' => $clean('src')];
			$key = is_scalar($row['key'] ?? null) ? (string) $row['key'] : '';
			$isOld = $key !== '' && isset($was[$key]);

			if ($isOld && !empty($row['delete'])) {
				if ($n = (int) ($used[$raw ? $key : 'v.' . $key] ?? 0)) {
					return [$f, at('attr.err_option_used', ['name' => $was[$key]['names']['en'], 'n' => $n]), $here];
				}
				continue;
			}
			// A new row removed in the form (without JS its "delete" flag stays)
			if (!empty($row['delete'])) {
				continue;
			}
			if ($optionNames['sr-latn'] === '' && $optionNames['en'] === '') {
				if ($isOld) {
					return [$f, at('attr.err_option_names'), $here];
				}
				continue;
			}
			$optionNames['sr-latn'] = $optionNames['sr-latn'] !== '' ? $optionNames['sr-latn'] : $optionNames['en'];
			$optionNames['en'] = $optionNames['en'] !== '' ? $optionNames['en'] : $optionNames['sr-latn'];
			$optionNames['sr-cyrl'] = $optionNames['sr-cyrl'] !== '' ? $optionNames['sr-cyrl'] : sr_cyr($optionNames['sr-latn']);
			if (!$isOld) {
				$key = $raw ? $optionNames['en'] : (attr_code_from($optionNames['en']) ?: attr_code_from($optionNames['sr-latn']));
				if ($key === '' || !preg_match($raw ? '/^[A-Za-z0-9+\-.,\/]{1,32}$/' : '/^[a-z][a-z0-9_]{0,31}$/', $key)) {
					return [$f, at('attr.err_option_key', ['name' => $optionNames['sr-latn']]), $here];
				}
			}
			if (isset($options[$key])) {
				return [$f, at('attr.err_option_twice', ['name' => $optionNames['sr-latn']]), $here];
			}
			$options[$key] = ['key' => $key, 'names' => $optionNames] + (!empty($row['hidden']) ? ['hidden' => true] : []);
			if (!empty($row['here'])) {
				$here[] = $key;
			}
		}
		if (!$options) {
			return [$f, at('attr.err_no_options'), $here];
		}
		$c = ($raw ? ['raw' => true] : []) + ['options' => array_values($options)];
	}
	$f['config'] = $c;

	return [$f, '', $here];
}

// One line about a characteristic's settings: "Number · 1900 – 2027 m²", "List · Centralno, Gas, …"
function attr_summary(array $field): string
{
	$parts = [at('attr.type_' . $field['type'])];
	if ($field['type'] === 'number') {
		$parts[] = attr_range_text($field) . (!empty($field['unit']) ? ' ' . $field['unit']['sr-latn'] : '');
	} elseif ($field['type'] === 'select') {
		$parts[] = implode(', ', array_slice(array_map(fn($o) => $o['names']['en'], $field['options']), 0, 4)) . (count($field['options']) > 4 ? ' … (' . count($field['options']) . ')' : '');
	} elseif ($field['type'] === 'text') {
		$parts[] = at('attr.max_short', ['n' => $field['max']]);
	} elseif ($field['row']) {
		$parts[] = $field['row']['en'] . ': ' . $field['names']['en'] . ' / ' . $field['off']['en'];
	}

	return implode(' · ', $parts);
}

$formState = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = (string) ($_POST['action'] ?? '');
	[, $cat] = ($catId = (int) ($_POST['cat'] ?? 0)) ? attr_subcategory($catId) : [null, null];
	$back = $cat ? 'attributes.php?cat=' . urlencode($cat['slug']) : 'attributes.php?tab=all';

	if (!csrf_valid()) {
		flash('danger', at('common.form_expired'));
		redirect($back);
	}
	if (str_starts_with($action, 'cat_') && !$cat) {
		flash('danger', at('cat.gone'));
		redirect('attributes.php');
	}
	$id = (int) ($_POST['id'] ?? 0) ?: null;
	$existing = $id ? attr_by_id($id) : null;
	if ($id && !$existing) {
		flash('danger', at('attr.gone'));
		redirect($back);
	}

	// A change of a category's set: saved, written to the journal, back to the row
	$catChanged = function (array $rows, array $log, string $message, ?int $rowId = null) use ($cat): never {
		attr_cat_save($cat, $rows);
		log_action('admin.category_attributes', ['category_id' => $cat['id'], 'slug' => $cat['slug']] + $log);
		flash('success', $message);
		redirect('attributes.php?cat=' . urlencode($cat['slug']) . ($rowId ? '#attr-' . $rowId : ''));
	};

	switch ($action) {
		case 'attr_save':
			[$f, $error, $here] = attr_from_post($existing);
			if ($error !== '') {
				$formState = ['dialog' => $existing ? 'attr-edit-' . $existing['id'] : 'attr-new', 'error' => $error];
				break;
			}
			$savedId = save_attribute($id, $f);
			$changed = $existing ? array_keys(array_filter([
				'names' => $f['names'] != $existing['names'], 'settings' => $f['config'] != $existing['config'], 'filter' => $f['filter'] !== $existing['filter'],
			])) : [];
			// In a category: its deals and its part of a list; a new one joins it
			if ($cat) {
				$rows = attr_cat_rows($cat['slug']);
				$deals = category_deals($cat['slug']);
				$chosen = array_values(array_intersect($deals, attr_post_list($_POST, 'deals')));
				$keys = $f['type'] === 'select' ? array_column($f['config']['options'], 'key') : [];
				$part = array_values(array_intersect($keys, $here));
				$was = $rows[$f['code']] ?? null;
				// The same options as before keep the category's own order (body types of motorbikes: "Other" last)
				$wasPart = array_map('strval', $was['options'] ?? []);
				if ($wasPart && count($wasPart) === count($part) && !array_diff($wasPart, $part)) {
					$part = $wasPart;
				}
				$row = ['attribute_id' => $savedId, 'sort' => 0,
					// All or none = any deal / the whole list
					'deals' => !$chosen || count($chosen) === count($deals) ? [] : $chosen,
					'options' => !$part || count($part) === count($keys) ? null : $part];
				if (!$was || $was['deals'] != $row['deals'] || $was['options'] != $row['options']) {
					$rows[$f['code']] = $row;
					attr_cat_save($cat, $rows);
					log_action('admin.category_attributes', ['category_id' => $cat['id'], 'slug' => $cat['slug']] + ($was ? ['changed' => true] : ['added' => [$f['code']]]));
					$changed[] = 'category';
				}
			}
			if (!$existing || $changed) {
				log_action($existing ? 'admin.attribute_update' : 'admin.attribute_create', ['attribute_id' => $savedId, 'code' => $f['code']] + ($changed ? ['fields' => $changed] : []));
			}
			flash('success', $existing
				? ($changed ? at('attr.saved', ['name' => $f['names']['en']]) : at('common.nothing_changed'))
				: at($cat ? 'attr.created_here' : 'attr.created', ['name' => $f['names']['en'], 'cat' => $cat['names']['en'] ?? '']));
			redirect($back . '#attr-' . $savedId);

		case 'attr_delete':
			if ($existing && delete_attribute($existing['id'], $existing['key'])) {
				log_action('admin.attribute_delete', ['attribute_id' => $existing['id'], 'code' => $existing['key']]);
				flash('success', at('attr.deleted', ['name' => $existing['names']['en']]));
				redirect('attributes.php?tab=all');
			}
			flash('danger', at('attr.delete_blocked', ['name' => $existing['names']['en'] ?? '', 'n' => $existing ? attribute_listing_count($existing['key']) : 0]));
			redirect('attributes.php?tab=all');

		case 'cat_add':
			$rows = attr_cat_rows($cat['slug']);
			if ($existing && !isset($rows[$existing['key']])) {
				$rows[$existing['key']] = ['attribute_id' => $existing['id'], 'sort' => 0, 'deals' => [], 'options' => null];
				$catChanged($rows, ['added' => [$existing['key']]], at('attr.added', ['name' => $existing['names']['en']]), $existing['id']);
			}
			redirect($back);

		case 'cat_remove':
			$rows = attr_cat_rows($cat['slug']);
			if ($existing && isset($rows[$existing['key']])) {
				unset($rows[$existing['key']]);
				$catChanged($rows, ['removed' => [$existing['key']]], at('attr.removed', ['name' => $existing['names']['en']]));
			}
			redirect($back);

		case 'cat_move':
			$rows = attr_cat_rows($cat['slug']);
			$codes = array_keys($rows);
			$at = $existing ? array_search($existing['key'], $codes, true) : false;
			$to = $at === false ? false : $at + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
			if ($at !== false && isset($codes[$to])) {
				[$codes[$at], $codes[$to]] = [$codes[$to], $codes[$at]];
				attr_cat_save($cat, array_merge(array_flip($codes), $rows));
				log_action('admin.category_attributes', ['category_id' => $cat['id'], 'slug' => $cat['slug'], 'changed' => true]);
			}
			redirect($back . ($existing ? '#attr-' . $existing['id'] : ''));

		case 'cat_condition':
			$on = !empty($_POST['condition']);
			if ($on !== $cat['condition']) {
				set_category_condition($cat['id'], $on);
				log_action('admin.category_attributes', ['category_id' => $cat['id'], 'slug' => $cat['slug'], 'condition' => $on]);
			}
			flash('success', at($on ? 'attr.condition_on' : 'attr.condition_off', ['name' => $cat['names']['en']]));
			redirect($back);

		case 'cat_copy':
			$rows = attr_cat_rows($cat['slug']);
			$added = [];
			foreach (attr_cat_rows((string) ($_POST['from'] ?? '')) as $code => $row) {
				if (!isset($rows[$code])) {
					$rows[$code] = $row;
					$added[] = $code;
				}
			}
			if ($added) {
				$catChanged($rows, ['added' => $added], atp('attr.copied', count($added)));
			}
			flash('success', at('common.nothing_changed'));
			redirect($back);

		default:
			flash('danger', at('common.unknown_action'));
			redirect($back);
	}
}

$tab = ($_GET['tab'] ?? '') === 'all' || ($formState !== null && empty($_POST['cat'])) ? 'all' : 'cat';
$fields = attr_fields();
$bindings = attr_bindings();

// Where each characteristic is used: code => [[parent, child]…]
$usedIn = [];
foreach (categories() as $parent) {
	foreach ($parent['children'] as $child) {
		foreach (array_keys($bindings[$child['slug']] ?? []) as $code) {
			$usedIn[$code][] = [$parent, $child];
		}
	}
}

// The window of a characteristic: new or edit, in a category or not. Returns <dialog>…</dialog>.
// $x: id (of the dialog), field (attr_fields() row or null), cat (subcategory or null), title,
// open (opened on load), before / after (HTML around the form: the tabs of "Add"), panel (form id)
function attr_dialog(array $x): string
{
	global $formState, $bindings, $usedIn;

	$field = $x['field'];
	$cat = $x['cat'];
	$isNew = !$field;
	$sent = $formState !== null && $formState['dialog'] === $x['id'];
	$error = $sent ? $formState['error'] : '';
	$c = $field['config'] ?? [];
	$type = $isNew ? ($sent && in_array($_POST['type'] ?? '', ATTR_TYPES, true) ? $_POST['type'] : 'bool') : $field['type'];
	$p = $x['id'];
	$binding = $cat && $field ? $bindings[$cat['slug']][$field['key']] ?? null : null;
	$deals = $cat ? category_deals($cat['slug']) : [];
	$raw = !empty($c['raw']);
	$used = !$isNew && $type === 'select' ? attribute_value_counts($field['key']) : [];

	// After an error the form shows what was sent
	$val = fn(string $name, mixed $stored): string => $sent ? (is_scalar($_POST[$name] ?? null) ? (string) $_POST[$name] : '') : str_replace('.', ',', (string) ($stored ?? ''));
	$on = fn(string $name, mixed $stored): bool => $sent ? !empty($_POST[$name]) : (bool) $stored;
	// A Cyrillic name made from the Latin is shown empty: it follows the Latin when that changes
	$autoCyr = fn(array $n): array => ($n['sr-cyrl'] ?? '') !== '' && $n['sr-cyrl'] === sr_cyr($n['sr-latn'] ?? '') ? ['sr-cyrl' => ''] + $n : $n;
	// (not for a unit: there an empty Cyrillic means "as in Latin": km, m²)
	$names = fn(string $prefix, ?array $stored, bool $auto = true): array => $sent
		? ['en' => $val($prefix . '_en', ''), 'sr-latn' => $val($prefix . '_srl', ''), 'sr-cyrl' => $val($prefix . '_src', '')]
		: ($auto ? $autoCyr($stored ?? []) : $stored ?? []) + ['en' => '', 'sr-latn' => '', 'sr-cyrl' => ''];

	// Option rows: as sent, or the stored ones ("here" = offered in this category); a new list gets two empty rows
	if ($sent) {
		$rows = array_values(array_filter((array) ($_POST['options'] ?? []), 'is_array'));
	} else {
		$rows = [];
		foreach ($field['options'] ?? [] as $key => $o) {
			$key = (string) $key;
			$rows[] = ['key' => $key, 'en' => $o['names']['en'], 'srl' => $o['names']['sr-latn'], 'src' => $autoCyr($o['names'])['sr-cyrl'], 'hidden' => $o['hidden'],
				'here' => $binding === null || $binding['options'] === null || in_array($key, array_map('strval', $binding['options']), true)];
		}
	}
	if (!$rows) {
		$rows = [['here' => true], ['here' => true]];
	}
	$dealChecked = fn(string $deal): bool => $sent ? in_array($deal, attr_post_list($_POST, 'deals'), true) : (!$binding || !$binding['deals'] || in_array($deal, $binding['deals'], true));
	$listingCount = !$isNew && !$cat ? attribute_listing_count($field['key']) : 0;

	// English (as the rest of the admin) and Serbian: one of them is enough; Cyrillic from the Latin
	$nameInputs = function (string $prefix, array $values, string $label, int $max, bool $required = false) use ($p): string {
		$id = $p . '-' . $prefix;
		return '<div class="attr-names">'
			. '<div class="field"><label class="field__label" for="' . $id . '-en">' . e($label) . ' <span class="muted">EN</span></label><input class="input" id="' . $id . '-en" name="' . $prefix . '_en" type="text" lang="en" maxlength="' . $max . '" value="' . e($values['en'] ?? '') . '"></div>'
			. '<div class="field"><label class="field__label" for="' . $id . '-srl">' . e($label) . ' <span class="muted">SR</span></label><input class="input" id="' . $id . '-srl" name="' . $prefix . '_srl" type="text" lang="sr-Latn" maxlength="' . $max . '" value="' . e($values['sr-latn'] ?? '') . '"></div>'
			. '<div class="field"><label class="field__label" for="' . $id . '-src">СР <span class="muted">(' . e(at('attr.auto')) . ')</span></label><input class="input" id="' . $id . '-src" name="' . $prefix . '_src" type="text" lang="sr-Cyrl" maxlength="' . $max . '" value="' . e($values['sr-cyrl'] ?? '') . '"></div>'
			. '</div>' . ($required ? '<p class="field__hint attr-names__hint">' . e(at('attr.names_hint')) . '</p>' : '');
	};

	// One option row; $i — its index in the form ('__i__' in the template)
	$optionRow = function (array $row, string $i) use ($cat, $raw, $used): string {
		$key = is_scalar($row['key'] ?? null) ? (string) $row['key'] : '';
		$n = $key !== '' ? (int) ($used[$raw ? $key : 'v.' . $key] ?? 0) : 0;
		$v = fn(string $k) => e(is_scalar($row[$k] ?? null) ? (string) $row[$k] : '');

		return '<li class="opt-row"' . ($key === '' ? ' data-new' : '') . (!empty($row['delete']) ? ' hidden' : '') . '>'
			. '<input type="hidden" name="options[' . $i . '][key]" value="' . e($key) . '">'
			. '<input type="hidden" name="options[' . $i . '][delete]" value="' . (!empty($row['delete']) ? '1' : '') . '" data-option-delete>'
			. '<span class="opt-row__move">'
			. '<button type="button" class="btn btn--ghost btn--icon btn--sm" data-option-move="up" aria-label="' . e(at('attr.up')) . '">' . icon('arrow-up', 16) . '</button>'
			. '<button type="button" class="btn btn--ghost btn--icon btn--sm attr-down" data-option-move="down" aria-label="' . e(at('attr.down')) . '">' . icon('arrow-up', 16) . '</button>'
			. '</span>'
			. '<input class="input input--sm" name="options[' . $i . '][en]" type="text" maxlength="100" lang="en" value="' . $v('en') . '" placeholder="' . e(at('attr.option_ph')) . ' EN" aria-label="EN">'
			. '<input class="input input--sm" name="options[' . $i . '][srl]" type="text" maxlength="100" lang="sr-Latn" value="' . $v('srl') . '" placeholder="SR" aria-label="SR">'
			. '<input class="input input--sm" name="options[' . $i . '][src]" type="text" maxlength="100" lang="sr-Cyrl" value="' . $v('src') . '" placeholder="СР (' . e(at('attr.auto')) . ')" aria-label="СР">'
			. '<span class="opt-row__flags">'
			. ($cat ? '<label class="check" title="' . e(at('attr.here_hint')) . '"><input type="checkbox" name="options[' . $i . '][here]" value="1"' . (!empty($row['here']) ? ' checked' : '') . '><span>' . e(at('attr.here')) . '</span></label>' : '')
			. ($key !== '' ? '<label class="check" title="' . e(at('attr.hidden_hint')) . '"><input type="checkbox" name="options[' . $i . '][hidden]" value="1"' . (!empty($row['hidden']) ? ' checked' : '') . '><span>' . e(at('attr.hide')) . '</span></label>' : '')
			. '</span>'
			. ($n > 0
				? '<span class="opt-row__lock muted" title="' . e(at('attr.option_in_use', ['n' => $n])) . '">' . icon('lock', 16) . $n . '</span>'
				: '<button type="button" class="btn btn--danger-ghost btn--icon btn--sm" data-option-remove aria-label="' . e(at('attr.option_remove')) . '">' . icon('trash', 16) . '</button>')
			. '</li>';
	};

	$typeHints = ['bool' => 'attr.type_bool_hint', 'select' => 'attr.type_select_hint', 'number' => 'attr.type_number_hint', 'text' => 'attr.type_text_hint'];
	$only = fn(string $types) => ' data-type-only="' . $types . '"' . (in_array($type, explode(' ', $types), true) ? '' : ' hidden disabled');

	ob_start();
	?>
<dialog class="modal modal--lg" id="<?= e($p) ?>" aria-labelledby="<?= e($p) ?>-title"<?= $sent || !empty($x['open']) ? ' data-modal-autoopen' : '' ?>>
	<div class="modal__inner">
		<div class="modal__head">
			<h2 class="modal__title" id="<?= e($p) ?>-title"><?= e($x['title']) ?></h2>
			<button type="button" class="btn btn--ghost btn--icon" data-modal-close aria-label="<?= e(at('common.close')) ?>"><?= icon('x') ?></button>
		</div>
		<?= $x['before'] ?? '' ?>
		<form class="attr-form" method="post" action="attributes.php" data-attr-form<?= !empty($x['panel']) ? ' id="' . e($x['panel']) . '" role="tabpanel"' : '' ?>>
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="attr_save">
			<input type="hidden" name="id" value="<?= $field ? (int) $field['id'] : '' ?>">
			<?php if ($cat): ?><input type="hidden" name="cat" value="<?= (int) $cat['id'] ?>"><?php endif; ?>
			<div class="modal__body">
				<?php if ($error !== ''): ?>
				<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><span class="notice__text"><?= e($error) ?></span></p>
				<?php endif; ?>

				<?= $nameInputs('name', $names('name', $field['names'] ?? null), at('attr.name'), 100, true) ?>

				<!-- Type: chosen once -->
				<?php if ($isNew): ?>
				<fieldset class="fieldset">
					<legend class="field__label"><?= e(at('attr.type')) ?></legend>
					<div class="attr-types">
						<?php foreach (ATTR_TYPES as $t): ?>
						<label class="attr-types__item">
							<input type="radio" name="type" value="<?= $t ?>"<?= $t === $type ? ' checked' : '' ?> data-attr-type>
							<span class="attr-types__title"><?= e(at('attr.type_' . $t)) ?></span>
							<span class="attr-types__hint"><?= e(at($typeHints[$t])) ?></span>
						</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<?php else: ?>
				<p class="field__hint"><?= e(at('attr.type')) ?>: <strong><?= e(at('attr.type_' . $type)) ?></strong> · <?= e(at('attr.type_fixed')) ?></p>
				<?php endif; ?>

				<!-- List: options -->
				<fieldset class="fieldset"<?= $only('select') ?>>
					<legend class="field__label"><?= e(at('attr.options')) ?></legend>
					<ol class="opt-rows" data-options>
						<?php foreach ($rows as $i => $row): ?>
						<?= $optionRow($row, (string) $i) ?>
						<?php endforeach; ?>
					</ol>
					<template data-option-tpl><?= $optionRow(['here' => true], '__i__') ?></template>
					<button type="button" class="btn btn--secondary btn--sm mt-2" data-option-add><?= icon('plus', 16) ?><?= e(at('attr.option_add')) ?></button>
					<p class="field__hint"><?= e(at($cat ? 'attr.options_hint_here' : 'attr.options_hint')) ?></p>
				</fieldset>

				<!-- Number: unit and range -->
				<fieldset class="fieldset"<?= $only('number') ?>>
					<div class="attr-nums">
						<div class="field">
							<label class="field__label" for="<?= e($p) ?>-unit-srl"><?= e(at('attr.unit')) ?></label>
							<input class="input" id="<?= e($p) ?>-unit-srl" name="unit_srl" type="text" maxlength="20" value="<?= e($names('unit', $c['unit'] ?? null, false)['sr-latn']) ?>" placeholder="m², km, kW">
						</div>
						<div class="field">
							<label class="field__label" for="<?= e($p) ?>-min"><?= e(at('attr.min')) ?></label>
							<input class="input" id="<?= e($p) ?>-min" name="min" type="text" inputmode="decimal" value="<?= e($val('min', $c['min'] ?? null)) ?>" placeholder="0">
						</div>
						<div class="field">
							<label class="field__label" for="<?= e($p) ?>-max"><?= e(at('attr.max')) ?></label>
							<input class="input" id="<?= e($p) ?>-max" name="max" type="text" inputmode="decimal" value="<?= e($type === 'number' ? $val('max', $c['max'] ?? null) : '') ?>">
						</div>
						<div class="field">
							<label class="field__label" for="<?= e($p) ?>-step"><?= e(at('attr.step')) ?></label>
							<input class="input" id="<?= e($p) ?>-step" name="step" type="text" inputmode="decimal" value="<?= e($val('step', $c['step'] ?? null)) ?>" placeholder="1">
						</div>
					</div>
					<label class="check mt-2"><input type="checkbox" name="max_next_year" value="1"<?= $on('max_next_year', $c['max_next_year'] ?? null) ? ' checked' : '' ?>><span><?= e(at('attr.max_next_year')) ?></span></label>
					<p class="field__hint"><?= e(at('attr.step_hint')) ?></p>
				</fieldset>

				<!-- Text: length -->
				<fieldset class="fieldset"<?= $only('text') ?>>
					<div class="field">
						<label class="field__label" for="<?= e($p) ?>-maxlen"><?= e(at('attr.max_length')) ?></label>
						<input class="input input--narrow" id="<?= e($p) ?>-maxlen" name="max" type="number" min="1" max="200" value="<?= e($type === 'text' ? ($val('max', $c['max'] ?? null) ?: '40') : '40') ?>">
					</div>
					<p class="field__hint"><?= e(at('attr.text_no_filter')) ?></p>
				</fieldset>

				<?php if ($cat && $deals): ?>
				<!-- Deals of this category it is asked with -->
				<fieldset class="fieldset">
					<legend class="field__label"><?= e(at('attr.deals', ['cat' => $cat['names']['en']])) ?></legend>
					<div class="row">
						<?php foreach ($deals as $deal): ?>
						<label class="check"><input type="checkbox" name="deals[]" value="<?= e($deal) ?>"<?= $dealChecked($deal) ? ' checked' : '' ?>><span><?= e(at('deal.' . $deal)) ?></span></label>
						<?php endforeach; ?>
					</div>
					<p class="field__hint"><?= e(at('attr.deals_hint')) ?></p>
				</fieldset>
				<?php endif; ?>

				<label class="switch" data-type-only="bool select number"<?= $type === 'text' ? ' hidden' : '' ?>>
					<input type="checkbox" role="switch" name="in_filter" value="1"<?= $on('in_filter', $isNew ? true : $field['filter']) ? ' checked' : '' ?>>
					<span class="switch__text"><span class="switch__title"><?= e(at('attr.in_filter')) ?></span><span class="switch__hint"><?= e(at('attr.in_filter_hint')) ?></span></span>
				</label>

				<!-- Rarely needed -->
				<details class="attr-more">
					<summary><?= e(at('attr.more')) ?></summary>
					<div class="form-list">
						<div class="field">
							<label class="field__label" for="<?= e($p) ?>-code"><?= e(at('attr.code')) ?></label>
							<?php if ($isNew): ?>
							<input class="input" id="<?= e($p) ?>-code" name="code" type="text" maxlength="32" pattern="[a-z][a-z0-9_]*" value="<?= e($val('code', '')) ?>" placeholder="<?= e(at('attr.code_auto')) ?>">
							<?php else: ?>
							<input class="input" id="<?= e($p) ?>-code" type="text" value="<?= e($field['key']) ?>" readonly>
							<?php endif; ?>
							<span class="field__hint"><?= e(at('attr.code_hint')) ?></span>
						</div>
						<fieldset class="fieldset"<?= $only('number') ?>>
							<div class="attr-names attr-names--2">
								<div class="field"><label class="field__label" for="<?= e($p) ?>-unit-en"><?= e(at('attr.unit')) ?> EN</label><input class="input" id="<?= e($p) ?>-unit-en" name="unit_en" type="text" maxlength="20" value="<?= e($names('unit', $c['unit'] ?? null, false)['en']) ?>"></div>
								<div class="field"><label class="field__label" for="<?= e($p) ?>-unit-src"><?= e(at('attr.unit')) ?> СР</label><input class="input" id="<?= e($p) ?>-unit-src" name="unit_src" type="text" maxlength="20" value="<?= e($names('unit', $c['unit'] ?? null, false)['sr-cyrl']) ?>" placeholder="<?= e(at('attr.unit_src_hint')) ?>"></div>
							</div>
						</fieldset>
						<fieldset class="fieldset"<?= $only('text') ?>>
							<label class="check"><input type="checkbox" name="raw" value="1"<?= $on('raw', $c['raw'] ?? null) ? ' checked' : '' ?>><span><?= e(at('attr.raw_text')) ?></span></label>
						</fieldset>
						<fieldset class="fieldset form-list"<?= $only('bool') ?>>
							<label class="check"><input type="checkbox" name="switch" value="1"<?= $on('switch', $c['switch'] ?? null) ? ' checked' : '' ?>><span><?= e(at('attr.style_switch_label')) ?></span></label>
							<p class="field__hint"><?= e(at('attr.two_sided_hint')) ?></p>
							<?= $nameInputs('row', $names('row', $c['row'] ?? null), at('attr.row'), 100) ?>
							<?= $nameInputs('off', $names('off', $c['off'] ?? null), at('attr.off'), 100) ?>
						</fieldset>
						<?php if (!$isNew && !$cat): ?>
						<p class="field__hint"><?= e(atp('attr.listings_have', $listingCount)) ?></p>
						<?php endif; ?>
					</div>
				</details>
			</div>
			<div class="modal__foot">
				<?php if (!$isNew && !$cat && $listingCount === 0): ?>
				<button type="submit" class="btn btn--danger-ghost attr-form__delete" name="action" value="attr_delete" formnovalidate data-confirm="<?= e(at('attr.delete_q', ['name' => $field['names']['en'], 'n' => count($usedIn[$field['key']] ?? [])])) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 18) ?><?= e(at('attr.delete')) ?></button>
				<?php endif; ?>
				<button type="button" class="btn btn--secondary" data-modal-close><?= e(at('common.cancel')) ?></button>
				<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?><?= e(at($isNew ? ($cat ? 'attr.create_here' : 'attr.create') : 'attr.save')) ?></button>
			</div>
		</form>
		<?= $x['after'] ?? '' ?>
	</div>
</dialog>
	<?php
	return ob_get_clean();
}

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title"><?= e(at('attr.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('attr.sub')) ?></p>
	</div>
</div>

<nav class="tabs__list tabs__list--flush attr-tabs" aria-label="<?= e(at('attr.title')) ?>">
	<a class="tabs__tab" href="attributes.php"<?= $tab === 'cat' ? ' aria-current="page"' : '' ?>><?= icon('layers', 18) ?><?= e(at('attr.tab_categories')) ?></a>
	<a class="tabs__tab" href="attributes.php?tab=all"<?= $tab === 'all' ? ' aria-current="page"' : '' ?>><?= icon('list', 18) ?><?= e(at('attr.tab_fields')) ?><span class="tabs__count"><?= count($fields) ?></span></a>
</nav>

<?php if ($tab === 'cat'):
	// The subcategory: from the form with an error, ?cat=, else the first one
	[$catParent, $cat] = attr_subcategory($formState !== null ? (int) ($_POST['cat'] ?? 0) : (string) ($_GET['cat'] ?? ''));
	if (!$cat) {
		foreach (categories() as $p) {
			if ($p['children']) {
				[$catParent, $cat] = [$p, $p['children'][0]];
				break;
			}
		}
	}
	$catFields = $cat ? category_attr_fields($cat['slug']) : [];
	$catDeals = $cat ? category_deals($cat['slug']) : [];
?>
<section class="card attr-cat" aria-labelledby="attr-cat-title">
	<!-- Category picker: a list with search, opens on choosing -->
	<form class="attr-cat__pick" method="get" action="attributes.php" data-autosubmit>
		<label class="attr-cat__label" for="attr-cat-select"><?= e(at('attr.category')) ?></label>
		<select class="select" id="attr-cat-select" name="cat">
			<?php foreach (categories() as $p): if (!$p['children']) continue; ?>
			<optgroup label="<?= e($p['names']['en'] . ' · ' . $p['names']['sr-latn']) ?>">
				<?php foreach ($p['children'] as $ch): $n = count($bindings[$ch['slug']] ?? []); ?>
				<option value="<?= e($ch['slug']) ?>" data-search="<?= e($p['names']['en'] . ' ' . $p['names']['sr-latn'] . ' ' . $ch['names']['sr-latn']) ?>"<?= $cat && $ch['id'] === $cat['id'] ? ' selected' : '' ?>><?= e($ch['names']['en'] . ' · ' . $ch['names']['sr-latn'] . ' (' . $n . ')') ?></option>
				<?php endforeach; ?>
			</optgroup>
			<?php endforeach; ?>
		</select>
		<noscript><button type="submit" class="btn btn--secondary"><?= e(at('common.show')) ?></button></noscript>
	</form>

	<?php if ($cat): ?>
	<div class="attr-cat__head">
		<div class="attr-cat__name">
			<h2 class="card__title" id="attr-cat-title"><?= e($catParent['names']['en']) ?> → <?= e($cat['names']['en']) ?></h2>
			<p class="muted text-sm"><?= e($catParent['names']['sr-latn'] . ' → ' . $cat['names']['sr-latn']) ?> · <a href="<?= e(SITE_URL . 'category.php?cat=' . urlencode($cat['slug'])) ?>" target="_blank" rel="noopener"><?= e(at('attr.on_site')) ?></a></p>
		</div>
		<form class="attr-cat__condition" method="post" action="attributes.php" data-autosubmit>
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="cat_condition">
			<input type="hidden" name="cat" value="<?= (int) $cat['id'] ?>">
			<label class="switch">
				<input type="checkbox" role="switch" name="condition" value="1"<?= $cat['condition'] ? ' checked' : '' ?>>
				<span class="switch__text"><span class="switch__title"><?= e(at('attr.condition')) ?></span></span>
			</label>
			<noscript><button type="submit" class="btn btn--ghost btn--sm"><?= e(at('common.apply')) ?></button></noscript>
		</form>
		<button type="button" class="btn btn--primary" data-modal-open="attr-new"><?= icon('plus', 18) ?><?= e(at('attr.add')) ?></button>
	</div>

	<?php if (!$catFields): ?>
	<div class="attr-empty">
		<p><?= e(at('attr.cat_none')) ?></p>
		<button type="button" class="btn btn--primary" data-modal-open="attr-new"><?= icon('plus', 18) ?><?= e(at('attr.add')) ?></button>
	</div>
	<?php else: ?>
	<ol class="attr-rows">
		<?php $last = count($catFields) - 1; $i = 0; foreach ($catFields as $code => $field): $all = $fields[$code]; ?>
		<li class="attr-row" id="attr-<?= (int) $field['id'] ?>">
			<form class="attr-row__move" method="post" action="attributes.php">
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="cat_move">
				<input type="hidden" name="cat" value="<?= (int) $cat['id'] ?>">
				<input type="hidden" name="id" value="<?= (int) $field['id'] ?>">
				<button type="submit" class="btn btn--ghost btn--icon btn--sm" name="dir" value="up" aria-label="<?= e(at('attr.up') . ': ' . $all['names']['en']) ?>"<?= $i === 0 ? ' disabled' : '' ?>><?= icon('arrow-up', 16) ?></button>
				<button type="submit" class="btn btn--ghost btn--icon btn--sm attr-down" name="dir" value="down" aria-label="<?= e(at('attr.down') . ': ' . $all['names']['en']) ?>"<?= $i === $last ? ' disabled' : '' ?>><?= icon('arrow-up', 16) ?></button>
			</form>
			<div class="attr-row__main">
				<button type="button" class="attr-row__name" data-modal-open="attr-edit-<?= (int) $field['id'] ?>"><?= e($all['names']['en']) ?></button>
				<span class="attr-row__sub muted"><?= e($all['names']['sr-latn']) ?></span>
				<div class="attr-row__meta"><?= e(attr_summary($field)) ?></div>
			</div>
			<div class="attr-row__tags">
				<?php if ($field['deals']): ?>
				<span class="badge badge--info"><?= e(at('attr.only_deals', ['deals' => implode(', ', array_map(fn($d) => at('deal.' . $d), $field['deals']))])) ?></span>
				<?php elseif ($catDeals): ?>
				<span class="badge badge--neutral"><?= e(at('attr.all_deals')) ?></span>
				<?php endif; ?>
				<?php if ($all['filter']): ?>
				<span class="badge badge--success"><?= e(at('attr.filter_yes')) ?></span>
				<?php else: ?>
				<span class="badge badge--neutral"><?= e(at('attr.filter_no')) ?></span>
				<?php endif; ?>
				<?php if ($field['type'] === 'select' && count($field['options']) < count($all['options'])): ?>
				<span class="badge badge--warning"><?= e(at('attr.part_options', ['n' => count($field['options']), 'all' => count($all['options'])])) ?></span>
				<?php endif; ?>
			</div>
			<div class="attr-row__actions">
				<button type="button" class="btn btn--secondary btn--sm" data-modal-open="attr-edit-<?= (int) $field['id'] ?>"><?= icon('edit', 16) ?><?= e(at('attr.edit')) ?></button>
				<form method="post" action="attributes.php">
					<?= csrf_field() ?>
					<input type="hidden" name="action" value="cat_remove">
					<input type="hidden" name="cat" value="<?= (int) $cat['id'] ?>">
					<input type="hidden" name="id" value="<?= (int) $field['id'] ?>">
					<button type="submit" class="btn btn--danger-ghost btn--sm" data-confirm="<?= e(at('attr.remove_q', ['name' => $all['names']['en'], 'cat' => $cat['names']['en']])) ?>" data-confirm-label="<?= e(at('attr.remove')) ?>"><?= icon('x', 16) ?><?= e(at('attr.remove')) ?></button>
				</form>
			</div>
		</li>
		<?php $i++; endforeach; ?>
	</ol>
	<?php endif; ?>
	<?php endif; ?>
</section>

<?php if ($cat):
	// Edit windows of the rows
	foreach ($catFields as $code => $field) {
		echo attr_dialog(['id' => 'attr-edit-' . $field['id'], 'field' => $fields[$code], 'cat' => $cat,
			'title' => at('attr.form_edit', ['name' => $fields[$code]['names']['en']])]);
	}

	// Add: a new one (the form) or one made before (a list with search), or copy another category's set
	$others = array_filter($fields, fn($f) => !isset($catFields[$f['key']]));
	$tabsHtml = '<div class="tabs__list attr-add-tabs" role="tablist" aria-label="' . e(at('attr.add')) . '" data-tabs>'
		. '<button type="button" class="tabs__tab" role="tab" aria-selected="true" aria-controls="attr-add-new">' . icon('plus', 16) . e(at('attr.add_new')) . '</button>'
		. '<button type="button" class="tabs__tab" role="tab" aria-selected="false" aria-controls="attr-add-existing">' . icon('list', 16) . e(at('attr.add_existing')) . '<span class="tabs__count">' . count($others) . '</span></button>'
		. '</div>';
	ob_start();
	?>
	<div class="attr-add-existing" id="attr-add-existing" role="tabpanel" hidden>
		<div class="modal__body">
			<div class="input-icon">
				<?= icon('search', 18) ?>
				<label class="visually-hidden" for="attr-pick-search"><?= e(at('common.search')) ?></label>
				<input class="input" id="attr-pick-search" type="search" placeholder="<?= e(at('attr.search_ph')) ?>" data-filter-input="attr-pick-list">
			</div>
			<ul class="attr-pick" id="attr-pick-list">
				<?php foreach ($others as $field): ?>
				<li class="attr-pick__item" data-filter-text="<?= e(mb_strtolower(implode(' ', $field['names']) . ' ' . $field['key'])) ?>">
					<div class="attr-pick__main">
						<strong><?= e($field['names']['en']) ?></strong> <span class="muted"><?= e($field['names']['sr-latn']) ?></span>
						<div class="attr-row__meta"><?= e(attr_summary($field)) ?></div>
					</div>
					<form method="post" action="attributes.php">
						<?= csrf_field() ?>
						<input type="hidden" name="action" value="cat_add">
						<input type="hidden" name="cat" value="<?= (int) $cat['id'] ?>">
						<input type="hidden" name="id" value="<?= (int) $field['id'] ?>">
						<button type="submit" class="btn btn--secondary btn--sm"><?= icon('plus', 16) ?><?= e(at('attr.add_button')) ?></button>
					</form>
				</li>
				<?php endforeach; ?>
				<li class="attr-pick__none muted" data-filter-empty hidden><?= e(at('common.nothing_found')) ?></li>
			</ul>
			<form class="attr-copy" method="post" action="attributes.php">
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="cat_copy">
				<input type="hidden" name="cat" value="<?= (int) $cat['id'] ?>">
				<label class="field__label" for="attr-copy-from"><?= e(at('attr.copy_from')) ?></label>
				<div class="field__row">
					<select class="select" id="attr-copy-from" name="from">
						<?php foreach (categories() as $p): ?>
						<?php foreach ($p['children'] as $ch): if ($ch['id'] === $cat['id'] || empty($bindings[$ch['slug']])) continue; ?>
						<option value="<?= e($ch['slug']) ?>"><?= e($p['names']['en'] . ' → ' . $ch['names']['en'] . ' (' . count($bindings[$ch['slug']]) . ')') ?></option>
						<?php endforeach; ?>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="btn btn--secondary"><?= e(at('attr.copy_button')) ?></button>
				</div>
			</form>
		</div>
	</div>
	<?php
	echo attr_dialog(['id' => 'attr-new', 'field' => null, 'cat' => $cat, 'title' => at('attr.add_to', ['cat' => $cat['names']['en']]),
		'before' => $tabsHtml, 'after' => ob_get_clean(), 'panel' => 'attr-add-new']);
endif; ?>

<?php else:
	// All characteristics
	$editId = $formState !== null && str_starts_with($formState['dialog'], 'attr-edit-') ? (int) substr($formState['dialog'], 10) : (int) ($_GET['edit'] ?? 0);
	$edit = $editId ? attr_by_id($editId) : null;
?>
<section class="card" aria-label="<?= e(at('attr.tab_fields')) ?>">
	<div class="toolbar">
		<div class="input-icon toolbar__search">
			<?= icon('search', 18) ?>
			<label class="visually-hidden" for="attr-search"><?= e(at('common.search')) ?></label>
			<input class="input" id="attr-search" type="search" placeholder="<?= e(at('attr.search_ph')) ?>" data-filter-input="attr-table">
		</div>
		<span class="toolbar__spacer"></span>
		<button type="button" class="btn btn--primary" data-modal-open="attr-new"><?= icon('plus', 18) ?><?= e(at('attr.new')) ?></button>
	</div>
	<div class="table-wrap">
		<table class="table attr-table" id="attr-table">
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
				<tr id="attr-<?= (int) $field['id'] ?>" data-filter-text="<?= e(mb_strtolower(implode(' ', $field['names']) . ' ' . $code)) ?>">
					<td>
						<a class="cell-main__title" href="attributes.php?tab=all&amp;edit=<?= (int) $field['id'] ?>"><?= e($field['names']['en']) ?></a>
						<div class="cell-main__sub"><?= e($field['names']['sr-latn']) ?></div>
					</td>
					<td class="text-sm"><?= e(attr_summary($field)) ?></td>
					<td class="text-sm">
						<?php if ($in): ?>
						<?php foreach (array_slice($in, 0, 3) as $k => [$p, $ch]): ?><?= $k ? ', ' : '' ?><a href="attributes.php?cat=<?= e(urlencode($ch['slug'])) ?>"><?= e($ch['names']['en']) ?></a><?php endforeach; ?>
						<?php if (count($in) > 3): ?><span class="muted" title="<?= e(implode(', ', array_map(fn($pc) => $pc[1]['names']['en'], array_slice($in, 3)))) ?>"> <?= e(at('attr.and_more', ['n' => count($in) - 3])) ?></span><?php endif; ?>
						<?php else: ?>
						<span class="muted"><?= e(at('attr.in_none')) ?></span>
						<?php endif; ?>
					</td>
					<td><?= $field['filter'] ? '<span class="badge badge--success">' . e(at('attr.filter_yes')) . '</span>' : '<span class="muted">—</span>' ?></td>
					<td class="col-actions"><a class="btn btn--secondary btn--sm" href="attributes.php?tab=all&amp;edit=<?= (int) $field['id'] ?>"><?= icon('edit', 16) ?><?= e(at('attr.edit')) ?></a></td>
				</tr>
				<?php endforeach; ?>
				<tr data-filter-empty hidden><td colspan="5" class="table__empty"><?= e(at('common.nothing_found')) ?></td></tr>
			</tbody>
		</table>
	</div>
</section>
<?php
	echo attr_dialog(['id' => 'attr-new', 'field' => null, 'cat' => null, 'title' => at('attr.new')]);
	if ($edit) {
		echo attr_dialog(['id' => 'attr-edit-' . $edit['id'], 'field' => $edit, 'cat' => null, 'title' => at('attr.form_edit', ['name' => $edit['names']['en']]), 'open' => true]);
	}
endif; ?>

<?php require ADMIN_INC . '/footer.php'; ?>
