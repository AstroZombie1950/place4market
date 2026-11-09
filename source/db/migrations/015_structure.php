<?php
/* ==========================================================================
	015_structure: the site structure of stage 6 (doc/semantics/structure.md,
	approved 09.11). Data generated once from source/db/taxonomy.php.
	- categories: 20 sections, 163 subcategories. The 27 existing rows keep their
	  ids (listings stay where they are): home-garden → Nameštaj i pokućstvo,
	  jobs-services → Posao, furniture → Sedeće garniture, appliances → Mašine za
	  veš, services → Ostale usluge, jobs → Ostali poslovi, computers → Desktop
	  računari, auto-parts → Auto delovi. Categories added in the admin are kept
	  but switched off (address words "old-<id>"); former address words are
	  forgotten: before the launch nothing links to them.
	  h1_* (heading) and text_* (SEO text under the listings): empty = defaults.
	- cities: address words (slug: Serbian, slug_en: only where English differs),
	  kind (city | resort | abroad: one place "Inostranstvo" for stays and jobs abroad), main_rank (1–40: the main places of filters, the home
	  page and landing pages); all municipalities of Serbia + Kosovo and Metohija.
	- districts: neighbourhoods of Beograd and Novi Sad (real estate).
	- listings: district_id, deal (real estate), item_type and brand (keys of
	  source/inc/taxonomy.php), seller_type (private | business).
	The previous code keeps working: new columns are nullable, ids stay.
	========================================================================== */

return <<<'SQL'
ALTER TABLE categories
	ADD COLUMN h1_en VARCHAR(100) NULL AFTER meta_sr,
	ADD COLUMN h1_sr VARCHAR(100) NULL AFTER h1_en,
	ADD COLUMN text_en TEXT NULL AFTER h1_sr,
	ADD COLUMN text_sr TEXT NULL AFTER text_en;

-- Free the address words first: they move between rows
UPDATE categories SET slug = CONCAT('old-', id), slug_sr = CONCAT('old-', id);
UPDATE categories SET active = 0, parent_id = NULL WHERE id NOT IN (1, 11, 101, 13, 12, 102, 103, 104, 105, 106, 2, 21, 22, 107, 23, 108, 109, 110, 7, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 72, 89, 122, 123, 124, 125, 126, 127, 128, 129, 130, 131, 132, 71, 3, 31, 33, 133, 134, 135, 136, 137, 32, 138, 139, 140, 141, 90, 142, 143, 144, 145, 146, 147, 148, 149, 150, 42, 4, 151, 152, 153, 154, 41, 155, 156, 157, 158, 159, 92, 160, 161, 162, 163, 164, 165, 166, 167, 168, 169, 170, 171, 172, 93, 173, 174, 175, 176, 177, 178, 94, 179, 180, 181, 182, 183, 184, 185, 186, 5, 187, 51, 188, 189, 190, 52, 191, 192, 193, 194, 195, 196, 6, 62, 197, 61, 198, 199, 200, 201, 202, 203, 204, 8, 81, 205, 82, 206, 207, 208, 209, 210, 211, 212, 96, 213, 214, 215, 216, 95, 217, 218, 219, 220, 221, 222, 91, 223, 224, 225, 226, 227, 97, 228, 229, 230, 231, 232, 233, 234, 98, 235, 236, 237, 238, 99, 239, 240, 241, 242, 100, 243, 244);
DELETE FROM category_old_slugs;

