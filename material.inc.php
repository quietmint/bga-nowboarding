<?php

$this->hourDesc = [
    'PREFLIGHT' => clienttranslate('Preflight'),
    'MORNING' => clienttranslate('Morning'),
    'NOON' => clienttranslate('Afternoon'),
    'NIGHT' => clienttranslate('Evening'),
    'FINALE' => clienttranslate('Final Round'),
];

$this->msg = [
    'addPax' => clienttranslate('${count} passengers arrive at ${location}'),
    'alliance' => clienttranslate('${player_name} joins alliance ${alliance}'),
    'anger' => clienttranslate('${count} passengers in airports get angry'),
    'board' => clienttranslate('${player_name} boards a ${route} passenger at ${location}'),
    'complaint' => clienttranslate('${complaint} complaints are filed by angry passengers at ${location}'),
    'complaintFinale' => clienttranslate('${complaint} complaints are filed by ${count} undelivered passengers'),
    'deplane' => clienttranslate('${player_name} deplanes a ${route} passenger at ${location}'),
    'deplaneDeliver' => clienttranslate('${player_name} delivers a ${route} passenger and earns ${cash}'),
    'endLose' => clienttranslate('Rough landing! Your airline goes out of business after receiving ${complaint} complaints!'),
    'endWin' => clienttranslate('Congratulations! Your airline is a soaring success!'),
    'hour' => clienttranslate('${hourDesc} round ${round} of ${total} begins'),
    'hourFinale' => clienttranslate('${hourDesc} begins'),
    'move' => clienttranslate('${player_name} flys to ${location}'),
    'seat' => clienttranslate('${player_name} upgrades seats to ${seat}'),
    'speed' => clienttranslate('${player_name} upgrades speed to ${speed}'),
    'temp' => clienttranslate('${player_name} purchases ${temp}'),
    'tempUsed' => clienttranslate('${player_name} uses ${temp}'),
    'undo' => clienttranslate('${player_name} restarts their turn'),
    'weather' => clienttranslate('Weather forecast: Storms slow travel between ${routeSlow} while tailwinds speed travel between ${routeFast}'),
];

$this->exMsg = [
    'allianceDuplicate' => $this->_("You already joined alliance %s"),
    'allianceOwner' => $this->_("%s already selected alliance %s"),
    'boardPort' => $this->_("You must be at %s to board this passenger"),
    'boardTransfer' => $this->_("You and %s must be at the same airport to transfer passengers"),
    'deplanePort' => $this->_("You must be at an airport to deplane passengers"),
    'noCash' => $this->_("You have insufficient funds (cost: %s, cash: %s)"),
    'noPay' => $this->_("You must choose bills totalling at least %s, even if it results in overpayment"),
    'noSeat' => $this->_("You have no empty seats"),
    'tempOwner' => $this->_("%s already owns %s"),
    'version' => $this->_("A new version of this game is now available. Please reload the page (F5)."),
];


$this->vips = [
    'BABY' => [
        'name' => clienttranslate('Crying Baby'),
        'desc' => clienttranslate('Other pasengers at same airport gain 2 anger per turn'),
        'hours' => ['MORNING', 'AFTERNOON'],
    ],
    'CELEBRITY' => [
        'name' => clienttranslate('Celebrity'),
        'desc' => clienttranslate('Must fly alone'),
        'hours' => ['EVENING'],
    ],
    'DIRECT' => [
        'name' => clienttranslate('Direct Flight'),
        'desc' => clienttranslate('Only deplanes at destination'),
        'hours' => ['EVENING'],
    ],
    'DOUBLE' => [
        'name' => clienttranslate('Captured Fugitive'),
        'desc' => clienttranslate('Requires 2 seats'),
        'hours' => ['AFTERNOON'],
    ],
    'FIRST' => [
        'name' => clienttranslate('First In Line'),
        'desc' => clienttranslate('Must board before other passengers'),
        'hours' => ['AFTERNOON'],
    ],
    'GRUMPY' => [
        'name' => clienttranslate('Grumpy'),
        'desc' => clienttranslate('Starts at 1 anger'),
        'hours' => ['EVENING'],
    ],
    'IMPATIENT' => [
        'name' => clienttranslate('Impatient'),
        'desc' => clienttranslate('Anger never clears'),
        'hours' => ['MORNING'],
    ],
    'NERVOUS' => [
        'name' => clienttranslate('Nervous'),
        'desc' => clienttranslate('Cannot fly through weather'),
        'hours' => ['MORNING'],
    ],
];
