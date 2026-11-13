<?php
/* ==========================================================================
	Taxonomy inside a subcategory (stage 6, approved 09.11): deal types of real
	estate, kinds of item ("vrsta") and brands. The dictionaries live here, in
	code; a listing stores the key (listings.deal / type / brand).
	Keys are English address words (like category slugs), each item also has its
	Serbian word: /oglasi/moda/zenska-obuca/patike ↔ /en/listings/fashion/womens-shoes/sneakers.
	Names: Serbian Latin + English, Cyrillic is converted (sr_cyr()); brands are
	the same in every language.
	The category tree, places and districts are in the database (source of the
	data: source/db/taxonomy.php, migration 015).
	========================================================================== */

// Real estate deals: key => [sr word, en word]; stored in listings.deal
const DEALS = [
	'sale' => ['prodaja', 'sale'],
	'rent' => ['izdavanje', 'rent'],
	'daily' => ['na-dan', 'daily'],
];

// Subcategory → deals it takes (the first is the default of the form)
const CATEGORY_DEALS = [
	'apartments' => ['sale', 'rent', 'daily'],
	'houses' => ['sale', 'rent', 'daily'],
	'cottages' => ['sale', 'rent', 'daily'],
	'land' => ['sale', 'rent'],
	'commercial' => ['sale', 'rent'],
	'garages' => ['sale', 'rent'],
	'rooms' => ['rent', 'daily'],
];

