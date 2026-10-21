<?php
/* ==========================================================================
	Shared helpers (site + admin)
	========================================================================== */

// Escape any value for HTML output
function e($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Versioned asset URL, so assets can be cached for a year:
// asset('css/site.css') → source/css/site.css?v=1729500000 (changes when the file changes)
function asset(string $path): string
{
	$file = SOURCE_DIR . '/' . $path;

	return ASSETS_URL . '/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

// Price: 9800, 'EUR' → "9 800 €"; null → "By agreement"
function format_price(?int $amount, string $currency = 'RSD'): string
{
	if ($amount === null) {
		return 'By agreement';
	}

	$nbsp = "\u{00A0}";
	$number = number_format($amount, 0, ',', $nbsp);

	return $currency === 'EUR' ? $number . $nbsp . '€' : $number . $nbsp . 'RSD';
}

// Date: "2026-10-20 14:32" → "20.10.2026" (or with time)
function format_date(string $date, bool $withTime = false): string
{
	$ts = strtotime($date);

	return $ts ? date($withTime ? 'd.m.Y H:i' : 'd.m.Y', $ts) : '';
}

// Initials for avatars: "Marko Petrović" → "MP"
function initials(string $name): string
{
	$parts = preg_split('/\s+/u', trim($name)) ?: [];
	$out = '';

	foreach (array_slice($parts, 0, 2) as $part) {
		$out .= mb_strtoupper(mb_substr($part, 0, 1));
	}

	return $out;
}

// URL slug from Serbian Latin / English text: "Kuća i bašta" → "kuca-i-basta"
function slugify(string $text): string
{
	$map = [
		'č' => 'c', 'ć' => 'c', 'đ' => 'dj', 'š' => 's', 'ž' => 'z',
		'Č' => 'c', 'Ć' => 'c', 'Đ' => 'dj', 'Š' => 's', 'Ž' => 'z',
		'&' => 'and',
	];

	$text = mb_strtolower(strtr($text, $map));
	$text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

	return trim($text, '-');
}

// JSON for data-* attributes (escaped for HTML)
function attr_json(array $data): string
{
	return e(json_encode($data, JSON_UNESCAPED_UNICODE));
}
