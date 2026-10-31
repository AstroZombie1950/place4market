<?php
/* ==========================================================================
	Listing details by subcategory: fields of the post form, their checks and
	how the values are shown on the listing page.
	Stored in listings.attrs (JSON) as raw values in field order: numbers,
	option keys ('v.diesel') and short texts. Labels: a.<key>, options: v.<value>.
	Demo values are ready strings ('168 000 km') and are shown as they are.
	========================================================================== */

// Subcategory → fields; 'condition' => false: new/used does not apply (property, services, jobs).
// A field is a key of attr_fields() or key => overrides (motorbike body types)
const CATEGORY_ATTRS = [
	'cars' => ['fields' => ['year', 'mileage', 'fuel', 'gearbox', 'power', 'body', 'color']],
	'motorcycles' => ['fields' => ['year', 'mileage', 'engine', 'body' => ['options' => ['naked', 'sport', 'touring', 'enduro', 'scooter', 'chopper', 'other']], 'color']],
	'auto-parts' => ['fields' => []],
	'apartments' => ['fields' => ['area', 'rooms', 'floor', 'heating', 'built', 'furnished'], 'condition' => false],
	'houses' => ['fields' => ['area', 'plot', 'rooms', 'heating', 'built', 'furnished'], 'condition' => false],
	'land' => ['fields' => ['plot'], 'condition' => false],
	'phones' => ['fields' => ['memory', 'battery', 'color']],
	'computers' => ['fields' => ['ram', 'ssd', 'battery']],
	'consoles' => ['fields' => ['memory', 'controllers']],
	'furniture' => ['fields' => ['material', 'dimensions', 'color']],
	'appliances' => ['fields' => ['capacity', 'energy']],
	'womens-clothing' => ['fields' => ['size', 'color']],
	'mens-clothing' => ['fields' => ['size', 'color']],
	'strollers' => ['fields' => ['age', 'color']],
	'toys' => ['fields' => ['age', 'material']],
	'services' => ['fields' => ['experience', 'area_served'], 'condition' => false],
	'jobs' => ['fields' => ['employment'], 'condition' => false],
	'bikes' => ['fields' => ['wheel', 'frame', 'gears']],
	'winter-sports' => ['fields' => ['length', 'size']],
];

// Field types: number (min, max, step, unit; a unit "u.*" is translated), select (options:
// v.<option>, or shown as is with 'raw'), text (max length)
function attr_fields(): array
{
	$nextYear = (int) date('Y') + 1;
	$colors = ['white', 'black', 'grey', 'silver', 'blue', 'red', 'green', 'yellow', 'brown', 'beige', 'other'];

	return [
		'year' => ['type' => 'number', 'min' => 1900, 'max' => $nextYear],
		'mileage' => ['type' => 'number', 'min' => 0, 'max' => 2_000_000, 'unit' => 'km'],
		'fuel' => ['type' => 'select', 'options' => ['petrol', 'diesel', 'lpg', 'hybrid', 'electric']],
		'gearbox' => ['type' => 'select', 'options' => ['manual', 'automatic']],
		'power' => ['type' => 'number', 'min' => 1, 'max' => 2000, 'unit' => 'kW'],
		'engine' => ['type' => 'number', 'min' => 1, 'max' => 10_000, 'unit' => 'cm³'],
		'body' => ['type' => 'select', 'options' => ['sedan', 'hatchback', 'wagon', 'suv', 'coupe', 'convertible', 'minivan', 'pickup', 'other']],
		'color' => ['type' => 'select', 'options' => $colors],
		'area' => ['type' => 'number', 'min' => 1, 'max' => 100_000, 'unit' => 'm²'],
		'plot' => ['type' => 'number', 'min' => 1, 'max' => 1_000_000, 'unit' => 'u.ares'],
		'rooms' => ['type' => 'number', 'min' => 0.5, 'max' => 50, 'step' => 0.5],
		'floor' => ['type' => 'text', 'max' => 12],
		'heating' => ['type' => 'select', 'options' => ['central', 'gas', 'electricity', 'storage', 'heat_pump', 'solid_fuel', 'no_heating']],
		'built' => ['type' => 'number', 'min' => 1800, 'max' => $nextYear],
		'furnished' => ['type' => 'select', 'options' => ['yes', 'partly', 'no']],
		'memory' => ['type' => 'number', 'min' => 1, 'max' => 100_000, 'unit' => 'GB'],
		'battery' => ['type' => 'number', 'min' => 1, 'max' => 100, 'unit' => '%'],
		'wheel' => ['type' => 'text', 'max' => 12],
		'frame' => ['type' => 'text', 'max' => 12],
		'gears' => ['type' => 'number', 'min' => 1, 'max' => 40],
		'size' => ['type' => 'text', 'max' => 12],
		'dimensions' => ['type' => 'text', 'max' => 40],
		'ram' => ['type' => 'number', 'min' => 1, 'max' => 2048, 'unit' => 'GB'],
		'ssd' => ['type' => 'number', 'min' => 1, 'max' => 100_000, 'unit' => 'GB'],
		'capacity' => ['type' => 'text', 'max' => 20],
		'energy' => ['type' => 'select', 'options' => ['A+++', 'A++', 'A+', 'A', 'B', 'C', 'D'], 'raw' => true],
		'length' => ['type' => 'number', 'min' => 1, 'max' => 500, 'unit' => 'cm'],
		'material' => ['type' => 'select', 'options' => ['wood', 'metal', 'plastic', 'glass', 'fabric', 'leather', 'other']],
		'age' => ['type' => 'text', 'max' => 20],
		'experience' => ['type' => 'select', 'options' => ['years_1', 'years_3', 'years_5', 'years_10', 'years_15']],
		'area_served' => ['type' => 'text', 'max' => 60],
		'controllers' => ['type' => 'number', 'min' => 0, 'max' => 10],
		'employment' => ['type' => 'select', 'options' => ['full_time', 'part_time', 'temporary', 'freelance']],
	];
}

