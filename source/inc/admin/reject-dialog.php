<?php
/* ==========================================================================
	Reject listing dialog (opened by [data-modal-open="reject-dialog"])
	Payload: {"title": "..."}
	========================================================================== */
?>
<dialog class="modal" id="reject-dialog" aria-labelledby="reject-title">
	<form class="modal__inner" method="post" data-demo-submit="Listing rejected, seller notified">
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
			</div>

			<div class="field">
				<label class="field__label" for="reject-comment">Message to the seller</label>
				<textarea class="textarea" id="reject-comment" name="comment" rows="4" placeholder="Explain what to fix so the listing can be approved."></textarea>
			</div>

			<label class="check"><input type="checkbox" name="notify" checked> Notify the seller by email</label>
		</div>
		<div class="modal__foot">
			<button type="button" class="btn btn--secondary" data-modal-close>Cancel</button>
			<button type="submit" class="btn btn--danger" data-modal-close>Reject listing</button>
		</div>
	</form>
</dialog>
