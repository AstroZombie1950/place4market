<?php
/* ==========================================================================
	Bootstrap for command-line database scripts (migrate.php, seed.php, password.php).
	Command line only: closed for the web here and in .htaccess (source/db/).
	========================================================================== */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

define('ROOT_DIR', dirname(__DIR__, 2));
define('SOURCE_DIR', ROOT_DIR . '/source');
define('INC_DIR', SOURCE_DIR . '/inc');

// On a developer machine the journal goes to ./logs, like with php -S
if (!getenv('P4M_LOG_DIR') && is_file(ROOT_DIR . '/config/local.php')) {
	putenv('P4M_LOG_DIR=' . ROOT_DIR . '/logs');
}

require INC_DIR . '/log.php';
require INC_DIR . '/helpers.php';
require INC_DIR . '/i18n.php';
require INC_DIR . '/db.php';
require INC_DIR . '/repo.php';

// Errors of the script itself go to the terminal as well as to the journal
ini_set('display_errors', 'stderr');

function out(string $line): void
{
	fwrite(STDOUT, $line . "\n");
}
