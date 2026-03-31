-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Mar 31, 2026 at 04:00 PM
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
-- Database: `edge_project_asset_inventory_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` int(11) NOT NULL,
  `asset_name` varchar(255) NOT NULL,
  `asset_type` varchar(150) DEFAULT NULL,
  `serial_number` varchar(150) DEFAULT NULL,
  `asset_status` enum('available','maintenance','damaged') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `quantity` int(11) DEFAULT 0,
  `status` enum('available','maintenance','damaged','lost') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `asset_name`, `asset_type`, `serial_number`, `asset_status`, `created_at`, `updated_at`, `quantity`, `status`) VALUES
(10, 'Welding Machine', 'Electronics', '1', 'available', '2026-03-16 09:09:42', '2026-03-23 10:25:42', 0, 'available'),
(11, 'Drills', 'Electronics', '2', 'available', '2026-03-16 10:21:46', '2026-03-16 10:21:46', 0, 'available');

-- --------------------------------------------------------

--
-- Table structure for table `asset_loss_logs`
--

CREATE TABLE `asset_loss_logs` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `reported_by` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `lost_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_qr_codes`
--

CREATE TABLE `asset_qr_codes` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `qr_code_value` varchar(512) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asset_qr_codes`
--

INSERT INTO `asset_qr_codes` (`id`, `asset_id`, `qr_code_value`, `created_at`) VALUES
(10, 10, 'asset_id=10', '2026-03-16 09:09:42'),
(11, 11, 'asset_id=11', '2026-03-16 10:21:46');

-- --------------------------------------------------------

--
-- Table structure for table `asset_scan_history`
--

CREATE TABLE `asset_scan_history` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `foreman_id` int(11) NOT NULL,
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `scan_device` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asset_scan_history`
--

INSERT INTO `asset_scan_history` (`id`, `asset_id`, `foreman_id`, `scan_time`, `scan_device`) VALUES
(1, 10, 11, '2026-03-16 09:27:34', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `asset_usage_logs`
--

CREATE TABLE `asset_usage_logs` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `foreman_id` int(11) NOT NULL,
  `worker_name` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asset_usage_logs`
--

INSERT INTO `asset_usage_logs` (`id`, `asset_id`, `foreman_id`, `worker_name`, `notes`, `used_at`) VALUES
(1, 10, 11, 'Junjun', '', '2026-03-16 09:27:34');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock` int(11) DEFAULT NULL,
  `status` enum('available','low-stock','out-of-stock') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `asset_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempts` int(11) DEFAULT 0,
  `last_attempt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempts`, `last_attempt`) VALUES
(4, 'jhayandriereyes@gmail.com', '::1', 6, '2026-03-10 07:33:33'),
(9, 'F2@gmail.com', '::1', 4, '2026-03-10 10:54:18');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `project_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('pending','ongoing','completed','on-hold') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_name`, `description`, `client_id`, `start_date`, `end_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'Vendo Machine', '', 7, '2026-03-30', '2026-03-31', 'completed', 5, '2026-03-30 01:30:58', '2026-03-30 16:07:23'),
(3, 'Working machine', '', 10, '2026-03-30', '2026-03-30', 'pending', 5, '2026-03-30 01:37:01', '2026-03-30 01:37:01'),
(4, 'Wirings', '', 9, '2026-03-31', '2026-03-31', 'ongoing', 5, '2026-03-31 01:37:12', '2026-03-31 01:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `project_assignments`
--

CREATE TABLE `project_assignments` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `engineer_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_assignments`
--

INSERT INTO `project_assignments` (`id`, `project_id`, `engineer_id`, `assigned_by`, `assigned_at`) VALUES
(1, 2, 6, 5, '2026-03-30 01:30:58'),
(2, 3, 6, 5, '2026-03-30 01:37:01'),
(3, 4, 14, 5, '2026-03-31 01:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `task_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('pending','ongoing','completed','delayed') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `project_id`, `assigned_to`, `task_name`, `description`, `deadline`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, 6, 'Cleaning', '', '2026-03-31', 'completed', 5, '2026-03-30 01:36:24', '2026-03-30 16:06:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `failed_attempts` int(11) DEFAULT 0,
  `last_failed_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password`, `role`, `status`, `failed_attempts`, `last_failed_login`, `created_at`, `updated_at`, `created_by`, `reset_token`, `token_expiry`) VALUES