// Kinds of item by subcategory: [key (en word), sr word, Serbian name, English name].
// The same key in several subcategories means the same kind: the section page
// gathers them (/oglasi/moda/patike = sneakers of every subcategory of Moda).
// "Other" (ostalo) is added to every list by category_types()
const CATEGORY_TYPES = [
	// Vozila
	'tyres-wheels' => [['tyres', 'gume', 'Gume', 'Tyres'], ['wheels', 'felne', 'Felne', 'Wheels'], ['wheel-sets', 'gume-sa-felnama', 'Gume sa felnama', 'Wheel sets']],
	'car-parts' => [
		['engine', 'motor', 'Motor i delovi motora', 'Engine & engine parts'], ['body', 'karoserija', 'Karoserija', 'Body parts'],
		['suspension', 'trap-i-vesanje', 'Trap i vešanje', 'Suspension'], ['brakes', 'kocnice', 'Kočnice', 'Brakes'],
		['lights', 'svetla', 'Svetla i elektrika', 'Lights & electrics'], ['batteries', 'akumulatori', 'Akumulatori', 'Batteries'],
		['interior', 'enterijer', 'Enterijer', 'Interior'], ['exhaust', 'auspuh', 'Auspuh', 'Exhaust'],
	],
	'trucks-vans' => [['trucks', 'kamioni', 'Kamioni', 'Trucks'], ['vans', 'kombiji', 'Kombiji', 'Vans'], ['buses', 'autobusi', 'Autobusi i minibusi', 'Buses & minibuses']],
	'trailers' => [['car-trailers', 'auto-prikolice', 'Auto prikolice', 'Car trailers'], ['caravans', 'kamp-prikolice', 'Kamp prikolice', 'Caravans'], ['campervans', 'kamperi', 'Kamperi', 'Campervans']],
	'boats' => [
		['boats', 'camci', 'Čamci', 'Boats'], ['inflatable', 'gumeni-camci', 'Gumeni čamci', 'Inflatable boats'],
		['yachts', 'jahte', 'Jahte i jedrilice', 'Yachts & sailboats'], ['outboard-motors', 'vanbrodski-motori', 'Vanbrodski motori', 'Outboard motors'],
	],
	'motorcycle-gear' => [['helmets', 'kacige', 'Kacige', 'Helmets'], ['clothing', 'odeca', 'Moto odeća i obuća', 'Clothing & boots'], ['parts', 'delovi', 'Moto delovi', 'Motorcycle parts']],
	// Nekretnine
	'land' => [['building-plots', 'gradjevinski-placevi', 'Građevinski placevi', 'Building plots'], ['farmland', 'poljoprivredno-zemljiste', 'Poljoprivredno zemljište', 'Farmland']],
	'commercial' => [['shops', 'lokali', 'Lokali', 'Shops'], ['offices', 'kancelarije', 'Kancelarije', 'Offices'], ['warehouses', 'magacini-i-hale', 'Magacini i hale', 'Warehouses & halls']],
	'garages' => [['garages', 'garaze', 'Garaže', 'Garages'], ['parking', 'parking-mesta', 'Parking mesta', 'Parking spaces']],
	// Odmor: by country for the seaside
	'seaside' => [
		['greece', 'grcka', 'Grčka', 'Greece'], ['montenegro', 'crna-gora', 'Crna Gora', 'Montenegro'], ['croatia', 'hrvatska', 'Hrvatska', 'Croatia'],
		['bulgaria', 'bugarska', 'Bugarska', 'Bulgaria'], ['turkey', 'turska', 'Turska', 'Turkey'], ['albania', 'albanija', 'Albanija', 'Albania'],
	],
	// Usluge
	'car-service' => [
		['car-repair', 'auto-servis', 'Auto servis i mehaničari', 'Car repair'], ['tyre-service', 'vulkanizer', 'Vulkanizeri', 'Tyre service'],
		['bodywork', 'limar-i-farbar', 'Autolimari i farbari', 'Bodywork & paint'], ['car-wash', 'perionica', 'Auto perionice i dubinsko pranje', 'Car wash & detailing'],
	],
	'tradesmen' => [
		['plumber', 'vodoinstalater', 'Vodoinstalateri', 'Plumbers'], ['electrician', 'elektricar', 'Električari', 'Electricians'],
		['painter', 'moler', 'Moleri i fasaderi', 'Painters & plasterers'], ['tiler', 'keramicar', 'Keramičari', 'Tilers'],
		['carpenter', 'stolar', 'Stolari', 'Carpenters'], ['locksmith', 'bravar', 'Bravari', 'Locksmiths'],
		['roofer', 'krovopokrivac', 'Krovopokrivači', 'Roofers'], ['renovation', 'adaptacije', 'Adaptacije i renoviranje', 'Renovation'],
	],
	'moving' => [['moving', 'selidbe', 'Selidbe', 'Moving'], ['freight', 'prevoz-robe', 'Prevoz robe', 'Freight'], ['passengers', 'prevoz-putnika', 'Prevoz putnika', 'Passenger transport']],
	'cleaning' => [['home', 'ciscenje-stanova', 'Čišćenje stanova i kuća', 'Home cleaning'], ['office', 'ciscenje-poslovnog-prostora', 'Čišćenje poslovnog prostora', 'Office cleaning'], ['garden', 'uredjenje-dvorista', 'Uređenje dvorišta i košenje', 'Garden & lawn']],
	'beauty-services' => [
		['hairdresser', 'frizer', 'Frizeri', 'Hairdressers'], ['makeup', 'sminkanje', 'Šminkanje', 'Make-up'], ['nails', 'manikir-i-pedikir', 'Manikir i pedikir', 'Nails'],
		['massage', 'masaza', 'Masaža', 'Massage'], ['cosmetic', 'kozmeticki-tretmani', 'Kozmetički tretmani', 'Cosmetic treatments'],
		['babysitting', 'cuvanje-dece', 'Čuvanje dece', 'Babysitting'], ['elderly-care', 'nega-starijih', 'Nega starijih osoba', 'Elderly care'],
	],
	'lessons' => [
		['languages', 'strani-jezici', 'Strani jezici', 'Languages'], ['maths-science', 'matematika-i-nauka', 'Matematika i prirodne nauke', 'Maths & science'],
		['music-lessons', 'muzika', 'Muzika', 'Music'], ['it-courses', 'racunari-i-it', 'Računari i IT', 'Computers & IT'],
	],
	'events' => [
		['catering', 'ketering-i-torte', 'Ketering i torte', 'Catering & cakes'], ['venues', 'dekoracije-i-sale', 'Dekoracije i sale', 'Decorations & venues'],
		['music', 'muzika-i-bendovi', 'Muzika i bendovi', 'Music & bands'], ['photo', 'fotografi', 'Fotografi i snimanje', 'Photo & video'],
	],
	'business-services' => [
		['websites', 'izrada-sajtova', 'Izrada sajtova', 'Websites'], ['design', 'graficki-dizajn', 'Grafički dizajn', 'Graphic design'],
		['accounting', 'knjigovodstvo', 'Knjigovodstvo', 'Accounting'], ['translation', 'prevodi', 'Prevodi', 'Translation'], ['marketing', 'marketing', 'Marketing', 'Marketing'],
	],
	'repairs' => [
		['appliances', 'kucni-aparati', 'Kućni aparati', 'Home appliances'], ['air-conditioning', 'klima-uredjaji', 'Klima uređaji', 'Air conditioning'],
		['phones', 'telefoni', 'Telefoni', 'Phones'], ['computers', 'racunari', 'Računari', 'Computers'], ['tv-electronics', 'tv-i-elektronika', 'TV i elektronika', 'TV & electronics'],
	],
	// Elektronika
	'phone-accessories' => [
		['cases', 'maske', 'Maske i futrole', 'Cases'], ['chargers', 'punjaci-i-kablovi', 'Punjači i kablovi', 'Chargers & cables'],
		['screen-protectors', 'zastitna-stakla', 'Zaštitna stakla', 'Screen protectors'], ['parts', 'delovi', 'Delovi za telefone', 'Phone parts'],
	],
	'peripherals' => [
		['monitors', 'monitori', 'Monitori', 'Monitors'], ['printers', 'stampaci', 'Štampači i skeneri', 'Printers & scanners'],
		['keyboards-mice', 'tastature-i-misevi', 'Tastature i miševi', 'Keyboards & mice'], ['networking', 'mrezna-oprema', 'Mrežna oprema i ruteri', 'Networking & routers'],
	],
	'components' => [
		['gpu', 'graficke-kartice', 'Grafičke kartice', 'Graphics cards'], ['cpu', 'procesori', 'Procesori', 'Processors'], ['ram', 'ram-memorija', 'RAM memorija', 'RAM'],
		['storage', 'ssd-i-hard-diskovi', 'SSD i hard diskovi', 'SSD & hard drives'], ['motherboards', 'maticne-ploce', 'Matične ploče', 'Motherboards'],
		['cases-psu', 'kucista-i-napajanja', 'Kućišta i napajanja', 'Cases & power supplies'],
	],
	'consoles' => [
		['playstation', 'playstation', 'PlayStation', 'PlayStation'], ['nintendo', 'nintendo', 'Nintendo', 'Nintendo'], ['xbox', 'xbox', 'Xbox', 'Xbox'],
		['games', 'igrice', 'Igrice', 'Games'], ['accessories', 'dzojstici-i-oprema', 'Džojstici i oprema', 'Controllers & accessories'],
	],
	'tv' => [['tvs', 'televizori', 'Televizori', 'TVs'], ['receivers', 'resiveri-i-tv-box', 'Resiveri, antene i TV box', 'Receivers, aerials & TV boxes'], ['projectors', 'projektori', 'Projektori', 'Projectors']],
	'audio' => [
		['headphones', 'slusalice', 'Slušalice', 'Headphones'], ['speakers', 'zvucnici', 'Zvučnici', 'Speakers'],
		['hifi', 'gramofoni-i-hi-fi', 'Gramofoni i hi-fi', 'Turntables & hi-fi'], ['car-audio', 'auto-audio', 'Auto audio', 'Car audio'],
	],
	'cameras' => [
		['cameras', 'fotoaparati', 'Fotoaparati', 'Cameras'], ['lenses', 'objektivi', 'Objektivi', 'Lenses'], ['camcorders', 'video-kamere', 'Video kamere', 'Camcorders'],
		['drones', 'dronovi', 'Dronovi', 'Drones'], ['action-cams', 'akcione-kamere', 'Akcione kamere', 'Action cameras'],
	],
	// Kućni aparati
	'fridges' => [['fridges', 'frizideri', 'Frižideri', 'Fridges'], ['freezers', 'zamrzivaci', 'Zamrzivači', 'Freezers']],
	'washing-machines' => [['washers', 'ves-masine', 'Mašine za veš', 'Washing machines'], ['dryers', 'susilice', 'Sušilice', 'Dryers']],
	'cookers' => [
		['cookers', 'sporeti', 'Šporeti', 'Cookers'], ['built-in', 'ugradne-rerne-i-ploce', 'Ugradne rerne i ploče', 'Built-in ovens & hobs'],
		['microwaves', 'mikrotalasne', 'Mikrotalasne', 'Microwaves'], ['hoods', 'aspiratori', 'Aspiratori', 'Cooker hoods'],
	],
	'air-conditioners' => [['inverter', 'inverter-klime', 'Inverter klime', 'Inverter units'], ['portable', 'pokretne-klime', 'Pokretne klime', 'Portable units']],
	'heaters' => [
		['oil-radiators', 'uljani-i-norveski-radijatori', 'Norveški i uljani radijatori', 'Oil & panel radiators'], ['fan-heaters', 'grejalice-i-kaloriferi', 'Grejalice i kaloriferi', 'Fan heaters'],
		['storage-heaters', 'ta-peci', 'TA peći', 'Storage heaters'],
	],
	'vacuums' => [
		['vacuums', 'usisivaci', 'Usisivači', 'Vacuum cleaners'], ['stick', 'stapni-usisivaci', 'Štapni i ručni usisivači', 'Stick & handheld'],
		['robot', 'robot-usisivaci', 'Robot usisivači', 'Robot vacuums'], ['steam', 'parocistaci', 'Paročistači', 'Steam cleaners'],
	],
	'small-appliances' => [
		['grills', 'rostilji', 'Roštilji i gril', 'Grills'], ['irons', 'pegle', 'Pegle i parne stanice', 'Irons & steam stations'],
		['blenders', 'blenderi-i-mikseri', 'Blenderi, mikseri i multipraktici', 'Blenders & food processors'], ['fryers', 'friteze', 'Friteze', 'Fryers'],
	],
	// Nameštaj
	'sofas' => [['corner-sofas', 'ugaone-garniture', 'Ugaone garniture', 'Corner sofas'], ['sofas', 'kauci-i-trosedi', 'Kauči, dvosedi i trosedi', 'Sofas'], ['armchairs', 'fotelje', 'Fotelje', 'Armchairs']],
	'beds' => [['beds', 'kreveti', 'Kreveti', 'Beds'], ['mattresses', 'duseci', 'Dušeci', 'Mattresses']],
	'tables-chairs' => [
		['dining', 'trpezarijski-stolovi-i-stolice', 'Trpezarijski stolovi i stolice', 'Dining tables & chairs'], ['bar-stools', 'barske-stolice', 'Barske stolice', 'Bar stools'],
		['office', 'kancelarijski', 'Kancelarijski stolovi i stolice', 'Office desks & chairs'], ['coffee-tables', 'klub-stolovi', 'Klub stolovi', 'Coffee tables'],
	],
	'wardrobes' => [['wardrobes', 'ormari-i-plakari', 'Ormari i plakari', 'Wardrobes'], ['dressers', 'komode', 'Komode', 'Dressers'], ['shelves', 'police-i-regali', 'Police i regali', 'Shelves & units'], ['cabinets', 'vitrine', 'Vitrine', 'Display cabinets']],
	'garden-furniture' => [['sets', 'bastenske-garniture', 'Baštenske garniture', 'Garden sets'], ['swings', 'ljuljaske', 'Ljuljaške', 'Swings'], ['loungers', 'lezaljke-i-suncobrani', 'Ležaljke i suncobrani', 'Loungers & parasols']],
	'lighting' => [
		['chandeliers', 'lusteri-i-plafonjere', 'Lusteri i plafonjere', 'Chandeliers & ceiling lights'], ['led', 'led-rasveta', 'LED rasveta i sijalice', 'LED & bulbs'],
		['lamps', 'podne-i-stone-lampe', 'Podne i stone lampe', 'Floor & table lamps'], ['outdoor', 'spoljna-rasveta', 'Spoljna rasveta', 'Outdoor lighting'],
	],
	'textiles' => [['rugs', 'tepisi-i-staze', 'Tepisi i staze', 'Rugs & runners'], ['bedding', 'posteljina-i-jorgani', 'Posteljina i jorgani', 'Bedding'], ['curtains', 'zavese', 'Zavese i garnišne', 'Curtains']],
	// Građevinarstvo
	'doors' => [
		['interior', 'sobna-vrata', 'Sobna vrata', 'Interior doors'], ['entrance', 'ulazna-vrata', 'Ulazna i sigurnosna vrata', 'Entrance & security doors'],
		['garage', 'garazna-vrata', 'Garažna vrata', 'Garage doors'], ['shutters', 'roletne-i-komarnici', 'Roletne i komarnici', 'Shutters & screens'],
	],
	'insulation' => [
		['eps', 'stiropor-i-stirodur', 'Stiropor i stirodur', 'EPS & XPS'], ['wool', 'kamena-vuna', 'Kamena i staklena vuna', 'Mineral wool'],
		['facade', 'fasada-i-lepkovi', 'Fasada i lepkovi', 'Facade & adhesives'], ['drywall', 'gips-i-plafoni', 'Gips, plafoni i paneli', 'Drywall & ceilings'],
		['paint', 'boje-i-tapete', 'Boje i tapete', 'Paint & wallpaper'],
	],
	'flooring' => [['laminate', 'laminat', 'Laminat', 'Laminate'], ['parquet', 'parket', 'Parket', 'Parquet']],
	'bathroom' => [
		['showers', 'tus-kabine', 'Tuš kabine', 'Shower cabins'], ['bathtubs', 'kade', 'Kade', 'Bathtubs'],
		['toilets', 'wc-solje', 'WC šolje', 'Toilets'], ['sinks', 'lavaboi-i-baterije', 'Lavaboi i baterije', 'Sinks & taps'],
	],
	'heating' => [['boilers', 'kotlovi', 'Kotlovi', 'Boilers'], ['radiators', 'radijatori', 'Radijatori', 'Radiators'], ['stoves', 'peci-na-drva-i-pelet', 'Peći na drva i pelet', 'Wood & pellet stoves']],
	'firewood' => [['firewood', 'drva', 'Drva', 'Firewood'], ['pellets', 'pelet', 'Pelet', 'Pellets'], ['coal', 'ugalj', 'Ugalj', 'Coal'], ['briquettes', 'briket', 'Briket', 'Briquettes']],
	'prefab' => [['houses', 'montazne-kuce', 'Montažne kuće', 'Prefab houses'], ['containers', 'kontejneri', 'Kontejneri', 'Containers'], ['log-cabins', 'brvnare', 'Brvnare i drvene kuće', 'Log cabins']],
	'materials' => [
		['roofing', 'crep-i-krov', 'Crep, krov i oluci', 'Roofing & gutters'], ['bricks', 'cigla-i-blokovi', 'Cigla i blokovi', 'Bricks & blocks'],
		['cement', 'cement-i-malteri', 'Cement i malteri', 'Cement & mortar'], ['timber', 'gradja', 'Građa', 'Timber'],
	],
	'solar' => [['solar', 'solarni-paneli', 'Solarni paneli', 'Solar panels'], ['electrical', 'elektromaterijal', 'Elektromaterijal', 'Electrical supplies']],
	'security' => [['cameras', 'kamere', 'Kamere za video nadzor', 'CCTV cameras'], ['alarms', 'alarmi', 'Alarmi', 'Alarms']],
	// Mašine i alati
	'power-tools' => [
		['grinders', 'brusilice-i-testere', 'Brusilice i testere', 'Grinders & saws'], ['drills', 'busilice-i-odvijaci', 'Bušilice i odvijači', 'Drills & drivers'],
		['welders', 'aparati-za-varenje', 'Aparati za varenje', 'Welders'], ['hand-tools', 'rucni-alat', 'Ručni alat', 'Hand tools'],
		['ladders', 'skele-i-merdevine', 'Skele i merdevine', 'Scaffolding & ladders'],
	],
	'mowers' => [['mowers', 'kosilice', 'Kosilice', 'Lawn mowers'], ['trimmers', 'trimeri', 'Trimeri', 'Trimmers']],
	'generators-pumps' => [['generators', 'agregati', 'Agregati', 'Generators'], ['pumps', 'pumpe-za-vodu', 'Pumpe za vodu', 'Water pumps'], ['compressors', 'kompresori', 'Kompresori', 'Compressors']],
	'machinery' => [['construction', 'gradjevinske-masine', 'Građevinske mašine', 'Construction machinery'], ['industrial', 'industrijske-masine', 'Industrijske mašine', 'Industrial machinery'], ['forklifts', 'viljuskari', 'Viljuškari', 'Forklifts']],
	'catering-equipment' => [['refrigeration', 'rashladne-vitrine', 'Rashladne vitrine', 'Refrigerated displays'], ['catering', 'ugostiteljska-oprema', 'Ugostiteljska oprema', 'Catering equipment'], ['tills', 'fiskalne-kase', 'Fiskalne kase i POS', 'Tills & POS']],
	// Poljoprivreda
	'farm-machinery' => [
		['implements', 'prikljucne-masine', 'Priključne mašine', 'Implements'], ['tillers', 'motokultivatori-i-freze', 'Motokultivatori i freze', 'Tillers'],
		['combines', 'kombajni', 'Kombajni', 'Combine harvesters'], ['farm-trailers', 'traktorske-prikolice', 'Traktorske prikolice', 'Farm trailers'],
	],
	'livestock' => [
		['sheep-goats', 'ovce-i-koze', 'Ovce i koze', 'Sheep & goats'], ['horses', 'konji', 'Konji', 'Horses'], ['pigs', 'svinje-i-prasici', 'Svinje i prasići', 'Pigs'],
		['cattle', 'krave-i-telad', 'Krave i telad', 'Cattle'], ['poultry', 'zivina', 'Živina', 'Poultry'],
	],
	'plants' => [['fruit-trees', 'vocne-sadnice', 'Voćne sadnice', 'Fruit trees'], ['seedlings', 'rasad-i-seme', 'Rasad i seme', 'Seedlings & seeds'], ['ornamental', 'ukrasno-bilje', 'Ukrasno bilje i cveće', 'Ornamental plants & flowers']],
	'feed' => [['hay', 'seno-i-detelina', 'Seno i detelina', 'Hay & clover'], ['grain', 'kukuruz-i-zitarice', 'Kukuruz i žitarice', 'Maize & grain']],
	'farm-produce' => [['rakija', 'rakija', 'Rakija i vino', 'Rakija & wine'], ['honey', 'med', 'Med', 'Honey'], ['preserves', 'ajvar-i-zimnica', 'Ajvar i zimnica', 'Ajvar & preserves'], ['dairy', 'sir-i-mlecni', 'Sir i mlečni proizvodi', 'Cheese & dairy']],
	// Ljubimci
	'birds' => [['parrots', 'papagaji', 'Papagaji', 'Parrots'], ['pigeons', 'golubovi', 'Golubovi', 'Pigeons']],
	'pet-supplies' => [
		['food', 'hrana', 'Hrana za ljubimce', 'Pet food'], ['aquariums', 'akvarijumi', 'Akvarijumi', 'Aquariums'],
		['cages', 'kavezi-i-transporteri', 'Kavezi, transporteri i kućice', 'Cages & carriers'], ['beds-collars', 'lezaljke-i-povoci', 'Ležaljke, ogrlice i povoci', 'Beds, collars & leads'],
	],
	'pet-services' => [['boarding', 'pansion', 'Pansion za ljubimce', 'Boarding'], ['grooming', 'sisanje', 'Šišanje i nega', 'Grooming'], ['training', 'dresura', 'Dresura', 'Training']],
	// Moda: the same keys across subcategories (section pages gather them)
	'womens-clothing' => [
		['dresses', 'haljine', 'Haljine', 'Dresses'], ['jackets', 'jakne-i-kaputi', 'Jakne i kaputi', 'Jackets & coats'], ['tracksuits', 'trenerke', 'Trenerke i kompleti', 'Tracksuits'],
		['trousers', 'pantalone-i-farmerke', 'Pantalone, farmerke i helanke', 'Trousers & jeans'], ['tops', 'bluze-i-kosulje', 'Bluze, majice, košulje i džemperi', 'Tops, shirts & jumpers'],
		['skirts', 'suknje', 'Suknje', 'Skirts'], ['swimwear', 'kupaci-i-ves', 'Kupaći i donji veš', 'Swimwear & underwear'],
	],
	'mens-clothing' => [
		['jackets', 'jakne-i-kaputi', 'Jakne i kaputi', 'Jackets & coats'], ['tracksuits', 'trenerke', 'Trenerke', 'Tracksuits'], ['suits', 'odela-i-sakoi', 'Odela i sakoi', 'Suits & blazers'],
		['trousers', 'pantalone-i-farmerke', 'Pantalone i farmerke', 'Trousers & jeans'], ['tops', 'kosulje-i-majice', 'Košulje, majice i duksevi', 'Shirts, T-shirts & hoodies'],
	],
	'womens-shoes' => [
		['sneakers', 'patike', 'Patike', 'Sneakers'], ['boots', 'cizme', 'Čizme i gležnjače', 'Boots'],
		['shoes', 'cipele', 'Cipele, štikle i baletanke', 'Shoes & heels'], ['sandals', 'sandale-i-papuce', 'Sandale i papuče', 'Sandals & slippers'],
	],
	'mens-shoes' => [['sneakers', 'patike', 'Patike', 'Sneakers'], ['shoes', 'cipele', 'Cipele', 'Shoes'], ['boots', 'cizme', 'Čizme i duboke cipele', 'Boots'], ['sandals', 'sandale-i-papuce', 'Papuče i sandale', 'Sandals & slippers']],
	'wedding' => [['wedding-dresses', 'vencanice', 'Venčanice', 'Wedding dresses'], ['evening-dresses', 'svecane-haljine', 'Svečane haljine', 'Evening dresses'], ['suits', 'odela', 'Odela', 'Suits']],
	'watches' => [['womens', 'zenski-satovi', 'Ženski satovi', "Women's watches"], ['mens', 'muski-satovi', 'Muški satovi', "Men's watches"]],
	'bags' => [
		['womens-bags', 'zenske-torbe', 'Ženske torbe i tašne', "Women's bags"], ['mens-bags', 'muske-torbe', 'Muške torbe', "Men's bags"],
		['wallets', 'novcanici', 'Novčanici', 'Wallets'], ['backpacks', 'rancevi-i-koferi', 'Rančevi i koferi', 'Backpacks & suitcases'], ['belts', 'kaisevi', 'Kaiševi', 'Belts'],
	],
	// Deca
	'strollers' => [
		['umbrella', 'kisobran-kolica', 'Kišobran kolica', 'Umbrella strollers'], ['travel-systems', 'kolica-3-u-1', 'Kolica 3 u 1 i 2 u 1', 'Travel systems'],
		['twin', 'kolica-za-blizance', 'Kolica za blizance', 'Twin strollers'], ['sport', 'sportska-kolica', 'Sportska kolica', 'Sport strollers'],
	],
	'car-seats' => [['car-seats', 'auto-sedista', 'Auto sedišta', 'Car seats'], ['carriers', 'nosiljke', 'Nosiljke', 'Baby carriers']],
	'kids-furniture' => [['cots', 'krevetci-i-kolevke', 'Krevetci i kolevke', 'Cots & cradles'], ['kids-beds', 'decji-kreveti', 'Dečiji kreveti', 'Kids beds'], ['room', 'decja-soba', 'Nameštaj za dečiju sobu', 'Kids room furniture']],
	'baby-gear' => [['high-chairs', 'hranilice', 'Hranilice', 'High chairs'], ['bouncers', 'njihalice-i-lezaljke', 'Njihalice i ležaljke', 'Bouncers & rockers']],
	'toys' => [
		['lego', 'lego', 'LEGO i kocke', 'LEGO & bricks'], ['outdoor', 'ljuljaske-i-tobogani', 'Ljuljaške i tobogani', 'Swings & slides'],
		['dolls', 'lutke-i-plisane', 'Lutke i plišane igračke', 'Dolls & soft toys'], ['ride-on', 'autici-na-akumulator', 'Autići i vozila na akumulator', 'Ride-on cars'],
		['board-games', 'drustvene-igre', 'Društvene igre i slagalice', 'Board games & puzzles'],
	],
	'kids-bikes' => [['bikes', 'decji-bicikli', 'Dečiji bicikli', 'Kids bikes'], ['scooters', 'trotineti', 'Trotineti', 'Scooters']],
	// Sport
	'bikes' => [
		['e-bikes', 'elektricni-bicikli', 'Električni bicikli', 'E-bikes'], ['mtb', 'mtb', 'MTB i brdski', 'Mountain bikes'],
		['city', 'gradski-i-treking', 'Gradski i treking', 'City & trekking'], ['road', 'drumski', 'Drumski', 'Road bikes'],
		['parts', 'delovi-i-oprema', 'Delovi i oprema za bicikl', 'Parts & accessories'],
	],
	'fitness' => [['treadmills', 'trake-za-trcanje', 'Trake za trčanje', 'Treadmills'], ['weights', 'tegovi-i-bucice', 'Tegovi i bučice', 'Weights & dumbbells'], ['exercise-bikes', 'sobni-bicikli', 'Sobni bicikli i orbitreci', 'Exercise bikes & cross trainers']],
	'winter-sports' => [['skis', 'skije-i-pancerice', 'Skije i pancerice', 'Skis & boots'], ['snowboard', 'snowboard', 'Snowboard', 'Snowboard'], ['ski-wear', 'ski-odeca', 'Ski odeća', 'Ski wear'], ['sledges', 'sanke-i-klizaljke', 'Sanke i klizaljke', 'Sledges & ice skates']],
	// Lepota
	'perfumes' => [['womens', 'zenski-parfemi', 'Ženski parfemi', "Women's perfumes"], ['mens', 'muski-parfemi', 'Muški parfemi', "Men's perfumes"], ['arabic', 'arapski-parfemi', 'Arapski parfemi', 'Arabic perfumes']],
	'cosmetics' => [['hair-tools', 'fenovi-i-figaro', 'Fenovi i figaro', 'Hair dryers & stylers'], ['cosmetics', 'kozmetika', 'Kozmetika i nega', 'Cosmetics & care'], ['shavers', 'aparati-za-brijanje', 'Aparati za brijanje', 'Shavers']],
	'medical' => [['orthopedic', 'ortopedska-pomagala', 'Ortopedska pomagala', 'Orthopedic aids'], ['devices', 'medicinski-aparati', 'Medicinski aparati', 'Medical devices'], ['uniforms', 'medicinske-uniforme', 'Medicinske uniforme', 'Medical uniforms']],
	// Muzika
	'guitars' => [['acoustic', 'akusticne', 'Akustične i klasične', 'Acoustic & classical'], ['electric', 'elektricne', 'Električne', 'Electric'], ['bass', 'bas-gitare', 'Bas gitare', 'Bass'], ['amps', 'pojacala', 'Pojačala i efekti', 'Amps & effects']],
];

