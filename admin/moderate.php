<?php
/* ==========================================================================
	Admin: moderation actions (POST + csrf from listings, listing-edit, reports,
	users), then back to the page they came from (back) with a message.
	action:
	  approve | reject | delete   ids[] (or id); reject: reason, comment, notify
	  block | unblock             user_id
	  report_review               report_id
	  report_resolve              report_id, resolution, note, message (to the seller on "edit")
	Every change is in the audit log; the seller gets a letter on approve and
	on reject (unless "notify" is off).
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';
require SOURCE_DIR . '/inc/media.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	redirect('listings.php');
}

$action = (string) ($_POST['action'] ?? '');
$back = safe_back((string) ($_POST['back'] ?? ''));

if (!csrf_valid()) {
	flash('danger', 'The form has expired. Please try again.');
	redirect($back);
}

// Listing ids: ids[] from the bulk bar (or "1,2,3" filled by a dialog), id from a single row
$ids = array_values(array_unique(array_filter(array_map('intval', array_merge(
	(array) ($_POST['ids'] ?? []),
	explode(',', (string) ($_POST['id_list'] ?? '')),
	[(int) ($_POST['id'] ?? 0)]
)))));

// Removed by staff: photo files go after the transaction
function remove_listing(int $id, array $context = []): bool
{
	db()->beginTransaction();
	try {
		$paths = staff_delete_listing($id);
		db()->commit();
	} catch (Throwable $e) {
		db()->rollBack();
		throw $e;
	}

	if ($paths === null) {
		return false;
	}

	array_map('delete_photo_files', $paths);
	log_action('listing.remove', ['listing_id' => $id, 'photos' => count($paths)] + $context);

	return true;
}

// Rejected with a reason and a message; the seller is told unless $notify is off
function reject_one(int $id, string $reason, string $comment, bool $notify, array $context = []): bool
{
	if (!reject_listing($id, $reason, $comment)) {
		return false;
	}

	log_action('listing.reject', ['listing_id' => $id, 'reason' => $reason, 'notified' => $notify] + $context);
	if ($notify) {
		notify_seller(find_listing($id), 'rejected');
	}

	return true;
}

switch ($action) {
	case 'approve':
		$done = 0;
		foreach ($ids as $id) {
			if (approve_listing($id)) {
				$done++;
				log_action('listing.approve', ['listing_id' => $id]);
				notify_seller(find_listing($id), 'approved');
			}
		}
		flash($done ? 'success' : 'info', $done ? plural($done, 'listing', 'listings') . ' approved, sellers notified' : 'Nothing to approve: the listings are not waiting for moderation.');
		break;

	case 'reject':
		$reason = (string) ($_POST['reason'] ?? '');
		$comment = trim(mb_substr((string) ($_POST['comment'] ?? ''), 0, 1000));

		if (!isset(reject_reasons()[$reason])) {
			flash('danger', 'Choose a reason.');
			break;
		}

		$done = count(array_filter($ids, fn($id) => reject_one($id, $reason, $comment, !empty($_POST['notify']))));
		flash($done ? 'success' : 'info', $done ? plural($done, 'listing', 'listings') . ' rejected' : 'Nothing to reject.');
		break;

	case 'delete':
		$done = count(array_filter($ids, fn($id) => remove_listing($id)));
		flash($done ? 'success' : 'info', $done ? plural($done, 'listing', 'listings') . ' removed' : 'Nothing to remove.');
		if ($done && str_starts_with($back, 'listing-edit.php')) {
			$back = 'listings.php';
		}
		break;

	case 'block':
	case 'unblock':
		$userId = (int) ($_POST['user_id'] ?? 0);
		$done = $action === 'block' ? block_user($userId) : unblock_user($userId);
		if ($done) {
			log_action('admin.user_' . $action, ['user_id' => $userId]);
		}
		flash($done ? 'success' : 'info', $done ? user_name($userId) . ($action === 'block' ? ' blocked: logged out, listings hidden' : ' unblocked') : 'Nothing changed (staff accounts cannot be blocked).');
		break;

	case 'report_review':
		$reportId = (int) ($_POST['report_id'] ?? 0);
		if (review_report($reportId)) {
			log_action('report.review', ['report_id' => $reportId, 'listing_id' => find_report($reportId)['listing_id']]);
			flash('success', 'Report #' . $reportId . ' is in review');
		}
		break;

	case 'report_resolve':
		$report = find_report((int) ($_POST['report_id'] ?? 0));
		$resolution = (string) ($_POST['resolution'] ?? '');
		$note = trim(mb_substr((string) ($_POST['note'] ?? ''), 0, 2000));

		if (!$report || $report['status'] === 'resolved' || !isset(report_resolutions()[$resolution])) {
			flash('danger', 'This report is already resolved or the resolution is unknown.');
			break;
		}

		db()->beginTransaction();
		try {
			resolve_report($report['id'], $resolution, $note, $currentAdmin['id']);
			db()->commit();
		} catch (Throwable $e) {
			db()->rollBack();
			throw $e;
		}

		$context = ['report_id' => $report['id']];
		$listing = find_listing($report['listing_id']);
		log_action('report.resolve', $context + ['listing_id' => $report['listing_id'], 'resolution' => $resolution]);

		// The listing and its seller, as resolved
		if ($resolution === 'edit') {
			$message = trim(mb_substr((string) ($_POST['message'] ?? ''), 0, 1000));
			reject_one($listing['id'], 'other', $message, true, $context);
		} elseif ($resolution === 'remove' || $resolution === 'remove_block') {
			remove_listing($listing['id'], $context);
		}
		if ($resolution === 'remove_block' && block_user($listing['user_id'])) {
			log_action('admin.user_block', ['user_id' => $listing['user_id']] + $context);
		}

		flash('success', 'Report #' . $report['id'] . ' resolved: ' . report_resolutions()[$resolution]);
		break;

	default:
		flash('danger', 'Unknown action.');
}

redirect($back);
