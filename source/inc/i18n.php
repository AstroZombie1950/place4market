<?php
/* ==========================================================================
	i18n: languages, translations, plurals, human addresses, transliteration
	Languages: sr-latn (main, at the root /), sr-cyrl (/cir/…), en (/en/…).
	The language comes from the address only (no cookie, no browser
	language): every version has its own URL for people and search engines.
	Pages have human addresses (page_routes(): /oglasi/vozila/automobili,
	/en/listing/vw-golf-7-1048, /pomoc); route.php maps them to the page
	scripts, url() / lang_link() build them. Endpoints (favorite.php,
	phone.php…) keep their .php address and get the language as ?lang=.
	========================================================================== */

const DEFAULT_LANG = 'sr-latn';
// Strings missing in a language come from here (English is the source text)
const FALLBACK_LANG = 'en';

// Supported languages: address prefix and the set of address words (sr | en)
function languages(): array
{
	return [
		'sr-latn' => ['label' => 'SR', 'name' => 'Srpski (latinica)', 'html' => 'sr-Latn', 'locale' => 'sr_RS@latin', 'prefix' => '', 'slugs' => 'sr'],
		'sr-cyrl' => ['label' => 'СР', 'name' => 'Српски (ћирилица)', 'html' => 'sr-Cyrl', 'locale' => 'sr_RS', 'prefix' => 'cir', 'slugs' => 'sr'],
		'en' => ['label' => 'EN', 'name' => 'English', 'html' => 'en', 'locale' => 'en_US', 'prefix' => 'en', 'slugs' => 'en'],
	];
}

// Language of the request: the address prefix (route.php), else ?lang= (endpoints
// and old addresses, which then redirect), else the main language
function i18n_init(): void
{
	$lang = $GLOBALS['__route']['lang'] ?? null;

	if ($lang === null) {
		$lang = isset($_GET['lang']) && is_string($_GET['lang']) && isset(languages()[$_GET['lang']]) ? $_GET['lang'] : DEFAULT_LANG;
	}

	i18n_use($lang);
}

// Strings of a language (the admin is always in English: i18n_use('en'))
function i18n_use(string $lang): void
{
	$lang = isset(languages()[$lang]) ? $lang : DEFAULT_LANG;
	$GLOBALS['__lang'] = $lang;
	$GLOBALS['__strings'] = require SOURCE_DIR . '/lang/' . $lang . '.php';
	$GLOBALS['__fallback'] = $lang === FALLBACK_LANG ? [] : require SOURCE_DIR . '/lang/' . FALLBACK_LANG . '.php';
}

