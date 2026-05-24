-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 24, 2026 at 03:16 PM
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
-- Database: `fitness_academy`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `posted_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `title`, `message`, `posted_by`, `created_at`, `is_active`) VALUES
(1, 'First Announcement ', 'Hello fellow gymrat', 9, '2026-05-02 21:50:01', 1);

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time NOT NULL,
  `time_out` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `member_id`, `date`, `time_in`, `time_out`) VALUES
(5, 7, '2026-05-11', '01:39:26', '01:40:08');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `member_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`member_id`, `first_name`, `last_name`, `contact_number`, `gender`, `created_at`, `profile_photo`) VALUES
(5, 'Harry', 'Jaurigue', '09127394453', 'Male', '2026-04-25 11:59:47', 'member_5.jfif'),
(7, 'Patrick', 'Balonda', '09127394467', 'Male', '2026-04-27 13:06:10', NULL),
(8, 'Gerome', 'Lopez', '09127392000', 'Male', '2026-04-28 13:37:23', NULL),
(10, 'Staff', 'User', '09000000000', 'Male', '2026-05-02 13:44:20', NULL),
(11, 'Owner', 'Admin', '09111111111', 'Male', '2026-05-02 14:31:24', NULL),
(13, 'bad', 'guy', '09876543219', 'Male', '2026-05-24 01:27:40', NULL),
(14, 'adam', 'Yu', '09675321987', 'Male', '2026-05-24 06:30:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `membership_plan`
--

CREATE TABLE `membership_plan` (
  `plan_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `duration` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_plan`
--

INSERT INTO `membership_plan` (`plan_id`, `plan_name`, `duration`, `price`) VALUES
(1, 'Basic Plan', 30, 1000.00),
(2, 'Standard Plan', 90, 2700.00),
(3, 'Premium Plan', 180, 5000.00);

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `transaction_reference` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`payment_id`, `subscription_id`, `amount`, `payment_date`, `payment_method`, `status`, `transaction_reference`) VALUES
(4, 4, 4500.00, '2026-05-11', 'Credit Card', 'paid', '5555 5555 5555 4444'),
(5, 5, 4500.00, '2026-05-11', 'Credit Card', 'rejected', '5555 5555 5555 4444'),
(6, 6, 4500.00, '2026-05-11', 'Credit Card', 'rejected', '5555 5555 5555 4444'),
(7, 7, 4500.00, '2026-05-11', 'Credit Card', 'rejected', '5555 5555 5555 4444'),
(8, 8, 2430.00, '2026-05-21', 'Credit Card', 'paid', '9377'),
(9, 9, 900.00, '2026-05-24', 'Credit Card', 'pending', '9876');

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `promo_id` int(11) NOT NULL,
  `promo_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_percent` decimal(5,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`promo_id`, `promo_name`, `description`, `discount_percent`, `start_date`, `end_date`, `plan_id`, `created_by`, `is_active`) VALUES
(1, 'newly open sale', 'get 10% off by registering this month', 10.00, '2026-05-02', '2026-05-25', NULL, 9, 1);

-- --------------------------------------------------------

--
-- Table structure for table `subscription`
--

CREATE TABLE `subscription` (
  `subscription_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Active','Expired','Cancelled') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription`
--

INSERT INTO `subscription` (`subscription_id`, `member_id`, `plan_id`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(4, 7, 3, '2026-05-11', '2026-11-07', 'Active', '2026-05-11 00:27:55'),
(5, 10, 3, '2026-05-11', '2026-11-07', '', '2026-05-11 00:28:20'),
(6, 10, 3, '2026-05-11', '2026-11-07', '', '2026-05-11 00:28:27'),
(7, 10, 3, '2026-05-11', '2026-11-07', '', '2026-05-11 00:28:38'),
(8, 5, 2, '2026-05-21', '2026-08-19', 'Active', '2026-05-21 12:08:08'),
(9, 14, 1, '2026-05-29', '2026-06-28', '', '2026-05-24 06:30:54');

-- --------------------------------------------------------

--
-- Table structure for table `user_account`
--

CREATE TABLE `user_account` (
  `user_id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('member','staff','manager','owner') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `deactivation_note` text DEFAULT NULL,
  `deactivated_by` int(11) DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_account`
--

INSERT INTO `user_account` (`user_id`, `member_id`, `email`, `password`, `role`, `is_active`, `deactivation_note`, `deactivated_by`, `deactivated_at`) VALUES
(4, 5, 'galahangaming15@gmail.com', '$2y$10$Cf1o6mI1ICHUcovwtri0KOnD1gPZmv8N2e3AfXXNJdJ4dXcqZKFBq', 'member', 1, NULL, NULL, NULL),
(6, 7, 'balonda15@gmail.com', '$2y$10$5gKho1w1vk2Bo5pae0Afnutw/pDWBzIDUpQVXBPl0p0jsyBQ34E3q', 'member', 1, NULL, NULL, NULL),
(7, 8, 'himod15@gmail.com', '$2y$10$z4eR.2XAnLEpO00a0vj6ievRDdJcCDwSHJYXberagbn1gfJFIKXsq', 'member', 1, NULL, NULL, NULL),
(9, 10, 'staff@fitnessacademy.com', '$2y$10$Rz36AQg5Mkr8CPZcPIYwAeH6CYskMgWX5VDEShPk2dF1fxfo8N.QW', 'staff', 1, NULL, NULL, NULL),
(10, 11, 'owner@fitnessacademy.com', '$2y$10$9FUSlNvsK9jXwqFgpGelKOCNeNr7S9kJYjH8W6vbRErbi0LQFCdbG', 'owner', 1, NULL, NULL, NULL),
(12, 13, 'badguy@gmail.com', '$2y$10$XKXVBKs47kyIKGaS7Zp8eu7CZER5AM6d0qOw73D9eD7GH9mycF/mq', 'member', 1, NULL, NULL, NULL),
(13, 14, 'adamyu@gmail.com', '$2y$10$9ZS7pQZLVceC2tSzbHBm8Odb5EONppgg1bZ9S16BKNkMeW2iIktsy', 'member', 1, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`member_id`);

--
-- Indexes for table `membership_plan`
--
ALTER TABLE `membership_plan`
  ADD PRIMARY KEY (`plan_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `subscription_id` (`subscription_id`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`promo_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `subscription`
--
ALTER TABLE `subscription`
  ADD PRIMARY KEY (`subscription_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `user_account`
--
ALTER TABLE `user_account`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `member_id` (`member_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `membership_plan`
--
ALTER TABLE `membership_plan`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `promo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscription`
--
ALTER TABLE `subscription`
  MODIFY `subscription_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_account`
--
ALTER TABLE `user_account`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `user_account` (`user_id`);

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE;

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `subscription` (`subscription_id`) ON DELETE CASCADE;

--
-- Constraints for table `promotions`
--
ALTER TABLE `promotions`
  ADD CONSTRAINT `promotions_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `membership_plan` (`plan_id`),
  ADD CONSTRAINT `promotions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `user_account` (`user_id`);

--
-- Constraints for table `subscription`
--
ALTER TABLE `subscription`
  ADD CONSTRAINT `subscription_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscription_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `membership_plan` (`plan_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_account`
--
ALTER TABLE `user_account`
  ADD CONSTRAINT `user_account_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
