<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Admin Class for vBulletin
 * For detailed descriptions on these functions please check JFusionAdmin
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_vbulletin extends JFusionUser
{
	/**
	 * @var $helper JFusionHelper_vbulletin
	 */
	var $helper;

	/**
	 * @param object $userinfo
	 * @param string $identifier_type
	 * @param int $ignore_id
	 * @return null|object
	 */
	function getUser($userinfo, $identifier_type = 'auto', $ignore_id = 0)
	{
		try {
			if($identifier_type == 'auto') {
				//get the identifier
				list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'u.username', 'u.email');
				if ($identifier_type == 'u.username') {
					//lower the username for case insensitivity purposes
					$identifier_type = 'LOWER(u.username)';
					$identifier = strtolower($identifier);
				}
			} else {
				$identifier_type = 'u.' . $identifier_type;
				$identifier = $userinfo;
			}

			// Get user info from database
			$db = JFusionFactory::getDatabase($this->getJname());

			$name_field = $this->params->get('name_field');

			$query = $db->getQuery(true)
				->select('u.userid, u.username, u.email, u.usergroupid AS group_id, u.membergroupids, u.displaygroupid, u.password, u.salt as password_salt, u.usertitle, u.customtitle, u.posts, u.username as name')
				->from('#__user AS u')
				->where($identifier_type . ' = ' . $db->quote($identifier));

			if ($ignore_id) {
				$query->where('u.userid != ' . $ignore_id);
			}

			$db->setQuery($query);
			$result = $db->loadObject();

			if ($result) {
				$query = $db->getQuery(true)
					->select('title')
					->from('#__usergroup')
					->where('usergroupid = ' . $result->group_id);

				$db->setQuery($query);
				$result->group_name = $db->loadResult();

				if (!empty($name_field)) {
					$query = $db->getQuery(true)
						->select($name_field)
						->from('#__userfield')
						->where('userid = ' . $result->userid);

					$db->setQuery($query);
					$name = $db->loadResult();
					if (!empty($name)) {
						$result->name = $name;
					}
				}
				//Check to see if they are banned
				$query = $db->getQuery(true)
					->select('userid')
					->from('#__userban')
					->where('userid = ' . $result->userid);

				$db->setQuery($query);
				if ($db->loadObject() || ($this->params->get('block_coppa_users', 1) && (int) $result->group_id == 4)) {
					$result->block = 1;
				} else {
					$result->block = 0;
				}

				//check to see if the user is awaiting activation
				$activationgroup = $this->params->get('activationgroup');

				if ($activationgroup == $result->group_id) {
					jimport('joomla.user.helper');
					$result->activation = JUserHelper::genRandomPassword(32);
				} else {
					$result->activation = '';
				}
			}
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
			$result = null;
		}
		return $result;
	}

	/**
	 * returns the name of this JFusion plugin
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'vbulletin';
	}

	/**
	 * @return string
	 */
	function getTablename()
	{
		return 'user';
	}

	/**
	 * @param object $userinfo
	 * @return array
	 */
	function deleteUser($userinfo)
	{
		//setup status array to hold debug info and errors
		$status = array();
		$status['debug'] = array();
		$status['error'] = array();

		$apidata = array('userinfo' => $userinfo);
		$response = $this->helper->apiCall('deleteUser', $apidata);

		if ($response['success']) {
			$status['debug'][] = JText::_('USER_DELETION') . ' ' . $userinfo->userid;
		}
		foreach ($response['errors'] as $error) {
			$status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $error;
		}
		foreach ($response['debug'] as $debug) {
			$status['debug'][] = $debug;
		}
		return $status;
	}

	/**
	 * @param object $userinfo
	 * @param array $options
	 *
	 * @return array
	 */
	function destroySession($userinfo, $options)
	{
		$status = array('error' => array(), 'debug' => array());
		try {
			$mainframe = JFusionFactory::getApplication();
			$cookie_prefix = $this->params->get('cookie_prefix');
			$vbversion = $this->helper->getVersion();
			if ((int) substr($vbversion, 0, 1) > 3) {
				if (substr($cookie_prefix, -1) !== '_') {
					$cookie_prefix .= '_';
				}
			}
			$cookie_domain = $this->params->get('cookie_domain');
			$cookie_path = $this->params->get('cookie_path');
			$cookie_expires = $this->params->get('cookie_expires', '15') * 60;
			$secure = $this->params->get('secure', false);
			$httponly = $this->params->get('httponly', true);
			$timenow = time();

			$session_user = $mainframe->input->cookie->get($cookie_prefix . 'userid', '');
			if (empty($session_user)) {
				$status['debug'][] = JText::_('VB_COOKIE_USERID_NOT_FOUND');
			}

			$session_hash = $mainframe->input->cookie->get($cookie_prefix . 'sessionhash', '');
			if (empty($session_hash)) {
				$status['debug'][] = JText::_('VB_COOKIE_HASH_NOT_FOUND');
			}

			//If blocking a user in Joomla User Manager, Joomla will initiate a logout.
			//Thus, prevent a logout of the currently logged in user if a user has been blocked:
			if (!defined('VBULLETIN_BLOCKUSER_CALLED')) {
				$cookies = JFusionFactory::getCookies();
				//clear out all of vB's cookies
				foreach ($_COOKIE AS $key => $val) {
					if (strpos($key, $cookie_prefix) !== false) {
						$status['debug'][] = $cookies->addCookie($key , 0, -3600, $cookie_path, $cookie_domain, $secure, $httponly);
					}
				}

				$db = JFusionFactory::getDatabase($this->getJname());
				$queries = array();

				if ($session_user) {
					$queries[] = $db->getQuery(true)
						->update('#__user')
						->set('lastvisit = ' .  $db->quote($timenow))
						->set('lastactivity = ' .  $db->quote($timenow))
						->where('userid = ' . $db->quote($session_user));

					$queries[] = $db->getQuery(true)
						->delete('#__session')
						->where('userid = ' . $db->quote($session_user));
				}
				$queries[] = $db->getQuery(true)
					->delete('#__session')
					->where('sessionhash = ' . $db->quote($session_hash));

				foreach ($queries as $q) {
					$db->setQuery($q);
					try {
						$db->execute();
					} catch (Exception $e) {
						$status['debug'][] = $e->getMessage();
					}
				}
			} else {
				$status['debug'][] = 'Joomla initiated a logout of a blocked user thus skipped vBulletin destroySession() to prevent current user from getting logged out.';
			}
		} catch (Exception $e) {
			$status['error'][] = $e->getMessage();
		}
		return $status;
	}

	/**
	 * @param object $userinfo
	 * @param array $options
	 * @return array
	 */
	function createSession($userinfo, $options)
	{
		$status = array('error' => array(), 'debug' => array());
		try {
			//do not create sessions for blocked users
			if (!empty($userinfo->block) || !empty($userinfo->activation)) {
				throw new RuntimeException(JText::_('FUSION_BLOCKED_USER'));
			} else {
				require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';
				//first check to see if striking is enabled to prevent further strikes
				$db = JFusionFactory::getDatabase($this->getJname());

				$query = $db->getQuery(true)
					->select('value')
					->from('#__setting')
					->where('varname = ' . $db->quote('usestrikesystem'));

				$db->setQuery($query);
				$strikeEnabled = $db->loadResult();

				if ($strikeEnabled) {
					$ip = $_SERVER['REMOTE_ADDR'];
					$time = strtotime('-15 minutes');

					$query = $db->getQuery(true)
						->select('COUNT(*)')
						->from('#__strikes')
						->where('strikeip = ' . $db->quote($ip))
						->where('striketime >= ' . $time);

					$db->setQuery($query);
					$strikes = $db->loadResult();

					if ($strikes >= 5) {
						throw new RuntimeException(JText::_('VB_TOO_MANY_STRIKES'));
					}
				}

				//make sure a session is not already active for this user
				$cookie_prefix = $this->params->get('cookie_prefix');
				$vbversion = $this->helper->getVersion();
				if ((int) substr($vbversion, 0, 1) > 3) {
					if (substr($cookie_prefix, -1) !== '_') {
						$cookie_prefix .= '_';
					}
				}
				$cookie_salt = $this->params->get('cookie_salt');
				$cookie_domain = $this->params->get('cookie_domain');
				$cookie_path = $this->params->get('cookie_path');
				$cookie_expires  = (!empty($options['remember'])) ? 0 : $this->params->get('cookie_expires');
				if ($cookie_expires == 0) {
					$expires_time = time() + (60 * 60 * 24 * 365);
				} else {
					$expires_time = time() + (60 * $cookie_expires);
				}
				$passwordhash = md5($userinfo->password . $cookie_salt);

				$query = $db->getQuery(true)
					->select('sessionhash')
					->from('#__session')
					->where('userid = ' . $userinfo->userid);

				$db->setQuery($query);
				$sessionhash = $db->loadResult();

				$mainframe = JFusionFactory::getApplication();
				$cookie_sessionhash = $mainframe->input->cookie->get($cookie_prefix . 'sessionhash', '');
				$cookie_userid = $mainframe->input->cookie->get($cookie_prefix . 'userid', '');
				$cookie_password = $mainframe->input->cookie->get($cookie_prefix . 'password', '');

				if (!empty($cookie_userid) && $cookie_userid == $userinfo->userid && !empty($cookie_password) && $cookie_password == $passwordhash) {
					$vbcookieuser = true;
				} else {
					$vbcookieuser = false;
				}

				if (!$vbcookieuser && (empty($cookie_sessionhash) || $sessionhash != $cookie_sessionhash)) {
					$secure = $this->params->get('secure', false);
					$httponly = $this->params->get('httponly', true);

					$cookies = JFusionFactory::getCookies();
					$status['debug'][] = $cookies->addCookie($cookie_prefix . 'userid', $userinfo->userid, $expires_time,  $cookie_path, $cookie_domain, $secure, $httponly);
					$status['debug'][] = $cookies->addCookie($cookie_prefix . 'password', $passwordhash, $expires_time, $cookie_path, $cookie_domain, $secure, $httponly, true);
				} else {
					$status['debug'][] = JText::_('VB_SESSION_ALREADY_ACTIVE');
					/*
				 * do not want to output as it indicate the cookies are set when they are not.
				$status['debug'][JText::_('COOKIES')][] = array(JText::_('NAME') => $cookie_prefix.'userid', JText::_('VALUE') => $cookie_userid, JText::_('EXPIRES') => $debug_expiration, JText::_('COOKIE_PATH') => $cookie_path, JText::_('COOKIE_DOMAIN') => $cookie_domain);
				$status['debug'][JText::_('COOKIES')][] = array(JText::_('NAME') => $cookie_prefix.'password', JText::_('VALUE') => substr($cookie_password, 0, 6) . '********, ', JText::_('EXPIRES') => $debug_expiration, JText::_('COOKIE_PATH') => $cookie_path, JText::_('COOKIE_DOMAIN') => $cookie_domain);
				$status['debug'][JText::_('COOKIES')][] = array(JText::_('NAME') => $cookie_prefix.'sessionhash', JText::_('VALUE') => $cookie_sessionhash, JText::_('EXPIRES') => $debug_expiration, JText::_('COOKIE_PATH') => $cookie_path, JText::_('COOKIE_DOMAIN') => $cookie_domain);
				*/
				}
			}
		} catch (Exception $e) {
			$status['error'][] = $e->getMessage();
		}
		return $status;
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function updatePassword($userinfo, &$existinguser, &$status)
	{
		jimport('joomla.user.helper');
		$existinguser->password_salt = JUserHelper::genRandomPassword(3);
		$existinguser->password = md5(md5($userinfo->password_clear) . $existinguser->password_salt);

		$date = date('Y-m-d');

		$db = JFusionFactory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__user')
			->set('passworddate = ' . $db->quote($date))
			->set('password = ' . $db->quote($existinguser->password))
			->set('salt = ' . $db->quote($existinguser->password_salt))
			->where('userid  = ' . $existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		$status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function updateEmail($userinfo, &$existinguser, &$status)
	{
		$apidata = array('userinfo' => $userinfo, 'existinguser' => $existinguser);
		$response = $this->helper->apiCall('updateEmail', $apidata);

		if($response['success']) {
			$status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
		}
		foreach ($response['errors'] as $error) {
			$status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . ' ' . $error;
		}
		foreach ($response['debug'] as $debug) {
			$status['debug'][] = $debug;
		}
	}

	/**
	 * @param object $userinfo
	 * @param object &$existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function blockUser($userinfo, &$existinguser, &$status)
	{
		$db = JFusionFactory::getDatabase($this->getJname());

		//get the id of the banned group
		$bannedgroup = $this->params->get('bannedgroup');

		//update the usergroup to banned
		$query = $db->getQuery(true)
			->update('#__user')
			->set('usergroupid = ' . $bannedgroup)
			->where('userid  = ' . $existinguser->userid);

		$db->setQuery($query);

		$db->execute();

		//add a banned user catch to vbulletin database
		$ban = new stdClass;
		$ban->userid = $existinguser->userid;
		$ban->usergroupid = $existinguser->group_id;
		$ban->displaygroupid = $existinguser->displaygroupid;
		$ban->customtitle = $existinguser->customtitle;
		$ban->usertitle = $existinguser->usertitle;
		$ban->adminid = 1;
		$ban->bandate = time();
		$ban->liftdate = 0;
		$ban->reason = (!empty($status['aec'])) ? $status['block_message'] : $this->params->get('blockmessage');

		//now append or update the new user data

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from('#__userban')
			->where('userid = ' . $existinguser->userid);

		$db->setQuery($query);
		$banned = $db->loadResult();

		if ($banned) {
			$db->updateObject('#__userban', $ban, 'userid');
		} else {
			$db->insertObject('#__userban', $ban, 'userid');
		}

		$status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;

		//note that blockUser has been called
		if (empty($status['aec'])) {
			define('VBULLETIN_BLOCKUSER_CALLED', 1);
		}
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function unblockUser($userinfo, &$existinguser, &$status)
	{
		$usergroups = $this->getCorrectUserGroups($existinguser);
		$usergroup = $usergroups[0];

		//found out what usergroup should be used
		$bannedgroup = $this->params->get('bannedgroup');

		//first check to see if user is banned and if so, retrieve the prebanned fields
		//must be something other than $db because it conflicts with vbulletin global variables
		$db = JFusionFactory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->select('b.*, g.usertitle AS bantitle')
			->from('#__userban AS b')
			->innerJoin('#__user AS u ON b.userid = u.userid')
			->innerJoin('#__usergroup AS g ON u.usergroupid = g.usergroupid')
			->where('b.userid = ' . $existinguser->userid);

		$db->setQuery($query);
		$result = $db->loadObject();

		$defaultgroup = $usergroup->defaultgroup;
		$displaygroup = $usergroup->displaygroup;

		$defaulttitle = $this->getDefaultUserTitle($defaultgroup, $existinguser->posts);

		$apidata = array(
			"userinfo" => $userinfo,
			"existinguser" => $existinguser,
			"usergroups" => $usergroup,
			"bannedgroup" => $bannedgroup,
			"defaultgroup" => $defaultgroup,
			"displaygroup" => $displaygroup,
			"defaulttitle" => $defaulttitle,
			"result" => $result
		);
		$response = $this->helper->apiCall('unblockUser', $apidata);

		if ($result) {
			//remove any banned user catches from vbulletin database
			$query = $db->getQuery(true)
				->delete('#__userban')
				->where('userid = ' . $existinguser->userid);

			$db->setQuery($query);
			$db->execute();
		}

		if ($response['success']) {
			$status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
		}
		foreach ($response['errors'] as $error) {
			$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . ': ' . $error;
		}
		foreach ($response['debug'] as $debug) {
			$status['debug'][] = $debug;
		}
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function activateUser($userinfo, &$existinguser, &$status)
	{
		//found out what usergroup should be used
		$usergroups = $this->getCorrectUserGroups($existinguser);
		$usergroup = $usergroups[0];

		//update the usergroup to default group
		$db = JFusionFactory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__user')
			->set('usergroupid = ' . $usergroup->defaultgroup)
			->where('userid  = ' . $existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		//remove any activation catches from vbulletin database
		$query = $db->getQuery(true)
			->delete('#__useractivation')
			->where('userid = ' . $existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function inactivateUser($userinfo, &$existinguser, &$status)
	{
		//found out what usergroup should be used
		$activationgroup = $this->params->get('activationgroup');

		//update the usergroup to awaiting activation
		$db = JFusionFactory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__user')
			->set('usergroupid = ' . $activationgroup)
			->where('userid  = ' . $existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		//update the activation status
		//check to see if the user is already inactivated
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from('#__useractivation')
			->where('userid = ' . $existinguser->userid);

		$db->setQuery($query);
		$count = $db->loadResult();
		if (empty($count)) {
			//if not, then add an activation catch to vbulletin database
			$useractivation = new stdClass;
			$useractivation->userid = $existinguser->userid;
			$useractivation->dateline = time();
			jimport('joomla.user.helper');
			$useractivation->activationid = JUserHelper::genRandomPassword(40);

			$usergroups = $this->getCorrectUserGroups($existinguser);
			$usergroup = $usergroups[0];
			$useractivation->usergroupid = $usergroup->defaultgroup;

			$db->insertObject('#__useractivation', $useractivation, 'useractivationid' );

			$apidata = array('existinguser' => $existinguser);
			$response = $this->helper->apiCall('inactivateUser', $apidata);
			if ($response['success']) {
				$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
			}
			foreach ($response['errors'] as $error) {
				$status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ' ' . $error;
			}
			foreach ($response['debug'] as $debug) {
				$status['debug'][] = $debug;
			}
		} else {
			$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		}
	}

	/**
	 * @param object $userinfo
	 * @param array $status
	 *
	 * @return void
	 */
	function createUser($userinfo, &$status)
	{
		try {
			//get the default user group and determine if we are using simple or advanced
			$usergroups = $this->getCorrectUserGroups($userinfo);

			//return if we are in advanced user group mode but the master did not pass in a group_id
			if (empty($usergroups)) {
				throw new RuntimeException(JText::_('ADVANCED_GROUPMODE_MASTER_NOT_HAVE_GROUPID'));
			} else {
				$usergroup = $usergroups[0];
				if (empty($userinfo->activation)) {
					$defaultgroup = $usergroup->defaultgroup;
					$setAsNeedsActivation = false;
				} else {
					$defaultgroup = $this->params->get('activationgroup');
					$setAsNeedsActivation = true;
				}

				$apidata = array();
				$apidata['usergroups'] = $usergroup;
				$apidata['defaultgroup'] = $defaultgroup;

				$usertitle = $this->getDefaultUserTitle($defaultgroup);
				$userinfo->usertitle = $usertitle;

				if (!isset($userinfo->password_clear)) {
					//clear password is not available, set a random password for now
					jimport('joomla.user.helper');
					$random_password = JFusionFunction::getHash(JUserHelper::genRandomPassword(10));
					$userinfo->password_clear = $random_password;
				}

				//set the timezone
				if (!isset($userinfo->timezone)) {
					$config = JFusionFactory::getConfig();
					$userinfo->timezone = $config->get('offset', 'UTC');
				}

				$timezone = new DateTimeZone($userinfo->timezone);
				$offset = $timezone->getOffset(new DateTime('NOW'));
				$userinfo->timezone = $offset/3600;

				$apidata['userinfo'] = $userinfo;

				//performs some final VB checks before saving
				$response = $this->helper->apiCall('createUser', $apidata);
				if ($response['success']) {
					$userdmid = $response['new_id'];
					//if we set a temp password, we need to move the hashed password over
					if (!isset($userinfo->password_clear)) {
						try {
							$db = JFusionFactory::getDatabase($this->getJname());

							$query = $db->getQuery(true)
								->update('#__user')
								->set('password = ' . $db->quote($userinfo->password))
								->where('userid  = ' . $userdmid);

							$db->setQuery($query);
							$db->execute();
						} catch (Exception $e) {
							$status['debug'][] = JText::_('USER_CREATION_ERROR') . '. '. JText::_('USERID') . ' ' . $userdmid . ': ' . JText::_('MASTER_PASSWORD_NOT_COPIED');
						}
					}

					//save the new user
					$status['userinfo'] = $this->getUser($userinfo);

					//does the user still need to be activated?
					if ($setAsNeedsActivation) {
						try {
							$this->inactivateUser($userinfo, $status['userinfo'], $status);
						} catch (Exception $e) {
						}
					}

					//return the good news
					$status['debug'][] = JText::_('USER_CREATION') . '. '. JText::_('USERID') . ' ' . $userdmid;
				}
				foreach ($response['errors'] as $error) {
					$status['error'][] = JText::_('USER_CREATION_ERROR') . ' ' . $error;
				}
				foreach ($response['debug'] as $debug) {
					$status['debug'][] = $debug;
				}
			}
		} catch (Exception $e) {
			$status['error'][] = JText::_('ERROR_CREATE_USER') . ': ' . $e->getMessage();
		}
	}

	/**
	 * @param object &$userinfo
	 * @param object &$existinguser
	 * @param array &$status
	 *
	 * @return bool
	 */
	function executeUpdateUsergroup(&$userinfo, &$existinguser, &$status)
	{
		$update_groups = false;
		$usergroups = $this->getCorrectUserGroups($userinfo);
		$usergroup = $usergroups[0];

		$membergroupids = (isset($usergroup->membergroups)) ? $usergroup->membergroups : array();

		//check to see if the default groups are different
		if ($usergroup->defaultgroup != $existinguser->group_id ) {
			$update_groups = true;
		} elseif ($this->params->get('compare_displaygroups', true) && $usergroup->displaygroup != $existinguser->displaygroupid ) {
			//check to see if the display groups are different
			$update_groups = true;
		} elseif ($this->params->get('compare_membergroups', true)) {
			//check to see if member groups are different
			$current_membergroups = explode(',', $existinguser->membergroupids);
			if (count($current_membergroups) != count($membergroupids)) {
				$update_groups = true;
			} else {
				foreach ($membergroupids as $gid) {
					if (!in_array($gid, $current_membergroups)) {
						$update_groups = true;
						break;
					}
				}
			}

		}

		if ($update_groups) {
			$this->updateUsergroup($userinfo, $existinguser, $status);
		}

		return $update_groups;
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array  $status
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	public function updateUsergroup($userinfo, &$existinguser, &$status)
	{
		//check to see if we have a group_id in the $userinfo, if not return
		$usergroups = $this->getCorrectUserGroups($userinfo);
		if (empty($usergroups)) {
			throw new RuntimeException(JText::_('ADVANCED_GROUPMODE_MASTER_NOT_HAVE_GROUPID'));
		} else {
			$usergroup = $usergroups[0];
			$defaultgroup = $usergroup->defaultgroup;
			$displaygroup = $usergroup->displaygroup;
			$titlegroupid = (!empty($displaygroup)) ? $displaygroup : $defaultgroup;
			$usertitle = $this->getDefaultUserTitle($titlegroupid);

			$apidata = array(
				'existinguser' => $existinguser,
				'userinfo' => $userinfo,
				'usergroups' => $usergroup,
				'usertitle' => $usertitle
			);
			$response = $this->helper->apiCall('updateUsergroup', $apidata);

			if ($response['success']) {
				$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . $existinguser->group_id . ' -> ' . $usergroup->defaultgroup;;
			}
			foreach ($response['errors'] AS $error) {
				$status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ' ' . $error;
			}
			foreach ($response['debug'] as $debug) {
				$status['debug'][] = $debug;
			}
		}
	}

	/**
	 * the user's title based on number of posts
	 *
	 * @param $groupid
	 * @param int $posts
	 *
	 * @return mixed
	 */
	function getDefaultUserTitle($groupid, $posts = 0)
	{
		try {
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('usertitle')
				->from('#__usergroup')
				->where('usergroupid = ' . $groupid);

			$db->setQuery($query);
			$title = $db->loadResult();

			if (empty($title)) {
				$query = $db->getQuery(true)
					->select('title')
					->from('#__usertitle')
					->where('minposts <= ' . $posts)
					->order('minposts DESC');

				$db->setQuery($query, 0, 1);
				$title = $db->loadResult();
			}
		} catch (Exception $e) {
			$title = '';
		}
		return $title;
	}

	/**
	 * @param bool $keepalive
	 *
	 * @return int
	 */
	function syncSessions($keepalive = false)
	{
		try {
			$debug = (defined('DEBUG_SYSTEM_PLUGIN') ? true : false);
			if ($debug) {
				JFusionFunction::raiseNotice('keep alive called', $this->getJname());
			}
			$options = array();
			//retrieve the values for vb cookies
			$cookie_prefix = $this->params->get('cookie_prefix');
			$vbversion = $this->helper->getVersion();
			if ((int) substr($vbversion, 0, 1) > 3) {
				if (substr($cookie_prefix, -1) !== '_') {
					$cookie_prefix .= '_';
				}
			}
			$mainframe = JFusionFactory::getApplication();
			$cookie_sessionhash = $mainframe->input->cookie->get($cookie_prefix . 'sessionhash', '');
			$cookie_userid = $mainframe->input->cookie->get($cookie_prefix . 'userid', '');
			$cookie_password = $mainframe->input->cookie->get($cookie_prefix . 'password', '');
			$JUser = JFactory::getUser();
			if (JPluginHelper::isEnabled('system', 'remember')) {
				jimport('joomla.utilities.utility');
				$hash = JFusionFunction::getHash('JLOGIN_REMEMBER');

				$joomla_persistant_cookie = $mainframe->input->cookie->get($hash, '', 'raw');
			} else {
				$joomla_persistant_cookie = '';
			}
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('userid')
				->from('#__session')
				->where('sessionhash = ' . $db->quote($cookie_sessionhash));

			$db->setQuery($query);
			$session_userid = $db->loadResult();

			if (!$JUser->get('guest', true)) {
				//user logged into Joomla so let's check for an active vb session
				if ($debug) {
					JFusionFunction::raiseNotice('Joomla user logged in', $this->getJname());
				}

				//find the userid attached to Joomla userid
				$joomla_userid = $JUser->get('id');
				$userlookup = JFusionFunction::lookupUser($this->getJname(), $joomla_userid);
				$vb_userid = (!empty($userlookup)) ? $userlookup->userid : 0;

				//is there a valid VB user logged in?
				$vb_session = ((!empty($cookie_userid) && !empty($cookie_password) && $cookie_userid == $vb_userid) || (!empty($session_userid) && $session_userid == $vb_userid)) ? 1 : 0;

				if ($debug) {
					JFusionFunction::raiseNotice('vB session active: ' . $vb_session, $this->getJname());
				}

				//create a new session if one does not exist and either keep alive is enabled or a joomla persistent cookie exists
				if (!$vb_session) {
					if ((!empty($keepalive) || !empty($joomla_persistant_cookie))) {
						if ($debug) {
							JFusionFunction::raiseNotice('vbulletin guest', $this->getJname());
							JFusionFunction::raiseNotice('cookie_sessionhash = '. $cookie_sessionhash, $this->getJname());
							JFusionFunction::raiseNotice('session_userid = '. $session_userid, $this->getJname());
							JFusionFunction::raiseNotice('vb_userid = ' . $vb_userid, $this->getJname());
						}
						//enable remember me as this is a keep alive function anyway
						$options['remember'] = 1;
						//get the user's info

						$query = $db->getQuery(true)
							->select('username, email')
							->from('#__user')
							->where('userid = ' . $userlookup->userid);

						$db->setQuery($query);
						$user_identifiers = $db->loadObject();
						$userinfo = $this->getUser($user_identifiers);
						//create a new session
						try {
							$status = $this->createSession($userinfo, $options);
							if ($debug) {
								JFusionFunction::raise('notice', $status, $this->getJname());
							}
						} catch (Exception $e) {
							JfusionFunction::raiseError($e, $this->getJname());
						}
						//signal that session was changed
						return 1;
					} else {
						if ($debug) {
							JFusionFunction::raiseNotice('keep alive disabled or no persistant session found so calling Joomla\'s destorySession', $this->getJname());
						}
						$JoomlaUser = JFusionFactory::getUser('joomla_int');

						$userinfo = new stdClass;
						$userinfo->id = $JUser->id;
						$userinfo->username = $JUser->username;
						$userinfo->name = $JUser->name;
						$userinfo->email = $JUser->email;
						$userinfo->block = $JUser->block;
						$userinfo->activation = $JUser->activation;
						$userinfo->groups = $JUser->groups;
						$userinfo->password = $JUser->password;
						$userinfo->password_clear = $JUser->password_clear;

						$options['clientid'][] = '0';
						try {
							$status = $JoomlaUser->destroySession($userinfo, $options);
							if ($debug) {
								JFusionFunction::raise('notice', $status, $this->getJname());
							}
						} catch (Exception $e) {
							JfusionFunction::raiseError($e, $JoomlaUser->getJname());
						}
					}
				} elseif ($debug) {
					JFusionFunction::raiseNotice('Nothing done as both Joomla and vB have active sessions.', $this->getJname());
				}
			} elseif (!empty($session_userid) || (!empty($cookie_userid) && !empty($cookie_password))) {
				//the user is not logged into Joomla and we have an active vB session

				if ($debug) {
					JFusionFunction::raiseNotice('Joomla has a guest session', $this->getJname());
				}

				if (!empty($cookie_userid) && $cookie_userid != $session_userid) {
					try {
						$status = $this->destroySession(null, null);
						if ($debug) {
							JFusionFunction::raiseNotice('Cookie userid did not match session userid thus destroyed vB\'s session.', $this->getJname());
							JFusionFunction::raise('notice', $status, $this->getJname());
						}
					} catch (Exception $e) {
						JfusionFunction::raiseError($e, $this->getJname());
					}
				}

				//find the Joomla user id attached to the vB user
				$userlookup = JFusionFunction::lookupUser($this->getJname(), $session_userid, false);

				if (!empty($joomla_persistant_cookie)) {
					if ($debug) {
						JFusionFunction::raiseNotice('Joomla persistant cookie found so let Joomla handle renewal', $this->getJname());
					}
					return 0;
				} elseif (empty($keepalive)) {
					if ($debug) {
						JFusionFunction::raiseNotice('Keep alive disabled so kill vBs session', $this->getJname());
					}
					//something fishy or user chose not to use remember me so let's destroy vB's session
					try {
						$this->destroySession(null, null);
					} catch (Exception $e) {
						JfusionFunction::raiseError($e, $this->getJname());
					}
					return 1;
				} elseif ($debug) {
					JFusionFunction::raiseNotice('Keep alive enabled so renew Joomla\'s session', $this->getJname());
				}

				if (!empty($userlookup)) {
					if ($debug) {
						JFusionFunction::raiseNotice('Found a phpBB user so attempting to renew Joomla\'s session.', $this->getJname());
					}
					//get the user's info
					$db = JFactory::getDBO();

					$query = $db->getQuery(true)
						->select('username, email')
						->from('#__users')
						->where('id = ' . $userlookup->id);

					$db->setQuery($query);
					$user_identifiers = $db->loadObject();
					$JoomlaUser = JFusionFactory::getUser('joomla_int');
					$userinfo = $JoomlaUser->getUser($user_identifiers);
					if (!empty($userinfo)) {
						global $JFusionActivePlugin;
						$JFusionActivePlugin = $this->getJname();
						try {
							$status = $JoomlaUser->createSession($userinfo, $options);
							if ($debug) {
								JFusionFunction::raise('notice', $status, $this->getJname());
							}
						} catch (Exception $e) {
							JfusionFunction::raiseError($e, $JoomlaUser->getJname());
						}

						//no need to signal refresh as Joomla will recognize this anyway
						return 0;
					}
				}
			}
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
		}
		return 0;
	}

	/**
	 * AEC Integration Functions
	 *
	 * @param array &$current_settings
	 *
	 * @return array
	 */

	function AEC_Settings(&$current_settings)
	{
		$settings = array();
		$settings['vb_notice'] = array('fieldset', 'vB - Notice', 'If it is not enabled below to update a user\'s group upon a plan expiration or subscription, JFusion will use vB\'s advanced group mode setting if enabled to update the group.  Otherwise the user\'s group will not be touched.');
		$settings['vb_block_user'] = array('list_yesno', 'vB - Ban User on Expiration', 'Ban the user in vBulletin on a plan\'s expiration.');
		$settings['vb_block_reason'] = array('inputE', 'vB - Ban Reason', 'Message displayed as the reason the user has been banned.');
		$settings['vb_update_expiration_group'] = array('list_yesno', 'vB - Update Group on Expiration', 'Updates the user\'s usergroup in vB on a plan\'s expiration.');
		$settings['vb_expiration_groupid'] = array('list', 'vB - Expiration Group', 'Group to move the user into upon expiration.');
		$settings['vb_unblock_user'] = array('list_yesno', 'vB - Unban User on Subscription', 'Unbans the user in vBulletin on a plan\'s subscription.');
		$settings['vb_update_subscription_group'] = array('list_yesno', 'vB - Update Group on Subscription', 'Updates the user\'s usergroup in vB on a plan\'s subscription.');
		$settings['vb_subscription_groupid'] = array('list', 'vB - Subscription Group', 'Group to move the user into upon a subscription.');
		$settings['vb_block_user_registration'] = array('list_yesno', 'vB - Ban User on Registration', 'Ban the user in vBulletin when a user registers.  This ensures they do not have access to vB until they subscribe to a plan.');
		$settings['vb_block_reason_registration'] = array('inputE', 'vB - Registration Ban Reason', 'Message displayed as the reason the user has been banned.');

		$admin = JFusionFactory::getAdmin($this->getJname());

		$usergroups = array();
		try {
			$usergroups = $admin->getUsergroupList();
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $admin->getJname());
		}

		array_unshift($usergroups, JHTML::_('select.option', '0', '- Select a Group -', 'id', 'name'));
		$v = (isset($current_settings['vb_expiration_groupid'])) ? $current_settings['vb_expiration_groupid'] : '';
		$settings['lists']['vb_expiration_groupid'] = JHTML::_('select.genericlist', $usergroups,  'vb_expiration_groupid', '', 'id', 'name', $v);
		$v = (isset($current_settings['vb_subscription_groupid'])) ? $current_settings['vb_subscription_groupid'] : '';
		$settings['lists']['vb_subscription_groupid'] = JHTML::_('select.genericlist', $usergroups,  'vb_subscription_groupid', '', 'id', 'name', $v);
		return $settings;
	}

	/**
	 * @param $settings
	 * @param $request
	 * @param $userinfo
	 */
	function AEC_expiration_action(&$settings, &$request, $userinfo)
	{
		$status = array();
		$status['error'] = array();
		$status['debug'] = array();
		$status['aec'] = 1;
		$status['block_message'] = $settings['vb_block_reason'];

		try {
			$existinguser = $this->getUser($userinfo);
			if (!empty($existinguser)) {
				if ($settings['vb_block_user']) {
					$userinfo->block =  1;

					$this->blockUser($userinfo, $existinguser, $status);
				}

				if ($settings['vb_update_expiration_group'] && !empty($settings['vb_expiration_groupid'])) {
					$usertitle = $this->getDefaultUserTitle($settings['vb_expiration_groupid']);

					$apidata = array(
						'userinfo' => $userinfo,
						'existinguser' => $existinguser,
						'aec' => 1,
						'aecgroupid' => $settings['vb_expiration_groupid'],
						'usertitle' => $usertitle
					);
					$response = $this->helper->apiCall('unblockUser', $apidata);

					if ($response['success']) {
						$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . $existinguser->group_id . ' -> ' . $settings['vb_expiration_groupid'];
					}
					foreach ($response['errors'] AS $error) {
						$status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ' ' . $error;
					}
					foreach ($response['debug'] as $debug) {
						$status['debug'][] = $debug;
					}
				} else {
					$this->updateUser($userinfo);
				}
			}
		} catch (Exception $e) {
			$status['error'][] = $e->getMessage();
		}
	}

	/**
	 * @param $settings
	 * @param $request
	 * @param $userinfo
	 */
	function AEC_action(&$settings, &$request, $userinfo)
	{
		$status = array();
		$status['error'] = array();
		$status['debug'] = array();
		$status['aec'] = 1;

		try {
			$existinguser = $this->getUser($userinfo);
			if (!empty($existinguser)) {
				if ($settings['vb_unblock_user']) {
					$userinfo->block =  0;
					try {
						$this->unblockUser($userinfo, $existinguser, $status);
					} catch (Exception $e) {
					}
				}

				if ($settings['vb_update_subscription_group'] && !empty($settings['vb_subscription_groupid'])) {
					$usertitle = $this->getDefaultUserTitle($settings['vb_subscription_groupid']);

					$apidata = array(
						'userinfo' => $userinfo,
						'existinguser' => $existinguser,
						'aec' => 1,
						'aecgroupid' => $settings['vb_subscription_groupid'],
						'usertitle' => $usertitle
					);
					$response = $this->helper->apiCall('unblockUser', $apidata);

					if ($response['success']) {
						$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . $existinguser->group_id . ' -> ' . $settings['vb_subscription_groupid'];
					}
					foreach ($response['errors'] AS $error) {
						$status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ' ' . $error;
					}
					foreach ($response['debug'] as $debug) {
						$status['debug'][] = $debug;
					}
				} else {
					$this->updateUser($userinfo);
				}
			}

			$mainframe = JFusionFactory::getApplication();
			if (!$mainframe->isAdmin()) {
				//login to vB
				$options = array();
				$options['remember'] = 1;

				$this->createSession($existinguser, $options);
			}
		} catch (Exception $e) {
			$status['error'][] = $e->getMessage();
		}
	}

	/**
	 * @param $settings
	 * @param $request
	 * @param $userinfo
	 */
	function AEC_on_userchange_action(&$settings, &$request, $userinfo)
	{
		//Only do something on registration
		if (strcmp($request->trace, 'registration') === 0) {
			$status = array();
			$status['error'] = array();
			$status['debug'] = array();
			$status['aec'] = 1;
			$status['block_message'] = $settings['vb_block_reason_registration'];
			$existinguser = $this->getUser($userinfo);
			if (!empty($existinguser)) {
				if ($settings['vb_block_user_registration']) {
					$userinfo->block =  1;
					try {
						$this->blockUser($userinfo, $existinguser, $status);
					} catch(Exception $e) {
					}
				}
			}
		}
	}

	/**
	 * Function That find the correct user group index
	 *
	 * @param stdClass $userinfo
	 *
	 * @return int
	 */
	function getUserGroupIndex($userinfo)
	{
		$index = 0;

		$master = JFusionFunction::getMaster();
		if ($master) {
			$mastergroups = JFusionFunction::getUserGroups($master->name);

			foreach ($mastergroups as $key => $mastergroup) {
				if ($mastergroup) {
					$found = true;
					//check to see if the default groups are different
					if ($mastergroup->defaultgroup != $userinfo->group_id ) {
						$found = false;
					} else {
						if ($this->params->get('compare_displaygroups', true) && $mastergroup->displaygroup != $userinfo->displaygroupid ) {
							//check to see if the display groups are different
							$found = false;
						} else {
							if ($this->params->get('compare_membergroups', true) && isset($mastergroup->membergroups)) {
								//check to see if member groups are different
								$current_membergroups = explode(',', $userinfo->membergroupids);
								if (count($current_membergroups) != count($mastergroup->membergroups)) {
									$found = false;
									break;
								} else {
									foreach ($mastergroup->membergroups as $gid) {
										if (!in_array($gid, $current_membergroups)) {
											$found = false;
											break;
										}
									}
								}
							}
						}
					}
					if ($found) {
						$index = $key;
						break;
					}
				}
			}
		}

		return $index;
	}
}