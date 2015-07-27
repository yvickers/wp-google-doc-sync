<?php

class Google_Doc_Records_Messages{
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

	private $format = '<div class="alert alert-%type$s" role="alert"><p>%content$s</p></div>';

	private $_messages = array(
		''=>array(
			'type'=>'',//success, info, warning, danger
			'content'=>'',//message content
		),
		9=>array(
			'type'=>'danger',
			'content'=>'Detected possibilty of losing data during save of settings.  Aborted.',
		),
		100=>array(
			'type'=>'success',
			'content'=>'Registered new sync spreadsheet for %type$s.',
		),
		101=>array(
			'type'=>'success',
			'content'=>'Removed sync for %type$s.',
		),
		160=>array(
			'type'=>'success',
			'content'=>'Additions process complete for %type$s.',
		),
		170=>array(
			'type'=>'success',
			'content'=>'Corrections process complete for %type$s.',
		),
		180=>array(
			'type'=>'success',
			'content'=>'Deletions process complete for %type$s.',
		),
		200=>array(
			'type'=>'success',
			'content'=>'Configuration saved for %type$s.',
		),
		900=>array(
			'type'=>'danger',
			'content'=>'No post type passed to configuration screen.',
		),
		901=>array(
			'type'=>'danger',
			'content'=>'Sync for post type [%type$s] not defined, please use the Add New Record Sync form.',
		),
		902=>array(
			'type'=>'danger',
			'content'=>'Configuration nonce is not valid.',
		),
		950=>array(
			'type'=>'danger',
			'content'=>'Missing required parameter to start process.',
		),
		951=>array(
			'type'=>'danger',
			'content'=>'Missing sync settings for %type$s.',
		),
		952=>array(
			'type'=>'danger',
			'content'=>'Missing required sync settings for %process$s process of %type$s.  Please check your configuration settings for %type$s.',
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
	 * load and display messages from query string
	 * @return sting messaging
	 */
	public function display(){
		$messages = isset($_GET['message'])? $_GET['message']:array();
		$message_vars = isset($_GET['message-variables'])? $_GET['message-variables']:array();
		if(!is_array($messages)){
			$messages = array($messages);
		}

		$messages = array_flip($messages);
		$messages = array_intersect_key($this->_messages,$messages);
		foreach($messages as $message){
			$message['content'] = $this->vksprintf($message['content'],$message_vars);
			echo $this->vksprintf($this->format,$message);
		}
	}

	/**
	 * vsprintf functionality with a keyed array
	 * @param  string $str  message format
	 * @param  array $args keyed array for replacements
	 * @return string       resulting message
	 */
	function vksprintf($str, $args) {
		if (is_object($args)) {
			$args = get_object_vars($args);
		}
		$map = array_flip(array_keys($args));
		$new_str = preg_replace_callback('/(^|[^%])%([a-zA-Z0-9_-]+)\$/',
			function($m) use ($map) { return $m[1].'%'.($map[$m[2]] + 1).'$'; },
			$str
		);
		return vsprintf($new_str, $args);
	}
}