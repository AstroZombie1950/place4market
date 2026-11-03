/* ==========================================================================
	Place4Market — admin UI (vanilla JS, no dependencies)
	Data attributes drive everything; see the comment above each block.
	========================================================================== */

(() => {
	'use strict';

	/* ----------------------------------------------------------------------
		Error reporting: uncaught errors and rejected promises → /js-error.php
		(server log, channel "js"); reportError(message, source, stack) for
		failures that are caught but still worth knowing about. Same block in main.js
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

	/* ----------------------------------------------------------------------
		Toasts: AdminUI.toast('Saved', 'success' | 'error' | 'info')
		---------------------------------------------------------------------- */
	const toast = (message, type = 'success') => {
		const region = $('[data-toast-region]');
		if (!region) return;

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

	/* ----------------------------------------------------------------------
		Fill fields from a JSON payload
		- elements with [data-fill="key"] get text (or value for inputs)
		- form fields with name="key" get value / checked
		---------------------------------------------------------------------- */
	const fillFields = (container, data) => {
		$$('[data-fill]', container).forEach((el) => {
			const value = data[el.dataset.fill];
			if (value === undefined) return;
			if (el.matches('input, select, textarea')) el.value = value;
			else el.textContent = value;
		});

		Object.entries(data).forEach(([key, value]) => {
			const field = container.querySelector(`[name="${CSS.escape(key)}"]`);
			if (!field) return;
			if (field.type === 'checkbox') field.checked = Boolean(value);
			else field.value = value;
			field.dispatchEvent(new Event('input', { bubbles: true }));
		});
	};

	const readPayload = (el) => {
		try {
			return JSON.parse(el.dataset.payload || '{}');
		} catch (err) {
			return {};
		}
	};

	/* ----------------------------------------------------------------------
		Sidebar: off-canvas on tablets/phones
		[data-sidebar-toggle] opens/closes, overlay and Esc close
		---------------------------------------------------------------------- */
	const initSidebar = () => {
		const sidebar = $('#sidebar');
		const overlay = $('[data-sidebar-overlay]');
		if (!sidebar) return;

		const toggles = $$('[data-sidebar-toggle]');
		const burger = $('.topbar__burger');

		const setOpen = (open) => {
			sidebar.classList.toggle('is-open', open);
			document.body.classList.toggle('is-locked', open);
			if (overlay) overlay.hidden = !open;
			toggles.forEach((btn) => btn.setAttribute('aria-expanded', String(open)));
			if (open) {
				const firstLink = $('.sidebar__link', sidebar);
				if (firstLink) firstLink.focus();
			} else if (burger && sidebar.contains(document.activeElement)) {
				burger.focus();
			}
		};

		toggles.forEach((btn) => btn.addEventListener('click', () => setOpen(!sidebar.classList.contains('is-open'))));
		if (overlay) overlay.addEventListener('click', () => setOpen(false));

		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && sidebar.classList.contains('is-open')) setOpen(false);
		});

		// Back to desktop layout (≥1024, admin.css): reset state
		window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => {
			if (e.matches) setOpen(false);
		});
	};

	/* ----------------------------------------------------------------------
		Tabs with panels: [data-tabs] wrapping role="tablist" + role="tab"
		Each tab has aria-controls="panel-id". Arrow keys, Home/End.
		[data-tabs-hash] keeps the open tab in the URL (#tab-name)
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

			// Initial tab: from URL hash, else aria-selected="true", else first
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

			// Links that open a tab: <a href="#photos" data-open-tab="tab-photos">
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
		Data tables: selection and bulk bar (filters and pages are on the server)
		table[data-table] id="x", rows tr[data-row][data-id]
		- selection: [data-check-all], [data-row-check]; bulk bar: [data-bulk="x"]
		- bulk actions: form[data-bulk-form="x"] sends ids[] of the checked rows;
		  [data-bulk-ids="x"] on a dialog opener puts them, comma-separated,
		  into the dialog's [name="id_list"]; [data-bulk-clear="x"] unchecks all
		- form[data-autosubmit] is sent when a select, file input or checkbox
		  changes (filters, photo upload, category switches)
		---------------------------------------------------------------------- */
	const checkedIds = (tableId) => {
		const table = document.getElementById(tableId);
		return table ? $$('tbody tr[data-row]', table).filter((row) => $('[data-row-check]', row)?.checked).map((row) => row.dataset.id) : [];
	};

	const updateBulk = (table) => {
		const checks = $$('tbody tr[data-row] [data-row-check]', table);
		const checked = checks.filter((cb) => cb.checked);
		const all = $('[data-check-all]', table);

		if (all) {
			all.checked = checks.length > 0 && checked.length === checks.length;
			all.indeterminate = checked.length > 0 && checked.length < checks.length;
		}

		$$('tbody tr[data-row]', table).forEach((row) => {
			const cb = $('[data-row-check]', row);
			row.classList.toggle('is-selected', Boolean(cb && cb.checked));
		});

		const bar = document.querySelector(`[data-bulk="${table.id}"]`);
		if (bar) {
			bar.hidden = checked.length === 0;
			const count = $('[data-bulk-count]', bar);
			if (count) count.textContent = checked.length;
		}
	};

	const initTables = () => {
		$$('table[data-table]').forEach((table) => {
			table.addEventListener('change', (e) => {
				if (e.target.matches('[data-check-all]')) {
					$$('tbody tr[data-row] [data-row-check]', table).forEach((cb) => {
						cb.checked = e.target.checked;
					});
				}
				if (e.target.matches('[data-check-all], [data-row-check]')) updateBulk(table);
			});

			// The whole checkbox cell toggles its box: a 24px box alone is a small touch target
			table.addEventListener('click', (e) => {
				const cell = e.target.closest('td, th');
				const box = cell && !e.target.matches('input') ? $('[data-row-check], [data-check-all]', cell) : null;
				if (box) box.click();
			});

			// Checkboxes the browser restored after "Back"
			updateBulk(table);
		});

		$$('[data-bulk-clear]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const table = document.getElementById(btn.dataset.bulkClear);
				if (!table) return;
				$$('[data-row-check], [data-check-all]', table).forEach((cb) => { cb.checked = false; });
				updateBulk(table);
			});
		});

		// Bulk forms: checked rows as ids[]
		$$('form[data-bulk-form]').forEach((form) => {
			form.addEventListener('submit', () => {
				$$('input[name="ids[]"]', form).forEach((el) => el.remove());
				checkedIds(form.dataset.bulkForm).forEach((id) => {
					const input = document.createElement('input');
					input.type = 'hidden';
					input.name = 'ids[]';
					input.value = id;
					form.appendChild(input);
				});
			});
		});

		$$('form[data-autosubmit] select, form[data-autosubmit] input[type="file"], form[data-autosubmit] input[type="checkbox"]').forEach((field) => {
			field.addEventListener('change', () => field.form.requestSubmit());
		});
	};

	/* ----------------------------------------------------------------------
		Dropdown menus: [data-dropdown] > [data-dropdown-toggle] + .dropdown__menu
		Menu is position:fixed so tables with overflow don't clip it.
		---------------------------------------------------------------------- */
	const positionMenu = (dropdown) => {
		const toggle = $('[data-dropdown-toggle]', dropdown);
		const menu = $('.dropdown__menu', dropdown);
		if (!toggle || !menu) return;

		const rect = toggle.getBoundingClientRect();
		const width = menu.offsetWidth;
		const height = menu.offsetHeight;
		const gap = 6;

		let left = dropdown.classList.contains('dropdown--left') ? rect.left : rect.right - width;
		left = Math.max(8, Math.min(left, window.innerWidth - width - 8));

		let top = rect.bottom + gap;
		if (top + height > window.innerHeight - 8) top = Math.max(8, rect.top - height - gap);

		menu.style.left = `${left}px`;
		menu.style.top = `${top}px`;
	};

	const closeDropdowns = (except = null) => {
		$$('[data-dropdown].is-open').forEach((dropdown) => {
			if (dropdown === except) return;
			dropdown.classList.remove('is-open');
			const toggle = $('[data-dropdown-toggle]', dropdown);
			if (toggle) toggle.setAttribute('aria-expanded', 'false');
		});
	};

	const initDropdowns = () => {
		document.addEventListener('click', (e) => {
			const toggle = e.target.closest('[data-dropdown-toggle]');

			if (toggle) {
				const dropdown = toggle.closest('[data-dropdown]');
				const open = !dropdown.classList.contains('is-open');
				closeDropdowns(dropdown);
				dropdown.classList.toggle('is-open', open);
				toggle.setAttribute('aria-expanded', String(open));
				if (open) {
					positionMenu(dropdown);
					const first = $('.dropdown__item', dropdown);
					if (first) first.focus();
				}
				return;
			}

			// Click outside or on a menu item closes menus
			if (!e.target.closest('.dropdown__menu') || e.target.closest('.dropdown__item')) closeDropdowns();
		});

		document.addEventListener('keydown', (e) => {
			if (e.key !== 'Escape') return;
			const open = $('[data-dropdown].is-open');
			if (!open) return;
			closeDropdowns();
			const toggle = $('[data-dropdown-toggle]', open);
			if (toggle) toggle.focus();
		});

		// Fixed menus don't follow scroll: close them
		window.addEventListener('scroll', () => closeDropdowns(), true);
		window.addEventListener('resize', () => closeDropdowns());
	};

	/* ----------------------------------------------------------------------
		Modals: native <dialog class="modal">
		[data-modal-open="dialog-id"] opens (optional data-payload JSON fills it)
		[data-modal-close] closes; click on backdrop closes
		---------------------------------------------------------------------- */
	const initModals = () => {
		document.addEventListener('click', (e) => {
			const opener = e.target.closest('[data-modal-open]');
			if (opener) {
				const dialog = document.getElementById(opener.dataset.modalOpen);
				if (dialog && typeof dialog.showModal === 'function') {
					closeDropdowns();
					// Opened for another row: nothing typed for the previous one stays
					// (a reject message must not reach the wrong seller)
					const form = $('form', dialog);
					if (form) form.reset();
					if (opener.dataset.payload) fillFields(dialog, readPayload(opener));
					if (opener.dataset.bulkIds) fillFields(dialog, { id_list: checkedIds(opener.dataset.bulkIds).join(',') });
					dialog.showModal();
				}
				return;
			}

			const closer = e.target.closest('[data-modal-close]');
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
		Confirm before destructive actions: [data-confirm="Question?"]
		Optional: data-confirm-label="Delete"
		After OK: follows the link or submits the form (with the button as submitter)
		---------------------------------------------------------------------- */
	const initConfirm = () => {
		const dialog = $('#confirm-dialog');
		if (!dialog) return;

		const text = $('[data-confirm-text]', dialog);
		const ok = $('[data-confirm-ok]', dialog);
		let pending = null;

		document.addEventListener('click', (e) => {
			const el = e.target.closest('[data-confirm]');
			if (!el) return;
			e.preventDefault();
			closeDropdowns();
			pending = el;
			text.textContent = el.dataset.confirm;
			ok.textContent = el.dataset.confirmLabel || 'Confirm';
			dialog.showModal();
		});

		ok.addEventListener('click', () => {
			dialog.close();
			if (!pending) return;

			const form = pending.closest('form');
			if (pending.tagName === 'A' && pending.getAttribute('href')) {
				window.location.href = pending.href;
			} else if (form) {
				form.requestSubmit(pending.matches('button[type="submit"]') ? pending : undefined);
			}
			pending = null;
		});
	};

	/* ----------------------------------------------------------------------
		Category tree: expand/collapse, edit form fill
		[data-tree-toggle] toggles children; [data-tree-all="expand|collapse"]
		[data-edit-category] with data-payload fills #category-form
		---------------------------------------------------------------------- */
	const setTreeItem = (toggle, open) => {
		toggle.setAttribute('aria-expanded', String(open));
		const children = document.getElementById(toggle.getAttribute('aria-controls'));
		if (children) children.hidden = !open;
	};

	const initTree = () => {
		document.addEventListener('click', (e) => {
			const toggle = e.target.closest('[data-tree-toggle]');
			if (toggle) setTreeItem(toggle, toggle.getAttribute('aria-expanded') !== 'true');

			const all = e.target.closest('[data-tree-all]');
			if (all) {
				const open = all.dataset.treeAll === 'expand';
				$$('[data-tree-toggle]').forEach((t) => setTreeItem(t, open));
			}

			const edit = e.target.closest('[data-edit-category]');
			if (edit) {
				const form = $('#category-form');
				if (!form) return;
				form.reset();
				fillFields(form.closest('.card') || form, readPayload(edit));
				$$('.tree__row.is-current').forEach((row) => row.classList.remove('is-current'));
				const row = edit.closest('.tree__row');
				if (row) row.classList.add('is-current');

				// Stacked layout (the form wrapped under the tree, up to ~1250px): bring it into view
				const card = form.closest('.card');
				const tree = card && card.previousElementSibling;
				if (card && tree && card.getBoundingClientRect().top >= tree.getBoundingClientRect().bottom) {
					form.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
				const firstField = $('input:not([type="hidden"])', form);
				if (firstField) firstField.focus({ preventScroll: true });
			}
		});
	};

	/* ----------------------------------------------------------------------
		Small form helpers
		- [data-password-toggle] aria-controls="input-id": show/hide password
		- textarea/input[maxlength][data-counter]: live "12 / 80"
		- [data-slug-source="input-name"] on slug input: auto slug for a new record
		---------------------------------------------------------------------- */
	const slugify = (text) => text
		.toLowerCase()
		.replace(/[čć]/g, 'c').replace(/đ/g, 'dj').replace(/š/g, 's').replace(/ž/g, 'z')
		.replace(/&/g, 'and')
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');

	const initForms = () => {
		$$('[data-password-toggle]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const input = document.getElementById(btn.getAttribute('aria-controls'));
				if (!input) return;
				const show = input.type === 'password';
				input.type = show ? 'text' : 'password';
				btn.setAttribute('aria-pressed', String(show));
				btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
			});
		});

		$$('[data-counter][maxlength]').forEach((field) => {
			const out = document.getElementById(field.dataset.counter);
			if (!out) return;
			const update = () => { out.textContent = `${field.value.length} / ${field.maxLength}`; };
			field.addEventListener('input', update);
			update();
		});

		$$('[data-slug-source]').forEach((slugInput) => {
			const form = slugInput.form;
			const source = form ? form.elements[slugInput.dataset.slugSource] : null;
			if (!source) return;
			let touched = false;
			slugInput.addEventListener('input', (e) => { if (e.isTrusted) touched = slugInput.value !== ''; });
			// Only for a new record (empty id): an existing slug is an address, it changes by hand
			source.addEventListener('input', () => {
				if (!touched && !(form.elements.id && form.elements.id.value)) slugInput.value = slugify(source.value);
			});
		});
	};

	/* ---------- Init ---------- */
	window.AdminUI = { toast };

	initSidebar();
	initTabs();
	initTables();
	initDropdowns();
	initModals();
	initConfirm();
	initTree();
	initForms();
})();
