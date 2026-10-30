<?php
/* ==========================================================================
	Demo data: the source for the database seed (source/db/seed.php), plus
	what is not in the database yet and is read through repo.php (mock()):
	admin activity (→ audit log) and language settings (→ settings).
	Dates are relative to the moment of seeding.
	User text is given in 'en' and 'sr' (Latin); the seed keeps one language
	per listing, as a real author writes (see SEED_ENGLISH in seed.php).
	========================================================================== */

// Relative date: ago('-2 hours') → "2026-10-21 05:10:00"
function ago(string $relative): string
{
	return date('Y-m-d H:i:s', strtotime($relative));
}

// Categories: parents with children, names in EN / SR (Latin) / SR (Cyrillic)
$categories = [
	[
		'id' => 1, 'slug' => 'vehicles', 'icon' => 'car', 'sort' => 1, 'active' => true,
		'names' => ['en' => 'Vehicles', 'sr-latn' => 'Vozila', 'sr-cyrl' => 'Возила'],
		'children' => [
			['id' => 11, 'slug' => 'cars', 'sort' => 1, 'active' => true, 'names' => ['en' => 'Cars', 'sr-latn' => 'Automobili', 'sr-cyrl' => 'Аутомобили']],
			['id' => 12, 'slug' => 'motorcycles', 'sort' => 2, 'active' => true, 'names' => ['en' => 'Motorcycles', 'sr-latn' => 'Motocikli', 'sr-cyrl' => 'Мотоцикли']],
			['id' => 13, 'slug' => 'auto-parts', 'sort' => 3, 'active' => true, 'names' => ['en' => 'Parts & accessories', 'sr-latn' => 'Delovi i oprema', 'sr-cyrl' => 'Делови и опрема']],
		],
	],
	[
		'id' => 2, 'slug' => 'real-estate', 'icon' => 'home', 'sort' => 2, 'active' => true,
		'names' => ['en' => 'Real estate', 'sr-latn' => 'Nekretnine', 'sr-cyrl' => 'Некретнине'],
		'children' => [
			['id' => 21, 'slug' => 'apartments', 'sort' => 1, 'active' => true, 'names' => ['en' => 'Apartments', 'sr-latn' => 'Stanovi', 'sr-cyrl' => 'Станови']],
			['id' => 22, 'slug' => 'houses', 'sort' => 2, 'active' => true, 'names' => ['en' => 'Houses', 'sr-latn' => 'Kuće', 'sr-cyrl' => 'Куће']],
			['id' => 23, 'slug' => 'land', 'sort' => 3, 'active' => false, 'names' => ['en' => 'Land', 'sr-latn' => 'Placevi', 'sr-cyrl' => 'Плацеви']],
		],
	],
	[
		'id' => 3, 'slug' => 'electronics', 'icon' => 'laptop', 'sort' => 3, 'active' => true,
		'names' => ['en' => 'Electronics', 'sr-latn' => 'Elektronika', 'sr-cyrl' => 'Електроника'],
		'children' => [
			['id' => 31, 'slug' => 'phones', 'sort' => 1, 'active' => true, 'names' => ['en' => 'Mobile phones', 'sr-latn' => 'Mobilni telefoni', 'sr-cyrl' => 'Мобилни телефони']],
			['id' => 32, 'slug' => 'computers', 'sort' => 2, 'active' => true, 'names' => ['en' => 'Computers', 'sr-latn' => 'Računari', 'sr-cyrl' => 'Рачунари']],
			['id' => 33, 'slug' => 'consoles', 'sort' => 3, 'active' => true, 'names' => ['en' => 'Consoles & games', 'sr-latn' => 'Konzole i igrice', 'sr-cyrl' => 'Конзоле и игрице']],
		],
	],
	[
		'id' => 4, 'slug' => 'home-garden', 'icon' => 'plant', 'sort' => 4, 'active' => true,
		'names' => ['en' => 'Home & garden', 'sr-latn' => 'Kuća i bašta', 'sr-cyrl' => 'Кућа и башта'],
		'children' => [
			['id' => 41, 'slug' => 'furniture', 'sort' => 1, 'active' => true, 'names' => ['en' => 'Furniture', 'sr-latn' => 'Nameštaj', 'sr-cyrl' => 'Намештај']],
			['id' => 42, 'slug' => 'appliances', 'sort' => 2, 'active' => true, 'names' => ['en' => 'Home appliances', 'sr-latn' => 'Bela tehnika', 'sr-cyrl' => 'Бела техника']],
		],
	],
	[
		'id' => 5, 'slug' => 'fashion', 'icon' => 'shirt', 'sort' => 5, 'active' => true,
		'names' => ['en' => 'Fashion', 'sr-latn' => 'Moda', 'sr-cyrl' => 'Мода'],
		'children' => [
			['id' => 51, 'slug' => 'womens-clothing', 'sort' => 1, 'active' => true, 'names' => ['en' => "Women's clothing", 'sr-latn' => 'Ženska odeća', 'sr-cyrl' => 'Женска одећа']],
			['id' => 52, 'slug' => 'mens-clothing', 'sort' => 2, 'active' => true, 'names' => ['en' => "Men's clothing", 'sr-latn' => 'Muška odeća', 'sr-cyrl' => 'Мушка одећа']],
		],
	],
	[
		'id' => 6, 'slug' => 'kids', 'icon' => 'stroller', 'sort' => 6, 'active' => true,
		'names' => ['en' => 'Kids', 'sr-latn' => 'Deca', 'sr-cyrl' => 'Деца'],
		'children' => [
			['id' => 61, 'slug' => 'strollers', 'sort' => 1, 'active' => true, 'names' => ['en' => 'Strollers', 'sr-latn' => 'Kolica', 'sr-cyrl' => 'Колица']],
			['id' => 62, 'slug' => 'toys', 'sort' => 2, 'active' => true, 'names' => ['en' => 'Toys', 'sr-latn' => 'Igračke', 'sr-cyrl' => 'Играчке']],
		],
	],
	[
		'id' => 7, 'slug' => 'jobs-services', 'icon' => 'briefcase', 'sort' => 7, 'active' => true,
		'names' => ['en' => 'Jobs & services', 'sr-latn' => 'Poslovi i usluge', 'sr-cyrl' => 'Послови и услуге'],
		'children' => [
			['id' => 71, 'slug' => 'services', 'sort' => 1, 'active' => true, 'names' => ['en' => 'Services', 'sr-latn' => 'Usluge', 'sr-cyrl' => 'Услуге']],
			['id' => 72, 'slug' => 'jobs', 'sort' => 2, 'active' => true, 'names' => ['en' => 'Jobs', 'sr-latn' => 'Poslovi', 'sr-cyrl' => 'Послови']],
		],
	],
	[
		'id' => 8, 'slug' => 'sports-hobby', 'icon' => 'ball', 'sort' => 8, 'active' => true,
		'names' => ['en' => 'Sports & hobby', 'sr-latn' => 'Sport i hobi', 'sr-cyrl' => 'Спорт и хоби'],
		'children' => [
			['id' => 81, 'slug' => 'bikes', 'sort' => 1, 'active' => true, 'names' => ['en' => 'Bikes', 'sr-latn' => 'Bicikli', 'sr-cyrl' => 'Бицикли']],
			['id' => 82, 'slug' => 'winter-sports', 'sort' => 2, 'active' => true, 'names' => ['en' => 'Winter sports', 'sr-latn' => 'Zimski sportovi', 'sr-cyrl' => 'Зимски спортови']],
		],
	],
];

