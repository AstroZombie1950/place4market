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

// Listing photo URL: photo_url($photo, 480) → source/img/demo/1048-1/480.webp
// $photo: ['path' => under source/, 'color' => '#rrggbb', 'sizes' => [long side => [w, h]]]
function photo_url(array $photo, int $size): string
{
	return ROOT_URL . $photo['path'] . '/' . $size . '.webp';
}

// Listing photo <img>: every stored size in srcset (the browser picks one for the box
// and screen density), width/height of the largest for the aspect ratio, average colour
// behind it until it loads (no CLS, no white flash).
// $sizes: "sizes" attribute for the place it is shown (PHOTO_SIZES in site.php).
// $opts: alt (empty = decorative), class, lazy (default true), priority (LCP photo),
// max: largest size offered (small boxes on 3x screens would otherwise pull a bigger file)
function photo(array $photo, string $sizes, array $opts = []): string
{
	$opts += ['alt' => '', 'class' => '', 'lazy' => true, 'priority' => false, 'max' => 0];
	$offered = array_filter($photo['sizes'], fn($size) => !$opts['max'] || $size <= $opts['max'], ARRAY_FILTER_USE_KEY)
		?: array_slice($photo['sizes'], -1, 1, true);
	$srcset = [];

	foreach ($offered as $size => [$w]) {
		$srcset[] = photo_url($photo, $size) . ' ' . $w . 'w';
	}

	[$width, $height] = reset($photo['sizes']);
	// Fallback src for browsers without srcset: a card-sized file
	$src = isset($offered[480]) ? 480 : array_key_first($offered);

	return '<img class="' . e(trim('pic ' . $opts['class'])) . '" src="' . e(photo_url($photo, $src)) . '"'
		. ' srcset="' . e(implode(', ', $srcset)) . '" sizes="' . e($sizes) . '"'
		. ' width="' . (int) $width . '" height="' . (int) $height . '" alt="' . e($opts['alt']) . '"'
		. ($opts['lazy'] && !$opts['priority'] ? ' loading="lazy"' : '')
		. ($opts['priority'] ? ' fetchpriority="high"' : '')
		. ' decoding="async" style="background-color:' . e($photo['color']) . '">';
}

// Listing video file URL; $video: ['path', 'file', 'width', 'height', 'duration', 'bytes', 'poster' => photo]
function video_url(array $video): string
{
	return ROOT_URL . $video['path'] . '/' . $video['file'];
}

// Video length as players show it (whole seconds, rounded down): 10.7 → "0:10", 75 → "1:15"
function video_time(float $seconds): string
{
	$total = (int) floor($seconds);

	return intdiv($total, 60) . ':' . str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT);
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
