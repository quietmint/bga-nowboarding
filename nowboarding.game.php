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
        // Create players and planes
        foreach ($players as $player_id => $player) {
            $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ($player_id, '000000', '" . $player['player_canal'] . "', '" . addslashes($player['player_name']) . "', '" . addslashes($player['player_avatar']) . "')";
            self::DbQuery($sql);

            $sql = "INSERT INTO plane (player_id) VALUES ($player_id)";
            self::DbQuery($sql);
        }
        self::reloadPlayersBasicInfos();

        // Create weather tokens
        $playerCount = count($players);
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
    }

    function checkVersion(int $clientVersion): void
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
            'map' => $this->getMap(),
            'planes' => $this->getPlanesByIds(),
            'players' => $players,
            'version' => intval($this->gamestate->table_globals[N_OPTION_VERSION]),
        ];

        return $data;
    }

    function getGameProgression(): int
    {
        return 0;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// States
    ////////////

    /*
     * SETUP
     * Each player builds their plane (private parallel states follow) 
     */
    function stBuild()
    {
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->initializePrivateStateForAllActivePlayers();
    }

    /*
     * SETUP #1
     * Each player chooses a starting airport and alliance
     */
    function stBuildAlliance(int $playerId): void
    {
        self::DbQuery("UPDATE plane SET `alliance` = NULL, `alliances` = NULL, `location` = NULL WHERE `player_id` = $playerId");
        self::DbQuery("UPDATE player SET `player_color` = '000000' WHERE `player_id` = $playerId");
        self::reloadPlayersBasicInfos();
    }

    function argBuildAlliance(int $playerId): array
    {
        $buys = [];
        $claimed = self::getObjectListFromDB("SELECT `alliance` FROM plane WHERE `alliance` IS NOT NULL AND `player_id` != $playerId", true);
        $possible = array_diff(array_keys(N_REF_ALLIANCE_COLOR), $claimed);
        foreach ($possible as $alliance) {
            $buys[] = [
                'type' => 'ALLIANCE',
                'alliance' => $alliance,
                'cost' => 0,
            ];
        }
        return ['buys' => $buys];
    }

    /*
     * SETUP #2
     * Each player chooses a second alliance (2-player only)
     */
    function stBuildAlliance2(int $playerId): void
    {
        self::DbQuery("UPDATE plane SET `cash` = 7, `alliances` = `alliance` WHERE `player_id` = $playerId");
    }

    function argBuildAlliance2(int $playerId): array
    {
        $buys = [];
        $claimed = self::getObjectListFromDB("SELECT `alliance` FROM plane WHERE `player_id` = $playerId", true);
        $possible = array_diff(array_keys(N_REF_ALLIANCE_COLOR), $claimed);
        foreach ($possible as $alliance) {
            $buys[] = [
                'type' => 'ALLIANCE',
                'alliance' => $alliance,
                'cost' => 0,
            ];
        }
        return ['buys' => $buys];
    }

    /*
     * SETUP #3
     * Each player chooses a seat or speed upgrade
     */
    function stBuildUpgrade(int $playerId): void
    {
        self::DbQuery("UPDATE plane SET `cash` = 5, `seats` = 1, `speed` = 3 WHERE `player_id` = $playerId");
    }

    function argBuildUpgrade(int $playerId): array
    {
        return [
            'buys' => [
                [
                    'type' => 'SEAT',
                    'seat' => 2,
                    'cost' => 0,
                ],
                [
                    'type' => 'SPEED',
                    'speed' => 4,
                    'cost' => 0,
                ],
            ],
        ];
    }

    /*
     * SETUP #4
     * Prepares passengers and weather
     */
    function stShuffle()
    {
        $this->gamestate->nextState('preflight');
    }

    /*
     * PHASE 2: PRE-FLIGHT
     * Add passengers
     * Players purchase upgrades
     */
    function stMultiactive()
    {
        $this->gamestate->setAllPlayersMultiactive();
    }

    function argPreflight(): array
    {
        return [];
    }

    /*
     * PHASE 3: FLIGHT
     * Players transport passengers
     */

    function argFlight(): array
    {
        return [
            'dropPassenger' => [],
            'move' => [],
            'pickPassenger' => [],
        ];
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
    //////////// Actions
    ////////////

    function buildReset(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $this->notifyAllPlayers('buildReset', '${player_name} restarts their turn', [
            'player_id' => $playerId,
            'player_name' => $this->getCurrentPlayerName(),
        ]);
        $this->gamestate->setPlayersMultiactive([$playerId], '');
        $this->gamestate->initializePrivateState($playerId);
    }

    function buy(string $type, ?string $alliance): void
    {
        $playerId = self::getCurrentPlayerId();
        $plane = $this->getPlane($playerId);
        if ($type == 'ALLIANCE') {
            if ($plane->alliance == null) {
                $this->buyAlliancePrimary($plane, $alliance);
            } else {
                $this->buyAlliance($plane, $alliance);
            }
        } else if ($type == 'SEAT') {
            $this->buySeat($plane);
        } else if ($type == 'SPEED') {
            $this->buySpeed($plane);
        }

        // $args = [];
        // if ($type == 'COLOR') {
        //     $plane->buyColor($color);
        //     $msg = '${player_name} joins the ${colorFancy} alliance';
        //     $args = [
        //         'i18n' => ['colorFancy'],
        //         'colorFancy' => $color,
        //     ];
        //     if ($state['name'] == 'build' && $plane->getColorsCount() == 1) {
        //         // The player's color was just assigned
        //         $args['plane'] = $plane;
        //         self::reloadPlayersBasicInfos();
        //     }
        // } else if ($type == 'EXTRA_SEAT') {
        //     $plane->buyExtraSeat();
        //     $msg = '${player_name} borrows the temporary seat';
        // } else if ($type == 'EXTRA_SPEED') {
        //     $plane->buyExtraSpeed();
        //     $msg = '${player_name} borrows the temporary engine';
        // } else if ($type == 'SEAT') {
        //     $plane->buySeat();
        //     $msg = '${player_name} upgrades to ${seat} seats';
        //     $args = [
        //         'seat' => $plane->getSeats(),
        //     ];
        // } else if ($type == 'SPEED') {
        //     $plane->buySpeed();
        //     $msg = '${player_name} upgrades to ${speed} engines';
        //     $args = [
        //         'speed' => $plane->getSpeed(),
        //     ];
        // } else {
        //     throw new BgaVisibleSystemException("Unknown purchase type: $type");
        // }

        // $args['player_id'] = $playerId;
        // $args['player_name'] = $plane->getName();
        // $this->notifyAllPlayers('buy', $msg, $args);

        // // Refresh state arguments
        // $argFunction = "arg" . ucfirst($state['name']);
        // $args = $this->$argFunction();
        // $this->notifyAllPlayers('stateArgs', '', $args);

        // // Give extra time
        // $this->giveExtraTime($playerId);

        // // Automatically proceed when build is complete
        // if ($state['name'] == 'build' && empty($args[$playerId]['buys'])) {
        //     $this->gamestate->setPlayerNonMultiactive($playerId, 'shuffle');
        // }
    }

    private function buyAlliancePrimary(NPlane $plane, string $alliance): void
    {
        $owner = self::getUniqueValueFromDB("SELECT 1 FROM plane WHERE `alliance` = '$alliance' LIMIT 1");
        if ($owner != null) {
            throw new BgaUserException("Another player already selected $alliance as their starting alliance. Choose again.");
        }

        $color = N_REF_ALLIANCE_COLOR[$alliance];
        self::DbQuery("UPDATE plane SET `alliance` = '$alliance', `alliances` = '$alliance', `location` = '$alliance' WHERE `player_id` = {$plane->id}");
        self::DbQuery("UPDATE player SET `player_color` = '$color' WHERE `player_id` = {$plane->id}");
        self::reloadPlayersBasicInfos();

        $this->notifyAllPlayers('alliance', '${player_name} joins the ${allianceFancy} alliance', [
            'alliance' => $alliance,
            'allianceFancy' => $alliance,
            'color' => $color,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $playerCount = $this->getPlayersNumber();
        $this->gamestate->nextPrivateState($plane->id,  $playerCount == 2 ? 'buildAlliance2' : 'buildUpgrade');
    }

    private function buyAlliance(NPlane $plane, string $alliance): void
    {
        if (in_array($alliance, $plane->alliances)) {
            throw new BgaUserException("You're already a member of the $alliance alliance");
        }
        $cost = 7;
        if ($plane->cash < $cost) {
            throw new BgaUserException("\${$plane->cash} is not enough to join an alliance (cost: \${$cost})");
        }

        $plane->cash -= $cost;
        $plane->alliances[] = $alliance;
        $alliances = join(',', $plane->alliances);
        self::DbQuery("UPDATE plane SET `cash` = {$plane->cash}, `alliances` = '$alliances' WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('alliance', '${player_name} joins alliance ${allianceFancy}', [
            'alliance' => $alliance,
            'allianceFancy' => $alliance,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $state = $this->gamestate->state();
        $this->gamestate->nextPrivateState($plane->id, $state['name'] == 'purchase' ? 'purchase' : 'buildUpgrade');
    }

    private function buySeat(NPlane $plane): void
    {
        if ($plane->seats >= 5) {
            throw new BgaUserException("Maximum seats is 5");
        }
        $cost = N_REF_SEAT_COST[$plane->seats + 1];
        if ($plane->cash < $cost) {
            throw new BgaUserException("\${$plane->cash} is not enough to purchase seat {$plane->seats} (cost: \${$cost})");
        }

        $plane->cash -= $cost;
        $plane->seats++;
        self::DbQuery("UPDATE plane SET `cash` = {$plane->cash}, `seats` = {$plane->seats} WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('seats', '${player_name} upgrades seats to ${seatFancy}', [
            'seat' => $plane->seats,
            'seatFancy' => $plane->seats,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $state = $this->gamestate->state();
        if ($state['name'] == 'purchase') {
            $this->gamestate->nextPrivateState($plane->id, 'purchase');
        } else {
            // $this->gamestate->unsetPrivateState($plane->id);
            $this->gamestate->setPlayerNonMultiactive($plane->id, 'buildComplete');
        }
    }

    private function buySpeed(NPlane $plane): void
    {
        if ($plane->speed >= 9) {
            throw new BgaUserException("Maximum speed is 9");
        }
        $cost = N_REF_SPEED_COST[$plane->speed + 1];
        if ($plane->cash < $cost) {
            throw new BgaUserException("\${$plane->cash} is not enough to purchase speed {$plane->speed} (cost: \${$cost})");
        }

        $plane->cash -= $cost;
        $plane->speed++;
        self::DbQuery("UPDATE plane SET `cash` = {$plane->cash}, `speed` = {$plane->speed} WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('seats', '${player_name} upgrades speed to ${speedFancy}', [
            'speed' => $plane->speed,
            'speedFancy' => $plane->speed,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $state = $this->gamestate->state();
        if ($state['name'] == 'purchase') {
            $this->gamestate->nextPrivateState($plane->id, 'purchase');
        } else {
            // $this->gamestate->unsetPrivateState($plane->id);
            $this->gamestate->setPlayerNonMultiactive($plane->id, 'buildComplete');
        }
    }

    function flightBegin(): void
    {
        $playerId = self::getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($playerId, 'flight');
    }

    function flightEnd(): void
    {
        $playerId = self::getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($playerId, 'maintenance');
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

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utilities
    ////////////

    function getMap(): NMap
    {
        $playerCount = $this->getPlayersNumber();
        $dbrows = self::getObjectListFromDB("SELECT * FROM weather");
        return new NMap($playerCount, $dbrows);
    }

    function getPlane(int $playerId): NPlane
    {
        return $this->getPlanesByIds([$playerId])[$playerId];
    }

    function getPlanesByIds($ids = []): array
    {
        $sql = "SELECT
        p.*,
        p.seats - (
            SELECT
                COUNT(1)
            FROM
                `pax` x
            WHERE
                x.player_id = p.player_id
                AND x.status = 'SEAT'
        ) AS seats_remain,
        b.player_name
    FROM
        `plane` p
        JOIN `player` b ON (b.player_id = p.player_id)";
        if (!empty($ids)) {
            $sql .= " WHERE p.player_id IN (" . join(',', $ids) . ")";
        }
        return array_map(function ($dbrow) {
            return new NPlane($dbrow);
        }, self::getCollectionFromDb($sql));
    }

    function getPax(int $paxId): NPax
    {
        return $this->getPaxByIds([$paxId])[$paxId];
    }

    function getPaxByIds($ids = []): array
    {
        $sql = "SELECT * FROM pax";
        if (!empty($ids)) {
            $sql .= " WHERE `pax_id` IN (" . join(',', $ids) . ")";
        }
        return array_map(function ($dbrow) {
            return new NPax($dbrow);
        }, self::getCollectionFromDb($sql));
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
