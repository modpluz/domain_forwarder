<?php
/**
 *
 * Domain Forwarder Module for ZPanel 10.1.0
 * Version : 1.1.0
 * Author :  Aderemi Adewale (modpluz @ ZPanel Forums)
 * Email : goremmy@gmail.com
 */

class module_controller {

    static $complete;
    static $error;
    static $badname;
    static $emptydomain;
    static $domainexists;
    static $emptyforward;
    static $forwardexists;
    static $ok;
    static $editing;
    static $limitreached;

/*START - Check for updates added by TGates*/
// Module update check functions
    static function getModuleVersion() {
        global $zdbh, $controller;
        $module_path="./modules/" . $controller->GetControllerRequest('URL', 'module');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/" . $controller->GetControllerRequest('URL', 'module') . "/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_version = $mod_config->document->version[0]->tagData;
        return "v".$module_version."";
    }
    
    static function getCheckUpdate() {
        global $zdbh, $controller;
        $module_path="./modules/" . $controller->GetControllerRequest('URL', 'module');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/" . $controller->GetControllerRequest('URL', 'module') . "/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_updateurl = $mod_config->document->updateurl[0]->tagData;
        $module_version = $mod_config->document->version[0]->tagData;

        // Download XML in Update URL and get Download URL and Version
        $myfile = self::getCheckRemoteXml($module_updateurl, $module_path."/" . $controller->GetControllerRequest('URL', 'module') . ".xml");
        $update_config = new xml_reader(fs_filehandler::ReadFileContents($module_path."/" . $controller->GetControllerRequest('URL', 'module') . ".xml"));
        $update_config->Parse();
        $update_url = $update_config->document->downloadurl[0]->tagData;
        $update_version = $update_config->document->latestversion[0]->tagData;

        if($update_version > $module_version)
            return true;
        return false;
    }

/*END - Check for updates added by TGates*/

/*START - Check for updates added by TGates*/
// Function to retrieve remote XML for update check
    static function getCheckRemoteXml($xmlurl,$destfile){
        $feed = simplexml_load_file($xmlurl);
        if ($feed)
        {
            // $feed is valid, save it
            $feed->asXML($destfile);
        } elseif (file_exists($destfile)) {
            // $feed is not valid, grab the last backup
            $feed = simplexml_load_file($destfile);
        } else {
            //die('Unable to retrieve XML file');
            echo('<div class="alert alert-danger">Unable to check for updates, your version may be outdated!.</div>');
        }
    }
/*END - Check for updates added by TGates*/

   /* Load CSS and JS files */
    static function getInit() {
        global $controller;
        $line = '<link rel="stylesheet" type="text/css" href="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/domain_forwarder.css">';
        $line .= '<script type="text/javascript" src="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/domain_forwarder.js"></script>';
        return $line;
    }

    /**
     * The 'worker' methods.
     */
    static function getForwardList() {
        global $zdbh;
		
		$currentuser = ctrl_users::GetUserDetail();

        $sql = "SELECT x_forwarded_domains.*,x_vhosts.vh_name_vc FROM x_forwarded_domains INNER JOIN 
                    x_vhosts ON x_vhosts.vh_id_pk=x_forwarded_domains.vh_fk_id 
                    WHERE fd_acc_fk=:uid AND vh_acc_fk=:uid 
                    AND fd_deleted_ts IS NULL AND vh_deleted_ts IS NULL 
                    ORDER BY fd_id_pk DESC";
        $bindArray = array(':uid' => $currentuser['userid']);                                        
        $zdbh->bindQuery($sql, $bindArray);
        $rows = $zdbh->returnRows(); 

        if (count($rows) > 0) {
            $res = array();
            foreach($rows as $row_idx=>$row) {
                if($row['fd_type_id'] == 1){
                    $forward_type = 'Redirect';
                } elseif($row['fd_type_id'] == 2){
                    $forward_type = 'Forward';
                }
                
                if($row['www_yn'] == 1){
                    $www_yn = 'YES';
                } elseif(!$row['www_yn']){
                    $www_yn = 'NO';
                }

                array_push($res, array(
                    'id' => $row['fd_id_pk'],
                    'name' => $row['fd_name'],
                    'forward_type' => $forward_type,
                    'www_yn' => $www_yn,
                    'target_domain' => $row['vh_name_vc'],
                ));
            }
            return $res;
        } else {
            return false;
        }
    }

