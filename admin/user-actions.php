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
	  avatar_remove  id — the profile photo goes (back to the initials), staff
	  review_delete  id, review — a review about or by this user is deleted, staff
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
	flash('danger', at('common.form_expired'));
	redirect($back);
}

$roles = ['user' => role_name('user'), 'moderator' => role_name('moderator'), 'admin' => role_name('admin')];
$adminOnly = ['save', 'role', 'delete'];

if (in_array($action, $adminOnly, true) && !is_admin()) {
	flash('danger', at('ua.admins_only'));
	redirect($back);
}

$id = (int) ($_POST['id'] ?? 0);
$user = $id ? user($id) : null;

if ($action !== 'delete' && (!$user || $user['status'] === 'deleted')) {
	flash('danger', at('ua.no_account'));
	redirect($back);
}

// A moderator does not act on staff accounts: logging an admin out, their photo, a reset link (A27)
if (in_array($action, ['reset_link', 'end_sessions', 'avatar_remove'], true) && $user['role'] !== 'user'
	&& !is_admin() && $user['id'] !== $currentAdmin['id']) {
	flash('danger', at('ua.staff_admins_only'));
	redirect($back);
}

switch ($action) {
	case 'save':
		$f = [
			'name' => trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? ''))),
			'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
			// Serbian numbers only, stored as +381XXXXXXXXX (auth.php, phone_normalize())
			'phone' => ($phone = trim((string) ($_POST['phone'] ?? ''))) !== '' ? (phone_normalize($phone) ?? $phone) : '',
			'city' => (string) ($_POST['city'] ?? ''),
			'role' => (string) ($_POST['role'] ?? ''),
			'email_verified' => !empty($_POST['email_verified']),
			'phone_verified' => !empty($_POST['phone_verified']),
		];
		$error = match (true) {
			mb_strlen($f['name']) < 2 || mb_strlen($f['name']) > 100 => at('ua.err_name'),
			!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 190 => at('ua.err_email'),
			$f['email'] !== $user['email'] && email_taken($f['email']) => at('ua.err_email_taken', ['email' => $f['email']]),
			$f['phone'] !== '' && phone_normalize($f['phone']) !== $f['phone'] => at('ua.err_phone'),
			$f['phone_verified'] && $f['phone'] === '' => at('ua.err_phone_none'),
			// One number — one account (as with the SMS check)
			$f['phone_verified'] && phone_confirmed_elsewhere($f['phone'], $id) => at('ua.err_phone_taken'),
			!in_array($f['city'], array_column(cities(), 'latn'), true) => at('edit.err_city'),
			!isset($roles[$f['role']]) => at('ua.err_role'),
			$f['role'] !== $user['role'] && $id === $currentAdmin['id'] => at('ua.err_own_role'),
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
		flash('success', $changed ? at('common.saved_fields', ['fields' => field_labels($changed)]) : at('common.nothing_changed'));
		break;

	case 'role':
		$role = (string) ($_POST['role'] ?? '');
		if (!isset($roles[$role]) || $id === $currentAdmin['id']) {
			flash('danger', at('ua.own_role'));
			break;
		}
		if (set_user_role($id, $role) && $role !== $user['role']) {
			log_action('admin.user_update', ['user_id' => $id, 'fields' => ['role'], 'role' => $role]);
		}
		flash('success', at('ua.role_now', ['name' => $user['name'], 'role' => $roles[$role]]));
		break;

	case 'note':
		$note = trim(mb_substr(str_replace("\r\n", "\n", (string) ($_POST['note'] ?? '')), 0, 2000));
		set_staff_note($id, $note !== '' ? $note : null);
		log_action('admin.user_note', ['user_id' => $id]);
		flash('success', at('ua.note_saved'));
		break;

	case 'reset_link':
		if ($user['status'] !== 'active') {
			flash('danger', at('ua.reset_blocked'));
			break;
		}
		revoke_auth_tokens($id, 'reset_password');
		$token = issue_auth_token($id, 'reset_password');
		$sent = in_lang($user['lang'] ?? 'sr-latn', fn() => send_template_mail($user['email'], 'reset', [
			'name' => person_name($user['name']),
			'link' => site_url(url('reset.php', ['token' => $token])),
		], ['user_id' => $id]));
		log_action('admin.user_reset_link', ['user_id' => $id, 'sent' => $sent]);
		flash($sent ? 'success' : 'danger', $sent ? at('ua.reset_sent', ['email' => $user['email']]) : at('ua.mail_failed'));
		break;

	case 'end_sessions':
		if ($id === $currentAdmin['id']) {
			flash('danger', at('ua.own_session'));
			break;
		}
		end_user_sessions($id);
		log_action('admin.user_sessions_end', ['user_id' => $id]);
		flash('success', at('ua.sessions_ended', ['name' => $user['name']]));
		break;

	case 'avatar_remove':
		if (($old = set_user_avatar($id, null)) !== null) {
			delete_photo_files($old);
			log_action('admin.user_avatar_remove', ['user_id' => $id]);
		}
		flash('success', at('ua.avatar_removed', ['name' => $user['name']]));
		break;

	case 'review_delete':
		$review = find_review((int) ($_POST['review'] ?? 0));
		if ($review && in_array($id, [$review['seller_id'], $review['author_id']], true) && delete_review($review['id'])) {
			log_action('admin.review_delete', ['review_id' => $review['id'], 'user_id' => $review['seller_id'], 'author_id' => $review['author_id'], 'listing_id' => $review['listing_id']]);
			flash('success', at('ua.review_deleted'));
		}
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

		flash($done ? 'success' : 'info', ($done ? atp('ua.deleted', $done) : at('ua.delete_none'))
			. ($skipped ? ' ' . at('ua.staff_skipped', ['names' => implode(', ', $skipped)]) : ''));
		if ($done && str_starts_with($back, 'user.php')) {
			$back = 'users.php';
		}
		break;

	default:
		flash('danger', at('common.unknown_action'));
}

redirect($back);
