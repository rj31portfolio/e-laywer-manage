-- Database: legal_platform
-- Purpose: Legal Consultation Platform with user roles (Super Admin, Admin, Lawyer, Client)
-- Author: DeepSeek Chat
-- Date: 2023-06-15

-- Create database
CREATE DATABASE IF NOT EXISTS `legal_platform` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `legal_platform`;

-- Table structure for table `users`
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','lawyer','client') NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `user_details`
CREATE TABLE IF NOT EXISTS `user_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT 'default-avatar.jpg',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `categories`
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `lawyers`
CREATE TABLE IF NOT EXISTS `lawyers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `bio` text DEFAULT NULL,
  `consultation_fee` decimal(10,2) NOT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `experience` int(11) DEFAULT NULL,
  `availability` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `lawyers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lawyers_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `enquiries`
CREATE TABLE IF NOT EXISTS `enquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','assigned','in_progress','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `enquiries_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enquiries_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `assignments`
CREATE TABLE IF NOT EXISTS `assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enquiry_id` int(11) NOT NULL,
  `lawyer_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','completed','rejected') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `enquiry_id` (`enquiry_id`),
  KEY `lawyer_id` (`lawyer_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`lawyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `payments`
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `razorpay_order_id` varchar(255) NOT NULL,
  `razorpay_payment_id` varchar(255) DEFAULT NULL,
  `razorpay_signature` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `assignment_id` (`assignment_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `case_updates`
CREATE TABLE IF NOT EXISTS `case_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `notes` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `assignment_id` (`assignment_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `case_updates_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `case_updates_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data

-- Users data (password is 'password' hashed with bcrypt)
INSERT INTO `users` (`id`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'superadmin@legal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, NOW(), NOW()),
(2, 'admin@legal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NOW(), NOW()),
(3, 'lawyer1@legal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lawyer', 1, NOW(), NOW()),
(4, 'lawyer2@legal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lawyer', 1, NOW(), NOW()),
(5, 'client1@legal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 1, NOW(), NOW()),
(6, 'client2@legal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 1, NOW(), NOW());

-- User details data
INSERT INTO `user_details` (`id`, `user_id`, `first_name`, `last_name`, `phone`, `address`, `photo`) VALUES
(1, 1, 'Super', 'Admin', '+1234567890', '123 Admin Street, Admin City', 'default-avatar.jpg'),
(2, 2, 'Admin', 'User', '+1234567891', '456 Admin Avenue, Admin Town', 'default-avatar.jpg'),
(3, 3, 'John', 'Doe', '+1234567892', '789 Lawyer Lane, Law City', 'lawyer1.jpg'),
(4, 4, 'Jane', 'Smith', '+1234567893', '101 Legal Street, Justice Town', 'lawyer2.jpg'),
(5, 5, 'Michael', 'Brown', '+1234567894', '202 Client Road, Client City', 'default-avatar.jpg'),
(6, 6, 'Sarah', 'Johnson', '+1234567895', '303 Customer Blvd, Customer Town', 'default-avatar.jpg');

-- Categories data
INSERT INTO `categories` (`id`, `name`, `description`, `created_by`, `created_at`) VALUES
(1, 'Family Law', 'Divorce, child custody, adoption, etc.', 1, NOW()),
(2, 'Criminal Law', 'Defense against criminal charges', 1, NOW()),
(3, 'Corporate Law', 'Business formation, contracts, compliance', 1, NOW()),
(4, 'Real Estate', 'Property transactions, landlord-tenant issues', 1, NOW()),
(5, 'Immigration Law', 'Visas, citizenship, deportation defense', 1, NOW());

-- Lawyers data
INSERT INTO `lawyers` (`id`, `user_id`, `category_id`, `bio`, `consultation_fee`, `rating`, `experience`, `availability`) VALUES
(1, 3, 1, 'Experienced family lawyer with 10+ years of practice helping clients with divorce, child custody, and adoption cases.', 2000.00, 4.50, 10, 1),
(2, 4, 3, 'Corporate law specialist with expertise in business formation, contracts, and regulatory compliance.', 3000.00, 4.80, 8, 1);

-- Enquiries data
INSERT INTO `enquiries` (`id`, `client_id`, `category_id`, `subject`, `description`, `budget`, `status`, `created_at`) VALUES
(1, 5, 1, 'Divorce Consultation', 'I need legal advice regarding divorce proceedings and child custody arrangements.', 2500.00, 'assigned', NOW()),
(2, 6, 3, 'Business Incorporation', 'Looking for assistance with incorporating a new business and drafting shareholder agreements.', 3500.00, 'pending', NOW()),
(3, 5, 2, 'Criminal Defense', 'Need representation for a misdemeanor charge.', 5000.00, 'pending', NOW());

-- Assignments data
INSERT INTO `assignments` (`id`, `enquiry_id`, `lawyer_id`, `assigned_by`, `assigned_at`, `status`) VALUES
(1, 1, 3, 2, NOW(), 'active');

-- Payments data
INSERT INTO `payments` (`id`, `assignment_id`, `amount`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 2000.00, 'order_rcpt_1', 'pay_Jq7z4X1X2X3X4X', 'sig_Jq7z4X1X2X3X4X5X6X7X8X9X', 'completed', NOW(), NOW());

-- Case updates data
INSERT INTO `case_updates` (`id`, `assignment_id`, `updated_by`, `notes`, `created_at`) VALUES
(1, 1, 3, 'Initial consultation completed. Discussed divorce options and child custody possibilities.', NOW()),
(2, 1, 5, 'Submitted required documents for divorce filing.', NOW());

-- Create indexes for better performance
CREATE INDEX idx_enquiries_status ON `enquiries` (`status`);
CREATE INDEX idx_payments_status ON `payments` (`status`);
CREATE INDEX idx_assignments_status ON `assignments` (`status`);