CREATE TABLE `pax` (
    `pax_id` INT(3) NOT NULL AUTO_INCREMENT,
    `status` ENUM(
        'MORNING',
        'NOON',
        'NIGHT',
        'SECRET',
        'PORT',
        'SEAT',
        'CASH',
        'COMPLAINT'
    ) NOT NULL,
    `anger` INT(1) NOT NULL DEFAULT '0',
    `cash` INT(1) NOT NULL,
    `origin` VARCHAR(50) NOT NULL,
    `destination` VARCHAR(50) NOT NULL,
    `location` VARCHAR(50) DEFAULT NULL,
    `player_id` INT(10) DEFAULT NULL,
    `vip` VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (`pax_id`),
    INDEX (`status`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1;

CREATE TABLE `plane` (
    `player_id` INT(10) NOT NULL,
    `alliances` VARCHAR(50) NOT NULL DEFAULT '',
    `debt` INT(2) NOT NULL DEFAULT '0',
    `origin` VARCHAR(50) DEFAULT NULL,
    `location` VARCHAR(50) DEFAULT NULL,
    `temp_speed` TINYINT(1) DEFAULT NULL,
    `temp_seat` TINYINT(1) DEFAULT NULL,
    `speed` INT(2) NOT NULL DEFAULT '3',
    `speed_remain` INT(2) NOT NULL DEFAULT '3',
    `seat` INT(2) NOT NULL DEFAULT '1',
    PRIMARY KEY (`player_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

CREATE TABLE `plane_undo` AS SELECT * FROM `plane`;

CREATE TABLE `var` (
    `key` VARCHAR(50) NOT NULL,
    `value` VARCHAR(50),
    PRIMARY KEY (`key`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

CREATE TABLE `weather` (
    `location` VARCHAR(50) NOT NULL,
    `token` ENUM('FAST', 'SLOW') NOT NULL,
    PRIMARY KEY (`location`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;