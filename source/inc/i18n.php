<?php
/* ==========================================================================
	i18n: language detection, translations, plurals, links
	Languages: en (default), sr-latn (Serbian Latin), sr-cyrl (Serbian Cyrillic)
	Current language: ?lang=… → cookie → default.
	All links go through url(), so switching to SEO paths later (/sr/…)
	means changing lang_link() and lang_url() only.
	========================================================================== */

const DEFAULT_LANG = 'en';
const LANG_COOKIE = 'p4m_lang';

// Supported languages
function languages(): array
{
	return [
		'en' => ['label' => 'EN', 'name' => 'English', 'html' => 'en', 'locale' => 'en_US'],
		'sr-latn' => ['label' => 'SR', 'name' => 'Srpski (latinica)', 'html' => 'sr-Latn', 'locale' => 'sr_RS@latin'],
		'sr-cyrl' => ['label' => 'СР', 'name' => 'Српски (ћирилица)', 'html' => 'sr-Cyrl', 'locale' => 'sr_RS'],
	];
}

// Detect language, remember it in a cookie, load strings
function i18n_init(): void
{
	$langs = languages();
	$lang = DEFAULT_LANG;

	if (isset($_GET['lang']) && isset($langs[$_GET['lang']])) {
		$lang = $_GET['lang'];
		setcookie(LANG_COOKIE, $lang, [
			'expires' => time() + 31536000,
			'path' => '/',
			'samesite' => 'Lax',
		]);
	} elseif (isset($_COOKIE[LANG_COOKIE]) && isset($langs[$_COOKIE[LANG_COOKIE]])) {
		$lang = $_COOKIE[LANG_COOKIE];
	}

	$GLOBALS['__lang'] = $lang;
	$GLOBALS['__strings'] = require SOURCE_DIR . '/lang/' . $lang . '.php';
	$GLOBALS['__fallback'] = $lang === DEFAULT_LANG ? [] : require SOURCE_DIR . '/lang/' . DEFAULT_LANG . '.php';
}

// Runs $fn in another language and comes back: a letter in the recipient's language
function in_lang(string $lang, callable $fn): mixed
{
	$saved = [$GLOBALS['__lang'] ?? null, $GLOBALS['__strings'] ?? [], $GLOBALS['__fallback'] ?? []];
	$lang = isset(languages()[$lang]) ? $lang : DEFAULT_LANG;
	$GLOBALS['__lang'] = $lang;
	$GLOBALS['__strings'] = require SOURCE_DIR . '/lang/' . $lang . '.php';
	$GLOBALS['__fallback'] = $lang === DEFAULT_LANG ? [] : require SOURCE_DIR . '/lang/' . DEFAULT_LANG . '.php';

	try {
		return $fn();
	} finally {
		[$GLOBALS['__lang'], $GLOBALS['__strings'], $GLOBALS['__fallback']] = $saved;
	}
}

// Current language code
function lang(): string
{
	return $GLOBALS['__lang'] ?? DEFAULT_LANG;
}

// Meta of the current language (label, html lang, …)
function lang_meta(?string $code = null): array
{
	return languages()[$code ?? lang()];
}

// Translate: t('nav.home'), t('l.member_since', ['date' => '…'])
function t(string $key, array $params = []): string
{
	$value = $GLOBALS['__strings'][$key] ?? $GLOBALS['__fallback'][$key] ?? $key;

	if (is_array($value)) {
		$value = $value['other'] ?? $value['many'] ?? reset($value);
	}

	return $params ? strtr($value, brace_keys($params)) : $value;
}

// Translate with plural: tp('cat.count', 5) → "5 listings" / "5 oglasa"
function tp(string $key, int $n, array $params = []): string
{
	$forms = $GLOBALS['__strings'][$key] ?? $GLOBALS['__fallback'][$key] ?? $key;
	$params['n'] = number_format($n, 0, ',', "\u{00A0}");

	if (!is_array($forms)) {
		return strtr($forms, brace_keys($params));
	}

	return strtr($forms[plural_form($n)] ?? reset($forms), brace_keys($params));
}

// Plural form name: English one/other, Serbian one/few/many
function plural_form(int $n): string
{
	if (lang() === 'en') {
		return $n === 1 ? 'one' : 'other';
	}

	$mod10 = $n % 10;
	$mod100 = $n % 100;

	if ($mod10 === 1 && $mod100 !== 11) {
		return 'one';
	}
	if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
		return 'few';
	}

	return 'many';
}

// ['n' => 5] → ['{n}' => 5]
function brace_keys(array $params): array
{
	$out = [];

	foreach ($params as $k => $v) {
		$out['{' . $k . '}'] = (string) $v;
	}

	return $out;
}

// Internal link with the current language: url('listing.php', ['id' => 5])
function url(string $path, array $params = []): string
{
	return lang_link(lang(), $path, $params);
}

// Internal link in a given language; the home page is always "./", never "index.php"
function lang_link(string $code, string $path, array $params = []): string
{
	unset($params['lang']);

	if ($code !== DEFAULT_LANG) {
		$params['lang'] = $code;
	}

	$params = array_filter($params, fn($v) => $v !== null && $v !== '' && $v !== false);

	return ($path === 'index.php' ? './' : $path) . ($params ? '?' . http_build_query($params) : '');
}

// Same page in another language for the switcher (keeps the query string).
// Always carries ?lang=, even for the default language, so the cookie gets updated.
function lang_url(string $code): string
{
	$params = $_GET;
	$params['lang'] = $code;
	$path = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');

	return ($path === 'index.php' ? './' : $path) . '?' . http_build_query($params);
}

// Hidden input to keep the language in GET forms
function lang_input(): string
{
	return lang() === DEFAULT_LANG ? '' : '<input type="hidden" name="lang" value="' . e(lang()) . '">';
}

