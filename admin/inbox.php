<?php
/* ==========================================================================
	Admin: Inbox (messages from the contact form, contact.php)
	GET: status (new by default, answered, all), topic, q (#id, name, e-mail,
	text), page. Reply by email (mailto, the visitor's address); POST here
	(csrf): action status, id, status (answered | new).
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = 'Inbox';
$activeNav = 'inbox';

$statusTabs = ['new' => 'New', 'answered' => 'Answered', 'all' => 'All'];
$topics = ['general', 'listing', 'account', 'fraud', 'business'];

$f = [
	'status' => list_param('status', array_keys($statusTabs)) ?: 'new',
	'topic' => list_param('topic', $topics),
	'q' => list_param('q'),
];
$back = list_url('inbox.php', $f, ['status' => $f['status'] === 'new' ? null : $f['status'], 'page' => (int) ($_GET['page'] ?? 1) > 1 ? (int) $_GET['page'] : null]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id = (int) ($_POST['id'] ?? 0);
	$status = (string) ($_POST['status'] ?? '');

	if (!csrf_valid()) {
		flash('danger', 'The form has expired. Please try again.');
	} elseif (in_array($status, ['new', 'answered'], true) && set_contact_status($id, $status, $currentAdmin['id'])) {
		log_action('contact.' . ($status === 'answered' ? 'answered' : 'reopened'), ['contact_id' => $id]);
		flash('success', 'Message #' . $id . ($status === 'answered' ? ' marked answered.' : ' is new again.'));
	}
	redirect(safe_back((string) ($_POST['back'] ?? ''), 'inbox.php'));
}

$query = ['status' => $f['status'] === 'all' ? '' : $f['status']] + $f;
$counts = contact_counts($query);
$total = count_contact_messages($query);
[$page, $offset] = list_page($total);
$messages = contact_messages($query, ADMIN_PAGE_SIZE, $offset);

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title">Inbox</h1>
		<p class="page-head__sub">Messages from the contact form. Answer by email, then mark as answered.</p>
	</div>
</div>

<section class="card" aria-label="Messages">
	<!-- Status tabs (keep the other filters) -->
	<nav class="tabs__list tabs__list--flush" aria-label="Filter by status">
		<?php foreach ($statusTabs as $value => $label): ?>
		<a class="tabs__tab" href="<?= e(list_url('inbox.php', ['status' => $value === 'new' ? null : $value] + $f)) ?>"<?= $value === $f['status'] ? ' aria-current="page"' : '' ?>>
			<?= e($label) ?>
			<span class="tabs__count"><?= (int) ($value === 'all' ? array_sum($counts) : ($counts[$value] ?? 0)) ?></span>
		</a>
		<?php endforeach; ?>
	</nav>

	<!-- Filters (GET; a select sends the form on change) -->
	<form class="toolbar" method="get" action="inbox.php" data-autosubmit>
		<input type="hidden" name="status" value="<?= e($f['status']) ?>">
		<div class="input-icon toolbar__search">
			<?= icon('search', 18) ?>
			<label class="visually-hidden" for="inbox-search">Search messages</label>
			<input class="input" id="inbox-search" name="q" type="search" placeholder="#ID, name, email, text…" value="<?= e($f['q']) ?>">
		</div>
		<label class="visually-hidden" for="inbox-topic">Topic</label>
		<select class="select" id="inbox-topic" name="topic">
			<option value="">All topics</option>
			<?php foreach ($topics as $topic): ?>
			<option value="<?= e($topic) ?>"<?= $topic === $f['topic'] ? ' selected' : '' ?>><?= e(t('contact.topic_' . $topic)) ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="btn btn--primary"><?= icon('search', 18) ?>Show</button>
		<a class="btn btn--ghost" href="<?= e(list_url('inbox.php', ['status' => $f['status'] === 'new' ? null : $f['status']])) ?>"><?= icon('refresh', 18) ?>Reset</a>
	</form>

	<?php if ($messages): ?>
	<ul class="list">
		<?php foreach ($messages as $m):
			$subject = 'Re: [' . SITE_NAME . ' #' . $m['id'] . '] ' . in_lang($m['lang'], fn() => t('contact.topic_' . $m['topic']));
		?>
		<li class="list__item list__item--top" id="message-<?= (int) $m['id'] ?>">
			<span class="stat__icon<?= $m['topic'] === 'fraud' ? ' stat__icon--danger' : '' ?>"><?= icon($m['topic'] === 'fraud' ? 'flag' : 'mail', 18) ?></span>
			<div class="list__main">
				<span class="list__title">#<?= (int) $m['id'] ?> · <?= e(t('contact.topic_' . $m['topic'])) ?> · <?= e($m['name']) ?></span>
				<div class="list__meta">
					<a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
					<?php if ($m['user_id']): ?> · <a href="user.php?id=<?= (int) $m['user_id'] ?>">account #<?= (int) $m['user_id'] ?></a><?php endif; ?>
					<?php if ($m['listing_id']): ?> · <a href="listing-edit.php?id=<?= (int) $m['listing_id'] ?>">listing #<?= (int) $m['listing_id'] ?></a><?php endif; ?>
					· <?= e($m['lang']) ?> · <?= e(format_date($m['created'], true)) ?>
				</div>
				<p class="message-text"><?= e($m['message']) ?></p>
				<?php if ($m['status'] === 'answered'): ?>
				<div class="list__meta">Answered by <?= e(user_name($m['answered_by'])) ?><?= $m['answered'] ? ' · ' . e(format_date($m['answered'], true)) : '' ?></div>
				<?php endif; ?>
			</div>
			<form class="list__aside" method="post" action="inbox.php">
				<?= csrf_field() ?>
				<input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
				<input type="hidden" name="back" value="<?= e($back) ?>">
				<a class="btn btn--secondary btn--sm" href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode($subject)) ?>"><?= icon('mail', 16) ?>Reply</a>
				<?php if ($m['status'] === 'new'): ?>
				<button type="submit" class="btn btn--success btn--sm" name="status" value="answered"><?= icon('check', 16) ?>Answered</button>
				<?php else: ?>
				<button type="submit" class="btn btn--ghost btn--sm" name="status" value="new">Mark new</button>
				<?php endif; ?>
			</form>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php else: ?>
	<div class="card__body muted"><?= $f['status'] === 'new' && !$f['q'] && !$f['topic'] ? 'No new messages.' : 'No messages match the filters.' ?></div>
	<?php endif; ?>

	<?= admin_pagination($total, $page, fn($n) => list_url('inbox.php', $f, ['status' => $f['status'] === 'new' ? null : $f['status'], 'page' => $n > 1 ? $n : null])) ?>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
