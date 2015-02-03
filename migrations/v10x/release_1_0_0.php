<?php
/**
*
* Moderate Own Topics extension
*
* @copyright (c) 2014 Daniel Chalsèche <https://www.danielchalseche.fr.cr/>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace dakinquelia\moderateowntopics\migrations\v10x;

/**
* Migration: Initial permission
*/
class release_1_0_0 extends \phpbb\db\migration\migration
{
	/**
	* Add or update data in the database
	*
	* @return array Array of table data
	* @access public
	*/
	public function update_data()
	{
		return array(
			// Add permission
			array('permission.add', array('f_moderate_own_topics', false)),

			// Set permissions
			array('permission.permission_set', array('ROLE_FORUM_FULL', 'f_moderate_own_topics')),
		);
	}
}
