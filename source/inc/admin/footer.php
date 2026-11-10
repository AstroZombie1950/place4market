<?php
/* ==========================================================================
	Admin footer: closes layout, shared dialogs, toast region
	========================================================================== */
?>
		</main>
	</div>
</div>

<!-- Shared confirm dialog (used by [data-confirm]) -->
<dialog class="modal modal--sm" id="confirm-dialog" aria-labelledby="confirm-title">
	<div class="modal__inner">
		<div class="modal__head">
			<h2 class="modal__title" id="confirm-title"><?= e(at('dlg.confirm_title')) ?></h2>
			<button type="button" class="btn btn--ghost btn--icon" data-modal-close aria-label="<?= e(at('common.close')) ?>"><?= icon('x') ?></button>
		</div>
		<div class="modal__body">
			<p data-confirm-text><?= e(at('dlg.confirm_text')) ?></p>
		</div>
		<div class="modal__foot">
			<button type="button" class="btn btn--secondary" data-modal-close><?= e(at('common.cancel')) ?></button>
			<button type="button" class="btn btn--danger" data-confirm-ok data-label="<?= e(at('common.confirm')) ?>"><?= e(at('common.confirm')) ?></button>
		</div>
	</div>
</dialog>

<!-- Toast notifications -->
<div class="toast-region" data-toast-region aria-live="polite"></div>
</body>
</html>
