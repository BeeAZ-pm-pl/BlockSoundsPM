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

namespace Xenophilicy\BlockSounds;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use Xenophilicy\BlockSounds\Command\BlockSound;

class BlockSounds extends PluginBase implements Listener {
    
    private static $blocks = [];
    private static $sessions = [];
    private static $cooldowns = [];
    private static $settings;
    private $blocksConfig;
    
    public static function setSession(string $name, string $mode, array $args = []){
        self::$sessions[$name] = [$mode, $args];
    }
    
    public function onEnable(): void{
        $this->saveResource("blocks.yml");
        $this->blocksConfig = new Config($this->getDataFolder() . "blocks.yml", Config::YAML);
        self::$blocks = $this->blocksConfig->getAll();
        self::$settings = $this->getConfig()->getAll();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getCommandMap()->register($this->getDescription()->getName(), new BlockSound("blocksounds", $this));
    }

    private function executeEventAction($event): void{
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if(!isset(self::$sessions[$player->getName()])){
            if(!$this->isCooled($player->getName())) return;
            $block = $this->getBlock($block);
            if(is_null($block)) return;
            $sound = $block[0];
            $pitch = $block[1];
            if(!$player->hasPermission("blocksound.use")) return;
            $event->cancel();
            $this->playSound($sound, $player, $pitch);
            return;
        }
        $event->cancel();
        $mode = self::$sessions[$player->getName()][0];
        $args = self::$sessions[$player->getName()][1];
        switch($mode){
            case "set":
                $soundName = array_shift($args);
                if($soundName === null || trim($soundName) === ""){
                    $player->sendMessage(TF::RED . "You must specify a sound to add");
                    break;
                }
                $pitch = array_shift($args) ?? 1;
                if(!is_numeric($pitch) && $pitch !== "random"){
                    $player->sendMessage(TF::RED . "Pitch must be either 'random' or a float greater than 0");
                    break;
                }
                $this->createBlock($block, $soundName, $pitch);
                $player->sendMessage(TF::GREEN . "Block sound set");
                break;
            case "remove":
                $target = $this->removeBlock($block);
                if(!$target){
                    $player->sendMessage(TF::RED . "That block has no sound");
                    break;
                }
                $player->sendMessage(TF::GREEN . "Block sound removed");
                break;
        }
        unset(self::$sessions[$player->getName()]);
    }
    
    public function onBlockBreak(BlockBreakEvent $event): void{
        $this->executeEventAction($event);
    }
    

    public function onInteract(PlayerInteractEvent $event): void{
        $this->executeEventAction($event);
    }
    
   
    private function isCooled(string $player): bool{
        if(isset(self::$cooldowns[$player]) && self::$cooldowns[$player] + 1 > time()) return false;
        self::$cooldowns[$player] = time();
        return true;
    }
    
    
    private function getBlock(Block $block): ?array{
        $b = $block;
        $coords = $b->getPosition()->x . ":" . $b->getPosition()->y . ":" . $b->getPosition()->z . ":" . $b->getPosition()->getWorld()->getFolderName();
        if(!isset(self::$blocks[$coords])) return null;
        return self::$blocks[$coords];
    }
    
    public function playSound(string $soundName, Player $player, float $pitch){
        if($pitch == "random") $pitch = mt_rand(self::$settings["random"]["min"], self::$settings["random"]["max"]);
        $sound = new PlaySoundPacket();
        $sound->x = (int)$player->getPosition()->getX();
        $sound->y = (int)$player->getPosition()->getY();
        $sound->z = (int)$player->getPosition()->getZ();
        $sound->volume = 1;
        $sound->pitch = $pitch;
        $sound->soundName = $soundName;
        $this->getServer()->broadcastPackets([$player], [$sound]);
    }
    
   
    private function createBlock(Block $block, string $soundName, $pitch): void{
        $b = $block;
        $coords = $b->getPosition()->x . ":" . $b->getPosition()->y . ":" . $b->getPosition()->z . ":" . $b->getPosition()->getWorld()->getFolderName();
        self::$blocks[$coords] = [$soundName, $pitch];
    }
   
    private function removeBlock(Block $block): bool{
        $b = $block;
        $coords = $b->getPosition()->x . ":" . $b->getPosition()->y . ":" . $b->getPosition()->z . ":" . $b->getPosition()->getWorld()->getFolderName();
        if(!isset(self::$blocks[$coords])) return false;
        unset(self::$blocks[$coords]);
        return true;
    }
    
    public function onDisable(): void{
        $this->blocksConfig->setAll(self::$blocks);
        $this->blocksConfig->save();
    }
}
