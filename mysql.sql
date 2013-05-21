--
-- Table structure for table `Issues`
--
DROP TABLE IF EXISTS `Issues`;
CREATE TABLE `Issues` (
  `id` varchar(32) NOT NULL,
  `priority` int(11) DEFAULT '1000000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
