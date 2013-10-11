<?php
/**
 * Uploadify module - provides Uploadify client side scripts along with back-end processing for use with other modules
 *
 * @package Modules
 * @subpackage Uploadify
 * @author Peter Epp
 * @copyright Copyright (c) 2009 Peter Epp (http://teknocat.org)
 * @license GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 * @version 1.0 $Id: controller.php 14599 2012-03-16 16:15:02Z teknocat $
 */
class UploadifyManager extends AbstractModuleController {
	/**
	 * Check for existing files
	 */
	const CHECK_EXISTING = true;
	/**
	 * Don't check for existing files
	 */
	const NO_CHECK_EXISTING = false;
	/**
	 * Overwrite existing files
	 */
	const OVERWRITE_EXISTING = true;
	/**
	 * Do not overwrite existing files
	 */
	const NO_OVERWRITE_EXISTING = false;
	/**
	 * Place to store the name of the existing file currently being checked. This value can get set by other modules when existing file check is performed
	 *
	 * @var string
	 */
	protected $_current_checked_existing_filename;
	/**
	 * Register the JS and CSS components. Call this with the module that needs to use it
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_secondary() {
		LibraryLoader::load('Swfobject');
		$this->Biscuit->register_js('footer','modules/uploadify/vendor/jquery.uploadify/jquery.uploadify.v2.1.4.min.js');
		$this->register_js('footer','uploadify_helpers.js');
		$this->register_css(array('filename' => 'uploadify.css', 'media' => 'screen'));
	}
	/**
	 * Render an Uploadify file upload field
	 *
	 * @param string $field_name 
	 * @param string $field_id 
	 * @param string $folder Path to upload to, relative to the uploads root
	 * @param bool $check_existing Whether or not to check if files exist before uploading
	 * @param bool $overwrite Whether or not to overwrite existing files
	 * @param array $options Uploadify options in the same format as the JS options as defined at {@link http://www.uploadify.com/documentation/}
	 * @return void
	 * @author Peter Epp
	 */
	public function render_upload_field($field_name, $field_id, $folder, $check_existing = self::CHECK_EXISTING, $overwrite = self::OVERWRITE_EXISTING, $options = array()) {
		$folder = trim($folder,'/');
		$default_options = array(
			'folder'       => '/var/uploads/'.$folder,
			'uploader'     => '/modules/uploadify/vendor/jquery.uploadify/uploadify.swf',
			'script'       => '/uploadify/handle_upload',
			'fileDataName' => 'uploadify_files',
			'auto'         => true,
			'cancelImg'    => '/framework/themes/sea_biscuit/images/x-button.png',
			'sizeLimit'    => FileUpload::max_size()
		);
		if ($check_existing) {
			$default_options['checkScript'] = '/uploadify/check_existing';
			// We provide a custom onCheck override function that checks if the data var is a string. This is because Biscuit adds the inArray function which
			// for some reason becomes an element of an array when looped through
			$default_options['onCheck'] = <<<JAVASCRIPT
function(event, data, key) {
	if (typeof(data[key]) == 'string') {
		var replaceFile = confirm(__('confirm_overwrite', [data[key]]));
		if (!replaceFile) {
			$(event.target).uploadifyCancel(key);
		}
	}
	return false;
}
JAVASCRIPT;
		}
		$default_options['onComplete'] = <<<JAVASCRIPT
function(event, id, fileObj, response, data) {
	if (response != '1') {
		$('#$field_id').uploadifyCancel(id);
		if (UploadifyHelpers.upload_errors['$field_id'] == undefined) {
			UploadifyHelpers.upload_errors['$field_id'] = [];
		}
		UploadifyHelpers.upload_errors['$field_id'].push('<strong>'+fileObj.name+'</strong>: '+response);
	}
	Biscuit.Session.Extend();
}
JAVASCRIPT;
		$default_options['onAllComplete'] = <<<JAVASCRIPT
function() {
	UploadifyHelpers.all_complete('$field_id');
}
JAVASCRIPT;
		$default_options['onSelect'] = <<<JAVASCRIPT
function(event, id, fileObj) {
	UploadifyHelpers.on_select(event, id, fileObj, '$field_id');
}
JAVASCRIPT;
		$default_options['onSelectOnce'] = <<<JAVASCRIPT
function(event, data) {
	UploadifyHelpers.on_select_once(event, data, '$field_id');
}
JAVASCRIPT;
		$default_options['onError'] = <<<JAVASCRIPT
function(event, id, fileObj, errorObj) {
	$('#$field_id').uploadifyCancel(id);
	if (UploadifyHelpers.upload_errors['$field_id'] == undefined) {
		UploadifyHelpers.upload_errors['$field_id'] = [];
	}
	if (errorObj.type != 'File Size') {
		UploadifyHelpers.upload_errors['$field_id'].push('<strong>'+fileObj.name+'</strong>: '+errorObj.type+' '+errorObj.info);
	}
	Biscuit.Session.Extend();
}
JAVASCRIPT;
		$custom_all_complete_function = '';
		if (!empty($options['onAllComplete'])) {
			$custom_all_complete_function = $options['onAllComplete'];
		}
		$custom_on_select_function = '';
		if (!empty($options['onSelect'])) {
			$custom_on_select_function = $options['onSelect'];
		}
		$custom_on_select_once_function = '';
		if (!empty($options['onSelectOnce'])) {
			$custom_on_select_once_function = $options['onSelectOnce'];
		}
		// Make sure other options the user passed do not contain any of the ones that must be set by this method and never overriden
		$disallowed_options = array('folder','uploader','script','fileDataName','checkScript','sizeLimit','onCheck','onError','onComplete','onAllComplete','onSelect','onSelectOnce');
		foreach ($options as $key => $value) {
			if (in_array($key, $disallowed_options)) {
				unset($options[$key]);
			}
		}
		$all_options = array_merge($default_options, $options);
		$all_options['scriptData']['overwrite'] = ($overwrite) ? 'true' : 'false';
		$all_options['scriptData']['session_id'] = Session::id();
		// Turn options into JS object string:
		$js_options = array();
		foreach ($all_options as $key => $value) {
			if (is_bool($value)) {
				$value = (($value) ? 'true' : 'false');
			} else if (is_array($value)) {
				$sub_options = array();
				foreach ($value as $value_key => $value_value) {
					$value_value = addslashes($value_value);
					$value_value = str_replace("\/","/",$value_value);
					$value_value = "'".$value_value."'";
					$sub_options[] = "'".$value_key."' : ".$value_value;
				}
				$value = "{".implode(",",$sub_options)."}";
			} else if (substr($value,0,8) != 'function') {
				$value = addslashes($value);
				$value = str_replace("\/","/",$value);
				$value = "'".$value."'";
			}
			$js_options[] = "'".$key."': ".$value;
		}
		$js_options = "{\n".implode(",\n",$js_options)."\n}";
		$view_vars = array(
			'field_name'                     => $field_name,
			'field_id'                       => $field_id,
			'uploadify_options'              => $js_options,
			'is_automatic'                   => $all_options['auto'],
			'custom_all_complete_function'   => $custom_all_complete_function,
			'custom_on_select_function'      => $custom_on_select_function,
			'custom_on_select_once_function' => $custom_on_select_once_function
		);
		return Crumbs::capture_include('uploadify/views/upload_field.php', $view_vars);
	}
	/**
	 * Process a file upload
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_handle_upload() {
		$all_files = Request::files();
		if (!empty($all_files) && !empty($all_files['uploadify_files'])) {
			$overwrite = ($this->params['overwrite'] == 'true') ? true : false;
			$upload = new FileUpload($all_files['uploadify_files'], $this->params['folder'], $overwrite);
			if ($upload->is_okay()) {
				// Hook to allow others to kick in and do something with the uploaded file at this point if desired
				Event::fire('file_uploaded',$upload);
				$this->Biscuit->render('1');
			} else {
				$this->Biscuit->render($upload->get_error_message());
			}
		} else {
			$this->Biscuit->render(__('File cannot be uploaded. Is it too large?'));
		}
	}
	/**
	 * Check for existing files and output the list of existing ones in JSON format
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_check_existing() {
		$existing_files = array();
		$base_path = SITE_ROOT.rtrim($this->params['folder'], '/');
		foreach ($this->params as $key => $value) {
			if ($key != 'folder') {
				$cleaned_filename = $this->_get_clean_filename($base_path.'/'.$value);
				$file_path = $base_path.'/'.$cleaned_filename;
				$this->_current_checked_existing_filename = null;
				if (file_exists($file_path)) {
					Event::fire("uploadify_file_exists_check", $file_path, $this);
					if (!empty($this->_current_checked_existing_filename)) {
						$value = $this->_current_checked_existing_filename;
					}
					$existing_files[$key] = $value;
				}
			}
		}
		$this->Biscuit->render_json($existing_files);
	}
	/**
	 * Set the name value to use for an existing file. Call this on the "uploadify_file_exists_check" event to override the value that gets returned 
	 *
	 * @param string $value 
	 * @return void
	 * @author Peter Epp
	 */
	public function set_existing_file_name_value($value) {
		$this->_current_checked_existing_filename = $value;
	}
	/**
	 * Get a filename cleaned up the same way as the file upload class
	 *
	 * @param string $file_path 
	 * @return void
	 * @author Peter Epp
	 */
	private function _get_clean_filename($file_path) {
		$info = pathinfo($file_path);
		$ext  = $info['extension'];
		$name = $info['filename'];
		
		$name = preg_replace("'[^A-Za-z0-9_-]+'","_", $name);

		return $name . '.' . $ext;
	}
	/**
	 * Ignore request tokens on uploadify requests
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_request_token_check() {
		if ($this->is_primary()) {
			RequestTokens::set_ignore($this->Biscuit->Page->hyphenized_slug());
		}
	}
	/**
	 * Provide URI mapping rule for the spider action
	 *
	 * @return array
	 * @author Peter Epp
	 */
	public static function uri_mapping_rules() {
		return array(
			'/^(?P<page_slug>uploadify)\/(?P<action>handle_upload)$/',
			'/^(?P<page_slug>uploadify)\/(?P<action>check_existing)$/'
		);
	}
	public static function install_migration($module_id) {
		DB::query("INSERT INTO `page_index` (`parent`, `slug`, `title`) VALUES (9999999, 'uploadify', 'Uploadify Handler')");
		DB::query("INSERT INTO `module_pages` SET `module_id` = {$module_id}, `page_name` = 'uploadify', `is_primary` = 1");
		DB::query("UPDATE `modules` SET `installed` = 1 WHERE `id` = {$module_id}");
	}
	public static function uninstall_migration($module_id) {
		DB::query("DELETE FROM `page_index` WHERE `slug` = 'uploadify'");
		DB::query("DELETE FROM `module_pages` WHERE `module_id` = {$module_id}");
		DB::query("UPDATE `modules` SET `installed` = 0 WHERE `id` = {$module_id}");
	}
}
