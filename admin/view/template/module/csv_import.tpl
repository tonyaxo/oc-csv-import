<?php echo $header; ?>
<div id="content">
<div class="breadcrumb">
  <?php foreach ($breadcrumbs as $breadcrumb) { ?>
  <?php echo $breadcrumb['separator']; ?><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a>
  <?php } ?>
</div>
<?php if ($error_warning) { ?>
<div class="warning"><?php echo $error_warning; ?></div>
<?php } ?>
<div class="box">
  <div class="heading">
    <h1><img src="view/image/module.png" alt="" /> <?php echo $heading_title; ?></h1>
    <div class="buttons">
		<a onclick="printResult()" class="button" id="print_button"><span><?php echo $button_print; ?></span></a>
		<a class="button" id="import_button"><span><?php echo $button_import; ?></span></a>
		<a onclick="$('#form').submit();" class="button"><span><?php echo $button_save; ?></span></a>
		<a href="<?php echo $cancel; ?>" class="button"><span><?php echo $button_cancel; ?></span></a>
	</div>
  </div>
  <div class="content">
	<div class="overlay">&nbsp;</div>
	<div class="overlay text"><img src="view/image/loading.gif" class="loading" style="padding-left: 5px;" /><?php echo $text_import_processing ?></div>
    <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form">
      <table class="form">
        <tr>
          <td><?php echo $entry_import_file; ?></td>
          <td><input type="text" size="50" name="csv_import_file" value="<?php echo $csv_import_file ?>"><a id="button-upload" class="button"><?php echo $button_upload; ?></a></td>
        </tr>
        <tr>
          <td><?php echo $entry_email_report; ?></td>
          <td>
			<?php if ($csv_import_report) { ?>
			<input type="checkbox" name="csv_import_report" value="1" checked="checked" />
			<input type="text" size="50" name="csv_import_email" value="<?php echo $csv_import_email ?>"/>
			<?php } else { ?>
			<input type="checkbox" name="csv_import_report" value="1" />
			<input type="text" size="50" name="csv_import_email" value="<?php echo $csv_import_email ?>" disabled="disabled"/>
			<?php } ?>

		  </td>
        </tr>
        <tr>
          <td><?php echo $entry_fields; ?></td>
          <td>
			<select name="product_field" id="product_field" multiple="multiple" size="6">
				<?php foreach($product_fields as $field => $value) { ?>
					<option value="<?php echo $field ?>"><?php echo $value ?></option>
				<?php } ?>
			</select>
			<a id="add_import_field" class="button"><span><?php echo $button_add_field; ?></span></a>
		  </td>
		</tr>
		<tr>
          <td colspan="2">
			<table class="fields list">
				<tbody>
					<tr id="fields">
					<?php foreach ($csv_import_fields as $key => $csv_import_field) { ?>
						<td title="<?php echo $text_dropped ?>">
							<p><?php echo $product_fields[$csv_import_field] ?></p>
							<input type="hidden" name="csv_import_fields[]" value="<?php echo $csv_import_field ?>" />
							<img src="view/image/delete.png" class="delete-field" title="<?php echo $button_remove ?>" />
						</td>
					<?php } ?>
					</tr>
				</tbody>
			</table>
			<!--<div style="clear:both;"></div>
			<div class="buttons">
				<a href="#" id="check_import" class="button"><span><?php echo $button_check_import; ?></span></a>
			</div> -->
		  </td>
		</tr>
		<tr>
          <td colspan="2">
			<div id="tabs" class="htabs">
				<a href="#tab-basic"><?php echo $tab_general; ?></a>
				<a href="#tab-product"><?php echo $tab_products; ?></a>
				<a href="#tab-category"><?php echo $tab_categories; ?></a>
				<a href="#tab-image"><?php echo $tab_image; ?></a>
			</div>
			<div id="tab-basic">
				<table class="form">
					<tr>
					  <td><?php echo $entry_skip_first ?></td>
					  <td>
						<select name="import_skip_first" id="import_skip_first">
							<?php if ($import_skip_first) { ?>
								<option value="1" selected="selected"><?php echo $text_enabled; ?></option>
								<option value="0"><?php echo $text_disabled; ?></option>
							<?php } else { ?>
								<option value="1"><?php echo $text_enabled; ?></option>
								<option value="0" selected="selected"><?php echo $text_disabled; ?></option>
							<?php } ?>
						</select>
					  </td>
					</tr>
					<tr>
					  <td><?php echo $entry_csv_delimiter ?></td>
					  <td>
						<input type="text" name="import_delimiter" id="import_delimiter" size="2" value="<?php echo $import_delimiter ?>" />
					  </td>
					</tr>
					<tr>
					  <td><?php echo $entry_csv_enclosure ?></td>
					  <td>
						<input type="text" name="import_enclosure" id="import_enclosure" size="2" value="<?php echo $import_enclosure ?>" />
					  </td>
					</tr>
					
				</table>
			</div>
			<div id="tab-product">
				<table class="form">
					<tr>
					  <td><?php echo $entry_import_key ?></td>
					  <td>
						<select name="import_key" id="import_key">
							<?php foreach ($import_keys as $key => $csv_key_field) { ?>
								<?php if ($key == $import_key) { ?>
									<option value="<?php echo $key ?>" selected="selected"><?php echo $product_fields[$key] ?></option>
								<?php } else { ?>
									<option value="<?php echo $key ?>"><?php echo $product_fields[$key] ?></option>
								<?php } ?>
							<?php } ?>
						</select>
					  </td>
					</tr>
					<tr>
					  <td><?php echo $entry_if_exists ?></td>
					  <td>
						<select name="if_exists" id="if_exists">
							<?php if ($if_exists == 0) { ?>
								<option value="0" selected="selected"><?php echo $text_update_if_exists ?></option>
							<?php } else { ?>
								<option value="0"><?php echo $text_update_if_exists ?></option>
							<?php } ?>
							<?php if ($if_exists == 1) { ?>
								<option value="1" selected="selected"><?php echo $text_missing_if_exists ?></option>
							<?php } else { ?>
								<option value="1"><?php echo $text_missing_if_exists ?></option>
							<?php } ?>
						</select>
					  </td>
					</tr>
					<tr>
					  <td><?php echo $entry_if_not_exists ?></td>
					  <td>
						<select name="if_not_exists" id="if_not_exists">
							<?php if ($if_not_exists == 0) { ?>
								<option value="0" selected="selected"><?php echo $text_hide_if_not_exists ?></option>
							<?php } else { ?>
								<option value="0"><?php echo $text_hide_if_not_exists ?></option>
							<?php } ?>
							<?php if ($if_not_exists == 1) { ?>
								<option value="1" selected="selected"><?php echo $text_delete_if_not_exists ?></option>
							<?php } else { ?>
								<option value="1"><?php echo $text_delete_if_not_exists ?></option>
							<?php } ?>
							<?php if ($if_not_exists == 2) { ?>
								<option value="2" selected="selected"><?php echo $text_donothing ?></option>
							<?php } else { ?>
								<option value="2"><?php echo $text_donothing ?></option>
							<?php } ?>
						</select>
					  </td>
					</tr>
				</table>
			</div>
			<div id="tab-category">
				<table class="form">
					<tr>
					  <td><?php echo $entry_create_category; ?></td>
					  <td><select name="csv_import_create_category">
						  <?php if ($csv_import_create_category) { ?>
						  <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
						  <option value="0"><?php echo $text_disabled; ?></option>
						  <?php } else { ?>
						  <option value="1"><?php echo $text_enabled; ?></option>
						  <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
						  <?php } ?>
						</select></td>
					</tr>
					<tr>
					  <td><?php echo $entry_clear_p2c; ?></td>
					  <td><select name="csv_import_clear_p2c">
						  <?php if ($csv_import_clear_p2c) { ?>
						  <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
						  <option value="0"><?php echo $text_disabled; ?></option>
						  <?php } else { ?>
						  <option value="1"><?php echo $text_enabled; ?></option>
						  <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
						  <?php } ?>
						</select></td>
					</tr>
					<tr>
					  <td><?php echo $entry_add_to_parent; ?></td>
					  <td><select name="csv_import_add_to_parent">
						  <?php if ($csv_import_add_to_parent) { ?>
						  <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
						  <option value="0"><?php echo $text_disabled; ?></option>
						  <?php } else { ?>
						  <option value="1"><?php echo $text_enabled; ?></option>
						  <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
						  <?php } ?>
						</select></td>
					</tr>
					<tr>
					  <td><?php echo $entry_category_key ?></td>
					  <td>
						<select name="category_key" id="category_key">
							<?php foreach ($category_keys as $key => $csv_key_field) { ?>
								<?php if ($key == $category_key) { ?>
									<option value="<?php echo $key ?>" selected="selected"><?php echo $category_fields[$key] ?></option>
								<?php } else { ?>
									<option value="<?php echo $key ?>"><?php echo $category_fields[$key] ?></option>
								<?php } ?>
							<?php } ?>
						</select>
					  </td>
					</tr>
				</table>			
			</div>
			<div id="tab-image">
				<table class="form">
					<tr>
					  <td><?php echo $entry_image_dir ?></td>
					  <td>
						<input type="text" name="import_image_dir" id="import_image_dir" size="50" value="<?= $import_image_dir ?>" />
					  </td>
					</tr>
					<tr>
					  <td><?php echo $entry_image_template ?></td>
					  <td>
						<input type="text" name="import_image_template" id="import_image_template" size="50" value="<?= $import_image_template ?>" />
					  </td>
					</tr>
				</table>			
			</div>
		  </td>
		</tr>
      </table>
	  <!--
      <table id="module" class="list">
        <thead>
          <tr>
            <td class="left"><?php echo $entry_limit; ?></td>
            <td class="left"><?php echo $entry_image; ?></td>
            <td class="left"><?php echo $entry_layout; ?></td>
            <td class="left"><?php echo $entry_position; ?></td>
            <td class="left"><?php echo $entry_status; ?></td>
            <td class="right"><?php echo $entry_sort_order; ?></td>
            <td></td>
          </tr>
        </thead>
        <?php $module_row = 0; ?>
        <?php foreach ($modules as $module) { ?>
        <tbody id="module-row<?php echo $module_row; ?>">
          <tr>
            <td class="left"><input type="text" name="csv_import_module[<?php echo $module_row; ?>][limit]" value="<?php echo $module['limit']; ?>" size="1" /></td>
            <td class="left"><input type="text" name="csv_import_module[<?php echo $module_row; ?>][image_width]" value="<?php echo $module['image_width']; ?>" size="3" />
              <input type="text" name="csv_import_module[<?php echo $module_row; ?>][image_height]" value="<?php echo $module['image_height']; ?>" size="3" />
              <?php if (isset($error_image[$module_row])) { ?>
              <span class="error"><?php echo $error_image[$module_row]; ?></span>
              <?php } ?></td>
            <td class="left"><select name="csv_import_module[<?php echo $module_row; ?>][layout_id]">
                <?php foreach ($layouts as $layout) { ?>
                <?php if ($layout['layout_id'] == $module['layout_id']) { ?>
                <option value="<?php echo $layout['layout_id']; ?>" selected="selected"><?php echo $layout['name']; ?></option>
                <?php } else { ?>
                <option value="<?php echo $layout['layout_id']; ?>"><?php echo $layout['name']; ?></option>
                <?php } ?>
                <?php } ?>
              </select></td>
            <td class="left"><select name="csv_import_module[<?php echo $module_row; ?>][position]">
                <?php if ($module['position'] == 'content_top') { ?>
                <option value="content_top" selected="selected"><?php echo $text_content_top; ?></option>
                <?php } else { ?>
                <option value="content_top"><?php echo $text_content_top; ?></option>
                <?php } ?>
                <?php if ($module['position'] == 'content_bottom') { ?>
                <option value="content_bottom" selected="selected"><?php echo $text_content_bottom; ?></option>
                <?php } else { ?>
                <option value="content_bottom"><?php echo $text_content_bottom; ?></option>
                <?php } ?>
                <?php if ($module['position'] == 'column_left') { ?>
                <option value="column_left" selected="selected"><?php echo $text_column_left; ?></option>
                <?php } else { ?>
                <option value="column_left"><?php echo $text_column_left; ?></option>
                <?php } ?>
                <?php if ($module['position'] == 'column_right') { ?>
                <option value="column_right" selected="selected"><?php echo $text_column_right; ?></option>
                <?php } else { ?>
                <option value="column_right"><?php echo $text_column_right; ?></option>
                <?php } ?>
              </select></td>
            <td class="left"><select name="csv_import_module[<?php echo $module_row; ?>][status]">
                <?php if ($module['status']) { ?>
                <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                <option value="0"><?php echo $text_disabled; ?></option>
                <?php } else { ?>
                <option value="1"><?php echo $text_enabled; ?></option>
                <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                <?php } ?>
              </select></td>
            <td class="right"><input type="text" name="csv_import_module[<?php echo $module_row; ?>][sort_order]" value="<?php echo $module['sort_order']; ?>" size="3" /></td>
            <td class="left"><a onclick="$('#module-row<?php echo $module_row; ?>').remove();" class="button"><span><?php echo $button_remove; ?></span></a></td>
          </tr>
        </tbody>
        <?php $module_row++; ?>
        <?php } ?>
        <tfoot>
          <tr>
            <td colspan="6"></td>
            <td class="left"><a onclick="addModule();" class="button"><span><?php echo $button_add_module; ?></span></a></td>
          </tr>
        </tfoot>
      </table> -->
    </form>

	<div id="results">
		<table class="list">
			<thead>
				<tr>
					<td class="left"><?php echo $text_import_results; ?></td>
					<td class="left"><?php echo $text_import_time; ?> <span id="import_time"></span></td>
					<td class="left"><?php echo $text_import_memory; ?> <span id="import_memory"></span></td>
				</tr>
			</thead>
		</table>
		<table class="list" id="import_error">
			<thead>
			  <tr>
				<td class="left"><?php echo $product_fields[$import_key] ?></td>
				<td class="left"><?php echo $text_product_name; ?></td>
				<td class="left"><?php echo $text_product_error; ?></td>
				<td class="left"><?php echo $text_product_status; ?></td>
			  </tr>
			</thead>
			<tbody><!-- results --></tbody>
			<tfoot><tr><td class="center pagination" colspan="3"><!-- pagination --></td></tr></tfoot>
		</table>
		<table class="list" id="import_exclude">
			<thead>
			  <tr>
				<td class="left"><?php echo $product_fields[$import_key] ?></td>
				<td class="left"><?php echo $text_product_name; ?></td>
				<td class="left"><?php echo $text_product_status; ?></td>
			  </tr>
			</thead>
			<tbody><!-- results --></tbody>
			<tfoot><tr><td class="center pagination" colspan="3"><!-- pagination --></td></tr></tfoot>
		</table>
		<table class="list" id="import_success">
			<thead>
			  <tr>
				<td class="left"><?php echo $product_fields[$import_key] ?></td>
				<td class="left"><?php echo $text_product_name; ?></td>
				<td class="left"><?php echo $text_product_status; ?></td>
			  </tr>
			</thead>
			<tbody><!-- results --></tbody>
			<tfoot><tr><td class="center pagination" colspan="3"><!-- pagination --></td></tr></tfoot>
		</table>
	</div>

  </div>
