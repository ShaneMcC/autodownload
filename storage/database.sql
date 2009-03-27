-- This table stores when shows have been downloaded so that the automatic
-- downloader knows what it has.
DROP TABLE IF EXISTS `downloaded`;
CREATE TABLE `downloaded` (
  `name` varchar(255) NOT NULL,
  `season` int(11) NOT NULL,
  `episode` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `title` text,
  PRIMARY KEY  (`season`,`episode`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


-- This table stores a selection of shows so that the auto downloader knows what
-- it should be automatically downloading.
DROP TABLE IF EXISTS `shows`;
CREATE TABLE `shows` (
  `name` varchar(255) NOT NULL,
  `automatic` enum('true','false') NOT NULL default 'true',
  `searchstring` text,
  `dirname` text,
  `important` enum('true','false') NOT NULL default 'false',
  `size` int(11) NOT NULL default '400',
  `attributes` text,
  PRIMARY KEY  (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- This table stores aliases for shows.
-- Sometimes shows use a different name on the calendar than on newzbin, this
-- table helps match those together.
CREATE TABLE `aliases` (
  `alias` varchar(255) NOT NULL,
  `show` varchar(255) NOT NULL,
  PRIMARY KEY  (`alias`,`show`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;