<?php
/* ==========================================================================
	Admin helpers: statuses, lookups, small render functions
	========================================================================== */

// Status → [label, badge modifier]
function status_meta(string $status): array
{
	$modifiers = [
		'active' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'expired' => 'neutral', 'draft' => 'neutral',
		'inactive' => 'neutral', 'deleted' => 'danger', 'blocked' => 'danger', 'open' => 'warning', 'review' => 'info', 'resolved' => 'success',
	];

	return isset($modifiers[$status]) ? [at('status.' . $status), $modifiers[$status]] : [ucfirst($status), 'neutral'];
}

// Status badge HTML
function status_badge(string $status): string
{
	[$label, $modifier] = status_meta($status);

	return '<span class="badge badge--' . $modifier . '">' . e($label) . '</span>';
}

// Role name and badge HTML
function role_name(string $role): string
{
	return in_array($role, ['user', 'moderator', 'admin'], true) ? at('role.' . $role) : ucfirst($role);
}

function role_badge(string $role): string
{
	$modifier = $role === 'admin' ? 'info' : 'neutral';

	return '<span class="badge badge--plain badge--' . $modifier . '">' . e(role_name($role)) . '</span>';
}

// Name of a field in "Saved: …" and the history ("title", "seller_type"); an unknown one as it is
function field_label(string $field): string
{
	$label = at('field.' . $field);

	return $label !== 'field.' . $field ? $label : $field;
}

function field_labels(array $fields): string
{
	return implode(', ', array_map('field_label', $fields));
}

// Reject reasons (moderation)
function reject_reasons(): array
{
	$keys = ['prohibited', 'contacts', 'category', 'duplicate', 'photos', 'price', 'other'];

	return array_combine($keys, array_map(fn($key) => at('reject.' . $key), $keys));
}

// Parent category for any slug (parent or child)
function category_parent(string $slug): ?array
{
	foreach (categories() as $parent) {
		if ($parent['slug'] === $slug) {
			return $parent;
		}
		foreach ($parent['children'] as $child) {
			if ($child['slug'] === $slug) {
				return $parent;
			}
		}
	}

	return null;
}

// Listing title for staff screens; a draft may have none yet: "Untitled (Draft)"
function admin_title(?array $listing): string
{
	if (!$listing) {
		return '—';
	}

	return $listing['title'] !== '' ? $listing['title'] : at('common.untitled', ['status' => status_meta($listing['status'])[0]]);
}

// Price of a listing in the admin language ("By agreement" without one)
function admin_price(array $listing): string
{
	return $listing['price'] === null ? at('common.by_agreement') : format_price($listing['price'], $listing['currency']);
}

// Category name (EN) for any slug
function category_name(string $slug): string
{
	foreach (categories() as $parent) {
		if ($parent['slug'] === $slug) {
			return $parent['names']['en'];
		}
		foreach ($parent['children'] as $child) {
			if ($child['slug'] === $slug) {
				return $child['names']['en'];
			}
		}
	}

	return $slug;
}

// "Vehicles › Cars"
function category_path(string $slug): string
{
	$parent = category_parent($slug);

	if (!$parent || $parent['slug'] === $slug) {
		return category_name($slug);
	}

	return $parent['names']['en'] . ' › ' . category_name($slug);
}

// Listing thumbnail: cover photo, or a tile with the category icon when there is none
function thumb(array $listing, string $size = ''): string
{
	$px = ['sm' => 40, 'lg' => 72][$size] ?? 48;

	if ($listing['images']) {
		return '<span class="thumb' . ($size ? ' thumb--' . $size : '') . '">' . photo($listing['images'][0], $px . 'px', ['max' => 200]) . '</span>';
	}

	$parent = category_parent($listing['category']);
	$tint = $parent ? $parent['slug'] : '';
	$iconName = $parent ? $parent['icon'] : 'image';
	$iconSize = $size === 'lg' ? 30 : 22;
	$classes = 'thumb' . ($tint ? ' thumb--' . $tint : '') . ($size ? ' thumb--' . $size : '');

	return '<span class="' . $classes . '">' . icon($iconName, $iconSize) . '</span>';
}

// User name by id; #id when the row is gone, — without an id
function user_name(?int $id): string
{
	$user = $id ? user($id) : null;

	return $user ? $user['name'] : ($id ? '#' . $id : '—');
}

// Number of listings in a category (parent counts its children), any status
function category_listing_count(array $category): int
{
	return count_admin_listings(['cat' => $category['slug']]);
}

// Number of listings by city, any status
function city_listing_count(string $city): int
{
	return count_admin_listings(['city' => $city]);
}