// Cities (Latin / Cyrillic)
$cities = [
	['latn' => 'Beograd', 'cyrl' => 'Београд', 'active' => true],
	['latn' => 'Novi Sad', 'cyrl' => 'Нови Сад', 'active' => true],
	['latn' => 'Niš', 'cyrl' => 'Ниш', 'active' => true],
	['latn' => 'Kragujevac', 'cyrl' => 'Крагујевац', 'active' => true],
	['latn' => 'Subotica', 'cyrl' => 'Суботица', 'active' => true],
	['latn' => 'Zrenjanin', 'cyrl' => 'Зрењанин', 'active' => true],
	['latn' => 'Čačak', 'cyrl' => 'Чачак', 'active' => true],
	['latn' => 'Kraljevo', 'cyrl' => 'Краљево', 'active' => true],
];

// Site languages
$languages = [
	['code' => 'en', 'name' => 'English', 'native' => 'English', 'prefix' => 'en', 'enabled' => true, 'default' => true],
	['code' => 'sr-latn', 'name' => 'Serbian (Latin)', 'native' => 'Srpski', 'prefix' => 'sr', 'enabled' => true, 'default' => false],
	['code' => 'sr-cyrl', 'name' => 'Serbian (Cyrillic)', 'native' => 'Српски', 'prefix' => 'ср', 'enabled' => true, 'default' => false],
];

