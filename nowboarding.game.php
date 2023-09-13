<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Now Boarding implementation : © quietmint
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

require_once APP_GAMEMODULE_PATH . 'module/table/table.game.php';
require_once 'modules/constants.inc.php';
require_once 'modules/NGameOverException.class.php';
require_once 'modules/NMap.class.php';
require_once 'modules/NMove.class.php';
require_once 'modules/NNode.class.php';
require_once 'modules/NPax.class.php';
require_once 'modules/NPlane.class.php';

class NowBoarding extends Table
{
    public function __construct()
    {
        parent::__construct();
        $this->bSelectGlobalsForUpdate = true;
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
            $this->DbQuery("INSERT INTO `plane` (`player_id`) VALUES ($player_id)");
        }
        $this->reloadPlayersBasicInfos();
        $optionVip = $this->getGlobal(N_OPTION_VIP);

        // Erase beginner flags (affects giveExtraTime)
        $this->DbQuery("UPDATE `player` SET `player_beginner` = NULL");
        $this->reloadPlayersBasicInfos();

        // Table statistics
        $this->initStat('table', 'complaintPort', 0);
        $this->initStat('table', 'complaintFinale', 0);
        if ($optionVip) {
            $this->initStat('table', 'complaintVip', 0);
            $this->initStat('table', 'vipMORNING', 0);
            $this->initStat('table', 'vipNOON', 0);
            $this->initStat('table', 'vipNIGHT', 0);
        }
        $this->initStat('table', 'moves', 0);
        $this->initStat('table', 'movesFAST', 0);
        $this->initStat('table', 'movesSLOW', 0);
        $this->initStat('table', 'pax', 0);
        $this->initStat('table', 'journeyAvg', 0);
        $this->initStat('table', 'journeyMax', 0);
        $this->initStat('table', 'efficiencyAvg', 0);
        $this->initStat('table', 'efficiencyMin', 0);
        $this->initStat('table', 'alliances', 0);
        $this->initStat('table', 'seat', 0);
        $this->initStat('table', 'speed', 0);
        $this->initStat('table', 'tempSeat', 0);
        $this->initStat('table', 'tempSeatUnused', 0);
        $this->initStat('table', 'tempSpeed', 0);
        $this->initStat('table', 'tempSpeedUnused', 0);

        // Player statistics
        $this->initStat('player', 'moves', 0);
        $this->initStat('player', 'movesFAST', 0);
        $this->initStat('player', 'movesSLOW', 0);
        $this->initStat('player', 'ATL', 0);
        $this->initStat('player', 'DEN', 0);
        $this->initStat('player', 'DFW', 0);
        $this->initStat('player', 'JFK', 0);
        $this->initStat('player', 'LAX', 0);
        $this->initStat('player', 'MIA', 0);
        $this->initStat('player', 'ORD', 0);
        if ($playerCount >= 4) {
            $this->initStat('player', 'SEA', 0);
        }
        $this->initStat('player', 'SFO', 0);
        $this->initStat('player', 'pax', 0);
        $this->initStat('player', 'cash', 0);
        $this->initStat('player', 'overpay', 0);
        $this->initStat('player', 'alliances', 0);
        $this->initStat('player', 'seat', 1);
        $this->initStat('player', 'speed', 3);
        $this->initStat('player', 'tempSeat', 0);
        $this->initStat('player', 'tempSeatUnused', 0);
        $this->initStat('player', 'tempSpeed', 0);
        $this->initStat('player', 'tempSpeedUnused', 0);

        // Setup weather
        $this->setVar('hour', 'PREFLIGHT');
        $this->setupWeather($playerCount);

        // Setup VIPs
        if ($optionVip) {
            $this->setupVips($optionVip, $playerCount);
        }

