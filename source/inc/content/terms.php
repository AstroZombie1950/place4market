<?php
/* ==========================================================================
	Terms of use: placeholder text, the final version comes from the client's lawyer
	Values are ['en' => …, 'sr' => …]; Cyrillic is made on the fly (tc())
	========================================================================== */

return [
	'updated' => '2026-10-22',
	'lead' => [
		'en' => 'These terms apply to everyone who uses Place4Market: visitors, buyers and sellers. By creating an account or posting a listing you accept them.',
		'sr' => 'Ovi uslovi važe za sve koji koriste Place4Market: posetioce, kupce i prodavce. Pravljenjem naloga ili postavljanjem oglasa prihvatate ih.',
	],

	'sections' => [
		[
			'id' => 'service',
			'title' => ['en' => '1. About the service', 'sr' => '1. O servisu'],
			'blocks' => [
				['en' => 'Place4Market is a platform where users publish classified ads. We are not a party to the deals between users and do not guarantee the quality, safety or legality of the items offered.', 'sr' => 'Place4Market je platforma na kojoj korisnici objavljuju male oglase. Nismo strana u poslovima između korisnika i ne garantujemo kvalitet, bezbednost ni zakonitost ponuđenih stvari.'],
			],
		],
		[
			'id' => 'account',
			'title' => ['en' => '2. Account', 'sr' => '2. Nalog'],
			'blocks' => [
				['en' => 'You must be at least 18 years old to create an account. You are responsible for the accuracy of your data and for keeping your password secret.', 'sr' => 'Da biste napravili nalog, morate imati najmanje 18 godina. Odgovorni ste za tačnost svojih podataka i za čuvanje lozinke.'],
				['en' => 'One person may have one account. We may suspend accounts that break these terms.', 'sr' => 'Jedna osoba može imati jedan nalog. Možemo suspendovati naloge koji krše ove uslove.'],
			],
		],
		[
			'id' => 'listings',
			'title' => ['en' => '3. Listings', 'sr' => '3. Oglasi'],
			'blocks' => [
				['en' => 'A listing must describe a real item or service, in the right category, with your own photos and an honest price. It is not allowed to post:', 'sr' => 'Oglas mora opisivati stvarnu stvar ili uslugu, u odgovarajućoj kategoriji, sa vašim fotografijama i poštenom cenom. Nije dozvoljeno objavljivati:'],
				['list' => [
					['en' => 'items whose sale is prohibited by the laws of Serbia;', 'sr' => 'stvari čija je prodaja zabranjena zakonima Srbije;'],
					['en' => 'duplicate listings for the same item;', 'sr' => 'duplikate oglasa za istu stvar;'],
					['en' => 'misleading or offensive content;', 'sr' => 'obmanjujući ili uvredljiv sadržaj;'],
					['en' => 'other people’s photos or personal data.', 'sr' => 'tuđe fotografije ili lične podatke.'],
				]],
			],
		],
		[
			'id' => 'moderation',
			'title' => ['en' => '4. Moderation', 'sr' => '4. Moderacija'],
			'blocks' => [
				['en' => 'New listings are checked before publication. We may reject, edit the category of, or remove a listing that breaks these terms, and we will tell you why.', 'sr' => 'Novi oglasi se proveravaju pre objavljivanja. Možemo odbiti, promeniti kategoriju ili ukloniti oglas koji krši ove uslove i obavestićemo vas o razlogu.'],
			],
		],
		[
			'id' => 'liability',
			'title' => ['en' => '5. Liability', 'sr' => '5. Odgovornost'],
			'blocks' => [
				['en' => 'Users are responsible for their listings and deals. Place4Market is not liable for damage resulting from contacts or deals between users.', 'sr' => 'Korisnici su odgovorni za svoje oglase i poslove. Place4Market ne odgovara za štetu nastalu iz kontakata ili poslova između korisnika.'],
			],
		],
		[
			'id' => 'changes',
			'title' => ['en' => '6. Changes to the terms', 'sr' => '6. Izmene uslova'],
			'blocks' => [
				['en' => 'We may update these terms. The date of the last change is shown at the top of the page; important changes will be announced by email.', 'sr' => 'Ove uslove možemo ažurirati. Datum poslednje izmene prikazan je na vrhu stranice; o važnim izmenama obavestićemo vas e-poštom.'],
			],
		],
	],
];