// Section pages that gather one kind from several subcategories (Moda without gender, 344k/month)
const SECTION_TYPES = [
	'fashion' => ['sneakers', 'tracksuits', 'jackets', 'trousers', 'boots', 'shoes', 'sandals'],
];

// Brands by subcategory: key (address word, the same in every language) => name
const CATEGORY_BRANDS = [
	'cars' => [
		'alfa-romeo' => 'Alfa Romeo', 'audi' => 'Audi', 'bmw' => 'BMW', 'byd' => 'BYD', 'chevrolet' => 'Chevrolet', 'chrysler' => 'Chrysler',
		'citroen' => 'Citroën', 'cupra' => 'Cupra', 'dacia' => 'Dacia', 'daewoo' => 'Daewoo', 'dodge' => 'Dodge', 'ds' => 'DS',
		'fiat' => 'Fiat', 'ford' => 'Ford', 'honda' => 'Honda', 'hyundai' => 'Hyundai', 'jaguar' => 'Jaguar', 'jeep' => 'Jeep',
		'kia' => 'Kia', 'lada' => 'Lada', 'lancia' => 'Lancia', 'land-rover' => 'Land Rover', 'lexus' => 'Lexus', 'mazda' => 'Mazda',
		'mercedes' => 'Mercedes-Benz', 'mg' => 'MG', 'mini' => 'Mini', 'mitsubishi' => 'Mitsubishi', 'nissan' => 'Nissan', 'opel' => 'Opel',
		'peugeot' => 'Peugeot', 'porsche' => 'Porsche', 'renault' => 'Renault', 'rover' => 'Rover', 'saab' => 'Saab', 'seat' => 'Seat',
		'skoda' => 'Škoda', 'smart' => 'Smart', 'ssangyong' => 'SsangYong', 'subaru' => 'Subaru', 'suzuki' => 'Suzuki', 'tesla' => 'Tesla',
		'toyota' => 'Toyota', 'volkswagen' => 'Volkswagen', 'volvo' => 'Volvo', 'zastava' => 'Zastava',
	],
	'motorcycles' => [
		'aprilia' => 'Aprilia', 'bmw' => 'BMW', 'ducati' => 'Ducati', 'harley-davidson' => 'Harley-Davidson', 'honda' => 'Honda',
		'husqvarna' => 'Husqvarna', 'kawasaki' => 'Kawasaki', 'ktm' => 'KTM', 'kymco' => 'Kymco', 'piaggio' => 'Piaggio', 'suzuki' => 'Suzuki',
		'sym' => 'SYM', 'tomos' => 'Tomos', 'triumph' => 'Triumph', 'vespa' => 'Vespa', 'yamaha' => 'Yamaha',
	],
	'phones' => [
		'iphone' => 'Apple iPhone', 'samsung' => 'Samsung', 'xiaomi' => 'Xiaomi', 'huawei' => 'Huawei', 'honor' => 'Honor', 'nokia' => 'Nokia',
		'motorola' => 'Motorola', 'google' => 'Google Pixel', 'oneplus' => 'OnePlus', 'oppo' => 'Oppo', 'realme' => 'Realme', 'vivo' => 'Vivo',
		'sony' => 'Sony', 'lg' => 'LG', 'alcatel' => 'Alcatel', 'zte' => 'ZTE', 'nothing' => 'Nothing',
	],
	'laptops' => [
		'apple' => 'Apple MacBook', 'lenovo' => 'Lenovo', 'hp' => 'HP', 'dell' => 'Dell', 'asus' => 'Asus', 'acer' => 'Acer',
		'msi' => 'MSI', 'microsoft' => 'Microsoft Surface', 'huawei' => 'Huawei', 'samsung' => 'Samsung', 'toshiba' => 'Toshiba',
	],
	'tractors' => [
		'imt' => 'IMT', 'belarus' => 'Belarus', 'zetor' => 'Zetor', 'torpedo' => 'Torpedo', 'rakovica' => 'Rakovica', 'tomo-vinkovic' => 'Tomo Vinković',
		'john-deere' => 'John Deere', 'massey-ferguson' => 'Massey Ferguson', 'new-holland' => 'New Holland', 'fiat' => 'Fiat', 'same' => 'Same',
		'landini' => 'Landini', 'lamborghini' => 'Lamborghini', 'deutz-fahr' => 'Deutz-Fahr', 'ursus' => 'Ursus', 'claas' => 'Claas',
		'case-ih' => 'Case IH', 'fendt' => 'Fendt', 'kubota' => 'Kubota', 'valtra' => 'Valtra',
	],
];