(5, 'adminedge', 'jeshowap@gmail.com', NULL, '$2y$10$pwI94G4xmgpb8xCqN36zCehqij7csJ5qTajN7V1J1VAdikJR2F2Jq', 'super_admin', 'active', 0, NULL, '2026-03-04 09:22:06', '2026-03-14 03:02:19', NULL, NULL, NULL),
(6, 'Engineer1', 'jhayreyes825@gmail.com', '09070782535', '$2y$10$krcZHjowKX5T385itOfSIuF00j6jr5pCNRjrCzpsM197lIPxxyrEe', 'engineer', 'active', 0, NULL, '2026-03-06 03:25:58', '2026-03-25 07:06:46', NULL, NULL, NULL),
(7, 'Client1', 'jhayandriereyes@gmail.com', '09070782533', '$2y$10$4N2cH6onWgAfxs/ERpK9O.gIDfyzJ/lkG.GkPZ/caqpHuriAlt9/S', 'client', 'active', 0, NULL, '2026-03-06 04:45:00', '2026-03-06 11:10:23', 5, NULL, NULL),
(9, 'Client2', 'Client2@gmail.com', '09070782534', '$2y$10$M5/21OQMCvZw99er8W2.2.AlFw2PYkoe41ixb7qMngfBayCI8hFx.', 'client', 'active', 0, NULL, '2026-03-06 12:04:17', '2026-03-06 12:04:17', 5, NULL, NULL),
(10, 'Client3', 'C3@gmail.com', '09070782536', '$2y$10$OX08NbDxuxpllNOYEE2P4eaNzDxG8H7aheHYdb6j2X.G13ZYsFx4G', 'client', 'active', 0, NULL, '2026-03-09 23:16:00', '2026-03-09 23:16:00', 5, NULL, NULL),
(11, 'Foreman1', 'foreman1@gmail.com', '09070782531', '$2y$10$jMvpZeC5ELve.b/tB4OFbeVnxMs7BIFWu2CpJpxWGcOgFE4TF.wgS', 'foreman', 'active', 0, NULL, '2026-03-10 02:53:28', '2026-03-10 02:53:28', 5, NULL, NULL),
(13, 'Super Admin', 'superadmin@edge.com', '09123456789', '$2y$10$MPyqNCvB2OwODVqCgWLI.eIMNKrr9deUup5c9zeSNQrwOA0A8zWU.', 'super_admin', 'active', 0, NULL, '2026-03-13 14:54:38', '2026-03-13 14:59:06', NULL, NULL, NULL),
(14, 'Engineer2', 'Engineer2@gmail.com', '09070782511', '$2y$10$6n9a2JhT./x3UAawlr5kce.sLNAtiRHYKpzpJScEQNF4Ccuo9LGhC', 'engineer', 'active', 0, NULL, '2026-03-14 02:53:41', '2026-03-30 13:49:15', 5, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`asset_status`);

--
-- Indexes for table `asset_loss_logs`
--
ALTER TABLE `asset_loss_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`);

--
-- Indexes for table `asset_qr_codes`
--
ALTER TABLE `asset_qr_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_asset` (`asset_id`);

--
-- Indexes for table `asset_scan_history`
--
ALTER TABLE `asset_scan_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_asset` (`asset_id`),
  ADD KEY `idx_foreman` (`foreman_id`),
  ADD KEY `idx_scan_time` (`scan_time`);

--
-- Indexes for table `asset_usage_logs`
--
ALTER TABLE `asset_usage_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_asset` (`asset_id`),
  ADD KEY `idx_foreman` (`foreman_id`),
  ADD KEY `idx_used_at` (`used_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `project_assignments`
--
ALTER TABLE `project_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`project_id`,`engineer_id`),
  ADD KEY `idx_engineer` (`engineer_id`),
  ADD KEY `fk_assignment_assigner` (`assigned_by`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_full_name` (`full_name`),
  ADD UNIQUE KEY `reset_token` (`reset_token`),
  ADD UNIQUE KEY `uq_users_phone` (`phone`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `asset_loss_logs`
--
ALTER TABLE `asset_loss_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_qr_codes`
--
ALTER TABLE `asset_qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `asset_scan_history`
--
ALTER TABLE `asset_scan_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `asset_usage_logs`
--
ALTER TABLE `asset_usage_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `project_assignments`
--
ALTER TABLE `project_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `asset_loss_logs`
--
ALTER TABLE `asset_loss_logs`
  ADD CONSTRAINT `asset_loss_logs_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`);

--
-- Constraints for table `asset_qr_codes`
--
ALTER TABLE `asset_qr_codes`
  ADD CONSTRAINT `fk_asset_qr_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `fk_project_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `project_assignments`
--
ALTER TABLE `project_assignments`
  ADD CONSTRAINT `fk_assignment_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_assignment_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_task_engineer` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_task_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
