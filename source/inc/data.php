<?php
/* ==========================================================================
	Site mock data: users, listings, favorites, saved searches, conversations
	Categories, cities, languages: data-common.php.
	Dates are relative to "now" so the demo always looks fresh.
	User content has 'en' and 'sr' (Latin); Cyrillic is transliterated (tc()).
	========================================================================== */

require SOURCE_DIR . '/inc/data-common.php';

// Logged-in user (mock session)
const CURRENT_USER_ID = 1;

// EUR → RSD rate for price filters
const EUR_RATE = 117;

// Users
$users = [
	1 => ['id' => 1, 'name' => 'Marko Petrović', 'email' => 'marko.petrovic@example.com', 'phone' => '+381 63 000 1101', 'city' => 'Novi Sad', 'since' => '2025-03-14', 'verified' => true],
	2 => ['id' => 2, 'name' => 'Jelena Jovanović', 'email' => 'jelena.j@example.com', 'phone' => '+381 64 000 2202', 'city' => 'Novi Sad', 'since' => '2024-11-02', 'verified' => true],
	4 => ['id' => 4, 'name' => 'Ana Ilić', 'email' => 'ana.ilic@example.com', 'phone' => '+381 65 000 4404', 'city' => 'Niš', 'since' => '2026-01-19', 'verified' => false],
	5 => ['id' => 5, 'name' => 'Stefan Đorđević', 'email' => 'stefan.dj@example.com', 'phone' => '+381 62 000 5505', 'city' => 'Kragujevac', 'since' => '2025-07-08', 'verified' => true],
	6 => ['id' => 6, 'name' => 'Milica Pavlović', 'email' => 'milica.p@example.com', 'phone' => '+381 63 000 6606', 'city' => 'Subotica', 'since' => '2025-09-23', 'verified' => true],
	7 => ['id' => 7, 'name' => 'Luka Marković', 'email' => 'luka.markovic@example.com', 'phone' => '+381 64 000 7707', 'city' => 'Beograd', 'since' => '2025-02-11', 'verified' => true],
	8 => ['id' => 8, 'name' => 'Ivana Stojanović', 'email' => 'ivana.s@example.com', 'phone' => '+381 66 000 8808', 'city' => 'Čačak', 'since' => '2024-12-05', 'verified' => true],
	9 => ['id' => 9, 'name' => 'Dragan Kostić', 'email' => 'dragan.k@example.com', 'phone' => '+381 61 000 9909', 'city' => 'Zrenjanin', 'since' => '2026-09-30', 'verified' => false],
	10 => ['id' => 10, 'name' => 'Tijana Lukić', 'email' => 'tijana.lukic@example.com', 'phone' => '+381 69 000 1010', 'city' => 'Kraljevo', 'since' => '2026-10-18', 'verified' => false],
	11 => ['id' => 11, 'name' => 'Petar Simić', 'email' => 'petar.simic@example.com', 'phone' => '+381 60 000 1111', 'city' => 'Beograd', 'since' => '2023-05-20', 'verified' => true],
];

