<?php
/* ==========================================================================
	Admin texts: English and Russian, apart from the site texts
	Dictionaries: source/lang/admin/en.php (the source of keys) and ru.php; a key
	missing in Russian falls back to English, then to the key itself.
	at('key', [params]) — a text, {name} placeholders replaced;
	atp('key', $n, [params]) — plural forms ['one', 'few', 'many'] / ['one', 'other'],
	{n} is the number.
	t() stays the site's (English in the admin) for letters to users in their own
	language: admin texts never go through it.
	========================================================================== */

// Admin languages: code => own name (the switcher)
const ADMIN_LANGS = ['en' => 'English', 'ru' => 'Русский'];

function admin_i18n_use(string $lang): void
{
	$lang = isset(ADMIN_LANGS[$lang]) ? $lang : 'en';
	$GLOBALS['__admin_lang'] = $lang;
	$GLOBALS['__admin_strings'] = require SOURCE_DIR . '/lang/admin/' . $lang . '.php';
	$GLOBALS['__admin_fallback'] = $lang === 'en' ? [] : require SOURCE_DIR . '/lang/admin/en.php';
}

function admin_lang(): string
{
	return $GLOBALS['__admin_lang'] ?? 'en';
}

function at(string $key, array $params = []): string
{
	$value = $GLOBALS['__admin_strings'][$key] ?? $GLOBALS['__admin_fallback'][$key] ?? $key;

	if (is_array($value)) {
		$value = $value['other'] ?? $value['many'] ?? reset($value);
	}

	return $params ? strtr($value, brace_keys($params)) : $value;
}

function atp(string $key, int $n, array $params = []): string
{
	$forms = $GLOBALS['__admin_strings'][$key] ?? $GLOBALS['__admin_fallback'][$key] ?? $key;
	$params['n'] = number_format($n, 0, ',', "\u{00A0}");

	if (!is_array($forms)) {
		return strtr($forms, brace_keys($params));
	}

	// A Russian text without its own forms has the English ones
	$form = plural_form($n, isset($forms['other']) ? 'en' : admin_lang());

	return strtr($forms[$form] ?? reset($forms), brace_keys($params));
}
