<?php
/* ==========================================================================
	Database (MariaDB 10.11): the shared connection and query helpers.
	Settings and db_connect() are in config.php (loaded by log.php).

	Dates are stored in UTC (the connection runs at +00:00) and handed to pages
	in the site time zone: db_local() on the way out, db_utc() on the way in.
	A failed connection or query throws: the error handler logs it and shows
	the 500 page with a code.
	========================================================================== */

// Shared connection, opened on first use
function db(): PDO
{
	static $pdo = null;

	return $pdo ??= db_connect();
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
