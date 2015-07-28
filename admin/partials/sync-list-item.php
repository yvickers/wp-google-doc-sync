<?php

	$links = array(
		'full'=>array(
			'class'=>'button button-primary',
			'label'=>'<i class="fa fa-lg fa-refresh"></i> Full Sync',
			'description'=>'Run the deletion, correction, and addition processes assuming they are mapped to tabs in the configuration.',
			'requires'=>'master_tab',
		),
		'additions'=>array(
			'class'=>'button',
			'label'=>'Additions',
			'description'=>'Add records to wordpress and the master tab.',
			'requires'=>'additions_tab',
		),
		'corrections'=>array(
			'class'=>'button',
			'label'=>'Corrections',
			'description'=>'Updates wordpress records and the master tab based on ID.',
			'requires'=>'corrections_tab',
		),
		'deletions'=>array(
			'class'=>'button',
			'label'=>'Deletions',
			'description'=>'Remove records from wordpress and the master tab based on ID.',
			'requires'=>'deletions_tab',
		),
		'resync'=>array(
			'class'=>'button',
			'label'=>'<i class="fa fa-lg fa-download"></i> ReSync',
			'description'=>'Run this process as an initial import from the master tab or to update records in wordpress based on master tab contents.',
			'requires'=>'master_tab',
		),
	);

	$link_params = array(
		'page'=>$plugin_name,
		'mode'=>'process',
		'type'=>$name,
	);

?>
<div id="Tab<?php echo $name; ?>">

	<?php if(isset($post_type['master_tab']) && $post_type['master_tab'] != ''): ?>
		<?php
			foreach($links as $process=>$link):
				$link_params['process'] = $process;
				$link_url = (isset($post_type[$link['requires']]) && $post_type[$link['requires']] != '')? wp_nonce_url(admin_url('admin.php?'.http_build_query($link_params)),$name.'_'.$process,'nonce'):'#';
				$disabled = (isset($post_type[$link['requires']]) && $post_type[$link['requires']] != '')? '':' disabled';
		?>
			<div>
			<p>
				<a href="<?php echo $link_url; ?>" class="<?php echo $link['class']; ?> js-process-button"<?php echo $disabled; ?>><?php echo $link['label']; ?></a>
				-
				<?php echo $link['description']; ?>
			</p>
			</div>
		<?php endforeach; ?>
	<?php else: ?>
		<p>This sync must be configured before using.  Please use the button below to setup this sync.</p>
	<?php endif; ?>


	<?php
		$link_params['process'] = 'remove';
		$link_url = wp_nonce_url(admin_url('admin.php?'.http_build_query($link_params)),$name.'_remove','nonce');
	?>
	<div>
		<p>
			<a href="<?php echo $link_url; ?>" class="button"><i class="fa fa-lg fa-ban"></i> Remove Sync</a>
			-
			Remove the connection between wordpress and the google document.
		</p>
	</div>
	<div>
		<p>
		<a href="<?php echo admin_url('admin.php?'.http_build_query(array('page'=>$plugin_name,'mode'=>'configure','type'=>$name))); ?>" class="button"><i class="fa fa-gear fa-lg"></i> Configure Sync</a>
		 -
		 Adjust the source, tabs and columns for this post type.
		 </p>
	</div>

	<br>
	<h4>Latest Logs</h4>
	<?php
		$logs = get_posts(array('post_type'=>'gdrc_log_'.$name,'post_status'=>'private'));
		if(count($logs) > 0):
	?>
	<div class="js-accordion">
	<?php
		foreach($logs as $post):
			setup_postdata( $post );
	?>
	<h3><?php echo $post->post_title; ?></h3>
	<div>
		<?php the_content(); ?>
		<p>Run on <?php echo date('m/d/Y h:i:s a',strtotime($post->post_date)); ?></p>
	</div>
	<?php endforeach; wp_reset_postdata(); ?>
	</div>
	<?php endif;?>
</div>