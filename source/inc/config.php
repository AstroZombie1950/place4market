<?php
/* ==========================================================================
	Settings file and database connection, loaded by log.php (first of all):
	the journal writes to the database through its own connection, before and
	independently of db.php.

	Settings (DB password…) are never in git and never in the web root:
	  P4M_CONFIG environment variable (VPS)
	  → <site root>/config/local.php (developer machine, in .gitignore)
	  → <site root>/../app-config.php (hosting, next to public_html)
	The file returns ['db' => ['host', 'port', 'name', 'user', 'pass'] + optional 'socket',
	'mail' => […] (mail.php), 'alerts' => […] (log.php)].
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

// New connection to the site database (UTC, exceptions on errors)
function db_connect(int $timeout = 5): PDO
{
	$c = config('db');
	$dsn = 'mysql:dbname=' . $c['name'] . ';charset=utf8mb4;'
		. (!empty($c['socket']) ? 'unix_socket=' . $c['socket'] : 'host=' . $c['host'] . ';port=' . ($c['port'] ?? 3306));

	$pdo = new PDO($dsn, $c['user'], $c['pass'], [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
		PDO::ATTR_TIMEOUT => $timeout,
	]);
	$pdo->exec("SET time_zone = '+00:00'");

	return $pdo;
}
