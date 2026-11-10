<?php
/* ==========================================================================
	Admin: Inbox (messages from the contact form, contact.php)
	GET: status (new by default, answered, all), topic, q (#id, name, e-mail,
	text), page. Reply by email (mailto, the visitor's address); POST here
	(csrf): action status, id, status (answered | new).
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = at('inbox.title');
$activeNav = 'inbox';

$statusTabs = ['new' => at('inbox.tab_new'), 'answered' => at('inbox.tab_answered'), 'all' => at('common.all')];
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
		flash('danger', at('common.form_expired'));
	} elseif (in_array($status, ['new', 'answered'], true) && set_contact_status($id, $status, $currentAdmin['id'])) {
		log_action('contact.' . ($status === 'answered' ? 'answered' : 'reopened'), ['contact_id' => $id]);
		flash('success', at($status === 'answered' ? 'inbox.marked_answered' : 'inbox.marked_new', ['id' => $id]));
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
		<h1 class="page-head__title"><?= e(at('inbox.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('inbox.sub')) ?></p>
	</div>
</div>

<section class="card" aria-label="<?= e(at('inbox.messages')) ?>">
	<!-- Status tabs (keep the other filters) -->
	<nav class="tabs__list tabs__list--flush" aria-label="<?= e(at('list.filter_status')) ?>">
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
			<label class="visually-hidden" for="inbox-search"><?= e(at('inbox.search')) ?></label>
			<input class="input" id="inbox-search" name="q" type="search" placeholder="<?= e(at('inbox.search_ph')) ?>" value="<?= e($f['q']) ?>">
		</div>
		<label class="visually-hidden" for="inbox-topic"><?= e(at('inbox.topic')) ?></label>
		<select class="select" id="inbox-topic" name="topic">
			<option value=""><?= e(at('inbox.all_topics')) ?></option>
			<?php foreach ($topics as $topic): ?>
			<option value="<?= e($topic) ?>"<?= $topic === $f['topic'] ? ' selected' : '' ?>><?= e(at('topic.' . $topic)) ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="btn btn--primary"><?= icon('search', 18) ?><?= e(at('common.show')) ?></button>
		<a class="btn btn--ghost" href="<?= e(list_url('inbox.php', ['status' => $f['status'] === 'new' ? null : $f['status']])) ?>"><?= icon('refresh', 18) ?><?= e(at('common.reset')) ?></a>
	</form>

	<?php if ($messages): ?>
	<ul class="list">
		<?php foreach ($messages as $m):
			$subject = 'Re: [' . SITE_NAME . ' #' . $m['id'] . '] ' . in_lang($m['lang'], fn() => t('contact.topic_' . $m['topic']));
		?>
		<li class="list__item list__item--top" id="message-<?= (int) $m['id'] ?>">
			<span class="stat__icon<?= $m['topic'] === 'fraud' ? ' stat__icon--danger' : '' ?>"><?= icon($m['topic'] === 'fraud' ? 'flag' : 'mail', 18) ?></span>
			<div class="list__main">
				<span class="list__title">#<?= (int) $m['id'] ?> · <?= e(at('topic.' . $m['topic'])) ?> · <?= e($m['name']) ?></span>
				<div class="list__meta">
					<a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
					<?php if ($m['user_id']): ?> · <a href="user.php?id=<?= (int) $m['user_id'] ?>"><?= e(at('inbox.account', ['id' => (int) $m['user_id']])) ?></a><?php endif; ?>
					<?php if ($m['listing_id']): ?> · <a href="listing-edit.php?id=<?= (int) $m['listing_id'] ?>"><?= e(at('inbox.listing', ['id' => (int) $m['listing_id']])) ?></a><?php endif; ?>
					· <?= e($m['lang']) ?> · <?= e(format_date($m['created'], true)) ?>
				</div>
				<p class="message-text"><?= e($m['message']) ?></p>
				<?php if ($m['status'] === 'answered'): ?>
				<div class="list__meta"><?= e(at('inbox.answered_by', ['name' => user_name($m['answered_by'])])) ?><?= $m['answered'] ? ' · ' . e(format_date($m['answered'], true)) : '' ?></div>
				<?php endif; ?>
			</div>
			<form class="list__aside" method="post" action="inbox.php">
				<?= csrf_field() ?>
				<input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
				<input type="hidden" name="back" value="<?= e($back) ?>">
				<a class="btn btn--secondary btn--sm" href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode($subject)) ?>"><?= icon('mail', 16) ?><?= e(at('inbox.reply')) ?></a>
				<?php if ($m['status'] === 'new'): ?>
				<button type="submit" class="btn btn--success btn--sm" name="status" value="answered"><?= icon('check', 16) ?><?= e(at('inbox.answered')) ?></button>
				<?php else: ?>
				<button type="submit" class="btn btn--ghost btn--sm" name="status" value="new"><?= e(at('inbox.mark_new')) ?></button>
				<?php endif; ?>
			</form>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php else: ?>
	<div class="card__body muted"><?= $f['status'] === 'new' && !$f['q'] && !$f['topic'] ? e(at('inbox.none_new')) : e(at('inbox.none')) ?></div>
	<?php endif; ?>

	<?= admin_pagination($total, $page, fn($n) => list_url('inbox.php', $f, ['status' => $f['status'] === 'new' ? null : $f['status'], 'page' => $n > 1 ? $n : null])) ?>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
