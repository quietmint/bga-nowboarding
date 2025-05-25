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

$machinestates = [
    // BGA framework state, do not modify
    N_STATE_BEGIN => [
        'name' => 'gameSetup',
        'action' => 'stGameSetup',
        'description' => '',
        'transitions' => [
            '' => N_STATE_BUILD
        ],
        'type' => 'manager',
    ],

    N_STATE_BUILD => [
        'name' => 'build',
        'action' => 'stInitPrivate',
        'description' => clienttranslate('Wait for others to choose'),
        'descriptionmyturn' => '',
        'initialprivate' => N_STATE_BUILD_ALLIANCE,
        'possibleactions' => [
            'actUndo',
        ],
        'transitions' => [
            'maintenance' => N_STATE_MAINTENANCE,
        ],
        'type' => 'multipleactiveplayer',
    ],

    N_STATE_BUILD_ALLIANCE => [
        'name' => 'buildAlliance',
        'args' => 'argBuildAlliance',
        'descriptionmyturn' => clienttranslate('Choose a starting airport and alliance'),
        'possibleactions' => [
            'actBuy',
        ],
        'transitions' => [
            'buildAlliance2' => N_STATE_BUILD_ALLIANCE2,
            'buildUpgrade' => N_STATE_BUILD_UPGRADE,
        ],
        'type' => 'private',
    ],

    N_STATE_BUILD_ALLIANCE2 => [
        'name' => 'buildAlliance2',
        'args' => 'argBuildAlliance2',
        'descriptionmyturn' => clienttranslate('Choose an additional alliance'),
        'possibleactions' => [
            'actBuy',
            'actUndo',
        ],
        'transitions' => [
            'buildAlliance' => N_STATE_BUILD_ALLIANCE,
            'buildUpgrade' => N_STATE_BUILD_UPGRADE,
        ],
        'type' => 'private',
    ],

    N_STATE_BUILD_UPGRADE => [
        'name' => 'buildUpgrade',
        'args' => 'argBuildUpgrade',
        'descriptionmyturn' => clienttranslate('Choose a starting upgrade'),
        'possibleactions' => [
            'actBuy',
            'actUndo',
        ],
        'transitions' => [
            'buildAlliance' => N_STATE_BUILD_ALLIANCE,
            'buildAlliance2' => N_STATE_BUILD_ALLIANCE2
        ],
        'type' => 'private',
    ],

    N_STATE_MAINTENANCE => [
        'name' => 'maintenance',
        'action' => 'stMaintenance',
        'description' => '',
        'transitions' => [
            'end' => N_STATE_END,
            'prepare' => N_STATE_PREPARE,
        ],
        'type' => 'game',
    ],

    N_STATE_PREPARE => [
        'name' => 'prepare',
        'args' => 'argPrepare',
        'action' => 'stPrepare',
        'description' => clienttranslate('Wait for others to prepare for ${hourDesc} ${round}'),
        'descriptionmyturn' => '',
        'initialprivate' => N_STATE_PREPARE_BUY,
        'possibleactions' => [
            'actUndo',
        ],
        'transitions' => [
            'reveal' => N_STATE_REVEAL,
        ],
        'type' => 'multipleactiveplayer',
        'updateGameProgression' => true,
    ],

    N_STATE_PREPARE_BUY => [
        'name' => 'prepareBuy',
        'args' => 'argPrepareBuy',
        'descriptionmyturn' => clienttranslate('Prepare for ${hourDesc} ${round}'),
        'possibleactions' => [
            'actBuy',
            'actPrepareDone',
            'actUndo',
            'actVip',
        ],
        'transitions' => [
            'prepareBuy' => N_STATE_PREPARE_BUY,
            'preparePay' => N_STATE_PREPARE_PAY,
        ],
        'type' => 'private',
    ],

    N_STATE_PREPARE_PAY => [
        'name' => 'preparePay',
        'args' => 'argPreparePay',
        'descriptionmyturn' => clienttranslate('Choose how to pay $${debt}'),
        'possibleactions' => [
            'actBuyAgain',
            'actPay',
        ],
        'transitions' => [
            'prepareBuy' => N_STATE_PREPARE_BUY,
        ],
        'type' => 'private',
    ],

    N_STATE_REVEAL => [
        'name' => 'reveal',
        'action' => 'stReveal',
        'description' => '',
        'transitions' => [
            'fly' => N_STATE_FLY,
        ],
        'type' => 'game',
    ],

    N_STATE_FLY => [
        'name' => 'fly',
        'action' => 'stInitPrivate',
        'args' => 'argFly',
        'description' => clienttranslate('Wait for others to finish'),
        'descriptionmyturn' => '',
        'initialprivate' => N_STATE_FLY_PRIVATE,
        'possibleactions' => [
            'actFlyAgain',
            'actFlyTimer',
        ],
        'transitions' => [
            'maintenance' => N_STATE_MAINTENANCE,
        ],
        'type' => 'multipleactiveplayer',
    ],

    N_STATE_FLY_PRIVATE => [
        'name' => 'flyPrivate',
        'args' => 'argFlyPrivate',
        'descriptionmyturn' => clienttranslate('Go!'),
        'possibleactions' => [
            'actBoard',
            'actDeplane',
            'actFlyDone',
            'actFlyTimer',
            'actMove',
        ],
        'transitions' => [
            'flyPrivate' => N_STATE_FLY_PRIVATE,
        ],
        'type' => 'private',
    ],

    // BGA framework state, do not modify
    N_STATE_END => [
        'name' => 'gameEnd',
        'description' => clienttranslate('End of game'),
        'type' => 'manager',
        'action' => 'stGameEnd',
        'args' => 'argGameEnd',
        'updateGameProgression' => true,
    ]
];
