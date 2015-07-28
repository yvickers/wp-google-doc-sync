<?php

use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;

/**
 * The dashboard-specific functionality of the plugin.
 *
 * @link       http://www.redclayinteractive.com
 * @since      1.0.0
 *
 * @package    Google_Doc_Records
 * @subpackage Google_Doc_Records/admin
 */

/**
 * The dashboard-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the dashboard-specific stylesheet and JavaScript.
 *
 * @package    Google_Doc_Records
 * @subpackage Google_Doc_Records/admin
 * @author     Yancey Vickers <yvickers@redclayinteractive.com>
 */
class Google_Doc_Records_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * individual options for settings page
	 * @var array
	 */
	protected $_options = array(
		'project'=>array(
			'label'=>'Project Name',
			'callback'=>'_input_project_name',
			'value'=>'',
		),
		'client_json'=>array(
			'label'=>'JSON Key',
			'callback'=>'_input_json_key',
			'value'=>'',
		),
	);

	protected $_sync_settings = false;
	protected $_type_settings = false;
	protected $_type = '';

	protected $_standard_wp_fields = array(
		'ID' => 1,
		'post_content' => 1,
		'post_name' => 1,
		'post_title' => 1,
		'post_status' => 1,
		'post_type' => 1,
		'post_author' => 1,
		'ping_status' => 1,
		'post_parent' => 1,
		'menu_order' => 1,
		'to_ping' => 1,
		'pinged' => 1,
		'post_password' => 1,
		'guid' => 1,
		'post_content_filtered' => 1,
		'post_excerpt' => 1,
		'post_date' => 1,
		'post_date_gmt' => 1,
		'comment_status' => 1,
		'post_category' => 1,
		'tags_input' => 1,
		'tax_input' => 1,
		'page_template' => 1,
	);

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @var      string    $plugin_name       The name of this plugin.
	 * @var      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->_start_log();
	}

	/**
	 * Add Entries to settings menu
	 */
	public function add_pages(){
		add_options_page('Settings','Google Records','manage_options',$this->plugin_name.'-options-page',array($this,'settings_page'));
		add_menu_page( 'Google Doc Syncs', 'Google Doc Syncs', 'edit_posts', $this->plugin_name, array($this,'main_switch') );
		//add_submenu_page( $this->plugin_name, 'Lookup Table', 'Lookup Table', 'edit_posts', $this->plugin_name, array($this,'lookup_page') );
	}

	/**
	 * sets up our pages - this runs before output is sent to browser
	 */
	public function init_page(){
		if(!isset($_GET['page'])){
			return;
		}

		$this->_type = isset($_GET['type'])? trim($_GET['type']):$this->_type;

		switch($_GET['page']){
			case $this->plugin_name:
				$this->_synced_documents();
			break;
			case $this->plugin_name.'-options-page':
				$this->_settings_page();
			break;
			default:
				return;
			break;
		}
	}

	/**
	 * load synchronization settings from database for plugin, set to class variables
	 */
	function _load_sync_settings(){
		$this->_sync_settings = maybe_unserialize(get_option($this->plugin_name.'-sync-settings'));
		if(!is_array($this->_sync_settings)){
			$this->_sync_settings = array();
		}

		if(is_array($this->_sync_settings) && $this->_type != ''){
			$this->_type_settings = isset($this->_sync_settings[$this->_type])? $this->_sync_settings[$this->_type]:$this->_type;
		}
	}

	/**
	 * save current settings to database
	 * @return boolean result of udpate_option
	 */
	function _update_sync_settings(){
		if($this->_sync_settings === false){
			$query_args = array(
				'page'=>$this->plugin_name,
				'message'=>9,
			);
			wp_redirect(admin_url('admin.php?'.http_build_query($query_args)));
			exit;
		}

		return update_option($this->plugin_name.'-sync-settings',serialize($this->_sync_settings));
	}

	/**
	 * load options from wp-options table and place value in class variables
	 */
	function _load_options(){
		foreach($this->_options as $k => &$v) {
			switch($k){
				default:
					$v['value'] = get_option($this->plugin_name.'_'.$k);
				break;
			}
		}
	}

	/**
	 * prep google service api for interaction
	 */
	function _synced_documents(){
		$this->_setup_client();
		$mode = $this->_request_var('mode');
		switch($mode){
			case 'list':
			default:
			break;
			case 'add':
				$this->_add_sync_type();
			break;
			case 'process':
				$this->_process_sync();
			break;
			case 'configure':
				$this->_configure_sync();
			break;
		}
	}

	/**
	 * switch for page output
	 */
	function main_switch(){
		$mode = $this->_request_var('mode');
		switch($mode){
			case 'list':
			default:
				$this->synced_documents();
			break;
			case 'configure':
				$this->configure_sync();
			break;
		}
	}

	/**
	 * generate form to setup sync configuration - tab and field mapping
	 * @return string html
	 */
	function configure_sync(){
		$auto_setting = false;
		$settings = $this->_type_settings;

		$settings += array(
			'master_tab'=>'',
			'additions_tab'=>'Additions',
			'corrections_tab'=>'Corrections',
			'deletions_tab'=>'Deletions',
		);

		$spreadsheetFeed = $this->_service->getSpreadsheets();
		$spreadsheet = $spreadsheetFeed->getByTitle($settings['spreadsheet']);
		$raw_worksheets = $spreadsheet->getWorksheets();
		$worksheets = array();
		$first = true;
		foreach($raw_worksheets as $worksheet){
			$arr = array(
				'label'=>$worksheet->getTitle(),
				'fields'=>array(),
			);
/*
			@todo	pull in column headers to make spreadsheet fields a dropdown within form
			$cell_feed = $worksheet->getCellFeed(array('min-row'=>1,'max-row'=>1));
			$cells = $cell_feed->getEntries();
			foreach($cells as $cell){
				$arr['fields'][$this->_google_header($cell->getContent())] = $cell->getContent();
			}
*/
			$worksheets[$worksheet->getGid()] = $arr;

			if($first){
				if($settings['master_tab'] == ''){
					$settings['master_tab'] = $worksheet->getTitle();
					$auto_setting = true;
				}
			}
		}

		//general tabs for each individual process
		$tabs = array(
			'additions'=>array(
				'label'=>'Additions',
				'id_message'=>'This is used to track the WP post id generated for this new record.',
				'sync_date_message'=>'This is used to track when this record was inserted into Wordpress.',
				'sync_status_message'=>'This is used as a status indicator.',
			),
			'corrections'=>array(
				'label'=>'Corrections',
				'id_message'=>'',
				'sync_date_message'=>'',
				'sync_status_message'=>'',
			),
			'deletions'=>array(
				'label'=>'Deletions',
				'id_message'=>'',
				'sync_date_message'=>'',
				'sync_status_message'=>'',
			),
		);

		include(plugin_dir_path( dirname( __FILE__ ) ).'admin/partials/configuration.php');
	}

	/**
	 * convert string into a google header cell - no spaces, all lowercase
	 * @param  string $text cell label
	 * @return string       cell name
	 */
	function _google_header($text){
		return strtolower(str_replace(' ','',$text));
	}

	/**
	 * save the configuration changes
	 */
	function _configure_sync(){
		if($this->_type == ''){
			$query_args = array(
				'page'=>$this->plugin_name,
				'message'=>900,
			);
			wp_redirect(admin_url('admin.php?'.http_build_query($query_args)));
			exit;
		}

		$this->_load_sync_settings();

		if(false === $this->_type_settings){
			$query_args = array(
				'page'=>$this->plugin_name,
				'message'=>901,
				'message-variables'=>array('type'=>$type),
			);
			wp_redirect(admin_url('admin.php?'.http_build_query($query_args)));
			exit;
		}

		if(!isset($_POST['google_doc_record_configure_nonce'])){
			return;
		}

		if(!check_admin_referer('configure_sync','google_doc_record_configure_nonce')){
			return;
		}

		//update settings with new values
		$this->_sync_settings[$this->_type] = $_POST['google_doc_record_configure'] + $this->_type_settings;

		$this->_update_sync_settings();

		$query = array(
			'page'=>$this->plugin_name,
			'message'=>200,
			'message-variables'=>array(
				'type'=>$this->_type_settings['label'],
			),
		);
		wp_redirect(admin_url('admin.php?'.http_build_query($query)));
		exit;
	}

	/**
	 * output html for quick functionality - list view of all synced record types with buttons to run processes
	 * @return string html
	 */
	function synced_documents(){
		$plugin_name = $this->plugin_name;
		$this->_load_sync_settings();

		$sync_settings = $this->_sync_settings;

		$std_post = new stdClass();
		$std_post->label = 'Posts';
		$post_types = get_post_types(array('show_ui'=>true,'_builtin'=>false),'objects');
		$post_types['post'] = $std_post;
		$post_types = array_diff_key($post_types,$this->_sync_settings);

		$spreadsheets = $this->_service->getSpreadsheets();

		include(plugin_dir_path( dirname( __FILE__ ) ).'admin/partials/sync-list-top.php');

		foreach($this->_sync_settings as $name=>$post_type){
			include(plugin_dir_path( dirname( __FILE__ ) ).'admin/partials/sync-list-item.php');
		}

		include(plugin_dir_path( dirname( __FILE__ ) ).'admin/partials/sync-list-bottom.php');
	}

	/**
	 * add the new post type to record sync - note that it will still need to be configured at another time, this just puts it into the array
	 */
	function _add_sync_type(){
		if(!check_admin_referer('add_sync','google_doc_record_add_nonce')){
			return;
		}

		//load current settings
		$this->_load_sync_settings();

		//generate array for new post type
		$post_type = $_POST['google_doc_record_add'];
		$post_types = get_post_types( array('name'=>$post_type['type']), 'objects' );
		$post_type['label'] = $post_types[$post_type['type']]->label;

		$this->_sync_settings[$post_type['type']] = $post_type;

		$this->_update_sync_settings();

		$query = array(
			'page'=>$this->plugin_name,
			'message'=>100,
			'message-variables'=>array(
				'type'=>$post_types[$post_type['type']]->label,
			),
		);
		wp_redirect(admin_url('admin.php?'.http_build_query($query)));
		exit;
	}

	/**
	 * determine and perform specified process
	 */
	function _process_sync(){
		$process = isset($_GET['process'])? trim($_GET['process']):'';
		$nonce = isset($_GET['nonce'])? trim($_GET['nonce']):'';
		if($process == '' || $this->_type == '' || $nonce == ''){
			$query = array(
				'page'=>$this->plugin_name,
				'message'=>950,
			);
			wp_redirect(admin_url('admin.php?'.http_build_query($query)));
			exit;
		}

		if(!wp_verify_nonce($nonce,$this->_type.'_'.$process)){
			return;
		}

		$this->_load_sync_settings();

		if(false === $this->_type_settings){
			$query = array(
				'page'=>$this->plugin_name,
				'message'=>951,
				'message-variables'=>array(
					'type'=>$this->_type,
				),
			);
			wp_redirect(admin_url('admin.php?'.http_build_query($query)));
			exit;
		}

		switch($process){
			case 'remove':
				$this->_remove_sync_type($this->_type);
			break;
			case 'full':
				$this->_full_sync();
			break;
			case 'additions':
				$this->_additions_process();
			break;
			case 'corrections':
				$this->_handle_corrections();
			break;
			case 'deletions':
				$this->_handle_deletions();
			break;
			case 'resync':
				$this->_resync();
			break;
			default:
				$query = array(
					'page'=>$this->plugin_name,
					'message'=>999,
					'message-variables'=>array(
						'process'=>$process,
					),
				);
				wp_redirect(admin_url('admin.php?'.http_build_query($query)));
				exit;
			break;
		}
	}

	/**
	 * remove a post type from the configured syncs array
	 * @param  string $type post type
	 */
	function _remove_sync_type($type){
		if(false !== $this->_type_settings){
			$label = $this->_type_settings['label'];
			unset($this->_sync_settings[$this->_type]);
		}else{
			$label = $this->_type;
		}

		$this->_update_sync_settings();

		$query = array(
			'page'=>$this->plugin_name,
			'message'=>101,
			'message-variables'=>array(
				'type'=>$label,
			),
		);
		wp_redirect(admin_url('admin.php?'.http_build_query($query)));
		exit;
	}

	/**
	 * process all tabs within spreadsheet
	 */
	function _full_sync(){
		$this->_handle_deletions();
		$this->_handle_additions();
		$this->_handle_corrections();

		$this->_save_log(array(
			'post_type'=>substr('gdrc_log_'.$this->_type,0,20),
			'post_title'=>'Full Sync Process '.$this->_type_settings['label'],
		));

		$query = array(
			'page'=>$this->plugin_name,
			'message'=>190,
			'message-variables'=>array(
				'type'=>$this->_type_settings['label'],
			),
		);
		wp_redirect(admin_url('admin.php?'.http_build_query($query)));
		exit;
	}

	/**
	 * match wordpress to master tab contents
	 */
	function _resync(){
		$this->_log('Additions Processing.');
		$update_time = date('m/d/y H:i:s');

		$posts = get_posts(array('post_type'=>$this->_type,'posts_per_page'=>-1,'post_status'=>'publish,private,draft'));
		$post_ids = array();
		foreach($posts as $post){
			$post_ids[$post->ID] = $post->ID;
		}

		$master_worksheet = $this->_get_worksheet($this->_type_settings['master_tab']);
		$master_list_feed = $master_worksheet->getListFeed();
		$entries = $master_list_feed->getEntries();
		$this->_log('Found '.count($entries).'.');
		$i = 0;
		foreach($entries as $entry){
			$values = $entry->getValues();
			$id = (int)$values[$this->_google_header($this->_type_settings['master_id'])];
			unset($values[$this->_google_header($this->_type_settings['master_sync_start'])]);

			//apply map field for spreadsheet values
			$wp_values = array(
				'post_type'=>$this->_type,
				'post_status'=>'publish',
			);
			foreach($this->_type_settings['field_map'] as $field){
				$wp_values[$field['wp_field']] = $values[$this->_google_header($field['doc_field'])];
			}

			//allow code to adjust what is saved to wordpress
			$wp_values = apply_filters('google_doc_records/sync_values',$wp_values);
			$wp_values = apply_filters('google_doc_records/sync_values_'.$this->_type,$wp_values);

			$fxn = (isset($post_ids[$id]))? 'wp_update_post':'wp_insert_post';
			unset($post_ids[$id]);

			$post_id = $this->_wp_record($wp_values,$fxn);
			if(is_wp_error($post_id)){
				$this->_log('Unable to sync post: '.$id.'.');
				$this->_log($post_id->get_error_messages());
				$status = 'error';
			}else{
				$status = 'synced';
			}

			//update master tab
			$master_tab_values = $values;
			$master_tab_values += array(
				$this->_google_header($this->_type_settings['master_sync_date']) => $update_time,
				$this->_google_header($this->_type_settings['master_sync_status']) => $status,
			);
			$master_tab_values = apply_filters('google_doc_records/sync_master_tab_values',$master_tab_values);
			$master_tab_values = apply_filters('google_doc_records/sync_master_tab_values_'.$this->_type,$master_tab_values);
			$entry->update($master_tab_values);

			$i++;
		}

		foreach($post_ids as $id){
			if(false === wp_delete_post($id)){
				$this->_log('Unable to delete post: '.$id.'.');
			}
			$i++;
		}

		$this->_log($i.' records processed.');

		$this->_save_log(array(
			'post_type'=>substr('gdrc_log_'.$this->_type,0,20),
			'post_title'=>'ReSync Process '.$this->_type_settings['label'],
		));

		$query = array(
			'page'=>$this->plugin_name,
			'message'=>195,
			'message-variables'=>array(
				'type'=>$this->_type_settings['label'],
			),
		);
		wp_redirect(admin_url('admin.php?'.http_build_query($query)));
		exit;
	}

	/**
	 * ensure we have the fields defined we need for a tab process
	 * @param  array  $params key=>value array of function parameters
	 * @return boolean         returns true if all fields present and populated
	 */
	function _double_check_process_requirements($params = array()){
		$params += array(
			'stem'=>'_does_not_exist',
			'process'=>'Not defined.',
			'passes'=>true,
		);
		extract($params);

		$fields = array(
			$stem.'_tab',
			$stem.'_status_field',
			$stem.'_id_field',
			$stem.'_date_field',
		);

		foreach($fields as $field){
			if(isset($this->_type_settings[$field]) && '' == trim($this->_type_settings[$field])){
				continue;
			}
			$passes = false;
		}

		if($passes){
			return true;
		}

		$query = array(
			'page'=>$this->plugin_name,
			'message'=>952,
			'message-variables'=>array(
				'type'=>$this->_type_settings['label'],
				'process'=>$process,
			),
		);
		wp_redirect(admin_url('admin.php?'.http_build_query($query)));
		exit;
	}

	/**
	 * load single worksheet by name
	 * @param  string $name worksheet name
	 * @return object       worksheet object
	 */
	function _get_worksheet($name){
		static $worksheetFeed;

		if(!isset($worksheetFeed)){
			$spreadsheetFeed = $this->_service->getSpreadsheets();
			$this->_log('Loading spreadsheet: '.$this->_type_settings['spreadsheet'].'.');
			$spreadsheet = $spreadsheetFeed->getByTitle($this->_type_settings['spreadsheet']);
			$worksheetFeed = $spreadsheet->getWorksheets();
		}

		$this->_log('Loading worksheet: '.$name).'.';
		return $worksheetFeed->getByTitle($name);
	}

	/**
	 * class mode to handle additions and post back results to main page
	 */
	function _additions_process(){
		$this->_handle_additions();

		$this->_save_log(array(
			'post_type'=>substr('gdrc_log_'.$this->_type,0,20),
			'post_title'=>'Additions Process '.$this->_type_settings['label'],
		));

		$query = array(
			'page'=>$this->plugin_name,
			'message'=>160,
			'message-variables'=>array(
				'type'=>$this->_type_settings['label'],
			),
		);
		wp_redirect(admin_url('admin.php?'.http_build_query($query)));
		exit;
	}

	/**
	 * process additions tab from spreadsheet
	 */
	function _handle_additions($process_limit = 0){
		$this->_double_check_process_requirements(array('stem'=>'additions','process'=>'Additions'));

		$this->_log('Additions Processing.');
		$update_time = date('m/d/y H:i:s');

		//load needed worksheets
		$master_worksheet = $this->_get_worksheet($this->_type_settings['master_tab']);
		$master_list_feed = $master_worksheet->getListFeed();
		$worksheet = $this->_get_worksheet($this->_type_settings['additions_tab']);
		$query = array("sq" => $this->_google_header($this->_type_settings['additions_status_field'])." = \"\"",);
		$listFeed = $worksheet->getListFeed($query);
		$entries = $listFeed->getEntries();
		$this->_log('Found '.count($entries).'.');
		$i = 0;
		foreach ($entries as $entry) {
			if($process_limit > 0 && $i > $process_limit){
				break;
			}
			$values = $entry->getValues();
			unset($values[$this->_google_header($this->_type_settings['additions_date_field'])]);
			unset($values[$this->_google_header($this->_type_settings['additions_status_field'])]);
			unset($values[$this->_google_header($this->_type_settings['additions_id_field'])]);

			//apply map field for spreadsheet values
			$wp_values = array(
				'post_type'=>$this->_type,
				'post_status'=>'publish',
			);
			foreach($this->_type_settings['field_map'] as $field){
				$wp_values[$field['wp_field']] = $values[$this->_google_header($field['doc_field'])];
			}

			//allow code to adjust what is saved to wordpress
			$wp_values = apply_filters('google_doc_records/additions_values',$wp_values);
			$wp_values = apply_filters('google_doc_records/additions_values_'.$this->_type,$wp_values);

			$post_id = $this->_wp_record($wp_values);
			if(is_wp_error($post_id)){
				$this->_log('Unable to insert new post.');
				$this->_log(var_export($wp_values,true));
				$this->_log($post_id->get_error_messages());
				$status = 'error';
			}else{
				//insert into master tab
				$master_tab_values = $values;
				$master_tab_values += array(
					$this->_google_header($this->_type_settings['master_sync_start']) => $update_time,
					$this->_google_header($this->_type_settings['master_sync_date']) => $update_time,
					$this->_google_header($this->_type_settings['master_sync_status']) => 'inserted',
					$this->_google_header($this->_type_settings['master_id']) => $post_id,
				);
				$master_tab_values = apply_filters('google_doc_records/additions_master_tab_values',$master_tab_values);
				$master_tab_values = apply_filters('google_doc_records/additions_master_tab_values_'.$this->_type,$master_tab_values);
				$master_list_feed->insert($master_tab_values);
				$status = 'inserted';
			}

			//update additions tab
			$additions_tab_values = $values;
			$additions_tab_values += array(
				$this->_google_header($this->_type_settings['additions_date_field']) => $update_time,
				$this->_google_header($this->_type_settings['additions_status_field']) => $status,
				$this->_google_header($this->_type_settings['additions_id_field']) => $post_id,
			);
			$additions_tab_values = apply_filters('google_doc_records/additions_additions_tab_values',$additions_tab_values);
			$additions_tab_values = apply_filters('google_doc_records/additions_additions_tab_values_'.$this->_type,$additions_tab_values);
			$entry->update($additions_tab_values);
			$i++;
		}

		$this->_log($i.' records processed.');
	}

	/**
	 * Handle corrections and direct back to main page
	 */
	function _corrections_process(){
		$this->_handle_corrections();

		$this->_save_log(array(
			'post_type'=>substr('gdrc_log_'.$this->_type,0,20),
			'post_title'=>'Corrections Process '.$this->_type_settings['label'],
		));

		$query = array(
			'page'=>$this->plugin_name,
			'message'=>170,
			'message-variables'=>array(
				'type'=>$this->_type_settings['label'],
			),
		);
		wp_redirect(admin_url('admin.php?'.http_build_query($query)));
		exit;
	}

	/**
	 * Process records in corrections tab
	 */
	function _handle_corrections($process_limit = 0){
		$this->_double_check_process_requirements(array('stem'=>'corrections','process'=>'Corrections'));

		$this->_log('Conditions Processing.');
		$update_time = date('m/d/y H:i:s');

		//load needed worksheets
		$master_worksheet = $this->_get_worksheet($this->_type_settings['master_tab']);
		$worksheet = $this->_get_worksheet($this->_type_settings['corrections_tab']);
		$query = array("sq" => $this->_google_header($this->_type_settings['corrections_status_field'])." = \"\"",);
		$listFeed = $worksheet->getListFeed($query);
		$entries = $listFeed->getEntries();
		$this->_log('Found '.count($entries).'.');
		$i = 0;
		foreach ($entries as $entry) {
			if($process_limit > 0 && $i > $process_limit){
				break;
			}
			$values = $entry->getValues();
			$id = (int)$values[$this->_google_header($this->_type_settings['corrections_id_field'])];
			unset($values[$this->_google_header($this->_type_settings['corrections_id_field'])]);
			unset($values[$this->_google_header($this->_type_settings['corrections_date_field'])]);
			unset($values[$this->_google_header($this->_type_settings['corrections_status_field'])]);

			//apply map field for spreadsheet values
			$wp_values = array(
				'ID'=>$id,
			);
			foreach($this->_type_settings['field_map'] as $field){
				$wp_values[$field['wp_field']] = $values[$this->_google_header($field['doc_field'])];
			}

			//allow code to adjust what is saved to wordpress
			$wp_values = apply_filters('google_doc_records/corrections_values',$wp_values);
			$wp_values = apply_filters('google_doc_records/corrections_values_'.$this->_type,$wp_values);

			$post_id = $this->_wp_record($wp_values,'wp_update_post');
			if(is_wp_error($post_id)){
				$this->_log('Unable to update post: '.$id.'.');
				$this->_log($post_id->get_error_messages());
				$status = 'error';
			}else{
				//update master tab
				$args = array(
					'sq'=>$this->_google_header($this->_type_settings['master_id'])." = $id",
				);
				$master_list_feed = $master_worksheet->getListFeed($args);
				$master_tab_values = $values;
				$master_tab_values += array(
					$this->_google_header($this->_type_settings['master_sync_date']) => $update_time,
					$this->_google_header($this->_type_settings['master_sync_status']) => 'updated',
				);
				$master_tab_values = apply_filters('google_doc_records/corrections_master_tab_values',$master_tab_values);
				$master_tab_values = apply_filters('google_doc_records/corrections_master_tab_values_'.$this->_type,$master_tab_values);
				foreach($master_list_feed->getEntries() as $update_row){
					$update_row->update($master_tab_values);
				}
				$status = 'updated';
			}

			//update corrections tab
			$corrections_tab_values = $values;
			$corrections_tab_values += array(
				$this->_google_header($this->_type_settings['corrections_date_field']) => $update_time,
				$this->_google_header($this->_type_settings['corrections_status_field']) => $status,
			);
			$corrections_tab_values = apply_filters('google_doc_records/corrections_corrections_tab_values',$corrections_tab_values);
			$corrections_tab_values = apply_filters('google_doc_records/corrections_corrections_tab_values_'.$this->_type,$corrections_tab_values);
			$entry->update($corrections_tab_values);
			$i++;
		}

		$this->_log($i.' records processed.');
	}

	/**
	 * update/insert record in wordpress database
	 * @param array $rs array of values from google doc
	 */
	function _wp_record($rs,$fxn = 'wp_insert_post'){
		$to_update = array_intersect_key($rs, $this->_standard_wp_fields);
		$post_id = $fxn($to_update, TRUE);
		if(is_wp_error($post_id)){
			return $post_id;
		}

		$meta_fields = array_diff_key($rs, $this->_standard_wp_fields);

		foreach($meta_fields as $k=>$v){
			update_post_meta($post_id,$k,$v);
		}

		return $post_id;
	}

	/**
	 * Handle deletions and direct back to main page
	 */
	function _deletions_process(){
		$this->_handle_corrections();

		$this->_save_log(array(
			'post_type'=>substr('gdrc_log_'.$this->_type,0,20),
			'post_title'=>'Deletions Process '.$this->_type_settings['label'],
		));

		$query = array(
			'page'=>$this->plugin_name,
			'message'=>180,
			'message-variables'=>array(
				'type'=>$this->_type_settings['label'],
			),
		);
		wp_redirect(admin_url('admin.php?'.http_build_query($query)));
		exit;
	}

	/**
	 * Process records in deletions tab, remove from master spreadsheet tab
	 */
	function _handle_deletions($process_limit = 0){
		$this->_double_check_process_requirements(array('stem'=>'deletions','process'=>'Deletions'));

		$this->_log('Deletions Processing.');
		$update_time = date('m/d/y H:i:s');

		//load needed worksheets
		$master_worksheet = $this->_get_worksheet($this->_type_settings['master_tab']);
		$worksheet = $this->_get_worksheet($this->_type_settings['deletions_tab']);
		$query = array("sq" => $this->_google_header($this->_type_settings['deletions_status_field'])." = \"\"",);
		$listFeed = $worksheet->getListFeed($query);
		$entries = $listFeed->getEntries();
		$this->_log('Found '.count($entries).'.');
		$i = 0;
		foreach ($entries as $entry) {
			if($process_limit > 0 && $i > $process_limit){
				break;
			}
			$values = $entry->getValues();
			$id = (int)$values[$this->_google_header($this->_type_settings['deletions_id_field'])];

			if(false === wp_delete_post($id)){
				$this->_log('Unable to delete post: '.$id.'.');
				$status = 'error';
			}else{
				//update master tab
				$args = array(
					'sq'=>$this->_google_header($this->_type_settings['master_id'])." = $id",
				);
				$master_list_feed = $master_worksheet->getListFeed($args);
				foreach($master_list_feed->getEntries() as $delete_row){
					$delete_row->delete();
				}
				$status = 'deleted';
			}

			//update deletion tab
			$deletions_tab_values = $values;
			$deletions_tab_values += array(
				$this->_google_header($this->_type_settings['deletions_date_field']) => $update_time,
				$this->_google_header($this->_type_settings['deletions_status_field']) => $status,
			);
			$deletions_tab_values = apply_filters('google_doc_records/deletions_deletions_tab_values',$deletions_tab_values);
			$deletions_tab_values = apply_filters('google_doc_records/deletions_deletions_tab_values_'.$this->_type,$deletions_tab_values);
			$entry->update($deletions_tab_values);
			$i++;
		}

		$this->_log($i.' records processed.');
	}

	/**
	 * setup authentication with google api, and establish a service object to tag manager
	 * currently using the "server" side oauth authentication method
	 */
	function _setup_client(){
		if(session_id() == '') {
			session_start();
		}

		if(!is_null($this->_service)){
			return;
		}

		$this->_client = new Google_Client();
		$this->_client->setApplicationName(get_option($this->plugin_name.'_project'));

		$json = get_option($this->plugin_name.'_client_json');
		$decoded_json = json_decode($json);
		if(!isset($decoded_json->installed)){
			$decoded_json->installed = 'installed';
		};
		$this->_client->setAuthConfig(json_encode($decoded_json));

		if (isset($_SESSION[$this->plugin_name.'-google-oauth-token'])) {
			$this->_client->setAccessToken($_SESSION[$this->plugin_name.'-google-oauth-token']);
		}

		$cred = new Google_Auth_AssertionCredentials(
			$decoded_json->client_email,
			array(
				'https://spreadsheets.google.com/feeds',
			),
			$decoded_json->private_key,
			false
		);
		$this->_client->setAssertionCredentials($cred);
		if ($this->_client->getAuth()->isAccessTokenExpired()) {
			$this->_client->getAuth()->refreshTokenWithAssertion($cred);
		}
		$token = $this->_client->getAccessToken();
		$_SESSION[$this->plugin_name.'-google-oauth-token'] = $token;

		$decoded_token = json_decode($token);
		$serviceRequest = new DefaultServiceRequest($decoded_token->access_token);
		ServiceRequestFactory::setInstance($serviceRequest);

		$this->_service = new Google\Spreadsheet\SpreadsheetService();
	}

	/**
	 * initialize log post array
	 */
	function _start_log(){
		$this->_log_contents = array();
	}

	/**
	 * add a message to log file
	 * @param  mixed $msg string/array of messages to add
	 */
	function _log($msg){
		if(is_array($msg)){
			foreach($msg as $m){
				$this->_log($m);
			}
		}
		$this->_log_contents[] = $msg;
	}

	/**
	 * save log file in wordpress database
	 * @param  array  $log_entry wordpress insert post array
	 */
	function _save_log($log_entry = array()){
		$log_entry += array(
			'post_type'=>'gdrc_log',
			'post_status'=>'private',
			'post_title'=>'General Google Doc Record Log',
		);

		$log_entry['post_content'] = implode("\n",$this->_log_contents);

		wp_insert_post($log_entry);
	}

	/**
	 * Output settings page
	 */
	function settings_page(){
		//get our settings values from database
		$this->_load_options();

		//pass some class variables to template
		$option_group = $this->plugin_name.'-options-group';
		$option_page = $this->plugin_name.'-options-page';
		include(plugin_dir_path( dirname( __FILE__ ) ).'admin/partials/settings-page.php');
	}

	/**
	 * setup settings page with our options, setup as standard options page
	 */
	function _settings_page(){
		foreach($this->_options as $k => &$v) {
			register_setting(
				$this->plugin_name.'-options-group',
				$this->plugin_name.'_'.$k
			);
		}

		add_settings_section(
			$this->plugin_name.'-options-section',
			'Google OAuth Settings',
			array(&$this, 'render_section'),
			$this->plugin_name.'-options-page'
		);

		foreach($this->_options as $k => &$v) {
			add_settings_field(
				$this->plugin_name.'-'.$k,
				$this->_options[$k]['label'],
				array(&$this, $this->_options[$k]['callback']),
				$this->plugin_name.'-options-page',
				$this->plugin_name.'-options-section',
				array('label_for'=>$this->plugin_name.'_'.$k)
			);
		}
	}

	function render_section(){}

	/**
	 * html input for project name field
	 * @return string html input
	 */
	function _input_project_name(){
		$key = 'project';
		echo $this->_text_input($key);
	}

	/**
	 * html input for json key field
	 * @return string html input
	 */
	function _input_json_key(){
		$key = 'client_json';
		echo $this->_textarea_input($key,array(),array('cols'=>60,'rows'=>10));
	}

	/**
	 * general text input type for settings
	 * @param  string $key     options key
	 * @param  array  $params  parameters to affect output
	 * @param  array  $attribs additional html attributes to avoid having to overwrite most used ones
	 * @return string          html input field
	 */
	function _text_input($key,$params = array(),$attribs = array()){
		$params += array(
			'attributes'=>array(
				'id'=>$this->plugin_name.'_'.$key,
				'name'=>$this->plugin_name.'_'.$key,
				'value'=>$this->_options[$key]['value'],
				'type'=>'text',
				'size'=>60,
			),
		);
		extract($params);
		$attribs += $attributes;
		return '<input '.$this->_html_attribs($attribs).'>';
	}

	/**
	 * general textarea input type for settings
	 * @param  string $key     options key
	 * @param  array  $params  parameters to affect output
	 * @param  array  $attribs additional html attributes to avoid having to overwrite most used ones
	 * @return string          html input field
	 */
	function _textarea_input($key,$params = array(),$attribs = array()){
		$params += array(
			'attributes'=>array(
				'id'=>$this->plugin_name.'_'.$key,
				'name'=>$this->plugin_name.'_'.$key,
			),
			'value'=>$this->_options[$key]['value'],
		);
		extract($params);
		$attribs += $attributes;
		return '<textarea '.$this->_html_attribs($attribs).'>'.esc_html($value).'</textarea>';
	}

	/**
	 * general function to escape and concatenate html attributes
	 * @param  array $attribs key=>value array of attributes
	 * @return string          html attributes escaped and spaced
	 */
	function _html_attribs($attribs){
		$ret = array();
		foreach($attribs as $k=>$v){
			$ret[] = $k.'="'.esc_attr($v).'"';
		}
		return implode(" ",$ret);
	}

	/**
	 * Register the stylesheets for the Dashboard.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Nearest_Location_Admin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Nearest_Location_Admin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		$allowed = array(
			$this->plugin_name=>'1',
			$this->plugin_name.'-options-page'=>1,
		);

		if(!isset($allowed[$_GET['page']])){
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/google-doc-records-admin.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'prefix-font-awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css', array(), '4.3.0' );
		wp_enqueue_style( 'prefix-bootstrap', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css', array(), '3.3.5' );
	}

	/**
	 * Register the JavaScript for the dashboard.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Nearest_Location_Admin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Nearest_Location_Admin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		$allowed = array(
			$this->plugin_name=>'1',
			$this->plugin_name.'-options-page'=>1,
		);

		if(!isset($allowed[$_GET['page']])){
			return;
		}

		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-tabs');

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/google-doc-records-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * retrieve a variable from post or get
	 * @param  string $name variable name
	 * @return mixed       variable value
	 */
	function _request_var($name){
		if(isset($_POST[$name])){
			return $_POST[$name];
		}
		if(isset($_GET[$name])){
			return $_GET[$name];
		}
		return false;
	}
}
