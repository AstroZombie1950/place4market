<?php
/* ==========================================================================
	Migrations: source/db/migrations/NNN_name.php, each returns its SQL
	(PHP, so the web server never shows the schema as text). Applied once each
	in name order; applied ones are listed in schema_migrations.
	Used by the console (source/db/migrate.php), the deploy hook (deploy-hook.php)
	and the admin (admin/migrations.php). Needs only log.php and db.php, so it
	works even when the rest of the code already expects the new schema.

	Rules: a migration is never edited after it reached production (changes go
	into a new file). It keeps the previous code working — new tables, new
	columns with defaults: the deploy hook runs right after the upload, so for a
	short time the new code meets the old schema and the old code the new one.
	Removing or renaming a column takes two deploys.
	========================================================================== */

const MIGRATIONS_LOCK = 'p4m_migrations';

// A migration that failed (already written to the journal as critical)
class MigrationFailed extends RuntimeException {}

function migrations_dir(): string
{
	return ROOT_DIR . '/source/db/migrations';
}

function migrations_table(): void
{
	db()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
		version VARCHAR(100) NOT NULL,
		applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (version)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

// All migration files in order: version => path
function migration_files(): array
{
	$files = glob(migrations_dir() . '/[0-9][0-9][0-9]_*.php') ?: [];
	sort($files);

	return array_combine(array_map(fn($file) => basename($file, '.php'), $files), $files);
}

// Applied migrations: version => applied_at (UTC)
function migrations_applied(): array
{
	migrations_table();

	return array_column(db_all('SELECT version, applied_at FROM schema_migrations ORDER BY version'), 'applied_at', 'version');
}

// Files not applied yet: version => path
function migrations_pending(): array
{
	return array_diff_key(migration_files(), migrations_applied());
}

// Applies the pending migrations in order and returns their versions.
// $by — who started it, for the journal: cli, deploy, admin.
// Stops at the first failure and throws (the ones before it stay applied).
// Two runs at once (deploy hook + admin) cannot overlap: the second one throws
function migrations_apply(string $by): array
{
	if ((int) db_value('SELECT GET_LOCK(?, 0)', [MIGRATIONS_LOCK]) !== 1) {
		throw new RuntimeException('Migrations are already being applied by another request');
	}

	$done = [];

	try {
		foreach (migrations_pending() as $version => $file) {
			// One statement at a time: PDO reports errors of the first statement only.
			// Statements are split on ";" at the end of a line, comments are dropped
			$sql = preg_replace('/^\s*--.*$/m', '', require $file);

			try {
				foreach (preg_split('/;\s*$/m', $sql) as $statement) {
					if (trim($statement) !== '') {
						db()->exec($statement);
					}
				}
				db_exec('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
			} catch (Throwable $e) {
				log_event('critical', 'Migration failed: ' . $version, ['version' => $version, 'by' => $by, 'error' => $e->getMessage(), 'applied' => $done], 'db');
				throw new MigrationFailed('Migration ' . $version . ' failed: ' . $e->getMessage(), 0, $e);
			}

			log_event('info', 'Migration applied', ['version' => $version, 'by' => $by], 'db');
			$done[] = $version;
		}
	} finally {
		db_value('SELECT RELEASE_LOCK(?)', [MIGRATIONS_LOCK]);
	}

	return $done;
}
