var UploadifyHelpers = {
	upload_key: null,
	upload_event: null,
	upload_errors: {},
	max_file_size: null,
	oversize_files: [],
	custom_all_complete_functions: {},
	custom_on_select_functions: {},
	custom_on_select_once_functions: {},
	on_select: function(event, id, fileObj, field_id) {
		if (fileObj.size > this.max_file_size) {
			$('#'+field_id).uploadifyCancel(id);
			this.oversize_files.push(fileObj.name);
		}
		if (this.custom_on_select_functions[field_id] != undefined) {
			this.custom_on_select_functions[field_id]();
		}
	},
	on_select_once: function(event, data, field_id) {
		if (this.oversize_files.length > 0) {
			var message = '<h4>'+__('files_too_big_msg')+'</h4><ul><li>'+this.oversize_files.join('</li><li>')+'</li></ul>';
			this.oversize_files = []
			Biscuit.Crumbs.Alert(message, __('error_box_title'));
		}
		if (this.custom_on_select_once_functions[field_id] != undefined) {
			this.custom_on_select_once_functions[field_id]();
		}
	},
	all_complete: function(field_id) {
		if (this.custom_all_complete_functions[field_id] != undefined) {
			this.custom_all_complete_functions[field_id]();
		}
		if (this.upload_errors[field_id] != undefined && this.upload_errors[field_id].length > 0) {
			Biscuit.Console.log("Errors occured");
			var message = '<p>'+__('uploads_failed')+'</p><ul><li>'+this.upload_errors[field_id].join('</li><li>')+'</li></ul>';
			Biscuit.Crumbs.Alert(message, __('error_box_title'));
			this.upload_errors[field_id] = []
		}
	}
}