// Users by id. role: user | moderator | admin; status: active | blocked
$users = [
	1 => ['id' => 1, 'name' => 'Marko Petrović', 'email' => 'marko.petrovic@example.com', 'phone' => '+381 63 000 1101', 'city' => 'Novi Sad', 'role' => 'user', 'status' => 'active', 'verified' => true, 'registered' => '2025-03-14', 'last_active' => ago('-5 minutes')],
	2 => ['id' => 2, 'name' => 'Jelena Jovanović', 'email' => 'jelena.j@example.com', 'phone' => '+381 64 000 2202', 'city' => 'Novi Sad', 'role' => 'user', 'status' => 'active', 'verified' => true, 'registered' => '2024-11-02', 'last_active' => ago('-38 minutes')],
	3 => ['id' => 3, 'name' => 'Nikola Nikolić', 'email' => 'nikola@place4market.example', 'phone' => '+381 60 000 3303', 'city' => 'Beograd', 'role' => 'admin', 'status' => 'active', 'verified' => true, 'registered' => '2024-06-01', 'last_active' => ago('-1 minute')],
	4 => ['id' => 4, 'name' => 'Ana Ilić', 'email' => 'ana.ilic@example.com', 'phone' => '+381 65 000 4404', 'city' => 'Niš', 'role' => 'user', 'status' => 'active', 'verified' => false, 'registered' => '2026-01-19', 'last_active' => ago('-2 hours')],
	5 => ['id' => 5, 'name' => 'Stefan Đorđević', 'email' => 'stefan.dj@example.com', 'phone' => '+381 62 000 5505', 'city' => 'Kragujevac', 'role' => 'user', 'status' => 'active', 'verified' => true, 'registered' => '2025-07-08', 'last_active' => ago('-3 hours')],
	6 => ['id' => 6, 'name' => 'Milica Pavlović', 'email' => 'milica.p@example.com', 'phone' => '+381 63 000 6606', 'city' => 'Subotica', 'role' => 'user', 'status' => 'active', 'verified' => true, 'registered' => '2025-09-23', 'last_active' => ago('-5 hours')],
	7 => ['id' => 7, 'name' => 'Luka Marković', 'email' => 'luka.markovic@example.com', 'phone' => '+381 64 000 7707', 'city' => 'Beograd', 'role' => 'user', 'status' => 'active', 'verified' => true, 'registered' => '2025-02-11', 'last_active' => ago('-1 hour')],
	8 => ['id' => 8, 'name' => 'Ivana Stojanović', 'email' => 'ivana.s@example.com', 'phone' => '+381 66 000 8808', 'city' => 'Čačak', 'role' => 'moderator', 'status' => 'active', 'verified' => true, 'registered' => '2024-12-05', 'last_active' => ago('-1 day')],
	9 => ['id' => 9, 'name' => 'Dragan Kostić', 'email' => 'dragan.k@example.com', 'phone' => '+381 61 000 9909', 'city' => 'Zrenjanin', 'role' => 'user', 'status' => 'active', 'verified' => false, 'registered' => '2026-09-30', 'last_active' => ago('-2 days')],
	10 => ['id' => 10, 'name' => 'Tijana Lukić', 'email' => 'tijana.lukic@example.com', 'phone' => '+381 69 000 1010', 'city' => 'Kraljevo', 'role' => 'user', 'status' => 'active', 'verified' => false, 'registered' => '2026-10-18', 'last_active' => ago('-25 minutes')],
	11 => ['id' => 11, 'name' => 'Petar Simić', 'email' => 'petar.simic@example.com', 'phone' => '+381 60 000 1111', 'city' => 'Beograd', 'role' => 'user', 'status' => 'active', 'verified' => true, 'registered' => '2023-05-20', 'last_active' => ago('-4 days')],
	12 => ['id' => 12, 'name' => 'Nenad Ristić', 'email' => 'nenad.r@example.com', 'phone' => '+381 61 000 1212', 'city' => 'Zrenjanin', 'role' => 'user', 'status' => 'blocked', 'verified' => false, 'registered' => '2026-10-11', 'last_active' => ago('-13 days')],
];

