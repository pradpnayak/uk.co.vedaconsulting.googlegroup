<?php

require_once 'googlegroup.civix.php';
require_once 'google-api-php-client/vendor/autoload.php';
set_include_path(get_include_path() . PATH_SEPARATOR . 'google-api-php-client/vendor');

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function googlegroup_civicrm_config(&$config) {
  _googlegroup_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function googlegroup_civicrm_xmlMenu(&$files) {
  _googlegroup_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function googlegroup_civicrm_install() {
  $extensionDir       = dirname( __FILE__ ) . DIRECTORY_SEPARATOR;
  $customDataXMLFile  = $extensionDir . '/xml/auto_install.xml';
  $import = new CRM_Utils_Migrate_Import();
  $import->run($customDataXMLFile);
  _googlegroup_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function googlegroup_civicrm_uninstall() {
  _googlegroup_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function googlegroup_civicrm_enable() {
  _googlegroup_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function googlegroup_civicrm_disable() {
  _googlegroup_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function googlegroup_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _googlegroup_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function googlegroup_civicrm_managed(&$entities) {
  $entities[] = [
    'name' => 'Google Group Sync',
    'module' => 'uk.co.vedaconsulting.googlegroup',
    'entity' => 'Job',
    'update' => 'never',
    'params' => [
      'version' => 3,
      'name' => 'Google Group Sync',
      'description' => 'Sync contacts between CiviCRM and Google Group.',
      'run_frequency' => 'Daily',
      'api_entity' => 'Googlegroup',
      'api_action' => 'sync',
      'parameters' => '',
      'is_active' => FALSE,
    ],
  ];
  _googlegroup_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function googlegroup_civicrm_caseTypes(&$caseTypes) {
  _googlegroup_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function googlegroup_civicrm_angularModules(&$angularModules) {
  _googlegroup_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function googlegroup_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _googlegroup_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_fieldOptions().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_fieldOptions
 *
 */
function googlegroup_civicrm_fieldOptions($entity, $field, &$options, $params) {
  if ('Group' == $entity) {
    $id = str_replace('custom_', '', $field);
    if (!is_numeric($id)) {
      return NULL;
    }
    $customFieldId = civicrm_api3('CustomField', 'getvalue', [
      'return' => "id",
      'name' => "Google_Group",
      'custom_group_id.name' => "Googlegroup_Settings",
    ]);
    if ($customFieldId == $id) {
      $lists = civicrm_api('Googlegroup', 'getgroups', []);
      $options = [];
      if (!empty($lists['values'])) {
        $options = ['' => '- select -'] + $lists['values'];
      }
    }
  }
}

/**
 * Implements hook_civicrm_validateForm().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_validateForm
 *
 */
function googlegroup_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName == 'CRM_Group_Form_Edit' ) {
    if (!empty($fields['google_group'])) {
      $otherGroups = CRM_Googlegroup_Utils::getGroupsToSync($fields['google_group']);
      $thisGroup = $form->getVar('_group');
      if ($thisGroup && $otherGroups) {
        unset($otherGroups[$thisGroup->id]);
      }
      if (!empty($otherGroups)) {
        $otherGroup = reset($otherGroups);
        $errors['google_group'] = ts('There is already a CiviCRM group tracking this Group, called "'
          . $otherGroup['civigroup_title'] . '"');
      }
    }
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
 */
function googlegroup_civicrm_navigationMenu(&$menu) {
  _googlegroup_civix_insert_navigation_menu($menu, 'Administer/System Settings', [
    'label' => ts('Googlegroup Settings', ['domain' => 'uk.co.vedaconsulting.googlegroup']),
    'name' => 'Googlegroup_Settings',
    'url' => CRM_Utils_System::url('civicrm/googlegroup/settings', 'reset=1', TRUE),
    'active' => 1,
    'operator' => NULL,
    'permission' => 'administer CiviCRM',
  ]);
  _googlegroup_civix_insert_navigation_menu($menu, 'Contacts', [
    'label' => ts('Sync Civi Contacts To Googlegroup', ['domain' => 'uk.co.vedaconsulting.googlegroup']),
    'name' => 'Googlegroup_Sync',
    'url' => CRM_Utils_System::url('civicrm/googlegroup/sync', 'reset=1', TRUE),
    'active' => 1,
    'operator' => NULL,
    'permission' => 'administer CiviCRM',
  ]);
}

/**
 * Implements hook_civicrm_alterSettingsMetaData().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsMetaData
 *
 */
function googlegroup_civicrm_alterSettingsMetaData(&$settingsMetadata, $domainID, $profile) {
  $settingsMetadata['cg_client_key'] = [
    'group_name' => 'Googlegroup Preferences',
    'group' => 'core',
    'name' => 'cg_client_key',
    'type' => 'String',
    'html_type' => 'text',
    'quick_form_type' => 'Element',
    'default' => '',
    'add' => '4.7',
    'title' => ts('Client Key'),
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => '',
    'help_text' => '',
    'html_attributes' => [
      'size' => 48,
    ],
  ];
  $settingsMetadata['cg_client_secret'] = [
    'group_name' => 'Googlegroup Preferences',
    'group' => 'core',
    'name' => 'cg_client_secret',
    'type' => 'String',
    'html_type' => 'text',
    'quick_form_type' => 'Element',
    'default' => '',
    'add' => '4.7',
    'title' => ts('Client Secret'),
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => '',
    'help_text' => '',
    'html_attributes' => [
      'size' => 48,
    ],
  ];
  $settingsMetadata['cg_domain_name'] = [
    'group_name' => 'Googlegroup Preferences',
    'group' => 'core',
    'name' => 'cg_domain_name',
    'type' => 'String',
    'html_type' => 'text',
    'quick_form_type' => 'Element',
    'default' => '',
    'add' => '4.7',
    'title' => ts('Domain names'),
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => '',
    'help_text' => '',
    'html_attributes' => [
      'size' => 48,
    ],
  ];
  $settingsMetadata['cg_access_token'] = [
    'group_name' => 'Googlegroup Preferences',
    'group' => 'core',
    'name' => 'cg_access_token',
    'type' => 'String',
    'html_type' => 'hidden',
    'quick_form_type' => 'Element',
    'default' => '',
    'add' => '4.7',
    'title' => ts('Access Token'),
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => '',
    'help_text' => '',
    'html_attributes' => [
      'size' => 48,
    ],
  ];
}
