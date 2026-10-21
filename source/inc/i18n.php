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

// Pick user content for the current language: ['en' => …, 'sr' => …]
function tc(array $pair): string
{
	return match (lang()) {
		'sr-latn' => $pair['sr'] ?? $pair['en'],
		'sr-cyrl' => sr_cyr($pair['sr'] ?? $pair['en']),
		default => $pair['en'] ?? $pair['sr'],
	};
}
