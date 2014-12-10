CREATE TABLE IF NOT EXISTS `#__jextboxcontenthitssimulator_lastexecute` (
  `time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `#__jextboxcontenthitssimulator_simulatedhits` (
  `content_id` int(10) NOT NULL,
  KEY `content_id` (`content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
