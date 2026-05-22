-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 22, 2026 at 12:54 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `israphil_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `related_table` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `description`, `related_table`, `related_id`, `ip_address`, `created_at`) VALUES
(1, 2, 'order_status_changed', 'Order 5 changed from pending to delivered', 'orders', 5, NULL, '2026-03-12 14:27:45'),
(2, 2, 'order_status_changed', 'Order 4 changed from pending to confirmed', 'orders', 4, NULL, '2026-03-12 14:27:59'),
(3, 2, 'order_status_changed', 'Order 4 changed from confirmed to delivered', 'orders', 4, NULL, '2026-03-12 14:28:21'),
(4, 2, 'order_status_changed', 'Order 3 changed from pending to delivered', 'orders', 3, NULL, '2026-03-12 15:07:31'),
(5, 2, 'order_status_changed', 'Order 2 changed from pending to delivered', 'orders', 2, NULL, '2026-03-12 15:07:35'),
(6, 2, 'order_status_changed', 'Order 6 changed from pending to delivered', 'orders', 6, NULL, '2026-03-12 15:13:17'),
(7, 2, 'order_status_changed', 'Order 7 changed from pending to confirmed', 'orders', 7, NULL, '2026-03-13 03:42:35'),
(8, 2, 'order_status_changed', 'Order 7 changed from confirmed to cancelled', 'orders', 7, NULL, '2026-03-13 15:30:36'),
(9, 2, 'order_status_changed', 'Order 8 changed from pending to cancelled', 'orders', 8, NULL, '2026-03-13 15:40:43'),
(10, 2, 'order_status_changed', 'Order 9 changed from pending to confirmed', 'orders', 9, NULL, '2026-03-13 15:41:58'),
(11, 2, 'order_status_changed', 'Order 9 changed from confirmed to cancelled', 'orders', 9, NULL, '2026-03-13 15:54:58'),
(12, 2, 'order_status_changed', 'Order 10 changed from pending to confirmed', 'orders', 10, NULL, '2026-03-14 04:13:13'),
(13, 2, 'order_status_changed', 'Order 10 changed from confirmed to out_for_delivery', 'orders', 10, NULL, '2026-03-14 04:13:19'),
(14, 2, 'order_status_changed', 'Order 10 changed from out_for_delivery to cancelled', 'orders', 10, NULL, '2026-03-14 05:08:07'),
(15, 2, 'order_status_changed', 'Order 11 changed from pending to cancelled', 'orders', 11, NULL, '2026-03-14 07:21:59'),
(16, 2, 'order_status_changed', 'Order 12 changed from pending to confirmed', 'orders', 12, NULL, '2026-03-14 10:46:24'),
(17, 2, 'order_status_changed', 'Order 12 changed from confirmed to delivered', 'orders', 12, NULL, '2026-03-14 10:47:45'),
(18, 2, 'order_status_changed', 'Order 13 changed from pending to confirmed', 'orders', 13, NULL, '2026-03-14 10:56:36'),
(19, 2, 'order_status_changed', 'Order 6 changed from delivered to cancelled', 'orders', 6, NULL, '2026-03-14 14:17:05'),
(20, 2, 'order_status_changed', 'Order 6 changed from cancelled to delivered', 'orders', 6, NULL, '2026-03-14 14:17:16'),
(21, 2, 'order_status_changed', 'Order 6 changed from delivered to out_for_delivery', 'orders', 6, NULL, '2026-03-14 14:17:37'),
(22, 2, 'order_status_changed', 'Order 6 changed from out_for_delivery to cancelled', 'orders', 6, NULL, '2026-03-14 14:17:42'),
(23, 2, 'order_status_changed', 'Order 7 changed from cancelled to delivered', 'orders', 7, NULL, '2026-03-15 05:49:47'),
(24, 2, 'order_status_changed', 'Order 10 changed from cancelled to delivered', 'orders', 10, NULL, '2026-03-15 05:49:59'),
(25, 2, 'order_status_changed', 'Order 9 changed from cancelled to delivered', 'orders', 9, NULL, '2026-03-15 05:50:02'),
(26, 2, 'order_status_changed', 'Order 13 changed from confirmed to delivered', 'orders', 13, NULL, '2026-03-15 05:54:36'),
(27, 6, 'order_status_changed', 'Order 14 changed from pending to confirmed', 'orders', 14, NULL, '2026-03-15 05:57:08'),
(28, 2, 'order_status_changed', 'Order 15 changed from pending to confirmed', 'orders', 15, NULL, '2026-03-15 05:58:31'),
(29, 7, 'order_status_changed', 'Order 16 changed from pending to confirmed', 'orders', 16, NULL, '2026-03-15 06:09:56'),
(30, 7, 'order_status_changed', 'Order 17 changed from pending to confirmed', 'orders', 17, NULL, '2026-03-15 06:13:27'),
(31, 6, 'order_status_changed', 'Order 18 changed from pending to confirmed', 'orders', 18, NULL, '2026-03-23 12:54:59'),
(32, 2, 'order_status_changed', 'Order 7 changed from delivered to cancelled', 'orders', 7, NULL, '2026-03-23 13:58:00'),
(33, 2, 'order_status_changed', 'Order 7 changed from cancelled to delivered', 'orders', 7, NULL, '2026-03-23 13:59:26'),
(34, 6, 'order_status_changed', 'Order 14 changed from confirmed to delivered', 'orders', 14, NULL, '2026-03-23 14:24:27'),
(35, 6, 'order_status_changed', 'Order 18 changed from confirmed to pending', 'orders', 18, NULL, '2026-03-23 14:42:50'),
(36, 7, 'order_status_changed', 'Order 16 changed from confirmed to pending', 'orders', 16, NULL, '2026-03-23 14:43:45'),
(37, 6, 'order_status_changed', 'Order 18 changed from pending to confirmed', 'orders', 18, NULL, '2026-03-23 14:48:06'),
(38, 7, 'order_status_changed', 'Order 16 changed from pending to confirmed', 'orders', 16, NULL, '2026-03-23 14:48:14'),
(39, 7, 'order_status_changed', 'Order 16 changed from confirmed to pending', 'orders', 16, NULL, '2026-03-23 14:49:17'),
(40, 6, 'order_status_changed', 'Order 18 changed from confirmed to pending', 'orders', 18, NULL, '2026-03-23 14:50:11'),
(41, 2, 'order_status_changed', 'Order 15 changed from confirmed to delivered', 'orders', 15, NULL, '2026-03-23 14:57:56'),
(42, 7, 'order_status_changed', 'Order 17 changed from confirmed to delivered', 'orders', 17, NULL, '2026-03-23 14:58:00'),
(43, 2, 'order_status_changed', 'Order 19 changed from pending to confirmed', 'orders', 19, NULL, '2026-03-25 14:47:14'),
(44, 2, 'order_status_changed', 'Order 19 changed from confirmed to pending', 'orders', 19, NULL, '2026-03-25 14:50:39'),
(45, 2, 'order_status_changed', 'Order 19 changed from pending to confirmed', 'orders', 19, NULL, '2026-03-25 14:51:37'),
(46, 2, 'order_status_changed', 'Order 19 changed from confirmed to delivered', 'orders', 19, NULL, '2026-03-25 14:51:58'),
(47, 6, 'order_status_changed', 'Order 20 changed from pending to confirmed', 'orders', 20, NULL, '2026-03-25 16:08:32'),
(48, 6, 'order_status_changed', 'Order 21 changed from pending to confirmed', 'orders', 21, NULL, '2026-03-26 14:50:15'),
(49, 6, 'order_status_changed', 'Order 20 changed from confirmed to delivered', 'orders', 20, NULL, '2026-03-26 15:06:53'),
(50, 7, 'order_status_changed', 'Order 16 changed from pending to confirmed', 'orders', 16, NULL, '2026-03-26 15:51:44'),
(51, 6, 'order_status_changed', 'Order 18 changed from pending to confirmed', 'orders', 18, NULL, '2026-03-26 15:52:00'),
(52, 7, 'order_status_changed', 'Order 16 changed from confirmed to pending', 'orders', 16, NULL, '2026-03-26 15:52:25'),
(53, 6, 'order_status_changed', 'Order 21 changed from confirmed to cancelled', 'orders', 21, NULL, '2026-05-07 02:06:13'),
(54, 6, 'order_status_changed', 'Order 18 changed from confirmed to cancelled', 'orders', 18, NULL, '2026-05-07 02:06:30'),
(55, 6, 'order_status_changed', 'Order 22 changed from pending to confirmed', 'orders', 22, NULL, '2026-05-07 04:00:25'),
(56, 6, 'order_status_changed', 'Order 23 changed from pending to confirmed', 'orders', 23, NULL, '2026-05-07 05:25:54'),
(57, 6, 'order_status_changed', 'Order 22 changed from confirmed to cancelled', 'orders', 22, NULL, '2026-05-07 05:29:28'),
(58, 6, 'order_status_changed', 'Order 24 changed from pending to confirmed', 'orders', 24, NULL, '2026-05-07 08:18:22'),
(59, 6, 'order_status_changed', 'Order 24 changed from confirmed to cancelled', 'orders', 24, NULL, '2026-05-08 07:37:43'),
(60, 6, 'order_status_changed', 'Order 23 changed from confirmed to cancelled', 'orders', 23, NULL, '2026-05-08 07:38:02'),
(61, 6, 'order_status_changed', 'Order 25 changed from pending to confirmed', 'orders', 25, NULL, '2026-05-08 08:02:46'),
(62, 6, 'order_status_changed', 'Order 25 changed from confirmed to cancelled', 'orders', 25, NULL, '2026-05-08 08:03:13'),
(63, 7, 'order_status_changed', 'Order 16 changed from pending to cancelled', 'orders', 16, NULL, '2026-05-10 12:49:19'),
(64, 6, 'order_status_changed', 'Order 26 changed from pending to confirmed', 'orders', 26, NULL, '2026-05-10 16:11:52'),
(65, 6, 'order_status_changed', 'Order 26 changed from confirmed to processing', 'orders', 26, NULL, '2026-05-10 16:17:52'),
(66, 6, 'order_status_changed', 'Order 26 changed from processing to out_for_delivery', 'orders', 26, NULL, '2026-05-10 16:18:12'),
(67, 6, 'order_status_changed', 'Order 26 changed from out_for_delivery to delivered', 'orders', 26, NULL, '2026-05-10 16:18:57'),
(68, 6, 'order_status_changed', 'Order 27 changed from pending to confirmed', 'orders', 27, NULL, '2026-05-11 10:29:27'),
(69, 6, 'order_status_changed', 'Order 27 changed from confirmed to out_for_delivery', 'orders', 27, NULL, '2026-05-11 10:41:03'),
(70, 6, 'order_status_changed', 'Order 27 changed from out_for_delivery to delivered', 'orders', 27, NULL, '2026-05-11 10:41:08'),
(71, 6, 'order_status_changed', 'Order 28 changed from pending to confirmed', 'orders', 28, NULL, '2026-05-11 10:47:59'),
(72, 6, 'order_status_changed', 'Order 28 changed from confirmed to processing', 'orders', 28, NULL, '2026-05-11 14:23:28'),
(74, 6, 'order_status_changed', 'Order 28 changed from processing to out_for_delivery', 'orders', 28, NULL, '2026-05-11 14:58:29'),
(75, 6, 'order_status_changed', 'Order 28 changed from out_for_delivery to delivered', 'orders', 28, NULL, '2026-05-11 14:58:56'),
(76, 2, 'order_status_changed', 'Order 35 changed from pending to confirmed', 'orders', 35, NULL, '2026-05-11 15:12:25'),
(77, 2, 'order_status_changed', 'Order 34 changed from pending to confirmed', 'orders', 34, NULL, '2026-05-11 15:12:30'),
(78, 2, 'order_status_changed', 'Order 33 changed from pending to confirmed', 'orders', 33, NULL, '2026-05-11 15:12:33'),
(79, 6, 'order_status_changed', 'Order 31 changed from pending to confirmed', 'orders', 31, NULL, '2026-05-11 15:12:53'),
(80, 6, 'order_status_changed', 'Order 32 changed from pending to confirmed', 'orders', 32, NULL, '2026-05-11 15:12:56'),
(81, 6, 'order_status_changed', 'Order 32 changed from confirmed to processing', 'orders', 32, NULL, '2026-05-11 15:19:27'),
(82, 2, 'order_status_changed', 'Order 33 changed from confirmed to processing', 'orders', 33, NULL, '2026-05-11 15:19:27'),
(83, 2, 'order_status_changed', 'Order 35 changed from confirmed to processing', 'orders', 35, NULL, '2026-05-11 15:20:37'),
(84, 2, 'order_status_changed', 'Order 35 changed from processing to out_for_delivery', 'orders', 35, NULL, '2026-05-11 15:22:58'),
(85, 2, 'order_status_changed', 'Order 35 changed from out_for_delivery to pending', 'orders', 35, NULL, '2026-05-11 15:23:43'),
(86, 2, 'order_status_changed', 'Order 35 changed from pending to confirmed', 'orders', 35, NULL, '2026-05-11 15:26:52'),
(87, 2, 'order_status_changed', 'Order 35 changed from confirmed to delivered', 'orders', 35, NULL, '2026-05-11 16:57:13'),
(88, 6, 'order_status_changed', 'Order 32 changed from processing to delivered', 'orders', 32, NULL, '2026-05-12 10:34:18'),
(89, 2, 'order_status_changed', 'Order 33 changed from processing to delivered', 'orders', 33, NULL, '2026-05-12 10:34:29'),
(90, 2, 'order_status_changed', 'Order 38 changed from pending to confirmed', 'orders', 38, NULL, '2026-05-16 15:49:44'),
(91, 6, 'order_status_changed', 'Order 36 changed from pending to confirmed', 'orders', 36, NULL, '2026-05-16 15:49:47'),
(92, 6, 'order_status_changed', 'Order 37 changed from pending to confirmed', 'orders', 37, NULL, '2026-05-16 15:49:51'),
(93, 2, 'order_status_changed', 'Order 39 changed from pending to confirmed', 'orders', 39, NULL, '2026-05-16 15:49:55'),
(94, 6, 'order_status_changed', 'Order 37 changed from confirmed to processing', 'orders', 37, NULL, '2026-05-16 16:13:36'),
(95, 6, 'order_status_changed', 'Order 36 changed from confirmed to processing', 'orders', 36, NULL, '2026-05-16 16:13:46'),
(96, 2, 'order_status_changed', 'Order 38 changed from confirmed to processing', 'orders', 38, NULL, '2026-05-16 16:13:52'),
(97, 2, 'order_status_changed', 'Order 39 changed from confirmed to processing', 'orders', 39, NULL, '2026-05-16 16:13:52'),
(98, 6, 'order_status_changed', 'Order 37 changed from processing to out_for_delivery', 'orders', 37, NULL, '2026-05-16 16:17:15'),
(99, 6, 'order_status_changed', 'Order 37 changed from out_for_delivery to delivered', 'orders', 37, NULL, '2026-05-16 16:18:16'),
(100, 6, 'order_status_changed', 'Order 36 changed from processing to out_for_delivery', 'orders', 36, NULL, '2026-05-16 16:20:29'),
(101, 6, 'order_status_changed', 'Order 36 changed from out_for_delivery to pending', 'orders', 36, NULL, '2026-05-16 16:21:17'),
(102, 2, 'order_status_changed', 'Order 38 changed from processing to out_for_delivery', 'orders', 38, NULL, '2026-05-16 16:21:40'),
(103, 2, 'order_status_changed', 'Order 39 changed from processing to out_for_delivery', 'orders', 39, NULL, '2026-05-16 16:21:40'),
(104, 2, 'order_status_changed', 'Order 38 changed from out_for_delivery to delivered', 'orders', 38, NULL, '2026-05-16 16:21:45'),
(105, 2, 'order_status_changed', 'Order 39 changed from out_for_delivery to pending', 'orders', 39, NULL, '2026-05-16 16:22:19'),
(106, 2, 'order_status_changed', 'Order 39 changed from pending to cancelled', 'orders', 39, NULL, '2026-05-16 16:23:19'),
(107, 6, 'order_status_changed', 'Order 36 changed from pending to cancelled', 'orders', 36, NULL, '2026-05-16 16:29:17'),
(108, 6, 'order_status_changed', 'Order 40 changed from pending to confirmed', 'orders', 40, NULL, '2026-05-17 13:53:40'),
(109, 6, 'order_status_changed', 'Order 40 changed from confirmed to processing', 'orders', 40, NULL, '2026-05-17 13:56:43'),
(110, 6, 'order_status_changed', 'Order 40 changed from processing to out_for_delivery', 'orders', 40, NULL, '2026-05-17 15:58:12'),
(111, 6, 'order_status_changed', 'Order 40 changed from out_for_delivery to delivered', 'orders', 40, NULL, '2026-05-17 15:58:59'),
(112, 7, 'order_status_changed', 'Order 55 changed from pending to confirmed', 'orders', 55, NULL, '2026-05-17 16:36:08'),
(113, 7, 'order_status_changed', 'Order 54 changed from pending to confirmed', 'orders', 54, NULL, '2026-05-17 16:36:17'),
(114, 7, 'order_status_changed', 'Order 53 changed from pending to confirmed', 'orders', 53, NULL, '2026-05-17 16:36:17'),
(115, 7, 'order_status_changed', 'Order 52 changed from pending to confirmed', 'orders', 52, NULL, '2026-05-17 16:36:17'),
(116, 7, 'order_status_changed', 'Order 51 changed from pending to confirmed', 'orders', 51, NULL, '2026-05-17 16:36:17'),
(117, 7, 'order_status_changed', 'Order 50 changed from pending to confirmed', 'orders', 50, NULL, '2026-05-17 16:36:17'),
(118, 7, 'order_status_changed', 'Order 49 changed from pending to confirmed', 'orders', 49, NULL, '2026-05-17 16:36:17'),
(119, 7, 'order_status_changed', 'Order 48 changed from pending to confirmed', 'orders', 48, NULL, '2026-05-17 16:36:17'),
(120, 7, 'order_status_changed', 'Order 47 changed from pending to confirmed', 'orders', 47, NULL, '2026-05-17 16:36:17'),
(121, 7, 'order_status_changed', 'Order 46 changed from pending to confirmed', 'orders', 46, NULL, '2026-05-17 16:36:18'),
(122, 7, 'order_status_changed', 'Order 45 changed from pending to confirmed', 'orders', 45, NULL, '2026-05-17 16:36:18'),
(123, 7, 'order_status_changed', 'Order 44 changed from pending to confirmed', 'orders', 44, NULL, '2026-05-17 16:36:18'),
(124, 7, 'order_status_changed', 'Order 43 changed from pending to confirmed', 'orders', 43, NULL, '2026-05-17 16:36:18'),
(125, 7, 'order_status_changed', 'Order 42 changed from pending to confirmed', 'orders', 42, NULL, '2026-05-17 16:36:18'),
(126, 7, 'order_status_changed', 'Order 41 changed from pending to confirmed', 'orders', 41, NULL, '2026-05-17 16:36:19'),
(127, 7, 'order_status_changed', 'Order 41 changed from confirmed to processing', 'orders', 41, NULL, '2026-05-17 16:38:27'),
(128, 7, 'order_status_changed', 'Order 42 changed from confirmed to processing', 'orders', 42, NULL, '2026-05-17 16:38:27'),
(129, 7, 'order_status_changed', 'Order 43 changed from confirmed to processing', 'orders', 43, NULL, '2026-05-17 16:38:27'),
(130, 7, 'order_status_changed', 'Order 44 changed from confirmed to processing', 'orders', 44, NULL, '2026-05-17 16:38:55'),
(131, 7, 'order_status_changed', 'Order 45 changed from confirmed to processing', 'orders', 45, NULL, '2026-05-17 16:38:55'),
(132, 7, 'order_status_changed', 'Order 46 changed from confirmed to processing', 'orders', 46, NULL, '2026-05-17 16:38:55'),
(133, 7, 'order_status_changed', 'Order 47 changed from confirmed to processing', 'orders', 47, NULL, '2026-05-17 16:39:01'),
(134, 7, 'order_status_changed', 'Order 48 changed from confirmed to processing', 'orders', 48, NULL, '2026-05-17 16:39:01'),
(135, 7, 'order_status_changed', 'Order 49 changed from confirmed to processing', 'orders', 49, NULL, '2026-05-17 16:39:01'),
(136, 7, 'order_status_changed', 'Order 50 changed from confirmed to processing', 'orders', 50, NULL, '2026-05-17 16:39:05'),
(137, 7, 'order_status_changed', 'Order 51 changed from confirmed to processing', 'orders', 51, NULL, '2026-05-17 16:39:05'),
(138, 7, 'order_status_changed', 'Order 52 changed from confirmed to processing', 'orders', 52, NULL, '2026-05-17 16:39:05'),
(139, 7, 'order_status_changed', 'Order 53 changed from confirmed to processing', 'orders', 53, NULL, '2026-05-17 16:39:10'),
(140, 7, 'order_status_changed', 'Order 54 changed from confirmed to processing', 'orders', 54, NULL, '2026-05-17 16:39:10'),
(141, 7, 'order_status_changed', 'Order 55 changed from confirmed to processing', 'orders', 55, NULL, '2026-05-17 16:39:10'),
(142, 2, 'order_status_changed', 'Order 34 changed from confirmed to processing', 'orders', 34, NULL, '2026-05-17 16:39:17'),
(143, 2, 'order_status_changed', 'Order 34 changed from processing to out_for_delivery', 'orders', 34, NULL, '2026-05-17 16:51:51'),
(144, 6, 'order_status_changed', 'Order 31 changed from confirmed to processing', 'orders', 31, NULL, '2026-05-19 16:32:17'),
(145, 7, 'order_status_changed', 'Order 53 changed from processing to out_for_delivery', 'orders', 53, NULL, '2026-05-19 16:32:40'),
(146, 7, 'order_status_changed', 'Order 54 changed from processing to out_for_delivery', 'orders', 54, NULL, '2026-05-19 16:32:40'),
(147, 7, 'order_status_changed', 'Order 55 changed from processing to out_for_delivery', 'orders', 55, NULL, '2026-05-19 16:32:40'),
(148, 7, 'order_status_changed', 'Order 53 changed from out_for_delivery to delivered', 'orders', 53, NULL, '2026-05-19 16:33:21'),
(149, 7, 'order_status_changed', 'Order 54 changed from out_for_delivery to delivered', 'orders', 54, NULL, '2026-05-19 16:33:27'),
(150, 7, 'order_status_changed', 'Order 55 changed from out_for_delivery to delivered', 'orders', 55, NULL, '2026-05-19 16:33:33'),
(151, 2, 'order_status_changed', 'Order 34 changed from out_for_delivery to delivered', 'orders', 34, NULL, '2026-05-19 16:33:39'),
(152, 7, 'order_status_changed', 'Order 47 changed from processing to out_for_delivery', 'orders', 47, NULL, '2026-05-19 16:35:00'),
(153, 7, 'order_status_changed', 'Order 48 changed from processing to out_for_delivery', 'orders', 48, NULL, '2026-05-19 16:35:00'),
(154, 7, 'order_status_changed', 'Order 49 changed from processing to out_for_delivery', 'orders', 49, NULL, '2026-05-19 16:35:00'),
(155, 7, 'order_status_changed', 'Order 50 changed from processing to out_for_delivery', 'orders', 50, NULL, '2026-05-19 16:35:07'),
(156, 7, 'order_status_changed', 'Order 51 changed from processing to out_for_delivery', 'orders', 51, NULL, '2026-05-19 16:35:07'),
(157, 7, 'order_status_changed', 'Order 52 changed from processing to out_for_delivery', 'orders', 52, NULL, '2026-05-19 16:35:07'),
(158, 7, 'order_status_changed', 'Order 47 changed from out_for_delivery to pending', 'orders', 47, NULL, '2026-05-19 16:35:35'),
(159, 7, 'order_status_changed', 'Order 48 changed from out_for_delivery to pending', 'orders', 48, NULL, '2026-05-19 16:35:50'),
(160, 7, 'order_status_changed', 'Order 49 changed from out_for_delivery to pending', 'orders', 49, NULL, '2026-05-19 16:36:03'),
(161, 7, 'order_status_changed', 'Order 50 changed from out_for_delivery to pending', 'orders', 50, NULL, '2026-05-19 16:36:21'),
(162, 7, 'order_status_changed', 'Order 51 changed from out_for_delivery to pending', 'orders', 51, NULL, '2026-05-19 16:36:32'),
(163, 7, 'order_status_changed', 'Order 52 changed from out_for_delivery to pending', 'orders', 52, NULL, '2026-05-19 16:36:42'),
(164, 7, 'order_status_changed', 'Order 52 changed from pending to confirmed', 'orders', 52, NULL, '2026-05-19 16:50:18'),
(165, 7, 'order_status_changed', 'Order 51 changed from pending to confirmed', 'orders', 51, NULL, '2026-05-19 16:50:19'),
(166, 7, 'order_status_changed', 'Order 50 changed from pending to confirmed', 'orders', 50, NULL, '2026-05-19 16:50:19'),
(167, 7, 'order_status_changed', 'Order 49 changed from pending to confirmed', 'orders', 49, NULL, '2026-05-19 16:50:19'),
(168, 7, 'order_status_changed', 'Order 48 changed from pending to confirmed', 'orders', 48, NULL, '2026-05-19 16:50:19'),
(169, 7, 'order_status_changed', 'Order 47 changed from pending to confirmed', 'orders', 47, NULL, '2026-05-19 16:50:19'),
(170, 7, 'order_status_changed', 'Order 47 changed from confirmed to processing', 'orders', 47, NULL, '2026-05-19 16:52:23'),
(171, 7, 'order_status_changed', 'Order 48 changed from confirmed to processing', 'orders', 48, NULL, '2026-05-19 16:52:23'),
(172, 7, 'order_status_changed', 'Order 49 changed from confirmed to processing', 'orders', 49, NULL, '2026-05-19 16:52:23'),
(173, 7, 'order_status_changed', 'Order 50 changed from confirmed to processing', 'orders', 50, NULL, '2026-05-19 16:52:31'),
(174, 7, 'order_status_changed', 'Order 51 changed from confirmed to processing', 'orders', 51, NULL, '2026-05-19 16:52:31'),
(175, 7, 'order_status_changed', 'Order 52 changed from confirmed to processing', 'orders', 52, NULL, '2026-05-19 16:52:31'),
(176, 7, 'order_status_changed', 'Order 47 changed from processing to out_for_delivery', 'orders', 47, NULL, '2026-05-19 16:53:44'),
(177, 7, 'order_status_changed', 'Order 48 changed from processing to out_for_delivery', 'orders', 48, NULL, '2026-05-19 16:53:44'),
(178, 7, 'order_status_changed', 'Order 49 changed from processing to out_for_delivery', 'orders', 49, NULL, '2026-05-19 16:53:44'),
(179, 7, 'order_status_changed', 'Order 50 changed from processing to out_for_delivery', 'orders', 50, NULL, '2026-05-19 16:53:47'),
(180, 7, 'order_status_changed', 'Order 51 changed from processing to out_for_delivery', 'orders', 51, NULL, '2026-05-19 16:53:47'),
(181, 7, 'order_status_changed', 'Order 52 changed from processing to out_for_delivery', 'orders', 52, NULL, '2026-05-19 16:53:47'),
(182, 7, 'order_status_changed', 'Order 47 changed from out_for_delivery to delivered', 'orders', 47, NULL, '2026-05-19 16:54:00'),
(183, 7, 'order_status_changed', 'Order 48 changed from out_for_delivery to delivered', 'orders', 48, NULL, '2026-05-19 16:54:06'),
(184, 7, 'order_status_changed', 'Order 49 changed from out_for_delivery to delivered', 'orders', 49, NULL, '2026-05-19 16:54:14'),
(185, 7, 'order_status_changed', 'Order 50 changed from out_for_delivery to delivered', 'orders', 50, NULL, '2026-05-19 16:54:24'),
(186, 7, 'order_status_changed', 'Order 51 changed from out_for_delivery to delivered', 'orders', 51, NULL, '2026-05-19 16:54:31'),
(187, 7, 'order_status_changed', 'Order 52 changed from out_for_delivery to delivered', 'orders', 52, NULL, '2026-05-19 16:54:36'),
(188, 7, 'order_status_changed', 'Order 41 changed from processing to out_for_delivery', 'orders', 41, NULL, '2026-05-19 16:57:23'),
(189, 7, 'order_status_changed', 'Order 42 changed from processing to out_for_delivery', 'orders', 42, NULL, '2026-05-19 16:57:23'),
(190, 7, 'order_status_changed', 'Order 43 changed from processing to out_for_delivery', 'orders', 43, NULL, '2026-05-19 16:57:23'),
(191, 7, 'order_status_changed', 'Order 44 changed from processing to out_for_delivery', 'orders', 44, NULL, '2026-05-19 16:57:26'),
(192, 7, 'order_status_changed', 'Order 45 changed from processing to out_for_delivery', 'orders', 45, NULL, '2026-05-19 16:57:26'),
(193, 7, 'order_status_changed', 'Order 46 changed from processing to out_for_delivery', 'orders', 46, NULL, '2026-05-19 16:57:26'),
(194, 6, 'order_status_changed', 'Order 31 changed from processing to out_for_delivery', 'orders', 31, NULL, '2026-05-19 16:57:29'),
(195, 6, 'order_status_changed', 'Order 31 changed from out_for_delivery to pending', 'orders', 31, NULL, '2026-05-19 17:10:03'),
(196, 7, 'order_status_changed', 'Order 41 changed from out_for_delivery to delivered', 'orders', 41, NULL, '2026-05-19 17:10:16'),
(197, 7, 'order_status_changed', 'Order 42 changed from out_for_delivery to delivered', 'orders', 42, NULL, '2026-05-19 17:10:23'),
(198, 7, 'order_status_changed', 'Order 43 changed from out_for_delivery to delivered', 'orders', 43, NULL, '2026-05-19 17:10:37'),
(199, 7, 'order_status_changed', 'Order 44 changed from out_for_delivery to delivered', 'orders', 44, NULL, '2026-05-19 17:11:08'),
(200, 7, 'order_status_changed', 'Order 45 changed from out_for_delivery to delivered', 'orders', 45, NULL, '2026-05-19 17:11:21'),
(201, 7, 'order_status_changed', 'Order 46 changed from out_for_delivery to delivered', 'orders', 46, NULL, '2026-05-19 17:11:34'),
(202, 6, 'order_status_changed', 'Order 31 changed from pending to cancelled', 'orders', 31, NULL, '2026-05-19 17:18:43'),
(203, 1, 'order_cancelled', 'Order cancelled by admin.', 'orders', 31, '::1', '2026-05-19 17:18:44'),
(204, 7, 'order_status_changed', 'Order 56 changed from pending to confirmed', 'orders', 56, NULL, '2026-05-22 05:11:24'),
(205, 7, 'order_status_changed', 'Order 56 changed from confirmed to processing', 'orders', 56, NULL, '2026-05-22 05:12:44'),
(206, 7, 'order_status_changed', 'Order 56 changed from processing to out_for_delivery', 'orders', 56, NULL, '2026-05-22 05:14:30'),
(207, 7, 'order_status_changed', 'Order 56 changed from out_for_delivery to delivered', 'orders', 56, NULL, '2026-05-22 05:14:46'),
(208, 6, 'order_status_changed', 'Order 57 changed from pending to cancelled', 'orders', 57, NULL, '2026-05-22 05:27:53'),
(209, 6, 'order_cancelled', 'Order cancelled by customer.', 'orders', 57, '::1', '2026-05-22 05:27:53'),
(210, 6, 'order_status_changed', 'Order 58 changed from pending to cancelled', 'orders', 58, NULL, '2026-05-22 05:29:53'),
(211, 6, 'order_cancelled', 'Order cancelled by customer.', 'orders', 58, '::1', '2026-05-22 05:29:53'),
(212, 6, 'order_status_changed', 'Order 59 changed from pending to cancelled', 'orders', 59, NULL, '2026-05-22 05:35:14'),
(213, 6, 'order_cancelled', 'Order cancelled by customer.', 'orders', 59, '::1', '2026-05-22 05:35:14'),
(214, 6, 'order_status_changed', 'Order 61 changed from pending to cancelled', 'orders', 61, NULL, '2026-05-22 05:52:53'),
(215, 6, 'order_cancelled', 'Order cancelled by customer.', 'orders', 61, '::1', '2026-05-22 05:52:53'),
(216, 6, 'order_status_changed', 'Order 60 changed from pending to confirmed', 'orders', 60, NULL, '2026-05-22 06:21:04'),
(217, 6, 'order_status_changed', 'Order 60 changed from confirmed to processing', 'orders', 60, NULL, '2026-05-22 06:22:58'),
(218, 6, 'order_status_changed', 'Order 60 changed from processing to out_for_delivery', 'orders', 60, NULL, '2026-05-22 06:24:00'),
(219, 6, 'order_status_changed', 'Order 60 changed from out_for_delivery to delivered', 'orders', 60, NULL, '2026-05-22 06:24:19'),
(220, 6, 'order_status_changed', 'Order 62 changed from pending to confirmed', 'orders', 62, NULL, '2026-05-22 10:20:03'),
(221, 6, 'order_status_changed', 'Order 62 changed from confirmed to processing', 'orders', 62, NULL, '2026-05-22 10:21:38'),
(222, 6, 'order_status_changed', 'Order 62 changed from processing to out_for_delivery', 'orders', 62, NULL, '2026-05-22 10:41:43'),
(223, 6, 'order_status_changed', 'Order 62 changed from out_for_delivery to delivered', 'orders', 62, NULL, '2026-05-22 10:42:05');

-- --------------------------------------------------------

--
-- Table structure for table `customer_delivery_addresses`
--

CREATE TABLE `customer_delivery_addresses` (
  `address_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `label` varchar(80) NOT NULL DEFAULT 'Delivery Address',
  `address` text NOT NULL,
  `street_address` text DEFAULT NULL,
  `barangay` varchar(120) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `province` varchar(120) DEFAULT NULL,
  `service_area_id` int(11) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_delivery_addresses`
--

INSERT INTO `customer_delivery_addresses` (`address_id`, `customer_id`, `label`, `address`, `street_address`, `barangay`, `city`, `province`, `service_area_id`, `is_default`, `created_at`, `updated_at`) VALUES
(2, 6, 'condo', 'basta building malaki, Calobaoan, San Carlos City, Pangasinan', 'basta building malaki', 'Calobaoan', 'San Carlos City', 'Pangasinan', 31, 0, '2026-05-07 05:25:53', '2026-05-10 16:13:03'),
(4, 2, 'Main Delivery Address', '62b, Salavante, Urbiztondo, Pangasinan', '62b', 'Salavante', 'Urbiztondo', 'Pangasinan', 24, 1, '2026-05-08 09:06:19', '2026-05-11 11:06:50'),
(5, 6, 'michaels home', 'bayaket street, Turac, San Carlos City, Pangasinan', 'bayaket street', 'Turac', 'San Carlos City', 'Pangasinan', 26, 1, '2026-05-10 16:11:51', '2026-05-10 16:13:15'),
(6, 8, 'Main Delivery Address', 'depths of hell, Real, Urbiztondo, Pangasinan', 'depths of hell', 'Real', 'Urbiztondo', 'Pangasinan', 21, 1, '2026-05-10 16:28:06', '2026-05-10 16:28:06'),
(7, 2, 'Mama\'s House', 'hermosa street, Tebag, San Carlos City, Pangasinan', 'hermosa street', 'Tebag', 'San Carlos City', 'Pangasinan', 33, 0, '2026-05-11 15:11:02', '2026-05-11 15:11:02'),
(8, 7, 'Main Delivery Address', 'tabi ng simbahan, Malacanang, San Carlos City, Pangasinan', 'tabi ng simbahan', 'Malacanang', 'San Carlos City', 'Pangasinan', 35, 1, '2026-05-17 16:13:35', '2026-05-17 16:24:29');

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `delivery_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivered_at` timestamp NULL DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `delivery_status` enum('assigned','in_transit','delivered','failed','returned','cancelled') DEFAULT 'assigned',
  `proof_of_delivery` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`delivery_id`, `order_id`, `staff_id`, `assigned_by`, `assigned_at`, `delivered_at`, `delivery_notes`, `delivery_status`, `proof_of_delivery`) VALUES
(1, 7, 3, 1, '2026-03-13 04:42:00', '2026-03-15 05:49:46', NULL, 'delivered', NULL),
(2, 9, 3, 1, '2026-03-13 15:41:58', '2026-03-15 05:50:02', NULL, 'delivered', NULL),
(3, 10, 3, 1, '2026-03-14 04:13:13', '2026-03-15 05:49:58', NULL, 'delivered', NULL),
(4, 12, 4, NULL, '2026-03-14 10:46:24', '2026-03-14 10:47:45', NULL, 'delivered', NULL),
(5, 13, 4, NULL, '2026-03-15 05:46:21', '2026-03-15 05:54:36', NULL, 'delivered', NULL),
(6, 14, 4, NULL, '2026-03-15 05:57:08', '2026-03-23 14:24:27', NULL, 'delivered', NULL),
(7, 15, 5, NULL, '2026-03-15 05:58:31', '2026-03-23 14:57:56', NULL, 'delivered', NULL),
(9, 17, 5, NULL, '2026-03-15 06:13:27', '2026-03-23 14:58:00', NULL, 'delivered', NULL),
(11, 18, 4, 1, '2026-03-23 14:48:06', '2026-03-23 14:50:11', NULL, 'returned', NULL),
(12, 16, 4, 1, '2026-03-23 14:48:14', NULL, 'idk', 'returned', NULL),
(13, 19, 5, 1, '2026-03-25 14:51:42', '2026-03-25 14:51:58', 'rejected by the customer', 'delivered', NULL),
(14, 20, 3, NULL, '2026-03-25 16:08:31', '2026-03-26 15:06:53', NULL, 'delivered', NULL),
(15, 21, 5, 1, '2026-03-26 15:12:22', NULL, NULL, 'returned', NULL),
(16, 22, 3, NULL, '2026-05-07 04:00:25', NULL, NULL, 'returned', NULL),
(17, 23, 4, NULL, '2026-05-07 05:25:54', NULL, NULL, 'returned', NULL),
(18, 24, 3, NULL, '2026-05-07 08:18:21', NULL, NULL, 'returned', NULL),
(19, 25, 3, NULL, '2026-05-08 08:02:45', NULL, NULL, 'returned', NULL),
(20, 26, 3, NULL, '2026-05-10 16:11:52', '2026-05-10 16:18:57', NULL, 'delivered', NULL),
(21, 27, 3, NULL, '2026-05-11 10:29:27', '2026-05-11 10:41:08', NULL, 'delivered', NULL),
(22, 28, 4, 1, '2026-05-11 14:23:27', '2026-05-11 14:58:56', NULL, 'delivered', NULL),
(24, 32, 5, 1, '2026-05-11 15:19:27', '2026-05-12 10:34:18', NULL, 'delivered', NULL),
(25, 33, 5, 1, '2026-05-11 15:19:27', '2026-05-12 10:34:29', NULL, 'delivered', NULL),
(26, 35, 3, 1, '2026-05-11 15:20:37', '2026-05-11 16:57:13', 'customer is not currently home', 'delivered', NULL),
(27, 37, 3, 1, '2026-05-16 16:13:36', '2026-05-16 16:18:16', NULL, 'delivered', NULL),
(28, 36, 4, 1, '2026-05-16 16:13:46', NULL, 'wala syang gallon pamalit', 'returned', NULL),
(29, 38, 5, 1, '2026-05-16 16:13:52', '2026-05-16 16:21:45', NULL, 'delivered', NULL),
(30, 39, 5, 1, '2026-05-16 16:13:52', NULL, 'napindot lang ng anak nya boss pinabalik', 'returned', NULL),
(31, 40, 5, 1, '2026-05-17 13:56:43', '2026-05-17 15:58:59', NULL, 'delivered', NULL),
(32, 41, 5, 1, '2026-05-17 16:38:27', '2026-05-19 17:10:16', NULL, 'delivered', NULL),
(33, 42, 5, 1, '2026-05-17 16:38:27', '2026-05-19 17:10:23', NULL, 'delivered', NULL),
(34, 43, 5, 1, '2026-05-17 16:38:27', '2026-05-19 17:10:37', NULL, 'delivered', NULL),
(35, 44, 5, 1, '2026-05-17 16:38:54', '2026-05-19 17:11:07', NULL, 'delivered', NULL),
(36, 45, 5, 1, '2026-05-17 16:38:55', '2026-05-19 17:11:21', NULL, 'delivered', NULL),
(37, 46, 5, 1, '2026-05-17 16:38:55', '2026-05-19 17:11:33', NULL, 'delivered', NULL),
(38, 47, 4, 1, '2026-05-19 16:52:23', '2026-05-19 16:54:00', 'the gallon is broken', 'delivered', NULL),
(39, 48, 4, 1, '2026-05-19 16:52:23', '2026-05-19 16:54:06', 'the order is over due', 'delivered', NULL),
(40, 49, 4, 1, '2026-05-19 16:52:23', '2026-05-19 16:54:14', 'the order is overdue', 'delivered', NULL),
(41, 50, 4, 1, '2026-05-19 16:52:31', '2026-05-19 16:54:24', 'i got flat tires', 'delivered', NULL),
(42, 51, 4, 1, '2026-05-19 16:52:31', '2026-05-19 16:54:31', 'i got flat tires', 'delivered', NULL),
(43, 52, 4, 1, '2026-05-19 16:52:31', '2026-05-19 16:54:36', 'i got flat tires', 'delivered', NULL),
(44, 53, 3, 1, '2026-05-17 16:39:10', '2026-05-19 16:33:21', NULL, 'delivered', NULL),
(45, 54, 3, 1, '2026-05-17 16:39:10', '2026-05-19 16:33:27', NULL, 'delivered', NULL),
(46, 55, 3, 1, '2026-05-17 16:39:10', '2026-05-19 16:33:33', NULL, 'delivered', NULL),
(47, 34, 3, 1, '2026-05-17 16:39:17', '2026-05-19 16:33:39', NULL, 'delivered', NULL),
(48, 31, 5, 1, '2026-05-19 16:32:17', NULL, 'the customer doesnt accept the order, fro some reasons that she doesnt want to disclose!', 'cancelled', NULL),
(49, 56, 3, 1, '2026-05-22 05:12:44', '2026-05-22 05:14:46', NULL, 'delivered', NULL),
(50, 60, 3, 1, '2026-05-22 06:22:58', '2026-05-22 06:24:19', NULL, 'delivered', NULL),
(51, 62, 3, 1, '2026-05-22 10:21:38', '2026-05-22 10:42:05', NULL, 'delivered', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `delivery_batches`
--

CREATE TABLE `delivery_batches` (
  `batch_id` int(11) NOT NULL,
  `batch_code` varchar(40) DEFAULT NULL,
  `batch_date` date NOT NULL,
  `zone_code` varchar(40) DEFAULT NULL,
  `zone_name` varchar(160) DEFAULT NULL,
  `batch_type` enum('normal','merged','underfilled') NOT NULL DEFAULT 'normal',
  `capacity_limit_units` tinyint(3) UNSIGNED NOT NULL DEFAULT 16,
  `used_capacity_units` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `batch_status` enum('draft','confirmed','assigned','in_transit','completed','cancelled') NOT NULL DEFAULT 'draft',
  `staff_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `confirmed_by` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_batches`
--

INSERT INTO `delivery_batches` (`batch_id`, `batch_code`, `batch_date`, `zone_code`, `zone_name`, `batch_type`, `capacity_limit_units`, `used_capacity_units`, `batch_status`, `staff_id`, `created_by`, `confirmed_by`, `assigned_by`, `confirmed_at`, `assigned_at`, `started_at`, `completed_at`, `cancelled_at`, `notes`, `created_at`, `updated_at`) VALUES
(5, 'BAT-20260511-SCC-02-0005', '2026-05-11', 'SCC-02', 'San Carlos West / Northwest Route', 'underfilled', 16, 10, 'completed', 4, 1, 1, 1, '2026-05-11 14:23:10', '2026-05-11 14:23:28', '2026-05-11 14:58:30', '2026-05-11 14:58:56', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-11 14:16:47', '2026-05-11 14:58:56'),
(7, 'BAT-20260511-URB-01-0007', '2026-05-11', 'URB-01', 'Urbiztondo Side Route', 'underfilled', 16, 0, 'cancelled', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-11 15:14:58', 'System-generated draft below full capacity. Admin may merge or edit before confirming.\nCancelled because all draft orders were removed.', '2026-05-11 15:13:50', '2026-05-11 15:14:58'),
(8, 'BAT-20260511-SCC-01-0008', '2026-05-11', 'SCC-01', 'San Carlos North Route', 'merged', 16, 10, 'completed', 5, 1, 1, 1, '2026-05-11 15:18:52', '2026-05-11 15:19:27', '2026-05-12 10:34:18', '2026-05-12 10:34:30', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-11 15:13:50', '2026-05-12 10:34:30'),
(9, 'BAT-20260511-SCC-02-0009', '2026-05-11', 'SCC-02', 'San Carlos West / Northwest Route', 'underfilled', 16, 0, 'cancelled', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-11 15:16:47', 'System-generated draft below full capacity. Admin may merge or edit before confirming.\nCancelled because all draft orders were removed.', '2026-05-11 15:13:51', '2026-05-11 15:16:47'),
(10, 'BAT-20260511-SCC-02-0010', '2026-05-11', 'SCC-02', 'San Carlos West / Northwest Route', 'underfilled', 16, 10, 'completed', 3, 1, 1, 1, '2026-05-11 15:20:21', '2026-05-11 15:20:38', '2026-05-11 15:22:58', '2026-05-11 15:23:43', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-11 15:19:36', '2026-05-11 15:23:43'),
(11, 'BAT-20260516-URB-01-0011', '2026-05-16', 'URB-01', 'Urbiztondo Side Route', 'underfilled', 16, 2, 'cancelled', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-16 16:09:47', 'System-generated draft below full capacity. Admin may merge or edit before confirming.\nCancelled during admin review.', '2026-05-16 16:08:35', '2026-05-16 16:09:47'),
(12, 'BAT-20260516-SCC-01-0012', '2026-05-16', 'SCC-01', 'San Carlos North Route', 'normal', 16, 16, 'completed', 3, 1, 1, 1, '2026-05-16 16:12:03', '2026-05-16 16:13:36', '2026-05-16 16:17:15', '2026-05-16 16:18:17', NULL, 'System-generated draft from active queue.', '2026-05-16 16:08:36', '2026-05-16 16:18:17'),
(13, 'BAT-20260516-SCC-02-0013', '2026-05-16', 'SCC-02', 'San Carlos West / Northwest Route', 'normal', 16, 16, 'completed', 4, 1, 1, 1, '2026-05-16 16:12:10', '2026-05-16 16:13:46', '2026-05-16 16:20:29', '2026-05-16 16:21:17', NULL, 'System-generated draft from active queue.', '2026-05-16 16:08:36', '2026-05-16 16:21:17'),
(14, 'BAT-20260516-SCC-02-0014', '2026-05-16', 'SCC-02', 'San Carlos West / Northwest Route', 'merged', 16, 12, 'completed', 5, 1, 1, 1, '2026-05-16 16:12:28', '2026-05-16 16:13:52', '2026-05-16 16:21:40', '2026-05-16 16:22:19', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-16 16:08:36', '2026-05-16 16:22:19'),
(15, 'BAT-20260517-SCC-01-0015', '2026-05-17', 'SCC-01', 'San Carlos North Route', 'underfilled', 16, 5, 'completed', 5, 1, 1, 1, '2026-05-17 13:54:57', '2026-05-17 13:56:43', '2026-05-17 15:58:12', '2026-05-17 15:58:59', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-17 13:54:47', '2026-05-17 15:58:59'),
(16, 'BAT-20260517-URB-01-0016', '2026-05-17', 'URB-01', 'Urbiztondo Side Route', 'underfilled', 16, 5, 'completed', 3, 1, 1, 1, '2026-05-17 16:38:15', '2026-05-17 16:39:17', '2026-05-17 16:51:51', '2026-05-19 16:33:39', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-17 16:36:46', '2026-05-19 16:33:39'),
(17, 'BAT-20260517-UNZONED-0017', '2026-05-17', 'UNZONED', 'Unzoned delivery area', 'underfilled', 16, 15, 'completed', 5, 1, 1, 1, '2026-05-17 16:37:39', '2026-05-17 16:38:27', '2026-05-19 16:57:23', '2026-05-19 17:10:38', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-17 16:36:46', '2026-05-19 17:10:38'),
(18, 'BAT-20260517-UNZONED-0018', '2026-05-17', 'UNZONED', 'Unzoned delivery area', 'underfilled', 16, 15, 'completed', 5, 1, 1, 1, '2026-05-17 16:37:46', '2026-05-17 16:38:55', '2026-05-19 16:57:26', '2026-05-19 17:11:37', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-17 16:36:47', '2026-05-19 17:11:37'),
(19, 'BAT-20260517-UNZONED-0019', '2026-05-17', 'UNZONED', 'Unzoned delivery area', 'underfilled', 16, 15, 'completed', 4, 1, 1, 1, '2026-05-17 16:37:53', '2026-05-17 16:39:01', '2026-05-19 16:35:00', '2026-05-19 16:36:03', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-17 16:36:47', '2026-05-19 16:36:03'),
(20, 'BAT-20260517-UNZONED-0020', '2026-05-17', 'UNZONED', 'Unzoned delivery area', 'underfilled', 16, 15, 'completed', 4, 1, 1, 1, '2026-05-17 16:37:59', '2026-05-17 16:39:05', '2026-05-19 16:35:07', '2026-05-19 16:36:42', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-17 16:36:47', '2026-05-19 16:36:42'),
(21, 'BAT-20260517-UNZONED-0021', '2026-05-17', 'UNZONED', 'Unzoned delivery area', 'underfilled', 16, 15, 'completed', 3, 1, 1, 1, '2026-05-17 16:38:06', '2026-05-17 16:39:10', '2026-05-19 16:32:40', '2026-05-19 16:33:33', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-17 16:36:47', '2026-05-19 16:33:33'),
(22, 'BAT-20260519-SCC-02-0022', '2026-05-19', 'SCC-02', 'San Carlos West / Northwest Route', 'underfilled', 16, 10, 'completed', 5, 1, 1, 1, '2026-05-19 16:32:01', '2026-05-19 16:32:17', '2026-05-19 16:57:29', '2026-05-19 17:10:04', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-19 16:31:40', '2026-05-19 17:10:04'),
(23, 'BAT-20260519-UNZONED-0023', '2026-05-19', 'UNZONED', 'Unzoned delivery area', 'underfilled', 16, 15, 'completed', 4, 1, 1, 1, '2026-05-19 16:52:06', '2026-05-19 16:52:23', '2026-05-19 16:53:44', '2026-05-19 16:54:14', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-19 16:51:07', '2026-05-19 16:54:14'),
(24, 'BAT-20260519-UNZONED-0024', '2026-05-19', 'UNZONED', 'Unzoned delivery area', 'underfilled', 16, 15, 'completed', 4, 1, 1, 1, '2026-05-19 16:52:10', '2026-05-19 16:52:31', '2026-05-19 16:53:47', '2026-05-19 16:54:36', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-19 16:51:07', '2026-05-19 16:54:36'),
(25, 'BAT-20260522-SCC-02-0025', '2026-05-22', 'SCC-02', 'San Carlos West / Northwest Route', 'normal', 16, 16, 'completed', 3, 1, 1, 1, '2026-05-22 05:12:34', '2026-05-22 05:12:44', '2026-05-22 05:14:30', '2026-05-22 05:14:46', NULL, 'System-generated draft from active queue.', '2026-05-22 05:11:41', '2026-05-22 05:14:46'),
(26, 'BAT-20260522-SCC-01-0026', '2026-05-22', 'SCC-01', 'San Carlos North Route', 'underfilled', 16, 5, 'completed', 3, 1, 1, 1, '2026-05-22 06:22:46', '2026-05-22 06:22:58', '2026-05-22 06:24:00', '2026-05-22 06:24:19', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-22 06:22:26', '2026-05-22 06:24:19'),
(27, 'BAT-20260522-SCC-01-0027', '2026-05-22', 'SCC-01', 'San Carlos North Route', 'underfilled', 16, 10, 'completed', 3, 1, 1, 1, '2026-05-22 10:20:54', '2026-05-22 10:21:38', '2026-05-22 10:41:44', '2026-05-22 10:42:05', NULL, 'System-generated draft below full capacity. Admin may merge or edit before confirming.', '2026-05-22 10:20:20', '2026-05-22 10:42:05');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_batch_items`
--

