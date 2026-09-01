-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 29, 2026 at 10:10 PM
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
-- Database: `tricycle_franchise_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Super Admin','Admin') DEFAULT 'Admin',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `first_name`, `last_name`, `username`, `email`, `password`, `role`, `status`, `last_login`, `created_at`, `address`) VALUES
(1, 'System', 'Administrator', 'admin', 'admin@tricyclefranchise.com', '$2y$10$WcGsCs.IE8uVilcX8Fvwu.2aeDtR4BWg8Ne8WWhVL4fCyuQhS0Zxm', 'Super Admin', 'Active', '2026-08-30 03:54:07', '2026-08-24 11:20:43', NULL),
(3, 'John', 'Doe', 'john', 'blasmiggy@gmail.com', '$2y$10$CmqQDwkMVLYrTjyfEjPUhOkGZY2HFyztLAxJuCYUevFqkFXxRzB0u', 'Admin', 'Active', NULL, '2026-08-29 04:32:23', 'Alicia, Isablea');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `driver_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `driver_license` varchar(255) NOT NULL,
  `or_cr` varchar(255) DEFAULT NULL,
  `president_certificate` varchar(255) DEFAULT NULL,
  `status` enum('Pending','For Review','Approved') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email` varchar(255) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`driver_id`, `full_name`, `contact_number`, `age`, `gender`, `address`, `driver_license`, `or_cr`, `president_certificate`, `status`, `created_at`, `updated_at`, `email`, `admin_id`) VALUES
(1, 'John Doe', '0987654321', 34, 'Male', 'address', 'uploads/driver_license_36a9e550c3c17e40bd20.jpg', 'uploads/or_cr_6cef98f5f4a7fffdf627.gif', 'uploads/president_certificate_f6bc232ef44f357700b8.jpg', 'Approved', '2026-08-25 16:04:14', '2026-08-25 16:04:14', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `driver_tricycle`
--

CREATE TABLE `driver_tricycle` (
  `assignment_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `tricycle_id` int(11) NOT NULL,
  `assigned_date` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `driver_tricycle`
--

INSERT INTO `driver_tricycle` (`assignment_id`, `driver_id`, `tricycle_id`, `assigned_date`) VALUES
(2, 1, 2, '2026-08-26');

-- --------------------------------------------------------

--
-- Table structure for table `franchises`
--

