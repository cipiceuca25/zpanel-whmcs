<?php

/**
 * @package zpanelx
 * @subpackage modules
 * @author knivey (knivey@botops.net)
 * @copyright knivey (knivey@botops.net)
 * @link http://www.zpanelcp.com/
 * @license GPL (http://www.gnu.org/licenses/gpl.html)
 */
class module_controller {
    
    /***********************************************************
     * Begin functions for WebPage
     ***********************************************************/
    
    /**
     * Get our icon path + filename
     * @global type $controller
     * @return string Path to the icon file
     */
    static function getModuleIcon() {
        global $controller;
        $module_icon = "modules/" . $controller->GetControllerRequest('URL', 'module') . "/assets/icon.png";
        return $module_icon;
    }
    
    /**
     * Returns our module description
     * @return string The module description from the database (originally improted from the module.xml file). 
     */
    static function getModuleDesc() {
        $message = ui_language::translate(ui_module::GetModuleDescription());
        return $message;
    }
    
    /**
     * Builds a 'hidden' form type which is populated with the generated token.
     * @author Bobby Allen (ballen@zpanelcp.com)
     * @return string The HTML form tag.
     */
    static function getCSFR_Tag() {
        return runtime_csfr::Token();
    }
    
    /**
     * Get our module name
     * @return string Name of the current module
     */
    static function getModuleName() {
        $module_name = ui_module::GetModuleName();
        return $module_name;
    }
    
