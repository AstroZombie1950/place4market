<?php
/* ==========================================================================
	Apply new migrations: php source/db/migrate.php
	On production the deploy runs it from the release folder before the new code
	goes live (.github/workflows/deploy.yml); the admin has the same
	(admin/migrations.php): source/inc/migrations.php.
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
