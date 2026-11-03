<?php
/* ==========================================================================
	Maintenance page (503, from bootstrap.php while admin → Settings →
	Maintenance mode is on). Staff log in at /admin/ and see the site as usual.
	========================================================================== */
?>
<!doctype html>
<html lang="<?= e(lang_meta()['html']) ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= e(t('maint.title')) ?> — Place4Market</title>
	<link rel="icon" href="/favicon.ico" sizes="32x32">
	<link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
	<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
</head>
<body class="site">
<main class="container page">
	<div class="empty">
		<span class="logo">Place<span class="logo__4">4</span>Market</span>
		<h1 class="empty__title"><?= e(t('maint.title')) ?></h1>
		<p><?= e(t('maint.text')) ?></p>
	</div>
</main>
</body>
</html>
