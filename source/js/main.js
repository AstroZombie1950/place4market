/* ==========================================================================
	Place4Market — site UI (vanilla JS, no dependencies)
	Behaviour is attached through data-* attributes; see each block.
	Translated strings come from <script id="i18n-js"> (footer.php).
	========================================================================== */

(() => {
	'use strict';

	/* ----------------------------------------------------------------------
		Error reporting: uncaught errors and rejected promises → /js-error.php
		(server log, channel "js"); reportError(message, source, stack) for
		failures that are caught but still worth knowing about. Same block in admin.js
		---------------------------------------------------------------------- */
	const reportError = (() => {
		let sent = 0;

		const report = (message, source = '', stack = '') => {
			// A broken page must not flood the log; extensions are not our code
			if (sent >= 5 || !navigator.sendBeacon || /extension:\/\//.test(`${source} ${stack}`)) return;
			sent++;
			navigator.sendBeacon('/js-error.php', JSON.stringify({
				message: String(message).slice(0, 500),
				source,
				stack: String(stack || '').slice(0, 2000),
				page: location.href,
			}));
		};

		window.addEventListener('error', (event) => {
			// Other origins give no details ("Script error.")
			if (!event.filename || !event.filename.startsWith(location.origin)) return;
			report(event.message, `${event.filename}:${event.lineno}:${event.colno}`, event.error && event.error.stack);
		});

		window.addEventListener('unhandledrejection', (event) => {
			const reason = event.reason || {};
			report(reason.message || reason, '', reason.stack);
		});

		return report;
	})();

	const $ = (selector, context = document) => context.querySelector(selector);
	const $$ = (selector, context = document) => Array.from(context.querySelectorAll(selector));

	// Strings from PHP
	const i18n = (() => {
		try {
			return JSON.parse(($('#i18n-js') || {}).textContent || '{}');
		} catch (err) {
			return {};
		}
	})();

	// Safe localStorage (private mode, blocked storage)
	const store = {
		get(key) {
			try { return localStorage.getItem(key); } catch (err) { return null; }
		},
		set(key, value) {
			try { localStorage.setItem(key, value); } catch (err) { /* ignore */ }
		},
	};

	// 145000 → "145 000"
	const formatNumber = (value) => String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

	// An item of a horizontally scrolling strip comes into view: only the strip scrolls,
	// never the page (scrollIntoView would also scroll the page to a strip below the fold).
	// center: the item goes to the middle, its neighbours stay visible on both sides
	const revealInStrip = (item, strip, center = false) => {
		const s = strip.getBoundingClientRect();
		const t = item.getBoundingClientRect();
		if (center) strip.scrollLeft += t.left + t.width / 2 - (s.left + s.width / 2);
		else if (t.left < s.left) strip.scrollLeft -= s.left - t.left + 8;
		else if (t.right > s.right) strip.scrollLeft += t.right - s.right + 8;
	};

	/* ----------------------------------------------------------------------
		Toasts: toast('Saved'), toast('Copied', 'info'), toast('Failed', 'danger')
		---------------------------------------------------------------------- */
	const toast = (message, type = 'success') => {
		const region = $('[data-toast-region]');
		if (!region || !message) return;

		const el = document.createElement('div');
		el.className = `toast toast--${type}`;
		el.setAttribute('role', 'status');
		el.textContent = message;
		region.appendChild(el);

		requestAnimationFrame(() => el.classList.add('is-visible'));
		setTimeout(() => {
			el.classList.remove('is-visible');
			setTimeout(() => el.remove(), 250);
		}, 3200);
	};

	// A multi-line field grows with its text from its CSS min-height up to
	// data-max-rows lines (10, on phones 8), then scrolls inside; it is not resized by hand.
	// After changing the value from code: field.dispatchEvent(new Event('input'))
	const autosize = (field) => {
		const fit = () => {
			// Not on the screen (a closed dialog or tab): the height of its rows, fitted on focus
			if (!field.offsetParent) { field.style.height = ''; return; }
			const cs = getComputedStyle(field);
			const line = parseFloat(cs.lineHeight) || parseFloat(cs.fontSize) * 1.5;
			const borders = parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth);
			const rows = Number(field.dataset.maxRows) || (window.matchMedia('(max-width: 767px)').matches ? 8 : 10);
			const max = Math.round(line * rows + parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom) + borders);
			field.style.height = 'auto';
			const full = field.scrollHeight + borders;
			field.style.height = `${Math.min(full, max)}px`;
			field.style.overflowY = full > max ? 'auto' : 'hidden';
		};
		field.addEventListener('input', fit);
		// Hidden at load (a closed dialog or tab) measures as 0: fit again when it is used
		field.addEventListener('focus', fit);
		fit();
	};

	// Fade out and remove an element
	const removeWithFade = (el, after) => {
		if (!el) return;
		el.classList.add('is-removing');
		setTimeout(() => {
			el.remove();
			if (after) after();
			document.dispatchEvent(new CustomEvent('p4m:removed'));
		}, 260);
	};

	/* ----------------------------------------------------------------------
		POST to our JSON endpoints (favorite.php, saved-search.php) with the csrf
		token. Resolves to the answer; rejects with a text for the visitor
		---------------------------------------------------------------------- */
	const postJson = async (url, fields) => {
		const body = new FormData();
		Object.entries({ ...fields, csrf: i18n.csrf || '' }).forEach(([key, value]) => body.append(key, value));

		let res;
		let data = {};
		try {
			res = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
			data = await res.json();
		} catch (err) {
			throw new Error(i18n.actionFailed);
		}
		if (!res.ok && !data.login) throw new Error(data.error || i18n.actionFailed);
		return data;
	};

	/* ----------------------------------------------------------------------
		Favorites: button[data-fav="id"] with aria-pressed, saved in favorite.php.
		The heart switches at once and goes back if saving fails; a guest is
		sent to the login page and comes back here.
		data-toast-on / data-toast-off: messages
		data-fav-remove: remove the card when unsaved (favorites page)
		---------------------------------------------------------------------- */
	const initFavorites = () => {
		const busy = new Set();

		document.addEventListener('click', async (e) => {
			const btn = e.target.closest('[data-fav]');
			if (!btn) return;
			e.preventDefault();

			const id = btn.dataset.fav;
			if (busy.has(id)) return;
			busy.add(id);

			const saved = btn.getAttribute('aria-pressed') !== 'true';
			// Sync every heart for the same listing on the page
			const show = (state) => $$(`[data-fav="${id}"]`).forEach((el) => el.setAttribute('aria-pressed', String(state)));
			show(saved);

			try {
				const data = await postJson(i18n.favoriteUrl, { id, on: saved ? 1 : 0, next: location.pathname + location.search });
				if (data.login) {
					show(!saved);
					window.location.href = data.login;
					return;
				}
			} catch (err) {
				show(!saved);
				toast(err.message, 'danger');
				return;
			} finally {
				busy.delete(id);
			}

			toast(saved ? btn.dataset.toastOn : btn.dataset.toastOff);

			if (!saved && btn.hasAttribute('data-fav-remove')) {
				const card = btn.closest('[data-card]');
				const grid = card ? card.parentElement : null;
				removeWithFade(card, () => {
					const empty = $('[data-fav-empty]');
					if (grid && empty && !grid.querySelector('[data-card]')) {
						grid.hidden = true;
						empty.hidden = false;
					}
					const count = $('[data-fav-count]');
					if (count && grid) count.textContent = grid.querySelectorAll('[data-card]').length;
				});
			}
		});
	};

	/* ----------------------------------------------------------------------
		Saved searches: the alerts switch input[data-search-alerts="id"] saves
		at once (saved-search.php); on failure it goes back.
		data-toast-on / data-toast-off: messages
		---------------------------------------------------------------------- */
	const initSavedSearches = () => {
		document.addEventListener('change', async (e) => {
			const box = e.target.closest('[data-search-alerts]');
			if (!box) return;

			const on = box.checked;
			box.disabled = true;
			try {
				await postJson(i18n.savedSearchUrl, { action: 'alerts', id: box.dataset.searchAlerts, on: on ? 1 : 0 });
				toast(on ? box.dataset.toastOn : box.dataset.toastOff, 'info');
			} catch (err) {
				box.checked = !on;
				toast(err.message, 'danger');
			} finally {
				box.disabled = false;
			}
		});
	};

	/* ----------------------------------------------------------------------
		Tabs: [data-tabs] > role="tablist" > role="tab" (aria-controls)
		[data-tabs-hash] keeps the open tab in the URL (#settings)
		[data-open-tab="tab-id"] on any link/button opens that tab
		---------------------------------------------------------------------- */
	const initTabs = () => {
		$$('[data-tabs]').forEach((root) => {
			const tabs = $$('[role="tab"]', root).filter((tab) => tab.closest('[data-tabs]') === root);
			if (!tabs.length) return;
			const useHash = root.hasAttribute('data-tabs-hash');

			const select = (tab, { focus = false, updateHash = true } = {}) => {
				tabs.forEach((t) => {
					const active = t === tab;
					t.setAttribute('aria-selected', String(active));
					t.tabIndex = active ? 0 : -1;
					const panel = document.getElementById(t.getAttribute('aria-controls'));
					if (panel) panel.hidden = !active;
				});
				if (focus) tab.focus();
				if (useHash && updateHash) history.replaceState(null, '', '#' + tab.id.replace(/^tab-/, ''));
			};

			tabs.forEach((tab, index) => {
				tab.addEventListener('click', () => select(tab));
				tab.addEventListener('keydown', (e) => {
					const keys = {
						ArrowRight: tabs[(index + 1) % tabs.length],
						ArrowLeft: tabs[(index - 1 + tabs.length) % tabs.length],
						Home: tabs[0],
						End: tabs[tabs.length - 1],
					};
					if (keys[e.key]) {
						e.preventDefault();
						select(keys[e.key], { focus: true });
					}
				});
			});

			const fromHash = useHash && location.hash ? document.getElementById('tab-' + location.hash.slice(1)) : null;
			const initial = (fromHash && tabs.includes(fromHash) && fromHash)
				|| tabs.find((t) => t.getAttribute('aria-selected') === 'true')
				|| tabs[0];
			select(initial, { updateHash: false });

			// Only the #part of the address changed (a link to another tab of this page): follow it
			if (useHash) {
				window.addEventListener('hashchange', () => {
					const tab = document.getElementById('tab-' + location.hash.slice(1));
					if (tab && tabs.includes(tab)) select(tab, { updateHash: false });
				});
			}

			$$('[data-open-tab]').forEach((link) => {
				const target = document.getElementById(link.dataset.openTab);
				if (!target || !tabs.includes(target)) return;
				link.addEventListener('click', (e) => {
					e.preventDefault();
					select(target, { focus: true });
				});
			});
		});
	};

	/* ----------------------------------------------------------------------
		Pill filters: [data-pills="list-id"] > button[data-value]
		Items in #list-id with data-status; value "" shows all
		---------------------------------------------------------------------- */
	const initPills = () => {
		$$('[data-pills]').forEach((group) => {
			const list = document.getElementById(group.dataset.pills);
			if (!list) return;
			const buttons = $$('button[data-value]', group);

			const apply = (button) => {
				const value = button.dataset.value;
				buttons.forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
				let visible = 0;
				$$('[data-status]', list).forEach((item) => {
					const show = !value || item.dataset.status === value;
					item.hidden = !show;
					if (show) visible += 1;
				});
				const empty = $('[data-pills-empty]', list);
				if (empty) empty.hidden = visible > 0;
			};

			// Phones show the same choice as a list: [data-pills-select]
			const select = $('[data-pills-select]', group);
			const choose = (button) => {
				apply(button);
				if (select) select.value = button.dataset.value;
			};
			if (select) select.addEventListener('change', () => apply(buttons.find((b) => b.dataset.value === select.value) || buttons[0]));

			buttons.forEach((button) => button.addEventListener('click', () => choose(button)));
			choose(buttons.find((b) => b.getAttribute('aria-pressed') === 'true') || buttons[0]);
		});
	};

	/* ----------------------------------------------------------------------
		Drawer (filters on tablets/phones)
		[data-drawer-open="id"] opens #id, [data-drawer-close] closes,
		overlay [data-overlay] and Esc close
		---------------------------------------------------------------------- */
	const initDrawers = () => {
		const overlay = $('[data-overlay]');
		let opener = null;

		const close = (drawer) => {
			drawer.classList.remove('is-open');
			document.body.classList.remove('is-locked');
			if (overlay) overlay.hidden = true;
			if (opener) {
				opener.setAttribute('aria-expanded', 'false');
				opener.focus();
			}
		};

		$$('[data-drawer-open]').forEach((btn) => {
			const drawer = document.getElementById(btn.dataset.drawerOpen);
			if (!drawer) return;

			btn.addEventListener('click', () => {
				opener = btn;
				drawer.classList.add('is-open');
				document.body.classList.add('is-locked');
				if (overlay) overlay.hidden = false;
				btn.setAttribute('aria-expanded', 'true');
				const first = $('button, a, input, select', drawer);
				if (first) setTimeout(() => first.focus(), 50);
			});

			$$('[data-drawer-close]', drawer).forEach((x) => x.addEventListener('click', () => close(drawer)));
			if (overlay) overlay.addEventListener('click', () => close(drawer));

			document.addEventListener('keydown', (e) => {
				if (e.key === 'Escape' && drawer.classList.contains('is-open')) close(drawer);
			});

			// Back to the laptop/desktop layout (≥1024, site.css): reset
			window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => {
				if (e.matches && drawer.classList.contains('is-open')) close(drawer);
			});
		});
	};

	/* ----------------------------------------------------------------------
		Dialogs: native <dialog class="modal">
		[data-dialog-open="id"] opens, [data-dialog-close] closes, backdrop closes
		---------------------------------------------------------------------- */
	const initDialogs = () => {
		document.addEventListener('click', (e) => {
			const opener = e.target.closest('[data-dialog-open]');
			if (opener) {
				const dialog = document.getElementById(opener.dataset.dialogOpen);
				if (dialog && typeof dialog.showModal === 'function') {
					e.preventDefault();
					dialog.showModal();
				}
				return;
			}

			const closer = e.target.closest('[data-dialog-close]');
			if (closer) {
				const dialog = closer.closest('dialog');
				if (dialog) dialog.close();
			}
		});

		$$('dialog.modal').forEach((dialog) => {
			dialog.addEventListener('click', (e) => {
				if (e.target === dialog) dialog.close();
			});
		});
	};

	/* ----------------------------------------------------------------------
		Confirm: [data-confirm="Question?"]
		data-confirm-label: OK button text
		After OK: follows the link or submits the form (a submit button is sent
		as the submitter, with its name and value).
		---------------------------------------------------------------------- */
	const initConfirm = () => {
		const dialog = $('#confirm-dialog');
		if (!dialog) return;

		const text = $('[data-confirm-text]', dialog);
		const ok = $('[data-confirm-ok]', dialog);
		const defaultLabel = ok.textContent;
		let pending = null;

		document.addEventListener('click', (e) => {
			const el = e.target.closest('[data-confirm]');
			if (!el) return;
			e.preventDefault();
			pending = el;
			text.textContent = el.dataset.confirm;
			ok.textContent = el.dataset.confirmLabel || defaultLabel;
			dialog.showModal();
		});

		ok.addEventListener('click', () => {
			dialog.close();
			if (!pending) return;

			if (pending.tagName === 'A' && pending.getAttribute('href')) {
				window.location.href = pending.href;
			} else if (pending.form) {
				pending.form.requestSubmit(pending.type === 'submit' ? pending : undefined);
			}
			pending = null;
		});
	};

	/* ----------------------------------------------------------------------
		Small form helpers
		- [data-password-toggle] aria-controls="input-id"
		- [data-counter="output-id"] + maxlength: "12 / 80"
		- select[data-autosubmit] submits its form on change
		- every textarea grows with its text (autosize)
		---------------------------------------------------------------------- */
	const initForms = () => {
		$$('textarea').forEach(autosize);

		$$('[data-password-toggle]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const input = document.getElementById(btn.getAttribute('aria-controls'));
				if (!input) return;
				const show = input.type === 'password';
				input.type = show ? 'text' : 'password';
				btn.setAttribute('aria-pressed', String(show));
				btn.setAttribute('aria-label', show ? i18n.hidePassword : i18n.showPassword);
			});
		});

		$$('[data-counter][maxlength]').forEach((field) => {
			const out = document.getElementById(field.dataset.counter);
			if (!out) return;
			const update = () => { out.textContent = `${field.value.length} / ${field.maxLength}`; };
			field.addEventListener('input', update);
			update();
		});

		$$('select[data-autosubmit]').forEach((select) => {
			select.addEventListener('change', () => select.form && select.form.requestSubmit());
		});
	};

	/* ----------------------------------------------------------------------
		Form errors in our own words, under the field, instead of the browser's
		bubbles. The rules stay in the markup (required, minlength, type="email").
		- on submit every field is checked, the first wrong one gets the focus
		- a field with an error is checked again as it is edited; a field that
		  was edited — when it is left (form[data-live-validation]: any field)
		- the text goes to [data-error] of the .field (made when missing; for a
		  lone checkbox — after its label)
		- a button with formnovalidate (draft) and form[data-no-validation] skip it
		---------------------------------------------------------------------- */
	const initValidation = () => {
		let uid = 0;
		$$('form:not([data-no-validation])').forEach((form) => { form.noValidate = true; });

		const message = (field) => {
			const v = field.validity;
			if (v.valueMissing) {
				if (field.type === 'checkbox') return i18n.errCheck;
				return field.type === 'radio' || field.tagName === 'SELECT' ? i18n.errChoose : i18n.errRequired;
			}
			if (v.typeMismatch && field.type === 'email') return i18n.errEmail;
			if (v.tooShort) return String(i18n.errShort).replace('{n}', field.minLength);
			if (v.customError) return field.validationMessage;
			return i18n.errInvalid;
		};

		// What the visitor sees and focuses: the button of a styled select (select.js)
		const control = (field) => (field.dataset.cselect && $('.cselect__button', field.parentNode)) || field;

		const slot = (field) => {
			const box = field.closest('.field, .check, .switch');
			if (!box) return null;
			const inField = box.matches('.field');
			let out = inField ? $('[data-error]', box) : box.nextElementSibling;
			if (!out || !out.matches('[data-error]')) {
				out = document.createElement('span');
				out.className = 'field__error';
				out.setAttribute('data-error', '');
				out.hidden = true;
				if (inField) box.appendChild(out);
				else box.after(out);
			}
			if (!out.id) out.id = `field-error-${++uid}`;
			return out;
		};

		const clear = (field) => {
			const el = control(field);
			if (el.getAttribute('aria-invalid') !== 'true') return;
			el.removeAttribute('aria-invalid');
			const out = slot(field);
			if (!out) return;
			el.setAttribute('aria-describedby', (el.getAttribute('aria-describedby') || '').split(' ').filter((id) => id && id !== out.id).join(' '));
			if (!el.getAttribute('aria-describedby')) el.removeAttribute('aria-describedby');
			// A slot shared by several fields (radio group) is emptied when none of them is wrong
			if (!$$('[aria-invalid="true"]', out.closest('.field') || out.parentNode).length) {
				out.hidden = true;
				out.textContent = '';
			}
		};

		// [data-match="input-id"] (repeat the password): must be the same as that field
		const match = (field) => {
			const other = field.dataset.match && document.getElementById(field.dataset.match);
			if (other) field.setCustomValidity(field.value !== '' && field.value !== other.value ? i18n.errMatch : '');
		};

		const check = (field) => {
			match(field);
			if (!field.willValidate || field.validity.valid) {
				clear(field);
				return true;
			}
			const el = control(field);
			const out = slot(field);
			el.setAttribute('aria-invalid', 'true');
			if (out) {
				out.textContent = message(field);
				out.hidden = false;
				const ids = (el.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
				if (!ids.includes(out.id)) el.setAttribute('aria-describedby', ids.concat(out.id).join(' '));
			}
			return false;
		};

		const isField = (el) => el && el.form && !el.form.hasAttribute('data-no-validation') && 'validity' in el && el.tagName !== 'BUTTON';
		// A click on the button of a styled select is about its <select>
		const fieldOf = (el) => (el && el.classList && el.classList.contains('cselect__button') ? $('select', el.parentNode) : el);

		// Capture: before the form's own submit handlers, which must not run for a wrong form
		document.addEventListener('submit', (e) => {
			const form = e.target;
			if (form.hasAttribute('data-no-validation') || (e.submitter && e.submitter.formNoValidate)) return;

			const wrong = Array.from(form.elements).filter((field) => isField(field) && !check(field));
			if (!wrong.length) return;
			e.preventDefault();
			e.stopImmediatePropagation();

			const first = control(wrong[0]);
			// A wrong field that is not on the screen cannot be shown: say it in a toast
			if (!first.offsetParent || !slot(wrong[0])) toast(message(wrong[0]), 'danger');
			else first.focus();
		}, true);

		const recheck = (e) => {
			const field = e.target;
			if (!isField(field)) return;
			field.dataset.edited = '1';
			// A radio group is wrong or right as a whole
			const group = field.type === 'radio' ? $$(`input[type="radio"][name="${field.name}"]`, field.form) : [field];
			group.forEach((item) => { if (control(item).getAttribute('aria-invalid') === 'true') check(item); });
		};
		document.addEventListener('input', recheck);
		document.addEventListener('change', recheck);

		document.addEventListener('focusout', (e) => {
			const field = fieldOf(e.target);
			if (!isField(field)) return;
			// Still inside the same styled select (its list is open)
			if (e.relatedTarget && field.dataset.cselect && field.parentNode.contains(e.relatedTarget)) return;
			if (field.dataset.edited || field.form.hasAttribute('data-live-validation')) check(field);
		});
	};

	/* ----------------------------------------------------------------------
		Grid / list view: [data-view-toggle="results-id"] > button[data-view]
		Remembers the choice in localStorage
		---------------------------------------------------------------------- */
	const initViewToggle = () => {
		$$('[data-view-toggle]').forEach((group) => {
			const results = document.getElementById(group.dataset.viewToggle);
			if (!results) return;
			const buttons = $$('[data-view]', group);

			const apply = (view) => {
				results.classList.toggle('cards--list', view === 'list');
				buttons.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.view === view)));
				store.set('p4m-view', view);
			};

			buttons.forEach((b) => b.addEventListener('click', () => apply(b.dataset.view)));
			apply(store.get('p4m-view') === 'list' ? 'list' : 'grid');
		});
	};

	/* ----------------------------------------------------------------------
		Gallery: [data-gallery] with [data-slide] items, [data-thumb] buttons,
		[data-gallery-prev] / [data-gallery-next], [data-gallery-counter]
		Arrow keys and swipe work on the main photo (not on a playing video:
		there they seek); a video on a slide that gets hidden is paused
		---------------------------------------------------------------------- */
	const initGallery = () => {
		$$('[data-gallery]').forEach((root) => {
			const slides = $$('[data-slide]', root);
			const thumbs = $$('[data-thumb]', root);
			const counter = $('[data-gallery-counter]', root);
			const main = $('.gallery__main', root);
			if (slides.length < 2) return;
			let current = 0;

			const show = (index) => {
				current = (index + slides.length) % slides.length;
				slides.forEach((s, i) => {
					s.hidden = i !== current;
					const video = s.hidden ? $('video', s) : null;
					if (video && !video.paused) video.pause();
				});
				thumbs.forEach((t, i) => t.setAttribute('aria-current', String(i === current)));
				if (counter) counter.textContent = `${current + 1} / ${slides.length}`;

				// The active thumbnail stays visible inside its strip
				const thumb = thumbs[current];
				const strip = thumb && thumb.closest('.gallery__thumbs');
				if (strip) revealInStrip(thumb, strip);
			};

			const prev = $('[data-gallery-prev]', root);
			const next = $('[data-gallery-next]', root);
			if (prev) prev.addEventListener('click', () => show(current - 1));
			if (next) next.addEventListener('click', () => show(current + 1));
			thumbs.forEach((t, i) => t.addEventListener('click', () => show(i)));

			main.addEventListener('keydown', (e) => {
				if (e.target.closest('video')) return;
				if (e.key === 'ArrowLeft') show(current - 1);
				if (e.key === 'ArrowRight') show(current + 1);
			});

			let startX = null;
			main.addEventListener('touchstart', (e) => { startX = e.target.closest('video') ? null : e.touches[0].clientX; }, { passive: true });
			main.addEventListener('touchend', (e) => {
				if (startX === null) return;
				const dx = e.changedTouches[0].clientX - startX;
				if (Math.abs(dx) > 40) show(current + (dx < 0 ? 1 : -1));
				startX = null;
			});

			show(0);
		});
	};

	/* ----------------------------------------------------------------------
		Video facade: button[data-video="file.mp4"] with a poster photo. Nothing
		of the video loads until the tap; then a <video> replaces the button and
		starts. data-video-label names the player for screen readers
		---------------------------------------------------------------------- */
	const initVideos = () => {
		$$('[data-video]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const poster = $('img', btn);
				const video = document.createElement('video');
				video.className = 'video-player';
				video.controls = true;
				video.playsInline = true;
				video.preload = 'auto';
				if (poster) video.poster = poster.currentSrc || poster.src;
				video.setAttribute('aria-label', btn.dataset.videoLabel || '');
				// Bad file or codec is ours to fix; network drops (codes 1–2) are not
				video.addEventListener('error', () => {
					const err = video.error;
					if (err && err.code >= 3) reportError(`Video error ${err.code}: ${err.message || ''}`, btn.dataset.video);
				});
				video.src = btn.dataset.video;
				btn.replaceWith(video);
				video.focus();
				// Blocked autoplay is fine: the controls are there
				video.play().catch(() => {});
			});
		});
	};

	/* ----------------------------------------------------------------------
		Show phone: button[data-reveal-phone="listing id"] gets the number from
		  phone.php and turns into a tel: link ([data-reveal-call] also dials)
		View: [data-listing-view="listing id"] is counted by view.php (beacon)
		Share: [data-share] uses the system share sheet or copies the link
		---------------------------------------------------------------------- */
	const initContacts = () => {
		// The number comes from phone.php on the first click (it is not in the page);
		// every button of the listing then becomes a tel: link, "Call" dials right away
		$$('[data-reveal-phone]').forEach((btn) => {
			btn.addEventListener('click', async () => {
				const id = btn.dataset.revealPhone;
				let data;
				btn.disabled = true;
				try {
					data = await postJson(i18n.phoneUrl, { id });
				} catch (err) {
					btn.disabled = false;
					toast(err.message, 'danger');
					return;
				}

				let focused = null;
				$$(`[data-reveal-phone="${id}"]`).forEach((el) => {
					const link = document.createElement('a');
					link.className = el.className;
					link.href = 'tel:' + data.tel;
					link.innerHTML = el.querySelector('svg') ? el.querySelector('svg').outerHTML : '';
					link.append(el.hasAttribute('data-reveal-call') ? el.textContent.trim() : data.phone);
					el.replaceWith(link);
					if (el === btn) focused = link;
				});
				if (btn.hasAttribute('data-reveal-call')) window.location.href = 'tel:' + data.tel;
				else if (focused) focused.focus();
			});
		});

		// A view: sent once a day per listing from this browser (view.php also skips repeats)
		const viewed = $('[data-listing-view]');
		if (viewed && navigator.sendBeacon) {
			const id = viewed.dataset.listingView;
			const key = `p4m:view:${id}`;
			const last = Number(store.get(key)) || 0;
			if (Date.now() - last > 864e5) {
				const body = new FormData();
				body.append('id', id);
				if (navigator.sendBeacon(i18n.viewUrl, body)) store.set(key, String(Date.now()));
			}
		}

		$$('[data-share]').forEach((btn) => {
			btn.addEventListener('click', async () => {
				const data = { title: document.title, url: location.href };
				if (navigator.share) {
					try { await navigator.share(data); } catch (err) { /* cancelled */ }
					return;
				}
				try {
					await navigator.clipboard.writeText(location.href);
					toast(i18n.linkCopied, 'info');
				} catch (err) { /* no clipboard */ }
			});
		});
	};

	/* ----------------------------------------------------------------------
		Post an ad: [data-post-form]
		- category select [data-category-select] (data-subcats JSON) fills
		  [data-subcategory-select]
		- photos: [data-photo-input], [data-dropzone], [data-photo-list],
		  <template id="photo-item-tpl">; first photo is the cover. Each photo is
		  prepared in the browser (see preparePhoto below), then uploaded right
		  away to [data-upload-url]; the form sends only photo_ids[] in order.
		  Photos already uploaded (editing, a form with an error) come in
		  [data-photo-list] data-photos='[{"id", "url"}]'
		- [data-subcategory-select] data-selected: subcategory chosen on load
		- blocks of some subcategories: [data-for="slug slug…"], a district block
		  also [data-city] = the address word of its city, typed in [data-city-input]
		- live preview: inputs [data-bind="…"] → [data-preview="…"]
		---------------------------------------------------------------------- */
	const initPostForm = () => {
		const form = $('[data-post-form]');
		if (!form) return;

		const preview = $('[data-preview-card]');
		const p = (name) => (preview ? $(`[data-preview="${name}"]`, preview) : null);
		const field = (name) => $(`[data-bind="${name}"]`, form);

		// Category → subcategories
		const catSelect = $('[data-category-select]', form);
		const subSelect = $('[data-subcategory-select]', form);
		let subcats = {};
		try { subcats = JSON.parse(catSelect.dataset.subcats || '{}'); } catch (err) { subcats = {}; }

		const fillSubcats = () => {
			const list = subcats[catSelect.value] || [];
			const placeholder = subSelect.options[0];
			subSelect.innerHTML = '';
			subSelect.appendChild(placeholder);
			list.forEach((item) => subSelect.add(new Option(item.name, item.slug)));
			subSelect.disabled = list.length === 0;
			placeholder.textContent = list.length ? placeholder.dataset.choose : placeholder.dataset.empty;
			if (subSelect.dataset.selected) {
				subSelect.value = subSelect.dataset.selected;
				subSelect.dataset.selected = '';
			}

			// Preview tint + icon
			const ph = p('media') ? $('.ph', p('media')) : null;
			if (ph) {
				ph.className = ph.className.replace(/tint--[\w-]+/g, '').trim() + ' tint--' + (catSelect.value || 'neutral');
				$$('[data-preview-icon]', ph).forEach((icon) => { icon.hidden = icon.dataset.previewIcon !== (catSelect.value || 'default'); });
			}
		};
		// Subcategory → its blocks [data-for="slug slug…"] (deal, kind, brand, details); a district
		// block also needs its city [data-city] typed in the place field. Condition is hidden where it
		// does not apply. Hidden blocks are disabled, so the form sends only the chosen ones
		const conditionField = $('[data-condition-field]', form);
		const cityInput = $('[data-city-input]', form);
		let noCondition = [];
		try { noCondition = JSON.parse(subSelect.dataset.noCondition || '[]'); } catch (err) { noCondition = []; }

		// "Novi Sad", "нови сад", "Cacak" → the address word of the place (as url_slug() in PHP)
		const CYR = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', ђ: 'dj', е: 'e', ж: 'z', з: 'z', и: 'i', ј: 'j', к: 'k', л: 'l', љ: 'lj', м: 'm', н: 'n', њ: 'nj', о: 'o', п: 'p', р: 'r', с: 's', т: 't', ћ: 'c', у: 'u', ф: 'f', х: 'h', ц: 'c', ч: 'c', џ: 'dz', ш: 's' };
		const placeWord = (text) => text.toLowerCase().replace(/đ/g, 'dj').replace(/[Ѐ-ӿ]/g, (ch) => CYR[ch] || '')
			.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

		const toggleAttrs = () => {
			const place = cityInput ? placeWord(cityInput.value) : '';
			$$('[data-for]', form).forEach((set) => {
				const on = set.dataset.for.split(' ').includes(subSelect.value) && (!set.dataset.city || set.dataset.city === place);
				set.hidden = !on;
				set.disabled = !on;
			});
			if (conditionField) {
				const off = noCondition.includes(subSelect.value);
				conditionField.hidden = off;
				conditionField.disabled = off;
			}
		};

		if (catSelect && subSelect) {
			catSelect.addEventListener('change', () => {
				fillSubcats();
				toggleAttrs();
			});
			subSelect.addEventListener('change', toggleAttrs);
			if (cityInput) cityInput.addEventListener('input', toggleAttrs);
			fillSubcats();
			toggleAttrs();
		}

		// Live preview
		const updatePreview = () => {
			if (!preview) return;

			const title = field('title').value.trim();
			p('title').textContent = title || i18n.previewTitle;

			const agreement = field('agreement').checked;
			const price = field('price');
			price.disabled = agreement;
			const currency = field('currency').value === 'EUR' ? '€' : 'RSD';
			const amount = price.value.replace(/\D/g, '');

			if (agreement || !amount) {
				p('price').innerHTML = '';
				const span = document.createElement('span');
				span.className = 'price__num price__num--text';
				span.textContent = agreement ? i18n.agreement : '—';
				p('price').appendChild(span);
			} else {
				p('price').innerHTML = `<span class="price__num">${formatNumber(amount)}</span>&nbsp;<span class="price__cur">${currency}</span>`;
			}

			// The place is a select: its value is the Latin name, the preview shows the name as listed
			const city = field('city');
			p('city').textContent = city.value && city.selectedOptions[0] ? city.selectedOptions[0].text : '';
			p('tag').hidden = !field('negotiable').checked || agreement;
		};
		$$('[data-bind]', form).forEach((input) => {
			input.addEventListener('input', updatePreview);
			input.addEventListener('change', updatePreview);
		});
		// A price is needed unless "by agreement" is ticked (the field is then disabled and not checked)
		if (field('price')) field('price').required = true;
		updatePreview();

		// "Publish" works once everything required is filled in (category, subcategory, kind, title,
		// description, price or "by agreement", place, terms); what is missing shows under the fields
		// as they are left. "Save draft" (formnovalidate) is always available
		const publish = $('[type="submit"]:not([formnovalidate])', form);
		const updatePublish = () => { if (publish) publish.disabled = !form.checkValidity(); };
		form.addEventListener('input', updatePublish);
		form.addEventListener('change', updatePublish);
		updatePublish();

		// Photos. Prepared in the browser before upload (CLAUDE.md, section 2): EXIF
		// rotation applied, long side up to 2560 px, JPEG 0.9, which also drops EXIF
		// and GPS. A 5–12 MB phone shot becomes ~1 MB. Small files stay as they are,
		// so they are not compressed twice. One photo at a time: phones run out of memory
		const MAX_SIDE = 2560;
		const KEEP_BYTES = 1.5 * 1024 * 1024;
		const MAX_BYTES = 30 * 1024 * 1024;

		const input = $('[data-photo-input]', form);
		const dropzone = $('[data-dropzone]', form);
		const list = $('[data-photo-list]', form);
		const tpl = $('#photo-item-tpl');
		const max = Number(input ? input.dataset.max : 12) || 12;
		// { id, url, name, busy }: busy while being prepared and uploaded, id from the server
		let photos = [];
		let queue = Promise.resolve();
		const uploadUrl = form.dataset.uploadUrl;
		const csrf = form.elements.csrf ? form.elements.csrf.value : '';
		try {
			photos = JSON.parse(list.dataset.photos || '[]').map((photo) => ({ id: photo.id, url: photo.url, name: '', busy: false }));
		} catch (err) { photos = []; }

		// Decoded image with EXIF rotation applied; error.unreadable for formats the
		// browser cannot open (e.g. HEIC outside Safari)
		const decodePhoto = async (file) => {
			try {
				return await createImageBitmap(file, { imageOrientation: 'from-image' });
			} catch (err) { /* older browsers: <img> applies EXIF rotation itself */ }

			const url = URL.createObjectURL(file);
			try {
				const img = new Image();
				img.src = url;
				await img.decode();
				return img;
			} catch (err) {
				const error = new Error('Photo cannot be decoded');
				error.unreadable = true;
				throw error;
			} finally {
				URL.revokeObjectURL(url);
			}
		};

		const preparePhoto = async (file) => {
			const source = await decodePhoto(file);
			const scale = Math.min(1, MAX_SIDE / Math.max(source.width, source.height));

			if (scale === 1 && file.size <= KEEP_BYTES) {
				if (source.close) source.close();
				return file;
			}

			const canvas = document.createElement('canvas');
			canvas.width = Math.round(source.width * scale);
			canvas.height = Math.round(source.height * scale);
			const ctx = canvas.getContext('2d');
			// Transparent PNG → white, not black, in JPEG
			ctx.fillStyle = '#fff';
			ctx.fillRect(0, 0, canvas.width, canvas.height);
			ctx.imageSmoothingQuality = 'high';
			ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
			if (source.close) source.close();

			const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
			// Release the canvas memory right away (matters on phones)
			canvas.width = 0;
			canvas.height = 0;
			if (!blob) throw new Error('canvas.toBlob returned nothing');

			return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: file.lastModified });
		};

		// One photo to the server; the error carries the text to show (from the server when it has one)
		const uploadPhoto = async (file) => {
			const body = new FormData();
			body.append('photo', file, file.name);
			body.append('csrf', csrf);

			let response;
			let data = {};
			try {
				response = await fetch(uploadUrl, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
				data = await response.json();
			} catch (err) { /* network or not JSON: the generic message below */ }

			if (!response || !response.ok || !data.id) {
				const error = new Error(data.error || i18n.photoUploadFailed);
				error.shown = true;
				throw error;
			}

			return data;
		};

		const renderPhotos = () => {
			list.innerHTML = '';
			photos.forEach((photo, index) => {
				const item = tpl.content.firstElementChild.cloneNode(true);
				const img = $('img', item);
				img.alt = photo.name;
				// Being prepared: a pulsing tile, no actions yet
				if (photo.busy) {
					item.classList.add('is-busy');
					item.setAttribute('aria-busy', 'true');
					item.title = photo.name;
					img.hidden = true;
					$('[data-cover-tag]', item).hidden = true;
					$('.photo-item__actions', item).hidden = true;
					list.appendChild(item);
					return;
				}
				img.src = photo.url;
				$('[data-cover-tag]', item).hidden = index !== 0;
				const coverBtn = $('[data-make-cover]', item);
				coverBtn.hidden = index === 0;
				coverBtn.addEventListener('click', () => {
					photos.unshift(photos.splice(index, 1)[0]);
					renderPhotos();
				});
				$('[data-remove-photo]', item).addEventListener('click', () => {
					URL.revokeObjectURL(photo.url);
					photos.splice(index, 1);
					renderPhotos();
				});
				list.appendChild(item);
			});

			// Cover in preview
			const media = p('media');
			if (media) {
				const img = $('img', media);
				const ph = $('.ph', media);
				if (photos[0] && !photos[0].busy) {
					img.src = photos[0].url;
					img.hidden = false;
					ph.hidden = true;
				} else {
					img.hidden = true;
					ph.hidden = false;
				}
			}
		};

		const addFiles = (files) => {
			let limitShown = false;

			Array.from(files).forEach((file) => {
				if (!file.type.startsWith('image/')) return;
				if (photos.length >= max) {
					if (!limitShown) toast(i18n.photosLimit, 'info');
					limitShown = true;
					return;
				}
				if (file.size > MAX_BYTES) {
					toast(i18n.photoTooBig, 'info');
					return;
				}

				const photo = { id: 0, url: '', name: file.name, busy: true };
				photos.push(photo);
				queue = queue.then(async () => {
					try {
						const prepared = await preparePhoto(file);
						photo.id = (await uploadPhoto(prepared)).id;
						photo.url = URL.createObjectURL(prepared);
						photo.busy = false;
					} catch (err) {
						photos.splice(photos.indexOf(photo), 1);
						toast(err.shown ? err.message : i18n.photoUnreadable, 'info');
						// A format the browser cannot open is the user's case, upload errors are logged
						// by the server; anything else is ours
						if (!err.unreadable && !err.shown) reportError(err.message, 'post form: preparePhoto', err.stack);
					}
					renderPhotos();
				});
			});
			renderPhotos();
		};

		if (input && list && tpl) {
			input.addEventListener('change', () => {
				addFiles(input.files || []);
				input.value = '';
			});

			['dragenter', 'dragover'].forEach((type) => dropzone.addEventListener(type, (e) => {
				e.preventDefault();
				dropzone.classList.add('is-dragover');
			}));
			['dragleave', 'drop'].forEach((type) => dropzone.addEventListener(type, (e) => {
				e.preventDefault();
				dropzone.classList.remove('is-dragover');
			}));
			dropzone.addEventListener('drop', (e) => addFiles(e.dataTransfer.files));
			renderPhotos();
		}

		// Photo ids in display order go with the form; not while uploads are running
		form.addEventListener('submit', (e) => {
			if (photos.some((photo) => photo.busy)) {
				e.preventDefault();
				toast(i18n.photosBusy, 'info');
				return;
			}
			$$('input[name="photo_ids[]"]', form).forEach((el) => el.remove());
			photos.forEach((photo) => {
				const el = document.createElement('input');
				el.type = 'hidden';
				el.name = 'photo_ids[]';
				el.value = photo.id;
				form.appendChild(el);
			});
		});
	};

	/* ----------------------------------------------------------------------
		Messages: [data-chat] (data-chat-url = messages.php for "read")
		- conversation buttons [data-conv="id"] open [data-thread="id"]; unread
		  ones (.count-badge[data-unread]) are marked read when they are seen,
		  the badges in the header and bottom nav ([data-unread-total]) go down
		- [data-chat-back] returns to the list on phones
		- form[data-composer] sends to messages.php and appends the message;
		  Enter sends, Shift+Enter = new line. A new conversation (id 0) reloads
		  into the created one
		- the open thread asks for new messages (action poll) every 10 s while
		  the tab is visible; they are read at once, the header badges follow
		- [data-quick] buttons put a ready phrase into the input
		- [data-chat-search] filters the list
		---------------------------------------------------------------------- */
	const initChat = () => {
		const chat = $('[data-chat]');
		if (!chat) return;

		const convs = $$('[data-conv]', chat);
		const empty = $('.chat__empty', chat);
		const desktop = window.matchMedia('(min-width: 768px)');

		const scrollDown = (thread) => {
			const body = $('.thread__body', thread);
			if (body) body.scrollTop = body.scrollHeight;
		};

		// A message from messages.php ({id, mine, text, time, day}) at the end of the thread,
		// and as the last one in the conversation list (moved to the top)
		const appendMessage = (thread, message) => {
			const body = $('.thread__body', thread);
			if (!body || $(`[data-msg="${message.id}"]`, body)) return;

			const days = $$('.day-sep', body);
			if (!days.length || days[days.length - 1].textContent !== message.day) {
				const sep = document.createElement('p');
				sep.className = 'day-sep';
				sep.textContent = message.day;
				body.appendChild(sep);
			}
			const msg = document.createElement('div');
			msg.className = `msg ${message.mine ? 'msg--out' : 'msg--in'}`;
			msg.dataset.msg = message.id;
			msg.textContent = message.text;
			const time = document.createElement('span');
			time.className = 'msg__time';
			time.textContent = message.time;
			msg.appendChild(time);
			body.appendChild(msg);

			const conv = $(`[data-conv="${thread.dataset.thread}"]`, chat);
			if (conv) {
				const last = $('.conv__last', conv);
				const when = $('.conv__time', conv);
				if (last) last.textContent = message.mine ? `${i18n.you}: ${message.text}` : message.text;
				if (when) when.textContent = message.time;
				const item = conv.closest('li');
				item.parentElement.prepend(item);
			}
		};

		// Phones: an open conversation is the whole screen (site.css). With the keyboard open the
		// visible part is lower than the screen: the thread takes its height, the field stays in view
		if (window.visualViewport) {
			const fitThread = () => {
				chat.style.setProperty('--chat-h', `${Math.round(window.visualViewport.height)}px`);
				if (!desktop.matches && chat.classList.contains('is-thread-open')) window.scrollTo(0, 0);
			};
			window.visualViewport.addEventListener('resize', fitThread);
			fitThread();
		}

		// New messages of the open thread: every POLL_SECONDS while the tab is visible
		const POLL_SECONDS = 10;
		let polling = false;
		const poll = async () => {
			const thread = $('[data-thread]:not([hidden])', chat);
			const shown = thread && thread.dataset.thread !== '0' && (desktop.matches || chat.classList.contains('is-thread-open'));
			if (!shown || polling || document.hidden) return;

			const ids = $$('[data-msg]', thread).map((el) => Number(el.dataset.msg));
			polling = true;
			try {
				const data = await postJson(chat.dataset.chatUrl, { action: 'poll', conversation: thread.dataset.thread, after: Math.max(0, ...ids) });
				const body = $('.thread__body', thread);
				const atBottom = body.scrollHeight - body.scrollTop - body.clientHeight < 80;
				data.messages.forEach((message) => appendMessage(thread, message));
				if (data.messages.length && atBottom) scrollDown(thread);
				$$('[data-unread-total]').forEach((total) => {
					if (data.unread > 0) total.textContent = data.unread;
					else total.remove();
				});
			} catch (err) {
				// Offline or logged out: the next round tries again
			} finally {
				polling = false;
			}
		};
		setInterval(poll, POLL_SECONDS * 1000);
		document.addEventListener('visibilitychange', poll);

		// Seen on screen: the server marks it read (a failure only leaves it unread)
		const markRead = (btn) => {
			const badge = $('.count-badge[data-unread]', btn);
			if (!badge) return;
			const n = Number(badge.dataset.unread) || 0;
			badge.remove();
			btn.classList.remove('is-unread');
			$$('[data-unread-total]').forEach((total) => {
				const left = (Number(total.textContent) || 0) - n;
				if (left > 0) total.textContent = left;
				else total.remove();
			});
			postJson(chat.dataset.chatUrl, { action: 'read', conversation: btn.dataset.conv }).catch(() => {});
		};

		// seen = false: opened behind the list on a phone (first load without ?c=)
		const open = (id, { focus = true, seen = true } = {}) => {
			let opened = null;
			$$('[data-thread]', chat).forEach((thread) => {
				const active = thread.dataset.thread === id;
				thread.hidden = !active;
				if (active) opened = thread;
			});
			$$('[data-conv]', chat).forEach((btn) => {
				const active = btn.dataset.conv === id;
				btn.setAttribute('aria-current', String(active));
				if (active && (seen || desktop.matches)) markRead(btn);
			});
			if (empty) empty.hidden = Boolean(opened);
			if (!opened) return;

			chat.classList.add('is-thread-open');
			scrollDown(opened);
			// A new conversation keeps ?listing= in the address
			if (id !== '0') {
				const params = new URLSearchParams(location.search);
				params.delete('listing');
				params.set('c', id);
				history.replaceState(null, '', '?' + params.toString());
			}
			if (focus) {
				const field = $('textarea', opened);
				if (field && desktop.matches) field.focus();
			}
		};

		convs.forEach((btn) => btn.addEventListener('click', () => open(btn.dataset.conv)));

		$$('[data-chat-back]', chat).forEach((btn) => btn.addEventListener('click', () => {
			chat.classList.remove('is-thread-open');
			const current = $('[data-conv][aria-current="true"]', chat);
			if (current) current.focus();
		}));

		// Send a message
		$$('form[data-composer]', chat).forEach((form) => {
			const field = $('textarea', form);
			const submit = $('[type="submit"]', form);
			const thread = form.closest('[data-thread]');
			let sending = false;

			const send = async () => {
				const text = field.value.trim();
				if (!text || sending) return;
				sending = true;
				submit.disabled = true;

				let data;
				try {
					// getAttribute: the hidden input named "action" shadows form.action
					data = await postJson(form.getAttribute('action'), Object.fromEntries(new FormData(form)));
				} catch (err) {
					toast(err.message, 'danger');
					return;
				} finally {
					sending = false;
					submit.disabled = false;
				}

				if (data.created || thread.dataset.thread === '0') {
					const params = new URLSearchParams(location.search);
					params.delete('listing');
					params.set('c', data.conversation);
					window.location.href = '?' + params.toString();
					return;
				}

				appendMessage(thread, data.message);
				field.value = '';
				field.dispatchEvent(new Event('input'));
				scrollDown(thread);
			};

			form.addEventListener('submit', (e) => {
				e.preventDefault();
				send();
				field.focus();
			});
			field.addEventListener('keydown', (e) => {
				if (e.key === 'Enter' && !e.shiftKey) {
					e.preventDefault();
					send();
				}
			});
		});

		// Quick replies
		$$('[data-quick]', chat).forEach((btn) => btn.addEventListener('click', () => {
			const field = $('textarea', btn.closest('[data-thread]'));
			field.value = btn.textContent.trim();
			field.dispatchEvent(new Event('input'));
			field.focus();
		}));

		// Search conversations
		const search = $('[data-chat-search]', chat);
		const noResults = $('[data-chat-no-results]', chat);
		if (search) {
			search.addEventListener('input', () => {
				const q = search.value.trim().toLowerCase();
				let visible = 0;
				convs.forEach((btn) => {
					const show = !q || btn.textContent.toLowerCase().includes(q);
					btn.closest('li').hidden = !show;
					if (show) visible += 1;
				});
				if (noResults) noResults.hidden = visible > 0;
			});
		}

		const active = $('[data-conv][aria-current="true"]', chat);
		const openOnLoad = chat.hasAttribute('data-open-on-load');
		if (active) open(active.dataset.conv, { focus: false, seen: openOnLoad });
		if (!openOnLoad) chat.classList.remove('is-thread-open');
	};

	/* ----------------------------------------------------------------------
		Folding blocks: every <details> opens and closes with a short height
		animation (questions on Help, "More places"). While it closes it has
		.is-closing, so a chevron can turn back at once
		---------------------------------------------------------------------- */
	const initDetails = () => {
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

		$$('details').forEach((details) => {
			const summary = $('summary', details);
			if (!summary || !details.animate) return;
			let anim = null;

			summary.addEventListener('click', (e) => {
				e.preventDefault();
				const opening = !details.open || details.classList.contains('is-closing');
				const from = details.offsetHeight;
				if (anim) anim.cancel();

				// The final height: measured in the target state, before anything is painted
				details.classList.toggle('is-closing', !opening);
				details.open = opening;
				const to = details.offsetHeight;
				details.open = true;

				details.style.overflow = 'hidden';
				const run = details.animate({ height: [`${from}px`, `${to}px`] }, { duration: 220, easing: 'ease' });
				anim = run;
				run.onfinish = () => {
					if (!opening) details.open = false;
					details.classList.remove('is-closing');
					details.style.overflow = '';
					anim = null;
				};
			});
		});
	};

	/* ----------------------------------------------------------------------
		"Show the rest": button[data-expand="id"] adds .is-expanded to the block
		(its CSS shows the hidden part), with the same height animation
		---------------------------------------------------------------------- */
	const initExpand = () => {
		const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		$$('[data-expand]').forEach((btn) => {
			const target = document.getElementById(btn.dataset.expand);
			if (!target) return;

			btn.addEventListener('click', () => {
				const from = target.offsetHeight;
				const on = !target.classList.contains('is-expanded');
				target.classList.toggle('is-expanded', on);
				btn.setAttribute('aria-expanded', String(on));
				const to = target.offsetHeight;
				if (still || !target.animate || from === to) return;

				// Closing: the part stays visible until the height has gone down
				if (!on) target.classList.add('is-expanded');
				target.style.overflow = 'hidden';
				target.animate({ height: [`${from}px`, `${to}px`] }, { duration: 220, easing: 'ease' }).onfinish = () => {
					if (!on) target.classList.remove('is-expanded');
					target.style.overflow = '';
				};
			});
		});
	};

	/* ----------------------------------------------------------------------
		"Back to top": [data-to-top] (footer.php) appears after a screen and a
		half of scrolling
		---------------------------------------------------------------------- */
	const initToTop = () => {
		const btn = $('[data-to-top]');
		if (!btn) return;
		let shown = false;

		const update = () => {
			const show = window.scrollY > window.innerHeight * 1.5;
			if (show === shown) return;
			shown = show;
			btn.classList.toggle('is-visible', show);
		};
		window.addEventListener('scroll', update, { passive: true });
		update();

		btn.addEventListener('click', () => window.scrollTo({ top: 0 }));
	};

	/* ----------------------------------------------------------------------
		Cookie consent and Google Analytics 4: [data-consent] is the banner
		(partials/consent.php, only while a GA4 id is set), data-ga-id its id.
		The choice is the p4m_consent cookie (granted / denied, a year).
		gtag.js is loaded only after "Accept" and after the page has loaded
		(idle time), so it never delays the page; "Decline" later switches
		storage off and removes the _ga cookies. [data-consent-open] (footer)
		shows the banner again
		---------------------------------------------------------------------- */
	const initConsent = () => {
		const banner = $('[data-consent]');
		if (!banner) return;

		const id = banner.dataset.gaId;
		const cookie = 'p4m_consent';
		const year = 365 * 24 * 3600;
		let loaded = false;

		const choice = () => (document.cookie.match(new RegExp(`(?:^|; )${cookie}=(granted|denied)`)) || [])[1] || '';

		const save = (value) => {
			document.cookie = `${cookie}=${value}; Max-Age=${year}; Path=/; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
		};

		const load = () => {
			if (loaded || !id) return;
			loaded = true;
			window.dataLayer = window.dataLayer || [];
			// gtag() must push the arguments object itself, not an array
			window.gtag = function gtag() { window.dataLayer.push(arguments); };
			window.gtag('consent', 'default', {
				analytics_storage: 'granted', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
			});
			window.gtag('js', new Date());
			// One-time links (/nova-lozinka?token=…, e-mail confirmation) never reach Google:
			// the token is cut from the address of the page and of the one before it
			const clean = (href) => {
				try {
					const address = new URL(href);
					if (!address.searchParams.has('token')) return href;
					address.searchParams.delete('token');
					return address.href;
				} catch (error) {
					return '';
				}
			};
			window.gtag('config', id, {
				page_location: clean(location.href),
				page_referrer: document.referrer ? clean(document.referrer) : '',
			});
			const script = document.createElement('script');
			script.async = true;
			script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`;
			document.head.append(script);
		};

		// After load, in idle time: analytics never competes with the page
		const loadLater = () => {
			const run = () => ('requestIdleCallback' in window ? requestIdleCallback(load, { timeout: 3000 }) : setTimeout(load, 200));
			if (document.readyState === 'complete') run();
			else window.addEventListener('load', run, { once: true });
		};

		// _ga, _ga_<id>: on this host and on the parent domain (GA sets them there)
		const removeGaCookies = () => {
			const domains = ['', location.hostname, `.${location.hostname.replace(/^www\./, '')}`];
			document.cookie.split('; ').map((c) => c.split('=')[0]).filter((name) => /^_ga/.test(name)).forEach((name) => {
				domains.forEach((domain) => {
					document.cookie = `${name}=; Max-Age=0; Path=/${domain ? `; Domain=${domain}` : ''}`;
				});
			});
		};

		banner.addEventListener('click', (event) => {
			const button = event.target.closest('[data-consent-choice]');
			if (!button) return;
			const value = button.dataset.consentChoice;
			save(value);
			banner.hidden = true;
			if (value === 'granted') {
				loadLater();
			} else {
				if (loaded) window.gtag('consent', 'update', { analytics_storage: 'denied' });
				removeGaCookies();
			}
		});

		document.addEventListener('click', (event) => {
			if (!event.target.closest('[data-consent-open]')) return;
			banner.hidden = false;
			$('[data-consent-choice="granted"]', banner).focus();
		});

		if (choice() === 'granted') loadLater();
	};

	/* ---------- Init ---------- */
	window.P4M = { toast };

	initConsent();
	initFavorites();
	initSavedSearches();
	initTabs();
	initPills();
	initDrawers();
	initDialogs();
	initConfirm();
	initForms();
	initValidation();
	initViewToggle();
	initDetails();
	initExpand();
	initToTop();
	initGallery();
	initVideos();
	initContacts();
	initPostForm();
	initChat();
})();
