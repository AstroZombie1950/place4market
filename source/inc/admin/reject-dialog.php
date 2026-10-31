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
			<h2 class="modal__title" id="reject-title">Reject listing</h2>
			<button type="button" class="btn btn--ghost btn--icon" data-modal-close aria-label="Close"><?= icon('x') ?></button>
		</div>
		<div class="modal__body">
			<p>Listing: <strong data-fill="title">—</strong></p>

			<div class="field">
				<label class="field__label" for="reject-reason">Reason</label>
				<select class="select" id="reject-reason" name="reason" required>
					<?= options(reject_reasons()) ?>
				</select>
				<span class="field__hint">The seller sees it in their language.</span>
			</div>

			<div class="field">
				<label class="field__label" for="reject-comment">Message to the seller</label>
				<textarea class="textarea" id="reject-comment" name="comment" rows="4" maxlength="1000" placeholder="Explain what to fix so the listing can be approved."></textarea>
			</div>

			<label class="check"><input type="checkbox" name="notify" value="1" checked> Notify the seller by email</label>
		</div>
		<div class="modal__foot">
			<button type="button" class="btn btn--secondary" data-modal-close>Cancel</button>
			<button type="submit" class="btn btn--danger">Reject listing</button>
		</div>
	</form>
</dialog>
