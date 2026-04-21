-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 21, 2026 at 07:04 PM
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
-- Database: `med_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(3, 'admin', '$2y$10$zqYMVrXk6yN7jqVBTpTnPumNaIpH5FCZQuHshtxv.4NZCDr1l3afS', '2026-03-20 17:11:59');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `qty` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `name`, `email`, `subject`, `message`, `user_id`, `is_read`, `submitted_at`) VALUES
(1, 'kt', 'asdfg@s.com', 'Order Issue', 'sdfghASDFGHTREWQWDFG', NULL, 1, '2026-03-22 19:24:40'),
(2, 'as', 'as@a.com', 'Medicine Enquiry', 'asdfgevfbghjkmjynthbrvecwx', NULL, 1, '2026-03-22 19:30:57');

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 100,
  `requires_prescription` tinyint(1) DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`id`, `name`, `brand`, `price`, `stock`, `requires_prescription`, `image_url`, `description`, `created_at`) VALUES
(1, 'Crocin Advance 650', 'GSK', 60.00, 85, 0, 'assets/img/crocin.jpg', NULL, '2026-03-20 16:22:04'),
(2, 'Dolo 650mg', 'Micro Labs', 32.00, 96, 0, 'assets/img/dolo.jpg', NULL, '2026-03-20 16:22:04'),
(3, 'Vicks Vaporub', 'P&G', 95.00, 100, 0, 'assets/img/vicks.jpg', NULL, '2026-03-20 16:22:04'),
(4, 'Amoxicillin 500mg', 'Cipla', 120.00, 94, 1, 'assets/img/medicine.jpg', NULL, '2026-03-20 16:22:04'),
(5, 'Azithromycin 250mg', 'Sun Pharma', 85.00, 100, 1, 'assets/img/medicine.jpg', NULL, '2026-03-20 16:22:04'),
(6, 'Pan-D Capsule', 'Alkem', 75.00, 100, 0, 'assets/img/medicine.jpg', NULL, '2026-03-20 16:22:04'),
(7, 'Telma 40mg', 'Glenmark', 145.00, 100, 1, 'assets/img/medicine.jpg', NULL, '2026-03-20 16:22:04'),
(8, 'Metformin 500mg SR', 'USV', 55.00, 100, 1, 'assets/img/medicine.jpg', NULL, '2026-03-20 16:22:04'),
(9, 'Paracetamol 500', '', 20.00, 100, 0, '', '', '2026-03-22 19:09:55');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shop_id` int(11) DEFAULT NULL,
  `prescription_id` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `cgst` decimal(10,2) DEFAULT 0.00,
  `sgst` decimal(10,2) DEFAULT 0.00,
  `delivery_fee` decimal(10,2) DEFAULT 20.00,
  `grand_total` decimal(10,2) NOT NULL,
  `delivery_address` text NOT NULL,
  `status` enum('pending','rx_pending','confirmed','dispatched','delivered','cancelled') DEFAULT 'pending',
  `placed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `shop_id`, `prescription_id`, `subtotal`, `cgst`, `sgst`, `delivery_fee`, `grand_total`, `delivery_address`, `status`, `placed_at`, `updated_at`) VALUES
(1, 3, NULL, NULL, 60.00, 3.60, 3.60, 20.00, 87.20, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'confirmed', '2026-03-20 18:17:03', '2026-03-20 18:17:03'),
(2, 3, NULL, NULL, 60.00, 3.60, 3.60, 20.00, 87.20, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'confirmed', '2026-03-20 18:18:58', '2026-03-20 18:18:58'),
(3, 3, NULL, NULL, 92.00, 5.52, 5.52, 20.00, 123.04, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'delivered', '2026-03-22 16:45:27', '2026-03-22 16:46:59'),
(4, 3, 8, NULL, 594.00, 35.64, 35.64, 20.00, 685.28, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'confirmed', '2026-03-22 18:16:57', '2026-03-22 18:16:57'),
(5, 3, 8, NULL, 216.00, 12.96, 12.96, 20.00, 261.92, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'confirmed', '2026-03-22 18:17:13', '2026-03-22 18:17:13'),
(6, 3, 8, NULL, 378.00, 22.68, 22.68, 20.00, 443.36, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'confirmed', '2026-03-22 18:23:32', '2026-03-22 18:23:32'),
(7, 3, 8, NULL, 162.00, 9.72, 9.72, 20.00, 201.44, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'confirmed', '2026-03-22 18:23:56', '2026-03-22 18:23:56'),
(8, 3, 8, 2, 80.00, 4.80, 4.80, 20.00, 109.60, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'confirmed', '2026-03-22 18:32:04', '2026-03-22 18:32:04'),
(9, 4, 8, NULL, 108.00, 6.48, 6.48, 20.00, 140.96, 'thane 400615', 'confirmed', '2026-03-22 19:01:16', '2026-03-22 19:01:16'),
(10, 4, 8, 3, 80.00, 4.80, 4.80, 20.00, 109.60, 'thane 400615', 'confirmed', '2026-03-22 19:08:02', '2026-03-22 19:08:17'),
(11, 4, 8, 3, 80.00, 4.80, 4.80, 20.00, 109.60, 'thane 400615', 'confirmed', '2026-03-22 19:08:41', '2026-03-22 19:08:41'),
(12, 4, 8, NULL, 54.00, 3.24, 3.24, 20.00, 80.48, 'thane 400615', 'confirmed', '2026-03-22 19:08:48', '2026-03-22 19:08:48'),
(13, 4, 8, 3, 134.00, 8.04, 8.04, 20.00, 170.08, 'thane 400615', 'confirmed', '2026-03-22 19:08:54', '2026-03-22 19:08:54'),
(14, 5, 8, 4, 80.00, 4.80, 4.80, 20.00, 109.60, 'dxcvbghygtfdcx vbnj', 'confirmed', '2026-04-21 16:02:25', '2026-04-21 16:04:49'),
(15, 5, 8, 4, 80.00, 4.80, 4.80, 20.00, 109.60, 'dxcvbghygtfdcx vbnj', 'confirmed', '2026-04-21 16:02:41', '2026-04-21 16:04:49'),
(16, 5, 8, NULL, 54.00, 3.24, 3.24, 20.00, 80.48, 'dxcvbghygtfdcx vbnj', 'confirmed', '2026-04-21 16:02:51', '2026-04-21 16:02:51'),
(17, 5, 9, NULL, 64.00, 3.84, 3.84, 20.00, 91.68, 'dxcvbghygtfdcx vbnj', 'delivered', '2026-04-21 16:03:05', '2026-04-21 16:04:59'),
(18, 5, 8, NULL, 32.00, 1.92, 1.92, 20.00, 55.84, 'dxcvbghygtfdcx vbnj', 'confirmed', '2026-04-21 16:11:41', '2026-04-21 16:11:41'),
(19, 5, 8, NULL, 32.00, 1.92, 1.92, 20.00, 55.84, 'dxcvbghygtfdcx vbnj', 'confirmed', '2026-04-21 16:11:52', '2026-04-21 16:11:52');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `medicine_name` varchar(150) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `qty` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `medicine_id`, `medicine_name`, `price`, `qty`) VALUES
(1, 2, 1, 'Crocin Advance 650', 60.00, 1),
(2, 3, 2, 'Dolo 650mg', 32.00, 1),
(3, 3, 1, 'Crocin Advance 650', 60.00, 1),
(4, 4, 1, 'Crocin Advance 650', 54.00, 11),
(5, 5, 1, 'Crocin Advance 650', 54.00, 4),
(6, 6, 1, 'Crocin Advance 650', 54.00, 7),
(7, 7, 1, 'Crocin Advance 650', 54.00, 3),
(8, 8, 4, 'Amoxicillin 500mg', 80.00, 1),
(9, 9, 1, 'Crocin Advance 650', 54.00, 2),
(10, 10, 4, 'Amoxicillin 500mg', 80.00, 1),
(11, 11, 4, 'Amoxicillin 500mg', 80.00, 1),
(12, 12, 1, 'Crocin Advance 650', 54.00, 1),
(13, 13, 4, 'Amoxicillin 500mg', 80.00, 1),
(14, 13, 1, 'Crocin Advance 650', 54.00, 1),
(15, 14, 4, 'Amoxicillin 500mg', 80.00, 1),
(16, 15, 4, 'Amoxicillin 500mg', 80.00, 1),
(17, 16, 1, 'Crocin Advance 650', 54.00, 1),
(18, 17, 2, 'Dolo 650mg', 32.00, 2),
(19, 18, 2, 'Dolo 650mg', 32.00, 1),
(20, 19, 2, 'Dolo 650mg', 32.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `user_id`, `file_path`, `original_filename`, `status`, `admin_note`, `uploaded_at`, `reviewed_at`, `reviewed_by`) VALUES
(1, 3, 'uploads/prescriptions/rx_3_1774030678.pdf', 'SDC_Instructions for Exp2.pdf', 'approved', '', '2026-03-20 18:17:58', '2026-03-20 18:19:42', 3),
(2, 3, 'uploads/prescriptions/rx_3_1774203163.png', 'Untitled.png', 'approved', '', '2026-03-22 18:12:43', '2026-03-22 18:12:55', 3),
(3, 4, 'uploads/prescriptions/rx_4_1774206475.png', 'Untitled.png', 'approved', '', '2026-03-22 19:07:55', '2026-03-22 19:08:17', 3),
(4, 5, 'uploads/prescriptions/rx_5_1776787329.pdf', '6. NLPP-without constraint.pdf', 'approved', '', '2026-04-21 16:02:09', '2026-04-21 16:04:49', 3);

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `maps_link` varchar(500) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`id`, `name`, `address`, `phone`, `maps_link`, `lat`, `lng`, `is_active`, `created_at`) VALUES
(8, 'Apollo', 'Thane 400615', '', '', 19.2650793, 72.9663961, 1, '2026-03-22 18:10:14'),
(9, 'Durga Chemist', 'Mumbai 400086', '', '', 19.0878405, 72.9038882, 1, '2026-03-22 18:58:57');

-- --------------------------------------------------------

--
-- Table structure for table `shop_medicines`
--

CREATE TABLE `shop_medicines` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shop_medicines`
--

