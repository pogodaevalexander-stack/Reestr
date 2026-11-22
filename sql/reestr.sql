-- ============================================================================
-- РЕЕСТР — единый SQL-файл установки и обновления схемы.
-- Импорт: PhpMyAdmin → Импорт → reestr.sql → Выполнить.
--
-- ВАЖНО: файл НЕ удаляет и НЕ пересоздаёт таблицы с данными.
-- Для новой установки создаёт таблицы; для старой — добавляет недостающие поля,
-- переносит outages.start_at в outages.actual_start и сохраняет записи.
-- Повторный запуск безопасен: уже существующие поля и индексы пропускаются.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `reestr` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `reestr`;

-- 1. Учётные записи: оператор/администратор, отображаемое ФИО, bcrypt-хэш,
--    активность и дата создания.
CREATE TABLE IF NOT EXISTS `accounts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login`         VARCHAR(64) NOT NULL,
  `full_name`     VARCHAR(255) NOT NULL DEFAULT '',
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('operator','admin') NOT NULL DEFAULT 'operator',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_login` (`login`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Реестр адресов домов. Уникальный адрес предотвращает дубли.
CREATE TABLE IF NOT EXISTS `houses` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `address`    VARCHAR(255) NOT NULL,
  `lat`        DECIMAL(10,7) NULL,
  `lng`        DECIMAL(10,7) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_address` (`address`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Отключения: плановое и фактическое начало, причина, плановое восстановление.
CREATE TABLE IF NOT EXISTS `outages` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `house_id`      INT UNSIGNED NOT NULL,
  `type`          ENUM('shutdown','damage','improvement') NOT NULL DEFAULT 'shutdown',
  `detail_type`   ENUM('full_shutdown','supply_without_circulation','damage_without_shutdown') NOT NULL DEFAULT 'full_shutdown',
  `planned_start` DATETIME NULL,
  `reason`        VARCHAR(255) NULL,
  `actual_start`  DATETIME NOT NULL,
  `planned_end`   DATETIME NULL,
  `mkd_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `social_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `operator_name` VARCHAR(255) NOT NULL DEFAULT '',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_house` (`house_id`),
  CONSTRAINT `fk_outages_house` FOREIGN KEY (`house_id`)
    REFERENCES `houses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Обращения: данные заявителя, текст состава, тип, статус, время звонка
--    и ФИО оператора, создавшего запись.
CREATE TABLE IF NOT EXISTS `appeals` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `address`       VARCHAR(255) NOT NULL,
  `fio`           VARCHAR(255) NOT NULL,
  `phone`         VARCHAR(32) NOT NULL,
  `subject`       TEXT NOT NULL,
  `type`          ENUM('no_hot_water','no_heating','information','other','false_call') NOT NULL DEFAULT 'other',
  `status`        ENUM('in_work','completed') NOT NULL DEFAULT 'in_work',
  `call_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `operator_name` VARCHAR(255) NOT NULL DEFAULT '',
  `lat`           DECIMAL(10,7) NULL,
  `lng`           DECIMAL(10,7) NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `closed_at`     DATETIME NULL,
  `closed_by`     VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_call_at` (`call_at`),
  KEY `idx_appeal_status` (`status`),
  KEY `idx_appeal_type` (`type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Журнал действий пользователей для административного аудита.
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` INT UNSIGNED NULL,
  `actor_name` VARCHAR(255) NOT NULL DEFAULT '',
  `action` VARCHAR(64) NOT NULL,
  `entity` VARCHAR(32) NOT NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `details` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_actor` (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Миграция старых установок.
--    Добавляет только отсутствующие поля и индексы. Старые appeals.call_at
--    заполняются из created_at; старое outages.start_at переименовывается
--    в actual_start без потери значений. Старым обращениям задаются пустой
--    subject, тип other и статус in_work.
DELIMITER $$
DROP PROCEDURE IF EXISTS `migrate_reestr_schema`$$
CREATE PROCEDURE `migrate_reestr_schema`()
BEGIN
  DECLARE column_count INT DEFAULT 0;
  DECLARE index_count INT DEFAULT 0;
  DECLARE old_type_count INT DEFAULT 0;

  -- Аккаунты: ФИО и роль добавлялись в разных версиях приложения.
  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'full_name';
  IF column_count = 0 THEN
    ALTER TABLE `accounts` ADD COLUMN `full_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `login`;
  END IF;

  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'role';
  IF column_count = 0 THEN
    ALTER TABLE `accounts` ADD COLUMN `role` ENUM('operator','admin') NOT NULL DEFAULT 'operator' AFTER `password_hash`;
  END IF;

  -- Обращения: время звонка и автор записи. Исторический call_at берётся из created_at.
  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appeals' AND COLUMN_NAME = 'call_at';
  IF column_count = 0 THEN
    ALTER TABLE `appeals` ADD COLUMN `call_at` DATETIME NULL AFTER `phone`;
  END IF;
  UPDATE `appeals` SET `call_at` = `created_at` WHERE `call_at` IS NULL;
  ALTER TABLE `appeals` MODIFY COLUMN `call_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appeals' AND COLUMN_NAME = 'operator_name';
  IF column_count = 0 THEN
    ALTER TABLE `appeals` ADD COLUMN `operator_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `call_at`;
  END IF;

  -- Состав обращения. NULL используется только временно, чтобы заполнить старые строки.
  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appeals' AND COLUMN_NAME = 'subject';
  IF column_count = 0 THEN
    ALTER TABLE `appeals` ADD COLUMN `subject` TEXT NULL AFTER `phone`;
  END IF;
  UPDATE `appeals` SET `subject` = '' WHERE `subject` IS NULL;
  ALTER TABLE `appeals` MODIFY COLUMN `subject` TEXT NOT NULL;

  -- Категория и рабочее состояние обращения.
  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appeals' AND COLUMN_NAME = 'type';
  IF column_count = 0 THEN
    ALTER TABLE `appeals` ADD COLUMN `type` ENUM('no_hot_water','no_heating','information','other','false_call') NOT NULL DEFAULT 'other' AFTER `subject`;
  END IF;

  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appeals' AND COLUMN_NAME = 'status';
  IF column_count = 0 THEN
    ALTER TABLE `appeals` ADD COLUMN `status` ENUM('in_work','completed') NOT NULL DEFAULT 'in_work' AFTER `type`;
  END IF;

  -- Плановое начало отключения и сохранение фактического времени из старого start_at.
  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outages' AND COLUMN_NAME = 'type';
  IF column_count > 0 THEN
    ALTER TABLE `outages` MODIFY COLUMN `type` ENUM('hot_water','heating','shutdown','damage','improvement') NOT NULL DEFAULT 'shutdown';
    UPDATE `outages` SET `type` = 'shutdown' WHERE `type` IN ('hot_water','heating');
    ALTER TABLE `outages` MODIFY COLUMN `type` ENUM('shutdown','damage','improvement') NOT NULL DEFAULT 'shutdown';
  END IF;

  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outages' AND COLUMN_NAME = 'detail_type';
  IF column_count = 0 THEN
    ALTER TABLE `outages` ADD COLUMN `detail_type` ENUM('full_shutdown','supply_without_circulation','damage_without_shutdown') NOT NULL DEFAULT 'full_shutdown' AFTER `type`;
  END IF;

  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outages' AND COLUMN_NAME = 'mkd_count';
  IF column_count = 0 THEN
    ALTER TABLE `outages` ADD COLUMN `mkd_count` INT UNSIGNED NOT NULL DEFAULT 0;
  END IF;
  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outages' AND COLUMN_NAME = 'social_count';
  IF column_count = 0 THEN
    ALTER TABLE `outages` ADD COLUMN `social_count` INT UNSIGNED NOT NULL DEFAULT 0;
  END IF;
  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outages' AND COLUMN_NAME = 'operator_name';
  IF column_count = 0 THEN
    ALTER TABLE `outages` ADD COLUMN `operator_name` VARCHAR(255) NOT NULL DEFAULT '';
  END IF;

  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appeals' AND COLUMN_NAME = 'closed_at';
  IF column_count = 0 THEN
    ALTER TABLE `appeals` ADD COLUMN `closed_at` DATETIME NULL AFTER `updated_at`;
  END IF;
  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appeals' AND COLUMN_NAME = 'closed_by';
  IF column_count = 0 THEN
    ALTER TABLE `appeals` ADD COLUMN `closed_by` VARCHAR(255) NULL AFTER `closed_at`;
  END IF;
  UPDATE `appeals` SET `closed_at` = `updated_at`, `closed_by` = `operator_name`
    WHERE `status` = 'completed' AND `closed_at` IS NULL;

  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outages' AND COLUMN_NAME = 'planned_start';
  IF column_count = 0 THEN
    ALTER TABLE `outages` ADD COLUMN `planned_start` DATETIME NULL AFTER `type`;
  END IF;

  SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outages' AND COLUMN_NAME = 'actual_start';
  IF column_count = 0 THEN
    SELECT COUNT(*) INTO column_count FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outages' AND COLUMN_NAME = 'start_at';
    IF column_count > 0 THEN
      ALTER TABLE `outages` CHANGE COLUMN `start_at` `actual_start` DATETIME NOT NULL;
    END IF;
  END IF;

  -- Индексы для списка по времени звонка, статусу и типу.
  SELECT COUNT(*) INTO index_count FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appeals' AND INDEX_NAME = 'idx_call_at';
  IF index_count = 0 THEN
    ALTER TABLE `appeals` ADD KEY `idx_call_at` (`call_at`);
  END IF;

  SELECT COUNT(*) INTO index_count FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appeals' AND INDEX_NAME = 'idx_appeal_status';
  IF index_count = 0 THEN
    ALTER TABLE `appeals` ADD KEY `idx_appeal_status` (`status`);
  END IF;

  SELECT COUNT(*) INTO index_count FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appeals' AND INDEX_NAME = 'idx_appeal_type';
  IF index_count = 0 THEN
    ALTER TABLE `appeals` ADD KEY `idx_appeal_type` (`type`);
  END IF;
END$$
CALL `migrate_reestr_schema`()$$
DROP PROCEDURE `migrate_reestr_schema`$$
DELIMITER ;

-- Для существующей базы назначьте администратора вручную, если его ещё нет:
-- UPDATE `accounts` SET `role` = 'admin' WHERE `login` = 'ВАШ_ЛОГИН_АДМИНИСТРАТОРА';
-- Скрипт не выдаёт права администратора автоматически, чтобы не повысить случайный аккаунт.

-- Новая база и обновлённая схема создаются без тестовых записей.