// Payload for the category edit form (filled by JS from [data-edit-category]); $category null = new
function category_payload(?array $category, ?array $parent = null): array
{
	$category ??= ['id' => '', 'slug' => '', 'slug_sr' => '', 'names' => ['en' => '', 'sr-latn' => '', 'sr-cyrl' => ''],
		'seo' => ['en' => [null, null], 'sr' => [null, null]], 'h1' => ['en' => null, 'sr' => null], 'text' => ['en' => null, 'sr' => null],
		'sort' => 0, 'active' => true];

	return [
		'formTitle' => $category['id'] ? at('cat.form_edit', ['name' => $category['names']['en']]) : at('cat.form_new'),
		'id' => (string) $category['id'],
		'name_en' => $category['names']['en'],
		'name_srl' => $category['names']['sr-latn'],
		'name_src' => $category['names']['sr-cyrl'],
		'slug' => $category['slug'],
		'slug_sr' => $category['slug_sr'],
		'seo_title_en' => (string) $category['seo']['en'][0],
		'meta_en' => (string) $category['seo']['en'][1],
		'seo_title_sr' => (string) $category['seo']['sr'][0],
		'meta_sr' => (string) $category['seo']['sr'][1],
		'h1_en' => (string) $category['h1']['en'],
		'h1_sr' => (string) $category['h1']['sr'],
		'text_en' => (string) $category['text']['en'],
		'text_sr' => (string) $category['text']['sr'],
		'parent' => $parent ? (string) $parent['id'] : '',
		'icon' => $category['icon'] ?? 'car',
		'sort' => (string) $category['sort'],
		'active' => $category['active'],
	];
}

// SEO text of a category or a landing: up to this many characters
const SEO_TEXT_MAX = 5000;

// Text in paragraphs from a form (SEO texts): line ends unified, spaces at line ends and
// more than one empty line between paragraphs dropped, cut to $max; empty → null
function clean_text_block(mixed $value, int $max): ?string
{
	$text = preg_replace(['/[ \t]+\n/u', '/\n{3,}/u'], ["\n", "\n\n"], str_replace(["\r\n", "\r"], "\n", is_string($value) ? $value : ''));
	$text = trim(mb_substr(trim($text), 0, $max));

	return $text !== '' ? $text : null;
}

// Active nav class
function nav_active(string $key, string $current): string
{
	return $key === $current ? ' is-active' : '';
}

// <option> list helper
function options(array $items, string $selected = ''): string
{
	$html = '';

	foreach ($items as $value => $label) {
		$isSelected = (string) $value === $selected ? ' selected' : '';
		$html .= '<option value="' . e($value) . '"' . $isSelected . '>' . e($label) . '</option>';
	}

	return $html;
}

// Grouped <option>s for the category select (children under parents);
// $withParents adds "All in …" for filters
function category_options(string $selected = '', bool $withParents = false): string
{
	$html = '';

	foreach (categories() as $parent) {
		$hidden = ' ' . at('common.hidden_mark');
		$html .= '<optgroup label="' . e($parent['names']['en'] . ($parent['visible'] ? '' : $hidden)) . '">';
		if ($withParents) {
			$html .= '<option value="' . e($parent['slug']) . '"' . ($parent['slug'] === $selected ? ' selected' : '') . '>' . e(at('common.all_in', ['name' => $parent['names']['en']])) . '</option>';
		}
		foreach ($parent['children'] as $child) {
			$isSelected = $child['slug'] === $selected ? ' selected' : '';
			$html .= '<option value="' . e($child['slug']) . '"' . $isSelected . '>' . e($child['names']['en'] . ($child['visible'] ? '' : $hidden)) . '</option>';
		}
		$html .= '</optgroup>';
	}

	return $html;
}

// Pre-built city list for selects
function city_options(string $selected = '', bool $withAll = false): string
{
	$items = $withAll ? ['' => at('common.all_cities')] : [];

	foreach (cities() as $city) {
		$items[$city['latn']] = $city['latn'];
	}

	return options($items, $selected);
}

/* ---------- Lists: filters in the address, pages ---------- */

// Rows per page of the admin lists
const ADMIN_PAGE_SIZE = 50;

// A filter from the address: one of $allowed, or text up to 100 characters when $allowed is null
function list_param(string $key, ?array $allowed = null): string
{
	$value = trim(mb_substr((string) ($_GET[$key] ?? ''), 0, 100));

	return $allowed === null || in_array($value, $allowed, true) ? $value : '';
}

// Address of a list with its filters (empty ones left out): list_url('users.php', $f, ['page' => 2])
function list_url(string $path, array $f, array $change = []): string
{
	$query = http_build_query(array_filter($change + $f, fn($v) => $v !== '' && $v !== null), '', '&', PHP_QUERY_RFC3986);

	return $path . ($query !== '' ? '?' . $query : '');
}

