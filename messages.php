<?php
/* ==========================================================================
	Messages: conversation list + thread. Logged-in users only.
	GET: c = conversation id (on phones opens the thread right away; its
	messages become read); listing = id from "Write to seller": the buyer's
	conversation about it, or an empty one that is created by the first message;
	user = id (staff only, from the admin): the staff member's conversation with
	this user — it has no listing and shows "Place4Market support" instead.
	POST (+ csrf), action:
	  send    conversation (or listing / user for a new one) + text, reply_to (id of
	          the message it answers, same conversation)
	  unsend  conversation, message: the author deletes a message the other
	          side has not read yet (the text is wiped, "Message deleted" stays)
	  read    conversation (opened in the browser without a reload)
	  poll    conversation, after (last message id shown): newer messages of the
	          open thread (main.js asks every few seconds); they become read.
	          Also state: what the other side has read, which ones are deleted
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

// The other side is told about a new message (unless turned off in the profile), the way they
// chose there. In the browser: every message (notify.php shows it while the site is open).
// By e-mail ($mail): once per conversation until they read it, in their language (or the one
// they write in here; the seller: of the listing until they reply).
// $textLang: the message's own, so its script follows the letter
function notify_recipient(array $conversation, ?array $listing, int $senderId, string $text, string $textLang, bool $mail = true): void
{
	$recipient = user($conversation['buyer_id'] === $senderId ? $conversation['seller_id'] : $conversation['buyer_id']);
	$sender = user($senderId);

	if (!$recipient || $recipient['status'] !== 'active' || !$recipient['notify']['messages']) {
		return;
	}

	if (notify_via($recipient['notify_channel'], 'browser')) {
		add_notification($recipient['id'], 'message', ['conversation' => $conversation['id'], 'sender' => $senderId, 'text' => mb_substr($text, 0, 140), 'lang' => $textLang]);
	}

	if (!$mail || !$recipient['email_verified'] || !notify_via($recipient['notify_channel'], 'email')) {
		return;
	}

	$lang = $recipient['lang'] ?? conversation_lang($conversation['id'], $recipient['id']) ?? ($listing && $recipient['id'] === $listing['user_id'] ? $listing['lang'] : DEFAULT_LANG);
	in_lang($lang, function () use ($conversation, $listing, $recipient, $sender, $text, $textLang) {
		// Without a listing: the staff member wrote to the user, or the user answered
		$template = $listing ? 'message_new' : ($conversation['seller_id'] === $sender['id'] ? 'message_support' : 'message_reply');
		send_template_mail($recipient['email'], $template, [
			'name' => person_name($recipient['name']),
			'sender' => person_name($sender['name']),
			'title' => $listing ? (listing_title($listing) ?: '#' . $listing['id']) : '',
			'text' => user_text(mb_strlen($text) > 500 ? mb_substr($text, 0, 500) . '…' : $text, $textLang),
			'link' => site_url(url('messages.php', ['c' => $conversation['id']])),
		], ['user_id' => $recipient['id'], 'listing_id' => $listing['id'] ?? null]);
	});
}

// A short line of a message for a quote or the conversation list
function message_line(array $msg, int $max = 140): string
{
	if ($msg['deleted']) {
		return t('m.msg_deleted');
	}
	$text = trim(preg_replace('/\s+/u', ' ', user_text($msg['text'], $msg['lang'])));

	return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
}

// One message of a thread: the page and the JSON answers (main.js) use the same markup.
// $quoted: the message it answers (message_from_row()) or null. The tools are shown by main.js
function message_html(array $msg, array $other, ?array $quoted): string
{
	$mine = $msg['from'] === current_user_id();
	$name = fn(array $m) => $m['from'] === current_user_id() ? t('m.you') : person_name($other['name']);
	$stamp = strtotime($msg['at']);

	$html = '<div class="msg ' . ($mine ? 'msg--out' : 'msg--in') . ($msg['deleted'] ? ' msg--deleted' : '') . ($mine && $msg['read'] && !$msg['deleted'] ? ' is-read' : '') . '" id="msg-' . (int) $msg['id'] . '" data-msg="' . (int) $msg['id'] . '">'
		. '<span class="visually-hidden">' . e($name($msg)) . ':</span>';

	if ($quoted && !$msg['deleted']) {
		$html .= '<button type="button" class="msg__quote" data-quote="' . (int) $quoted['id'] . '">'
			. '<span class="msg__quote-name">' . e($name($quoted)) . '</span>'
			. '<span class="msg__quote-text">' . e(message_line($quoted)) . '</span></button>';
	}

	$html .= $msg['deleted']
		? '<span class="msg__text">' . icon('ban', 16) . e(t('m.msg_deleted')) . '</span>'
		: '<span class="msg__text">' . e(user_text($msg['text'], $msg['lang'])) . '</span>';

	$html .= '<span class="msg__meta"><time class="msg__time" datetime="' . e(date('c', $stamp)) . '">' . date('H:i', $stamp) . '</time>';
	if ($mine && !$msg['deleted']) {
		$html .= '<span class="msg__ticks" role="img" aria-label="' . e(t($msg['read'] ? 'm.read' : 'm.delivered')) . '" title="' . e(t($msg['read'] ? 'm.read' : 'm.delivered')) . '">' . icon('checks', 16) . '</span>';
	}
	$html .= '</span>';

	if (!$msg['deleted']) {
		$html .= '<span class="msg__tools" hidden>'
			. '<button type="button" class="msg__tool" data-msg-reply aria-label="' . e(t('m.reply')) . '" title="' . e(t('m.reply')) . '">' . icon('reply', 18) . '</button>';
		if ($mine && !$msg['read']) {
			$html .= '<button type="button" class="msg__tool" data-msg-delete aria-label="' . e(t('m.msg_delete')) . '" title="' . e(t('m.msg_delete')) . '" data-confirm="' . e(t('m.msg_delete_confirm')) . '" data-confirm-label="' . e(t('common.delete')) . '">' . icon('trash', 18) . '</button>';
		}
		$html .= '</span>';
	}

	return $html . '</div>';
}

// A message for main.js: its markup plus what the conversation list shows
function message_json(array $msg, array $other, ?array $quoted): array
{
	return [
		'id' => $msg['id'], 'mine' => $msg['from'] === current_user_id(), 'text' => message_line($msg, 200),
		'time' => date('H:i', strtotime($msg['at'])), 'day' => chat_day($msg['at']), 'html' => message_html($msg, $other, $quoted),
	];
}

/* ---------- Actions ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = (string) ($_POST['action'] ?? '');
	$conversation = find_conversation((int) ($_POST['conversation'] ?? 0), $me['id']);
	$cid = $conversation['id'] ?? null;
	// A new conversation: about a listing, or (staff) with a user
	$toUser = !$conversation && is_staff() ? (int) ($_POST['user'] ?? 0) : 0;
	$back = $cid ? ['c' => $cid] : ($toUser ? ['user' => $toUser] : ['listing' => (int) ($_POST['listing'] ?? 0) ?: null]);

	if (!csrf_valid()) {
		messages_answer(403, ['error' => t('auth.err_csrf')], $back);
	}

	if ($action === 'read' && $conversation) {
		messages_answer(200, ['read' => mark_conversation_read($cid, $me['id'])], $back);
	}

	if ($action === 'poll' && $conversation) {
		$new = messages_after($cid, (int) ($_POST['after'] ?? 0));
		if ($new) {
			mark_conversation_read($cid, $me['id']);
		}
		$other = user($conversation['buyer_id'] === $me['id'] ? $conversation['seller_id'] : $conversation['buyer_id']);
		messages_answer(200, [
			'messages' => array_map(fn($m) => message_json($m, $other, $m['reply_to'] ? find_message($m['reply_to'], $cid) : null), $new),
			'state' => conversation_state($cid, $me['id']),
			'unread' => unread_count(),
		], $back);
	}

	if ($action === 'unsend' && $conversation) {
		$messageId = (int) ($_POST['message'] ?? 0);
		if (!delete_unread_message($messageId, $cid, $me['id'])) {
			messages_answer(409, ['error' => t('m.err_already_read'), 'state' => conversation_state($cid, $me['id'])], $back);
		}
		log_action('message.unsend', ['conversation_id' => $cid, 'listing_id' => $conversation['listing_id']]);
		$other = user($conversation['buyer_id'] === $me['id'] ? $conversation['seller_id'] : $conversation['buyer_id']);
		messages_answer(200, ['message' => message_json(find_message($messageId, $cid), $other, null), 'flash' => t('m.msg_deleted_done')], $back);
	}

	if ($action === 'delete' && $conversation) {
		hide_conversation($cid, $me['id']);
		messages_answer(200, ['flash' => t('m.deleted')]);
	}

	if ($action !== 'send') {
		messages_answer(404, ['error' => t('m.unavailable')]);
	}

	$text = trim(mb_substr(str_replace("\r\n", "\n", (string) ($_POST['text'] ?? '')), 0, MESSAGE_MAX));
	// An existing conversation, or a new one about a published listing of another active user.
	// $support: a conversation of the staff with a user, it has no listing
	$support = $conversation ? $conversation['listing_id'] === null : $toUser > 0;
	$listing = $support ? null : find_listing($conversation ? $conversation['listing_id'] : (int) ($_POST['listing'] ?? 0));
	$otherId = $conversation ? ($conversation['buyer_id'] === $me['id'] ? $conversation['seller_id'] : $conversation['buyer_id']) : ($support ? $toUser : ($listing['user_id'] ?? 0));
	$other = user($otherId);

	if ($text === '') {
		messages_answer(422, ['error' => t('m.err_empty')], $back);
	}
	if ((!$listing && !$support) || !$other || $other['status'] !== 'active' || $otherId === $me['id']
		|| (!$conversation && !$support && $listing['status'] !== 'active')) {
		messages_answer(404, ['error' => t('m.unavailable')], $back);
	}
	// The seller takes calls only on this listing (D6): no new conversation about it
	if (!$conversation && !$support && !listing_contact($listing, $other)['messages']) {
		messages_answer(403, ['error' => t('m.calls_only')], $back);
	}
	if (rate_limited('message', (string) $me['id']) || (!$conversation && rate_limited('conversation', (string) $me['id']))) {
		log_event('warning', 'Message rate limit', ['listing_id' => $listing['id'] ?? null, 'new' => !$conversation], 'audit');
		messages_answer(429, ['error' => t('auth.err_too_many')], $back);
	}

	$created = false;
	if (!$conversation) {
		['id' => $cid, 'created' => $created] = $support ? start_support_conversation($me['id'], $otherId) : start_conversation($listing['id'], $me['id'], $otherId);
		$conversation = find_conversation($cid, $me['id']);
		if ($created) {
			rate_hit('conversation', (string) $me['id']);
		}
		if ($support) {
			log_action('admin.message', ['user_id' => $otherId, 'conversation_id' => $cid]);
		}
	}

	rate_hit('message', (string) $me['id']);
	$firstUnread = conversation_unread($cid, $otherId) === 0;
	$lang = detect_text_lang($text);
	// An answer to a message of this conversation that is still there
	$quoted = !$created && isset($_POST['reply_to']) ? find_message((int) $_POST['reply_to'], $cid) : null;
	if ($quoted && $quoted['deleted']) {
		$quoted = null;
	}
	$messageId = add_message($cid, $me['id'], $text, $lang, $quoted['id'] ?? null);

	// A failed letter is logged by mail.php and does not stop the message
	try {
		notify_recipient($conversation, $listing, $me['id'], $text, $lang, $firstUnread);
	} catch (Throwable $e) {
		log_exception($e);
	}

	messages_answer(200, [
		'conversation' => $cid,
		'created' => $created,
		'message' => message_json(find_message($messageId, $cid), $other, $quoted),
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
		if (!listing_contact($item, $seller)['messages']) {
			flash('info', t('m.calls_only'));
			redirect(listing_url($item));
		}
		$draft = ['id' => 0, 'with' => $seller['id'], 'listing_id' => $item['id'], 'unread' => 0, 'messages' => []];
	}
}

// From the admin: the staff member's conversation with a user, or a new one
if (isset($_GET['user']) && is_staff()) {
	$to = user((int) $_GET['user']);
	if (!$to || $to['status'] !== 'active' || $to['id'] === $me['id']) {
		require ROOT_DIR . '/404.php';
		exit;
	}
	$requested = support_conversation($me['id'], $to['id']);
	if (!$requested) {
		$draft = ['id' => 0, 'with' => $to['id'], 'listing_id' => null, 'unread' => 0, 'messages' => []];
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
// The listing of every conversation, read once for the list and the threads
$convListings = find_listings(array_column($conversations, 'listing_id'));
// Listings whose seller the user may review now (the buyer, after the seller's answer)
$reviewable = reviewable_listings(array_column($conversations, 'listing_id'), $me['id']);
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
	<!-- The title is for screen readers only (checklist K10): the header and the bottom navigation
	     show where we are -->
	<h1 class="visually-hidden"><?= e(t('m.title')) ?></h1>

	<?php if (!$conversations): ?>
	<!-- No conversations yet: no search (nothing to search in), a placeholder in place of the
	     messenger; on phones it fills the first screen, the footer stays below it -->
	<div class="chat chat--none">
		<div class="empty chat__none">
			<?= icon('message', 40) ?>
			<p class="empty__title"><?= e(t('m.none_title')) ?></p>
			<p><?= e(t('m.none')) ?></p>
			<a class="btn btn--outline" href="<?= e(url('category.php')) ?>"><?= e(t('m.browse')) ?></a>
		</div>
	</div>
	<?php else: ?>
	<div class="chat" data-chat data-chat-url="<?= e(url('messages.php')) ?>" data-read-label="<?= e(t('m.read')) ?>" data-deleted-label="<?= e(t('m.msg_deleted')) ?>"<?= $openOnLoad ? ' data-open-on-load' : '' ?>>
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
					$item = $conv['listing_id'] ? ($convListings[$conv['listing_id']] ?? null) : null;
					$last = end($conv['messages']) ?: null;
					$rowId = 'conv-' . $conv['id'];
				?>
				<li id="<?= e($rowId) ?>">
					<button type="button" class="conv<?= $conv['unread'] ? ' is-unread' : '' ?>" data-conv="<?= (int) $conv['id'] ?>" aria-current="<?= $conv['id'] === $activeId ? 'true' : 'false' ?>">
						<?= avatar($other) ?>
						<span class="conv__main">
							<span class="conv__top">
								<span class="conv__name"><?= e(person_name($other['name'])) ?></span>
								<?php if ($last): ?>
								<time class="conv__time" datetime="<?= e(date('c', strtotime($last['at']))) ?>"><?= e(chat_time($last['at'])) ?></time>
								<?php endif; ?>
							</span>
							<span class="conv__listing"><?= e($item ? listing_title($item) : t('m.support')) ?></span>
							<span class="conv__bottom">
								<span class="conv__last"><?php if ($last): ?><?= $last['from'] === current_user_id() ? e(t('m.you')) . ': ' : '' ?><?= e(message_line($last, 200)) ?><?php else: ?><?= e(t('m.new_conversation')) ?><?php endif; ?></span>
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
			$item = $conv['listing_id'] ? ($convListings[$conv['listing_id']] ?? null) : null;
			$threadId = 'thread-' . $conv['id'];
			$prevDay = null;
			$byId = array_column($conv['messages'], null, 'id');
		?>
		<section class="thread" id="<?= e($threadId) ?>" data-thread="<?= (int) $conv['id'] ?>" aria-label="<?= e(person_name($other['name'])) ?>"<?= $conv['id'] === $activeId ? '' : ' hidden' ?>>
			<!-- Head: back, person, delete -->
			<div class="thread__head">
				<button type="button" class="btn btn--ghost btn--icon thread__back" data-chat-back aria-label="<?= e(t('m.back')) ?>"><?= icon('chevron-left') ?></button>
				<?= avatar($other, 'sm') ?>
				<div class="thread__who">
					<h2 class="thread__name"><?= e(person_name($other['name'])) ?><?= trust_marks($other) ?></h2>
				</div>
				<?php if ($conv['id']): ?>
				<form method="post" action="<?= e(url('messages.php')) ?>">
					<?= csrf_field() ?>
					<input type="hidden" name="conversation" value="<?= (int) $conv['id'] ?>">
					<button type="submit" class="btn btn--ghost btn--icon" name="action" value="delete" aria-label="<?= e(t('m.delete_chat')) ?>" data-confirm="<?= e(t('m.delete_confirm')) ?>" data-confirm-label="<?= e(t('common.delete')) ?>"><?= icon('trash', 20) ?></button>
				</form>
				<?php endif; ?>
			</div>

			<!-- Listing the conversation is about; without one — the site's support -->
			<?php if (!$item): ?>
			<div class="thread__listing">
				<span class="thread__thumb thread__thumb--support"><?= icon('shield', 22) ?></span>
				<div class="thread__listing-text">
					<p class="thread__listing-title"><?= e(t('m.support')) ?></p>
					<p class="thread__listing-note"><?= e(t('m.support_note')) ?></p>
				</div>
			</div>
			<?php else: ?>
			<div class="thread__listing">
				<span class="thread__thumb"><?= $item['images'] ? photo($item['images'][0], PHOTO_SIZES['chat'], ['max' => PHOTO_MAX['chat']]) : placeholder($item['category'], 20) ?></span>
				<div class="thread__listing-text">
					<p class="thread__listing-title"><?= e(listing_title($item)) ?></p>
					<p class="price"><?= price_html($item['price'], $item['currency']) ?></p>
				</div>
				<?php if ($item['status'] === 'active'): ?>
				<a class="btn btn--soft btn--sm" href="<?= e(listing_url($item)) ?>"><?= e(t('m.view_listing')) ?></a>
				<?php endif; ?>
				<?php if (in_array($item['id'], $reviewable, true) && $item['user_id'] === $other['id']): ?>
				<!-- The buyer, once the seller has answered: a review of the seller (seller.php) -->
				<a class="btn btn--soft btn--sm thread__review" href="<?= e(url('seller.php', ['id' => $other['id'], 'review' => $item['id']])) ?>#reviews" title="<?= e(t('rv.write')) ?>"><?= icon('star', 16) ?><span><?= e(t('rv.write')) ?></span></a>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<!-- Messages -->
			<div class="thread__body" role="log" aria-live="polite" tabindex="0">
				<?php foreach ($conv['messages'] as $msg):
					$day = date('Y-m-d', strtotime($msg['at']));
				?>
				<?php if ($day !== $prevDay): $prevDay = $day; ?>
				<p class="day-sep"><?= e(chat_day($msg['at'])) ?></p>
				<?php endif; ?>
				<?= message_html($msg, $other, $msg['reply_to'] ? ($byId[$msg['reply_to']] ?? null) : null) ?>

				<?php endforeach; ?>
			</div>

			<!-- Quick replies: a buyer's questions, so the seller of the listing does not get them -->
			<?php if ($item && $item['user_id'] !== $me['id']): ?>
			<div class="quick" role="group" aria-label="<?= e(t('m.quick')) ?>">
				<?php foreach ($quickReplies as $reply): ?>
				<button type="button" class="chip" data-quick><?= e($reply) ?></button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<!-- The message being answered (main.js fills it from [data-msg-reply]) -->
			<div class="reply-bar" data-reply-bar hidden>
				<?= icon('reply', 18) ?>
				<p class="reply-bar__text"><span class="reply-bar__name" data-reply-name></span><span data-reply-text></span></p>
				<button type="button" class="btn btn--ghost btn--icon" data-reply-cancel aria-label="<?= e(t('m.reply_cancel')) ?>"><?= icon('x', 18) ?></button>
			</div>

			<!-- Composer: a new conversation is sent with the listing id -->
			<form class="composer" method="post" action="<?= e(url('messages.php')) ?>" data-composer data-no-validation>
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="send">
				<?php if ($conv['id']): ?>
				<input type="hidden" name="conversation" value="<?= (int) $conv['id'] ?>">
				<input type="hidden" name="reply_to" value="" data-reply-input>
				<?php elseif ($conv['listing_id'] === null): ?>
				<input type="hidden" name="user" value="<?= (int) $conv['with'] ?>">
				<?php else: ?>
				<input type="hidden" name="listing" value="<?= (int) $conv['listing_id'] ?>">
				<?php endif; ?>
				<label class="visually-hidden" for="msg-<?= (int) $conv['id'] ?>"><?= e(t('m.message_input')) ?></label>
				<textarea class="textarea composer__input" id="msg-<?= (int) $conv['id'] ?>" name="text" rows="1" data-max-rows="5" maxlength="<?= MESSAGE_MAX ?>" required placeholder="<?= e(t('m.placeholder')) ?>"></textarea>
				<button type="submit" class="btn btn--primary" aria-label="<?= e(t('m.send')) ?>"><?= icon('send', 18) ?></button>
			</form>
		</section>
		<?php endforeach; ?>

		<!-- Nothing selected -->
		<div class="chat__empty"<?= $activeId !== null ? ' hidden' : '' ?>><?= e(t('m.empty')) ?></div>
	</div>
	<?php endif; ?>
</div>

<?php require INC_DIR . '/footer.php'; ?>