INSERT INTO categories (id, parent_id, slug, slug_sr, icon, name_en, name_sr_latn, name_sr_cyrl, h1_sr, h1_en, sort) VALUES
	(1, NULL, 'vehicles', 'vozila', 'car', 'Vehicles', 'Vozila', 'Возила', 'Vozila', 'Vehicles', 1),
	(11, 1, 'cars', 'automobili', NULL, 'Cars', 'Automobili', 'Аутомобили', 'Polovni automobili', 'Cars', 1),
	(101, 1, 'tyres-wheels', 'gume-i-felne', NULL, 'Tyres & wheels', 'Gume i felne', 'Гуме и фелне', 'Gume i felne', 'Tyres & wheels', 2),
	(13, 1, 'car-parts', 'auto-delovi', NULL, 'Car parts', 'Auto delovi', 'Ауто делови', 'Auto delovi', 'Car parts', 3),
	(12, 1, 'motorcycles', 'motocikli', NULL, 'Motorcycles, scooters & quads', 'Motocikli, skuteri i kvadovi', 'Мотоцикли, скутери и квадови', 'Polovni motocikli i skuteri', 'Motorcycles, scooters & quads', 4),
	(102, 1, 'trailers', 'prikolice', NULL, 'Trailers & caravans', 'Prikolice i kamp prikolice', 'Приколице и камп приколице', 'Prikolice i kamp prikolice', 'Trailers & caravans', 5),
	(103, 1, 'trucks-vans', 'kamioni-i-kombiji', NULL, 'Trucks & vans', 'Kamioni, kombiji i radna vozila', 'Камиони, комбији и радна возила', 'Polovni kamioni i kombiji', 'Trucks & vans', 6),
	(104, 1, 'car-accessories', 'auto-oprema', NULL, 'Car accessories', 'Auto oprema i kozmetika', 'Ауто опрема и козметика', 'Auto oprema', 'Car accessories', 7),
	(105, 1, 'boats', 'camci', NULL, 'Boats', 'Čamci i plovila', 'Чамци и пловила', 'Polovni čamci i plovila', 'Boats', 8),
	(106, 1, 'motorcycle-gear', 'moto-oprema', NULL, 'Motorcycle gear & helmets', 'Moto oprema i kacige', 'Мото опрема и кациге', 'Moto oprema i kacige', 'Motorcycle gear & helmets', 9),
	(2, NULL, 'real-estate', 'nekretnine', 'home', 'Real estate', 'Nekretnine', 'Некретнине', 'Nekretnine', 'Real estate', 2),
	(21, 2, 'apartments', 'stanovi', NULL, 'Apartments', 'Stanovi', 'Станови', 'Stanovi', 'Apartments', 1),
	(22, 2, 'houses', 'kuce', NULL, 'Houses', 'Kuće', 'Куће', 'Kuće', 'Houses', 2),
	(107, 2, 'cottages', 'vikendice', NULL, 'Cottages', 'Vikendice', 'Викендице', 'Vikendice', 'Cottages', 3),
	(23, 2, 'land', 'placevi', NULL, 'Land', 'Placevi i zemljište', 'Плацеви и земљиште', 'Placevi i zemljište', 'Land', 4),
	(108, 2, 'commercial', 'poslovni-prostor', NULL, 'Commercial property', 'Poslovni prostor', 'Пословни простор', 'Poslovni prostor i lokali', 'Commercial property', 5),
	(109, 2, 'rooms', 'sobe', NULL, 'Rooms & flatmates', 'Sobe i cimeri', 'Собе и цимери', 'Sobe za izdavanje', 'Rooms & flatmates', 6),
	(110, 2, 'garages', 'garaze', NULL, 'Garages & parking', 'Garaže i parking mesta', 'Гараже и паркинг места', 'Garaže i parking mesta', 'Garages & parking', 7),
	(7, NULL, 'jobs', 'posao', 'briefcase', 'Jobs', 'Posao', 'Посао', 'Oglasi za posao', 'Jobs', 3),
	(111, 7, 'remote-jobs', 'rad-od-kuce', NULL, 'Remote & online', 'Rad od kuće i online', 'Рад од куће и онлине', 'Posao od kuće', 'Remote & online', 1),
	(112, 7, 'drivers-delivery', 'vozaci-i-dostava', NULL, 'Drivers & delivery', 'Vozači, dostava i logistika', 'Возачи, достава и логистика', 'Posao vozača i dostavljača', 'Drivers & delivery', 2),
	(113, 7, 'jobs-abroad', 'posao-u-inostranstvu', NULL, 'Jobs abroad', 'Posao u inostranstvu', 'Посао у иностранству', 'Posao u inostranstvu', 'Jobs abroad', 3),
	(114, 7, 'student-jobs', 'studentski-poslovi', NULL, 'Student & part-time jobs', 'Studentski i honorarni poslovi', 'Студентски и хонорарни послови', 'Studentski i honorarni poslovi', 'Student & part-time jobs', 4),
	(115, 7, 'hospitality', 'ugostiteljstvo', NULL, 'Hospitality', 'Ugostiteljstvo i turizam', 'Угоститељство и туризам', 'Posao u ugostiteljstvu', 'Hospitality', 5),
	(116, 7, 'construction-jobs', 'gradjevina-i-zanati', NULL, 'Construction & trades', 'Građevina i zanati', 'Грађевина и занати', 'Posao u građevini', 'Construction & trades', 6),
	(117, 7, 'production-jobs', 'proizvodnja-i-magacin', NULL, 'Production, warehouse & security', 'Proizvodnja, magacin i obezbeđenje', 'Производња, магацин и обезбеђење', 'Posao u proizvodnji i obezbeđenju', 'Production, warehouse & security', 7),
	(118, 7, 'care-jobs', 'zdravstvo-i-nega', NULL, 'Healthcare, care & cleaning', 'Zdravstvo, nega i čišćenje', 'Здравство, нега и чишћење', 'Posao u zdravstvu i nezi', 'Healthcare, care & cleaning', 8),
	(119, 7, 'sales-jobs', 'trgovina-i-prodaja', NULL, 'Retail & sales', 'Trgovina i prodaja', 'Трговина и продаја', 'Posao prodavca', 'Retail & sales', 9),
	(120, 7, 'office-jobs', 'it-i-administracija', NULL, 'IT, office & finance', 'IT, administracija i finansije', 'IT, администрација и финансије', 'Posao u IT-u i administraciji', 'IT, office & finance', 10),
	(121, 7, 'beauty-jobs', 'frizeri-i-kozmeticari', NULL, 'Hairdressers & beauticians', 'Frizeri, kozmetičari i maseri', 'Фризери, козметичари и масери', 'Posao frizera i kozmetičara', 'Hairdressers & beauticians', 11),
	(72, 7, 'other-jobs', 'ostali-poslovi', NULL, 'Other jobs', 'Ostali poslovi', 'Остали послови', 'Ostali poslovi', 'Other jobs', 12),
	(89, NULL, 'services', 'usluge', 'wrench', 'Services', 'Usluge', 'Услуге', 'Usluge', 'Services', 4),
	(122, 89, 'car-service', 'auto-servisi', NULL, 'Car service & tyre repair', 'Auto servisi i vulkanizeri', 'Ауто сервиси и вулканизери', 'Auto servisi i vulkanizeri', 'Car service & tyre repair', 1),
	(123, 89, 'car-rental', 'rent-a-car', NULL, 'Car rental', 'Rent a car', 'Рент а кар', 'Rent a car', 'Car rental', 2),
	(124, 89, 'events', 'proslave', NULL, 'Events, catering, photo & music', 'Proslave, ketering, foto i muzika', 'Прославе, кетеринг, фото и музика', 'Ketering, muzika i fotografi za proslave', 'Events, catering, photo & music', 3),
	(125, 89, 'beauty-services', 'lepota-i-nega', NULL, 'Beauty, care & babysitting', 'Lepota, nega i čuvanje', 'Лепота, нега и чување', 'Frizeri, šminka i nega', 'Beauty, care & babysitting', 4),
	(126, 89, 'tradesmen', 'majstori', NULL, 'Tradesmen & building work', 'Majstori i građevinske usluge', 'Мајстори и грађевинске услуге', 'Majstori i građevinske usluge', 'Tradesmen & building work', 5),
	(127, 89, 'lessons', 'casovi-i-kursevi', NULL, 'Lessons & courses', 'Časovi i kursevi', 'Часови и курсеви', 'Privatni časovi i kursevi', 'Lessons & courses', 6),
	(128, 89, 'towing', 'slep-sluzba', NULL, 'Towing', 'Šlep služba', 'Шлеп служба', 'Šlep služba', 'Towing', 7),
	(129, 89, 'repairs', 'servis-uredjaja', NULL, 'Appliance & device repair', 'Servis i popravka uređaja', 'Сервис и поправка уређаја', 'Servis i popravka uređaja', 'Appliance & device repair', 8),
	(130, 89, 'business-services', 'it-i-poslovne-usluge', NULL, 'IT, design & business', 'IT, dizajn i poslovne usluge', 'IT, дизајн и пословне услуге', 'Izrada sajtova i poslovne usluge', 'IT, design & business', 9),
	(131, 89, 'moving', 'selidbe-i-prevoz', NULL, 'Moving & transport', 'Selidbe i prevoz', 'Селидбе и превоз', 'Selidbe i prevoz', 'Moving & transport', 10),
	(132, 89, 'cleaning', 'ciscenje', NULL, 'Cleaning & upkeep', 'Čišćenje i održavanje', 'Чишћење и одржавање', 'Čišćenje i održavanje', 'Cleaning & upkeep', 11),
	(71, 89, 'other-services', 'ostale-usluge', NULL, 'Other services', 'Ostale usluge', 'Остале услуге', 'Ostale usluge', 'Other services', 12),
	(3, NULL, 'electronics', 'elektronika', 'laptop', 'Electronics', 'Elektronika', 'Електроника', 'Elektronika', 'Electronics', 5),
	(31, 3, 'phones', 'mobilni-telefoni', NULL, 'Mobile phones', 'Mobilni telefoni', 'Мобилни телефони', 'Mobilni telefoni', 'Mobile phones', 1),
	(33, 3, 'consoles', 'konzole-i-igrice', NULL, 'Consoles & video games', 'Konzole i video igre', 'Конзоле и видео игре', 'Konzole i video igre', 'Consoles & video games', 2),
	(133, 3, 'tv', 'televizori', NULL, 'TV & video', 'Televizori i video', 'Телевизори и видео', 'Televizori', 'TV & video', 3),
	(134, 3, 'audio', 'audio', NULL, 'Audio & headphones', 'Audio oprema i slušalice', 'Аудио опрема и слушалице', 'Slušalice, zvučnici i audio oprema', 'Audio & headphones', 4),
	(135, 3, 'peripherals', 'periferija', NULL, 'Monitors, printers & peripherals', 'Monitori, štampači i periferija', 'Монитори, штампачи и периферија', 'Monitori, štampači i tastature', 'Monitors, printers & peripherals', 5),
	(136, 3, 'components', 'komponente', NULL, 'Computer components', 'Komponente za računare', 'Компоненте за рачунаре', 'Komponente za računare', 'Computer components', 6),
	(137, 3, 'phone-accessories', 'oprema-za-telefone', NULL, 'Phone accessories', 'Oprema za telefone', 'Опрема за телефоне', 'Oprema za mobilne telefone', 'Phone accessories', 7),
	(32, 3, 'desktops', 'racunari', NULL, 'Desktop computers', 'Desktop računari', 'Десктоп рачунари', 'Desktop računari', 'Desktop computers', 8),
	(138, 3, 'laptops', 'laptopovi', NULL, 'Laptops', 'Laptopovi', 'Лаптопови', 'Polovni laptopovi', 'Laptops', 9),
	(139, 3, 'cameras', 'foto-i-video', NULL, 'Cameras & drones', 'Foto i video oprema', 'Фото и видео опрема', 'Fotoaparati, kamere i dronovi', 'Cameras & drones', 10),
	(140, 3, 'smartwatches', 'pametni-satovi', NULL, 'Smartwatches & bands', 'Pametni satovi i narukvice', 'Паметни сатови и наруквице', 'Pametni satovi', 'Smartwatches & bands', 11),
	(141, 3, 'tablets', 'tableti', NULL, 'Tablets & e-readers', 'Tableti i e-čitači', 'Таблети и е-читачи', 'Polovni tableti', 'Tablets & e-readers', 12),
	(90, NULL, 'appliances', 'kucni-aparati', 'washer', 'Home appliances', 'Kućni aparati', 'Кућни апарати', 'Bela tehnika i kućni aparati', 'Home appliances', 6),
	(142, 90, 'vacuums', 'usisivaci', NULL, 'Vacuums & steam cleaners', 'Usisivači i paročistači', 'Усисивачи и парочистачи', 'Usisivači i paročistači', 'Vacuums & steam cleaners', 1),
	(143, 90, 'small-appliances', 'mali-kucni-aparati', NULL, 'Small appliances', 'Mali kućni aparati', 'Мали кућни апарати', 'Mali kućni aparati', 'Small appliances', 2),
	(144, 90, 'coffee-machines', 'aparati-za-kafu', NULL, 'Coffee machines', 'Aparati za kafu', 'Апарати за кафу', 'Aparati za kafu', 'Coffee machines', 3),
	(145, 90, 'cookers', 'sporeti', NULL, 'Cookers, ovens & microwaves', 'Šporeti, rerne i mikrotalasne', 'Шпорети, рерне и микроталасне', 'Šporeti i mikrotalasne', 'Cookers, ovens & microwaves', 4),
	(146, 90, 'air-conditioners', 'klima-uredjaji', NULL, 'Air conditioners', 'Klima uređaji', 'Клима уређаји', 'Klima uređaji', 'Air conditioners', 5),
	(147, 90, 'heaters', 'grejalice', NULL, 'Heaters & radiators', 'Grejalice i radijatori', 'Грејалице и радијатори', 'Grejalice, norveški i uljani radijatori', 'Heaters & radiators', 6),
	(148, 90, 'water-heaters', 'bojleri', NULL, 'Water heaters', 'Bojleri', 'Бојлери', 'Bojleri', 'Water heaters', 7),
	(149, 90, 'dishwashers', 'masine-za-sudove', NULL, 'Dishwashers', 'Mašine za sudove', 'Машине за судове', 'Mašine za sudove', 'Dishwashers', 8),
	(150, 90, 'fridges', 'frizideri', NULL, 'Fridges & freezers', 'Frižideri i zamrzivači', 'Фрижидери и замрзивачи', 'Frižideri i zamrzivači', 'Fridges & freezers', 9),
	(42, 90, 'washing-machines', 'ves-masine', NULL, 'Washing machines & dryers', 'Mašine za veš i sušilice', 'Машине за веш и сушилице', 'Mašine za veš', 'Washing machines & dryers', 10),
	(4, NULL, 'furniture', 'namestaj', 'sofa', 'Furniture & home', 'Nameštaj i pokućstvo', 'Намештај и покућство', 'Polovni nameštaj', 'Furniture & home', 7),
	(151, 4, 'lighting', 'rasveta', NULL, 'Lighting', 'Rasveta', 'Расвета', 'Lusteri, lampe i rasveta', 'Lighting', 1),
	(152, 4, 'beds', 'kreveti-i-duseci', NULL, 'Beds & mattresses', 'Kreveti i dušeci', 'Кревети и душеци', 'Kreveti i dušeci', 'Beds & mattresses', 2),
	(153, 4, 'textiles', 'tepisi-i-tekstil', NULL, 'Rugs, curtains & bedding', 'Tepisi, zavese i posteljina', 'Теписи, завесе и постељина', 'Tepisi, zavese i posteljina', 'Rugs, curtains & bedding', 3),
	(154, 4, 'tables-chairs', 'stolovi-i-stolice', NULL, 'Tables & chairs', 'Stolovi i stolice', 'Столови и столице', 'Stolovi i stolice', 'Tables & chairs', 4),
	(41, 4, 'sofas', 'garniture', NULL, 'Sofas & armchairs', 'Sedeće garniture, kauči i fotelje', 'Седеће гарнитуре, каучи и фотеље', 'Ugaone garniture, kauči i fotelje', 'Sofas & armchairs', 5),
	(155, 4, 'garden-furniture', 'bastenski-namestaj', NULL, 'Garden furniture', 'Baštenski nameštaj', 'Баштенски намештај', 'Baštenski nameštaj', 'Garden furniture', 6),
	(156, 4, 'wardrobes', 'ormari-i-komode', NULL, 'Wardrobes & dressers', 'Ormari, plakari i komode', 'Ормари, плакари и комоде', 'Ormari i plakari', 'Wardrobes & dressers', 7),
	(157, 4, 'kitchenware', 'posudje', NULL, 'Kitchenware', 'Posuđe i kuhinjski pribor', 'Посуђе и кухињски прибор', 'Posuđe i escajg', 'Kitchenware', 8),
	(158, 4, 'kitchens', 'kuhinje', NULL, 'Kitchens', 'Kuhinje i kuhinjski elementi', 'Кухиње и кухињски елементи', 'Polovne kuhinje', 'Kitchens', 9),
	(159, 4, 'decor', 'dekoracija', NULL, 'Decor & mirrors', 'Dekoracija i ogledala', 'Декорација и огледала', 'Dekoracija za stan', 'Decor & mirrors', 10),
	(92, NULL, 'construction', 'gradjevinarstvo', 'brick', 'Building & renovation', 'Građevinarstvo', 'Грађевинарство', 'Građevinski materijal i oprema', 'Building & renovation', 8),
	(160, 92, 'doors', 'vrata', NULL, 'Doors & shutters', 'Vrata, roletne i komarnici', 'Врата, ролетне и комарници', 'Sobna i ulazna vrata', 'Doors & shutters', 1),
	(161, 92, 'heating', 'grejanje', NULL, 'Stoves, boilers & radiators', 'Peći, kotlovi i radijatori', 'Пећи, котлови и радијатори', 'Kotlovi, peći i radijatori', 'Stoves, boilers & radiators', 2),
	(162, 92, 'windows', 'stolarija', NULL, 'Windows & joinery', 'PVC i ALU stolarija', 'PVC и ALU столарија', 'PVC stolarija i prozori', 'Windows & joinery', 3),
	(163, 92, 'insulation', 'izolacija-i-fasada', NULL, 'Insulation, facade & drywall', 'Izolacija, fasada i gips', 'Изолација, фасада и гипс', 'Stiropor, kamena vuna i fasada', 'Insulation, facade & drywall', 4),
	(164, 92, 'fences', 'ograde', NULL, 'Fences & gates', 'Ograde i kapije', 'Ограде и капије', 'Ograde i kapije za dvorište', 'Fences & gates', 5),
	(165, 92, 'bathroom', 'kupatilo', NULL, 'Bathroom & sanitary ware', 'Kupatilo i sanitarije', 'Купатило и санитарије', 'Tuš kabine, kade i sanitarije', 'Bathroom & sanitary ware', 6),
	(166, 92, 'prefab', 'montazne-kuce', NULL, 'Prefab houses & containers', 'Montažne kuće i kontejneri', 'Монтажне куће и контејнери', 'Montažne kuće', 'Prefab houses & containers', 7),
	(167, 92, 'flooring', 'podne-obloge', NULL, 'Flooring', 'Laminat, parket i podne obloge', 'Ламинат, паркет и подне облоге', 'Laminat i parket', 'Flooring', 8),
	(168, 92, 'tiles', 'keramika', NULL, 'Tiles', 'Keramika i pločice', 'Керамика и плочице', 'Pločice i keramika', 'Tiles', 9),
	(169, 92, 'firewood', 'ogrev', NULL, 'Firewood & pellets', 'Ogrev: drva, pelet, ugalj', 'Огрев: дрва, пелет, угаљ', 'Pelet i drva za ogrev', 'Firewood & pellets', 10),
	(170, 92, 'materials', 'gradjevinski-materijal', NULL, 'Building materials & roofing', 'Građevinski materijal i krov', 'Грађевински материјал и кров', 'Građevinski materijal', 'Building materials & roofing', 11),
	(171, 92, 'solar', 'solarni-paneli', NULL, 'Solar panels & electrical', 'Solarni paneli i elektromaterijal', 'Соларни панели и електроматеријал', 'Solarni paneli', 'Solar panels & electrical', 12),
	(172, 92, 'security', 'video-nadzor', NULL, 'CCTV & alarms', 'Video nadzor i alarmi', 'Видео надзор и аларми', 'Video nadzor i alarmi', 'CCTV & alarms', 13),
	(93, NULL, 'tools', 'masine-i-alati', 'drill', 'Machines & tools', 'Mašine i alati', 'Машине и алати', 'Mašine i alati', 'Machines & tools', 9),
	(173, 93, 'power-tools', 'alati', NULL, 'Hand & power tools', 'Ručni i električni alati', 'Ручни и електрични алати', 'Alati', 'Hand & power tools', 1),
	(174, 93, 'chainsaws', 'motorne-testere', NULL, 'Chainsaws', 'Motorne testere', 'Моторне тестере', 'Motorne testere', 'Chainsaws', 2),
	(175, 93, 'generators-pumps', 'agregati-i-pumpe', NULL, 'Generators, pumps & compressors', 'Agregati, pumpe i kompresori', 'Агрегати, пумпе и компресори', 'Agregati i pumpe za vodu', 'Generators, pumps & compressors', 3),
	(176, 93, 'mowers', 'kosilice-i-trimeri', NULL, 'Mowers & garden tools', 'Kosilice, trimeri i baštenski alati', 'Косилице, тримери и баштенски алати', 'Kosilice i trimeri', 'Mowers & garden tools', 4),
	(177, 93, 'machinery', 'gradjevinske-masine', NULL, 'Construction & industrial machinery', 'Građevinske i industrijske mašine', 'Грађевинске и индустријске машине', 'Građevinske mašine', 'Construction & industrial machinery', 5),
	(178, 93, 'catering-equipment', 'ugostiteljska-oprema', NULL, 'Catering & shop equipment', 'Ugostiteljska i trgovinska oprema', 'Угоститељска и трговинска опрема', 'Ugostiteljska oprema', 'Catering & shop equipment', 6),
	(94, NULL, 'agriculture', 'poljoprivreda', 'tractor', 'Agriculture', 'Poljoprivreda', 'Пољопривреда', 'Poljoprivreda', 'Agriculture', 10),
	(179, 94, 'tractors', 'traktori', NULL, 'Tractors', 'Traktori', 'Трактори', 'Polovni traktori', 'Tractors', 1),
	(180, 94, 'plants', 'sadnice', NULL, 'Seeds, seedlings & flowers', 'Seme, sadnice i cveće', 'Семе, саднице и цвеће', 'Sadnice i rasad', 'Seeds, seedlings & flowers', 2),
	(181, 94, 'livestock', 'domace-zivotinje', NULL, 'Livestock', 'Domaće životinje', 'Домаће животиње', 'Domaće životinje na prodaju', 'Livestock', 3),
	(182, 94, 'farm-machinery', 'poljoprivredne-masine', NULL, 'Farm machinery', 'Poljoprivredne mašine i priključci', 'Пољопривредне машине и прикључци', 'Poljoprivredne mašine', 'Farm machinery', 4),
	(183, 94, 'beekeeping', 'pcelarstvo', NULL, 'Beekeeping', 'Pčelarstvo', 'Пчеларство', 'Pčele i košnice', 'Beekeeping', 5),
	(184, 94, 'feed', 'stocna-hrana', NULL, 'Animal feed & crops', 'Stočna hrana i ratarski proizvodi', 'Сточна храна и ратарски производи', 'Seno, kukuruz i stočna hrana', 'Animal feed & crops', 6),
	(185, 94, 'farm-produce', 'domaci-proizvodi', NULL, 'Farm produce', 'Domaći proizvodi', 'Домаћи производи', 'Domaća rakija, med i ajvar', 'Farm produce', 7),
	(186, 94, 'farm-equipment', 'poljoprivredna-oprema', NULL, 'Farm equipment & irrigation', 'Poljoprivredna oprema i navodnjavanje', 'Пољопривредна опрема и наводњавање', 'Oprema za navodnjavanje', 'Farm equipment & irrigation', 8),
	(5, NULL, 'fashion', 'moda', 'shirt', 'Fashion', 'Moda', 'Мода', 'Odeća i obuća', 'Fashion', 11),
	(187, 5, 'womens-shoes', 'zenska-obuca', NULL, 'Women''s shoes', 'Ženska obuća', 'Женска обућа', 'Ženska obuća i patike', 'Women''s shoes', 1),
	(51, 5, 'womens-clothing', 'zenska-odeca', NULL, 'Women''s clothing', 'Ženska odeća', 'Женска одећа', 'Ženska odeća', 'Women''s clothing', 2),
	(188, 5, 'watches', 'satovi', NULL, 'Watches', 'Satovi', 'Сатови', 'Satovi', 'Watches', 3),
	(189, 5, 'bags', 'torbe', NULL, 'Bags, wallets & belts', 'Torbe, novčanici i kaiševi', 'Торбе, новчаници и каишеви', 'Torbe i novčanici', 'Bags, wallets & belts', 4),
	(190, 5, 'mens-shoes', 'muska-obuca', NULL, 'Men''s shoes', 'Muška obuća', 'Мушка обућа', 'Muška obuća i patike', 'Men''s shoes', 5),
	(52, 5, 'mens-clothing', 'muska-odeca', NULL, 'Men''s clothing', 'Muška odeća', 'Мушка одећа', 'Muška odeća', 'Men''s clothing', 6),
	(191, 5, 'wedding', 'vencanice', NULL, 'Wedding & formal wear', 'Venčanice i svečana odeća', 'Венчанице и свечана одећа', 'Venčanice i svečane haljine', 'Wedding & formal wear', 7),
	(192, 5, 'eyewear', 'naocare', NULL, 'Eyewear', 'Naočare', 'Наочаре', 'Naočare za sunce', 'Eyewear', 8),
	(193, 5, 'jewelry', 'nakit', NULL, 'Jewellery', 'Nakit', 'Накит', 'Nakit', 'Jewellery', 9),
	(194, 5, 'sports-shoes', 'sportska-obuca', NULL, 'Sportswear', 'Sportska odeća i obuća', 'Спортска одећа и обућа', 'Patike za trčanje i košarku', 'Sportswear', 10),
	(195, 5, 'workwear', 'radna-odeca', NULL, 'Workwear & uniforms', 'Radna odeća i uniforme', 'Радна одећа и униформе', 'Radna odeća i cipele', 'Workwear & uniforms', 11),
	(196, 5, 'costumes', 'kostimi', NULL, 'Masks & costumes', 'Maske i kostimi', 'Маске и костими', 'Kostimi za maskenbal', 'Masks & costumes', 12),
	(6, NULL, 'kids', 'deca', 'stroller', 'Kids', 'Deca', 'Деца', 'Sve za decu', 'Kids', 12),
	(62, 6, 'toys', 'igracke', NULL, 'Toys', 'Igračke', 'Играчке', 'Igračke', 'Toys', 1),
	(197, 6, 'kids-furniture', 'decji-namestaj', NULL, 'Cots & kids furniture', 'Krevetci i nameštaj za decu', 'Креветци и намештај за децу', 'Krevetci za bebe i dečiji kreveti', 'Cots & kids furniture', 2),
	(61, 6, 'strollers', 'kolica', NULL, 'Strollers', 'Kolica', 'Колица', 'Kolica za bebe', 'Strollers', 3),
	(198, 6, 'kids-shoes', 'obuca-za-decu', NULL, 'Kids'' shoes', 'Obuća za decu', 'Обућа за децу', 'Dečije patike i obuća', 'Kids'' shoes', 4),
	(199, 6, 'kids-clothing', 'odeca-za-decu', NULL, 'Kids'' clothing', 'Odeća za decu', 'Одећа за децу', 'Odeća za decu', 'Kids'' clothing', 5),
	(200, 6, 'kids-bikes', 'decji-bicikli', NULL, 'Kids bikes & scooters', 'Bicikli i trotineti za decu', 'Бицикли и тротинети за децу', 'Dečiji bicikli i trotineti', 'Kids bikes & scooters', 6),
	(201, 6, 'baby-gear', 'oprema-za-bebe', NULL, 'Baby gear', 'Oprema za bebe', 'Опрема за бебе', 'Oprema za bebe', 'Baby gear', 7),
	(202, 6, 'car-seats', 'auto-sedista', NULL, 'Car seats & carriers', 'Auto sedišta i nosiljke', 'Ауто седишта и носиљке', 'Auto sedišta za decu', 'Car seats & carriers', 8),
	(203, 6, 'school-supplies', 'skolski-pribor', NULL, 'School supplies', 'Školski pribor i rančevi', 'Школски прибор и ранчеви', 'Školski pribor i rančevi', 'School supplies', 9),
	(204, 6, 'kids-other', 'ostalo-za-decu', NULL, 'Other for kids', 'Ostalo za decu', 'Остало за децу', 'Ostalo za decu', 'Other for kids', 10),
	(8, NULL, 'sports', 'sport', 'ball', 'Sports & outdoors', 'Sport i rekreacija', 'Спорт и рекреација', 'Sportska oprema', 'Sports & outdoors', 13),
	(81, 8, 'bikes', 'bicikli', NULL, 'Bikes', 'Bicikli', 'Бицикли', 'Bicikli', 'Bikes', 1),
	(205, 8, 'fitness', 'fitnes', NULL, 'Fitness & gym', 'Fitnes i teretana', 'Фитнес и теретана', 'Fitnes oprema', 'Fitness & gym', 2),
	(82, 8, 'winter-sports', 'zimski-sportovi', NULL, 'Winter sports', 'Zimski sportovi', 'Зимски спортови', 'Ski oprema', 'Winter sports', 3),
	(206, 8, 'fishing', 'ribolov', NULL, 'Fishing', 'Ribolov', 'Риболов', 'Oprema za ribolov', 'Fishing', 4),
	(207, 8, 'e-scooters', 'elektricni-trotineti', NULL, 'E-scooters', 'Električni trotineti', 'Електрични тротинети', 'Električni trotineti', 'E-scooters', 5),
	(208, 8, 'skating', 'roleri-i-skejt', NULL, 'Skates & other sports', 'Roleri, skejt i ostali sportovi', 'Ролери, скејт и остали спортови', 'Roleri i skejtbord', 'Skates & other sports', 6),
	(209, 8, 'water-sports', 'vodeni-sportovi', NULL, 'Water sports', 'Vodeni sportovi', 'Водени спортови', 'SUP daske i kajaci', 'Water sports', 7),
	(210, 8, 'camping', 'kampovanje', NULL, 'Camping & hiking', 'Kampovanje i planinarenje', 'Камповање и планинарење', 'Šatori i kamp oprema', 'Camping & hiking', 8),
	(211, 8, 'hunting', 'lov', NULL, 'Hunting gear', 'Oprema za lov', 'Опрема за лов', 'Oprema za lov', 'Hunting gear', 9),
	(212, 8, 'martial-arts', 'borilacki-sportovi', NULL, 'Martial arts', 'Borilački sportovi', 'Борилачки спортови', 'Oprema za borilačke sportove', 'Martial arts', 10),
	(96, NULL, 'beauty-health', 'lepota-i-zdravlje', 'sparkle', 'Beauty & health', 'Lepota i zdravlje', 'Лепота и здравље', 'Lepota i zdravlje', 'Beauty & health', 14),
	(213, 96, 'perfumes', 'parfemi', NULL, 'Perfumes', 'Parfemi', 'Парфеми', 'Parfemi', 'Perfumes', 1),
	(214, 96, 'medical', 'medicinska-pomagala', NULL, 'Medical & orthopedic aids', 'Medicinska i ortopedska pomagala', 'Медицинска и ортопедска помагала', 'Ortopedska pomagala i medicinski aparati', 'Medical & orthopedic aids', 2),
	(215, 96, 'cosmetics', 'kozmetika', NULL, 'Cosmetics & personal care', 'Kozmetika i lična nega', 'Козметика и лична нега', 'Kozmetika i fenovi', 'Cosmetics & personal care', 3),
	(216, 96, 'salon-equipment', 'oprema-za-salone', NULL, 'Salon equipment', 'Oprema za salone', 'Опрема за салоне', 'Oprema za frizerske i kozmetičke salone', 'Salon equipment', 4),
	(95, NULL, 'pets', 'ljubimci', 'paw', 'Pets', 'Kućni ljubimci', 'Кућни љубимци', 'Kućni ljubimci', 'Pets', 15),
	(217, 95, 'pet-supplies', 'oprema-za-ljubimce', NULL, 'Pet supplies & food', 'Oprema i hrana za ljubimce', 'Опрема и храна за љубимце', 'Oprema i hrana za ljubimce', 'Pet supplies & food', 1),
	(218, 95, 'dogs', 'psi', NULL, 'Dogs', 'Psi', 'Пси', 'Psi i štenci', 'Dogs', 2),
	(219, 95, 'birds', 'ptice', NULL, 'Birds', 'Ptice', 'Птице', 'Papagaji, golubovi i ptice', 'Birds', 3),
	(220, 95, 'cats', 'macke', NULL, 'Cats', 'Mačke', 'Мачке', 'Mačke i mačići', 'Cats', 4),
	(221, 95, 'pet-services', 'usluge-za-ljubimce', NULL, 'Pet services', 'Usluge za ljubimce', 'Услуге за љубимце', 'Pansion, šišanje i dresura pasa', 'Pet services', 5),
	(222, 95, 'other-pets', 'ostali-ljubimci', NULL, 'Fish, rodents & others', 'Ribice, glodari i ostali', 'Рибице, глодари и остали', 'Ribice, glodari i ostali ljubimci', 'Fish, rodents & others', 6),
	(91, NULL, 'holidays', 'odmor', 'sun', 'Holidays & stays', 'Odmor i smeštaj', 'Одмор и смештај', 'Smeštaj za odmor', 'Holidays & stays', 16),
	(223, 91, 'mountains', 'planine', NULL, 'Mountains', 'Planine', 'Планине', 'Smeštaj na planinama', 'Mountains', 1),
	(224, 91, 'spas', 'banje', NULL, 'Spas', 'Banje', 'Бање', 'Smeštaj u banjama', 'Spas', 2),
	(225, 91, 'seaside', 'more', NULL, 'Seaside', 'More', 'Море', 'Smeštaj na moru', 'Seaside', 3),
	(226, 91, 'lakes-rivers', 'jezera-i-reke', NULL, 'Lakes & rivers', 'Jezera i reke', 'Језера и реке', 'Smeštaj na jezerima i rekama', 'Lakes & rivers', 4),
	(227, 91, 'rural', 'seoski-turizam', NULL, 'Rural stays & cabins', 'Seoski turizam i brvnare', 'Сеоски туризам и брвнаре', 'Seoski turizam', 'Rural stays & cabins', 5),
	(97, NULL, 'music', 'muzika', 'music', 'Music & instruments', 'Muzika i instrumenti', 'Музика и инструменти', 'Muzički instrumenti', 'Music & instruments', 17),
	(228, 97, 'pro-audio', 'ozvucenje', NULL, 'Pro audio & PA', 'Muzička oprema i ozvučenje', 'Музичка опрема и озвучење', 'Ozvučenje, mikseri i mikrofoni', 'Pro audio & PA', 1),
	(229, 97, 'pianos', 'klaviri', NULL, 'Pianos & keyboards', 'Klaviri i klavijature', 'Клавири и клавијатуре', 'Klaviri i klavijature', 'Pianos & keyboards', 2),
	(230, 97, 'drums', 'bubnjevi', NULL, 'Drums & percussion', 'Bubnjevi i udaraljke', 'Бубњеви и удараљке', 'Bubnjevi', 'Drums & percussion', 3),
	(231, 97, 'accordions', 'harmonike', NULL, 'Accordions', 'Harmonike', 'Хармонике', 'Harmonike', 'Accordions', 4),
	(232, 97, 'guitars', 'gitare', NULL, 'Guitars', 'Gitare', 'Гитаре', 'Gitare', 'Guitars', 5),
	(233, 97, 'wind-strings', 'duvacki-i-gudacki', NULL, 'Wind & string instruments', 'Duvački i gudački instrumenti', 'Дувачки и гудачки инструменти', 'Violine, trube i duvački instrumenti', 'Wind & string instruments', 6),
	(234, 97, 'records', 'ploce-i-cd', NULL, 'Records, CDs & films', 'Ploče, CD i filmovi', 'Плоче, CD и филмови', 'Gramofonske ploče, CD i DVD', 'Records, CDs & films', 7),
	(98, NULL, 'books', 'knjige', 'book', 'Books', 'Knjige', 'Књиге', 'Polovne knjige', 'Books', 18),
	(235, 98, 'fiction', 'beletristika', NULL, 'Fiction & other books', 'Beletristika i ostale knjige', 'Белетристика и остале књиге', 'Polovne knjige', 'Fiction & other books', 1),
	(236, 98, 'textbooks', 'udzbenici', NULL, 'Textbooks', 'Udžbenici i lektira', 'Уџбеници и лектира', 'Polovni udžbenici i lektira', 'Textbooks', 2),
	(237, 98, 'comics', 'stripovi', NULL, 'Comics & magazines', 'Stripovi i časopisi', 'Стрипови и часописи', 'Stripovi', 'Comics & magazines', 3),
	(238, 98, 'antiquarian-books', 'antikvarne-knjige', NULL, 'Antiquarian books', 'Antikvarne knjige', 'Антикварне књиге', 'Antikvarne knjige', 'Antiquarian books', 4),
	(99, NULL, 'antiques', 'antikviteti', 'vase', 'Antiques & collectibles', 'Antikviteti i kolekcionarstvo', 'Антиквитети и колекционарство', 'Antikviteti i kolekcionarstvo', 'Antiques & collectibles', 19),
	(239, 99, 'gold', 'zlato', NULL, 'Gold & silver', 'Zlato i srebro', 'Злато и сребро', 'Otkup i prodaja zlata', 'Gold & silver', 1),
	(240, 99, 'art', 'umetnost', NULL, 'Paintings, icons & art', 'Slike, ikone i umetnost', 'Слике, иконе и уметност', 'Umetničke slike i ikone', 'Paintings, icons & art', 2),
	(241, 99, 'antique-items', 'stari-predmeti', NULL, 'Antiques & folk items', 'Antikviteti i etno predmeti', 'Антиквитети и етно предмети', 'Antikviteti i stari predmeti', 'Antiques & folk items', 3),
	(242, 99, 'coins-stamps', 'novac-i-marke', NULL, 'Coins, stamps & badges', 'Novac, marke i značke', 'Новац, марке и значке', 'Stari novac i marke', 'Coins, stamps & badges', 4),
	(100, NULL, 'other', 'ostalo', 'grid', 'Other', 'Ostalo', 'Остало', 'Ostali oglasi', 'Other', 20),
	(243, 100, 'tickets', 'ulaznice', NULL, 'Tickets', 'Ulaznice i karte', 'Улазнице и карте', 'Ulaznice i karte', 'Tickets', 1),
	(244, 100, 'misc', 'razno', NULL, 'Miscellaneous', 'Razno', 'Разно', 'Razno', 'Miscellaneous', 2)