// Current page for $total rows: [page, offset]
function list_page(int $total): array
{
	$page = min(max(1, (int) ceil($total / ADMIN_PAGE_SIZE)), max(1, (int) ($_GET['page'] ?? 1)));

	return [$page, ($page - 1) * ADMIN_PAGE_SIZE];
}

// "Showing 51–100 of 230" + page links (first, last and two around the current one); $url(page)
function admin_pagination(int $total, int $page, callable $url): string
{
	$pages = max(1, (int) ceil($total / ADMIN_PAGE_SIZE));
	$from = $total ? ($page - 1) * ADMIN_PAGE_SIZE + 1 : 0;
	$to = min($total, $page * ADMIN_PAGE_SIZE);
	$link = fn(int $n, string $label, string $attrs = '') => '<li><a class="pagination__page" href="' . e($url($n)) . '"' . $attrs . '>' . $label . '</a></li>';

	$html = '<nav class="pagination" aria-label="' . e(at('pager.aria')) . '"><span>'
		. e($total ? at('pager.showing', ['from' => $from, 'to' => $to, 'total' => number_format($total, 0, ',', ' ')]) : at('pager.none')) . '</span>';
	if ($pages > 1) {
		$html .= '<ul class="pagination__pages">' . $link(max(1, $page - 1), icon('chevron-left', 16), ' aria-label="' . e(at('pager.prev')) . '"' . ($page === 1 ? ' aria-disabled="true"' : ''));
		$last = 0;
		foreach (array_unique([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages]) as $n) {
			if ($n < 1 || $n > $pages) {
				continue;
			}
			if ($n > $last + 1) {
				$html .= '<li><span class="pagination__gap">…</span></li>';
			}
			$html .= $link($n, (string) $n, $n === $page ? ' aria-current="page"' : '');
			$last = $n;
		}
		$html .= $link(min($pages, $page + 1), icon('chevron-right', 16), ' aria-label="' . e(at('pager.next')) . '"' . ($page === $pages ? ' aria-disabled="true"' : '')) . '</ul>';
	}

	return $html . '</nav>';
}

/* ---------- Moderation ---------- */

// Report reasons (keys as in the form on the site: r.<key>)
function report_reasons(): array
{
	$keys = ['fraud', 'sold', 'duplicate', 'wrong_category', 'inappropriate', 'other'];

	return array_combine($keys, array_map(fn($key) => at('report.' . $key), $keys));
}

// Report resolutions
function report_resolutions(): array
{
	$keys = ['dismiss', 'edit', 'remove', 'remove_block'];

	return array_combine($keys, array_map(fn($key) => at('resolution.' . $key), $keys));
}

// Letter to the seller about a decision (approved | rejected), in the language the ad
// is written in. A failure is logged by send_mail() and does not stop the moderation
function notify_seller(array $listing, string $decision): void
{
	$seller = user($listing['user_id']);

	if (!$seller) {
		return;
	}

	// The seller's language, or the one the listing is written in
	in_lang($seller['lang'] ?? $listing['lang'], function () use ($listing, $seller, $decision) {
		$params = ['name' => person_name($seller['name']), 'title' => listing_title($listing) ?: '#' . $listing['id'], 'days' => setting('listing_days')];

		if ($decision === 'approved') {
			$params['link'] = site_url(listing_url($listing));
		} else {
			$params['reason'] = t('rj.' . $listing['reject_reason']);
			$params['comment'] = $listing['reject_comment'] ? t('p.reject_comment', ['comment' => $listing['reject_comment']]) . "\n" : '';
			$params['link'] = site_url(url('post.php', ['id' => $listing['id']]));
		}

		send_template_mail($seller['email'], 'listing_' . $decision, $params, ['user_id' => $seller['id'], 'listing_id' => $listing['id']]);
	});
}

// Back to the page the action came from (only an admin page)
function safe_back(string $back, string $fallback = 'listings.php'): string
{
	return preg_match('/^[a-z-]+\.php(\?[\w=&%.\-]*)?(#[\w-]+)?$/', $back) ? $back : $fallback;
}