CREATE TABLE `franchises` (
  `franchise_id` int(11) NOT NULL,
  `franchise_name` varchar(150) NOT NULL,
  `owner_name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `renewal_status` enum('Active','Expired','Pending Renewal') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `owner_email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `franchises`
--

INSERT INTO `franchises` (`franchise_id`, `franchise_name`, `owner_name`, `address`, `issue_date`, `expiry_date`, `renewal_status`, `created_at`, `updated_at`, `owner_email`) VALUES
(3, 'Somewhere Franchise', 'Cardo Dalisay', 'Address', '2026-08-28', '2027-05-26', 'Active', '2026-08-25 16:05:50', '2026-08-25 16:05:50', NULL),
(5, 'san mateo', 'Ricardo Dalisaysay', 'Address address', '2025-08-29', '2026-08-30', 'Active', '2026-08-29 02:10:26', '2026-08-29 02:10:26', NULL),
(6, 'cauayan', 'Ricardo Dalisaysaysay', 'Address de address', '2025-08-29', '2026-08-30', 'Active', '2026-08-29 02:17:55', '2026-08-29 02:17:55', NULL),
(7, 'santiago', 'Ricardo Dalisaysaysaysay', 'Address de address adress', '2025-08-29', '2026-08-30', 'Active', '2026-08-29 02:26:40', '2026-08-29 02:26:40', NULL),
(11, 'Ipad franchise', 'John Doe', 'San pablo, Isabela', '2026-08-29', '2027-08-29', 'Active', '2026-08-29 05:09:50', '2026-08-29 05:09:50', 'blasmiggy@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `franchise_applications`
--

CREATE TABLE `franchise_applications` (
  `application_id` int(11) NOT NULL,
  `rider_id` int(11) DEFAULT NULL,
  `rider_name` varchar(150) NOT NULL,
  `rider_email` varchar(255) NOT NULL,
  `franchise_id` int(11) DEFAULT NULL,
  `franchise_name` varchar(150) DEFAULT NULL,
  `application_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `admin_comments` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `address` text DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `receipt_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `franchise_applications`
--

INSERT INTO `franchise_applications` (`application_id`, `rider_id`, `rider_name`, `rider_email`, `franchise_id`, `franchise_name`, `application_date`, `status`, `admin_comments`, `updated_at`, `address`, `issue_date`, `expiry_date`, `receipt_photo`) VALUES
(1, 3, 'John Doe', 'blasmiggy@gmail.com', 11, 'Ipad franchise', '2026-08-29 05:08:51', 'Approved', '', '2026-08-29 05:09:50', 'San pablo, Isabela', '2026-08-29', '2027-08-29', 'uploads/franchise_application_receipt_183fa9bf495c503bca00.gif');

-- --------------------------------------------------------

--
-- Table structure for table `franchise_documents`
--

CREATE TABLE `franchise_documents` (
  `document_id` int(11) NOT NULL,
  `franchise_id` int(11) NOT NULL,
  `receipt_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `franchise_documents`
--

INSERT INTO `franchise_documents` (`document_id`, `franchise_id`, `receipt_photo`) VALUES
(1, 3, 'uploads/franchise_receipt_1e5c2213d1d4d553fee9.jpg'),
(2, 4, 'uploads/franchise_receipt_0e5007b8c29c9af7f33b.gif'),
(3, 5, 'uploads/franchise_receipt_32b629c44b9f76bd1db8.gif'),
(4, 6, 'uploads/franchise_receipt_1a8fc2da1b4acbbd2790.gif'),
(5, 7, 'uploads/franchise_receipt_520a2557b91ccc9e3a8a.gif'),
(6, 8, 'uploads/franchise_receipt_f24798b60cc28aa2f512.gif'),
(7, 9, 'uploads/franchise_receipt_4e958d594fbd458203d4.gif'),
(8, 10, 'uploads/franchise_receipt_4238c6e47a16e1fe76c9.gif'),
(9, 11, 'uploads/franchise_application_receipt_183fa9bf495c503bca00.gif');

-- --------------------------------------------------------

--
-- Table structure for table `franchise_driver`
--

CREATE TABLE `franchise_driver` (
  `assignment_id` int(11) NOT NULL,
  `franchise_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `franchise_tricycle`
--

CREATE TABLE `franchise_tricycle` (
  `assignment_id` int(11) NOT NULL,
  `franchise_id` int(11) NOT NULL,
  `tricycle_id` int(11) NOT NULL,
  `assigned_date` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `franchise_tricycle`
--

INSERT INTO `franchise_tricycle` (`assignment_id`, `franchise_id`, `tricycle_id`, `assigned_date`) VALUES
(1, 3, 2, '2026-08-26'),
(2, 11, 3, '2026-08-30');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'Franchise',
  `severity` varchar(20) DEFAULT 'info',
  `recipient_email` varchar(255) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(100) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `title`, `message`, `type`, `severity`, `recipient_email`, `related_id`, `related_type`, `is_read`, `created_at`) VALUES
(1, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 29, 2026 at 10:34 AM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 02:34:22'),
(2, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 29, 2026 at 10:56 AM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 02:56:17'),
(3, 'Franchise Renewal: 1-Day Reminder', 'Your franchise \'san mateo\' will expire tomorrow (2026-08-30). Please renew now.', 'Renewal', 'urgent', 'admin@tricyclefranchise.com', 5, 'franchise_renewal', 0, '2026-08-29 02:59:43'),
(4, 'Franchise Renewal: 1-Day Reminder', 'Your franchise \'cauayan\' will expire tomorrow (2026-08-30). Please renew now.', 'Renewal', 'urgent', 'admin@tricyclefranchise.com', 6, 'franchise_renewal', 0, '2026-08-29 02:59:48'),
(5, 'Franchise Renewal: 1-Day Reminder', 'Your franchise \'santiago\' will expire tomorrow (2026-08-30). Please renew now.', 'Renewal', 'urgent', 'admin@tricyclefranchise.com', 7, 'franchise_renewal', 0, '2026-08-29 02:59:53'),
(6, 'Franchise Renewal: 1-Day Reminder', 'Arian Grande\'s franchise \'alicia\' will expire tomorrow (2026-08-30). Please renew now.', 'Renewal', 'urgent', 'blasmiggy@gmail.com', 9, 'franchise_renewal', 0, '2026-08-29 03:37:47'),
(7, 'Franchise Renewal: 1-Day Reminder', 'Franchise \'alicia\' is about to expire tomorrow (2026-08-30). Please follow up on its renewal.', 'Renewal', 'urgent', 'admin@tricyclefranchise.com', 9, 'franchise_renewal', 0, '2026-08-29 03:37:52'),
(8, 'Franchise Renewal: 1-Day Reminder', 'Franchise \'san mateo\' is about to expire tomorrow (2026-08-30). Please follow up on its renewal.', 'Renewal', 'urgent', 'reinamercedes2026@gmail.com', 5, 'franchise_renewal', 0, '2026-08-29 03:40:12'),
(9, 'Franchise Renewal: 1-Day Reminder', 'Franchise \'cauayan\' is about to expire tomorrow (2026-08-30). Please follow up on its renewal.', 'Renewal', 'urgent', 'reinamercedes2026@gmail.com', 6, 'franchise_renewal', 0, '2026-08-29 03:40:17'),
(10, 'Franchise Renewal: 1-Day Reminder', 'Franchise \'santiago\' is about to expire tomorrow (2026-08-30). Please follow up on its renewal.', 'Renewal', 'urgent', 'reinamercedes2026@gmail.com', 7, 'franchise_renewal', 0, '2026-08-29 03:40:21'),
(11, 'Franchise Renewal: 1-Day Reminder', 'Franchise \'alicia\' is about to expire tomorrow (2026-08-30). Please follow up on its renewal.', 'Renewal', 'urgent', 'reinamercedes2026@gmail.com', 9, 'franchise_renewal', 0, '2026-08-29 03:40:25'),
(12, 'Franchise Renewal: EXPIRED', 'Billie Elish\'s franchise \'BOAD\' expired on 2026-08-28. Please renew immediately to avoid penalties.', 'Renewal', 'urgent', 'blasmiggy@gmail.com', 10, 'franchise_renewal', 0, '2026-08-29 03:45:31'),
(13, 'Franchise Renewal: EXPIRED', 'Franchise \'BOAD\' expired on 2026-08-28. Immediate renewal action is required.', 'Renewal', 'urgent', 'admin@tricyclefranchise.com', 10, 'franchise_renewal', 0, '2026-08-29 03:45:37'),
(14, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 29, 2026 at 12:19 PM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 04:19:03'),
(15, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 29, 2026 at 12:31 PM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 04:31:04'),
(16, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 29, 2026 at 12:40 PM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 04:40:00'),
(17, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 29, 2026 at 1:03 PM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 05:03:47'),
(18, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 29, 2026 at 1:09 PM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 05:09:16'),
(19, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 29, 2026 at 1:56 PM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 05:56:34'),
(20, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 30, 2026 at 3:52 AM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 19:52:46'),
(21, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 30, 2026 at 3:54 AM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 19:54:07');

-- --------------------------------------------------------

--
-- Table structure for table `renewals`
--

CREATE TABLE `renewals` (
  `renewal_id` int(11) NOT NULL,
  `franchise_id` int(11) NOT NULL,
  `renewal_year` year(4) NOT NULL,
  `renewal_date` date NOT NULL,
  `due_date` date NOT NULL,
  `penalty` decimal(10,2) DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `receipt_photo` varchar(255) DEFAULT NULL,
  `receipt_submitted_at` datetime DEFAULT NULL,
  `receipt_status` enum('Not Submitted','Submitted','Confirmed') DEFAULT 'Not Submitted',
  `receipt_confirmed_at` datetime DEFAULT NULL,
  `receipt_confirmed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `riders`
--

CREATE TABLE `riders` (
  `rider_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `driver_license` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tricycles`
--

CREATE TABLE `tricycles` (
  `tricycle_id` int(11) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `engine_number` varchar(100) NOT NULL,
  `chassis_number` varchar(100) NOT NULL,
  `color` varchar(50) DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `sticker_number` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive','Pending') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tricycles`
--

INSERT INTO `tricycles` (`tricycle_id`, `brand`, `engine_number`, `chassis_number`, `color`, `plate_number`, `sticker_number`, `status`, `created_at`, `updated_at`, `admin_id`) VALUES
(2, 'Honda Bugati', '13131435243', '12313131413', 'Blue', 'abcde123', NULL, 'Active', '2026-08-25 16:04:57', '2026-08-29 19:54:20', NULL),
(3, 'Not specified', '1242412413241', '1412414141', NULL, 'lmk123', NULL, 'Pending', '2026-08-29 19:56:01', '2026-08-29 19:56:01', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`driver_id`),
  ADD KEY `idx_drivers_admin_id` (`admin_id`);

--
-- Indexes for table `driver_tricycle`
--
ALTER TABLE `driver_tricycle`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `tricycle_id` (`tricycle_id`);

--
-- Indexes for table `franchises`
--
ALTER TABLE `franchises`
  ADD PRIMARY KEY (`franchise_id`);

--
-- Indexes for table `franchise_applications`
--
ALTER TABLE `franchise_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `idx_rider_email` (`rider_email`),
  ADD KEY `idx_franchise_id` (`franchise_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `franchise_documents`
--
ALTER TABLE `franchise_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD UNIQUE KEY `franchise_id` (`franchise_id`);

--
-- Indexes for table `franchise_driver`
--
ALTER TABLE `franchise_driver`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `franchise_driver` (`franchise_id`,`driver_id`);

--
-- Indexes for table `franchise_tricycle`
--
ALTER TABLE `franchise_tricycle`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `franchise_id` (`franchise_id`),
  ADD KEY `tricycle_id` (`tricycle_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_recipient_email` (`recipient_email`),
  ADD KEY `idx_related_id` (`related_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_read` (`is_read`);

--
-- Indexes for table `renewals`
--
ALTER TABLE `renewals`
  ADD PRIMARY KEY (`renewal_id`),
  ADD KEY `franchise_id` (`franchise_id`),
  ADD KEY `fk_receipt_confirmed_by` (`receipt_confirmed_by`);

--
-- Indexes for table `riders`
--
ALTER TABLE `riders`
  ADD PRIMARY KEY (`rider_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `tricycles`
--
ALTER TABLE `tricycles`
  ADD PRIMARY KEY (`tricycle_id`),
  ADD UNIQUE KEY `engine_number` (`engine_number`),
  ADD UNIQUE KEY `chassis_number` (`chassis_number`),
  ADD UNIQUE KEY `plate_number` (`plate_number`),
  ADD KEY `idx_tricycles_admin_id` (`admin_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `driver_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `driver_tricycle`
--
ALTER TABLE `driver_tricycle`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `franchises`
--
ALTER TABLE `franchises`
  MODIFY `franchise_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `franchise_applications`
--
ALTER TABLE `franchise_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `franchise_documents`
--
ALTER TABLE `franchise_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `franchise_driver`
--
ALTER TABLE `franchise_driver`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `franchise_tricycle`
--
ALTER TABLE `franchise_tricycle`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `renewals`
--
ALTER TABLE `renewals`
  MODIFY `renewal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `riders`
--
ALTER TABLE `riders`
  MODIFY `rider_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tricycles`
--
ALTER TABLE `tricycles`
  MODIFY `tricycle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `driver_tricycle`
--
ALTER TABLE `driver_tricycle`
  ADD CONSTRAINT `driver_tricycle_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `driver_tricycle_ibfk_2` FOREIGN KEY (`tricycle_id`) REFERENCES `tricycles` (`tricycle_id`) ON DELETE CASCADE;

--
-- Constraints for table `franchise_tricycle`
--
ALTER TABLE `franchise_tricycle`
  ADD CONSTRAINT `franchise_tricycle_ibfk_1` FOREIGN KEY (`franchise_id`) REFERENCES `franchises` (`franchise_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `franchise_tricycle_ibfk_2` FOREIGN KEY (`tricycle_id`) REFERENCES `tricycles` (`tricycle_id`) ON DELETE CASCADE;

--
-- Constraints for table `renewals`
--
ALTER TABLE `renewals`
  ADD CONSTRAINT `fk_receipt_confirmed_by` FOREIGN KEY (`receipt_confirmed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `renewals_ibfk_1` FOREIGN KEY (`franchise_id`) REFERENCES `franchises` (`franchise_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
