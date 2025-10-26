<?php

namespace KitUI\Commands;

use KitUI\Libs\jojoe77777\FormAPI\SimpleForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;

class Kit extends Command
{
    private array $cooldowns = [];
    private Config $config;

    public function __construct(private readonly Plugin $plugin)
    {
        parent::__construct("kit", "Opens the UI to select a kit.", "/kit");
        $this->setPermission(DefaultPermissions::ROOT_USER);

        $this->plugin->saveResource("config.yml");
        $this->config = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);
    }

    private function hasCooldown(Player $player, string $kit, int $seconds): bool
    {
        $playerName = $player->getName();

        if (!isset($this->cooldowns[$playerName][$kit])) {
            return false;
        }

        $lastUsed = $this->cooldowns[$playerName][$kit];
        $timePassed = time() - $lastUsed;

        return $timePassed < $seconds;
    }

    private function setCooldown(Player $player, string $kit): void
    {
        $this->cooldowns[$player->getName()][$kit] = time();
    }

    private function getRemainingCooldown(Player $player, string $kit, int $seconds): int
    {
        $lastUsed = $this->cooldowns[$player->getName()][$kit];
        return $seconds - (time() - $lastUsed);
    }

    private function createItem(array $itemData): ?Item
    {
        $item = StringToItemParser::getInstance()->parse($itemData["type"]);

        if ($item === null) {
            return null;
        }

        if (isset($itemData["count"])) {
            $item->setCount($itemData["count"]);
        }

        if (isset($itemData["enchantments"])) {
            foreach ($itemData["enchantments"] as $enchantData) {
                $enchantment = StringToEnchantmentParser::getInstance()->parse($enchantData["name"]);
                if ($enchantment !== null) {
                    $item->addEnchantment(new EnchantmentInstance($enchantment, $enchantData["level"]));
                }
            }
        }

        if (isset($itemData["lore"])) {
            $item->setLore($itemData["lore"]);
        }

        if (isset($itemData["unbreakable"]) && $itemData["unbreakable"]) {
            $item->setUnbreakable();
        }

        return $item;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $form = new SimpleForm(function (Player $player, mixed $data) {
            if ($data === null) {
                return;
            }

            $kits = $this->config->get("kits", []);

            if (!isset($kits[$data])) {
                $player->sendActionBarMessage("§cKit not found.");
                return;
            }

            $kitData = $kits[$data];

            // Check permission
            if (!empty($kitData["permission"]) && !$player->hasPermission($kitData["permission"])) {
                $player->sendActionBarMessage("§cYou do not have permission to use this kit.");
                return;
            }

            // Check cooldown
            $cooldown = $kitData["cooldown"] ?? 0;
            if ($this->hasCooldown($player, $data, $cooldown)) {
                $remaining = $this->getRemainingCooldown($player, $data, $cooldown);
                $player->sendActionBarMessage("§cYou must wait " . gmdate("H:i:s", $remaining) . " before using this kit again.");
                return;
            }

            // Give items
            foreach ($kitData["items"] as $itemData) {
                $item = $this->createItem($itemData);
                if ($item !== null) {
                    $player->getInventory()->addItem($item);
                }
            }

            $this->setCooldown($player, $data);
            $player->sendActionBarMessage("§aYou have been given the kit §e" . ucfirst($data) . "§a.");
        });

        $form->setTitle("Kits");
        $form->setContent("Select a kit.");

        $kits = $this->config->get("kits", []);
        foreach ($kits as $kitName => $kitData) {
            $form->addButton(ucfirst($kitName), label: $kitName);
        }

        $sender->sendForm($form);
    }
}
