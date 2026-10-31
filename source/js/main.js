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

	/* ----------------------------------------------------------------------
		Toasts: toast('Saved'), toast('Copied', 'info')
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
		Favorites: button[data-fav="id"] with aria-pressed
		data-toast-on / data-toast-off: messages
		data-fav-remove: remove the card when unsaved (favorites page)
		---------------------------------------------------------------------- */
	const initFavorites = () => {
		document.addEventListener('click', (e) => {
			const btn = e.target.closest('[data-fav]');
			if (!btn) return;
			e.preventDefault();

			const saved = btn.getAttribute('aria-pressed') !== 'true';

			// Sync every heart for the same listing on the page
			$$(`[data-fav="${btn.dataset.fav}"]`).forEach((el) => el.setAttribute('aria-pressed', String(saved)));
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

			buttons.forEach((button) => button.addEventListener('click', () => apply(button)));
			apply(buttons.find((b) => b.getAttribute('aria-pressed') === 'true') || buttons[0]);
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

			// Back to desktop: reset
			window.matchMedia('(min-width: 1025px)').addEventListener('change', (e) => {
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
		data-done: toast after OK
		data-remove-target: CSS selector(s) of elements to remove after OK
		Otherwise follows the link or submits the form.
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

			if (pending.dataset.removeTarget) {
				$$(pending.dataset.removeTarget).forEach((el) => removeWithFade(el));
			}

			if (pending.dataset.done) {
				toast(pending.dataset.done);
			} else if (pending.tagName === 'A' && pending.getAttribute('href')) {
				window.location.href = pending.href;
			} else if (pending.form) {
				pending.form.requestSubmit();
			}
			pending = null;
		});
	};

	/* ----------------------------------------------------------------------
		Demo feedback until the backend is connected:
		- button/link[data-toast="Message"] shows a toast
		- form[data-demo="Message"] prevents submit, shows a toast,
		  closes its dialog; data-demo-reset also resets the form
		- checkbox[data-toast-on][data-toast-off] shows a toast on change
		---------------------------------------------------------------------- */
	const initDemo = () => {
		document.addEventListener('click', (e) => {
			const el = e.target.closest('[data-toast]:not([data-confirm])');
			if (!el) return;
			if (el.tagName === 'A' || el.type === 'submit') e.preventDefault();
			toast(el.dataset.toast, el.dataset.toastType || 'success');
		});

		$$('form[data-demo]').forEach((form) => {
			form.addEventListener('submit', (e) => {
				e.preventDefault();
				toast(form.dataset.demo);
				const dialog = form.closest('dialog');
				if (dialog) dialog.close();
				if (form.hasAttribute('data-demo-reset')) form.reset();
			});
		});

		document.addEventListener('change', (e) => {
			const box = e.target;
			if (!box.matches('input[type="checkbox"][data-toast-on]')) return;
			toast(box.checked ? box.dataset.toastOn : box.dataset.toastOff, 'info');
		});
	};

	/* ----------------------------------------------------------------------
		Small form helpers
		- [data-password-toggle] aria-controls="input-id"
		- [data-counter="output-id"] + maxlength: "12 / 80"
		- select[data-autosubmit] submits its form on change
		---------------------------------------------------------------------- */
	const initForms = () => {
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
				const thumb = thumbs[current];
				if (thumb) thumb.scrollIntoView({ block: 'nearest', inline: 'nearest' });
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
		Show phone: button[data-reveal-phone="+381 …"] turns into a tel: link
		Share: [data-share] uses the system share sheet or copies the link
		---------------------------------------------------------------------- */
	const initContacts = () => {
		$$('[data-reveal-phone]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const phone = btn.dataset.revealPhone;
				const link = document.createElement('a');
				link.className = btn.className;
				link.href = 'tel:' + phone.replace(/\s+/g, '');
				link.innerHTML = btn.querySelector('svg') ? btn.querySelector('svg').outerHTML : '';
				link.append(phone);
				btn.replaceWith(link);
				link.focus();
			});
		});

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
		if (catSelect && subSelect) {
			catSelect.addEventListener('change', fillSubcats);
			fillSubcats();
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

			const city = field('city');
			p('city').textContent = city.options[city.selectedIndex].text;
			p('tag').hidden = !field('negotiable').checked || agreement;
		};
		$$('[data-bind]', form).forEach((input) => {
			input.addEventListener('input', updatePreview);
			input.addEventListener('change', updatePreview);
		});
		updatePreview();

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
		Messages: [data-chat]
		- conversation buttons [data-conv="id"] open [data-thread="id"]
		- [data-chat-back] returns to the list on phones
		- form[data-composer] appends your message; Enter sends, Shift+Enter = new line
		- [data-quick] buttons put a ready phrase into the input
		- [data-chat-search] filters the list
		---------------------------------------------------------------------- */
	const initChat = () => {
		const chat = $('[data-chat]');
		if (!chat) return;

		const convs = $$('[data-conv]', chat);
		const empty = $('.chat__empty', chat);

		const scrollDown = (thread) => {
			const body = $('.thread__body', thread);
			if (body) body.scrollTop = body.scrollHeight;
		};

		const open = (id, { focus = true } = {}) => {
			let opened = null;
			$$('[data-thread]', chat).forEach((thread) => {
				const active = thread.dataset.thread === id;
				thread.hidden = !active;
				if (active) opened = thread;
			});
			$$('[data-conv]', chat).forEach((btn) => {
				const active = btn.dataset.conv === id;
				btn.setAttribute('aria-current', String(active));
				if (active) {
					btn.classList.remove('is-unread');
					const badge = $('.count-badge', btn);
					if (badge) badge.remove();
				}
			});
			if (empty) empty.hidden = Boolean(opened);
			if (!opened) return;

			chat.classList.add('is-thread-open');
			scrollDown(opened);
			const params = new URLSearchParams(location.search);
			params.set('c', id);
			history.replaceState(null, '', '?' + params.toString());
			if (focus) {
				const field = $('textarea', opened);
				if (field && window.matchMedia('(min-width: 768px)').matches) field.focus();
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
			const thread = form.closest('[data-thread]');
			const body = $('.thread__body', thread);

			const send = () => {
				const text = field.value.trim();
				if (!text) return;
				const now = new Date();
				const msg = document.createElement('div');
				msg.className = 'msg msg--out';
				msg.textContent = text;
				const time = document.createElement('span');
				time.className = 'msg__time';
				time.textContent = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
				msg.appendChild(time);
				body.appendChild(msg);
				field.value = '';
				scrollDown(thread);

				// Update the list item
				const conv = $(`[data-conv="${thread.dataset.thread}"]`, chat);
				if (conv) {
					const last = $('.conv__last', conv);
					const when = $('.conv__time', conv);
					if (last) last.textContent = text;
					if (when) when.textContent = time.textContent;
				}
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

		// After deleting a conversation: back to the list
		document.addEventListener('p4m:removed', () => {
			if (!$('[data-thread]:not([hidden])', chat)) {
				chat.classList.remove('is-thread-open');
				if (empty) empty.hidden = false;
			}
		});

		const active = $('[data-conv][aria-current="true"]', chat);
		if (active) open(active.dataset.conv, { focus: false });
		if (!chat.hasAttribute('data-open-on-load')) chat.classList.remove('is-thread-open');
	};

	/* ---------- Init ---------- */
	window.P4M = { toast };

	initFavorites();
	initTabs();
	initPills();
	initDrawers();
	initDialogs();
	initConfirm();
	initDemo();
	initForms();
	initViewToggle();
	initGallery();
	initVideos();
	initContacts();
	initPostForm();
	initChat();
})();
