<?php
/* ==========================================================================
	Listing details (characteristics) by subcategory: fields of the post form,
	their checks, how the values are shown on the listing page and the filters
	of the results page.
	Since 036 (20.11) they live in the database and are edited in admin →
	Characteristics (admin/attributes.php): attributes (one detail: type,
	names, config) and category_attributes (which details a subcategory asks,
	in order, with which deals, which options of a list); new / used —
	categories.has_condition.
	Stored in listings.attrs (JSON) as raw values in field order: numbers,
	option keys ('v.diesel'; a 'raw' list stores the key itself: 'A+++'), true
	(a checked box) and short texts. A characteristic's code never changes,
	so stored values keep their meaning.
	Demo values are ready strings ('168 000 km') and are shown as they are.
	========================================================================== */

// Codes a characteristic cannot take: params of the results page and of the form
const ATTR_RESERVED = ['cat', 'q', 'city', 'district', 'deal', 'type', 'brand', 'seller', 'price', 'currency', 'condition',
	'negotiable', 'agreement', 'photos', 'sort', 'page', 'build', 'user', 'exclude', 'since', 'lang', 'id', 'title',
	'description', 'contact', 'phone', 'category', 'subcategory', 'attrs', 'model_brand'];

// Field types: number (min, max, step, unit — names in three languages), select (options:
// key => ['names', 'hidden']; 'raw' = stored without "v."), text (max length; 'raw' = a name, never
// put into Cyrillic), bool (a checkbox, or a switch with 'switch': stored as true when on; with 'row'
// and 'off' the listing page shows a row named 'row' either way — its own name when on, 'off' when
// off; otherwise an off field is not shown).
// Every field: id, key, type, names, filter (offered in the filters; never for text), sort
function attr_fields(): array
{
	static $fields = null;

	if ($fields === null) {
		$fields = [];
		foreach (attribute_rows() as $r) {
			$fields[$r['code']] = attr_from_row($r);
		}
	}

	return $fields;
}

// A row of attributes → a field (see attr_fields())
function attr_from_row(array $r): array
{
	$c = json_decode((string) $r['config'], true) ?: [];
	$field = [
		'id' => (int) $r['id'], 'key' => $r['code'], 'type' => $r['type'],
		'names' => ['en' => $r['name_en'], 'sr-latn' => $r['name_sr_latn'], 'sr-cyrl' => $r['name_sr_cyrl']],
		'filter' => (bool) $r['in_filter'] && $r['type'] !== 'text', 'sort' => (int) $r['sort'], 'config' => $c,
	];
	$num = fn($v) => $v == (int) $v ? (int) $v : (float) $v;

	return $field + match ($r['type']) {
		// A year: up to the next one, whatever the year is now
		'number' => array_filter([
			'min' => $num($c['min'] ?? 0),
			'max' => !empty($c['max_next_year']) ? (int) date('Y') + 1 : $num($c['max'] ?? 999_999),
			'step' => isset($c['step']) ? $num($c['step']) : null,
			'unit' => $c['unit'] ?? null,
		], fn($v) => $v !== null),
		'select' => [
			'raw' => !empty($c['raw']),
			'options' => array_column(array_map(fn($o) => ['key' => (string) $o['key'], 'names' => $o['names'], 'hidden' => !empty($o['hidden'])], $c['options'] ?? []), null, 'key'),
		],
		'text' => ['max' => (int) ($c['max'] ?? 60), 'raw' => !empty($c['raw'])],
		'bool' => ['switch' => !empty($c['switch']), 'row' => $c['row'] ?? null, 'off' => $c['off'] ?? null],
		default => [],
	};
}

// Characteristics of every subcategory: slug => [code => ['sort', 'deals' (list, [] = any),
// 'options' (keys offered, null = all)]]
function attr_bindings(): array
{
	static $bindings = null;

	if ($bindings === null) {
		$bindings = [];
		foreach (category_attribute_rows() as $r) {
			$bindings[$r['slug']][$r['code']] = [
				'sort' => (int) $r['sort'],
				'deals' => $r['deals'] !== null && $r['deals'] !== '' ? explode(' ', $r['deals']) : [],
				'options' => $r['options'] !== null ? json_decode($r['options'], true) : null,
			];
		}
	}

	return $bindings;
}

