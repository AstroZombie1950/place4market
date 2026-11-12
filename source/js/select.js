/* ==========================================================================
	Place4Market — drop-down lists (site and admin; vanilla JS, no dependencies)
	Every <select> gets a button and a list in the site style. The <select>
	itself stays in the page as the value of the form: it is filled, disabled
	and listened to (input / change) exactly as before.
	- 12 options and more: a search field on top (Latin and Cyrillic, with or
	  without č ć š ž đ); on phones such a list opens on the whole screen
	- select[data-plain] is left as the browser draws it
	Strings: <body data-select-search="…" data-select-empty="…" data-select-close="…">
	Styles: "Drop-down lists" in site.css and admin.css
	========================================================================== */

(() => {
	'use strict';

	const SEARCH_FROM = 12;
	const body = document.body;
	const phone = window.matchMedia('(max-width: 767px)');
	const CYR = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', ђ: 'dj', е: 'e', ж: 'z', з: 'z', и: 'i', ј: 'j', к: 'k', л: 'l', љ: 'lj', м: 'm', н: 'n', њ: 'nj', о: 'o', п: 'p', р: 'r', с: 's', т: 't', ћ: 'c', у: 'u', ф: 'f', х: 'h', ц: 'c', ч: 'c', џ: 'dz', ш: 's' };

	// "Čačak", "чачак", "cacak" → "cacak"
	const norm = (text) => text.toLowerCase().replace(/đ/g, 'dj').replace(/[Ѐ-ӿ]/g, (ch) => (ch in CYR ? CYR[ch] : ch))
		.normalize('NFD').replace(/[̀-ͯ]/g, '').trim();

	const el = (tag, className, attrs = {}) => {
		const node = document.createElement(tag);
		if (className) node.className = className;
		Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
		return node;
	};

	const valueProp = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
	const indexProp = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');
	let uid = 0;
	let closeOpen = null;

	const enhance = (select) => {
		if (select.dataset.cselect || select.multiple || select.hasAttribute('data-plain')) return;
		select.dataset.cselect = '1';
		const id = `cselect-${++uid}`;

		// Wrapper (anchor of the list), button with the classes of the select (same look and size)
		const wrap = el('div', 'cselect');
		const button = el('button', `${select.className} cselect__button`, {
			type: 'button', id: `${id}-button`, role: 'combobox', 'aria-haspopup': 'listbox', 'aria-expanded': 'false', 'aria-controls': `${id}-list`,
		});
		const value = el('span', 'cselect__value', { id: `${id}-value` });
		button.appendChild(value);

		const panel = el('div', 'cselect__panel');
		panel.hidden = true;
		const head = el('div', 'cselect__head');
		const title = el('span', 'cselect__title');
		const close = el('button', 'cselect__close', { type: 'button', 'aria-label': body.dataset.selectClose || 'Close' });
		close.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
		head.append(title, close);
		const search = el('input', 'cselect__search', {
			type: 'search', autocomplete: 'off', placeholder: body.dataset.selectSearch || 'Search', 'aria-label': body.dataset.selectSearch || 'Search',
			role: 'combobox', 'aria-expanded': 'true', 'aria-controls': `${id}-list`, 'aria-autocomplete': 'list',
		});
		const list = el('ul', 'cselect__list', { role: 'listbox', id: `${id}-list`, tabindex: '-1' });
		const empty = el('p', 'cselect__empty');
		empty.textContent = body.dataset.selectEmpty || 'Nothing found';
		empty.hidden = true;
		panel.append(head, search, list, empty);

		select.parentNode.insertBefore(wrap, select);
		wrap.append(select, button, panel);
		select.classList.add('cselect__native');
		select.tabIndex = -1;
		select.setAttribute('aria-hidden', 'true');

		// Name for screen readers: the labels of the select + the chosen value
		const labels = Array.from(select.labels || []).map((label) => {
			const named = label.querySelector('.visually-hidden') || label;
			if (!named.id) named.id = `${id}-label-${Math.random().toString(36).slice(2, 7)}`;
			return named;
		});
		const labelText = labels.map((label) => label.textContent.trim()).join(' ') || select.getAttribute('aria-label') || '';
		if (labels.length) {
			button.setAttribute('aria-labelledby', `${labels.map((label) => label.id).join(' ')} ${id}-value`);
			list.setAttribute('aria-labelledby', labels[0].id);
		} else if (labelText) {
			button.setAttribute('aria-label', labelText);
			list.setAttribute('aria-label', labelText);
		}
		title.textContent = labelText;
		if (select.getAttribute('aria-describedby')) button.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));

		let items = [];
		let active = -1;
		let searchable = false;
		let isOpen = false;
		let locked = false;
		let typed = '';
		let typedAt = 0;

		const shown = () => items.filter((item) => !item.hidden && !item.classList.contains('is-disabled'));

		const sync = () => {
			const option = select.options[indexProp.get.call(select)];
			value.textContent = option ? option.text : '';
			button.classList.toggle('is-placeholder', !option || option.value === '');
			button.disabled = select.disabled;
			items.forEach((item) => item.setAttribute('aria-selected', String(option ? Number(item.dataset.index) === option.index : false)));
		};

		const render = () => {
			list.textContent = '';
			items = [];
			const add = (option) => {
				if (option.hidden) return;
				const item = el('li', `cselect__option${option.disabled ? ' is-disabled' : ''}`, {
					role: 'option', id: `${id}-o${option.index}`, 'data-index': option.index, 'aria-selected': 'false',
				});
				if (option.disabled) item.setAttribute('aria-disabled', 'true');
				item.textContent = option.text;
				item.dataset.search = norm(`${option.text} ${option.value} ${option.dataset.search || ''}`);
				list.appendChild(item);
				items.push(item);
			};
			Array.from(select.children).forEach((child) => {
				if (child.tagName === 'OPTGROUP') {
					const group = el('li', 'cselect__group', { role: 'presentation' });
					group.textContent = child.label;
					list.appendChild(group);
					Array.from(child.children).forEach(add);
				} else if (child.tagName === 'OPTION') {
					add(child);
				}
			});
			searchable = items.length >= SEARCH_FROM;
			wrap.classList.toggle('cselect--search', searchable);
			search.hidden = !searchable;
			sync();
		};

		const setActive = (item, scroll = true) => {
			items.forEach((other) => other.classList.toggle('is-active', other === item));
			active = item ? items.indexOf(item) : -1;
			const owner = searchable ? search : button;
			if (item) owner.setAttribute('aria-activedescendant', item.id);
			else owner.removeAttribute('aria-activedescendant');
			// Only the list scrolls, never the page
			if (item && scroll) {
				const top = item.offsetTop - list.offsetTop;
				if (top < list.scrollTop) list.scrollTop = top - 4;
				else if (top + item.offsetHeight > list.scrollTop + list.clientHeight) list.scrollTop = top + item.offsetHeight - list.clientHeight + 4;
			}
		};

		const filter = () => {
			const q = norm(search.value);
			items.forEach((item) => { item.hidden = q !== '' && !item.dataset.search.includes(q); });
			// A group title is shown while one of its options is
			Array.from(list.children).forEach((node) => {
				if (!node.classList.contains('cselect__group')) return;
				let next = node.nextElementSibling;
				let any = false;
				while (next && !next.classList.contains('cselect__group')) {
					if (!next.hidden) any = true;
					next = next.nextElementSibling;
				}
				node.hidden = !any;
			});
			const visible = shown();
			empty.hidden = visible.length > 0;
			list.scrollTop = 0;
			setActive(q !== '' ? visible[0] || null : visible.find((item) => item.getAttribute('aria-selected') === 'true') || null);
		};

		const closePanel = (focusButton = false) => {
			if (!isOpen) return;
			isOpen = false;
			closeOpen = null;
			wrap.classList.remove('is-open');
			button.setAttribute('aria-expanded', 'false');
			button.removeAttribute('aria-activedescendant');
			if (locked) body.classList.remove('is-locked');
			locked = false;
			if (focusButton || panel.contains(document.activeElement)) button.focus();
			// Hidden after the closing animation
			setTimeout(() => { if (!isOpen) panel.hidden = true; }, 160);
		};

		const openPanel = () => {
			if (isOpen || select.disabled) return;
			if (closeOpen) closeOpen();
			isOpen = true;
			closeOpen = closePanel;
			sync();
			search.value = '';
			panel.hidden = false;

			const sheet = searchable && phone.matches;
			wrap.classList.toggle('cselect--sheet', sheet);
			if (sheet && !body.classList.contains('is-locked')) {
				body.classList.add('is-locked');
				locked = true;
			}
			// Opens upwards when there is no room below
			const rect = button.getBoundingClientRect();
			const room = window.innerHeight - rect.bottom;
			wrap.classList.toggle('cselect--up', !sheet && room < Math.min(panel.offsetHeight, 340) + 12 && rect.top > room);
			// Not wider than the screen: a narrow button at the right edge opens to the left
			wrap.classList.toggle('cselect--right', !sheet && rect.left + panel.offsetWidth > window.innerWidth - 8);

			filter();
			requestAnimationFrame(() => wrap.classList.add('is-open'));
			button.setAttribute('aria-expanded', 'true');
			// On phones the keyboard would cover the list: the field is focused by a tap
			if (searchable && !sheet) search.focus();
			else if (sheet) list.focus();
		};

		const choose = (item) => {
			if (!item || item.classList.contains('is-disabled')) return;
			const index = Number(item.dataset.index);
			const changed = index !== indexProp.get.call(select);
			indexProp.set.call(select, index);
			sync();
			closePanel(true);
			if (changed) {
				select.dispatchEvent(new Event('input', { bubbles: true }));
				select.dispatchEvent(new Event('change', { bubbles: true }));
			}
		};

		const move = (step) => {
			const visible = shown();
			if (!visible.length) return;
			const at = visible.indexOf(items[active]);
			const next = step === 'first' ? 0 : step === 'last' ? visible.length - 1 : Math.max(0, Math.min(visible.length - 1, at + step));
			setActive(visible[at === -1 && step === -1 ? visible.length - 1 : next]);
		};

		// Typing on a list without the search field jumps to the option
		const typeAhead = (key) => {
			const now = Date.now();
			typed = now - typedAt > 700 ? key : typed + key;
			typedAt = now;
			const q = norm(typed);
			const found = shown().find((item) => norm(item.textContent).startsWith(q));
			if (found) setActive(found);
		};

		const onKey = (e) => {
			if (!isOpen) {
				if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
					e.preventDefault();
					openPanel();
				} else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
					openPanel();
					if (!searchable) typeAhead(e.key);
				}
				return;
			}
			if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
			else if (e.key === 'PageDown') { e.preventDefault(); move(8); }
			else if (e.key === 'PageUp') { e.preventDefault(); move(-8); }
			else if (e.key === 'Home' && e.target !== search) { e.preventDefault(); move('first'); }
			else if (e.key === 'End' && e.target !== search) { e.preventDefault(); move('last'); }
			else if (e.key === 'Enter' || (e.key === ' ' && e.target !== search)) { e.preventDefault(); choose(items[active]); }
			else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closePanel(true); }
			else if (e.key === 'Tab') closePanel();
			else if (e.target !== search && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) typeAhead(e.key);
		};

		button.addEventListener('click', () => (isOpen ? closePanel() : openPanel()));
		button.addEventListener('keydown', onKey);
		search.addEventListener('keydown', onKey);
		list.addEventListener('keydown', onKey);
		search.addEventListener('input', filter);
		close.addEventListener('click', () => closePanel(true));
		// The focus stays where it is while an option is pressed
		list.addEventListener('mousedown', (e) => e.preventDefault());
		list.addEventListener('click', (e) => choose(e.target.closest('.cselect__option')));
		list.addEventListener('mousemove', (e) => {
			const item = e.target.closest('.cselect__option');
			if (item && items[active] !== item && !item.classList.contains('is-disabled')) setActive(item, false);
		});
		document.addEventListener('pointerdown', (e) => { if (isOpen && !wrap.contains(e.target)) closePanel(); });
		wrap.addEventListener('focusout', (e) => { if (isOpen && e.relatedTarget && !wrap.contains(e.relatedTarget)) closePanel(); });

		// The select is still the field of the form: a label click, a script or form validation
		// that focuses it lands on the button; a value set from code shows at once
		select.addEventListener('focus', () => button.focus());
		select.addEventListener('change', sync);
		if (select.form) select.form.addEventListener('reset', () => setTimeout(sync));
		Object.defineProperty(select, 'value', {
			configurable: true,
			get() { return valueProp.get.call(this); },
			set(next) { valueProp.set.call(this, next); sync(); },
		});
		Object.defineProperty(select, 'selectedIndex', {
			configurable: true,
			get() { return indexProp.get.call(this); },
			set(next) { indexProp.set.call(this, next); sync(); },
		});
		new MutationObserver(render).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'hidden', 'selected'] });

		render();
	};

	const init = (root = document) => Array.from(root.querySelectorAll('select')).forEach(enhance);

	init();
	// For selects added to the page later
	window.P4MSelect = { init };
})();