// Listings (newest first). status: active | pending | expired
$listings = [
	[
		'id' => 1050, 'category' => 'auto-parts', 'user_id' => 1, 'status' => 'pending',
		'title' => ['en' => 'Car roof box, 420 L', 'sr' => 'Krovni kofer, 420 l'],
		'price' => 9500, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Novi Sad',
		'created' => ago('-40 minutes'), 'views' => 0, 'messages' => 0, 'photos' => 3, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'capacity' => '420 l', 'color' => 'v.black'],
		'description' => ['en' => 'Universal roof box with keys and mounting kit. Fits most roof bars.', 'sr' => 'Univerzalni krovni kofer sa ključevima i nosačima. Odgovara većini krovnih šipki.'],
	],
	[
		'id' => 1048, 'category' => 'cars', 'user_id' => 1, 'status' => 'active',
		'title' => ['en' => 'VW Golf 7 1.6 TDI, 2016', 'sr' => 'VW Golf 7 1.6 TDI, 2016'],
		'price' => 9800, 'currency' => 'EUR', 'negotiable' => true, 'city' => 'Novi Sad',
		'created' => ago('-12 minutes'), 'views' => 214, 'messages' => 6, 'photos' => 8, 'condition' => 'used',
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
		'created' => ago('-35 minutes'), 'views' => 512, 'messages' => 9, 'photos' => 12, 'condition' => null,
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
		'created' => ago('-1 hour'), 'views' => 88, 'messages' => 3, 'photos' => 4, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'memory' => '128 GB', 'battery' => '87%', 'color' => 'v.black'],
		'description' => ['en' => 'No scratches on the screen, always used with a case. Box and cable included.', 'sr' => 'Ekran bez ogrebotina, uvek korišćen sa maskom. Uz telefon idu kutija i kabl.'],
	],
	[
		'id' => 1045, 'category' => 'bikes', 'user_id' => 4, 'status' => 'active',
		'title' => ['en' => 'City bike, 28 inch', 'sr' => 'Gradski bicikl, 28 inča'],
		'price' => 14500, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Niš',
		'created' => ago('-2 hours'), 'views' => 96, 'messages' => 2, 'photos' => 3, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'wheel' => '28"', 'frame' => '54 cm', 'gears' => '7'],
		'description' => ['en' => 'Comfortable city bike with a basket and lights. New brake pads.', 'sr' => 'Udoban gradski bicikl sa korpom i svetlima. Nove kočione pakne.'],
	],
	[
		'id' => 1044, 'category' => 'strollers', 'user_id' => 5, 'status' => 'active',
		'title' => ['en' => 'Baby stroller 3 in 1', 'sr' => 'Kolica za bebe 3 u 1'],
		'price' => 18000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Kragujevac',
		'created' => ago('-3 hours'), 'views' => 41, 'messages' => 1, 'photos' => 5, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'color' => 'v.grey', 'age' => '0–3'],
		'description' => ['en' => 'Carrycot, seat and car seat. Used for one child, washed and complete.', 'sr' => 'Korpa, sedište i auto-sedište. Korišćena za jedno dete, oprana i kompletna.'],
	],
	[
		'id' => 1043, 'category' => 'furniture', 'user_id' => 6, 'status' => 'active',
		'title' => ['en' => 'Corner sofa, grey', 'sr' => 'Ugaona garnitura, siva'],
		'price' => 35000, 'currency' => 'RSD', 'negotiable' => true, 'city' => 'Subotica',
		'created' => ago('-5 hours'), 'views' => 143, 'messages' => 4, 'photos' => 6, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'dimensions' => '260 × 180 cm', 'color' => 'v.grey', 'sleeping' => 'v.yes'],
		'description' => ['en' => 'Sofa bed with storage. Pick-up only, ground floor.', 'sr' => 'Garnitura na razvlačenje sa sandukom. Lično preuzimanje, prizemlje.'],
	],
	[
		'id' => 1042, 'category' => 'computers', 'user_id' => 7, 'status' => 'active',
		'title' => ['en' => 'MacBook Air M1, 8/256 GB', 'sr' => 'MacBook Air M1, 8/256 GB'],
		'price' => 85000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Beograd',
		'created' => ago('-1 day -2 hours'), 'views' => 301, 'messages' => 8, 'photos' => 5, 'condition' => 'like_new',
		'attrs' => ['condition' => 'v.like_new', 'ram' => '8 GB', 'ssd' => '256 GB', 'battery' => '94%'],
		'description' => ['en' => '112 battery cycles, original charger. Bought in Serbia, receipt available.', 'sr' => '112 ciklusa baterije, originalni punjač. Kupljen u Srbiji, postoji račun.'],
	],
	[
		'id' => 1041, 'category' => 'appliances', 'user_id' => 8, 'status' => 'active',
		'title' => ['en' => 'Washing machine Gorenje, 7 kg', 'sr' => 'Veš mašina Gorenje, 7 kg'],
		'price' => 22000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Čačak',
		'created' => ago('-1 day -5 hours'), 'views' => 77, 'messages' => 1, 'photos' => 2, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'capacity' => '7 kg', 'energy' => 'A+++'],
		'description' => ['en' => 'Works perfectly, selling because we are moving.', 'sr' => 'Radi besprekorno, prodajem zbog selidbe.'],
	],
	[
		'id' => 1040, 'category' => 'winter-sports', 'user_id' => 10, 'status' => 'active',
		'title' => ['en' => 'Ski set Atomic, 170 cm', 'sr' => 'Skijaški komplet Atomic, 170 cm'],
		'price' => 16000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Kraljevo',
		'created' => ago('-2 days'), 'views' => 52, 'messages' => 0, 'photos' => 3, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'length' => '170 cm'],
		'description' => ['en' => 'Skis with bindings, serviced last season. Ready for Kopaonik.', 'sr' => 'Skije sa vezovima, servisirane prošle sezone. Spremne za Kopaonik.'],
	],
	[
		'id' => 1039, 'category' => 'mens-clothing', 'user_id' => 10, 'status' => 'active',
		'title' => ['en' => 'Winter jacket, size M', 'sr' => 'Zimska jakna, veličina M'],
		'price' => 4500, 'currency' => 'RSD', 'negotiable' => true, 'city' => 'Kraljevo',
		'created' => ago('-2 days -4 hours'), 'views' => 23, 'messages' => 0, 'photos' => 2, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'size' => 'M', 'color' => 'v.blue'],
		'description' => ['en' => 'Warm down jacket, worn one season.', 'sr' => 'Topla pernata jakna, nošena jednu sezonu.'],
	],
	[
		'id' => 1038, 'category' => 'toys', 'user_id' => 2, 'status' => 'active',
		'title' => ['en' => 'Wooden toy kitchen', 'sr' => 'Drvena dečja kuhinja'],
		'price' => 6500, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Novi Sad',
		'created' => ago('-3 days'), 'views' => 64, 'messages' => 2, 'photos' => 4, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'material' => 'v.wood', 'age' => '3+'],
		'description' => ['en' => 'With pots and accessories. Smoke-free home.', 'sr' => 'Sa šerpama i dodacima. Kuća bez pušača.'],
	],
	[
		'id' => 1037, 'category' => 'auto-parts', 'user_id' => 1, 'status' => 'expired',
		'title' => ['en' => 'Winter tyres 205/55 R16', 'sr' => 'Zimske gume 205/55 R16'],
		'price' => 18000, 'currency' => 'RSD', 'negotiable' => true, 'city' => 'Novi Sad',
		'created' => ago('-70 days'), 'views' => 188, 'messages' => 5, 'photos' => 4, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used'],
		'description' => ['en' => 'Set of four, 6 mm tread, 2022.', 'sr' => 'Komplet od četiri, šara 6 mm, 2022. godište.'],
	],
	[
		'id' => 1036, 'category' => 'houses', 'user_id' => 2, 'status' => 'active',
		'title' => ['en' => 'House with a yard, Sremska Kamenica', 'sr' => 'Kuća sa dvorištem, Sremska Kamenica'],
		'price' => 210000, 'currency' => 'EUR', 'negotiable' => true, 'city' => 'Novi Sad',
		'created' => ago('-3 days -6 hours'), 'views' => 640, 'messages' => 12, 'photos' => 10, 'condition' => null,
		'attrs' => ['area' => '140 m²', 'plot' => '6 ari', 'rooms' => '4', 'heating' => 'v.gas', 'built' => '2008'],
		'description' => ['en' => 'Family house with a garage and fruit garden, 10 minutes from the city centre.', 'sr' => 'Porodična kuća sa garažom i voćnjakom, 10 minuta od centra grada.'],
	],
	[
		'id' => 1035, 'category' => 'services', 'user_id' => 11, 'status' => 'active',
		'title' => ['en' => 'Electrician, apartment wiring and repairs', 'sr' => 'Električar, elektroinstalacije i popravke'],
		'price' => null, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Beograd',
		'created' => ago('-4 days'), 'views' => 210, 'messages' => 7, 'photos' => 2, 'condition' => null,
		'attrs' => ['experience' => 'v.years_15', 'area_served' => 'v.beograd_all'],
		'description' => ['en' => 'Wiring, sockets, lighting, fuse boxes. Free estimate.', 'sr' => 'Instalacije, utičnice, rasveta, razvodne table. Besplatna procena.'],
	],
	[
		'id' => 1034, 'category' => 'motorcycles', 'user_id' => 4, 'status' => 'active',
		'title' => ['en' => 'Yamaha MT-07, 2019', 'sr' => 'Yamaha MT-07, 2019'],
		'price' => 6200, 'currency' => 'EUR', 'negotiable' => false, 'city' => 'Niš',
		'created' => ago('-4 days -3 hours'), 'views' => 175, 'messages' => 3, 'photos' => 6, 'condition' => 'used',
		'attrs' => ['year' => '2019', 'mileage' => '21 000 km', 'engine' => '689 cm³', 'body' => 'v.naked'],
		'description' => ['en' => 'Garage kept, regular service, new chain.', 'sr' => 'Garažiran, redovno servisiran, nov lanac.'],
	],
	[
		'id' => 1033, 'category' => 'consoles', 'user_id' => 9, 'status' => 'active',
		'title' => ['en' => 'PlayStation 5 with two controllers', 'sr' => 'PlayStation 5 sa dva džojstika'],
		'price' => 48000, 'currency' => 'RSD', 'negotiable' => false, 'city' => 'Zrenjanin',
		'created' => ago('-5 days'), 'views' => 58, 'messages' => 3, 'photos' => 3, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'memory' => '825 GB', 'controllers' => '2'],
		'description' => ['en' => 'Disc edition, two games included.', 'sr' => 'Verzija sa disk čitačem, uz dve igrice.'],
	],
	[
		'id' => 1032, 'category' => 'furniture', 'user_id' => 5, 'status' => 'active',
		'title' => ['en' => 'Dining table with 6 chairs', 'sr' => 'Trpezarijski sto sa 6 stolica'],
		'price' => 18500, 'currency' => 'RSD', 'negotiable' => true, 'city' => 'Kragujevac',
		'created' => ago('-6 days'), 'views' => 49, 'messages' => 2, 'photos' => 4, 'condition' => 'used',
		'attrs' => ['condition' => 'v.used', 'material' => 'v.oak', 'dimensions' => '180 × 90 cm'],
		'description' => ['en' => 'Solid oak table, chairs reupholstered last year.', 'sr' => 'Sto od punog hrasta, stolice presvučene prošle godine.'],
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

// Favorites of the current user (listing ids)
$favorites = [1047, 1042, 1036, 1034, 1043];

// Saved searches of the current user
$savedSearches = [
	['id' => 1, 'label' => ['en' => 'Apartments in Novi Sad up to 150 000 €', 'sr' => 'Stanovi u Novom Sadu do 150 000 €'], 'params' => ['cat' => 'apartments', 'city' => 'Novi Sad', 'price_to' => 150000, 'currency' => 'EUR'], 'new' => 3, 'alerts' => true],
	['id' => 2, 'label' => ['en' => 'MacBook in Beograd', 'sr' => 'MacBook u Beogradu'], 'params' => ['q' => 'MacBook', 'city' => 'Beograd'], 'new' => 1, 'alerts' => true],
	['id' => 3, 'label' => ['en' => 'Bikes in Niš', 'sr' => 'Bicikli u Nišu'], 'params' => ['cat' => 'bikes', 'city' => 'Niš'], 'new' => 0, 'alerts' => false],
];

// Conversations of the current user. 'from' = user id (CURRENT_USER_ID = me)
$conversations = [
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
];
