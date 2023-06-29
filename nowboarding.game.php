<?php

require_once(APP_GAMEMODULE_PATH . 'module/table/table.game.php');
require_once('modules/constants.inc.php');
require_once('modules/NMap.class.php');
require_once('modules/NMove.class.php');
require_once('modules/NNode.class.php');
require_once('modules/NNodeHop.class.php');
require_once('modules/NNodePort.class.php');
require_once('modules/NPax.class.php');
require_once('modules/NPlane.class.php');

class NowBoarding extends Table
{
    function __construct()
    {
        parent::__construct();
        $this->initGameStateLabels([]);
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
            $this->DbQuery($sql);

            $sql = "INSERT INTO plane (player_id) VALUES ($player_id)";
            $this->DbQuery($sql);
        }
        $this->reloadPlayersBasicInfos();

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
            $this->DbQuery("INSERT INTO weather (token) VALUES ('FAST')");
            $this->DbQuery("INSERT INTO weather (token) VALUES ('SLOW')");
        }

        // Create passengers
        $pax = [
            ['ATL', 'DEN', 2],
            ['ATL', 'DFW', 2],
            ['ATL', 'LAX', 4],
            ['ATL', 'MIA', 1],
            ['ATL', 'ORD', 2],
            ['ATL', 'SFO', 4],
            ['DEN', 'ATL', 2],
            ['DEN', 'DFW', 2],
            ['DEN', 'LAX', 2],
            ['DEN', 'MIA', 3],
            ['DEN', 'ORD', 2],
            ['DEN', 'ORD', 2],
            ['DEN', 'SFO', 2],
            ['DFW', 'ATL', 2],
            ['DFW', 'DEN', 2],
            ['DFW', 'LAX', 2],
            ['DFW', 'MIA', 2],
            ['DFW', 'ORD', 3],
            ['DFW', 'SFO', 3],
            ['LAX', 'ATL', 4],
            ['LAX', 'DEN', 2],
            ['LAX', 'DFW', 2],
            ['LAX', 'MIA', 3],
            ['LAX', 'ORD', 3],
            ['LAX', 'SFO', 1],
            ['MIA', 'ATL', 1],
            ['MIA', 'DEN', 3],
            ['MIA', 'DFW', 2],
            ['MIA', 'LAX', 3],
            ['MIA', 'ORD', 3],
            ['MIA', 'SFO', 4],
            ['MIA', 'SFO', 4],
            ['ORD', 'ATL', 2],
            ['ORD', 'DEN', 2],
            ['ORD', 'DFW', 3],
            ['ORD', 'LAX', 3],
            ['ORD', 'MIA', 3],
            ['ORD', 'SFO', 3],
            ['SFO', 'ATL', 4],
            ['SFO', 'DFW', 3],
            ['SFO', 'LAX', 1],
            ['SFO', 'MIA', 4],
            ['SFO', 'ORD', 3],
        ];

        if ($playerCount >= 3) {
            // Include JFK with 3+ players
            $pax += [
                ['ATL', 'JFK', 2],
                ['DEN', 'JFK', 3],
                ['JFK', 'ATL', 2],
                ['JFK', 'DEN', 2],
                ['JFK', 'DFW', 3],
                ['JFK', 'LAX', 5],
                ['JFK', 'LAX', 5],
                ['JFK', 'MIA', 3],
                ['JFK', 'ORD', 2],
                ['JFK', 'SFO', 4],
                ['LAX', 'JFK', 5],
                ['LAX', 'JFK', 5],
                ['MIA', 'JFK', 3],
                ['ORD', 'JFK', 2],
                ['SFO', 'JFK', 4],
            ];
        }

        if ($playerCount >= 4) {
            // Include SEA with 4+ players
            $pax += [
                ['ATL', 'SEA', 4],
                ['DEN', 'SEA', 2],
                ['DFW', 'SEA', 3],
                ['JFK', 'SEA', 3],
                ['LAX', 'SEA', 3],
                ['MIA', 'SEA', 5],
                ['ORD', 'SEA', 2],
                ['SEA', 'ATL', 4],
                ['SEA', 'DEN', 2],
                ['SEA', 'DFW', 3],
                ['SEA', 'JFK', 3],
                ['SEA', 'LAX', 3],
                ['SEA', 'MIA', 5],
                ['SEA', 'ORD', 2],
                ['SEA', 'SFO', 2],
                ['SFO', 'SEA', 2],
                ['SFO', 'SEA', 2],
            ];
        }

        $paxCount = 10;
        shuffle($pax);
        array_splice($pax, $paxCount);
        foreach ($pax as $p) {
            [$destination, $origin, $cash] = $p;
            $sql = "INSERT INTO pax (`destination`, `origin`, `cash`) VALUES ('$destination', '$origin', $cash)";
            $this->DbQuery($sql);
        }
    }

    function checkVersion(int $clientVersion): void
    {
        $gameVersion = $this->gamestate->table_globals[N_OPTION_VERSION];
        if ($clientVersion != $gameVersion) {
            throw new BgaVisibleSystemException($this->_("A new version of this game is now available. Please reload the page (F5)."));
        }
    }

    protected function getAllDatas(): array
    {
        $players = $this->getCollectionFromDb("SELECT player_id id, player_score score FROM player");
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
     * Each player builds their plane 
     */
    function stInitPrivate()
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
        $this->DbQuery("UPDATE plane SET `alliance` = NULL, `alliances` = NULL, `location` = NULL WHERE `player_id` = $playerId");
        $this->DbQuery("UPDATE player SET `player_color` = '000000' WHERE `player_id` = $playerId");
        $this->reloadPlayersBasicInfos();
    }

    function argBuildAlliance(int $playerId): array
    {
        $buys = [];
        $claimed = $this->getObjectListFromDB("SELECT `alliance` FROM plane WHERE `alliance` IS NOT NULL AND `player_id` != $playerId", true);
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
        $this->DbQuery("UPDATE plane SET `debt` = -7, `alliances` = `alliance` WHERE `player_id` = $playerId");
    }

    function argBuildAlliance2(int $playerId): array
    {
        $buys = [];
        $claimed = $this->getObjectListFromDB("SELECT `alliance` FROM plane WHERE `player_id` = $playerId", true);
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
        $this->DbQuery("UPDATE plane SET `debt` = -5, `seat` = 1, `speed` = 3 WHERE `player_id` = $playerId");
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
     * Prepare passengers and weather
     */
    function stShuffle()
    {
        $this->gamestate->nextState('prepare');
    }

    /*
     * PREPARE
     * Reset planes
     * Add passengers
     * Players purchase upgrades
     */
    function stPrepare()
    {
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->initializePrivateStateForAllActivePlayers();
        $this->DbQuery("UPDATE plane SET `speed_remain` = `speed`");
        $planes = $this->getPlanesByIds();
        $this->notifyAllPlayers('prepare', clienttranslate('Prepare for the next round'), [
            'planes' => array_values($planes)
        ]);
    }

    function argPreparePrivate(int $playerId): array
    {
        $plane = $this->getPlane($playerId);
        $buys = [];

        // Alliances?
        $debt = $plane->debt;
        $cash = $plane->cash - $debt;
        $cost = 7;
        if ($cash >= $cost) {
            $claimed = $this->getObjectListFromDB("SELECT `alliance` FROM plane WHERE `player_id` = $playerId", true);
            $possible = array_diff(array_keys(N_REF_ALLIANCE_COLOR), $claimed);
            foreach ($possible as $alliance) {
                $buys[] = [
                    'type' => 'ALLIANCE',
                    'alliance' => $alliance,
                    'cost' => $cost,
                ];
            }
        }

        // Seat?
        if ($plane->seat < 5) {
            $seat = $plane->seat + 1;
            $cost = N_REF_SEAT_COST[$seat];
            if ($cash >= $cost) {
                $buys[] = [
                    'type' => 'SEAT',
                    'seat' => $seat,
                    'cost' => $cost,
                ];
            }
        }

        // Speed?
        if ($plane->speed < 9) {
            $speed = $plane->speed + 1;
            $cost = N_REF_SPEED_COST[$speed];
            if ($cash >= $cost) {
                $buys[] = [
                    'type' => 'SPEED',
                    'speed' => $speed,
                    'cost' => $cost,
                ];
            }
        }

        // Temp Seat?
        $cost = 2;
        if ($cash >= $cost && $plane->tempSeat == false && $this->getOwnerName("temp_seat = 1") == null) {
            $buys[] = [
                'type' => 'TEMP_SEAT',
                'cost' => $cost,
            ];
        }

        // Temp Speed?
        $cost = 1;
        if ($cash >= $cost && $plane->tempSpeed == false && $this->getOwnerName("temp_speed = 1") == null) {
            $buys[] = [
                'type' => 'TEMP_SPEED',
                'cost' => $cost,
            ];
        }

        $args = [
            'buys' => $buys,
            'cash' => $cash,
        ];
        if ($debt > 0) {
            $args['debt'] = $debt;
        }
        return $args;


        // $change = [];
        // $cash = [1, 2, 3, 4, 4, 5, 5];
        // $count = count($cash);
        // $goal = 8;
        // $bestOverpaid = null;
        // $bestPerm = null;
        // foreach ($this->generatePermutations($cash) as $perm) {
        //     $paid = [];
        //     $sum = 0;
        //     for ($i = $count - 1; $sum < $goal && $i >= 0; $i--) {
        //         $paid[] = $perm[$i];
        //         $sum += $perm[$i];
        //     }
        //     $change[] = $paid;
        //     $overpaid = $sum - $goal;
        //     if ($bestOverpaid == null || $overpaid < $bestOverpaid) {
        //         self::debug("found overpaid=$overpaid, paid=" . join(',', $paid) . " for goal=$goal // ");
        //         $bestOverpaid = $overpaid;
        //         $bestPerm = $paid;
        //     }
        //     if ($overpaid == 0) {
        //         break;
        //     }
        // }
    }

    /*
     * FLIGHT
     * Players transport passengers
     */

    function argFlyPrivate(int $playerId): array
    {
        $map = $this->getMap();
        $plane = $this->getPlane($playerId);
        return [
            'moves' => $map->getPossibleMoves($plane),
            'paxDrop' => [],
            'paxPickup' => [],
        ];
    }

    /*
     * MAINTENANCE
     * Add anger and complaints
     */

    function stMaintenance(): void
    {

        $this->gamestate->nextState('prepare');
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Actions
    ////////////

    function buildReset(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlane($playerId);
        $this->notifyAllPlayers('buildReset', clienttranslate('${player_name} restarts their turn'), [
            'plane' => $plane,
            'player_id' => $playerId,
            'player_name' => $this->getCurrentPlayerName(),
        ]);
        $this->gamestate->setPlayersMultiactive([$playerId], '');
        $this->gamestate->initializePrivateState($playerId);
    }

    function buy(string $type, ?string $alliance): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlane($playerId);
        if ($type == 'ALLIANCE') {
            if ($plane->alliance == null) {
                $this->buyAlliancePrimary($plane, $alliance);
            } else {
                $this->buyAlliance($plane, $alliance);
            }
        } else if ($type == 'SEAT') {
            $this->buySeat($plane);
        } else if ($type == 'TEMP_SEAT') {
            $this->buyTempSeat($plane);
        } else if ($type == 'SPEED') {
            $this->buySpeed($plane);
        } else if ($type == 'TEMP_SPEED') {
            $this->buyTempSpeed($plane);
        } else {
            throw new BgaVisibleSystemException("Invalid buy type: $type");
        }
    }

    private function buyAlliancePrimary(NPlane $plane, string $alliance): void
    {
        $owner = $this->getOwnerName("alliance = '$alliance'");
        if ($owner != null) {
            throw new BgaUserException(sprintf($this->_("%s already selected the %s alliance"), $owner, $alliance));
        }

        $color = N_REF_ALLIANCE_COLOR[$alliance];
        $this->DbQuery("UPDATE plane SET `alliance` = '$alliance', `alliances` = '$alliance', `location` = '$alliance' WHERE `player_id` = {$plane->id}");
        $this->DbQuery("UPDATE player SET `player_color` = '$color' WHERE `player_id` = {$plane->id}");
        $this->reloadPlayersBasicInfos();

        $this->notifyAllPlayers('buyAlliancePrimary', clienttranslate('${player_name} joins the ${allianceFancy} alliance'), [
            'allianceFancy' => $alliance,
            'color' => $color,
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $playerCount = $this->getPlayersNumber();
        $this->gamestate->nextPrivateState($plane->id,  $playerCount == 2 ? 'buildAlliance2' : 'buildUpgrade');
    }

    private function buyAlliance(NPlane $plane, string $alliance): void
    {
        if (in_array($alliance, $plane->alliances)) {
            throw new BgaUserException(sprintf($this->_("Already joined the %s alliance"), $alliance));
        }
        $cost = 7;
        $cash = $plane->cash - $plane->debt;
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->_("Insufficient funds (%s) to purchase %s alliance for %s"), "\${$cash}", $alliance, "\${$cost}"));
        }

        $plane->debt += $cost;
        $plane->alliances[] = $alliance;
        $alliances = join(',', $plane->alliances);
        $this->DbQuery("UPDATE plane SET `debt` = {$plane->debt}, `alliances` = '$alliances' WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('buyAlliance', clienttranslate('${player_name} joins the ${allianceFancy} alliance'), [
            'allianceFancy' => $alliance,
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $state = $this->gamestate->state();
        $this->gamestate->nextPrivateState($plane->id, $state['name'] == 'prepare' ? 'preparePrivate' : 'buildUpgrade');
    }

    private function buySeat(NPlane $plane): void
    {
        if ($plane->seat >= 5) {
            throw new BgaUserException($this->_("Maximum seats is 5"));
        }
        $cost = N_REF_SEAT_COST[$plane->seat + 1];
        $cash = $plane->cash - $plane->debt;
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->_("Insufficient funds (%s) to purchase seat %d for %s"), "\${$cash}", "\${$cost}"));
        }

        $plane->debt += $cost;
        $plane->seat++;
        $plane->seatRemain++;
        $this->DbQuery("UPDATE plane SET `debt` = {$plane->debt}, `seat` = {$plane->seat} WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('buy', clienttranslate('${player_name} upgrades seats to ${seatFancy}'), [
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'seatFancy' => $plane->seat,
        ]);

        $state = $this->gamestate->state();
        if ($state['name'] == 'prepare') {
            $this->gamestate->nextPrivateState($plane->id, 'preparePrivate');
        } else {
            $this->gamestate->setPlayerNonMultiactive($plane->id, 'buildComplete');
        }
    }

    private function buyTempSeat(NPlane $plane): void
    {
        $owner = $this->getOwnerName("temp_seat = 1");
        if ($owner != null) {
            throw new BgaUserException(sprintf($this->_("%s already owns the temporary seat"), $owner));
        }
        $cost = 2;
        $cash = $plane->cash - $plane->debt;
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->_("Insufficient funds (%s) to purchase the temporary seat for %s"), "\${$cash}", $plane->seat, "\${$cost}"));
        }

        $plane->debt += $cost;
        $plane->tempSeat = true;
        $this->DbQuery("UPDATE plane SET `debt` = {$plane->debt}, `temp_seat` = 1 WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('buy', clienttranslate('${player_name} purchases the temporary seat'), [
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'preparePrivate');
    }

    private function buySpeed(NPlane $plane): void
    {
        if ($plane->speed >= 9) {
            throw new BgaUserException($this->_("Maximum speed is 9"));
        }
        $cost = N_REF_SPEED_COST[$plane->speed + 1];
        $cash = $plane->cash - $plane->debt;
        if ($cost < $cost) {
            throw new BgaUserException(sprintf($this->_("Insufficient funds (%s) to purchase speed %d for %s"), "\${$cash}", $plane->speed, "\${$cost}"));
        }

        $plane->debt += $cost;
        $plane->speed++;
        $plane->speedRemain = $plane->speed;
        $this->DbQuery("UPDATE plane SET `debt` = {$plane->debt}, `speed` = {$plane->speed}, `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('buy', clienttranslate('${player_name} upgrades speed to ${speedFancy}'), [
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'speedFancy' => $plane->speed,
        ]);

        $state = $this->gamestate->state();
        if ($state['name'] == 'prepare') {
            $this->gamestate->nextPrivateState($plane->id, 'preparePrivate');
        } else {
            $this->gamestate->setPlayerNonMultiactive($plane->id, 'buildComplete');
        }
    }

    private function buyTempSpeed(NPlane $plane): void
    {
        $owner = $this->getOwnerName("temp_speed = 1");
        if ($owner != null) {
            throw new BgaUserException(sprintf($this->_("%s already owns the temporary speed"), $owner));
        }
        $cost = 1;
        $cash = $plane->cash - $plane->debt;
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->_("Insufficient funds (%s) to purchase the temporary speed for %s"), "\${$cash}", "\${$cost}"));
        }

        $plane->debt += $cost;
        $plane->tempSpeed = true;
        $this->DbQuery("UPDATE plane SET `debt` = {$plane->debt}, `temp_speed` = 1 WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('buy', clienttranslate('${player_name} purchases the temporary speed'), [
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'preparePrivate');
    }

    function flightBegin(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($playerId, 'fly');
    }

    function flightEnd(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($playerId, 'maintenance');
    }

    function move(string $location): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlane($playerId);
        $map = $this->getMap();
        $possible = $map->getPossibleMoves($plane);
        if (!array_key_exists($location, $possible)) {
            throw new BgaVisibleSystemException("Player {$plane->id} cannot move to $location");
        }

        $move = $possible[$location];
        $plane->origin = $move->getOrigin(); // $plane->location;
        $plane->location = $location;
        $plane->speedRemain -= $move->fuel;
        $this->DbQuery("UPDATE plane SET `location` = '{$plane->location}', `origin` = '{$plane->origin}', `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('move', clienttranslate('${player_name} flys to ${locationFancy}'), [
            'locationFancy' => $plane->location,
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        if ($plane->speedRemain > 0 && !empty($map->getPossibleMoves($plane))) {
            $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
        } else {
            $this->gamestate->setPlayerNonMultiactive($plane->id, 'maintenance');
        }
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
        $dbrows = $this->getObjectListFromDB("SELECT * FROM weather");
        return new NMap($playerCount, $dbrows);
    }

    function getPlane(int $playerId): NPlane
    {
        return $this->getPlanesByIds([$playerId])[$playerId];
    }

    function getPlanesByIds($ids = []): array
    {
        $sql = <<<SQL
SELECT
  p.*,
  p.seat - (
    SELECT
      COUNT(1)
    FROM
      `pax` x
    WHERE
      x.status = 'SEAT'
      AND x.player_id = p.player_id
  ) AS seat_remain,
  (
    SELECT
      SUM(cash)
    FROM
      `pax` x
    WHERE
      x.status = 'CASH'
      AND x.player_id = p.player_id
  ) AS cash,
  b.player_name
FROM
  `plane` p
  JOIN `player` b ON (b.player_id = p.player_id)
SQL;
        if (!empty($ids)) {
            $sql .= " WHERE p.player_id IN (" . join(',', $ids) . ")";
        }
        return array_map(function ($dbrow) {
            return new NPlane($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    function getOwnerName(string $sqlWhere): ?string
    {
        return $this->getUniqueValueFromDB(<<<SQL
SELECT
  b.`player_name`
FROM
  `plane` p
  JOIN `player` b ON (b.player_id = p.player_id)
WHERE $sqlWhere
LIMIT 1
SQL);
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
        }, $this->getCollectionFromDb($sql));
    }

    public function generatePermutations(array $array): Generator
    {
        // https://en.wikipedia.org/wiki/Permutation#Generation_in_lexicographic_order
        // Sort the array and this is the first permutation
        sort($array);
        $y = 1;
        yield $array;

        $count = count($array);
        do {
            // Find the largest index k where a[k] < a[k + 1]
            // End when no such index exists
            $found = false;
            for ($k = $count - 2; $k >= 0; $k--) {
                $kvalue = $array[$k];
                $knext = $array[$k + 1];
                if ($kvalue < $knext) {
                    // Find the largest index l greater than k where a[k] < a[l]
                    for ($l = $count - 1; $l > $k; $l--) {
                        $lvalue = $array[$l];
                        if ($kvalue < $lvalue) {
                            // Swap a[k] and a[l]
                            [$array[$k], $array[$l]] = [$array[$l], $array[$k]];

                            // Reverse the array from a[k + 1] through the end
                            $reverse = array_reverse(array_slice($array, $k + 1));
                            array_splice($array, $k + 1, $count, $reverse);
                            $y++;
                            yield $array;

                            // Restart with the new array to find the next permutation
                            $found = true;
                            break 2;
                        }
                    }
                }
            }
        } while ($found);
        self::debug("generatePermutations completed: $y iterations for count=$count // ");
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
        //            $this->applyDbUpgradeToAllDB( $sql );
        //        }
        //        if( $from_version <= 1405061421 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
        //            $this->applyDbUpgradeToAllDB( $sql );
        //        }
        //        // Please add your future database scheme changes here
        //
        //


    }
}
