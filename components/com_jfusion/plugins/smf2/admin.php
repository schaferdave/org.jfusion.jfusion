<?php

/**
* @package JFusion_SMF
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * Load the JFusion framework
 */
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.jfusion.php');
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.abstractadmin.php');

/**
 * JFusion Admin Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 * @package JFusion_SMF
 */
class JFusionAdmin_smf2 extends JFusionAdmin{

    /**
     * @return string
     */
    function getJname()
    {
        return 'smf2';
    }

    /**
     * @return string
     */
    function getTablename()
    {
        return 'members';
    }

    /**
     * @param string $forumPath
     * @return array
     */
    function setupFromPath($forumPath)
    {
        //check for trailing slash and generate file path
        if (substr($forumPath, -1) == DS) {
            $myfile = $forumPath . 'Settings.php';
        } else {
            $myfile = $forumPath . DS. 'Settings.php';
        }
        //try to open the file
        $params = array();
        if (($file_handle = @fopen($myfile, 'r')) === FALSE) {
            JError::raiseWarning(500,JText::_('WIZARD_FAILURE'). ": $myfile " . JText::_('WIZARD_MANUAL'));
        } else {
            //parse the file line by line to get only the config variables
            $file_handle = fopen($myfile, 'r');
            $config = array();
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                if (strpos($line, '$') === 0) {
                    $vars = explode("'", $line);
                     if(isset($vars[1]) && isset($vars[0])){
	                    $name = trim($vars[0], ' $=');
    	                $value = trim($vars[1], ' $=');
        	            $config[$name] = $value;
                    }
                }
            }
            fclose($file_handle);
            //Save the parameters into the standard JFusion params format
            $params['database_host'] = isset($config['db_server']) ? $config['db_server'] : '';
            $params['database_type'] = 'mysql';
            $params['database_name'] = isset($config['db_name']) ? $config['db_name'] : '';
            $params['database_user'] = isset($config['db_user']) ? $config['db_user'] : '';
            $params['database_password'] = isset($config['db_passwd']) ? $config['db_passwd'] : '';
            $params['database_prefix'] = isset($config['db_prefix']) ? $config['db_prefix'] : '';
            $params['source_url'] = isset($config['boardurl']) ? $config['boardurl'] : '';
            $params['cookie_name'] = isset($config['cookiename']) ? $config['cookiename'] : '';
            $params['source_path'] = $forumPath;
        }
        return $params;
    }

    /**
     * @return array
     */
    function getUserList()
    {
        // initialise some objects
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT member_name as username, email_address as email from #__members';
        $db->setQuery($query );
        $userlist = $db->loadObjectList();

        return $userlist;
    }

    /**
     * @return int
     */
    function getUserCount()
    {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT count(*) from #__members';
        $db->setQuery($query );

        //getting the results
        return $db->loadResult();
    }

    /**
     * get default user group list
     *
     * @return array array with object with default user group list
     */
    function getUsergroupList()
    {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT id_group as id, group_name as name FROM #__membergroups WHERE min_posts = -1';
        $db->setQuery($query);
        $usergrouplist = $db->loadObjectList();
        //append the default usergroup
        $default_group = new stdClass;
        $default_group->id = 0;
        $default_group->name = 'Default User';
        $usergrouplist[] = $default_group;
        return $usergrouplist;
    }

    /**
     * @return string
     */
    function getDefaultUsergroup()
    {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),null);
        $usergroup_id = $usergroups[0];

        if ($usergroup_id==0) {
            return "Default Usergroup";
        }

        //we want to output the usergroup name
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT group_name FROM #__membergroups WHERE id_group = ' . $usergroup_id;
        $db->setQuery($query );
        return $db->loadResult();
    }

    /**
     * return list of post groups
     *
     * @return object with default user group
     */
    function getUserpostgroupList()
    {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT id_group as id, group_name as name FROM #__membergroups WHERE min_posts != -1';
        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * @return bool
     */
    function allowRegistration()
    {

        $db = JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT value FROM #__settings WHERE variable ='registration_method';";
        $db->setQuery($query );
        $new_registration = $db->loadResult();

        if ($new_registration == 3) {
            $result = false;
            return $result;
        } else {
            $result = true;
            return $result;
        }
    }

    /**
     * @return string
     */
    function generateRedirectCode()
	{
        	$params = JFusionFactory::getParams($this->getJname());
        	$joomla_params = JFusionFactory::getParams('joomla_int');
		    $joomla_url = $joomla_params->get('source_url');
    		$joomla_itemid = $params->get('redirect_itemid');

			//create the new redirection code
/*
$pattern = \'#action=(login|admin|profile|featuresettings|news|packages|detailedversion|serversettings|theme|manageboards|postsettings|managecalendar|managesearch|smileys|manageattachments|viewmembers|membergroups|permissions|regcenter|ban|maintain|reports|viewErrorLog|optimizetables|detailedversion|repairboards|boardrecount|convertutf8|helpadmin|packageget)#\';
 */
			$redirect_code = '
//JFUSION REDIRECT START
//SET SOME VARS
$joomla_url = \''. $joomla_url . '\';
$joomla_itemid = ' . $joomla_itemid .';
	';
		    $redirect_code .= '
if(!defined(\'_JEXEC\') && strpos($_SERVER[\'QUERY_STRING\'], \'dlattach\') === false && strpos($_SERVER[\'QUERY_STRING\'], \'verificationcode\') === false)';

		    $redirect_code .= '
{
	$pattern = \'#action=(login|logout)#\';
	if ( !preg_match( $pattern , $_SERVER[\'QUERY_STRING\'] ) ) {
		$file = $_SERVER["SCRIPT_NAME"];
		$break = explode(\'/\', $file);
		$pfile = $break[count($break) - 1];
		$jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$pfile. \'&\' . $_SERVER[\'QUERY_STRING\'];
		header(\'Location: \' . $jfusion_url);
		exit;
	}
}
//JFUSION REDIRECT END
';
	return $redirect_code;
	}

    function enableRedirectMod()
    {
    	$error = 0;
    	$error = 0;
    	$reason = '';
    	$mod_file = $this->getModFile('index.php',$error,$reason);

		if($error == 0) {
			//get the joomla path from the file
			jimport('joomla.filesystem.file');
			$file_data = JFile::read($mod_file);
	      	preg_match_all('/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms',$file_data,$matches);

			//remove any old code
			if(!empty($matches[1][0])){
		      	$search = '/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms';
		      	$file_data = preg_replace($search,'',$file_data);

			}

			$redirect_code = $this->generateRedirectCode();

	      	$search = '/\<\?php/si';
			$replace = '<?php' . $redirect_code;
	      	$file_data = preg_replace($search,$replace,$file_data);
			JFile::write($mod_file, $file_data);
		}
    }

    /**
     * @return int
     */
    function disableRedirectMod()
    {
    	$error = 0;
    	$reason = '';
    	$mod_file = $this->getModFile('index.php',$error,$reason);

		if($error == 0) {
			//get the joomla path from the file
			jimport('joomla.filesystem.file');
			$file_data = JFile::read($mod_file);
	      	$search = '/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/si';
	      	$file_data = preg_replace($search,'',$file_data);
            if (!JFile::write($mod_file, $file_data)) {
                $error = 1;
            }
		}
        return $error;
    }

    /**
     * @param $name
     * @param $value
     * @param $node
     * @param $control_name
     * @return string
     */
    function showRedirectMod($name, $value, $node, $control_name)
    {
    	$error = 0;
    	$reason = '';
    	$mod_file = $this->getModFile('index.php',$error,$reason);

		if($error == 0) {
			//get the joomla path from the file
			jimport('joomla.filesystem.file');
			$file_data = JFile::read($mod_file);
	      	preg_match_all('/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms',$file_data,$matches);

			//compare it with our joomla path
			if(empty($matches[1][0])){
	        	$error = 1;
	        	$reason = JText::_('MOD_NOT_ENABLED');
			}
		}

		//add the javascript to enable buttons
        if ($error == 0) {
            //return success
            $text = JText::_('REDIRECTION_MOD') . ' ' . JText::_('ENABLED');
            $disable = JText::_('MOD_DISABLE');
            $update = JText::_('MOD_UPDATE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_good.png" height="20" width="20">{$text}
            <a href="javascript:void(0);" onclick="return module('disableRedirectMod')">{$disable}</a>
            <a href="javascript:void(0);" onclick="return module('enableRedirectMod')">{$update}</a>
HTML;
            return $output;
        } else {
            $text = JText::_('REDIRECTION_MOD') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $enable = JText::_('MOD_ENABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_bad.png" height="20" width="20">{$text}
            <a href="javascript:void(0);" onclick="return module('enableRedirectMod')">{$enable}</a>
HTML;
            return $output;
        }
    }

    /**
     * uninstall function is to disable verious mods
     *
     * @return array
     */
    function uninstall()
    {
        $error = $this->disableRedirectMod();

        if (!empty($error)) {
           $reason = JText::_('REDIRECT_MOD_UNINSTALL_FAILED');
           return array(false, $reason);
        }

        return array(true, '');
    }

    /**
     * do plugin support multi usergroups
     *
     * @return bool
     */
    function isMultiGroup()
	{
		return false;
	}

    /**
     * do plugin support multi usergroups
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
	{
		return 'JNO';
	}
}

