-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 15, 2023 at 09:18 PM
-- Server version: 10.3.39-MariaDB
-- PHP Version: 8.1.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `mod_wiretransferbt_transactions`
--

CREATE TABLE `mod_wiretransferbt_transactions` (
  `iban` varchar(64) NOT NULL,
  `debitname` varchar(64) NOT NULL,
  `amount` float NOT NULL,
  `currency` varchar(5) NOT NULL,
  `error` varchar(128) NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MEMORY DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

