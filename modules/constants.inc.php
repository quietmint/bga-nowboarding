<?php

// States
define('N_STATE_BEGIN', 1);
define('N_STATE_BUILD', 2);
define('N_STATE_BUILD_ALLIANCE', 21);
define('N_STATE_BUILD_ALLIANCE2', 22);
define('N_STATE_BUILD_UPGRADE', 23);
define('N_STATE_MAINTENANCE', 3);
define('N_STATE_PREPARE', 4);
define('N_STATE_PREPARE_BUY', 41);
define('N_STATE_PREPARE_PAY', 42);
define('N_STATE_REVEAL', 5);
define('N_STATE_FLY', 6);
define('N_STATE_FLY_PRIVATE', 61);
define('N_STATE_END', 99);

// Globals
define('N_BGA_ELO', 201);
define('N_BGA_CLOCK', 200);
define('N_BGA_TIME_MAX', 8);
define('N_BGA_VERSION', 300);

define('N_REF_BGA_CLOCK_REALTIME', [0, 1, 2, 9]);
define('N_REF_BGA_CLOCK_UNLIMITED', [9, 20]);

// Game options
define('N_OPTION_TIMER', 100);
define('N_OPTION_VIP', 101);
define('N_OPTION_HANDOFF', 102);

// Reference lookups
define('N_REF_ALLIANCE_COLOR', [
    'ATL' => '16a34a', // green
    'DFW' => '7e22ce', // purple
    'LAX' => 'd97706', // orange
    'ORD' => 'b91c1c', // red
    'SEA' => '1d4ed8', // blue
]);

define('N_REF_HOUR', [
    'PREFLIGHT' => [
        'desc' => clienttranslate('Preflight'),
        'next' => 'MORNING',
        'prev' => null,
    ],
    'MORNING' => [
        'desc' => clienttranslate('Morning'),
        'next' => 'NOON',
        'prev' => null,
    ],
    'NOON' => [
        'desc' => clienttranslate('Afternoon'),
        'next' => 'NIGHT',
        'prev' => 'MORNING',
    ],
    'NIGHT' => [
        'desc' => clienttranslate('Evening'),
        'next' => 'FINALE',
        'prev' => 'NOON',
    ],
    'FINALE' => [
        'desc' => clienttranslate('Final Round'),
        'next' => null,
        'prev' => 'NIGHT',
    ],
]);

define('N_REF_HOUR_PAX', [
    2 => ['MORNING' => 3, 'NOON' => 10, 'NIGHT' => 27],
    3 => ['MORNING' => 6, 'NOON' => 15, 'NIGHT' => 32],
    4 => ['MORNING' => 12, 'NOON' => 20, 'NIGHT' => 35],
    5 => ['MORNING' => 12, 'NOON' => 30, 'NIGHT' => 24],
]);

define('N_REF_MSG', [
    'addPax' => clienttranslate('${count} passengers arrive at ${location}'),
    'alliance' => clienttranslate('${player_name} joins alliance ${alliance}'),
    'anger' => clienttranslate('${count} passengers in airports get angry'),
    'board' => clienttranslate('${player_name} boards a passenger at ${location}'),
    'complaintFinale' => clienttranslate('${complaint} complaints are filed by ${count} undelivered passengers'),
    'complaintPort' => clienttranslate('${complaint} complaints are filed by angry passengers at ${location}'),
    'complaintVip' => clienttranslate('${complaint} complaints are filed by VIPs not accepted during ${hourDesc}'),
    'deplane' => clienttranslate('${player_name} deplanes a passenger at ${location}'),
    'deplaneDeliver' => clienttranslate('${player_name} delivers a passenger to ${location} after ${moves} moves and earns ${cash}'),
    'endLose' => clienttranslate('Rough landing! Your airline goes out of business after receiving ${complaint} complaints!'),
    'endWin' => clienttranslate('Congratulations! Your airline is a soaring success!'),
    'hour' => clienttranslate('${hourDesc} round ${round} of ${total} begins'),
    'hourFinale' => clienttranslate('${hourDesc} begins. ${count} undelivered passengers remain.'),
    'hourVip' => clienttranslate('VIP: ${count} VIPs must be accepted during ${hourDesc}'),
    'move' => clienttranslate('${player_name} flys ${fuel} moves'),
    'movePort' => clienttranslate('${player_name} flys ${fuel} moves to ${location}'),
    'seat' => clienttranslate('${player_name} upgrades seats to ${seat}'),
    'speed' => clienttranslate('${player_name} upgrades speed to ${speed}'),
    'temp' => clienttranslate('${player_name} purchases ${temp}'),
    'tempUsed' => clienttranslate('${player_name} uses ${temp}'),
    'undo' => clienttranslate('${player_name} restarts their turn'),
    'vip' => clienttranslate('VIP: A new passenger at ${location} is ${vip} (${desc})'),
    'vipAccept' => clienttranslate('${player_name} accepts a VIP passenger this round'),
    'vipDecline' => clienttranslate('${player_name} declines a VIP passenger this round'),
    'weather' => clienttranslate('Weather forecast: Storms slow travel between ${routeSlow} while tailwinds speed travel between ${routeFast}'),
]);

