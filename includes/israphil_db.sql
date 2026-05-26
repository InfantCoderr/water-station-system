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

-- Presentation reset: no seed data for this table.

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

-- Presentation reset: no seed data for this table.

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

-- Presentation reset: no seed data for this table.

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

-- Presentation reset: no seed data for this table.

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

-- Presentation reset: no seed data for this table.

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

-- Presentation reset: no seed data for this table.

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

-- Presentation reset: no seed data for this table.

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

-- Presentation reset: no seed data for this table.

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

-- Presentation reset: no seed data for this table.

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
(1, 'admin', '$2y$10$EMoafSb53CJxHi88ZvNB3O6xoLcE2.qDQEpZjOSYGIZsLaiI9hPme', 'admin@israphil.com', '09123456789', 'System Administrator', 'Main Branch', 'admin', 'active', '2026-03-11 13:54:13', '2026-05-22 10:19:36', '2026-05-22 10:19:36');

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `customer_delivery_addresses`
--
ALTER TABLE `customer_delivery_addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `delivery_batches`
--
ALTER TABLE `delivery_batches`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `delivery_batch_items`
--
ALTER TABLE `delivery_batch_items`
  MODIFY `batch_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `delivery_service_areas`
--
ALTER TABLE `delivery_service_areas`
  MODIFY `area_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27566;

--
-- AUTO_INCREMENT for table `free_gallon_redemptions`
--
ALTER TABLE `free_gallon_redemptions`
  MODIFY `redemption_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `loyalty`
--
ALTER TABLE `loyalty`
  MODIFY `loyalty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
