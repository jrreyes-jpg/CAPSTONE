-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 12, 2026 at 09:47 AM
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

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `created_at`) VALUES
(1, 5, 'add_task', 'task', 2, NULL, '{\"project_name\":\"Wirings\",\"task_name\":\"Checking wires\",\"assigned_to\":6,\"deadline\":\"2026-04-01\"}', '::1', '2026-04-01 14:44:57'),
(2, 5, 'deploy_inventory_to_project', 'deployment', 2, NULL, '{\"project_name\":\"Wirings\",\"asset_name\":\"Drills\",\"quantity\":1,\"remaining_quantity\":0}', '::1', '2026-04-01 14:45:44'),
(3, 5, 'create_user', 'user', 15, NULL, '{\"full_name\":\"Rhose Anne Reyes\",\"email\":\"rhose@gmail.com\",\"phone\":\"09123456782\",\"role\":\"engineer\",\"status\":\"active\"}', '::1', '2026-04-01 14:51:56'),
(4, 5, 'create_user', 'user', 16, NULL, '{\"full_name\":\"Jomarie Reyes\",\"email\":\"jomarie@gmail.com\",\"phone\":\"09123456781\",\"role\":\"client\",\"status\":\"active\"}', '::1', '2026-04-01 14:53:40'),
(5, 5, 'create_project', 'project', 6, NULL, '{\"project_name\":\"Washing Machine\",\"status\":\"pending\",\"client_id\":16,\"engineer_id\":15}', '::1', '2026-04-01 14:54:15'),
(6, 5, 'create_inventory_item', 'inventory', 2, NULL, '{\"asset_id\":10,\"asset_name\":\"Welding Machine\",\"quantity\":5,\"min_stock\":null,\"status\":\"available\"}', '::1', '2026-04-01 14:55:32'),
(7, 5, 'update_inventory_item', 'inventory', 1, '{\"asset_name\":\"Drills\",\"quantity\":0,\"min_stock\":1,\"status\":\"out-of-stock\"}', '{\"asset_name\":\"Drills\",\"quantity\":5,\"min_stock\":null,\"status\":\"available\"}', '::1', '2026-04-01 14:55:54'),
(8, 5, 'deploy_inventory_to_project', 'deployment', 3, NULL, '{\"project_name\":\"Washing Machine\",\"asset_name\":\"Drills\",\"quantity\":1,\"remaining_quantity\":4}', '::1', '2026-04-01 14:56:15'),
(9, 5, 'create_project', 'project', 7, NULL, '{\"project_name\":\"washing machine\",\"status\":\"pending\",\"client_id\":16,\"engineer_id\":15}', '::1', '2026-04-05 09:37:05'),
(10, 5, 'create_project', 'project', 8, NULL, '{\"project_name\":\"washing machine\",\"status\":\"pending\",\"client_id\":16,\"engineer_id\":15}', '::1', '2026-04-05 09:37:30'),
(11, 5, 'update_project_status', 'project', 8, '{\"project_name\":\"washing machine\",\"status\":\"pending\"}', '{\"project_name\":\"washing machine\",\"status\":\"archived\"}', '::1', '2026-04-05 09:49:58'),
(12, 5, 'update_project_status', 'project', 8, '{\"project_name\":\"washing machine\",\"status\":\"archived\"}', '{\"project_name\":\"washing machine\",\"status\":\"cancelled\"}', '::1', '2026-04-05 09:50:33'),
(13, 5, 'add_task', 'task', 3, NULL, '{\"project_name\":\"washing machine\",\"task_name\":\"Cleaning Debris\",\"assigned_to\":15,\"deadline\":\"2026-04-06\"}', '::1', '2026-04-06 00:56:21'),
(14, 5, 'deploy_inventory_to_project', 'deployment', 4, NULL, '{\"project_name\":\"washing machine\",\"asset_name\":\"Welding Machine\",\"quantity\":1,\"remaining_quantity\":4}', '::1', '2026-04-06 00:56:46'),
(15, 15, 'engineer_task_update', 'task', 3, '{\"status\":\"pending\"}', '{\"status\":\"ongoing\",\"progress_note\":null}', '::1', '2026-04-06 00:58:11'),
(16, 5, 'create_project', 'project', 9, NULL, '{\"project_name\":\"Motor welding\",\"status\":\"pending\",\"client_id\":16,\"engineer_id\":15}', '::1', '2026-04-07 13:47:42'),
(17, 5, 'deploy_inventory_to_project', 'deployment', 5, NULL, '{\"project_name\":\"Motor welding\",\"asset_name\":\"Drills\",\"quantity\":1,\"remaining_quantity\":3}', '::1', '2026-04-07 14:14:06'),
(18, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":null}', '{\"full_name\":\"superadminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\"}', '::1', '2026-04-10 23:55:52'),
(19, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"superadminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\"}', '::1', '2026-04-10 23:56:48'),
(20, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":null}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775879125.png\"}', '::1', '2026-04-11 03:45:25'),
(21, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775879125.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775879174.png\"}', '::1', '2026-04-11 03:46:14'),
(22, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775879174.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775879180.png\"}', '::1', '2026-04-11 03:46:20'),
(23, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775879180.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775879191.png\"}', '::1', '2026-04-11 03:46:31'),
(24, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775879191.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775879298.png\"}', '::1', '2026-04-11 03:48:18'),
(25, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775879298.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775886921.png\"}', '::1', '2026-04-11 05:55:21'),
(26, 5, 'update_project_status', 'project', 7, '{\"project_name\":\"washing machine\",\"status\":\"pending\"}', '{\"project_name\":\"washing machine\",\"status\":\"ongoing\"}', '::1', '2026-04-11 13:23:45'),
(27, 15, 'engineer_task_update', 'task', 3, '{\"status\":\"ongoing\"}', '{\"status\":\"completed\",\"progress_note\":null}', '::1', '2026-04-11 13:24:36'),
(28, 5, 'create_project', 'project', 10, NULL, '{\"project_name\":\"new\",\"status\":\"pending\",\"client_id\":16,\"engineer_id\":15}', '::1', '2026-04-11 13:25:51'),
(29, 5, 'update_project_status', 'project', 10, '{\"project_name\":\"new\",\"status\":\"pending\"}', '{\"project_name\":\"new\",\"status\":\"ongoing\"}', '::1', '2026-04-11 13:26:01'),
(30, 5, 'update_project_status', 'project', 10, '{\"project_name\":\"new\",\"status\":\"ongoing\"}', '{\"project_name\":\"new\",\"status\":\"completed\"}', '::1', '2026-04-11 13:26:10'),
(31, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775886921.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775914892.png\"}', '::1', '2026-04-11 13:41:32'),
(32, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775914892.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775914897.png\"}', '::1', '2026-04-11 13:41:37'),
(33, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775914897.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915084.png\"}', '::1', '2026-04-11 13:44:44'),
(34, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915084.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915169.png\"}', '::1', '2026-04-11 13:46:09'),
(35, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915169.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915175.png\"}', '::1', '2026-04-11 13:46:15'),
(36, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915175.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915205.png\"}', '::1', '2026-04-11 13:46:45'),
(37, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915205.png\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915336.jpg\"}', '::1', '2026-04-11 13:48:56'),
(38, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915336.jpg\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915384.jpg\"}', '::1', '2026-04-11 13:49:44'),
(39, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915384.jpg\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915387.jpg\"}', '::1', '2026-04-11 13:49:47'),
(40, 5, 'update_user_profile', 'user', 5, '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915387.jpg\"}', '{\"full_name\":\"adminedge\",\"email\":\"jeshowap@gmail.com\",\"phone\":\"\",\"profile_photo_path\":\"uploads/profile_photos/super-admin-5-1775915394.jpg\"}', '::1', '2026-04-11 13:49:54'),
(41, 5, 'create_user', 'user', 17, NULL, '{\"full_name\":\"Johnny Reyes\",\"email\":\"johnny@gmail.com\",\"phone\":\"09338268806\",\"role\":\"foreman\",\"status\":\"active\"}', '::1', '2026-04-12 03:56:06');

-- --------------------------------------------------------

--
-- Table structure for table `engineer_task_updates`
--

CREATE TABLE `engineer_task_updates` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `engineer_id` int(11) NOT NULL,
  `status_snapshot` varchar(50) DEFAULT NULL,
  `progress_note` text NOT NULL,
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

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `quantity`, `min_stock`, `status`, `created_at`, `updated_at`, `asset_id`) VALUES
(1, 3, NULL, 'available', '2026-03-31 15:12:26', '2026-04-07 14:14:06', 11),
(2, 4, NULL, 'available', '2026-04-01 14:55:32', '2026-04-06 00:56:46', 10);

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
(9, 'F2@gmail.com', '::1', 4, '2026-03-10 10:54:18'),
(25, 'jhayreyes825@gmail.com', '::1', 1, '2026-04-01 22:50:02');

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
  `status` enum('draft','pending','ongoing','completed','on-hold','cancelled','archived') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_name`, `description`, `client_id`, `start_date`, `end_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'Vendo Machine', '', 7, '2026-03-30', '2026-03-31', 'ongoing', 5, '2026-03-30 01:30:58', '2026-03-31 15:13:30'),
(3, 'Working machine', '', 10, '2026-03-30', '2026-03-30', 'ongoing', 5, '2026-03-30 01:37:01', '2026-03-31 14:52:31'),
(4, 'Wirings', '', 9, '2026-03-31', '2026-03-31', 'ongoing', 5, '2026-03-31 01:37:12', '2026-03-31 01:37:12'),
(5, 'Mechanical', '', 7, '2026-03-31', '2026-03-31', 'completed', 5, '2026-03-31 14:54:42', '2026-03-31 14:54:55'),
(6, 'Washing Machine', '', 16, '2026-04-01', '2026-04-01', 'pending', 5, '2026-04-01 14:54:15', '2026-04-01 14:54:15'),
(7, 'washing machine', '', 16, '2026-04-05', '2026-04-05', 'ongoing', 5, '2026-04-05 09:37:05', '2026-04-11 13:23:45'),
(8, 'washing machine', '', 16, '2026-04-05', '2026-04-05', 'cancelled', 5, '2026-04-05 09:37:30', '2026-04-05 09:50:33'),
(9, 'Motor welding', 'Hanggang bukas dapat tapos na.', 16, '2026-04-07', '2026-04-07', 'pending', 5, '2026-04-07 13:47:42', '2026-04-07 13:47:42'),
(10, 'new', '', 16, '2026-04-11', '2026-04-11', 'completed', 5, '2026-04-11 13:25:51', '2026-04-11 13:26:09');

-- --------------------------------------------------------

--
-- Table structure for table `project_asset_deployments`
--

CREATE TABLE `project_asset_deployments` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `deployed_by` int(11) NOT NULL,
  `deployed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `returned_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(3, 4, 14, 5, '2026-03-31 01:37:12'),
(4, 5, 6, 5, '2026-03-31 14:54:42'),
(5, 6, 15, 5, '2026-04-01 14:54:15'),
(6, 7, 15, 5, '2026-04-05 09:37:05'),
(7, 8, 15, 5, '2026-04-05 09:37:30'),
(8, 9, 15, 5, '2026-04-07 13:47:42'),
(9, 10, 15, 5, '2026-04-11 13:25:51');

-- --------------------------------------------------------

--
-- Table structure for table `project_inventory_deployments`
--

CREATE TABLE `project_inventory_deployments` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `deployed_by` int(11) NOT NULL,
  `deployed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `returned_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_inventory_deployments`
--

INSERT INTO `project_inventory_deployments` (`id`, `project_id`, `inventory_id`, `quantity`, `deployed_by`, `deployed_at`, `returned_at`, `notes`) VALUES
(1, 4, 1, 1, 5, '2026-03-31 15:13:10', NULL, NULL),
(2, 4, 1, 1, 5, '2026-04-01 14:45:44', NULL, NULL),
(3, 6, 1, 1, 5, '2026-04-01 14:56:15', NULL, NULL),
(4, 7, 2, 1, 5, '2026-04-06 00:56:46', NULL, NULL),
(5, 9, 1, 1, 5, '2026-04-07 14:14:06', NULL, 'nyah');

-- --------------------------------------------------------

--
-- Table structure for table `project_inventory_return_logs`
--

CREATE TABLE `project_inventory_return_logs` (
  `id` int(11) NOT NULL,
  `deployment_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `returned_by` int(11) NOT NULL,
  `returned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 2, 6, 'Cleaning', '', '2026-03-31', 'completed', 5, '2026-03-30 01:36:24', '2026-03-30 16:06:09'),
(2, 4, 6, 'Checking wires', '', '2026-04-01', 'pending', 5, '2026-04-01 14:44:57', '2026-04-01 14:44:57'),
(3, 7, 15, 'Cleaning Debris', '', '2026-04-06', 'completed', 5, '2026-04-06 00:56:21', '2026-04-11 13:24:36');

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
  `token_expiry` datetime DEFAULT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `profile_photo_data` longtext DEFAULT NULL,
  `profile_photo_mime` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password`, `role`, `status`, `failed_attempts`, `last_failed_login`, `created_at`, `updated_at`, `created_by`, `reset_token`, `token_expiry`, `profile_photo_path`, `profile_photo_data`, `profile_photo_mime`) VALUES
(5, 'adminedge', 'jeshowap@gmail.com', '', '$2y$10$pwI94G4xmgpb8xCqN36zCehqij7csJ5qTajN7V1J1VAdikJR2F2Jq', 'super_admin', 'active', 0, NULL, '2026-03-04 09:22:06', '2026-04-11 14:39:18', NULL, '656319f981aac380183420bb1673f056cf51adb6f27c2f96b502584645a092fb33727868c84d828832e339d6a889235c29fd', '2026-04-02 00:31:09', 'system-profile:user-5.jpg', NULL, NULL),
(6, 'Engineer1', 'jhayreyes825@gmail.com', '09070782535', '$2y$10$krcZHjowKX5T385itOfSIuF00j6jr5pCNRjrCzpsM197lIPxxyrEe', 'engineer', 'active', 0, NULL, '2026-03-06 03:25:58', '2026-03-25 07:06:46', NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'Client1', 'jhayandriereyes@gmail.com', '09070782533', '$2y$10$4N2cH6onWgAfxs/ERpK9O.gIDfyzJ/lkG.GkPZ/caqpHuriAlt9/S', 'client', 'active', 0, NULL, '2026-03-06 04:45:00', '2026-03-06 11:10:23', 5, NULL, NULL, NULL, NULL, NULL),
(9, 'Client2', 'Client2@gmail.com', '09070782534', '$2y$10$M5/21OQMCvZw99er8W2.2.AlFw2PYkoe41ixb7qMngfBayCI8hFx.', 'client', 'active', 0, NULL, '2026-03-06 12:04:17', '2026-03-06 12:04:17', 5, NULL, NULL, NULL, NULL, NULL),
(10, 'Client3', 'C3@gmail.com', '09070782536', '$2y$10$OX08NbDxuxpllNOYEE2P4eaNzDxG8H7aheHYdb6j2X.G13ZYsFx4G', 'client', 'active', 0, NULL, '2026-03-09 23:16:00', '2026-03-09 23:16:00', 5, NULL, NULL, NULL, NULL, NULL),
(11, 'Foreman1', 'foreman1@gmail.com', '09070782531', '$2y$10$jMvpZeC5ELve.b/tB4OFbeVnxMs7BIFWu2CpJpxWGcOgFE4TF.wgS', 'foreman', 'active', 0, NULL, '2026-03-10 02:53:28', '2026-03-10 02:53:28', 5, NULL, NULL, NULL, NULL, NULL),
(13, 'Super Admin', 'superadmin@edge.com', '09123456789', '$2y$10$MPyqNCvB2OwODVqCgWLI.eIMNKrr9deUup5c9zeSNQrwOA0A8zWU.', 'super_admin', 'active', 0, NULL, '2026-03-13 14:54:38', '2026-03-13 14:59:06', NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'Engineer2', 'Engineer2@gmail.com', '09070782511', '$2y$10$6n9a2JhT./x3UAawlr5kce.sLNAtiRHYKpzpJScEQNF4Ccuo9LGhC', 'engineer', 'active', 0, NULL, '2026-03-14 02:53:41', '2026-03-30 13:49:15', 5, NULL, NULL, NULL, NULL, NULL),
(15, 'Rhose Anne Reyes', 'rhose@gmail.com', '09123456782', '$2y$10$Lph93qCtXudrvgtk3bZr3OwPH.hyvvJnjYa3lJuCl6JFfhb/Jb.lS', 'engineer', 'active', 0, NULL, '2026-04-01 14:51:56', '2026-04-01 14:51:56', 5, NULL, NULL, NULL, NULL, NULL),
(16, 'Jomarie Reyes', 'jomarie@gmail.com', '09123456781', '$2y$10$Wt0uqRK4h7h5uP5fnQt4dunq3PHbAIZXq6V5a2onbQdL/kfz28d8q', 'client', 'active', 0, NULL, '2026-04-01 14:53:40', '2026-04-01 14:53:40', 5, NULL, NULL, NULL, NULL, NULL),
(17, 'Johnny Reyes', 'johnny@gmail.com', '09338268806', '$2y$10$goXbpMushPu03Ya31T0xbuNXtXsoX41LNm1Uw2AceXbek8vdlhNFa', 'foreman', 'active', 0, NULL, '2026-04-12 03:56:06', '2026-04-12 03:56:06', 5, NULL, NULL, NULL, NULL, NULL);

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
-- Indexes for table `engineer_task_updates`
--
ALTER TABLE `engineer_task_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_engineer_task_updates_task` (`task_id`),
  ADD KEY `idx_engineer_task_updates_engineer` (`engineer_id`),
  ADD KEY `idx_engineer_task_updates_created_at` (`created_at`);

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
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_projects_search_name_status_created` (`project_name`,`status`,`created_at`,`id`);

--
-- Indexes for table `project_asset_deployments`
--
ALTER TABLE `project_asset_deployments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_asset_deployments_project` (`project_id`),
  ADD KEY `idx_project_asset_deployments_asset` (`asset_id`),
  ADD KEY `idx_project_asset_deployments_returned` (`returned_at`),
  ADD KEY `fk_project_asset_deployments_user` (`deployed_by`);

--
-- Indexes for table `project_assignments`
--
ALTER TABLE `project_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`project_id`,`engineer_id`),
  ADD KEY `idx_engineer` (`engineer_id`),
  ADD KEY `fk_assignment_assigner` (`assigned_by`),
  ADD KEY `idx_project_assignments_project_latest` (`project_id`,`id`,`engineer_id`);

--
-- Indexes for table `project_inventory_deployments`
--
ALTER TABLE `project_inventory_deployments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_inventory_deployments_project` (`project_id`),
  ADD KEY `idx_project_inventory_deployments_inventory` (`inventory_id`),
  ADD KEY `idx_project_inventory_deployments_returned` (`returned_at`),
  ADD KEY `idx_project_inventory_deployments_user` (`deployed_by`);

--
-- Indexes for table `project_inventory_return_logs`
--
ALTER TABLE `project_inventory_return_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_inventory_return_logs_deployment` (`deployment_id`),
  ADD KEY `idx_project_inventory_return_logs_returned_at` (`returned_at`),
  ADD KEY `fk_project_inventory_return_logs_user` (`returned_by`);

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
  ADD KEY `fk_created_by` (`created_by`),
  ADD KEY `idx_users_search_role_status_name` (`role`,`status`,`full_name`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `engineer_task_updates`
--
ALTER TABLE `engineer_task_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `project_asset_deployments`
--
ALTER TABLE `project_asset_deployments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_assignments`
--
ALTER TABLE `project_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `project_inventory_deployments`
--
ALTER TABLE `project_inventory_deployments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `project_inventory_return_logs`
--
ALTER TABLE `project_inventory_return_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
-- Constraints for table `engineer_task_updates`
--
ALTER TABLE `engineer_task_updates`
  ADD CONSTRAINT `fk_engineer_task_updates_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_engineer_task_updates_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `project_asset_deployments`
--
ALTER TABLE `project_asset_deployments`
  ADD CONSTRAINT `fk_project_asset_deployments_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_project_asset_deployments_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_project_asset_deployments_user` FOREIGN KEY (`deployed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_assignments`
--
ALTER TABLE `project_assignments`
  ADD CONSTRAINT `fk_assignment_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_assignment_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_inventory_deployments`
--
ALTER TABLE `project_inventory_deployments`
  ADD CONSTRAINT `fk_project_inventory_deployments_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_project_inventory_deployments_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_project_inventory_deployments_user` FOREIGN KEY (`deployed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_inventory_return_logs`
--
ALTER TABLE `project_inventory_return_logs`
  ADD CONSTRAINT `fk_project_inventory_return_logs_deployment` FOREIGN KEY (`deployment_id`) REFERENCES `project_inventory_deployments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_project_inventory_return_logs_user` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
