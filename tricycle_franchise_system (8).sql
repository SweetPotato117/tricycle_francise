-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2026 at 09:01 AM
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
(1, 'System', 'Administrator', 'admin', 'admin@tricyclefranchise.com', '$2y$10$WcGsCs.IE8uVilcX8Fvwu.2aeDtR4BWg8Ne8WWhVL4fCyuQhS0Zxm', 'Super Admin', 'Active', '2026-08-30 11:44:04', '2026-08-24 11:20:43', NULL),
(4, 'john', 'doe', 'john', 'blasmiggy@gmail.com', '$2y$10$.yztrVGv9H1Aw6Cej72NZO862Cmkx8oqlBo80xJAOIE5rTONmnTOC', 'Admin', 'Active', NULL, '2026-08-29 21:05:01', 'Alicia, Isablea'),
(5, 'reina', 'mercedes', 'Mayora', 'reinamercedes2026@gmail.com', '$2y$10$EOZb2YeAIP5sKo8jcicO4e2FB/Q9ExwXA6btoZ.5xVcBMKUselTz.', 'Super Admin', 'Active', '2026-09-03 14:49:33', '2026-08-30 07:39:13', 'Reina Mercedes'),
(6, 'Manong', 'Berting', 'Berto', 'labsmiggy@gmail.com', '$2y$10$EAOEBgoW3NRbcY/JnHisA.o36QVDvAyPEhTXjFfpKUdDTp/aLzXFO', 'Admin', 'Active', NULL, '2026-08-30 07:41:14', 'Alicia, Isablea');

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
  `driver_license_number` varchar(100) DEFAULT NULL,
  `or_cr_number` varchar(100) DEFAULT NULL,
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

