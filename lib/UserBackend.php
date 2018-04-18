<?php
/**
 * @copyright Copyright (c) 2018 Alexey Abel <dev@abelonline.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserBackendSqlRaw;

use OC\User\Backend;

class UserBackend implements \OCP\IUserBackend, \OCP\UserInterface {

	private $db;
	private $config;

	public function __construct(Config $config, Db $db) {
		$this->config = $config;
		// Don't get db handle (dbo object) here yet, so that it is only created
		// when db queries are actually run.
		$this->db = $db;
	}

	public function getBackendName() {
		return 'SQL raw';
	}

	public function implementsActions($actions) {

		return (bool)((
			($this->queriesForUserLoginAreSet() ? Backend::CHECK_PASSWORD : 0)
			) & $actions);
	}

	/**
	 * Checks provided login name and password against the database. This method
	 * is not part of \OCP\UserInterface but is called by Manager.php of
	 * Nextcloud if Backend::CHECK_PASSWORD is set.
	 * @param $providedUsername
	 * @param $providedPassword
	 * @return bool whether the provided password was correct for provided user
	 */
	public function checkPassword($providedUsername, $providedPassword) {
		$dbHandle = $this->db->getDbHandle();
		$statement = $dbHandle->prepare($this->config->getQueryGetPasswordHashForUser());
		$statement->execute(['username' => $providedUsername]);
		$retrievedPasswordHash = $statement->fetchColumn();

		if ($retrievedPasswordHash === FALSE) {
			return FALSE;
		}

		if (password_verify($providedPassword, $retrievedPasswordHash)) {
			return $providedUsername;
		} else {
			return FALSE;
		}
	}

	public function deleteUser($uid) {
		// TODO: Implement deleteUser() method.
	}

	public function getUsers($searchString = '', $limit = null, $offset = null) {
		// If the search string contains % or _ these would be interpreted as
		// wildcards in the LIKE expression. Therefore they will be escaped.
		$searchString = $this->escapePercentAndUnderscore($searchString);

		$parameterSubstitution['username'] = '%'.$searchString.'%';

		if (is_null($limit)) {
			$limitSegment = '';
		} else {
			$limitSegment = ' LIMIT :limit';
			$parameterSubstitution['limit'] = $limit;
		}

		if (is_null($offset)) {
			$offsetSegment = '';
		} else {
			$offsetSegment = ' OFFSET :offset';
			$parameterSubstitution['offset'] = $offset;
		}

		$queryFromConfig = $this->config->getQueryGetUsers();

		$finalQuery = '('.$queryFromConfig.')'. $limitSegment . $offsetSegment;

		$statement = $this->db->getDbHandle()->prepare($finalQuery);
		$statement->execute($parameterSubstitution);
		// Setting the second parameter to 0 will ensure, that only the first
		// column is returned.
		$matchedUsers = $statement->fetchAll(\PDO::FETCH_COLUMN,0);
		return $matchedUsers;

	}

	public function userExists($providedUsername) {
		$statement = $this->db->getDbHandle()->prepare($this->config->getQueryUserExists());
		$statement->execute(['username' => $providedUsername]);
		$doesUserExist = $statement->fetchColumn();
		return $doesUserExist;
	}

	public function getDisplayName($uid) {
		// TODO: Implement getDisplayName() method.
	}

	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		// TODO: Implement getDisplayNames() method.
	}

	public function hasUserListings() {
		// TODO: Implement hasUserListings() method.
	}

	/**
	 * Escape % and _ with \.
	 *
	 * @param $search string the input that will be escaped
	 * @return string input string with % and _ escaped
	 */
	private function escapePercentAndUnderscore($input) {
		return str_replace('%', '\\%', str_replace('_', '\\_', $input));
	}

	private function queriesForUserLoginAreSet() {
		return (!empty($this->config->getQueryGetPasswordHashForUser())
			&& !empty($this->config->getQueryUserExists()));
	}
}