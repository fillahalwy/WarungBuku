-- ============================================================
-- db_marketplace — Full Schema (English naming, snake_case)
-- Run this script in phpMyAdmin to recreate all tables.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `db_marketplace`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_marketplace`;

-- Drop old tables if they exist (old Indonesian names)
DROP TABLE IF EXISTS `tb_barang`;
DROP TABLE IF EXISTS `tb_kategori`;
DROP TABLE IF EXISTS `tb_admin`;

-- Drop new tables so we can recreate cleanly
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `admins`;

-- ----------------------------------------------------------
-- 1. admins
-- ----------------------------------------------------------
CREATE TABLE `admins` (
  `id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `name`     VARCHAR(100) NOT NULL,
  `username` VARCHAR(50)  NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone`    VARCHAR(20)  DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
-- 2. categories
-- ----------------------------------------------------------
CREATE TABLE `categories` (
  `id`   INT(11)      NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
-- 3. products
-- ----------------------------------------------------------
CREATE TABLE `products` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `category_id` INT(11)      NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `price`       INT(11)      NOT NULL DEFAULT 0,
  `image`       VARCHAR(255) DEFAULT NULL,
  `description` TEXT         DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_products_category` (`category_id`),
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
-- Seed Data
-- ----------------------------------------------------------
INSERT INTO `admins` (`name`, `username`, `password`, `phone`) VALUES
  ('Administrator', 'admin', MD5('admin'), '081234567890');

INSERT INTO `categories` (`name`) VALUES
  ('Pendidikan & Sejarah'),
  ('Novel & Komik'),
  ('Dongeng & Fiksi'),
  ('Anak-anak');
