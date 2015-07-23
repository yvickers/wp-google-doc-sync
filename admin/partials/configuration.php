<div class="wrap">

	<h2>Sync Configuration - <?php echo $settings['label']; ?></h2>

	<?php do_action('google_doc_records/display_messages'); ?>

	<p>Use this form to map your wordpress fields to your google spreadsheet.
	If you wish to map this record type to a different spreadsheet, please remove it, then add a new sync with the new spreadsheet.</p>

	<form method="post" class="form-horizontal">
		<div class="form-group">
			<label for="google_doc_record_configure__master_tab" class="col-sm-2 control-label">Master Tab</label>
			<div class="col-sm-10">
			<select name="google_doc_record_configure[master_tab]" id="google_doc_record_configure__master_tab" required class="form-control" >
				<option value="">Select...</option>
				<?php
					foreach($worksheets as $wkid=>$worksheet):
						$sel = ($settings['master_tab'] == $worksheet['label'])? ' SELECTED':'';
				?>
					<option value="<?php echo $worksheet['label']; ?>"<?php echo $sel; ?> data-guid="<?php echo $wkid; ?>"><?php echo $worksheet['label']; ?></option>
				<?php endforeach; ?>
			</select>
			<p class="help-block">This governs the field mapping for the information, the other tabs must also contain the mapped fields in order for their information to be synced properly.</p>
			</div>
		</div>

		<fieldset>
			<legend>Field Mapping</legend>
			<p>Please map the content fields from your google spreadsheet to fields within wordpress.</p>
			<div class="form-group">
				<label for="google_doc_record_configure__master_id" class="col-sm-2 control-label">ID Field</label>
				<div class="col-sm-10">
				<input class="form-control" name="google_doc_record_configure[master_id]" id="google_doc_record_configure__master_id" value="<?php echo esc_attr($settings['master_id']); ?>">
				</div>
			</div>
			<div class="form-group">
				<label for="google_doc_record_configure__master_sync_start" class="col-sm-2 control-label">Sync Started Date</label>
				<div class="col-sm-10">
				<input class="form-control" name="google_doc_record_configure[master_sync_start]" id="google_doc_record_configure__master_sync_start" value="<?php echo esc_attr($settings['master_sync_start']); ?>">
				</div>
			</div>
			<div class="form-group">
				<label for="google_doc_record_configure__master_sync_date" class="col-sm-2 control-label">Last Sync Date</label>
				<div class="col-sm-10">
				<input class="form-control" name="google_doc_record_configure[master_sync_date]" id="google_doc_record_configure__master_sync_date" value="<?php echo esc_attr($settings['master_sync_date']); ?>">
				</div>
			</div>
			<?php
			$i = 0;
			foreach($settings['field_map'] as $field):
			?>

			<div class="google_doc_record_field_map form-group">
				<label for="google_doc_record_wp_field_<?php echo $i; ?>" class="col-sm-3 control-label">Wordpress Field</label>
				<div class="col-sm-3">
				<input class="form-control" name="google_doc_record_configure[field_map][<?php echo $i; ?>][wp_field]" id="google_doc_record_wp_field_<?php echo $i; ?>" value="<?php echo esc_attr($field['wp_field']); ?>">
				</div>
				<label for="google_doc_record_doc_field_<?php echo $i; ?>" class="col-sm-3 control-label">Spreadsheet Field</label>
				<div class="col-sm-3">
				<input class="form-control" name="google_doc_record_configure[field_map][<?php echo $i; ?>][doc_field]" id="google_doc_record_doc_field_<?php echo $i; ?>" value="<?php echo esc_attr($field['doc_field']); ?>">
				</div>
			</div>

			<?php
			$i++;
			endforeach;
			?>

			<center>
			<input type="button" value="Add Field" class="js-repeater-add btn btn-sm btn-success" data-template="new_field_map" data-count="google_doc_record_field_map" data-template-vars='{"stem":"google_doc_record"}'>
			</center>

			<div class="hidden" id="new_field_map">
				<div class="{{stem}}_field_map form-group">
					<label for="{{stem}}_wp_field_{{count}}" class="col-sm-3 control-label">Wordpress Field</label>
					<div class="col-sm-3">
					<input class="form-control" name="{{stem}}_configure[field_map][{{count}}][wp_field]" id="{{stem}}_wp_field_{{count}}">
					</div>
					<label for="{{stem}}_doc_field_{{count}}" class="col-sm-3 control-label">Spreadsheet Field</label>
					<div class="col-sm-3">
					<input class="form-control" name="{{stem}}_configure[field_map][{{count}}][doc_field]" id="{{stem}}_doc_field_{{count}}">
					</div>
				</div>
			</div>
		</fieldset>

		<?php foreach($tabs as $tab_name=>$tab): ?>
		<fieldset>
		<legend><?php echo $tab['label']; ?> Setup</legend>
		<div class="form-group">
			<label for="google_doc_record_configure__<?php echo $tab_name; ?>_tab" class="col-sm-2 control-label">Tab</label>
			<div class="col-sm-10">
			<select class="form-control" name="google_doc_record_configure[<?php echo $tab_name; ?>_tab]" id="google_doc_record_configure__<?php echo $tab_name; ?>_tab" required>
				<option value="">Select...</option>
				<?php
					foreach($worksheets as $wkid=>$worksheet):
						$sel = ($settings[$tab_name.'_tab'] == $worksheet['label'])? ' SELECTED':'';
				?>
					<option value="<?php echo $worksheet['label']; ?>"<?php echo $sel; ?> data-guid="<?php echo $wkid; ?>"><?php echo $worksheet['label']; ?></option>
				<?php endforeach; ?>
			</select>
			</div>
		</div>
		<div class="form-group">
			<label for="google_doc_record_configure__<?php echo $tab_name; ?>_id_field" class="col-sm-2 control-label">ID Field</label>
			<div class="col-sm-10">
			<input class="form-control" name="google_doc_record_configure[<?php echo $tab_name; ?>_id_field]" id="google_doc_record_configure__<?php echo $tab_name; ?>_id_field" value="<?php echo esc_attr($settings[$tab_name.'_id_field']);?>">
			<p><?php echo $tab['id_message']; ?></p>
			</div>
		</div>
		<div class="form-group">
			<label for="google_doc_record_configure__<?php echo $tab_name; ?>_date_field" class="col-sm-2 control-label">Date Field</label>
			<div class="col-sm-10">
			<input class="form-control" name="google_doc_record_configure[<?php echo $tab_name; ?>_date_field]" id="google_doc_record_configure__<?php echo $tab_name; ?>_date_field" value="<?php echo esc_attr($settings[$tab_name.'_date_field']);?>">
			<p><?php echo $tab['sync_date_message']; ?></p>
			</div>
		</div>
		<div class="form-group">
			<label for="google_doc_record_configure__<?php echo $tab_name; ?>_status_field" class="col-sm-2 control-label">Status Field</label>
			<div class="col-sm-10">
			<input class="form-control" name="google_doc_record_configure[<?php echo $tab_name; ?>_status_field]" id="google_doc_record_configure__<?php echo $tab_name; ?>_status_field" value="<?php echo esc_attr($settings[$tab_name.'_status_field']);?>">
			<p><?php echo $tab['sync_status_message']; ?></p>
			</div>
		</div>
		</fieldset>
		<?php endforeach; ?>

		<input type="submit" value="Save Configuration" class="button button-primary">

		<?php wp_nonce_field( 'configure_sync','google_doc_record_configure_nonce' ); ?>
	</form>
</div>