<?php

require_once(APP_GAMEMODULE_PATH . 'module/table/table.game.php');
require_once('modules/constants.inc.php');
require_once('modules/polyfill.inc.php');
require_once('modules/NMap.class.php');
require_once('modules/NNode.class.php');
require_once('modules/NNodeHop.class.php');
require_once('modules/NNodePort.class.php');
require_once('modules/NPax.class.php');
require_once('modules/NPlane.class.php');

class NowBoarding extends Table
{
    public static $instance = null;

    function __construct()
    {
        parent::__construct();
        self::$instance = $this;
        self::initGameStateLabels([]);
    }

    protected function getGameName(): string
    {
        // Used for translations and stuff. Please do not modify.
        return "nowboarding";
    }

    protected function setupNewGame($players, $options = [])
    {
        $playerCount = count($players);
        $cash = $playerCount == 2 ? 19 : 12;

        // Create players and planes
        foreach ($players as $player_id => $player) {
            $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ($player_id, '000000', '" . $player['player_canal'] . "', '" . addslashes($player['player_name']) . "', '" . addslashes($player['player_avatar']) . "')";
            self::DbQuery($sql);

            $sql = "INSERT INTO plane (player_id, cash) VALUES ($player_id, $cash)";
            self::DbQuery($sql);
        }
        self::reloadPlayersBasicInfos();

        // Create weather tokens
        if ($playerCount == 2) {
            $weatherCount = 1;
        } else if ($playerCount == 3) {
            $weatherCount = 2;
        } else {
            $weatherCount = 3;
        }
        for ($i = 0; $i < $weatherCount; $i++) {
            self::DbQuery("INSERT INTO weather (token) VALUES ('FAST')");
            self::DbQuery("INSERT INTO weather (token) VALUES ('SLOW')");
        }

        // Create passengers

        // Activate all players
        $this->gamestate->setAllPlayersMultiactive();
    }

    public function checkVersion(int $clientVersion): void
    {
        $gameVersion = $this->gamestate->table_globals[N_OPTION_VERSION];
        if ($clientVersion != $gameVersion) {
            throw new BgaVisibleSystemException(self::_("A new version of this game is now available. Please reload the page (F5)."));
        }
    }

    protected function getAllDatas(): array
    {
        $players = self::getCollectionFromDb("SELECT player_id id, player_score score FROM player");
        $data = [
            'map' => NMap::load(),
            'planes' => NPlane::loadAll(),
            'players' => $players,
            'version' => intval($this->gamestate->table_globals[N_OPTION_VERSION]),
        ];

        return $data;
    }

    function getGameProgression(): int
    {
        return 0;
    }

    /*
     * PHASE 1: SETUP
     * Each player buys a color (2 colors in 2-player games)
     * Each player buys a seat or speed
     * Shuffle passengers
     */

    function stMultiactive()
    {
        $this->gamestate->setAllPlayersMultiactive();
    }

    function argBuild(): array
    {
        $args = [];
        $requiredCount = self::getPlayersNumber() == 2 ? 2 : 1;
        $planes = NPlane::loadAll();
        foreach ($planes as $playerId => $plane) {
            $buys = $plane->getBuys();
            if ($plane->getColorsCount() < $requiredCount) {
                $buys = array_values(array_filter($buys, function ($buy) {
                    return $buy['type'] == 'COLOR';
                }));
            } else {
                $buys = array_values(array_filter($buys, function ($buy) {
                    return $buy['type'] != 'EXTRA_SEAT' && $buy['type'] != 'EXTRA_SPEED';
                }));
            }
            $args[$playerId]['buys'] = $buys;
        }
        return $args;
    }

    function buy(string $type, ?string $color): void
    {
        $state = $this->gamestate->state();
        $playerId = self::getCurrentPlayerId();
        $plane = NPlane::loadById($playerId);

        $args = [];
        if ($type == 'COLOR') {
            $plane->buyColor($color);
            $msg = '${player_name} joins the ${colorFancy} alliance';
            $args = [
                'i18n' => ['colorFancy'],
                'colorFancy' => $color,
            ];
            if ($state['name'] == 'build' && $plane->getColorsCount() == 1) {
                // The player's color was just assigned
                $args['plane'] = $plane;
                self::reloadPlayersBasicInfos();
            }
        } else if ($type == 'EXTRA_SEAT') {
            $plane->buyExtraSeat();
            $msg = '${player_name} borrows the temporary seat';
        } else if ($type == 'EXTRA_SPEED') {
            $plane->buyExtraSpeed();
            $msg = '${player_name} borrows the temporary engine';
        } else if ($type == 'SEAT') {
            $plane->buySeat();
            $msg = '${player_name} upgrades to ${seat} seats';
            $args = [
                'seat' => $plane->getSeats(),
            ];
        } else if ($type == 'SPEED') {
            $plane->buySpeed();
            $msg = '${player_name} upgrades to ${speed} engines';
            $args = [
                'speed' => $plane->getSpeed(),
            ];
        } else {
            throw new BgaVisibleSystemException("Unknown purchase type: $type");
        }

        $args['player_id'] = $playerId;
        $args['player_name'] = $plane->getName();
        $this->notifyAllPlayers('buy', $msg, $args);

        // Refresh state arguments
        $argFunction = "arg" . ucfirst($state['name']);
        $args = $this->$argFunction();
        $this->notifyAllPlayers('stateArgs', '', $args);

        // Give extra time
        $this->giveExtraTime($playerId);

        // Automatically proceed when build is complete
        if ($state['name'] == 'build' && empty($args[$playerId]['buys'])) {
            $this->gamestate->setPlayerNonMultiactive($playerId, 'shuffle');
        }
    }

    function stShuffle()
    {
        $this->gamestate->nextState('preflight');
    }

    /*
     * PHASE 2: PRE-FLIGHT
     * Add passengers
     * Players purchase upgrades
     */

    function argPreflight(): array
    {
        $args = [];
        $planes = NPlane::loadAll();
        foreach ($planes as $playerId => $plane) {
            $buys = $plane->getBuys();
            $args[$playerId]['buys'] = $buys;
        }
        return $args;
    }

    function begin(): void
    {
        $playerId = self::getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($playerId, 'flight');
    }

    /*
     * PHASE 3: FLIGHT
     * Players transport passengers
     */

    function argFlight(): array
    {
        $plane = NPlane::loadById(self::getCurrentPlayerId());
        return [
            'move' => [],
            'pickPassenger' => [],
            'dropPassenger' => [],
        ];
    }

    function move(string $nodeId): void
    {
    }

    function pickPassenger(int $paxId): void
    {
    }

    function dropPassenger(int $paxId): void
    {
    }

    function end(): void
    {
        $playerId = self::getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($playerId, 'maintenance');
    }

    /*
     * PHASE 4: MAINTENANCE
     * Add anger and complaints
     */

    function stMaintenance(): void
    {
        $this->gamestate->nextState('preflight');
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn($state, $active_player)
    {
        $statename = $state['name'];

        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState("zombiePass");
                    break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive($active_player, '');

            return;
        }

        throw new feException("Zombie mode not supported at this game state: " . $statename);
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */

    function upgradeTableDb($from_version)
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345

        // Example:
        //        if( $from_version <= 1404301345 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
        //            self::applyDbUpgradeToAllDB( $sql );
        //        }
        //        if( $from_version <= 1405061421 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
        //            self::applyDbUpgradeToAllDB( $sql );
        //        }
        //        // Please add your future database scheme changes here
        //
        //


    }
}
