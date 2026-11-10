<?php

declare(strict_types=1);

namespace pocketmine\world\worldedit;

use pocketmine\world\worldedit\command\CommandManager;
use pocketmine\world\worldedit\command\defaults\brush\BrushCommand;
use pocketmine\world\worldedit\command\defaults\clipboard\CopyCommand;
use pocketmine\world\worldedit\command\defaults\clipboard\FlipCommand;
use pocketmine\world\worldedit\command\defaults\clipboard\LoadSchematicCommand;
use pocketmine\world\worldedit\command\defaults\clipboard\PasteCommand;
use pocketmine\world\worldedit\command\defaults\clipboard\RotateCommand;
use pocketmine\world\worldedit\command\defaults\clipboard\SaveSchematicCommand;
use pocketmine\world\worldedit\command\defaults\generation\CubeCommand;
use pocketmine\world\worldedit\command\defaults\generation\CylinderCommand;
use pocketmine\world\worldedit\command\defaults\generation\NoiseCommand;
use pocketmine\world\worldedit\command\defaults\generation\SphereCommand;
use pocketmine\world\worldedit\command\defaults\history\HistoryAccessCommand;
use pocketmine\world\worldedit\command\defaults\movement\ThruCommand;
use pocketmine\world\worldedit\command\defaults\movement\UnstuckCommand;
use pocketmine\world\worldedit\command\defaults\movement\UpCommand;
use pocketmine\world\worldedit\command\defaults\selection\AliasedContextCommand;
use pocketmine\world\worldedit\command\defaults\selection\CountCommand;
use pocketmine\world\worldedit\command\defaults\selection\DeselectCommand;
use pocketmine\world\worldedit\command\defaults\selection\ExtendCommand;
use pocketmine\world\worldedit\command\defaults\selection\ExtinguishCommand;
use pocketmine\world\worldedit\command\defaults\selection\GenericPositionCommand;
use pocketmine\world\worldedit\command\defaults\selection\MoveCommand;
use pocketmine\world\worldedit\command\defaults\selection\NaturalizeCommand;
use pocketmine\world\worldedit\command\defaults\selection\OverlayCommand;
use pocketmine\world\worldedit\command\defaults\selection\ReplaceCommand;
use pocketmine\world\worldedit\command\defaults\selection\SetCommand;
use pocketmine\world\worldedit\command\defaults\selection\SmoothCommand;
use pocketmine\world\worldedit\command\defaults\selection\StackCommand;
use pocketmine\world\worldedit\command\defaults\selection\ViewCommand;
use pocketmine\world\worldedit\command\defaults\utility\BenchmarkCommand;
use pocketmine\world\worldedit\command\defaults\utility\BlockInfoCommand;
use pocketmine\world\worldedit\command\defaults\utility\BuilderRodCommand;
use pocketmine\world\worldedit\command\defaults\utility\CancelCommand;
use pocketmine\world\worldedit\command\defaults\utility\DrainCommand;
use pocketmine\world\worldedit\command\defaults\utility\FillCommand;
use pocketmine\world\worldedit\command\defaults\utility\HelpCommand;
use pocketmine\world\worldedit\command\defaults\utility\LineCommand;
use pocketmine\world\worldedit\command\defaults\utility\PasteStatesCommand;
use pocketmine\world\worldedit\command\defaults\utility\StatusCommand;
use pocketmine\world\worldedit\command\defaults\utility\WandCommand;
use pocketmine\world\worldedit\command\FlagRemapAlias;
use pocketmine\world\worldedit\command\flags\IntegerCommandFlag;
use pocketmine\world\worldedit\command\flags\SingularCommandFlag;
use pocketmine\world\worldedit\environment\MainThreadHandler;
use pocketmine\world\worldedit\environment\ThreadEnvironmentHandler;
use pocketmine\world\worldedit\listener\ChunkRefreshListener;
use pocketmine\world\worldedit\listener\DefaultEventListener;
use pocketmine\world\worldedit\selection\SelectionContext;
use pocketmine\world\worldedit\task\editing\DynamicPasteTask;
use pocketmine\world\worldedit\thread\EditAdapter;
use pocketmine\world\worldedit\thread\EditThread;
use pocketmine\world\worldedit\utils\ConfigManager;
use pocketmine\world\worldedit\world\clientblock\Registry;
use pocketmine\world\worldedit\world\clientblock\RegistryUpdateTask;
use pocketmine\plugin\CorePluginHost;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Config;
use PrefixedLogger;
use Symfony\Component\Filesystem\Path;
use function copy;
use function dirname;
use function file_exists;
use function fopen;
use function is_dir;
use function mkdir;
use function rename;
use function rtrim;
use const DIRECTORY_SEPARATOR;

