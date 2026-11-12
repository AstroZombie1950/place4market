/* ==========================================================================
	Place4Market — phone number fields (site and admin; vanilla JS, no dependencies)
	input[data-phone] (phone_input() in helpers.php): "+381" stands before the
	field, the field takes the national number in groups as it is typed:
	"64 123 4567". Only digits stay; "+381…", "00381…" and a leading 0 (typed,
	pasted or filled in by the browser) are dropped; 9 digits at most. The
	server reads the number the same way (phone_normalize() in auth.php)
	========================================================================== */

(() => {
	'use strict';

	const MAX = 9;

	// Digits of the national number and how many digits were cut from the front
	const national = (raw) => {
		const all = raw.replace(/\D/g, '');
		let cut = 0;
		if (all.startsWith('00381')) cut = 5;
		else if (all.startsWith('381') && (raw.trim().startsWith('+') || all.length >= 10)) cut = 3;
		if (all[cut] === '0') cut += 1;

		return { digits: all.slice(cut, cut + MAX), cut };
	};

	// "641234567" → "64 123 4567"
	const group = (digits) => [digits.slice(0, 2), digits.slice(2, 5), digits.slice(5)].filter(Boolean).join(' ');

	const tidy = (input) => {
		const raw = input.value;
		const caret = input.selectionStart ?? raw.length;
		const { digits, cut } = national(raw);
		const value = group(digits);
		if (value === raw) return;

		// The caret stays after the same digit
		const before = Math.max(0, Math.min(digits.length, raw.slice(0, caret).replace(/\D/g, '').length - cut));
		input.value = value;
		if (document.activeElement !== input) return;
		let pos = 0;
		for (let seen = 0; pos < value.length && seen < before; pos += 1) {
			if (/\d/.test(value[pos])) seen += 1;
		}
		input.setSelectionRange(pos, pos);
	};

	document.querySelectorAll('input[data-phone]').forEach((input) => {
		// A number shown back as typed (with an error) is left as it is until edited
		if (/^[\d\s]*$/.test(input.value)) tidy(input);
		input.addEventListener('input', () => tidy(input));
		// Backspace / Delete next to a group space take the digit behind it (else nothing changes)
		input.addEventListener('keydown', (event) => {
			const pos = input.selectionStart;
			if (pos === null || pos !== input.selectionEnd) return;
			if (event.key === 'Backspace' && input.value[pos - 1] === ' ') input.setSelectionRange(pos - 1, pos - 1);
			if (event.key === 'Delete' && input.value[pos] === ' ') input.setSelectionRange(pos + 1, pos + 1);
		});
	});
})();