// Runs $fn in another language and comes back: a letter in the recipient's language
function in_lang(string $lang, callable $fn): mixed
{
	$saved = [$GLOBALS['__lang'] ?? null, $GLOBALS['__strings'] ?? [], $GLOBALS['__fallback'] ?? []];
	i18n_use($lang);

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

/* ---------- Human addresses ---------- */

// Pages with human addresses: script => [Serbian word, English word].
// Changing a word changes the addresses: add the old one to route_aliases()
function page_routes(): array
{
	return [
		'index.php' => ['', ''],
		'category.php' => ['oglasi', 'listings'],
		'listing.php' => ['oglas', 'listing'],
		'seller.php' => ['prodavac', 'seller'],
		'post.php' => ['postavi-oglas', 'post-ad'],
		'profile.php' => ['profil', 'profile'],
		'favorites.php' => ['omiljeno', 'favorites'],
		'messages.php' => ['poruke', 'messages'],
		'auth.php' => ['prijava', 'login'],
		'reset.php' => ['nova-lozinka', 'reset-password'],
		'verify.php' => ['potvrda', 'verify'],
		'about.php' => ['o-nama', 'about'],
		'help.php' => ['pomoc', 'help'],
		'safety.php' => ['bezbednost', 'safety'],
		'terms.php' => ['uslovi-koriscenja', 'terms'],
		'privacy.php' => ['privatnost', 'privacy'],
		'contact.php' => ['kontakt', 'contact'],
	];
}

// Former address words: [word => script], answered with a 301 to the current one
function route_aliases(): array
{
	return [];
}

// Internal link in a given language, always from the site root:
// lang_link('en', 'category.php', ['cat' => 'cars', 'page' => 2]) → /en/listings/vehicles/cars?page=2
// Routed pages: listing.php takes id (+ slug), seller.php id, category.php cat (the
// category key = its English slug); the rest of the params go to the query.
// Endpoints keep /name.php and carry the language as ?lang= (not for the main one)
function lang_link(string $code, string $path, array $params = []): string
{
	unset($params['lang']);
	$params = array_filter($params, fn($v) => $v !== null && $v !== '' && $v !== false);
	$meta = languages()[$code];
	$route = page_routes()[$path] ?? null;

	if ($route === null) {
		if ($code !== DEFAULT_LANG) {
			$params['lang'] = $code;
		}

		return '/' . $path . ($params ? '?' . http_build_query($params) : '');
	}

	$segments = $route[$meta['slugs'] === 'en' ? 1 : 0] === '' ? [] : [$route[$meta['slugs'] === 'en' ? 1 : 0]];

	if ($path === 'category.php') {
		[$words, $params] = category_route_words($params, $meta['slugs']);
		array_push($segments, ...$words);
	} elseif ($path === 'listing.php' && isset($params['id'])) {
		$segments[] = (isset($params['slug']) ? $params['slug'] . '-' : '') . (int) $params['id'];
		unset($params['id'], $params['slug']);
	} elseif ($path === 'seller.php' && isset($params['id'])) {
		$segments[] = (int) $params['id'];
		unset($params['id']);
	}

	return ($meta['prefix'] === '' ? '' : '/' . $meta['prefix']) . '/' . implode('/', $segments)
		. ($params ? '?' . http_build_query($params) : '');
}

// Address words of a results page in the sr | en set: the category, then the deal (real estate), the
// kind or the brand, the place, the district (/oglasi/nekretnine/stanovi/prodaja/beograd/zemun). Values
// that do not fit the category stay in the query. Returns [words, params left]. Without the
// dictionaries (a console script) only the category goes into the path
function category_route_words(array $params, string $set): array
{
	$words = [];
	$key = isset($params['cat']) ? (string) $params['cat'] : '';
	$catPath = $key !== '' ? category_url_path($key, $set) : null;

	if ($key !== '' && $catPath === null) {
		return [[], $params];
	}
	if ($catPath) {
		$words = $catPath;
		unset($params['cat']);
	}

	$isChild = $catPath !== null && count($catPath) === 2;
	if (function_exists('category_types')) {
		if ($isChild && isset($params['deal']) && in_array($params['deal'], category_deals($key), true)) {
			$words[] = deal_word($params['deal'], $set);
			unset($params['deal']);
		}
		if ($catPath !== null && isset($params['type'])) {
			$types = $isChild ? category_types($key) : section_types($key, array_column(find_category($key)[0]['children'] ?? [], 'slug'));
			if (isset($types[$params['type']])) {
				$words[] = $types[$params['type']][$set === 'en' ? 'en' : 'sr'];
				unset($params['type']);
			}
		}
		if ($isChild && !isset($types[$params['type'] ?? '']) && isset($params['brand'], category_brands($key)[$params['brand']])) {
			$words[] = $params['brand'];
			unset($params['brand']);
		}
	}

	if (isset($params['city']) && function_exists('cities')) {
		foreach (cities() as $city) {
			if ($city['latn'] === $params['city']) {
				$words[] = $set === 'en' ? $city['slug_en'] : $city['slug'];
				unset($params['city']);
				foreach (function_exists('districts') ? districts()[$city['latn']] ?? [] : [] as $district) {
					if ($district['latn'] === ($params['district'] ?? null)) {
						$words[] = $district['slug'];
						unset($params['district']);
					}
				}
				break;
			}
		}
	}

	return [$words, $params];
}

// Address words of a category: [parent, child] in the sr | en set; null if unknown
function category_url_path(string $key, string $set): ?array
{
	if (!function_exists('categories')) {
		return null;
	}

	$slug = fn(array $c) => $set === 'sr' ? $c['slug_sr'] : $c['slug'];

	foreach (categories() as $parent) {
		if ($parent['slug'] === $key) {
			return [$slug($parent)];
		}
		foreach ($parent['children'] as $child) {
			if ($child['slug'] === $key) {
				return [$slug($parent), $slug($child)];
			}
		}
	}

	return null;
}

// Address word from any text: "VW Golf 7 1.6 TDI, 2016" → "vw-golf-7-1-6-tdi-2016";
// Cyrillic is converted, Serbian letters lose their marks (đ → dj), at most $max characters
function url_slug(string $text, int $max = 70): string
{
	$text = strtr(mb_strtolower(sr_lat($text)), ['č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'dj']);
	$text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: '';
	$text = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');

	if (strlen($text) > $max) {
		$text = substr($text, 0, $max);
		$text = str_contains($text, '-') ? substr($text, 0, strrpos($text, '-')) : $text;
	}

	return $text;
}

// Page script of the request: the routed one or the file itself
function current_script(): string
{
	return $GLOBALS['__route']['script'] ?? basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
}

// Reads a human address (path without the query): ['lang', 'script', 'params'] or
// null (404). Lenient: case, a trailing slash, a word from the other language or a
// former word are accepted — the page then redirects to its exact address (301).
// Results page words stay raw in params['cat_path']: route_resolve() looks them up (category, deal,
// kind or brand, place, district)
function route_match(string $path): ?array
{
	$segments = array_values(array_filter(explode('/', strtolower($path)), fn($s) => $s !== ''));
	$lang = DEFAULT_LANG;

	foreach (languages() as $code => $meta) {
		if ($meta['prefix'] !== '' && ($segments[0] ?? null) === $meta['prefix']) {
			$lang = $code;
			array_shift($segments);
			break;
		}
	}

	if (!$segments) {
		return ['lang' => $lang, 'script' => 'index.php', 'params' => []];
	}

	$word = array_shift($segments);
	$script = route_aliases()[$word] ?? null;
	foreach (page_routes() as $file => $words) {
		if ($word !== '' && in_array($word, $words, true)) {
			$script = $file;
			break;
		}
	}

	$rest = count($segments);

	return match (true) {
		$script === null, $script === 'index.php' => null,
		$script === 'category.php' && $rest <= 7 => ['lang' => $lang, 'script' => $script, 'params' => $rest ? ['cat_path' => $segments] : []],
		$script === 'listing.php' && $rest === 1 && preg_match('/^(?:([a-z0-9-]*)-)?(\d+)$/', $segments[0], $m) === 1
			=> ['lang' => $lang, 'script' => $script, 'params' => ['id' => $m[2]]],
		$script === 'seller.php' && $rest === 1 && ctype_digit($segments[0]) => ['lang' => $lang, 'script' => $script, 'params' => ['id' => $segments[0]]],
		!in_array($script, ['category.php', 'listing.php', 'seller.php'], true) && $rest === 0 => ['lang' => $lang, 'script' => $script, 'params' => []],
		default => null,
	};
}

// The same page in another language (switcher): the page's canonical address
// with the current query (filters, sorting) kept
function lang_url(string $code): string
{
	[$path, $params] = $GLOBALS['page']['canonical'] ?? [current_script(), []];
	$query = array_diff_key($_GET, ['lang' => true, 'cat_path' => true]);

	return lang_link($code, $path, $params + $query);
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
		'PlayStation', 'Naked', 'Instagram', 'Facebook',
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
