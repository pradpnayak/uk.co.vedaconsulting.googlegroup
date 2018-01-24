<?php
/**
 * @file
 * This provides the Sync Push from CiviCRM to Google.
 */

class CRM_Googlegroup_Form_Sync extends CRM_Core_Form {

  const QUEUE_NAME = 'gg-sync';
  const END_URL    = 'civicrm/googlegroup/sync';
  const END_PARAMS = 'state=done';
  const BATCH_COUNT = 10;

  /**
   * Function to pre processing
   *
   * @return None
   * @access public
   */
  function preProcess() {
    $state = CRM_Utils_Request::retrieve('state', 'String', CRM_Core_DAO::$_nullObject, FALSE, 'tmp', 'GET');
    if ($state == 'done') {
      $stats = CRM_Googlegroup_Utils::getSettingValue('cg_push_stats');
      $groups = CRM_Googlegroup_Utils::getGroupsToSync();
      if (!$groups) {
        return;
      }
      $output_stats = [];
      foreach ($groups as $group_id => $details) {
        $group_stats = $stats[$details['group_id']];
        $output_stats[] = [
          'name' => $details['civigroup_title'],
          'stats' => $group_stats,
        ];
      }
      $this->assign('stats', $output_stats);
    }
  }

  /**
   * Function to actually build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    // Create the Submit Button.
    $buttons = [
      [
        'type' => 'submit',
        'name' => ts('Sync Contacts'),
      ],
    ];

    // Add the Buttons.
    $this->addButtons($buttons);
  }

  /**
   * Function to process the form
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    $runner = self::getRunner();
    if ($runner) {
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    }
    else {
      CRM_Core_Session::setStatus(ts('Nothing to sync. Make Google Group settings are configured for the groups with enough members.'));
    }
  }

  /**
   * Function to get runner.
   *
   * @access public
   *
   * @return obj
   */
  static function getRunner($skipEndUrl = FALSE) {
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create([
      'name'  => self::QUEUE_NAME,
      'type'  => 'Sql',
      'reset' => TRUE,
    ]);

    // reset push stats
    CRM_Core_BAO_Setting::setItem([], CRM_Googlegroup_Form_Setting::GG_SETTING_GROUP, 'cg_push_stats');

    // We need to process one list at a time.
    $groups = CRM_Googlegroup_Utils::getGroupsToSync();
    if (!$groups) {
      // Nothing to do.
      return FALSE;
    }

    // Each list is a task.
    $groupCount = 1;
    foreach ($groups as $group_id => $details) {
      $identifier = "Group" . $groupCount++ . " " . $details['civigroup_title'];
      $task  = new CRM_Queue_Task(
        ['CRM_Googlegroup_Form_Sync', 'syncPushList'],
        [$details['group_id'], $identifier],
        "Preparing queue for $identifier"
      );
      // Add the Task to the Queue
      $queue->createItem($task);
    }

    // Setup the Runner
		$runnerParams = [
      'title' => ts('Googlegroup Sync: CiviCRM to Googlegroup'),
      'queue' => $queue,
      'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
      'onEndUrl' => CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE),
    ];
		// Skip End URL to prevent redirect
		// if calling from cron job
		if ($skipEndUrl == TRUE) {
			unset($runnerParams['onEndUrl']);
		}
    $runner = new CRM_Queue_Runner($runnerParams);
    return $runner;
  }

  /**
   * Set up (sub)queue for syncing a Google Group.
   */
  static function syncPushList(CRM_Queue_TaskContext $ctx, $groupID, $identifier) {

    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Googlegroup_Form_Sync', 'syncPushCollectGoogleGroups'],
      [$groupID],
      "$identifier: Fetched data from Google Groups"
    ));

    // Add the CiviCRM collect data task to the queue
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Googlegroup_Form_Sync', 'syncPushCollectCiviCRM'],
      [$groupID],
      "$identifier: Fetched data from CiviCRM"
    ));

    // Add the removals task to the queue
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Googlegroup_Form_Sync', 'syncPushRemove'],
      [$groupID],
      "$identifier: Removed those who should no longer be subscribed"
    ));

    // Add the batchUpdate to the queue
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Googlegroup_Form_Sync', 'syncPushAdd'],
      [$groupID],
      "$identifier: Added new subscribers and updating existing data changes"
    ));

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Collect Google groups data into temporary working table.
   */
  static function syncPushCollectGoogleGroups(CRM_Queue_TaskContext $ctx, $groupID) {

    $stats[$groupID]['gg_count'] = static::syncCollectGoogle($groupID);
    static::updatePushStats($stats);

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Collect CiviCRM data into temporary working table.
   */
  static function syncPushCollectCiviCRM(CRM_Queue_TaskContext $ctx, $groupID) {

    //$stats[$listID]['c_count'] = static::syncCollectCiviCRM($listID);
    $stats[$groupID]['c_count'] = static::syncCollectCiviCRM($groupID);
    static::updatePushStats($stats);
    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Unsubscribe contacts that are subscribed at Google but not in our list.
   */
  static function syncPushRemove(CRM_Queue_TaskContext $ctx, $groupID) {

    $dao = CRM_Core_DAO::executeQuery(
      "SELECT g.email, g.euid
       FROM tmp_googlegroup_push_g g
       WHERE NOT EXISTS (
         SELECT email FROM tmp_googlegroup_push_c c WHERE c.email = g.email
       );");

    $batch = [];
    $stats[$groupID]['removed'] = 0;
    while ($dao->fetch()) {
      $batch[] = $dao->email;
      $stats[$groupID]['removed']++;
    }
    if (!$batch) {
      // Nothing to do
      return CRM_Queue_Task::TASK_SUCCESS;
    }
    // Log the batch unsubscribe details
    CRM_Core_Error::debug_var( 'Google Group batchUnsubscribe $batch= ', $batch);
    $results = civicrm_api3('Googlegroup', 'deletemember', [
      'group_id' => $groupID,
      'member' => $batch,
    ]);
    // Finally we can delete the emails that we just processed from the google temp table.
    CRM_Core_DAO::executeQuery(
      "DELETE FROM tmp_googlegroup_push_g
       WHERE NOT EXISTS (
         SELECT email FROM tmp_googlegroup_push_c c WHERE c.email = tmp_googlegroup_push_g.email
       );");

    static::updatePushStats($stats);
    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Batch update google with new contacts that need to be subscribed, or have changed data.
   *
   * This also does the clean-up tasks of removing the temporary tables.
   */
  static function syncPushAdd(CRM_Queue_TaskContext $ctx, $groupID) {

    // To avoid 'already member exists' error thrown, so remove contacts alreay in Google from civi temp table
    CRM_Core_DAO::executeQuery(
      "DELETE FROM tmp_googlegroup_push_c
       WHERE EXISTS (
         SELECT email FROM tmp_googlegroup_push_g g WHERE g.email = tmp_googlegroup_push_c.email
       );");

    $dao = CRM_Core_DAO::executeQuery( "SELECT * FROM tmp_googlegroup_push_c;");
    // Loop the $dao object to make a list of emails to subscribe/update
    $batch = [];
    while ($dao->fetch()) {
      $batch[] = $dao->email;
      $stats[$groupID]['added']++;
    }
    if (!$batch) {
      // Nothing to do
      return CRM_Queue_Task::TASK_SUCCESS;
    }
    $results = civicrm_api3('Googlegroup', 'subscribe', [
      'group_id' => $groupID,
      'emails' => $batch,
      'role' => 'MEMBER',
    ]);

    // Log the batch subscribe details
    CRM_Core_Error::debug_var( 'Google Group batchSubscribe $batch= ', $batch);

    static::updatePushStats($stats);
    // Finally, finish up by removing the two temporary tables
    CRM_Core_DAO::executeQuery("DROP TABLE tmp_googlegroup_push_g;");
    CRM_Core_DAO::executeQuery("DROP TABLE tmp_googlegroup_push_c;");

    return CRM_Queue_Task::TASK_SUCCESS;
  }


  /**
   * Collect Google Group data into temporary working table.
   */
  static function syncCollectGoogle($groupID) {
    // Create a temporary table.
    CRM_Core_DAO::executeQuery( "DROP TABLE IF EXISTS tmp_googlegroup_push_g;");
    $dao = CRM_Core_DAO::executeQuery(
      "CREATE TABLE tmp_googlegroup_push_g (
        email VARCHAR(200),
        euid VARCHAR(100),
        PRIMARY KEY (email));");
    // Cheekily access the database directly to obtain a prepared statement.
    $db = $dao->getDatabaseConnection();
    $insert = $db->prepare('INSERT INTO tmp_googlegroup_push_g VALUES(?, ?)');
    $googleGroupMembers = civicrm_api3('Googlegroup', 'getmembers', [
      'group_id' => $groupID,
    ]);
    foreach ($googleGroupMembers['values'] as $memberId => $memberEmail) {
      $db->execute($insert, [$memberEmail, $memberId]);
    }
    $db->freePrepared($insert);
    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(*) c FROM tmp_googlegroup_push_g");
    $dao->fetch();
    return $dao->c;
  }

  /**
   * Collect CiviCRM data into temporary working table.
   */
  static function syncCollectCiviCRM($groupID) {

    // Nb. these are temporary tables but we don't use TEMPORARY table because they are
    // needed over multiple sessions because of queue.
    CRM_Core_DAO::executeQuery( "DROP TABLE IF EXISTS tmp_googlegroup_push_c;");
    $dao = CRM_Core_DAO::executeQuery("CREATE TABLE tmp_googlegroup_push_c (
        contact_id INT(10) UNSIGNED NOT NULL,
        email_id INT(10) UNSIGNED NOT NULL,
        email VARCHAR(200),
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        PRIMARY KEY (email_id, email)
      );"
    );
    // Cheekily access the database directly to obtain a prepared statement.
    $db = $dao->getDatabaseConnection();
    $insert = $db->prepare('INSERT INTO tmp_googlegroup_push_c VALUES(?, ?, ?, ?, ?)');

    // We only care about CiviCRM groups that are mapped to this Google Group:
    $mapped_groups = CRM_Googlegroup_Utils::getGroupsToSync($groupID);
    foreach ($mapped_groups as $group_id => $details) {
      CRM_Contact_BAO_GroupContactCache::loadAll($group_id);
      $groupContact = CRM_Googlegroup_Utils::getGroupContactObject($group_id);
      while ($groupContact->fetch()) {
        // Find the contact, for the name fields
        $contact = new CRM_Contact_BAO_Contact();
        $contact->id = $groupContact->contact_id;
        $contact->is_deleted = 0;
        $contact->find(TRUE);

        // Find their primary (bulk) email
        $email = new CRM_Core_BAO_Email();
        $email->contact_id = $groupContact->contact_id;
        $email->is_primary = TRUE;
        $email->find(TRUE);

        // If no email, it's like they're not there.
        if (!$email->email || $email->on_hold || $contact->is_opt_out || $contact->do_not_email) {
          //@todo update stats.
          continue;
        }

        // run insert prepared statement
        $db->execute($insert, [$contact->id, $email->id, $email->email, $contact->first_name, $contact->last_name]);
      }
    }

    // Tidy up.
    $db->freePrepared($insert);
    // count
    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(*) c FROM tmp_googlegroup_push_c");
    $dao->fetch();
    return $dao->c;
  }

  /**
   * Update the push stats setting.
   */
  static function updatePushStats($updates) {
    $stats =  CRM_Googlegroup_Utils::getSettingValue('cg_push_stats');
    foreach ($updates as $groupId=>$settings) {
      foreach ($settings as $key=>$val) {
        $stats[$groupId][$key] = $val;
      }
    }
    CRM_Core_BAO_Setting::setItem($stats, CRM_Googlegroup_Form_Setting::GG_SETTING_GROUP, 'cg_push_stats');
  }
}
