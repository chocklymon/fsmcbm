-- Database: `fsmcbm`
--

-- --------------------------------------------------------

--
-- Table structure for table `incident`
--

CREATE TABLE IF NOT EXISTS `incident` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `moderator` INT UNSIGNED NOT NULL,
  `created_date` DATETIME NOT NULL,
  `incident_date` DATETIME DEFAULT NULL,
  `incident_type` VARCHAR(20) DEFAULT NULL,
  `notes` TEXT,
  `action_taken` TEXT,
  `world` VARCHAR(20) DEFAULT NULL,
  `coord_x` INT DEFAULT NULL,
  `coord_y` TINYINT UNSIGNED DEFAULT NULL,
  `coord_z` INT DEFAULT NULL,
  `appeal_date` DATETIME DEFAULT NULL,
  `appeal` TEXT,
  `appeal_response` TEXT,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `moderator` (`moderator`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(20) NOT NULL,
  `rank` VARCHAR(20) DEFAULT NULL,
  `relations` TEXT,
  `notes` TEXT,
  `banned` BOOLEAN NOT NULL DEFAULT FALSE,
  `permanent` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `banned` (`banned`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;