INSERT INTO `drivers` (`driver_id`, `full_name`, `contact_number`, `age`, `gender`, `address`, `driver_license`, `or_cr`, `driver_license_number`, `or_cr_number`, `president_certificate`, `status`, `created_at`, `updated_at`, `email`, `admin_id`) VALUES
(1, 'John Doe', '0987654321', 34, 'Male', 'address', 'uploads/driver_license_36a9e550c3c17e40bd20.jpg', 'uploads/or_cr_6cef98f5f4a7fffdf627.gif', NULL, NULL, 'uploads/president_certificate_f6bc232ef44f357700b8.jpg', 'Approved', '2026-08-25 16:04:14', '2026-08-25 16:04:14', NULL, NULL),
(2, 'Marial Rizal', '09876543152', 23, NULL, 'Address street 09', 'uploads/driver_license_483bdf6d3231b4775131.jpg', 'uploads/or_cr_6bbce60a8cede24aa630.gif', NULL, NULL, 'uploads/president_certificate_f342bfa3cf5ffe65e372.jpg', 'Approved', '2026-08-30 03:43:32', '2026-08-30 03:44:29', NULL, NULL),
(3, 'rene rizal', '0987654321', 24, NULL, 'address street 89', 'uploads/driver_license_f6b4e88c343b2f6e9a39.jpg', 'uploads/or_cr_5b43bde70feeae37e6c2.gif', NULL, NULL, 'uploads/president_certificate_47fd5130ce3cc6dec73d.jpg', 'Approved', '2026-08-30 05:12:15', '2026-08-30 07:30:00', NULL, NULL),
(4, 'Ariana Grande', '098765432', 27, NULL, 'Address street, city, 90', 'uploads/driver_license_234a35c6f0cc24f2deb1.jpg', 'uploads/or_cr_091add83ff9603850a02.jpg', NULL, NULL, 'uploads/president_certificate_4506ef8135808580c5da.jpg', 'Approved', '2026-08-30 07:34:45', '2026-09-01 05:59:44', NULL, NULL),
(5, 'Elsa', '09876321', 22, NULL, 'Reina Mercede Ice castle', 'uploads/driver_license_e6ab06d527fb993a5dfa.jpg', 'uploads/or_cr_4527513eccc8fc680b52.jpg', NULL, NULL, 'uploads/president_certificate_98a76c589341934e349f.jpg', 'Approved', '2026-08-30 08:13:19', '2026-08-30 08:14:22', NULL, NULL),
(6, 'Anna', '0987654322', 35, NULL, 'address street reina', 'uploads/driver_license_2f31e67e561e3aa1812b.gif', 'uploads/or_cr_5625aa38d7bf4a2fa5c4.jpg', NULL, NULL, 'uploads/president_certificate_136c9332658a056a85ba.gif', 'Approved', '2026-08-30 09:26:04', '2026-08-30 09:26:34', NULL, NULL);

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
(9, 2, 7, '2026-08-30'),
(10, 6, 9, '2026-08-30'),
(11, 2, 13, '2026-09-01');

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
(11, 'Ipad franchise', 'John Doe', 'San pablo, Isabela', '2026-08-29', '2027-08-29', 'Active', '2026-08-29 05:09:50', '2026-08-29 05:09:50', 'blasmiggy@gmail.com'),
(12, 'Berting\'s franchise', 'Manong Berting', 'Reina Mercedes somewhere', '2029-01-01', '2030-01-01', 'Active', '2026-08-30 07:55:33', '2026-08-30 09:02:50', 'labsmiggy@gmail.com');

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
(1, 3, 'John Doe', 'blasmiggy@gmail.com', 11, 'Ipad franchise', '2026-08-29 05:08:51', 'Approved', '', '2026-08-29 05:09:50', 'San pablo, Isabela', '2026-08-29', '2027-08-29', 'uploads/franchise_application_receipt_183fa9bf495c503bca00.gif'),
(2, 6, 'Manong Berting', 'labsmiggy@gmail.com', 12, 'Berting\'s franchise', '2026-08-30 07:53:38', 'Approved', '', '2026-08-30 07:55:33', 'Reina Mercedes somewhere', '2027-01-01', '2028-01-01', 'uploads/franchise_application_receipt_55227a00293303dfad63.jpg');

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
(9, 11, 'uploads/franchise_application_receipt_183fa9bf495c503bca00.gif'),
(10, 12, 'uploads/franchise_application_receipt_55227a00293303dfad63.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `franchise_driver`
--

CREATE TABLE `franchise_driver` (
  `assignment_id` int(11) NOT NULL,
  `franchise_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `franchise_driver`
--

INSERT INTO `franchise_driver` (`assignment_id`, `franchise_id`, `driver_id`) VALUES
(1, 11, 2),
(2, 11, 3),
(3, 11, 4),
(4, 12, 5),
(5, 12, 6);

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
(2, 11, 3, '2026-08-30'),
(5, 11, 7, '2026-08-30'),
(6, 11, 8, '2026-08-30'),
(7, 12, 9, '2026-08-30'),
(8, 11, 12, '2026-09-01'),
(9, 11, 13, '2026-09-01');

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
(21, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 30, 2026 at 3:54 AM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 19:54:07'),
(22, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 30, 2026 at 4:22 AM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 20:22:06'),
(23, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 30, 2026 at 5:00 AM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-29 21:00:26'),
(24, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 30, 2026 at 11:43 AM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-30 03:43:58'),
(25, 'Super Admin Login Detected', 'System Administrator signed in to the Tricycle Franchise System on August 30, 2026 at 11:44 AM.', 'Admin', 'info', 'admin@tricyclefranchise.com', 1, 'super_admin_login', 0, '2026-08-30 03:44:04'),
(26, 'Driver License Status Updated', 'Your driver license application status has been updated to: Approved. Congratulations! You are now approved to operate.', 'Driver', 'info', NULL, 2, 'driver_status', 0, '2026-08-30 03:44:29'),
(27, 'Tricycle Status: Inactive', 'Tricycle mk32 status changed from Pending to Inactive.', 'Tricycle', 'urgent', 'blasmiggy@gmail.com', 6, 'tricycle_status_change', 0, '2026-08-30 04:36:18'),
(28, 'mk32 - Tricycle Status: Inactive', 'Tricycle mk32 status changed from Pending to Inactive.', 'Tricycle', 'urgent', 'reinamercedes2026@gmail.com', 6, 'tricycle_status_change', 0, '2026-08-30 04:36:27'),
(29, 'Tricycle Status: Inactive', 'Tricycle mk32 status changed from Inactive to Inactive.', 'Tricycle', 'urgent', 'blasmiggy@gmail.com', 6, 'tricycle_status_change', 0, '2026-08-30 04:36:32'),
(30, 'mk32 - Tricycle Status: Inactive', 'Tricycle mk32 status changed from Inactive to Inactive.', 'Tricycle', 'urgent', 'reinamercedes2026@gmail.com', 6, 'tricycle_status_change', 0, '2026-08-30 04:36:37'),
(31, 'abcde123 - Tricycle Status: Inactive', 'Tricycle abcde123 status changed from Active to Inactive.', 'Tricycle', 'urgent', 'reinamercedes2026@gmail.com', 2, 'tricycle_status_change', 0, '2026-08-30 04:37:32'),
(32, 'Tricycle Submission Edited', 'The pending tricycle submission for TMX (1234) was edited by the rider.', 'Tricycle', 'warning', 'blasmiggy@gmail.com', 7, 'tricycle_submission_edited', 0, '2026-08-30 04:41:48'),
(33, 'Tricycle Submission Edited', 'The pending tricycle submission for TMX (1234) was edited by the rider.', 'Tricycle', 'warning', 'blasmiggy@gmail.com', 7, 'tricycle_submission_edited', 0, '2026-08-30 04:41:54'),
(34, 'Tricycle Submission Edited', 'The pending tricycle submission for TMX (1234) was edited by the rider.', 'Tricycle', 'warning', 'blasmiggy@gmail.com', 7, 'tricycle_submission_edited', 0, '2026-08-30 04:41:59'),
(35, 'Tricycle Submission Edited', 'The pending tricycle submission for TMX (1234) was edited by the rider.', 'Tricycle', 'warning', 'blasmiggy@gmail.com', 7, 'tricycle_submission_edited', 0, '2026-08-30 04:42:04'),
(36, 'Tricycle Submission Edited', 'The pending tricycle submission for TMX (1234) was edited by the rider.', 'Tricycle', 'warning', 'blasmiggy@gmail.com', 7, 'tricycle_submission_edited', 0, '2026-08-30 04:42:10'),
(37, 'Tricycle Submission Edited', 'The pending tricycle submission for TMX (1234) was edited by the rider.', 'Tricycle', 'warning', 'blasmiggy@gmail.com', 7, 'tricycle_submission_edited', 0, '2026-08-30 04:42:15'),
(38, 'Tricycle Status: Active', 'Tricycle 1234 status changed from Pending to Active.', 'Tricycle', 'info', 'blasmiggy@gmail.com', 7, 'tricycle_status_change', 0, '2026-08-30 06:44:03'),
(39, '1234 - Tricycle Status: Active', 'Tricycle 1234 status changed from Pending to Active.', 'Tricycle', 'info', 'reinamercedes2026@gmail.com', 7, 'tricycle_status_change', 0, '2026-08-30 06:44:09'),
(40, 'Driver License Status Updated', 'Your driver license application status has been updated to: Approved. Congratulations! You are now approved to operate.', 'Driver', 'info', 'blasmiggy@gmail.com', 3, 'driver_status', 0, '2026-08-30 07:30:00'),
(41, 'rene rizal - Driver License Status Updated', 'rene rizal\'s status changed from Pending to Approved.', 'Driver', 'info', 'reinamercedes2026@gmail.com', 3, 'driver_status', 0, '2026-08-30 07:30:06'),
(42, 'New Driver Registration', 'john doe submitted driver registration for \'Ariana Grande\'.', 'Driver', 'warning', 'admin@tricyclefranchise.com', 4, 'driver_submission', 1, '2026-08-30 07:34:45'),
(43, 'New Tricycle Registration', 'john doe submitted tricycle registration for plate \'DFR312\'.', 'Tricycle', 'warning', 'admin@tricyclefranchise.com', 8, 'tricycle_submission', 0, '2026-08-30 07:36:01'),
(44, 'Super Admin Login Detected', 'reina mercedes signed in to the Tricycle Franchise System on August 30, 2026 at 3:39 PM.', 'Admin', 'info', 'reinamercedes2026@gmail.com', 5, 'super_admin_login', 0, '2026-08-30 07:39:37'),
(45, 'Super Admin Login Detected', 'reina mercedes signed in to the Tricycle Franchise System on August 30, 2026 at 3:41 PM.', 'Admin', 'info', 'reinamercedes2026@gmail.com', 5, 'super_admin_login', 0, '2026-08-30 07:41:30'),
(46, 'New Franchise Application', 'Manong Berting submitted a franchise application for \'Berting\'s franchise\'.', 'Franchise', 'warning', 'admin@tricyclefranchise.com', 2, 'franchise_submission', 0, '2026-08-30 07:53:38'),
(47, 'New Franchise Application', 'Manong Berting submitted a franchise application for \'Berting\'s franchise\'.', 'Franchise', 'warning', 'reinamercedes2026@gmail.com', 2, 'franchise_submission', 0, '2026-08-30 07:53:42'),
(48, 'Franchise Application Approved!', 'Your application for Berting\'s franchise franchise has been approved. Your franchise has been approved and is now active.', 'Franchise', 'info', 'labsmiggy@gmail.com', NULL, 'franchise_approval', 0, '2026-08-30 07:55:33'),
(49, 'Franchise Application Approved', 'Manong Berting has been approved for Berting\'s franchise', 'Franchise', 'info', 'reinamercedes2026@gmail.com', 12, 'franchise_approval', 0, '2026-08-30 07:55:38'),
(50, 'New Tricycle Registration', 'Manong Berting submitted tricycle registration for plate \'mk45\'.', 'Tricycle', 'warning', 'admin@tricyclefranchise.com', 9, 'tricycle_submission', 0, '2026-08-30 08:09:24'),
(51, 'New Tricycle Registration', 'Manong Berting submitted tricycle registration for plate \'mk45\'.', 'Tricycle', 'warning', 'reinamercedes2026@gmail.com', 9, 'tricycle_submission', 0, '2026-08-30 08:09:30'),
(52, 'Tricycle Status: Active', 'Tricycle mk45 status changed from Pending to Active.', 'Tricycle', 'info', 'labsmiggy@gmail.com', 9, 'tricycle_status_change', 0, '2026-08-30 08:11:05'),
(53, 'mk45 - Tricycle Status: Active', 'Tricycle mk45 status changed from Pending to Active.', 'Tricycle', 'info', 'reinamercedes2026@gmail.com', 9, 'tricycle_status_change', 0, '2026-08-30 08:11:10'),
(54, 'New Driver Registration', 'Manong Berting submitted driver registration for \'Elsa\'.', 'Driver', 'warning', 'admin@tricyclefranchise.com', 5, 'driver_submission', 0, '2026-08-30 08:13:19'),
(55, 'New Driver Registration', 'Manong Berting submitted driver registration for \'Elsa\'.', 'Driver', 'warning', 'reinamercedes2026@gmail.com', 5, 'driver_submission', 0, '2026-08-30 08:13:25'),
(56, 'Driver License Status Updated', 'Your driver license application status has been updated to: Approved. Congratulations! You are now approved to operate.', 'Driver', 'info', 'labsmiggy@gmail.com', 5, 'driver_status', 0, '2026-08-30 08:14:22'),
(57, 'Elsa - Driver License Status Updated', 'Elsa has been approved as a driver.', 'Driver', 'info', 'reinamercedes2026@gmail.com', 5, 'driver_status', 0, '2026-08-30 08:14:27'),
(58, 'Renewal Submitted Successfully', 'Your renewal application for Berting\'s franchise has been submitted successfully. Our admin team is reviewing the payment receipt and will update you once the verification is complete.', 'Renewal', 'info', 'labsmiggy@gmail.com', 2, 'renewal_submission', 0, '2026-08-30 08:52:21'),
(59, 'New Renewal Application', 'Manong Berting submitted a renewal receipt for franchise \'Berting\'s franchise\'.', 'Renewal', 'warning', 'admin@tricyclefranchise.com', 2, 'renewal_submission', 0, '2026-08-30 08:52:27'),
(60, 'New Renewal Application', 'Manong Berting submitted a renewal receipt for franchise \'Berting\'s franchise\'.', 'Renewal', 'warning', 'reinamercedes2026@gmail.com', 2, 'renewal_submission', 0, '2026-08-30 08:52:32'),
(61, 'Renewal Approved', 'Your renewal for Berting\'s franchise has been approved. Your franchise is active through 2029-01-01.', 'Renewal', 'info', 'labsmiggy@gmail.com', 2, 'renewal_decision', 0, '2026-08-30 08:56:50'),
(62, 'Renewal Submitted Successfully', 'Your renewal application for Berting\'s franchise has been submitted successfully. Our admin team is reviewing the payment receipt and will update you once the verification is complete.', 'Renewal', 'info', 'labsmiggy@gmail.com', 3, 'renewal_submission', 0, '2026-08-30 09:02:19'),
(63, 'New Renewal Application', 'Manong Berting submitted a renewal receipt for franchise \'Berting\'s franchise\'.', 'Renewal', 'warning', 'admin@tricyclefranchise.com', 3, 'renewal_submission', 0, '2026-08-30 09:02:23'),
(64, 'New Renewal Application', 'Manong Berting submitted a renewal receipt for franchise \'Berting\'s franchise\'.', 'Renewal', 'warning', 'reinamercedes2026@gmail.com', 3, 'renewal_submission', 1, '2026-08-30 09:02:28'),
(65, 'Renewal Approved', 'Your renewal for Berting\'s franchise has been approved. Your franchise is active through 2030-01-01.', 'Renewal', 'info', 'labsmiggy@gmail.com', 3, 'renewal_decision', 0, '2026-08-30 09:02:50'),
(66, 'New Driver Registration', 'Manong Berting submitted driver registration for \'Anna\'.', 'Driver', 'warning', 'admin@tricyclefranchise.com', 6, 'driver_submission', 0, '2026-08-30 09:26:04'),
(67, 'New Driver Registration', 'Manong Berting submitted driver registration for \'Anna\'.', 'Driver', 'warning', 'reinamercedes2026@gmail.com', 6, 'driver_submission', 0, '2026-08-30 09:26:10'),
(68, 'Driver License Status Updated', 'Anna\'s driver license application status has been updated to: Approved. Congratulations! Anna is now approved to operate.', 'Driver', 'info', 'labsmiggy@gmail.com', 6, 'driver_status', 0, '2026-08-30 09:26:34'),
(69, 'Anna - Driver License Status Updated', 'Anna has been approved as a driver.', 'Driver', 'info', 'reinamercedes2026@gmail.com', 6, 'driver_status', 0, '2026-08-30 09:26:39'),
(70, 'Super Admin Login Detected', 'reina mercedes signed in to the Tricycle Franchise System on September 1, 2026 at 1:09 PM.', 'Admin', 'info', 'reinamercedes2026@gmail.com', 5, 'super_admin_login', 0, '2026-09-01 05:09:32'),
(71, 'Franchise Renewal: EXPIRED', 'Franchise \'san mateo\' expired on 2026-08-30. Immediate renewal action is required.', 'Renewal', 'urgent', 'admin@tricyclefranchise.com', 5, 'franchise_renewal', 0, '2026-09-01 05:09:38'),
(72, 'Franchise Renewal: EXPIRED', 'Franchise \'san mateo\' expired on 2026-08-30. Immediate renewal action is required.', 'Renewal', 'urgent', 'reinamercedes2026@gmail.com', 5, 'franchise_renewal', 0, '2026-09-01 05:09:44'),
(73, 'Franchise Renewal: EXPIRED', 'Franchise \'cauayan\' expired on 2026-08-30. Immediate renewal action is required.', 'Renewal', 'urgent', 'admin@tricyclefranchise.com', 6, 'franchise_renewal', 0, '2026-09-01 05:09:49'),
(74, 'Franchise Renewal: EXPIRED', 'Franchise \'cauayan\' expired on 2026-08-30. Immediate renewal action is required.', 'Renewal', 'urgent', 'reinamercedes2026@gmail.com', 6, 'franchise_renewal', 0, '2026-09-01 05:09:55'),
(75, 'Franchise Renewal: EXPIRED', 'Franchise \'santiago\' expired on 2026-08-30. Immediate renewal action is required.', 'Renewal', 'urgent', 'admin@tricyclefranchise.com', 7, 'franchise_renewal', 0, '2026-09-01 05:10:00'),
(76, 'Franchise Renewal: EXPIRED', 'Franchise \'santiago\' expired on 2026-08-30. Immediate renewal action is required.', 'Renewal', 'urgent', 'reinamercedes2026@gmail.com', 7, 'franchise_renewal', 0, '2026-09-01 05:10:05'),
(77, 'Tricycle Status: Active', 'Tricycle DFR312 status changed from Pending to Active.', 'Tricycle', 'info', 'blasmiggy@gmail.com', 8, 'tricycle_status_change', 0, '2026-09-01 05:11:42'),
(78, 'DFR312 - Tricycle Status: Active', 'Tricycle DFR312 status changed from Pending to Active.', 'Tricycle', 'info', 'reinamercedes2026@gmail.com', 8, 'tricycle_status_change', 0, '2026-09-01 05:11:48'),
(79, 'Driver License Status Updated', 'Ariana Grande\'s driver license application status has been updated to: Approved. Congratulations! Ariana Grande is now approved to operate.', 'Driver', 'info', 'blasmiggy@gmail.com', 4, 'driver_status', 0, '2026-09-01 05:59:44'),
(80, 'Ariana Grande - Driver License Status Updated', 'Ariana Grande has been approved as a driver.', 'Driver', 'info', 'reinamercedes2026@gmail.com', 4, 'driver_status', 0, '2026-09-01 05:59:50'),
(81, 'New Tricycle Registration', 'john doe submitted tricycle registration for plate \'1234567\'.', 'Tricycle', 'warning', 'admin@tricyclefranchise.com', 12, 'tricycle_submission', 0, '2026-09-01 06:11:41'),
(82, 'New Tricycle Registration', 'john doe submitted tricycle registration for plate \'1234567\'.', 'Tricycle', 'warning', 'reinamercedes2026@gmail.com', 12, 'tricycle_submission', 0, '2026-09-01 06:11:46'),
(83, 'Tricycle Status: Active', 'Tricycle 1234567 status changed from Pending to Active.', 'Tricycle', 'info', 'blasmiggy@gmail.com', 12, 'tricycle_status_change', 0, '2026-09-01 06:12:10'),
(84, '1234567 - Tricycle Status: Active', 'Tricycle 1234567 status changed from Pending to Active.', 'Tricycle', 'info', 'reinamercedes2026@gmail.com', 12, 'tricycle_status_change', 0, '2026-09-01 06:12:15'),
(85, 'New Tricycle Registration', 'john doe submitted tricycle registration for plate \'7654345\'.', 'Tricycle', 'warning', 'admin@tricyclefranchise.com', 13, 'tricycle_submission', 0, '2026-09-01 06:26:24'),
(86, 'New Tricycle Registration', 'john doe submitted tricycle registration for plate \'7654345\'.', 'Tricycle', 'warning', 'reinamercedes2026@gmail.com', 13, 'tricycle_submission', 0, '2026-09-01 06:26:30'),
(87, 'Tricycle Status: Active', 'Tricycle 7654345 status changed from Pending to Active.', 'Tricycle', 'info', 'blasmiggy@gmail.com', 13, 'tricycle_status_change', 0, '2026-09-01 06:26:55'),
(88, '7654345 - Tricycle Status: Active', 'Tricycle 7654345 status changed from Pending to Active.', 'Tricycle', 'info', 'reinamercedes2026@gmail.com', 13, 'tricycle_status_change', 0, '2026-09-01 06:27:00'),
(89, 'Super Admin Login Detected', 'reina mercedes signed in to the Tricycle Franchise System on September 3, 2026 at 2:25 PM.', 'Admin', 'info', 'reinamercedes2026@gmail.com', 5, 'super_admin_login', 0, '2026-09-03 06:25:59'),
(90, 'Super Admin Login Detected', 'reina mercedes signed in to the Tricycle Franchise System on September 3, 2026 at 2:49 PM.', 'Admin', 'info', 'reinamercedes2026@gmail.com', 5, 'super_admin_login', 0, '2026-09-03 06:49:33');

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
  `receipt_status` enum('Not Submitted','Submitted','Confirmed','Rejected') DEFAULT 'Not Submitted',
  `receipt_confirmed_at` datetime DEFAULT NULL,
  `receipt_confirmed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `renewals`
--

INSERT INTO `renewals` (`renewal_id`, `franchise_id`, `renewal_year`, `renewal_date`, `due_date`, `penalty`, `remarks`, `created_at`, `receipt_photo`, `receipt_submitted_at`, `receipt_status`, `receipt_confirmed_at`, `receipt_confirmed_by`) VALUES
(2, 12, '2028', '2028-01-01', '2029-01-01', 0.00, '', '2026-08-30 08:52:21', 'uploads/renewal_receipt_5baefbd1d2467a21ba4b.gif', '2026-08-30 16:52:21', 'Confirmed', '2026-08-30 16:56:50', 5),
(3, 12, '2029', '2029-01-01', '2030-01-01', 0.00, '', '2026-08-30 09:02:19', 'uploads/renewal_receipt_ca913209a279028446f3.gif', '2026-08-30 17:02:19', 'Confirmed', '2026-08-30 17:02:50', 5);

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

--
-- Dumping data for table `riders`
--

INSERT INTO `riders` (`rider_id`, `full_name`, `email`, `password`, `contact_number`, `address`, `driver_license`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Test Rider', 'testrider@example.com', '$2y$10$fxjFuwpotAXfz8My/7dZ8uQCf14BMtbwwuy1oS.kE.s6Z/0yZraj2', NULL, NULL, NULL, 'Active', '2026-08-30 03:33:07', '2026-08-30 03:33:07');

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
  `admin_id` int(11) DEFAULT NULL,
  `or_document` varchar(255) DEFAULT NULL,
  `cr_document` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tricycles`
--

INSERT INTO `tricycles` (`tricycle_id`, `brand`, `engine_number`, `chassis_number`, `color`, `plate_number`, `sticker_number`, `status`, `created_at`, `updated_at`, `admin_id`, `or_document`, `cr_document`) VALUES
(3, 'Not specified', '1242412413241', '1412414141', NULL, 'lmk123', NULL, 'Inactive', '2026-08-29 19:56:01', '2026-08-29 20:22:28', NULL, NULL, NULL),
(7, 'TMX', '1234', '1234', 'red', '1234', '12312', 'Active', '2026-08-30 04:38:37', '2026-08-30 06:44:03', NULL, 'uploads/tricycle_or_c902e3838cfd8140029c.gif', NULL),
(8, 'TMX 125', '5435353', '3242344', 'red', 'DFR312', '2', 'Active', '2026-08-30 07:36:01', '2026-09-01 05:11:42', NULL, 'uploads/tricycle_or_dae4308753550d41877f.gif', NULL),
(9, 'yamaha', '132123123', '31235445', 'red', 'mk45', '9', 'Active', '2026-08-30 08:09:24', '2026-08-30 08:11:05', NULL, 'uploads/tricycle_or_6e97473082af2d97d515.gif', NULL),
(12, 'TMX 123', '12349', '1323131', 'red', '1234567', '7', 'Active', '2026-09-01 06:11:41', '2026-09-01 06:12:10', NULL, 'uploads/tricycle_or_539731fdca22bcd5af65.jpg', NULL),
(13, 'honda 213', '534674', '5365', 'red', '7654345', '5', 'Active', '2026-09-01 06:26:24', '2026-09-01 06:26:55', NULL, 'uploads/tricycle_or_07eb9061c3fc51cff638.gif', NULL);

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
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `driver_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `driver_tricycle`
--
ALTER TABLE `driver_tricycle`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `franchises`
--
ALTER TABLE `franchises`
  MODIFY `franchise_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `franchise_applications`
--
ALTER TABLE `franchise_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `franchise_documents`
--
ALTER TABLE `franchise_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `franchise_driver`
--
ALTER TABLE `franchise_driver`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `franchise_tricycle`
--
ALTER TABLE `franchise_tricycle`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `renewals`
--
ALTER TABLE `renewals`
  MODIFY `renewal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `riders`
--
ALTER TABLE `riders`
  MODIFY `rider_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tricycles`
--
ALTER TABLE `tricycles`
  MODIFY `tricycle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
