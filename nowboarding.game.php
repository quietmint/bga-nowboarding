<?php

require_once APP_GAMEMODULE_PATH . 'module/table/table.game.php';
require_once 'modules/constants.inc.php';
require_once 'modules/NMap.class.php';
require_once 'modules/NMove.class.php';
require_once 'modules/NNode.class.php';
require_once 'modules/NNodeHop.class.php';
require_once 'modules/NNodePort.class.php';
require_once 'modules/NPax.class.php';
require_once 'modules/NPlane.class.php';

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
        $playerCount = count($players);
        foreach ($players as $player_id => $player) {
            $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ($player_id, '000000', '" . $player['player_canal'] . "', '" . addslashes($player['player_name']) . "', '" . addslashes($player['player_avatar']) . "')";
            $this->DbQuery($sql);

            $sql = "INSERT INTO plane (player_id) VALUES ($player_id)";
            $this->DbQuery($sql);
        }
        $this->reloadPlayersBasicInfos();

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
            ['ORD', 'ATL', 2],
            ['ORD', 'DEN', 2],
            ['ORD', 'DFW', 3],
            ['ORD', 'LAX', 3],
            ['ORD', 'MIA', 3],
            ['ORD', 'SFO', 3],
            ['SFO', 'ATL', 4],
            ['SFO', 'DEN', 2],
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
                ['DFW', 'JFK', 3],
                ['JFK', 'ATL', 2],
                ['JFK', 'DEN', 2],
                ['JFK', 'DFW', 3],
                ['JFK', 'LAX', 5],
                ['JFK', 'MIA', 3],
                ['JFK', 'ORD', 2],
                ['JFK', 'SFO', 4],
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
            ];
        }

        shuffle($pax);
        $paxCounts = N_REF_PAX_COUNTS[$playerCount];
        foreach ($paxCounts as $status => $count) {
            $dayPax = array_splice($pax, $count * -1);
            foreach ($dayPax as $x) {
                [$destination, $origin, $cash] = $x;
                $sql = "INSERT INTO pax (`status`, `destination`, `origin`, `cash`) VALUES ('$status', '$destination', '$origin', $cash)";
                $this->DbQuery($sql);
            }
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
            'pax' => $this->filterPax($this->getPaxByStatus(['SECRET', 'PORT', 'SEAT', 'TEMP_SEAT', 'CASH'])),
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
     * Add weather
     * Add starting passengers
     */
    function stBuildComplete()
    {
        $this->newWeather();
        $planes = $this->getPlanesByIds();
        $startingPax = [];
        foreach ($planes as $plane) {
            $x = $this->getPaxByStatus('MORNING', 1, $plane->alliance)[0];
            $startingPax[] = $x;
        }
        $this->newPax($startingPax);
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
        $this->notifyAllPlayers('planes', clienttranslate('Prepare for the next round'), [
            'planes' => array_values($planes)
        ]);
    }

    function argPreparePrivate(int $playerId): array
    {
        $plane = $this->getPlaneById($playerId);
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
     * Reveal passengers and start the clock
     * Players transport passengers
     */

    function stReveal(): void
    {
        $pax = $this->getPaxByStatus('SECRET');
        foreach ($pax as $x) {
            $x->status = 'PORT';
            $this->DbQuery("UPDATE `pax` SET `status` = 'PORT' WHERE `pax_id` = {$x->id}");
        }
        $this->notifyAllPlayers('pax', 'stReveal', [
            'pax' => $pax,
        ]);
        $this->gamestate->nextState('fly');
    }

    function argFlyPrivate(int $playerId): array
    {
        $map = $this->getMap();
        $plane = $this->getPlaneById($playerId);
        return [
            'moves' => $map->getPossibleMoves($plane),
            'paxDrop' => [],
            'paxPickup' => [],
        ];
    }

    /*
     * MAINTENANCE
     * Add anger and complaints
     * Add new passengers
     */

    function stMaintenance(): void
    {
        $pax = $this->getPaxByStatus('PORT');
        foreach ($pax as $x) {
            $x->anger++;
            $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger} WHERE `pax_id` = {$x->id}");
        }
        $this->notifyAllPlayers('pax', clienttranslate('${count} passengers waiting in airports get angry'), [
            'count' => count($pax),
            'pax' => $pax,
        ]);

        $playerCount = $this->getPlayersNumber();
        $paxCount = $playerCount;
        $day = 'MORNING';
        if ($day == 'MORNING') {
            $paxCount--;
        } else if (false == 'EVENING') {
            $paxCount++;
        }
        $pax = $this->getPaxByStatus('QUEUE', $paxCount);
        $this->newPax($pax);
        $this->gamestate->nextState('prepare');
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Actions
    ////////////

    function buildReset(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
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
        $plane = $this->getPlaneById($playerId);
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

        $this->notifyAllPlayers('buildPrimary', clienttranslate('${player_name} joins the ${allianceFancy} alliance'), [
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

        $this->notifyAllPlayers('planes', clienttranslate('${player_name} joins the ${allianceFancy} alliance'), [
            'allianceFancy' => $alliance,
            'planes' => [$plane],
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

        $this->notifyAllPlayers('planes', clienttranslate('${player_name} upgrades seats to ${seatFancy}'), [
            'planes' => [$plane],
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

        $this->notifyAllPlayers('planes', clienttranslate('${player_name} purchases the temporary seat'), [
            'planes' => [$plane],
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
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->_("Insufficient funds (%s) to purchase speed %d for %s"), "\${$cash}", $plane->speed, "\${$cost}"));
        }

        $plane->debt += $cost;
        $plane->speed++;
        $plane->speedRemain = $plane->speed;
        $this->DbQuery("UPDATE plane SET `debt` = {$plane->debt}, `speed` = {$plane->speed}, `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('planes', clienttranslate('${player_name} upgrades speed to ${speedFancy}'), [
            'planes' => [$plane],
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

        $this->notifyAllPlayers('planes', clienttranslate('${player_name} purchases the temporary speed'), [
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'preparePrivate');
    }

    function newWeather(): void
    {
        $map = $this->getMap();

        // Delete old weather
        $map->weather = [];
        $this->DbQuery("DELETE FROM `weather`");

        // Determine how many weather tokens to add
        $playerCount = $this->getPlayersNumber();
        $tokens = ['FAST', 'SLOW', 'FAST', 'SLOW', 'FAST', 'SLOW'];
        if ($playerCount == 2) {
            array_splice($tokens, 2);
        } else if ($playerCount == 3) {
            array_splice($tokens, 4);
        }

        // Select a different random route for each token
        $locationFancy = [];
        $routeIds = array_rand($map->routes, count($tokens));
        foreach ($routeIds as $routeId) {
            // Select a random node on the route
            $route = $map->routes[$routeId];
            $node = $route[array_rand($route)];
            $token = array_pop($tokens);
            $this->DbQuery("INSERT INTO weather (`location`, `token`) VALUES ('{$node->id}', '$token')");
            $map->weather[$node->id] = $token;
            $locationFancy[$token][] = substr_replace($routeId, '-', 3, 0);
        }

        $this->notifyAllPlayers('message', clienttranslate('Storms slow travel along ${locationFancy}'), [
            'locationFancy' => join(', ', $locationFancy['SLOW']),
        ]);
        $this->notifyAllPlayers('message', clienttranslate('Tailwinds speed travel along ${locationFancy}'), [
            'locationFancy' => join(', ', $locationFancy['FAST']),
        ]);
        $this->notifyAllPlayers('weather', '', [
            'weather' => $map->weather,
        ]);
    }

    function flightBegin(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($playerId, 'reveal');
    }

    function flightEnd(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($playerId, 'maintenance');
    }

    function move(string $location): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
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

    function enplane(int $paxId): void
    {
    }

    function deplane(int $paxId): void
    {
    }



    //////////////////////////////////////////////////////////////////////////////
    //////////// Helpers
    ////////////

    function getMap(): NMap
    {
        $playerCount = $this->getPlayersNumber();
        $weather = $this->getWeather();
        return new NMap($playerCount, $weather);
    }

    function getWeather(): array
    {
        return $this->getCollectionFromDb("SELECT `location`, `token` FROM `weather`", true);
    }

    function getPlaneById(int $playerId): NPlane
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

    function getPaxById(int $paxId): NPax
    {
        return $this->getPaxByIds([$paxId])[$paxId];
    }

    function getPaxByIds(array $ids = []): array
    {
        $sql = "SELECT * FROM `pax`";
        if (!empty($ids)) {
            $sql .= " WHERE `pax_id` IN (" . join(',', $ids) . ")";
        }
        return array_map(function ($dbrow) {
            return new NPax($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    function getPaxByStatus($status, ?int $limit = null, ?string $origin = null): array
    {
        if (is_array($status)) {
            $status = join("', '", $status);
        }
        $sql = "SELECT * FROM `pax` WHERE `status` IN ('$status')";
        if ($origin != null) {
            $sql .= " AND `origin` = '$origin'";
        }
        $sql .= " ORDER BY `pax_id`";
        if ($limit != null) {
            $sql .= " LIMIT $limit";
        }
        return array_map(function ($dbrow) {
            return new NPax($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    function newPax(array $pax): void
    {
        foreach ($pax as $x) {
            $x->status = 'SECRET';
            $x->location = $x->origin;
            $this->DbQuery("UPDATE `pax` SET `location` = '{$x->location}', `status` = 'SECRET' WHERE `pax_id` = {$x->id}");
        }

        $this->notifyAllPlayers('pax', clienttranslate('${count} passengers arrive at airports'), [
            'count' => count($pax),
            'pax' => $this->filterPax($pax),
        ]);
    }

    function filterPax(array $pax): array
    {
        foreach ($pax as $x) {
            if ($x->status == 'SECRET') {
                $x->destination = null;
            }
        }
        return $pax;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utilities
    ////////////

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

    function upgradeTableDb($from_version)
    {
    }
}