// Listings (newest first). status: active | pending | rejected | expired
$listings = [
	[
		'id' => 1054, 'category' => 'phones', 'user_id' => 10, 'status' => 'pending',
		'title' => ['en' => 'Samsung Galaxy S21, 128 GB', 'sr' => 'Samsung Galaxy S21, 128 GB'],
		'price' => 38000, 'currency' => 'RSD', 'negotiable' => true, 'city' => 'Kraljevo',
		'created' => ago('-25 minutes'), 'views' => 0, 'favorites' => 0, 'messages' => 0, 'photos' => 4, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'memory' => '128 GB', 'color' => 'v.grey'],
		'description' => ['en' => 'Small scratch on the back, screen without damage. Charger included.', 'sr' => 'Mala ogrebotina na poleđini, ekran bez oštećenja. Uz telefon ide punjač.'],
	],
	[
		'id' => 1053, 'category' => 'bikes', 'user_id' => 6, 'status' => 'pending',
		'title' => ['en' => 'Kids bike, 20 inch', 'sr' => 'Dečji bicikl, 20 inča'],
		'price' => 9000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Subotica',
		'created' => ago('-1 hour -20 minutes'), 'views' => 0, 'favorites' => 0, 'messages' => 0, 'photos' => 3, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'wheel' => '20"', 'gears' => '6'],
		'description' => ['en' => 'For ages 6–9, new tyres, a few scratches on the frame.', 'sr' => 'Za uzrast 6–9 godina, nove gume, par ogrebotina na ramu.'],
	],
	[
		'id' => 1050, 'category' => 'auto-parts', 'user_id' => 1, 'status' => 'pending',
		'title' => ['en' => 'Car roof box, 420 L', 'sr' => 'Krovni kofer, 420 l'],
		'price' => 9500, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Novi Sad',
		'created' => ago('-40 minutes'), 'views' => 0, 'favorites' => 0, 'messages' => 0, 'photos' => 3, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'capacity' => '420 l', 'color' => 'v.black'],
		'description' => ['en' => 'Universal roof box with keys and mounting kit. Fits most roof bars.', 'sr' => 'Univerzalni krovni kofer sa ključevima i nosačima. Odgovara većini krovnih šipki.'],
	],
	[
		'id' => 1048, 'category' => 'cars', 'user_id' => 1, 'status' => 'active',
		'title' => ['en' => 'VW Golf 7 1.6 TDI, 2016', 'sr' => 'VW Golf 7 1.6 TDI, 2016'],
		'price' => 9800, 'currency' => 'EUR', 'negotiable' => true, 'city' => 'Novi Sad',
		'created' => ago('-12 minutes'), 'views' => 214, 'favorites' => 18, 'messages' => 6, 'photos' => 8, 'condition' => 'used',
		'attrs' => ['year' => '2016', 'mileage' => '168 000 km', 'fuel' => 'v.diesel', 'gearbox' => 'v.manual', 'power' => '81 kW', 'body' => 'v.hatchback', 'color' => 'v.grey'],
		'description' => [
			'en' => "Well maintained, one owner in Serbia. Full service history, timing belt changed at 150 000 km.\nTwo sets of tyres on rims. Registered until March 2027. Test drive and a check at your mechanic are welcome.",
			'sr' => "Uredno održavan, prvi vlasnik u Srbiji. Kompletna servisna istorija, zupčasti kaiš menjan na 150 000 km.\nDva kompleta guma na felnama. Registrovan do marta 2027. Probna vožnja i provera kod vašeg majstora su mogući.",
		],
	],
	[
		'id' => 1047, 'category' => 'apartments', 'user_id' => 2, 'status' => 'active',
		'title' => ['en' => 'Two-bedroom apartment, Liman 3', 'sr' => 'Dvosoban stan, Liman 3'],
		'price' => 145000, 'currency' => 'EUR', 'negotiable' => true, 'city' => 'Novi Sad',
		'created' => ago('-35 minutes'), 'views' => 512, 'favorites' => 41, 'messages' => 9, 'photos' => 12, 'condition' => null,
		'attrs' => ['area' => '58 m²', 'rooms' => '2', 'floor' => '4/8', 'heating' => 'v.central', 'built' => '1978', 'furnished' => 'v.partly'],
		'description' => [
			'en' => "Bright apartment near the Danube quay, renovated in 2023. Two bedrooms, separate kitchen, glazed terrace.\nElevator, storage room in the basement. Schools and shops nearby.",
			'sr' => "Svetao stan blizu keja, renoviran 2023. Dve spavaće sobe, odvojena kuhinja, zastakljena terasa.\nLift, ostava u podrumu. U blizini škole i prodavnice.",
		],
	],
	[
		'id' => 1046, 'category' => 'phones', 'user_id' => 7, 'status' => 'active',
		'title' => ['en' => 'iPhone 13, 128 GB', 'sr' => 'iPhone 13, 128 GB'],
		'price' => 52000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Beograd',
		'created' => ago('-1 hour'), 'views' => 88, 'favorites' => 5, 'messages' => 3, 'photos' => 4, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'memory' => '128 GB', 'battery' => '87%', 'color' => 'v.black'],
		'description' => ['en' => 'No scratches on the screen, always used with a case. Box and cable included.', 'sr' => 'Ekran bez ogrebotina, uvek korišćen sa maskom. Uz telefon idu kutija i kabl.'],
	],
	[
		'id' => 1045, 'category' => 'bikes', 'user_id' => 4, 'status' => 'active',
		'title' => ['en' => 'City bike, 28 inch', 'sr' => 'Gradski bicikl, 28 inča'],
		'price' => 14500, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Niš',
		'created' => ago('-2 hours'), 'views' => 96, 'favorites' => 7, 'messages' => 2, 'photos' => 3, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'wheel' => '28"', 'frame' => '54 cm', 'gears' => '7'],
		'description' => ['en' => 'Comfortable city bike with a basket and lights. New brake pads.', 'sr' => 'Udoban gradski bicikl sa korpom i svetlima. Nove kočione pakne.'],
	],
	[
		'id' => 1044, 'category' => 'strollers', 'user_id' => 5, 'status' => 'active',
		'title' => ['en' => 'Baby stroller 3 in 1', 'sr' => 'Kolica za bebe 3 u 1'],
		'price' => 18000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Kragujevac',
		'created' => ago('-3 hours'), 'views' => 41, 'favorites' => 4, 'messages' => 1, 'photos' => 5, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'color' => 'v.grey', 'age' => '0–3'],
		'description' => ['en' => 'Carrycot, seat and car seat. Used for one child, washed and complete.', 'sr' => 'Korpa, sedište i auto-sedište. Korišćena za jedno dete, oprana i kompletna.'],
	],
	[
		'id' => 1043, 'category' => 'furniture', 'user_id' => 6, 'status' => 'active',
		'title' => ['en' => 'Corner sofa, grey', 'sr' => 'Ugaona garnitura, siva'],
		'price' => 35000, 'currency' => 'RSD', 'negotiable' => true, 'city' => 'Subotica',
		'created' => ago('-5 hours'), 'views' => 143, 'favorites' => 12, 'messages' => 4, 'photos' => 6, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'dimensions' => '260 × 180 cm', 'color' => 'v.grey', 'sleeping' => 'v.yes'],
		'description' => ['en' => 'Sofa bed with storage. Pick-up only, ground floor.', 'sr' => 'Garnitura na razvlačenje sa sandukom. Lično preuzimanje, prizemlje.'],
	],
	[
		'id' => 1042, 'category' => 'computers', 'user_id' => 7, 'status' => 'active',
		'title' => ['en' => 'MacBook Air M1, 8/256 GB', 'sr' => 'MacBook Air M1, 8/256 GB'],
		'price' => 85000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Beograd',
		'created' => ago('-1 day -2 hours'), 'views' => 301, 'favorites' => 25, 'messages' => 8, 'photos' => 5, 'condition' => 'like_new',
		'attrs' => ['condition' => 'v.like_new', 'ram' => '8 GB', 'ssd' => '256 GB', 'battery' => '94%'],
		'description' => ['en' => '112 battery cycles, original charger. Bought in Serbia, receipt available.', 'sr' => '112 ciklusa baterije, originalni punjač. Kupljen u Srbiji, postoji račun.'],
	],
	[
		'id' => 1041, 'category' => 'appliances', 'user_id' => 8, 'status' => 'active',
		'title' => ['en' => 'Washing machine Gorenje, 7 kg', 'sr' => 'Veš mašina Gorenje, 7 kg'],
		'price' => 22000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Čačak',
		'created' => ago('-1 day -5 hours'), 'views' => 77, 'favorites' => 3, 'messages' => 1, 'photos' => 2, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'capacity' => '7 kg', 'energy' => 'A+++'],
		'description' => ['en' => 'Works perfectly, selling because we are moving.', 'sr' => 'Radi besprekorno, prodajem zbog selidbe.'],
	],
	[
		'id' => 1040, 'category' => 'winter-sports', 'user_id' => 10, 'status' => 'active',
		'title' => ['en' => 'Ski set Atomic, 170 cm', 'sr' => 'Skijaški komplet Atomic, 170 cm'],
		'price' => 16000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Kraljevo',
		'created' => ago('-2 days'), 'views' => 52, 'favorites' => 2, 'messages' => 0, 'photos' => 3, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'length' => '170 cm'],
		'description' => ['en' => 'Skis with bindings, serviced last season. Ready for Kopaonik.', 'sr' => 'Skije sa vezovima, servisirane prošle sezone. Spremne za Kopaonik.'],
	],
	[
		'id' => 1039, 'category' => 'mens-clothing', 'user_id' => 10, 'status' => 'active',
		'title' => ['en' => 'Winter jacket, size M', 'sr' => 'Zimska jakna, veličina M'],
		'price' => 4500, 'currency' => 'RSD', 'negotiable' => true, 'city' => 'Kraljevo',
		'created' => ago('-2 days -4 hours'), 'views' => 23, 'favorites' => 1, 'messages' => 0, 'photos' => 2, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'size' => 'M', 'color' => 'v.blue'],
		'description' => ['en' => 'Warm down jacket, worn one season.', 'sr' => 'Topla pernata jakna, nošena jednu sezonu.'],
	],
	[
		'id' => 1038, 'category' => 'toys', 'user_id' => 2, 'status' => 'active',
		'title' => ['en' => 'Wooden toy kitchen', 'sr' => 'Drvena dečja kuhinja'],
		'price' => 6500, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Novi Sad',
		'created' => ago('-3 days'), 'views' => 64, 'favorites' => 6, 'messages' => 2, 'photos' => 4, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'material' => 'v.wood', 'age' => '3+'],
		'description' => ['en' => 'With pots and accessories. Smoke-free home.', 'sr' => 'Sa šerpama i dodacima. Kuća bez pušača.'],
	],
	[
		'id' => 1037, 'category' => 'auto-parts', 'user_id' => 1, 'status' => 'expired',
		'title' => ['en' => 'Winter tyres 205/55 R16', 'sr' => 'Zimske gume 205/55 R16'],
		'price' => 18000, 'currency' => 'RSD', 'negotiable' => true, 'city' => 'Novi Sad',
		'created' => ago('-70 days'), 'views' => 188, 'favorites' => 9, 'messages' => 5, 'photos' => 4, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used'],
		'description' => ['en' => 'Set of four, 6 mm tread, 2022.', 'sr' => 'Komplet od četiri, šara 6 mm, 2022. godište.'],
	],
	[
		'id' => 1036, 'category' => 'houses', 'user_id' => 2, 'status' => 'active',
		'title' => ['en' => 'House with a yard, Sremska Kamenica', 'sr' => 'Kuća sa dvorištem, Sremska Kamenica'],
		'price' => 210000, 'currency' => 'EUR', 'negotiable' => true, 'city' => 'Novi Sad',
		'created' => ago('-3 days -6 hours'), 'views' => 640, 'favorites' => 33, 'messages' => 12, 'photos' => 10, 'condition' => null,
		'attrs' => ['area' => '140 m²', 'plot' => '6 ari', 'rooms' => '4', 'heating' => 'v.gas', 'built' => '2008'],
		'description' => ['en' => 'Family house with a garage and fruit garden, 10 minutes from the city centre.', 'sr' => 'Porodična kuća sa garažom i voćnjakom, 10 minuta od centra grada.'],
	],
	[
		'id' => 1035, 'category' => 'services', 'user_id' => 11, 'status' => 'active',
		'title' => ['en' => 'Electrician, apartment wiring and repairs', 'sr' => 'Električar, elektroinstalacije i popravke'],
		'price' => null, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Beograd',
		'created' => ago('-4 days'), 'views' => 210, 'favorites' => 8, 'messages' => 7, 'photos' => 2, 'condition' => null,
		'attrs' => ['experience' => 'v.years_15', 'area_served' => 'v.beograd_all'],
		'description' => ['en' => 'Wiring, sockets, lighting, fuse boxes. Free estimate.', 'sr' => 'Instalacije, utičnice, rasveta, razvodne table. Besplatna procena.'],
	],
	[
		'id' => 1034, 'category' => 'motorcycles', 'user_id' => 4, 'status' => 'active',
		'title' => ['en' => 'Yamaha MT-07, 2019', 'sr' => 'Yamaha MT-07, 2019'],
		'price' => 6200, 'currency' => 'EUR', 'negotiable' => false, 'city' => 'Niš',
		'created' => ago('-4 days -3 hours'), 'views' => 175, 'favorites' => 11, 'messages' => 3, 'photos' => 6, 'condition' => 'used',
		'attrs' => ['year' => '2019', 'mileage' => '21 000 km', 'engine' => '689 cm³', 'body' => 'v.naked'],
		'description' => ['en' => 'Garage kept, regular service, new chain.', 'sr' => 'Garažiran, redovno servisiran, nov lanac.'],
	],
	[
		'id' => 1033, 'category' => 'consoles', 'user_id' => 9, 'status' => 'active',
		'title' => ['en' => 'PlayStation 5 with two controllers', 'sr' => 'PlayStation 5 sa dva džojstika'],
		'price' => 48000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Zrenjanin',
		'created' => ago('-5 days'), 'views' => 58, 'favorites' => 2, 'messages' => 3, 'photos' => 3, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'memory' => '825 GB', 'controllers' => '2'],
		'description' => ['en' => 'Disc edition, two games included.', 'sr' => 'Verzija sa disk čitačem, uz dve igrice.'],
	],
	[
		'id' => 1032, 'category' => 'furniture', 'user_id' => 5, 'status' => 'active',
		'title' => ['en' => 'Dining table with 6 chairs', 'sr' => 'Trpezarijski sto sa 6 stolica'],
		'price' => 18500, 'currency' => 'RSD', 'negotiable' => true, 'city' => 'Kragujevac',
		'created' => ago('-6 days'), 'views' => 49, 'favorites' => 5, 'messages' => 2, 'photos' => 4, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'material' => 'v.oak', 'dimensions' => '180 × 90 cm'],
		'description' => ['en' => 'Solid oak table, chairs reupholstered last year.', 'sr' => 'Sto od punog hrasta, stolice presvučene prošle godine.'],
	],
	[
		'id' => 1031, 'category' => 'consoles', 'user_id' => 12, 'status' => 'rejected',
		'title' => ['en' => 'PlayStation 5, sealed', 'sr' => 'PlayStation 5, zapakovan'],
		'price' => 39000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Zrenjanin',
		'created' => ago('-13 days -2 hours'), 'views' => 58, 'favorites' => 2, 'messages' => 3, 'photos' => 1, 'condition' => 'new',
		'attrs' => ['condition' => 'v.new', 'memory' => '825 GB'],
		'description' => ['en' => 'New, sealed. Advance payment required, shipping only.', 'sr' => 'Nov, zapakovan. Obavezna uplata unapred, samo slanje.'],
		'reject_reason' => 'Stock photos only, no real photos of the item.',
	],
	[
		'id' => 1030, 'category' => 'services', 'user_id' => 12, 'status' => 'rejected',
		'title' => ['en' => 'Plumbing services, 0–24', 'sr' => 'Vodoinstalaterske usluge, 0–24'],
		'price' => null, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Zrenjanin',
		'created' => ago('-14 days'), 'views' => 34, 'favorites' => 0, 'messages' => 0, 'photos' => 0, 'condition' => null,
		'attrs' => [],
		'description' => ['en' => 'Call any time: 061 000 1212.', 'sr' => 'Pozovite bilo kada: 061 000 1212.'],
		'reject_reason' => 'Contact details in the title.',
	],
];

