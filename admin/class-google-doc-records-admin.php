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
		$type = isset($_GET['type'])? trim($_GET['type']):'';
		if($type == ''){
			//message
			return;
		}

		$sync_settings = maybe_unserialize(get_option($this->plugin_name.'-sync-settings'));
		if(!is_array($sync_settings)){
			$sync_settings = array();
		}

		if(!isset($sync_settings[$type])){
			//message
			return;
		}

		$auto_setting = false;
		$settings = $sync_settings[$type];

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
		if(!isset($_POST['google_doc_record_configure_nonce'])){
			return;
		}

		if(!check_admin_referer('configure_sync','google_doc_record_configure_nonce')){
			//message + redirect
			return;
		}

		$type = isset($_GET['type'])? trim($_GET['type']):'';
		if($type == ''){
			//message + redirect
			return;
		}

		//load current settings
		$sync_settings = maybe_unserialize(get_option($this->plugin_name.'-sync-settings'));
		if(!is_array($sync_settings)){
			$sync_settings = array();
		}

		if(!isset($sync_settings[$type])){
			//message + redirect
			return;
		}

		//update settings with new values
		$sync_settings[$type] = $_POST['google_doc_record_configure'] + $sync_settings[$type];

		//save settings
		update_option($this->plugin_name.'-sync-settings',serialize($sync_settings));
		$query = array(
			'page'=>$this->plugin_name,
			'mode'=>'configure',
			'type'=>$type,
			'message'=>'XXX',
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
		$sync_settings = maybe_unserialize(get_option($this->plugin_name.'-sync-settings'));
		if(!is_array($sync_settings)){
			$sync_settings = array();
		}

		$std_post = new stdClass();
		$std_post->label = 'Posts';
		$post_types = get_post_types(array('show_ui'=>true,'_builtin'=>false),'objects');
		$post_types['post'] = $std_post;
		$post_types = array_diff_key($post_types,$sync_settings);

		$spreadsheets = $this->_service->getSpreadsheets();

		include(plugin_dir_path( dirname( __FILE__ ) ).'admin/partials/sync-list-top.php');

		foreach($sync_settings as $name=>$post_type){
			include(plugin_dir_path( dirname( __FILE__ ) ).'admin/partials/sync-list-item.php');
		}

		include(plugin_dir_path( dirname( __FILE__ ) ).'admin/partials/sync-list-bottom.php');
	}

	/**
	 * add the new post type to record sync - note that it will still need to be configured at another time, this just puts it into the array
	 */
	function _add_sync_type(){
		if(!check_admin_referer('add_sync','google_doc_record_add_nonce')){
			//message + redirect
			return;
		}

		//load current settings
		$sync_settings = maybe_unserialize(get_option($this->plugin_name.'-sync-settings'));
		if(!is_array($sync_settings)){
			$sync_settings = array();
		}

		//generate array for new post type
		$post_type = $_POST['google_doc_record_add'];
		$post_types = get_post_types( array('name'=>$post_type['type']), 'objects' );
		$post_type['label'] = $post_types[$post_type['type']]->label;

		//save updated settings
		$sync_settings[$post_type['type']] = $post_type;
		update_option($this->plugin_name.'-sync-settings',serialize($sync_settings));

		wp_redirect(admin_url('admin.php?page='.$this->plugin_name.'&message=XXX'));
		exit;
	}

	/**
	 * determine and perform specified process
	 */
	function _process_sync(){
		$process = isset($_GET['process'])? trim($_GET['process']):'';
		$type = isset($_GET['type'])? trim($_GET['type']):'';
		$nonce = isset($_GET['nonce'])? trim($_GET['nonce']):'';
		if($process == '' || $type == '' || $nonce == ''){
			wp_redirect(admin_url('admin.php?page='.$this->plugin_name.'&message=XXX'));
			exit;
		}

		if(!wp_verify_nonce($nonce,$type.'_'.$process)){
			wp_redirect(admin_url('admin.php?page='.$this->plugin_name.'&message=XXX'));
			exit;
		}

		switch($process){
			case 'remove':
				$this->_remove_sync_type($type);
			break;
			case 'full':
			break;
			case 'additions':
			break;
			case 'corrections':
			break;
			case 'deletions':
			break;
			case 'resync':
			break;
		}

		echo $process,'<hr>',$type;
		exit;
	}

	/**
	 * remove a post type from the configured syncs array
	 * @param  string $type post type
	 */
	function _remove_sync_type($type){
		//load current settings
		$sync_settings = maybe_unserialize(get_option($this->plugin_name.'-sync-settings'));
		if(!is_array($sync_settings)){
			$sync_settings = array();
		}

		unset($sync_settings[$type]);

		//save updated settings
		update_option($this->plugin_name.'-sync-settings',serialize($sync_settings));
		wp_redirect(admin_url('admin.php?page='.$this->plugin_name.'&message=XXX'));
		exit;
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
