<?php
# MADE BY:
#  __    __                                          __        __  __  __
# /  |  /  |                                        /  |      /  |/  |/  |
# $$ |  $$ |  ______   _______    ______    ______  $$ |____  $$/ $$ |$$/   _______  __    __
# $$  \/$$/  /      \ /       \  /      \  /      \ $$      \ /  |$$ |/  | /       |/  |  /  |
#  $$  $$<  /$$$$$$  |$$$$$$$  |/$$$$$$  |/$$$$$$  |$$$$$$$  |$$ |$$ |$$ |/$$$$$$$/ $$ |  $$ |
#   $$$$  \ $$    $$ |$$ |  $$ |$$ |  $$ |$$ |  $$ |$$ |  $$ |$$ |$$ |$$ |$$ |      $$ |  $$ |
#  $$ /$$  |$$$$$$$$/ $$ |  $$ |$$ \__$$ |$$ |__$$ |$$ |  $$ |$$ |$$ |$$ |$$ \_____ $$ \__$$ |
# $$ |  $$ |$$       |$$ |  $$ |$$    $$/ $$    $$/ $$ |  $$ |$$ |$$ |$$ |$$       |$$    $$ |
# $$/   $$/  $$$$$$$/ $$/   $$/  $$$$$$/  $$$$$$$/  $$/   $$/ $$/ $$/ $$/  $$$$$$$/  $$$$$$$ |
#                                         $$ |                                      /  \__$$ |
#                                         $$ |                                      $$    $$/
#                                         $$/                                        $$$$$$/

namespace Xenophilicy\BlockSounds\Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat as TF;
use Xenophilicy\BlockSounds\BlockSounds;

/**
 * Class BlockSound
 * @package Xenophilicy\BlockSounds\Command
 */
class BlockSound extends Command{
    
    private $plugin;
    
    /**
     * @param string $name
     * @param BlockSounds $plugin
     */
    public function __construct(string $name, BlockSounds $plugin){
        parent::__construct($name, "Manage sounds on blocks");
        $this->plugin = $plugin;
        $this->setPermission("blocksound.command");
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool{
        if(!$sender->hasPermission($this->getPermission())){
            $sender->sendMessage(TF::RED . "You don't have permission to manage block sounds");
            return false;
        }
        if(!$sender instanceof Player){
            $sender->sendMessage(TF::RED . "This is an in-game command only");
            return false;
        }
        if(!isset($args[0])){
            $sender->sendMessage(TF::RED . "Usage: /blocksounds <set <sound> [pitch]|remove>");
            return false;
        }
        $mode = array_shift($args);
        switch($mode){
            case "create":
            case "new":
            case "set":
            case "add":
                if(!$this->hasPermission($sender, "set", $args)) return false;
                break;
            case "remove":
            case "rem":
            case "del":
            case "delete":
                if(!$this->hasPermission($sender, "remove")) return false;
                break;
            default:
                $sender->sendMessage(TF::RED . "Usage: /blocksounds <set <sound> [pitch]|remove>");
                return false;
        }
        $sender->sendMessage(TF::GREEN . "Tap a block to apply action");
        return true;
    }
    
    /**
     * @param Player $player
     * @param string $mode
     * @param array $args
     * @return bool
     */
    private function hasPermission(Player $player, string $mode, array $args = []): bool{
        if(!$player->hasPermission("blocksound.command." . $mode)){
            $player->sendMessage(TF::RED . "You don't have permission to use that function");
            return false;
        }
        BlockSounds::setSession($player->getName(), $mode, $args);
        return true;
    }
    
    public function getPlugin(): Plugin{
        return $this->plugin;
    }
}
