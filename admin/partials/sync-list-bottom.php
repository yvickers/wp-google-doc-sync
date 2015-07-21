
<form method="post">
	<h3>Add New Record Sync</h3>
	<input type="hidden" name="mode" value="add">

	<div>
		<label for="google_doc_record_post_type">Post Type</label>
		<select name="google_doc_record_add[type]" id="google_doc_record_type" required>
			<option value="">Select...</option>
			<?php
				foreach($post_types as $name=>$pt):
			?>
				<option value="<?php echo $name; ?>"><?php echo $pt->label; ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div>
		<label for="google_doc_record_spreadsheet">Spreadsheet</label>
		<select name="google_doc_record_add[spreadsheet]" id="google_doc_record_spreadsheet" required>
			<option value="">Select...</option>
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