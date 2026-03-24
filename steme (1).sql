-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 24, 2026 at 11:00 AM
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
-- Database: `steme`
--

-- --------------------------------------------------------

--
-- Table structure for table `cardiac_cath`
--

CREATE TABLE `cardiac_cath` (
  `patient_id` int(11) NOT NULL,
  `arrived_date` date DEFAULT NULL,
  `arrived_time` time DEFAULT NULL,
  `pci_status` varchar(255) DEFAULT NULL,
  `cardiogenic_shock` varchar(10) DEFAULT NULL,
  `pci_indication` text DEFAULT NULL,
  `procedure_success` varchar(10) DEFAULT NULL,
  `door_in_datetime` datetime DEFAULT NULL,
  `puncture_time` datetime DEFAULT NULL,
  `first_device_time` datetime DEFAULT NULL,
  `finish_time` datetime DEFAULT NULL,
  `iabp` varchar(10) DEFAULT NULL,
  `mc_support` varchar(10) DEFAULT NULL,
  `mc_type` varchar(255) DEFAULT NULL,
  `cad_presentation` varchar(100) DEFAULT NULL,
  `access_site` varchar(100) DEFAULT NULL,
  `door_to_device` int(11) DEFAULT NULL COMMENT 'คำนวณอัตโนมัติ (นาที)',
  `onset_to_device` int(11) DEFAULT NULL COMMENT 'คำนวณอัตโนมัติ (นาที)',
  `access_crossover` varchar(10) DEFAULT NULL,
  `dominance` varchar(100) DEFAULT NULL,
  `nonsignificant_cad` varchar(10) DEFAULT NULL,
  `segment_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`segment_data`)),
  `cath_conclusion` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `first_device_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cardiac_cath`
--

INSERT INTO `cardiac_cath` (`patient_id`, `arrived_date`, `arrived_time`, `pci_status`, `cardiogenic_shock`, `pci_indication`, `procedure_success`, `door_in_datetime`, `puncture_time`, `first_device_time`, `finish_time`, `iabp`, `mc_support`, `mc_type`, `cad_presentation`, `access_site`, `door_to_device`, `onset_to_device`, `access_crossover`, `dominance`, `nonsignificant_cad`, `segment_data`, `cath_conclusion`, `updated_at`, `first_device_date`) VALUES
(50, '0000-00-00', '00:00:00', '', 'No', '', 'No', NULL, NULL, NULL, NULL, 'No', 'No', '', 'Unstable angina', 'Brachial a.', NULL, NULL, NULL, NULL, NULL, '[]', '', '2026-01-14 09:26:44', NULL),
(51, '0000-00-00', '00:00:00', '', 'No', '', 'No', NULL, NULL, NULL, NULL, 'No', 'No', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '[]', '', '2026-01-15 04:20:57', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `medication_reconciliation`
--

CREATE TABLE `medication_reconciliation` (
  `patient_id` int(11) NOT NULL,
  `asa_type` text DEFAULT NULL,
  `admit_asa_status` enum('Yes','No') DEFAULT NULL,
  `admit_asa_remark` text DEFAULT NULL,
  `disch_asa_status` enum('Yes','No') DEFAULT NULL,
  `disch_asa_remark` text DEFAULT NULL,
  `p2y12_type` text DEFAULT NULL,
  `admit_p2y12_status` enum('Yes','No') DEFAULT NULL,
  `admit_p2y12_remark` text DEFAULT NULL,
  `disch_p2y12_status` enum('Yes','No') DEFAULT NULL,
  `disch_p2y12_remark` text DEFAULT NULL,
  `bb_type` text DEFAULT NULL,
  `admit_bb_status` enum('Yes','No') DEFAULT NULL,
  `admit_bb_remark` text DEFAULT NULL,
  `disch_bb_status` enum('Yes','No') DEFAULT NULL,
  `disch_bb_remark` text DEFAULT NULL,
  `acei_type` text DEFAULT NULL,
  `admit_acei_status` enum('Yes','No') DEFAULT NULL,
  `admit_acei_remark` text DEFAULT NULL,
  `disch_acei_status` enum('Yes','No') DEFAULT NULL,
  `disch_acei_remark` text DEFAULT NULL,
  `statin_type` text DEFAULT NULL,
  `admit_statin_status` enum('Yes','No') DEFAULT NULL,
  `admit_statin_remark` text DEFAULT NULL,
  `disch_statin_status` enum('Yes','No') DEFAULT NULL,
  `disch_statin_remark` text DEFAULT NULL,
  `admit_m6_status` enum('Yes','No') DEFAULT NULL,
  `admit_m6_remark` text DEFAULT NULL,
  `disch_m6_status` enum('Yes','No') DEFAULT NULL,
  `disch_m6_remark` text DEFAULT NULL,
  `admit_m7_status` enum('Yes','No') DEFAULT NULL,
  `admit_m7_remark` text DEFAULT NULL,
  `disch_m7_status` enum('Yes','No') DEFAULT NULL,
  `disch_m7_remark` text DEFAULT NULL,
  `admit_m8_status` enum('Yes','No') DEFAULT NULL,
  `admit_m8_remark` text DEFAULT NULL,
  `disch_m8_status` enum('Yes','No') DEFAULT NULL,
  `disch_m8_remark` text DEFAULT NULL,
  `admit_m9_status` enum('Yes','No') DEFAULT NULL,
  `admit_m9_remark` text DEFAULT NULL,
  `disch_m9_status` enum('Yes','No') DEFAULT NULL,
  `disch_m9_remark` text DEFAULT NULL,
  `admit_m10_status` enum('Yes','No') DEFAULT NULL,
  `admit_m10_remark` text DEFAULT NULL,
  `disch_m10_status` enum('Yes','No') DEFAULT NULL,
  `disch_m10_remark` text DEFAULT NULL,
  `admit_m11_status` enum('Yes','No') DEFAULT NULL,
  `admit_m11_remark` text DEFAULT NULL,
  `disch_m11_status` enum('Yes','No') DEFAULT NULL,
  `disch_m11_remark` text DEFAULT NULL,
  `admit_m12_status` enum('Yes','No') DEFAULT NULL,
  `admit_m12_remark` text DEFAULT NULL,
  `disch_m12_status` enum('Yes','No') DEFAULT NULL,
  `disch_m12_remark` text DEFAULT NULL,
  `admit_m13_status` enum('Yes','No') DEFAULT NULL,
  `admit_m13_remark` text DEFAULT NULL,
  `disch_m13_status` enum('Yes','No') DEFAULT NULL,
  `disch_m13_remark` text DEFAULT NULL,
  `extra_m14_name` text DEFAULT NULL,
  `admit_m14_status` enum('Yes','No') DEFAULT NULL,
  `admit_m14_remark` text DEFAULT NULL,
  `disch_m14_status` enum('Yes','No') DEFAULT NULL,
  `disch_m14_remark` text DEFAULT NULL,
  `extra_m15_name` text DEFAULT NULL,
  `admit_m15_status` enum('Yes','No') DEFAULT NULL,
  `admit_m15_remark` text DEFAULT NULL,
  `disch_m15_status` enum('Yes','No') DEFAULT NULL,
  `disch_m15_remark` text DEFAULT NULL,
  `extra_m16_name` text DEFAULT NULL,
  `admit_m16_status` enum('Yes','No') DEFAULT NULL,
  `admit_m16_remark` text DEFAULT NULL,
  `disch_m16_status` enum('Yes','No') DEFAULT NULL,
  `disch_m16_remark` text DEFAULT NULL,
  `extra_m17_name` text DEFAULT NULL,
  `admit_m17_status` enum('Yes','No') DEFAULT NULL,
  `admit_m17_remark` text DEFAULT NULL,
  `disch_m17_status` enum('Yes','No') DEFAULT NULL,
  `disch_m17_remark` text DEFAULT NULL,
  `extra_m18_name` text DEFAULT NULL,
  `admit_m18_status` enum('Yes','No') DEFAULT NULL,
  `admit_m18_remark` text DEFAULT NULL,
  `disch_m18_status` enum('Yes','No') DEFAULT NULL,
  `disch_m18_remark` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medication_reconciliation`
--

INSERT INTO `medication_reconciliation` (`patient_id`, `asa_type`, `admit_asa_status`, `admit_asa_remark`, `disch_asa_status`, `disch_asa_remark`, `p2y12_type`, `admit_p2y12_status`, `admit_p2y12_remark`, `disch_p2y12_status`, `disch_p2y12_remark`, `bb_type`, `admit_bb_status`, `admit_bb_remark`, `disch_bb_status`, `disch_bb_remark`, `acei_type`, `admit_acei_status`, `admit_acei_remark`, `disch_acei_status`, `disch_acei_remark`, `statin_type`, `admit_statin_status`, `admit_statin_remark`, `disch_statin_status`, `disch_statin_remark`, `admit_m6_status`, `admit_m6_remark`, `disch_m6_status`, `disch_m6_remark`, `admit_m7_status`, `admit_m7_remark`, `disch_m7_status`, `disch_m7_remark`, `admit_m8_status`, `admit_m8_remark`, `disch_m8_status`, `disch_m8_remark`, `admit_m9_status`, `admit_m9_remark`, `disch_m9_status`, `disch_m9_remark`, `admit_m10_status`, `admit_m10_remark`, `disch_m10_status`, `disch_m10_remark`, `admit_m11_status`, `admit_m11_remark`, `disch_m11_status`, `disch_m11_remark`, `admit_m12_status`, `admit_m12_remark`, `disch_m12_status`, `disch_m12_remark`, `admit_m13_status`, `admit_m13_remark`, `disch_m13_status`, `disch_m13_remark`, `extra_m14_name`, `admit_m14_status`, `admit_m14_remark`, `disch_m14_status`, `disch_m14_remark`, `extra_m15_name`, `admit_m15_status`, `admit_m15_remark`, `disch_m15_status`, `disch_m15_remark`, `extra_m16_name`, `admit_m16_status`, `admit_m16_remark`, `disch_m16_status`, `disch_m16_remark`, `extra_m17_name`, `admit_m17_status`, `admit_m17_remark`, `disch_m17_status`, `disch_m17_remark`, `extra_m18_name`, `admit_m18_status`, `admit_m18_remark`, `disch_m18_status`, `disch_m18_remark`, `updated_at`) VALUES
(50, NULL, 'Yes', '', NULL, '', NULL, NULL, '', NULL, '', NULL, NULL, '', NULL, '', NULL, NULL, '', NULL, '', NULL, NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', '', NULL, '', NULL, '', '', NULL, '', NULL, '', '', NULL, '', NULL, '', '', NULL, '', NULL, '', '', NULL, '', NULL, '', '2026-01-15 03:19:58'),
(51, NULL, 'Yes', '', NULL, '', NULL, NULL, '', NULL, '', NULL, 'Yes', '', NULL, '', NULL, 'Yes', '', NULL, '', NULL, 'Yes', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', 'Yes', '', NULL, '', 'Yes', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', 'Yes', '', NULL, '', '', 'Yes', '', NULL, '', '', NULL, '', NULL, '', '', NULL, '', NULL, '', '', NULL, '', NULL, '', '', NULL, '', NULL, '', '2026-01-27 08:55:17');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `hospital_code` varchar(20) DEFAULT NULL,
  `hn` varchar(50) DEFAULT NULL,
  `id_type` varchar(50) DEFAULT NULL,
  `citizen_id` varchar(20) DEFAULT NULL,
  `firstname` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `first_ekg_date` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` enum('ชาย','หญิง') DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `treatment_right` varchar(100) DEFAULT NULL,
  `credit_name` varchar(255) DEFAULT NULL,
  `health_zone` varchar(50) DEFAULT NULL,
  `outside_detail` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `phone_alt` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `hospital_code`, `hn`, `id_type`, `citizen_id`, `firstname`, `lastname`, `weight`, `height`, `first_ekg_date`, `age`, `gender`, `occupation`, `religion`, `treatment_right`, `credit_name`, `health_zone`, `outside_detail`, `phone`, `phone_alt`, `created_at`) VALUES
(50, 'โรงพยาบาลสงขลา', '52100/50', 'เลขบัตรประชาชน', '1909802625848', 'ทดสอบ', 'ระบบ', 50.00, 150.00, NULL, 20, '', 'พนักงาน', 'พุทธ', '', 'ปกส. ในเครือ', '', '', '0866950400', '-', '2026-01-14 03:27:48'),
(51, 'โรงพยาบาลหาดใหญ่', '52100/55', 'เลขบัตรประชาชน', '1909802625949', 'สิรบดินทร์', 'รัตนพันธ์', 50.00, 150.00, NULL, 23, 'ชาย', 'พนักงาน', 'พุทธ', '', 'ปกส. ในเครือ', '', '', '0866950400', '0866950400', '2026-01-15 04:20:34');

-- --------------------------------------------------------

--
-- Table structure for table `patient_consults`
--

CREATE TABLE `patient_consults` (
  `patient_id` int(11) NOT NULL,
  `diag_date` date DEFAULT NULL,
  `diag_time` time DEFAULT NULL,
  `cardio_name` varchar(255) DEFAULT NULL,
  `cardio_date` date DEFAULT NULL,
  `cardio_time` time DEFAULT NULL,
  `inter_name` varchar(255) DEFAULT NULL,
  `inter_date` date DEFAULT NULL,
  `inter_time` time DEFAULT NULL,
  `exit_er_date` date DEFAULT NULL,
  `exit_er_time` time DEFAULT NULL,
  `admit_ward` varchar(255) DEFAULT NULL,
  `admit_date` date DEFAULT NULL,
  `admit_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_consults`
--

INSERT INTO `patient_consults` (`patient_id`, `diag_date`, `diag_time`, `cardio_name`, `cardio_date`, `cardio_time`, `inter_name`, `inter_date`, `inter_time`, `exit_er_date`, `exit_er_time`, `admit_ward`, `admit_date`, `admit_time`) VALUES
(51, '0000-00-00', '00:00:00', '', '2026-01-22', '00:00:00', '', '2026-01-22', '00:00:00', '0000-00-00', '00:00:00', '', '0000-00-00', '00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `patient_discharges`
--

CREATE TABLE `patient_discharges` (
  `id` int(11) NOT NULL,
  `patient_id` varchar(50) NOT NULL COMMENT 'เก็บ HN (ไม่ต้องมีในตาราง patients ก็บันทึกได้)',
  `discharge_ward` varchar(255) DEFAULT NULL,
  `admit_date` date DEFAULT NULL,
  `discharge_date` date DEFAULT NULL,
  `discharge_time` time DEFAULT NULL,
  `hospital_cost` decimal(10,2) DEFAULT NULL,
  `dis_status` varchar(50) DEFAULT 'Dead',
  `ds_dead_cause` text DEFAULT NULL COMMENT 'สาเหตุหลัก',
  `death_cause_list` text DEFAULT NULL COMMENT 'Checkboxes',
  `dis_notes` text DEFAULT NULL COMMENT 'Note เพิ่มเติม',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `final_dx_other` varchar(255) DEFAULT NULL,
  `icd_other` varchar(255) DEFAULT NULL,
  `fup1_date` date DEFAULT NULL,
  `fup1_detail` text DEFAULT NULL,
  `fup2_date` date DEFAULT NULL,
  `fup2_detail` text DEFAULT NULL,
  `fup3_date` date DEFAULT NULL,
  `fup3_detail` text DEFAULT NULL,
  `admit_time` time DEFAULT NULL,
  `final_diagnosis` varchar(255) DEFAULT NULL,
  `mi_type` varchar(50) DEFAULT NULL,
  `icd_code` varchar(255) DEFAULT NULL,
  `length_days` int(11) DEFAULT NULL,
  `length_hours` int(11) DEFAULT NULL,
  `ds_against_reason` text DEFAULT NULL,
  `ds_refer1_hosp` varchar(255) DEFAULT NULL,
  `ds_refer1_reason` text DEFAULT NULL,
  `ds_refer2_hosp` varchar(255) DEFAULT NULL,
  `ds_refer2_reason` text DEFAULT NULL,
  `ds_referback_hosp` varchar(255) DEFAULT NULL,
  `ds_referback_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patient_discharges`
--

INSERT INTO `patient_discharges` (`id`, `patient_id`, `discharge_ward`, `admit_date`, `discharge_date`, `discharge_time`, `hospital_cost`, `dis_status`, `ds_dead_cause`, `death_cause_list`, `dis_notes`, `created_at`, `updated_at`, `final_dx_other`, `icd_other`, `fup1_date`, `fup1_detail`, `fup2_date`, `fup2_detail`, `fup3_date`, `fup3_detail`, `admit_time`, `final_diagnosis`, `mi_type`, `icd_code`, `length_days`, `length_hours`, `ds_against_reason`, `ds_refer1_hosp`, `ds_refer1_reason`, `ds_refer2_hosp`, `ds_refer2_reason`, `ds_referback_hosp`, `ds_referback_reason`) VALUES
(1, '', NULL, NULL, '2026-01-28', '03:57:00', NULL, 'Dead', '', '', '', '2026-01-23 02:40:46', '2026-01-28 02:57:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, '50', '', NULL, NULL, NULL, 0.00, 'Dead', '', '', '', '2026-01-26 04:32:18', '2026-01-26 04:32:18', '', '', NULL, '', NULL, '', NULL, '', NULL, '', '', '', 0, 0, '', NULL, NULL, NULL, NULL, NULL, NULL),
(32, '51', '', '0000-00-00', '0000-00-00', '00:00:00', 0.00, 'Alive', '', '', '', '2026-02-03 06:45:56', '2026-02-03 06:45:56', '', '', NULL, '', NULL, '', NULL, '', '00:00:00', '', '', '', 0, 0, '', '', '', '', '', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `patient_medications`
--

CREATE TABLE `patient_medications` (
  `patient_id` int(11) NOT NULL,
  `fibrinolytic_drug` varchar(100) DEFAULT NULL COMMENT 'ชนิดยา Fibrinolytic (SK, TNK, rTPA)',
  `asa` varchar(255) DEFAULT NULL,
  `asa_reason` varchar(255) DEFAULT NULL,
  `p2y12` varchar(255) DEFAULT NULL,
  `p2y12_reason` varchar(255) DEFAULT NULL,
  `fibrinolytic_type` varchar(255) DEFAULT NULL,
  `hospital_date_rpth` date DEFAULT NULL,
  `hospital_time_start_rpth` time DEFAULT NULL,
  `hospital_time_end_rpth` time DEFAULT NULL,
  `rpth_door_to_needle` int(11) DEFAULT NULL,
  `rpth_stemi_dx_to_needle` int(11) DEFAULT NULL,
  `complications` varchar(255) DEFAULT NULL,
  `complications_detail` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rpth_onset_to_fmc` int(11) DEFAULT NULL,
  `rpth_fmc_to_needle` int(11) DEFAULT NULL,
  `rpth_ekg_to_dx` int(11) DEFAULT NULL,
  `rpth_onset_to_needle` int(11) DEFAULT NULL,
  `sk_opened` varchar(50) DEFAULT NULL COMMENT 'หลังให้ SK เปิดหลอดเลือดได้ไหม',
  `hatyai_place` text DEFAULT NULL COMMENT 'สถานที่ใน รพ.หาดใหญ่ (ER, CCU...)',
  `hatyai_kpi_data` text DEFAULT NULL COMMENT 'เก็บข้อมูล Time Intervals ของหาดใหญ่ (JSON)',
  `refer_out` varchar(10) DEFAULT NULL COMMENT 'Refer Out (Yes/No)',
  `refer_detail` text DEFAULT NULL COMMENT 'รายละเอียด Refer Out 1 และ 2 (JSON)',
  `pre_asa` varchar(10) DEFAULT NULL,
  `hosp_asa` varchar(10) DEFAULT NULL,
  `pre_p2y12` varchar(10) DEFAULT NULL,
  `hosp_p2y12` varchar(10) DEFAULT NULL,
  `p2y12_type` varchar(20) DEFAULT NULL,
  `asa_admin` varchar(5) DEFAULT NULL,
  `asa_dis` varchar(5) DEFAULT NULL,
  `p2y12_admin` varchar(5) DEFAULT NULL,
  `p2y12_dis` varchar(5) DEFAULT NULL,
  `p2y12_specific` varchar(20) DEFAULT NULL,
  `bb_admin` varchar(5) DEFAULT NULL,
  `bb_dis` varchar(5) DEFAULT NULL,
  `acei_admin` varchar(5) DEFAULT NULL,
  `acei_dis` varchar(5) DEFAULT NULL,
  `statin_admin` varchar(5) DEFAULT NULL,
  `statin_dis` varchar(5) DEFAULT NULL,
  `no_asa_reason` varchar(255) DEFAULT NULL,
  `no_p2y12_reason` varchar(255) DEFAULT NULL,
  `no_bb_reason` varchar(255) DEFAULT NULL,
  `no_acei_reason` varchar(255) DEFAULT NULL,
  `no_statin_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_medications`
--

INSERT INTO `patient_medications` (`patient_id`, `fibrinolytic_drug`, `asa`, `asa_reason`, `p2y12`, `p2y12_reason`, `fibrinolytic_type`, `hospital_date_rpth`, `hospital_time_start_rpth`, `hospital_time_end_rpth`, `rpth_door_to_needle`, `rpth_stemi_dx_to_needle`, `complications`, `complications_detail`, `created_at`, `updated_at`, `rpth_onset_to_fmc`, `rpth_fmc_to_needle`, `rpth_ekg_to_dx`, `rpth_onset_to_needle`, `sk_opened`, `hatyai_place`, `hatyai_kpi_data`, `refer_out`, `refer_detail`, `pre_asa`, `hosp_asa`, `pre_p2y12`, `hosp_p2y12`, `p2y12_type`, `asa_admin`, `asa_dis`, `p2y12_admin`, `p2y12_dis`, `p2y12_specific`, `bb_admin`, `bb_dis`, `acei_admin`, `acei_dis`, `statin_admin`, `statin_dis`, `no_asa_reason`, `no_p2y12_reason`, `no_bb_reason`, `no_acei_reason`, `no_statin_reason`) VALUES
(50, NULL, '', '', '', '', 'SK (Streptokinase),TNK (Tenecteplase),rtPA', NULL, NULL, NULL, 0, NULL, 'Yes', '', '2026-01-15 03:20:10', '2026-01-15 03:20:10', NULL, NULL, NULL, NULL, '', '', '{\"er\":{\"date\":\"\",\"start\":\"\",\"end\":\"\",\"detail\":\"\"},\"ccu\":{\"date\":\"\",\"start\":\"\",\"end\":\"\",\"detail\":\"\"},\"icu\":{\"date\":\"\",\"start\":\"\",\"end\":\"\",\"detail\":\"\"},\"ward\":{\"date\":\"\",\"start\":\"\",\"end\":\"\",\"detail\":\"\"},\"kpi\":{\"onset_to_fmc\":\"\",\"door_to_needle\":\"\",\"fmc_to_needle\":\"\",\"ekg_to_dx\":\"\",\"onset_to_needle\":\"\",\"stemi_dx_to_needle\":\"\"}}', '', '{\"ref1\":{\"hospital\":\"\",\"province\":\"\",\"date\":\"\",\"time\":\"\"},\"ref2\":{\"hospital\":\"\",\"province\":\"\",\"date\":\"\",\"time\":\"\"}}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(51, NULL, '', '', '', '', '', NULL, NULL, NULL, 0, NULL, '', '', '2026-02-03 06:45:36', '2026-02-03 06:45:36', NULL, NULL, NULL, NULL, '', '', '{\"er\":{\"date\":\"\",\"start\":\"\",\"end\":\"\",\"detail\":\"\"},\"ccu\":{\"date\":\"\",\"start\":\"\",\"end\":\"\",\"detail\":\"\"},\"icu\":{\"date\":\"\",\"start\":\"\",\"end\":\"\",\"detail\":\"\"},\"ward\":{\"date\":\"\",\"start\":\"\",\"end\":\"\",\"detail\":\"\"},\"kpi\":{\"onset_to_fmc\":\"\",\"door_to_needle\":\"\",\"fmc_to_needle\":\"\",\"ekg_to_dx\":\"\",\"onset_to_needle\":\"\",\"stemi_dx_to_needle\":\"\"}}', '', '{\"ref1\":{\"hospital\":\"\",\"province\":\"\",\"date\":\"\",\"time\":\"\"},\"ref2\":{\"hospital\":\"\",\"province\":\"\",\"date\":\"\",\"time\":\"\"}}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `patient_risk_factors`
--

CREATE TABLE `patient_risk_factors` (
  `patient_id` int(11) NOT NULL,
  `referral_type` varchar(20) DEFAULT NULL,
  `ward` varchar(100) DEFAULT NULL,
  `refer_from_hosp1` varchar(255) DEFAULT NULL,
  `refer_from_province1` varchar(100) DEFAULT NULL,
  `refer_from_hosp2` varchar(255) DEFAULT NULL,
  `refer_from_province2` varchar(100) DEFAULT NULL,
  `ds_refer1_hosp` varchar(255) DEFAULT NULL,
  `fibrinolytic_status` enum('NO','YES') DEFAULT NULL,
  `fibrinolytic_refer_option` varchar(255) DEFAULT NULL,
  `prior_mi` varchar(20) DEFAULT NULL,
  `prior_hf` varchar(20) DEFAULT NULL,
  `prior_pci` varchar(20) DEFAULT NULL,
  `prior_cabg` varchar(20) DEFAULT NULL,
  `diabetes` varchar(20) DEFAULT NULL,
  `fbs` decimal(5,2) DEFAULT NULL,
  `hba1c` decimal(5,2) DEFAULT NULL,
  `dyslipidemia` varchar(20) DEFAULT NULL,
  `chol_check` varchar(100) DEFAULT NULL,
  `chol_value` decimal(5,2) DEFAULT NULL,
  `tg_value` decimal(5,2) DEFAULT NULL,
  `hdl_value` decimal(5,2) DEFAULT NULL,
  `ldl_value` decimal(5,2) DEFAULT NULL,
  `hypertension` varchar(20) DEFAULT NULL,
  `smoker` varchar(20) DEFAULT NULL,
  `family_history` varchar(20) DEFAULT NULL,
  `cerebrovascular` varchar(20) DEFAULT NULL,
  `peripheral` varchar(20) DEFAULT NULL,
  `cope` varchar(20) DEFAULT NULL,
  `ckd` varchar(20) DEFAULT NULL,
  `dialysis` varchar(20) DEFAULT NULL,
  `dialysis_type` varchar(20) DEFAULT NULL,
  `other_comorbidity` text DEFAULT NULL,
  `allergy` text DEFAULT NULL,
  `food_allergy` text DEFAULT NULL,
  `hb` decimal(5,2) DEFAULT NULL,
  `platelet` decimal(8,2) DEFAULT NULL,
  `inr` decimal(5,2) DEFAULT NULL,
  `pt` decimal(5,2) DEFAULT NULL,
  `ptt` decimal(5,2) DEFAULT NULL,
  `k` decimal(5,2) DEFAULT NULL,
  `cr` decimal(5,2) DEFAULT NULL,
  `gfr` decimal(5,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_risk_factors`
--

INSERT INTO `patient_risk_factors` (`patient_id`, `referral_type`, `ward`, `refer_from_hosp1`, `refer_from_province1`, `refer_from_hosp2`, `refer_from_province2`, `ds_refer1_hosp`, `fibrinolytic_status`, `fibrinolytic_refer_option`, `prior_mi`, `prior_hf`, `prior_pci`, `prior_cabg`, `diabetes`, `fbs`, `hba1c`, `dyslipidemia`, `chol_check`, `chol_value`, `tg_value`, `hdl_value`, `ldl_value`, `hypertension`, `smoker`, `family_history`, `cerebrovascular`, `peripheral`, `cope`, `ckd`, `dialysis`, `dialysis_type`, `other_comorbidity`, `allergy`, `food_allergy`, `hb`, `platelet`, `inr`, `pt`, `ptt`, `k`, `cr`, `gfr`, `updated_at`) VALUES
(50, 'Referral', '', '', '', '', '', NULL, 'YES', '', 'YES', 'YES', 'YES', 'YES', '', NULL, NULL, 'YES', 'CHOL,TG,HDL,LDL', NULL, NULL, NULL, NULL, 'YES', 'YES', 'YES', 'YES', 'YES', 'YES', 'YES', 'YES', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-15 03:19:43'),
(51, '', '', '', '', '', '', NULL, 'YES', '', '', '', '', 'YES', '', NULL, NULL, '', '', NULL, NULL, NULL, NULL, '', '', '', '', '', '', 'YES', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, '2026-02-03 06:45:26');

-- --------------------------------------------------------

--
-- Table structure for table `symptoms_diagnosis`
--

CREATE TABLE `symptoms_diagnosis` (
  `patient_id` int(11) NOT NULL,
  `onset_date` date DEFAULT NULL,
  `onset_time` time DEFAULT NULL,
  `onset_unknown` tinyint(1) DEFAULT 0,
  `fmc` varchar(20) DEFAULT NULL,
  `opd_date` date DEFAULT NULL,
  `opd_clinic` varchar(255) DEFAULT NULL,
  `ems_date` date DEFAULT NULL,
  `ems_time` time DEFAULT NULL,
  `dx_date` date DEFAULT NULL,
  `dx_time` time DEFAULT NULL,
  `hospital_date_rpch` date DEFAULT NULL,
  `hospital_time_rpch` time DEFAULT NULL,
  `pain_score_rpch` int(11) DEFAULT NULL,
  `hospital_date_rpth` date DEFAULT NULL,
  `hospital_time_rpth` time DEFAULT NULL,
  `pain_score_rpth` int(11) DEFAULT NULL,
  `hospital_date_hatyai` date DEFAULT NULL,
  `hospital_time_hatyai` time DEFAULT NULL,
  `pain_score_hatyai` int(11) DEFAULT NULL,
  `first_ekg_date` date DEFAULT NULL,
  `first_ekg_time` time DEFAULT NULL,
  `door_to_ekg_time` int(11) DEFAULT NULL,
  `diag_ekg_date` date DEFAULT NULL,
  `diag_ekg_time` time DEFAULT NULL,
  `ekg_interpretation` varchar(255) DEFAULT NULL,
  `diagnosis_btn` varchar(20) DEFAULT NULL,
  `initial_diagnosis_main` varchar(50) DEFAULT NULL,
  `stemi_sub` text DEFAULT NULL,
  `area_infarction` text DEFAULT NULL,
  `angina` varchar(20) DEFAULT NULL,
  `dyspnea_type` varchar(20) DEFAULT NULL,
  `syncope` varchar(20) DEFAULT NULL,
  `cardiac_arrest` varchar(20) DEFAULT NULL,
  `heart_failure_value` varchar(10) DEFAULT NULL,
  `on_ett_value` varchar(10) DEFAULT NULL,
  `killip_class_value` varchar(10) DEFAULT NULL,
  `arrhythmia_value` varchar(10) DEFAULT NULL,
  `arrhythmia_main_type` varchar(50) DEFAULT NULL,
  `cpr_value` varchar(10) DEFAULT NULL,
  `cpr_detail` varchar(255) DEFAULT NULL,
  `death_value` varchar(10) DEFAULT NULL,
  `dead_status_value` varchar(50) DEFAULT NULL,
  `grace_score` int(11) DEFAULT NULL,
  `hr` int(11) DEFAULT NULL,
  `bp_systolic` int(11) DEFAULT NULL,
  `bp_diastolic` int(11) DEFAULT NULL,
  `creatinine` decimal(5,2) DEFAULT NULL,
  `gfr` decimal(5,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `symptoms_diagnosis`
--

INSERT INTO `symptoms_diagnosis` (`patient_id`, `onset_date`, `onset_time`, `onset_unknown`, `fmc`, `opd_date`, `opd_clinic`, `ems_date`, `ems_time`, `dx_date`, `dx_time`, `hospital_date_rpch`, `hospital_time_rpch`, `pain_score_rpch`, `hospital_date_rpth`, `hospital_time_rpth`, `pain_score_rpth`, `hospital_date_hatyai`, `hospital_time_hatyai`, `pain_score_hatyai`, `first_ekg_date`, `first_ekg_time`, `door_to_ekg_time`, `diag_ekg_date`, `diag_ekg_time`, `ekg_interpretation`, `diagnosis_btn`, `initial_diagnosis_main`, `stemi_sub`, `area_infarction`, `angina`, `dyspnea_type`, `syncope`, `cardiac_arrest`, `heart_failure_value`, `on_ett_value`, `killip_class_value`, `arrhythmia_value`, `arrhythmia_main_type`, `cpr_value`, `cpr_detail`, `death_value`, `dead_status_value`, `grace_score`, `hr`, `bp_systolic`, `bp_diastolic`, `creatinine`, `gfr`, `updated_at`) VALUES
(50, NULL, NULL, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0, 0, 0, 0, 0.00, 0.00, '2026-01-15 03:20:13'),
(51, NULL, NULL, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'STEMI: STEMI, anterior or LBBB, STEMI, other sites, STEMI, unspecified sites', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0, 0, 0, 0, 0.00, 0.00, '2026-02-03 06:45:31');

-- --------------------------------------------------------

--
-- Table structure for table `treatment_results`
--

CREATE TABLE `treatment_results` (
  `patient_id` int(11) NOT NULL,
  `inhospital_echo` varchar(10) DEFAULT NULL,
  `ef_value` decimal(4,1) DEFAULT NULL,
  `echo_additional` text DEFAULT NULL,
  `inhospital_comp` varchar(10) DEFAULT NULL,
  `heart_failure` varchar(10) DEFAULT NULL,
  `on_ventilator` varchar(10) DEFAULT NULL,
  `cardiogenic_shock` varchar(10) DEFAULT NULL,
  `stroke` varchar(10) DEFAULT NULL,
  `stroke_time` varchar(50) DEFAULT NULL,
  `stroke_type` varchar(50) DEFAULT NULL,
  `renal_failure` varchar(10) DEFAULT NULL,
  `dialysis_type` varchar(50) DEFAULT NULL,
  `dialysis_detail` text DEFAULT NULL,
  `major_bleeding` varchar(10) DEFAULT NULL,
  `blood_transfusion` varchar(10) DEFAULT NULL,
  `arrhythmia` varchar(10) DEFAULT NULL,
  `vtvf` varchar(10) DEFAULT NULL,
  `heart_block` varchar(10) DEFAULT NULL,
  `arrhythmia_detail` text DEFAULT NULL,
  `mechanical_comp` varchar(10) DEFAULT NULL,
  `mechanical_type` varchar(50) DEFAULT NULL,
  `other_complication` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mechanical_other_text` varchar(255) DEFAULT NULL,
  `dialysis_other_text` varchar(255) DEFAULT NULL,
  `complication_death` varchar(5) DEFAULT NULL,
  `killip_class` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treatment_results`
--

INSERT INTO `treatment_results` (`patient_id`, `inhospital_echo`, `ef_value`, `echo_additional`, `inhospital_comp`, `heart_failure`, `on_ventilator`, `cardiogenic_shock`, `stroke`, `stroke_time`, `stroke_type`, `renal_failure`, `dialysis_type`, `dialysis_detail`, `major_bleeding`, `blood_transfusion`, `arrhythmia`, `vtvf`, `heart_block`, `arrhythmia_detail`, `mechanical_comp`, `mechanical_type`, `other_complication`, `updated_at`, `mechanical_other_text`, `dialysis_other_text`, `complication_death`, `killip_class`) VALUES
(50, NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-01-14 09:19:58', NULL, NULL, NULL, NULL),
(51, '', 0.0, '', '', '', '', '', '', '', '', '', '', '', 'Yes', '', 'Yes', '', '', '', 'Yes', '', '', '2026-01-27 08:47:32', NULL, NULL, '', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cardiac_cath`
--
ALTER TABLE `cardiac_cath`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `medication_reconciliation`
--
ALTER TABLE `medication_reconciliation`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patient_consults`
--
ALTER TABLE `patient_consults`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `patient_discharges`
--
ALTER TABLE `patient_discharges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_hn` (`patient_id`);

--
-- Indexes for table `patient_medications`
--
ALTER TABLE `patient_medications`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `patient_risk_factors`
--
ALTER TABLE `patient_risk_factors`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `symptoms_diagnosis`
--
ALTER TABLE `symptoms_diagnosis`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `treatment_results`
--
ALTER TABLE `treatment_results`
  ADD PRIMARY KEY (`patient_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `patient_discharges`
--
ALTER TABLE `patient_discharges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cardiac_cath`
--
ALTER TABLE `cardiac_cath`
  ADD CONSTRAINT `cardiac_cath_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medication_reconciliation`
--
ALTER TABLE `medication_reconciliation`
  ADD CONSTRAINT `fk_recon_patients` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_consults`
--
ALTER TABLE `patient_consults`
  ADD CONSTRAINT `patient_consults_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_medications`
--
ALTER TABLE `patient_medications`
  ADD CONSTRAINT `patient_medications_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_risk_factors`
--
ALTER TABLE `patient_risk_factors`
  ADD CONSTRAINT `patient_risk_factors_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `symptoms_diagnosis`
--
ALTER TABLE `symptoms_diagnosis`
  ADD CONSTRAINT `symptoms_diagnosis_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `treatment_results`
--
ALTER TABLE `treatment_results`
  ADD CONSTRAINT `treatment_results_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
