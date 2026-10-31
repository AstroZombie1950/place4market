<?php
/* ==========================================================================
	Set a user's password: php source/db/password.php <email> [--sql]
	The password is asked in the terminal (not shown) or read from a pipe.
	--sql: change nothing, print an UPDATE with the hash to run in phpMyAdmin
	on the hosting (the password itself never leaves this machine).
	========================================================================== */

require __DIR__ . '/cli.php';

$email = mb_strtolower(trim((string) ($argv[1] ?? '')));
$sqlOnly = in_array('--sql', $argv, true);

if ($email === '' || str_starts_with($email, '--')) {
	out('Usage: php source/db/password.php <email> [--sql]');
	exit(1);
}

if (!$sqlOnly && !user_credentials($email)) {
	out('No user with this email in the local database.');
	exit(1);
}

// Hidden input in a terminal, plain read from a pipe
$tty = function_exists('posix_isatty') ? posix_isatty(STDIN) : stream_isatty(STDIN);
if ($tty) {
	fwrite(STDOUT, 'New password: ');
	shell_exec('stty -echo');
}
$password = rtrim((string) fgets(STDIN), "\r\n");
if ($tty) {
	shell_exec('stty echo');
	fwrite(STDOUT, "\n");
}

if (mb_strlen($password) < 8 || strlen($password) > 72) {
	out('The password must be 8 to 72 characters long.');
	exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

if ($sqlOnly) {
	out("UPDATE users SET password_hash = '" . $hash . "' WHERE email = '" . str_replace("'", "''", $email) . "';");
	exit(0);
}

set_user_password(user_credentials($email)['id'], $hash);
log_event('info', 'Password set from the command line', ['user_id' => user_credentials($email)['id']], 'audit');
out('Password set for ' . $email . '.');