</div>
<style>
	.fields {margin: 10px 0;}
	.fields tbody tr:hover td {background: transparent !important; }
	#fields td { margin: 3px 10px 3px 10px; padding: 2px;  text-align: center; cursor: move;  }
	#fields td:hover {background: #FFFFCB !important;}
	#fields td .button { margin: 0 10px 0 10px; }
	.delete-field {cursor: pointer;}
	#print_button,
	#results {display: none;}
	.content {
		position: relative;
	}
	.overlay {
		background-color: #fff;
		opacity: 0.7;
		position: absolute;
		width:98%;
		min-height: 95%;
		display: none;
	}
	.overlay.text {
		min-height: 0;
		top: 30%;
		text-align: center;
		color: #000;
		font-size: 30px;
		background-color: transparent;
		opacity: 1;
	}
</style>
<script src="http://malsup.github.com/jquery.form.js"></script>
<script type="text/javascript" src="view/javascript/jquery/ajaxupload.js"></script>
<script type="text/javascript" src="view/javascript/jquery.pagination.js"></script>
<script>
	var options = {
        beforeSubmit:  function() {
			$('#import_button').before('<img src="view/image/loading.gif" class="loading" style="padding-left: 5px;" />');
			$('.overlay').fadeIn();
			$(window).bind('beforeunload', function() {
				return '<?php echo $text_exit_on_import ?>';
			});
		},
        success:   function(json) {
			$('.loading, .warning, .success').remove();
			$('.overlay').fadeOut();
			$(window).unbind('beforeunload');
			
			if (typeof json.warning != "undefined") {
				$('.breadcrumb').after('<div class="warning">'+json.warning+'</div>');
				return false;
			}
			
			$('#form').fadeOut();
			$('#print_button').fadeIn();
			$('#import_button').hide();
			$('#results').fadeIn();

			$('.breadcrumb').after('<div class="success"><?php echo $text_import_success ?></div>');

			if (json.warnings != undefined) {
				$.each(json.warnings, function(product_id, product) {
					$('#import_error tbody').append('<tr><td>'+product.key+'</td><td><a target="_blank" href="./index.php?route=catalog/product/update&token=<?php echo $token; ?>&product_id='+product_id+'">'+product.name+'</a></td><td>'+product.error+'</td><td>'+product.status+'</td></tr>');
				});
			}
			if (json.exclude != undefined) {
				$.each(json.exclude, function(product_id, product) {
					$('#import_exclude tbody').append('<tr><td>'+product.key+'</td><td><a target="_blank" href="./index.php?route=catalog/product/update&token=<?php echo $token; ?>&product_id='+product_id+'" >'+product.name+'</a></td><td>'+product.status+'</td></tr>');
				});
			}
			if (json.success != undefined) {
				$.each(json.success, function(product_id, product) {

					$('#import_success tbody').append('<tr><td>'+product.key+'</td><td><a target="_blank" href="./index.php?route=catalog/product/update&token=<?php echo $token; ?>&product_id='+product_id+'" >'+product.name+'</a></td><td>'+product.status+'</td></tr>');
					$('#import_success .pagination').pagination({
						'container': '#import_success tbody'
					});
					$('#import_error .pagination').pagination({
						'container': '#import_error tbody'
					});
					$('#import_exclude .pagination').pagination({
						'container': '#import_exclude tbody'
					});
				});
			}
			$('#import_time').text(json.time);
			$('#import_memory').text(json.memory_total+'/'+json.memory_peak);
			
			$('.buttons a:last').bind('click', function(event) {
				event.preventDefault();
				$('#form').fadeIn();
				$('#print_button').hide();
				$('#import_button').show();
				$('#results').fadeOut();	
				$(this).unbind( event );
			});
		},
        error:   function(jqXHR, textStatus, errorThrown) {
			$('.loading, .warning, .success').remove();
			$('.overlay').fadeOut();
			$('.breadcrumb').after('<div class="warning"><?php echo $text_import_error ?></div>');
			$(window).unbind('beforeunload');
			alert(textStatus+errorThrown);
		},
        type:      'post',
        dataType:  'json',
		data: { 'import': 'true' }
    };

	$(document).ready(function() {
		$('#import_button').bind('click', function(event) {
			event.preventDefault();
			if (confirm("<?php echo $text_import_confirm ?>")) {
			  $('#form').ajaxSubmit(options);
			}
		});
	});

	function printResult()
	{
		var print_win = window.open('','Print');
		var content = '<html><head><title><?php echo $text_import_results; ?></title></head><body>' + $('#results').html() + '</body></html>';
		print_win.document.write(content);
		print_win.print();
		print_win.close();
	}
    </script>
