<?php
/* ==========================================================================
	Messages: conversation list + thread
	GET: c = conversation id (on phones opens the thread right away)
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$requested = isset($_GET['c']) ? (int) $_GET['c'] : null;
$activeId = $requested ?? ($conversations[0]['id'] ?? null);
$quickReplies = [t('m.quick_1'), t('m.quick_2'), t('m.quick_3')];

$page['title'] = t('m.title');
$page['robots'] = 'noindex, follow';
$page['nav'] = 'messages';
$page['search'] = false;

require INC_DIR . '/header.php';
?>

<div class="container page page--chat">
	<div class="page-head">
		<h1 class="page-title"><?= e(t('m.title')) ?></h1>
	</div>

	<div class="chat" data-chat<?= $requested ? ' data-open-on-load' : '' ?>>
		<!-- Conversation list -->
		<section class="chat__list" aria-labelledby="convs-title">
			<h2 class="visually-hidden" id="convs-title"><?= e(t('m.conversations')) ?></h2>
			<div class="chat__search">
				<label class="visually-hidden" for="chat-search"><?= e(t('m.search')) ?></label>
				<input class="input input--sm" id="chat-search" type="search" placeholder="<?= e(t('m.search')) ?>…" data-chat-search>
			</div>
			<ul class="chat__convs">
				<?php foreach ($conversations as $conv):
					$other = user($conv['with']);
					$item = find_listing($conv['listing_id']);
					$last = end($conv['messages']);
					$rowId = 'conv-' . $conv['id'];
				?>
				<li id="<?= e($rowId) ?>">
					<button type="button" class="conv<?= $conv['unread'] ? ' is-unread' : '' ?>" data-conv="<?= (int) $conv['id'] ?>" aria-current="<?= $conv['id'] === $activeId ? 'true' : 'false' ?>">
						<?= avatar($other['name']) ?>
						<span class="conv__main">
							<span class="conv__top">
								<span class="conv__name"><?= e(person_name($other['name'])) ?></span>
								<time class="conv__time" datetime="<?= e(date('c', strtotime($last['at']))) ?>"><?= e(chat_time($last['at'])) ?></time>
							</span>
							<span class="conv__listing"><?= e(listing_title($item)) ?></span>
							<span class="conv__bottom">
								<span class="conv__last"><?= $last['from'] === CURRENT_USER_ID ? e(t('m.you')) . ': ' : '' ?><?= e(tc($last['text'])) ?></span>
								<?php if ($conv['unread']): ?>
								<span class="count-badge" aria-label="<?= e(t('m.unread', ['n' => $conv['unread']])) ?>"><?= (int) $conv['unread'] ?></span>
								<?php endif; ?>
							</span>
						</span>
					</button>
				</li>
				<?php endforeach; ?>
				<li class="chat__no-results" data-chat-no-results hidden><p><?= e(t('m.no_results')) ?></p></li>
			</ul>
		</section>

		<!-- Threads (one visible at a time) -->
		<?php foreach ($conversations as $conv):
			$other = user($conv['with']);
			$item = find_listing($conv['listing_id']);
			$threadId = 'thread-' . $conv['id'];
			$prevDay = null;
		?>
		<section class="thread" id="<?= e($threadId) ?>" data-thread="<?= (int) $conv['id'] ?>" aria-label="<?= e(person_name($other['name'])) ?>"<?= $conv['id'] === $activeId ? '' : ' hidden' ?>>
			<!-- Head: back, person, delete -->
			<div class="thread__head">
				<button type="button" class="btn btn--ghost btn--icon thread__back" data-chat-back aria-label="<?= e(t('m.back')) ?>"><?= icon('chevron-left') ?></button>
				<?= avatar($other['name'], 'sm') ?>
				<div class="thread__who">
					<h2 class="thread__name"><?= e(person_name($other['name'])) ?></h2>
				</div>
				<button type="button" class="btn btn--ghost btn--icon" aria-label="<?= e(t('m.delete_chat')) ?>" data-confirm="<?= e(t('m.delete_confirm')) ?>" data-confirm-label="<?= e(t('common.delete')) ?>" data-done="<?= e(t('m.deleted')) ?>" data-remove-target="#<?= e($threadId) ?>, #conv-<?= (int) $conv['id'] ?>"><?= icon('trash', 20) ?></button>
			</div>

			<!-- Listing the conversation is about -->
			<div class="thread__listing">
				<span class="thread__thumb"><?= placeholder($item['category'], 20) ?></span>
				<div class="thread__listing-text">
					<p class="thread__listing-title"><?= e(listing_title($item)) ?></p>
					<p class="price"><?= price_html($item['price'], $item['currency']) ?></p>
				</div>
				<a class="btn btn--soft btn--sm" href="<?= e(listing_url($item)) ?>"><?= e(t('m.view_listing')) ?></a>
			</div>

			<!-- Messages -->
			<div class="thread__body" role="log" aria-live="polite" tabindex="0">
				<?php foreach ($conv['messages'] as $msg):
					$day = date('Y-m-d', strtotime($msg['at']));
					$mine = $msg['from'] === CURRENT_USER_ID;
				?>
				<?php if ($day !== $prevDay): $prevDay = $day; ?>
				<p class="day-sep"><?= e(chat_day($msg['at'])) ?></p>
				<?php endif; ?>
				<div class="msg <?= $mine ? 'msg--out' : 'msg--in' ?>">
					<span class="visually-hidden"><?= e($mine ? t('m.you') : person_name($other['name'])) ?>:</span>
					<?= e(tc($msg['text'])) ?>
					<time class="msg__time" datetime="<?= e(date('c', strtotime($msg['at']))) ?>"><?= date('H:i', strtotime($msg['at'])) ?></time>
				</div>
				<?php endforeach; ?>
			</div>

			<!-- Quick replies -->
			<div class="quick" role="group" aria-label="<?= e(t('m.quick')) ?>">
				<?php foreach ($quickReplies as $reply): ?>
				<button type="button" class="chip" data-quick><?= e($reply) ?></button>
				<?php endforeach; ?>
			</div>

			<!-- Composer -->
			<form class="composer" method="post" action="" data-composer>
				<input type="hidden" name="conversation" value="<?= (int) $conv['id'] ?>">
				<button type="button" class="btn btn--ghost btn--icon" aria-label="<?= e(t('m.attach')) ?>"><?= icon('paperclip', 20) ?></button>
				<label class="visually-hidden" for="msg-<?= (int) $conv['id'] ?>"><?= e(t('m.message_input')) ?></label>
				<textarea class="textarea composer__input" id="msg-<?= (int) $conv['id'] ?>" name="text" rows="1" placeholder="<?= e(t('m.placeholder')) ?>"></textarea>
				<button type="submit" class="btn btn--primary" aria-label="<?= e(t('m.send')) ?>"><?= icon('send', 18) ?></button>
			</form>
		</section>
		<?php endforeach; ?>

		<!-- Nothing selected -->
		<div class="chat__empty"<?= $activeId ? ' hidden' : '' ?>><?= e(t('m.empty')) ?></div>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