INSERT INTO `shop_medicines` (`id`, `shop_id`, `medicine_id`, `price`, `stock`, `created_at`) VALUES
(1, 8, 4, 80.00, 14, '2026-03-22 18:10:56'),
(4, 8, 1, 54.00, 0, '2026-03-22 18:14:35'),
(6, 9, 4, 120.00, 100, '2026-03-22 19:14:47'),
(7, 9, 5, 85.00, 100, '2026-03-22 19:14:53'),
(9, 9, 2, 32.00, 98, '2026-03-22 19:15:03'),
(10, 9, 9, 20.00, 100, '2026-03-22 19:15:07'),
(11, 8, 5, 85.00, 100, '2026-03-22 19:15:12'),
(12, 8, 2, 32.00, 98, '2026-03-22 19:15:18'),
(13, 8, 8, 55.00, 100, '2026-03-22 19:15:23'),
(14, 8, 6, 75.00, 100, '2026-03-22 19:15:28'),
(15, 8, 9, 20.00, 100, '2026-03-22 19:15:33'),
(16, 8, 7, 145.00, 100, '2026-03-22 19:15:37'),
(17, 8, 3, 95.00, 100, '2026-03-22 19:15:40');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remember_token` varchar(64) DEFAULT NULL COMMENT 'SHA-256 hashed remember-me token',
  `token_expiry` datetime DEFAULT NULL COMMENT 'Expiry datetime for remember-me token'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password`, `address`, `created_at`, `remember_token`, `token_expiry`) VALUES