<script type="text/javascript"><!--
new AjaxUpload('#button-upload', {
	action: 'index.php?route=module/csv_import/upload&token=<?php echo $token; ?>',
	name: 'file',
	autoSubmit: true,
	responseType: 'json',
	onSubmit: function(file, extension) {
		$('#button-upload').after('<img src="view/image/loading.gif" class="loading" style="padding-left: 5px;" />');
		$('#button-upload').attr('disabled', true);
	},
	onComplete: function(file, json) {
		$('#button-upload').attr('disabled', false);

		if (json['success']) {
			alert(json['success']);

			$('input[name=\'csv_import_file\']').attr('value', json['filename']);
			//$('input[name=\'mask\']').attr('value', json['mask']);
		}

		if (json['error']) {
			alert(json['error']);
		}

		$('.loading').remove();
	}
});
//--></script>
<script type="text/javascript"><!--
$('#add_import_field').bind('click',function(event) {
	event.preventDefault();
	
	$('#product_field option:selected').each( function(i, element) {
		var option = $(element);
		$('#fields').append('<td title="<?php echo $text_dropped ?>"><p>'+option.text()+'</p><input type="hidden" name="csv_import_fields[]" value="'+option.val()+'" /><img src="view/image/delete.png" class="delete-field" title="<?php echo $button_remove ?>" /></td>');
		
		var unique = ['product_id', 'model', 'sku', 'upc', 'ean',	'jan', 'isbn', 'mpn', 'keyword'];
		if (unique.indexOf(option.val()) >= 0) {
			$('#import_key').append('<option value="'+option.val()+'">'+option.text()+'</option>');
		}
		option.remove();
	});
});

