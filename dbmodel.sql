CREATE TABLE IF NOT EXISTS `plane` (
    `player_id` INT(10) NOT NULL,
    `color` ENUM(
        'RED',
        'ORANGE',
        'GREEN',
        'BLUE',
        'PURPLE'
    ) DEFAULT NULL,
    `colors` SET(
        'RED',
        'ORANGE',
        'GREEN',
        'BLUE',
        'PURPLE'
    ) DEFAULT NULL,
    `speed` INT(2) NOT NULL DEFAULT '3',
    `seats` INT(2) NOT NULL DEFAULT '1',
    `fuel` INT(2) NOT NULL DEFAULT '3',
    `cash` INT(2) NOT NULL,
    `current_node` VARCHAR(50) DEFAULT NULL,
    `prior_node` VARCHAR(50) DEFAULT NULL,
    `extra_speed` TINYINT(1) DEFAULT NULL,
    `extra_seat` TINYINT(1) DEFAULT NULL,
    PRIMARY KEY (`player_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `pax` (
    `pax_id` INT(3) NOT NULL AUTO_INCREMENT,
    `order` INT(3) NOT NULL,
    `status` ENUM(
        'QUEUED',
        'PORT',
        'SEAT',
        'EXTRA_SEAT',
        'DELIVERED',
        'SPENT',
        'COMPLAINT'
    ) NOT NULL,
    `anger` INT(1) NOT NULL DEFAULT '0',
    `cash` INT(1) NOT NULL,
    `begin_node` VARCHAR(50) NOT NULL,
    `end_node` VARCHAR(50) NOT NULL,
    `current_node` VARCHAR(50) DEFAULT NULL,
    `player_id` INT(10) DEFAULT NULL,
    PRIMARY KEY (`pax_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1;

CREATE TABLE IF NOT EXISTS `weather` (
    `weather_id` INT(1) NOT NULL AUTO_INCREMENT,
    `token` ENUM('FAST', 'SLOW') NOT NULL,
    `current_node` VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (`weather_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1;