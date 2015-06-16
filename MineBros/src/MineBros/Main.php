<?php

namespace MineBros;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use MineBros\character\CharacterLoader;
use MineBros\character\BaseCharacter;

class Main extends PluginBase {

    const HEAD_MBROS = TextFormat::YELLOW.'[MineBros] '.TextFormat::WHITE;

    public $characterLoader;
    private $deathMatch = false;
    private $status = false;
    private $minutes = 0;
    private $cfg;
    protected $lastTID = NULL;

    public function onEnable(){
        @mkdir($this->getDataFolder());
        $this->cfg = new Config(Config::YAML, $this->getDataFolder().'config.yml', array('min_players' => 3, 'normal_game_minutes' => 9, 'deathmatch_minutes' => 1, 'deathmatch_pos' => array('x' => 'unset', 'y' => 'unset', 'z' => 'unset')));
        $this->characterLoader = new CharacterLoader($this);
        $this->getServer()->getPluginManager()->registerEvents($this->characterLoader, $this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new GameSchedulingTask, 20*60);
    }

    public function isStarted(){
        return $this->status;
    }

    public function onJoin(PlayerJoinEvent $ev){
        if($this->status === true and !isset($this->characterLoader->nameDict[$ev->getPlayer()->getName()])) $this->characterLoader->chooseRandomCharacter($ev->getPlayer(), true);
        if($this->status === true and $this->deathMatch === true) $ev->getPlayer()->teleport(new Vector3((int) $this->cfg->get('deathmatch_pos')['x'], (int) $this->cfg->get('deathmatch_pos')['y'], (int) $this->cfg->get('deathmatch_pos')['z']));
    }

    public function onQuit(PlayerQuitEvent $ev){
        if($this->status === false) unset($name = $this->characterLoader->nameDict[$ev->getPlayer()->getName]);
    }

    public function minuteSchedule(){
        if($this->cfg->get('deathmatch_pos')['x'] === 'unset' or
           $this->cfg->get('deathmatch_pos')['y'] === 'unset' or
           $this->cfg->get('deathmatch_pos')['z'] === 'unset'){
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.TextFormat::RED.'/mi setdp로 데스매치 장소를 선택하셔야 게임을 진행할 수 있습니다.');
            return;
        }
        if($this->status) $this->minutes++;
        if(count($this->getServer()->getOnlinePlayers()) < (int) $this->cfg->get('min_players')){
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.'사람이 너무 적습니다. 최소 '.TextFormat::GREEN.$this->cfg->get('min_players').'명의 사람이 필요합니다.');
            return;
        } elseif($this->status === false) {
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.TextFormat::BOLD.'게임이 시작되었습니다! /mi help로 자신의 능력을 확인해보세요.');
            $this->startGame();
        } elseif($this->minutes == $this->cfg->get('normal_game_minutes') - 1) {
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.TextFormat::RED.'데스매치가 1분 남았습니다. 데스매치 시작 시 모두가 지정된 장소로 이동합니다.');
        } elseif($this->minutes == $this->cfg->get('normal_game_minutes')){
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.TextFormat::RED.'데스매치를 시작합니다. 모든 데미지가 0.5배 증가합니다.');
            $this->deathMatch = true;
            foreach($this->getServer()->getOnlinePlayers() as $p) $p->teleport(new Vector3((int) $this->cfg->get('deathmatch_pos')['x'], (int) $this->cfg->get('deathmatch_pos')['y'], (int) $this->cfg->get('deathmatch_pos')['z']));
        } elseif($this->minutes == ((int)$this->cfg->get('normal_game_minutes')) + ((int)$this->cfg->get('deathmatch_minutes'))) {
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.TextFormat::GREEN.'게임이 완전히 종료되었습니다. 스폰 포인트로 돌아갑니다.');
            foreach($this->getServer()->getOnlinePlayers() as $p) $p->teleport($p->getSpawn());
            $this->endGame();
            $this->minutes = 0;
        }
    }

//TODO: random skill shuffling in startGame(), /mi command

    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
        if(count($args) < 1 or ($args[0] === 'setdp' and !$sender->isOp())) return true;
        switch($args){
            case 'help':
                if($sender->getName() === "CONSOLE" or !$this->status){
                    $sender->sendMessage('사용이 불가능합니다.');
                    return true;
                }
                $ch = $this->characterLoader->characters[$this->characterLoader->nameDict[$sender->getName()]];
                $sender->sendMessage(self::HEAD_MBROS.'능력 이름: '.$ch->getName());
                if($ch & BaseCharacter::CLASS_B)                                       $color = TextFormat::DARK_BLUE.'B';
                if(($ch & BaseCharacter::CLASS_B) && (ch & BaseCharacter::CLASS_PLUS)) $color = TextFormat::BLUE.'B+';
                if($ch & BaseCharacter::CLASS_A)                                       $color = TextFormat::DARK_RED.'A';
                if(($ch & BaseCharacter::CLASS_A) && (ch & BaseCharacter::CLASS_PLUS)) $color = TextFormat::RED.'A+';
                if($ch & BaseCharacter::CLASS_S)                                       $color = TextFormat::GOLD.'S';
                if(($ch & BaseCharacter::CLASS_S) && (ch & BaseCharacter::CLASS_PLUS)) $color = TextFormat::YELLOW.'S+';
                $sender->sendMessage(self::HEAD_MBROS.'능력 등급: '.$color);
                $l = 1;
                foreach(explode("\n", $ch->getDescription()) as $line){
                    if($l === 1){
                        $sender->sendMessage(self::HEAD_MBROS.'능력 설명: '.$line);
                        $line++;
                        continue;
                    }
                    $sender->sendMessage($line);
                    $line++;
                }
                switch($color{2}){
                    case 'B':
                        $sec = 18;
                        if($color{3} === '+') $sec += 7;
                        break;
                    case 'A':
                        $sec = 35;
                        if($color{3} === '+') $sec += 10;
                        break;
                    case 'S':
                        $sec = 52;
                        if($color{3} === '+') $sec += 13;
                        break;
                }
                $sender->sendMessage(self::HEAD_MBROS.'능력 쿨타임: '.TextFormat::AQUA.$sec.'초');
                return true;
                break;

            case 'setdp':
                //
                break;

            case 'rank':
                //
                break;
        }
    }

    public function startGame(){
        if($this->status){
            $this->getLogger()->emergency("FATAL ERROR: MineBros: Game started while game is running");
            $this->getServer()->shutdown();
        }
        $this->status = true;
        $this->lastTID = $this->getServer()->getScheduler()->scheduleRepeatingTask(new PassiveSkillTask, 10)->getTaskId();
        foreach($this->getServer()->getOnlinePlayers() as $p) $this->characterLoader->chooseRandomCharacter($p);
    }

    public function endGame(){
        $this->getServer()->getScheduler()->cancelTask($this->lastTID);
        $this->lastTID = NULL;
        $this->deathMatch = $this->status = false;
        $this->characterLoader->reset();
    }

}