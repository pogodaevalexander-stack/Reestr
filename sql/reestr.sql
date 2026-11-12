-- ============================================================
--  «Реестр» — схема базы без адресов и обращений-примеров
--  Импорт: PhpMyAdmin → «Импорт» → reestr.sql → Выполнить
-- ============================================================
CREATE DATABASE IF NOT EXISTS `reestr` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `reestr`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `outages`;
DROP TABLE IF EXISTS `appeals`;
DROP TABLE IF EXISTS `houses`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS `accounts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login`         VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_login` (`login`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица аккаунтов остаётся пустой. Создайте первого пользователя через add_account.bat.

CREATE TABLE `houses` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `address`    VARCHAR(255) NOT NULL,
  `lat`        DECIMAL(10,7) NULL,
  `lng`        DECIMAL(10,7) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_address` (`address`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `outages` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `house_id`    INT UNSIGNED NOT NULL,
  `type`        ENUM('hot_water','heating') NOT NULL,
  `reason`      VARCHAR(255) NULL,
  `start_at`    DATETIME NOT NULL,
  `planned_end` DATETIME NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_house` (`house_id`),
  CONSTRAINT `fk_outages_house` FOREIGN KEY (`house_id`)
    REFERENCES `houses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appeals` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `address`    VARCHAR(255) NOT NULL,
  `fio`        VARCHAR(255) NOT NULL,
  `phone`      VARCHAR(32)  NOT NULL,
  `lat`        DECIMAL(10,7) NULL,
  `lng`        DECIMAL(10,7) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблицы создаются пустыми. Добавляйте адреса и обращения через приложение.
