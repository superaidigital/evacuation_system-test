-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 22, 2025 at 12:28 PM
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
  `prefix` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `shelter_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `caretakers`
--

INSERT INTO `caretakers` (`id`, `prefix`, `first_name`, `last_name`, `shelter_id`, `full_name`, `phone`, `position`, `status`) VALUES
(1, 'นาย', 'นายปฐวีกานต์', 'ศรีคราม', 1, 'อาจารย์สมปอง', '081-234-5678', 'หัวหน้าศูนย์พักพิง', 'active'),
(2, 'นาย', 'นายปฐวีกานต์', 'ศรีคราม', 2, 'พระครูวิจิตร', '089-876-5432', 'ผู้ดูแลสถานที่', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `distribution_logs`
--

CREATE TABLE `distribution_logs` (
  `id` int(11) NOT NULL,
  `evacuee_id` int(11) NOT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `given_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `given_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evacuees`
--

CREATE TABLE `evacuees` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `shelter_id` int(11) DEFAULT NULL,
  `stay_type` enum('shelter','outside') NOT NULL DEFAULT 'shelter' COMMENT 'ประเภทการพัก',
  `stay_detail` text DEFAULT NULL COMMENT 'รายละเอียดที่พัก (กรณีพักนอกศูนย์)',
  `id_card` varchar(13) DEFAULT NULL COMMENT 'เลขบัตรประชาชน',
  `address_card` text DEFAULT NULL COMMENT 'ที่อยู่ตามบัตรประชาชน',
  `prefix` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `age` int(3) DEFAULT NULL,
  `health_condition` text DEFAULT NULL COMMENT 'โรคประจำตัว/ยาที่แพ้',
  `registered_by` int(11) DEFAULT NULL COMMENT 'ID เจ้าหน้าที่ที่ลงทะเบียนให้',
  `check_in_date` datetime NOT NULL DEFAULT current_timestamp(),
  `check_out_date` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evacuees`
--

INSERT INTO `evacuees` (`id`, `incident_id`, `shelter_id`, `stay_type`, `stay_detail`, `id_card`, `address_card`, `prefix`, `first_name`, `last_name`, `phone`, `gender`, `age`, `health_condition`, `registered_by`, `check_in_date`, `check_out_date`, `updated_at`, `created_at`) VALUES
(1, 1, 1, 'shelter', NULL, '1330000111222', NULL, 'นาย', 'สมศักดิ์', 'รักชาติ', '081-111-2222', 'male', 45, 'ความดันโลหิตสูง', 2, '2025-12-22 14:12:37', NULL, NULL, '2025-12-22 14:12:37'),
(2, 1, 1, 'shelter', NULL, '1330000333444', NULL, 'นาง', 'สมศรี', 'มีสุข', '089-333-4444', 'female', 42, '-', 2, '2025-12-22 14:12:37', NULL, NULL, '2025-12-22 14:12:37'),
(3, 1, 1, 'shelter', NULL, '1330000555666', NULL, 'ด.ช.', 'เก่ง', 'รักชาติ', NULL, 'male', 10, 'แพ้อาหารทะเล', 2, '2025-12-22 14:12:37', NULL, NULL, '2025-12-22 14:12:37'),
(4, 1, 2, 'shelter', 'บ้านญาติ', '1332000000946', '', '', 'วิชัย', 'ใจดี', '090-555-6666', 'male', 60, 'เบาหวาน, เดินไม่สะดวก', 3, '2025-12-22 14:12:37', NULL, '2025-12-22 16:55:16', '2025-12-22 14:12:37'),
(5, 3, NULL, 'outside', 'บ้านญาติ', '1332000000946', '', 'นาย', 'วิชัย', 'ใจดี', '090-555-6666', 'male', 60, '', 1, '2025-12-22 16:15:33', NULL, NULL, '2025-12-22 16:15:33');

-- --------------------------------------------------------

--
-- Table structure for table `evacuee_needs`
--

CREATE TABLE `evacuee_needs` (
  `id` int(11) NOT NULL,
  `evacuee_id` int(11) NOT NULL,
  `need_type` enum('pregnant','disabled','elderly','infant','chronic_disease') NOT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL COMMENT 'ชื่อเหตุการณ์ (เช่น น้ำท่วมปี 67)',
  `type` varchar(50) NOT NULL DEFAULT 'other',
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `name`, `type`, `description`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 'อุทกภัย ปี 2567 (ศรีสะเกษ)', 'other', 'น้ำท่วมใหญ่ในพื้นที่อำเภอเมืองและกันทรลักษ์ ระดับน้ำสูงกว่า 1 เมตร', '2024-09-15', NULL, 'active', '2025-12-22 07:12:37'),
(2, 'วาตภัย พายุฤดูร้อน (เม.ย. 67)', 'other', 'พายุฝนฟ้าคะนอง บ้านเรือนเสียหาย 50 หลังคาเรือน', '2024-04-10', '2024-04-20', 'closed', '2025-12-22 07:12:37'),
(3, 'เหตุปะทะไทย–กัมพูชา ธ.ค. 2568', 'other', '', '2025-12-22', NULL, 'active', '2025-12-22 07:41:33');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(20) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `transaction_type` enum('in','out') NOT NULL COMMENT 'in=รับบริจาค/ซื้อ, out=แจกจ่าย/เสียหาย',
  `quantity` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'ผู้ทำรายการ',
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shelters`
--

