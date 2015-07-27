<?php if(count($sync_settings) > 0): ?>
</div>
<?php endif; ?>

<form method="post" class="form-inline">
	<h3>Add New Record Sync</h3>
	<input type="hidden" name="mode" value="add">

	<div class="form-group">
		<label for="google_doc_record_post_type" class="control-label">Post Type</label>
		<select name="google_doc_record_add[type]" id="google_doc_record_type" required class="form-control">
			<option value="">Select...</option>
			<?php
				foreach($post_types as $name=>$pt):
			?>
				<option value="<?php echo $name; ?>"><?php echo $pt->label; ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="form-group">
		<label for="google_doc_record_spreadsheet" class="control-label">Spreadsheet</label>
		<select name="google_doc_record_add[spreadsheet]" id="google_doc_record_spreadsheet" required class="form-control">
			<option value="">Select...</option>>
			<?php
				foreach($spreadsheets as $sheet):
			?>
				<option><?php echo $sheet->getTitle(); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<input type="submit" value="New Record Sync" class="button button-primary">

	<?php wp_nonce_field( 'add_sync','google_doc_record_add_nonce' ); ?>
</form>

</div>