CREATE TABLE `delivery_batch_items` (
  `batch_item_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `capacity_units` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `item_status` enum('active','removed') NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_batch_items`
--

INSERT INTO `delivery_batch_items` (`batch_item_id`, `batch_id`, `order_id`, `capacity_units`, `item_status`, `sort_order`, `created_at`, `updated_at`) VALUES
(6, 5, 28, 10, 'active', 1, '2026-05-11 14:16:47', '2026-05-11 14:16:47'),
(8, 7, 33, 5, 'removed', 1, '2026-05-11 15:13:50', '2026-05-11 15:14:57'),
(9, 8, 32, 5, 'active', 1, '2026-05-11 15:13:51', '2026-05-11 15:13:51'),
(10, 9, 35, 10, 'removed', 1, '2026-05-11 15:13:51', '2026-05-11 15:16:47'),
(11, 8, 33, 5, 'active', 4, '2026-05-11 15:15:39', '2026-05-11 15:18:05'),
(12, 8, 35, 10, 'removed', 3, '2026-05-11 15:17:03', '2026-05-11 15:17:51'),
(14, 10, 35, 10, 'active', 1, '2026-05-11 15:19:37', '2026-05-11 15:19:37'),
(15, 11, 39, 2, 'active', 1, '2026-05-16 16:08:36', '2026-05-16 16:08:36'),
(16, 12, 37, 16, 'active', 1, '2026-05-16 16:08:36', '2026-05-16 16:08:36'),
(17, 13, 36, 16, 'active', 1, '2026-05-16 16:08:36', '2026-05-16 16:08:36'),
(18, 14, 38, 10, 'active', 1, '2026-05-16 16:08:36', '2026-05-16 16:08:36'),
(19, 14, 39, 2, 'active', 2, '2026-05-16 16:10:14', '2026-05-16 16:10:14'),
(20, 15, 40, 5, 'active', 1, '2026-05-17 13:54:47', '2026-05-17 13:54:47'),
(21, 16, 34, 5, 'active', 1, '2026-05-17 16:36:46', '2026-05-17 16:36:46'),
(22, 17, 41, 5, 'active', 1, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(23, 17, 42, 5, 'active', 2, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(24, 17, 43, 5, 'active', 3, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(25, 18, 44, 5, 'active', 1, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(26, 18, 45, 5, 'active', 2, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(27, 18, 46, 5, 'active', 3, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(28, 19, 47, 5, 'active', 1, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(29, 19, 48, 5, 'active', 2, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(30, 19, 49, 5, 'active', 3, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(31, 20, 50, 5, 'active', 1, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(32, 20, 51, 5, 'active', 2, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(33, 20, 52, 5, 'active', 3, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(34, 21, 53, 5, 'active', 1, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(35, 21, 54, 5, 'active', 2, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(36, 21, 55, 5, 'active', 3, '2026-05-17 16:36:47', '2026-05-17 16:36:47'),
(37, 22, 31, 10, 'active', 1, '2026-05-19 16:31:40', '2026-05-19 16:31:40'),
(38, 23, 47, 5, 'active', 1, '2026-05-19 16:51:07', '2026-05-19 16:51:07'),
(39, 23, 48, 5, 'active', 2, '2026-05-19 16:51:07', '2026-05-19 16:51:07'),
(40, 23, 49, 5, 'active', 3, '2026-05-19 16:51:07', '2026-05-19 16:51:07'),
(41, 24, 50, 5, 'active', 1, '2026-05-19 16:51:07', '2026-05-19 16:51:07'),
(42, 24, 51, 5, 'active', 2, '2026-05-19 16:51:07', '2026-05-19 16:51:07'),
(43, 24, 52, 5, 'active', 3, '2026-05-19 16:51:07', '2026-05-19 16:51:07'),
(44, 25, 56, 16, 'active', 1, '2026-05-22 05:11:41', '2026-05-22 05:11:41'),
(45, 26, 60, 5, 'active', 1, '2026-05-22 06:22:26', '2026-05-22 06:22:26'),
(46, 27, 62, 10, 'active', 1, '2026-05-22 10:20:21', '2026-05-22 10:20:21');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_service_areas`
--

CREATE TABLE `delivery_service_areas` (
  `area_id` int(11) NOT NULL,
  `province` varchar(120) NOT NULL,
  `city` varchar(120) NOT NULL,
  `barangay` varchar(120) NOT NULL,
  `zone_code` varchar(40) DEFAULT NULL,
  `zone_name` varchar(160) DEFAULT NULL,
  `zone_sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_service_areas`
--

INSERT INTO `delivery_service_areas` (`area_id`, `province`, `city`, `barangay`, `zone_code`, `zone_name`, `zone_sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Pangasinan', 'Basista', 'Bayoyong', 'BAS-01', 'Bayoyong Core / Fastest Everyday Route', 10, 1, '2026-05-10 15:40:13', '2026-05-11 13:27:29'),
(2, 'Pangasinan', 'Basista', 'Anambongan', 'BAS-01', 'Bayoyong Core / Fastest Everyday Route', 10, 1, '2026-05-10 15:40:13', '2026-05-11 13:27:29'),
(3, 'Pangasinan', 'Basista', 'Mapolopolo', 'BAS-01', 'Bayoyong Core / Fastest Everyday Route', 10, 1, '2026-05-10 15:40:13', '2026-05-11 13:27:29'),
(4, 'Pangasinan', 'Basista', 'Dumpay', 'BAS-01', 'Bayoyong Core / Fastest Everyday Route', 10, 1, '2026-05-10 15:40:14', '2026-05-11 13:27:29'),
(5, 'Pangasinan', 'Basista', 'Palma', 'BAS-01', 'Bayoyong Core / Fastest Everyday Route', 10, 1, '2026-05-10 15:40:14', '2026-05-11 13:27:29'),
(6, 'Pangasinan', 'Basista', 'Navatat', 'BAS-01', 'Bayoyong Core / Fastest Everyday Route', 10, 1, '2026-05-10 15:40:14', '2026-05-11 13:27:29'),
(7, 'Pangasinan', 'Basista', 'Poblacion', 'BAS-01', 'Bayoyong Core / Fastest Everyday Route', 10, 1, '2026-05-10 15:40:14', '2026-05-11 13:27:29'),
(8, 'Pangasinan', 'Basista', 'Pasibi East', 'BAS-01', 'Bayoyong Core / Fastest Everyday Route', 10, 1, '2026-05-10 15:40:15', '2026-05-11 13:27:29'),
(9, 'Pangasinan', 'Basista', 'Pasibi West', 'BAS-01', 'Bayoyong Core / Fastest Everyday Route', 10, 1, '2026-05-10 15:40:15', '2026-05-11 13:27:29'),
(10, 'Pangasinan', 'Basista', 'Bituag', 'BAS-01', 'Bayoyong Core / Fastest Everyday Route', 10, 1, '2026-05-10 15:40:15', '2026-05-11 13:27:29'),
(11, 'Pangasinan', 'Basista', 'Osmena Sr.', 'BAS-02', 'East / Southeast Basista Route', 20, 1, '2026-05-10 15:40:15', '2026-05-11 13:27:29'),
(12, 'Pangasinan', 'Basista', 'Obong', 'BAS-02', 'East / Southeast Basista Route', 20, 1, '2026-05-10 15:40:15', '2026-05-11 13:27:29'),
(13, 'Pangasinan', 'Basista', 'Malimpec East', 'BAS-02', 'East / Southeast Basista Route', 20, 1, '2026-05-10 15:40:15', '2026-05-11 13:27:29'),
(14, 'Pangasinan', 'Basista', 'Nalneran', 'BAS-02', 'East / Southeast Basista Route', 20, 1, '2026-05-10 15:40:16', '2026-05-11 13:27:29'),
(15, 'Pangasinan', 'Basista', 'Patacbo', 'BAS-02', 'East / Southeast Basista Route', 20, 1, '2026-05-10 15:40:16', '2026-05-11 13:27:29'),
(16, 'Pangasinan', 'Basista', 'Cabeldatan', 'BAS-02', 'East / Southeast Basista Route', 20, 1, '2026-05-10 15:40:16', '2026-05-11 13:27:29'),
(17, 'Pangasinan', 'Urbiztondo', 'Malaca', 'URB-01', 'Urbiztondo Side Route', 30, 1, '2026-05-10 15:40:16', '2026-05-11 13:27:44'),
(18, 'Pangasinan', 'Urbiztondo', 'Batangcaoa', 'URB-01', 'Urbiztondo Side Route', 30, 1, '2026-05-10 15:40:16', '2026-05-11 13:27:44'),
(19, 'Pangasinan', 'Urbiztondo', 'Camambugan', 'URB-01', 'Urbiztondo Side Route', 30, 1, '2026-05-10 15:40:16', '2026-05-11 13:27:44'),
(20, 'Pangasinan', 'Urbiztondo', 'Poblacion', 'URB-01', 'Urbiztondo Side Route', 30, 1, '2026-05-10 15:40:16', '2026-05-11 13:27:44'),
(21, 'Pangasinan', 'Urbiztondo', 'Real', 'URB-01', 'Urbiztondo Side Route', 30, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:44'),
(22, 'Pangasinan', 'Urbiztondo', 'Pisuac', 'URB-01', 'Urbiztondo Side Route', 30, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:44'),
(23, 'Pangasinan', 'Urbiztondo', 'Angatel', 'URB-01', 'Urbiztondo Side Route', 30, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:44'),
(24, 'Pangasinan', 'Urbiztondo', 'Salavante', 'URB-01', 'Urbiztondo Side Route', 30, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:44'),
(25, 'Pangasinan', 'Urbiztondo', 'Sawat', 'URB-01', 'Urbiztondo Side Route', 30, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:44'),
(26, 'Pangasinan', 'San Carlos City', 'Turac', 'SCC-01', 'San Carlos North Route', 40, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:43'),
(27, 'Pangasinan', 'San Carlos City', 'Tarectec', 'SCC-01', 'San Carlos North Route', 40, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:43'),
(28, 'Pangasinan', 'San Carlos City', 'Cobol', 'SCC-01', 'San Carlos North Route', 40, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:43'),
(29, 'Pangasinan', 'San Carlos City', 'Payapa', 'SCC-01', 'San Carlos North Route', 40, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:43'),
(30, 'Pangasinan', 'San Carlos City', 'Bacnar', 'SCC-02', 'San Carlos West / Northwest Route', 50, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:43'),
(31, 'Pangasinan', 'San Carlos City', 'Calobaoan', 'SCC-02', 'San Carlos West / Northwest Route', 50, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:43'),
(32, 'Pangasinan', 'San Carlos City', 'Bolosan', 'SCC-02', 'San Carlos West / Northwest Route', 50, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:43'),
(33, 'Pangasinan', 'San Carlos City', 'Tebag', 'SCC-02', 'San Carlos West / Northwest Route', 50, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:43'),
(34, 'Pangasinan', 'San Carlos City', 'Abanon', 'SCC-02', 'San Carlos West / Northwest Route', 50, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:43'),
(35, 'Pangasinan', 'San Carlos City', 'Malacanang', 'SCC-02', 'San Carlos West / Northwest Route', 50, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:43'),
(36, 'Pangasinan', 'San Carlos City', 'Mestizo Norte', 'SCC-02', 'San Carlos West / Northwest Route', 50, 1, '2026-05-10 15:40:17', '2026-05-11 13:27:43'),
(37, 'Pangasinan', 'San Carlos City', 'Maliwara', 'SCC-02', 'San Carlos West / Northwest Route', 50, 1, '2026-05-10 15:40:18', '2026-05-11 13:27:43');

-- --------------------------------------------------------

--
-- Table structure for table `free_gallon_redemptions`
--

CREATE TABLE `free_gallon_redemptions` (
  `redemption_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `used_order_id` int(11) DEFAULT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gallons_redeemed` int(11) DEFAULT 1,
  `status` enum('active','used','expired') DEFAULT 'active',
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `free_gallon_redemptions`
--

INSERT INTO `free_gallon_redemptions` (`redemption_id`, `customer_id`, `order_id`, `used_order_id`, `redeemed_at`, `gallons_redeemed`, `status`, `expires_at`) VALUES
(1, 2, 33, NULL, '2026-05-12 10:34:30', 1, 'active', '2026-06-11 10:34:30'),
(2, 6, 37, 60, '2026-05-16 16:18:17', 1, 'used', '2026-06-15 16:18:17'),
(3, 7, 47, 56, '2026-05-19 16:54:00', 1, 'used', '2026-06-18 16:54:00'),
(4, 7, 52, 56, '2026-05-19 16:54:36', 1, 'used', '2026-06-18 16:54:36'),
(5, 7, 45, 56, '2026-05-19 17:11:22', 1, 'used', '2026-06-18 17:11:22');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `item_name` varchar(50) NOT NULL,
  `item_image` varchar(255) DEFAULT NULL,
  `item_type` enum('container','accessory','other') DEFAULT 'container',
  `capacity_units` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `stock_quantity` int(11) DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL,
  `reorder_level` int(11) DEFAULT 10,
  `status` enum('available','out_of_stock','discontinued') DEFAULT 'available',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `item_name`, `item_image`, `item_type`, `capacity_units`, `stock_quantity`, `unit_price`, `reorder_level`, `status`, `last_updated`, `updated_by`) VALUES
(1, '5-Gallon Slim Container', 'uploads/inventory/inventory_20260510182158_24d43172.png', 'container', 1, 89, 25.00, 20, 'available', '2026-05-22 10:16:46', 1),
(2, '5-Gallon Round Container', 'uploads/inventory/inventory_20260509174446_d36fbb84.png', 'container', 2, 112, 25.00, 15, 'available', '2026-05-22 05:52:53', 1);

-- --------------------------------------------------------

--
-- Table structure for table `loyalty`
--

CREATE TABLE `loyalty` (
  `loyalty_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_orders` int(11) DEFAULT 0,
  `consecutive_orders` int(11) DEFAULT 0,
  `free_gallons_earned` int(11) DEFAULT 0,
  `free_gallons_used` int(11) DEFAULT 0,
  `last_order_date` date DEFAULT NULL,
  `streak_start_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loyalty`
--

INSERT INTO `loyalty` (`loyalty_id`, `customer_id`, `total_orders`, `consecutive_orders`, `free_gallons_earned`, `free_gallons_used`, `last_order_date`, `streak_start_date`) VALUES
(1, 2, 14, 7, 1, 0, '2026-05-20', NULL),
(2, 6, 10, 8, 1, 1, '2026-05-22', NULL),
(4, 7, 17, 17, 3, 3, '2026-05-22', NULL),
(203, 8, 0, 0, 0, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_date` date DEFAULT NULL,
  `delivery_address` text NOT NULL,
  `delivery_street` text DEFAULT NULL,
  `delivery_barangay` varchar(120) DEFAULT NULL,
  `delivery_city` varchar(120) DEFAULT NULL,
  `delivery_province` varchar(120) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('cash_on_delivery','online_payment') DEFAULT 'cash_on_delivery',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `order_status` enum('pending','confirmed','processing','out_for_delivery','delivered','cancelled','returned') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `order_date`, `delivery_date`, `delivery_address`, `delivery_street`, `delivery_barangay`, `delivery_city`, `delivery_province`, `contact_number`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `notes`, `created_at`, `updated_at`) VALUES
(6, 2, '2026-03-12 15:12:35', '2026-03-12', 'rizal street, brgy baliwag,san carlos', NULL, NULL, NULL, NULL, '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-03-12 15:12:35', '2026-03-14 14:17:42'),
(7, 2, '2026-03-13 03:42:05', '0000-00-00', 'rizal street, brgy baliwag,san carlos', NULL, NULL, NULL, NULL, '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'asdsda', '2026-03-13 03:42:05', '2026-03-23 13:59:26'),
(8, 2, '2026-03-13 15:40:31', '2026-03-24', 'rizal street, brgy baliwag,san carlos', NULL, NULL, NULL, NULL, '0987654323456', 75.00, 'cash_on_delivery', 'pending', 'cancelled', 'grt', '2026-03-13 15:40:31', '2026-03-13 15:40:43'),
(9, 2, '2026-03-13 15:41:29', '2026-03-26', 'rizal street, brgy baliwag,san carlos', NULL, NULL, NULL, NULL, '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'helo', '2026-03-13 15:41:29', '2026-03-15 05:50:02'),
(10, 2, '2026-03-14 04:12:29', '0000-00-00', 'rizal street, brgy baliwag,san carlos', NULL, NULL, NULL, NULL, '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'hyt', '2026-03-14 04:12:29', '2026-03-15 05:49:59'),
(11, 2, '2026-03-14 07:21:17', '2026-03-15', 'rizal street, brgy baliwag,san carlos', NULL, NULL, NULL, NULL, '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-03-14 07:21:17', '2026-03-14 07:21:59'),
(12, 2, '2026-03-14 10:46:21', '2026-03-14', 'rizal street, brgy baliwag,san carlos', NULL, NULL, NULL, NULL, '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'hshhsh', '2026-03-14 10:46:21', '2026-03-14 10:47:45'),
(13, 2, '2026-03-14 10:56:35', '2026-03-21', 'rizal street, brgy baliwag,san carlos', NULL, NULL, NULL, NULL, '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-03-14 10:56:35', '2026-03-15 05:54:36'),
(14, 6, '2026-03-15 05:57:07', '0000-00-00', 'rizal street, brgy pagal, dagupan', NULL, NULL, NULL, NULL, '099958501733', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try', '2026-03-15 05:57:07', '2026-03-23 14:24:27'),
(15, 2, '2026-03-15 05:58:30', '0000-00-00', 'rizal street, brgy baliwag,san carlos', NULL, NULL, NULL, NULL, '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'ill get my freegallon paldo', '2026-03-15 05:58:30', '2026-03-23 14:57:56'),
(16, 7, '2026-03-15 06:09:55', '0000-00-00', 'rizal street, brgy cruz ,san carlos', NULL, NULL, NULL, NULL, '098765322343', 125.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-03-15 06:09:55', '2026-05-10 12:49:19'),
(17, 7, '2026-03-15 06:13:27', '2026-03-16', 'rizal street, brgy cruz ,san carlos', NULL, NULL, NULL, NULL, '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-03-15 06:13:27', '2026-03-23 14:58:00'),
(18, 6, '2026-03-23 12:54:56', '2026-03-23', 'rizal street, brgy pagal, dagupan', NULL, NULL, NULL, NULL, '099958501733', 125.00, 'cash_on_delivery', 'pending', 'cancelled', 'hello', '2026-03-23 12:54:56', '2026-05-07 02:06:30'),
(19, 2, '2026-03-25 14:47:14', '2026-03-25', 'rizal street, brgy baliwag,san carlos', NULL, NULL, NULL, NULL, '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'hello', '2026-03-25 14:47:14', '2026-03-25 14:51:58'),
(20, 6, '2026-03-25 16:08:31', '2026-03-26', 'rizal street, brgy pagal, dagupan', NULL, NULL, NULL, NULL, '099958501733', 125.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-03-25 16:08:31', '2026-03-26 15:06:53'),
(21, 6, '2026-03-26 14:50:14', '2026-03-26', 'rizal street, brgy pagal, dagupan', NULL, NULL, NULL, NULL, '099958501733', 150.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-03-26 14:50:14', '2026-05-07 02:06:13'),
(22, 6, '2026-05-07 04:00:25', '2026-05-08', 'rizal street, brgy pagal, dagupan', NULL, NULL, NULL, NULL, '099958501733', 250.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-05-07 04:00:25', '2026-05-07 05:29:28'),
(23, 6, '2026-05-07 05:25:53', '2026-05-07', 'hermosa street, pagal, san carlos city', NULL, NULL, NULL, NULL, '099958501733', 200.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-05-07 05:25:53', '2026-05-08 07:38:02'),
(24, 6, '2026-05-07 08:18:21', '2026-05-07', 'taga manila', NULL, NULL, NULL, NULL, '099958501733', 250.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-05-07 08:18:21', '2026-05-08 07:37:43'),
(25, 6, '2026-05-08 08:02:44', '2026-05-10', 'taga manila', NULL, NULL, NULL, NULL, '099958501733', 125.00, 'cash_on_delivery', 'pending', 'cancelled', 'be quick', '2026-05-08 08:02:44', '2026-05-08 08:03:13'),
(26, 6, '2026-05-10 16:11:51', '2026-05-11', 'bayaket street, Turac, San Carlos City, Pangasinan', 'bayaket street', 'Turac', 'San Carlos City', 'Pangasinan', '099958501733', 250.00, 'cash_on_delivery', 'pending', 'delivered', 'sirain nyo yung pinto', '2026-05-10 16:11:51', '2026-05-10 16:18:57'),
(27, 6, '2026-05-11 10:29:27', '2026-05-11', 'bayaket street, Turac, San Carlos City, Pangasinan', 'bayaket street', 'Turac', 'San Carlos City', 'Pangasinan', '099958501733', 125.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-05-11 10:29:27', '2026-05-11 10:41:08'),
(28, 6, '2026-05-11 10:42:18', '2026-05-11', 'basta building malaki, Calobaoan, San Carlos City, Pangasinan', 'basta building malaki', 'Calobaoan', 'San Carlos City', 'Pangasinan', '099958501733', 125.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-05-11 10:42:18', '2026-05-11 14:58:56'),
(31, 6, '2026-05-11 15:05:13', '2026-05-20', 'basta building malaki, Calobaoan, San Carlos City, Pangasinan', 'basta building malaki', 'Calobaoan', 'San Carlos City', 'Pangasinan', '099958501733', 125.00, 'cash_on_delivery', 'pending', 'cancelled', 'knock three times then do push up 1 week.', '2026-05-11 15:05:13', '2026-05-19 17:18:43'),
(32, 6, '2026-05-11 15:05:58', '2026-05-11', 'bayaket street, Turac, San Carlos City, Pangasinan', 'bayaket street', 'Turac', 'San Carlos City', 'Pangasinan', '099958501733', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'goodmorning', '2026-05-11 15:05:58', '2026-05-12 10:34:18'),
(33, 2, '2026-05-11 15:07:40', '2026-05-11', '62b, Salavante, Urbiztondo, Pangasinan', '62b', 'Salavante', 'Urbiztondo', 'Pangasinan', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-05-11 15:07:40', '2026-05-12 10:34:29'),
(34, 2, '2026-05-11 15:08:50', '2026-05-18', '62b, Salavante, Urbiztondo, Pangasinan', '62b', 'Salavante', 'Urbiztondo', 'Pangasinan', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-05-11 15:08:50', '2026-05-19 16:33:39'),
(35, 2, '2026-05-11 15:11:02', '2026-05-11', 'hermosa street, Tebag, San Carlos City, Pangasinan', 'hermosa street', 'Tebag', 'San Carlos City', 'Pangasinan', '0987654323456', 250.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-05-11 15:11:02', '2026-05-11 16:57:13'),
(36, 6, '2026-05-16 15:26:17', '2026-05-17', 'basta building malaki, Calobaoan, San Carlos City, Pangasinan', 'basta building malaki', 'Calobaoan', 'San Carlos City', 'Pangasinan', '099958501733', 200.00, 'cash_on_delivery', 'pending', 'cancelled', 'pota inang mo', '2026-05-16 15:26:17', '2026-05-16 16:29:17'),
(37, 6, '2026-05-16 15:35:35', '2026-05-17', 'bayaket street, Turac, San Carlos City, Pangasinan', 'bayaket street', 'Turac', 'San Carlos City', 'Pangasinan', '099958501733', 200.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-05-16 15:35:35', '2026-05-16 16:18:16'),
(38, 2, '2026-05-16 15:40:34', '2026-05-17', 'hermosa street, Tebag, San Carlos City, Pangasinan', 'hermosa street', 'Tebag', 'San Carlos City', 'Pangasinan', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-05-16 15:40:34', '2026-05-16 16:21:45'),
(39, 2, '2026-05-16 15:42:46', '2026-05-17', '62b, Salavante, Urbiztondo, Pangasinan', '62b', 'Salavante', 'Urbiztondo', 'Pangasinan', '0987654323456', 25.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-05-16 15:42:46', '2026-05-16 16:23:19'),
(40, 6, '2026-05-17 13:51:43', '2026-05-17', 'bayaket street, Turac, San Carlos City, Pangasinan', 'bayaket street', 'Turac', 'San Carlos City', 'Pangasinan', '099958501733', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'hello', '2026-05-17 13:51:43', '2026-05-17 15:58:59'),
(41, 7, '2026-05-17 16:16:12', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'get the payment in my neighbour i already notify them.', '2026-05-17 16:16:12', '2026-05-19 17:10:16'),
(42, 7, '2026-05-17 16:16:19', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'get the payment in my neighbour i already notify them.', '2026-05-17 16:16:19', '2026-05-19 17:10:23'),
(43, 7, '2026-05-17 16:18:18', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:18:18', '2026-05-19 17:10:37'),
(44, 7, '2026-05-17 16:18:26', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:18:26', '2026-05-19 17:11:08'),
(45, 7, '2026-05-17 16:18:35', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:18:35', '2026-05-19 17:11:21'),
(46, 7, '2026-05-17 16:18:41', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:18:41', '2026-05-19 17:11:34'),
(47, 7, '2026-05-17 16:18:48', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:18:48', '2026-05-19 16:54:00'),
(48, 7, '2026-05-17 16:18:54', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:18:54', '2026-05-19 16:54:06'),
(49, 7, '2026-05-17 16:18:59', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:18:59', '2026-05-19 16:54:14'),
(50, 7, '2026-05-17 16:19:05', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:19:05', '2026-05-19 16:54:24'),
(51, 7, '2026-05-17 16:19:11', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:19:11', '2026-05-19 16:54:31'),
(52, 7, '2026-05-17 16:19:18', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:19:18', '2026-05-19 16:54:36'),
(53, 7, '2026-05-17 16:19:24', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:19:24', '2026-05-19 16:33:21'),
(54, 7, '2026-05-17 16:19:31', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:19:31', '2026-05-19 16:33:27'),
(55, 7, '2026-05-17 16:19:38', '2026-05-18', 'rizal street, brgy cruz ,san carlos', '', '', '', '', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try again for bug by clickling the place order multiple times.', '2026-05-17 16:19:38', '2026-05-19 16:33:33'),
(56, 7, '2026-05-22 05:09:16', '2026-05-22', 'tabi ng simbahan, Malacanang, San Carlos City, Pangasinan', 'tabi ng simbahan', 'Malacanang', 'San Carlos City', 'Pangasinan', '098765322343', 325.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-05-22 05:09:16', '2026-05-22 05:14:46'),
(57, 6, '2026-05-22 05:20:40', '2026-05-22', 'basta building malaki, Calobaoan, San Carlos City, Pangasinan', 'basta building malaki', 'Calobaoan', 'San Carlos City', 'Pangasinan', '099958501733', 75.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-05-22 05:20:40', '2026-05-22 05:27:53'),
(58, 6, '2026-05-22 05:28:25', '2026-05-22', 'bayaket street, Turac, San Carlos City, Pangasinan', 'bayaket street', 'Turac', 'San Carlos City', 'Pangasinan', '099958501733', 100.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-05-22 05:28:25', '2026-05-22 05:29:53'),
(59, 6, '2026-05-22 05:30:26', '2026-05-22', 'bayaket street, Turac, San Carlos City, Pangasinan', 'bayaket street', 'Turac', 'San Carlos City', 'Pangasinan', '099958501733', 100.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-05-22 05:30:26', '2026-05-22 05:35:14'),
(60, 6, '2026-05-22 05:38:01', '2026-05-22', 'bayaket street, Turac, San Carlos City, Pangasinan', 'bayaket street', 'Turac', 'San Carlos City', 'Pangasinan', '099958501733', 100.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-05-22 05:38:01', '2026-05-22 06:24:19'),
(61, 6, '2026-05-22 05:46:12', '2026-05-22', 'bayaket street, Turac, San Carlos City, Pangasinan', 'bayaket street', 'Turac', 'San Carlos City', 'Pangasinan', '099958501733', 200.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-05-22 05:46:12', '2026-05-22 05:52:53'),
(62, 6, '2026-05-22 10:16:47', '2026-05-22', 'bayaket street, Turac, San Carlos City, Pangasinan', 'bayaket street', 'Turac', 'San Carlos City', 'Pangasinan', '099958501733', 250.00, '', 'paid', 'delivered', '', '2026-05-22 10:16:47', '2026-05-22 10:42:05');

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `trg_order_status_log` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF OLD.order_status != NEW.order_status THEN
        INSERT INTO activity_logs (user_id, action, description, related_table, related_id)
        VALUES (NEW.customer_id, 'order_status_changed', 
                CONCAT('Order ', NEW.order_id, ' changed from ', OLD.order_status, ' to ', NEW.order_status),
                'orders', NEW.order_id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL CHECK (`quantity` > 0),
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`item_id`, `order_id`, `inventory_id`, `quantity`, `unit_price`) VALUES
(6, 6, 1, 5, 25.00),
(7, 7, 2, 5, 25.00),
(8, 8, 1, 3, 25.00),
(9, 9, 2, 5, 25.00),
(10, 10, 2, 5, 25.00),
(11, 11, 2, 5, 25.00),
(12, 12, 2, 5, 25.00),
(13, 13, 1, 5, 25.00),
(14, 14, 2, 5, 25.00),
(15, 15, 1, 5, 25.00),
(16, 16, 1, 5, 25.00),
(17, 17, 1, 5, 25.00),
(18, 18, 1, 5, 25.00),
(19, 19, 2, 5, 25.00),
(20, 20, 2, 5, 25.00),
(21, 21, 2, 6, 25.00),
(22, 22, 1, 10, 25.00),
(23, 23, 2, 8, 25.00),
(24, 24, 1, 10, 25.00),
(25, 25, 2, 5, 25.00),
(26, 26, 2, 10, 25.00),
(27, 27, 1, 5, 25.00),
(28, 28, 2, 5, 25.00),
(31, 31, 2, 5, 25.00),
(32, 32, 1, 5, 25.00),
(33, 33, 1, 5, 25.00),
(34, 34, 1, 5, 25.00),
(35, 35, 1, 10, 25.00),
(36, 36, 2, 8, 25.00),
(37, 37, 2, 8, 25.00),
(38, 38, 2, 5, 25.00),
(39, 39, 2, 1, 25.00),
(40, 40, 1, 5, 25.00),
(41, 41, 1, 5, 25.00),
(42, 42, 1, 5, 25.00),
(43, 43, 1, 5, 25.00),
(44, 44, 1, 5, 25.00),
(45, 45, 1, 5, 25.00),
(46, 46, 1, 5, 25.00),
(47, 47, 1, 5, 25.00),
(48, 48, 1, 5, 25.00),
(49, 49, 1, 5, 25.00),
(50, 50, 1, 5, 25.00),
(51, 51, 1, 5, 25.00),
(52, 52, 1, 5, 25.00),
(53, 53, 1, 5, 25.00),
(54, 54, 1, 5, 25.00),
(55, 55, 1, 5, 25.00),
(56, 56, 1, 16, 25.00),
(57, 57, 2, 3, 25.00),
(58, 58, 1, 5, 25.00),
(59, 59, 1, 5, 25.00),
(60, 60, 1, 5, 25.00),
(61, 61, 2, 8, 25.00),
(62, 62, 1, 10, 25.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `role` enum('admin','staff','customer') DEFAULT 'customer',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `phone`, `full_name`, `address`, `role`, `status`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'admin', '$2y$10$EMoafSb53CJxHi88ZvNB3O6xoLcE2.qDQEpZjOSYGIZsLaiI9hPme', 'admin@israphil.com', '09123456789', 'System Administrator', 'Main Branch', 'admin', 'active', '2026-03-11 13:54:13', '2026-05-22 10:19:36', '2026-05-22 10:19:36'),
(2, 'testcustomer', '$2y$10$KvSmS/i1JXXtwjjBCboGlezh7xLKA1fYgMM5Yyr4wKroPMGFcqfU6', 'testcustomer123@gmail.com', '0987654323456', 'test cusmoter', '62b, Salavante, Urbiztondo, Pangasinan', 'customer', 'active', '2026-03-12 13:20:11', '2026-05-16 15:39:22', '2026-05-16 15:39:22'),
(3, 'staff1', '$2y$10$vg5dbCxf4Pv3xLvhKLJ08.nmc1TmHkvHWbBIKsHuO5/xOsrX597cm', 'staff123@gmail.com', '099958501733', 'staff one', 'rizal street, brgy pagal,san carlos', 'staff', 'active', '2026-03-13 03:23:17', '2026-05-22 10:31:42', '2026-05-22 10:31:42'),
(4, 'staff2', '$2y$10$LGQEPM2Ec5ZMfB.fu.l7beyJgpnlV3yxmc7gqL2Uh1fH91KE//tke', 'michaelfrias72@gmail.com', '099968501733', 'Michael Frias', 'mapolopolo, basista city', 'staff', 'active', '2026-03-14 10:20:26', '2026-05-19 16:53:40', '2026-05-19 16:53:40'),
(5, 'staff3', '$2y$10$mVVAm4DJoyU6YCJfBN3eFeg/BNbnp2Exh2DmNKYJYGufhfYicnc7G', 'jandavidmanayan69@gmail.com', '456789993', 'John David Manayan', 'pdf stret, brgy bayuasm, urbiztondo', 'staff', 'active', '2026-03-14 10:21:47', '2026-05-19 17:08:56', '2026-05-19 17:08:56'),
(6, 'customer1', '$2y$10$PV78dePn1BmwddsMCZmKUOahuBauxSJQA2dS12bgsb7.UR8PwEP1W', 'kazumakiri69@gmail.com', '099958501733', 'edison panuyas', 'bayaket street, Turac, San Carlos City, Pangasinan', 'customer', 'active', '2026-03-15 01:28:46', '2026-05-22 07:22:24', '2026-05-22 07:22:24'),
(7, 'customer2', '$2y$10$RQbe2vrJ0xp9Pzgmap84MOcd.TVrQr91QINP0UWbwHR3EDlGApYIq', 'tony123@gmail.com', '098765322343', 'tony stark', 'tabi ng simbahan, Malacanang, San Carlos City, Pangasinan', 'customer', 'active', '2026-03-15 01:40:54', '2026-05-22 05:15:01', '2026-05-22 05:15:01'),
(8, 'david lust', '$2y$10$G7208eyXzCkjaurapcKZ3unk/3HnH6xvKZ4Pcflcm/.nYgMiRLZZy', 'davidmaniac67@gmail.com', '09876524524', 'luster david', 'depths of hell, Real, Urbiztondo, Pangasinan', 'customer', 'active', '2026-05-10 16:28:06', '2026-05-10 16:28:27', '2026-05-10 16:28:27');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `trg_new_customer_loyalty` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.role = 'customer' THEN
        INSERT INTO loyalty (customer_id, consecutive_orders, free_gallons_earned, free_gallons_used)
        VALUES (NEW.user_id, 0, 0, 0);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_active_orders`
-- (See below for the actual view)
--
CREATE TABLE `view_active_orders` (
`order_id` int(11)
,`customer_id` int(11)
,`customer_name` varchar(100)
,`customer_phone` varchar(20)
,`delivery_address` text
,`order_status` enum('pending','confirmed','processing','out_for_delivery','delivered','cancelled','returned')
,`total_amount` decimal(10,2)
,`order_date` timestamp
,`staff_id` int(11)
,`assigned_staff` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_customer_loyalty`
-- (See below for the actual view)
--
CREATE TABLE `view_customer_loyalty` (
`user_id` int(11)
,`full_name` varchar(100)
,`email` varchar(100)
,`total_orders` int(11)
,`consecutive_orders` int(11)
,`available_free_gallons` bigint(12)
,`last_order_date` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_staff_workload`
-- (See below for the actual view)
--
CREATE TABLE `view_staff_workload` (
`staff_id` int(11)
,`staff_name` varchar(100)
,`active_deliveries` bigint(21)
,`pending_pickup` decimal(22,0)
,`in_progress` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Structure for view `view_active_orders`
--
DROP TABLE IF EXISTS `view_active_orders`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_active_orders`  AS SELECT `o`.`order_id` AS `order_id`, `o`.`customer_id` AS `customer_id`, `u`.`full_name` AS `customer_name`, `u`.`phone` AS `customer_phone`, `o`.`delivery_address` AS `delivery_address`, `o`.`order_status` AS `order_status`, `o`.`total_amount` AS `total_amount`, `o`.`order_date` AS `order_date`, `d`.`staff_id` AS `staff_id`, `staff`.`full_name` AS `assigned_staff` FROM (((`orders` `o` join `users` `u` on(`o`.`customer_id` = `u`.`user_id`)) left join `deliveries` `d` on(`o`.`order_id` = `d`.`order_id`)) left join `users` `staff` on(`d`.`staff_id` = `staff`.`user_id`)) WHERE `o`.`order_status` not in ('delivered','cancelled') ;

-- --------------------------------------------------------

--
-- Structure for view `view_customer_loyalty`
--
DROP TABLE IF EXISTS `view_customer_loyalty`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_customer_loyalty`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`full_name` AS `full_name`, `u`.`email` AS `email`, `l`.`total_orders` AS `total_orders`, `l`.`consecutive_orders` AS `consecutive_orders`, `l`.`free_gallons_earned`- `l`.`free_gallons_used` AS `available_free_gallons`, `l`.`last_order_date` AS `last_order_date` FROM (`users` `u` join `loyalty` `l` on(`u`.`user_id` = `l`.`customer_id`)) WHERE `u`.`role` = 'customer' ;

-- --------------------------------------------------------

--
-- Structure for view `view_staff_workload`
--
DROP TABLE IF EXISTS `view_staff_workload`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_staff_workload`  AS SELECT `u`.`user_id` AS `staff_id`, `u`.`full_name` AS `staff_name`, count(`d`.`delivery_id`) AS `active_deliveries`, sum(case when `d`.`delivery_status` = 'assigned' then 1 else 0 end) AS `pending_pickup`, sum(case when `d`.`delivery_status` = 'in_transit' then 1 else 0 end) AS `in_progress` FROM (`users` `u` left join `deliveries` `d` on(`u`.`user_id` = `d`.`staff_id` and `d`.`delivery_status` not in ('delivered','failed','returned'))) WHERE `u`.`role` = 'staff' AND `u`.`status` = 'active' GROUP BY `u`.`user_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `customer_delivery_addresses`
--
ALTER TABLE `customer_delivery_addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `idx_customer_delivery_addresses_customer` (`customer_id`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`delivery_id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_deliveries_staff` (`staff_id`),
  ADD KEY `idx_deliveries_status` (`delivery_status`);

--
-- Indexes for table `delivery_batches`
--
ALTER TABLE `delivery_batches`
  ADD PRIMARY KEY (`batch_id`),
  ADD UNIQUE KEY `uniq_delivery_batch_code` (`batch_code`),
  ADD KEY `idx_delivery_batches_date_status` (`batch_date`,`batch_status`),
  ADD KEY `idx_delivery_batches_zone` (`zone_code`,`batch_date`),
  ADD KEY `idx_delivery_batches_staff` (`staff_id`,`batch_status`),
  ADD KEY `idx_delivery_batches_created_by` (`created_by`),
  ADD KEY `idx_delivery_batches_confirmed_by` (`confirmed_by`),
  ADD KEY `idx_delivery_batches_assigned_by` (`assigned_by`);

--
-- Indexes for table `delivery_batch_items`
--
ALTER TABLE `delivery_batch_items`
  ADD PRIMARY KEY (`batch_item_id`),
  ADD UNIQUE KEY `uniq_delivery_batch_order` (`batch_id`,`order_id`),
  ADD KEY `idx_delivery_batch_items_order` (`order_id`),
  ADD KEY `idx_delivery_batch_items_status` (`item_status`),
  ADD KEY `idx_delivery_batch_items_sort` (`batch_id`,`sort_order`);

--
-- Indexes for table `delivery_service_areas`
--
ALTER TABLE `delivery_service_areas`
  ADD PRIMARY KEY (`area_id`),
  ADD UNIQUE KEY `uniq_delivery_service_area` (`province`,`city`,`barangay`),
  ADD KEY `idx_delivery_service_area_city` (`province`,`city`),
  ADD KEY `idx_delivery_service_area_barangay` (`barangay`),
  ADD KEY `idx_delivery_service_area_zone` (`zone_code`,`zone_sort_order`);

--
-- Indexes for table `free_gallon_redemptions`
--
ALTER TABLE `free_gallon_redemptions`
  ADD PRIMARY KEY (`redemption_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `loyalty`
--
ALTER TABLE `loyalty`
  ADD PRIMARY KEY (`loyalty_id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_orders_customer` (`customer_id`),
  ADD KEY `idx_orders_status` (`order_status`),
  ADD KEY `idx_orders_date` (`order_date`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `idx_order_items_order` (`order_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=224;

--
-- AUTO_INCREMENT for table `customer_delivery_addresses`
--
ALTER TABLE `customer_delivery_addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `delivery_batches`
--
ALTER TABLE `delivery_batches`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `delivery_batch_items`
--
ALTER TABLE `delivery_batch_items`
  MODIFY `batch_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `delivery_service_areas`
--
ALTER TABLE `delivery_service_areas`
  MODIFY `area_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27566;

--
-- AUTO_INCREMENT for table `free_gallon_redemptions`
--
ALTER TABLE `free_gallon_redemptions`
  MODIFY `redemption_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `loyalty`
--
ALTER TABLE `loyalty`
  MODIFY `loyalty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=434;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_delivery_addresses`
--
ALTER TABLE `customer_delivery_addresses`
  ADD CONSTRAINT `customer_delivery_addresses_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `deliveries_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `delivery_batches`
--
ALTER TABLE `delivery_batches`
  ADD CONSTRAINT `delivery_batches_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `delivery_batches_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `delivery_batches_ibfk_3` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `delivery_batches_ibfk_4` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `delivery_batch_items`
--
ALTER TABLE `delivery_batch_items`
  ADD CONSTRAINT `delivery_batch_items_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `delivery_batches` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_batch_items_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `free_gallon_redemptions`
--
ALTER TABLE `free_gallon_redemptions`
  ADD CONSTRAINT `free_gallon_redemptions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `free_gallon_redemptions_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `loyalty`
--
ALTER TABLE `loyalty`
  ADD CONSTRAINT `loyalty_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
