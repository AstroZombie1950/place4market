<?php
/* ==========================================================================
	Mock data for the admin layout
	Replace each array with a database query when the backend is ready.
	========================================================================== */

// Categories, cities and languages are shared with the site
require SOURCE_DIR . '/inc/data-common.php';

// Users
$users = [
	['id' => 1, 'name' => 'Marko Petrović', 'email' => 'marko.petrovic@example.com', 'phone' => '+381 63 000 1101', 'city' => 'Novi Sad', 'role' => 'user', 'status' => 'active', 'verified' => true, 'registered' => '2025-03-14', 'last_active' => '2026-10-20 14:40'],
	['id' => 2, 'name' => 'Jelena Jovanović', 'email' => 'jelena.j@example.com', 'phone' => '+381 64 000 2202', 'city' => 'Novi Sad', 'role' => 'user', 'status' => 'active', 'verified' => true, 'registered' => '2024-11-02', 'last_active' => '2026-10-20 13:58'],
	['id' => 3, 'name' => 'Nikola Nikolić', 'email' => 'nikola@place4market.example', 'phone' => '+381 60 000 3303', 'city' => 'Beograd', 'role' => 'admin', 'status' => 'active', 'verified' => true, 'registered' => '2024-06-01', 'last_active' => '2026-10-20 14:55'],
	['id' => 4, 'name' => 'Ana Ilić', 'email' => 'ana.ilic@example.com', 'phone' => '+381 65 000 4404', 'city' => 'Niš', 'role' => 'user', 'status' => 'active', 'verified' => false, 'registered' => '2026-01-19', 'last_active' => '2026-10-20 12:41'],
	['id' => 5, 'name' => 'Stefan Đorđević', 'email' => 'stefan.dj@example.com', 'phone' => '+381 62 000 5505', 'city' => 'Kragujevac', 'role' => 'user', 'status' => 'active', 'verified' => true, 'registered' => '2025-07-08', 'last_active' => '2026-10-20 11:30'],
	['id' => 6, 'name' => 'Milica Pavlović', 'email' => 'milica.p@example.com', 'phone' => '+381 63 000 6606', 'city' => 'Subotica', 'role' => 'user', 'status' => 'active', 'verified' => true, 'registered' => '2025-09-23', 'last_active' => '2026-10-20 09:12'],
	['id' => 7, 'name' => 'Luka Marković', 'email' => 'luka.markovic@example.com', 'phone' => '+381 64 000 7707', 'city' => 'Beograd', 'role' => 'user', 'status' => 'active', 'verified' => true, 'registered' => '2025-02-11', 'last_active' => '2026-10-20 13:20'],
	['id' => 8, 'name' => 'Ivana Stojanović', 'email' => 'ivana.s@example.com', 'phone' => '+381 66 000 8808', 'city' => 'Čačak', 'role' => 'moderator', 'status' => 'active', 'verified' => true, 'registered' => '2024-12-05', 'last_active' => '2026-10-19 16:44'],
	['id' => 9, 'name' => 'Dragan Kostić', 'email' => 'dragan.k@example.com', 'phone' => '+381 61 000 9909', 'city' => 'Zrenjanin', 'role' => 'user', 'status' => 'blocked', 'verified' => false, 'registered' => '2026-09-30', 'last_active' => '2026-10-17 20:02'],
	['id' => 10, 'name' => 'Tijana Lukić', 'email' => 'tijana.lukic@example.com', 'phone' => '+381 69 000 1010', 'city' => 'Kraljevo', 'role' => 'user', 'status' => 'active', 'verified' => false, 'registered' => '2026-10-18', 'last_active' => '2026-10-18 10:15'],
];

