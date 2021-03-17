CREATE TABLE IF NOT EXISTS `card` (
	`card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`card_type` varchar(16) NOT NULL,
	`card_type_arg` int(11) NOT NULL,
	`card_location` varchar(16) NOT NULL,
	`card_location_arg` int(11) NOT NULL,
	PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

/*
0 -> K
1 -> Q
2 -> J
3 -> L
4 -> H
5 -> N
6,7,8 -> +
*/
CREATE TABLE IF NOT EXISTS `bid` (
	`player_id` int(10) unsigned NOT NULL,
	`bid_type` int(10) NOT NULL,
	`is_allowed` BOOLEAN
) ENGINE=InnoDB DEFAULT CHARSET=utf8;