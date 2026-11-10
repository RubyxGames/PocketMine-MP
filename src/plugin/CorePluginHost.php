<?php

declare(strict_types=1);

namespace pocketmine\plugin;

use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;
use function file_exists;
use function mkdir;

/**
 * Always-enabled internal plugin host so core subsystems can register event listeners.
 *
 * @internal
 */
final class CorePluginHost implements Plugin{

	private bool $enabled = true;
	private PluginDescription $description;
	private TaskScheduler $scheduler;
	private PluginLogger $logger;
	private string $dataFolder;

	public function __construct(
		private PluginLoader $loader,
		private Server $server,
		PluginDescription $description,
		string $dataFolder,
		private string $file,
		private ResourceProvider $resourceProvider
	){
		$this->description = $description;
		$this->dataFolder = $dataFolder;
		if(!file_exists($this->dataFolder)){
			mkdir($this->dataFolder, 0777, true);
		}
		$this->scheduler = new TaskScheduler($description->getFullName());
		$this->logger = new PluginLogger($server->getLogger(), $description->getName());
	}

	public static function create(Server $server) : self{
		// main must not be under pocketmine\ (PluginDescription rule); this host is never phar-loaded.
		$description = new PluginDescription([
			"name" => "RubyxCore",
			"version" => "1.0.0",
			"main" => "internal\\rubyx\\CoreHost",
			"api" => ["5.0.0"],
		]);
		$loader = new class implements PluginLoader{
			public function canLoadPlugin(string $path) : bool{ return false; }
			public function loadPlugin(string $file) : void{}
			public function getPluginDescription(string $file) : ?PluginDescription{ return null; }
			public function getAccessProtocol() : string{ return ""; }
		};
		$resources = new class implements ResourceProvider{
			public function getResource(string $filename){ return null; }
			public function getResources() : array{ return []; }
		};
		return new self(
			$loader,
			$server,
			$description,
			$server->getDataPath() . "rubyx_core/",
			"",
			$resources
		);
	}

	public function isEnabled() : bool{
		return $this->enabled;
	}

	public function onEnableStateChange(bool $enabled) : void{
		$this->enabled = $enabled;
	}

	public function getDataFolder() : string{
		return $this->dataFolder;
	}

	public function getDescription() : PluginDescription{
		return $this->description;
	}

	public function getName() : string{
		return $this->description->getName();
	}

	public function getLogger() : PluginLogger{
		return $this->logger;
	}

	public function getPluginLoader() : PluginLoader{
		return $this->loader;
	}

	public function getScheduler() : TaskScheduler{
		return $this->scheduler;
	}

	public function getServer() : Server{
		return $this->server;
	}
}