// Listings
$listings = [
	['id' => 1048, 'title' => 'VW Golf 7 1.6 TDI, 2016', 'category' => 'cars', 'price' => 9800, 'currency' => 'EUR', 'negotiable' => true, 'city' => 'Novi Sad', 'user_id' => 1, 'status' => 'active', 'created' => '2026-10-20 14:32', 'views' => 214, 'favorites' => 18, 'messages' => 6, 'photos' => 8,
		'description' => "Well maintained, one owner in Serbia. Full service history, two sets of tyres.\nRegistered until March 2027."],
	['id' => 1047, 'title' => 'Two-bedroom apartment, Liman 3', 'category' => 'apartments', 'price' => 145000, 'currency' => 'EUR', 'negotiable' => true, 'city' => 'Novi Sad', 'user_id' => 2, 'status' => 'active', 'created' => '2026-10-20 13:57', 'views' => 512, 'favorites' => 41, 'messages' => 9, 'photos' => 12],
	['id' => 1046, 'title' => 'iPhone 13, 128 GB', 'category' => 'phones', 'price' => 52000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Beograd', 'user_id' => 7, 'status' => 'pending', 'created' => '2026-10-20 13:20', 'views' => 0, 'favorites' => 0, 'messages' => 0, 'photos' => 4],
	['id' => 1045, 'title' => 'City bike, 28 inch', 'category' => 'bikes', 'price' => 14500, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Niš', 'user_id' => 4, 'status' => 'active', 'created' => '2026-10-20 12:41', 'views' => 96, 'favorites' => 7, 'messages' => 2, 'photos' => 3],
	['id' => 1044, 'title' => 'Baby stroller 3 in 1', 'category' => 'strollers', 'price' => 18000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Kragujevac', 'user_id' => 5, 'status' => 'pending', 'created' => '2026-10-20 11:30', 'views' => 0, 'favorites' => 0, 'messages' => 0, 'photos' => 5],
	['id' => 1043, 'title' => 'Corner sofa, grey', 'category' => 'furniture', 'price' => 35000, 'currency' => 'RSD', 'negotiable' => true, 'city' => 'Subotica', 'user_id' => 6, 'status' => 'active', 'created' => '2026-10-20 09:12', 'views' => 143, 'favorites' => 12, 'messages' => 4, 'photos' => 6],
	['id' => 1042, 'title' => 'MacBook Air M1, 8/256 GB', 'category' => 'computers', 'price' => 85000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Beograd', 'user_id' => 7, 'status' => 'active', 'created' => '2026-10-19 18:05', 'views' => 301, 'favorites' => 25, 'messages' => 8, 'photos' => 5],
	['id' => 1041, 'title' => 'Washing machine Gorenje, 7 kg', 'category' => 'appliances', 'price' => 22000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Čačak', 'user_id' => 8, 'status' => 'active', 'created' => '2026-10-19 16:44', 'views' => 77, 'favorites' => 3, 'messages' => 1, 'photos' => 2],
	['id' => 1040, 'title' => 'Ski set Atomic, 170 cm', 'category' => 'winter-sports', 'price' => 16000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Kraljevo', 'user_id' => 10, 'status' => 'pending', 'created' => '2026-10-18 10:15', 'views' => 0, 'favorites' => 0, 'messages' => 0, 'photos' => 3],
	['id' => 1039, 'title' => 'PlayStation 5 with two controllers', 'category' => 'consoles', 'price' => 48000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Beograd', 'user_id' => 9, 'status' => 'rejected', 'created' => '2026-10-17 20:02', 'views' => 58, 'favorites' => 2, 'messages' => 3, 'photos' => 1, 'reject_reason' => 'Stock photos only, no real photos of the item.'],
	['id' => 1038, 'title' => 'Plumbing services, 0–24', 'category' => 'services', 'price' => null, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Zrenjanin', 'user_id' => 9, 'status' => 'rejected', 'created' => '2026-10-16 08:30', 'views' => 34, 'favorites' => 0, 'messages' => 0, 'photos' => 0, 'reject_reason' => 'Contact details in the title.'],
	['id' => 1037, 'title' => 'Winter tyres 205/55 R16', 'category' => 'auto-parts', 'price' => 18000, 'currency' => 'RSD', 'negotiable' => true, 'city' => 'Novi Sad', 'user_id' => 1, 'status' => 'expired', 'created' => '2026-08-10 17:20', 'views' => 188, 'favorites' => 9, 'messages' => 5, 'photos' => 4],
];

// Reports (complaints about listings)
$reports = [
	['id' => 307, 'listing_id' => 1039, 'reason' => 'Suspected fraud', 'reporter_id' => 2, 'comment' => 'Price is well below market and the seller asks for an advance payment.', 'created' => '2026-10-20 12:05', 'status' => 'open'],
	['id' => 306, 'listing_id' => 1043, 'reason' => 'Item already sold', 'reporter_id' => 4, 'comment' => 'Called the seller, the sofa was sold last week.', 'created' => '2026-10-20 10:31', 'status' => 'open'],
	['id' => 305, 'listing_id' => 1046, 'reason' => 'Duplicate listing', 'reporter_id' => 6, 'comment' => 'Same phone posted twice under different titles.', 'created' => '2026-10-19 22:14', 'status' => 'open'],
	['id' => 304, 'listing_id' => 1038, 'reason' => 'Inappropriate content', 'reporter_id' => 5, 'comment' => 'Phone number written in the title.', 'created' => '2026-10-19 15:02', 'status' => 'review'],
	['id' => 303, 'listing_id' => 1045, 'reason' => 'Wrong price', 'reporter_id' => 8, 'comment' => 'Price in the title differs from the description.', 'created' => '2026-10-18 09:40', 'status' => 'review'],
	['id' => 302, 'listing_id' => 1037, 'reason' => 'Item already sold', 'reporter_id' => 10, 'comment' => '', 'created' => '2026-10-15 18:22', 'status' => 'resolved'],
	['id' => 301, 'listing_id' => 1048, 'reason' => 'Suspected fraud', 'reporter_id' => 7, 'comment' => 'Mileage looks edited on one of the photos.', 'created' => '2026-10-12 11:05', 'status' => 'resolved'],
];

// Recent activity log
$activity = [
	['type' => 'listing', 'icon' => 'tag', 'time' => '2026-10-20 14:32', 'text' => 'Marko Petrović posted “VW Golf 7 1.6 TDI, 2016”'],
	['type' => 'listing', 'icon' => 'tag', 'time' => '2026-10-20 13:20', 'text' => 'Luka Marković posted “iPhone 13, 128 GB”, waiting for moderation'],
	['type' => 'report', 'icon' => 'flag', 'time' => '2026-10-20 12:05', 'text' => 'New report on “PlayStation 5 with two controllers”: Suspected fraud'],
	['type' => 'user', 'icon' => 'user', 'time' => '2026-10-18 10:02', 'text' => 'New user registered: Tijana Lukić'],
	['type' => 'moderation', 'icon' => 'ban', 'time' => '2026-10-17 21:10', 'text' => 'Nikola Nikolić rejected “PlayStation 5 with two controllers”'],
	['type' => 'moderation', 'icon' => 'lock', 'time' => '2026-10-17 20:40', 'text' => 'Dragan Kostić was blocked by Nikola Nikolić'],
];

// Reject reasons (moderation)
$rejectReasons = [
	'prohibited' => 'Prohibited item',
	'contacts' => 'Contact details in title or photos',
	'category' => 'Wrong category',
	'duplicate' => 'Duplicate listing',
	'photos' => 'Low quality or stock photos',
	'price' => 'Misleading price',
	'other' => 'Other',
];