// Demo photos and videos (Pexels, demo-photos.php / demo-videos.php) by listing id;
// 'photos' becomes the photo count
$demoPhotos = require SOURCE_DIR . '/inc/demo-photos.php';
$demoVideos = require SOURCE_DIR . '/inc/demo-videos.php';
foreach ($listings as $i => $item) {
	$listings[$i]['images'] = $demoPhotos[$item['id']] ?? [];
	$listings[$i]['videos'] = $demoVideos[$item['id']] ?? [];
	$listings[$i]['photos'] = count($listings[$i]['images']) ?: $item['photos'];
}
unset($i, $item);

// Favorites by user id (listing ids)
$favorites = [1 => [1047, 1042, 1036, 1034, 1043]];

// Saved searches by user id
$savedSearches = [1 => [
	['id' => 1, 'label' => ['en' => 'Apartments in Novi Sad up to 150 000 €', 'sr' => 'Stanovi u Novom Sadu do 150 000 €'], 'params' => ['cat' => 'apartments', 'city' => 'Novi Sad', 'price_to' => 150000, 'currency' => 'EUR'], 'new' => 3, 'alerts' => true],
	['id' => 2, 'label' => ['en' => 'MacBook in Beograd', 'sr' => 'MacBook u Beogradu'], 'params' => ['q' => 'MacBook', 'city' => 'Beograd'], 'new' => 1, 'alerts' => true],
	['id' => 3, 'label' => ['en' => 'Bikes in Niš', 'sr' => 'Bicikli u Nišu'], 'params' => ['cat' => 'bikes', 'city' => 'Niš'], 'new' => 0, 'alerts' => false],
]];

