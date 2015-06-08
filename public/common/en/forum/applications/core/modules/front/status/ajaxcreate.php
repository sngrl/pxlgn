<?php
/**
 * @brief        Status Updates Feed
 * @author        <a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright    (c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license        http://www.invisionpower.com/legal/standards/
 * @package        IPS Social Suite
 * @since        15 Aug 2014
 * @version        SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\front\status;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
	header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
	exit;
}

/**
 * Status Updates
 */
class _ajaxcreate extends \IPS\Dispatcher\Controller
{
	/**
	 * Status Update Create Form
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( !\IPS\Settings::i()->profile_comments )
		{
			\IPS\Output::i()->error('node_error', '2C231/2', 403, '');
		}

		if ( !\IPS\core\Statuses\Status::canCreateFromCreateMenu() )
		{
			\IPS\Output::i()->error('no_module_permission', '2C231/1', 403, '');
		}

		$form = new \IPS\Helpers\Form( 'new_status', 'status_new', \IPS\Member::loggedIn()->url() );
		foreach ( \IPS\core\Statuses\Status::formElements(NULL, NULL, TRUE ) AS $k => $element )
		{
			$form->add( $element );
		}
		
		$form = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'statusPopupTemplate' ) );

		if ( \IPS\core\Statuses\Status::moderateNewItems( \IPS\Member::loggedIn() ) )
		{
			$form = \IPS\Theme::i()->getTemplate('forms', 'core')->modQueueMessage( \IPS\Member::loggedIn()->warnings( 5, NULL, 'mq' ), \IPS\Member::loggedIn()->mod_posts) . $form;
		}
		\IPS\Output::i()->output = $form;
	}
}