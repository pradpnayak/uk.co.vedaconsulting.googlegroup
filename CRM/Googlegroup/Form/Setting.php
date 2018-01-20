<?php
class CRM_Googlegroup_Form_Setting extends CRM_Admin_Form_Setting {

  const GG_SETTING_GROUP = 'Googlegroup Preferences';

  protected $_settings = [
    'cg_client_key' => CRM_Googlegroup_Form_Setting::GG_SETTING_GROUP,
    'cg_client_secret' => CRM_Googlegroup_Form_Setting::GG_SETTING_GROUP,
    'cg_domain_name' => CRM_Googlegroup_Form_Setting::GG_SETTING_GROUP,
    'cg_access_token' => CRM_Googlegroup_Form_Setting::GG_SETTING_GROUP,
  ];
  protected $_access_token;
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
    if (isset($_GET['code'])) {
      $client = CRM_Googlegroup_Utils::googleClient();
      $redirectUrl    = CRM_Utils_System::url('civicrm/googlegroup/settings', 'reset=1',  TRUE, NULL, FALSE, TRUE, TRUE);
      $client->setRedirectUri($redirectUrl);
      $client->authenticate($_GET['code']);
      $accessToken = $client->getRefreshToken();
      $this->_access_token = $accessToken;
      CRM_Core_Session::setStatus(ts("Autheticated successfully. Please click 'Save Domains' to store the google api keys."), ts('Authentication'), 'success');
    }
    elseif (isset($_GET['error'])) {
      CRM_Core_Session::setStatus(ts('Could not authenticate with Google. Please check google client key and secret and try again.'), ts('Authentication'), 'error');
    }
    $buttons = [
      [
        'type' => 'next',
        'name' => $accessToken ? ts('Save Domains') : ts('Authenticate with Google'),
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ];
    $this->addButtons($buttons);
  }

  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();
    if ($this->_access_token) {
      $defaults['cg_access_token'] = $this->_access_token;
    }
    return $defaults;
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
    parent::commonProcess($params);

    $accessToken = CRM_Googlegroup_Utils::getSettingValue('cg_access_token');
    if (empty($accessToken)) {
      $client = CRM_Googlegroup_Utils::googleClient();
      $redirectUrl = CRM_Utils_System::url('civicrm/googlegroup/settings', 'reset=1',  TRUE, NULL, FALSE, TRUE, TRUE);
      $client->setRedirectUri($redirectUrl);
      $service = new Google_Service_Directory($client);
      $auth_url = $client->createAuthUrl();
      header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
      exit;
    }
  }

}