// Conversations by user id: 'with' = the other person, 'from' = sender's user id
$conversations = [1 => [
	[
		'id' => 1, 'with' => 2, 'listing_id' => 1047, 'unread' => 2,
		'messages' => [
			['from' => 1, 'at' => ago('-1 day -5 hours'), 'text' => ['en' => 'Hello, is the apartment still available? Could I see it this week?', 'sr' => 'Dobar dan, da li je stan još uvek dostupan? Mogu li da ga pogledam ove nedelje?']],
			['from' => 2, 'at' => ago('-1 day -4 hours'), 'text' => ['en' => 'Hello! Yes, it is. Thursday after 17:00 or Saturday morning works for me.', 'sr' => 'Dobar dan! Jeste. Odgovara mi četvrtak posle 17 h ili subota ujutru.']],
			['from' => 1, 'at' => ago('-1 day -3 hours'), 'text' => ['en' => 'Saturday at 10 would be great.', 'sr' => 'Subota u 10 bi bila odlična.']],
			['from' => 2, 'at' => ago('-40 minutes'), 'text' => ['en' => 'Great, see you on Saturday. The address is Bulevar cara Lazara, I’ll send the exact number.', 'sr' => 'Dogovoreno, vidimo se u subotu. Adresa je Bulevar cara Lazara, poslaću tačan broj.']],
			['from' => 2, 'at' => ago('-38 minutes'), 'text' => ['en' => 'Parking is in the yard.', 'sr' => 'Parking je u dvorištu.']],
		],
	],
	[
		'id' => 2, 'with' => 4, 'listing_id' => 1048, 'unread' => 1,
		'messages' => [
			['from' => 4, 'at' => ago('-3 hours'), 'text' => ['en' => 'Hi, is the price negotiable? I can come to Novi Sad this weekend.', 'sr' => 'Zdravo, da li je moguć dogovor oko cene? Mogu da dođem u Novi Sad za vikend.']],
			['from' => 1, 'at' => ago('-2 hours -50 minutes'), 'text' => ['en' => 'Hi, a little, after you see the car. Saturday works.', 'sr' => 'Zdravo, malo, kada pogledate auto. Subota odgovara.']],
			['from' => 4, 'at' => ago('-2 hours'), 'text' => ['en' => 'Can we also take it to a service for a check?', 'sr' => 'Možemo li da ga proverimo i u servisu?']],
		],
	],
	[
		'id' => 3, 'with' => 7, 'listing_id' => 1042, 'unread' => 0,
		'messages' => [
			['from' => 1, 'at' => ago('-1 day -1 hour'), 'text' => ['en' => 'Hi, how many battery cycles does it have?', 'sr' => 'Zdravo, koliko ciklusa ima baterija?']],
			['from' => 7, 'at' => ago('-1 day -50 minutes'), 'text' => ['en' => '112 cycles, battery health 94%.', 'sr' => '112 ciklusa, stanje baterije 94%.']],
			['from' => 1, 'at' => ago('-1 day -30 minutes'), 'text' => ['en' => 'Thanks, I’ll think about it.', 'sr' => 'Hvala, razmisliću.']],
		],
	],
	[
		'id' => 4, 'with' => 5, 'listing_id' => 1032, 'unread' => 0,
		'messages' => [
			['from' => 1, 'at' => ago('-5 days'), 'text' => ['en' => 'Is the table still available?', 'sr' => 'Da li je sto još uvek dostupan?']],
			['from' => 5, 'at' => ago('-4 days'), 'text' => ['en' => 'Yes, it is. You can see it any evening this week.', 'sr' => 'Jeste. Možete da ga pogledate bilo koje veče ove nedelje.']],
		],
	],
]];

