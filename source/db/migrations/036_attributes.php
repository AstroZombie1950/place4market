<?php
/* ==========================================================================
	036_attributes: listing details (characteristics) move from the code
	(attrs.php) to the database, edited in admin → Characteristics (20.11,
	request of the client: new fields for rentals without a deploy).
	- attributes: one detail. code — its key in listings.attrs (never changes),
	  type number | select | text | bool, names in three languages, config (JSON):
	    number  min, max (or max_next_year: years), step, unit {en, sr-latn, sr-cyrl}
	    select  options [{key, names {…}, hidden}], raw (stored without "v.": energy class)
	    text    max (length), raw (a name: never put into Cyrillic)
	    bool    switch (a switch, not a checkbox), row + off {…}: shown either way,
	            "Building: New build / Resale", the filter offers both
	  in_filter — offered in the filters of the results page
	- category_attributes: which details a subcategory asks, in order; deals —
	  only with these deals (space-separated, NULL = any), options — the part of
	  a list offered here (NULL = all: body types of cars and of motorbikes)
	- categories.has_condition: whether new / used is asked (0: property, jobs…)
	Filled with what attrs.php had, the same translations, and three new
	details of rentals (client, 20.11): deposit, contract, fixed price — for
	apartments, houses, cottages and rooms, with rent and daily rent.
	The previous code does not read the tables nor the column: safe before the
	release. listings.attrs stays as it is.
	========================================================================== */

