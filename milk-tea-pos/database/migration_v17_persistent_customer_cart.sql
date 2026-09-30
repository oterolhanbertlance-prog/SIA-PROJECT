USE `milk_tea_pos`;
CREATE TABLE IF NOT EXISTS customer_cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  sugar_level VARCHAR(40) NOT NULL DEFAULT '50%',
  ice_level VARCHAR(40) NOT NULL DEFAULT 'Regular Ice',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_cart_variant (user_id, product_id, sugar_level, ice_level),
  KEY idx_customer_cart_user (user_id),
  KEY idx_customer_cart_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