    static function ListDomains($uid=0) {
        global $zdbh;

		//$currentuser = ctrl_users::GetUserDetail();
        $sql = "SELECT vh_id_pk,vh_name_vc FROM x_vhosts WHERE vh_acc_fk=:user_id AND vh_deleted_ts IS NULL";
        $bindArray = array(':user_id' => $uid);
        $zdbh->bindQuery($sql, $bindArray);
        $rows = $zdbh->returnRows(); 

        if (count($rows) > 0) {
            $res = array();
            foreach($rows as $row_idx=>$row) {
               array_push($res, array('id' => $row['vh_id_pk'],
				                        'name' => $row['vh_name_vc']));
            }
            return $res;
        } else {
            return false;
        }
    }
    
    static function getisAddDomainForwarder(){
        global $controller;
        $urlvars = $controller->GetAllControllerRequests('URL');
        $formvars = $controller->GetAllControllerRequests('FORM');

		if (isset($urlvars['action']) && (($urlvars['action'] == 'UpdateForward' || $urlvars['action'] == 'DeleteForward') && self::$ok)){
            return true;
		}
		
		if (isset($urlvars['action']) && ($urlvars['action'] != 'ForwardDomain')){
            return false;
		}
        return true;
    }
    
    static function getMaxForwarders(){
        global $zdbh;
		
		$currentuser = ctrl_users::GetUserDetail();
        $sql = "SELECT qt_domain_forwarders_in FROM x_quotas WHERE qt_package_fk=:package_id";
        $bindArray = array(':package_id' => $currentuser['packageid']);
        $zdbh->bindQuery($sql, $bindArray);
        $quota = $zdbh->returnRow();
        if(isset($quota['qt_domain_forwarders_in'])){
            return $quota['qt_domain_forwarders_in'];
        }
        return 0;
    }
    
    static function getCanAddForward(){
        global $zdbh;
        
		$currentuser = ctrl_users::GetUserDetail();
        // let's check total domain forwards this user already has against max domain forwarders
        $sql = "SELECT COUNT(fd_id_pk) AS total_forwards FROM x_forwarded_domains WHERE fd_acc_fk=:user_id AND fd_deleted_ts IS NULL";
        $bindArray = array(':user_id' => $currentuser['userid']);
        $zdbh->bindQuery($sql, $bindArray);
        $forwarders = $zdbh->returnRow();
        $total_forwarders = $forwarders['total_forwards'];
        
        $max_forwards = self::getMaxForwarders();
        if($total_forwarders >= $max_forwards){
            return false;
        }
        
        return true;
    }

    static function CheckForwardForErrors($domain_id,$domain) {
        global $zdbh;
        
        // Check for spaces and remove if found...
        $domain = strtolower(str_replace(' ', '', $domain));
        
        // Check to make sure the domain is not blank before we go any further...
        if ($domain == '') {
            self::$emptyforward = true;
            self::$editing = true;
            return false;
        }

        if (!$domain_id) {
            self::$emptydomain = true;
            self::$editing = true;
            return false;
        }

        // Check for invalid characters in the domain...
        if (!fs_director::IsValidDomainName($domain)) {
            self::$badname = true;
            self::$editing = true;
            return false;
        }
        
        // Check to make sure the domain is in the correct format before we go any further...
        $wwwclean = stristr($domain, 'www.');
        if ($wwwclean == true) {
            self::$badname = true;
            self::$editing = true;
            return false;
        }

        // Check to see if the domain already exists in ZPanel somewhere....
        $numrows = $zdbh->prepare("SELECT COUNT(*) FROM x_vhosts WHERE vh_name_vc=:domain AND vh_deleted_ts IS NULL");
        $numrows->bindParam(':domain', $domain);
        if ($numrows->execute()) {
            if ($numrows->fetchColumn() > 0) {
                self::$domainexists = true;
                self::$editing = true;
                return false;
            }
        }

        // Check to see if the forwarded domain already exists in ZPanel somewhere....
        $sql = "SELECT COUNT(*) FROM x_forwarded_domains WHERE fd_name=:domain AND fd_deleted_ts IS NULL";
        if(self::getisEditForward() && self::getForwardDomainID()){
            $sql .= " AND fd_id_pk<>:id";
        }
        /*echo((int) self::getisEditForward().'<br>');
        echo(self::getForwardDomainID().'<br>');
        echo($sql);
        exit;*/
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':domain', $domain);
        if(self::getisEditForward() && self::getForwardDomainID()){
            $numrows->bindParam(':id', self::getForwardDomainID());
        }
        if ($numrows->execute()) {
            if ($numrows->fetchColumn() > 0) {
                self::$forwardexists = true;
                self::$editing = true;
                return false;
            }
        }

