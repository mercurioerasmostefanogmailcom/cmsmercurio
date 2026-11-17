-- MercurioCMS: schema pagine e categorie.
-- Il prefisso viene sostituito dall'installer del modulo.
CREATE TABLE IF NOT EXISTS `{{prefix}}pages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(220) NOT NULL,
  `slug` VARCHAR(240) NOT NULL,
  `content` MEDIUMTEXT NOT NULL,
  `excerpt` TEXT NULL,
  `status` ENUM('draft','published','hidden') NOT NULL DEFAULT 'draft',
  `template` VARCHAR(40) NOT NULL DEFAULT 'standard',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `published_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_status_updated` (`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `landing_page_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}page_categories` (
  `page_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`page_id`,`category_id`),
  CONSTRAINT `{{prefix}}fk_pc_page` FOREIGN KEY (`page_id`) REFERENCES `{{prefix}}pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{prefix}}fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `{{prefix}}categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