    /**
     * Checks if there is a bad version notice set
     */
    static function getBadVersionIsSet() {
        $whmcs_version = self::getBadVersion();
        if($whmcs_version == 'false') {
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * generate form for sendemail options
     * @return string HTML FORM ITEM
     */
    static function getSendEmailForm() {
        $cval = ctrl_options::GetSystemOption('whmcs_sendemail_bo');
        return ctrl_options::OuputSettingMenuField('SendEmail', 'true|false', $cval);
    }
    
    /**
     * generate form for whmcs login link
     * @return string HTML FORM ITEM
     */
    static function getWHMCSLinkForm() {
        $cval = ctrl_options::GetSystemOption('whmcs_link');
        return ctrl_options::OutputSettingTextField('Link', $cval);
    }
    
    /**
     * Accepts Admin settings form
     * @return null
     */
    static function doUpdateSettings() {
        global $controller;
        runtime_csfr::Protect();
        $form = $controller->GetAllControllerRequests('FORM');
        if(!isset($form['inAdminSettings'])) {
            return false;
        }
        ctrl_options::SetSystemOption('whmcs_sendemail_bo', $form['SendEmail']);
        ctrl_options::SetSystemOption('whmcs_link', $form['Link']);
        self::$Results[] = ui_sysmessage::shout('Settings updated!', 'znotice', 'znotice');
    }


    /**
     * Gets the version reported by whmcs or if version matches us 'false'
     * @return string version reported by whmcs
     */
    static function getBadVersion() {
        return ctrl_options::GetSystemOption('whmcs_reported');
    }

    /**
     * 
     * @return string The link admin defined in settings
     */
    static function getWHMCSLink() {
        return ctrl_options::GetSystemOption('whmcs_link');;
    }
    
    /**
     * Get the zip file for WHMCS Module download
     * @return string Path to the file
     */
    static function getWHMCSModule() {
        global $controller;
        $module_icon = "modules/" . $controller->GetControllerRequest('URL', 'module') . "/assets/whmcs.zip";
        return $module_icon;
    }
    
    /**
     * Get the current version of the module
     * @return string Version number
     */
    static function getVersion() {
        $tags = ui_module::GetModuleXMLTags('whmcs');
        return $tags['version'];
    }
    
    /**
     * Check if the user has full admin access
     * @return boolean true if admin false if not
     */
    static function getIsAdmin() {
        $user = ctrl_users::GetUserDetail();
        if($user['usergroupid'] == 1) {
            return true;
        } else {
            return false;
        }
    }
    
    static $Results = Array();
    /**
     * Gets the result of any operations we have ran
     * @return string Html Output seperated by br tags
     */
    static function getResult() {
        return implode('<br />', self::$Results);
    }


    /***********************************************************
     * End functions for WebPage
     ***********************************************************/
    
    /**
     * Sends an email to all admins noticing of mismatched whmcs-zpanel versions
     * @global db_driver $zdbh
     * @param type $version WHMCS Version detected
     * @return null
     */
    function sendBadVersionMail($version) {
        global $zdbh;
        $zversion = self::getVersion();
        $sql = 'select ac_email_vc from x_accounts where ac_group_fk = 1';
        $stmt = $zdbh->query($sql);
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $phpmailer = new sys_email();
        $phpmailer->Subject = "ZPanel - WHMCS Version Mismatch";
        $phpmailer->Body = "This Email is to warn you that the versions of your WHMCS module (Version: $version)"
            ." and the ZPanel module (Version: $zversion) do not match! Please correct this ASAP!";
        foreach($addresses as $email) {
            $phpmailer->AddAddress($email['ac_email_vc']);
        }
        $phpmailer->SendEmail();
    }
    
    /**
     * Fetches the uid for username
     * @param string $name Username
     * @global db_driver $zdbh
     * @return mixed False if no user exists Otherwise UID
     */
    static function getUserId($name) {
        global $zdbh;
        $stmt = $zdbh->prepare("SELECT COUNT(*) FROM `x_accounts` WHERE `ac_user_vc`=? AND ac_deleted_ts is NULL");
        $stmt->execute(array($name));
        $rows = $stmt->fetch(PDO::FETCH_ASSOC);
        $numrows = $rows['COUNT(*)'];
        if ($numrows == 0) {
            return false;
        } else {
            $stmt = $zdbh->prepare("SELECT `ac_id_pk` FROM `x_accounts` WHERE `ac_user_vc`=? AND ac_deleted_ts is NULL");
            $stmt->execute(array($name));
            $rowpack = $stmt->fetch(PDO::FETCH_ASSOC);
            return $rowpack['ac_id_pk'];
        }
        return false;
    }
    
    /**********
     * Our Version of password_assistant
     * no changes
     **********/
    
    static function UpdatePassword($uid, $password) {
        global $zdbh;
        $crypto = new runtime_hash;
        $crypto->SetPassword($password);
        $randomsalt = $crypto->RandomSalt();
        $crypto->SetSalt($randomsalt);
        $secure_password = $crypto->CryptParts($crypto->Crypt())->Hash;
        $sql = $zdbh->prepare("UPDATE x_accounts SET ac_pass_vc=:secure_password, ac_passsalt_vc= :randomsalt WHERE ac_id_pk=:userid AND ac_deleted_ts is NULL");
        $sql->bindParam(':randomsalt', $randomsalt);
        $sql->bindParam(':secure_password', $secure_password);
        $sql->bindParam(':userid', $uid);
        $sql->execute();
        return true;
    }
    
    
    /**********
     * Our version of manage_clients
     * Changes:
     * Added getUserExists
     * ExecuteCreateClient - initialize $time only once
     * ExecuteCreateClient - add support for packages by textual name
     * ExecuteUpdateClient - add support for packages by textual name
     * ExecuteUpdateClient - dropped self::Enable/Disable client and just added the hooks to top/bottom
     **********/
    /**
     * Checks If a username exists in the database
     * @return bool True if username exists false if available
     */
    static function getUserExists($username) {
        global $zdbh;
        $stmt = $zdbh->prepare("SELECT COUNT(*) FROM x_accounts WHERE ac_user_vc=? AND ac_deleted_ts is NULL");
        $stmt->execute(Array($username));
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res['COUNT(*)'] > 0) {
            return true;
        } else {
            return false;
        }
    }
    
    static function ExecuteCreateClient($uid, $username, $packageid, $groupid, $fullname, $email, $address, $post, $phone, $password, $sendemail, $emailsubject, $emailbody) {
        global $zdbh;
        // Check for spaces and remove if found...
        $username = strtolower(str_replace(' ', '', $username));
        $reseller = ctrl_users::GetUserDetail($uid);
        
        //convert package to numerical id if needed
        if(!is_numeric($packageid)) $packageid=self::getPackageIdFix($packageid);
        
        // Check for errors before we continue...
        if (fs_director::CheckForEmptyValue(self::CheckCreateForErrors($username, $packageid, $groupid, $email, $password))) {
            return false;
        }
        runtime_hook::Execute('OnBeforeCreateClient');
        
        $crypto = new runtime_hash;
        $crypto->SetPassword($password);
        $randomsalt = $crypto->RandomSalt();
        $crypto->SetSalt($randomsalt);
        $secure_password = $crypto->CryptParts($crypto->Crypt())->Hash;
        $time = time();
        
        // No errors found, so we can add the user to the database...
        $sql = $zdbh->prepare("INSERT INTO x_accounts (ac_user_vc, ac_pass_vc, ac_passsalt_vc, ac_email_vc, ac_package_fk, ac_group_fk, ac_usertheme_vc, ac_usercss_vc, ac_reseller_fk, ac_created_ts) VALUES (
            :username, :password, :passsalt, :email, :packageid, :groupid, :resellertheme, :resellercss, :uid, :time)");
        $sql->bindParam(':uid', $uid);
        $sql->bindParam(':time', $time);
        $sql->bindParam(':username', $username);
        $sql->bindParam(':password', $secure_password);
        $sql->bindParam(':passsalt', $randomsalt);
        $sql->bindParam(':email', $email);
        $sql->bindParam(':packageid', $packageid);
        $sql->bindParam(':groupid', $groupid);
        $sql->bindParam(':resellertheme', $reseller['usertheme']);
        $sql->bindParam(':resellercss', $reseller['usercss']);
        $sql->execute();
        // Now lets pull back the client ID so that we can add their personal address details etc...
        //$client = $zdbh->query("SELECT * FROM x_accounts WHERE ac_reseller_fk=" . $uid . " ORDER BY ac_id_pk DESC")->Fetch();
        $numrows = $zdbh->prepare("SELECT * FROM x_accounts WHERE ac_reseller_fk=:uid ORDER BY ac_id_pk DESC");
        $numrows->bindParam(':uid', $uid);
        $numrows->execute();
        $client = $numrows->fetch();
        
        $sql = $zdbh->prepare("INSERT INTO x_profiles (ud_user_fk, ud_fullname_vc, ud_group_fk, ud_package_fk, ud_address_tx, ud_postcode_vc, ud_phone_vc, ud_created_ts) VALUES (:userid, :fullname, :packageid, :groupid, :address, :postcode, :phone, :time)");
        $sql->bindParam(':userid', $client['ac_id_pk']);
        $sql->bindParam(':fullname', $fullname);
        $sql->bindParam(':packageid', $packageid);
        $sql->bindParam(':groupid', $groupid);
        $sql->bindParam(':address', $address);
        $sql->bindParam(':postcode', $post);
        $sql->bindParam(':phone', $phone);
        $sql->bindParam(':time', $time);
        $sql->execute();
        // Now we add an entry into the bandwidth table, for the user for the upcoming month.
        $sql = $zdbh->prepare("INSERT INTO x_bandwidth (bd_acc_fk, bd_month_in, bd_transamount_bi, bd_diskamount_bi) VALUES (:ac_id_pk, :date, 0, 0)");
        $date = date("Ym", time());
        $sql->bindParam(':date', $date);
        $sql->bindParam(':ac_id_pk', $client['ac_id_pk']);
        $sql->execute();
        // Lets create the client diectories
        fs_director::CreateDirectory(ctrl_options::GetSystemOption('hosted_dir') . $username);
        fs_director::SetFileSystemPermissions(ctrl_options::GetSystemOption('hosted_dir') . $username, 0777);
        fs_director::CreateDirectory(ctrl_options::GetSystemOption('hosted_dir') . $username . "/public_html");
        fs_director::SetFileSystemPermissions(ctrl_options::GetSystemOption('hosted_dir') . $username . "/public_html", 0777);
        fs_director::CreateDirectory(ctrl_options::GetSystemOption('hosted_dir') . $username . "/backups");
        fs_director::SetFileSystemPermissions(ctrl_options::GetSystemOption('hosted_dir') . $username . "/backups", 0777);
        // Send the user account details via. email (if requested)... 
        if ($sendemail <> 0) {
            $emailsubject = str_replace("{{username}}", $username, $emailsubject);
            $emailsubject = str_replace("{{password}}", $password, $emailsubject);
            $emailsubject = str_replace("{{fullname}}", $fullname, $emailsubject);
            $emailbody = str_replace("{{username}}", $username, $emailbody);
            $emailbody = str_replace("{{password}}", $password, $emailbody);
            $emailbody = str_replace("{{fullname}}", $fullname, $emailbody);

            $phpmailer = new sys_email();
            $phpmailer->Subject = $emailsubject;
            $phpmailer->Body = $emailbody;
            $phpmailer->AddAddress($email);
            $phpmailer->SendEmail();
        }
        runtime_hook::Execute('OnAfterCreateClient');
        self::$resetform = true;
        self::$ok = true;
        return true;
    }
    
    static function ExecuteUpdateClient($clientid, $package, $enabled, $group, $fullname, $email, $address, $post, $phone, $newpass) {
        global $zdbh;
        runtime_hook::Execute('OnBeforeUpdateClient');
        //convert package to numerical id if needed
        if(!is_numeric($package)) $package=self::getPackageIdFix($package);
        
        if ($enabled == 0) {
            runtime_hook::Execute('OnBeforeDisableClient');
        }
        if ($enabled == 1) {
            runtime_hook::Execute('OnBeforeEnableClient');
        }
        if ($newpass != "") {
            // Check for password length...
            if (strlen($newpass) < ctrl_options::GetSystemOption('password_minlength')) {
                self::$badpassword = true;
                return false;
            }

            $crypto = new runtime_hash;
            $crypto->SetPassword($newpass);
            $randomsalt = $crypto->RandomSalt();
            $crypto->SetSalt($randomsalt);
            $secure_password = $crypto->CryptParts($crypto->Crypt())->Hash;

            $sql = $zdbh->prepare("UPDATE x_accounts SET ac_pass_vc= :newpass, ac_passsalt_vc= :passsalt WHERE ac_id_pk= :clientid");
            $sql->bindParam(':clientid', $clientid);
            $sql->bindParam(':newpass', $secure_password);
            $sql->bindParam(':passsalt', $randomsalt);
            $sql->execute();
        }
        $sql = $zdbh->prepare("UPDATE x_accounts SET ac_email_vc= :email, ac_package_fk= :package, ac_enabled_in= :isenabled, ac_group_fk= :group WHERE ac_id_pk = :clientid");
        $sql->bindParam(':email', $email);
        $sql->bindParam(':package', $package);
        $sql->bindParam(':isenabled', $enabled);
        $sql->bindParam(':group', $group);
        $sql->bindParam(':clientid', $clientid);
        //$sql->bindParam(':accountid', $clientid);
        $sql->execute();

        $sql = $zdbh->prepare("UPDATE x_profiles SET ud_fullname_vc= :fullname, ud_group_fk= :group, ud_package_fk= :package, ud_address_tx= :address,ud_postcode_vc= :postcode, ud_phone_vc= :phone WHERE ud_user_fk=:accountid");
        $sql->bindParam(':fullname', $fullname);
        $sql->bindParam(':group', $group);
        $sql->bindParam(':package', $package);
        $sql->bindParam(':address', $address);
        $sql->bindParam(':postcode', $post);
        $sql->bindParam(':phone', $phone);
        $sql->bindParam(':accountid', $clientid);
        $sql->execute();
        if ($enabled == 0) {
            runtime_hook::Execute('OnAfterDisableClient');
        }
        if ($enabled == 1) {
            runtime_hook::Execute('OnAfterEnableClient');
        }
        runtime_hook::Execute('OnAfterUpdateClient');
        self::$ok = true;
        return true;
    }
    
    static $complete;
    static $error;
    static $alreadyexists;
    static $badname;
    static $bademail;
    static $badpassword;
    static $userblank;
    static $emailblank;
    static $passwordblank;
    static $packageblank;
    static $groupblank;
    static $ok;
    static $edit;
    static $clientid;
    static $clientpkgid;
    static $resetform;
    
    static function CheckCreateForErrors($username, $packageid, $groupid, $email, $password = "") {
        global $zdbh;
        $username = strtolower(str_replace(' ', '', $username));
        // Check to make sure the username is not blank or exists before we go any further...
        if (!fs_director::CheckForEmptyValue($username)) {
            $sql = "SELECT COUNT(*) FROM x_accounts WHERE UPPER(ac_user_vc)=:user AND ac_deleted_ts IS NULL";
            $numrows = $zdbh->prepare($sql);
            $user = strtoupper($username);
            $numrows->bindParam(':user', $user);
            if ($numrows->execute()) {
                if ($numrows->fetchColumn() <> 0) {
                    self::$alreadyexists = true;
                    return false;
                }
            }
            if (!self::IsValidUserName($username)) {
                self::$badname = true;
                return false;
            }
        } else {
            self::$userblank = true;
            return false;
        }
        // Check to make sure the packagename is not blank and exists before we go any further...
        if (!fs_director::CheckForEmptyValue($packageid)) {
            $sql = "SELECT COUNT(*) FROM x_packages WHERE pk_id_pk=:packageid AND pk_deleted_ts IS NULL";
            $numrows = $zdbh->prepare($sql);
            $numrows->bindParam(':packageid', $packageid);
            if ($numrows->execute()) {
                if ($numrows->fetchColumn() == 0) {
                    self::$packageblank = true;
                    return false;
                }
            }
        } else {
            self::$packageblank = true;
            return false;
        }
        // Check to make sure the groupname is not blank and exists before we go any further...
        if (!fs_director::CheckForEmptyValue($groupid)) {
            $sql = "SELECT COUNT(*) FROM x_groups WHERE ug_id_pk=:groupid";
            $numrows = $zdbh->prepare($sql);
            $numrows->bindParam(':groupid', $groupid);
            
            if ($numrows->execute()) {
                if ($numrows->fetchColumn() == 0) {
                    self::$groupblank = true;
                    return;
                }
            }
        } else {
            self::$groupblank = true;
            return false;
        }
        // Check for invalid characters in the email and that it exists...
        if (!fs_director::CheckForEmptyValue($email)) {
            if (!self::IsValidEmail($email)) {
                self::$bademail = true;
                return false;
            }
        } else {
            self::$emailblank = true;
            return false;
        }
        // Check for password length...
        if (!fs_director::CheckForEmptyValue($password)) {
            if (strlen($password) < ctrl_options::GetSystemOption('password_minlength')) {
                self::$badpassword = true;
                return false;
            }
        } else {
            self::$passwordblank = true;
            return false;
        }

        return true;
    }

    static function IsValidEmail($email) {
        if (!preg_match('/^[a-z0-9]+([_\\.-][a-z0-9]+)*@([a-z0-9]+([\.-][a-z0-9]+)*)+\\.[a-z]{2,}$/i', $email)) {
            return false;
        }
        return true;
    }

    static function IsValidUserName($username) {
        if (!preg_match('/^[a-z\d][a-z\d-]{0,62}$/i', $username) || preg_match('/-$/', $username)) {
            return false;
        }
        return true;
    }

    static function DefaultEmailBody() {
        $line = ui_language::translate("Hi {{fullname}},\r\rWe are pleased to inform you that your new hosting account is now active, you can now login to ZPanel using the following credentials:\r\rUsername: {{username}}\rPassword: {{password}}");
        return $line;
    }
    
    static function getPackageIdFix($name) {
        global $zdbh;
        $stmt = $zdbh->prepare("SELECT COUNT(*) FROM `x_packages` WHERE `pk_name_vc`=? AND pk_deleted_ts IS NULL");
        $stmt->execute(array($name));
        $rows = $stmt->fetch(PDO::FETCH_ASSOC);
        $numrows = $rows['COUNT(*)'];
        if ($numrows == 0) {
            return false;
        } else {
            $stmt = $zdbh->prepare("SELECT * FROM `x_packages` WHERE `pk_name_vc`=? AND pk_deleted_ts IS NULL");
            $stmt->execute(array($name));
            $rowpack = $stmt->fetch(PDO::FETCH_ASSOC);
            return $rowpack['pk_id_pk'];
        }
        return false;
    }
}
