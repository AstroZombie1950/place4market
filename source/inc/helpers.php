<?php
/* ==========================================================================
	Shared helpers (site + admin)
	========================================================================== */

// Where uploads live (disk or cloud bucket): photo_url(), video_url(), csp() use it
require_once __DIR__ . '/storage.php';

/* ---------- Security headers (site and admin, sent from both bootstraps) ---------- */

// Content Security Policy: our own files only. Inline style attributes stay allowed (photo()
// paints the average colour), scripts never inline: JSON in <script type="application/json">
// is data, not code. blob: is the photo preview in the post form. A new integration (maps,
// analytics) adds its origin here
const CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; "
	. "media-src 'self' blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; "
	. "form-action 'self'; frame-ancestors 'self'";

// Google Analytics 4 (gtag.js, loaded only after consent): added to the site's CSP while
// config('analytics')['ga4'] is set. Hosts from Google's CSP guide for GA4; www.google.com
// and *.g.doubleclick.net take hits too (seen 11.11, and with Google signals on)
const CSP_ANALYTICS = [
	'script-src' => 'https://www.googletagmanager.com',
	'img-src' => 'https://*.google-analytics.com https://*.googletagmanager.com https://www.google.com',
	'connect-src' => 'https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com https://www.google.com https://*.g.doubleclick.net',
];

// GA4 measurement ID from app-config.php ('analytics' => ['ga4' => 'G-…']), '' when not set
function analytics_id(): string
{
	$id = (string) (config('analytics')['ga4'] ?? '');

	return preg_match('/^G-[A-Z0-9]{4,16}$/', $id) ? $id : '';
}

// CSP of a page: photos and videos from the cloud bucket when one is set (site and admin),
// the analytics hosts on the site while analytics is on
function csp(bool $analytics = false): string
{
	$extra = [];
	if (preg_match('#^https://[^/]+#', storage_public_url(), $m)) {
		$extra['img-src'][] = $m[0];
		$extra['media-src'][] = $m[0];
	}
	if ($analytics && analytics_id() !== '') {
		foreach (CSP_ANALYTICS as $directive => $hosts) {
			$extra[$directive][] = $hosts;
		}
	}

	return preg_replace_callback('/(^|; )([a-z-]+) ([^;]+)/', fn($m) => $m[1] . $m[2] . ' ' . $m[3] . (isset($extra[$m[2]]) ? ' ' . implode(' ', $extra[$m[2]]) : ''), CSP);
}

// Sent by PHP: OpenLiteSpeed ignores Header in .htaccess. $analytics: the site, not the admin
function security_headers(bool $analytics = false): void
{
	if (headers_sent() || PHP_SAPI === 'cli') {
		return;
	}

	$https = ($_SERVER['HTTPS'] ?? '') === 'on';

	header('Content-Security-Policy: ' . csp($analytics) . ($https ? '; upgrade-insecure-requests' : ''));
	header('X-Content-Type-Options: nosniff');
	header('X-Frame-Options: SAMEORIGIN');
	header('Referrer-Policy: strict-origin-when-cross-origin');
	header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
	header('Cross-Origin-Opener-Policy: same-origin');
	if ($https) {
		header('Strict-Transport-Security: max-age=31536000');
	}
}

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

// Listing photo URL: photo_url($photo, 480) → /source/img/demo/1048-1/480.webp,
// /uploads/listings/…/480.webp or, for a photo in the cloud, https://<bucket URL>/uploads/listings/…/480.webp
// $photo: ['path' => stored path (storage.php), 'color' => '#rrggbb', 'sizes' => [long side => [w, h]]]
function photo_url(array $photo, int $size): string
{
	return storage_file_url($photo['path'], $size . '.webp');
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
	return storage_file_url($video['path'], $video['file']);
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

// Crawlers, link previews and test tools: not counted as views, get no phone numbers
function is_bot(): bool
{
	$agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

	return $agent === '' || (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|lighthouse|headless|curl|wget|python|scrapy/i', $agent);
}