// Seller: private person or a business (car dealer, agency). A setting of the account (users.seller_type,
// 035), copied to its listings (listings.seller_type, the "Advertiser" filter)
const SELLER_TYPES = ['private', 'business'];

/* ---------- Lookups ---------- */

// Deals of a subcategory (empty = no deal asked)
function category_deals(string $slug): array
{
	return CATEGORY_DEALS[$slug] ?? [];
}

// Deal key by an address word of either language, or null
function deal_by_word(string $word): ?string
{
	foreach (DEALS as $key => $words) {
		if (in_array($word, $words, true)) {
			return $key;
		}
	}

	return null;
}

// Address word of a deal in the sr | en set
function deal_word(string $deal, string $set): string
{
	return DEALS[$deal][$set === 'en' ? 1 : 0];
}

// Kinds of a subcategory: key => ['sr' => word, 'en' => word, 'name' => ['sr', 'en']], "other" last; empty = not asked
function category_types(string $slug): array
{
	static $cache = [];

	if (!isset($cache[$slug])) {
		$types = [];
		foreach (CATEGORY_TYPES[$slug] ?? [] as [$key, $sr, $nameSr, $nameEn]) {
			$types[$key] = ['sr' => $sr, 'en' => $key, 'name' => ['sr' => $nameSr, 'en' => $nameEn]];
		}
		if ($types) {
			$types['other'] = ['sr' => 'ostalo', 'en' => 'other', 'name' => ['sr' => 'Ostalo', 'en' => 'Other']];
		}
		$cache[$slug] = $types;
	}

	return $cache[$slug];
}