$(document).on('click','.delete-field',function(event) {
	event.preventDefault();
	var parent = $(this).parent();
	var value = parent.find('input[type=hidden]').val();
	var name = parent.find('p:first').text();
	
	$('#product_field').append('<option value="'+value+'">'+name+'</option>');
	$('#import_key option[value='+value+']').remove();
	parent.remove();
});
$(document).ready(function(){
	$( "#fields" ).sortable({
      placeholder: "ui-state-highlight"
    });
    $( "#fields" ).disableSelection();
	$( "#fields"  ).selectable();

	$('input[name=csv_import_report]').on('click', function(){
		if ($(this).is(':checked')) {
			$('input[name=csv_import_email]').removeAttr('disabled');
		} else {
			$('input[name=csv_import_email]').attr('disabled','disabled');
		}
	});

});
var module_row = <?php echo $module_row; ?>;

function addModule() {
	html  = '<tbody id="module-row' + module_row + '">';
	html += '  <tr>';
	html += '    <td class="left"><input type="text" name="csv_import_module[' + module_row + '][limit]" value="5" size="1" /></td>';
	html += '    <td class="left"><input type="text" name="csv_import_module[' + module_row + '][image_width]" value="80" size="3" /> <input type="text" name="csv_import_module[' + module_row + '][image_height]" value="80" size="3" /></td>';
	html += '    <td class="left"><select name="csv_import_module[' + module_row + '][layout_id]">';
	<?php foreach ($layouts as $layout) { ?>
	html += '      <option value="<?php echo $layout['layout_id']; ?>"><?php echo $layout['name']; ?></option>';
	<?php } ?>
	html += '    </select></td>';
	html += '    <td class="left"><select name="csv_import_module[' + module_row + '][position]">';
	html += '      <option value="content_top"><?php echo $text_content_top; ?></option>';
	html += '      <option value="content_bottom"><?php echo $text_content_bottom; ?></option>';
	html += '      <option value="column_left"><?php echo $text_column_left; ?></option>';
	html += '      <option value="column_right"><?php echo $text_column_right; ?></option>';
	html += '    </select></td>';
	html += '    <td class="left"><select name="csv_import_module[' + module_row + '][status]">';
    html += '      <option value="1" selected="selected"><?php echo $text_enabled; ?></option>';
    html += '      <option value="0"><?php echo $text_disabled; ?></option>';
    html += '    </select></td>';
	html += '    <td class="right"><input type="text" name="csv_import_module[' + module_row + '][sort_order]" value="" size="3" /></td>';
	html += '    <td class="left"><a onclick="$(\'#module-row' + module_row + '\').remove();" class="button"><span><?php echo $button_remove; ?></span></a></td>';
	html += '  </tr>';
	html += '</tbody>';

	$('#module tfoot').before(html);

	module_row++;
}
//--></script>
<script type="text/javascript"><!--
$('#tabs a').tabs(); 
//--></script> 
<?php echo $footer; ?>