// Fields of a subcategory: key => definition (with overrides applied)
function category_attr_fields(string $slug): array
{
	$all = attr_fields();
	$fields = [];

	foreach (CATEGORY_ATTRS[$slug]['fields'] ?? [] as $key => $field) {
		[$key, $override] = is_int($key) ? [$field, []] : [$key, $field];
		$fields[$key] = $override + $all[$key];
	}

	return $fields;
}

// Whether new / like new / used is asked for a subcategory
function category_has_condition(string $slug): bool
{
	return CATEGORY_ATTRS[$slug]['condition'] ?? true;
}

// Form input → [attrs to store, error text or '']. Empty fields are skipped;
// a draft ($strict = false) drops wrong values instead of reporting them
function clean_attrs(string $slug, array $input, bool $strict = true): array
{
	$attrs = [];

	foreach (category_attr_fields($slug) as $key => $field) {
		$raw = trim((string) ($input[$key] ?? ''));

		if ($raw === '') {
			continue;
		}

		$value = match ($field['type']) {
			'number' => attr_number($raw, $field),
			'select' => in_array($raw, $field['options'], true) ? (empty($field['raw']) ? 'v.' . $raw : $raw) : null,
			default => mb_strlen($raw) <= $field['max'] ? preg_replace('/\s+/u', ' ', $raw) : null,
		};

		if ($value !== null) {
			$attrs[$key] = $value;
		} elseif ($strict) {
			return [[], t('post.err_attr', ['field' => t('a.' . $key)])];
		}
	}

	return [$attrs, ''];
}

// "168 000" / "2,5" → number within the field's limits and step, or null
function attr_number(string $raw, array $field): int|float|null
{
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

// Stored value → form value: 'v.diesel' → 'diesel', 2.5 → '2,5'
function attr_input_value(mixed $value): string
{
	if (is_float($value)) {
		return str_replace('.', ',', (string) $value);
	}

	return str_starts_with((string) $value, 'v.') ? substr($value, 2) : (string) $value;
}

// Value for the listing page: options translated, numbers with their unit, texts in the page script
function attr_value(string $key, mixed $value): string
{
	if (is_int($value) || is_float($value)) {
		$unit = attr_fields()[$key]['unit'] ?? '';
		$unit = str_starts_with($unit, 'u.') ? t($unit) : $unit;
		$number = is_float($value) ? str_replace('.', ',', (string) $value) : ($unit !== '' ? format_number($value) : (string) $value);

		return $unit === '' ? $number : $number . ($unit === '%' ? '' : "\u{00A0}") . $unit;
	}

	return str_starts_with($value, 'v.') ? t($value) : (lang() === 'sr-cyrl' ? sr_cyr($value) : $value);
}

// Rows of the details block: condition (from its own column) first, then the attributes
function listing_details(array $listing): array
{
	$rows = [];

	if ($listing['condition'] !== null && !isset($listing['attrs']['condition']) && category_has_condition($listing['category'])) {
		$rows[] = [t('a.condition'), t('v.' . $listing['condition'])];
	}

	foreach ($listing['attrs'] as $key => $value) {
		$rows[] = [t('a.' . $key), attr_value($key, $value)];
	}

	return $rows;
}
