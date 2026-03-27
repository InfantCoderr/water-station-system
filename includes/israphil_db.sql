-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 27, 2026 at 05:00 PM
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

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_auto_assign_order` (IN `p_order_id` INT)   BEGIN
    DECLARE v_staff_id INT;
    
    SELECT user_id INTO v_staff_id
    FROM view_staff_workload
    ORDER BY active_deliveries ASC, staff_id ASC
    LIMIT 1;
    
    IF v_staff_id IS NOT NULL THEN
        INSERT INTO deliveries (order_id, staff_id, assignment_type, delivery_status)
        VALUES (p_order_id, v_staff_id, 'automatic', 'assigned');
        
        UPDATE orders SET order_status = 'confirmed' WHERE order_id = p_order_id;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reset_consecutive` (IN `p_customer_id` INT)   BEGIN
    UPDATE loyalty 
    SET consecutive_orders = 0,
        streak_start_date = NULL
    WHERE customer_id = p_customer_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_loyalty` (IN `p_customer_id` INT)   BEGIN
    DECLARE v_consecutive INT;
    
    SELECT consecutive_orders INTO v_consecutive
    FROM loyalty WHERE customer_id = p_customer_id;
    
    UPDATE loyalty 
    SET 
        total_orders = total_orders + 1,
        consecutive_orders = consecutive_orders + 1,
        last_order_date = CURDATE()
    WHERE customer_id = p_customer_id;
    
    SET v_consecutive = v_consecutive + 1;
    IF v_consecutive % 5 = 0 THEN
        UPDATE loyalty 
        SET free_gallons_earned = free_gallons_earned + 1
        WHERE customer_id = p_customer_id;
        
        INSERT INTO free_gallon_redemptions (customer_id, gallons_redeemed, status)
        VALUES (p_customer_id, 1, 'active');
    END IF;
END$$

DELIMITER ;

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
(52, 7, 'order_status_changed', 'Order 16 changed from confirmed to pending', 'orders', 16, NULL, '2026-03-26 15:52:25');

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `delivery_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assignment_type` enum('manual','automatic') DEFAULT 'manual',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `delivery_status` enum('assigned','picked_up','in_transit','delivered','failed','returned') DEFAULT 'assigned',
  `proof_of_delivery` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`delivery_id`, `order_id`, `staff_id`, `assigned_by`, `assignment_type`, `assigned_at`, `picked_up_at`, `delivered_at`, `delivery_notes`, `delivery_status`, `proof_of_delivery`) VALUES
(1, 7, 3, 1, 'manual', '2026-03-13 04:42:00', NULL, '2026-03-15 05:49:46', NULL, 'delivered', NULL),
(2, 9, 3, 1, 'manual', '2026-03-13 15:41:58', NULL, '2026-03-15 05:50:02', NULL, 'delivered', NULL),
(3, 10, 3, 1, 'manual', '2026-03-14 04:13:13', NULL, '2026-03-15 05:49:58', NULL, 'delivered', NULL),
(4, 12, 4, NULL, 'manual', '2026-03-14 10:46:24', NULL, '2026-03-14 10:47:45', NULL, 'delivered', NULL),
(5, 13, 4, NULL, 'manual', '2026-03-15 05:46:21', NULL, '2026-03-15 05:54:36', NULL, 'delivered', NULL),
(6, 14, 4, NULL, 'manual', '2026-03-15 05:57:08', NULL, '2026-03-23 14:24:27', NULL, 'delivered', NULL),
(7, 15, 5, NULL, 'manual', '2026-03-15 05:58:31', NULL, '2026-03-23 14:57:56', NULL, 'delivered', NULL),
(9, 17, 5, NULL, 'manual', '2026-03-15 06:13:27', NULL, '2026-03-23 14:58:00', NULL, 'delivered', NULL),
(11, 18, 4, 1, 'manual', '2026-03-23 14:48:06', NULL, '2026-03-23 14:50:11', NULL, 'assigned', NULL),
(12, 16, 4, 1, 'manual', '2026-03-23 14:48:14', NULL, NULL, 'idk', 'failed', NULL),
(13, 19, 5, 1, 'manual', '2026-03-25 14:51:42', NULL, '2026-03-25 14:51:58', 'rejected by the customer', 'delivered', NULL),
(14, 20, 3, NULL, 'automatic', '2026-03-25 16:08:31', NULL, '2026-03-26 15:06:53', NULL, 'delivered', NULL),
(15, 21, 5, 1, 'manual', '2026-03-26 15:12:22', NULL, NULL, NULL, 'assigned', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `free_gallon_redemptions`
--

CREATE TABLE `free_gallon_redemptions` (
  `redemption_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gallons_redeemed` int(11) DEFAULT 1,
  `status` enum('active','used','expired') DEFAULT 'active',
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `item_name` varchar(50) NOT NULL,
  `item_type` enum('container','accessory','other') DEFAULT 'container',
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

INSERT INTO `inventory` (`inventory_id`, `item_name`, `item_type`, `stock_quantity`, `unit_price`, `reorder_level`, `status`, `last_updated`, `updated_by`) VALUES
(1, '5-Gallon Slim Container', 'container', 130, 25.00, 20, 'available', '2026-03-26 11:05:03', 1),
(2, '5-Gallon Round Container', 'container', 134, 25.00, 15, 'available', '2026-03-26 14:50:14', NULL);

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
(1, 2, 10, 3, 0, 0, '2026-03-25', NULL),
(2, 6, 2, 2, 0, 0, '2026-03-26', NULL),
(4, 7, 1, 1, 0, 0, '2026-03-23', NULL);

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

INSERT INTO `orders` (`order_id`, `customer_id`, `order_date`, `delivery_date`, `delivery_address`, `contact_number`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `notes`, `created_at`, `updated_at`) VALUES
(6, 2, '2026-03-12 15:12:35', '2026-03-12', 'rizal street, brgy baliwag,san carlos', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-03-12 15:12:35', '2026-03-14 14:17:42'),
(7, 2, '2026-03-13 03:42:05', '0000-00-00', 'rizal street, brgy baliwag,san carlos', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'asdsda', '2026-03-13 03:42:05', '2026-03-23 13:59:26'),
(8, 2, '2026-03-13 15:40:31', '2026-03-24', 'rizal street, brgy baliwag,san carlos', '0987654323456', 75.00, 'cash_on_delivery', 'pending', 'cancelled', 'grt', '2026-03-13 15:40:31', '2026-03-13 15:40:43'),
(9, 2, '2026-03-13 15:41:29', '2026-03-26', 'rizal street, brgy baliwag,san carlos', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'helo', '2026-03-13 15:41:29', '2026-03-15 05:50:02'),
(10, 2, '2026-03-14 04:12:29', '0000-00-00', 'rizal street, brgy baliwag,san carlos', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'hyt', '2026-03-14 04:12:29', '2026-03-15 05:49:59'),
(11, 2, '2026-03-14 07:21:17', '2026-03-15', 'rizal street, brgy baliwag,san carlos', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'cancelled', '', '2026-03-14 07:21:17', '2026-03-14 07:21:59'),
(12, 2, '2026-03-14 10:46:21', '2026-03-14', 'rizal street, brgy baliwag,san carlos', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'hshhsh', '2026-03-14 10:46:21', '2026-03-14 10:47:45'),
(13, 2, '2026-03-14 10:56:35', '2026-03-21', 'rizal street, brgy baliwag,san carlos', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-03-14 10:56:35', '2026-03-15 05:54:36'),
(14, 6, '2026-03-15 05:57:07', '0000-00-00', 'rizal street, brgy pagal, dagupan', '099958501733', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'try', '2026-03-15 05:57:07', '2026-03-23 14:24:27'),
(15, 2, '2026-03-15 05:58:30', '0000-00-00', 'rizal street, brgy baliwag,san carlos', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'ill get my freegallon paldo', '2026-03-15 05:58:30', '2026-03-23 14:57:56'),
(16, 7, '2026-03-15 06:09:55', '0000-00-00', 'rizal street, brgy cruz ,san carlos', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'pending', '', '2026-03-15 06:09:55', '2026-03-26 15:52:25'),
(17, 7, '2026-03-15 06:13:27', '2026-03-16', 'rizal street, brgy cruz ,san carlos', '098765322343', 125.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-03-15 06:13:27', '2026-03-23 14:58:00'),
(18, 6, '2026-03-23 12:54:56', '2026-03-23', 'rizal street, brgy pagal, dagupan', '099958501733', 125.00, 'cash_on_delivery', 'pending', 'confirmed', 'hello', '2026-03-23 12:54:56', '2026-03-26 15:52:00'),
(19, 2, '2026-03-25 14:47:14', '2026-03-25', 'rizal street, brgy baliwag,san carlos', '0987654323456', 125.00, 'cash_on_delivery', 'pending', 'delivered', 'hello', '2026-03-25 14:47:14', '2026-03-25 14:51:58'),
(20, 6, '2026-03-25 16:08:31', '2026-03-26', 'rizal street, brgy pagal, dagupan', '099958501733', 125.00, 'cash_on_delivery', 'pending', 'delivered', '', '2026-03-25 16:08:31', '2026-03-26 15:06:53'),
(21, 6, '2026-03-26 14:50:14', '2026-03-26', 'rizal street, brgy pagal, dagupan', '099958501733', 150.00, 'cash_on_delivery', 'pending', 'confirmed', '', '2026-03-26 14:50:14', '2026-03-26 14:50:15');

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
(21, 21, 2, 6, 25.00);

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
(1, 'admin', '$2y$10$EMoafSb53CJxHi88ZvNB3O6xoLcE2.qDQEpZjOSYGIZsLaiI9hPme', 'admin@israphil.com', '09123456789', 'System Administrator', 'Main Branch', 'admin', 'active', '2026-03-11 13:54:13', '2026-03-27 15:22:14', '2026-03-27 15:22:14'),
(2, 'testcustomer', '$2y$10$KvSmS/i1JXXtwjjBCboGlezh7xLKA1fYgMM5Yyr4wKroPMGFcqfU6', 'testcustomer123@gmail.com', '0987654323456', 'test cusmoter', 'rizal street, brgy baliwag,san carlos', 'customer', 'active', '2026-03-12 13:20:11', '2026-03-25 15:54:42', '2026-03-25 15:54:42'),
(3, 'staff1', '$2y$10$vg5dbCxf4Pv3xLvhKLJ08.nmc1TmHkvHWbBIKsHuO5/xOsrX597cm', 'staff123@gmail.com', '099958501733', 'staff one', 'rizal street, brgy pagal,san carlos', 'staff', 'active', '2026-03-13 03:23:17', '2026-03-27 11:45:36', '2026-03-27 11:45:36'),
(4, 'staff2', '$2y$10$LGQEPM2Ec5ZMfB.fu.l7beyJgpnlV3yxmc7gqL2Uh1fH91KE//tke', 'michaelfrias72@gmail.com', '099968501733', 'Michael Frias', 'mapolopolo, basista city', 'staff', 'active', '2026-03-14 10:20:26', '2026-03-26 15:52:12', '2026-03-26 15:52:12'),
(5, 'staff3', '$2y$10$mVVAm4DJoyU6YCJfBN3eFeg/BNbnp2Exh2DmNKYJYGufhfYicnc7G', 'jandavidmanayan69@gmail.com', '456789993', 'John David Manayan', 'pdf stret, brgy bayuasm, urbiztondo', 'staff', 'active', '2026-03-14 10:21:47', '2026-03-25 14:51:54', '2026-03-25 14:51:54'),
(6, 'customer1', '$2y$10$/R/EH5evF5GodB2lD7q.i..R6mx2BD7LVCe2mULO5LMd.kl77CMfa', 'kazumakiri69@gmail.com', '099958501733', 'edison panuyas', 'rizal street, brgy pagal, dagupan', 'customer', 'active', '2026-03-15 01:28:46', '2026-03-27 11:43:58', '2026-03-27 11:43:58'),
(7, 'customer2', '$2y$10$RQbe2vrJ0xp9Pzgmap84MOcd.TVrQr91QINP0UWbwHR3EDlGApYIq', 'tony123@gmail.com', '098765322343', 'tony stark', 'rizal street, brgy cruz ,san carlos', 'customer', 'active', '2026-03-15 01:40:54', '2026-03-23 14:55:52', '2026-03-23 14:55:52');

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

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_staff_workload`  AS SELECT `u`.`user_id` AS `staff_id`, `u`.`full_name` AS `staff_name`, count(`d`.`delivery_id`) AS `active_deliveries`, sum(case when `d`.`delivery_status` = 'assigned' then 1 else 0 end) AS `pending_pickup`, sum(case when `d`.`delivery_status` in ('picked_up','in_transit') then 1 else 0 end) AS `in_progress` FROM (`users` `u` left join `deliveries` `d` on(`u`.`user_id` = `d`.`staff_id` and `d`.`delivery_status` not in ('delivered','failed','returned'))) WHERE `u`.`role` = 'staff' AND `u`.`status` = 'active' GROUP BY `u`.`user_id` ;

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
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`delivery_id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_deliveries_staff` (`staff_id`),
  ADD KEY `idx_deliveries_status` (`delivery_status`);

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `free_gallon_redemptions`
--
ALTER TABLE `free_gallon_redemptions`
  MODIFY `redemption_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `loyalty`
--
ALTER TABLE `loyalty`
  MODIFY `loyalty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `deliveries_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

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
