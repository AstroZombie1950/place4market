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

// Count items where $field equals $value
function count_by(array $items, string $field, $value): int
{
	return count(array_filter($items, fn($item) => ($item[$field] ?? null) === $value));
}

// Filter items where $field equals $value
function filter_by(array $items, string $field, $value): array
{
	return array_values(array_filter($items, fn($item) => ($item[$field] ?? null) === $value));
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

// User name by id
function user_name(?int $id): string
{
	$user = $id ? user($id) : null;

	return $user ? $user['name'] : '—';
}

// Number of listings in a category (parent counts its children)
function category_listing_count(array $category): int
{
	$slugs = [$category['slug']];

	foreach ($category['children'] ?? [] as $child) {
		$slugs[] = $child['slug'];
	}

	return count(array_filter(all_listings(), fn($l) => in_array($l['category'], $slugs, true)));
}

// Number of listings by city
function city_listing_count(string $city): int
{
	return count_by(all_listings(), 'city', $city);
}

// Payload for the category edit form (used by JS)
function category_payload(array $category, ?array $parent = null): array
{
	return [
		'formTitle' => 'Edit: ' . $category['names']['en'],
		'id' => $category['id'],
		'name_en' => $category['names']['en'],
		'name_srl' => $category['names']['sr-latn'],
		'name_src' => $category['names']['sr-cyrl'],
		'slug_en' => slugify($category['names']['en']),
		'slug_srl' => slugify($category['names']['sr-latn']),
		'slug_src' => slugify($category['names']['sr-latn']),
		'parent' => $parent ? (string) $parent['id'] : '',
		'icon' => $category['icon'] ?? ($parent['icon'] ?? 'car'),
		'sort' => $category['sort'] ?? 0,
		'active' => (bool) $category['active'],
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

// Grouped <option>s for the category select (children under parents)
function category_options(string $selected = ''): string
{
	$html = '';

	foreach (categories() as $parent) {
		$html .= '<optgroup label="' . e($parent['names']['en']) . '">';
		foreach ($parent['children'] as $child) {
			$isSelected = $child['slug'] === $selected ? ' selected' : '';
			$html .= '<option value="' . e($child['slug']) . '"' . $isSelected . '>' . e($child['names']['en']) . '</option>';
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

	in_lang($listing['lang'], function () use ($listing, $seller, $decision) {
		$params = ['name' => person_name($seller['name']), 'title' => listing_title($listing) ?: '#' . $listing['id'], 'days' => LISTING_DAYS];

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
		'auth.register' => ['user', 'user', 'New user registered: ' . user_name($c['user_id'] ?? null)],
		default => ['listing', 'info', $who . ': ' . $entry['action'] . ($listing ? ' ' . $listing : '')],
	};
}
