<?php
/* ==========================================================================
	Move an account to another number (checklist K15: 1–10 are the staff's):
		php source/db/user-id.php <from> <to>             move it
		php source/db/user-id.php <from> <to> --dry-run   show what would change
	<to> must be free. Every column that points to users.id moves with it: the
	foreign keys are read from the database itself (listings, messages,
	reviews, tokens…), plus the journal (log_entries.user_id and "user_id" in
	its context, so histories and the dashboard feed stay right). One
	transaction; the users' counter is set back to the largest number + 1 (at
	least 11), so the next registration does not hit a moved number.
	The account is logged out where it was logged in (the session holds the
	old number); "remember me" moves with it.
	========================================================================== */

require __DIR__ . '/cli.php';

const STAFF_IDS = 10;

[$from, $to] = [(int) ($argv[1] ?? 0), (int) ($argv[2] ?? 0)];
$dry = in_array('--dry-run', $argv, true);

if ($from < 1 || $to < 1 || $from === $to) {
	out('Usage: php source/db/user-id.php <from> <to> [--dry-run]');
	exit(1);
}
$user = db_one('SELECT id, name, role FROM users WHERE id = ?', [$from]);
if (!$user) {
	out("No account $from.");
	exit(1);
}
if (db_value('SELECT 1 FROM users WHERE id = ?', [$to])) {
	out("Number $to is taken: move that account first (to a free number).");
	exit(1);
}

// Columns that point to users.id: the foreign keys of this database, and the journal
$refs = db_all('SELECT k.TABLE_NAME AS t, k.COLUMN_NAME AS c FROM information_schema.KEY_COLUMN_USAGE k
	WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME = \'users\' AND k.REFERENCED_COLUMN_NAME = \'id\'
	ORDER BY k.TABLE_NAME, k.COLUMN_NAME');
$refs[] = ['t' => 'log_entries', 'c' => 'user_id'];

$counts = [];
foreach ($refs as $ref) {
	$counts["{$ref['t']}.{$ref['c']}"] = (int) db_value("SELECT COUNT(*) FROM `{$ref['t']}` WHERE `{$ref['c']}` = ?", [$from]);
}
// "user_id": <from> in the journal's context (the account an action was about)
$pattern = '"user_id":' . $from . '([,}])';
$counts['log_entries.context'] = (int) db_value('SELECT COUNT(*) FROM log_entries WHERE context REGEXP ?', ['"user_id":' . $from . '[,}]']);

out(sprintf('Account %d (%s, %s) → %d%s', $from, $user['name'], $user['role'], $to, $to > STAFF_IDS && $user['role'] !== 'user' ? ' (staff outside 1–' . STAFF_IDS . ')' : ''));
foreach (array_filter($counts) as $where => $n) {
	out("  $where: $n");
}
// The journal has no foreign key: rows of a former account <to> would join this one's history
$stale = (int) db_value('SELECT COUNT(*) FROM log_entries WHERE user_id = ?', [$to]);
if ($stale) {
	out("  Note: the journal already has $stale rows of a former account $to; they will show as this account's.");
}
if ($dry) {
	exit(0);
}

db_exec('SET FOREIGN_KEY_CHECKS = 0');
db()->beginTransaction();
try {
	db_exec('UPDATE users SET id = ? WHERE id = ?', [$to, $from]);
	foreach ($refs as $ref) {
		db_exec("UPDATE `{$ref['t']}` SET `{$ref['c']}` = ? WHERE `{$ref['c']}` = ?", [$to, $from]);
	}
	db_exec('UPDATE log_entries SET context = REGEXP_REPLACE(context, ?, ?) WHERE context REGEXP ?', [$pattern, '"user_id":' . $to . '\\1', '"user_id":' . $from . '[,}]']);
	db()->commit();
} catch (Throwable $e) {
	db()->rollBack();
	db_exec('SET FOREIGN_KEY_CHECKS = 1');
	throw $e;
}
db_exec('SET FOREIGN_KEY_CHECKS = 1');

// The counter: past the largest number, never below the staff's range
$next = max(STAFF_IDS + 1, (int) db_value('SELECT MAX(id) FROM users') + 1);
db()->exec('ALTER TABLE users AUTO_INCREMENT = ' . $next);

log_event('info', 'Account number changed', ['from' => $from, 'to' => $to, 'rows' => array_sum($counts)], 'db');
out("Done. Next registration gets $next or more.");
