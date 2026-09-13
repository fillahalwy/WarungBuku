-- ============================================================
-- db_marketplace — Full Schema (English naming, snake_case)
-- Run this script in phpMyAdmin / MariaDB to recreate all tables.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `db_marketplace`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_marketplace`;

-- Drop tables cleanly
DROP TABLE IF EXISTS `books`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- ----------------------------------------------------------
-- 1. users
-- ----------------------------------------------------------
CREATE TABLE `users` (
  `id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `name`     VARCHAR(100) NOT NULL,
  `username` VARCHAR(50)  NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone`    VARCHAR(20)  DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. categories
-- ----------------------------------------------------------
CREATE TABLE `categories` (
  `id`   INT(11)      NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. books (Product Book Management)
-- ----------------------------------------------------------
CREATE TABLE `books` (
  `id`               CHAR(36)       NOT NULL,
  `isbn`             VARCHAR(20)    NOT NULL UNIQUE,
  `title`            VARCHAR(255)   NOT NULL,
  `author`           VARCHAR(150)   NOT NULL,
  `publisher`        VARCHAR(150)   NOT NULL,
  `publication_year` INT(4)         NOT NULL,
  `category`         VARCHAR(100)   NOT NULL,
  `image`            VARCHAR(255)   DEFAULT NULL,
  `price`            DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `stock`            INT(11)        NOT NULL DEFAULT 0,
  `description`      TEXT           DEFAULT NULL,
  `created_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Seed Data
-- ----------------------------------------------------------
INSERT INTO `users` (`name`, `username`, `password`, `phone`) VALUES
  ('Administrator', 'admin', MD5('admin'), '081234567890');

INSERT INTO `categories` (`name`) VALUES
  ('Pendidikan & Sejarah'),
  ('Novel & Komik'),
  ('Dongeng & Fiksi'),
  ('Anak-anak');

INSERT INTO `books` (`id`, `isbn`, `title`, `author`, `publisher`, `publication_year`, `category`, `price`, `stock`, `description`) VALUES
  (UUID(), '978-602-03-8591-4', 'Bumi Manusia', 'Pramoedya Ananta Toer', 'Lentera Dipantara', 2018, 'Pendidikan & Sejarah', 135000.00, 24, 'Karya masterpiece sastra Indonesia mengisahkan pergulatan Minke di masa kolonial Hindia Belanda.'),
  (UUID(), '978-602-06-3317-6', 'Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', 2019, 'Novel & Komik', 89000.00, 15, 'Kisah inspiratif tentang perjuangan sepuluh anak di Belitung dalam menempuh pendidikan.'),
  (UUID(), '978-602-03-2478-4', 'Hujan', 'Tere Liye', 'Gramedia Pustaka Utama', 2016, 'Dongeng & Fiksi', 95000.00, 30, 'Novel fiksi ilmiah tentang persahabatan, cinta, dan teknologi masa depan.'),
  (UUID(), '978-623-00-2180-0', 'Filosofi Teras', 'Henry Manampiring', 'Kompas Buku', 2021, 'Pendidikan & Sejarah', 108000.00, 42, 'Penerapan filsafat Stoisisme kuno untuk mengatasi emosi negatif dan hidup lebih tenang.'),
  (UUID(), '978-602-291-663-5', 'Si Kancil dan Buaya', 'Kak Budi', 'Mizan Anak', 2020, 'Anak-anak', 45000.00, 8, 'Dongeng cerita rakyat fabel mendidik untuk anak-anak dengan ilustrasi warna-warni.'),
  (UUID(), '978-602-03-3294-9', 'Laut Bercerita', 'Leila S. Chudori', 'Kepustakaan Populer Gramedia', 2017, 'Novel & Komik', 115000.00, 0, 'Novel yang mengangkat kisah para aktivis mahasiswa yang hilang pada masa Orde Baru.');