// Fields of a subcategory: key => definition with 'deals' (only with these deals, [] = any) and
// its part of the options. $deal given: only the fields asked with that deal
function category_attr_fields(string $slug, ?string $deal = null): array
{
	$all = attr_fields();
	$fields = [];

	foreach (attr_bindings()[$slug] ?? [] as $key => $binding) {
		if (!isset($all[$key])) {
			continue;
		}
		$field = ['deals' => $binding['deals']] + $all[$key];
		// Its part of a list, in its own order (body types of motorbikes)
		if ($binding['options'] !== null && $field['type'] === 'select') {
			$part = array_flip($binding['options']);
			$field['options'] = array_intersect_key(array_replace($part, array_intersect_key($field['options'], $part)), $field['options']);
		}
		if ($deal !== null && !attr_for_deal($field, $deal)) {
			continue;
		}
		$fields[$key] = $field;
	}

	return $fields;
}

// Whether a field is asked with a deal ('' = none chosen: only the fields of every deal)
function attr_for_deal(array $field, string $deal): bool
{
	return !$field['deals'] || in_array($deal, $field['deals'], true);
}

// Whether new / like new / used is asked for a subcategory (admin → Characteristics)
function category_has_condition(string $slug): bool
{
	[, $child] = find_category($slug);

	return $child['condition'] ?? true;
}

// A name in the current language: ['en' => …, 'sr-latn' => …, 'sr-cyrl' => …]
function attr_text(?array $names): string
{
	if (!$names) {
		return '';
	}

	return (string) (($names[lang()] ?? '') !== '' ? $names[lang()] : (lang() === 'sr-cyrl' ? sr_cyr($names['sr-latn'] ?? '') : $names['en'] ?? ''));
}

// Name of a field ("Gorivo"), of its unit ("km", "ari", '' = none), of an option ("Dizel")
function attr_label(string $key): string
{
	return attr_text(attr_fields()[$key]['names'] ?? null) ?: $key;
}

function attr_unit(array $field): string
{
	return attr_text($field['unit'] ?? null);
}

function attr_option_label(array $field, string $option): string
{
	return attr_text($field['options'][$option]['names'] ?? null) ?: $option;
}

// Stored value of an option: 'v.diesel', or the key itself in a raw list ('A+++')
function attr_option_value(array $field, string $option): string
{
	return $field['raw'] ? $option : 'v.' . $option;
}

// Form input → [attrs to store, error text or '']. Empty fields are skipped, so are fields of another
// deal ($deal given); a draft ($strict = false) drops wrong values instead of reporting them
function clean_attrs(string $slug, array $input, bool $strict = true, ?string $deal = null): array
{
	$attrs = [];

	foreach (category_attr_fields($slug, $deal) as $key => $field) {
		$raw = trim((string) ($input[$key] ?? ''));

		if ($raw === '') {
			continue;
		}

		$value = match ($field['type']) {
			'number' => attr_number($raw, $field),
			'select' => isset($field['options'][$raw]) ? attr_option_value($field, $raw) : null,
			'bool' => $raw === '1' ? true : null,
			default => mb_strlen($raw) <= $field['max'] ? preg_replace('/\s+/u', ' ', $raw) : null,
		};

		if ($value !== null) {
			$attrs[$key] = $value;
		} elseif ($strict) {
			// A number out of its range says the range (K19)
			return [[], $field['type'] === 'number'
				? t('post.err_attr_range', ['field' => attr_label($key), 'min' => attr_limit_text($field['min']), 'max' => attr_limit_text($field['max'])])
				: t('post.err_attr', ['field' => attr_label($key)])];
		}
	}

	return [$attrs, ''];
}

// A limit of a number field as written for people: 1900 (years are not split), 2 000 000, 0,5
function attr_limit_text(int|float $v): string
{
	return $v != (int) $v ? str_replace('.', ',', (string) $v) : ($v >= 10_000 ? number_format((int) $v, 0, ',', "\u{00A0}") : (string) (int) $v);
}

// The range of a number field as shown in it (placeholder): "1900 – 2027", "0 – 2 000 000"
function attr_range_text(array $field): string
{
	return attr_limit_text($field['min']) . ' – ' . attr_limit_text($field['max']);
}

// "168 000" / "2,5" / "168 000 km" (the field's own unit typed after the number) → number
// within the field's limits and step, or null
function attr_number(string $raw, array $field): int|float|null
{
	$unit = attr_unit($field);
	if ($unit !== '') {
		$raw = preg_replace('/\s*' . preg_quote($unit, '/') . '$/iu', '', $raw);
	}
	$raw = str_replace(',', '.', preg_replace('/[\s\x{00A0}]/u', '', $raw));

	if (!preg_match('/^\d+(\.\d+)?$/', $raw)) {
		return null;
	}

	$n = (float) $raw;
	$step = $field['step'] ?? 1;

	if ($n < $field['min'] || $n > $field['max'] || fmod($n, $step) != 0) {
		return null;
	}

	return $n == (int) $n ? (int) $n : $n;
}