// Brands of a subcategory: key => name (empty = not asked)
function category_brands(string $slug): array
{
	return CATEGORY_BRANDS[$slug] ?? [];
}

// Kind keys a section page gathers (SECTION_TYPES) with the words and names of their first occurrence
function section_types(string $sectionSlug, array $childSlugs): array
{
	$types = [];

	foreach (SECTION_TYPES[$sectionSlug] ?? [] as $key) {
		foreach ($childSlugs as $child) {
			if (isset(category_types($child)[$key])) {
				$types[$key] ??= category_types($child)[$key];
			}
		}
	}

	return $types;
}

// Name of a kind in the current language (Cyrillic converted)
function type_name(array $type): string
{
	return match (lang()) {
		'en' => $type['name']['en'],
		'sr-cyrl' => sr_cyr($type['name']['sr']),
		default => $type['name']['sr'],
	};
}

// Sections whose listings may name a district of the city (decided 09.11: real estate only)
const DISTRICT_SECTIONS = ['real-estate'];

// Label of the kind select: the seaside asks for the country
const TYPE_LABELS = ['seaside' => 'post.f_country'];

// Deal, kind, brand and district of a form for subcategory $slug of section $section in $city
// (Latin name): [values (deal, type, brand, district; null = none), error key or ''].
// Not strict (a draft, a staff edit) drops what does not fit instead of reporting it
function clean_facets(string $slug, string $section, string $city, array $in, bool $strict = true): array
{
	$out = ['deal' => null, 'type' => null, 'brand' => null, 'district' => null];
	$pick = fn(string $key) => is_scalar($in[$key] ?? null) ? (string) $in[$key] : '';

	if ($deals = category_deals($slug)) {
		if (in_array($pick('deal'), $deals, true)) {
			$out['deal'] = $pick('deal');
		} elseif ($strict) {
			return [$out, 'post.err_deal'];
		}
	}
	if (category_types($slug)) {
		if (isset(category_types($slug)[$pick('type')])) {
			$out['type'] = $pick('type');
		} elseif ($strict) {
			return [$out, 'post.err_type'];
		}
	}
	if ($brands = category_brands($slug)) {
		if (isset($brands[$pick('brand')]) || $pick('brand') === 'other') {
			$out['brand'] = $pick('brand');
		} elseif ($strict) {
			return [$out, 'post.err_brand'];
		}
	}
	// A district is optional: one of the city's, else none
	if (in_array($section, DISTRICT_SECTIONS, true) && in_array($pick('district'), array_column(districts()[$city] ?? [], 'latn'), true)) {
		$out['district'] = $pick('district');
	}
	return [$out, ''];
}

