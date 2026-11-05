<?php
/* ==========================================================================
	Apply new migrations: php source/db/migrate.php
	The same code runs on production right after a deploy (deploy-hook.php)
	and from the admin (admin/migrations.php): source/inc/migrations.php.
	========================================================================== */

require __DIR__ . '/cli.php';
require INC_DIR . '/migrations.php';

try {
	$done = migrations_apply('cli');
} catch (RuntimeException $e) {
	out($e->getMessage());
	exit(1);
}

foreach ($done as $version) {
	out('Applied ' . $version);
}

out($done ? 'Done: ' . count($done) . ' migration(s).' : 'Nothing to apply, the schema is up to date.');
