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
define('N_BGA_VERSION', 300);

define('N_REF_BGA_CLOCK_REALTIME', [0, 1, 2, 9]);
define('N_REF_BGA_CLOCK_UNLIMITED', [9, 20]);

// Game options
define('N_OPTION_TIMER', 100);
define('N_OPTION_VIP', 101);
define('N_OPTION_VIP_COUNT', 111);
define('N_VIP_FOWERS', 1);
define('N_VIP_BGA', 2);
define('N_VIP_ALL', 3);
define('N_VIP_INCREASE', 2);
define('N_VIP_DOUBLE', 4);
define('N_OPTION_MAP', 110);
define('N_MAP_JFK', 1);
define('N_MAP_SEA', 2);
define('N_OPTION_UPGRADE', 112);
define('N_UPGRADE_SEAT', 2);
define('N_UPGRADE_SPEED', 3);
define('N_UPGRADE_BOTH', 4);
define('N_OPTION_ANGER', 113);

// Game preferences
define('N_PREF_ANIMATION', 150);

// Reference lookups
define('N_REF_ALLIANCE_COLOR', [
    'ATL' => '43a047', // green-600
    'DFW' => '8e24aa', // purple-600
    'LAX' => 'f9a825', // yellow-800
    'ORD' => 'd32f2f', // red-700
    'SEA' => '1976d2', // blue-700
]);