/**
 * Core WorldEdit module (formerly EasyEdit plugin).
 */
final class WorldEdit{

	private static self $instance;
	private static ThreadEnvironmentHandler $environment;

	private string $dataFolder;
	private Config $config;
	private \AttachableLogger|\Logger $logger;
	private bool $enabled = false;

	private function __construct(
		private Server $server,
		private CorePluginHost $pluginHost
	){
		$this->logger = new PrefixedLogger($server->getLogger(), "WorldEdit");
		$this->dataFolder = Path::join($server->getDataPath(), "plugin_data", "WorldEdit") . DIRECTORY_SEPARATOR;
		$this->migrateLegacyDataFolder();
		if(!is_dir($this->dataFolder) && !mkdir($this->dataFolder, 0777, true) && !is_dir($this->dataFolder)){
			throw new AssumptionFailedError("Failed to create WorldEdit data folder");
		}
		$this->saveDefaultConfig();
		$this->config = new Config($this->dataFolder . "config.yml", Config::YAML);
	}

	public static function enable(Server $server, CorePluginHost $host) : void{
		if(isset(self::$instance) && self::$instance->enabled){
			return;
		}
		$inst = new self($server, $host);
		self::$instance = $inst;
		$inst->boot();
	}

	public static function disable() : void{
		if(!isset(self::$instance) || !self::$instance->enabled){
			return;
		}
		try{
			EditThread::getInstance()->quit();
		}catch(\Throwable){
			// thread may already be gone
		}
		self::$instance->enabled = false;
	}

	private function boot() : void{
		self::setEnvironment(new MainThreadHandler());

		if(!is_dir(self::getSchematicPath()) && !mkdir(self::getSchematicPath(), 0777, true) && !is_dir(self::getSchematicPath())){
			throw new AssumptionFailedError("Failed to create schematic directory");
		}

		$thread = new EditThread($this->server->getLogger());
		$thread->start();

		ConfigManager::load();

		$this->getScheduler()->scheduleRepeatingTask(new EditAdapter(), 1);

		DefaultEventListener::init();
		$this->server->getPluginManager()->registerEvents(ChunkRefreshListener::getInstance(), $this->pluginHost);

		CommandManager::registerCommands([
			new GenericPositionCommand(1),
			new GenericPositionCommand(2),
			new DeselectCommand(),
			new ExtendCommand(),
			new SetCommand(),
			new ReplaceCommand(),
			new OverlayCommand(),
			new NaturalizeCommand(),
			new SmoothCommand(),
			new AliasedContextCommand(SelectionContext::center(), "/center", ["/middle"]),
			new AliasedContextCommand(SelectionContext::walls(), "/walls", ["/wall"]),
			new AliasedContextCommand(SelectionContext::hollow(), "/sides", ["/side", "/hset", "/hollow"]),
			new MoveCommand(),
			$stack = new StackCommand(),
			new FlagRemapAlias($stack, new SingularCommandFlag("insert"), "/istack"),
			new CountCommand(),
			new ExtinguishCommand(),
			new ViewCommand(),

			new HistoryAccessCommand(true),
			new HistoryAccessCommand(false),

			$copy = new CopyCommand(),
			new FlagRemapAlias($copy, new SingularCommandFlag("remove"), "/cut"),
			$paste = new PasteCommand(),
			new FlagRemapAlias($paste, IntegerCommandFlag::with(DynamicPasteTask::MODE_REPLACE_AIR, "mode"), "/insert"),
			new FlagRemapAlias($paste, IntegerCommandFlag::with(DynamicPasteTask::MODE_ONLY_SOLID, "mode"), "/merge"),
			new FlagRemapAlias($paste, IntegerCommandFlag::with(DynamicPasteTask::MODE_REPLACE_SOLID, "mode"), "/rpaste"),
			new RotateCommand(),
			new FlipCommand(),
			new LoadSchematicCommand(),
			new SaveSchematicCommand(),

			$sphere = new SphereCommand(),
			new FlagRemapAlias($sphere, new SingularCommandFlag("hollow"), "/hsphere", ["/hsph", "/hollowsphere"]),
			$cylinder = new CylinderCommand(),
			new FlagRemapAlias($cylinder, new SingularCommandFlag("hollow"), "/hcylinder", ["/hcy", "/hcyl", "/hollowcylinder"]),
			new NoiseCommand(),
			$cube = new CubeCommand(),
			new FlagRemapAlias($cube, new SingularCommandFlag("hollow"), "/hcube", ["/hollowcube", "/hcb"]),

			new HelpCommand(),
			new BrushCommand(),
			new FillCommand(),
			new DrainCommand(),
			new LineCommand(),
			new BlockInfoCommand(),
			new BuilderRodCommand(),
			new StatusCommand(),
			new CancelCommand(),
			new BenchmarkCommand(),
			new PasteStatesCommand(),
			new WandCommand(),

			new ThruCommand(),
			new UnstuckCommand(),
			new UpCommand()
		]);

		Registry::registerToNetwork();
		$this->server->getAsyncPool()->addWorkerStartHook(function(int $workerId) : void{
			$this->server->getAsyncPool()->submitTaskToWorker(new RegistryUpdateTask(), $workerId);
		});

		$this->enabled = true;
		$this->logger->info("WorldEdit module enabled");
	}

