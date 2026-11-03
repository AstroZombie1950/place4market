<?php
/* ==========================================================================
	Admin helpers: statuses, lookups, small render functions
	========================================================================== */

// Status → [label, badge modifier]
function status_meta(string $status): array
{
	$map = [
		'active' => ['Active', 'success'],
		'pending' => ['Pending', 'warning'],
		'rejected' => ['Rejected', 'danger'],
		'expired' => ['Expired', 'neutral'],
		'draft' => ['Draft', 'neutral'],
		'inactive' => ['Inactive', 'neutral'],
		'deleted' => ['Deleted', 'danger'],
		'blocked' => ['Blocked', 'danger'],
		'open' => ['Open', 'warning'],
		'review' => ['In review', 'info'],
		'resolved' => ['Resolved', 'success'],
	];

	return $map[$status] ?? [ucfirst($status), 'neutral'];
}

// Status badge HTML
function status_badge(string $status): string
{
	[$label, $modifier] = status_meta($status);

	return '<span class="badge badge--' . $modifier . '">' . e($label) . '</span>';
}

// Role badge HTML
function role_badge(string $role): string
{
	$modifier = $role === 'admin' ? 'info' : 'neutral';

	return '<span class="badge badge--plain badge--' . $modifier . '">' . e(ucfirst($role)) . '</span>';
}

// Reject reasons (moderation)
function reject_reasons(): array
{
	return [
		'prohibited' => 'Prohibited item',
		'contacts' => 'Contact details in title or photos',
		'category' => 'Wrong category',
		'duplicate' => 'Duplicate listing',
		'photos' => 'Low quality or stock photos',
		'price' => 'Misleading price',
		'other' => 'Other',
	];
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

	return $listing['title'] !== '' ? $listing['title'] : 'Untitled (' . status_meta($listing['status'])[0] . ')';
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
	$category ??= ['id' => '', 'slug' => '', 'names' => ['en' => '', 'sr-latn' => '', 'sr-cyrl' => ''],
		'seo' => ['en' => [null, null], 'sr' => [null, null]], 'sort' => 0, 'active' => true];

	return [
		'formTitle' => $category['id'] ? 'Edit: ' . $category['names']['en'] : 'New category',
		'id' => (string) $category['id'],
		'name_en' => $category['names']['en'],
		'name_srl' => $category['names']['sr-latn'],
		'name_src' => $category['names']['sr-cyrl'],
		'slug' => $category['slug'],
		'seo_title_en' => (string) $category['seo']['en'][0],
		'meta_en' => (string) $category['seo']['en'][1],
		'seo_title_sr' => (string) $category['seo']['sr'][0],
		'meta_sr' => (string) $category['seo']['sr'][1],
		'parent' => $parent ? (string) $parent['id'] : '',
		'icon' => $category['icon'] ?? 'car',
		'sort' => (string) $category['sort'],
		'active' => $category['active'],
	];
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
		$html .= '<optgroup label="' . e($parent['names']['en'] . ($parent['visible'] ? '' : ' (hidden)')) . '">';
		if ($withParents) {
			$html .= '<option value="' . e($parent['slug']) . '"' . ($parent['slug'] === $selected ? ' selected' : '') . '>All in ' . e($parent['names']['en']) . '</option>';
		}
		foreach ($parent['children'] as $child) {
			$isSelected = $child['slug'] === $selected ? ' selected' : '';
			$html .= '<option value="' . e($child['slug']) . '"' . $isSelected . '>' . e($child['names']['en'] . ($child['visible'] ? '' : ' (hidden)')) . '</option>';
		}
		$html .= '</optgroup>';
	}

	return $html;
}

// Pre-built city list for selects
function city_options(string $selected = '', bool $withAll = false): string
{
	$items = $withAll ? ['' => 'All cities'] : [];

	foreach (cities() as $city) {
		$items[$city['latn']] = $city['latn'];
	}

	return options($items, $selected);
}

// "1 listing" / "3 listings"
function plural(int $count, string $one, string $many): string
{
	return $count . ' ' . ($count === 1 ? $one : $many);
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

	$html = '<nav class="pagination" aria-label="Pagination"><span>' . ($total ? 'Showing ' . $from . '–' . $to . ' of ' . number_format($total, 0, ',', ' ') : 'Nothing found') . '</span>';
	if ($pages > 1) {
		$html .= '<ul class="pagination__pages">' . $link(max(1, $page - 1), icon('chevron-left', 16), ' aria-label="Previous page"' . ($page === 1 ? ' aria-disabled="true"' : ''));
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
		$html .= $link(min($pages, $page + 1), icon('chevron-right', 16), ' aria-label="Next page"' . ($page === $pages ? ' aria-disabled="true"' : '')) . '</ul>';
	}

	return $html . '</nav>';
}

/* ---------- Moderation ---------- */

// Report reasons (keys as in the form on the site: r.<key>)
function report_reasons(): array
{
	return [
		'fraud' => 'Suspected fraud',
		'sold' => 'Item already sold',
		'duplicate' => 'Duplicate listing',
		'wrong_category' => 'Wrong category',
		'inappropriate' => 'Inappropriate content',
		'other' => 'Other',
	];
}

