<?php
/* ==========================================================================
	Messages: conversation list + thread. Logged-in users only.
	GET: c = conversation id (on phones opens the thread right away; its
	messages become read); listing = id from "Write to seller": the buyer's
	conversation about it, or an empty one that is created by the first message.
	POST (+ csrf), action:
	  send    conversation (or listing for a new one) + text
	  read    conversation (opened in the browser without a reload)
	  delete  conversation: hidden for this user only
	main.js sends with Accept: application/json and gets JSON; without JS the
	form posts here and comes back to the thread.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

// Characters per message
const MESSAGE_MAX = 2000;

$me = require_login();

// Answer to a POST: JSON for main.js, otherwise a message and back ($back: params of messages.php)
function messages_answer(int $status, array $data, array $back = []): never
{
	if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		http_response_code($status);
		echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	if (isset($data['error'])) {
		flash('danger', $data['error']);
	} elseif (isset($data['flash'])) {
		flash('success', $data['flash']);
	}
	redirect(url('messages.php', $back));
}

// The other side gets an email about a new message (unless turned off in the profile), once per
// conversation until they read it, in their language (or the one they write in here; the seller:
// of the listing until they reply).
// $textLang: the message's own, so its script follows the letter
function notify_recipient(array $conversation, array $listing, int $senderId, string $text, string $textLang): void
{
	$recipient = user($conversation['buyer_id'] === $senderId ? $conversation['seller_id'] : $conversation['buyer_id']);
	$sender = user($senderId);

	if (!$recipient || $recipient['status'] !== 'active' || !$recipient['email_verified'] || !$recipient['notify']['messages']) {
		return;
	}

	$lang = $recipient['lang'] ?? conversation_lang($conversation['id'], $recipient['id']) ?? ($recipient['id'] === $listing['user_id'] ? $listing['lang'] : DEFAULT_LANG);
	in_lang($lang, function () use ($conversation, $listing, $recipient, $sender, $text, $textLang) {
		send_template_mail($recipient['email'], 'message_new', [
			'name' => person_name($recipient['name']),
			'sender' => person_name($sender['name']),
			'title' => listing_title($listing) ?: '#' . $listing['id'],
			'text' => user_text(mb_strlen($text) > 500 ? mb_substr($text, 0, 500) . '…' : $text, $textLang),
			'link' => site_url(url('messages.php', ['c' => $conversation['id']])),
		], ['user_id' => $recipient['id'], 'listing_id' => $listing['id']]);
	});
}

/* ---------- Actions ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = (string) ($_POST['action'] ?? '');
	$conversation = find_conversation((int) ($_POST['conversation'] ?? 0), $me['id']);
	$cid = $conversation['id'] ?? null;
	$back = $cid ? ['c' => $cid] : ['listing' => (int) ($_POST['listing'] ?? 0) ?: null];

	if (!csrf_valid()) {
		messages_answer(403, ['error' => t('auth.err_csrf')], $back);
	}

	if ($action === 'read' && $conversation) {
		messages_answer(200, ['read' => mark_conversation_read($cid, $me['id'])], $back);
	}

	if ($action === 'delete' && $conversation) {
		hide_conversation($cid, $me['id']);
		messages_answer(200, ['flash' => t('m.deleted')]);
	}

	if ($action !== 'send') {
		messages_answer(404, ['error' => t('m.unavailable')]);
	}

	$text = trim(mb_substr(str_replace("\r\n", "\n", (string) ($_POST['text'] ?? '')), 0, MESSAGE_MAX));
	// An existing conversation, or a new one about a published listing of another active user
	$listing = find_listing($conversation ? $conversation['listing_id'] : (int) ($_POST['listing'] ?? 0));
	$otherId = $conversation ? ($conversation['buyer_id'] === $me['id'] ? $conversation['seller_id'] : $conversation['buyer_id']) : ($listing['user_id'] ?? 0);
	$other = user($otherId);

	if ($text === '') {
		messages_answer(422, ['error' => t('m.err_empty')], $back);
	}
	if (!$listing || !$other || $other['status'] !== 'active' || $otherId === $me['id']
		|| (!$conversation && $listing['status'] !== 'active')) {
		messages_answer(404, ['error' => t('m.unavailable')], $back);
	}
	if (rate_limited('message', (string) $me['id']) || (!$conversation && rate_limited('conversation', (string) $me['id']))) {
		log_event('warning', 'Message rate limit', ['listing_id' => $listing['id'], 'new' => !$conversation], 'audit');
		messages_answer(429, ['error' => t('auth.err_too_many')], $back);
	}

	$created = false;
	if (!$conversation) {
		['id' => $cid, 'created' => $created] = start_conversation($listing['id'], $me['id'], $otherId);
		$conversation = find_conversation($cid, $me['id']);
		if ($created) {
			rate_hit('conversation', (string) $me['id']);
		}
	}

	rate_hit('message', (string) $me['id']);
	$firstUnread = conversation_unread($cid, $otherId) === 0;
	$lang = detect_text_lang($text);
	add_message($cid, $me['id'], $text, $lang);

	// A failed letter is logged by mail.php and does not stop the message
	if ($firstUnread) {
		notify_recipient($conversation, $listing, $me['id'], $text, $lang);
	}

	messages_answer(200, [
		'conversation' => $cid,
		'created' => $created,
		'message' => ['text' => user_text($text, $lang), 'time' => date('H:i'), 'day' => t('time.today')],
	], ['c' => $cid]);
}

/* ---------- Page ---------- */

