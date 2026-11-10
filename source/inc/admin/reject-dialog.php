<?php
/* ==========================================================================
	Reject listing dialog (opened by [data-modal-open="reject-dialog"])
	Payload: {"title": "...", "id_list": "1048"}; from the bulk bar
	(data-bulk-ids) id_list gets the checked rows. Sends to moderate.php,
	then back to $rejectBack.
	========================================================================== */
?>
<dialog class="modal" id="reject-dialog" aria-labelledby="reject-title">
	<form class="modal__inner" method="post" action="moderate.php">
		<?= csrf_field() ?>
		<input type="hidden" name="action" value="reject">
		<input type="hidden" name="id_list" value="" data-fill="id_list">
		<input type="hidden" name="back" value="<?= e($rejectBack ?? 'listings.php') ?>">
		<div class="modal__head">
			<h2 class="modal__title" id="reject-title"><?= e(at('dlg.reject_title')) ?></h2>
			<button type="button" class="btn btn--ghost btn--icon" data-modal-close aria-label="<?= e(at('common.close')) ?>"><?= icon('x') ?></button>
		</div>
		<div class="modal__body">
			<p><?= e(at('dlg.reject_listing')) ?> <strong data-fill="title">—</strong></p>

			<div class="field">
				<label class="field__label" for="reject-reason"><?= e(at('dlg.reason')) ?></label>
				<select class="select" id="reject-reason" name="reason" required>
					<?= options(reject_reasons()) ?>
				</select>
				<span class="field__hint"><?= e(at('dlg.reason_hint')) ?></span>
			</div>

			<div class="field">
				<label class="field__label" for="reject-comment"><?= e(at('dlg.message')) ?></label>
				<textarea class="textarea" id="reject-comment" name="comment" rows="4" maxlength="1000" placeholder="<?= e(at('dlg.message_ph')) ?>"></textarea>
			</div>

			<label class="check"><input type="checkbox" name="notify" value="1" checked> <?= e(at('dlg.notify')) ?></label>
		</div>
		<div class="modal__foot">
			<button type="button" class="btn btn--secondary" data-modal-close><?= e(at('common.cancel')) ?></button>
			<button type="submit" class="btn btn--danger"><?= e(at('dlg.reject_title')) ?></button>
		</div>
	</form>
</dialog>
