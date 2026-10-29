<?php
/* ==========================================================================
	500 page (from log_fail_page() in log.php). Must work when anything else
	is broken: no header/footer, translations only if i18n is already loaded
	(otherwise English), absolute URLs. On php -S shows the error itself.
	Included inside log_fail_page(): uses its $detail
	========================================================================== */

$say = function (string $key, string $fallback, array $params = []): string {
	$value = function_exists('t') ? t($key, $params) : $key;

	return $value === $key ? strtr($fallback, ['{code}' => $params['code'] ?? '']) : $value;
};
$htmlLang = function_exists('lang_meta') ? lang_meta()['html'] : 'en';
$esc = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="<?= $esc($htmlLang) ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= $esc($say('500.title', 'Something went wrong')) ?> — Place4Market</title>
	<link rel="icon" href="/favicon.ico" sizes="32x32">
	<link rel="stylesheet" href="/source/css/variables.css">
	<link rel="stylesheet" href="/source/css/site.css">
</head>
<body class="site">
<main class="container page">
	<div class="empty">
		<a class="logo" href="/">Place<span class="logo__4">4</span>Market</a>
		<h1 class="empty__title"><?= $esc($say('500.title', 'Something went wrong')) ?></h1>
		<p><?= $esc($say('500.text', 'We have already logged the error and will look into it. Please try again in a few minutes.')) ?></p>
		<p><?= $esc($say('500.code', 'Error code: {code}', ['code' => request_id()])) ?></p>
<?php if (IS_DEV): ?>
		<pre><?= $esc($detail) ?></pre>
<?php endif; ?>
		<a class="btn btn--primary" href="/"><?= $esc($say('404.home', 'Go to the home page')) ?></a>
	</div>
</main>
</body>
</html>