        $this->createUndo();
    }

    private function setupWeather(int $playerCount): void
    {
        // Determine how many weather tokens are needed
        $hours = ['MORNING', 'NOON', 'NIGHT'];
        $tokens = ['FAST', 'SLOW', 'FAST', 'SLOW', 'FAST', 'SLOW'];
        if ($playerCount == 2) {
            array_splice($tokens, 2);
        } else if ($playerCount == 3) {
            array_splice($tokens, 4);
        }

        // Select 6, 12, or 18 random routes
        $map = $this->getMap();
        $routeIds = array_rand($map->routes, count($hours) * count($tokens));
        foreach ($hours as $hour) {
            foreach ($tokens as $token) {
                $routeId = array_pop($routeIds);
                $route = $map->routes[$routeId];
                // Select a random node on this route
                $node = $route[array_rand($route)];
                $this->DbQuery("INSERT INTO `weather` (`hour`, `location`, `token`) VALUES ('$hour', '{$node->id}', '$token')");
            }
        }

        // Final round keeps the same weather
        $this->DbQuery("INSERT INTO `weather` (`hour`, `location`, `token`) SELECT 'FINALE', `location`, `token` FROM `weather` WHERE `hour` = 'NIGHT'");
    }

    public function setupVips(int $optionVip, int $playerCount): void
    {
        // Determine how many VIPs we need
        $optionCount = $this->getGlobal(N_OPTION_VIP_COUNT);
        $vipMax = N_REF_HOUR_ROUND[$playerCount]['MORNING'] +
            N_REF_HOUR_ROUND[$playerCount]['NOON'] +
            N_REF_HOUR_ROUND[$playerCount]['NIGHT'];
        $vipCount = $playerCount + 2;
        if ($optionCount == N_VIP_INCREASE) {
            $vipCount = $playerCount + 4;
        } else if ($optionCount == N_VIP_DOUBLE) {
            $vipCount = min($vipCount * 2, $vipMax);
        }

        // Populate possible VIPs, respecting hours and max counts
        // Double the number only if needed
        $possibleRepeat = 1;
        if ($optionVip == N_VIP_FOWERS && $vipCount > 9 || $optionVip == N_VIP_BGA && $vipCount > 10) {
            $possibleRepeat = 2;
        }
        $possibleByHour = [
            'MORNING' => [],
            'NOON' => [],
            'NIGHT' => [],
        ];
        for ($i = 0; $i < $possibleRepeat; $i++) {
            foreach (N_REF_VIP as $key => $vip) {
                if ($optionVip == N_VIP_ALL || $optionVip == $vip['set']) {
                    $vipHours = $vip['hours'];
                    shuffle($vipHours);
                    if (count($vipHours) > $vip['count']) {
                        array_splice($vipHours, $vip['count']);
                    }
                    foreach ($vipHours as $hour) {
                        $possibleByHour[$hour][] = $key;
                    }
                }
            }
        }
        foreach ($possibleByHour as $hour => &$keys) {
            shuffle($keys);
            $rounds = N_REF_HOUR_ROUND[$playerCount][$hour];
            if (count($keys) > $rounds) {
                array_splice($keys, $rounds);
            }
        }
        unset($keys);

        // Select the desired number of VIPs
        $vipsByHour = [
            'MORNING' => [],
            'NOON' => [],
            'NIGHT' => [],
        ];
        for ($i = 0; $i < $vipCount; $i++) {
            $hour = array_rand($possibleByHour);
            $key = array_pop($possibleByHour[$hour]);
            if (empty($possibleByHour[$hour])) {
                unset($possibleByHour[$hour]);
            }
            $vipsByHour[$hour][] = $key;
        }

        // Save the results
        foreach ($vipsByHour as $hour => $keys) {
            $this->setVar("vip$hour", $keys);
            $this->setStat(count($keys), "vip$hour");
        }
    }

    public function checkVersion(int $clientVersion): void
    {
        if ($clientVersion != $this->getGlobal(N_BGA_VERSION)) {
            throw new BgaVisibleSystemException(self::_(N_REF_MSG_EX['version']));
        }
    }

    protected function getAllDatas(): array
    {
        $players = $this->getCollectionFromDb("SELECT player_id id, player_score score FROM player");
        return [
            'complaint' => $this->countComplaint(),
            'hour' => $this->getHourInfo(),
            'map' => $this->getMap(),
            'noTimeLimit' => in_array($this->getGlobal(N_BGA_CLOCK), N_REF_BGA_CLOCK_UNLIMITED),
            'pax' => $this->filterPax($this->getPaxByStatus(['SECRET', 'PORT', 'SEAT'])),
            'planes' => $this->getPlanesByIds(),
            'players' => $players,
            'version' => $this->getGlobal(N_BGA_VERSION),
            'vip' => $this->getGlobal(N_OPTION_VIP) ? $this->getVipInfo()['overall'] : null,
        ];
    }

    public function getGameProgression(): int
    {
        $paxTotal = $this->countPaxByStatus();
        if ($paxTotal == 0) {
            return 0;
        }
        $paxRemain = $this->countPaxByStatus(['MORNING', 'NOON', 'NIGHT']);
        return ($paxTotal - $paxRemain) / $paxTotal * 100;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// States
    ////////////

    /*
     * SETUP
     * Each player builds their plane
     */
    public function stInitPrivate()
    {
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->initializePrivateStateForAllActivePlayers();
    }

    /*
     * SETUP #1
     * Each player chooses a starting airport and alliance
     */
    public function argBuildAlliance(int $playerId): array
    {
        $buys = [];
        $claimed = [];
        $playerCount = $this->getPlayersNumber();
        if ($playerCount <= 3 && $this->getGlobal(N_OPTION_MAP) != N_MAP_SEA) {
            // Exclude Seattle for 2-3 players
            $claimed[] = 'SEA';
        }
        $possible = array_diff(array_keys(N_REF_ALLIANCE_COLOR), $claimed);
        foreach ($possible as $alliance) {
            $ownerId = $this->getOwnerId("SUBSTRING_INDEX(`alliances`, ',', 1) = '$alliance'");
            $buys[] = [
                'type' => 'ALLIANCE',
                'alliance' => $alliance,
                'cost' => 0,
                'enabled' => true,
                'ownerId' => $ownerId,
            ];
        }
        return ['buys' => $buys];
    }

    /*
     * SETUP #2
     * Each player chooses a second alliance (2-player only)
     */
    public function argBuildAlliance2(int $playerId): array
    {
        $buys = [];
        $claimed = $this->getObjectListFromDB("SELECT `alliances` FROM `plane` WHERE `player_id` = $playerId", true);
        if ($this->getGlobal(N_OPTION_MAP) != N_MAP_SEA) {
            // Exclude Seattle for 2-3 players
            $claimed[] = 'SEA';
        }
        $possible = array_diff(array_keys(N_REF_ALLIANCE_COLOR), $claimed);
        foreach ($possible as $alliance) {
            $buys[] = [
                'type' => 'ALLIANCE',
                'alliance' => $alliance,
                'cost' => 0,
                'enabled' => true,
            ];
        }
        return ['buys' => $buys];
    }

    /*
     * SETUP #3
     * Each player chooses a seat or speed upgrade
     */
    public function argBuildUpgrade(int $playerId): array
    {
        return [
            'buys' => [
                [
                    'type' => 'SEAT',
                    'seat' => 2,
                    'cost' => 0,
                    'enabled' => true,
                ],
                [
                    'type' => 'SPEED',
                    'speed' => 4,
                    'cost' => 0,
                    'enabled' => true,
                ],
            ],
        ];
    }

    /*
     * MAINTENANCE
     * Create passengers (first turn only)
     * Increase anger and file complaints
     * Add new passengers
     */

    public function stMaintenance(): void
    {
        // Everybody stop snoozing
        $this->DbQuery("UPDATE `player` SET `snooze` = 0");

        try {
            $hourInfo = $this->getHourInfo(true);
            if ($hourInfo['hour'] == 'PREFLIGHT') {
                // Create pax on the first turn
                $this->eraseUndo();
                $this->createPax();
                $pax = $this->getPaxByStatus('PORT');
                $this->notifyAllPlayers('pax', N_REF_MSG['addPax'], [
                    'count' => count($pax),
                    'pax' => array_values($pax),
                    'location' => $this->getPaxLocations($pax),
                ]);
            } else {
                // Add anger/complaints on subsequent turns
                $this->angerPax();
            }

            if ($hourInfo['hour'] == 'FINALE') {
                // File complaint for every 2 pax
                $count = $this->countPaxByStatus(['PORT', 'SEAT']);
                $complaint = floor($count / 2);
                $this->setStat($complaint, 'complaintFinale');
                if ($complaint > 0) {
                    $this->notifyAllPlayers('complaint', N_REF_MSG['complaintFinale'], [
                        'complaint' => $complaint,
                        'count' => $count,
                        'total' => $this->countComplaint(),
                    ]);
                }

                // End the game
                $this->endGame();
            } else {
                // Advance the hour
                $hourInfo = $this->advanceHour($hourInfo);
                if ($hourInfo['hour'] != 'FINALE') {
                    $this->addPax($hourInfo);
                }
                $this->gamestate->nextState('prepare');
            }
        } catch (NGameOverException $e) {
            $this->endGame();
        }
    }

    /*
     * PREPARE
     * Reset planes
     * Add passengers
     * Players purchase upgrades
     */
    public function stPrepare()
    {
        // Reset time to the full amount
        $this->giveExtraTimeAll(9999);
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->initializePrivateStateForAllActivePlayers();
        $this->DbQuery("UPDATE `plane` SET `speed_remain` = `speed`");
        $this->createUndo();
        $planes = $this->getPlanesByIds();
        $this->notifyAllPlayers('planes', '', [
            'planes' => array_values($planes)
        ]);

        foreach ($planes as $plane) {
            if ($plane->tempSeat) {
                $this->notifyAllPlayers('message', N_REF_MSG['tempUnused'], [
                    'i18n' => ['temp'],
                    'preserve' => ['tempIcon'],
                    'player_id' => $plane->id,
                    'player_name' => $plane->name,
                    'temp' => clienttranslate('Temporary Seat'),
                    'tempIcon' => 'seat',
                ]);
                $this->incStat(1, 'tempSeatUnused');
                $this->incStat(1, 'tempSeatUnused', $plane->id);
            }
            if ($plane->tempSpeed) {
                $this->notifyAllPlayers('message', N_REF_MSG['tempUnused'], [
                    'i18n' => ['temp'],
                    'preserve' => ['tempIcon'],
                    'player_id' => $plane->id,
                    'player_name' => $plane->name,
                    'temp' => clienttranslate('Temporary Speed'),
                    'tempIcon' => 'speed',
                ]);
                $this->incStat(1, 'tempSpeedUnused');
                $this->incStat(1, 'tempSpeedUnused', $plane->id);
            }
        }
    }

    public function argPrepareBuy(int $playerId): array
    {
        $plane = $this->getPlaneById($playerId);
        $cash = $plane->getCashRemain();
        $wallet = $this->getPaxWallet($plane->id);
        $buys = [];

        // Seat
        if ($plane->seat < 5) {
            $seat = $plane->seat + 1;
            $cost = N_REF_SEAT_COST[$seat];
            $buys[] = [
                'type' => 'SEAT',
                'seat' => $seat,
                'cost' => $cost,
                'enabled' => $cash >= $cost,
            ];
        }

        // Temp Seat
        $cost = 2;
        $ownerId = $this->getOwnerId("`temp_seat` = 1");
        $buys[] = [
            'type' => 'TEMP_SEAT',
            'cost' => $cost,
            'enabled' => $cash >= $cost,
            'ownerId' => $ownerId,
        ];

        // Speed
        if ($plane->speed < 9) {
            $speed = $plane->speed + 1;
            $cost = N_REF_SPEED_COST[$speed];
            $buys[] = [
                'type' => 'SPEED',
                'speed' => $speed,
                'cost' => $cost,
                'enabled' => $cash >= $cost,
            ];
        }

        // Temp Speed
        $cost = 1;
        $ownerId = $this->getOwnerId("`temp_speed` = 1");
        $buys[] = [
            'type' => 'TEMP_SPEED',
            'cost' => $cost,
            'enabled' => $cash >= $cost,
            'ownerId' => $ownerId,
        ];

        // Alliances
        $cost = 7;
        $claimed = $plane->alliances;
        $playerCount = $this->getPlayersNumber();
        if ($playerCount <= 3 && $this->getGlobal(N_OPTION_MAP) != N_MAP_SEA) {
            // Exclude Seattle for 2-3 players
            $claimed[] = 'SEA';
        }
        $possible = array_diff(array_keys(N_REF_ALLIANCE_COLOR), $claimed);
        foreach ($possible as $alliance) {
            $buys[] = [
                'type' => 'ALLIANCE',
                'alliance' => $alliance,
                'cost' => $cost,
                'enabled' => $cash >= $cost,
            ];
        }

        $args = [
            'buys' => $buys,
            'cash' => $cash,
            'wallet' => array_values($wallet),
        ];
        if ($plane->debt > 0) {
            $ledger = $this->getLedger($playerId);
            $pay = self::_argPreparePay($plane, $wallet);
            $args['ledger'] = $ledger;
            $args['overpay'] = $pay['overpay'];
        }

        return $args;
    }

    public function argPreparePay(int $playerId): array
    {
        $plane = $this->getPlaneById($playerId);
        $wallet = $this->getPaxWallet($plane->id);
        return self::_argPreparePay($plane, $wallet);
    }

    private function _argPreparePay(NPlane $plane, array $wallet): array
    {
        $walletCount = count($wallet);
        $suggestion = null;
        $overpay = null;
        foreach ($this->generatePermutations($wallet) as $p) {
            $thisSum = 0;
            $thisSuggestion = [];
            for ($i = $walletCount - 1; $thisSum < $plane->debt && $i >= 0; $i--) {
                $thisSuggestion[] = $p[$i];
                $thisSum += $p[$i];
            }
            $thisOverpay = $thisSum - $plane->debt;
            if ($suggestion == null || $thisOverpay < $overpay || $thisOverpay == $overpay && count($thisSuggestion) < count($suggestion)) {
                $suggestion = $thisSuggestion;
                $overpay = $thisOverpay;
            }
            if ($overpay == 0) {
                break;
            }
        }

        return [
            'debt' => $plane->debt,
            'overpay' => $overpay,
            'suggestion' => $suggestion,
            'wallet' => array_values($wallet),
        ];
    }

    /*
     * FLIGHT
     * Reveal passengers
     * Start the clock
     * Players transport passengers
     */
    public function stReveal(): void
    {
        // Remove undo info
        $this->eraseUndo();

        // Reveal passengers
        $msg = '';
        $args = [];
        $pax = $this->getPaxByStatus('SECRET');
        $vipNew = $this->getVarInt('vipNew');
        foreach ($pax as $x) {
            if ($x->vip == 'MYSTERY') {
                continue;
            }
            $x->status = 'PORT';

            if ($vipNew) {
                // Make them a VIP
                $vipNew = 0;
                $this->setVar('vipNew', 0);
                $hourInfo = $this->getHourInfo();
                $key = "vip" . $hourInfo['hour'];
                $hourVips = $this->getVarArray($key);
                $nextVip = array_pop($hourVips);
                if (!$nextVip) {
                    throw new BgaVisibleSystemException("stReveal: No VIP exists [???]");
                }
                $this->setVar($key, $hourVips);
                $this->notifyAllPlayers('vip', '', [
                    'overall' => $this->getVipInfo()['overall']
                ]);
                $x->vip = $nextVip;

                // Apply starting conditions
                if ($x->vip == 'DOUBLE') {
                    // VIP Double
                    // Create the fugitive
                    $doubleId = $x->id * -1;
                    $this->DbQuery("INSERT INTO `pax` (`pax_id`, `cash`, `destination`, `location`, `optimal`, `origin`, `status`, `vip`) VALUES ($doubleId, 0, '{$x->destination}', '{$x->location}', {$x->optimal}, '{$x->origin}', '{$x->status}', '{$x->vip}')");
                } else if ($x->vip == 'GRUMPY') {
                    // VIP Grumpy
                    // Starts at 1 anger
                    $x->anger = 1;
                    $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger} WHERE `pax_id` = {$x->id}");
                } else if ($x->vip == 'CREW' || $x->vip == 'DISCOUNT' || $x->vip == 'RETURN') {
                    // VIP Crew/Discount/Return
                    // Reduce the fare
                    $x->cash = $x->vip == 'DISCOUNT' ? floor($x->cash / 2) : 0;
                    $this->DbQuery("UPDATE `pax` SET `cash` = {$x->cash} WHERE `pax_id` = {$x->id}");
                } else if ($x->vip == 'LOYAL') {
                    // VIP Loyal
                    // Choose an alliance
                    $possibleAlliances = [];
                    if (array_key_exists($x->origin, N_REF_ALLIANCE_COLOR)) {
                        $possibleAlliances[] = $x->origin;
                    } else if (array_key_exists($x->destination, N_REF_ALLIANCE_COLOR)) {
                        $possibleAlliances[] = $x->destination;
                    }
                    if (empty($possibleAlliances)) {
                        $possibleAlliances = ['ATL', 'DFW', 'LAX', 'ORD'];
                        $playerCount = $this->getPlayersNumber();
                        $optionMap = $this->getGlobal(N_OPTION_MAP);
                        if ($playerCount >= 4 || $optionMap == N_MAP_SEA) {
                            // Include SEA with 4+ players
                            $possibleAlliances[] = 'SEA';
                        }
                    }
                    $alliance = $possibleAlliances[array_rand($possibleAlliances)];
                    $x->vip = "LOYAL_$alliance";
                } else if ($x->vip == 'MYSTERY') {
                    // VIP Mystery
                    // Do not flip
                    $x->status = 'SECRET';
                }
                $this->DbQuery("UPDATE `pax` SET `vip` = '{$x->vip}' WHERE `pax_id` = {$x->id}");

                // Notification message
                $msg = N_REF_MSG['vipWelcome'];
                $vipInfo = $x->getVipInfo();
                if ($vipInfo['args']) {
                    $args['desc'] = [
                        'log' => $vipInfo['desc'],
                        'args' => $vipInfo['args'],
                    ];
                    $args['vip'] = [
                        'log' => $vipInfo['name'],
                        'args' => $vipInfo['args'],
                    ];
                } else {
                    $args['desc'] = $vipInfo['desc'];
                    $args['vip'] = $vipInfo['name'];
                }
                $args['location'] = $x->location;
            }

            $this->DbQuery("UPDATE `pax` SET `status` = '{$x->status}' WHERE `pax_id` = {$x->id}");
        }
        $args['pax'] = array_values($pax);
        $this->notifyAllPlayers('pax', $msg, $args);

        // Start the timer
        $seconds = null;
        if (in_array($this->getGlobal(N_BGA_CLOCK), N_REF_BGA_CLOCK_REALTIME)) {
            $duration = $this->getGlobal(N_OPTION_TIMER) * ($this->getPlayersNumber() * 5 + 20);
            if ($duration) {
                $seconds = $duration;
                $endTime = time() + $seconds;
                $this->setVar('endTime', $endTime);
            }
        } else {
            $this->setVar('endTime', null);
        }
        $this->giveExtraTimeAll($seconds + 60);

        // Play the sound and begin flying
        $this->notifyAllPlayers('sound', '', [
            'sound' => 'chime',
            'suppress' => ['yourturn'],
        ]);
        $this->gamestate->nextState('fly');
    }

    public function argFly(): array
    {
        return [
            'endTime' => $this->getVarInt('endTime'),
        ];
    }

    public function argFlyPrivate(int $playerId): array
    {
        $map = $this->getMap();
        $plane = $this->getPlaneById($playerId);
        return [
            'moves' => $map->getPossibleMoves($plane),
            'paxDrop' => [],
            'paxPickup' => [],
            'speedRemain' => max(0, $plane->speedRemain) + ($plane->tempSpeed ? 1 : 0),
        ];
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Actions (ajax)
    ////////////

    public function undo(): void
    {
        $this->gamestate->checkPossibleAction('undo');
        $playerId = $this->getCurrentPlayerId();
        $this->applyUndo($playerId);

        $plane = $this->getPlaneById($playerId);
        $this->notifyAllPlayers('planes', N_REF_MSG['undo'], [
            'planes' => [$plane],
            'player_id' => $playerId,
            'player_name' => $this->getCurrentPlayerName(),
        ]);
        $this->gamestate->setPlayersMultiactive([$playerId], '');
        $this->gamestate->initializePrivateState($playerId);
    }

    public function buy(string $type, ?string $alliance): void
    {
        $this->checkAction('buy');
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        if ($type == 'ALLIANCE') {
            if (empty($plane->alliances)) {
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
            throw new BgaVisibleSystemException("buy: Invalid type $type [???]");
        }
    }

    private function buyAlliancePrimary(NPlane $plane, string $alliance): void
    {
        $owner = $this->getOwnerName("SUBSTRING_INDEX(`alliances`, ',', 1) = '$alliance'");
        if ($owner != null) {
            $this->userException('allianceOwner', $owner, $alliance);
        }

        $color = N_REF_ALLIANCE_COLOR[$alliance];
        $plane->alliances = [$alliance];
        $plane->location = $alliance;
        $this->DbQuery("UPDATE `plane` SET `alliances` = '{$plane->getAlliancesSql()}', `location` = '{$plane->location}' WHERE `player_id` = {$plane->id}");
        $this->DbQuery("UPDATE `player` SET `player_color` = '$color' WHERE `player_id` = {$plane->id}");
        $this->reloadPlayersBasicInfos();

        // Statistics
        $this->setStat(1, 'alliances', $plane->id);

        $this->notifyAllPlayers('buildPrimary', N_REF_MSG['alliance'], [
            'alliance' => $alliance,
            'color' => $color,
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);
        $this->notifyAllPlayers('buys', '', [
            'buys' => [
                [
                    'type' => 'ALLIANCE',
                    'alliance' => $alliance,
                    'ownerId' => $plane->id,
                ]
            ],
            'state' => 'buildAlliance',
        ]);

        $playerCount = $this->getPlayersNumber();
        $this->gamestate->nextPrivateState($plane->id,  $playerCount == 2 ? 'buildAlliance2' : 'buildUpgrade');
    }

    private function buyAlliance(NPlane $plane, string $alliance): void
    {
        $isBuild = $this->gamestate->state()['name'] == 'build';
        if (in_array($alliance, $plane->alliances)) {
            throw new BgaVisibleSystemException("buyAlliance: $plane already joined $alliance [???]");
        }
        $cost = $isBuild ? 0 : 7;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaVisibleSystemException("buyAlliance: $plane with $$cash cannot afford $$cost [???]");
        }

        $plane->debt += $cost;
        $plane->alliances[] = $alliance;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `alliances` = '{$plane->getAlliancesSql()}' WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'ALLIANCE', $alliance, $cost);

        // Statistics
        $this->setStat(count($plane->alliances), 'alliances', $plane->id);

        $this->notifyAllPlayers('planes', N_REF_MSG['alliance'], [
            'alliance' => $alliance,
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $this->gamestate->nextPrivateState($plane->id, $isBuild ? 'buildUpgrade' : 'prepareBuy');
    }

    private function buySeat(NPlane $plane): void
    {
        if ($plane->seat >= 5) {
            throw new BgaVisibleSystemException("buySeat: $plane already at maximum seats 5 [???]");
        }
        $isBuild = $this->gamestate->state()['name'] == 'build';
        $cost = $isBuild ? 0 : N_REF_SEAT_COST[$plane->seat + 1];
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaVisibleSystemException("buySeat: $plane with $$cash cannot afford $$cost [???]");
        }

        $plane->debt += $cost;
        $plane->seat++;
        $plane->seatRemain++;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `seat` = {$plane->seat} WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'SEAT', $plane->seat, $cost);

        // Statistics
        $this->setStat($plane->seat, 'seat', $plane->id);

        $this->notifyAllPlayers('planes', N_REF_MSG['seat'], [
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'seat' => $plane->seat,
        ]);

        if ($isBuild) {
            $this->gamestate->setPlayerNonMultiactive($plane->id, 'maintenance');
        } else {
            $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
        }
    }

    private function buyTempSeat(NPlane $plane): void
    {
        $owner = $this->getOwnerName("`temp_seat` = 1");
        if ($owner != null) {
            $this->userException('tempOwner', $owner, self::_('Temporary Seat'));
        }
        $cost = 2;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaVisibleSystemException("buyTempSeat: $plane with $$cash cannot afford $$cost [???]");
        }

        $plane->debt += $cost;
        $plane->tempSeat = true;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `temp_seat` = 1 WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'TEMP_SEAT', null, $cost);

        // Statistics
        $this->incStat(1, 'tempSeat', $plane->id);

        $this->notifyAllPlayers('planes', N_REF_MSG['temp'], [
            'i18n' => ['temp'],
            'preserve' => ['tempIcon'],
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'temp' => clienttranslate('Temporary Seat'),
            'tempIcon' => 'speed',
        ]);
        $this->notifyAllPlayers('buys', '', [
            'buys' => [
                [
                    'type' => 'TEMP_SEAT',
                    'ownerId' => $plane->id,
                ]
            ],
            'state' => 'prepareBuy',
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
    }

    private function buySpeed(NPlane $plane): void
    {
        if ($plane->speed >= 9) {
            throw new BgaVisibleSystemException("buySpeed: $plane already at maximum speed 9 [???]");
        }
        $isBuild = $this->gamestate->state()['name'] == 'build';
        $cost = $isBuild ? 0 : N_REF_SPEED_COST[$plane->speed + 1];
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaVisibleSystemException("buySpeed: $plane with $$cash cannot afford $$cost [???]");
        }

        $plane->debt += $cost;
        $plane->speed++;
        $plane->speedRemain = $plane->speed;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `speed` = {$plane->speed}, `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'SPEED', $plane->speed, $cost);

        // Statistics
        $this->setStat($plane->speed, 'speed', $plane->id);

        $this->notifyAllPlayers('planes', N_REF_MSG['speed'], [
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'speed' => $plane->speed,
        ]);

        if ($isBuild) {
            $this->gamestate->setPlayerNonMultiactive($plane->id, 'maintenance');
        } else {
            $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
        }
    }

    private function buyTempSpeed(NPlane $plane): void
    {
        $owner = $this->getOwnerName("`temp_speed` = 1");
        if ($owner != null) {
            $this->userException('tempOwner', $owner, self::_('Temporary Speed'));
        }
        $cost = 1;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaVisibleSystemException("buyTempSpeed: $plane with $$cash cannot afford $$cost [???]");
        }

        $plane->debt += $cost;
        $plane->tempSpeed = true;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `temp_speed` = 1 WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'TEMP_SPEED', null, $cost);

        // Statistics
        $this->incStat(1, 'tempSpeed', $plane->id);

        $this->notifyAllPlayers('planes', N_REF_MSG['temp'], [
            'i18n' => ['temp'],
            'preserve' => ['tempIcon'],
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'temp' => clienttranslate('Temporary Speed'),
            'tempIcon' => 'speed',
        ]);
        $this->notifyAllPlayers('buys', '', [
            'buys' => [
                [
                    'type' => 'TEMP_SPEED',
                    'ownerId' => $plane->id,
                ]
            ],
            'state' => 'prepareBuy',
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
    }

    public function buyAgain(): void
    {
        $this->checkAction('buyAgain');
        $playerId = $this->getCurrentPlayerId();
        $this->gamestate->nextPrivateState($playerId, 'prepareBuy');
    }

    public function pay($paid): void
    {
        $this->checkAction('pay');
        $playerId = $this->getCurrentPlayerId();
        $wallet = $this->getPaxWallet($playerId);
        $total = 0;
        $validIds = [];
        foreach ($paid as $cash) {
            if (!$cash) {
                continue;
            }
            $paxId = array_search($cash, $wallet);
            if ($paxId === false) {
                throw new BgaVisibleSystemException("pay: Wallet $playerId has no \$$cash bill with validIds=" . join(',', $validIds) . " [???]");
            }
            unset($wallet[$paxId]);
            $total += $cash;
            $validIds[] = $paxId;
        }

        $plane = $this->getPlaneById($playerId);
        if ($total < $plane->debt) {
            $this->userException('pay', "\${$plane->debt}");
        }

        // Payment
        $this->DbQuery("UPDATE `pax` SET `status` = 'PAID' WHERE `pax_id` IN (" . join(',', $validIds) . ")");
        $this->DbQuery("UPDATE `plane` SET `debt` = 0 WHERE `player_id` = $playerId");

        // Statistics
        $overpay = $total - $plane->debt;
        if ($overpay > 0) {
            $this->incStat($overpay, 'overpay', $plane->id);
        }

        // Update plane gauges UI (e.g., overpayment)
        $plane = $this->getPlaneById($playerId);
        $this->notifyAllPlayers('planes', '', [
            'planes' => [$plane],
        ]);


        $this->gamestate->setPlayerNonMultiactive($playerId, 'reveal');
    }

    public function vip(bool $accept): void
    {
        $this->checkAction('vip');
        $hourInfo = $this->getHourInfo();
        $key = "vip" . $hourInfo['hour'];
        $hourVips = $this->getVarArray($key);
        if ($accept && empty($hourVips)) {
            throw new BgaVisibleSystemException("vip: No VIP to accept [???]");
        }
        $this->setVar('vipNew', $accept ? 1 : 0);

        $hourInfo = $this->getHourInfo();
        $hourInfo['player_id'] = $this->getCurrentPlayerId();
        $hourInfo['player_name'] = $this->getCurrentPlayerName();
        $msg = $accept ? N_REF_MSG['vipAccept'] : N_REF_MSG['vipDecline'];
        $this->notifyAllPlayers('hour', $msg, $hourInfo);
    }

    public function prepareDone(): void
    {
        $this->checkAction('prepareDone');
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        if ($plane->debt > 0) {
            $this->gamestate->nextPrivateState($plane->id, 'preparePay');
        } else {
            $this->gamestate->setPlayerNonMultiactive($playerId, 'reveal');
        }
    }

    public function flyDone(bool $snooze = false): void
    {
        $this->checkAction('flyDone');
        if ($this->enforceTimer()) {
            return;
        }
        $playerId = $this->getCurrentPlayerId();
        if ($snooze) {
            if (intval($this->getUniqueValueFromDB("SELECT COUNT(1) FROM `player` WHERE `player_is_multiactive` = 1 AND `player_id` != $playerId")) == 0) {
                $this->userException('noSnooze');
            }
            $this->DbQuery("UPDATE `player` SET `snooze` = 1 WHERE `player_id` = $playerId");
        }
        $this->gamestate->setPlayerNonMultiactive($playerId, 'maintenance');
    }

    public function flyTimer(): void
    {
        $this->enforceTimer();
    }

    public function flyAgain(): void
    {
        $this->gamestate->checkPossibleAction('flyAgain');
        if ($this->enforceTimer()) {
            return;
        }
        $playerId = $this->getCurrentPlayerId();
        $this->gamestate->setPlayersMultiactive([$playerId], '');
        $this->gamestate->initializePrivateState($playerId);
    }

    public function move(string $location): void
    {
        $this->checkAction('move');
        if ($this->enforceTimer()) {
            return;
        }
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        $map = $this->getMap();
        $possible = $map->getPossibleMoves($plane);
        if (!array_key_exists($location, $possible)) {
            throw new BgaVisibleSystemException("move: $plane cannot reach $location [???]");
        }

        $move = $possible[$location];
        $plane->origin = $move->getOrigin();
        $plane->location = $location;
        $plane->speedRemain -= $move->fuel;
        $plane->speedPenalty = $move->penalty;
        if ($plane->speedRemain == -1 && $plane->tempSpeed) {
            $this->useTempSpeed($plane);
        } else if ($plane->speedRemain < 0) {
            throw new BgaVisibleSystemException("move: $plane not enough fuel to reach $location with speedRemain={$plane->speedRemain}, tempSpeed={$plane->tempSpeed} [???]");
        }
        $penalty = intval($plane->speedPenalty);
        $this->DbQuery("UPDATE `plane` SET `location` = '{$plane->location}', `origin` = '{$plane->origin}', `speed_penalty` = $penalty, `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");
        $this->DbQuery("UPDATE `pax` SET `moves` = `moves` + {$move->fuel} WHERE `status` = 'SEAT' AND `player_id` = {$plane->id}");

        // Statistics
        $this->incStat($move->fuel, 'moves');
        $this->incStat($move->fuel, 'moves', $playerId);
        array_shift($move->path);
        $hasStorm = false;
        foreach ($move->path as $location) {
            if (strlen($location) == 3) {
                $this->incStat(1, $location, $playerId);
            }
            if (array_key_exists($location, $map->weather)) {
                $type = $map->weather[$location];
                if ($type == 'SLOW') {
                    $hasStorm = true;
                }
                $this->incStat(1, "moves$type");
                $this->incStat(1, "moves$type", $playerId);
            }
        }

        // VIP Storm
        // Remove the VIP condition after flying through a storm
        if ($hasStorm) {
            $stormIds = array_map('intval', $this->getObjectListFromDB("SELECT `pax_id` FROM `pax` WHERE `status` = 'SEAT' AND `player_id` = {$plane->id} AND `vip` = 'STORM'", true));
            if (!empty($stormIds)) {
                $this->DbQuery("UPDATE `pax` SET `vip` = NULL WHERE `pax_id` IN (" . join(',', $stormIds) . ")");
                $pax = $this->getPaxByIds($stormIds);
                $this->notifyAllPlayers('pax', '', [
                    'pax' => array_values($pax),
                ]);
            }
        }

        // Notify UI
        $msg = strlen($location) == 3 ? N_REF_MSG['movePort'] : N_REF_MSG['move'];
        $this->notifyAllPlayers('move', $msg, [
            'fuel' => $move->fuel,
            'location' => $plane->location,
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $this->awakenSnoozers($playerId);
        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    public function useTempSpeed(NPlane &$plane): void
    {
        $plane->tempSpeed = false;
        $this->DbQuery("UPDATE `plane` SET `temp_speed` = 0 WHERE `player_id` = {$plane->id}");
        $this->notifyAllPlayers('message', N_REF_MSG['tempUsed'], [
            'i18n' => ['temp'],
            'preserve' => ['tempIcon'],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'temp' => clienttranslate('Temporary Speed'),
            'tempIcon' => 'speed',
        ]);
    }

    public function board(int $paxId): void
    {
        $this->checkAction('board');
        if ($this->enforceTimer()) {
            return;
        }
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        self::_board($plane, $paxId);
        $this->awakenSnoozers($playerId);
        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    private function _board(NPlane $plane, int $paxId): void
    {
        $x = $this->getPaxById($paxId, true);
        $planeIds = [$plane->id];

        $msg = N_REF_MSG['board'];
        $args = [];
        if ($x->status == 'SEAT') {
            if ($x->playerId == $plane->id) {
                throw new BgaVisibleSystemException("board: $x player ID is already {$plane->id} [???]");
            }
            // Transfer from another plane
            // Must be together at an airport
            $other = $this->getPlaneById($x->playerId);
            if ($other->location != $plane->location || strlen($plane->location) != 3) {
                $this->userException('boardTransfer', $other->name);
            }
            // Implicit deplane
            $msg = N_REF_MSG['boardTransfer'];
            $args['player_name2'] = $other->name;
            self::_deplane($other, $paxId, true);
            $planeIds[] = $other->id;
            $x = $this->getPaxById($paxId, true);
        }

        $vipInfo = $x->getVipInfo();
        if ($this->getGlobal(N_OPTION_VIP)) {
            // VIP Celebrity
            // If this pax is a celebrity, the plane must be empty
            if ($vipInfo && $vipInfo['key'] == 'CELEBRITY' && intval($this->getUniqueValueFromDB("SELECT COUNT(1) FROM `pax` WHERE `player_id` = {$plane->id} AND `status` = 'SEAT'")) > 0) {
                $this->vipException($vipInfo);
            }
            // Or, if a celebrity is on board, no boarding
            if (intval($this->getUniqueValueFromDB("SELECT COUNT(1) FROM `pax` WHERE `player_id` = {$plane->id} AND `status` = 'SEAT' AND `vip` = 'CELEBRITY'")) > 0) {
                $this->vipException('CELEBRITY');
            }

            // VIP First
            // If anyone is first, this pax must be among them
            $firstList = $this->getObjectListFromDB("SELECT `pax_id` FROM `pax` WHERE `location` = '{$x->location}' AND `status` = 'PORT' AND `vip` = 'FIRST'", true);
            if (!empty($firstList) && !in_array($paxId, $firstList)) {
                $this->vipException('FIRST');
            }

            // VIP Double
            // Board the fugitive (must be BEFORE the real pax)
            if ($vipInfo && $vipInfo['key'] == 'DOUBLE' && $x->id > 0) {
                self::_board($plane, $x->id * -1);
                $plane = $this->getPlaneById($plane->id);
            }

            // VIP Last
            // If this pax is last, the airport must be empty
            if ($vipInfo && $vipInfo['key'] == 'LAST' && intval($this->getUniqueValueFromDB("SELECT COUNT(1) FROM `pax` WHERE `location` = '{$x->location}' AND `status` = 'PORT' AND COALESCE(`vip`, 'X') != 'LAST'")) > 0) {
                $this->vipException($vipInfo);
            }

            // VIP Late
            // Requires 1 speed
            if ($vipInfo && $vipInfo['key'] == 'LATE') {
                $plane->speedRemain -= 1;
                if ($plane->speedRemain == -1 && $plane->tempSpeed) {
                    $this->useTempSpeed($plane);
                } else if ($plane->speedRemain < 0) {
                    $this->vipException($vipInfo);
                }
                $this->DbQuery("UPDATE `plane` SET `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");
            }

            // VIP Loyal
            // Requires specific alliance
            if ($vipInfo && $vipInfo['key'] == 'LOYAL') {
                $alliance = $vipInfo['args']['1'];
                if (!in_array($alliance, $plane->alliances)) {
                    $this->vipException($vipInfo);
                }
            }
        }

        if ($x->status == 'PORT' || ($x->status == 'SECRET' && $vipInfo && $vipInfo['key'] == 'MYSTERY')) {
            // Pickup from airport
            if (strlen($x->location) != 3) {
                throw new BgaVisibleSystemException("board: $x location is not at an airport [???]");
            }
            if ($x->location != $plane->location) {
                $this->userException('boardPort', $x->location);
            }
        } else {
            throw new BgaVisibleSystemException("board: $x status is invalid [???]");
        }

        if ($plane->seatRemain <= 0) {
            if (!$plane->tempSeat) {
                $this->userException('noSeat');
            }
            $this->useTempSeat($plane);
        }

        // Note: Unlike physical game, we preserve anger until deplane
        $x->playerId = $plane->id;
        $x->status = 'SEAT';
        $this->DbQuery("UPDATE `pax` SET `player_id` = {$x->playerId}, `status` = '{$x->status}' WHERE `pax_id` = {$x->id}");

        // Notify UI (except for VIP Double)
        if ($x->id > 0) {
            $planes = $this->getPlanesByIds($planeIds);
            $this->notifyAllPlayers('planes', '', [
                'planes' => array_values($planes)
            ]);
            $this->notifyAllPlayers('pax', $msg, [
                'location' => $x->location,
                'pax' => [$x],
                'player_id' => $plane->id,
                'player_name' => $plane->name,
            ] + $args);
        }
    }

    public function useTempSeat(NPlane &$plane): void
    {
        $plane->tempSeat = false;
        $this->DbQuery("UPDATE `plane` SET `temp_seat` = 0 WHERE `player_id` = {$plane->id}");
        $this->notifyAllPlayers('planes', N_REF_MSG['tempUsed'], [
            'i18n' => ['temp'],
            'preserve' => ['tempIcon'],
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'temp' => clienttranslate('Temporary Seat'),
            'tempIcon' => 'seat',
        ]);
    }

    public function deplane(int $paxId): void
    {
        $this->checkAction('deplane');
        if ($this->enforceTimer()) {
            return;
        }
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        self::_deplane($plane, $paxId);
        $this->awakenSnoozers($playerId);
        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    private function _deplane(NPlane $plane, int $paxId, bool $transfer = false): void
    {
        $x = $this->getPaxById($paxId, true);
        if ($x->status != 'SEAT') {
            throw new BgaVisibleSystemException("deplane: $x status is invalid [???]");
        }
        if (strlen($plane->location) != 3) {
            $this->userException('deplanePort');
        }

        $x->location = $plane->location;
        // Erase anger if deplaned at a new location
        if ($x->location != $plane->location) {
            $x->resetAnger();
        }

        // Is this the final destination?
        $deliver = $x->location == $x->destination;
        $vipInfo = $x->getVipInfo();
        if ($vipInfo) {
            // VIP Double
            // Deplane the fugitive (must be BEFORE the real pax)
            if ($vipInfo['key'] == 'DOUBLE' && $x->id > 0) {
                self::_deplane($plane, $x->id * -1);
            }

            // VIP Direct
            if ($vipInfo['key'] == 'DIRECT' && !$deliver) {
                $this->vipException($vipInfo);
            }

            // VIP Storm
            // Never deliver (condition is removed when met)
            if ($vipInfo['key'] == 'STORM') {
                $deliver = false;
            }
        }

        // Cannot transfer if delivery is possible
        if ($transfer && $deliver) {
            $this->userException('boardDeliver', $plane->name);
        }

        $args = [
            'location' => $x->location,
            'pax' => [$x],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ];
        if ($deliver) {
            $x->status = 'CASH';
            $args['cash'] = $x->cash;
            $args['moves'] = $x->moves;
            $this->incStat(1, 'pax');
            $this->incStat(1, 'pax', $plane->id);
            $this->incStat($x->cash, 'cash', $plane->id);

            // VIP Return
            // Create the round trip
            if ($vipInfo && $vipInfo['key'] == 'RETURN' && $x->cash == 0) {
                $cash = N_REF_FARE[$x->destination][$x->origin] * 2;
                $this->DbQuery("INSERT INTO `pax` (`cash`, `destination`, `location`, `optimal`, `origin`, `status`, `vip`) VALUES ($cash, '{$x->origin}', '{$x->location}', {$x->optimal}, '{$x->destination}', 'PORT', '{$x->vip}')");
                $newPax = $this->getPaxById($this->DbGetLastId());
                $args['pax'][] = $newPax;
            }
        } else {
            $x->playerId = null;
            $x->status = 'PORT';
        }
        $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `location` = '{$x->location}', `player_id` = {$x->getPlayerIdSql()}, `status` = '{$x->status}' WHERE `pax_id` = {$x->id}");

        // Notify UI (except for transfers and VIP Double)
        if (!$transfer && $x->id > 0) {
            $planes = $this->getPlanesByIds([$plane->id]);
            $this->notifyAllPlayers('planes', '', [
                'planes' => array_values($planes)
            ]);
            $msg = $deliver ? N_REF_MSG['deplaneDeliver'] : N_REF_MSG['deplane'];
            $this->notifyAllPlayers('pax', $msg, $args);
        }
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Helpers
    ////////////

    private function getGlobal(int $id): ?int
    {
        $value = @$this->gamestate->table_globals[$id];
        return $value == null ? null : intval($value);
    }

    private function awakenSnoozers(int $playerId): void
    {
        $snoozers = $this->getObjectListFromDB("SELECT `player_id` FROM `player` WHERE `player_is_multiactive` = 0 AND `snooze` = 1 AND `player_id` != $playerId", true);
        if (!empty($snoozers)) {
            $this->DbQuery("UPDATE `player` SET `snooze` = 0 WHERE `player_id` IN (" . join(', ', $snoozers) . ")");
            $this->gamestate->setPlayersMultiactive($snoozers, '');
        }
    }

    private function getVar(string $key): ?string
    {
        return $this->getUniqueValueFromDB("SELECT `value` FROM `var` WHERE `key` = '$key'");
    }

    private function getVarArray(string $key): array
    {
        $value = $this->getVar($key);
        return empty($value) ? [] : explode(',', $value);
    }

    private function getVarInt(string $key): int
    {
        $value = $this->getVar($key);
        return intval($value);
    }

    private function setVar(string $key, $value): void
    {
        if ($value == null) {
            $this->DbQuery("DELETE FROM `var` WHERE `key` = '$key'");
        } else {
            if (is_array($value)) {
                $value = join(',', $value);
            }
            $this->DbQuery("INSERT INTO `var` (`key`, `value`) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE `value` = '$value'");
        }
    }

    private function enforceTimer(): bool
    {
        // We need to check BGA speed in case table switched to turn-based
        if (
            $this->getGlobal(N_OPTION_TIMER)
            && in_array($this->getGlobal(N_BGA_CLOCK), N_REF_BGA_CLOCK_REALTIME)
            && $this->gamestate->state()['name'] == 'fly'
            && time() > $this->getVarInt('endTime')
        ) {
            $this->notifyAllPlayers('flyTimer', '', []);
            $this->gamestate->setAllPlayersNonMultiactive('maintenance');
            return true;
        }
        return false;
    }

    private function getHourInfo(bool $beforeAddPax = false): array
    {
        $hour = $this->getVar('hour');
        $hourInfo = [
            'hour' => $hour,
            'hourDesc' => N_REF_HOUR[$hour]['desc'],
        ];

        if ($hour == 'MORNING' || $hour == 'NOON' || $hour == 'NIGHT') {
            $playerCount = $this->getPlayersNumber();
            $draw = $playerCount;
            if ($hour == 'MORNING') {
                $draw--;
            } else if ($hour == 'NIGHT') {
                $draw++;
            }
            $hourPax = N_REF_HOUR_PAX[$playerCount][$hour];
            $remainPax = $this->countPaxByStatus($hour);
            $total = $hourPax / $draw;
            $round = ($hourPax - $remainPax) / $draw;
            if ($beforeAddPax) {
                $round++;
            }
            $hourInfo['draw'] = $draw;
            $hourInfo['round'] = $round;
            $hourInfo['total'] = $total;

            if ($this->getGlobal(N_OPTION_VIP)) {
                $vipInfo = $this->getVipInfo();
                $vipRemain = count($vipInfo[$hour]);
                $hourInfo['vipRemain'] = $vipRemain;
                $hourInfo['vipNeed'] = $vipRemain > 0 && $vipRemain >= ($total - $round + 1);
                $hourInfo['vipNew'] = $vipInfo['new'] == 1;
            }
        }
        return $hourInfo;
    }

    private function getVipInfo(): array
    {
        $vipInfo = [
            'MORNING' => [],
            'NOON' => [],
            'NIGHT' => [],
            'new' => 0,
        ];
        $dbrows = $this->getCollectionFromDB("SELECT `key`, `value` FROM `var` WHERE `key` IN ('vipMORNING', 'vipNOON', 'vipNIGHT', 'vipNew')", true);
        foreach ($dbrows as $key => $value) {
            if ($key == 'vipNew') {
                $vipInfo['new'] = intval($value);
            } else {
                $vipInfo[substr($key, 3)] = empty($value) ? [] : explode(',', $value);
            }
        }
        $morning = count($vipInfo['MORNING']);
        $noon = count($vipInfo['NOON']);
        $night = count($vipInfo['NIGHT']);
        $vipInfo['overall'] = "$morning/$noon/$night";
        return $vipInfo;
    }

    private function advanceHour(array $hourInfo): array
    {
        $advance = $hourInfo['hour'] == 'PREFLIGHT' || $hourInfo['round'] > $hourInfo['total'];
        if ($advance) {
            $nextHour = N_REF_HOUR[$hourInfo['hour']]['next'];
            $prevHour = N_REF_HOUR[$nextHour]['prev'];
            $this->setVar('hour', $nextHour);
            $hourInfo = $this->getHourInfo(true);
        }
        $vip = array_key_exists('vipRemain', $hourInfo);
        $finale = $hourInfo['hour'] == 'FINALE';

        // Anger VIPs
        if ($advance && $vip && $prevHour) {
            $this->angerVips($prevHour);
        }

        // Notify hour
        $hourInfo['i18n'] = ['hourDesc'];
        if ($finale) {
            $hourInfo['count'] = $this->countPaxByStatus(['PORT', 'SEAT']);
            $this->notifyAllPlayers('hour', N_REF_MSG['hourFinale'], $hourInfo);
        } else {
            if ($advance) {
                // Weather speed penalty
                $this->DbQuery("UPDATE `plane` SET `speed_penalty` = 0 WHERE `location` NOT IN (SELECT `location` FROM `weather` WHERE `hour` = '{$hourInfo['hour']}' AND `token` = 'SLOW')");
                $this->DbQuery("UPDATE `plane` SET `speed_penalty` = 1 WHERE `location` IN (SELECT `location` FROM `weather` WHERE `hour` = '{$hourInfo['hour']}' AND `token` = 'SLOW')");

                // Notify weather
                $weather = $this->getWeather($hourInfo['hour']);
                $desc = [];
                foreach ($weather as $location => $token) {
                    $desc[$token][] = substr($location, 0, 3) . "-" . substr($location, 3, 3);
                }
                $this->notifyAllPlayers('message', N_REF_MSG['weatherSLOW'], [
                    'preserve' => ['wrapper', 'weatherIcon'],
                    'i18n' => ['hourDesc'],
                    'hourDesc' => $hourInfo['hourDesc'],
                    'location' => join(', ', $desc['SLOW']),
                    'weatherIcon' => 'SLOW',
                    'wrapper' => 'weatherFlex',
                ]);
                $this->notifyAllPlayers('weather', N_REF_MSG['weatherFAST'], [
                    'preserve' => ['wrapper', 'weatherIcon'],
                    'i18n' => ['hourDesc'],
                    'hourDesc' => $hourInfo['hourDesc'],
                    'location' => join(', ', $desc['FAST']),
                    'weather' => $weather,
                    'weatherIcon' => 'FAST',
                    'wrapper' => 'weatherFlex',
                ]);
            }

            // Notify hour
            $this->notifyAllPlayers('hour', N_REF_MSG['hour'], $hourInfo);

            // Notify VIP count
            if ($advance && $vip) {
                $this->notifyAllPlayers('message', N_REF_MSG['hourVip'], [
                    'i18n' => ['hourDesc'],
                    'count' => $hourInfo['vipRemain'],
                    'hourDesc' => $hourInfo['hourDesc'],
                ]);
            }
        }
        return $hourInfo;
    }

    private function createUndo(): void
    {
        $this->DbQuery("INSERT INTO `pax_undo` SELECT * FROM `pax` WHERE `status` = 'CASH'");
        $this->DbQuery("INSERT INTO `plane_undo` SELECT * FROM `plane`");
        $this->DbQuery("INSERT INTO `stats_undo` SELECT * FROM `stats` WHERE `stats_type` >= 10 AND `stats_player_id` IS NOT NULL");
    }

    private function applyUndo(int $playerId): void
    {
        $oldPlane = $this->getPlaneById($playerId);
        $this->DbQuery("DELETE FROM `ledger` WHERE `player_id` = $playerId");
        $this->DbQuery("REPLACE INTO `pax` SELECT * FROM `pax_undo` WHERE `player_id` = $playerId");
        $this->DbQuery("REPLACE INTO `plane` SELECT * FROM `plane_undo` WHERE `player_id` = $playerId");
        $this->DbQuery("DELETE FROM `stats` WHERE `stats_type` >= 10 AND `stats_player_id` = $playerId");
        $this->DbQuery("INSERT INTO `stats` SELECT * FROM `stats_undo` WHERE `stats_player_id` = $playerId");
        $newPlane = $this->getPlaneById($playerId);

        $buys = [];
        if ($oldPlane->tempSeat && !$newPlane->tempSeat) {
            $state = 'prepareBuy';
            $buys[] = [
                'type' => 'TEMP_SEAT',
                'ownerId' => null,
            ];
        }
        if ($oldPlane->tempSpeed && !$newPlane->tempSpeed) {
            $state = 'prepareBuy';
            $buys[] = [
                'type' => 'TEMP_SPEED',
                'ownerId' => null,
            ];
        }
        if (empty($newPlane->alliances)) {
            $state = 'buildAlliance';
            $buys[] = [
                'type' => 'ALLIANCE',
                'alliance' => $oldPlane->alliances,
                'ownerId' => null,
            ];
        }
        if (!empty($buys)) {
            $this->notifyAllPlayers('buys', '', [
                'buys' => $buys,
                'state' => $state,
            ]);
        }
    }

    private function eraseUndo(): void
    {
        $this->DbQuery("DELETE FROM `ledger`");
        $this->DbQuery("DELETE FROM `pax_undo`");
        $this->DbQuery("DELETE FROM `plane_undo`");
        $this->DbQuery("DELETE FROM `stats_undo`");
    }

    private function getPlayerIds(): array
    {
        return $this->getObjectListFromDB("SELECT `player_id` FROM `player`", true);
    }

    private function giveExtraTimeAll(?int $seconds = null)
    {
        if ($this->isAsync()) {
            return;
        }
        foreach ($this->getPlayerIds() as $playerId) {
            $this->giveExtraTime($playerId, $seconds);
        }
    }

    private function getMap(): NMap
    {
        $playerCount = $this->getPlayersNumber();
        $hour = $this->getVar('hour');
        $weather = $this->getWeather($hour);
        return new NMap($playerCount, $this->getGlobal(N_OPTION_MAP), $weather);
    }

    private function getWeather(string $hour): array
    {
        return $this->getCollectionFromDb("SELECT `location`, `token` FROM `weather` WHERE `hour` = '$hour'", true);
    }

    private function getPlaneById(int $playerId): NPlane
    {
        return $this->getPlanesByIds([$playerId])[$playerId];
    }

    private function getPlanesByIds($ids = []): array
    {
        $sql = "SELECT p.*, p.seat - ( SELECT COUNT(1) FROM `pax` x WHERE x.status = 'SEAT' AND x.player_id = p.player_id ) AS seat_remain, ( SELECT SUM(cash) FROM `pax` x WHERE x.status = 'CASH' AND x.player_id = p.player_id ) AS cash, b.player_name FROM `plane` p JOIN `player` b ON (b.player_id = p.player_id)";
        if (!empty($ids)) {
            $sql .= " WHERE p.player_id IN (" . join(',', $ids) . ")";
        }
        return array_map(function ($dbrow) {
            return new NPlane($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    private function getOwnerName(string $sqlWhere): ?string
    {
        return $this->getUniqueValueFromDB("SELECT b.`player_name` FROM `plane` p JOIN `player` b ON (b.player_id = p.player_id) WHERE $sqlWhere LIMIT 1");
    }

    private function getOwnerId(string $sqlWhere): ?string
    {
        return $this->getUniqueValueFromDB("SELECT `player_id` FROM `plane` WHERE $sqlWhere LIMIT 1");
    }

    private function getPaxById(int $paxId, bool $lock = false): NPax
    {
        return $this->getPaxByIds([$paxId], $lock)[$paxId];
    }

    private function getPaxByIds(array $ids = [], bool $lock = false): array
    {
        $sql = "SELECT * FROM `pax`";
        if (!empty($ids)) {
            $sql .= " WHERE `pax_id` IN (" . join(',', $ids) . ")";
        }
        if ($lock) {
            $sql .= " FOR UPDATE";
        }
        return array_map(function ($dbrow) {
            return new NPax($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    private function getPaxByStatus($status, ?int $limit = null, ?int $playerId = null): array
    {
        if (is_array($status)) {
            $status = join("', '", $status);
        }
        $sql = "SELECT * FROM `pax` WHERE `status` IN ('$status')";
        if ($playerId != null) {
            $sql .= " AND `player_id` = $playerId";
        }
        $sql .= " ORDER BY `pax_id`";
        if ($limit != null) {
            $sql .= " LIMIT $limit";
        }
        return array_map(function ($dbrow) {
            return new NPax($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    private function countPaxByStatus($status = null): int
    {
        if (is_array($status)) {
            $status = join("', '", $status);
        }
        $sql = "SELECT COUNT(1) FROM `pax`";
        if (!empty($status)) {
            $sql .= " WHERE `status` IN ('$status')";
        }
        return intval($this->getUniqueValueFromDB($sql));
    }

    private function countComplaint(): int
    {
        return $this->countPaxByStatus('COMPLAINT') + $this->getStat('complaintFinale') + $this->getStat('complaintVip');
    }

    private function exceptionMsg(string $msgExKey, ...$args): string
    {
        $msg = self::_(N_REF_MSG_EX[$msgExKey]);
        if (!empty($args)) {
            $msg = sprintf($msg, ...$args);
        }
        return $msg;
    }

    private function userException(string $msgExKey, ...$args): void
    {
        $msg = $this->exceptionMsg($msgExKey, ...$args);
        throw new BgaUserException($msg);
    }

    private function vipException($vipInfo): void
    {
        if (!is_array($vipInfo)) { // string input
            $vipInfo = [
                'name' => N_REF_VIP[$vipInfo]['name'],
                'desc' => N_REF_VIP[$vipInfo]['desc'],
            ];
        }
        $msg = $this->exceptionMsg('vip', self::_($vipInfo['name']), self::_($vipInfo['desc']));
        if ($vipInfo['args']) {
            foreach ($vipInfo['args'] as $argKey => $argValue) {
                $msg = str_replace('${' . $argKey . '}', $argValue, $msg);
            }
        }
        throw new BgaUserException($msg);
    }

    private function getPaxWallet(int $playerId): array
    {
        $sql = "SELECT `pax_id`, `cash` FROM `pax` WHERE `cash` > 0 AND `status` = 'CASH' AND `player_id` = $playerId ORDER BY `cash` DESC, `pax_id`";
        return array_map('intval', $this->getCollectionFromDB($sql, true));
    }

    private function getLedger(int $playerId): array
    {
        $sql = "SELECT `type`, `arg`, `cost` FROM `ledger` WHERE `player_id` = $playerId ORDER BY `type`, `cost`";
        return $this->getObjectListFromDB($sql);
    }

    private function addLedger(int $playerId, string $type, ?string $arg, int $cost): void
    {
        if ($arg) {
            $this->DbQuery("INSERT INTO `ledger` (`player_id`, `type`, `arg`, `cost`) VALUES ($playerId, '$type', '$arg', $cost)");
        } else {
            $this->DbQuery("INSERT INTO `ledger` (`player_id`, `type`, `cost`) VALUES ($playerId, '$type', $cost)");
        }
    }

    private function createPax(): void
    {
        $planes = $this->getPlanesByIds();
        $playerCount = count($planes);
        $optionMap = $this->getGlobal(N_OPTION_MAP);
        $airports = ['ATL', 'DEN', 'DFW', 'LAX', 'MIA', 'ORD', 'SFO'];
        if ($playerCount >= 3 || $optionMap == N_MAP_JFK || $optionMap == N_MAP_SEA) {
            // Include JFK with 3+ players
            $airports[] = 'JFK';
        }
        if ($playerCount >= 4 || $optionMap == N_MAP_SEA) {
            // Include SEA with 4+ players
            $airports[] = 'SEA';
        }

        // Create possible passengers
        $pax = [];
        foreach (N_REF_FARE as $origin => $destinations) {
            if (in_array($origin, $airports)) {
                foreach ($destinations as $destination => $cash) {
                    if (in_array($destination, $airports)) {
                        $pax[] = [$origin, $destination, $cash];
                    }
                }
            }
        }
        shuffle($pax);

        // Compute optimal moves between each airport
        $optimal = [];
        $map = $this->getMap();
        $fakePlane = new NPlane([
            'player_id' => 0,
            'alliances' => 'ATL,DFW,LAX,ORD,SEA',
            'cash' => 0,
            'debt' => 0,
            'location' => '',
            'origin' => '',
            'player_name' => '',
            'seat_remain' => 1,
            'seat' => 1,
            'speed_penalty' => 0,
            'speed_remain' => 9,
            'speed' => 9,
            'temp_seat' => 0,
            'temp_speed' => 0,
        ]);
        foreach ($airports as $airport) {
            $fakePlane->location = $airport;
            $moves = $map->getPossibleMoves($fakePlane);
            foreach ($moves as $move) {
                if (strlen($move->location) == 3) {
                    $optimal[$airport][$move->location] = $move->fuel;
                }
            }
        }

        // Create starting passenger in each airport
        foreach ($planes as $plane) {
            foreach ($pax as $k => $x) {
                [$origin, $destination, $cash] = $x;
                $opt = $optimal[$origin][$destination];
                if ($origin == $plane->alliances[0]) {
                    $sql = "INSERT INTO `pax` (`cash`, `destination`, `location`, `optimal`, `origin`, `status`) VALUES ($cash, '$destination', '$origin', $opt, '$origin', 'PORT')";
                    $this->DbQuery($sql);
                    unset($pax[$k]);
                    $startingPax[] = $x;
                    break;
                }
            }
        }

        // Create queued passengers in each hour
        $paxCounts = N_REF_HOUR_PAX[$playerCount];
        foreach ($paxCounts as $status => $count) {
            $hourPax = array_splice($pax, $count * -1);
            foreach ($hourPax as $x) {
                [$origin, $destination, $cash] = $x;
                $opt = $optimal[$origin][$destination];
                $sql = "INSERT INTO `pax` (`cash`, `destination`, `optimal`, `origin`, `status`) VALUES ($cash, '$destination', $opt, '$origin', '$status')";
                $this->DbQuery($sql);
            }
        }
    }

    private function addPax(array $hourInfo)
    {
        $pax = $this->getPaxByStatus($hourInfo['hour'], $hourInfo['draw']);
        if (empty($pax)) {
            throw new BgaVisibleSystemException("addPax: No passengers to add [???]");
        }

        foreach ($pax as $x) {
            $x->status = 'SECRET';
            $x->location = $x->origin;
            $this->DbQuery("UPDATE `pax` SET `location` = '{$x->location}', `status` = 'SECRET' WHERE `pax_id` = {$x->id}");
        }

        $this->notifyAllPlayers('pax', N_REF_MSG['addPax'], [
            'count' => count($pax),
            'location' => $this->getPaxLocations($pax),
            'pax' => array_values($this->filterPax($pax)),
        ]);
    }

    private function filterPax(array $pax): array
    {
        foreach ($pax as $x) {
            if ($x->status == 'SECRET') {
                // Don't display cash or destination
                $x->cash = 0;
                $x->destination = null;
            }
        }
        // VIP Double is never included (managed by client)
        return array_filter($pax, function ($x) {
            return $x->id > 0;
        });
    }

    private function getPaxLocations(array $pax): string
    {
        $uniq = [];
        foreach ($pax as $x) {
            if ($x->location) {
                $uniq[$x->location] = true;
            }
        }
        $locations = array_keys($uniq);
        sort($locations);
        return join(', ', $locations);
    }

    private function angerPax(): void
    {
        $pax = $this->getPaxByStatus(['SECRET', 'PORT']);
        if (!empty($pax)) {
            // VIP Baby
            $babyLocations = $this->getObjectListFromDB("SELECT DISTINCT `location` FROM `pax` WHERE `status` = 'PORT' AND `vip` = 'BABY'", true);
            $angerPax = [];
            $complaintPax = [];
            foreach ($pax as $x) {
                if ($x->vip == 'CREW') {
                    // VIP Crew
                    // Never angry
                    continue;
                }
                $increase = $x->vip != 'BABY' && in_array($x->location, $babyLocations) ? 2 : 1;
                $x->anger += $increase;
                if ($x->anger < 4) {
                    // Increase anger
                    $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger} WHERE `pax_id` = {$x->id}");
                    $angerPax[] = $x;
                } else {
                    if ($x->id > 0) {
                        // File complaint
                        $x->status = 'COMPLAINT';
                        $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `status` = 'COMPLAINT' WHERE `pax_id` = {$x->id}");
                        $complaintPax[] = $x;
                    } else {
                        // VIP Double
                        // Delete the fugitive
                        $this->DbQuery("DELETE FROM `pax` WHERE `pax_id` = {$x->id}");
                    }
                }
            }
            $this->notifyAllPlayers('pax', '', [
                'pax' => array_values($pax),
            ]);
            if (!empty($angerPax)) {
                $this->notifyAllPlayers('message', N_REF_MSG['anger'], [
                    'count' => count($angerPax),
                ]);
            }
            if (!empty($complaintPax)) {
                $count = count($complaintPax);
                $total = $this->countComplaint();
                $this->notifyAllPlayers('complaint', N_REF_MSG['complaintPort'], [
                    'complaint' => $count,
                    'location' => $this->getPaxLocations($complaintPax),
                    'total' => $total,
                ]);
                if ($total >= 3) {
                    throw new NGameOverException();
                }
            }
        }
    }

    private function angerVips(string $hour): void
    {
        // File complaint for every unserved VIP
        $vips = $this->getVarArray("vip$hour");
        $count = count($vips);
        if ($count > 0) {
            // Erase missed VIPs
            $this->setVar("vip$hour", null);
            $this->notifyAllPlayers('vip', '', [
                'overall' => $this->getVipInfo()['overall']
            ]);

            // Notify complaints
            $this->incStat($count, 'complaintVip');
            $total = $this->countComplaint();
            $this->notifyAllPlayers('complaint', N_REF_MSG['complaintVip'], [
                'i18n' => ['hourDesc'],
                'complaint' => $count,
                'hourDesc' => N_REF_HOUR[$hour]['desc'],
                'total' => $total,
            ]);
            if ($total >= 3) {
                throw new NGameOverException();
            }
        }
    }

    private function endGame(): void
    {
        // Calculate final statistics
        $planes = $this->getPlanesByIds();
        $playerCount = count($planes);
        $alliances = 0;
        $seat = 0;
        $speed = 0;
        $tempSeat = 0;
        $tempSpeed = 0;
        foreach ($planes as $plane) {
            $alliances += count($plane->alliances);
            $seat += $plane->seat;
            $speed += $plane->speed;
            $tempSeat += intval($this->getStat('tempSeat', $plane->id));
            $tempSpeed += intval($this->getStat('tempSpeed', $plane->id));
        }
        $this->setStat($alliances / $playerCount, 'alliances');
        $this->setStat($seat / $playerCount, 'seat');
        $this->setStat($speed / $playerCount, 'speed');
        $this->setStat($tempSeat, 'tempSeat');
        $this->setStat($tempSpeed, 'tempSpeed');

        $complaintPort = $this->countPaxByStatus('COMPLAINT');
        $journeyAvg = intval($this->getUniqueValueFromDB("SELECT AVG(`moves`) FROM `pax` WHERE `status` IN ('CASH', 'PAID')"));
        $journeyMax = intval($this->getUniqueValueFromDB("SELECT MAX(`moves`) FROM `pax` WHERE `status` IN ('CASH', 'PAID')"));
        $efficiencyAvg = floatval($this->getUniqueValueFromDB("SELECT ROUND(SUM(`optimal`)/SUM(`moves`) * 100, 2) FROM `pax` WHERE `status` IN ('CASH', 'PAID')"));
        $efficiencyMin = floatval($this->getUniqueValueFromDB("SELECT MIN(ROUND(`optimal`/`moves` * 100, 2)) FROM `pax` WHERE `status` IN ('CASH', 'PAID')"));
        $this->setStat($complaintPort, 'complaintPort');
        $this->setStat($journeyAvg, 'journeyAvg');
        $this->setStat($journeyMax, 'journeyMax');
        $this->setStat($efficiencyAvg, 'efficiencyAvg');
        $this->setStat($efficiencyMin, 'efficiencyMin');

        // Calculate final score
        $complaint = $this->countComplaint();
        if ($complaint >= 3) {
            $this->DbQuery("UPDATE `player` SET `player_score` = 0");
            $this->notifyAllPlayers('message', N_REF_MSG['endLose'], [
                'complaint' => $complaint,
            ]);
        } else {
            $score = $playerCount * $playerCount + 10;
            if ($this->getGlobal(N_OPTION_TIMER) != 1) {
                // Half score with double/unlimited timer
                $score = ceil($score / 2);
            }
            $this->DbQuery("UPDATE `player` SET `player_score` = $score");
            $this->notifyAllPlayers('message', N_REF_MSG['endWin'], []);
        }

        // Really end the game
        $this->gamestate->nextState('end');
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utilities
    ////////////

    private function generatePermutations(array $array): Generator
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

    public function zombieTurn($state, $playerId)
    {
        $stateName = $state['name'];
        self::debug("zombieTurn state name $stateName // ");
        if ($stateName == 'build' || $stateName == 'prepare') {
            $this->applyUndo($playerId);
        }
        $plane = $this->getPlaneById($playerId);

        // Surrender temporary purchases
        if ($plane->tempSeat) {
            $this->useTempSeat($plane);
        }
        if ($plane->tempSpeed) {
            $this->useTempSpeed($plane);
        }
        if ($plane->tempSeat || $plane->tempSpeed) {
            $plane->tempSeat = false;
            $plane->tempSpeed = false;
            $this->notifyAllPlayers('planes', '', [
                'planes' => [$plane],
            ]);
        }

        // Surrender passengers
        $pax = $this->getPaxByStatus('SEAT', null, $playerId);
        if (!empty($pax)) {
            foreach ($pax as &$x) {
                $x->resetAnger();
                $x->playerId = null;
                $x->status = 'PORT';
                $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `player_id` = NULL, `status` = '{$x->status}' WHERE `pax_id` = {$x->id}");
                if ($x->id > 0) {
                    $this->notifyAllPlayers('message', N_REF_MSG['deplane'], [
                        'location' => $x->location,
                        'player_id' => $plane->id,
                        'player_name' => $plane->name,
                    ]);
                }
            }
            $this->notifyAllPlayers('pax', '', [
                'pax' => array_values($this->filterPax($pax)),
            ]);
        }

        // End turn
        $next = $stateName == 'prepare'  ? 'reveal' : 'maintenance';
        $this->gamestate->setPlayerNonMultiactive($playerId, $next);
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    public function upgradeTableDb($fromVersion)
    {
        $changes = [
            [2307141730, "ALTER TABLE `DBPREFIX_player` ADD COLUMN `snooze` TINYINT(1) NOT NULL DEFAULT '0'"],
            [2307150617, "ALTER TABLE `DBPREFIX_pax` ADD COLUMN `moves` INT(3) NOT NULL DEFAULT '0'"],
            [2307150617, "ALTER TABLE `DBPREFIX_pax_undo` ADD COLUMN `moves` INT(3) NOT NULL DEFAULT '0'"],
            [2307150617, "ALTER TABLE `DBPREFIX_pax` ADD COLUMN `optimal` INT(3) NOT NULL DEFAULT '1'"],
            [2307150617, "ALTER TABLE `DBPREFIX_pax_undo` ADD COLUMN `optimal` INT(3) NOT NULL DEFAULT '1'"],
            [2307191554, "ALTER TABLE `DBPREFIX_plane` ADD COLUMN `speed_penalty` TINYINT(1) NOT NULL DEFAULT '0'"],
            [2307191554, "ALTER TABLE `DBPREFIX_plane_undo` ADD COLUMN `speed_penalty` TINYINT(1) NOT NULL DEFAULT '0'"],
            [2307191554, "DELETE FROM `DBPREFIX_weather`"],
            [2307191554, "ALTER TABLE `DBPREFIX_weather` ADD COLUMN `hour` VARCHAR(50) NOT NULL"],
            [2307222151, "ALTER TABLE `DBPREFIX_weather` DROP PRIMARY KEY, ADD PRIMARY KEY(`hour`, `location`)"],
            [2307222151, "INSERT INTO `DBPREFIX_weather` (`hour`, `location`, `token`) SELECT 'FINALE', `location`, `token` FROM `DBPREFIX_weather` WHERE `hour` = 'NIGHT'"],
            [2309031701, "DELETE FROM `DBPREFIX_pax` WHERE `pax_id` < 0"],
            [2309031701, "DELETE FROM `DBPREFIX_pax_undo` WHERE `pax_id` < 0"],
            [2309031701, "INSERT INTO `DBPREFIX_pax` (`pax_id`, `anger`, `cash`, `destination`, `location`, `moves`, `optimal`, `origin`, `player_id`, `status`, `vip`) SELECT `pax_id` * -1 AS `pax_id`, `anger`, 0 AS `cash`, `destination`, `location`, `moves`, `optimal`, `origin`, `player_id`, `status`, `vip` FROM `DBPREFIX_pax` WHERE `vip` = 'DOUBLE'"],
            [2309031701, "INSERT INTO `DBPREFIX_pax_undo` (`pax_id`, `anger`, `cash`, `destination`, `location`, `moves`, `optimal`, `origin`, `player_id`, `status`, `vip`) SELECT `pax_id` * -1 AS `pax_id`, `anger`, 0 AS `cash`, `destination`, `location`, `moves`, `optimal`, `origin`, `player_id`, `status`, `vip` FROM `DBPREFIX_pax_undo` WHERE `vip` = 'DOUBLE'"],
            [2309100719, "DELETE FROM `DBPREFIX_global` WHERE `global_id` = 101"],
            [2309100719, "UPDATE `DBPREFIX_global` SET `global_id` = 101 WHERE `global_id` = 112"],
        ];

        foreach ($changes as [$version, $sql]) {
            if ($fromVersion <= $version) {
                self::warn("upgradeTableDb: fromVersion=$fromVersion, change=[ $version, $sql ]");
                self::applyDbUpgradeToAllDB($sql);
            }
        }

        if ($fromVersion <= 2307191554) {
            self::warn("upgradeTableDb: fromVersion=$fromVersion, setupWeather");
            $this->setupWeather($this->getPlayersNumber());
        }

        // Add 1 minute to all clocks
        $this->giveExtraTimeAll(60);
        self::applyDbUpgradeToAllDB("UPDATE `DBPREFIX_var` SET `value` = `value` + 60 WHERE `key` = 'endTime'");

        self::warn("upgradeTableDb complete: fromVersion=$fromVersion");
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// Production bug report handler
    //////////

    public function loadBug($reportId): void
    {
        $db = explode('_', self::getUniqueValueFromDB("SELECT SUBSTRING_INDEX(DATABASE(), '_', -2)"));
        $game = $db[0];
        $tableId = $db[1];
        self::notifyAllPlayers('loadBug', "Trying to load <a href='https://boardgamearena.com/bug?id=$reportId' target='_blank'>bug report $reportId</a>", [
            'urls' => [
                "https://studio.boardgamearena.com/admin/studio/getSavedGameStateFromProduction.html?game=$game&report_id=$reportId&table_id=$tableId",
                "https://studio.boardgamearena.com/table/table/loadSaveState.html?table=$tableId&state=1",
                "https://studio.boardgamearena.com/1/$game/$game/loadBugSQL.html?table=$tableId&report_id=$reportId",
                "https://studio.boardgamearena.com/admin/studio/clearGameserverPhpCache.html?game=$game",
            ]
        ]);
    }

    public function loadBugSQL($reportId): void
    {
        $studioPlayer = self::getCurrentPlayerId();
        $playerIds = self::getObjectListFromDb("SELECT player_id FROM player", true);

        $sql = [
            "UPDATE global SET global_value=2 WHERE global_id=1 AND global_value=99"
        ];
        foreach ($playerIds as $pId) {
            $sql[] = "UPDATE global SET global_value=$studioPlayer WHERE global_value=$pId";
            $sql[] = "UPDATE pax SET player_id=$studioPlayer WHERE player_id=$pId";
            $sql[] = "UPDATE pax_undo SET player_id=$studioPlayer WHERE player_id=$pId";
            $sql[] = "UPDATE plane SET player_id=$studioPlayer WHERE player_id=$pId";
            $sql[] = "UPDATE plane_undo SET player_id=$studioPlayer WHERE player_id=$pId";
            $sql[] = "UPDATE player SET player_id=$studioPlayer WHERE player_id=$pId";
            $sql[] = "UPDATE stats SET stats_player_id=$studioPlayer WHERE stats_player_id=$pId";
            $sql[] = "UPDATE stats_undo SET stats_player_id=$studioPlayer WHERE stats_player_id=$pId";
            $studioPlayer++;
        }
        $msg = "<b>Loaded <a href='https://boardgamearena.com/bug?id=$reportId' target='_blank'>bug report $reportId</a></b><hr><ul><li>" . implode(';</li><li>', $sql) . ';</li></ul>';
        self::warn($msg);
        self::notifyAllPlayers('message', $msg, []);

        foreach ($sql as $q) {
            self::DbQuery($q);
        }
        self::reloadPlayersBasicInfos();
    }
}
