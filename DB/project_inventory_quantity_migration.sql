START TRANSACTION;

CREATE TABLE IF NOT EXISTS `project_inventory_deployments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `deployed_by` int(11) NOT NULL,
  `deployed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `returned_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_inventory_deployments_project` (`project_id`),
  KEY `idx_project_inventory_deployments_inventory` (`inventory_id`),
  KEY `idx_project_inventory_deployments_returned` (`returned_at`),
  KEY `idx_project_inventory_deployments_user` (`deployed_by`),
  CONSTRAINT `fk_project_inventory_deployments_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_inventory_deployments_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_inventory_deployments_user` FOREIGN KEY (`deployed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `project_inventory_return_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deployment_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `returned_by` int(11) NOT NULL,
  `returned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_inventory_return_logs_deployment` (`deployment_id`),
  KEY `idx_project_inventory_return_logs_returned_at` (`returned_at`),
  KEY `idx_project_inventory_return_logs_user` (`returned_by`),
  CONSTRAINT `fk_project_inventory_return_logs_deployment` FOREIGN KEY (`deployment_id`) REFERENCES `project_inventory_deployments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_inventory_return_logs_user` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

UPDATE `inventory`
SET `status` = CASE
    WHEN `quantity` <= 0 THEN 'out-of-stock'
    WHEN `min_stock` IS NOT NULL AND `quantity` <= `min_stock` THEN 'low-stock'
    ELSE 'available'
END;

COMMIT;
