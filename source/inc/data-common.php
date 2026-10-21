<?php
/* ==========================================================================
	Shared mock data: categories, cities, languages (site + admin)
	Replace with database queries when the backend is ready.
	========================================================================== */

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
