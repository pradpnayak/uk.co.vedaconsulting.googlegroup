<?php

class CRM_Googlegroup_Utils {

  /**
   * Function to connect Google.
   *
   * @return obj
   * @access public
   */
  static function googleClient() {
    $clientKey = CRM_Googlegroup_Utils::getSettingValue('cg_client_key');
    $secretKey = CRM_Googlegroup_Utils::getSettingValue('cg_client_secret');
    $client = new Google_Client();
    $client->setClientId($clientKey);
    $client->setClientSecret($secretKey);
    $client->setAccessType('offline');
    $client->setApprovalPrompt('force');
    $client->addScope(Google_Service_Directory::ADMIN_DIRECTORY_GROUP);
    return $client;
  }

  /**
   * Function to get Groups to sync.
   *
   * @param int $gc_group_id
   *
   * @return array
   * @access public
   */
  static function getGroupsToSync($gc_group_id = NULL) {
    $params = $groups = [];
    $whereClause = "gc_group_id IS NOT NULL AND gc_group_id <> ''";

    if ($gc_group_id) {
      // just want results for a particular google.
      $whereClause .= " AND gc_group_id = %1 ";
      $params[1] = [$gc_group_id, 'String'];
    }
    $query  = "
      SELECT entity_id, gc_group_id, cg.title as civigroup_title, cg.saved_search_id, cg.children
      FROM civicrm_value_googlegroup_settings mcs
        INNER JOIN civicrm_group cg ON mcs.entity_id = cg.id
      WHERE $whereClause";
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    while ($dao->fetch()) {
      $groups[$dao->entity_id] = [
        'group_id' => $dao->gc_group_id,
        'civigroup_title' => $dao->civigroup_title,
        'civigroup_uses_cache' => (bool) (($dao->saved_search_id > 0) || (bool) $dao->children),
      ];
    }
    return $groups;
  }

  /**
   * Function to get Group detai.
   *
   * @param int $groupID
   * @param int $start
   *
   * @return array
   * @access public
   */
  static function getGroupContactObject($groupID, $start = NULL) {
    $group = new CRM_Contact_DAO_Group();
    $group->id = $groupID;
    $group->find();

    if ($group->fetch()) {
      //Check smart groups (including parent groups, which function as smart groups).
      if ($group->saved_search_id || $group->children) {
        $groupContactCache = new CRM_Contact_BAO_GroupContactCache();
        $groupContactCache->group_id = $groupID;
        if ($start !== NULL) {
          $groupContactCache->limit($start, CRM_Googlegroup_Form_Sync::BATCH_COUNT);
        }
        $groupContactCache->find();
        return $groupContactCache;
      }
      else {
        $groupContact = new CRM_Contact_BAO_GroupContact();
        $groupContact->group_id = $groupID;
        $groupContact->whereAdd("status = 'Added'");
        if ($start !== NULL) {
          $groupContact->limit($start, CRM_Googlegroup_Form_Sync::BATCH_COUNT);
        }
        $groupContact->find();
        return $groupContact;
      }
    }
    return FALSE;
  }

  /**
   * Function retrieve values from civicrm_setting using api.
   *
   * @param string $settingName
   */
  public static function getSettingValue($settingName) {
    try {
      $setting = civicrm_api3('Setting', 'getvalue', [
        'name' => $settingName,
      ]);
      if ($settingName == 'cg_domain_name') {
        return explode(',', $setting);
      }
      return $setting;
    }
    catch (CiviCRM_API3_Exception $e) {
      return NULL;
    }
  }

}