// Stored value → form value: 'v.diesel' → 'diesel', 2.5 → '2,5', true → '1'
function attr_input_value(mixed $value): string
{
	if (is_bool($value)) {
		return $value ? '1' : '';
	}
	if (is_float($value)) {
		return str_replace('.', ',', (string) $value);
	}

	return str_starts_with((string) $value, 'v.') ? substr($value, 2) : (string) $value;
}

// Value for the listing page: options by their names, numbers with their unit, texts in the page script
function attr_value(string $key, mixed $value): string
{
	$field = attr_fields()[$key] ?? null;

	if (is_bool($value)) {
		return t($value ? 'v.yes' : 'v.no');
	}
	if (is_int($value) || is_float($value)) {
		$unit = $field ? attr_unit($field) : '';
		$number = is_float($value) ? str_replace('.', ',', (string) $value) : ($unit !== '' ? format_number($value) : (string) $value);

		return $unit === '' ? $number : $number . ($unit === '%' ? '' : "\u{00A0}") . $unit;
	}

	$value = (string) $value;
	if ($field && $field['type'] === 'select') {
		$option = $field['raw'] ? $value : (str_starts_with($value, 'v.') ? substr($value, 2) : null);
		if ($option !== null && isset($field['options'][$option])) {
			return attr_option_label($field, $option);
		}
	}
	// An option no longer in the list, a demo value: as stored
	if (str_starts_with($value, 'v.')) {
		return substr($value, 2);
	}

	return lang() === 'sr-cyrl' && empty($field['raw']) ? sr_cyr($value) : $value;
}

// Rows of the details block: deal, kind and brand, condition (from their own columns) first, then the
// attributes (a switch with 'off' also when it is off: new build / resale), the advertiser last
function listing_details(array $listing): array
{
	$rows = [];

	if (!empty($listing['deal'])) {
		$rows[] = [t('post.f_deal'), t('deal.' . $listing['deal'])];
	}
	if (!empty($listing['type']) && ($type = category_types($listing['category'])[$listing['type']] ?? null)) {
		$rows[] = [t(TYPE_LABELS[$listing['category']] ?? 'post.f_type'), type_name($type)];
	}
	if (($brand = brand_name($listing['category'], $listing['brand'] ?? null)) !== null) {
		$rows[] = [t('post.f_brand'), $brand];
	}

	if ($listing['condition'] !== null && !isset($listing['attrs']['condition']) && category_has_condition($listing['category'])) {
		$rows[] = [t('a.condition'), t('v.' . $listing['condition'])];
	}

	$fields = attr_fields();
	foreach ($listing['attrs'] as $key => $value) {
		// A switch with two names shows the one that is on: "Building: New build"
		$row = $fields[$key]['row'] ?? null;
		$rows[] = $row !== null && $value === true ? [attr_text($row), attr_label($key)] : [attr_label($key), attr_value($key, $value)];
	}
	foreach (category_attr_fields($listing['category'], (string) ($listing['deal'] ?? '')) as $key => $field) {
		if ($field['type'] === 'bool' && !empty($field['row']) && !empty($field['off']) && !isset($listing['attrs'][$key])) {
			$rows[] = [attr_text($field['row']), attr_text($field['off'])];
		}
	}

	// Private person or business: the account's (035), on every listing (M5)
	$rows[] = [t('post.f_seller'), t('seller.' . ($listing['seller_type'] ?? 'private'))];

	return $rows;
}

/* ---------- Filters of the results page ---------- */

// A switch with two names (new build / resale): the filter offers both, "any" by default
function attr_two_sided(array $field): bool
{
	return $field['type'] === 'bool' && !empty($field['row']) && !empty($field['off']);
}

// Filters of a subcategory with a deal ('' = any): the fields marked "in filters"
function attr_filter_fields(string $slug, string $deal): array
{
	return array_filter(category_attr_fields($slug), fn($field) => $field['filter'] && ($deal === '' || attr_for_deal($field, $deal)));
}