(1, 'test', 'test@ads', 'test', '$2y$10$JLkJ6kQpIC6/b0mXpWHGEe1ZHjlitCSgyws.CNiokSP77utH1SRuC', 'test', '2026-03-20 16:54:57', NULL, NULL),
(2, 'Test', 'SEsfsdf@asdas', '', '$2y$10$eWD.wQkvo6zVhwQ25mVoyeOaCB5DKW6H.a3HznxxYxNUOExSBbQb2', '', '2026-03-20 18:04:00', NULL, NULL),
(3, 'test', 'test@test.com', '9859674834', '$2y$10$mvs4Uv5wZA0Cwo5ZoIdKve5Rv46M3PHAoIMHnTxs7GrUzygJ0UQRq', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '2026-03-20 18:16:26', NULL, NULL),
(4, 'kt', 'test@google.com', '9586784948', '$2y$10$UNo/22L6alpxinPjcNIdmutJV3TddLE2uvmNoh0wWgkQlPj5JsZLy', 'thane 400615', '2026-03-22 18:57:43', NULL, NULL),
(5, 'test', 'remember@gmail.com', '9876543211', '$2y$10$T6WWAYJorwLakkt0KkGrLOM2SrU8Rd7rU3/21ltwohaOuAbKYMom6', 'dxcvbghygtfdcx vbnj', '2026-04-16 13:45:26', '5ba99abdd8c129112aa350715b6302ba7e8bae98e1a5ac95b31298fbdf12c732', '2026-05-21 18:11:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `shop_id` (`shop_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shop_medicines`
--
ALTER TABLE `shop_medicines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shop_medicine_unique` (`shop_id`,`medicine_id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_remember_token` (`remember_token`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `shop_medicines`
--
ALTER TABLE `shop_medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_shop_fk` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shop_medicines`
--
ALTER TABLE `shop_medicines`
  ADD CONSTRAINT `sm_med_fk` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sm_shop_fk` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
