/*
 Navicat Premium Data Transfer

 Source Server         : 106.14.78.28
 Source Server Type    : MySQL
 Source Server Version : 50733
 Source Host           : 106.14.78.28:3306
 Source Schema         : location

 Target Server Type    : MySQL
 Target Server Version : 50733
 File Encoding         : 65001

 Date: 11/03/2021 17:03:01
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE if exists `location`;
CREATE DATABASE `location` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `location`;

-- ----------------------------
-- Table structure for basic_info
-- ----------------------------
DROP TABLE IF EXISTS `basic_info`;
CREATE TABLE `basic_info`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sw_v` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `index` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `time` datetime(0) NULL DEFAULT NULL,
  `board_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `uCnt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `labelCnt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `humCnt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `doorCnt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `exInfo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `runMode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `airSpeed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `humiture` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `drSwitch` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `label_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of basic_info
-- ----------------------------

SET FOREIGN_KEY_CHECKS = 1;
