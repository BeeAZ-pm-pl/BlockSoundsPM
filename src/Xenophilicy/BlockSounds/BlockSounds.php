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
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use Xenophilicy\BlockSounds\Command\BlockSound;

/**
 * Class BlockSounds
 * @package blocksound
 */
class BlockSounds extends PluginBase implements Listener {
    
    private static $blocks = [];
    private static $sessions = [];
    private static $cooldowns = [];
    private static $settings;
    private $blocksConfig;
    
    /**
     * @param string $name
     * @param string $mode
     * @param array $args
     */
    public static function setSession(string $name, string $mode, array $args = []){
        self::$sessions[$name] = [$mode, $args];
    }
    
    public function onEnable(){
        $this->saveResource("blocks.yml");
        $this->blocksConfig = new Config($this->getDataFolder() . "blocks.yml", Config::YAML);
        self::$blocks = $this->blocksConfig->getAll();
        self::$settings = $this->getConfig()->getAll();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getCommandMap()->register("blocksound", new BlockSound("blocksound", $this));
    }
    
    /**
     * @param $event
     */
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
            $event->setCancelled();
            $this->playSound($sound, $player, $pitch);
            return;
        }
        $event->setCancelled();
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
    
    /**
     * @param string $player
     * @return bool
     */
    private function isCooled(string $player): bool{
        if(isset(self::$cooldowns[$player]) && self::$cooldowns[$player] + 1 > time()) return false;
        self::$cooldowns[$player] = time();
        return true;
    }
    
    /**
     * @param Block $block
     * @return array|null
     */
    private function getBlock(Block $block): ?array{
        $b = $block;
        $coords = $b->x . ":" . $b->y . ":" . $b->z . ":" . $b->getLevel()->getFolderName();
        if(!isset(self::$blocks[$coords])) return null;
        return self::$blocks[$coords];
    }
    
    /**
     * @param string $soundName
     * @param Player $player
     * @param float $pitch
     */
    public function playSound(string $soundName, Player $player, $pitch){
        if($pitch === "random") $pitch = mt_rand(self::$settings["random"]["min"], self::$settings["random"]["max"]);
        $sound = new PlaySoundPacket();
        $sound->x = $player->getX();
        $sound->y = $player->getY();
        $sound->z = $player->getZ();
        $sound->volume = 1;
        $sound->pitch = $pitch;
        $sound->soundName = $soundName;
        $this->getServer()->broadcastPacket([$player], $sound);
    }
    
    /**
     * @param Block $block
     * @param string $soundName
     * @param float $pitch
     */
    private function createBlock(Block $block, string $soundName, $pitch): void{
        $b = $block;
        $coords = $b->x . ":" . $b->y . ":" . $b->z . ":" . $b->getLevel()->getFolderName();
        self::$blocks[$coords] = [$soundName, $pitch];
    }
    
    /**
     * @param Block $block
     * @return bool
     */
    private function removeBlock(Block $block): bool{
        $b = $block;
        $coords = $b->x . ":" . $b->y . ":" . $b->z . ":" . $b->getLevel()->getFolderName();
        if(!isset(self::$blocks[$coords])) return false;
        unset(self::$blocks[$coords]);
        return true;
    }
    
    public function onDisable(){
        $this->blocksConfig->setAll(self::$blocks);
        $this->blocksConfig->save();
    }
}
