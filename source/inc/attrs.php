<?php
/* ==========================================================================
	Listing details by subcategory: fields of the post form, their checks and
	how the values are shown on the listing page.
	Stored in listings.attrs (JSON) as raw values in field order: numbers,
	option keys ('v.diesel') and short texts. Labels: a.<key>, options: v.<value>.
	Demo values are ready strings ('168 000 km') and are shown as they are.
	========================================================================== */

// Subcategory → fields; 'condition' => false: new/used does not apply (property, stays, jobs, services,
// animals, food). A field is a key of attr_fields() or key => overrides (motorbike body types).
// Kind, brand and deal are columns of their own (taxonomy.php), not fields here; the model is a
// field, typed by the seller and shown next to the brand (post.php)
const ATTRS_VEHICLE = ['year', 'mileage', 'fuel', 'gearbox', 'power'];
const ATTRS_FURNITURE = ['material', 'dimensions', 'color'];
const ATTRS_WEAR = ['size', 'color'];
const ATTRS_ANIMAL = ['breed', 'age'];
const ATTRS_STAY = ['guests'];
const CATEGORY_ATTRS = [
	// Vozila
	'cars' => ['fields' => ['model', ...ATTRS_VEHICLE, 'body', 'color', 'damaged', 'financing']],
	'motorcycles' => ['fields' => ['model', 'year', 'mileage', 'engine', 'body' => ['options' => ['naked', 'sport', 'touring', 'enduro', 'scooter', 'chopper', 'other']], 'color']],
	'trucks-vans' => ['fields' => [...ATTRS_VEHICLE, 'damaged', 'financing']],
	'trailers' => ['fields' => ['year']],
	'boats' => ['fields' => ['year', 'length']],
	'tyres-wheels' => ['fields' => ['season', 'tyre_size']],
	'car-parts' => ['fields' => []],
	'car-accessories' => ['fields' => []],
	'motorcycle-gear' => ['fields' => ['size']],
	// Nekretnine
	'apartments' => ['fields' => ['area', 'rooms', 'floor', 'heating', 'built', 'furnished', 'new_build'], 'condition' => false],
	'houses' => ['fields' => ['area', 'plot', 'rooms', 'heating', 'built', 'furnished'], 'condition' => false],
	'cottages' => ['fields' => ['area', 'plot', 'rooms', 'heating'], 'condition' => false],
	'land' => ['fields' => ['plot'], 'condition' => false],
	'commercial' => ['fields' => ['area', 'floor', 'heating'], 'condition' => false],
	'garages' => ['fields' => ['area'], 'condition' => false],
	'rooms' => ['fields' => ['area', 'furnished'], 'condition' => false],
	// Odmor
	'mountains' => ['fields' => ATTRS_STAY, 'condition' => false],
	'spas' => ['fields' => ATTRS_STAY, 'condition' => false],
	'seaside' => ['fields' => ATTRS_STAY, 'condition' => false],
	'lakes-rivers' => ['fields' => ATTRS_STAY, 'condition' => false],
	'rural' => ['fields' => ATTRS_STAY, 'condition' => false],
	// Posao
	'remote-jobs' => ['fields' => ['employment'], 'condition' => false],
	'drivers-delivery' => ['fields' => ['employment'], 'condition' => false],
	'jobs-abroad' => ['fields' => ['employment'], 'condition' => false],
	'student-jobs' => ['fields' => ['employment'], 'condition' => false],
	'hospitality' => ['fields' => ['employment'], 'condition' => false],
	'construction-jobs' => ['fields' => ['employment'], 'condition' => false],
	'production-jobs' => ['fields' => ['employment'], 'condition' => false],
	'care-jobs' => ['fields' => ['employment'], 'condition' => false],
	'sales-jobs' => ['fields' => ['employment'], 'condition' => false],
	'office-jobs' => ['fields' => ['employment'], 'condition' => false],
	'beauty-jobs' => ['fields' => ['employment'], 'condition' => false],
	'other-jobs' => ['fields' => ['employment'], 'condition' => false],
	// Usluge
	'car-service' => ['fields' => ['experience', 'area_served'], 'condition' => false],
	'car-rental' => ['fields' => ['area_served'], 'condition' => false],
	'events' => ['fields' => ['experience', 'area_served'], 'condition' => false],
	'beauty-services' => ['fields' => ['experience', 'area_served'], 'condition' => false],
	'tradesmen' => ['fields' => ['experience', 'area_served'], 'condition' => false],
	'lessons' => ['fields' => ['experience', 'area_served'], 'condition' => false],
	'towing' => ['fields' => ['area_served'], 'condition' => false],
	'repairs' => ['fields' => ['experience', 'area_served'], 'condition' => false],
	'business-services' => ['fields' => ['experience', 'area_served'], 'condition' => false],
	'moving' => ['fields' => ['area_served'], 'condition' => false],
	'cleaning' => ['fields' => ['experience', 'area_served'], 'condition' => false],
	'other-services' => ['fields' => ['experience', 'area_served'], 'condition' => false],
	// Elektronika
	'phones' => ['fields' => ['memory', 'battery', 'color']],
	'tablets' => ['fields' => ['memory']],
	'laptops' => ['fields' => ['ram', 'ssd', 'battery']],
	'desktops' => ['fields' => ['ram', 'ssd']],
	'consoles' => ['fields' => ['memory', 'controllers']],
	'tv' => ['fields' => ['diagonal']],
	// Kućni aparati
	'fridges' => ['fields' => ['capacity', 'energy']],
	'washing-machines' => ['fields' => ['capacity', 'energy']],
	'dishwashers' => ['fields' => ['capacity', 'energy']],
	'cookers' => ['fields' => ['energy']],
	'air-conditioners' => ['fields' => ['capacity', 'energy']],
	'water-heaters' => ['fields' => ['capacity']],
	// Nameštaj
	'sofas' => ['fields' => ATTRS_FURNITURE],
	'beds' => ['fields' => ATTRS_FURNITURE],
	'tables-chairs' => ['fields' => ATTRS_FURNITURE],
	'wardrobes' => ['fields' => ATTRS_FURNITURE],
	'garden-furniture' => ['fields' => ATTRS_FURNITURE],
	'kitchens' => ['fields' => ['dimensions', 'color']],
	// Poljoprivreda
	'tractors' => ['fields' => ['model', 'year', 'power', 'hours']],
	'farm-machinery' => ['fields' => ['year']],
	'livestock' => ['fields' => ATTRS_ANIMAL, 'condition' => false],
	'plants' => ['fields' => [], 'condition' => false],
	'feed' => ['fields' => [], 'condition' => false],
	'farm-produce' => ['fields' => [], 'condition' => false],
	// Ljubimci
	'dogs' => ['fields' => ATTRS_ANIMAL, 'condition' => false],
	'cats' => ['fields' => ATTRS_ANIMAL, 'condition' => false],
	'birds' => ['fields' => ATTRS_ANIMAL, 'condition' => false],
	'other-pets' => ['fields' => ATTRS_ANIMAL, 'condition' => false],
	'pet-services' => ['fields' => ['experience', 'area_served'], 'condition' => false],
	// Moda
	'womens-clothing' => ['fields' => ATTRS_WEAR],
	'mens-clothing' => ['fields' => ATTRS_WEAR],
	'womens-shoes' => ['fields' => ATTRS_WEAR],
	'mens-shoes' => ['fields' => ATTRS_WEAR],
	'sports-shoes' => ['fields' => ATTRS_WEAR],
	'wedding' => ['fields' => ATTRS_WEAR],
	'workwear' => ['fields' => ATTRS_WEAR],
	'costumes' => ['fields' => ['size']],
	// Deca
	'strollers' => ['fields' => ['age', 'color']],
	'toys' => ['fields' => ['age', 'material']],
	'kids-clothing' => ['fields' => ATTRS_WEAR],
	'kids-shoes' => ['fields' => ATTRS_WEAR],
	'kids-bikes' => ['fields' => ['wheel']],
	'car-seats' => ['fields' => ['age']],
	// Sport
	'bikes' => ['fields' => ['wheel', 'frame', 'gears']],
	'winter-sports' => ['fields' => ['length', 'size']],
	// Ostalo
	'tickets' => ['fields' => [], 'condition' => false],
];

