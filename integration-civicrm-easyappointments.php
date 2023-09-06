<?php
/*
Plugin Name: Integration between Easy Appointments and CiviCRM
Description: Provides an integration between Easy Appointments and CiviCRM. A new appointment is send to a form processor in CiviCRM. You can use this plugin with Connector to CiviCRM with CiviMcRestFace (https://wordpress.org/plugins/connector-civicrm-mcrestface/)
Version:     1.0.1
Author:      Jaap Jansma
License:     AGPL3
License URI: https://www.gnu.org/licenses/agpl-3.0.html
Text Domain: integration-civicrm-easyappointments
*/
/**
 * Copyright (C) 2023  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

defined('ABSPATH') or die("Cannot access pages directly.");
define('INTEGRATION_EASYAPPAOINTMENTS_CIVICRM_ROOT_PATH', plugin_dir_path(__FILE__));

add_action('init', function() {
  require_once INTEGRATION_EASYAPPAOINTMENTS_CIVICRM_ROOT_PATH . "CiviCRM/class.civicrm-api-local.php";
  require_once INTEGRATION_EASYAPPAOINTMENTS_CIVICRM_ROOT_PATH . "CiviCRM/class.civicrm-api-wpcmrf.php";
});

/**
 * Returns a list of possible connection profiles.
 * @return array
 */
function integration_civicrm_easyappointments_get_profiles() {
  static $profiles = null;
  if (is_array($profiles)) {
    return $profiles;
  }

  $profiles = array();
  $profiles = CiviCRMEasyAppointmentsApiLocal::profiles($profiles);
  $profiles = CiviCRMasyAppointmentsApiWpcmrf::profiles($profiles);

  $profiles = apply_filters('integration_civicrm_easyappointments_get_profiles', $profiles);
  return $profiles;
}

function integration_civicrm_easyappointments_api($entity, $action, $params, $options, $profile_id) {
  $profiles = integration_civicrm_easyappointments_get_profiles();
  if (!isset($profiles[$profile_id])) {
    return ['error' => 'Invalid connection', 'is_error' => '1'];
  }
  $func = $profiles[$profile_id]['function'];
  return call_user_func($func, $entity, $action, $params, $options, $profiles[$profile_id]['profile_id']);
}

add_action('ea_new_app', function($id, $appointment, $isFinal) {
  if ($isFinal || (is_admin() && is_user_logged_in())) {
    integration_civicrm_easyappointments_submit_to_form_processor($id, 'new');
  }
}, 10, 3);

add_action('ea_edit_app', function($id) {
  if (is_admin() && is_user_logged_in()) {
    integration_civicrm_easyappointments_submit_to_form_processor($id, 'edit');
  }
}, 10, 1);

add_action('wp_ajax_ea_appointment', function() {
  if (is_admin() && is_user_logged_in()) {
    $method = $_SERVER['REQUEST_METHOD'];

    if (!empty($_REQUEST['_method'])) {
      $method = strtoupper($_REQUEST['_method']);
      unset($_REQUEST['_method']);
    }
    if ($method == 'DELETE') {
      $id = null;
      if (isset($_REQUEST['id'])) {
        $id = $_REQUEST['id'];
      }
      if ($id) {
        integration_civicrm_easyappointments_submit_to_form_processor($id, 'delete');
      }
    }
  }
}, 0);

function integration_civicrm_easyappointments_submit_to_form_processor($appointment_id, $action) {
  global $ea_app;
  $models = $ea_app->get_container()['db_models'];
  $options = $ea_app->get_container()['options'];
  $civicrm_ea_form_processor = $options->get_option_value('civicrm_ea_form_processor_'.$action, '');
  if (!empty($civicrm_ea_form_processor)) {
    $app_data = $models->get_appintment_by_id($appointment_id);
    $options = [];
    $profiles = integration_civicrm_easyappointments_get_profiles();
    foreach($profiles as $profile => $profile_data) {
      $result = integration_civicrm_easyappointments_api('FormProcessorInstance', 'list', [], ['cache' => '180 minutes'], $profile);
      if (isset($result['values'] )) {
        foreach ($result['values'] as $value) {
          if ($profile.'__'.$value['name'] == $civicrm_ea_form_processor) {
            integration_civicrm_easyappointments_api('FormProcessor', $value['name'], $app_data, $options,$profile);
          }
        }
      }
    }
  }
}

