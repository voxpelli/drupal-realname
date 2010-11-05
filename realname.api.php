<?php
// $Id$

/**
 * @file
 * Hooks provided by the RealName module.
 */

/**
 * @addtogroup hooks
 * @{
 */

 /**
  * Define RealName support and options.
  *
  * This hook enables modules to register RealName support.
  *
  * @return
  *   An array of options that the module support. The item is an associative array
  *   that may contain the following key-value pairs:
  *   - "name": Required. The name of the module.
  *   - "types": Whether the module supports types.
  *   - "fields": Whether the module uses fields.
  *   - "file": A file that will be included before the module is used by RealName;
  *     this allows callback functions to be in separate files. The file should
  *     be relative to the implementing module's directory unless otherwise
  *     specified by the "file path" option.
  *   - "file path": The path to the folder containing the file specified in
  *     "file". This defaults to the path to the module implementing the hook.
  */
function hook_realname() {
  return array(
    'name'   => 'Content Profile',
    'types'  => TRUE,
    'fields' => TRUE,
    'file'   => 'realname_content_profile.inc',
    'path'   => drupal_get_path('module', 'realname'),
  );
}

/**
 * Loads the profile fields.
 *
 * @param $account
 *   An user object.
 * @param $type
 *   The type used - if supported by the module.
 */
function hook_load_profile(&$account, $type = NULL) {
  $profile = content_profile_load($type, $account->uid);
  if (!$profile) {
    return;
  }
  $fields = content_fields(NULL, $type);
  foreach ($fields as $field_name => $field_attributes) {
    if (isset($profile->$field_name)) {
      $values = array();
      $contents = $profile->$field_name;
      foreach ($contents as $content) {
        if (isset($content['value'])) {
          $values[] = $content['value'];
        }
        else {
          $values[] = content_format($field_name, $content);
        }
      }
      if (empty($account->{$field_name})) {
        switch (count($values)) {
          case 0:
            $account->{$field_name} = NULL;
            break;
          case 1:
            $account->{$field_name} = $values[0];
            break;
          default:
            $account->{$field_name} = $values;
        }
      }
    }
  }
}

/**
 * Gets available types.
 *
 * Supplies the types supported by the module if the module supports types.
 *
 * @return
 *   An array keyed by type with info.
 */
function hook_realname_get_types() {
  return content_profile_get_types('names');
}

/**
 * Gets available fields.
 *
 * Supplies the fields supported by the module if the module supports fields.
 *
 * @param $current
 *   An array with the currently used fields keyed by field.
 * @param $type
 *   An array containing the current type.
 * @return
 *   An associative array with two keys:
 *   - "fields": Required. An array keyed by field name of fields as
 *     associative arrays containing:
 *     - "title": The title of the field
 *     - "weight": The weight of the field
 *     - "selected": A boolean value of whether the field is slected or not
 *   - "links": Required. An array of field labels keyed by field name
 */
function hook_realname_get_fields($current, $type = NULL) {
  $fields = $links = array();
  $all_fields = content_fields(NULL, $type);
  if ($all_fields) {
    foreach ($all_fields as $field_name => $field_attributes) {
      // If it's not they type we are looking for, then skip the field.
      if ($field_attributes['type_name'] != $type) {
        continue;
      }
      switch ($field_attributes['type']) {
        case 'text':
          if ($field_attributes['multiple']) {
            drupal_set_message(t('The RealName module does not currently support fields with multiple values, such as @fld.', array('@fld' => $field_name)), 'warning');
          }
          else {
            $selected = array_key_exists($field_name, $current);
            $fields[$field_name] = array(
              'title' => $field_attributes['widget']['label'],
              'weight' => $selected ? $current[$field_name] : 0,
              'selected' => $selected,
              );
          }
          break;

        case 'link':
          $links[$field_name] = $field_attributes['widget']['label'];
      }
    }
  }
  else {
    drupal_set_message(t('The !type content type has no fields to use.', array('!type' => $type)), 'error');
  }

  if (variable_get('realname_use_title', FALSE)) {
    $fields['title'] = array(
      'title' => t('Node title'),
      'weight' => isset($current['title']) ? $current['title']['weight'] : 0,
      'selected' => array_key_exists('title', $current),
      );
  }

  return array('fields' => $fields, 'links' => $links);
}

/**
 * Gets a name for a user
 *
 * Modules not supporting fields for name data has to
 * implement this hook instead
 *
 * @param $account
 *   An user object.
 * @return
 *   A string with the name or NULL.
 */
function hook_realname_make($account) {
  $info = _connector_information_fetch($account);
  return !empty($info['real name']) ? $info['real name'] : NULL;
}