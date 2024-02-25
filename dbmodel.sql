ALTER TABLE `player` ADD COLUMN `snooze` TINYINT(1) NOT NULL DEFAULT '0';

CREATE TABLE `stats_undo` LIKE `stats`;

CREATE TABLE `ledger` (
    `player_id` INT(10) NOT NULL,
    `arg` VARCHAR(50) DEFAULT NULL,
    `cost` INT(2) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    INDEX (`player_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

CREATE TABLE `pax` (
    `pax_id` INT(3) NOT NULL AUTO_INCREMENT,
    `anger` INT(1) NOT NULL DEFAULT '0',
    `cash` INT(1) NOT NULL,
    `destination` VARCHAR(50) NOT NULL,
    `location` VARCHAR(50) DEFAULT NULL,
    `moves` INT(2) NOT NULL DEFAULT '0',
    `optimal` INT(2) NOT NULL,
    `origin` VARCHAR(50) NOT NULL,
    `player_id` INT(10) DEFAULT NULL,
    `status` ENUM(
        'MORNING',
        'NOON',
        'NIGHT',
        'SECRET',
        'PORT',
        'SEAT',
        'CASH',
        'PAID',
        'COMPLAINT'
    ) NOT NULL,
    `vip` VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (`pax_id`),
    INDEX (`status`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1;

CREATE TABLE `pax_undo` LIKE `pax`;

CREATE TABLE `plan` (
    `plan_id` INT(3) NOT NULL AUTO_INCREMENT,
    `alliance` VARCHAR(3) NOT NULL,
    `destination` VARCHAR(3),
    `destination_move` INT(3),
    `hr` INT(2) NOT NULL,
    `min` INT(2) NOT NULL,
    `optimal` INT(2),
    `origin` VARCHAR(3) NOT NULL,
    `origin_move` INT(3) NOT NULL,
    `rand` INT(1) NOT NULL,
    PRIMARY KEY (`plan_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

CREATE TABLE `plane` (
    `player_id` INT(10) NOT NULL,
    `alliances` VARCHAR(50) NOT NULL DEFAULT '',
    `debt` INT(2) NOT NULL DEFAULT '0',
    `location` VARCHAR(50) DEFAULT NULL,
    `origin` VARCHAR(50) DEFAULT NULL,
    `seat` INT(2) NOT NULL DEFAULT '1',
    `seat_x` INT(2) NOT NULL DEFAULT '0',
    `speed_remain` INT(2) NOT NULL DEFAULT '3',
    `speed` INT(2) NOT NULL DEFAULT '3',
    `temp_seat` TINYINT(1) NOT NULL DEFAULT '0',
    `temp_speed` TINYINT(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`player_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

CREATE TABLE `plane_undo` LIKE `plane`;

CREATE TABLE `var` (
    `key` VARCHAR(50) NOT NULL,
    `value` VARCHAR(50),
    PRIMARY KEY (`key`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

CREATE TABLE `weather` (
    `hour` VARCHAR(50) NOT NULL,
    `location` VARCHAR(50) NOT NULL,
    `token` ENUM('FAST', 'SLOW') NOT NULL,
    PRIMARY KEY (`hour`, `location`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;