-- Create inventory_items table if it doesn't exist
CREATE TABLE IF NOT EXISTS `inventory_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some initial data if the table is empty
INSERT INTO `inventory_items` (`name`, `category`, `quantity`)
SELECT 'Bible', 'Books', 50
WHERE NOT EXISTS (SELECT 1 FROM `inventory_items` LIMIT 1);

INSERT INTO `inventory_items` (`name`, `category`, `quantity`)
SELECT 'Microphone', 'Electronics', 10
WHERE NOT EXISTS (SELECT 1 FROM `inventory_items` WHERE `id` = 2);

INSERT INTO `inventory_items` (`name`, `category`, `quantity`)
SELECT 'Communion Cup', 'Worship', 200
WHERE NOT EXISTS (SELECT 1 FROM `inventory_items` WHERE `id` = 3);

INSERT INTO `inventory_items` (`name`, `category`, `quantity`)
SELECT 'Hymn Book', 'Books', 30
WHERE NOT EXISTS (SELECT 1 FROM `inventory_items` WHERE `id` = 4);
