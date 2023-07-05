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
    public $hourDesc;
    public $exMsg;
    public $msg;

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

            $sql = "INSERT INTO `plane` (`player_id`) VALUES ($player_id)";
            $this->DbQuery($sql);
        }
        $this->reloadPlayersBasicInfos();

        $this->setVar('hour', 'PREFLIGHT');
        $this->createUndo();
    }

    function checkVersion(int $clientVersion): void
    {
        $gameVersion = $this->gamestate->table_globals[N_OPTION_VERSION];
        if ($clientVersion != $gameVersion) {
            throw new BgaVisibleSystemException($this->exMsg['version']);
        }
    }

    protected function getAllDatas(): array
    {
        $players = $this->getCollectionFromDb("SELECT player_id id, player_score score FROM player");
        return [
            'complaint' => $this->countPaxByStatus('COMPLAINT'),
            'hour' => $this->getHourInfo(),
            'map' => $this->getMap(),
            'pax' => $this->filterPax($this->getPaxByStatus(['SECRET', 'PORT', 'SEAT'])),
            'planes' => $this->getPlanesByIds(),
            'players' => $players,
            'version' => intval($this->gamestate->table_globals[N_OPTION_VERSION]),
        ];
    }

    function getGameProgression(): int
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
    function stInitPrivate()
    {
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->initializePrivateStateForAllActivePlayers();
    }

    /*
     * SETUP #1
     * Each player chooses a starting airport and alliance
     */
    function argBuildAlliance(int $playerId): array
    {
        $buys = [];
        $claimed = $this->getObjectListFromDB("SELECT `alliances` FROM `plane` WHERE `alliances` IS NOT NULL AND `player_id` != $playerId", true);
        $playerCount = $this->getPlayersNumber();
        if ($playerCount <= 3) {
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
     * SETUP #2
     * Each player chooses a second alliance (2-player only)
     */
    function argBuildAlliance2(int $playerId): array
    {
        $buys = [];
        $claimed = $this->getObjectListFromDB("SELECT `alliances` FROM `plane` WHERE `player_id` = $playerId", true);
        // Exclude Seattle for 2-3 players
        $claimed[] = 'SEA';
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
    function argBuildUpgrade(int $playerId): array
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

    function stMaintenance(): void
    {
        $hourInfo = $this->getHourInfo(true);
        if ($hourInfo['hour'] == 'FINALE') {
            // End final round
            $this->endGame();
        } else {
            if ($hourInfo['hour'] == 'PREFLIGHT') {
                // Create pax on the first turn
                $this->createPax();
                $pax = $this->getPaxByStatus('PORT');
                $this->notifyAllPlayers('pax', $this->msg['addPax'], [
                    'count' => count($pax),
                    'pax' => array_values($pax),
                    'location' => $this->getPaxLocations($pax),
                ]);
            } else {
                // Add anger/complaints on subsequent turns
                $this->angerPax();
            }

            // Advance the hour
            $hourInfo = $this->advanceHour($hourInfo);
            if ($hourInfo['hour'] != 'FINALE') {
                if ($hourInfo['round'] == 1) {
                    $this->addWeather();
                }
                $this->addPax($hourInfo);
            }

            $this->gamestate->nextState('prepare');
        }
    }

    /*
     * PREPARE
     * Reset planes
     * Add passengers
     * Players purchase upgrades
     */
    function stPrepare()
    {
        $this->giveExtraTimeAll();
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->initializePrivateStateForAllActivePlayers();
        $this->DbQuery("UPDATE `plane` SET `speed_remain` = `speed`");
        $this->createUndo();
        $planes = $this->getPlanesByIds();
        $this->notifyAllPlayers('planes', '!!! stPrepare !!!', [
            'planes' => array_values($planes)
        ]);
    }

    function argPreparePrivate(int $playerId): array
    {
        $plane = $this->getPlaneById($playerId);
        $cash = $plane->getCashRemain();
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
        if (!$plane->tempSeat && $this->getOwnerName("`temp_seat` = 1") == null) {
            $buys[] = [
                'type' => 'TEMP_SEAT',
                'cost' => $cost,
                'enabled' => $cash >= $cost,
            ];
        }

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
        if (!$plane->tempSpeed && $this->getOwnerName("`temp_speed` = 1") == null) {
            $buys[] = [
                'type' => 'TEMP_SPEED',
                'cost' => $cost,
                'enabled' => $cash >= $cost,
            ];
        }

        // Alliances
        $cost = 7;
        $claimed = $plane->alliances;
        $playerCount = $this->getPlayersNumber();
        if ($playerCount <= 3) {
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
            'undo' => $plane->debt > 0,
        ];

        if ($plane->debt > 0) {
            // Did we overpay?
            $wallet = $this->getPaxWallet($plane->id);
            self::debug("wallet for player {$plane->id} : " . json_encode($wallet) . " // ");
            $args['wallet'] = $wallet;
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

    function argPreparePay(int $playerId): array
    {
        $plane = $this->getPlaneById($playerId);
        $wallet = $this->getPaxWallet($plane->id);

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
            self::debug("found suggestion " . join(',', $thisSuggestion) . " with overpay=$thisOverpay // ");
            if ($suggestion == null || $thisOverpay < $overpay || $thisOverpay == $overpay && count($thisSuggestion) < count($suggestion)) {
                self::debug("it's the best! // ");
                $suggestion = $thisSuggestion;
                $overpay = $thisOverpay;
            }
            if ($overpay == 0) {
                break;
            }
        }

        return [
            'debt' => $plane->debt,
            'wallet' => $wallet,
            'suggestion' => $suggestion,
            'overpay' => $overpay,
        ];
    }

    /*
     * FLIGHT
     * Reveal passengers
     * Start the clock
     * Players transport passengers
     */

    function stReveal(): void
    {
        // Remove undo info
        $this->eraseUndo();

        // Play the sound!
        $this->notifyAllPlayers('sound', '', [
            'sound' => 'chime',
        ]);

        // Revael passengers
        $pax = $this->getPaxByStatus('SECRET');
        foreach ($pax as $x) {
            $x->status = 'PORT';
            $this->DbQuery("UPDATE `pax` SET `status` = 'PORT' WHERE `pax_id` = {$x->id}");
        }
        $this->notifyAllPlayers('pax', '', [
            'pax' => array_values($pax),
        ]);

        // Reset thinking time
        $this->giveExtraTimeAll(120, true);
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
            'speedRemain' => max(0, $plane->speedRemain),
        ];
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Actions (ajax)
    ////////////

    function undo(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $this->DbQuery("REPLACE INTO `plane` SELECT * FROM `plane_undo` WHERE `player_id` = $playerId");
        $this->DbQuery("REPLACE INTO `pax` SELECT * FROM `pax_undo` WHERE `player_id` = $playerId");

        $plane = $this->getPlaneById($playerId);
        $this->notifyAllPlayers('planes', $this->msg['undo'], [
            'planes' => [$plane],
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
        $owner = $this->getOwnerName("`alliances` = '$alliance'");
        if ($owner != null) {
            throw new BgaUserException(sprintf($this->exMsg['allianceOwner'], $owner, $alliance));
        }

        $color = N_REF_ALLIANCE_COLOR[$alliance];
        $plane->alliances = [$alliance];
        $plane->location = $alliance;
        $this->DbQuery("UPDATE `plane` SET `alliances` = '{$plane->getAlliancesSql()}', `location` = '{$plane->location}' WHERE `player_id` = {$plane->id}");
        $this->DbQuery("UPDATE `player` SET `player_color` = '$color' WHERE `player_id` = {$plane->id}");
        $this->reloadPlayersBasicInfos();

        $this->notifyAllPlayers('buildPrimary', $this->msg['alliance'], [
            'alliance' => $alliance,
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
        $isBuild = $this->gamestate->state()['name'] == 'build';
        if (in_array($alliance, $plane->alliances)) {
            throw new BgaUserException(sprintf($this->exMsg['allianceDuplicate'], $alliance));
        }
        $cost = $isBuild ? 0 : 7;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->exMsg['noCash'], "\${$cost}", "\${$cash}"));
        }

        $plane->debt += $cost;
        $plane->alliances[] = $alliance;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `alliances` = '{$plane->getAlliancesSql()}' WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('planes', $this->msg['alliance'], [
            'alliance' => $alliance,
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $this->gamestate->nextPrivateState($plane->id, $isBuild ? 'buildUpgrade' : 'preparePrivate');
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
            throw new BgaUserException(sprintf($this->exMsg['noCash'], "\${$cost}", "\${$cash}"));
        }

        $plane->debt += $cost;
        $plane->seat++;
        $plane->seatRemain++;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `seat` = {$plane->seat} WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('planes', $this->msg['seat'], [
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'seat' => $plane->seat,
        ]);

        if ($isBuild) {
            $this->gamestate->setPlayerNonMultiactive($plane->id, 'maintenance');
        } else {
            $this->gamestate->nextPrivateState($plane->id, 'preparePrivate');
        }
    }

    private function buyTempSeat(NPlane $plane): void
    {
        $owner = $this->getOwnerName("`temp_seat` = 1");
        if ($owner != null) {
            throw new BgaUserException(sprintf($this->exMsg['tempOwner'], $owner, $this->_('Temporary Seat')));
        }
        $cost = 2;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->exMsg['noCash'], "\${$cost}", "\${$cash}"));
        }

        $plane->debt += $cost;
        $plane->tempSeat = true;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `temp_seat` = 1 WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('planes', $this->msg['temp'], [
            'i18n' => ['temp'],
            'preserve' => ['tempIcon'],
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'temp' => clienttranslate('Temporary Seat'),
            'tempIcon' => 'speed',
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'preparePrivate');
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
            throw new BgaUserException(sprintf($this->exMsg['noCash'], "\${$cost}", "\${$cash}"));
        }

        $plane->debt += $cost;
        $plane->speed++;
        $plane->speedRemain = $plane->speed;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `speed` = {$plane->speed}, `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('planes', $this->msg['speed'], [
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'speed' => $plane->speed,
        ]);


        if ($isBuild) {
            $this->gamestate->setPlayerNonMultiactive($plane->id, 'maintenance');
        } else {
            $this->gamestate->nextPrivateState($plane->id, 'preparePrivate');
        }
    }

    private function buyTempSpeed(NPlane $plane): void
    {
        $owner = $this->getOwnerName("`temp_speed` = 1");
        if ($owner != null) {
            throw new BgaUserException(sprintf($this->exMsg['tempOwner'], $owner, $this->_('Temporary Speed')));
        }
        $cost = 1;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->exMsg['noCash'], "\${$cost}", "\${$cash}"));
        }

        $plane->debt += $cost;
        $plane->tempSpeed = true;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `temp_speed` = 1 WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('planes', $this->msg['temp'], [
            'i18n' => ['temp'],
            'preserve' => ['tempIcon'],
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'temp' => clienttranslate('Temporary Speed'),
            'tempIcon' => 'speed',
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'preparePrivate');
    }

    function pay($paxIds): void
    {
        $playerId = $this->getCurrentPlayerId();
        $wallet = $this->getPaxWallet($playerId);
        $total = 0;
        $validIds = [];
        foreach ($wallet as $paxId => $cash) {
            if (in_array($paxId, $paxIds)) {
                $total += $cash;
                $validIds[] = $paxId;
            }
        }

        $plane = $this->getPlaneById($playerId);
        if ($total < $plane->debt) {
            throw new BgaUserException(sprintf($this->exMsg['noPay'], "\${$plane->debt}"));
        }

        // Payment
        $this->DbQuery("UPDATE `pax` SET `status` = 'PAID' WHERE `pax_id` IN (" . join(',', $validIds) . ")");
        $this->DbQuery("UPDATE `plane` SET `debt` = 0 WHERE `player_id` = $playerId");

        // Update plane gauges UI (e.g., overpayment)
        $plane = $this->getPlaneById($playerId);
        $this->notifyAllPlayers('planes', '', [
            'planes' => [$plane],
        ]);

        $this->gamestate->setPlayerNonMultiactive($playerId, 'reveal');
    }


    function prepareDone(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        if ($plane->debt > 0) {
            $this->gamestate->nextPrivateState($plane->id, 'preparePay');
        } else {
            $this->gamestate->setPlayerNonMultiactive($playerId, 'reveal');
        }
    }

    function flyDone(): void
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
            throw new BgaVisibleSystemException("move: $plane cannot reach $location [???]");
        }

        $move = $possible[$location];
        $plane->origin = $move->getOrigin();
        $plane->location = $location;
        $plane->speedRemain -= $move->fuel;
        if ($plane->speedRemain == -1 && $plane->tempSpeed) {
            $plane->tempSpeed = false;
            $this->DbQuery("UPDATE `plane` SET `temp_speed` = NULL WHERE `player_id` = {$plane->id}");
            $this->notifyAllPlayers('message', $this->msg['tempUsed'], [
                'i18n' => ['temp'],
                'preserve' => ['tempIcon'],
                'player_id' => $plane->id,
                'player_name' => $plane->name,
                'temp' => clienttranslate('Temporary Speed'),
                'tempIcon' => 'speed',
            ]);
        } else if ($plane->speedRemain < 0) {
            throw new BgaVisibleSystemException("move: $plane not enough fuel to reach $location with speedRemain={$plane->speedRemain}, tempSpeed={$plane->getTempSpeedSql()} [???]");
        }
        $this->DbQuery("UPDATE `plane` SET `location` = '{$plane->location}', `origin` = '{$plane->origin}', `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");

        $this->notifyAllPlayers('move', $this->msg['move'], [
            'location' => $plane->location,
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);



        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    function board(int $paxId): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        $x = $this->getPaxById($paxId, true);
        $planeIds = [$playerId];

        if ($x->status == 'SEAT') {
            if ($x->playerId == $playerId) {
                throw new BgaVisibleSystemException("board: $x player ID is already $playerId [???]");
            }
            // Transfer from another plane
            // Must be together at an airport
            $other = $this->getPlaneById($x->playerId);
            if ($other->location != $plane->location || strlen($plane->location) != 3) {
                throw new BgaUserException(sprintf($this->exMsg['boardTransfer'], $other->name));
            }
            // Implicit deplane
            $planeIds[] = $other->id;
            $this->notifyAllPlayers('message', $this->msg['deplane'], [
                'location' => $other->location,
                'player_id' => $other->id,
                'player_name' => $other->name,
                'route' => "{$x->origin}-{$x->destination}",
            ]);
            if ($other->location != $plane->location) {
                // Erase anger if deplaned at a new location
                $x->anger = 0;
            }
        } else if ($x->status == 'PORT') {
            // Pickup from airport
            if (strlen($x->location) != 3) {
                throw new BgaVisibleSystemException("board: $x location is not at an airport [???]");
            }
            if ($x->location != $plane->location) {
                throw new BgaUserException(sprintf($this->exMsg['boardPort'], $x->location));
            }
        } else {
            throw new BgaVisibleSystemException("board: $x status is invalid [???]");
        }

        if ($plane->seatRemain <= 0) {
            if (!$plane->tempSeat) {
                throw new BgaUserException($this->exMsg['noSeat']);
            }
            $plane->tempSeat = false;
            $this->DbQuery("UPDATE `plane` SET `temp_seat` = NULL WHERE `player_id` = {$plane->id}");
            $this->notifyAllPlayers('planes', $this->msg['tempUsed'], [
                'i18n' => ['temp'],
                'preserve' => ['tempIcon'],
                'planes' => [$plane],
                'player_id' => $plane->id,
                'player_name' => $plane->name,
                'temp' => clienttranslate('Temporary Seat'),
                'tempIcon' => 'seat',
            ]);
        }

        // Note: We preserve anger until deplane
        $x->playerId = $plane->id;
        $x->status = 'SEAT';
        $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `player_id` = {$x->playerId}, `status` = '{$x->status}' WHERE `pax_id` = {$x->id}");

        // Update UI empty seats
        $planes = $this->getPlanesByIds($planeIds);
        $this->notifyAllPlayers('planes', '', [
            'planes' => array_values($planes)
        ]);

        $this->notifyAllPlayers('pax', $this->msg['board'], [
            'location' => $x->location,
            'pax' => [$x],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'route' => "{$x->origin}-{$x->destination}",
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    function deplane(int $paxId, bool $confirm = false): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        $x = $this->getPaxById($paxId, true);
        if ($x->status != 'SEAT') {
            throw new BgaVisibleSystemException("deplane: $x status is invalid [???]");
        }
        if (strlen($plane->location) != 3) {
            throw new BgaUserException($this->exMsg['deplanePort']);
        }

        if ($x->location == $plane->location) {
            // Preserve anger if deplaned at prior location
            if ($x->anger > 0 && !$confirm) {
                throw new BgaUserException("!!!deplaneConfirm");
            }
        } else {
            // Erase anger if deplaned at a new location
            $x->anger = 0;
        }

        $x->location = $plane->location;
        $args = [
            'pax' => [$x],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'route' => "{$x->origin}-{$x->destination}",
        ];
        if ($x->destination == $plane->location) {
            $x->status = 'CASH';
            $msg = $this->msg['deplaneDeliver'];
            $args['cash'] = "\${$x->cash}";
        } else {
            $x->playerId = null;
            $x->status = 'PORT';
            $msg = $this->msg['deplane'];
            $args['location'] = $x->location;
        }
        $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `location` = '{$x->location}', `player_id` = {$x->getPlayerIdSql()}, `status` = '{$x->status}' WHERE `pax_id` = {$x->id}");

        // Update UI empty seats
        self::debug("Refresh plane for $playerId // ");
        $planes = $this->getPlanesByIds([$playerId]);
        $this->notifyAllPlayers('planes', '', [
            'planes' => array_values($planes)
        ]);

        $this->notifyAllPlayers('pax', $msg, $args);

        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Helpers
    ////////////

    function getVar(string $key): ?string
    {
        return $this->getUniqueValueFromDB("SELECT `value` FROM `var` WHERE `key` = '$key'");
    }

    function setVar(string $key, string $value): void
    {
        $this->DbQuery("INSERT INTO `var` (`key`, `value`) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE `value` = '$value'");
    }

    function getHourInfo(bool $beforeAddPax = false): array
    {
        $playerCount = $this->getPlayersNumber();
        $hour = $this->getVar('hour');
        $hourInfo = [
            'hour' => $hour,
            'hourDesc' => $this->hourDesc[$hour],
        ];
        if ($hour == 'MORNING' || $hour == 'NOON' || $hour == 'NIGHT') {
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
        }
        return $hourInfo;
    }

    function advanceHour(array $hourInfo): array
    {
        $advance = $hourInfo['hour'] == 'PREFLIGHT' || $hourInfo['round'] > $hourInfo['total'];
        if ($advance) {
            $nextHour = N_REF_HOUR_NEXT[$hourInfo['hour']];
            $this->setVar('hour', $nextHour);
            $hourInfo = $this->getHourInfo(true);
        }

        // Notify hour
        $hourInfo['i18n'] = ['hourDesc'];
        $msg = $hourInfo['hour'] == 'FINALE' ? $this->msg['hourFinale'] : $this->msg['hour'];
        $this->notifyAllPlayers('hour', $msg, $hourInfo);
        return $hourInfo;
    }

    function createUndo(): void
    {
        $this->DbQuery("INSERT INTO `plane_undo` SELECT * FROM `plane`");
        $this->DbQuery("INSERT INTO `pax_undo` SELECT * FROM `pax` WHERE `status` = 'CASH'");
    }

    function eraseUndo(): void
    {
        $this->DbQuery("DELETE FROM `plane_undo`");
        $this->DbQuery("DELETE FROM `pax_undo`");
    }

    function getPlayerIds(): array
    {
        return $this->getObjectListFromDB("SELECT `player_id` FROM `player`", true);
    }

    function giveExtraTimeAll(?int $seconds = null, ?bool $reset = false)
    {
        if ($reset && !$this->isAsync()) {
            $this->DbQuery("UPDATE `player` SET `player_remaining_reflexion_time` = 0");
        }
        foreach ($this->getPlayerIds() as $playerId) {
            $this->giveExtraTime($playerId, $seconds);
        }
    }

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

    function getPaxById(int $paxId, bool $lock = false): NPax
    {
        return $this->getPaxByIds([$paxId], $lock)[$paxId];
    }

    function getPaxByIds(array $ids = [], bool $lock = false): array
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

    function getPaxByStatus($status, ?int $limit = null): array
    {
        if (is_array($status)) {
            $status = join("', '", $status);
        }
        $sql = "SELECT * FROM `pax` WHERE `status` IN ('$status') ORDER BY `pax_id`";
        if ($limit != null) {
            $sql .= " LIMIT $limit";
        }
        return array_map(function ($dbrow) {
            return new NPax($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    function countPaxByStatus($status = null): int
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

    function getPaxWallet(int $playerId): array
    {
        $sql = "SELECT `pax_id`, `cash` FROM `pax` WHERE `status` = 'CASH' AND `player_id` = $playerId ORDER BY `pax_id`";
        return $this->getCollectionFromDB($sql, true);
    }

    function createPax(): void
    {
        $planes = $this->getPlanesByIds();
        $playerCount = count($planes);
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
            array_push(
                $pax,
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
                ['SFO', 'JFK', 4]
            );
        }
        if ($playerCount >= 4) {
            // Include SEA with 4+ players
            array_push(
                $pax,
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
                ['SFO', 'SEA', 2]
            );
        }
        shuffle($pax);

        // Create starting passenger in each airport
        foreach ($planes as $plane) {
            foreach ($pax as $k => $x) {
                [$destination, $origin, $cash] = $x;
                if ($origin == $plane->alliances[0]) {
                    $sql = "INSERT INTO pax (`status`,`cash`, `destination`, `location`, `origin`) VALUES ('PORT', $cash, '$destination', '$origin', '$origin')";
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
                [$destination, $origin, $cash] = $x;
                $sql = "INSERT INTO pax (`status`, `cash`, `destination`, `origin`) VALUES ('$status', $cash, '$destination', '$origin')";
                $this->DbQuery($sql);
            }
        }
    }

    function addPax(array $hourInfo)
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

        $this->notifyAllPlayers('pax', $this->msg['addPax'], [
            'count' => count($pax),
            'location' => $this->getPaxLocations($pax),
            'pax' => array_values($this->filterPax($pax)),
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

    function getPaxLocations(array $pax): string
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

    function angerPax(): void
    {
        $pax = $this->getPaxByStatus('PORT');
        if (!empty($pax)) {
            $anger = [];
            $complaint = [];
            foreach ($pax as $x) {
                $x->anger++;
                if ($x->anger < 4) {
                    // Increase anger
                    $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger} WHERE `pax_id` = {$x->id}");
                    $anger[] = $x;
                } else {
                    // File complaint
                    $x->status = 'COMPLAINT';
                    $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `status` = 'COMPLAINT' WHERE `pax_id` = {$x->id}");
                    $complaint[] = $x;
                }
            }
            if (!empty($anger)) {
                $this->notifyAllPlayers('message', $this->msg['anger'], [
                    'count' => count($anger),
                ]);
            }
            if (!empty($complaint)) {
                $total = $this->countPaxByStatus('COMPLAINT');
                $this->notifyAllPlayers('complaint', $this->msg['complaint'], [
                    'complaint' => count($complaint),
                    'location' => $this->getPaxLocations($complaint),
                    'total' => $total,
                ]);
                if ($total >= 3) {
                    $this->notifyAllPlayers('message', $this->msg['endLose'], [
                        'complaint' => $total,
                    ]);
                    $this->gamestate->nextState('end');
                    return;
                }
            }
            $this->notifyAllPlayers('pax', '', [
                'pax' => array_values($pax),
            ]);
        }
    }

    function addWeather(): void
    {
        // Delete old weather
        $map = $this->getMap();
        $map->weather = [];
        $this->DbQuery("DELETE FROM `weather`");

        // Determine how much weather to add
        $playerCount = $this->getPlayersNumber();
        $tokens = ['FAST', 'SLOW', 'FAST', 'SLOW', 'FAST', 'SLOW'];
        if ($playerCount == 2) {
            array_splice($tokens, 2);
        } else if ($playerCount == 3) {
            array_splice($tokens, 4);
        }

        // Select a (different) random route for each token
        $desc = [];
        $routeIds = array_rand($map->routes, count($tokens));
        foreach ($routeIds as $routeId) {
            // Select a random node on the route
            $route = $map->routes[$routeId];
            $node = $route[array_rand($route)];
            $token = array_pop($tokens);
            $this->DbQuery("INSERT INTO weather (`location`, `token`) VALUES ('{$node->id}', '$token')");
            $map->weather[$node->id] = $token;
            $desc[$token][] = substr_replace($routeId, '-', 3, 0);
        }

        // Notify
        $this->notifyAllPlayers('weather', $this->msg['weather'], [
            'routeFast' => join(', ', $desc['FAST']),
            'routeSlow' => join(', ', $desc['SLOW']),
            'weather' => $map->weather,
        ]);
    }

    function endGame(): void
    {
        // End final round
        // File a complaint for every 2 pax
        $count = $this->countPaxByStatus(['PORT', 'SEAT']);
        $complaint = floor($count / 2);
        $this->DbQuery("UPDATE `pax` SET `status` = 'COMPLAINT' WHERE `status` IN ('PORT', 'SEAT') ORDER BY `pax_id` LIMIT $complaint");
        $total = $this->countPaxByStatus('COMPLAINT');
        if ($complaint > 0) {
            $this->notifyAllPlayers('complaint', $this->msg['complaintFinale'], [
                'complaint' => $complaint,
                'count' => $count,
                'total' => $total,
            ]);
        }

        // Determine win/lose
        if ($total >= 3) {
            $this->notifyAllPlayers('message', $this->msg['endLose'], [
                'complaint' => $total,
            ]);
        } else {
            $this->DbQuery("UPDATE `player` SET `player_score` = 1");
            $this->notifyAllPlayers('message', $this->msg['endWin'], []);
        }
        $this->gamestate->nextState('end');
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

    function zombieTurn($state, $activePlayer)
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
            $this->gamestate->setPlayerNonMultiactive($activePlayer, '');

            return;
        }

        throw new feException("Zombie mode not supported at this game state: " . $statename);
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    function upgradeTableDb($fromVersion)
    {
    }
}
