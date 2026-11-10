<?php
/* ==========================================================================
	Admin texts: Russian (keys as in en.php; a missing key falls back to English)
	Plural forms: ['one' => …, 'few' => …, 'many' => …] (1 / 2–4 / 5+).
	========================================================================== */

return [
	/* ---------- Sidebar ---------- */
	'nav.landings' => 'Посадочные',

	/* ---------- Shared ---------- */
	'common.actions' => 'Действия',
	'common.apply' => 'Применить',
	'common.edit_name' => 'Изменить: {name}',
	'common.form_expired' => 'Форма устарела. Попробуйте ещё раз.',
	'common.languages' => 'Языки',
	'common.latin' => 'латиница',
	'common.nothing_changed' => 'Ничего не изменилось.',
	'common.reset' => 'Сбросить',
	'common.save_changes' => 'Сохранить',
	'common.saved_fields' => 'Сохранено: {fields}.',
	'common.show' => 'Показать',
	'common.unknown_action' => 'Неизвестное действие.',
	'common.view_on_site' => 'Открыть на сайте',

	/* ---------- SEO texts (categories, landings) ---------- */
	'seo.h1' => 'Заголовок страницы (H1)',
	'seo.title_hint' => 'До 45 знаков: после него добавляется « — Place4Market».',
	'seo.count_hint_en' => '<code>{count}</code> заменяется числом объявлений («12 listings»; пока нет — «Listings»).',
	'seo.count_hint_sr' => '<code>{count}</code> заменяется числом объявлений («12 oglasa»; пока нет — «Oglasi»).',
	'seo.text' => 'SEO-текст',
	'seo.text_short' => 'Текст',
	'seo.text_empty' => 'Пусто: без текста',
	'seo.cyrillic_hint' => 'Кириллическая страница берёт тексты с латиницы и переводит в кириллицу сама.',

	/* ---------- Categories ---------- */
	'cat.h1_empty' => 'Пусто: название категории',
	'cat.text_hint' => 'Под объявлениями на странице категории (без фильтров), в свёрнутом блоке. Пустая строка начинает новый абзац.',

	/* ---------- Landing pages ---------- */
	'land.title' => 'Посадочные страницы',
	'land.sub' => 'Утверждённые страницы фильтров из семантического ядра: свой заголовок, title, description и SEO-текст. Пустые поля берутся из шаблонов.',
	'land.table' => 'Таблица посадочных страниц',
	'land.search' => 'Поиск посадочных',
	'land.search_ph' => 'Заголовок, место, вид, марка…',
	'land.all_categories' => 'Все категории',
	'land.all_listings' => 'Все объявления',
	'land.texts' => 'Тексты',
	'land.texts_any' => 'Любые тексты',
	'land.texts_own' => 'Со своими текстами',
	'land.texts_template' => 'Только шаблоны',
	'land.state' => 'Состояние',
	'land.state_any' => 'Любое состояние',
	'land.filter_on' => 'Включённые',
	'land.filter_off' => 'Выключенные',
	'land.state_off' => 'Выключена',
	'land.state_indexed' => 'Индексируется',
	'land.state_empty' => 'Нет объявлений: noindex',
	'land.col_page' => 'Страница',
	'land.col_demand' => 'Запросов в месяц',
	'land.col_listings' => 'Объявлений',
	'land.col_own' => 'Свои тексты',
	'land.col_state' => 'Состояние',
	'land.empty' => 'Под фильтры не подходит ни одна посадочная.',
	'land.not_found' => 'Этой посадочной страницы больше нет.',
	'land.edit_title' => 'Посадочная #{id}',
	'land.texts_hint' => 'Пустые поля берутся из шаблонов, они показаны серым. Основной язык — сербский; кириллическая страница переводит тексты с латиницы.',
	'land.h1_hint' => 'Пусто: заголовок собирается из фильтров (серым).',
	'land.text_hint' => 'Под объявлениями посадочной страницы, в свёрнутом блоке. Пустая строка начинает новый абзац.',
	'land.page' => 'Страница',
	'land.f_category' => 'Категория',
	'land.f_deal' => 'Сделка',
	'land.f_type' => 'Вид',
	'land.f_brand' => 'Марка',
	'land.f_place' => 'Место',
	'land.f_district' => 'Район',
	'land.demand' => 'Запросов в месяц',
	'land.listings' => 'Объявлений сейчас',
	'land.address' => 'Адрес {lang}',
	'land.demand_hint' => 'Запросов в месяц по семантическому ядру (Google Keyword Planner, Сербия).',
	'land.active' => 'Утверждённая посадочная',
	'land.active_hint' => 'Включена: индексируется, пока есть объявления, есть в карте сайта и в «Popularne pretrage». Выключена: обычная страница фильтра (noindex).',
	'land.flash_on' => '«{name}» включена: индексируется, пока есть объявления.',
	'land.flash_off' => '«{name}» выключена: обычная страница фильтра (noindex).',

	/* ---------- Listing edit ---------- */
	'edit.not_set' => '— Не указано —',
	'edit.f_deal' => 'Сделка',
	'edit.f_type' => 'Вид',
	'edit.f_country' => 'Страна',
	'edit.f_brand' => 'Марка',
	'edit.brand_other' => 'Другая марка',
	'edit.f_district' => 'Район',
	'edit.district_none' => 'Не указан',
	'edit.f_seller' => 'Кто размещает',

	/* ---------- Facet values ---------- */
	'deal.sale' => 'Продажа',
	'deal.rent' => 'Аренда',
	'deal.daily' => 'Посуточно',
	'seller.private' => 'Частное лицо',
	'seller.business' => 'Компания',
];