$requested = isset($_GET['c']) ? (int) $_GET['c'] : null;
$draft = null;

// "Write to seller": the conversation about this listing, or a new one
if (isset($_GET['listing'])) {
	$item = find_listing((int) $_GET['listing']);
	$requested = $item ? buyer_conversation($item['id'], $me['id']) : null;

	if (!$requested) {
		$seller = $item ? user($item['user_id']) : null;
		if (!$item || $item['status'] !== 'active' || !$seller || $seller['status'] !== 'active') {
			require ROOT_DIR . '/404.php';
			exit;
		}
		if ($seller['id'] === $me['id']) {
			redirect(url('messages.php'));
		}
		$draft = ['id' => 0, 'with' => $seller['id'], 'listing_id' => $item['id'], 'unread' => 0, 'messages' => []];
	}
}

// Opened: its messages are read before the header counts unread ones
if ($requested) {
	mark_conversation_read($requested, $me['id']);
}

$conversations = conversations($requested);
if ($draft) {
	array_unshift($conversations, $draft);
}
$ids = array_column($conversations, 'id');
$activeId = $draft ? 0 : (in_array($requested, $ids, true) ? $requested : ($ids[0] ?? null));
$openOnLoad = $draft || ($requested && $activeId === $requested);
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

	<div class="chat" data-chat data-chat-url="<?= e(url('messages.php')) ?>"<?= $openOnLoad ? ' data-open-on-load' : '' ?>>
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
					$last = end($conv['messages']) ?: null;
					$rowId = 'conv-' . $conv['id'];
				?>
				<li id="<?= e($rowId) ?>">
					<button type="button" class="conv<?= $conv['unread'] ? ' is-unread' : '' ?>" data-conv="<?= (int) $conv['id'] ?>" aria-current="<?= $conv['id'] === $activeId ? 'true' : 'false' ?>">
						<?= avatar($other['name']) ?>
						<span class="conv__main">
							<span class="conv__top">
								<span class="conv__name"><?= e(person_name($other['name'])) ?></span>
								<?php if ($last): ?>
								<time class="conv__time" datetime="<?= e(date('c', strtotime($last['at']))) ?>"><?= e(chat_time($last['at'])) ?></time>
								<?php endif; ?>
							</span>
							<span class="conv__listing"><?= e(listing_title($item)) ?></span>
							<span class="conv__bottom">
								<span class="conv__last"><?php if ($last): ?><?= $last['from'] === current_user_id() ? e(t('m.you')) . ': ' : '' ?><?= e(user_text($last['text'], $last['lang'])) ?><?php else: ?><?= e(t('m.new_conversation')) ?><?php endif; ?></span>
								<?php if ($conv['unread']): ?>
								<span class="count-badge" data-unread="<?= (int) $conv['unread'] ?>"><span aria-hidden="true"><?= (int) $conv['unread'] ?></span><span class="visually-hidden"><?= e(t('m.unread', ['n' => $conv['unread']])) ?></span></span>
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
				<?php if ($conv['id']): ?>
				<form method="post" action="<?= e(url('messages.php')) ?>">
					<?= csrf_field() ?>
					<input type="hidden" name="conversation" value="<?= (int) $conv['id'] ?>">
					<button type="submit" class="btn btn--ghost btn--icon" name="action" value="delete" aria-label="<?= e(t('m.delete_chat')) ?>" data-confirm="<?= e(t('m.delete_confirm')) ?>" data-confirm-label="<?= e(t('common.delete')) ?>"><?= icon('trash', 20) ?></button>
				</form>
				<?php endif; ?>
			</div>

			<!-- Listing the conversation is about -->
			<div class="thread__listing">
				<span class="thread__thumb"><?= $item['images'] ? photo($item['images'][0], PHOTO_SIZES['chat'], ['max' => PHOTO_MAX['chat']]) : placeholder($item['category'], 20) ?></span>
				<div class="thread__listing-text">
					<p class="thread__listing-title"><?= e(listing_title($item)) ?></p>
					<p class="price"><?= price_html($item['price'], $item['currency']) ?></p>
				</div>
				<?php if ($item['status'] === 'active'): ?>
				<a class="btn btn--soft btn--sm" href="<?= e(listing_url($item)) ?>"><?= e(t('m.view_listing')) ?></a>
				<?php endif; ?>
			</div>

			<!-- Messages -->
			<div class="thread__body" role="log" aria-live="polite" tabindex="0">
				<?php foreach ($conv['messages'] as $msg):
					$day = date('Y-m-d', strtotime($msg['at']));
					$mine = $msg['from'] === current_user_id();
				?>
				<?php if ($day !== $prevDay): $prevDay = $day; ?>
				<p class="day-sep"><?= e(chat_day($msg['at'])) ?></p>
				<?php endif; ?>
				<div class="msg <?= $mine ? 'msg--out' : 'msg--in' ?>">
					<span class="visually-hidden"><?= e($mine ? t('m.you') : person_name($other['name'])) ?>:</span>
					<?= e(user_text($msg['text'], $msg['lang'])) ?>
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

			<!-- Composer: a new conversation is sent with the listing id -->
			<form class="composer" method="post" action="<?= e(url('messages.php')) ?>" data-composer>
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="send">
				<?php if ($conv['id']): ?>
				<input type="hidden" name="conversation" value="<?= (int) $conv['id'] ?>">
				<?php else: ?>
				<input type="hidden" name="listing" value="<?= (int) $conv['listing_id'] ?>">
				<?php endif; ?>
				<button type="button" class="btn btn--ghost btn--icon" aria-label="<?= e(t('m.attach')) ?>"><?= icon('paperclip', 20) ?></button>
				<label class="visually-hidden" for="msg-<?= (int) $conv['id'] ?>"><?= e(t('m.message_input')) ?></label>
				<textarea class="textarea composer__input" id="msg-<?= (int) $conv['id'] ?>" name="text" rows="1" maxlength="<?= MESSAGE_MAX ?>" required placeholder="<?= e(t('m.placeholder')) ?>"></textarea>
				<button type="submit" class="btn btn--primary" aria-label="<?= e(t('m.send')) ?>"><?= icon('send', 18) ?></button>
			</form>
		</section>
		<?php endforeach; ?>

		<!-- Nothing selected -->
		<div class="chat__empty"<?= $activeId !== null ? ' hidden' : '' ?>><?= e(t($conversations ? 'm.empty' : 'm.none')) ?></div>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