ON DUPLICATE KEY UPDATE parent_id = VALUES(parent_id), slug = VALUES(slug), slug_sr = VALUES(slug_sr), icon = VALUES(icon),
	name_en = VALUES(name_en), name_sr_latn = VALUES(name_sr_latn), name_sr_cyrl = VALUES(name_sr_cyrl),
	h1_sr = VALUES(h1_sr), h1_en = VALUES(h1_en), sort = VALUES(sort), active = 1;

ALTER TABLE cities
	ADD COLUMN slug VARCHAR(64) NULL AFTER cyrl,
	ADD COLUMN slug_en VARCHAR(64) NULL AFTER slug,
	ADD COLUMN kind ENUM('city', 'resort', 'abroad') NOT NULL DEFAULT 'city' AFTER slug_en,
	ADD COLUMN main_rank TINYINT UNSIGNED NULL AFTER kind,
	ADD UNIQUE KEY uq_cities_slug (slug);

INSERT INTO cities (latn, cyrl, slug, slug_en, kind, main_rank, sort) VALUES
	('Beograd', 'Београд', 'beograd', 'belgrade', 'city', 1, 1),
	('Novi Sad', 'Нови Сад', 'novi-sad', NULL, 'city', 2, 2),
	('Niš', 'Ниш', 'nis', NULL, 'city', 3, 3),
	('Kragujevac', 'Крагујевац', 'kragujevac', NULL, 'city', 4, 4),
	('Pančevo', 'Панчево', 'pancevo', NULL, 'city', 5, 5),
	('Zrenjanin', 'Зрењанин', 'zrenjanin', NULL, 'city', 6, 6),
	('Kraljevo', 'Краљево', 'kraljevo', NULL, 'city', 7, 7),
	('Kruševac', 'Крушевац', 'krusevac', NULL, 'city', 8, 8),
	('Smederevo', 'Смедерево', 'smederevo', NULL, 'city', 9, 9),
	('Subotica', 'Суботица', 'subotica', NULL, 'city', 10, 10),
	('Čačak', 'Чачак', 'cacak', NULL, 'city', 11, 11),
	('Leskovac', 'Лесковац', 'leskovac', NULL, 'city', 12, 12),
	('Jagodina', 'Јагодина', 'jagodina', NULL, 'city', 13, 13),
	('Valjevo', 'Ваљево', 'valjevo', NULL, 'city', 14, 14),
	('Vranje', 'Врање', 'vranje', NULL, 'city', 15, 15),
	('Sremska Mitrovica', 'Сремска Митровица', 'sremska-mitrovica', NULL, 'city', 16, 16),
	('Sombor', 'Сомбор', 'sombor', NULL, 'city', 17, 17),
	('Šabac', 'Шабац', 'sabac', NULL, 'city', 18, 18),
	('Loznica', 'Лозница', 'loznica', NULL, 'city', 19, 19),
	('Kikinda', 'Кикинда', 'kikinda', NULL, 'city', 20, 20),
	('Obrenovac', 'Обреновац', 'obrenovac', NULL, 'city', 21, 21),
	('Ruma', 'Рума', 'ruma', NULL, 'city', 22, 22),
	('Mladenovac', 'Младеновац', 'mladenovac', NULL, 'city', 23, 23),
	('Požarevac', 'Пожаревац', 'pozarevac', NULL, 'city', 24, 24),
	('Užice', 'Ужице', 'uzice', NULL, 'city', 25, 25),
	('Novi Pazar', 'Нови Пазар', 'novi-pazar', NULL, 'city', 26, 26),
	('Bor', 'Бор', 'bor', NULL, 'city', 27, 27),
	('Pirot', 'Пирот', 'pirot', NULL, 'city', 28, 28),
	('Inđija', 'Инђија', 'indjija', NULL, 'city', 29, 29),
	('Temerin', 'Темерин', 'temerin', NULL, 'city', 30, 30),
	('Zlatibor', 'Златибор', 'zlatibor', NULL, 'resort', 31, 31),
	('Vrnjačka Banja', 'Врњачка Бања', 'vrnjacka-banja', NULL, 'resort', 32, 32),
	('Sokobanja', 'Сокобања', 'sokobanja', NULL, 'resort', 33, 33),
	('Divčibare', 'Дивчибаре', 'divcibare', NULL, 'resort', 34, 34),
	('Tara', 'Тара', 'tara', NULL, 'resort', 35, 35),
	('Kopaonik', 'Копаоник', 'kopaonik', NULL, 'resort', 36, 36),
	('Srebrno jezero', 'Сребрно језеро', 'srebrno-jezero', NULL, 'resort', 37, 37),
	('Fruška gora', 'Фрушка гора', 'fruska-gora', NULL, 'resort', 38, 38),
	('Stara planina', 'Стара планина', 'stara-planina', NULL, 'resort', 39, 39),
	('Palić', 'Палић', 'palic', NULL, 'resort', 40, 40),
	('Ada', 'Ада', 'ada', NULL, 'city', NULL, 41),
	('Aleksandrovac', 'Александровац', 'aleksandrovac', NULL, 'city', NULL, 42),
	('Aleksinac', 'Алексинац', 'aleksinac', NULL, 'city', NULL, 43),
	('Alibunar', 'Алибунар', 'alibunar', NULL, 'city', NULL, 44),
	('Apatin', 'Апатин', 'apatin', NULL, 'city', NULL, 45),
	('Aranđelovac', 'Аранђеловац', 'arandjelovac', NULL, 'city', NULL, 46),
	('Arilje', 'Ариље', 'arilje', NULL, 'city', NULL, 47),
	('Babušnica', 'Бабушница', 'babusnica', NULL, 'city', NULL, 48),
	('Bač', 'Бач', 'bac', NULL, 'city', NULL, 49),
	('Bačka Palanka', 'Бачка Паланка', 'backa-palanka', NULL, 'city', NULL, 50),
	('Bačka Topola', 'Бачка Топола', 'backa-topola', NULL, 'city', NULL, 51),
	('Bački Petrovac', 'Бачки Петровац', 'backi-petrovac', NULL, 'city', NULL, 52),
	('Bajina Bašta', 'Бајина Башта', 'bajina-basta', NULL, 'city', NULL, 53),
	('Barajevo', 'Барајево', 'barajevo', NULL, 'city', NULL, 54),
	('Batočina', 'Баточина', 'batocina', NULL, 'city', NULL, 55),
	('Bečej', 'Бечеј', 'becej', NULL, 'city', NULL, 56),
	('Bela Crkva', 'Бела Црква', 'bela-crkva', NULL, 'city', NULL, 57),
	('Bela Palanka', 'Бела Паланка', 'bela-palanka', NULL, 'city', NULL, 58),
	('Beočin', 'Беочин', 'beocin', NULL, 'city', NULL, 59),
	('Blace', 'Блаце', 'blace', NULL, 'city', NULL, 60),
	('Bogatić', 'Богатић', 'bogatic', NULL, 'city', NULL, 61),
	('Bojnik', 'Бојник', 'bojnik', NULL, 'city', NULL, 62),
	('Boljevac', 'Бољевац', 'boljevac', NULL, 'city', NULL, 63),
	('Bosilegrad', 'Босилеград', 'bosilegrad', NULL, 'city', NULL, 64),
	('Brus', 'Брус', 'brus', NULL, 'city', NULL, 65),
	('Bujanovac', 'Бујановац', 'bujanovac', NULL, 'city', NULL, 66),
	('Čajetina', 'Чајетина', 'cajetina', NULL, 'city', NULL, 67),
	('Ćićevac', 'Ћићевац', 'cicevac', NULL, 'city', NULL, 68),
	('Čoka', 'Чока', 'coka', NULL, 'city', NULL, 69),
	('Crna Trava', 'Црна Трава', 'crna-trava', NULL, 'city', NULL, 70),
	('Ćuprija', 'Ћуприја', 'cuprija', NULL, 'city', NULL, 71),
	('Dečani', 'Дечани', 'decani', NULL, 'city', NULL, 72),
	('Despotovac', 'Деспотовац', 'despotovac', NULL, 'city', NULL, 73),
	('Dimitrovgrad', 'Димитровград', 'dimitrovgrad', NULL, 'city', NULL, 74),
	('Đakovica', 'Ђаковица', 'djakovica', NULL, 'city', NULL, 75),
	('Doljevac', 'Дољевац', 'doljevac', NULL, 'city', NULL, 76),
	('Dragaš', 'Драгаш', 'dragas', NULL, 'city', NULL, 77),
	('Gadžin Han', 'Гаџин Хан', 'gadzin-han', NULL, 'city', NULL, 78),
	('Glogovac', 'Глоговац', 'glogovac', NULL, 'city', NULL, 79),
	('Gnjilane', 'Гњилане', 'gnjilane', NULL, 'city', NULL, 80),
	('Golubac', 'Голубац', 'golubac', NULL, 'city', NULL, 81),
	('Gora', 'Гора', 'gora', NULL, 'city', NULL, 82),
	('Gornji Milanovac', 'Горњи Милановац', 'gornji-milanovac', NULL, 'city', NULL, 83),
	('Gračanica', 'Грачаница', 'gracanica', NULL, 'city', NULL, 84),
	('Grocka', 'Гроцка', 'grocka', NULL, 'city', NULL, 85),
	('Irig', 'Ириг', 'irig', NULL, 'city', NULL, 86),
	('Istok', 'Исток', 'istok', NULL, 'city', NULL, 87),
	('Ivanjica', 'Ивањица', 'ivanjica', NULL, 'city', NULL, 88),
	('Kačanik', 'Качаник', 'kacanik', NULL, 'city', NULL, 89),
	('Kanjiža', 'Кањижа', 'kanjiza', NULL, 'city', NULL, 90),
	('Kladovo', 'Кладово', 'kladovo', NULL, 'city', NULL, 91),
	('Klina', 'Клина', 'klina', NULL, 'city', NULL, 92),
	('Knić', 'Кнић', 'knic', NULL, 'city', NULL, 93),
	('Knjaževac', 'Књажевац', 'knjazevac', NULL, 'city', NULL, 94),
	('Koceljeva', 'Коцељева', 'koceljeva', NULL, 'city', NULL, 95),
	('Kosjerić', 'Косјерић', 'kosjeric', NULL, 'city', NULL, 96),
	('Kosovo Polje', 'Косово Поље', 'kosovo-polje', NULL, 'city', NULL, 97),
	('Kosovska Kamenica', 'Косовска Каменица', 'kosovska-kamenica', NULL, 'city', NULL, 98),
	('Kosovska Mitrovica', 'Косовска Митровица', 'kosovska-mitrovica', NULL, 'city', NULL, 99),
	('Kovačica', 'Ковачица', 'kovacica', NULL, 'city', NULL, 100),
	('Kovin', 'Ковин', 'kovin', NULL, 'city', NULL, 101),
	('Krupanj', 'Крупањ', 'krupanj', NULL, 'city', NULL, 102),
	('Kučevo', 'Кучево', 'kucevo', NULL, 'city', NULL, 103),
	('Kula', 'Кула', 'kula', NULL, 'city', NULL, 104),
	('Kuršumlija', 'Куршумлија', 'kursumlija', NULL, 'city', NULL, 105),
	('Lajkovac', 'Лајковац', 'lajkovac', NULL, 'city', NULL, 106),
	('Lapovo', 'Лапово', 'lapovo', NULL, 'city', NULL, 107),
	('Lazarevac', 'Лазаревац', 'lazarevac', NULL, 'city', NULL, 108),
	('Lebane', 'Лебане', 'lebane', NULL, 'city', NULL, 109),
	('Leposavić', 'Лепосавић', 'leposavic', NULL, 'city', NULL, 110),
	('Lipljan', 'Липљан', 'lipljan', NULL, 'city', NULL, 111),
	('Ljig', 'Љиг', 'ljig', NULL, 'city', NULL, 112),
	('Ljubovija', 'Љубовија', 'ljubovija', NULL, 'city', NULL, 113),
	('Lučani', 'Лучани', 'lucani', NULL, 'city', NULL, 114),
	('Majdanpek', 'Мајданпек', 'majdanpek', NULL, 'city', NULL, 115),
	('Mali Iđoš', 'Мали Иђош', 'mali-idjos', NULL, 'city', NULL, 116),
	('Mali Zvornik', 'Мали Зворник', 'mali-zvornik', NULL, 'city', NULL, 117),
	('Malo Crniće', 'Мало Црниће', 'malo-crnice', NULL, 'city', NULL, 118),
	('Medveđa', 'Медвеђа', 'medvedja', NULL, 'city', NULL, 119),
	('Merošina', 'Мерошина', 'merosina', NULL, 'city', NULL, 120),
	('Mionica', 'Мионица', 'mionica', NULL, 'city', NULL, 121),
	('Negotin', 'Неготин', 'negotin', NULL, 'city', NULL, 122),
	('Nova Crnja', 'Нова Црња', 'nova-crnja', NULL, 'city', NULL, 123),
	('Nova Varoš', 'Нова Варош', 'nova-varos', NULL, 'city', NULL, 124),
	('Novi Bečej', 'Нови Бечеј', 'novi-becej', NULL, 'city', NULL, 125),
	('Novi Kneževac', 'Нови Кнежевац', 'novi-knezevac', NULL, 'city', NULL, 126),
	('Novo Brdo', 'Ново Брдо', 'novo-brdo', NULL, 'city', NULL, 127),
	('Obilić', 'Обилић', 'obilic', NULL, 'city', NULL, 128),
	('Odžaci', 'Оџаци', 'odzaci', NULL, 'city', NULL, 129),
	('Opovo', 'Опово', 'opovo', NULL, 'city', NULL, 130),
	('Orahovac', 'Ораховац', 'orahovac', NULL, 'city', NULL, 131),
	('Osečina', 'Осечина', 'osecina', NULL, 'city', NULL, 132),
	('Paraćin', 'Параћин', 'paracin', NULL, 'city', NULL, 133),
	('Peć', 'Пећ', 'pec', NULL, 'city', NULL, 134),
	('Pećinci', 'Пећинци', 'pecinci', NULL, 'city', NULL, 135),
	('Petrovac na Mlavi', 'Петровац на Млави', 'petrovac-na-mlavi', NULL, 'city', NULL, 136),
	('Plandište', 'Пландиште', 'plandiste', NULL, 'city', NULL, 137),
	('Podujevo', 'Подујево', 'podujevo', NULL, 'city', NULL, 138),
	('Požega', 'Пожега', 'pozega', NULL, 'city', NULL, 139),
	('Preševo', 'Прешево', 'presevo', NULL, 'city', NULL, 140),
	('Priboj', 'Прибој', 'priboj', NULL, 'city', NULL, 141),
	('Prijepolje', 'Пријепоље', 'prijepolje', NULL, 'city', NULL, 142),
	('Priština', 'Приштина', 'pristina', NULL, 'city', NULL, 143),
	('Prizren', 'Призрен', 'prizren', NULL, 'city', NULL, 144),
	('Prokuplje', 'Прокупље', 'prokuplje', NULL, 'city', NULL, 145),
	('Rača', 'Рача', 'raca', NULL, 'city', NULL, 146),
	('Ranilug', 'Ранилуг', 'ranilug', NULL, 'city', NULL, 147),
	('Raška', 'Рашка', 'raska', NULL, 'city', NULL, 148),
	('Ražanj', 'Ражањ', 'razanj', NULL, 'city', NULL, 149),
	('Rekovac', 'Рековац', 'rekovac', NULL, 'city', NULL, 150),
	('Sečanj', 'Сечањ', 'secanj', NULL, 'city', NULL, 151),
	('Senta', 'Сента', 'senta', NULL, 'city', NULL, 152),
	('Šid', 'Шид', 'sid', NULL, 'city', NULL, 153),
	('Sjenica', 'Сјеница', 'sjenica', NULL, 'city', NULL, 154),
	('Smederevska Palanka', 'Смедеревска Паланка', 'smederevska-palanka', NULL, 'city', NULL, 155),
	('Sopot', 'Сопот', 'sopot', NULL, 'city', NULL, 156),
	('Srbica', 'Србица', 'srbica', NULL, 'city', NULL, 157),
	('Srbobran', 'Србобран', 'srbobran', NULL, 'city', NULL, 158),
	('Sremski Karlovci', 'Сремски Карловци', 'sremski-karlovci', NULL, 'city', NULL, 159),
	('Stara Pazova', 'Стара Пазова', 'stara-pazova', NULL, 'city', NULL, 160),
	('Štrpce', 'Штрпце', 'strpce', NULL, 'city', NULL, 161),
	('Surčin', 'Сурчин', 'surcin', NULL, 'city', NULL, 162),
	('Surdulica', 'Сурдулица', 'surdulica', NULL, 'city', NULL, 163),
	('Suva Reka', 'Сува Река', 'suva-reka', NULL, 'city', NULL, 164),
	('Svilajnac', 'Свилајнац', 'svilajnac', NULL, 'city', NULL, 165),
	('Svrljig', 'Сврљиг', 'svrljig', NULL, 'city', NULL, 166),
	('Titel', 'Тител', 'titel', NULL, 'city', NULL, 167),
	('Topola', 'Топола', 'topola', NULL, 'city', NULL, 168),
	('Trgovište', 'Трговиште', 'trgoviste', NULL, 'city', NULL, 169),
	('Trstenik', 'Трстеник', 'trstenik', NULL, 'city', NULL, 170),
	('Tutin', 'Тутин', 'tutin', NULL, 'city', NULL, 171),
	('Ub', 'Уб', 'ub', NULL, 'city', NULL, 172),
	('Uroševac', 'Урошевац', 'urosevac', NULL, 'city', NULL, 173),
	('Varvarin', 'Варварин', 'varvarin', NULL, 'city', NULL, 174),
	('Velika Plana', 'Велика Плана', 'velika-plana', NULL, 'city', NULL, 175),
	('Veliko Gradište', 'Велико Градиште', 'veliko-gradiste', NULL, 'city', NULL, 176),
	('Vitina', 'Витина', 'vitina', NULL, 'city', NULL, 177),
	('Vladičin Han', 'Владичин Хан', 'vladicin-han', NULL, 'city', NULL, 178),
	('Vladimirci', 'Владимирци', 'vladimirci', NULL, 'city', NULL, 179),
	('Vlasotince', 'Власотинце', 'vlasotince', NULL, 'city', NULL, 180),
	('Vrbas', 'Врбас', 'vrbas', NULL, 'city', NULL, 181),
	('Vršac', 'Вршац', 'vrsac', NULL, 'city', NULL, 182),
	('Vučitrn', 'Вучитрн', 'vucitrn', NULL, 'city', NULL, 183),
	('Žabalj', 'Жабаљ', 'zabalj', NULL, 'city', NULL, 184),
	('Žabari', 'Жабари', 'zabari', NULL, 'city', NULL, 185),
	('Žagubica', 'Жагубица', 'zagubica', NULL, 'city', NULL, 186),
	('Zaječar', 'Зајечар', 'zajecar', NULL, 'city', NULL, 187),
	('Žitište', 'Житиште', 'zitiste', NULL, 'city', NULL, 188),
	('Žitorađa', 'Житорађа', 'zitoradja', NULL, 'city', NULL, 189),
	('Zubin Potok', 'Зубин Поток', 'zubin-potok', NULL, 'city', NULL, 190),
	('Zvečan', 'Звечан', 'zvecan', NULL, 'city', NULL, 191),
	('Inostranstvo', 'Иностранство', 'inostranstvo', 'abroad', 'abroad', NULL, 192)
