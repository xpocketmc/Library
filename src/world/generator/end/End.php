<?php

declare(strict_types=1);

namespace pocketmine\world\generator\end;

use pocketmine\block\Block;
use pocketmine\world\biome\Biome;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\end\populator\EndPillar;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\noise\Simplex;
use pocketmine\world\generator\populator\Populator;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use function abs;
use function exp;

class End extends Generator{

	/** @var Populator[] */
	private $populators = [];
	/** @var int */
	private $waterHeight = 0;
	/** @var int */
	private $emptyHeight = 32;
	/** @var int */
	private $emptyAmplitude = 1;
	/** @var float */
	private $density = 0.6;

	/** @var Populator[] */
	private $generationPopulators = [];
	/** @var Simplex */
	private $noiseBase;

	private static $GAUSSIAN_KERNEL = null;
	private static $SMOOTH_SIZE = 2;

	public function __construct(array $options = []){
		if(self::$GAUSSIAN_KERNEL === null){
			self::generateKernel();
		}
	}

	public function init(ChunkManager $world, Random $random) : void{
		parent::init($world, $random);

		$this->random->setSeed($this->world->getSeed());
		$this->noiseBase = new Simplex($this->random, 4, 1 / 4, 1 / 64);
		$this->populators[] = new EndPillar();
	}

	private static function generateKernel(){
		self::$GAUSSIAN_KERNEL = [];

		$bellSize = 1 / self::$SMOOTH_SIZE;
		$bellHeight = 2 * self::$SMOOTH_SIZE;

		for($sx = -self::$SMOOTH_SIZE; $sx <= self::$SMOOTH_SIZE; ++$sx){
			self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE] = [];

			for($sz = -self::$SMOOTH_SIZE; $sz <= self::$SMOOTH_SIZE; ++$sz){
				$bx = $bellSize * $sx;
				$bz = $bellSize * $sz;
				self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE][$sz + self::$SMOOTH_SIZE] = $bellHeight * exp(-($bx * $bx + $bz * $bz) / 2);
			}
		}
	}

	public function getName() : string{
		return "end";
	}

	public function getWaterHeight() : int{
		return $this->waterHeight;
	}

	public function getSettings() : array{
		return [];
	}

	public function generateChunk(int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(0xa6fe78dc ^ ($chunkX << 8) ^ $chunkZ ^ $this->world->getSeed());

		$noise = $this->noiseBase->getFastNoise3D(16, 128, 16, 4, 8, 4, $chunkX * 16, 0, $chunkZ * 16);

		$chunk = $this->world->getChunk($chunkX, $chunkZ);

		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){

				$biome = Biome::getBiome(Biome::END);
				$biome->setGroundCover([
					Block::get(Block::OBSIDIAN, 0)
				]);
				$chunk->setBiomeId($x, $z, $biome->getId());
				$color = [0, 0, 0];
				$bColor = 2;
				$color[0] += (($bColor >> 16) ** 2);
				$color[1] += ((($bColor >> 8) & 0xff) ** 2);
				$color[2] += (($bColor & 0xff) ** 2);

				for($y = 0; $y < 128; ++$y){

					$noiseValue = (abs($this->emptyHeight - $y) / $this->emptyHeight) * $this->emptyAmplitude - $noise[$x][$z][$y];
					$noiseValue -= 1 - $this->density;

					$distance = new Vector3(0, 64, 0);
					$distance = $distance->distance(new Vector3($chunkX * 16 + $x, ($y / 1.3), $chunkZ * 16 + $z));

					if($noiseValue < 0 && $distance < 100 or $noiseValue < -0.2 && $distance > 400){
						$chunk->setBlock($x, $y, $z, Block::END_STONE, 0);
					}
				}
			}
		}

		foreach($this->generationPopulators as $populator){
			$populator->populate($this->world, $chunkX, $chunkZ, $this->random);
		}
	}

	public function populateChunk(int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(0xa6fe78dc ^ ($chunkX << 8) ^ $chunkZ ^ $this->world->getSeed());
		foreach($this->populators as $populator){
			$populator->populate($this->world, $chunkX, $chunkZ, $this->random);
		}

		$chunk = $this->world->getChunk($chunkX, $chunkZ);
		$biome = Biome::getBiome($chunk->getBiomeId(7, 7));
		$biome->populateChunk($this->world, $chunkX, $chunkZ, $this->random);
	}

	public function getSpawn() : Vector3{
		return new Vector3(48, 128, 48);
	}
}
