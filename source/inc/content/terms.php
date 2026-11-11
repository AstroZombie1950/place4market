<?php
/* ==========================================================================
	Terms of use. Written 11.11.2026 from how the site really works (posting,
	moderation, reports, messages, account); the client and the client's lawyer
	finish it: [brackets] are to fill. Numbers follow admin → Settings
	(listing_days 60, max_photos 12, raise_hours 24): change both together.
	Values are ['en' => …, 'sr' => …]; Cyrillic is made on the fly (tc())
	========================================================================== */

return [
	'updated' => '2026-11-11',
	'lead' => [
		'en' => 'These terms apply to everyone who uses Place4Market: visitors, buyers and sellers. By creating an account or posting a listing you accept them. How we handle personal data is described in the privacy policy.',
		'sr' => 'Ovi uslovi važe za sve koji koriste Place4Market: posetioce, kupce i prodavce. Pravljenjem naloga ili postavljanjem oglasa prihvatate ih. Kako postupamo sa ličnim podacima opisano je u politici privatnosti.',
	],

	'sections' => [
		[
			'id' => 'service',
			'title' => ['en' => '1. About the service', 'sr' => '1. O servisu'],
			'blocks' => [
				['en' => 'Place4Market is a website where users publish classified ads and contact each other. Browsing and posting listings is free. The site is run by [Company name], [address], company ID (MB) [number], tax ID (PIB) [number].', 'sr' => 'Place4Market je sajt na kome korisnici objavljuju male oglase i stupaju u kontakt. Pregled i postavljanje oglasa su besplatni. Sajtom upravlja [Naziv firme], [adresa], matični broj [broj], PIB [broj].'],
				['en' => 'We are not a party to deals between users: we do not sell, buy, deliver or take payments, and we do not guarantee the quality, safety or legality of what is offered.', 'sr' => 'Nismo strana u poslovima između korisnika: ne prodajemo, ne kupujemo, ne dostavljamo i ne primamo uplate, i ne garantujemo kvalitet, bezbednost ni zakonitost onoga što se nudi.'],
			],
		],
		[
			'id' => 'account',
			'title' => ['en' => '2. Account', 'sr' => '2. Nalog'],
			'blocks' => [
				['list' => [
					['en' => 'You must be at least 18 years old to create an account. You confirm your email address with the link we send.', 'sr' => 'Da biste napravili nalog, morate imati najmanje 18 godina. Adresu e-pošte potvrđujete linkom koji vam pošaljemo.'],
					['en' => 'Your data must be true. You are responsible for everything done from your account, so keep the password secret; in your profile you can log out of all devices.', 'sr' => 'Vaši podaci moraju biti tačni. Odgovorni ste za sve što se radi sa vašeg naloga, zato čuvajte lozinku; u profilu se možete odjaviti sa svih uređaja.'],
					['en' => 'One person may have one account. A company may advertise as a business seller.', 'sr' => 'Jedna osoba može imati jedan nalog. Firma može oglašavati kao poslovni prodavac.'],
					['en' => 'You can delete your account at any time in your profile.', 'sr' => 'Nalog možete obrisati u svakom trenutku u profilu.'],
				]],
			],
		],
		[
			'id' => 'listings',
			'title' => ['en' => '3. Listings', 'sr' => '3. Oglasi'],
			'blocks' => [
				['en' => 'A listing must describe a real item, property, job or service you are offering, in the right category and place, with your own photos (up to 12) and an honest price. A listing is published for 60 days; after that you can renew it. Once a day you can move it back to the top of the list.', 'sr' => 'Oglas mora opisivati stvarnu stvar, nekretninu, posao ili uslugu koju nudite, u odgovarajućoj kategoriji i mestu, sa vašim fotografijama (do 12) i poštenom cenom. Oglas je objavljen 60 dana; posle toga ga možete obnoviti. Jednom dnevno ga možete podići na vrh liste.'],
				['en' => 'It is not allowed to post:', 'sr' => 'Nije dozvoljeno objavljivati:'],
				['list' => [
					['en' => 'items and services whose sale or advertising is prohibited by the laws of Serbia (weapons and ammunition without a permit, drugs, medicines on prescription, counterfeit goods, stolen items and the like);', 'sr' => 'stvari i usluge čija je prodaja ili oglašavanje zabranjeno zakonima Srbije (oružje i municija bez dozvole, droge, lekovi na recept, falsifikovana roba, ukradene stvari i slično);'],
					['en' => 'the same listing more than once, or one listing for many different items;', 'sr' => 'isti oglas više puta, ili jedan oglas za mnogo različitih stvari;'],
					['en' => 'misleading, offensive or discriminatory content, and job offers that ask candidates for payment;', 'sr' => 'obmanjujući, uvredljiv ili diskriminatorski sadržaj, i oglase za posao koji od kandidata traže uplatu;'],
					['en' => 'other people\'s photos or texts without their permission, or other people\'s personal data;', 'sr' => 'tuđe fotografije ili tekstove bez dozvole, ili tuđe lične podatke;'],
					['en' => 'links and contacts meant to take users away from the site for fraud.', 'sr' => 'linkove i kontakte kojima se korisnici odvode sa sajta radi prevare.'],
				]],
				['en' => 'You keep the rights to your texts and photos. By posting them you allow us to show them on Place4Market (including on its pages in other languages and scripts) for as long as the listing is published.', 'sr' => 'Prava na vaše tekstove i fotografije zadržavate vi. Objavljivanjem nam dozvoljavate da ih prikazujemo na Place4Market (i na stranicama na drugim jezicima i pismima) dok je oglas objavljen.'],
			],
		],
		[
			'id' => 'moderation',
			'title' => ['en' => '4. Moderation and reports', 'sr' => '4. Moderacija i prijave'],
			'blocks' => [
				['en' => 'As a rule, new and changed listings are checked before they are published. We may reject a listing that breaks these terms, correct its category or details, or remove it, and we tell you the reason. Any user can report a listing with "Report listing"; reports are reviewed by moderators.', 'sr' => 'Novi i izmenjeni oglasi se, po pravilu, proveravaju pre objavljivanja. Oglas koji krši ove uslove možemo odbiti, ispraviti mu kategoriju ili podatke, ili ga ukloniti, i javljamo vam razlog. Svaki korisnik može prijaviti oglas preko „Prijavi oglas“; prijave pregledaju moderatori.'],
				['en' => 'For serious or repeated violations we may block the account. If you think a decision is wrong, write to support@place4market.com.', 'sr' => 'Zbog ozbiljnih ili ponovljenih kršenja možemo blokirati nalog. Ako mislite da je odluka pogrešna, pišite na support@place4market.com.'],
			],
		],
		[
			'id' => 'contact',
			'title' => ['en' => '5. Messages and contacts', 'sr' => '5. Poruke i kontakti'],
			'blocks' => [
				['en' => 'Messages on the site are for agreeing on a deal about a listing. Spam, advertising other services, threats and insults are not allowed. Your phone number is shown only in listings where you turn it on.', 'sr' => 'Poruke na sajtu služe za dogovor o oglasu. Nije dozvoljeno slati neželjene poruke, reklamirati druge usluge, pretiti ili vređati. Vaš broj telefona prikazuje se samo u oglasima u kojima to uključite.'],
				['en' => 'Before a deal, read the safety tips. Never pay in advance to someone you have not met, and do not send codes or card data received by message.', 'sr' => 'Pre dogovora pročitajte savete za bezbednost. Nikad ne plaćajte unapred nekome koga niste upoznali i ne šaljite kodove ni podatke kartice primljene porukom.'],
			],
		],
		[
			'id' => 'liability',
			'title' => ['en' => '6. Liability', 'sr' => '6. Odgovornost'],
			'blocks' => [
				['en' => 'Users are responsible for their listings, messages and deals. Place4Market is not liable for damage resulting from contacts or deals between users, nor for interruptions of the site, within the limits allowed by law.', 'sr' => 'Korisnici su odgovorni za svoje oglase, poruke i poslove. Place4Market ne odgovara za štetu nastalu iz kontakata ili poslova između korisnika, niti za prekide u radu sajta, u granicama koje zakon dozvoljava.'],
			],
		],
		[
			'id' => 'changes',
			'title' => ['en' => '7. Changes and applicable law', 'sr' => '7. Izmene i merodavno pravo'],
			'blocks' => [
				['en' => 'We may update these terms. The date of the last change is at the top of the page; important changes are announced by email before they apply. The laws of the Republic of Serbia apply; disputes are resolved by the competent court in [city].', 'sr' => 'Ove uslove možemo ažurirati. Datum poslednje izmene nalazi se na vrhu stranice; važne izmene najavljujemo e-poštom pre nego što počnu da važe. Primenjuje se pravo Republike Srbije; sporove rešava nadležni sud u [grad].'],
			],
		],
	],
];
