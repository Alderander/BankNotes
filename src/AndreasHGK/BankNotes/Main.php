<?php

namespace AndreasHGK\BankNotes;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\inventory\Inventory;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\Listener;
use pocketmine\Player;
use onebone\economyapi\EconomyAPI;
use pocketmine\utils\TextFormat as C;

class Main extends PluginBase implements Listener{
	
	public $NoteVersion = 2.0;
	private $cfg;
	public $CompalibleVersions = [2.0];

	public function onEnable() : void{
		@mkdir($this->getDataFolder());
		$this->saveDefaultConfig();
		$this->cfg = $this->getConfig()->getAll();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	
	public function onDisable() : void{
        $this->config->save();
    }
	
	public function replaceVars($str, array $vars) : string{
        foreach($vars as $key => $value){
            $str = str_replace("{" . $key . "}", $value, $str);
        }
        return $str;
    }

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		$player = $sender->getName();
		#check if command is ran from console
		if(!($sender instanceof Player)){
			$sender->sendMessage(C::colorize($this->cfg["error-execute-ingame"]));
			return true;
		}
		switch(strtolower($command->getName())){
			
			#future command
/* 			case "banknotes":
				
				switch(strtolower($args[0])){
					case "admin":
					switch(strtolower($args[1])){
						case "reload":
						return true;
						
						default:
							$sender->sendMessage(C::RED.C::BOLD."Invalid command");
						return true;
						break;
					}
					return true;
					
					case "check":
					return true;
					
					default:
						$sender->sendMessage(C::RED.C::BOLD."Invalid command");
					return true;
					break;
				}
				
			return true;
			break; */
			
			case "note":
			case "withdraw":
				
				#check if player used arguments
				if(empty($args[0])){
					$sender->sendMessage(C::colorize($this->replaceVars($this->cfg["error-empty-argument"], array(
						"USER" => $player))));
				return true;
			} else{
				
				#check if argument is integer
				if(!is_int($args[0])){
					$amount = (int)$args[0];
				}
				if(is_int($amount)){
					
				$bal = EconomyAPI::getInstance()->myMoney($player);	
				if($bal >= $amount) {
					if($amount > 0){
						
					#make and give the custom bank note and reduce playermoney
					EconomyAPI::getInstance()->reduceMoney($player, $amount);
					
					$note = $this->noteItem($amount, $player);
					
					$sender->getInventory()->addItem($note);
					$sender->sendMessage(C::colorize($this->replaceVars($this->cfg["withdraw-sucess"], array(
						"VALUE" => $amount,
						"USER" => $player))));
					return true;
					} else {
						$sender->sendMessage(C::colorize($this->replaceVars($this->cfg["error-value-invalid"], array(
						"VALUE" => $amount,
						"USER" => $player))));
						return true;
					}
				} else {
					$playermoney = EconomyAPI::getInstance()->myMoney($player);
					$sender->sendMessage(C::colorize($this->replaceVars($this->cfg["error-insufficient-money"], array(
						"VALUE" => $amount,
						"USER" => $player,
						"BAL" => $playermoney))));
					return true;
				}
				} else{
					$sender->sendMessage(C::colorize($this->replaceVars($this->cfg["error-invalid-argument"], array(
						"VALUE" => $amount,
						"USER" => $player))));
					return true;
				}
			}
			break;
			
			case "deposit":
			$return = $this->depositCheck($sender);
			switch($return){
				case -1:
				$sender->sendMessage(C::colorize($this->replaceVars($this->cfg["error-note-incompatible"], array(
					"USER" => $player))));
				return true;
				break;
			
				case -2:
				$sender->sendMessage(C::colorize($this->replaceVars($this->cfg["error-note-invalid"], array(
					"USER" => $player))));
				return true;
				break;
				
				case -3;
				return true;
				break;

				default:
				$sender->sendMessage(C::colorize($this->replaceVars($this->cfg["deposit-succes"], array(
					"VALUE" => $return,
					"USER" => $player))));
				return true;
				break;
				
			}return true;
		
		} return false;
	}
	
	#Thanks to JackMD for providing help with this part!
 	public function onInteract(PlayerInteractEvent $event) : void{
		$p = $event->getPlayer();
		$name = $p->getName();
		$return = $this->depositCheck($event->getPlayer(), true, $event);
		switch($return){
			case -1:
			$p->sendMessage(C::colorize($this->replaceVars($this->cfg["error-note-incompatible"], array(
				"USER" => $name))));
			
			case -2:
			break;
			
			case -3:
			break;
			
			default:
			$p->sendMessage(C::colorize($this->replaceVars($this->cfg["deposit-succes"], array(
				"VALUE" => $return,
				"USER" => $name))));
			break;
		}
	}
	
	/* error codes
	-1 : oudated note
	-2 : invalid note
	-3 : illegal interaction
	anything else : value of note */
	public function depositCheck(player $p, bool $interact = false, PlayerInteractEvent $event = NULL) : int{
		
		$name = $p->getName();
		$inv = $p->getInventory();
		$hand = $inv->getItemInHand();
		$nbt = $hand->getNamedTag();
		if ($nbt->getByte("IsValidNote", false) == true) {
			if($nbt->getInt("NoteVersion", 0) == $this->NoteVersion){
				
				#check to see on which block the player claims the note if it's a playerinteractevent
				if($interact){
					switch($event->getBlock()->getName()){
					
						case "Item Frame":
						case "Anvil":
						case "Crafting Table":
						case "Furnace":
						case "Chest":
						case "Brewing Stand":
						case "Cake":
						case "Door":
						case "Wooden Door":
						case "Wooden Button":
						case "Stone Button":
						case "Enchanting Table":
						case "Ender Chest":
						case "Fence Gate":
						case "Iron Door":
						case "Stonecutter":
						case "Trapped Chest":
						case "Wooden Trapdoor":
						case "Bed":
							return -3;
							break;
						
						default:
							return $this->deposit($p);
							break;
					}
				}else{
					return $this->deposit($p);
				}
			}else{
				return -1;
			}
		}else{
			return -2;
		}
	}
	
	public function deposit(player $p) : int{
		
		$name = $p->getName();
		$inv = $p->getInventory();
		$hand = $inv->getItemInHand();
		$nbt = $hand->getNamedTag();
		
		$dep = $nbt->getInt("NoteValue");
		EconomyAPI::getInstance()->addMoney($name, $dep);
		$hand->setCount($hand->getCount() - 1);
		$inv->setItemInHand($hand);
		return $dep;
	}
	
	#returns the note as item
	public function noteItem(int $amount, string $player = "ADMIN") : item{
		$note = Item::get($this->cfg["note-id"], 0, 1);
		$note->setCustomName(C::colorize($this->replaceVars($this->cfg["note-name"], array(
			"VALUE" => $amount,
			"USER" => $player))));
					
		$loreint = 0;
		$lorearray	;
		foreach($this->cfg["note-lore"] as $line){
			$lorearray[$loreint] = C::colorize($this->replaceVars($line, array("VALUE" => $amount, "CREATOR" => $player)));
			$loreint++;
		}
					
		$note->setLore($lorearray);
		$nbt = $note->getNamedTag();
		$nbt->setTag(new ByteTag("IsValidNote", true));
		$nbt->setTag(new IntTag("NoteVersion", $this->NoteVersion));
		$nbt->setTag(new IntTag("NoteValue", $amount));
		$nbt->setTag(new StringTag("Creator", $player));
		$note->setCompoundTag($nbt);
		
		return $note;
	}
}