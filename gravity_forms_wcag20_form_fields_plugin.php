<?php
/*
Plugin Name: Gravity Forms - WCAG 2.0 form fields
Description: Extends the Gravity Forms plugin. Modifies radio, checkbox and repeater list fields so that they meet WCAG 2.0 accessibility requirements.
Version: 1.2.9
Author: Adrian Gordon
Author URI: http://www.itsupportguides.com 
License: GPL2
Text Domain: gfwcag
*/

add_action('admin_notices', array('ITSP_GF_WCAG20_Form_Fields', 'admin_warnings'), 20);
load_plugin_textdomain( 'gfwcag', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

if (!class_exists('ITSP_GF_WCAG20_Form_Fields')) {
    class ITSP_GF_WCAG20_Form_Fields
    {
        /**
         * Construct the plugin object
         */
		 public function __construct()
        {		
            // register actions
            if (self::is_gravityforms_installed()) {
                //start plug in
                add_filter('gform_column_input_content',  array(&$this,'change_column_add_title_wcag'), 10, 6);
				add_filter('gform_field_content',  array(&$this,'change_fields_content_wcag'), 10, 5);
				add_action('gform_enqueue_scripts',  array(&$this,'queue_scripts'), 90, 3);
				add_filter('gform_tabindex', create_function('', 'return false;'));   //disable tab-index
				
				add_filter('gform_validation_message', array(&$this,'change_validation_message'), 10, 2);
				
				//add_filter('gform_pre_render', array(&$this,'set_save_continue_button')); // TO DO: currently customising Gravity Forms code, need to implement in this plugin.
            }
        } // END __construct
		
		/**
         * Replaces default 'Save and continue' link with a button 
         */
		function set_save_continue_button($form){
			$form['save']['button']['type'] = 'text';
			return $form;
		}
		
		public static function change_validation_message($message, $form){
			$referrer = $_SERVER['HTTP_REFERER'];
	
			foreach ( $form['fields'] as $field ) {
				$failed[] = rgget("failed_validation", $field);
				
				$failed_field = rgget("failed_validation", $field);
				$failed_message = rgget("validation_message", $field);
				if ( $failed_field == 1) {
				
				$error .= '<li><a href="'.$referrer.'#field_'.$form['id'].'_'.$field['id'].'">'.$field[label].' - '.(( "" == $field[errorMessage]) ? $failed_message:$field[errorMessage]).'</a></li>';

				}
				
			}
			
			$length  = count( array_keys( $failed, "true" ));
			$prompt  = sprintf( _n( "There was %s error found in the information you submitted", "There were %s errors found in the information you submitted", $length, 'gfwcag' ), $length );
			
			add_action('wp_footer', array('ITSP_GF_WCAG20_Form_Fields','change_validation_message_js_script'));
			
			$message .= "<div id='error' aria-live='assertive' role='alert'>";
			$message .= "<div class='validation_error' tabindex='-1'>";
			$message .= $prompt;
			$message .= "</div>";
			$message .= "<ol class='validation_list'>";
			$message .= $error;
			$message .= "</ol>";
			$message .= "</div>";
			return $message;
		}
		
		public function change_validation_message_js_script() {
		
		?>
			<script type='text/javascript'>
				(function ($) {
					'use strict';
					$(function () {
						//$(document).bind('gform_post_render', function(){
						//	window.setTimeout(function(){
								window.location.hash = '#error';
								$(this).find('.validation_error').focus();
								$(this).scrollTop($('.validation_error').offset().top);
							}, 500);
						//});
					//});
				}(jQuery));	
				</script>
		<?php
		}

		/**
         * Replaces field content for repeater lists - adds title to input fields using the column title
         */
		public static function change_column_add_title_wcag($input, $input_info, $field, $text, $value, $form_id) {
		if (!is_admin()) {
			$input = str_replace("<input ","<input title='".$text."'",$input);
		}
		return $input;
		} // END change_column_add_title_wcag
		
		/*
         * Replaces field content with WCAG 2.0 compliant fieldset, rather than the default orphaned labels - applied to checkboxes, radio lists and repeater lists
         */
		public static function change_fields_content_wcag($content, $field, $value, $lead_id, $form_id){
			if (!is_admin()) {
			$field_type = rgar($field,"type");
			$field_required = rgar($field,"isRequired");
			$field_failed_valid = rgar($field,"failed_validation");
			$field_label = rgar($field,"label");
			$field_id = rgar($field,"id");
			$field_page = rgar($field,"pageNumber");
			$current_page = GFFormDisplay::get_current_page( $form_id );
			$field_description = rgar($field,"description");
			$field_maxFileSize = rgar($field,"maxFileSize");
			$field_allowedExtensions = rgar($field,"allowedExtensions");
			
			// adds labels to radio 'other' field - both the radio and input fields.
			if("radio" == $field_type ) {
				foreach($field['choices'] as $key=>$choice){
					if (true == $choice[isOtherChoice]) {
						$choice_position = $key;
						// add label to radio
						$content = str_replace("<li class='gchoice_".$form_id."_".$field_id."_".$choice_position."'><input name='input_".$field_id."' ","<li class='gchoice_".$form_id."_".$field_id."_".$choice_position."'><label id='label_".$form_id."_".$field_id."_".$choice_position."' for='choice_".$form_id."_".$field_id."_".$choice_position."' class='sr-only'>".__(' Other ','gfwcag')."</label><input name='input_".$field_id."' ",$content);
						// add label to text input
						$content = str_replace("<input id='input_".$form_id."_".$field_id."_other' ","<label id='label_".$form_id."_".$field_id."_other' for='input_".$form_id."_".$field_id."_other' class='sr-only'>".__(' Other ','gfwcag')."</label><input id='input_".$form_id."_".$field_id."_other' ",$content);
						// change radio jQuery
						$content = str_replace("jQuery(this).next('input').focus()","jQuery(this).closest('li').find('#input_43_1_other').focus()",$content);
						// change inout jQuery - NOTE Gravity Forms code uses double quotation mark 
						$content = str_replace("jQuery(this).prev(\"input\").attr(\"checked\", true)","jQuery(this).closest(\"li\").find(\"#choice_43_1_3\").attr(\"checked\", true)",$content);
					}
				}
			}
			
			// wrap single fileupload file field in fieldset
			// adds aria-required='true' if required field
			if("fileupload" == $field_type ) {
					if ( true == $field_required ) {
						// Gravity Forms 1.9.2 appears to no longer include for attribute on field group labels 
						// for='input_".$form_id."_".$field_id."'
						$content = str_replace("<label class='gfield_label' for='input_".$form_id."_".$field_id."' >".$field_label."<span class='gfield_required'>*</span></label>","<fieldset aria-required='true' class='gfieldset'><legend class='gfield_label'><label class='gfield_label' for='input_".$form_id."_".$field_id."' >".$field_label."<span class='gfield_required'>*</span><span class='sr-only'> ".__(' File upload ','gfwcag')."</span></label></legend>",$content);
					} else {
						$content = str_replace("<label class='gfield_label' for='input_".$form_id."_".$field_id."' >".$field_label."</label>","<fieldset class='gfieldset'><legend class='gfield_label'><label class='gfield_label' for='input_".$form_id."_".$field_id."' >".$field_label."<span class='sr-only'> ".__(' File upload ','gfwcag')."</span></label></legend>",$content);
					}
					$content .= "</fieldset>";
			}
			
			//radio and checkbox fields in fieldset
			// adds aria-required='true' if required field
			if( ("checkbox" == $field_type ) || ("radio" == $field_type) || ("fileupload" == $field_type)){
			//wrap in fieldset
				if ( true == $field_required ) {
					// Gravity Forms 1.9.2 appears to no longer include for attribute on field group labels 
					// for='input_".$form_id."_".$field_id."'
					$content = str_replace("<label class='gfield_label'  >".$field_label."<span class='gfield_required'>*</span></label>","<fieldset aria-required='true' class='gfieldset'><legend class='gfield_label'>".$field_label."<span class='gfield_required'>*</span></legend>",$content);
				} else {
					$content = str_replace("<label class='gfield_label'  >".$field_label."</label>","<fieldset class='gfieldset'><legend class='gfield_label'>".$field_label."</legend>",$content);
				}
				$content .= "</fieldset>";
			}
			
			if(("list" == $field_type ) ){
				$maxRow = intval(rgar($field, "maxRows"));
				
				//wrap list fields in fieldset
				$content = str_replace("<label class='gfield_label' for='input_".$form_id."_".$field_id."_shim' >".$field_label."</label>","<fieldset class='gfieldset'><legend class='gfield_label'>".$field_label."</legend>",$content);
				$content .= "</fieldset>";
				
				//remove shim input 
				$content = str_replace("<input type='text' id='input_".$form_id."_".$field_id."_shim' style='position:absolute;left:-999em;' onfocus='jQuery( \"#field_".$form_id."_".$field_id." table tr td:first-child input\" ).focus();' />","",$content);
				
				//replace 'add another row' image with button
				$add_row = _x( 'Add a row', 'String must have same translation as found in Gravity Forms', 'gfwcag' );
				$content = str_replace("<img src='".GFCommon::get_base_url()."/images/blankspace.png' class='add_list_item '  title='Add another row' alt='$add_row' onclick='gformAddListItem(this, ".$maxRow.")' style='cursor:pointer; margin:0 3px;' />","<button type='button' class='add_list_item'  title='$add_row' alt='$add_row' onclick='gformAddListItem(this, ".$maxRow.")'></button>",$content);
				
				//replace 'remove this row' image with button - if field is visible 
				// removew row 
				$remove_row = _x( 'Remove this row', 'String must have same translation as found in Gravity Forms', 'gfwcag' );
				$content = str_replace("<img src='".GFCommon::get_base_url()."/images/blankspace.png'  title='Remove this row' alt='$remove_row' class='delete_list_item' style='cursor:pointer; ' onclick='gformDeleteListItem(this, ".$maxRow.")' />","<button type='button' class='delete_list_item' title='$remove_row' alt='$remove_row' onclick='gformDeleteListItem(this, ".$maxRow.")'></button>",$content);
				
				//replace 'remove this row' image with button - if field is hidden 
				$content = str_replace("<img src='".GFCommon::get_base_url()."/images/blankspace.png'  title='$remove_row' alt='$remove_row' class='delete_list_item' style='cursor:pointer; visibility:hidden;' onclick='gformDeleteListItem(this, ".$maxRow.")' />","<button style='visibility:hidden;' type='button' class='delete_list_item'  title='$remove_row' alt='$remove_row' onclick='gformDeleteListItem(this, ".$maxRow.")'></button>",$content);
			}
			
			// add description for date field 
			if("date" == $field_type ){
				if ( 'mdy' == $field["dateFormat"]) {
					$date_format = 'mm/dd/yyyy';
				} else if ( 'dmy' == $field["dateFormat"]) {
					$date_format = 'dd/mm/yyyy';
				} else if ( 'dmy_dash' == $field["dateFormat"]) {
					$date_format = 'dd-mm-yyyy';
				} else if ( 'dmy_dot' == $field["dateFormat"]) {
					$date_format = 'dd.mm.yyyy';
				} else if ( 'ymd_slash' == $field["dateFormat"]) {
					$date_format = 'yyyy/mm/dd';
				} else if ( 'ymd_dash' == $field["dateFormat"]) {
					$date_format = 'yyyy-mm-dd';
				} else if ( 'ymd_dot' == $field["dateFormat"]) {
					$date_format = 'yyyy.mm.dd';
				} 
				
				$content = str_replace("<label class='gfield_label'  >".$field_label,"<label class='gfield_label' for='input_".$form_id."_".$field_id."' >".$field_label." <span id='field_".$form_id."_".$field_id."_dmessage' class='sr-only'> - " . sprintf( __( 'must be %s format', 'gfwcag' ), $date_format ) . "</span>",$content );
				
				// attach to aria-described-by
				$content = str_replace(" name='input_"," aria-describedby='field_".$form_id."_".$field_id."_dmessage' name='input_",$content);
			}
			
			// add description for website field 
			if ("website" == $field_type ){
				$content = str_replace("<label class='gfield_label' for='input_".$form_id."_".$field_id."' >".$field_label,"<label class='gfield_label' for='input_".$form_id."_".$field_id."' >".$field_label." <span id='field_".$form_id."_".$field_id."_dmessage' class='sr-only'> - ". __( 'enter a valid website URL for example http://www.google.com', 'gfwcag' ) ."</span>",$content);
				
				// attach to aria-described-by
				$content = str_replace(" name='input_"," aria-describedby='field_".$form_id."_".$field_id."_dmessage' name='input_",$content);
			}
			
			//validation for fields in page 
			if ($current_page == $field_page) {
			
			
			
				//if field has failed validation
					if(true == $field_failed_valid ){
					//add add aria-invalid='true' attribute to input
					$content = str_replace(" name='input_"," aria-invalid='true' name='input_",$content);
					//if aria-describedby attribute not already present
					if (strpos(strtolower($content),'aria-describedby') !== false)  {
						$content = str_replace(" aria-describedby='"," aria-describedby='field_".$form_id."_".$field_id."_vmessage ",$content);
					} else { 
						// aria-describedby attribute is already present
						$content = str_replace(" name='input_"," aria-describedby='field_".$form_id."_".$field_id."_vmessage' name='input_",$content);
					}
					//add add class for aria-describedby error message
					$content = str_replace(" class='gfield_description validation_message'"," class='gfield_description validation_message' id='field_".$form_id."_".$field_id."_vmessage'",$content);
				}
			
				//if field is required
				if(true == $field_required ){
					//if HTML required attribute not already present
					// COMMENTED OUT in version 1.2.6 until I can resolve issues are resolved with it prompting 'required' when field has been filled out correctly.
					// aria-required=true still working and seems to provide broader support for assistive technology
					/*	if (( strpos(strtolower($content),'required') !== true ) && ("checkbox" != $field_type ) )  {
						//add HTML5 required attribute
						$content = str_replace(" name='input_"," required name='input_",$content);
					} */
					if ( (strpos(strtolower($content),"aria-required='true'") !== true) && ("checkbox" != $field_type ) && ("radio" != $field_type ) )  {
						//add aria-required='true'
						$content = str_replace(" name='input_"," aria-required='true' name='input_",$content);
					}
					//add screen reader only 'Required' message to asterisk
					$content = str_replace("*</span>"," * <span class='sr-only'> ".__('Required','gfwcag')."</span></span>",$content);
				}
				
				if(!empty($field_description) && "Infobox" != $field_type){
				// if field has a description, link description to field using aria-describedby
					// dont apply to validation message - it already has an ID
					//if (strpos(strtolower($content),'_vmessage') !== true)  {
						//if aria-describedby attribute not already present
						if (strpos(strtolower($content),'aria-describedby') !== false)  {
							$content = str_replace(" aria-describedby='"," aria-describedby='field_".$form_id."_".$field_id."_dmessage ",$content);
						} else { 
							// aria-describedby attribute is already present
							$content = str_replace(" name='input_"," aria-describedby='field_".$form_id."_".$field_id."_dmessage' name='input_",$content);
						}
						//add add class for aria-describedby description message
						$content = str_replace(" class='gfield_description'"," id='field_".$form_id."_".$field_id."_dmessage' class='gfield_description'",$content);
					//}
				}
				
				if("fileupload" == $field_type ) {
					if(!empty($field_maxFileSize)){
						// turn max file size to human understandable term
						$file_limit = $field_maxFileSize. ' mega bytes';
					}
					if(!empty($field_allowedExtensions)){
						// add accept attribute with comma separated list of accept file types
						$content = str_replace(" type='file' "," type='file' accept='".$field_allowedExtensions."'",$content);
						// turn allowed extensions into a human understandable list - remove commas and replace with spaces
						$extensions_list = str_replace(","," ",$field_allowedExtensions);
					}
					
					// only add if either max file size of extension limit specified for field
					if(!empty($field_maxFileSize) || !empty($field_allowedExtensions) ) {
						//add title attirbute to file input field
							$content = str_replace(" type='file' "," type='file' title='".$field_label."' ",$content);
						//if aria-describedby attribute not already present
						if (strpos(strtolower($content),'aria-describedby') !== false)  {
							$content = str_replace(" aria-describedby='"," aria-describedby='field_".$form_id."_".$field_id."_fmessage ",$content);
						} else { 
						// aria-describedby attribute is already present
							$content = str_replace(" name='input_"," aria-describedby='field_".$form_id."_".$field_id."_fmessage' name='input_",$content);
						}
						$content .= "<span id='field_".$form_id."_".$field_id."_fmessage' class='sr-only'>";
						if(!empty($field_maxFileSize)) {
							$content .= "Maximum file size - ".$file_limit.". ";
						}
						if(!empty($field_allowedExtensions)) {
							$content .= "Allowed file extensions - ".$extensions_list.". ";
						}
						$content .= "</span>";
					}
				}
			}
			
		}
		return $content;
		} // END change_fields_content_wcag
		
		/*
         * Enqueue styles and scripts.
         */
		public function queue_scripts($form, $is_ajax) {
			if ( !is_admin() ) {
				//add_action( 'wp_enqueue_scripts', array( &$this,'css_styles' ) );
				wp_enqueue_style( 'gfwcag-css', plugins_url( 'gf_wcag20_form_fields.css', __FILE__ ) );
				add_action('wp_footer', array('ITSP_GF_WCAG20_Form_Fields','queue_scripts_js_script'));
			}
		}  // END queue_scripts
		
		/*
         * Looks for links in form body (in descriptions, HTML fields etc.) 
		 * changes them to open in a new window and adds/appends 
		 * 'this link will open in a new window' to title for screen reader users.
         */
		 public function queue_scripts_js_script() {
		 ?>
			<script type='text/javascript'>
			(function ($) {
					'use strict';
					$(function () {
						$('.gform_body a').not('.target-self').each(function() {
						//get the current title
						var title = $(this).attr('title');
						//if title doesnt exist or is empty, add line otherwise append it
							if (title == undefined || title == '') {
									$(this).attr('target', '_blank').attr('title', '<?php echo __('this link will open in a new window','gfwcag') ?>');
								} else {
									$(this).attr('target', '_blank').attr('title', title + ' <?php echo __('- this link will open in a new window','gfwcag') ?>');
							}
						});
					});
				}(jQuery));	
			</script> <?php
			
		} // END queue_scripts_js_script
		
		/*
         * CSS styles - remove border, margin and padding from fieldset
         */
		public static function css_styles() {
			wp_enqueue_style( 'gfwcag-css', plugins_url( 'gf_wcag20_form_fields.css', __FILE__ ) );
		}
		
		/*
         * Warning message if Gravity Forms is not installed and enabled
         */
		public static function admin_warnings() {
			if ( !self::is_gravityforms_installed() ) {
				$message = printf( __( 'The plugin %1$s requires Gravity Forms to be installed.', 'gfwcag' ), self::$name );
				$message .= "<br />";
				$message .= printf( __( 'Please <a href="%s">download the latest version</a> of Gravity Forms and try again.', 'gfwcag' ), "http://www.gravityforms.com" );
			} else {
				return;
			}
			?>
			<div class="error">
				<p>
					<?php echo $message; ?>
				</p>
			</div>
			<?php
		}
		
		/*
         * Check if GF is installed
         */
        private static function is_gravityforms_installed()
        {
            return class_exists('GFAPI');
        } // END is_gravityforms_installed
	}
    $ITSP_GF_WCAG20_Form_Fields = new ITSP_GF_WCAG20_Form_Fields();
}