// Audit record → dashboard / history line: ['type', 'icon', 'text'] (texts: audit.<action>,
// {who} — the actor, {listing} — "#1048", {user} — the account acted upon)
function audit_line(array $entry): array
{
	$c = $entry['context'];
	$p = [
		'who' => $entry['user_id'] ? user_name($entry['user_id']) : at('audit.system'),
		'listing' => isset($c['listing_id']) ? '#' . $c['listing_id'] : '',
		'user' => user_name($c['user_id'] ?? null),
		'report' => '#' . ($c['report_id'] ?? ''),
		'message' => '#' . ($c['contact_id'] ?? ''),
		'id' => (string) ($c['landing_id'] ?? $c['category_id'] ?? ''),
		// A landing's "title" is its SEO title, not a listing title
		'fields' => field_labels(array_map(fn($f) => $entry['action'] === 'admin.landing_update' && $f === 'title' ? 'seo_title' : $f, $c['fields'] ?? [])),
	];
	// ": wrong category", ": title, price" after the line, when there is one
	$tail = fn(string $text) => $text !== '' ? ': ' . $text : '';

	[$type, $iconName, $key] = match ($entry['action']) {
		'listing.create' => ['listing', 'tag', 'listing_create'],
		'listing.update' => ['listing', 'edit', 'listing_update'],
		'listing.draft' => ['listing', 'edit', 'listing_draft'],
		'listing.approve' => ['user', 'check', 'listing_approve'],
		'listing.reject' => ['moderation', 'ban', 'listing_reject'],
		'listing.remove' => ['moderation', 'trash', 'listing_remove'],
		'listing.staff_edit' => ['moderation', 'edit', 'listing_staff_edit'],
		'listing.staff_photos' => ['moderation', 'image', 'listing_photos_' . (in_array($c['op'] ?? '', ['add', 'cover'], true) ? $c['op'] : 'delete')],
		'listing.delete' => ['listing', 'trash', 'listing_delete'],
		'listing.raise' => ['listing', 'arrow-up', 'listing_raise'],
		'listing.deactivate' => ['listing', 'power', 'listing_deactivate'],
		'listing.activate' => ['listing', 'power', 'listing_activate'],
		'listing.renew' => ['listing', 'refresh', 'listing_renew'],
		'report.create' => ['report', 'flag', 'report_create'],
		'report.review' => ['report', 'flag', 'report_review'],
		'report.resolve' => ['report', 'check', 'report_resolve'],
		'admin.user_block' => ['moderation', 'lock', 'user_block'],
		'admin.user_unblock' => ['user', 'lock', 'user_unblock'],
		'admin.user_update' => ['user', 'edit', 'user_update'],
		'admin.user_note' => ['user', 'edit', 'user_note'],
		'admin.user_reset_link' => ['user', 'lock', 'user_reset_link'],
		'admin.user_sessions_end' => ['moderation', 'logout', 'user_sessions_end'],
		'admin.user_delete' => ['moderation', 'trash', 'user_delete'],
		'admin.category_create', 'admin.category_update', 'admin.category_hide', 'admin.category_show', 'admin.category_delete'
			=> ['moderation', 'layers', substr($entry['action'], 6)],
		'admin.landing_update', 'admin.landing_hide', 'admin.landing_show' => ['moderation', 'filter', substr($entry['action'], 6)],
		'auth.login' => ['user', 'user', 'login'],
		'auth.password_changed', 'auth.password_reset' => ['user', 'lock', 'password'],
		'account.update' => ['user', 'edit', 'account_update'],
		'account.delete' => ['moderation', 'trash', 'account_delete'],
		'auth.register' => ['user', 'user', 'register'],
		'contact.message' => ['user', 'mail', 'contact_message'],
		'contact.answered' => ['user', 'check', 'contact_answered'],
		'contact.reopened' => ['user', 'mail', 'contact_reopened'],
		default => ['listing', 'info', ''],
	};

	if ($key === '') {
		return [$type, $iconName, $p['who'] . ': ' . $entry['action'] . ($p['listing'] !== '' ? ' ' . $p['listing'] : '')];
	}

	$text = at('audit.' . $key, $p) . match ($entry['action']) {
		'listing.reject' => $tail(isset($c['reason']) ? reject_reasons()[$c['reason']] ?? $c['reason'] : ''),
		'report.create' => $tail(isset($c['reason']) ? report_reasons()[$c['reason']] ?? $c['reason'] : ''),
		'report.resolve' => ' (' . (report_resolutions()[$c['resolution'] ?? ''] ?? '—') . ')',
		'listing.staff_photos' => '',
		'admin.user_update' => $tail($p['fields']) . (isset($c['role']) ? ' (' . role_name($c['role']) . ')' : ''),
		default => isset($c['fields']) ? $tail($p['fields']) : '',
	};
	if ($entry['action'] === 'listing.staff_photos' && ($c['op'] ?? '') === 'add') {
		$text = at('audit.listing_photos_add', $p + ['photos' => atp('common.photos', (int) ($c['photos'] ?? 0))]);
	}

	return [$type, $iconName, $text];
}
