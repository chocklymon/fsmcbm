-- Ban Manager database tables.
-- v3
-- Last updated 2014-04-08

-- --------------------------------------------------------

--
-- Table structure for table `appeal`
--

CREATE TABLE IF NOT EXISTS `appeal` (
  `appeal_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `author_id` int(10) unsigned NOT NULL,
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  `date` datetime DEFAULT NULL,
  `message` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`appeal_id`),
  KEY `user_id` (`user_id`),
  KEY `author_id` (`author_id`),
  KEY `closed` (`closed`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `ban_history`
--

CREATE TABLE IF NOT EXISTS `ban_history` (
  `user_id` int(10) unsigned NOT NULL,
  `moderator_id` int(10) unsigned NOT NULL,
  `date` datetime NOT NULL,
  `banned` tinyint(1) NOT NULL DEFAULT '0',
  `permanent` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`date`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `incident`
--

CREATE TABLE IF NOT EXISTS `incident` (
  `incident_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `moderator_id` int(10) unsigned NOT NULL,
  `created_date` datetime NOT NULL,
  `modified_date` datetime NOT NULL,
  `incident_date` datetime DEFAULT NULL,
  `incident_type` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8_unicode_ci,
  `action_taken` text COLLATE utf8_unicode_ci,
  `world` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `coord_x` int(11) DEFAULT NULL,
  `coord_z` int(11) DEFAULT NULL,
  `coord_y` tinyint(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`incident_id`),
  KEY `user_id` (`user_id`),
  KEY `moderator` (`moderator_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `rank`
--

CREATE TABLE IF NOT EXISTS `rank` (
  `rank_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`rank_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` BINARY(16) NULL COMMENT 'Store the users universally unique identifier',
  `username` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `modified_date` datetime NOT NULL,
  `rank` int(10) NOT NULL DEFAULT '1',
  `relations` text COLLATE utf8_unicode_ci,
  `notes` text COLLATE utf8_unicode_ci,
  `banned` tinyint(1) NOT NULL DEFAULT '0',
  `permanent` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `username` (`username`),
  KEY `banned` (`banned`),
  KEY `rank` (`rank`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `passwords`
--

CREATE TABLE `passwords` (
	`user_id` INT(10) unsigned NOT NULL,
	`password_hash` BINARY(64) NOT NULL COLLATE 'utf8_unicode_ci'
) COMMENT='Store user passwords for the built in authentication' COLLATE='utf8_unicode_ci' ENGINE=MyISAM ;

-- --------------------------------------------------------

--
-- Table structure for table `auth_nonce`
--

CREATE TABLE `auth_nonce` (
	`nonce` BINARY(16) NOT NULL,
	`timestamp` DATETIME NOT NULL,
    PRIMARY KEY (`nonce`)
) COMMENT='Stores used nonce\'s' COLLATE='utf8_unicode_ci' ENGINE=MyISAM ;