CREATE TABLE `shelters` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL COMMENT 'ผูกกับเหตุการณ์ไหน',
  `name` varchar(200) NOT NULL COMMENT 'ชื่อศูนย์',
  `location` text NOT NULL COMMENT 'ที่ตั้ง',
  `capacity` int(11) NOT NULL DEFAULT 0 COMMENT 'ความจุสูงสุด',
  `contact_phone` varchar(20) DEFAULT NULL,
  `status` enum('open','full','closed') NOT NULL DEFAULT 'open',
  `last_updated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shelters`
--

INSERT INTO `shelters` (`id`, `incident_id`, `name`, `location`, `capacity`, `contact_phone`, `status`, `last_updated`) VALUES
(1, 1, 'โรงเรียนสตรีสิริเกศ', 'ต.เมืองใต้ อ.เมือง จ.ศรีสะเกษ', 500, '045-612-888', 'open', '2025-12-22 14:12:37'),
(2, 1, 'วัดเจียงอีศรีมงคลวราราม', 'ต.เมืองใต้ อ.เมือง จ.ศรีสะเกษ', 300, '081-999-1234', 'open', '2025-12-22 14:12:37'),
(3, 1, 'หอประชุมอำเภอกันทรลักษ์', 'ต.หนองหญ้าลาด อ.กันทรลักษ์', 1000, '045-661-555', 'open', '2025-12-22 14:12:37'),
(4, 2, 'ศาลากลางหมู่บ้านหนองแคน', 'ต.หนองแคน อ.อุทุมพรพิสัย', 100, '089-777-6666', 'closed', '2025-12-22 14:12:37');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL COMMENT 'ประเภทการกระทำ (Login, Add, Edit)',
  `description` text DEFAULT NULL COMMENT 'รายละเอียดเพิ่มเติม',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'Login', 'เข้าสู่ระบบสำเร็จ', '127.0.0.1', '2025-12-22 07:12:37'),
