<?php

/***
 *        __  ___                           __
 *       / / / (_)__  _________ ___________/ /_  __  __
 *      / /_/ / / _ \/ ___/ __ `/ ___/ ___/ __ \/ / / /
 *     / __  / /  __/ /  / /_/ / /  / /__/ / / / /_/ /
 *    /_/ /_/_/\___/_/   \__,_/_/   \___/_/ /_/\__, /
 *                                            /____/
 *
 * Hierarchy - Role-based permission management system
 * Copyright (C) 2019-Present CortexPE
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace CortexPE\Hierarchy\data;


use CortexPE\Hierarchy\Hierarchy;
use CortexPE\Hierarchy\member\BaseMember;
use CortexPE\Hierarchy\role\Role;
use Generator;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use SOFe\AwaitGenerator\Await;
use Throwable;

abstract class SQLDataSource extends DataSource {
	protected const STMTS_FILE = null;
	protected const DIALECT = null;
	/** @var DataConnector */
	protected $db;

	public function __construct(Hierarchy $plugin, array $config) {
		parent::__construct($plugin);

		$this->db = libasynql::create($plugin, [
			"type" => static::DIALECT,
			static::DIALECT => $this->getExtraDBSettings($plugin, $config),
			"worker-limit" => $config["workerLimit"]
		], [
			static::DIALECT => static::STMTS_FILE
		]);
	}

	public function initialize(): void {
		Await::f2c(function () {
			foreach(
				[
					"hierarchy.init.rolesTable",
					"hierarchy.init.rolePermissionTable",
					"hierarchy.init.memberRolesTable"
				] as $tableSchema
			) {
				yield $this->asyncGenericQuery($tableSchema);
			}

			$roles = yield $this->asyncSelect("hierarchy.role.list");
			if(empty($roles)) {
				// create default role & add default permissions
				yield $this->asyncInsert("hierarchy.role.createDefault", [
					"name" => "Member"
				]);
				yield $this->asyncInsert("hierarchy.role.create", [
					"name" => "Operator"
				]);
				$roles = yield $this->asyncSelect("hierarchy.role.list");
				$pMgr = PermissionManager::getInstance();
				foreach($roles as $role) {
					if($role["Name"] === "Member") {
						foreach($pMgr->getDefaultPermissions(false) as $permission) {
							yield $this->asyncInsert("hierarchy.role.permissions.add", [
								"role_id" => $role["ID"],
								"permission" => $permission->getName()
							]);
						}
					} elseif($role["Name"] === "Operator") {
						foreach($pMgr->getDefaultPermissions(true) as $permission) {
							yield $this->asyncInsert("hierarchy.role.permissions.add", [
								"role_id" => $role["ID"],
								"permission" => $permission->getName()
							]);
						}
					}
				}
				$roles = yield $this->asyncSelect("hierarchy.role.list");
			}
			foreach($roles as $k => $role) {
				$permissions = yield $this->asyncSelect("hierarchy.role.permissions.get", [
					"role_id" => $role["ID"]
				]);
				foreach($permissions as $permission_row) {
					$roles[$k]["Permissions"][] = $permission_row["Permission"];
				}
			}
			$this->postInitialize(yield $this->asyncSelect("hierarchy.role.list"));
		}, function () {
		}, function (Throwable $err) {
			$this->getPlugin()->getLogger()->logException($err);
		});
		$this->db->waitAll();
	}

	abstract function getExtraDBSettings(Hierarchy $plugin, array $config): array;

	protected function asyncGenericQuery(string $query, array $args = []): Generator {
		$this->db->executeGeneric($query, $args, yield, yield Await::REJECT);

		return yield Await::ONCE;
	}

	protected function asyncSelect(string $query, array $args = []): Generator {
		$this->db->executeSelect($query, $args, yield, yield Await::REJECT);

		return yield Await::ONCE;
	}

	protected function asyncInsert(string $query, array $args = []): Generator {
		$this->db->executeInsert($query, $args, yield, yield Await::REJECT);

		return yield Await::ONCE;
	}

	public function loadMemberData(BaseMember $member, ?callable $onLoad = null): void {
		Await::f2c(function () use ($member, $onLoad) {
			$data = [
				"roles" => [
					$this->plugin->getRoleManager()->getDefaultRole()->getId()
				]
			];
			$rows = yield $this->asyncSelect("hierarchy.member.roles.get", [
				"username" => $member->getName()
			]);
			foreach($rows as $row) {
				$data["roles"][] = $row["RoleID"];
			}
			$member->loadData($data);
			if($onLoad !== null) {
				$onLoad($member);
			}
		}, function () {
		}, function (Throwable $err) {
			$this->getPlugin()->getLogger()->logException($err);
		});
	}

	public function updateMemberData(BaseMember $member, string $action, $data): void {
		switch($action) {
			case self::ACTION_MEMBER_ROLE_ADD:
				$this->db->executeChange("hierarchy.member.roles.add", [
					"username" => $member->getName(),
					"role_id" => (int)$data
				]);
				break;
			case self::ACTION_MEMBER_ROLE_REMOVE:
				$this->db->executeChange("hierarchy.member.roles.remove", [
					"username" => $member->getName(),
					"role_id" => (int)$data
				]);
				break;
		}
	}

	public function addRolePermission(Role $role, Permission $permission, bool $inverted = false): void {
		$this->removeRolePermission($role, $permission);
		$permission = ($inverted ? "-" : "") . $permission->getName();
		$this->db->executeInsert("hierarchy.role.permissions.add", [
			"role_id" => $role->getId(),
			"permission" => $permission
		]);
	}

	public function removeRolePermission(Role $role, $permission): void {
		if($permission instanceof Permission) {
			$permission = $permission->getName();
		}
		$this->db->executeInsert("hierarchy.role.permissions.remove", [
			"role_id" => $role->getId(),
			"permission" => $permission
		]);
	}

	public function createRoleOnStorage(string $name, int $id, int $position): void {
		$this->db->executeInsert("hierarchy.role.create", [
			"name" => $name
		]);
	}

	public function deleteRoleFromStorage(Role $role): void {
		$this->db->executeInsert("hierarchy.role.delete", [
			"role_id" => $role->getId()
		]);
	}

	public function bumpPosition(Role $role): void {
		$this->db->executeChange("hierarchy.role.bumpPosition", [
			"role_id" => $role->getId()
		]);
	}

	public function shutdown(): void {
		if($this->db instanceof DataConnector) {
			$this->db->close();
		}
	}

	public function flush(): void {
		// noop
	}
}