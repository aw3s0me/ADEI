-- phpMyAdmin SQL Dump
-- version 2.7.0-pl1
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Erstellungszeit: 21. Juni 2006 um 23:46
-- Server Version: 4.1.11
-- PHP-Version: 4.3.10-16
-- 
-- Datenbank: `zeus71`
-- 

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `blocks`
-- 

CREATE TABLE `blocks` (
  `bid` int(10) NOT NULL default '0',
  `name` varchar(50) collate latin1_general_ci NOT NULL default '',
  `oid` int(10) NOT NULL default '0',
  `oindex` int(10) NOT NULL default '0',
  `length` int(10) NOT NULL default '0',
  `datatype` tinyint(3) NOT NULL default '0',
  `scalefactor` double NOT NULL default '0',
  `itemnames` text collate latin1_general_ci NOT NULL,
  PRIMARY KEY  (`bid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `config`
-- 

CREATE TABLE `config` (
  `facility` varchar(255) collate latin1_general_ci NOT NULL default '',
  `host` varchar(255) collate latin1_general_ci NOT NULL default '',
  `hmis` text collate latin1_general_ci NOT NULL,
  `lclb` tinyblob NOT NULL,
  `port` smallint(5) unsigned NOT NULL default '0',
  `mode` tinyint(4) NOT NULL default '0',
  `password` varchar(20) collate latin1_general_ci NOT NULL default 'wsdb!00',
  PRIMARY KEY  (`facility`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `g2id`
-- 

CREATE TABLE `g2id` (
  `gid` int(10) NOT NULL default '0',
  `id` int(10) NOT NULL default '0',
  `deadband` double NOT NULL default '0',
  `pos` int(10) NOT NULL default '0',
  KEY `gid` (`gid`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `groups`
-- 

CREATE TABLE `groups` (
  `gid` int(10) NOT NULL default '0',
  `name` varchar(100) collate latin1_general_ci NOT NULL default '',
  `comment` text collate latin1_general_ci NOT NULL,
  `grouptype` tinyint(3) NOT NULL default '0',
  `interval` int(10) NOT NULL default '0',
  `mode` tinyint(3) NOT NULL default '0',
  PRIMARY KEY  (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `items`
-- 

CREATE TABLE `items` (
  `iid` int(10) NOT NULL default '0',
  `name` varchar(50) collate latin1_general_ci NOT NULL default '',
  `formular` varchar(255) collate latin1_general_ci NOT NULL default '',
  `inputs` blob NOT NULL,
  PRIMARY KEY  (`iid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `loginfo`
-- 

CREATE TABLE `loginfo` (
  `gid` int(11) NOT NULL default '0',
  `name` varchar(100) NOT NULL default '',
  `comment` text NOT NULL,
  `size` int(10) NOT NULL default '0',
  `itemnames` text NOT NULL,
  PRIMARY KEY  (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `marker`
-- 

CREATE TABLE `marker` (
  `start` double NOT NULL default '0',
  `name` varchar(50) collate latin1_general_ci NOT NULL default '',
  `stop` double NOT NULL default '0',
  `experiment` varchar(50) collate latin1_general_ci NOT NULL default '',
  `text` text collate latin1_general_ci NOT NULL,
  PRIMARY KEY  (`start`),
  KEY `experiment` (`experiment`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `masks`
-- 

CREATE TABLE `masks` (
  `maskid` int(10) NOT NULL default '0',
  `name` varchar(50) collate latin1_general_ci NOT NULL default '',
  `gid` int(10) NOT NULL default '0',
  `mask` blob NOT NULL,
  PRIMARY KEY  (`maskid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `messagelog`
-- 

CREATE TABLE `messagelog` (
  `lid` bigint(20) NOT NULL auto_increment,
  `messageid` int(10) NOT NULL default '0',
  `name` text collate latin1_general_ci NOT NULL,
  `mtype` tinyint(4) NOT NULL default '0',
  `cond` text collate latin1_general_ci NOT NULL,
  `ack` tinyint(4) NOT NULL default '0',
  `come` double NOT NULL default '0',
  `go` double NOT NULL default '0',
  `acked` double NOT NULL default '0',
  PRIMARY KEY  (`lid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=13 ;

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `messages`
-- 

CREATE TABLE `messages` (
  `messageid` int(10) NOT NULL default '0',
  `name` text collate latin1_general_ci NOT NULL,
  `mtype` tinyint(3) NOT NULL default '0',
  `bid` int(10) NOT NULL default '0',
  `bindex` smallint(5) NOT NULL default '0',
  `trigger` tinyint(3) NOT NULL default '0',
  `threshold` double NOT NULL default '0',
  `hyst` double NOT NULL default '0',
  `ack` tinyint(3) NOT NULL default '0',
  `signal` tinyint(3) NOT NULL default '0',
  PRIMARY KEY  (`messageid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `opc`
-- 

CREATE TABLE `opc` (
  `oid` int(10) NOT NULL default '0',
  `name` varchar(50) collate latin1_general_ci NOT NULL default '',
  `hardware` text collate latin1_general_ci NOT NULL,
  `length` int(10) NOT NULL default '0',
  `opcurl` text collate latin1_general_ci NOT NULL,
  `mode` tinyint(3) NOT NULL default '0',
  `must_updat` int(10) NOT NULL default '0',
  PRIMARY KEY  (`oid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

-- 
-- Tabellenstruktur für Tabelle `syslog`
-- 

CREATE TABLE `syslog` (
  `lid` bigint(20) NOT NULL auto_increment,
  `ts` double NOT NULL default '0',
  `source` varchar(250) collate latin1_general_ci NOT NULL default '',
  `message` text collate latin1_general_ci NOT NULL,
  PRIMARY KEY  (`lid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=126 ;
