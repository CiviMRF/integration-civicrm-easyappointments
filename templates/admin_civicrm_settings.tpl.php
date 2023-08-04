<div class="wrap">
<h1><?php _e('CiviCRM Settings', 'integration-civicrm-easyappointments'); ?></h1>
<form method="post">

<?php _e('Submit new appointments to CiviCRM Form Processor', 'integration-civicrm-easyappointments'); ?><br />
<select name="civicrm_ea_form_processor">
  <option value="" <?php if (empty($civicrm_ea_form_processor)) { ?>selected="selected"<?php } ?>><?php _e('Do not submit to CiviCRM', 'integration-civicrm-easyappointments'); ?></option>
  <?php foreach($formProcessors as $value => $label) { ?>
    <option value="<?php echo $value; ?>" <?php if (!empty($civicrm_ea_form_processor) && $civicrm_ea_form_processor == $value) { ?>selected="selected"<?php } ?>><?php echo $label; ?></option>
  <?php } ?>
</select>
<br /><br />
<?php _e('The following fields will be submitted to the form processor', 'integration-civicrm-easyappointments'); ?><br />
<?php echo implode("<br>", $fields); ?>

<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save', 'integration-civicrm-easyappointments'); ?>"  /></p>
</form>
</div>