<?php
/* ==========================================================================
	Admin texts: English (the source of keys; ru.php has the same keys)
	at('key', ['name' => …]) — {name} placeholders; atp() — plural forms
	['one' => …, 'other' => …], {n} is the number. Values with <code> are HTML.
	Groups: nav (sidebar), common (shared words and messages), seo (text fields
	of categories and landings), cat (categories), land (landing pages),
	edit (listing edit), deal / seller (facet values).
	========================================================================== */

return [
	/* ---------- Sidebar ---------- */
	'nav.landings' => 'Landing pages',

	/* ---------- Shared ---------- */
	'common.actions' => 'Actions',
	'common.apply' => 'Apply',
	'common.edit_name' => 'Edit {name}',
	'common.form_expired' => 'The form has expired. Please try again.',
	'common.languages' => 'Languages',
	'common.latin' => 'Latin',
	'common.nothing_changed' => 'Nothing changed.',
	'common.reset' => 'Reset',
	'common.save_changes' => 'Save changes',
	'common.saved_fields' => 'Saved: {fields}.',
	'common.show' => 'Show',
	'common.unknown_action' => 'Unknown action.',
	'common.view_on_site' => 'View on site',

	/* ---------- SEO texts (categories, landings) ---------- */
	'seo.h1' => 'Page heading (H1)',
	'seo.title_hint' => 'Up to 45 characters: “ — Place4Market” is added after it.',
	'seo.count_hint_en' => '<code>{count}</code> becomes the number of listings (“12 listings”; none yet: “Listings”).',
	'seo.count_hint_sr' => '<code>{count}</code> becomes the number of listings (“12 oglasa”; none yet: “Oglasi”).',
	'seo.text' => 'SEO text',
	'seo.text_short' => 'Text',
	'seo.text_empty' => 'Empty: no text',
	'seo.cyrillic_hint' => 'The Cyrillic page converts the Latin texts.',

	/* ---------- Categories ---------- */
	'cat.h1_empty' => 'Empty: the category name',
	'cat.text_hint' => 'Under the listings of the category page (without filters), collapsed. An empty line starts a new paragraph.',

	/* ---------- Landing pages ---------- */
	'land.title' => 'Landing pages',
	'land.sub' => 'Approved filter pages from the keyword research: their own heading, title, description and SEO text. Empty fields use the templates.',
	'land.table' => 'Landing pages table',
	'land.search' => 'Search landing pages',
	'land.search_ph' => 'Heading, place, kind, brand…',
	'land.all_categories' => 'All categories',
	'land.all_listings' => 'All listings',
	'land.texts' => 'Texts',
	'land.texts_any' => 'Any texts',
	'land.texts_own' => 'With own texts',
	'land.texts_template' => 'Templates only',
	'land.state' => 'State',
	'land.state_any' => 'Any state',
	'land.filter_on' => 'Switched on',
	'land.filter_off' => 'Switched off',
	'land.state_off' => 'Switched off',
	'land.state_indexed' => 'Indexed',
	'land.state_empty' => 'No listings: noindex',
	'land.col_page' => 'Page',
	'land.col_demand' => 'Searches / month',
	'land.col_listings' => 'Listings',
	'land.col_own' => 'Own texts',
	'land.col_state' => 'State',
	'land.empty' => 'No landing pages match the filters.',
	'land.not_found' => 'This landing page no longer exists.',
	'land.edit_title' => 'Landing page #{id}',
	'land.texts_hint' => 'Empty fields use the templates shown in grey. Serbian is the main language; the Cyrillic page converts the Serbian Latin texts.',
	'land.h1_hint' => 'Empty: the heading built from the filters (grey).',
	'land.text_hint' => 'Under the listings of the landing page, collapsed. An empty line starts a new paragraph.',
	'land.page' => 'Page',
	'land.f_category' => 'Category',
	'land.f_deal' => 'Deal',
	'land.f_type' => 'Kind',
	'land.f_brand' => 'Brand',
	'land.f_place' => 'Place',
	'land.f_district' => 'District',
	'land.demand' => 'Searches / month',
	'land.listings' => 'Listings now',
	'land.address' => 'Address {lang}',
	'land.demand_hint' => 'Searches per month from the keyword research (Google Keyword Planner, Serbia).',
	'land.active' => 'Approved landing page',
	'land.active_hint' => 'Switched on: indexed while it has listings, in the sitemap and in “Popular searches”. Switched off: an ordinary filter page (noindex).',
	'land.flash_on' => '“{name}” is switched on: indexed while it has listings.',
	'land.flash_off' => '“{name}” is switched off: an ordinary filter page (noindex).',

	/* ---------- Listing edit ---------- */
	'edit.not_set' => '— Not set —',
	'edit.f_deal' => 'Deal',
	'edit.f_type' => 'Kind',
	'edit.f_country' => 'Country',
	'edit.f_brand' => 'Brand',
	'edit.brand_other' => 'Other brand',
	'edit.f_district' => 'District',
	'edit.district_none' => 'Not specified',
	'edit.f_seller' => 'Advertiser',

	/* ---------- Facet values ---------- */
	'deal.sale' => 'For sale',
	'deal.rent' => 'For rent',
	'deal.daily' => 'Daily rent',
	'seller.private' => 'Private person',
	'seller.business' => 'Business',
];
