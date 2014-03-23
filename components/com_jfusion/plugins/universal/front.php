<?php namespace JFusion\Plugins\universal;

/**
* @package JFusion_universal
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
use JFusion\Plugin\Plugin_Front;

defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Public Class for universal plugin
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 * @package JFusion_universal
 */
class Front extends Plugin_Front
{
    /**
     * @return string
     */
    function getRegistrationURL()
	{
		return $this->params->get('registerurl');
	}

    /**
     * @return string
     */
    function getLostPasswordURL()
	{
		return $this->params->get('lostpasswordurl');
	}

    /**
     * @return string
     */
    function getLostUsernameURL()
	{
		return $this->params->get('lostusernameurl');
	}
}