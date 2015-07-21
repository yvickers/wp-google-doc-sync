<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Google Document Records Settings</h2>
	<form method="post" action="options.php">
		<?php
			settings_fields( $option_group );
			do_settings_sections( $option_page );
			submit_button();
		?>
	</form>
</div>