// Serbian Latin → Cyrillic. Keeps placeholders, tags, abbreviations,
// words with digits or q/w/x/y, and brand names in Latin.
function sr_cyr(string $text): string
{
	static $map = [
		'DŽ' => 'Џ', 'Dž' => 'Џ', 'dž' => 'џ',
		'LJ' => 'Љ', 'Lj' => 'Љ', 'lj' => 'љ',
		'NJ' => 'Њ', 'Nj' => 'Њ', 'nj' => 'њ',
		'A' => 'А', 'B' => 'Б', 'C' => 'Ц', 'Č' => 'Ч', 'Ć' => 'Ћ', 'D' => 'Д', 'Đ' => 'Ђ', 'E' => 'Е',
		'F' => 'Ф', 'G' => 'Г', 'H' => 'Х', 'I' => 'И', 'J' => 'Ј', 'K' => 'К', 'L' => 'Л', 'M' => 'М',
		'N' => 'Н', 'O' => 'О', 'P' => 'П', 'R' => 'Р', 'S' => 'С', 'Š' => 'Ш', 'T' => 'Т', 'U' => 'У',
		'V' => 'В', 'Z' => 'З', 'Ž' => 'Ж',
		'a' => 'а', 'b' => 'б', 'c' => 'ц', 'č' => 'ч', 'ć' => 'ћ', 'd' => 'д', 'đ' => 'ђ', 'e' => 'е',
		'f' => 'ф', 'g' => 'г', 'h' => 'х', 'i' => 'и', 'j' => 'ј', 'k' => 'к', 'l' => 'л', 'm' => 'м',
		'n' => 'н', 'o' => 'о', 'p' => 'п', 'r' => 'р', 's' => 'с', 'š' => 'ш', 't' => 'т', 'u' => 'у',
		'v' => 'в', 'z' => 'з', 'ž' => 'ж',
	];
	static $keep = [
		'Place4Market', 'iPhone', 'MacBook', 'Air', 'Golf', 'Gorenje', 'Atomic', 'Yamaha', 'Thule',
		'PlayStation', 'Liman', 'Naked', 'Instagram', 'Facebook',
	];

	return preg_replace_callback('/\{[a-z_]+\}|<[^>]*>|&[a-z#0-9]+;|[\p{L}\p{N}]+/u', function ($m) use ($map, $keep) {
		$word = $m[0];

		if ($word[0] === '{' || $word[0] === '<' || $word[0] === '&') {
			return $word;
		}
		if (in_array($word, $keep, true) || preg_match('/\d|[qwxyQWXY]|\p{Lu}{2,}/u', $word)) {
			return $word;
		}

		return strtr($word, $map);
	}, $text) ?? $text;
}

// Serbian Cyrillic → Latin (exact, letter by letter). For text written in
// Cyrillic: Latin pages and search (search_text is always Latin).
function sr_lat(string $text): string
{
	static $map = [
		'Љ' => 'Lj', 'Њ' => 'Nj', 'Џ' => 'Dž', 'љ' => 'lj', 'њ' => 'nj', 'џ' => 'dž',
		'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Ђ' => 'Đ', 'Е' => 'E', 'Ж' => 'Ž',
		'З' => 'Z', 'И' => 'I', 'Ј' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
		'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'Ћ' => 'Ć', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H',
		'Ц' => 'C', 'Ч' => 'Č', 'Ш' => 'Š',
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'ђ' => 'đ', 'е' => 'e', 'ж' => 'ž',
		'з' => 'z', 'и' => 'i', 'ј' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
		'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'ћ' => 'ć', 'у' => 'u', 'ф' => 'f', 'х' => 'h',
		'ц' => 'c', 'ч' => 'č', 'ш' => 'š',
	];

	// Words in capitals stay in capitals: ЉУБАВ → LJUBAV, not LjUBAV
	return preg_replace_callback('/\p{L}+/u', function ($m) use ($map) {
		$latin = strtr($m[0], $map);
		return mb_strlen($m[0]) > 1 && mb_strtoupper($m[0]) === $m[0] ? mb_strtoupper($latin) : $latin;
	}, $text) ?? $text;
}

// Site content (info pages) for the current language: ['en' => …, 'sr' => …]
function tc(array $pair): string
{
	return match (lang()) {
		'sr-latn' => $pair['sr'] ?? $pair['en'],
		'sr-cyrl' => sr_cyr($pair['sr'] ?? $pair['en']),
		default => $pair['en'] ?? $pair['sr'],
	};
}

// Language of a text a user wrote: mostly Cyrillic → sr-cyrl; Serbian Latin letters
// (č ć ž š đ) → sr-latn; otherwise the page decides (English page → en, Serbian → sr-latn)
function detect_text_lang(string $text): string
{
	$cyrillic = preg_match_all('/\p{Cyrillic}/u', $text);
	$latin = preg_match_all('/\p{Latin}/u', $text);

	if ($cyrillic > $latin) {
		return 'sr-cyrl';
	}
	if (preg_match('/[čćžšđ]/iu', $text)) {
		return 'sr-latn';
	}

	return lang() === 'en' ? 'en' : 'sr-latn';
}

// User content (listings, messages) as written, in the visitor's script:
// Serbian follows the page (Latin ↔ Cyrillic), English stays as is, nothing is translated.
// $lang is the author's: sr-latn | sr-cyrl | en
function user_text(string $text, string $lang): string
{
	return match (true) {
		$lang === 'sr-latn' && lang() === 'sr-cyrl' => sr_cyr($text),
		$lang === 'sr-cyrl' && lang() === 'sr-latn' => sr_lat($text),
		default => $text,
	};
}
