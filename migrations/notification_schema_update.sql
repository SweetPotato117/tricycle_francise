-- Update notifications table to support comprehensive notification system
-- Run this SQL to update your database schema

ALTER TABLE `renewals` MODIFY `receipt_status` ENUM('Not Submitted','Submitted','Confirmed','Rejected') DEFAULT 'Not Submitted';

ALTER TABLE `notifications` ADD COLUMN `type` VARCHAR(50) DEFAULT 'Franchise' AFTER `message`;
ALTER TABLE `notifications` ADD COLUMN `severity` VARCHAR(20) DEFAULT 'info' AFTER `type`;
ALTER TABLE `notifications` ADD COLUMN `recipient_email` VARCHAR(255) DEFAULT NULL AFTER `severity`;
ALTER TABLE `notifications` ADD COLUMN `related_id` INT(11) DEFAULT NULL AFTER `recipient_email`;
ALTER TABLE `notifications` ADD COLUMN `related_type` VARCHAR(100) DEFAULT NULL AFTER `related_id`;

-- Add index for faster queries
ALTER TABLE `notifications` ADD INDEX `idx_recipient_email` (`recipient_email`);
ALTER TABLE `notifications` ADD INDEX `idx_related_id` (`related_id`);
ALTER TABLE `notifications` ADD INDEX `idx_created_at` (`created_at`);
ALTER TABLE `notifications` ADD INDEX `idx_is_read` (`is_read`);

-- Update franchises table to store owner email if not exists
ALTER TABLE `franchises` ADD COLUMN `owner_email` VARCHAR(255) DEFAULT NULL;

-- Update drivers table to store email if not exists
ALTER TABLE `drivers` ADD COLUMN `email` VARCHAR(255) DEFAULT NULL;

-- Create a table for franchise applications (rider side)
CREATE TABLE IF NOT EXISTS `franchise_applications` (
  `application_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `rider_id` INT(11),
  `rider_name` VARCHAR(150) NOT NULL,
  `rider_email` VARCHAR(255) NOT NULL,
  `franchise_id` INT(11),
  `franchise_name` VARCHAR(150),
  `application_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
  `admin_comments` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_rider_email` (`rider_email`),
  INDEX `idx_franchise_id` (`franchise_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create rider accounts table for franchise applications
CREATE TABLE IF NOT EXISTS `riders` (
  `rider_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `contact_number` VARCHAR(20),
  `address` TEXT,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