return <<<'SQL'
CREATE TABLE attributes (
	id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	code VARCHAR(32) NOT NULL,
	type ENUM('number', 'select', 'text', 'bool') NOT NULL,
	name_en VARCHAR(100) NOT NULL,
	name_sr_latn VARCHAR(100) NOT NULL,
	name_sr_cyrl VARCHAR(100) NOT NULL,
	config JSON NOT NULL,
	in_filter TINYINT(1) NOT NULL DEFAULT 0,
	sort SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_attributes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE category_attributes (
	category_id SMALLINT UNSIGNED NOT NULL,
	attribute_id SMALLINT UNSIGNED NOT NULL,
	sort SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	deals VARCHAR(64) NULL,
	options JSON NULL,
	PRIMARY KEY (category_id, attribute_id),
	KEY idx_category_attributes_attribute (attribute_id),
	CONSTRAINT fk_category_attributes_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
	CONSTRAINT fk_category_attributes_attribute FOREIGN KEY (attribute_id) REFERENCES attributes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE categories
	ADD COLUMN has_condition TINYINT(1) NOT NULL DEFAULT 1 AFTER active;

INSERT INTO attributes (code, type, name_en, name_sr_latn, name_sr_cyrl, config, in_filter, sort) VALUES
	('model', 'text', 'Model', 'Model', 'Модел', '{"max":40,"raw":true}', 0, 10),
	('year', 'number', 'Year', 'Godište', 'Годиште', '{"min":1900,"max_next_year":true}', 1, 20),
	('mileage', 'number', 'Mileage', 'Kilometraža', 'Километража', '{"min":0,"max":999999,"unit":{"en":"km","sr-latn":"km","sr-cyrl":"km"}}', 1, 30),
	('fuel', 'select', 'Fuel', 'Gorivo', 'Гориво', '{"options":[{"key":"petrol","names":{"en":"Petrol","sr-latn":"Benzin","sr-cyrl":"Бензин"}},{"key":"diesel","names":{"en":"Diesel","sr-latn":"Dizel","sr-cyrl":"Дизел"}},{"key":"lpg","names":{"en":"LPG","sr-latn":"TNG (plin)","sr-cyrl":"TNG (плин)"}},{"key":"hybrid","names":{"en":"Hybrid","sr-latn":"Hibrid","sr-cyrl":"Хибрид"}},{"key":"electric","names":{"en":"Electric","sr-latn":"Električni","sr-cyrl":"Електрични"}}]}', 1, 40),
	('gearbox', 'select', 'Transmission', 'Menjač', 'Мењач', '{"options":[{"key":"manual","names":{"en":"Manual","sr-latn":"Manuelni","sr-cyrl":"Мануелни"}},{"key":"automatic","names":{"en":"Automatic","sr-latn":"Automatski","sr-cyrl":"Аутоматски"}}]}', 1, 50),
	('power', 'number', 'Engine power', 'Snaga motora', 'Снага мотора', '{"min":1,"max":2000,"unit":{"en":"kW","sr-latn":"kW","sr-cyrl":"kW"}}', 1, 60),
	('engine', 'number', 'Engine', 'Motor', 'Мотор', '{"min":1,"max":10000,"unit":{"en":"cm³","sr-latn":"cm³","sr-cyrl":"cm³"}}', 0, 70),
	('body', 'select', 'Body type', 'Karoserija', 'Каросерија', '{"options":[{"key":"sedan","names":{"en":"Sedan","sr-latn":"Limuzina","sr-cyrl":"Лимузина"}},{"key":"hatchback","names":{"en":"Hatchback","sr-latn":"Hečbek","sr-cyrl":"Хечбек"}},{"key":"wagon","names":{"en":"Estate","sr-latn":"Karavan","sr-cyrl":"Караван"}},{"key":"suv","names":{"en":"SUV","sr-latn":"Džip / SUV","sr-cyrl":"Џип / SUV"}},{"key":"coupe","names":{"en":"Coupé","sr-latn":"Kupe","sr-cyrl":"Купе"}},{"key":"convertible","names":{"en":"Convertible","sr-latn":"Kabriolet","sr-cyrl":"Кабриолет"}},{"key":"minivan","names":{"en":"Minivan","sr-latn":"Monovolumen","sr-cyrl":"Моноволумен"}},{"key":"pickup","names":{"en":"Pickup","sr-latn":"Pikap","sr-cyrl":"Пикап"}},{"key":"other","names":{"en":"Other","sr-latn":"Ostalo","sr-cyrl":"Остало"}},{"key":"naked","names":{"en":"Naked","sr-latn":"Naked","sr-cyrl":"Naked"}},{"key":"sport","names":{"en":"Sport","sr-latn":"Sport","sr-cyrl":"Спорт"}},{"key":"touring","names":{"en":"Touring","sr-latn":"Turing","sr-cyrl":"Туринг"}},{"key":"enduro","names":{"en":"Enduro","sr-latn":"Enduro","sr-cyrl":"Ендуро"}},{"key":"scooter","names":{"en":"Scooter","sr-latn":"Skuter","sr-cyrl":"Скутер"}},{"key":"chopper","names":{"en":"Chopper","sr-latn":"Čoper","sr-cyrl":"Чопер"}}]}', 1, 80),
	('color', 'select', 'Color', 'Boja', 'Боја', '{"options":[{"key":"white","names":{"en":"White","sr-latn":"Bela","sr-cyrl":"Бела"}},{"key":"black","names":{"en":"Black","sr-latn":"Crna","sr-cyrl":"Црна"}},{"key":"grey","names":{"en":"Grey","sr-latn":"Siva","sr-cyrl":"Сива"}},{"key":"silver","names":{"en":"Silver","sr-latn":"Srebrna","sr-cyrl":"Сребрна"}},{"key":"blue","names":{"en":"Blue","sr-latn":"Plava","sr-cyrl":"Плава"}},{"key":"red","names":{"en":"Red","sr-latn":"Crvena","sr-cyrl":"Црвена"}},{"key":"green","names":{"en":"Green","sr-latn":"Zelena","sr-cyrl":"Зелена"}},{"key":"yellow","names":{"en":"Yellow","sr-latn":"Žuta","sr-cyrl":"Жута"}},{"key":"brown","names":{"en":"Brown","sr-latn":"Braon","sr-cyrl":"Браон"}},{"key":"beige","names":{"en":"Beige","sr-latn":"Bež","sr-cyrl":"Беж"}},{"key":"other","names":{"en":"Other","sr-latn":"Ostalo","sr-cyrl":"Остало"}}]}', 0, 90),
	('area', 'number', 'Area', 'Kvadratura', 'Квадратура', '{"min":1,"max":100000,"unit":{"en":"m²","sr-latn":"m²","sr-cyrl":"m²"}}', 1, 100),
	('plot', 'number', 'Plot size', 'Površina placa', 'Површина плаца', '{"min":1,"max":1000000,"unit":{"en":"ares","sr-latn":"ari","sr-cyrl":"ари"}}', 1, 110),
	('rooms', 'number', 'Rooms', 'Broj soba', 'Број соба', '{"min":0.5,"max":50,"step":0.5}', 1, 120),
	('floor', 'text', 'Floor', 'Sprat', 'Спрат', '{"max":12}', 0, 130),
	('heating', 'select', 'Heating', 'Grejanje', 'Грејање', '{"options":[{"key":"central","names":{"en":"Central","sr-latn":"Centralno","sr-cyrl":"Централно"}},{"key":"gas","names":{"en":"Gas","sr-latn":"Gas","sr-cyrl":"Гас"}},{"key":"electricity","names":{"en":"Electric","sr-latn":"Struja","sr-cyrl":"Струја"}},{"key":"storage","names":{"en":"Storage heater","sr-latn":"Termoakumulaciona peć","sr-cyrl":"Термоакумулациона пећ"}},{"key":"heat_pump","names":{"en":"Heat pump","sr-latn":"Toplotna pumpa","sr-cyrl":"Топлотна пумпа"}},{"key":"solid_fuel","names":{"en":"Solid fuel","sr-latn":"Čvrsto gorivo","sr-cyrl":"Чврсто гориво"}},{"key":"no_heating","names":{"en":"No heating","sr-latn":"Bez grejanja","sr-cyrl":"Без грејања"}}]}', 1, 140),
	('built', 'number', 'Year built', 'Godina izgradnje', 'Година изградње', '{"min":1800,"max_next_year":true}', 0, 150),
	('furnished', 'select', 'Furnished', 'Nameštenost', 'Намештеност', '{"options":[{"key":"yes","names":{"en":"Yes","sr-latn":"Da","sr-cyrl":"Да"}},{"key":"partly","names":{"en":"Partly","sr-latn":"Polunamešten","sr-cyrl":"Полунамештен"}},{"key":"no","names":{"en":"No","sr-latn":"Ne","sr-cyrl":"Не"}}]}', 1, 160),
	('memory', 'number', 'Storage', 'Memorija', 'Меморија', '{"min":1,"max":100000,"unit":{"en":"GB","sr-latn":"GB","sr-cyrl":"GB"}}', 1, 170),
	('battery', 'number', 'Battery health', 'Stanje baterije', 'Стање батерије', '{"min":1,"max":100,"unit":{"en":"%","sr-latn":"%","sr-cyrl":"%"}}', 0, 180),
	('wheel', 'text', 'Wheel size', 'Veličina točka', 'Величина точка', '{"max":12}', 0, 190),
	('frame', 'text', 'Frame size', 'Veličina rama', 'Величина рама', '{"max":12}', 0, 200),
	('gears', 'number', 'Gears', 'Broj brzina', 'Број брзина', '{"min":1,"max":40}', 0, 210),
	('size', 'text', 'Size', 'Veličina', 'Величина', '{"max":12}', 0, 220),
	('dimensions', 'text', 'Dimensions', 'Dimenzije', 'Димензије', '{"max":40}', 0, 230),
	('ram', 'number', 'RAM', 'RAM', 'RAM', '{"min":1,"max":2048,"unit":{"en":"GB","sr-latn":"GB","sr-cyrl":"GB"}}', 1, 240),
	('ssd', 'number', 'SSD', 'SSD', 'SSD', '{"min":1,"max":100000,"unit":{"en":"GB","sr-latn":"GB","sr-cyrl":"GB"}}', 1, 250),
	('capacity', 'text', 'Capacity', 'Kapacitet', 'Капацитет', '{"max":20}', 0, 260),
	('energy', 'select', 'Energy class', 'Energetski razred', 'Енергетски разред', '{"raw":true,"options":[{"key":"A+++","names":{"en":"A+++","sr-latn":"A+++","sr-cyrl":"A+++"}},{"key":"A++","names":{"en":"A++","sr-latn":"A++","sr-cyrl":"A++"}},{"key":"A+","names":{"en":"A+","sr-latn":"A+","sr-cyrl":"A+"}},{"key":"A","names":{"en":"A","sr-latn":"A","sr-cyrl":"A"}},{"key":"B","names":{"en":"B","sr-latn":"B","sr-cyrl":"B"}},{"key":"C","names":{"en":"C","sr-latn":"C","sr-cyrl":"C"}},{"key":"D","names":{"en":"D","sr-latn":"D","sr-cyrl":"D"}}]}', 1, 270),
	('length', 'number', 'Length', 'Dužina', 'Дужина', '{"min":1,"max":500,"unit":{"en":"cm","sr-latn":"cm","sr-cyrl":"cm"}}', 0, 280),
	('material', 'select', 'Material', 'Materijal', 'Материјал', '{"options":[{"key":"wood","names":{"en":"Wood","sr-latn":"Drvo","sr-cyrl":"Дрво"}},{"key":"metal","names":{"en":"Metal","sr-latn":"Metal","sr-cyrl":"Метал"}},{"key":"plastic","names":{"en":"Plastic","sr-latn":"Plastika","sr-cyrl":"Пластика"}},{"key":"glass","names":{"en":"Glass","sr-latn":"Staklo","sr-cyrl":"Стакло"}},{"key":"fabric","names":{"en":"Fabric","sr-latn":"Tkanina","sr-cyrl":"Тканина"}},{"key":"leather","names":{"en":"Leather","sr-latn":"Koža","sr-cyrl":"Кожа"}},{"key":"other","names":{"en":"Other","sr-latn":"Ostalo","sr-cyrl":"Остало"}}]}', 0, 290),
	('age', 'text', 'Age', 'Uzrast', 'Узраст', '{"max":20}', 0, 300),
	('experience', 'select', 'Experience', 'Iskustvo', 'Искуство', '{"options":[{"key":"years_1","names":{"en":"1+ years","sr-latn":"1+ godina","sr-cyrl":"1+ година"}},{"key":"years_3","names":{"en":"3+ years","sr-latn":"3+ godine","sr-cyrl":"3+ године"}},{"key":"years_5","names":{"en":"5+ years","sr-latn":"5+ godina","sr-cyrl":"5+ година"}},{"key":"years_10","names":{"en":"10+ years","sr-latn":"10+ godina","sr-cyrl":"10+ година"}},{"key":"years_15","names":{"en":"15+ years","sr-latn":"15+ godina","sr-cyrl":"15+ година"}}]}', 0, 310),
	('area_served', 'text', 'Area served', 'Područje rada', 'Подручје рада', '{"max":60}', 0, 320),
	('controllers', 'number', 'Controllers', 'Džojstici', 'Џојстици', '{"min":0,"max":10}', 0, 330),
	('employment', 'select', 'Employment type', 'Vrsta zaposlenja', 'Врста запослења', '{"options":[{"key":"full_time","names":{"en":"Full time","sr-latn":"Puno radno vreme","sr-cyrl":"Пуно радно време"}},{"key":"part_time","names":{"en":"Part time","sr-latn":"Skraćeno radno vreme","sr-cyrl":"Скраћено радно време"}},{"key":"temporary","names":{"en":"Temporary","sr-latn":"Privremeni posao","sr-cyrl":"Привремени посао"}},{"key":"freelance","names":{"en":"Freelance","sr-latn":"Honorarno","sr-cyrl":"Хонорарно"}}]}', 1, 340),
	('damaged', 'bool', 'Damaged / for parts', 'Oštećen / havarisan', 'Оштећен / хаварисан', '{}', 1, 350),
	('financing', 'bool', 'Instalments or leasing possible', 'Moguće na rate / lizing', 'Могуће на рате / лизинг', '{}', 1, 360),
	('new_build', 'bool', 'New build', 'Novogradnja', 'Новоградња', '{"switch":true,"row":{"en":"Building","sr-latn":"Gradnja","sr-cyrl":"Градња"},"off":{"en":"Resale","sr-latn":"Starogradnja","sr-cyrl":"Староградња"}}', 1, 370),
	('season', 'select', 'Season', 'Sezona', 'Сезона', '{"options":[{"key":"summer","names":{"en":"Summer","sr-latn":"Letnje","sr-cyrl":"Летње"}},{"key":"winter","names":{"en":"Winter","sr-latn":"Zimske","sr-cyrl":"Зимске"}},{"key":"all_season","names":{"en":"All-season","sr-latn":"Za sve sezone","sr-cyrl":"За све сезоне"}}]}', 1, 380),
	('tyre_size', 'text', 'Tyre size', 'Dimenzija gume', 'Димензија гуме', '{"max":20}', 0, 390),
	('diagonal', 'number', 'Screen size', 'Dijagonala', 'Дијагонала', '{"min":10,"max":150,"unit":{"en":"″","sr-latn":"″","sr-cyrl":"″"}}', 1, 400),
	('guests', 'number', 'Guests', 'Broj osoba', 'Број особа', '{"min":1,"max":50}', 1, 410),
	('hours', 'number', 'Working hours', 'Radni sati', 'Радни сати', '{"min":0,"max":100000,"unit":{"en":"h","sr-latn":"h","sr-cyrl":"х"}}', 0, 420),
	('breed', 'text', 'Breed', 'Rasa', 'Раса', '{"max":40}', 0, 430),
	('deposit', 'bool', 'Deposit', 'Depozit', 'Депозит', '{}', 1, 440),
	('contract', 'bool', 'Contract', 'Ugovor', 'Уговор', '{}', 1, 450),
	('fixed_price', 'bool', 'Fixed price', 'Fiksna cena', 'Фиксна цена', '{}', 1, 460);

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'model' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'year' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'mileage' AS code, 30 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'fuel' AS code, 40 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'gearbox' AS code, 50 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'power' AS code, 60 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'body' AS code, 70 AS sort, NULL AS deals, '["sedan","hatchback","wagon","suv","coupe","convertible","minivan","pickup","other"]' AS options
		UNION ALL SELECT 'color' AS code, 80 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'damaged' AS code, 90 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'financing' AS code, 100 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'cars';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'model' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'year' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'mileage' AS code, 30 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'engine' AS code, 40 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'body' AS code, 50 AS sort, NULL AS deals, '["naked","sport","touring","enduro","scooter","chopper","other"]' AS options
		UNION ALL SELECT 'color' AS code, 60 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'motorcycles';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'year' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'mileage' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'fuel' AS code, 30 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'gearbox' AS code, 40 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'power' AS code, 50 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'damaged' AS code, 60 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'financing' AS code, 70 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'trucks-vans';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'year' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'trailers';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'year' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'length' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'boats';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'season' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'tyre_size' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'tyres-wheels';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'size' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'motorcycle-gear';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'area' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'rooms' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'floor' AS code, 30 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'heating' AS code, 40 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'built' AS code, 50 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'furnished' AS code, 60 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'new_build' AS code, 70 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'deposit' AS code, 80 AS sort, 'rent daily' AS deals, NULL AS options
		UNION ALL SELECT 'contract' AS code, 90 AS sort, 'rent daily' AS deals, NULL AS options
		UNION ALL SELECT 'fixed_price' AS code, 100 AS sort, 'rent daily' AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'apartments';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'area' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'plot' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'rooms' AS code, 30 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'heating' AS code, 40 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'built' AS code, 50 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'furnished' AS code, 60 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'new_build' AS code, 70 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'deposit' AS code, 80 AS sort, 'rent daily' AS deals, NULL AS options
		UNION ALL SELECT 'contract' AS code, 90 AS sort, 'rent daily' AS deals, NULL AS options
		UNION ALL SELECT 'fixed_price' AS code, 100 AS sort, 'rent daily' AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'houses';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'area' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'plot' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'rooms' AS code, 30 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'heating' AS code, 40 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'deposit' AS code, 50 AS sort, 'rent daily' AS deals, NULL AS options
		UNION ALL SELECT 'contract' AS code, 60 AS sort, 'rent daily' AS deals, NULL AS options
		UNION ALL SELECT 'fixed_price' AS code, 70 AS sort, 'rent daily' AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'cottages';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'plot' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'land';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'area' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'floor' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'heating' AS code, 30 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'commercial';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'area' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'garages';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'area' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'furnished' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'deposit' AS code, 30 AS sort, 'rent daily' AS deals, NULL AS options
		UNION ALL SELECT 'contract' AS code, 40 AS sort, 'rent daily' AS deals, NULL AS options
		UNION ALL SELECT 'fixed_price' AS code, 50 AS sort, 'rent daily' AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'rooms';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'guests' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'mountains';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'guests' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'spas';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'guests' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'seaside';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'guests' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'lakes-rivers';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'guests' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'rural';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'remote-jobs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'drivers-delivery';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'jobs-abroad';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'student-jobs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'hospitality';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'construction-jobs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'production-jobs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'care-jobs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'sales-jobs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'office-jobs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'beauty-jobs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'employment' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'other-jobs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'experience' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'area_served' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'car-service';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'area_served' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'car-rental';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'experience' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'area_served' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'events';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'experience' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'area_served' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'beauty-services';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'experience' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'area_served' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'tradesmen';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'experience' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'area_served' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'lessons';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'area_served' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'towing';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'experience' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'area_served' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'repairs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'experience' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'area_served' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'business-services';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'area_served' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'moving';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'experience' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'area_served' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'cleaning';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'experience' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'area_served' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'other-services';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'memory' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'battery' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 30 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'phones';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'memory' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'tablets';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'ram' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'ssd' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'battery' AS code, 30 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'laptops';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'ram' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'ssd' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'desktops';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'memory' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'controllers' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'consoles';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'diagonal' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'tv';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'capacity' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'energy' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'fridges';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'capacity' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'energy' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'washing-machines';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'capacity' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'energy' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'dishwashers';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'energy' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'cookers';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'capacity' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'energy' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'air-conditioners';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'capacity' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'water-heaters';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'material' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'dimensions' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 30 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'sofas';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'material' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'dimensions' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 30 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'beds';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'material' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'dimensions' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 30 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'tables-chairs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'material' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'dimensions' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 30 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'wardrobes';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'material' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'dimensions' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 30 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'garden-furniture';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'dimensions' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'kitchens';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'model' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'year' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'power' AS code, 30 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'hours' AS code, 40 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'tractors';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'year' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'farm-machinery';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'breed' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'age' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'livestock';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'breed' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'age' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'dogs';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'breed' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'age' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'cats';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'breed' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'age' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'birds';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'breed' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'age' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'other-pets';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'experience' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'area_served' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'pet-services';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'size' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'womens-clothing';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'size' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'mens-clothing';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'size' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'womens-shoes';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'size' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'mens-shoes';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'size' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'sports-shoes';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'size' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'wedding';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'size' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'workwear';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'size' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'costumes';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'age' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'strollers';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'age' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'material' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'toys';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'size' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'kids-clothing';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'size' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'color' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'kids-shoes';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'wheel' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'kids-bikes';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'age' AS code, 10 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'car-seats';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'wheel' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'frame' AS code, 20 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'gears' AS code, 30 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'bikes';

INSERT INTO category_attributes (category_id, attribute_id, sort, deals, options)
	SELECT c.id, a.id, x.sort, x.deals, x.options FROM categories c
	JOIN (SELECT 'length' AS code, 10 AS sort, NULL AS deals, NULL AS options
		UNION ALL SELECT 'size' AS code, 20 AS sort, NULL AS deals, NULL AS options) x
	JOIN attributes a ON a.code = x.code
	WHERE c.slug = 'winter-sports';

UPDATE categories SET has_condition = 0 WHERE slug IN ('apartments', 'houses', 'cottages', 'land', 'commercial', 'garages', 'rooms', 'mountains', 'spas', 'seaside', 'lakes-rivers', 'rural', 'remote-jobs', 'drivers-delivery', 'jobs-abroad', 'student-jobs', 'hospitality', 'construction-jobs', 'production-jobs', 'care-jobs', 'sales-jobs', 'office-jobs', 'beauty-jobs', 'other-jobs', 'car-service', 'car-rental', 'events', 'beauty-services', 'tradesmen', 'lessons', 'towing', 'repairs', 'business-services', 'moving', 'cleaning', 'other-services', 'livestock', 'plants', 'feed', 'farm-produce', 'dogs', 'cats', 'birds', 'other-pets', 'pet-services', 'tickets');
SQL;
