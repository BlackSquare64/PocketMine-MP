<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\level;

use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\utils\Utils;

class BlockWriteBatch{
	/** @var Block[][][] */
	private $blocks = [];

	/** @var \Closure[] */
	private $validators = [];

	public function __construct(){
		$this->addValidator(function(ChunkManager $world, int $x, int $y, int $z) : bool{
			return $world->isInWorld($x, $y, $z);
		});
	}

	/**
	 * Adds a block to the batch at the given position.
	 *
	 * @param Vector3 $pos
	 * @param Block   $state
	 *
	 * @return $this
	 */
	public function addBlock(Vector3 $pos, Block $state) : self{
		return $this->addBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $state);
	}

	/**
	 * Adds a block to the batch at the given coordinates.
	 *
	 * @param int   $x
	 * @param int   $y
	 * @param int   $z
	 * @param Block $state
	 *
	 * @return $this
	 */
	public function addBlockAt(int $x, int $y, int $z, Block $state) : self{
		$this->blocks[$x][$y][$z] = $state;
		return $this;
	}

	/**
	 * Reads a block from the given world, masked by the blocks in this writebatch. This can be useful if you want to
	 * add blocks to the batch that depend on previous blocks should they exist.
	 *
	 * @param ChunkManager $world
	 * @param Vector3      $pos
	 *
	 * @return Block
	 */
	public function fetchBlock(ChunkManager $world, Vector3 $pos) : Block{
		return $this->fetchBlockAt($world, $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
	}

	/**
	 * @see BlockWriteBatch::fetchBlock()
	 *
	 * @param ChunkManager $world
	 * @param int          $x
	 * @param int          $y
	 * @param int          $z
	 *
	 * @return Block
	 */
	public function fetchBlockAt(ChunkManager $world, int $x, int $y, int $z) : Block{
		return $this->blocks[$x][$y][$z] ?? $world->getBlockAt($x, $y, $z);
	}

	/**
	 * Validates and attempts to apply the batch to the given world. If any part of the batch fails to validate, no
	 * changes will be made to the world.
	 *
	 * @param ChunkManager $world
	 *
	 * @return bool if the application was successful
	 */
	public function apply(ChunkManager $world) : bool{
		foreach($this->getBlocks() as [$x, $y, $z, $_]){
			foreach($this->validators as $validator){
				if(!$validator($world, $x, $y, $z)){
					return false;
				}
			}
		}
		foreach($this->getBlocks() as [$x, $y, $z, $block]){
			$world->setBlockAt($x, $y, $z, $block);
		}
		return true;
	}

	/**
	 * @return \Generator|mixed[] [int $x, int $y, int $z, Block $block]
	 */
	public function getBlocks() : \Generator{
		foreach($this->blocks as $x => $yLine){
			foreach($yLine as $y => $zLine){
				foreach($zLine as $z => $block){
					yield [$x, $y, $z, $block];
				}
			}
		}
	}

	/**
	 * Add a validation predicate which will be used to validate every block.
	 * The callable signature should be the same as the below dummy function.
	 * @see BlockWriteBatch::dummyValidator()
	 *
	 * @param callable $validator
	 */
	public function addValidator(callable $validator) : void{
		Utils::validateCallableSignature([$this, 'dummyValidator'], $validator);
		$this->validators[] = $validator;
	}

	/**
	 * Dummy function demonstrating the required closure signature for validators.
	 * @see BlockWriteBatch::addValidator()
	 *
	 * @dummy
	 *
	 * @param ChunkManager $world
	 * @param int          $x
	 * @param int          $y
	 * @param int          $z
	 *
	 * @return bool
	 */
	public function dummyValidator(ChunkManager $world, int $x, int $y, int $z) : bool{
		return true;
	}
}
