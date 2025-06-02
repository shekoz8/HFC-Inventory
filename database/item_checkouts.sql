-- Create item_checkouts table if it doesn't exist
CREATE TABLE IF NOT EXISTS `item_checkouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `checkout_date` timestamp NULL DEFAULT NULL,
  `expected_return_date` timestamp NULL DEFAULT NULL,
  `actual_return_date` timestamp NULL DEFAULT NULL,
  `status` enum('Pending','Checked Out','Returned','Partially Returned') NOT NULL DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `user_id` (`user_id`),
  KEY `item_id` (`item_id`),
  KEY `status` (`status`),
  CONSTRAINT `item_checkouts_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `item_requests` (`id`),
  CONSTRAINT `item_checkouts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `item_checkouts_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
