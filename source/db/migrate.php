<?php
/* ==========================================================================
	Apply new migrations: php source/db/migrate.php
	Migrations are source/db/migrations/NNN_name.sql, applied once each in
	name order; applied ones are listed in schema_migrations. A migration is
	never edited after it reached production: changes go into a new file.
	========================================================================== */

require __DIR__ . '/cli.php';

db()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
	version VARCHAR(100) NOT NULL,
	applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$applied = db_column('SELECT version FROM schema_migrations');
$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);
$count = 0;

foreach ($files as $file) {
	$version = basename($file, '.sql');

	if (in_array($version, $applied, true)) {
		continue;
	}

	// One statement at a time: PDO reports errors of the first statement only.
	// Statements are split on ";" at the end of a line, comments are dropped
	$sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));

	foreach (preg_split('/;\s*$/m', $sql) as $statement) {
		if (trim($statement) !== '') {
			db()->exec($statement);
		}
	}

	db_exec('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
	log_event('info', 'Migration applied', ['version' => $version], 'db');
	out('Applied ' . $version);
	$count++;
}

out($count ? 'Done: ' . $count . ' migration(s).' : 'Nothing to apply, the schema is up to date.');