(2, 2, 'Login', 'เข้าสู่ระบบสำเร็จ', '127.0.0.1', '2025-12-22 07:12:37'),
(3, 2, 'Add Evacuee', 'ลงทะเบียน: สมศักดิ์ รักชาติ (Shelter ID: 1)', '127.0.0.1', '2025-12-22 07:12:37'),
(4, 1, 'Logout', 'ออกจากระบบ', '::1', '2025-12-22 07:12:47'),
(5, 1, 'Login', 'เข้าสู่ระบบสำเร็จ', '::1', '2025-12-22 07:13:12'),
(6, 1, 'Login', 'เข้าสู่ระบบสำเร็จ', '::1', '2025-12-22 07:22:42'),
(7, 1, 'Edit Incident', 'แก้ไขภารกิจ ID: 1', '::1', '2025-12-22 07:40:54'),
(8, 1, 'Edit Incident', 'แก้ไขภารกิจ ID: 2', '::1', '2025-12-22 07:40:59'),
(9, 1, 'Add Incident', 'เปิดภารกิจใหม่: เหตุปะทะไทย–กัมพูชา ธ.ค. 2568 (other)', '::1', '2025-12-22 07:41:33'),
(10, 1, 'Edit Evacuee', 'ชื่อ: วิชัย ใจดี (Shelter ID: 2)', '::1', '2025-12-22 08:34:03'),
(11, 1, 'Edit Evacuee', 'ชื่อ: วิชัย ใจดี (Shelter ID: 2)', '::1', '2025-12-22 08:40:55'),
(12, 1, 'Edit Evacuee', 'ชื่อ: วิชัย ใจดี (Shelter ID: 2)', '::1', '2025-12-22 08:41:16'),
(13, 1, 'Edit Evacuee', 'ชื่อ: วิชัย ใจดี (Shelter ID: 2)', '::1', '2025-12-22 08:56:09'),
(14, 1, 'Edit Evacuee', 'ชื่อ: วิชัย ใจดี (Shelter ID: 2)', '::1', '2025-12-22 08:56:26'),
(15, 1, 'Edit Caretaker', 'แก้ไขผู้ดูแล: นายปฐวีกานต์ ศรีคราม', '::1', '2025-12-22 09:13:51'),
(16, 1, 'Edit Caretaker', 'แก้ไขผู้ดูแล: นายปฐวีกานต์ ศรีคราม', '::1', '2025-12-22 09:13:57'),
(17, 1, 'Add Evacuee', 'ชื่อ: วิชัย ใจดี (Shelter ID: )', '::1', '2025-12-22 09:15:33'),
(18, 1, 'Edit Evacuee', 'ชื่อ: วิชัย ใจดี (Shelter ID: )', '::1', '2025-12-22 09:16:23'),
(19, 1, 'Edit Evacuee', 'ชื่อ: วิชัย ใจดี (ID: 4)', '::1', '2025-12-22 09:54:55'),
(20, 1, 'Edit Evacuee', 'ชื่อ: วิชัย ใจดี (ID: 4)', '::1', '2025-12-22 09:55:16'),
(21, 1, 'Edit Incident', 'แก้ไขภารกิจ ID: 1', '::1', '2025-12-22 10:00:07'),
(22, 1, 'Edit Caretaker', 'แก้ไขผู้ดูแล: นายปฐวีกานต์ ศรีคราม', '::1', '2025-12-22 10:00:30'),
(23, 1, 'Logout', 'ออกจากระบบ', '::1', '2025-12-22 10:03:32'),
(24, 1, 'Login', 'เข้าสู่ระบบสำเร็จ', '::1', '2025-12-22 10:08:41'),
(25, 1, 'Edit Incident', 'แก้ไขภารกิจ ID: 3', '::1', '2025-12-22 10:10:19'),
(26, 1, 'Edit Incident', 'แก้ไขภารกิจ ID: 1', '::1', '2025-12-22 10:10:22'),
(27, 1, 'Edit Incident', 'แก้ไขภารกิจ ID: 1', '::1', '2025-12-22 10:10:31'),
(28, 1, 'Edit Incident', 'แก้ไขภารกิจ ID: 1', '::1', '2025-12-22 10:10:36'),
(29, 1, 'Edit Caretaker', 'แก้ไขผู้ดูแล: นายปฐวีกานต์ ศรีคราม', '::1', '2025-12-22 10:29:36'),
(30, 1, 'Edit Caretaker', 'แก้ไขผู้ดูแล: นายปฐวีกานต์ ศรีคราม', '::1', '2025-12-22 10:53:28'),
(31, 1, 'Edit User', 'แก้ไขผู้ใช้งาน: staff01', '::1', '2025-12-22 11:22:04'),
(32, 1, 'Logout', 'ออกจากระบบ', '::1', '2025-12-22 11:22:07'),
(33, 2, 'Login', 'เข้าสู่ระบบสำเร็จ', '::1', '2025-12-22 11:22:11');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL COMMENT 'ชื่อผู้ใช้',
  `password` varchar(255) NOT NULL COMMENT 'รหัสผ่าน (Hashed)',
  `full_name` varchar(100) NOT NULL COMMENT 'ชื่อ-นามสกุล',
  `role` enum('admin','staff','volunteer') NOT NULL DEFAULT 'staff' COMMENT 'สิทธิ์การใช้งาน',
  `shelter_id` int(11) DEFAULT NULL COMMENT 'สังกัดศูนย์พักพิง',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `shelter_id`, `created_at`, `last_login`) VALUES
