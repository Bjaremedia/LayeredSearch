-- phpMyAdmin SQL Dump
-- version 5.0.4
-- https://www.phpmyadmin.net/
--
-- Värd: localhost
-- Tid vid skapande: 12 mars 2021 kl 11:10
-- Serverversion: 10.5.8-MariaDB
-- PHP-version: 7.4.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Databas: `admin_joakim-framework`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `search_v2_filters`
--

CREATE TABLE `search_v2_filters` (
  `id` int(11) NOT NULL,
  `name` tinytext COLLATE utf8_swedish_ci NOT NULL,
  `display_name` tinytext COLLATE utf8_swedish_ci NOT NULL,
  `needle_type` tinytext COLLATE utf8_swedish_ci NOT NULL,
  `return_type` tinytext COLLATE utf8_swedish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- Dumpning av Data i tabell `search_v2_filters`
--

INSERT INTO `search_v2_filters` (`id`, `name`, `display_name`, `needle_type`, `return_type`) VALUES
(1, 'goods-letter', 'Varubrev', 'tracking-number', 'tracking-number'),
(2, 'postage', 'Porto', 'tracking-number', 'tracking-number'),
(3, 'asendia', 'Asendia', 'tracking-number', 'tracking-number'),
(4, 'ups', 'UPS', 'tracking-number', 'tracking-number'),
(5, 'mypack', 'MyPack', 'tracking-number', 'tracking-number'),
(6, 'sello-order-number', 'Sello ordernummer', 'sello-order-number', 'order-number'),
(7, 'sello-order-number-long', 'Sello ordernummer (långt)', 'sello-order-number-long', 'order-number'),
(8, 'dhl', 'DHL', 'tracking-number', 'tracking-number'),
(9, 'gls', 'GLS', 'tracking-number', 'tracking-number'),
(10, 'schenker', 'Schenker', 'tracking-number', 'tracking-number'),
(11, 'budbee', 'BudBee', 'tracking-number', 'tracking-number');

-- --------------------------------------------------------

--
-- Tabellstruktur `search_v2_layers`
--

CREATE TABLE `search_v2_layers` (
  `id` int(11) NOT NULL,
  `function_name` varchar(30) COLLATE utf8_swedish_ci NOT NULL COMMENT 'Klassnamn och huvudbenämning på php filen',
  `active` tinyint(1) NOT NULL,
  `prio` tinyint(4) NOT NULL COMMENT 'Högre siffra = högre prio'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- Dumpning av Data i tabell `search_v2_layers`
--

INSERT INTO `search_v2_layers` (`id`, `function_name`, `active`, `prio`) VALUES
(1, 'StringCase', 1, 100),
(2, 'Sql', 1, 80),
(3, 'InputParams', 1, 90);

-- --------------------------------------------------------

--
-- Tabellstruktur `search_v2_layer_input_params`
--

CREATE TABLE `search_v2_layer_input_params` (
  `id` int(11) NOT NULL,
  `filters_id` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `priority` int(11) NOT NULL DEFAULT 100 COMMENT 'Högre siffra = högre prio',
  `param_key` varchar(60) COLLATE utf8_swedish_ci NOT NULL,
  `match_value` varchar(60) COLLATE utf8_swedish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- Dumpning av Data i tabell `search_v2_layer_input_params`
--

INSERT INTO `search_v2_layer_input_params` (`id`, `filters_id`, `active`, `priority`, `param_key`, `match_value`) VALUES
(1, 1, 1, 100, 'method', 'postenbrev'),
(2, 1, 1, 90, 'method', 'posten'),
(3, 2, 1, 100, 'method', 'postenbrev'),
(4, 5, 1, 100, 'method', 'posten'),
(5, 3, 1, 100, 'method', 'asendiaGermany'),
(6, 4, 1, 100, 'method', 'ups'),
(7, 8, 1, 90, 'method', 'dhl'),
(8, 8, 1, 100, 'method', 'dhlParcelConnect'),
(9, 9, 1, 100, 'method', 'gls'),
(10, 10, 1, 100, 'method', 'schenker'),
(11, 11, 1, 100, 'method', 'budbee');

-- --------------------------------------------------------

--
-- Tabellstruktur `search_v2_layer_sql`
--

CREATE TABLE `search_v2_layer_sql` (
  `id` int(11) NOT NULL,
  `filters_id` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `priority` tinyint(4) NOT NULL DEFAULT 100 COMMENT 'Högre siffra = högre prio',
  `select_pdoquery` varchar(100) COLLATE utf8_swedish_ci NOT NULL,
  `base_pdoquery` tinytext COLLATE utf8_swedish_ci NOT NULL COMMENT '{needle} = replaced with match case for all needles (must be present once). Must not contain OFFSET or LIMIT!!!',
  `needle_column` varchar(60) COLLATE utf8_swedish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- Dumpning av Data i tabell `search_v2_layer_sql`
--

INSERT INTO `search_v2_layer_sql` (`id`, `filters_id`, `active`, `priority`, `select_pdoquery`, `base_pdoquery`, `needle_column`) VALUES
(1, 1, 1, 100, 'SELECT sod.`id` tracking_id, sod.`id_order` order_id', 'FROM `sello_order_deliveries` sod WHERE sod.`method` = \'postenbrev\' AND {needle}', 'sod.`id`'),
(2, 1, 1, 90, 'SELECT sod.`id` tracking_id, sod.`id_order` order_id', 'FROM `sello_order_deliveries` sod WHERE sod.`method` = \'posten\' AND {needle}', 'sod.`id`'),
(3, 2, 1, 100, 'SELECT sod.`id` tracking_id, sod.`id_order` order_id', 'FROM `sello_order_deliveries` sod WHERE sod.`method` = \'postenbrev\' AND {needle}', 'sod.`id`'),
(4, 5, 1, 100, 'SELECT sod.`id` tracking_id, sod.`id_order` order_id', 'FROM `sello_order_deliveries` sod WHERE sod.`method` = \'posten\' AND {needle}', 'sod.`id`'),
(5, 3, 1, 100, 'SELECT sod.`id`  tracking_id, sod.`id_order` order_id', 'FROM `sello_order_deliveries` sod WHERE sod.`method` = \'asendiaGermany\' AND {needle}', 'sod.`id`'),
(6, 4, 1, 100, 'SELECT sod.`id` tracking_id, sod.`id_order` order_id', 'FROM `sello_order_deliveries` sod WHERE sod.`method` = \'ups\' AND {needle}', 'sod.`id`'),
(9, 8, 1, 90, 'SELECT sod.`id` tracking_id, sod.`id_order` order_id', 'FROM `sello_order_deliveries` sod WHERE sod.`method` = \'dhl\' AND {needle}', 'sod.`id`'),
(10, 8, 1, 100, 'SELECT sod.`id` tracking_id, sod.`id_order` order_id', 'FROM `sello_order_deliveries` sod WHERE sod.`method` = \'dhlParcelConnect\' AND {needle}', 'sod.`id`'),
(11, 9, 1, 100, 'SELECT sod.`id` tracking_id, sod.`id_order` order_id', 'FROM `sello_order_deliveries` sod WHERE sod.`method` = \'gls\' AND {needle}', 'sod.`id`'),
(12, 10, 1, 100, 'SELECT sod.`id` tracking_id, sod.`id_order` order_id', 'FROM `sello_order_deliveries` sod WHERE sod.`method` = \'schenker\' AND {needle}', 'sod.`id`'),
(13, 6, 1, 100, 'SELECT soc.`number` order_number, soc.`id` order_id', 'FROM `sello_order_cache` soc WHERE {needle}', 'soc.`number`'),
(14, 7, 1, 100, 'SELECT soc.`number` order_number, soc.`id` order_id', 'FROM `sello_order_cache` soc WHERE {needle}', 'soc.`number`'),
(15, 11, 1, 100, 'SELECT sod.`id` tracking_id, sod.`id_order` order_id', 'FROM `sello_order_deliveries` sod WHERE sod.`method` = \'budbee\' AND {needle}', 'sod.`id`');

-- --------------------------------------------------------

--
-- Tabellstruktur `search_v2_layer_string_case`
--

CREATE TABLE `search_v2_layer_string_case` (
  `id` int(11) NOT NULL,
  `filters_id` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `priority` int(11) NOT NULL DEFAULT 100 COMMENT 'Högre siffra = högre prio',
  `minlength` int(11) NOT NULL,
  `maxlength` int(11) NOT NULL,
  `regex_case` tinytext COLLATE utf8_swedish_ci NOT NULL,
  `regex_replace` varchar(10) COLLATE utf8_swedish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- Dumpning av Data i tabell `search_v2_layer_string_case`
--

INSERT INTO `search_v2_layer_string_case` (`id`, `filters_id`, `active`, `priority`, `minlength`, `maxlength`, `regex_case`, `regex_replace`) VALUES
(1, 1, 1, 100, 13, 13, '/^UI\\d{9}[a-zA-Z]{2}$/', NULL),
(2, 2, 1, 90, 13, 13, '/^UX\\d{9}[a-zA-Z]{2}$/', NULL),
(3, 2, 1, 100, 13, 14, '/^NO_TRACKING_\\d{1,2}$/', NULL),
(5, 3, 1, 100, 13, 13, '/^RL\\d{9}(?!SE$)[a-zA-Z]{2}$/', NULL),
(6, 3, 1, 90, 18, 18, '/^9840\\d{14}$/', NULL),
(7, 4, 1, 100, 18, 18, '/^1Z[a-zA-Z0-9]{6}\\d{10}$/', NULL),
(8, 5, 1, 100, 18, 20, '/^0{0,2}(3735\\d{14})$/', '00$1'),
(9, 5, 1, 100, 13, 13, '/^\\d{11}SE$/', NULL),
(10, 6, 1, 100, 6, 7, '/^\\d{6,7}$/', NULL),
(11, 7, 1, 100, 12, 13, '/^0{4,7}(\\d{5,7})\\d{1}$/', '$1'),
(12, 1, 1, 90, 13, 13, '/^UA\\d{9}[a-zA-Z]{2}$/', NULL),
(13, 1, 1, 90, 13, 13, '/^BM\\d{9}[a-zA-Z]{2}$/', NULL),
(14, 1, 1, 90, 13, 13, '/^AE\\d{9}[a-zA-Z]{2}$/', NULL),
(15, 3, 1, 100, 13, 13, '/^RK\\d{9}(?!SE$)[a-zA-Z]{2}$/', NULL),
(16, 3, 1, 100, 13, 13, '/^LS\\d{9}(?!SE$)[a-zA-Z]{2}$/', NULL),
(17, 3, 1, 100, 13, 13, '/^LF\\d{9}(?!SE$)[a-zA-Z]{2}$/', NULL),
(18, 3, 1, 80, 18, 20, '/^0{0,2}(373325\\d{12})$/', '$1'),
(19, 5, 1, 100, 18, 20, '/^0{0,2}(373323\\d{12})$/', '$1'),
(20, 8, 1, 100, 18, 20, '/^0{0,2}(373323\\d{12})$/', '$1'),
(21, 9, 1, 100, 12, 12, '/^0129\\d{8}$/', NULL),
(22, 10, 1, 100, 18, 20, '/^0{0,2}(373325\\d{12})$/', '$1'),
(23, 11, 1, 100, 18, 20, '/^0{0,2}(373325\\d{12})$/', '$1'),
(24, 11, 1, 90, 10, 10, '/^56\\d{8}$/', NULL),
(25, 2, 1, 80, 11, 11, '/^NO_TRACKING$/', NULL),
(26, 1, 1, 90, 13, 13, '/^LA\\d{9}[a-zA-Z]{2}$/', NULL);

--
-- Index för dumpade tabeller
--

--
-- Index för tabell `search_v2_filters`
--
ALTER TABLE `search_v2_filters`
  ADD PRIMARY KEY (`id`);

--
-- Index för tabell `search_v2_layers`
--
ALTER TABLE `search_v2_layers`
  ADD PRIMARY KEY (`id`);

--
-- Index för tabell `search_v2_layer_input_params`
--
ALTER TABLE `search_v2_layer_input_params`
  ADD PRIMARY KEY (`id`);

--
-- Index för tabell `search_v2_layer_sql`
--
ALTER TABLE `search_v2_layer_sql`
  ADD PRIMARY KEY (`id`);

--
-- Index för tabell `search_v2_layer_string_case`
--
ALTER TABLE `search_v2_layer_string_case`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT för dumpade tabeller
--

--
-- AUTO_INCREMENT för tabell `search_v2_filters`
--
ALTER TABLE `search_v2_filters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT för tabell `search_v2_layers`
--
ALTER TABLE `search_v2_layers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT för tabell `search_v2_layer_input_params`
--
ALTER TABLE `search_v2_layer_input_params`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT för tabell `search_v2_layer_sql`
--
ALTER TABLE `search_v2_layer_sql`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT för tabell `search_v2_layer_string_case`
--
ALTER TABLE `search_v2_layer_string_case`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
