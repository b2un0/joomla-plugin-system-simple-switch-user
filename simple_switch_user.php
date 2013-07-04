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
		$doc	= JFactory::getDocument();
		$option = $app->input->get('option', null, 'cmd');
		$view 	= $app->input->get('view', null, 'cmd');
		$layout = $app->input->get('layout', null, 'cmd');
		$id 	= $app->input->get('id', 0, 'int');
		
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
            
            $content = $doc->getBuffer('component');
            $content = $content . $js;
            $doc->setBuffer($content, 'component');
            
            JToolBarHelper::divider();
            JToolBarHelper::custom('switchuser', 'upload', 'upload', 'Switch to User', false);
		}
	}
    
	function onAfterInitialise() {
		$app	= JFactory::getApplication();
		$db		= JFactory::getDbo();
		$user	= JFactory::getUser();
		$userId = $app->input->get('uid', 0, 'int');
		
		if ($app->isAdmin() || !$app->input->get('switchuser', false, 'bool') || !$userId) {
			return;
		}
		
		if ($user->id == $userId) {
			return $app->redirect('index.php', JText::_('You have already login as the user'), 'warning');
		}
		
		if ($user->id) {
			return $app->redirect('index.php', JText::_('You have login as another user, please logout first'), 'warning');
		}
		
		$backendSessionId = $app->input->cookie->get(md5(JApplication::getHash('administrator')));
		$query = 'SELECT userid'
			. ' FROM #__session'
			. ' WHERE session_id = '.$db->Quote($backendSessionId)
			. ' AND client_id = 1'
			. ' AND guest = 0'
		;
		
		$db->setQuery($query);
		
		if (!$db->loadResult()) {
			return $app->redirect('index.php', JText::_('Back-end User Session Expired'), 'error');
		}
		
		$instance = JFactory::getUser($userId);

		// If _getUser returned an error, then pass it back.
		if ($instance instanceof Exception) {
			$app->redirect('index.php', JText::_('User login failed'), 'error');
			return;
		}

		// If the user is blocked, redirect with an error
		if ($instance->get('block') == 1) {
			$app->redirect('index.php', JText::_('JERROR_NOLOGIN_BLOCKED'), 'error');
			return;
		}

		//Mark the user as logged in
		$instance->set('guest', 0);

		//Set the usertype based on the ACL group name
		$instance->set('usertype', $grp->name);
		
		// Register the needed session variables
		$session = JFactory::getSession();
		$session->set('user', $instance);

		// Check to see the the session already exists.
		$app = JFactory::getApplication();
		$app->checkSession();

		// Update the user related fields for the Joomla sessions table.
		$query = $db->getQuery(true)
			->update($db->quoteName('#__session'))
			->set($db->quoteName('guest') . ' = ' . $db->quote($instance->get('guest')))
			->set($db->quoteName('username') . ' = ' . $db->quote($instance->get('username')))
			->set($db->quoteName('userid') . ' = ' . (int) $instance->get('id'))
			->where($db->quoteName('session_id') . ' = ' . $db->quote($session->getId()));
		$db->setQuery($query);
		$db->execute();
		
		$app->redirect('index.php', JText::_('You have login successfully'));
	}
}