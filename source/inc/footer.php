<?php
/* ==========================================================================
	Site footer, mobile bottom navigation, shared dialog, toasts, JS strings
	========================================================================== */

$unread = unread_count();
?>
</main>

<!-- Footer -->
<footer class="site-footer">
	<div class="container site-footer__inner">
		<a class="site-footer__logo" href="<?= e(url('index.php')) ?>">Place<span class="logo__4">4</span>Market</a>
		<nav aria-label="<?= e(t('footer.nav')) ?>">
			<ul class="site-footer__links">
				<li><a href="#"><?= e(t('footer.about')) ?></a></li>
				<li><a href="#"><?= e(t('footer.help')) ?></a></li>
				<li><a href="#"><?= e(t('footer.safety')) ?></a></li>
				<li><a href="#"><?= e(t('footer.terms')) ?></a></li>
				<li><a href="#"><?= e(t('footer.privacy')) ?></a></li>
				<li><a href="#"><?= e(t('footer.contact')) ?></a></li>
			</ul>
		</nav>
		<p class="site-footer__copy">© <?= date('Y') ?> Place4Market. <?= e(t('footer.rights')) ?></p>
	</div>
</footer>

<!-- Bottom navigation (phones) -->
<nav class="bottom-nav" aria-label="<?= e(t('nav.main')) ?>">
	<a class="bottom-nav__link" href="<?= e(url('index.php')) ?>"<?= nav_current('home', $page['nav']) ?>>
		<?= icon('home', 22) ?><span><?= e(t('nav.home')) ?></span>
	</a>
	<a class="bottom-nav__link" href="<?= e(url('favorites.php')) ?>"<?= nav_current('favorites', $page['nav']) ?>>
		<?= icon('heart', 22) ?><span><?= e(t('nav.favorites')) ?></span>
	</a>
	<a class="bottom-nav__link bottom-nav__link--post" href="<?= e(url('post.php')) ?>"<?= nav_current('post', $page['nav']) ?>>
		<span class="bottom-nav__plus"><?= icon('plus', 22) ?></span><span><?= e(t('nav.post')) ?></span>
	</a>
	<a class="bottom-nav__link" href="<?= e(url('messages.php')) ?>"<?= nav_current('messages', $page['nav']) ?> aria-label="<?= e(t('nav.messages_unread', ['n' => $unread])) ?>">
		<?= icon('message', 22) ?><span aria-hidden="true"><?= e(t('nav.messages')) ?></span>
		<?php if ($unread > 0): ?><span class="count-badge bottom-nav__badge" aria-hidden="true"><?= $unread ?></span><?php endif; ?>
	</a>
	<a class="bottom-nav__link" href="<?= e(url('profile.php')) ?>"<?= nav_current('profile', $page['nav']) ?>>
		<?= icon('user', 22) ?><span><?= e(t('nav.profile')) ?></span>
	</a>
</nav>

<!-- Shared confirm dialog (used by [data-confirm]) -->
<dialog class="modal modal--sm" id="confirm-dialog" aria-labelledby="confirm-title">
	<div class="modal__inner">
		<div class="modal__head">
			<h2 class="modal__title" id="confirm-title"><?= e(t('common.are_you_sure')) ?></h2>
			<button type="button" class="btn btn--ghost btn--icon" data-dialog-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button>
		</div>
		<div class="modal__body">
			<p data-confirm-text></p>
		</div>
		<div class="modal__foot">
			<button type="button" class="btn btn--outline" data-dialog-close><?= e(t('common.cancel')) ?></button>
			<button type="button" class="btn btn--danger" data-confirm-ok><?= e(t('common.confirm')) ?></button>
		</div>
	</div>
</dialog>

<!-- Toasts -->
<div class="toast-region" data-toast-region aria-live="polite"></div>

<!-- Strings for main.js -->
<script type="application/json" id="i18n-js"><?= json_encode([
	'linkCopied' => t('l.link_copied'),
	'photosLimit' => t('post.photos_limit'),
	'photoCover' => t('post.photo_cover'),
	'makeCover' => t('post.photo_make_cover'),
	'removePhoto' => t('post.photo_remove'),
	'agreement' => t('price.agreement'),
	'previewTitle' => t('post.preview_title'),
	'showPassword' => t('auth.show_password'),
	'hidePassword' => t('auth.hide_password'),
	'today' => t('time.today'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</body>
</html>
