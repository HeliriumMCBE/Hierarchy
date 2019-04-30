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

namespace CortexPE\Hierarchy\cmd;


use pocketmine\command\CommandSender;

abstract class SubCommand {

	/** @var string */
	private $name;

	/** @var array */
	private $aliases = [];

	/** @var string */
	private $usageMessage;

	/** @var string */
	private $descriptionMessage;

	/** @var string */
	private $permission = null;

	/**
	 * SubCommand constructor.
	 * @param string $name
	 * @param array $aliases
	 * @param string $usageMessage
	 * @param string $descriptionMessage
	 */
	public function __construct(string $name, array $aliases, string $usageMessage, string $descriptionMessage){
		$this->aliases = array_map("strtolower", $aliases);
		$this->name = strtolower($name);
		$this->usageMessage = $usageMessage;
		$this->descriptionMessage = $descriptionMessage;
	}

	/**
	 * @return string
	 */
	public function getName(): string{
		return $this->name;
	}

	/**
	 * @return array
	 */
	public function getAliases(): array{
		return $this->aliases;
	}

	/**
	 * @return string
	 */
	public function getUsage(): string{
		return $this->usageMessage;
	}

	/**
	 * @return string
	 */
	public function getDescription(): string{
		return $this->descriptionMessage;
	}

	/**
	 * @param CommandSender $sender
	 * @param array $args
	 */
	abstract public function execute(CommandSender $sender, array $args): void;

	/**
	 * @return string
	 */
	public function getPermission(): ?string{
		return $this->permission;
	}

	/**
	 * @param string $permission
	 */
	public function setPermission(string $permission): void{
		$this->permission = $permission;
	}

	public function sendUsage(CommandSender $sender):void{
		$sender->sendMessage("Usage: " . $this->getUsage());
	}
}