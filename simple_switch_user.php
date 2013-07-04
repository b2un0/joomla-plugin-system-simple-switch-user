<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

class plgSystemSimple_Switch_User extends JPlugin {
    
	function onBeforeRender() {
		$app 	= JFactory::getApplication();
		$option = JRequest::getCmd('option');
		$view 	= JRequest::getCmd('view');
		$layout = JRequest::getCmd('layout');
		$id 	= JRequest::getCmd('id');
        
		if ($app->isAdmin() && $option == 'com_users' && $view == 'user' && $layout == 'edit') {
        
            $js = '<script type="text/javascript">
            Joomla.submitbuttonOld = Joomla.submitbutton;
            Joomla.submitbutton = function(task) {
                if(task == "switchuser") {
                    window.open("'.JURI::root().'index.php?switchuser=1&uid='.$id.'");
                    return false;
                }else{
                    Joomla.submitbuttonOld(task);
                }
            }</script>';
            
            $document =JFactory::getDocument();
            $content = $document->getBuffer('component');
            $content = $content . $js;
            $document->setBuffer($content, 'component');
            
            JToolBarHelper::divider();
            JToolBarHelper::custom('switchuser', 'preview', 'preview', 'Switch to User', false);
		}
	}
    
	function onAfterInitialise() {
		$app	= JFactory::getApplication();
		$db		= JFactory::getDbo();
		$user	= JFactory::getUser();
		$userId = JRequest::getInt('uid', 0);
		
		if ($app->isAdmin() || !JRequest::getBool('switchuser', 0) || !$userId) {
			return;
		}
		
		if ($user->id == $userId) {
			return $app->redirect('index.php', JText::_('You have already login as the user'));
		}
		
		if ($user->id) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::_('You have login as another user, please logout first'));
			return $app->redirect('index.php');
		}
		
		$backendSessionId = JRequest::getVar(md5(JUtility::getHash('administrator')), null ,"COOKIE");
		$query = 'SELECT userid'
			. ' FROM #__session'
			. ' WHERE session_id = '.$db->Quote($backendSessionId)
			. ' AND client_id = 1'
			. ' AND guest = 0'
		;
		
		$db->setQuery($query);
		
		if (!$db->loadResult()) {
			JError::raiseWarning(500, JText::_('Back-end User Session Expired'));
			return $app->redirect('index.php');
		}
		
		$instance = JFactory::getUser($userId);

		// If _getUser returned an error, then pass it back.
		if (JError::isError($instance)) {
			$app->redirect('index.php');
			return;
		}

		// If the user is blocked, redirect with an error
		if ($instance->get('block') == 1) {
			JError::raiseWarning(500, JText::_('E_NOLOGIN_BLOCKED'));
			$app->redirect('index.php');
			return;
		}

		// Get an ACL object
		$acl =JFactory::getACL();
        
		// Get the user group from the ACL
		if ($instance->get('tmp_user') == 1) {
			$grp = new JObject;
			// This should be configurable at some point
			$grp->set('name', 'Registered');
		} else {
			$grp = $acl->getGroupsByUser($instance->get('id'));
		}

		//Mark the user as logged in
		$instance->set('guest', 0);
		$instance->set('aid', 1);

		//Set the usertype based on the ACL group name
		$instance->set('usertype', $grp->name);

		// Register the needed session variables
		$session = JFactory::getSession();
		$session->set('user', $instance);

		// Get the session object
		$table = JTable::getInstance('session');
		$table->load( $session->getId() );

		$table->guest 		= $instance->get('guest');
		$table->username 	= $instance->get('username');
		$table->userid 		= intval($instance->get('id'));
		$table->usertype 	= $instance->get('usertype');
		$table->gid 		= intval($instance->get('gid'));

		$table->update();

		$app->redirect('index.php', JText::_('You have login successfully'));
	}
}