ON DUPLICATE KEY UPDATE cyrl = VALUES(cyrl), slug = VALUES(slug), slug_en = VALUES(slug_en), kind = VALUES(kind),
	main_rank = VALUES(main_rank), sort = VALUES(sort), active = 1;

CREATE TABLE districts (
	id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	city_id SMALLINT UNSIGNED NOT NULL,
	latn VARCHAR(64) NOT NULL,
	cyrl VARCHAR(64) NOT NULL,
	slug VARCHAR(64) NOT NULL,
	sort SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	active TINYINT(1) NOT NULL DEFAULT 1,
	PRIMARY KEY (id),
	UNIQUE KEY uq_districts_slug (city_id, slug),
	CONSTRAINT fk_districts_city FOREIGN KEY (city_id) REFERENCES cities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Novi Beograd', 'Нови Београд', 'novi-beograd', 1 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Zemun', 'Земун', 'zemun', 2 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Mirijevo', 'Миријево', 'mirijevo', 3 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Voždovac', 'Вождовац', 'vozdovac', 4 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Banovo brdo', 'Баново брдо', 'banovo-brdo', 5 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Karaburma', 'Карабурма', 'karaburma', 6 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Batajnica', 'Батајница', 'batajnica', 7 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Vračar', 'Врачар', 'vracar', 8 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Borča', 'Борча', 'borca', 9 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Čukarica', 'Чукарица', 'cukarica', 10 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Rakovica', 'Раковица', 'rakovica', 11 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Zvezdara', 'Звездара', 'zvezdara', 12 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Vidikovac', 'Видиковац', 'vidikovac', 13 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Železnik', 'Железник', 'zeleznik', 14 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Žarkovo', 'Жарково', 'zarkovo', 15 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Bežanijska kosa', 'Бежанијска коса', 'bezanijska-kosa', 16 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Cerak', 'Церак', 'cerak', 17 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Altina', 'Алтина', 'altina', 18 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Ledine', 'Ледине', 'ledine', 19 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Dorćol', 'Дорћол', 'dorcol', 20 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Konjarnik', 'Коњарник', 'konjarnik', 21 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Sremčica', 'Сремчица', 'sremcica', 22 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Stari grad', 'Стари град', 'stari-grad', 23 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Dedinje', 'Дедиње', 'dedinje', 24 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Kaluđerica', 'Калуђерица', 'kaludjerica', 25 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Krnjača', 'Крњача', 'krnjaca', 26 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Banjica', 'Бањица', 'banjica', 27 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Bele vode', 'Беле воде', 'bele-vode', 28 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Kanarevo brdo', 'Канарево брдо', 'kanarevo-brdo', 29 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Labudovo brdo', 'Лабудово брдо', 'labudovo-brdo', 30 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Resnik', 'Ресник', 'resnik', 31 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Kumodraž', 'Кумодраж', 'kumodraz', 32 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Savski venac', 'Савски венац', 'savski-venac', 33 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Kotež', 'Котеж', 'kotez', 34 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Medaković', 'Медаковић', 'medakovic', 35 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Palilula', 'Палилула', 'palilula', 36 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Senjak', 'Сењак', 'senjak', 37 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Mali mokri lug', 'Мали мокри луг', 'mali-mokri-lug', 38 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Julino brdo', 'Јулино брдо', 'julino-brdo', 39 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Jajinci', 'Јајинци', 'jajinci', 40 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Veliki mokri lug', 'Велики мокри луг', 'veliki-mokri-lug', 41 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Galenika', 'Галеника', 'galenika', 42 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Vinča', 'Винча', 'vinca', 43 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Zemun polje', 'Земун поље', 'zemun-polje', 44 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Višnjica', 'Вишњица', 'visnjica', 45 FROM cities WHERE latn = 'Beograd';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Petrovaradin', 'Петроварадин', 'petrovaradin', 1 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Veternik', 'Ветерник', 'veternik', 2 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Sremska Kamenica', 'Сремска Каменица', 'sremska-kamenica', 3 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Novo naselje', 'Ново насеље', 'novo-naselje', 4 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Futog', 'Футог', 'futog', 5 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Liman', 'Лиман', 'liman', 6 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Adice', 'Адице', 'adice', 7 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Detelinara', 'Детелинара', 'detelinara', 8 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Telep', 'Телеп', 'telep', 9 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Klisa', 'Клиса', 'klisa', 10 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Grbavica', 'Грбавица', 'grbavica', 11 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Podbara', 'Подбара', 'podbara', 12 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Sajlovo', 'Сајлово', 'sajlovo', 13 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Satelit', 'Сателит', 'satelit', 14 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Salajka', 'Салајка', 'salajka', 15 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Rotkvarija', 'Роткварија', 'rotkvarija', 16 FROM cities WHERE latn = 'Novi Sad';
INSERT INTO districts (city_id, latn, cyrl, slug, sort) SELECT id, 'Centar', 'Центар', 'centar', 17 FROM cities WHERE latn = 'Novi Sad';

ALTER TABLE listings
	ADD COLUMN deal ENUM('sale', 'rent', 'daily') NULL AFTER category_id,
	ADD COLUMN item_type VARCHAR(32) NULL AFTER deal,
	ADD COLUMN brand VARCHAR(32) NULL AFTER item_type,
	ADD COLUMN district_id SMALLINT UNSIGNED NULL AFTER city_id,
	ADD COLUMN seller_type ENUM('private', 'business') NULL AFTER district_id,
	ADD KEY idx_listings_facets (category_id, deal, item_type, brand),
	ADD KEY idx_listings_district (district_id),
	ADD CONSTRAINT fk_listings_district FOREIGN KEY (district_id) REFERENCES districts (id) ON DELETE SET NULL;
SQL;