// Report resolutions
function report_resolutions(): array
{
	return [
		'dismiss' => 'Dismiss: no violation',
		'edit' => 'Ask the seller to edit the listing',
		'remove' => 'Remove the listing',
		'remove_block' => 'Remove the listing and block the seller',
	];
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

// Audit record → dashboard / history line: ['type', 'icon', 'text']
function audit_line(array $entry): array
{
	$c = $entry['context'];
	$who = $entry['user_id'] ? user_name($entry['user_id']) : 'System';
	$listing = isset($c['listing_id']) ? '#' . $c['listing_id'] : '';

	return match ($entry['action']) {
		'listing.create' => ['listing', 'tag', $who . ' posted listing ' . $listing . ', waiting for moderation'],
		'listing.update' => ['listing', 'edit', $who . ' edited listing ' . $listing . ', back to moderation'],
		'listing.draft' => ['listing', 'edit', $who . ' saved draft ' . $listing],
		'listing.approve' => ['user', 'check', $who . ' approved listing ' . $listing],
		'listing.reject' => ['moderation', 'ban', $who . ' rejected listing ' . $listing . (isset($c['reason']) ? ': ' . (reject_reasons()[$c['reason']] ?? $c['reason']) : '')],
		'listing.remove' => ['moderation', 'trash', $who . ' removed listing ' . $listing],
		'listing.staff_edit' => ['moderation', 'edit', $who . ' corrected listing ' . $listing . (!empty($c['fields']) ? ': ' . implode(', ', $c['fields']) : '')],
		'listing.staff_photos' => ['moderation', 'image', $who . match ($c['op'] ?? '') {
			'add' => ' added ' . plural((int) ($c['photos'] ?? 0), 'photo', 'photos') . ' to listing ',
			'cover' => ' changed the cover of listing ',
			default => ' deleted a photo of listing ',
		} . $listing],
		'listing.delete' => ['listing', 'trash', $who . ' deleted their listing ' . $listing],
		'listing.raise' => ['listing', 'arrow-up', $who . ' bumped listing ' . $listing],
		'listing.deactivate' => ['listing', 'power', $who . ' deactivated listing ' . $listing],
		'listing.activate' => ['listing', 'power', $who . ' activated listing ' . $listing],
		'listing.renew' => ['listing', 'refresh', $who . ' renewed listing ' . $listing],
		'report.create' => ['report', 'flag', $who . ' reported listing ' . $listing . (isset($c['reason']) ? ': ' . (report_reasons()[$c['reason']] ?? $c['reason']) : '')],
		'report.review' => ['report', 'flag', $who . ' took report #' . ($c['report_id'] ?? '') . ' into review'],
		'report.resolve' => ['report', 'check', $who . ' resolved report #' . ($c['report_id'] ?? '') . ' (' . (report_resolutions()[$c['resolution'] ?? ''] ?? '—') . ')'],
		'admin.user_block' => ['moderation', 'lock', $who . ' blocked ' . user_name($c['user_id'] ?? null)],
		'admin.user_unblock' => ['user', 'lock', $who . ' unblocked ' . user_name($c['user_id'] ?? null)],
		'admin.user_update' => ['user', 'edit', $who . ' changed ' . user_name($c['user_id'] ?? null) . ': ' . implode(', ', $c['fields'] ?? []) . (isset($c['role']) ? ' (role ' . $c['role'] . ')' : '')],
		'admin.user_note' => ['user', 'edit', $who . ' updated the note about ' . user_name($c['user_id'] ?? null)],
		'admin.user_reset_link' => ['user', 'lock', $who . ' sent a password reset link to ' . user_name($c['user_id'] ?? null)],
		'admin.user_sessions_end' => ['moderation', 'logout', $who . ' ended all sessions of ' . user_name($c['user_id'] ?? null)],
		'admin.user_delete' => ['moderation', 'trash', $who . ' deleted the account ' . user_name($c['user_id'] ?? null)],
		'auth.login' => ['user', 'user', $who . ' logged in'],
		'auth.password_changed', 'auth.password_reset' => ['user', 'lock', $who . ' changed the password'],
		'account.update' => ['user', 'edit', $who . ' updated the profile'],
		'account.delete' => ['moderation', 'trash', $who . ' deleted their account'],
		'auth.register' => ['user', 'user', 'New user registered: ' . user_name($c['user_id'] ?? null)],
		'contact.message' => ['user', 'mail', $who . ' wrote to support (message #' . ($c['contact_id'] ?? '') . ')'],
		'contact.answered' => ['user', 'check', $who . ' answered message #' . ($c['contact_id'] ?? '')],
		'contact.reopened' => ['user', 'mail', $who . ' marked message #' . ($c['contact_id'] ?? '') . ' as new'],
		default => ['listing', 'info', $who . ': ' . $entry['action'] . ($listing ? ' ' . $listing : '')],
	};
}
