<?php

namespace KitUI;

use KitUI\Commands\Kit;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase
{
    /** @var self */
    private static self $instance;

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function onEnable(): void
    {
        self::$instance = $this;
        $this->getLogger()->info("KitUI has been enabled!");
        $cm = $this->getServer()->getCommandMap();
        $cm->register("kit", new Kit($this));
    }
    public function onDisable(): void
    {
        $this->getLogger()->info("KitUI has been disabled!");
    }
}