	private function migrateLegacyDataFolder() : void{
		$legacy = Path::join($this->server->getDataPath(), "plugin_data", "EasyEdit");
		if(is_dir($legacy) && !is_dir(rtrim($this->dataFolder, "/\\"))){
			$parent = Path::join($this->server->getDataPath(), "plugin_data");
			if(!is_dir($parent)){
				mkdir($parent, 0777, true);
			}
			rename($legacy, rtrim($this->dataFolder, "/\\"));
			$this->logger->notice("Migrated plugin_data/EasyEdit -> plugin_data/WorldEdit");
		}
	}

	public static function getInstance() : self{
		return self::$instance;
	}

	public function getPluginHost() : CorePluginHost{
		return $this->pluginHost;
	}

	public function getScheduler() : TaskScheduler{
		return $this->pluginHost->getScheduler();
	}

	public function getLogger() : \AttachableLogger|\Logger{
		return $this->logger;
	}

	public function getDataFolder() : string{
		return $this->dataFolder;
	}

	public function getConfig() : Config{
		return $this->config;
	}

	public function saveDefaultConfig() : void{
		$this->saveResource("config.yml", false);
	}

	/**
	 * @return resource|null
	 */
	public function getResource(string $filename){
		$path = Path::join(self::getResourcePathLegacy(), $filename);
		if(!file_exists($path)){
			return null;
		}
		$stream = fopen($path, "rb");
		return $stream === false ? null : $stream;
	}

	public function saveResource(string $filename, bool $replace = false) : bool{
		$out = Path::join($this->dataFolder, $filename);
		if(!$replace && file_exists($out)){
			return false;
		}
		$dir = dirname($out);
		if(!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)){
			return false;
		}
		$src = Path::join(self::getResourcePathLegacy(), $filename);
		if(!file_exists($src)){
			return false;
		}
		return copy($src, $out);
	}

	public static function setEnvironment(ThreadEnvironmentHandler $environment) : void{
		if(isset(self::$environment)){
			throw new AssumptionFailedError("Environment already set");
		}
		self::$environment = $environment;
	}

	public static function getEnv() : ThreadEnvironmentHandler{
		return self::$environment;
	}

	/**
	 * Bundled resources root (no trailing slash).
	 * @internal
	 */
	public static function getResourcePathLegacy() : string{
		return Path::join(\pocketmine\RESOURCE_PATH, "worldedit");
	}

	public static function getSchematicPath() : string{
		return self::getInstance()->getDataFolder() . "schematics" . DIRECTORY_SEPARATOR;
	}

	public static function getCachePath() : string{
		return self::getInstance()->getDataFolder() . "cache" . DIRECTORY_SEPARATOR;
	}
}