// Name of a brand of a subcategory ("other" = Druga marka), null when unknown
function brand_name(string $slug, ?string $brand): ?string
{
	if ($brand === null) {
		return null;
	}

	return $brand === 'other' ? t('post.brand_other') : (category_brands($slug)[$brand] ?? null);
}

// Headings of real estate deal pages: subcategory => deal => [Serbian, English] (the Serbian
// form follows the search: "prodaja stanova", "izdavanje kuća", "stan na dan")
const DEAL_H1 = [
	'apartments' => ['sale' => ['Prodaja stanova', 'Apartments for sale'], 'rent' => ['Izdavanje stanova', 'Apartments for rent'], 'daily' => ['Stan na dan', 'Apartments for daily rent']],
	'houses' => ['sale' => ['Prodaja kuća', 'Houses for sale'], 'rent' => ['Izdavanje kuća', 'Houses for rent'], 'daily' => ['Kuće na dan', 'Houses for daily rent']],
	'cottages' => ['sale' => ['Prodaja vikendica', 'Cottages for sale'], 'rent' => ['Izdavanje vikendica', 'Cottages for rent'], 'daily' => ['Vikendice na dan', 'Cottages for daily rent']],
	'land' => ['sale' => ['Prodaja placeva i zemljišta', 'Land for sale'], 'rent' => ['Zakup placeva i zemljišta', 'Land for lease']],
	'commercial' => ['sale' => ['Prodaja poslovnog prostora', 'Commercial property for sale'], 'rent' => ['Izdavanje poslovnog prostora', 'Commercial property for rent']],
	'garages' => ['sale' => ['Prodaja garaža i parking mesta', 'Garages for sale'], 'rent' => ['Izdavanje garaža i parking mesta', 'Garages for rent']],
	'rooms' => ['rent' => ['Izdavanje soba', 'Rooms for rent'], 'daily' => ['Sobe na dan', 'Rooms for daily rent']],
];
