<?php

/**
 * @package zpanelx
 * @subpackage modules
 * @author knivey (knivey@botops.net)
 * @copyright knivey (knivey@botops.net)
 * @link http://www.zpanelcp.com/
 * @license GPL (http://www.gnu.org/licenses/gpl.html)
 */
class webservice extends ws_xmws {

    /*
     * todo
     * 
     * release:
     * 
     * add a version check for the whmcs' module
     *   could add a new api call to all whmcs function to tell zpanel its version
     *   perhaps even have an option to send email to admin
     * 
     * add a user page that will link them to their whmcs clientarea
     * 
     * add a serveradmin page to provide download & instructions for setting up whmcs side
     * 
     * possibly add a table to track which users are setup through whmcs
     * 
     * Future:
     * 
     * add ssl support and provide a guide on how to setup zpanel for ssl
     * 
     * add option on whmcs for if remote logins (csfr disabled) on zpanel and explain
     *   why this is a bad idea (Warning running zpanel with remote logins enabled is a security vunerability)
     *   ^ actually might be best not to support this to discourage
     * 
     * test reseller support
     */
    
    /**
     * Looks for a UID matching the username provided
     * @param string $username Username to lookup
     * @return mixed string UID or empty Array() on failure
     */
    function getUserId() {
        $request_data = $this->XMLDataToArray($this->wsdata);
        $ctags = $request_data['xmws']['content'];
        $dataobject = new runtime_dataobject();
        $dataobject->addItemValue('response', '');
        $uid = module_controller::getUserId($ctags['username']);
        $dataobject->addItemValue('content', ws_xmws::NewXMLTag('uid', $uid));
        return $dataobject->getDataObject();
    }
    
    /**
     * Checks if ZPanel module version matches the module in WHMCS
     * @param string $version WHMCS module version
     * @return string "true" if versions match "false" otherwise
     */
    function checkVersion() {
        $request_data = $this->XMLDataToArray($this->wsdata);
        $ctags = $request_data['xmws']['content'];
        $dataobject = new runtime_dataobject();
        $dataobject->addItemValue('response', '');
        $version = module_controller::getVersion();
        if((int)$version != (int)$ctags['version']) {
            $dataobject->addItemValue('content', ws_xmws::NewXMLTag('pass', 'false'));
            //check database if this is first time
            $alreadyReported = ctrl_options::GetSystemOption('whmcs_reported');
            if ($alreadyReported == 'false') {
                //if so then update database
                ctrl_options::SetSystemOption('whmcs_reported', $ctags['version']);
                //then send email to admins if possible
                $sendemail = ctrl_options::GetSystemOption('whmcs_sendemail_bo');
                if ($sendemail == 'true') {
                    module_controller::sendBadVersionMail($ctags['version']);
                }
            }
        } else {
            $alreadyReported = ctrl_options::GetSystemOption('whmcs_reported');
            if($alreadyReported != 'false') {
                ctrl_options::SetSystemOption('whmcs_reported', 'false');
            }
            $dataobject->addItemValue('content', ws_xmws::NewXMLTag('pass', 'true'));
        }
        return $dataobject->getDataObject();
    }
    
    /****************
     * Our version of password_assistant
     ****************/
    /**
     * Resets a user's ZPanel account password. Requires <username> and <newpassword> tags.
     * @return type
     */
    function ResetUserPassword() {
        $request_data = $this->XMLDataToArray($this->wsdata);
        $ctags = $request_data['xmws']['content'];
        $dataobject = new runtime_dataobject();
        $dataobject->addItemValue('response', '');
        $uid = module_controller::getUserId($ctags['username']);
        if (module_controller::UpdatePassword($uid, $ctags['newpassword'])) {
            $dataobject->addItemValue('content',
                    ws_xmws::NewXMLTag('uid', $uid) .
                    ws_xmws::NewXMLTag('reset', 'true'));
        } else {
            $dataobject->addItemValue('content', 
                    ws_xmws::NewXMLTag('uid', $uid) .
                    ws_xmws::NewXMLTag('reset', 'false'));
        }
        return $dataobject->getDataObject();
    }
    
    
    /****************
     * Our version of manage_clients
     ****************/
    /**
     * Checks if <username> exists in the database
     * Returns "true" or "false"
     * @return type
     */
    public function UsernameExits() {
        $request_data = $this->XMLDataToArray($this->wsdata);
        $ctags = $request_data['xmws']['content'];
        $response = null;
        if (module_controller::getUserExists($ctags['username'])) {
            $response = "true";
        } else {
            $response = "false";
        }
        $dataobject = new runtime_dataobject();
        $dataobject->addItemValue('response', '');
        $dataobject->addItemValue('content', $response);
        return $dataobject->getDataObject();
    }
    
    /**
     * Checks if username is taken if not Creates a new client with data provided
     * Accepts <resellerid> <username> <packageid> <groupid> <fullname> <email>
     * Accepts <address> <postcode> <phone> <password> <sendemail> <emailsubject> <emailbody>
     * @return type
     */
    public function CreateClient() {
        $response_xml = 'unknown error';
        $request_data = $this->XMLDataToArray($this->wsdata);
        $ctags = $request_data['xmws']['content'];
        //$fp = fopen("/etc/zpanel/knivey.debug", 'w');
        //fputs($fp, var_export($request_data, true) . "\n\n\n" . var_export($ctags, true));
        //fclose($fp);
        $userExists = module_controller::getUserExists($ctags['username']);
        if ($userExists == false) {
            module_controller::ExecuteCreateClient(
                    $ctags['resellerid'],
                    $ctags['username'],
                    $ctags['packageid'],
                    $ctags['groupid'],
                    $ctags['fullname'],
                    $ctags['email'],
                    $ctags['address'],
                    $ctags['postcode'],
                    $ctags['phone'],
                    $ctags['password'],
                    $ctags['sendmail'],
                    $ctags['emailsubject'],
                    $ctags['emailbody']
                );
            $response_xml = 'success';
        } else {
            $response_xml = "A user already exists with that username.";
        }
        $dataobject = new runtime_dataobject();
        $dataobject->addItemValue('response', '');
        $dataobject->addItemValue('content', $response_xml);
        return $dataobject->getDataObject();
    }
    
    public function UpdateClient() {
        $request_data = $this->XMLDataToArray($this->wsdata);
        $ctags = $request_data['xmws']['content'];

        $response_xml = module_controller::ExecuteUpdateClient(
                $ctags['uid'],
                $ctags['packageid'],
                '1',
                $ctags['groupid'],
                $ctags['fullname'],
                $ctags['email'],
                $ctags['address'],
                $ctags['postcode'],
                $ctags['phone'],
                $ctags['password']);
        if ($response_xml == false)
            $response_xml = "Can't update user.";
        else
            $response_xml = "success";
        
        $dataobject = new runtime_dataobject();
        $dataobject->addItemValue('response', '');
        $dataobject->addItemValue('content', $response_xml);
        return $dataobject->getDataObject();
    }
}

?>