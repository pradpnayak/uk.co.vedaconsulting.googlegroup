<?php
class CRM_Googlegroup_Form_Setting extends CRM_Admin_Form_Setting {

  const GG_SETTING_GROUP = 'Googlegroup Preferences';

  protected $_settings = [
    'cg_client_key' => CRM_Googlegroup_Form_Setting::GG_SETTING_GROUP,
    'cg_client_secret' => CRM_Googlegroup_Form_Setting::GG_SETTING_GROUP,
    'cg_domain_name' => CRM_Googlegroup_Form_Setting::GG_SETTING_GROUP,
  ];

  /**
   * Function to actually build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Google Api Key Settings'));
    parent::buildQuickForm();

    $accessToken = CRM_Googlegroup_Utils::getSettingValue('cg_access_token');
    $buttons = [
      [
        'type' => 'next',
        'name' => $accessToken ? ts('Save Domains') : ts('Connect To My Google Group'),
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ];
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
    $params = $this->controller->exportValues($this->_name);
    $accessToken = CRM_Googlegroup_Utils::getSettingValue('cg_access_token');

    //TODO::pradeep
    $this->_settings['cg_access_token'] = CRM_Googlegroup_Form_Setting::GG_SETTING_GROUP;
    $params['cg_access_token'] = 'sdsds';
/*
    if (empty($accessToken)) {
      $client = CRM_Googlegroup_Utils::googleClient();
      $redirectUrl    = CRM_Utils_System::url('civicrm/googlegroup/settings', 'reset=1',  TRUE, NULL, FALSE, TRUE, TRUE);
      $client->setRedirectUri($redirectUrl);
      $service = new Google_Service_Directory($client);
      $auth_url = $client->createAuthUrl();
      CRM_Core_Error::debug_var('$auth_url', $auth_url);
      header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
    }
    if (isset($_GET['code'])) {
      $client = CRM_Googlegroup_Utils::googleClient();
      $redirectUrl    = CRM_Utils_System::url('civicrm/googlegroup/settings', 'reset=1',  TRUE, NULL, FALSE, TRUE, TRUE);
      $client->setRedirectUri($redirectUrl);
      $client->authenticate($_GET['code']);
      $params['cg_access_token'] = $client->getRefreshToken();
      //header('Location: ' . filter_var($redirectUrl, FILTER_SANITIZE_URL));
    }
*/
    parent::commonProcess($params);
  }

}
