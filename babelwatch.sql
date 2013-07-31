-- --------------------------------------------------------
-- Host:                         svrtest10
-- Server version:               5.5.30-30.1-log - Percona Server (GPL), Release 30.1
-- Server OS:                    Linux
-- HeidiSQL version:             6.0.0.3991
-- Date/time:                    2013-07-31 17:06:28
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET FOREIGN_KEY_CHECKS=0 */;

-- Dumping database structure for babelwatch
CREATE DATABASE IF NOT EXISTS `babelwatch` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_bin */;
USE `babelwatch`;


-- Dumping structure for table babelwatch.bw_changeset
CREATE TABLE IF NOT EXISTS `bw_changeset` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `hg_id` binary(20) NOT NULL,
  `repo_id` int(10) DEFAULT NULL,
  `user_id` int(10) DEFAULT NULL,
  `summary` varchar(500) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hg_id_repo_id` (`hg_id`,`repo_id`),
  KEY `FK_bw_changeset_bw_repo` (`repo_id`),
  KEY `FK_bw_changeset_bw_user` (`user_id`),
  CONSTRAINT `FK_bw_changeset_bw_repo` FOREIGN KEY (`repo_id`) REFERENCES `bw_repo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_bw_changeset_bw_user` FOREIGN KEY (`user_id`) REFERENCES `bw_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- Data exporting was unselected.


-- Dumping structure for table babelwatch.bw_changeset_string
CREATE TABLE IF NOT EXISTS `bw_changeset_string` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `changeset_id` int(10) NOT NULL,
  `string_id` int(10) NOT NULL,
  `action` varchar(1) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `changeset_id_string_id_action` (`changeset_id`,`string_id`,`action`),
  KEY `FK_bw_changeset_string_bw_string` (`string_id`),
  CONSTRAINT `FK_bw_changeset_string_bw_changeset` FOREIGN KEY (`changeset_id`) REFERENCES `bw_changeset` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_bw_changeset_string_bw_string` FOREIGN KEY (`string_id`) REFERENCES `bw_string` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- Data exporting was unselected.


-- Dumping structure for table babelwatch.bw_reference
CREATE TABLE IF NOT EXISTS `bw_reference` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `filepath` text COLLATE utf8_bin NOT NULL,
  `line` int(10) NOT NULL,
  `hash` varchar(64) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- Data exporting was unselected.


-- Dumping structure for table babelwatch.bw_repo
CREATE TABLE IF NOT EXISTS `bw_repo` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8_bin,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- Data exporting was unselected.


-- Dumping structure for table babelwatch.bw_string
CREATE TABLE IF NOT EXISTS `bw_string` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `content` text CHARACTER SET utf8,
  `hash` varchar(64) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- Data exporting was unselected.


-- Dumping structure for table babelwatch.bw_string_ref
CREATE TABLE IF NOT EXISTS `bw_string_ref` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `string_id` int(10) DEFAULT NULL,
  `ref_id` int(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK__bw_string` (`string_id`),
  KEY `FK__bw_reference` (`ref_id`),
  CONSTRAINT `FK__bw_string` FOREIGN KEY (`string_id`) REFERENCES `bw_string` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK__bw_reference` FOREIGN KEY (`ref_id`) REFERENCES `bw_reference` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- Data exporting was unselected.


-- Dumping structure for table babelwatch.bw_user
CREATE TABLE IF NOT EXISTS `bw_user` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- Data exporting was unselected.
/*!40014 SET FOREIGN_KEY_CHECKS=1 */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
