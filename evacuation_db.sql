-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 21, 2025 at 10:17 AM
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
-- Database: `evacuation_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `caretakers`
--

CREATE TABLE `caretakers` (
  `id` int(11) NOT NULL,
  `prefix` varchar(50) DEFAULT NULL COMMENT 'คำนำหน้า',
  `first_name` varchar(100) NOT NULL COMMENT 'ชื่อจริง',
  `last_name` varchar(100) NOT NULL COMMENT 'นามสกุล',
  `position` varchar(100) DEFAULT NULL COMMENT 'ตำแหน่ง/หน้าที่ เช่น พยาบาล, อาสาสมัคร',
  `phone` varchar(50) DEFAULT NULL COMMENT 'เบอร์โทรศัพท์ติดต่อ',
  `shelter_id` int(11) DEFAULT NULL COMMENT 'ID ของศูนย์พักพิงที่รับผิดชอบ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `caretakers`
--

INSERT INTO `caretakers` (`id`, `prefix`, `first_name`, `last_name`, `position`, `phone`, `shelter_id`, `created_at`, `updated_at`) VALUES
(2, 'นาย', 'สมศักดิ์', 'รักดี', 'หัวหน้าศูนย์', '081-234-5678', 1, '2025-12-21 07:29:28', NULL),
(3, 'นาย', 'นายปฐวีกานต์', 'ศรีคราม', 'zxczc', '0981051534', 22, '2025-12-21 08:10:05', '2025-12-21 08:49:08');

-- --------------------------------------------------------

--
-- Table structure for table `evacuees`
--

CREATE TABLE `evacuees` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `id_card` varchar(13) DEFAULT NULL,
  `prefix` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `age` int(3) DEFAULT NULL,
  `health_condition` text DEFAULT NULL,
  `status` enum('registered','checked_in','checked_out','transferred') NOT NULL DEFAULT 'registered',
  `check_in_date` datetime DEFAULT current_timestamp() COMMENT 'วันที่เข้าพัก',
  `check_out_date` datetime DEFAULT NULL COMMENT 'วันที่จำหน่ายออก',
  `registered_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `type` enum('flood','fire','storm','earthquake','other') NOT NULL,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `name`, `type`, `status`, `start_date`, `end_date`, `created_at`) VALUES
(1, 'เหตุปะทะไทย–กัมพูชา ธ.ค. 2568', 'other', 'closed', '2025-12-07', '2025-12-21', '2025-12-21 06:24:33'),
(2, 'เหตุปะทะไทย–กัมพูชา ธ.ค. 2568 (ครั้งที่ 2)', 'other', 'closed', '2025-12-21', '2025-12-21', '2025-12-21 06:40:46'),
(3, 'เหตุปะทะไทย–กัมพูชา ธ.ค. 2568 (ครั้งที่ 2)', 'other', 'closed', '2025-12-21', '2025-12-21', '2025-12-21 06:40:52'),
(4, 'เหตุปะทะไทย–กัมพูชา ธ.ค. 2568 (ครั้งที่ 2)', 'other', 'closed', '2025-12-21', '2025-12-21', '2025-12-21 06:43:03'),
(5, 'เหตุปะทะไทย–กัมพูชา ธ.ค. 2568 (ครั้งที่ 4)', 'other', 'closed', '2025-12-21', '2025-12-21', '2025-12-21 06:43:22');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('current_event', 'เหตุปะทะไทย–กัมพูชา ธ.ค. 2568');

-- --------------------------------------------------------

--
-- Table structure for table `shelters`
--

CREATE TABLE `shelters` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `location` text DEFAULT NULL COMMENT 'รายละเอียดที่ตั้ง',
  `code` varchar(50) DEFAULT NULL COMMENT 'รหัสศูนย์',
  `district` varchar(100) NOT NULL,
  `province` varchar(100) DEFAULT 'ศรีสะเกษ',
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT 100,
  `subdistrict` varchar(100) DEFAULT NULL,
  `status` enum('OPEN','CLOSED') DEFAULT 'OPEN',
  `current_event` varchar(150) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shelters`
--

INSERT INTO `shelters` (`id`, `incident_id`, `name`, `location`, `code`, `district`, `province`, `contact_person`, `phone`, `capacity`, `subdistrict`, `status`, `current_event`, `last_updated`) VALUES
(22, NULL, 'ศูนย์อาคารพละวีสมหมาย', NULL, NULL, 'เมือง', 'ศรีสะเกษ', NULL, NULL, 3000, NULL, 'OPEN', 'เหตุปะทะไทย–กัมพูชา ธ.ค. 2568', '2025-12-21 03:36:26'),
(49, 2, 'อาคารพลศึกษา วีสมหมาย', 'ต.  อ.  จ. ', '', '', '', '', '', 3000, '', 'OPEN', NULL, '2025-12-21 08:04:26');

-- --------------------------------------------------------

--
-- Table structure for table `shelter_coordinators`
--

CREATE TABLE `shelter_coordinators` (
  `id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shelter_coordinators`
--

INSERT INTO `shelter_coordinators` (`id`, `shelter_id`, `name`, `phone`, `position`) VALUES
(1, 22, 'นายศักดิ์ชัย กงแก้ว', '095-616-9981', 'หน.ศูนย์');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'Logout', 'ออกจากระบบ', '::1', '2025-12-21 06:53:45'),
(2, 2, 'Logout', 'ออกจากระบบ', '::1', '2025-12-21 06:55:18'),
(3, 1, 'Logout', 'ออกจากระบบ', '::1', '2025-12-21 08:05:54'),
(4, 2, 'Add Caretaker', 'เพิ่มผู้ดูแล: นายปฐวีกานต์ ศรีคราม', '::1', '2025-12-21 08:10:05'),
(5, 2, 'Logout', 'ออกจากระบบ', '::1', '2025-12-21 08:28:03'),
(6, 1, 'Edit Caretaker', 'แก้ไขผู้ดูแล: นายปฐวีกานต์ ศรีคราม', '::1', '2025-12-21 08:49:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','staff','volunteer') NOT NULL DEFAULT 'staff',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2a$12$zk8PKOASddu96PjkItPoFu9JyLOZMeoC4gg4.BoVElSsBKyKtcss2', 'Admin User', 'admin', NULL, '2025-12-21 05:50:15'),
(2, 'max', '$2y$10$aXlj7pfOt3lhN4u.XE3PjulKjsKbhuiRRApHtjl7dqNrWW1Q2GIpu', 'ป', 'staff', NULL, '2025-12-21 06:20:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `caretakers`
--
ALTER TABLE `caretakers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shelter` (`shelter_id`);

--
-- Indexes for table `evacuees`
--
ALTER TABLE `evacuees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `shelter_id` (`shelter_id`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `shelters`
--
ALTER TABLE `shelters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incident` (`incident_id`);

--
-- Indexes for table `shelter_coordinators`
--
ALTER TABLE `shelter_coordinators`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shelter_id` (`shelter_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `caretakers`
--
ALTER TABLE `caretakers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `evacuees`
--
ALTER TABLE `evacuees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shelters`
--
ALTER TABLE `shelters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `shelter_coordinators`
--
ALTER TABLE `shelter_coordinators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `shelter_coordinators`
--
ALTER TABLE `shelter_coordinators`
  ADD CONSTRAINT `shelter_coordinators_ibfk_1` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
