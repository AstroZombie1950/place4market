<?php
/* ==========================================================================
	Help center: questions by topic (placeholders until the semantic core is ready)
	Values are ['en' => …, 'sr' => …]; Cyrillic is made on the fly (tc())
	========================================================================== */

return [
	'lead' => [
		'en' => 'Answers to common questions about buying, selling and your account. Can’t find yours? Write to us.',
		'sr' => 'Odgovori na česta pitanja o kupovini, prodaji i nalogu. Niste našli svoje? Pišite nam.',
	],

	'groups' => [
		[
			'id' => 'buying',
			'icon' => 'search',
			'title' => ['en' => 'Buying', 'sr' => 'Kupovina'],
			'items' => [
				[
					'q' => ['en' => 'How do I contact a seller?', 'sr' => 'Kako da kontaktiram prodavca?'],
					'a' => ['en' => 'Open the listing and press “Send message”, or show the seller’s phone number if they added it. You need an account to write messages.', 'sr' => 'Otvorite oglas i pritisnite „Pošalji poruku“ ili prikažite broj telefona prodavca, ako ga je naveo. Za slanje poruka potreban je nalog.'],
				],
				[
					'q' => ['en' => 'Can I pay through Place4Market?', 'sr' => 'Mogu li da platim preko Place4Market?'],
					'a' => ['en' => 'No. Payment is agreed directly with the seller. We recommend paying in person, after you have checked the item.', 'sr' => 'Ne. Plaćanje se dogovara direktno sa prodavcem. Preporučujemo da platite lično, nakon što proverite stvar.'],
				],
				[
					'q' => ['en' => 'How do I save a search?', 'sr' => 'Kako da sačuvam pretragu?'],
					'a' => ['en' => 'Set the filters on the results page and press “Save search”. We will email you when new matching listings appear.', 'sr' => 'Podesite filtere na stranici rezultata i pritisnite „Sačuvaj pretragu“. Javićemo vam e-poštom kada se pojave novi odgovarajući oglasi.'],
				],
			],
		],
		[
			'id' => 'selling',
			'icon' => 'tag',
			'title' => ['en' => 'Selling', 'sr' => 'Prodaja'],
			'items' => [
				[
					'q' => ['en' => 'How much does it cost to post an ad?', 'sr' => 'Koliko košta postavljanje oglasa?'],
					'a' => ['en' => 'Posting is free for private sellers. Paid promotion options will be announced separately.', 'sr' => 'Postavljanje je besplatno za fizička lica. Plaćene opcije isticanja biće objavljene naknadno.'],
				],
				[
					'q' => ['en' => 'Why is my listing not visible yet?', 'sr' => 'Zašto moj oglas još nije vidljiv?'],
					'a' => ['en' => 'Every new listing is checked by a moderator. It usually takes up to an hour; you will get a notification when it is published.', 'sr' => 'Svaki novi oglas proverava moderator. To obično traje do sat vremena; dobićete obaveštenje kada bude objavljen.'],
				],
				[
					'q' => ['en' => 'How long does a listing stay active?', 'sr' => 'Koliko dugo je oglas aktivan?'],
					'a' => ['en' => 'Sixty days. Before it expires you can renew it in your profile with one click.', 'sr' => 'Šezdeset dana. Pre isteka možete ga obnoviti u profilu jednim klikom.'],
				],
				[
					'q' => ['en' => 'How many photos can I add?', 'sr' => 'Koliko fotografija mogu da dodam?'],
					'a' => ['en' => 'Up to 12 photos in JPG or PNG. The first photo is the cover, you can change the order at any time.', 'sr' => 'Do 12 fotografija u JPG ili PNG formatu. Prva fotografija je naslovna, redosled možete promeniti u bilo kom trenutku.'],
				],
			],
		],
		[
			'id' => 'account',
			'icon' => 'user',
			'title' => ['en' => 'Account', 'sr' => 'Nalog'],
			'items' => [
				[
					'q' => ['en' => 'I forgot my password. What now?', 'sr' => 'Zaboravio sam lozinku. Šta sada?'],
					'a' => ['en' => 'On the log in page press “Forgot password?” and enter your email. We will send a link to set a new password.', 'sr' => 'Na stranici za prijavu pritisnite „Zaboravili ste lozinku?“ i unesite e-poštu. Poslaćemo vam link za postavljanje nove lozinke.'],
				],
				[
					'q' => ['en' => 'How do I change my email notifications?', 'sr' => 'Kako da promenim obaveštenja e-poštom?'],
					'a' => ['en' => 'Open your profile, the “Notifications” tab, and switch off what you don’t need.', 'sr' => 'Otvorite profil, karticu „Obaveštenja“, i isključite ono što vam ne treba.'],
				],
				[
					'q' => ['en' => 'How do I delete my account?', 'sr' => 'Kako da obrišem nalog?'],
					'a' => ['en' => 'In your profile settings choose “Delete account”. Your listings and messages will be removed.', 'sr' => 'U podešavanjima profila izaberite „Obriši nalog“. Vaši oglasi i poruke biće uklonjeni.'],
				],
			],
		],
		[
			'id' => 'safety',
			'icon' => 'shield',
			'title' => ['en' => 'Safety', 'sr' => 'Bezbednost'],
			'items' => [
				[
					'q' => ['en' => 'How do I report a suspicious listing?', 'sr' => 'Kako da prijavim sumnjiv oglas?'],
					'a' => ['en' => 'On the listing page press “Report listing” and choose the reason. Moderators check every report.', 'sr' => 'Na stranici oglasa pritisnite „Prijavi oglas“ i izaberite razlog. Moderatori proveravaju svaku prijavu.'],
				],
				[
					'q' => ['en' => 'A seller asks for an advance payment. Is that normal?', 'sr' => 'Prodavac traži avans. Da li je to normalno?'],
					'a' => ['en' => 'No. Never send money before you have seen the item. This is the most common scam on classifieds sites.', 'sr' => 'Ne. Nikada ne šaljite novac pre nego što vidite stvar. To je najčešća prevara na sajtovima za oglase.'],
				],
			],
		],
	],
];