function integration_civicrm_easyappointments_settings_page() {
  global $ea_app;
  $formProcessors = [];
  $profiles = integration_civicrm_easyappointments_get_profiles();
  foreach($profiles as $profile => $profile_data) {
    $result = integration_civicrm_easyappointments_api('FormProcessorInstance', 'list', [], ['cache' => '180 minutes'], $profile);
    if (isset($result['values'] )) {
      foreach ($result['values'] as $value) {
        $formProcessors[$profile.'__'.$value['name']] = $profile_data['title'].': '.$value['title'];
      }
    }
  }

  $models = $ea_app->get_container()['db_models'];
  $options = $ea_app->get_container()['options'];
  if (isset($_REQUEST['civicrm_ea_form_processor_new'])) {
    $civicrm_ea_form_processor = sanitize_text_field($_REQUEST['civicrm_ea_form_processor_new']);
    $option['ea_key'] = 'civicrm_ea_form_processor_new';
    $option['ea_value'] = $civicrm_ea_form_processor;
    $models->update_option($option);
  }
  if (isset($_REQUEST['civicrm_ea_form_processor_edit'])) {
    $civicrm_ea_form_processor = sanitize_text_field($_REQUEST['civicrm_ea_form_processor_edit']);
    $option['ea_key'] = 'civicrm_ea_form_processor_edit';
    $option['ea_value'] = $civicrm_ea_form_processor;
    $models->update_option($option);
  }
  if (isset($_REQUEST['civicrm_ea_form_processor_delete'])) {
    $civicrm_ea_form_processor = sanitize_text_field($_REQUEST['civicrm_ea_form_processor_delete']);
    $option['ea_key'] = 'civicrm_ea_form_processor_delete';
    $option['ea_value'] = $civicrm_ea_form_processor;
    $models->update_option($option);
  }

  $civicrm_ea_form_processor_new = $options->get_option_value('civicrm_ea_form_processor_new', '');
  $civicrm_ea_form_processor_edit = $options->get_option_value('civicrm_ea_form_processor_edit', '');
  $civicrm_ea_form_processor_delete = $options->get_option_value('civicrm_ea_form_processor_delete', '');

  $fields = [
    'id',
    'location',
    'service',
    'worker',
    'name',
    'email',
    'phone',
    'date',
    'start',
    'end',
    'end_date',
    'description',
    'status',
    'user',
    'created',
    'price',
    'ip',
    'session',
    'service_name',
    'service_duration',
    'service_price',
    'worker_name',
    'worker_email',
    'worker_phone',
    'location_name',
    'location_address',
    'location_location',
  ];
  $metafields = $models->get_all_rows('ea_meta_fields');
  foreach($metafields as $metafield) {
    if (!in_array($metafield->slug, $fields)) {
      $fields[] = $metafield->slug;
    }
  }
  require_once INTEGRATION_EASYAPPAOINTMENTS_CIVICRM_ROOT_PATH . 'templates/admin_civicrm_settings.tpl.php';
}

if (is_admin()) {
  add_action('admin_menu', function() {
    // locations page
    $integration_civicrm_settings = add_submenu_page(
      'easy_app_top_level',
      __('CiviCRM Settings', 'integration-civicrm-easyappointments'),
       __('CiviCRM Settings', 'integration-civicrm-easyappointments'),
      'manage_options',
      'easy_app_civicrm_settings',
      'integration_civicrm_easyappointments_settings_page'
    );
  });
}