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

require_once 'modules/constants.inc.php';

$game_options = [
    N_OPTION_TIMER => [
        'name' => totranslate('Flight Phase Timer'),
        'default' => 2,
        'notdisplayedmessage' => totranslate('Off'),
        'values' => [
            1 => [
                'name' => totranslate('On'),
                'description' => totranslate("The flight phase lasts 30 - 45 seconds (depending on player count) and time is STRICTLY ENFORCED"),
                'tmdisplay' => totranslate('Flight Phase Timer On'),
            ],
            2 => [
                'name' => totranslate('Doubled'),
                'description' => totranslate("The flight phase lasts 60 - 90 seconds (depending on player count) and time is STRICTLY ENFORCED [unofficial, easier]"),
                'tmdisplay' => totranslate('Flight Phase Timer Doubled'),
            ],
            0 => [
                'name' => totranslate('Off'),
                'description' => totranslate("The flight phase has no specific time limit [unofficial, easier]"),
                'tmdisplay' => totranslate('Flight Phase Timer Off'),
            ],
        ],
        'displaycondition' => [
            [
                'type' => 'otheroption',
                'id' => N_BGA_CLOCK,
                'value' => N_REF_BGA_CLOCK_REALTIME,
            ],
        ],
    ],

    N_OPTION_VIP => [
        'name' => totranslate('VIP Variant'),
        'default' => 0,
        'values' => [
            0 => [
                'name' => totranslate('Off'),
            ],
            N_VIP_FOWERS => [
                'name' => totranslate('Normal VIPs'),
                'description' => totranslate('Some passengers will have normal complications (like "must board first" or "must fly alone")'),
                'tmdisplay' => totranslate('Normal VIPs'),
                'nobeginner' => true,
            ],
            N_VIP_BGA => [
                'name' => totranslate('BGA Community VIPs'),
                'description' => totranslate('Some passengers will have crowdsourced complications (suggested by the BGA player community) [unofficial, harder]'),
                'tmdisplay' => totranslate('BGA Community VIPs'),
                'nobeginner' => true,
                'beta' => true,
            ],
            N_VIP_ALL => [
                'name' => totranslate('Normal + BGA Community VIPs'),
                'description' => totranslate('Some passengers will have normal or crowdsourced complications [unofficial, harder]'),
                'tmdisplay' => totranslate('Normal + BGA Community VIPs'),
                'nobeginner' => true,
                'beta' => true,
            ],
        ],
    ],

    N_OPTION_VIP_COUNT => [
        'level' => 'additional',
        'name' => totranslate('VIP Count'),
        'default' => 1,
        'values' => [
            1 => [
                'name' => totranslate('Normal'),
                'description' => totranslate('4/5/6/7 VIPs, depending on player count'),
            ],
            N_VIP_INCREASE => [
                'name' => totranslate('Increased'),
                'description' => totranslate('6/7/8/9 VIPs, depending on player count [unofficial, harder]'),
                'tmdisplay' => totranslate('VIP Count Increased'),
            ],
            N_VIP_DOUBLE => [
                'name' => totranslate('Doubled'),
                'description' => totranslate('8/10/12/13 VIPs, depending on player count [unofficial, harder]'),
                'tmdisplay' => totranslate('VIP Count Doubled'),
            ],
        ],
        'displaycondition' => [
            [
                'type' => 'otheroptionisnot',
                'id' => N_OPTION_VIP,
                'value' => 0,
            ],
        ],
    ],

    N_OPTION_MAP => [
        'level' => 'additional',
        'name' => totranslate('Map Size'),
        'default' => 0,
        'values' => [
            0 => [
                'name' => totranslate('Normal'),
            ],
            N_MAP_JFK => [
                'name' => totranslate('Add JFK (2 players)'),
                'description' => totranslate('Play with additional airports intended for more players [unofficial, harder]'),
                'tmdisplay' => totranslate('Add JFK (2 players)'),
                'nobeginner' => true,
            ],
            N_MAP_SEA => [
                'name' => totranslate('Add JFK and SEA (2-3 players)'),
                'description' => totranslate('Play with additional airports intended for more players [unofficial, harder]'),
                'tmdisplay' => totranslate('Add JFK and SEA (2-3 players)'),
                'nobeginner' => true,
            ],
        ],
        'displaycondition' => [
            [
                'type' => 'maxplayers',
                'value' => [2, 3],
            ],
        ],
    ],

    N_OPTION_UPGRADE => [
        'level' => 'additional',
        'name' => totranslate('Starting Upgrade'),
        'default' => 0,
        'values' => [
            0 => [
                'name' => totranslate('Normal'),
                'description' => totranslate('Choose to start with either 1 seat, 4 speed or 2 seats, 3 speed'),
            ],
            N_UPGRADE_SEAT => [
                'name' => totranslate('Seat'),
                'description' => totranslate('Start with 2 seats, 3 speed'),
                'tmdisplay' => totranslate('Start With Seat Upgrade'),
                'nobeginner' => true,
            ],
            N_UPGRADE_SPEED => [
                'name' => totranslate('Speed'),
                'description' => totranslate('Start with 1 seat, 4 speed'),
                'tmdisplay' => totranslate('Start With Speed Upgrade'),
                'nobeginner' => true,
            ],
            N_UPGRADE_BOTH => [
                'name' => totranslate('Both'),
                'description' => totranslate('Start with 2 seats, 4 speed [unofficial, easier]'),
                'tmdisplay' => totranslate('Start With Both Upgrades'),
                'nobeginner' => true,
            ],
        ],
    ],
];

$game_preferences = [
    N_PREF_ANIMATION => [
        'name' => totranslate('Animation'),
        'needReload' => false,
        'values' => [
            0 => ['name' => totranslate('Enabled')],
            1 => ['name' => totranslate('Enabled except airport pinging')],
            2 => ['name' => totranslate('Disabled')],
        ],
    ],
];
