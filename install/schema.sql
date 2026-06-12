-- SmartTV CMS database schema
-- Engine: InnoDB, charset: utf8mb4
-- This file is executed by the installation wizard.

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ----------------------------------------------------------------------------
-- Roles
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(80) NOT NULL,
    `slug`        VARCHAR(80) NOT NULL,
    `permissions` LONGTEXT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Administrators
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id`       INT UNSIGNED NULL,
    `name`          VARCHAR(120) NOT NULL,
    `email`         VARCHAR(160) NOT NULL,
    `username`      VARCHAR(80) NOT NULL,
    `password`      VARCHAR(255) NOT NULL,
    `avatar`        VARCHAR(255) NULL,
    `status`        TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME NULL,
    `last_login_ip` VARCHAR(64) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `admins_username_unique` (`username`),
    UNIQUE KEY `admins_email_unique` (`email`),
    KEY `admins_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Site users
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(120) NOT NULL,
    `email`          VARCHAR(160) NOT NULL,
    `username`       VARCHAR(80) NOT NULL,
    `password`       VARCHAR(255) NOT NULL,
    `avatar`         VARCHAR(255) NULL,
    `status`         TINYINT(1) NOT NULL DEFAULT 1,
    `remember_token` VARCHAR(255) NULL,
    `reset_token`    VARCHAR(255) NULL,
    `reset_expires`  DATETIME NULL,
    `last_login_at`  DATETIME NULL,
    `last_login_ip`  VARCHAR(64) NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_username_unique` (`username`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Categories
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `slug`       VARCHAR(140) NOT NULL,
    `icon`       VARCHAR(120) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Channels
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `channels` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number`         INT NOT NULL,
    `name`           VARCHAR(160) NOT NULL,
    `slug`           VARCHAR(180) NOT NULL,
    `category_id`    INT UNSIGNED NULL,
    `country`        VARCHAR(80) NULL,
    `logo`           VARCHAR(255) NULL,
    `cover`          VARCHAR(255) NULL,
    `description`    TEXT NULL,
    `stream_url`     TEXT NOT NULL,
    `backup_streams` LONGTEXT NULL,            -- JSON array of backup URLs
    `tags`           VARCHAR(255) NULL,
    `channel_group`  VARCHAR(120) NULL,
    `quality`        VARCHAR(16) NOT NULL DEFAULT 'HD',  -- SD | HD | FHD | 4K
    `is_featured`    TINYINT(1) NOT NULL DEFAULT 0,
    `is_locked`      TINYINT(1) NOT NULL DEFAULT 0,
    `status`         TINYINT(1) NOT NULL DEFAULT 1,
    `views`          INT UNSIGNED NOT NULL DEFAULT 0,
    `sort_order`     INT NOT NULL DEFAULT 0,
    `last_status`    VARCHAR(16) NULL,         -- online | offline | unknown
    `last_checked`   DATETIME NULL,
    `response_ms`    INT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `channels_number_unique` (`number`),
    KEY `channels_category` (`category_id`),
    KEY `channels_featured` (`is_featured`),
    KEY `channels_status` (`status`),
    FULLTEXT KEY `channels_search` (`name`, `description`, `tags`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Favorites
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `favorites` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `channel_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `favorites_unique` (`user_id`, `channel_id`),
    KEY `favorites_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Watch history / continue watching
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `watch_history` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NULL,
    `session_key` VARCHAR(64) NULL,
    `channel_id`  INT UNSIGNED NOT NULL,
    `position`    INT NOT NULL DEFAULT 0,
    `duration`    INT NOT NULL DEFAULT 0,
    `watched_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `history_user` (`user_id`),
    KEY `history_channel` (`channel_id`),
    KEY `history_session` (`session_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Login history / devices
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_history` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(64) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `login_history_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Settings (key/value)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`   VARCHAR(120) NOT NULL,
    `value`  LONGTEXT NULL,
    `autoload` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `settings_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Advertisements
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ads` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(160) NOT NULL,
    `placement`  VARCHAR(60) NOT NULL,   -- header | sidebar | player_pre | popunder | footer
    `type`       VARCHAR(30) NOT NULL DEFAULT 'html', -- html | image | script
    `content`    LONGTEXT NULL,
    `image`      VARCHAR(255) NULL,
    `link`       VARCHAR(255) NULL,
    `status`     TINYINT(1) NOT NULL DEFAULT 1,
    `starts_at`  DATETIME NULL,
    `ends_at`    DATETIME NULL,
    `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
    `clicks`     INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ads_placement` (`placement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Static pages
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pages` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(180) NOT NULL,
    `slug`       VARCHAR(200) NOT NULL,
    `content`    LONGTEXT NULL,
    `meta_title` VARCHAR(200) NULL,
    `meta_desc`  VARCHAR(255) NULL,
    `status`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `pages_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Menus
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menus` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `label`      VARCHAR(120) NOT NULL,
    `url`        VARCHAR(255) NOT NULL,
    `location`   VARCHAR(60) NOT NULL DEFAULT 'header', -- header | footer
    `parent_id`  INT UNSIGNED NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status`     TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `menus_location` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Logs (activity / audit / error)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`       VARCHAR(30) NOT NULL DEFAULT 'activity', -- activity | audit | error
    `admin_id`   INT UNSIGNED NULL,
    `action`     VARCHAR(120) NULL,
    `entity`     VARCHAR(80) NULL,
    `entity_id`  INT UNSIGNED NULL,
    `message`    TEXT NULL,
    `context`    LONGTEXT NULL,
    `ip_address` VARCHAR(64) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `logs_type` (`type`),
    KEY `logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- IP blocks
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ip_blocks` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(64) NOT NULL,
    `reason`     VARCHAR(255) NULL,
    `expires_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ip_blocks_ip_unique` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Online presence (concurrent viewers)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `online_users` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_key` VARCHAR(64) NOT NULL,
    `user_id`     INT UNSIGNED NULL,
    `channel_id`  INT UNSIGNED NULL,
    `ip_address`  VARCHAR(64) NULL,
    `last_seen`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `online_session_unique` (`session_key`),
    KEY `online_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
