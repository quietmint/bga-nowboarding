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
        'default' => 1,
        'notdisplayedmessage' => totranslate('Off'),
        'values' => [
            0 => [
                'name' => totranslate('Off'),
                'description' => totranslate("House rule: The flight phase has no specific time limit"),
                'tmdisplay' => totranslate('Flight Phase Timer Off'),
            ],
            1 => [
                'name' => totranslate('On'),
                'description' => totranslate("The flight phase lasts 30 - 45 seconds (depending on player count) and time is STRICTLY ENFORCED"),
                'nobeginner' => true,
            ],
            2 => [
                'name' => totranslate('Doubled'),
                'description' => totranslate("The flight phase lasts 60 - 90 seconds (depending on player count) and time is STRICTLY ENFORCED"),
                'tmdisplay' => totranslate('Flight Phase Timer Doubled'),
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
            1 => [
                'name' => totranslate('On'),
                'description' => totranslate('Adds complications like "must board first" or "must fly alone"'),
                'tmdisplay' => totranslate('VIP Variant'),
                'nobeginner' => true,
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
                'description' => totranslate('Unofficial house rule: Increase difficulty by playing on a larger map intended for more players'),
                'tmdisplay' => totranslate('Add JFK (2 players)'),
                'nobeginner' => true,
                'beta' => true,
            ],
            N_MAP_SEA => [
                'name' => totranslate('Add JFK and SEA (2-3 players)'),
                'description' => totranslate('Unofficial house rule: Increase difficulty by playing on a larger map intended for more players'),
                'tmdisplay' => totranslate('Add JFK and SEA (2-3 players)'),
                'nobeginner' => true,
                'beta' => true,
            ],
        ],
        'displaycondition' => [
            [
                'type' => 'maxplayers',
                'value' => [2, 3],
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
