CREATE DATABASE IF NOT EXISTS `feedbackflow_master` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `feedbackflow_master`;

CREATE TABLE IF NOT EXISTS `ff_super_admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `ff_super_admins` (`name`,`email`,`password`,`is_active`)
SELECT 'Super Admin', 'admin@rakesh', '$2y$10$e0NR9Vd1Ik5dUvNbzHjn/eFQki71RJKVQ1BM8DT6vKrrf5gYv7FpK', 1
WHERE NOT EXISTS (
  SELECT 1 FROM `ff_super_admins` WHERE `email` = 'admin@rakesh'
);
