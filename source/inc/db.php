<?php
/* ==========================================================================
	Database (MariaDB 10.11): settings file, PDO connection, query helpers.

	Settings (DB password…) are never in git and never in the web root:
	  P4M_CONFIG environment variable (VPS)
	  → <site root>/config/local.php (developer machine, in .gitignore)
	  → <site root>/../app-config.php (hosting, next to public_html)
	The file returns ['db' => ['host', 'port', 'name', 'user', 'pass'] + optional 'socket'].

	Dates are stored in UTC (the connection runs at +00:00) and handed to pages
	in the site time zone: db_local() on the way out, db_utc() on the way in.
	A failed connection or query throws: the error handler logs it and shows
	the 500 page with a code.
	========================================================================== */

// Value from the settings file: config('db')
function config(string $key): mixed
{
	static $config = null;

	if ($config === null) {
		$local = ROOT_DIR . '/config/local.php';
		$file = getenv('P4M_CONFIG') ?: (is_file($local) ? $local : dirname(ROOT_DIR) . '/app-config.php');

		if (!is_file($file)) {
			throw new RuntimeException('Settings file not found: ' . $file);
		}

		$config = require $file;
	}

	return $config[$key] ?? null;
}

// Shared connection, opened on first use
function db(): PDO
{
	static $pdo = null;

	if ($pdo === null) {
		$c = config('db');
		$dsn = 'mysql:dbname=' . $c['name'] . ';charset=utf8mb4;'
			. (!empty($c['socket']) ? 'unix_socket=' . $c['socket'] : 'host=' . $c['host'] . ';port=' . ($c['port'] ?? 3306));

		$pdo = new PDO($dsn, $c['user'], $c['pass'], [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		]);
		$pdo->exec("SET time_zone = '+00:00'");
	}

	return $pdo;
}

function db_query(string $sql, array $params = []): PDOStatement
{
	$stmt = db()->prepare($sql);
	$stmt->execute($params);

	return $stmt;
}

// All rows
function db_all(string $sql, array $params = []): array
{
	return db_query($sql, $params)->fetchAll();
}

// First row or null
function db_one(string $sql, array $params = []): ?array
{
	return db_query($sql, $params)->fetch() ?: null;
}

// First column of the first row (false when there is no row)
function db_value(string $sql, array $params = []): mixed
{
	return db_query($sql, $params)->fetchColumn();
}

// First column of every row
function db_column(string $sql, array $params = []): array
{
	return db_query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
}

// INSERT / UPDATE / DELETE: number of affected rows
function db_exec(string $sql, array $params = []): int
{
	return db_query($sql, $params)->rowCount();
}

// Placeholders for IN (…): db_in([3, 5, 8]) → "?,?,?"; an empty list → "NULL" (matches nothing)
function db_in(array $values): string
{
	return $values ? implode(',', array_fill(0, count($values), '?')) : 'NULL';
}

// UTC from the database → site time "2026-10-30 22:58:03" (null stays null)
function db_local(?string $utc): ?string
{
	if ($utc === null) {
		return null;
	}

	return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
		->setTimezone(new DateTimeZone(date_default_timezone_get()))
		->format('Y-m-d H:i:s');
}

// Site time → UTC for the database
function db_utc(string $local): string
{
	return (new DateTimeImmutable($local))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
