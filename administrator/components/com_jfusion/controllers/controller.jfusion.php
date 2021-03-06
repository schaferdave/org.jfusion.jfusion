<?php

/**
 * This is the jfusion admin controller
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   ControllerAdmin
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Load the JFusion framework
 */
jimport('joomla.application.component.controller');
jimport('joomla.application.component.view');
require_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'import.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusionadmin.php';
/**
 * JFusion Controller class
 *
 * @category  JFusion
 * @package   ControllerAdmin
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionController extends JControllerLegacy
{
    /**
     * @param bool $cachable
     * @param bool $urlparams
     *
     * @return JController|void
     */
    function display($cachable = false, $urlparams = false) {
        parent::display();
    }

    /**
     * Display the results of the wizard set-up
     *
     * @return void
     */
    function wizardresult()
    {
        //set jname as a global variable in order for elements to access it.
        global $jname;
        //find out the submitted values
        $jname = JFactory::getApplication()->input->get('jname');
        $post = JFactory::getApplication()->input->post->get('params', array(), 'array');

	    //check to see data was posted
	    $msg = JText::_('WIZARD_FAILURE');
	    $msgType = 'warning';
	    if ($jname && $post) {
		    try {
			    //Initialize the forum
			    $JFusionPlugin = JFusionFactory::getAdmin($jname);

			    if (substr($post['source_path'], -1) != DIRECTORY_SEPARATOR) {
				    $post['source_path'] .= DIRECTORY_SEPARATOR;
			    }

			    try {
				    $params = $JFusionPlugin->setupFromPath($post['source_path']);
			    } catch (Exception $e) {
				    JFusionFunction::raiseError($e, $JFusionPlugin->getJname());
				    $params = array();
			    }

			    if (!empty($params)) {
				    //save the params first in order for elements to utilize data
				    $JFusionPlugin->saveParameters($params, true);

				    //make sure the usergroup params are available on first view
				    $status = 0;
				    try {
					    if ($JFusionPlugin->checkConfig()) {
						    $status = 1;
					    }
				    } catch (Exception $e) {
					    JFusionFunction::raiseError($e, $JFusionPlugin->getJname());
				    }
				    $JFusionPlugin->updateStatus($status);
				    $this->setRedirect('index.php?option=com_jfusion&task=plugineditor&jname=' . $jname, JText::_('WIZARD_SUCCESS'), 'message');
			    } else {
				    $this->setRedirect('index.php?option=com_jfusion&task=plugineditor&jname=' . $jname);
			    }
		    } catch (Exception $e) {
			    $this->setRedirect('index.php?option=com_jfusion&task=plugineditor&jname=' . $jname, $e->getMessage(), 'warning');
		    }
	    } else {
		    $this->setRedirect('index.php?option=com_jfusion&task=plugindisplay', $msg, $msgType);
	    }
    }

    /**
     * Function to change the master/slave/encryption settings in the jos_jfusion table
     *
     * @return void
     */
    function changesettings()
    {
	    try {
		    //find out the posted ID of the JFusion module to publish
		    $jname = JFactory::getApplication()->input->get('jname');
		    $field_name = JFactory::getApplication()->input->get('field_name');
		    $field_value = JFactory::getApplication()->input->get('field_value');
		    //check to see if an integration was selected
		    $db = JFactory::getDBO();
		    if ($jname) {
			    if ($field_name == 'master') {
				    //If a master is being set make sure all other masters are disabled first
				    $query = $db->getQuery(true)
					    ->update('#__jfusion')
					    ->set('master = 0');
				    $db->setQuery($query);
				    $db->execute();
			    }
			    //perform the update
			    $query = $db->getQuery(true)
				    ->update('#__jfusion')
				    ->set($field_name . ' = ' . $db->quote($field_value))
				    ->where('name = ' . $db->quote($jname));
			    $db->setQuery($query);
			    $db->execute();

			    //get the new plugin settings
			    $query = $db->getQuery(true)
				    ->select('*')
				    ->from('#__jfusion')
				    ->where('name = ' . $db->quote($jname));
			    $db->setQuery($query);
			    $result = $db->loadObject();
			    //disable a slave when it is turned into a master
			    if ($field_name == 'master' && $field_value == '1' && $result->slave == '1') {
				    $query = $db->getQuery(true)
					    ->update('#__jfusion')
					    ->set('slave = 0')
					    ->where('name = ' . $db->quote($jname));
				    $db->setQuery($query);
				    $db->execute();
			    }
			    //disable a master when it is turned into a slave
			    if ($field_name == 'slave' && $field_value == '1' && $result->master == '1') {
				    $query = $db->getQuery(true)
					    ->update('#__jfusion')
					    ->set('master = 0')
					    ->where('name = ' . $db->quote($jname));
				    $db->setQuery($query);
				    $db->execute();
			    }
			    //auto enable the auth and dual login for newly enabled plugins
			    if (($field_name == 'slave' || $field_name == 'master') && $field_value == '1') {
				    $query = $db->getQuery(true)
					    ->select('dual_login')
					    ->from('#__jfusion')
					    ->where('name = ' . $db->quote($jname));
				    $db->setQuery($query);
				    $dual_login = $db->loadResult();
				    if ($dual_login > 1) {
					    //only set the encryption if dual login is disabled
					    $query = $db->getQuery(true)
						    ->update('#__jfusion')
						    ->set('check_encryption = 1')
						    ->where('name = ' . $db->quote($jname));
					    $db->setQuery($query);
					    $db->execute();
				    } else {
					    $query = $db->getQuery(true)
						    ->update('#__jfusion')
						    ->set('dual_login = 1')
						    ->set('check_encryption = 1')
						    ->where('name = ' . $db->quote($jname));
					    $db->setQuery($query);
					    $db->execute();
				    }
			    }
			    //auto disable the auth and dual login for newly disabled plugins
			    if (($field_name == 'slave' || $field_name == 'master') && $field_value == '0') {
				    //only set the encryption if dual login is disabled
				    $query = $db->getQuery(true)
					    ->update('#__jfusion')
					    ->set('dual_login = 0')
					    ->set('check_encryption = 0')
					    ->where('name = ' . $db->quote($jname));
				    $db->setQuery($query);
				    $db->execute();
			    }
		    } else {
				throw new RuntimeException('NO_JNAME');
		    }
		    /**
		     * @ignore
		     * @var $view jfusionViewplugindisplay
		     */
		    $view = $this->getView('plugindisplay', 'html');
		    $plugins = $view->getPlugins();
			$data = new stdClass();
		    $data->pluginlist = $view->generateListHTML($plugins);
		    echo new JResponseJson($data);
	    } catch (Exception $e) {
			echo new JResponseJson($e);
	    }
	    exit();
    }

    /**
     * Function to save the JFusion plugin parameters
     *
     * @return void
     */
    function saveconfig()
    {
        //set jname as a global variable in order for elements to access it.
        global $jname;
        //get the posted variables
        $post = JFactory::getApplication()->input->post->get('params', array(), 'array');
        $jname = JFactory::getApplication()->input->post->getString('jname', '');
	    $action = JFactory::getApplication()->input->get('action');
	    $msg = $msgType = null;
	    try {
		    if (empty($post) || empty($jname)) {
			    throw new RuntimeException(JText::_('SAVE_FAILURE'));
		    }

		    $JFusionPlugin = JFusionFactory::getAdmin($jname);
		    if (!$JFusionPlugin->saveParameters($post)) {
			    throw new RuntimeException(JText::_('SAVE_FAILURE'));
		    } else {
			    $status = 0;
			    try {
				    if ($JFusionPlugin->checkConfig()) {
					    $status = 1;
				    }
				    $JFusionPlugin->updateStatus($status);
			    } catch (Exception $e) {
				    $JFusionPlugin->updateStatus($status);
				    throw $e;
			    }

			    if ($status) {
				    $msg = $jname . ': ' . JText::_('SAVE_SUCCESS');
				    $msgType = 'message';
				    //check for any custom commands
				    $customcommand = JFactory::getApplication()->input->get('customcommand');
				    if (!empty($customcommand)) {
					    $customarg1 = JFactory::getApplication()->input->getString('customarg1', null);
					    $customarg2 = JFactory::getApplication()->input->getString('customarg2', null);
					    $JFusionPlugin = JFusionFactory::getAdmin($jname);
					    if (method_exists($JFusionPlugin, $customcommand)) {
						    $JFusionPlugin->$customcommand($customarg1, $customarg2);
					    }
				    }
			    }
		    }
	    } catch (Exception $e) {
		    $msg = $jname . ': ' . $e->getMessage();
		    $msgType = 'error';
	    }
	    if ($action == 'apply') {
		    $this->setRedirect('index.php?option=com_jfusion&task=plugineditor&jname=' . $jname, $msg, $msgType);
	    } else {
		    $this->setRedirect('index.php?option=com_jfusion&task=plugindisplay', $msg, $msgType);
	    }
    }

    /**
     * Resumes a usersync if it has stopped
     *
     * @return void
     */
    function syncresume()
    {
	    try {
		    $syncid = JFactory::getApplication()->input->get->get('syncid', '');
		    $db = JFactory::getDBO();

		    $query = $db->getQuery(true)
			    ->select('syncid')
			    ->from('#__jfusion_sync')
			    ->where('syncid =' . $db->quote($syncid));

		    $db->setQuery($query);

		    $syncdata = array();
		    if ($db->loadResult()) {
			    $syncdata = JFusionUsersync::getSyncdata($syncid);
			    if (is_array($syncdata)) {
				    //start the usersync
				    $plugin_offset = (!empty($syncdata['plugin_offset'])) ? $syncdata['plugin_offset'] : 0;
				    //start at the next user
				    $user_offset = (!empty($syncdata['user_offset'])) ? $syncdata['user_offset'] : 0;
				    if (JFactory::getApplication()->input->get('userbatch')) {
					    $syncdata['userbatch'] = JFactory::getApplication()->input->get('userbatch');
				    }
				    JFusionUsersync::syncExecute($syncdata, $syncdata['action'], $plugin_offset, $user_offset);
			    } else {
				    throw new RuntimeException(JText::_('SYNC_FAILED_TO_LOAD_SYNC_DATA'));
			    }
		    } else {
			    throw new RuntimeException(JText::sprintf('SYNC_ID_NOT_EXIST', $syncid));
		    }
	        echo new JResponseJson($syncdata);
        } catch (Exception $e) {
			echo new JResponseJson($e);
		}
		exit();
    }

    /**
     * sync process
     *
     * @return void
     */
    function syncprogress()
    {
	    try {
		    $syncid = JFactory::getApplication()->input->get->get('syncid', '');

		    $syncdata = JFusionUsersync::getSyncdata($syncid);
		    echo new JResponseJson($syncdata);
	    } catch (Exception $e) {
		    echo new JResponseJson($e);
	    }
	    exit();
    }

    /**
     * Displays the usersync error screen
     *
     * @return void
     */
    function resolvesyncerror()
    {
	    $syncid = JFactory::getApplication()->input->post->get('syncid', '');
	    try {
		    $syncError = JFactory::getApplication()->input->post->get('syncError', array(), 'array');
		    if ($syncError) {
			    //apply the submitted sync error instructions
			    JFusionUsersync::syncError($syncid, $syncError);
		    }
		    $this->setRedirect('index.php?option=com_jfusion&task=syncerror&syncid=' . $syncid);
	    } catch (Exception $e) {
		    $this->setRedirect('index.php?option=com_jfusion&task=syncerror&syncid=' . $syncid, $e->getMessage(), 'error');
	    }

    }

    /**
     * Initiates the sync
     *
     * @return void
     */
    function syncinitiate()
    {
	    try {
	        //check to see if the sync has already started
	        $syncid = JFactory::getApplication()->input->get('syncid');
	        $action = JFactory::getApplication()->input->get('action');
	        if (!empty($syncid)) {
	            //clear sync in progress catch in case we manually stopped the sync so that the sync will continue
	            JFusionUsersync::changeSyncStatus($syncid, 0);
	        }

	        $syncdata = array();
	        $syncdata['completed'] = false;
	        $syncdata['sync_errors'] = 0;
	        $syncdata['total_to_sync'] = 0;
	        $syncdata['synced_users'] = 0;
	        $syncdata['userbatch'] = JFactory::getApplication()->input->getInt('userbatch', 100);
		    if ($syncdata['userbatch'] < 1 ) {
			    $syncdata['userbatch'] = 1;
		    }
	        $syncdata['user_offset'] = 0;
	        $syncdata['syncid'] = $syncid;
	        $syncdata['action'] = $action;

		    $db = JFactory::getDBO();

		    $query = $db->getQuery(true)
			    ->select('syncid')
			    ->from('#__jfusion_sync')
			    ->where('syncid =' . $db->quote($syncid));

		    $db->setQuery($query);
		    if (!$db->loadResult()) {
			    //sync has not started, lets get going :)
			    $slaves = JFactory::getApplication()->input->get('slave', array(), 'array');
			    $master_plugin = JFusionFunction::getMaster();
			    $master = $master_plugin->name;
			    $JFusionMaster = JFusionFactory::getAdmin($master);
			    if (empty($slaves)) {
				    throw new RuntimeException(JText::_('SYNC_NODATA'));
			    } else {
				    //initialise the slave data array
				    $slave_data = array();
				    //lets find out which slaves need to be imported into the Master
				    foreach ($slaves as $jname => $slave) {
					    if ($slave['perform_sync'] == $jname) {
						    $temp_data = array();
						    $temp_data['jname'] = $jname;
						    $JFusionPlugin = JFusionFactory::getAdmin($jname);
						    if ($action == 'master') {
							    $temp_data['total'] = $JFusionPlugin->getUserCount();
						    } else {
							    $temp_data['total'] = $JFusionMaster->getUserCount();
						    }
						    $syncdata['total_to_sync']+= $temp_data['total'];
						    //this doesn't change and used by usersync when limiting the number of users to grab at a time
						    $temp_data['total_to_sync'] = $temp_data['total'];
						    $temp_data['created'] = 0;
						    $temp_data['deleted'] = 0;
						    $temp_data['updated'] = 0;
						    $temp_data['error'] = 0;
						    $temp_data['unchanged'] = 0;
						    //save the data
						    $slave_data[] = $temp_data;
						    //reset the variables
						    unset($temp_data, $JFusionPlugin);
					    }
				    }
				    //format the syncdata for storage in the JFusion sync table
				    $syncdata['master'] = $master;
				    $syncdata['slave_data'] = $slave_data;
				    //save the submitted syncdata in order for AJAX updates to work
				    JFusionUsersync::saveSyncdata($syncdata);
				    //start the usersync
				    JFusionUsersync::syncExecute($syncdata, $action, 0, 0);
			    }
		    } else {
			    throw new RuntimeException(JText::_('SYNC_CANNOT_START'));
		    }
		    echo new JResponseJson($syncdata);
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e);
		    echo new JResponseJson($e);
	    }
		exit();
    }

    /**
     * Function to upload, parse & install JFusion plugins
     *
     * @return void
     */
    function installplugin()
    {
        include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.install.php';
        $model = new JFusionModelInstaller();
        $result = $model->install();

        $format = JFactory::getApplication()->input->get('format', 'html');
        if ($format == 'json') {
	        try {
		        $error = true;
		        /**
		         * @ignore
		         * @var $view jfusionViewplugindisplay
		         */
		        $view = $this->getView('plugindisplay', 'html');
		        $plugins = $view->getPlugins();
		        $result['pluginlist'] = $view->generateListHTML($plugins);

		        if ($result['status']) {
			        $error = false;
		        }
		        unset($result['status']);

		        echo new JResponseJson($result, null, $error);
            } catch (Exception $e) {
	            echo new JResponseJson($e);
            }
	        exit();
        } else {
            $this->setRedirect('index.php?option=com_jfusion&task=plugindisplay');
        }
    }

	/**
	 * install language
	 *
	 * @return void
	 */
	function installlanguage()
	{
		JFactory::getLanguage()->load('com_installer');
		require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_installer' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'install.php';
		$installer = JModelLegacy::getInstance('Install', 'InstallerModel');

		$installer->install();
		$this->setRedirect('index.php?option=com_jfusion&task=languages');
	}

	/**
	 * uninstall language
	 *
	 * @return void
	 */
	function uninstallanguage()
	{
		JFactory::getLanguage()->load('com_installer');

		require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_installer' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'manage.php';
		$manager = JModelLegacy::getInstance('Manage', 'InstallerModel');

		$eid = JFactory::getApplication()->input->getInt('eid', 0);
		if ($eid) {
			$manager->remove(array($eid));
		}
		$this->setRedirect('index.php?option=com_jfusion&task=languages');
	}

    function plugincopy()
    {
	    try {
	        $jname = JFactory::getApplication()->input->get('jname');
	        $new_jname = JFactory::getApplication()->input->get('new_jname');

	        //replace not-allowed characters with _
	        $new_jname = preg_replace('/([^a-zA-Z0-9_])/', '_', $new_jname);

		    $error = true;
	        //initialise response element
	        $result = array();

		    //check to see if an integration was selected
		    $db = JFactory::getDBO();

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__jfusion')
			    ->where('original_name IS NULL')
		        ->where('name LIKE ' . $db->quote($jname));

		    $db->setQuery($query);
		    $record = $db->loadResult();

		    $query = $db->getQuery(true)
			    ->select('id')
			    ->from('#__jfusion')
			    ->where('name = ' . $db->quote($new_jname));

		    $db->setQuery($query);
		    $exsist = $db->loadResult();
		    if ($exsist) {
			    throw new RuntimeException($new_jname . ' ' . JText::_('ALREADY_IN_USE'));
		    } else if ($jname && $new_jname && $record) {
			    $JFusionPlugin = JFusionFactory::getAdmin($jname);
			    if ($JFusionPlugin->multiInstance()) {
				    include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.install.php';
				    $model = new JFusionModelInstaller();
				    $result = $model->copy($jname, $new_jname);

				    if ($result['status']) {
					    $error = false;
					    $result['new_jname'] =  $new_jname;
				    }
				    unset($result['status']);
			    } else {
				    throw new RuntimeException(JText::_('CANT_COPY'));
			    }
		    } else {
				throw new RuntimeException(JText::_('NONE_SELECTED'));
		    }

		    /**
		     * @ignore
		     * @var $view jfusionViewplugindisplay
		     */
		    $view = $this->getView('plugindisplay', 'html');
		    $plugins = $view->getPlugins();
		    $result['pluginlist'] = $view->generateListHTML($plugins);
		    echo new JResponseJson($result, null, $error);
	    } catch (Exception $e) {
		    echo new JResponseJson($e);
	    }
	    exit();
    }

    /**
     * Function to uninstall JFusion plugins
     *
     * @return void
     */
    function uninstallplugin()
    {
	    try {
		    $error = true;
	        $jname = JFactory::getApplication()->input->get('jname');

	        //set uninstall options
	        $db = JFactory::getDBO();

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__jfusion')
			    ->where('original_name LIKE ' . $db->quote($jname));

	        $db->setQuery($query);
	        $copys = $db->loadResult();

	        //check to see if an integration was selected
	        if ($jname && $jname != 'joomla_int' && !$copys) {
	            include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.install.php';
	            $model = new JFusionModelInstaller();
	            $result = $model->uninstall($jname);

		        if ($result['status']) {
			        $error = false;
		        }
		        unset($result['status']);
	        } else {
		        throw new RuntimeException('JFusion ' . JText::_('PLUGIN') . ' ' . JText::_('UNINSTALL') . ' ' . JText::_('FAILED'));
	        }
	        $result['jname'] = $jname;
	        //output results
		    echo new JResponseJson($result, null, $error);
	    } catch (Exception $e) {
			echo new JResponseJson($e);
		}
		exit();
    }

    /**
     * Enables the JFusion Plugins
     *
     * @return void
     */
    function enableplugins()
    {
        if (JFusionFunctionAdmin::isConfigOk()) {
	        //enable the JFusion login behaviour, but we wanna make sure there is at least 1 master with good config
	        JFusionFunctionAdmin::changePluginStatus('joomla', 'authentication', 0);
	        JFusionFunctionAdmin::changePluginStatus('joomla', 'user', 0);
	        JFusionFunctionAdmin::changePluginStatus('jfusion', 'authentication', 1);
	        JFusionFunctionAdmin::changePluginStatus('jfusion', 'user', 1);
        }
	    $this->setRedirect('index.php?option=com_jfusion&task=cpanel');
    }

    /**
     * Disables the JFusion Plugins
     *
     * @return void
     */
    function disableplugins()
    {
        //restore the normal login behaviour
        JFusionFunctionAdmin::changePluginStatus('joomla', 'authentication', 1);
        JFusionFunctionAdmin::changePluginStatus('joomla', 'user', 1);
        JFusionFunctionAdmin::changePluginStatus('jfusion', 'authentication', 0);
        JFusionFunctionAdmin::changePluginStatus('jfusion', 'user', 0);
        $this->setRedirect('index.php?option=com_jfusion&task=cpanel');
    }

    /**
     * delete sync history
     *
     * @return void
     */
    function deletehistory()
    {
        $db = JFactory::getDBO();
        $syncid = JFactory::getApplication()->input->get('syncid', array(), 'array');
        if(!is_array($syncid) || empty($syncid)) {
            JFusionFunction::raiseWarning(JText::_('NO_SYNCID_SELECTED'));
        } else {
            foreach ($syncid as $key => $value) {
	            $query = $db->getQuery(true)
		            ->delete('#__jfusion_sync')
		            ->where('syncid = ' . $db->quote($key));

                $db->setQuery($query);
                $db->execute();

	            $query = $db->getQuery(true)
		            ->delete('#__jfusion_sync_details')
		            ->where('syncid = ' . $db->quote($key));

	            $db->setQuery($query);
                $db->execute();
            }
        }
	    $this->setRedirect('index.php?option=com_jfusion&task=synchistory');
    }

    /**
     * resolve error
     *
     * @return void
     */
    function resolveerror()
    {
        $syncid = JFactory::getApplication()->input->get('syncid', array(), 'array');
        if(!is_array($syncid) || empty($syncid)) {
            JFusionFunction::raiseWarning(JText::_('NO_SYNCID_SELECTED'));
	        $this->setRedirect('index.php?option=com_jfusion&task=synchistory');
        } else {
            foreach ($syncid as $key => $value) {
                //output the sync errors to the user
	            $this->setRedirect('index.php?option=com_jfusion&task=syncerror&syncid=', $key);
                break;
            }
        }
	    $this->setRedirect('index.php?option=com_jfusion&task=syncerror');
    }

    /**
     * Displays the JFusion PluginMenu Parameters
     *
     * @return void
     */
	function advancedparamsubmit()
	{
		$params = JFactory::getApplication()->input->get('params', array(), 'array');
		$ename = JFactory::getApplication()->input->get('ename');

		$multiselect = JFactory::getApplication()->input->get('multiselect');
		if ($multiselect) {
			$multiselect = true;
		} else {
			$multiselect = false;
		}

		$serParam = base64_encode(serialize($params));

		$session = JFactory::getSession();
		$hash = JFactory::getApplication()->input->get($ename);
		$session->set($hash, $serParam);

		$title = '';
		if (isset($params['jfusionplugin'])) {
			$title = $params['jfusionplugin'];
		} else if ($multiselect) {
			$del = '';
			if (is_array($params)) {
				foreach ($params as $value) {
					if (isset($value['jfusionplugin'])) {
						$title.= $del . $value['jfusionplugin'];
						$del = '; ';
					}
				}
			}
		}
		if (empty($title)) {
			$title = JText::_('NO_PLUGIN_SELECTED');
		}
		$js = '<script type="text/javascript">';
		$js .= <<<JS
            window.parent.JFusion.submitParams('{$ename}', '{$serParam}','{$title}');
JS;
		$js .= '</script>';
		echo $js;
	}

    function saveorder()
    {
	    try {
		    //split the value of the sort action
		    $sort_order = JFactory::getApplication()->input->getString('sort_order');
		    $ids = explode('|', $sort_order);
		    $db = JFactory::getDBO();

		    $result = array('status' => true, 'messages' => '');
		    /* run the update query for each id */
		    foreach($ids as $index=>$id)
		    {
			    if($id != '') {
				    $query = $db->getQuery(true)
					    ->update('#__jfusion')
					    ->set('ordering = ' .(int) $index)
					    ->where('name = ' . $db->quote($id));

				    $db->setQuery($query);
				    $db->execute();
			    }
		    }
		    /**
		     * @ignore
		     * @var $view jfusionViewplugindisplay
		     */
		    $view = $this->getView('plugindisplay', 'html');
		    $plugins = $view->getPlugins();
		    $result['pluginlist'] = $view->generateListHTML($plugins);
		    echo new JResponseJson($result);
	    } catch (Exception $e) {
		    echo new JResponseJson($e);
	    }
	    exit();
    }

    function import()
    {
        $jname = JFactory::getApplication()->input->get('jname');

        $xml = $error = null;
	    $mainframe = JFactory::getApplication();
	    try {
		    jimport('joomla.utilities.simplexml');
		    $file = JFactory::getApplication()->input->files->get('file');

		    $filename = JFactory::getApplication()->input->get('url');

		    if( !empty($filename) ) {
			    $filename = base64_decode($filename);
			    $ConfigFile = JFusionFunctionAdmin::getFileData($filename);
			    if (!empty($ConfigFile)) {
				    $xml = JFusionFunction::getXml($ConfigFile, false);
			    }
		    } else if($file['error'] > 0) {
			    switch ($file['error']) {
				    case UPLOAD_ERR_INI_SIZE:
					    $error = JText::_('UPLOAD_ERR_INI_SIZE');
					    break;
				    case UPLOAD_ERR_FORM_SIZE:
					    $error = JText::_('UPLOAD_ERR_FORM_SIZE');
					    break;
				    case UPLOAD_ERR_PARTIAL:
					    $error = JText::_('UPLOAD_ERR_PARTIAL');
					    break;
				    case UPLOAD_ERR_NO_FILE:
					    $error = JText::_('UPLOAD_ERR_NO_FILE');
					    break;
				    case UPLOAD_ERR_NO_TMP_DIR:
					    $error = JText::_('UPLOAD_ERR_NO_TMP_DIR');
					    break;
				    case UPLOAD_ERR_CANT_WRITE:
					    $error = JText::_('UPLOAD_ERR_CANT_WRITE');
					    break;
				    case UPLOAD_ERR_EXTENSION:
					    $error = JText::_('UPLOAD_ERR_EXTENSION');
					    break;
				    default:
					    $error = JText::_('UNKNOWN_UPLOAD_ERROR');
			    }
			    throw new RuntimeException( JText::_('ERROR') . ': ' . $error);
		    } else {
			    $filename = $file['tmp_name'];
			    $xml = JFusionFunction::getXml($filename);
		    }
		    if(!$xml) {
			    throw new RuntimeException(JText::_('ERROR_LOADING_FILE') . ': ' . $filename);
		    } else {
			    /**
			     * @ignore
			     * @var $val SimpleXMLElement
			     */
			    $info = $config = null;
			    foreach ($xml->children() as $val) {
				    switch ($val->getName()) {
					    case 'info':
						    $info = $val;
						    break;
					    case 'config':
						    $config = $val->children();
						    break;
				    }
			    }

			    if (!$info || !$config) {
				    throw new RuntimeException(JText::_('ERROR_FILE_SYNTAX') . ': ' . $file['type']);
			    } else {
				    $att = $info->attributes();
				    $original_name = (string)$att['original_name'];
				    $db = JFactory::getDBO();

				    $query = $db->getQuery(true)
					    ->select('name , original_name')
					    ->from('#__jfusion')
					    ->where('name = ' . $db->quote($jname));

				    $db->setQuery($query);
				    $plugin = $db->loadObject();

				    if ($plugin) {
					    $pluginname = $plugin->original_name ? $plugin->original_name : $plugin->name;
					    if ($pluginname == $original_name) {
						    $conf = array();
						    /**
						     * @ignore
						     * @var $val SimpleXMLElement
						     */
						    foreach ($config as $val) {
							    $att = $val->attributes();
							    $attName = (string)$att['name'];
							    $conf[$attName] = htmlspecialchars_decode((string)$val);
							    if (strpos($conf[$attName], 'a:') === 0) $conf[$attName] = unserialize($conf[$attName]);
						    }

						    $database_type = JFactory::getApplication()->input->get('database_type');
						    $database_host = JFactory::getApplication()->input->get('database_host');
						    $database_name = JFactory::getApplication()->input->get('database_name');
						    $database_user = JFactory::getApplication()->input->get('database_user');
						    $database_password = JFactory::getApplication()->input->get('database_password');
						    $database_prefix = JFactory::getApplication()->input->get('database_prefix');

						    if( !empty($database_type) ) $conf['database_type'] = $database_type;
						    if( !empty($database_host) ) $conf['database_host'] = $database_host;
						    if( !empty($database_name) ) $conf['database_name'] = $database_name;
						    if( !empty($database_user) ) $conf['database_user'] = $database_user;
						    if( !empty($database_password) ) $conf['database_password'] = $database_password;
						    if( !empty($database_prefix) ) $conf['database_prefix'] = $database_prefix;

						    $JFusionPlugin = JFusionFactory::getAdmin($jname);
						    if (!$JFusionPlugin->saveParameters($conf)) {
							    throw new RuntimeException(JText::_('SAVE_FAILURE'));
						    } else {
							    //update the status field
							    $status = 0;
							    try {
								    if ($JFusionPlugin->checkConfig()) {
									    $status = 1;
								    }
								    $JFusionPlugin->updateStatus($status);
							    } catch (Exception $e) {
								    $JFusionPlugin->updateStatus($status);
								    throw $e;
							    }
						    }
					    } else {
						    throw new RuntimeException(JText::_('PLUGIN_DONT_MATCH_XMLFILE'));
					    }
				    } else {
					    throw new RuntimeException(JText::_('PLUGIN_NOT_FOUNED'));
				    }
			    }
		    }
		    $mainframe->redirect('index.php?option=com_jfusion&task=plugineditor&jname=' . $jname, $jname . ': ' . JText::_('IMPORT_SUCCESS'));
	    } catch (Exception $e) {
		    JFusionFunction::raiseWarning($e, $jname);
		    $mainframe->redirect('index.php?option=com_jfusion&task=importexport&jname=' . $jname);
	    }
        exit();
    }

    function export()
    {
        $jname = JFactory::getApplication()->input->get('jname');
        $dbinfo = JFactory::getApplication()->input->get('dbinfo');

        $params = JFusionFactory::getParams($jname);
        $params = $params->toObject();
        jimport('joomla.utilities.simplexml');

        $arr = array();
        foreach ($params as $key => $val) {
            if(!$dbinfo && substr($key, 0, 8) == 'database' && substr($key, 0, 13) != 'database_type') {
                continue;
            }
            $arr[$key] = $val;
        }

	    $xml = JFusionFunction::getXml('<jfusionconfig></jfusionconfig>', false);

        $info = $xml->addChild('info');

        list($VersionCurrent, $RevisionCurrent) = JFusionFunctionAdmin::currentVersion(true);

        $info->addAttribute('jfusionversion', $VersionCurrent);
        $info->addAttribute('jfusionrevision', $RevisionCurrent);

        //get the current JFusion version number
        $filename = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR . 'jfusion.xml';
        if (file_exists($filename) && is_readable($filename)) {
            //get the version number
	        $element = JFusionFunction::getXml($filename);

            $info->addAttribute('pluginversion', (string)$element->version);
        } else {
            $info->addAttribute('pluginversion', 'UNKNOWN');
        }

        $info->addAttribute('date', date('F j, Y, H:i:s'));

        $info->addAttribute('jname', $jname);

        $db = JFactory::getDBO();

	    $query = $db->getQuery(true)
		    ->select('original_name')
		    ->from('#__jfusion')
		    ->where('name = ' . $db->quote($jname));

        $db->setQuery($query);
        $original_name = $db->loadResult();

	    $original_name = $original_name ? $original_name : $jname;

        $info->addAttribute('original_name', $original_name);

	    $config = $xml->addChild('config');
	    foreach ($arr as $key => $val) {
		    if (is_array($val)) $val = serialize($val);
		    $node = $config->addChild('key', $val);
		    $node->addAttribute('name', $key);
	    }
	    header('content-type: text/xml');
	    header('Content-disposition: attachment; filename=jfusion_' . $jname . '_config.xml');
	    header('Pragma: no-cache');
	    header('Expires: 0');
	    echo $xml->asXML();
	    exit();
    }

	/**
	 * resolve error
	 *
	 * @return void
	 */
	function saveusergroups()
	{
		$usergroups = JFactory::getApplication()->input->post->get('usergroups', null, 'ARRAY');
		$updateusergroups = JFactory::getApplication()->input->post->get('updateusergroups', null, 'ARRAY');
		$sort = JFactory::getApplication()->input->post->get('sort', null, 'ARRAY');

		$groups = array();
		if ($usergroups && $sort) {
			if (!isset($usergroups['joomla_int'])) {
				$usergroups['joomla_int'] = array();
			}
			foreach ($sort as $index => $id) {
				foreach ($usergroups as $jname => $group) {
					if (isset($group[$id])) {
						if ($group[$id] !== 'JFUSION_NO_USERGROUP') {
							$groups[$jname][$index] = $group[$id];
						} else {
							$groups[$jname][$index] = null;
						}
					} else {
						$groups[$jname][$index] = null;
					}
				}
			}
		}

		$master = JFusionFunction::getMaster();

		foreach ($groups as $jname => $plugin) {
			foreach ($plugin as $index => $group) {
				if ($group === null) {
					if ($index == 0) {
						JFusionFunction::raiseError(JText::_('NO_DEFAULT_GROUP_FOR_PAIR') . ': ' . ($index+1), $jname);
					} else if (($master && $master->name == $jname) || (isset($updateusergroups[$jname]) && $updateusergroups[$jname])) {
						JFusionFunction::raiseError(JText::_('NO_GROUP_FOR_PAIR') . ': ' . ($index+1), $jname);
					}
				}
			}
		}

		jimport('joomla.application.component.helper');
		$jfusion = JComponentHelper::getComponent('com_jfusion');

		$table = JTable::getInstance('extension');
		$table->load($jfusion->id); // pass your component id

		$jfusion->params->set('usergroups', $groups);
		$jfusion->params->set('updateusergroups', $updateusergroups);

		$post = array();
		$post['params'] = (string)$jfusion->params;
		$table->bind($post);
		// pre-save checks
		if (!$table->check()) {
			JFusionFunction::raiseWarning($table->getError());
		} else {
			// save the changes
			if (!$table->store()) {
				JFusionFunction::raiseWarning($table->getError());
			} else {
				JFusionFunction::raiseMessage(JText::_('USERGROUPS_SAVED'));
			}
		}
		$this->setRedirect('index.php?option=com_jfusion&task=usergroups');
	}
}
