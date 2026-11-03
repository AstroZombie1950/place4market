<?php
/* ==========================================================================
	Admin: account actions (POST + csrf from user.php and users.php), then back
	to the page they came from (back) with a message.
	action:
	  save           id; name, email, phone, city, role, email_verified,
	                 phone_verified — admins only, not the own role
	  role           id, role — admins only (row menu), not the own role
	  note           id, note — internal note, staff
	  reset_link     id — password reset link to the user (their language), staff
	  end_sessions   id — every session and "remember me" ends, staff
	  delete         ids[] (or id) — as the owner's own deletion: listings and
	                 photos go, personal data removed; admins only, not staff
	                 accounts (change the role first)
	Block / unblock stay in moderate.php (also used from listings and reports).
	Every change is in the audit log (admin.user_*).
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';
require SOURCE_DIR . '/inc/media.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	redirect('users.php');
}

$action = (string) ($_POST['action'] ?? '');
$back = safe_back((string) ($_POST['back'] ?? ''), 'users.php');

if (!csrf_valid()) {
	flash('danger', 'The form has expired. Please try again.');
	redirect($back);
}

$roles = ['user' => 'User', 'moderator' => 'Moderator', 'admin' => 'Admin'];
$adminOnly = ['save', 'role', 'delete'];

if (in_array($action, $adminOnly, true) && !is_admin()) {
	flash('danger', 'Only administrators can change accounts, roles and delete users.');
	redirect($back);
}

$id = (int) ($_POST['id'] ?? 0);
$user = $id ? user($id) : null;

if ($action !== 'delete' && (!$user || $user['status'] === 'deleted')) {
	flash('danger', 'This account does not exist or was deleted.');
	redirect($back);
}

switch ($action) {
	case 'save':
		$f = [
			'name' => trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? ''))),
			'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
			'phone' => trim((string) ($_POST['phone'] ?? '')),
			'city' => (string) ($_POST['city'] ?? ''),
			'role' => (string) ($_POST['role'] ?? ''),
			'email_verified' => !empty($_POST['email_verified']),
			'phone_verified' => !empty($_POST['phone_verified']),
		];
		$error = match (true) {
			mb_strlen($f['name']) < 2 || mb_strlen($f['name']) > 100 => 'The name takes 2 to 100 characters.',
			!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 190 => 'Enter a valid email.',
			$f['email'] !== $user['email'] && email_taken($f['email']) => 'Another account already uses ' . $f['email'] . '.',
			$f['phone'] !== '' && !preg_match('/^\+?[0-9 ()\-]{6,20}$/', $f['phone']) => 'The phone takes digits, spaces, + ( ) -, 6 to 20 characters.',
			!in_array($f['city'], array_column(cities(), 'latn'), true) => 'Choose a city.',
			!isset($roles[$f['role']]) => 'Choose a role.',
			$f['role'] !== $user['role'] && $id === $currentAdmin['id'] => 'You cannot change your own role: ask another administrator.',
			default => '',
		};

		if ($error !== '') {
			flash('danger', $error);
			break;
		}

		staff_update_user($id, ['phone' => $f['phone'] !== '' ? $f['phone'] : null] + $f);

		$was = ['name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone'] ?? '', 'city' => $user['city'], 'role' => $user['role'],
			'email_verified' => $user['email_verified'], 'phone_verified' => $user['verified']];
		$changed = array_keys(array_filter($f, fn($value, $key) => $value !== $was[$key], ARRAY_FILTER_USE_BOTH));
		if ($changed) {
			log_action('admin.user_update', ['user_id' => $id, 'fields' => $changed] + (in_array('role', $changed, true) ? ['role' => $f['role']] : []));
		}
		flash('success', $changed ? 'Saved: ' . implode(', ', $changed) . '.' : 'Nothing changed.');
		break;

	case 'role':
		$role = (string) ($_POST['role'] ?? '');
		if (!isset($roles[$role]) || $id === $currentAdmin['id']) {
			flash('danger', 'You cannot change your own role.');
			break;
		}
		if (set_user_role($id, $role) && $role !== $user['role']) {
			log_action('admin.user_update', ['user_id' => $id, 'fields' => ['role'], 'role' => $role]);
		}
		flash('success', $user['name'] . ' is now ' . $roles[$role] . '.');
		break;

	case 'note':
		$note = trim(mb_substr(str_replace("\r\n", "\n", (string) ($_POST['note'] ?? '')), 0, 2000));
		set_staff_note($id, $note !== '' ? $note : null);
		log_action('admin.user_note', ['user_id' => $id]);
		flash('success', 'Note saved.');
		break;

	case 'reset_link':
		if ($user['status'] !== 'active') {
			flash('danger', 'A blocked user cannot reset the password: unblock first.');
			break;
		}
		revoke_auth_tokens($id, 'reset_password');
		$token = issue_auth_token($id, 'reset_password');
		$sent = in_lang($user['lang'] ?? 'sr-latn', fn() => send_template_mail($user['email'], 'reset', [
			'name' => person_name($user['name']),
			'link' => site_url(url('reset.php', ['token' => $token])),
		], ['user_id' => $id]));
		log_action('admin.user_reset_link', ['user_id' => $id, 'sent' => $sent]);
		flash($sent ? 'success' : 'danger', $sent ? 'Password reset link sent to ' . $user['email'] . ' (valid 1 hour).' : 'The email could not be sent: see the journal.');
		break;

	case 'end_sessions':
		if ($id === $currentAdmin['id']) {
			flash('danger', 'To end your own session, log out.');
			break;
		}
		end_user_sessions($id);
		log_action('admin.user_sessions_end', ['user_id' => $id]);
		flash('success', $user['name'] . ' is logged out on all devices.');
		break;

	case 'delete':
		$ids = array_values(array_unique(array_filter(array_map('intval', array_merge((array) ($_POST['ids'] ?? []), [$id])))));
		$done = 0;
		$skipped = [];

		foreach ($ids as $userId) {
			$target = user($userId);
			if (!$target || $target['status'] === 'deleted') {
				continue;
			}
			if ($target['role'] !== 'user') {
				$skipped[] = $target['name'];
				continue;
			}

			db()->beginTransaction();
			try {
				$paths = delete_account($userId);
				db()->commit();
			} catch (Throwable $e) {
				db()->rollBack();
				throw $e;
			}
			array_map('delete_photo_files', $paths);
			log_action('admin.user_delete', ['user_id' => $userId, 'photos' => count($paths)]);
			$done++;
		}

		flash($done ? 'success' : 'info', ($done ? plural($done, 'account', 'accounts') . ' deleted: listings removed, personal data erased.' : 'Nothing to delete.')
			. ($skipped ? ' Staff accounts are not deleted (change the role first): ' . implode(', ', $skipped) . '.' : ''));
		if ($done && str_starts_with($back, 'user.php')) {
			$back = 'users.php';
		}
		break;

	default:
		flash('danger', 'Unknown action.');
}

redirect($back);