define('N_REF_HOUR', [
    'PREFLIGHT' => [
        'desc' => clienttranslate('Preflight'),
        '_desc' => totranslate('Preflight'), // https://studio.boardgamearena.com/bug?id=154
        'next' => 'MORNING',
        'prev' => null,
    ],
    'MORNING' => [
        'desc' => clienttranslate('Morning'),
        '_desc' => totranslate('Morning'), // https://studio.boardgamearena.com/bug?id=154
        'next' => 'NOON',
        'prev' => null,
    ],
    'NOON' => [
        'desc' => clienttranslate('Afternoon'),
        '_desc' => totranslate('Afternoon'), // https://studio.boardgamearena.com/bug?id=154
        'next' => 'NIGHT',
        'prev' => 'MORNING',
    ],
    'NIGHT' => [
        'desc' => clienttranslate('Evening'),
        '_desc' => totranslate('Evening'), // https://studio.boardgamearena.com/bug?id=154
        'next' => 'FINALE',
        'prev' => 'NOON',
    ],
    'FINALE' => [
        'desc' => clienttranslate('Final Round'),
        '_desc' => totranslate('Final Round'), // https://studio.boardgamearena.com/bug?id=154
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

define('N_REF_HOUR_ROUND', [
    2 => [
        'MORNING' => N_REF_HOUR_PAX[2]['MORNING'] / 1, // 3 rounds
        'NOON' => N_REF_HOUR_PAX[2]['NOON'] / 2, // 5 rounds
        'NIGHT' => N_REF_HOUR_PAX[2]['NIGHT'] / 3, // 9 rounds
    ],
    3 => [
        'MORNING' => N_REF_HOUR_PAX[3]['MORNING'] / 2, // 3 rounds
        'NOON' => N_REF_HOUR_PAX[3]['NOON'] / 3, // 5 rounds
        'NIGHT' => N_REF_HOUR_PAX[3]['NIGHT'] / 4, // 8 rounds
    ],
    4 => [
        'MORNING' => N_REF_HOUR_PAX[4]['MORNING'] / 3, // 4 rounds
        'NOON' => N_REF_HOUR_PAX[4]['NOON'] / 4, // 5 rounds
        'NIGHT' => N_REF_HOUR_PAX[4]['NIGHT'] / 5, // 7 rounds
    ],
    5 => [
        'MORNING' => N_REF_HOUR_PAX[5]['MORNING'] / 4, // 3 rounds
        'NOON' => N_REF_HOUR_PAX[5]['NOON'] / 5, // 6 rounds
        'NIGHT' => N_REF_HOUR_PAX[5]['NIGHT'] / 6, // 4 rounds
    ],
]);

define('N_REF_PROGRESSION', [
    2 => 19,
    3 => 18,
    4 => 18,
    5 => 15,
]);

define('N_REF_MSG', [
    'addPax' => clienttranslate('${count} passengers arrive at ${location}'),
    'alliance' => clienttranslate('${player_name} joins alliance ${alliance}'),
    'anger' => clienttranslate('${count} passengers at airports get angry'),
    'board' => clienttranslate('${player_name} boards the ${route} passenger at ${location}'),
    'boardTransfer' => clienttranslate('${player_name} boards the ${route} passenger at ${location} (transferred from ${player_name2})'),
    'complaintFinale' => clienttranslate('${complaint} complaints are filed by ${count} undelivered passengers'),
    'complaintPort' => clienttranslate('${complaint} complaints are filed by angry passengers at ${location}'),
    'complaintVip' => clienttranslate('${complaint} complaints are filed by VIPs not accepted during ${hourDesc}'),
    'deplane' => clienttranslate('${player_name} deplanes the ${route} passenger at ${location}'),
    'deplaneDeliver' => clienttranslate('${player_name} delivers the ${route} passenger to ${location} after ${moves} moves and earns ${cash}'),
    'endLose' => clienttranslate('Rough landing! Your airline goes out of business after receiving ${complaint} complaints!'),
    'endWin' => clienttranslate('Congratulations! Your airline is a soaring success!'),
    'flyAgain' => clienttranslate('${player_name} continues their turn'),
    'flyDone' => clienttranslate('${player_name} ends their turn'),
    'flyTimer' => clienttranslate('Time is up!'),
    'hour' => clienttranslate('${hourDesc} round ${round} of ${total} begins'),
    'hourFinale' => clienttranslate('${hourDesc}! Avoid new complaints and deliver ${countToWin} passengers to win'),
    'hourVip' => clienttranslate('VIP: ${count} VIPs must be accepted during ${hourDesc}'),
    'move' => clienttranslate('${player_name} flies ${fuel} moves'),
    'movePort' => clienttranslate('${player_name} flies ${fuel} moves to ${location}'),
    'seat' => clienttranslate('${player_name} upgrades seats to ${seat}'),
    'snooze' => clienttranslate('${player_name} snoozes until the next move'),
    'snoozeDeadlock' => clienttranslate('${players} snoozed until the next move, but nobody made a move! If you are finished, end the round.'),
    'speed' => clienttranslate('${player_name} upgrades speed to ${speed}'),
    'temp' => clienttranslate('${player_name} purchases ${temp}'),
    'tempUndo' => clienttranslate('${player_name} recovers ${temp}'),
    'tempUnused' => clienttranslate('${player_name} didn\'t use ${temp} last round, so it is unavailable for purchase'),
    'tempUsed' => clienttranslate('${player_name} uses ${temp}'),
    'undo' => clienttranslate('${player_name} restarts their turn'),
    'vipAccept' => clienttranslate('${player_name} accepts a VIP passenger this round'),
    'vipDecline' => clienttranslate('${player_name} declines a VIP passenger this round'),
    'vipWelcome' => clienttranslate('VIP: ${vip} arrives at ${location}: ${desc}'),
    'weatherFAST' => clienttranslate('${hourDesc} weather forecast: Tailwinds speed travel between ${location}'),
    'weatherSLOW' => clienttranslate('${hourDesc} weather forecast: Storms slow travel between ${location}'),
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

define(
    'N_REF_FARE',
    [
        'ATL' => [
            'DEN' => 2,
            'DFW' => 2,
            'JFK' => 2,
            'LAX' => 4,
            'MIA' => 1,
            'ORD' => 2,
            'SEA' => 4,
            'SFO' => 4,
        ],
        'DEN' => [
            'ATL' => 2,
            'DFW' => 2,
            'JFK' => 3,
            'LAX' => 2,
            'MIA' => 3,
            'ORD' => 2,
            'SEA' => 2,
            'SFO' => 2,
        ],
        'DFW' => [
            'ATL' => 2,
            'DEN' => 2,
            'JFK' => 3,
            'LAX' => 2,
            'MIA' => 2,
            'ORD' => 3,
            'SEA' => 3,
            'SFO' => 3,
        ],
        'JFK' => [
            'ATL' => 2,
            'DEN' => 3,
            'DFW' => 3,
            'LAX' => 5,
            'MIA' => 3,
            'ORD' => 2,
            'SEA' => 3,
            'SFO' => 4,
        ],
        'LAX' => [
            'ATL' => 4,
            'DEN' => 2,
            'DFW' => 2,
            'JFK' => 5,
            'MIA' => 3,
            'ORD' => 3,
            'SEA' => 3,
            'SFO' => 1,
        ],
        'MIA' => [
            'ATL' => 1,
            'DEN' => 3,
            'DFW' => 2,
            'JFK' => 3,
            'LAX' => 3,
            'ORD' => 3,
            'SEA' => 5,
            'SFO' => 4,
        ],
        'ORD' => [
            'ATL' => 2,
            'DEN' => 2,
            'DFW' => 3,
            'JFK' => 2,
            'LAX' => 3,
            'MIA' => 3,
            'SEA' => 2,
            'SFO' => 3,
        ],
        'SEA' => [
            'ATL' => 4,
            'DEN' => 2,
            'DFW' => 3,
            'JFK' => 3,
            'LAX' => 3,
            'MIA' => 5,
            'ORD' => 2,
            'SFO' => 2,
        ],
        'SFO' => [
            'ATL' => 4,
            'DEN' => 2,
            'DFW' => 3,
            'JFK' => 4,
            'LAX' => 1,
            'MIA' => 4,
            'ORD' => 3,
            'SEA' => 2,
        ],
    ]
);

define('N_REF_VIP', [
    // Fowers VIPs
    'BABY' => [
        'name' => clienttranslate('Crying Baby'),
        '_name' => totranslate('Crying Baby'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Other passengers at their airport gain 2 anger per turn'),
        '_desc' => totranslate('Other passengers at their airport gain 2 anger per turn'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 2,
        'hours' => ['MORNING', 'NOON'],
        'set' => N_VIP_FOWERS,
    ],
    'CELEBRITY' => [
        'name' => clienttranslate('Celebrity'),
        '_name' => totranslate('Celebrity'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Must fly alone'),
        '_desc' => totranslate('Must fly alone'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NIGHT'],
        'set' => N_VIP_FOWERS,
    ],
    'DIRECT' => [
        'name' => clienttranslate('Direct Flight'),
        '_name' => totranslate('Direct Flight'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Only deplanes at their destination'),
        '_desc' => totranslate('Only deplanes at their destination'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NIGHT'],
        'set' => N_VIP_FOWERS,
    ],
    'DOUBLE' => [
        'name' => clienttranslate('Captured Fugitive'),
        '_name' => totranslate('Captured Fugitive'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Requires 2 seats'),
        '_desc' => totranslate('Requires 2 seats'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NOON'],
        'set' => N_VIP_FOWERS,
    ],
    'FIRST' => [
        'name' => clienttranslate('First In Line'),
        '_name' => totranslate('First In Line'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Must board before other passengers at their airport'),
        '_desc' => totranslate('Must board before other passengers at their airport'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NOON'],
        'set' => N_VIP_FOWERS,
    ],
    'GRUMPY' => [
        'name' => clienttranslate('Grumpy'),
        '_name' => totranslate('Grumpy'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Starts at 1 anger'),
        '_desc' => totranslate('Starts at 1 anger'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NIGHT'],
        'set' => N_VIP_FOWERS,
    ],
    'IMPATIENT' => [
        'name' => clienttranslate('Impatient'),
        '_name' => totranslate('Impatient'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Anger never resets'),
        '_desc' => totranslate('Anger never resets'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['MORNING'],
        'set' => N_VIP_FOWERS,
    ],
    'NERVOUS' => [
        'name' => clienttranslate('Nervous'),
        '_name' => totranslate('Nervous'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Cannot fly through storms or tailwinds'),
        '_desc' => totranslate('Cannot fly through storms or tailwinds'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['MORNING'],
        'set' => N_VIP_FOWERS,
    ],

    // BGA Community VIPs
    'CONVENTION' => [
        'name' => clienttranslate('Convention'),
        '_name' => totranslate('Convention'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('All new passengers have the same destination'),
        '_desc' => totranslate('All new passengers have the same destination'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NIGHT'],
        'set' => N_VIP_BGA,
    ],
    'CREW' => [
        'name' => clienttranslate('Crewmember'),
        '_name' => totranslate('Crewmember'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Pays nothing but never gains anger'),
        '_desc' => totranslate('Pays nothing but never gains anger'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NOON', 'NIGHT'],
        'set' => N_VIP_BGA,
    ],
    'DISCOUNT' => [
        'name' => clienttranslate('Discount Ticket'),
        '_name' => totranslate('Discount Ticket'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Pays a reduced fare'),
        '_desc' => totranslate('Pays a reduced fare'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NIGHT'],
        'set' => N_VIP_BGA,
    ],
    'LATE' => [
        'name' => clienttranslate('Late Connection'),
        '_name' => totranslate('Late Connection'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Boarding consumes 1 speed'),
        '_desc' => totranslate('Boarding consumes 1 speed'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['MORNING', 'NOON'],
        'set' => N_VIP_BGA,
    ],
    'LAST' => [
        'name' => clienttranslate('Last In Line'),
        '_name' => totranslate('Last In Line'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Must board after other passengers at their airport'),
        '_desc' => totranslate('Must board after other passengers at their airport'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NOON'],
        'set' => N_VIP_BGA,
    ],
    'LOYAL' => [
        'name' => clienttranslate('${1} Loyalist'),
        '_name' => totranslate('${1} Loyalist'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Only flies on planes in the ${1} alliance'),
        '_desc' => totranslate('Only flies on planes in the ${1} alliance'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 2,
        'hours' => ['NOON', 'NIGHT'],
        'set' => N_VIP_BGA,
    ],
    'MYSTERY' => [
        'name' => clienttranslate('Mystery Shopper'),
        '_name' => totranslate('Mystery Shopper'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Destination remains secret until boarding'),
        '_desc' => totranslate('Destination remains secret until boarding'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['MORNING', 'NIGHT'],
        'set' => N_VIP_BGA,
    ],
    'RETURN' => [
        'name' => clienttranslate('Round-Trip Ticket'),
        '_name' => totranslate('Round-Trip Ticket'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Pays nothing for the first leg, then reappears for the reverse leg and pays double'),
        '_desc' => totranslate('Pays nothing for the first leg, then reappears for the reverse leg and pays double'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 2,
        'hours' => ['MORNING', 'NOON'],
        'set' => N_VIP_BGA,
    ],
    'REUNION' => [
        'name' => clienttranslate('Reunion'),
        '_name' => totranslate('Reunion'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Two passengers must meet at their destination'),
        '_desc' => totranslate('Two passengers must meet at their destination'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NOON'],
        'set' => N_VIP_BGA,
    ],
    'STORM' => [
        'name' => clienttranslate('Storm Chaser'),
        '_name' => totranslate('Storm Chaser'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Must fly through a storm (a tailwind does not count)'),
        '_desc' => totranslate('Must fly through a storm (a tailwind does not count)'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NOON'],
        'set' => N_VIP_BGA,
    ],
    'WIND' => [
        'name' => clienttranslate('Wind Glider'),
        '_name' => totranslate('Wind Glider'), // https://studio.boardgamearena.com/bug?id=154
        'desc' => clienttranslate('Must fly through a tailwind (a storm does not count)'),
        '_desc' => totranslate('Must fly through a tailwind (a storm does not count)'), // https://studio.boardgamearena.com/bug?id=154
        'count' => 1,
        'hours' => ['NOON'],
        'set' => N_VIP_BGA,
    ],
]);

define('N_REF_WEATHER_SPEED', [
    'FAST' => 0,
    'SLOW' => 2,
    null => 1,
]);
