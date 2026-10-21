<?php
/* ==========================================================================
	CLI: regenerate sr-cyrl.php from sr-latn.php
	Usage: php source/lang/build-cyrillic.php
	Only string values are transliterated, keys stay as they are.
	Abbreviations, brands and {placeholders} stay in Latin (see sr_cyr()).
	========================================================================== */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

define('SOURCE_DIR', dirname(__DIR__));
require SOURCE_DIR . '/inc/i18n.php';

$source = file_get_contents(__DIR__ . '/sr-latn.php');

// Transliterate every value literal: => '…'
$result = preg_replace_callback("/=> '((?:[^'\\\\]|\\\\.)*)'/u", function ($m) {
	return "=> '" . sr_cyr($m[1]) . "'";
}, $source);

// Header comment for the generated file
$result = preg_replace(
	'~/\* =+.*?=+ \*/~s',
	"/* ==========================================================================\n\tСрпски (ћирилица)\n\tGenerated from sr-latn.php by build-cyrillic.php. Safe to edit by hand,\n\tbut re-running the script overwrites the changes.\n\t========================================================================== */",
	$result,
	1
);

file_put_contents(__DIR__ . '/sr-cyrl.php', $result);
echo "sr-cyrl.php written\n";