// Reports (complaints about listings). status: open | review | resolved
$reports = [
	['id' => 307, 'listing_id' => 1031, 'reason' => 'Suspected fraud', 'reporter_id' => 2, 'comment' => 'Price is well below market and the seller asks for an advance payment.', 'created' => ago('-13 days'), 'status' => 'open'],
	['id' => 306, 'listing_id' => 1043, 'reason' => 'Item already sold', 'reporter_id' => 4, 'comment' => 'Called the seller, the sofa was sold last week.', 'created' => ago('-2 hours'), 'status' => 'open'],
	['id' => 305, 'listing_id' => 1046, 'reason' => 'Duplicate listing', 'reporter_id' => 6, 'comment' => 'Same phone posted twice under different titles.', 'created' => ago('-50 minutes'), 'status' => 'open'],
	['id' => 304, 'listing_id' => 1030, 'reason' => 'Inappropriate content', 'reporter_id' => 5, 'comment' => 'Phone number written in the title.', 'created' => ago('-13 days -5 hours'), 'status' => 'review'],
	['id' => 303, 'listing_id' => 1045, 'reason' => 'Wrong price', 'reporter_id' => 8, 'comment' => 'Price in the title differs from the description.', 'created' => ago('-1 hour'), 'status' => 'review'],
	['id' => 302, 'listing_id' => 1037, 'reason' => 'Item already sold', 'reporter_id' => 10, 'comment' => '', 'created' => ago('-6 days'), 'status' => 'resolved'],
	['id' => 301, 'listing_id' => 1048, 'reason' => 'Suspected fraud', 'reporter_id' => 7, 'comment' => 'Mileage looks edited on one of the photos.', 'created' => ago('-10 minutes'), 'status' => 'resolved'],
];