(1, 'admin', '$2a$12$zk8PKOASddu96PjkItPoFu9JyLOZMeoC4gg4.BoVElSsBKyKtcss2', 'ผู้ดูแลระบบสูงสุด', 'admin', NULL, '2025-12-22 07:12:37', NULL),
(2, 'staff01', '$2y$10$cOmaH/AjDmYBy1O4H/OMx.W/EUCtipk7jFSyaprR7C93pYj.X1dfO', 'เจ้าหน้าที่ สมชาย ใจดี', 'staff', 1, '2025-12-22 07:12:37', NULL),
(3, 'staff02', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'เจ้าหน้าที่ สมหญิง รักงาน', 'staff', NULL, '2025-12-22 07:12:37', NULL),
(4, 'volunteer01', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'อาสาสมัคร ก.', 'volunteer', NULL, '2025-12-22 07:12:37', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_shelter_stats`
-- (See below for the actual view)
--
CREATE TABLE `view_shelter_stats` (
`shelter_id` int(11)
,`shelter_name` varchar(200)
,`capacity` int(11)
,`incident_id` int(11)
,`status` enum('open','full','closed')
,`current_occupancy` bigint(21)
,`vacancy` bigint(22)
,`occupancy_rate` decimal(26,2)
);

-- --------------------------------------------------------

--
-- Structure for view `view_shelter_stats`
--
DROP TABLE IF EXISTS `view_shelter_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_shelter_stats`  AS SELECT `s`.`id` AS `shelter_id`, `s`.`name` AS `shelter_name`, `s`.`capacity` AS `capacity`, `s`.`incident_id` AS `incident_id`, `s`.`status` AS `status`, count(`e`.`id`) AS `current_occupancy`, `s`.`capacity`- count(`e`.`id`) AS `vacancy`, round(count(`e`.`id`) / `s`.`capacity` * 100,2) AS `occupancy_rate` FROM (`shelters` `s` left join `evacuees` `e` on(`s`.`id` = `e`.`shelter_id` and `e`.`check_out_date` is null)) GROUP BY `s`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `caretakers`
--
ALTER TABLE `caretakers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shelter_caretaker` (`shelter_id`);

--
-- Indexes for table `distribution_logs`
--
ALTER TABLE `distribution_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `evacuees`
--
ALTER TABLE `evacuees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_card` (`id_card`),
  ADD KEY `idx_names` (`first_name`,`last_name`),
  ADD KEY `idx_shelter_active` (`shelter_id`,`check_out_date`),
  ADD KEY `idx_incident` (`incident_id`);

--
-- Indexes for table `evacuee_needs`
--
ALTER TABLE `evacuee_needs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_needs_evacuee` (`evacuee_id`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_inventory_shelter` (`shelter_id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory` (`inventory_id`);

--
-- Indexes for table `shelters`
--
ALTER TABLE `shelters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incident_id` (`incident_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_id`,`action`),
  ADD KEY `idx_created_at` (`created_at`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `distribution_logs`
--
ALTER TABLE `distribution_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evacuees`
--
ALTER TABLE `evacuees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `evacuee_needs`
--
ALTER TABLE `evacuee_needs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shelters`
--
ALTER TABLE `shelters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `caretakers`
--
ALTER TABLE `caretakers`
  ADD CONSTRAINT `fk_caretakers_shelter` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evacuees`
--
ALTER TABLE `evacuees`
  ADD CONSTRAINT `fk_evacuees_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_evacuees_shelter` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evacuee_needs`
--
ALTER TABLE `evacuee_needs`
  ADD CONSTRAINT `evacuee_needs_ibfk_1` FOREIGN KEY (`evacuee_id`) REFERENCES `evacuees` (`id`),
  ADD CONSTRAINT `fk_needs_evacuee` FOREIGN KEY (`evacuee_id`) REFERENCES `evacuees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_shelter` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_trans_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shelters`
--
ALTER TABLE `shelters`
  ADD CONSTRAINT `fk_shelters_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