define('N_REF_MSG_EX', [
    'allianceOwner' => totranslate("%s already selected alliance %s"),
    'boardDeliver' => totranslate("%s must deliver this passenger"),
    'boardPort' => totranslate("You must be at %s to board this passenger"),
    'boardTransfer' => totranslate("You and %s must be at the same airport to transfer passengers"),
    'deplanePort' => totranslate("You must be at an airport to deplane passengers"),
    'noSeat' => totranslate("You don't have enough empty seats"),
    'noSnooze' => totranslate("You can't snooze because you are the only active player. If you are finished, end the round."),
    'pay' => totranslate("You must choose bills totalling at least %s, even if it results in overpayment"),
    'tempOwner' => totranslate("%s already owns %s"),
    'version' => totranslate("A new version of this game is now available. Please reload the page (F5)."),
    'vip' => totranslate("VIP: You must honor %s (%s)"),
]);

define('N_REF_SEAT_COST', [
    2 => 5,
    3 => 9,
    4 => 13,
    5 => 17,
]);

define('N_REF_SPEED_COST', [
    4 => 5,
    5 => 7,
    6 => 9,
    7 => 11,
    8 => 13,
    9 => 15
]);

define('N_REF_VIP', [
    'BABY' => [
        'name' => clienttranslate('Crying Baby'),
        'desc' => clienttranslate('Other pasengers at same airport gain 2 anger per turn'),
        'hours' => ['MORNING', 'NOON'],
    ],
    'CELEBRITY' => [
        'name' => clienttranslate('Celebrity'),
        'desc' => clienttranslate('Must fly alone'),
        'hours' => ['NIGHT'],
    ],
    'DIRECT' => [
        'name' => clienttranslate('Direct Flight'),
        'desc' => clienttranslate('Only deplanes at destination'),
        'hours' => ['NIGHT'],
    ],
    'DOUBLE' => [
        'name' => clienttranslate('Captured Fugitive'),
        'desc' => clienttranslate('Requires 2 seats'),
        'hours' => ['NOON'],
    ],
    'FIRST' => [
        'name' => clienttranslate('First In Line'),
        'desc' => clienttranslate('Must board before other passengers at the same airport'),
        'hours' => ['NOON'],
    ],
    'GRUMPY' => [
        'name' => clienttranslate('Grumpy'),
        'desc' => clienttranslate('Starts at 1 anger'),
        'hours' => ['NIGHT'],
    ],
    'IMPATIENT' => [
        'name' => clienttranslate('Impatient'),
        'desc' => clienttranslate('Anger never resets'),
        'hours' => ['MORNING'],
    ],
    'NERVOUS' => [
        'name' => clienttranslate('Nervous'),
        'desc' => clienttranslate('Cannot fly through weather'),
        'hours' => ['MORNING'],
    ],
]);

define('N_REF_WEATHER_SPEED', [
    'FAST' => -1,
    'SLOW' => 1,
]);