// Recent activity for the admin dashboard (becomes the audit log, log_action())
$activity = [
	['type' => 'listing', 'icon' => 'tag', 'time' => ago('-12 minutes'), 'text' => 'Marko Petrović posted “VW Golf 7 1.6 TDI, 2016”'],
	['type' => 'listing', 'icon' => 'tag', 'time' => ago('-25 minutes'), 'text' => 'Tijana Lukić posted “Samsung Galaxy S21, 128 GB”, waiting for moderation'],
	['type' => 'report', 'icon' => 'flag', 'time' => ago('-50 minutes'), 'text' => 'New report on “iPhone 13, 128 GB”: Duplicate listing'],
	['type' => 'user', 'icon' => 'user', 'time' => '2026-10-18 10:02', 'text' => 'New user registered: Tijana Lukić'],
	['type' => 'moderation', 'icon' => 'ban', 'time' => ago('-12 days -20 hours'), 'text' => 'Nikola Nikolić rejected “PlayStation 5, sealed”'],
	['type' => 'moderation', 'icon' => 'lock', 'time' => ago('-12 days -19 hours'), 'text' => 'Nenad Ristić was blocked by Nikola Nikolić'],
];

return [
	'categories' => $categories,
	'cities' => $cities,
	'languages' => $languages,
	'users' => $users,
	'listings' => $listings,
	'favorites' => $favorites,
	'saved_searches' => $savedSearches,
	'conversations' => $conversations,
	'reports' => $reports,
	'activity' => $activity,
];
