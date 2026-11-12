<?php
/* ==========================================================================
	Privacy policy (with cookies). Written 11.11.2026 from what the site really
	does (README: accounts, listings, photos, messages, log, storage, analytics,
	mail); the client and the client's lawyer finish it: [brackets] are to fill.
	Periods of section 7 follow the code (RETENTION_MONTHS in repo.php, log 90 days, backups 14 days).
	Update it when a processor is added (mail service, cloud storage).
	Values are ['en' => …, 'sr' => …]; Cyrillic is made on the fly (tc())
	========================================================================== */

return [
	'updated' => '2026-11-11',
	'lead' => [
		'en' => 'This policy explains what personal data Place4Market collects, why, how long we keep it, who else processes it and what rights you have under the Law on Personal Data Protection of the Republic of Serbia (ZZPL).',
		'sr' => 'Ova politika objašnjava koje lične podatke Place4Market prikuplja, zašto, koliko dugo ih čuvamo, ko ih još obrađuje i koja prava imate prema Zakonu o zaštiti podataka o ličnosti Republike Srbije (ZZPL).',
	],

	'sections' => [
		[
			'id' => 'controller',
			'title' => ['en' => '1. Who is responsible', 'sr' => '1. Ko je odgovoran'],
			'blocks' => [
				['en' => 'The controller of your data is [Company name], [address], company ID (MB) [number], tax ID (PIB) [number]. For any question about your data, write to support@place4market.com or use the contact page.', 'sr' => 'Rukovalac vašim podacima je [Naziv firme], [adresa], matični broj [broj], PIB [broj]. Za sva pitanja o vašim podacima pišite na support@place4market.com ili preko stranice za kontakt.'],
			],
		],
		[
			'id' => 'data',
			'title' => ['en' => '2. What data we collect', 'sr' => '2. Koje podatke prikupljamo'],
			'blocks' => [
				['list' => [
					['en' => 'Account: name, email, phone number if you enter it, password (stored only as a one-way hash, never readable), site language and the notifications you have turned on.', 'sr' => 'Nalog: ime, e-pošta, broj telefona ako ga unesete, lozinka (čuva se samo kao jednosmerni heš, nikad u čitljivom obliku), jezik sajta i obaveštenja koja ste uključili.'],
					['en' => 'Listings: title, description, category, price, place and part of the city, photos and videos. Before a photo is stored we remove all its metadata, including the location where it was taken.', 'sr' => 'Oglasi: naslov, opis, kategorija, cena, mesto i deo grada, fotografije i video. Pre čuvanja fotografije uklanjamo sve njene metapodatke, uključujući lokaciju na kojoj je snimljena.'],
					['en' => 'Messages you exchange with other users about listings.', 'sr' => 'Poruke koje razmenjujete sa drugim korisnicima u vezi sa oglasima.'],
					['en' => 'Favourite listings and saved searches.', 'sr' => 'Omiljeni oglasi i sačuvane pretrage.'],
					['en' => 'Reports of listings and messages sent through the contact page (name, email, text).', 'sr' => 'Prijave oglasa i poruke poslate preko stranice za kontakt (ime, e-pošta, tekst).'],
					['en' => 'Technical data: IP address, browser, the address of the page and the time of the request, in the error and security log.', 'sr' => 'Tehnički podaci: IP adresa, pregledač, adresa stranice i vreme zahteva, u dnevniku grešaka i bezbednosti.'],
					['en' => 'Listing views are counted without knowing who viewed them: to count a view only once, we keep a one-way hash of the IP address for one day.', 'sr' => 'Preglede oglasa brojimo bez podatka o tome ko ih je gledao: da bismo jedan pregled brojali samo jednom, jedan dan čuvamo jednosmerni heš IP adrese.'],
				]],
				['en' => 'We do not collect payment data: there are no payments on the site.', 'sr' => 'Podatke o plaćanju ne prikupljamo: na sajtu nema plaćanja.'],
			],
		],
		[
			'id' => 'purpose',
			'title' => ['en' => '3. Why and on what basis', 'sr' => '3. Zašto i na osnovu čega'],
			'blocks' => [
				['list' => [
					['en' => 'To provide the service under the terms of use: your account, publishing listings, delivering messages, the notifications you have chosen.', 'sr' => 'Da bismo pružili uslugu prema uslovima korišćenja: vaš nalog, objavljivanje oglasa, dostava poruka, obaveštenja koja ste izabrali.'],
					['en' => 'Our legitimate interest: security of users and the site, preventing fraud and abuse, checking listings and reports, limiting repeated attempts (logging in, sending messages), fixing errors.', 'sr' => 'Naš legitimni interes: bezbednost korisnika i sajta, sprečavanje prevara i zloupotreba, provera oglasa i prijava, ograničenje ponovljenih pokušaja (prijava, slanje poruka), otklanjanje grešaka.'],
					['en' => 'Your consent: analytics cookies (Google Analytics) and emails about news. You can withdraw consent at any time; this does not affect what was done before.', 'sr' => 'Vaš pristanak: analitički kolačići (Google Analytics) i e-pošta o novostima. Pristanak možete povući u svakom trenutku; to ne utiče na ono što je urađeno ranije.'],
					['en' => 'A legal obligation, when a regulation or an authority requires it.', 'sr' => 'Zakonska obaveza, kada to propis ili nadležni organ zahteva.'],
				]],
			],
		],
		[
			'id' => 'sharing',
			'title' => ['en' => '4. What is public and who sees what', 'sr' => '4. Šta je javno i ko šta vidi'],
			'blocks' => [
				['list' => [
					['en' => 'Public: your name, your listings with their place and photos, and your seller page with your active listings.', 'sr' => 'Javno: vaše ime, vaši oglasi sa mestom i fotografijama i vaša stranica prodavca sa aktivnim oglasima.'],
					['en' => 'Your phone number is shown only in listings where you have turned it on.', 'sr' => 'Broj telefona se prikazuje samo u oglasima u kojima ste to uključili.'],
					['en' => 'Your email is never shown. Messages are seen only by the people in the conversation; moderators do not see their content in the administration.', 'sr' => 'E-pošta se nikad ne prikazuje. Poruke vide samo učesnici razgovora; moderatori u administraciji ne vide njihov sadržaj.'],
					['en' => 'Moderators and administrators see the data they need to check listings and reports; their actions are recorded.', 'sr' => 'Moderatori i administratori vide podatke potrebne za proveru oglasa i prijava; njihove radnje se beleže.'],
					['en' => 'We do not sell personal data and do not use it for third-party advertising.', 'sr' => 'Lične podatke ne prodajemo i ne koristimo ih za reklame trećih lica.'],
				]],
			],
		],
		[
			'id' => 'processors',
			'title' => ['en' => '5. Who else processes the data', 'sr' => '5. Ko još obrađuje podatke'],
			'blocks' => [
				['list' => [
					['en' => 'AdriaHost (Serbia): hosting of the site, the database, the photos and the email. The server is in a data centre in the Netherlands (EU), a country with an adequate level of protection under the ZZPL.', 'sr' => 'AdriaHost (Srbija): hosting sajta, baze podataka, fotografija i e-pošte. Server se nalazi u data centru u Holandiji (EU), zemlji sa primerenim nivoom zaštite prema ZZPL.'],
					['en' => 'Google (Google Analytics): statistics of visits, only if you accept analytics cookies. Google may process this data outside the EU under its own terms.', 'sr' => 'Google (Google Analytics): statistika poseta, samo ako prihvatite analitičke kolačiće. Google ove podatke može obrađivati i van EU prema svojim uslovima.'],
				]],
				['en' => 'If we start using an external service for sending email or for storing photos, we will add it here before it starts processing your data.', 'sr' => 'Ako počnemo da koristimo spoljni servis za slanje e-pošte ili za čuvanje fotografija, navešćemo ga ovde pre nego što počne da obrađuje vaše podatke.'],
			],
		],
		[
			'id' => 'cookies',
			'title' => ['en' => '6. Cookies and browser storage', 'sr' => '6. Kolačići i skladište pregledača'],
			'blocks' => [
				['list' => [
					['en' => 'p4m_sid: keeps you logged in during a visit; deleted when you close the browser.', 'sr' => 'p4m_sid: održava vašu prijavu tokom posete; briše se kada zatvorite pregledač.'],
					['en' => 'p4m_remember: only if you tick "Remember me" when logging in; 30 days.', 'sr' => 'p4m_remember: samo ako pri prijavi označite „Zapamti me“; 30 dana.'],
					['en' => 'p4m_consent: your choice about analytics cookies; one year.', 'sr' => 'p4m_consent: vaš izbor o analitičkim kolačićima; godinu dana.'],
					['en' => '_ga and _ga_…: Google Analytics, only after you accept; up to two years.', 'sr' => '_ga i _ga_…: Google Analytics, samo kada prihvatite; do dve godine.'],
				]],
				['en' => 'In the browser\'s local storage we keep only whether you view listings as a grid or a list, and when you last opened a listing, so that one view is not counted twice. This stays on your device. The site language is kept in the page address, not in a cookie.', 'sr' => 'U lokalnom skladištu pregledača čuvamo samo da li oglase gledate kao mrežu ili listu i kada ste poslednji put otvorili oglas, da jedan pregled ne bismo brojali dvaput. To ostaje na vašem uređaju. Jezik sajta se pamti u adresi stranice, ne u kolačiću.'],
				['en' => 'You can change your choice about analytics at any time with "Cookie settings" at the bottom of every page.', 'sr' => 'Izbor o analitici možete promeniti u svakom trenutku preko „Podešavanja kolačića“ na dnu svake stranice.'],
			],
		],
		[
			'id' => 'storage',
			'title' => ['en' => '7. How long we keep it', 'sr' => '7. Koliko dugo čuvamo podatke'],
			'blocks' => [
				['list' => [
					['en' => 'Account data: while the account is active.', 'sr' => 'Podaci naloga: dok je nalog aktivan.'],
					['en' => 'When you delete your account (profile, "Delete account"), we immediately erase your name, email, phone and password, your listings with their photos, favourites, saved searches and logins on your devices. Messages you have sent stay visible to the other side, signed "Deleted user".', 'sr' => 'Kada obrišete nalog (profil, „Obriši nalog“), odmah brišemo vaše ime, e-poštu, telefon i lozinku, vaše oglase sa fotografijama, omiljene oglase, sačuvane pretrage i prijave na vašim uređajima. Poruke koje ste poslali ostaju vidljive sagovornicima, sa oznakom „Obrisan korisnik“.'],
					['en' => 'A deleted listing is no longer public and its photos are erased at once; we keep its text for 12 months for security and for handling reports and disputes, then erase it.', 'sr' => 'Obrisan oglas više nije javan, a njegove fotografije brišemo odmah; njegov tekst čuvamo 12 meseci radi bezbednosti i rešavanja prijava i sporova, a zatim ga brišemo.'],
					['en' => 'A listing is published for 30 days; after that it is not public, and you can renew it from your profile.', 'sr' => 'Oglas je objavljen 30 dana; posle toga nije javan, a možete ga obnoviti iz profila.'],
					['en' => 'Error and security log (IP address, browser): 90 days.', 'sr' => 'Dnevnik grešaka i bezbednosti (IP adresa, pregledač): 90 dana.'],
					['en' => 'Backup copies of the database: 14 days.', 'sr' => 'Rezervne kopije baze podataka: 14 dana.'],
					['en' => 'Messages from the contact page: 12 months; reports of listings: 12 months after they are handled.', 'sr' => 'Poruke sa stranice za kontakt: 12 meseci; prijave oglasa: 12 meseci od rešavanja.'],
					['en' => 'One-time links: email confirmation 48 hours, password reset 1 hour.', 'sr' => 'Jednokratni linkovi: potvrda e-pošte 48 sati, promena lozinke 1 sat.'],
				]],
			],
		],
		[
			'id' => 'security',
			'title' => ['en' => '8. How we protect it', 'sr' => '8. Kako ih štitimo'],
			'blocks' => [
				['en' => 'The site works only over an encrypted connection (HTTPS). Passwords are stored as hashes. Only staff with a role can open the administration, and their actions are recorded. We make daily backups. Repeated login attempts are limited.', 'sr' => 'Sajt radi samo preko šifrovane veze (HTTPS). Lozinke se čuvaju kao heš. Administraciju mogu da otvore samo zaposleni sa ulogom, a njihove radnje se beleže. Svakog dana pravimo rezervne kopije. Ponovljeni pokušaji prijave su ograničeni.'],
			],
		],
		[
			'id' => 'rights',
			'title' => ['en' => '9. Your rights', 'sr' => '9. Vaša prava'],
			'blocks' => [
				['list' => [
					['en' => 'Access: a copy of the data we hold about you.', 'sr' => 'Pristup: kopija podataka koje imamo o vama.'],
					['en' => 'Correction: most data you can change yourself in your profile.', 'sr' => 'Ispravka: većinu podataka možete sami promeniti u profilu.'],
					['en' => 'Erasure: delete the account in your profile, or ask us.', 'sr' => 'Brisanje: obrišite nalog u profilu ili nam se obratite.'],
					['en' => 'Restriction of processing, data portability and objection to processing based on our legitimate interest.', 'sr' => 'Ograničenje obrade, prenosivost podataka i prigovor na obradu zasnovanu na našem legitimnom interesu.'],
					['en' => 'Withdrawal of consent: "Cookie settings" for analytics, the notification settings in your profile for emails.', 'sr' => 'Povlačenje pristanka: „Podešavanja kolačića“ za analitiku, podešavanja obaveštenja u profilu za e-poštu.'],
				]],
				['en' => 'Write to support@place4market.com from the address of your account (so we know it is you). We answer within 30 days. You also have the right to file a complaint with the Commissioner for Information of Public Importance and Personal Data Protection, www.poverenik.rs.', 'sr' => 'Pišite na support@place4market.com sa adrese vašeg naloga (da bismo znali da ste to vi). Odgovaramo u roku od 30 dana. Imate i pravo da podnesete pritužbu Povereniku za informacije od javnog značaja i zaštitu podataka o ličnosti, www.poverenik.rs.'],
			],
		],
		[
			'id' => 'children',
			'title' => ['en' => '10. Children', 'sr' => '10. Deca'],
			'blocks' => [
				['en' => 'An account can be created only by a person aged 18 or over. We do not knowingly collect data of children.', 'sr' => 'Nalog može da napravi samo lice starije od 18 godina. Svesno ne prikupljamo podatke dece.'],
			],
		],
		[
			'id' => 'changes',
			'title' => ['en' => '11. Changes to this policy', 'sr' => '11. Izmene ove politike'],
			'blocks' => [
				['en' => 'The date of the last change is at the top of the page. We announce important changes by email before they apply.', 'sr' => 'Datum poslednje izmene nalazi se na vrhu stranice. Važne izmene najavljujemo e-poštom pre nego što počnu da važe.'],
			],
		],
	],
];
