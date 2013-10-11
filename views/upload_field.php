<div class="uploadify-field">
	<input id="attr_<?php echo $field_id; ?>" name="<?php echo $field_name ?>" type="file">
	<?php
	if (!$is_automatic) {
		?><a href="#upload" id="uploadify-trigger-link"><?php echo __('Upload'); ?></a><?php
	}
	?>
</div>
<script type="text/javascript">
	$(document).ready(function() {
		<?php
		if (!$is_automatic) {
		?>
		$('#uploadify-trigger-link').click(function() {
			$('#attr_<?php echo $field_id; ?>').uploadifyUpload();
			return false;
		});
		<?php
		}
		if (!empty($custom_all_complete_function)) {
			?>
		UploadifyHelpers.custom_all_complete_functions['<?php echo $field_id; ?>'] = <?php echo $custom_all_complete_function; ?>;
			<?php
		}
		if (!empty($custom_on_select_function)) {
			?>
		UploadifyHelpers.custom_on_select_functions['<?php echo $field_id; ?>'] = <?php echo $custom_on_select_function; ?>;
			<?php
		}
		if (!empty($custom_on_select_once_function)) {
			?>
		UploadifyHelpers.custom_on_select_once_functions['<?php echo $field_id; ?>'] = <?php echo $custom_on_select_once_function; ?>;
			<?php
		}
		?>

		UploadifyHelpers.max_file_size = <?php echo FileUpload::max_size(); ?>;
		$('#attr_<?php echo $field_id; ?>').uploadify(<?php echo $uploadify_options; ?>);
	});
</script>
