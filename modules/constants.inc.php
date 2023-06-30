<?php

// States
const N_STATE_BEGIN = 1;
const N_STATE_BUILD = 2;
const N_STATE_BUILD_ALLIANCE = 21;
const N_STATE_BUILD_ALLIANCE2 = 22;
const N_STATE_BUILD_UPGRADE = 23;
const N_STATE_BUILD_COMPLETE = 3;
const N_STATE_PREPARE = 4;
const N_STATE_PREPARE_PRIVATE = 41;
const N_STATE_REVEAL = 5;
const N_STATE_FLY = 6;
const N_STATE_FLY_PRIVATE = 61;
const N_STATE_MAINTENANCE = 7;
const N_STATE_END = 99;

// Options
const N_OPTION_VERSION = 300;

// Reference lookups
const N_REF_ALLIANCE_COLOR = [
    'ATL' => '16a34a', // green
    'DFW' => '7e22ce', // purple
    'LAX' => 'd97706', // orange
    'ORD' => 'b91c1c', // red
    'SEA' => '1d4ed8', // blue
];

const N_REF_PAX_COUNTS = [
    2 => ['MORNING' => 2 + 3, 'AFTERNOON' => 10, 'EVENING' => 27],
    3 => ['MORNING' => 3 + 6, 'AFTERNOON' => 15, 'EVENING' => 32],
    4 => ['MORNING' => 4 + 12, 'AFTERNOON' => 20, 'EVENING' => 35],
    5 => ['MORNING' => 5 + 12, 'AFTERNOON' => 30, 'EVENING' => 24],
];

const N_REF_SEAT_COST = [
    2 => 5,
    3 => 9,
    4 => 13,
    5 => 17,
];

const N_REF_SPEED_COST = [
    4 => 5,
    5 => 7,
    6 => 9,
    7 => 11,
    8 => 13,
    9 => 15
];

const N_REF_WEATHER_SPEED = [
    'FAST' => -1,
    'SLOW' => 1,
];
