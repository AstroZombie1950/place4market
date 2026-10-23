<?php
/* ==========================================================================
	Safety tips: page texts (placeholders until the semantic core is ready)
	Values are ['en' => …, 'sr' => …]; Cyrillic is made on the fly (tc())
	========================================================================== */

return [
	'lead' => [
		'en' => 'Most deals on Place4Market go smoothly. These simple rules help keep it that way, for buyers and for sellers.',
		'sr' => 'Većina kupoprodaja na Place4Market prođe bez problema. Ova jednostavna pravila pomažu da tako i ostane, i kupcima i prodavcima.',
	],

	'rules' => [
		[
			'icon' => 'map-pin',
			'title' => ['en' => 'Meet in person', 'sr' => 'Nađite se uživo'],
			'text' => ['en' => 'Choose a busy public place in daylight, or bring someone along.', 'sr' => 'Izaberite prometno javno mesto po danu ili povedite nekoga sa sobom.'],
		],
		[
			'icon' => 'eye',
			'title' => ['en' => 'Check before you pay', 'sr' => 'Proverite pre plaćanja'],
			'text' => ['en' => 'Look at the item, try it out and compare it with the description.', 'sr' => 'Pogledajte stvar, isprobajte je i uporedite sa opisom.'],
		],
		[
			'icon' => 'lock',
			'title' => ['en' => 'Keep your data private', 'sr' => 'Čuvajte svoje podatke'],
			'text' => ['en' => 'Never share card details, codes from SMS or passwords.', 'sr' => 'Nikada ne delite podatke o kartici, kodove iz SMS poruka ili lozinke.'],
		],
	],

	'sections' => [
		[
			'id' => 'buyers',
			'title' => ['en' => 'Tips for buyers', 'sr' => 'Saveti za kupce'],
			'blocks' => [
				['list' => [
					['en' => 'Ask the seller questions in messages, so the conversation is saved with the listing.', 'sr' => 'Postavljajte pitanja prodavcu u porukama, da bi razgovor ostao sačuvan uz oglas.'],
					['en' => 'Do not send an advance payment or a deposit before you have seen the item.', 'sr' => 'Ne šaljite avans ni kaparu pre nego što vidite stvar.'],
					['en' => 'For cars and real estate, check the documents and the owner’s identity.', 'sr' => 'Kod automobila i nekretnina proverite dokumentaciju i identitet vlasnika.'],
					['en' => 'Be careful with prices far below the market.', 'sr' => 'Budite oprezni sa cenama mnogo nižim od tržišnih.'],
				]],
			],
		],
		[
			'id' => 'sellers',
			'title' => ['en' => 'Tips for sellers', 'sr' => 'Saveti za prodavce'],
			'blocks' => [
				['list' => [
					['en' => 'Accept cash in person or check that the money has arrived before handing over the item.', 'sr' => 'Primite gotovinu lično ili proverite da je novac stigao pre predaje stvari.'],
					['en' => 'Do not follow links to “delivery” or “payment” pages sent by buyers.', 'sr' => 'Ne otvarajte linkove ka stranicama za „dostavu“ ili „plaćanje“ koje šalju kupci.'],
					['en' => 'Do not send codes from SMS messages to anyone, even if they say they work for us.', 'sr' => 'Nikome ne šaljite kodove iz SMS poruka, čak ni ako tvrde da rade za nas.'],
				]],
			],
		],
		[
			'id' => 'scams',
			'warning' => true,
			'title' => ['en' => 'Signs of a scam', 'sr' => 'Znaci prevare'],
			'blocks' => [
				['list' => [
					['en' => 'The other side is in a hurry and pushes you to pay right now.', 'sr' => 'Druga strana žuri i tera vas da platite odmah.'],
					['en' => 'They refuse to meet or to show the item on a video call.', 'sr' => 'Odbija da se nađe ili da pokaže stvar u video pozivu.'],
					['en' => 'They ask to continue the conversation in another app.', 'sr' => 'Traži da razgovor nastavite u drugoj aplikaciji.'],
					['en' => 'They send a link to a page that looks like Place4Market or a bank.', 'sr' => 'Šalje link ka stranici koja izgleda kao Place4Market ili banka.'],
				]],
			],
		],
		[
			'id' => 'problem',
			'title' => ['en' => 'If something went wrong', 'sr' => 'Ako nešto nije u redu'],
			'blocks' => [
				['en' => 'Report the listing from its page and write to our support with the listing number. If you have lost money, contact your bank and the police right away.', 'sr' => 'Prijavite oglas sa njegove stranice i pišite našoj podršci uz broj oglasa. Ako ste izgubili novac, odmah se obratite banci i policiji.'],
			],
		],
	],
];
