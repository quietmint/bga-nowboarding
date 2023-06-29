<?php

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
        'description' => clienttranslate('Wait for others to finish'),
        'descriptionmyturn' => '',
        'initialprivate' => N_STATE_BUILD_ALLIANCE,
        'possibleactions' => [
            'buildReset',
        ],
        'transitions' => [
            'buildComplete' => N_STATE_SHUFFLE,
        ],
        'type' => 'multipleactiveplayer',
    ],

    N_STATE_BUILD_ALLIANCE => [
        'name' => 'buildAlliance',
        'action' => 'stBuildAlliance',
        'args' => 'argBuildAlliance',
        'descriptionmyturn' => clienttranslate('Choose a starting airport and alliance'),
        'possibleactions' => [
            'buy',
        ],
        'transitions' => [
            'buildAlliance2' => N_STATE_BUILD_ALLIANCE2,
            'buildUpgrade' => N_STATE_BUILD_UPGRADE,
        ],
        'type' => 'private',
    ],

    N_STATE_BUILD_ALLIANCE2 => [
        'name' => 'buildAlliance2',
        'action' => 'stBuildAlliance2',
        'args' => 'argBuildAlliance2',
        'descriptionmyturn' => clienttranslate('Choose a second alliance'),
        'possibleactions' => [
            'buildReset',
            'buy',
        ],
        'transitions' => [
            'buildAlliance' => N_STATE_BUILD_ALLIANCE,
            'buildUpgrade' => N_STATE_BUILD_UPGRADE,
        ],
        'type' => 'private',
    ],

    N_STATE_BUILD_UPGRADE => [
        'name' => 'buildUpgrade',
        'action' => 'stBuildUpgrade',
        'args' => 'argBuildUpgrade',
        'descriptionmyturn' => clienttranslate('Choose a starting upgrade'),
        'possibleactions' => [
            'buildReset',
            'buy',
        ],
        'transitions' => [
            'buildAlliance' => N_STATE_BUILD_ALLIANCE,
            'buildAlliance2' => N_STATE_BUILD_ALLIANCE2
        ],
        'type' => 'private',
    ],

    N_STATE_SHUFFLE => [
        'name' => 'shuffle',
        'action' => 'stShuffle',
        'description' => '',
        'transitions' => [
            'prepare' => N_STATE_PREPARE,
        ],
        'type' => 'game',
    ],

    N_STATE_PREPARE => [
        'name' => 'prepare',
        'action' => 'stPrepare',
        'description' => clienttranslate('Wait for others to prepare'),
        'descriptionmyturn' => '',
        'initialprivate' => N_STATE_PREPARE_PRIVATE,
        'transitions' => [
            'fly' => N_STATE_FLY,
        ],
        'type' => 'multipleactiveplayer',
    ],

    N_STATE_PREPARE_PRIVATE => [
        'name' => 'preparePrivate',
        'args' => 'argPreparePrivate',
        'descriptionmyturn' => clienttranslate('Prepare for the next round'),
        'possibleactions' => [
            'begin',
            'buy',
        ],
        'transitions' => [
            'fly' => N_STATE_FLY,
            'preparePrivate' => N_STATE_PREPARE_PRIVATE,
        ],
        'type' => 'private',
    ],

    N_STATE_FLY => [
        'name' => 'fly',
        'action' => 'stInitPrivate',
        'description' => clienttranslate('Wait for others to finish'),
        'descriptionmyturn' => '',
        'initialprivate' => N_STATE_FLY_PRIVATE,
        'transitions' => [
            'maintenance' => N_STATE_MAINTENANCE,
        ],
        'type' => 'multipleactiveplayer',
    ],

    N_STATE_FLY_PRIVATE => [
        'name' => 'flyPrivate',
        'args' => 'argFlyPrivate',
        'descriptionmyturn' => clienttranslate('Go! Go! Go!'),
        'possibleactions' => [
            'dropPassenger',
            'end',
            'move',
            'pickPassenger',
        ],
        'transitions' => [
            'flyPrivate' => N_STATE_FLY_PRIVATE,
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

    // BGA framework state, do not modify
    N_STATE_END => [
        'name' => 'gameEnd',
        'description' => clienttranslate('End of game'),
        'type' => 'manager',
        'action' => 'stGameEnd',
        'args' => 'argGameEnd'
    ]
];
