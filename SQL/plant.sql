-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 03, 2026 at 09:10 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `plant`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `username`, `password`) VALUES
(1, 'garde', '$2y$10$H09pzaxEzI4XmZUr7e.IaueDUJXgzYRXy.JV4kGrp7jcWJfsY18YO'),
(2, 'admin', '$2y$10$bJSI0rd.MIol5UCzbHmn8uKLxgsbV/Xt4hE7wHGLbxAvI/eDCpR2W');

-- --------------------------------------------------------

--
-- Table structure for table `customer_info`
--

CREATE TABLE `customer_info` (
  `customer_id` int(11) NOT NULL,
  `firstname` text NOT NULL,
  `lastname` text NOT NULL,
  `address` text DEFAULT NULL,
  `contact_number` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_info`
--

INSERT INTO `customer_info` (`customer_id`, `firstname`, `lastname`, `address`, `contact_number`, `created_at`) VALUES
(5, 'lQYjJTmeq0M2U8z3zAVmNmVmVkJCR0FURmJRYjFsY3I3ZTBUeGc9PQ==', 'V0wXsIlrRcXHvnuVRVzr7zc4a2xYUExZTzIreTZ4cFpVTmFUZkE9PQ==', '2bEKO/JkfeBQ4o8peMZ77Xlod1dXUk1aakFYeGxPR0dVYmsxZm5XVTkwZmZhdjY3cW5TWFZOVlUycTdwd3RWYmtLbTJxKytucGQ1bkpMQVU=', 'ct9yXYYCDFCVCEjoNpQXAEVzWUNGSmUrektua3NiNGNtQXA1c2c9PQ==', '2026-05-03 00:44:56'),
(6, 'GQQpgx5DJBmCx4+IlBmYa3YrbDVncitBTHF6d0Q4Yk1CbnhMUVE9PQ==', 'pnycK0cfDAnAyGfMY14G1XlTSXRGUmhaOVNIRWxERUtNVE1VcXc9PQ==', 'L3sPLNHSggPxxRuLUc73STlOazdWWTZpK0RRd3N3eUlUMUErWkN1U3FEWmtBMThGZ0RZQ2NLSlo5QW54ZVMwaUZScWJ5R0N1cEQvNXk3SSs=', 'hrShZjm1CBtHhBwdSCv17GVFS0lIVEd1dlJ2Rk5TNTlJbm9LelE9PQ==', '2026-05-03 06:09:41'),
(7, 'u30Flf3BTrpp8aQF+j26L0V6cnlTS1pKL0hXdmVYQWcyaHdaL2c9PQ==', 'CsFrFS4LO1viOGA/Dtkp4mlrcXlKWmd0Y09mWE5xR2Z6VW9Sb1E9PQ==', 'rZ+50oJFgLsLFwPB5EoENHJKVm5YWWxKeDFtcVZ4WnBSTm1CWXl5V2pEZVNkdmhjQ2lmNE1WSFBOcjBjZi9LUDRvNE9QN2x3TkcvMFJYd1A=', 'MGF7ZAl6zJIFdCKTnImzzi9yNCtPMVlBZisyTzA3dlA5OUxqOVE9PQ==', '2026-05-03 06:17:29'),
(8, '92j7awtydtKnUod6qGIpPTRyMlFOVHBvM29oMGcyNzcxMTdsaFE9PQ==', 'AT+JJi1Kln7cA+nqafNr2lY2WGs1dnJ1WWx5Y1l4R2VwZUZOUUE9PQ==', '6BxtajXXZ9V3sDD+XQRx1XdQbDZmZlpsM0lVR3JQQ21BLzVHcitoSGF5Rk9idzlKK0h1aURkbVFheVVtMnArZ0cyTlllQmhRWnBWdEVvb1g=', 'cCyRIuRvICyTREpOZ+epwUJwNFVyTDYwU3E5SmNQTzlYUUprZnc9PQ==', '2026-05-03 06:28:48'),
(9, 'pJ6Y++xGwpi1Twj0BZ87I2Y0WW1QS2RkVFRCbzVscFpEUFVJTXc9PQ==', 'krNhKe6SG+bVYxCqOfF1DXExZ29lZlpFaHZFc3B0TjFjY3hlWFE9PQ==', 'hegMMW/qEUsF58z3TGf7tkxoZTd1L25GbytwT1pVNzZ1UVpMUkRCdDd1aVluZzNiVGFDckVIZHBjVVFJOXZkUmN5YmVVYUFvV3lkL21aZlc=', 'vJVOv2rIhS+ZfuJhtIMl7TdtaEJEWDlFNWVSNFdkVUxvdzNIQkE9PQ==', '2026-05-03 06:52:14');

-- --------------------------------------------------------

--
-- Table structure for table `damage_photos`
--

CREATE TABLE `damage_photos` (
  `photo_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `damage_photos`
--

INSERT INTO `damage_photos` (`photo_id`, `report_id`, `photo_path`) VALUES
(5, 6, 'uploads/damage/dmg_69f6eb0507605.jpeg');

-- --------------------------------------------------------

--
-- Table structure for table `damage_reports`
--

CREATE TABLE `damage_reports` (
  `report_id` int(11) NOT NULL,
  `plot_id` int(11) NOT NULL,
  `plot_seedling_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `quantity_damaged` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `damage_reports`
--

INSERT INTO `damage_reports` (`report_id`, `plot_id`, `plot_seedling_id`, `staff_id`, `quantity_damaged`, `description`, `reported_at`) VALUES
(6, 16, 6, 6, 2, 'Na apakan ka kabayo', '2026-05-03 06:28:21');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `variety_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `variety_id`, `quantity`, `updated_at`) VALUES
(5, 1, 6, '2026-05-03 06:52:14');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `log_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `variety_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`log_id`, `staff_id`, `variety_id`, `quantity`, `logged_at`) VALUES
(6, 6, 1, 10, '2026-05-03 06:27:46');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `device_hash` varchar(64) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'admin',
  `attempts` int(11) DEFAULT 0,
  `ban_until` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `ordered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `staff_id`, `ordered_at`) VALUES
(8, 8, 6, '2026-05-03 06:28:48'),
(9, 9, 6, '2026-05-03 06:52:14');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `variety_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`item_id`, `order_id`, `variety_id`, `quantity`) VALUES
(9, 8, 1, 1),
(10, 9, 1, 1),
(11, 9, 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `plots`
--

CREATE TABLE `plots` (
  `plot_id` int(11) NOT NULL,
  `plot_number` int(11) NOT NULL,
  `plot_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plots`
--

INSERT INTO `plots` (`plot_id`, `plot_number`, `plot_name`) VALUES
(16, 1, 'Plot 1'),
(17, 2, 'Plot 2'),
(18, 3, 'Plot 3'),
(19, 4, 'Plot 4'),
(20, 5, 'Plot 5'),
(21, 6, 'Plot 6'),
(22, 7, 'Plot 7'),
(23, 8, 'Plot 8'),
(24, 9, 'Plot 9'),
(25, 10, 'Plot 10'),
(26, 11, 'Plot 11'),
(27, 12, 'Plot 12'),
(28, 13, 'Plot 13'),
(29, 14, 'Plot 14'),
(30, 15, 'Plot 15');

-- --------------------------------------------------------

--
-- Table structure for table `plot_seedlings`
--

CREATE TABLE `plot_seedlings` (
  `plot_seedling_id` int(11) NOT NULL,
  `plot_id` int(11) NOT NULL,
  `variety_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plot_seedlings`
--

INSERT INTO `plot_seedlings` (`plot_seedling_id`, `plot_id`, `variety_id`, `staff_id`, `quantity`, `added_at`) VALUES
(6, 16, 1, 6, 88, '2026-05-03 06:27:28');

-- --------------------------------------------------------

--
-- Table structure for table `security_answer`
--

CREATE TABLE `security_answer` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('admin','staff') NOT NULL DEFAULT 'admin',
  `question_id` int(11) NOT NULL,
  `answer_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_answer`
--

INSERT INTO `security_answer` (`id`, `user_id`, `user_type`, `question_id`, `answer_hash`, `created_at`, `updated_at`) VALUES
(9, 4, 'staff', 1, '$2y$10$3dgcYM6jJJZqWz9ieqxXreYYvBCBS4xHefn08gjIIg8r4Qhhaj7pS', '2026-05-03 01:55:04', '2026-05-03 01:55:04');

-- --------------------------------------------------------

--
-- Table structure for table `security_questions`
--

CREATE TABLE `security_questions` (
  `id` int(11) NOT NULL,
  `question` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_questions`
--

INSERT INTO `security_questions` (`id`, `question`) VALUES
(1, 'What is your mother\'s maiden name?'),
(2, 'What was the name of your first pet?'),
(3, 'What city were you born in?'),
(4, 'What is the name of your elementary school?'),
(5, 'What was your nickname?'),
(6, 'What is your favorite childhood memory?'),
(7, 'What was the make of your first car?');

-- --------------------------------------------------------

--
-- Table structure for table `seedlings`
--

CREATE TABLE `seedlings` (
  `seedling_id` int(11) NOT NULL,
  `seedling_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seedlings`
--

INSERT INTO `seedlings` (`seedling_id`, `seedling_name`) VALUES
(1, 'Durian'),
(2, 'Manggo'),
(3, 'Nara');

-- --------------------------------------------------------

--
-- Table structure for table `staff_info`
--

CREATE TABLE `staff_info` (
  `staff_id` int(11) NOT NULL,
  `firstname` varchar(500) NOT NULL,
  `lastname` varchar(500) NOT NULL,
  `address` text NOT NULL,
  `contact` varchar(500) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_info`
--

INSERT INTO `staff_info` (`staff_id`, `firstname`, `lastname`, `address`, `contact`, `username`, `password`, `created_at`) VALUES
(6, 'BESuBzR0VV6sG9NNLkGV1jRaSFJWTGV1M0d6dXFHQkUyTU9ZR0E9PQ==', 'sHE01LIbdwzI4OppAPBlQ01Ra1RnK0hlYkpvZFJqYjY4b0JjNUE9PQ==', 'B5qqo0LIaK20DjCw6WmqDmI2WXBhNjZsSTU1UzhjNGxQRWJFRzVCMHI5VGxaVzA3Tk9sVDZiMUZxSi94MWZXOVg3S1Z6dkpGWVhjY3hxVTY=', 'sTqUALRdDaLxfd/I9In41HBDUHo1N0UzaEFjMXZyTVNCMUVxelE9PQ==', 'ryan', '$2y$10$NyaIR6YAblTYJ80sijbx1OBLL2RQjDD1MlP6LuXEDRhWWskCqKWd6', '2026-05-03 06:16:28');

-- --------------------------------------------------------

--
-- Table structure for table `varieties`
--

CREATE TABLE `varieties` (
  `variety_id` int(11) NOT NULL,
  `seedling_id` int(11) NOT NULL,
  `variety_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `varieties`
--

INSERT INTO `varieties` (`variety_id`, `seedling_id`, `variety_name`, `price`) VALUES
(1, 1, 'Arrancilo', 60.00),
(2, 2, 'Apple Manggo', 40.00),
(3, 1, 'Native', 50.00),
(4, 1, 'Puyapuya', 70.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `customer_info`
--
ALTER TABLE `customer_info`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `damage_photos`
--
ALTER TABLE `damage_photos`
  ADD PRIMARY KEY (`photo_id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `damage_reports`
--
ALTER TABLE `damage_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `plot_id` (`plot_id`),
  ADD KEY `plot_seedling_id` (`plot_seedling_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `variety_id` (`variety_id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `variety_id` (`variety_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device` (`ip_address`,`device_hash`,`type`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `variety_id` (`variety_id`);

--
-- Indexes for table `plots`
--
ALTER TABLE `plots`
  ADD PRIMARY KEY (`plot_id`),
  ADD UNIQUE KEY `plot_number` (`plot_number`);

--
-- Indexes for table `plot_seedlings`
--
ALTER TABLE `plot_seedlings`
  ADD PRIMARY KEY (`plot_seedling_id`),
  ADD KEY `plot_id` (`plot_id`),
  ADD KEY `variety_id` (`variety_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `security_answer`
--
ALTER TABLE `security_answer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_sq` (`user_id`,`user_type`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `security_questions`
--
ALTER TABLE `security_questions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `seedlings`
--
ALTER TABLE `seedlings`
  ADD PRIMARY KEY (`seedling_id`);

--
-- Indexes for table `staff_info`
--
ALTER TABLE `staff_info`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `varieties`
--
ALTER TABLE `varieties`
  ADD PRIMARY KEY (`variety_id`),
  ADD KEY `seedling_id` (`seedling_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customer_info`
--
ALTER TABLE `customer_info`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `damage_photos`
--
ALTER TABLE `damage_photos`
  MODIFY `photo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `damage_reports`
--
ALTER TABLE `damage_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `plots`
--
ALTER TABLE `plots`
  MODIFY `plot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `plot_seedlings`
--
ALTER TABLE `plot_seedlings`
  MODIFY `plot_seedling_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `security_answer`
--
ALTER TABLE `security_answer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `security_questions`
--
ALTER TABLE `security_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `seedlings`
--
ALTER TABLE `seedlings`
  MODIFY `seedling_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_info`
--
ALTER TABLE `staff_info`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `varieties`
--
ALTER TABLE `varieties`
  MODIFY `variety_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `damage_photos`
--
ALTER TABLE `damage_photos`
  ADD CONSTRAINT `damage_photos_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `damage_reports` (`report_id`);

--
-- Constraints for table `damage_reports`
--
ALTER TABLE `damage_reports`
  ADD CONSTRAINT `damage_reports_ibfk_1` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`plot_id`),
  ADD CONSTRAINT `damage_reports_ibfk_2` FOREIGN KEY (`plot_seedling_id`) REFERENCES `plot_seedlings` (`plot_seedling_id`),
  ADD CONSTRAINT `damage_reports_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `staff_info` (`staff_id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`variety_id`) REFERENCES `varieties` (`variety_id`);

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff_info` (`staff_id`),
  ADD CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`variety_id`) REFERENCES `varieties` (`variety_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer_info` (`customer_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff_info` (`staff_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`variety_id`) REFERENCES `varieties` (`variety_id`);

--
-- Constraints for table `plot_seedlings`
--
ALTER TABLE `plot_seedlings`
  ADD CONSTRAINT `plot_seedlings_ibfk_1` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`plot_id`),
  ADD CONSTRAINT `plot_seedlings_ibfk_2` FOREIGN KEY (`variety_id`) REFERENCES `varieties` (`variety_id`),
  ADD CONSTRAINT `plot_seedlings_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `staff_info` (`staff_id`);

--
-- Constraints for table `security_answer`
--
ALTER TABLE `security_answer`
  ADD CONSTRAINT `security_answer_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `security_questions` (`id`),
  ADD CONSTRAINT `security_answer_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `security_questions` (`id`);

--
-- Constraints for table `varieties`
--
ALTER TABLE `varieties`
  ADD CONSTRAINT `varieties_ibfk_1` FOREIGN KEY (`seedling_id`) REFERENCES `seedlings` (`seedling_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;