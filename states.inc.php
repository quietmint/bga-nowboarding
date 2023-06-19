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
        'action' => 'stBuild',
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
            'preflight' => N_STATE_PREFLIGHT,
        ],
        'type' => 'game',
    ],

    N_STATE_PREFLIGHT => [
        'name' => 'preflight',
        'action' => 'stMultiactive',
        'args' => 'argPreflight',
        'description' => clienttranslate('Wait for others to finish'),
        'descriptionmyturn' => clienttranslate('Discuss plans and purchase upgrades before the round begins'),
        'possibleactions' => [
            'begin',
            'buy',
        ],
        'transitions' => [
            'flight' => N_STATE_FLIGHT,
        ],
        'type' => 'multipleactiveplayer',
    ],

    N_STATE_FLIGHT => [
        'name' => 'flight',
        'action' => 'stMultiactive',
        'args' => 'argFlight',
        'description' => clienttranslate('Wait for others to finish'),
        'descriptionmyturn' => clienttranslate('Go! Go! Go!'),
        'possibleactions' => [
            'dropPassenger',
            'end',
            'move',
            'pickPassenger',
        ],
        'transitions' => [
            'maintenance' => N_STATE_MAINTENANCE,
        ],
        'type' => 'multipleactiveplayer',
    ],

    N_STATE_MAINTENANCE => [
        'name' => 'maintenance',
        'action' => 'stMaintenance',
        'description' => '',
        'transitions' => [
            'end' => N_STATE_END,
            'preflight' => N_STATE_PREFLIGHT,
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