// Field types: number (min, max, step, unit; a unit "u.*" is translated), select (options:
// v.<option>, or shown as is with 'raw'), text (max length; 'raw' = a name, never put into Cyrillic),
// bool (a checkbox: stored as true when on)
function attr_fields(): array
{
	$nextYear = (int) date('Y') + 1;
	$colors = ['white', 'black', 'grey', 'silver', 'blue', 'red', 'green', 'yellow', 'brown', 'beige', 'other'];

	return [
		'model' => ['type' => 'text', 'max' => 40, 'raw' => true],
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
		'damaged' => ['type' => 'bool'],
		'financing' => ['type' => 'bool'],
		'new_build' => ['type' => 'bool'],
		'season' => ['type' => 'select', 'options' => ['summer', 'winter', 'all_season']],
		'tyre_size' => ['type' => 'text', 'max' => 20],
		'diagonal' => ['type' => 'number', 'min' => 10, 'max' => 150, 'unit' => '″'],
		'guests' => ['type' => 'number', 'min' => 1, 'max' => 50],
		'hours' => ['type' => 'number', 'min' => 0, 'max' => 100_000, 'unit' => 'u.hours'],
		'breed' => ['type' => 'text', 'max' => 40],
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
			'bool' => $raw === '1' ? true : null,
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

// "168 000" / "2,5" / "168 000 km" (the field's own unit typed after the number) → number
// within the field's limits and step, or null
function attr_number(string $raw, array $field): int|float|null
{
	$unit = $field['unit'] ?? '';
	if ($unit !== '') {
		$raw = preg_replace('/\s*' . preg_quote(str_starts_with($unit, 'u.') ? t($unit) : $unit, '/') . '$/iu', '', $raw);
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

// Value for the listing page: options translated, numbers with their unit, texts in the page script
function attr_value(string $key, mixed $value): string
{
	if (is_bool($value)) {
		return t($value ? 'v.yes' : 'v.no');
	}
	if (is_int($value) || is_float($value)) {
		$unit = attr_fields()[$key]['unit'] ?? '';
		$unit = str_starts_with($unit, 'u.') ? t($unit) : $unit;
		$number = is_float($value) ? str_replace('.', ',', (string) $value) : ($unit !== '' ? format_number($value) : (string) $value);

		return $unit === '' ? $number : $number . ($unit === '%' ? '' : "\u{00A0}") . $unit;
	}

	if (str_starts_with($value, 'v.')) {
		return t($value);
	}

	return lang() === 'sr-cyrl' && empty(attr_fields()[$key]['raw']) ? sr_cyr($value) : $value;
}

// Rows of the details block: deal, kind and brand, condition (from their own columns) first, then the
// attributes, the advertiser last
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

	foreach ($listing['attrs'] as $key => $value) {
		$rows[] = [t('a.' . $key), attr_value($key, $value)];
	}

	if (($listing['seller_type'] ?? null) === 'business') {
		$rows[] = [t('post.f_seller'), t('seller.business')];
	}

	return $rows;
}