// Filters from a query: key => '1' / '0' (a two-sided switch: off) for bool, an option key for a list,
// ['from' => '', 'to' => ''] for a number (params key_from, key_to). Every characteristic marked
// "in filters" is read here; fit_filters() then keeps those of the page's subcategory.
// The old ?build=new|old of new build / resale (before 036) still works
function attr_filters_from(array $src): array
{
	if (isset($src['build']) && !isset($src['new_build'])) {
		$src['new_build'] = ['new' => '1', 'old' => '0'][$src['build']] ?? '';
	}
	$number = function (mixed $raw): string {
		$raw = str_replace(',', '.', preg_replace('/[\s\x{00A0}]/u', '', (string) $raw));

		return preg_match('/^\d{1,9}(\.\d{1,2})?$/', $raw) ? $raw : '';
	};
	$out = [];

	foreach (attr_fields() as $key => $field) {
		if (!$field['filter']) {
			continue;
		}
		if ($field['type'] === 'number') {
			$range = ['from' => $number($src[$key . '_from'] ?? ''), 'to' => $number($src[$key . '_to'] ?? '')];
			if ($range['from'] !== '' || $range['to'] !== '') {
				$out[$key] = $range;
			}
			continue;
		}
		$value = is_scalar($src[$key] ?? null) ? (string) $src[$key] : '';
		if ($field['type'] === 'bool' && ($value === '1' || ($value === '0' && attr_two_sided($field)))) {
			$out[$key] = $value;
		} elseif ($field['type'] === 'select' && isset($field['options'][$value])) {
			$out[$key] = $value;
		}
	}

	return $out;
}

// Filters that fit a subcategory and a deal (on a section page or without a category — none)
function attr_filters_fit(array $attrs, string $slug, string $deal): array
{
	$fields = $slug !== '' ? attr_filter_fields($slug, $deal) : [];
	$fit = [];

	foreach ($attrs as $key => $value) {
		if (isset($fields[$key]) && ($fields[$key]['type'] !== 'select' || isset($fields[$key]['options'][$value]))) {
			$fit[$key] = $value;
		}
	}

	return $fit;
}

// Filters → query params (key, key_from, key_to)
function attr_filters_query(array $attrs): array
{
	$params = [];

	foreach ($attrs as $key => $value) {
		if (is_array($value)) {
			$params[$key . '_from'] = $value['from'] !== '' ? $value['from'] : null;
			$params[$key . '_to'] = $value['to'] !== '' ? $value['to'] : null;
		} else {
			$params[$key] = $value;
		}
	}

	return $params;
}

// Filters as text, 'attr.key' => label: "Depozit", "Novogradnja", "Gorivo: Dizel", "Godište: 2010 – 2020"
function attr_filters_labels(array $attrs): array
{
	$labels = [];
	$fields = attr_fields();

	foreach ($attrs as $key => $value) {
		if (!$field = $fields[$key] ?? null) {
			continue;
		}
		$labels['attr.' . $key] = match ($field['type']) {
			'bool' => $value === '0' ? attr_text($field['off']) : attr_label($key),
			'select' => attr_label($key) . ': ' . attr_option_label($field, $value),
			default => attr_label($key) . ': ' . attr_range_label($field, $value),
		};
	}

	return $labels;
}

// "2010 – 2020", "od 2010", "do 150 m²"
function attr_range_label(array $field, array $range): string
{
	$unit = attr_unit($field);
	$text = fn(string $n) => attr_limit_text($n == (int) $n ? (int) $n : (float) $n);
	$tail = $unit !== '' ? ($unit === '%' ? '' : "\u{00A0}") . $unit : '';

	return match (true) {
		$range['from'] !== '' && $range['to'] !== '' => $text($range['from']) . ' – ' . $text($range['to']) . $tail,
		$range['from'] !== '' => t('cat.from_value', ['value' => $text($range['from']) . $tail]),
		default => t('cat.to_value', ['value' => $text($range['to']) . $tail]),
	};
}

// Filters → SQL conditions on listings l: [where list, params]. A code is [a-z0-9_] (admin), safe in a path
function attr_filters_where(array $attrs): array
{
	$where = [];
	$params = [];
	$fields = attr_fields();

	foreach ($attrs as $key => $value) {
		if (!($field = $fields[$key] ?? null) || !preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
			continue;
		}
		$path = "'$." . $key . "'";
		if ($field['type'] === 'bool') {
			$where[] = $value === '1' ? "JSON_VALUE(l.attrs, $path) IN ('true', '1')" : "COALESCE(JSON_VALUE(l.attrs, $path), 'false') NOT IN ('true', '1')";
		} elseif ($field['type'] === 'select') {
			$where[] = "JSON_VALUE(l.attrs, $path) = ?";
			$params[] = attr_option_value($field, $value);
		} elseif ($field['type'] === 'number') {
			foreach (['from' => '>=', 'to' => '<='] as $end => $op) {
				if ($value[$end] !== '') {
					$where[] = "CAST(JSON_VALUE(l.attrs, $path) AS DECIMAL(14, 2)) $op ?";
					$params[] = $value[$end];
				}
			}
		}
	}

	return [$where, $params];
}
