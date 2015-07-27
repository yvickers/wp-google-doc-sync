<div class="wrap">

<h2>Google Document Sync</h2>

<?php do_action('google_doc_records/display_messages'); ?>

<h3>Sync Processes</h3>
<p>Please note that these processes may take a good bit of time to run especially if there are a lot of records to process.  Please be patient.</p>
<?php if(count($sync_settings) > 0): ?>
<div class="js-tabs">
<ul>
<?php foreach($sync_settings as $name=>$pt): ?>
<li><a href="#Tab<?php echo $name; ?>"><?php echo $pt['label']; ?></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>