        return true;
    }
	

    /**
     * End 'worker' methods.
     */

    /**
     * Webinterface sudo methods.
     */
    static function getDomainList() {
        global $controller,$zdbh;
        $currentuser = ctrl_users::GetUserDetail();
        $res = array();
        $domains = self::ListDomains($currentuser['userid']);
        if (!fs_director::CheckForEmptyValue($domains)){
           $selected_id = 0;
           if(self::getisEditForward()){
              if(self::getForwardDomainID()){
                $domain_id = self::getForwardDomainID();
                
			    $sql = "SELECT vh_fk_id FROM x_forwarded_domains WHERE fd_id_pk=:id AND fd_deleted_ts IS NULL";
                $bindArray = array(':id' => $domain_id);
                $zdbh->bindQuery($sql, $bindArray);
                $row = $zdbh->returnRow(); 
			    $selected_id = $row['vh_fk_id'];                
              }
           }
                            
            foreach ($domains as $row){
                $selected_yn = ($row['id'] == $selected_id) ? ' selected="selected"':'';
                array_push($res, array('name' => $row['name'],'id' => $row['id'], 'selected_yn' => $selected_yn));
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getCSFR_Tag() {
        return runtime_csfr::Token();
    }

    static function getModuleName() {
        $module_name = ui_module::GetModuleName();
        return $module_name;

    }

    static function getModuleIcon() {
        global $controller;
        $module_icon = "/modules/" . $controller->GetControllerRequest('URL', 'module') . "/assets/icon.png";
        return $module_icon;
    }


    static function getModuleDesc() {
        $message = ui_language::translate(ui_module::GetModuleDescription());
        return $message;
    }



    static function getisForwardDomain() {
        global $controller;
        $urlvars = $controller->GetAllControllerRequests('URL');
        $formvars = $controller->GetAllControllerRequests('FORM');
		
		if(!$formvars){
			return false;
		}
		
		if (!fs_director::CheckForEmptyValue(self::CheckForwardForErrors($formvars['domain_id'],$formvars['fd_name']))) {
	        if ((isset($urlvars['action'])) && ($urlvars['action'] == "ConfirmDomainForward"))
	            return true;

		}
        return false;
    }
	
    static function getisEditForward() {
        global $controller;
        $urlvars = $controller->GetAllControllerRequests('URL');
        $formvars = $controller->GetAllControllerRequests('FORM');
		
		if(!$formvars){
			return false;
		}
		
		if (isset($formvars['id']) && !fs_director::CheckForEmptyValue($formvars['id'])){
	        if ((isset($urlvars['action'])) && ($urlvars['action'] == "EditForward" || ($urlvars['action'] == "UpdateForward" && !self::$ok)))
	            return true;

		}
        return false;
    }
    
    static function doEditForward(){
        return self::getisEditForward();
    }

	static function getForwardDomainName(){
        global $controller, $zdbh;
		$domain_id = (int) $controller->GetControllerRequest('FORM', 'id');
        if ($domain_id) {
			$sql = "SELECT fd_name FROM x_forwarded_domains WHERE fd_id_pk=:id AND fd_deleted_ts IS NULL";
            $bindArray = array(':id' => $domain_id);                                        
            $zdbh->bindQuery($sql, $bindArray);
            $row = $zdbh->returnRow(); 
			return $row['fd_name'];
        } else {
            return "";
        }
	}

	static function getForwardDomainWWWChecked(){
        global $controller, $zdbh;
		$domain_id = (int) $controller->GetControllerRequest('FORM', 'id');
        if ($domain_id) {
			$sql = "SELECT www_yn FROM x_forwarded_domains WHERE fd_id_pk=:id AND fd_deleted_ts IS NULL";
            $bindArray = array(':id' => $domain_id);                                        
            $zdbh->bindQuery($sql, $bindArray);
            $row = $zdbh->returnRow(); 
			if($row['www_yn'] == 1){
			    return ' checked="checked"';
			} else {
			    return '';
			}
        } else {
            return '';
        }
	}

	static function getForwardedDomainType(){
        global $controller, $zdbh;
		$domain_id = (int) $controller->GetControllerRequest('FORM', 'id');
        $fd_type_id = 1;
        
        if ($domain_id) {
			$sql = "SELECT fd_type_id FROM x_forwarded_domains WHERE fd_id_pk=:id AND fd_deleted_ts IS NULL";
            $bindArray = array(':id' => $domain_id);                                        
            $zdbh->bindQuery($sql, $bindArray);
            $row = $zdbh->returnRow();
            $fd_type_id = $row['fd_type_id'];
        }
        
        //redirect option
        $ret_html = '<option value="1"';
        
        if($fd_type_id == 1){
            $ret_html .= ' selected="selected"';
        }
        $ret_html .= '>Redirect</option>';

        //forward option
        $ret_html .= '<option value="2"';
        
        if($fd_type_id == 2){
            $ret_html .= ' selected="selected"';
        }
        $ret_html .= '>Forward</option>';
        
        return $ret_html;
	}

	static function getForwardProtocols(){
        global $controller, $zdbh;
		$domain_id = (int) $controller->GetControllerRequest('FORM', 'id');
        $fd_protocol = 'http';
        
        if ($domain_id) {
			$sql = "SELECT fd_protocol FROM x_forwarded_domains WHERE fd_id_pk=:id AND fd_deleted_ts IS NULL";
            $bindArray = array(':id' => $domain_id);                                        
            $zdbh->bindQuery($sql, $bindArray);
            $row = $zdbh->returnRow();
            $fd_protocol = $row['fd_protocol'];
        }
        
        //protocol options
        $ret_html = '<option value="http"';
        
        if($fd_protocol == 'http'){
            $ret_html .= ' selected="selected"';
        }
        $ret_html .= '>http://</option>';

        $ret_html .= '<option value="https"';
        
        if($fd_protocol == 'https'){
            $ret_html .= ' selected="selected"';
        }
        $ret_html .= '>https://</option>';
        
        return $ret_html;
	}

	static function getForwardDomainID(){
        global $controller;
        if ($controller->GetControllerRequest('FORM', 'id')){
            return $controller->GetControllerRequest('FORM', 'id');
        } else {
            return "";
        }
	}

    static function getisDeleteForward(){
        global $controller;
        $urlvars = $controller->GetAllControllerRequests('URL');
        $formvars = $controller->GetAllControllerRequests('FORM');

        if((isset($urlvars['action']) && $urlvars['action'] == 'ConfirmDeleteForward')){
            return true;
        }
        return false;
    }
    
    static function SetWriteApacheConfigTrue(){
        global $zdbh;
        // update apache_changed
		$sql = $zdbh->prepare("UPDATE x_settings SET so_value_tx='true'	WHERE so_name_vc='apache_changed'");
		$sql->execute();        
    }
    
    /*static function doConfirmDeleteForward(){
        return true;
    }*/

    static function doDeleteForward() {
        global $controller;
        runtime_csfr::Protect();
        $formvars = $controller->GetAllControllerRequests('FORM');
        
        if((int) $formvars['fd_id']){
            if (self::ExecuteDeleteForward((int) $formvars['fd_id'])) {
                self::SetWriteApacheConfigTrue();
                self::$ok = true;
                return true;
            }        
        } else {
            self::$error = true;
            return false;
        }        
        return;
    }
    

	static function doForwardDomain(){
        global $controller;
        runtime_csfr::Protect();
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');

        //validate form submission
		/*if (!fs_director::CheckForEmptyValue(self::CheckForwardForErrors($formvars['domain_id'],$formvars['fd_name']))){
	         return false;
		}*/
		
		if(fs_director::CheckForEmptyValue(self::getCanAddForward())){
            self::$limitreached = true;
            return false;		    
		}
        
        if (self::ExecuteForwardDomain($formvars)){
           self::SetWriteApacheConfigTrue();
           self::$ok = true;
           return true;			
        } else {
           return false;
        }
        return;		
	}

	static function doUpdateForward(){
        global $controller;
        runtime_csfr::Protect();
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');

        //validate form submission
		/*if (!fs_director::CheckForEmptyValue(self::CheckForwardForErrors($formvars['domain_id'],$formvars['fd_name']))){
	         return false;
		}*/
        
        if (self::ExecuteUpdateForward($formvars)){
           self::SetWriteApacheConfigTrue();
           self::$ok = true;
           self::$editing = false;
           return true;			
        } else {
           return false;
        }
        return;		
	}

    static function ExecuteForwardDomain($data) {
        global $zdbh;
        $retval = false;
        
        runtime_hook::Execute('OnBeforeForwardDomain');
		if (!fs_director::CheckForEmptyValue(self::CheckForwardForErrors($data['domain_id'],$data['fd_name']))){
	       $currentuser = ctrl_users::GetUserDetail();
           $data['www_yn'] = isset($data['www_yn']) ? $data['www_yn'] : 0;
           $data['fd_type_id'] = isset($data['fd_type_id']) ? $data['fd_type_id'] : 1;
           $data['fd_protocol'] = isset($data['fd_protocol']) ? $data['fd_protocol'] : '';

           $sql = $zdbh->prepare("INSERT INTO x_forwarded_domains (vh_fk_id,fd_acc_fk,fd_name,
                                   www_yn,fd_type_id,fd_protocol,fd_created_ts) VALUES (
                                   :domain_id, :user_id, :name, :www_yn, :type_id, :protocol, :time)");
           $sql->bindParam(':domain_id', $data['domain_id']);
           $sql->bindParam(':name', $data['fd_name']);
           $sql->bindParam(':user_id', $currentuser['userid']);
           $sql->bindParam(':www_yn', $data['www_yn']);
           $sql->bindParam(':type_id', $data['fd_type_id']);
           $sql->bindParam(':protocol', $data['fd_protocol']);
           $sql->bindParam(':time', time());
           $sql->execute();
           
		   $retval = true;
		}
        runtime_hook::Execute('OnAfterForwardDomain');
		

        return $retval;
    }

    static function ExecuteUpdateForward($data){
        global $zdbh;
        $retval = false;
        
        runtime_hook::Execute('OnBeforeUpdateForwardDomain');
		if (!fs_director::CheckForEmptyValue(self::CheckForwardForErrors($data['domain_id'],$data['fd_name']))){
	       $currentuser = ctrl_users::GetUserDetail();
           $data['www_yn'] = isset($data['www_yn']) ? $data['www_yn'] : 0;
           $data['fd_type_id'] = isset($data['fd_type_id']) ? $data['fd_type_id'] : 1;

           $sql = $zdbh->prepare("UPDATE x_forwarded_domains SET vh_fk_id=:domain_id,fd_name=:name,
                                   www_yn=:www_yn,fd_type_id=:type_id,fd_protocol=:protocol 
                                   WHERE fd_id_pk=:id");
           $sql->bindParam(':domain_id', $data['domain_id']);
           $sql->bindParam(':name', $data['fd_name']);
           $sql->bindParam(':id', self::getForwardDomainID());
           $sql->bindParam(':www_yn', $data['www_yn']);
           $sql->bindParam(':type_id', $data['fd_type_id']);
           $sql->bindParam(':protocol', $data['fd_protocol']);
           $sql->execute();
           
		   $retval = true;
		}
        runtime_hook::Execute('OnAfterUpdateForwardDomain');
		

        return $retval;
    }

    static function ExecuteDeleteForward($id){
        global $zdbh,$controller;
    
        $currentuser = ctrl_users::GetUserDetail();

           //delete domain forward
           runtime_hook::Execute('OnBeforeDeleteDomainForward');
           $sql = $zdbh->prepare("UPDATE x_forwarded_domains SET fd_deleted_ts='".time()."' 
                                    WHERE fd_id_pk=:id AND fd_acc_fk=:user_id");
           $sql->bindParam(':user_id', $currentuser['userid']);
           $sql->bindParam(':id', $id);
           $sql->execute();

           runtime_hook::Execute('OnAfterDeleteDomainForward');
           
           self::$complete = true;
           return true;            

    }




    static function getResult() {
        if (!fs_director::CheckForEmptyValue(self::$badname)) {
            return ui_sysmessage::shout(ui_language::translate("Please specify a valid forwarded domain and try again."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$emptyforward)) {
            return ui_sysmessage::shout(ui_language::translate("Forwarded domain cannot be empty."), "zannounceerror");
        }

        if (!fs_director::CheckForEmptyValue(self::$emptydomain)) {
            return ui_sysmessage::shout(ui_language::translate("Please select a domain to forward to."), "zannounceerror");
        }

        if (!fs_director::CheckForEmptyValue(self::$ok)) {
            return ui_sysmessage::shout(ui_language::translate("Process completed successfully."), "zannounceok");
        }
        if (!fs_director::CheckForEmptyValue(self::$domainexists)) {
            return ui_sysmessage::shout(ui_language::translate("That domain is not available."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$forwardexists)) {
            return ui_sysmessage::shout(ui_language::translate("Forwarded Domain is not available."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$limitreached)) {
            return ui_sysmessage::shout(ui_language::translate("Cannot add new forward, maximum domain forwarders limit reached."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$error)) {
            return ui_sysmessage::shout(ui_language::translate("An error has occurred while executing your request, please check your input and try again."), "zannounceerror");
        }
        return;
    }

    /**
     * Webinterface sudo methods.
     */
}

?>
