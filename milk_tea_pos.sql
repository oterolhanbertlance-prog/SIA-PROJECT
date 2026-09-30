-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2026 at 11:21 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `milk_tea_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `branch_code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `manager_name` varchar(150) DEFAULT NULL,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 50.00,
  `delivery_radius_km` decimal(8,2) NOT NULL DEFAULT 8.00,
  `delivery_eta_minutes` int(11) NOT NULL DEFAULT 30,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `branch_code`, `name`, `address`, `city`, `latitude`, `longitude`, `phone`, `manager_name`, `delivery_fee`, `delivery_radius_km`, `delivery_eta_minutes`, `status`, `created_at`, `updated_at`) VALUES
(3, 'BR-CAV-01', 'Cavite Branch', 'Emilio Aguinaldo Highway, Brgy. Anabu I-A', 'Imus, Cavite', 14.4297000, 120.9367000, NULL, NULL, 50.00, 8.00, 30, 'active', '2026-09-07 05:47:02', '2026-09-21 14:59:00'),
(4, 'BR-BTG-01', 'Batangas Branch', 'P. Burgos Street, Brgy. Poblacion', 'Batangas City', 13.7565000, 121.0583000, NULL, NULL, 50.00, 8.00, 30, 'active', '2026-09-07 05:47:02', '2026-09-21 14:59:00'),
(5, 'BR-MAIN-HQ', 'Main Branch / Head Office', '123 Talisay Street, Brgy. Centro', 'Cebu City', 10.3157000, 123.8854000, NULL, NULL, 50.00, 8.00, 30, 'active', '2026-09-08 12:28:09', '2026-09-21 14:59:00'),
(6, 'BR-BH-02', 'North Branch', '456 Mandaue Road, Brgy. Looc', 'Mandaue City', 10.3237000, 123.9223000, NULL, NULL, 50.00, 8.00, 30, 'active', '2026-09-08 12:28:09', '2026-09-21 14:59:00');

-- --------------------------------------------------------

--
-- Table structure for table `cash_fund`
--

CREATE TABLE `cash_fund` (
  `id` int(11) NOT NULL DEFAULT 1,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cash_fund`
--

INSERT INTO `cash_fund` (`id`, `balance`, `updated_at`) VALUES
(1, 1634.99, '2026-09-21 14:58:47');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(2, 'Fruit Tea'),
(4, 'Add-ons'),
(5, 'Milk Tea'),
(7, 'Smoothies');

-- --------------------------------------------------------

--
-- Table structure for table `customer_cart_items`
--

CREATE TABLE `customer_cart_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `sugar_level` varchar(40) NOT NULL DEFAULT '50%',
  `ice_level` varchar(40) NOT NULL DEFAULT 'Regular Ice',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `selected_size` varchar(80) DEFAULT NULL,
  `toppings` text DEFAULT NULL,
  `variant_key` char(64) DEFAULT NULL,
  `size_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `topping_details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_riders`
--

CREATE TABLE `delivery_riders` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `vehicle` varchar(100) DEFAULT NULL,
  `plate_no` varchar(30) DEFAULT NULL,
  `status` enum('available','on_delivery','off_duty') NOT NULL DEFAULT 'available',
  `branch_id` int(11) DEFAULT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_zones`
--

CREATE TABLE `delivery_zones` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `coverage` text DEFAULT NULL,
  `base_fee` decimal(10,2) NOT NULL DEFAULT 50.00,
  `extra_km_fee` decimal(10,2) NOT NULL DEFAULT 10.00,
  `est_minutes` int(11) NOT NULL DEFAULT 30,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ingredients`
--

CREATE TABLE `ingredients` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `stock_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `low_stock_threshold` decimal(10,2) NOT NULL DEFAULT 5.00,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `addon_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_addon` tinyint(1) NOT NULL DEFAULT 0,
  `addon_serving_qty` decimal(10,2) NOT NULL DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ingredients`
--

INSERT INTO `ingredients` (`id`, `name`, `unit`, `stock_quantity`, `low_stock_threshold`, `is_available`, `created_at`, `updated_at`, `addon_price`, `is_addon`, `addon_serving_qty`) VALUES
(1, 'Tapioca Pearls', 'pcs', 0.00, 5.00, 0, '2026-07-14 14:46:17', '2026-09-21 07:10:07', 10.00, 1, 1.00),
(2, 'Pudding', 'pcs', 0.00, 5.00, 0, '2026-07-15 03:21:30', '2026-07-15 05:16:19', 0.00, 0, 1.00),
(3, 'Brown Sugar Syrup', 'liters', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00),
(4, 'Taro Powder', 'kg', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00),
(5, 'Matcha Powder', 'kg', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00),
(6, 'Thai Tea Mix', 'kg', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00),
(7, 'Jasmine Tea Leaves', 'kg', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00),
(8, 'Fresh Milk', 'liters', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00),
(9, 'Non-Dairy Creamer', 'kg', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00),
(10, 'Tapioca Pearls (raw)', 'kg', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00),
(11, 'Grass Jelly (raw)', 'kg', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00),
(12, 'Pudding Cups', 'pcs', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00),
(13, '16oz Cups', 'pcs', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00),
(14, 'Straws', 'pcs', 25.00, 5.00, 1, '2026-07-22 14:43:27', '2026-09-07 15:12:13', 0.00, 0, 1.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `allow_size` tinyint(1) NOT NULL DEFAULT 0,
  `allow_sugar` tinyint(1) NOT NULL DEFAULT 0,
  `allow_ice` tinyint(1) NOT NULL DEFAULT 0,
  `allow_toppings` tinyint(1) NOT NULL DEFAULT 0,
  `size_options` text DEFAULT NULL,
  `topping_options` text DEFAULT NULL,
  `customization_v23_repaired` tinyint(1) NOT NULL DEFAULT 0,
  `customization_initialized` tinyint(1) NOT NULL DEFAULT 0,
  `customization_v25_repaired` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `price`, `stock`, `image_url`, `allow_size`, `allow_sugar`, `allow_ice`, `allow_toppings`, `size_options`, `topping_options`, `customization_v23_repaired`, `customization_initialized`, `customization_v25_repaired`) VALUES
(1, 5, 'Classic Brown Sugar Milk Tea', 4.99, 49, 'img_6ab1421244d70.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(3, 5, 'Matcha Milk Tea', 60.00, 35, 'img_6ab142ebbdcc0.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(4, 5, 'Thai Milk Tea', 65.00, 45, 'img_6ab1445204b9f.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(5, 5, 'Jasmine Milk Tea', 85.00, 30, 'img_6ab1424bbe8c6.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(6, 5, 'Oolong Milk Tea', 70.00, 28, 'img_6ab14309e3816.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(7, 2, 'Passion Fruit Tea', 75.00, 38, 'img_6ab1436ee0b32.png', 1, 1, 1, 1, 'Regular|0\r\nLarge|20', 'Pearls|15\r\nNata de Coco|15\r\nCoffee Jelly|15', 0, 1, 1),
(8, 2, 'Lychee Fruit Tea', 90.00, 42, 'img_6ab14277dd9ca.png', 1, 1, 1, 1, 'Regular|0\r\nLarge|20', 'Pearls|15\r\nNata de Coco|15\r\nCoffee Jelly|15', 0, 1, 1),
(11, 7, 'Avocado Smoothie', 100.00, 4, 'img_6ab141e953505.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(12, 7, 'Strawberry Smoothie', 70.00, 18, 'img_6ab143de627bd.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(13, 4, 'Tapioca Pearls', 10.00, 100, 'img_6ab1443807f91.jpg', 0, 0, 0, 0, NULL, NULL, 0, 1, 0),
(14, 4, 'Grass Jelly', 0.75, 80, 'img_6ab1423234d68.jpg', 0, 0, 0, 0, NULL, NULL, 0, 1, 0),
(15, 4, 'Pudding', 0.75, 90, 'img_6ab143a3d5cc7.webp', 0, 0, 0, 0, NULL, NULL, 0, 1, 0),
(17, 5, 'Chocolate Milk Tea', 75.00, 10, 'img_6ab141fc1095a.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(18, 4, 'Chocolate Pieces', 15.00, 10, 'img_6ab1420471276.png', 0, 0, 0, 0, NULL, NULL, 0, 1, 0),
(19, 4, 'Chocolate', 15.00, 12, 'img_6ab141f2d8033.png', 0, 0, 0, 0, NULL, NULL, 0, 1, 0),
(20, 5, 'Coconut Milk Tea', 60.00, 15, 'img_6ab1421dba2d0.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(21, 4, 'Cream', 15.00, 15, 'img_6ab142279113c.png', 0, 0, 0, 0, NULL, NULL, 0, 1, 0),
(22, 2, 'Green Apple Fruit Tea', 85.00, 17, 'img_6ab1423d62ef0.png', 1, 1, 1, 1, 'Regular|0\r\nLarge|20', 'Pearls|15\r\nNata de Coco|15\r\nCoffee Jelly|15', 0, 1, 1),
(23, 2, 'Lemon Fruit Tea', 65.00, 15, 'img_6ab142558fc66.png', 1, 1, 1, 1, 'Regular|0\r\nLarge|20', 'Pearls|15\r\nNata de Coco|15\r\nCoffee Jelly|15', 0, 1, 1),
(24, 4, 'Mango Dice', 15.00, 16, 'img_6ab14297d5389.png', 0, 0, 0, 0, NULL, NULL, 0, 1, 0),
(25, 5, 'Mango Milk Tea', 75.00, 12, 'img_6ab142d7166b2.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(26, 7, 'Mango Smoothie', 80.00, 15, 'img_6ab142c19ddce.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(27, 7, 'Melon Smoothie', 75.00, 33, 'img_6ab142facbb77.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(28, 2, 'Orange Fruit Tea', 65.00, 16, 'img_6ab143563f796.png', 1, 1, 1, 1, 'Regular|0\r\nLarge|20', 'Pearls|15\r\nNata de Coco|15\r\nCoffee Jelly|15', 0, 1, 1),
(29, 4, 'Oreo', 15.00, 22, 'img_6ab1436375544.png', 0, 0, 0, 0, NULL, NULL, 0, 1, 0),
(30, 7, 'Peach Smoothie', 75.00, 14, 'img_6ab1437c1e143.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(31, 7, 'Pineapple Smoothie', 75.00, 10, 'img_6ab14392e8473.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(32, 5, 'Strawberry Milk Tea', 75.00, 20, 'img_6ab143b01ce5e.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(33, 4, 'Strawberry Pieces', 15.00, 10, 'img_6ab143d37f346.png', 0, 0, 0, 0, NULL, NULL, 0, 1, 0),
(34, 2, 'Water Melon Fruit Tea', 85.00, 10, 'img_6ab1445c3ce5d.png', 1, 1, 1, 1, 'Regular|0\r\nLarge|20', 'Pearls|15\r\nNata de Coco|15\r\nCoffee Jelly|15', 0, 1, 1),
(35, 7, 'Water Melon Smoothie', 75.00, 25, 'img_6ab1446a784e7.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(38, 5, 'Taro Milk Tea', 75.00, 40, 'img_6ab14446e286a.png', 1, 1, 1, 1, 'Small|0\r\nMedium|10\r\nLarge|20\r\nExtra Large|30', 'Oreo|15\r\nTapioca Pearls|10\r\nStrawberry Pieces|15\r\nMango Dice|15\r\nCream|10\r\nGrass Jelly|10\r\nChocolate Pieces|15\r\nChocolate|15', 1, 1, 1),
(45, 2, 'Strawberry Yakult', 80.00, 25, 'img_6ab143ea4d9e8.png', 1, 1, 1, 1, 'Regular|0\r\nLarge|20', 'Pearls|15\r\nNata de Coco|15\r\nCoffee Jelly|15', 0, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `product_ingredients`
--

CREATE TABLE `product_ingredients` (
  `product_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_ingredients`
--

INSERT INTO `product_ingredients` (`product_id`, `ingredient_id`) VALUES
(3, 1),
(4, 1),
(17, 1),
(25, 1),
(32, 1);

-- --------------------------------------------------------

--
-- Table structure for table `smtp_settings`
--

CREATE TABLE `smtp_settings` (
  `id` int(11) NOT NULL,
  `smtp_host` varchar(255) NOT NULL DEFAULT 'smtp.gmail.com',
  `smtp_port` smallint(6) NOT NULL DEFAULT 587,
  `smtp_username` varchar(255) NOT NULL DEFAULT '',
  `smtp_password` varchar(255) NOT NULL DEFAULT '',
  `smtp_from` varchar(255) NOT NULL DEFAULT 'noreply@bobahouse.local',
  `smtp_name` varchar(150) NOT NULL DEFAULT 'Boba House',
  `smtp_secure` enum('tls','ssl','none') NOT NULL DEFAULT 'tls',
  `smtp_auth` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `smtp_settings`
--

INSERT INTO `smtp_settings` (`id`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_from`, `smtp_name`, `smtp_secure`, `smtp_auth`, `updated_at`) VALUES
(1, 'smtp.gmail.com', 587, '', '', 'noreply@bobahouse.local', 'Boba House', 'tls', 1, '2026-09-07 05:47:01');

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

CREATE TABLE `staff_attendance` (
  `id` int(11) NOT NULL,
  `pos_user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `hours_worked` decimal(5,2) NOT NULL DEFAULT 0.00,
  `late_minutes` int(11) NOT NULL DEFAULT 0,
  `undertime_minutes` int(11) NOT NULL DEFAULT 0,
  `location_status` varchar(20) NOT NULL DEFAULT 'unknown',
  `time_in_lat` decimal(10,7) DEFAULT NULL,
  `time_in_lon` decimal(10,7) DEFAULT NULL,
  `time_out_lat` decimal(10,7) DEFAULT NULL,
  `time_out_lon` decimal(10,7) DEFAULT NULL,
  `synced_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_attendance`
--

INSERT INTO `staff_attendance` (`id`, `pos_user_id`, `date`, `time_in`, `time_out`, `hours_worked`, `late_minutes`, `undertime_minutes`, `location_status`, `time_in_lat`, `time_in_lon`, `time_out_lat`, `time_out_lon`, `synced_at`) VALUES
(1, 1, '2026-09-07', '23:11:08', NULL, 0.00, 0, 0, 'unknown', NULL, NULL, NULL, NULL, '2026-09-07 23:11:08'),
(2, 17, '2026-09-08', '22:06:44', NULL, 0.00, 846, 0, 'unknown', NULL, NULL, NULL, NULL, '2026-09-08 22:06:57');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'cash',
  `amount_paid` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','preparing','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `order_type` enum('pickup','delivery') NOT NULL DEFAULT 'pickup',
  `branch_id` int(11) DEFAULT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `delivery_name` varchar(150) DEFAULT NULL,
  `delivery_phone` varchar(30) DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `delivery_status` enum('pending','preparing','out_for_delivery','delivered','failed') NOT NULL DEFAULT 'pending',
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `rider_name` varchar(100) DEFAULT NULL,
  `rider_id` int(11) DEFAULT NULL,
  `estimated_arrival` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `accepted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `processed_by`, `total_amount`, `payment_method`, `amount_paid`, `change_amount`, `status`, `order_type`, `branch_id`, `delivery_address`, `delivery_name`, `delivery_phone`, `delivery_notes`, `delivery_status`, `delivery_fee`, `rider_name`, `rider_id`, `estimated_arrival`, `delivered_at`, `date_created`, `accepted_at`, `completed_at`) VALUES
(1, NULL, NULL, 25.96, 'cash', 50.00, 24.04, 'refunded', 'pickup', NULL, NULL, NULL, NULL, NULL, 'pending', 0.00, NULL, NULL, NULL, NULL, '2026-06-24 12:34:34', NULL, NULL),
(4, 3, NULL, 4.99, 'cash', 100.00, 95.01, 'completed', 'delivery', NULL, 'Brgy NIgga', NULL, NULL, NULL, 'pending', 0.00, NULL, NULL, NULL, NULL, '2026-07-14 14:45:18', NULL, NULL),
(5, 16, 17, 150.00, 'cash', 150.00, 0.00, 'completed', 'delivery', 4, 'blk 17 lot 9', NULL, NULL, NULL, 'preparing', 50.00, NULL, NULL, NULL, NULL, '2026-09-08 14:02:14', '2026-09-08 22:11:30', '2026-09-08 22:11:30'),
(6, 16, 17, 100.00, 'cash', 150.00, 50.00, 'completed', 'pickup', 4, NULL, NULL, NULL, NULL, 'pending', 0.00, NULL, NULL, NULL, NULL, '2026-09-08 14:05:29', '2026-09-08 22:11:36', '2026-09-08 22:11:36'),
(7, 16, 17, 100.00, 'cash', 150.00, 50.00, 'completed', 'pickup', 6, NULL, NULL, NULL, NULL, 'pending', 0.00, NULL, NULL, NULL, NULL, '2026-09-08 14:06:23', '2026-09-08 22:13:14', '2026-09-08 22:13:14'),
(8, 16, 17, 100.00, 'cash', 150.00, 50.00, 'completed', 'pickup', 5, NULL, NULL, NULL, NULL, 'pending', 0.00, NULL, NULL, NULL, NULL, '2026-09-08 14:09:53', '2026-09-08 22:15:09', '2026-09-08 22:15:09'),
(9, 16, 17, 100.00, 'cash', 100.00, 0.00, 'completed', 'pickup', 5, NULL, NULL, NULL, NULL, 'pending', 0.00, NULL, NULL, NULL, NULL, '2026-09-14 05:51:00', '2026-09-14 13:51:21', '2026-09-14 13:51:21'),
(10, 16, 17, 100.00, 'cash', 150.00, 50.00, 'completed', 'pickup', 5, NULL, NULL, NULL, NULL, 'pending', 0.00, NULL, NULL, NULL, NULL, '2026-09-21 14:18:58', '2026-09-21 22:29:58', '2026-09-21 22:29:58'),
(11, 16, 17, 130.00, 'cash', 200.00, 70.00, 'completed', 'pickup', 5, NULL, NULL, NULL, NULL, 'pending', 0.00, NULL, NULL, NULL, NULL, '2026-09-21 14:58:32', '2026-09-21 22:58:47', '2026-09-21 22:58:47');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_details`
--

CREATE TABLE `transaction_details` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `sugar_level` varchar(20) DEFAULT NULL,
  `ice_level` varchar(20) DEFAULT NULL,
  `selected_size` varchar(80) DEFAULT NULL,
  `toppings` text DEFAULT NULL,
  `customization_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `size_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `topping_details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaction_details`
--

INSERT INTO `transaction_details` (`id`, `transaction_id`, `product_id`, `quantity`, `unit_price`, `subtotal`, `sugar_level`, `ice_level`, `selected_size`, `toppings`, `customization_price`, `size_price`, `topping_details`) VALUES
(1, 1, 11, 4, 6.49, 25.96, '0%', 'Regular Ice', NULL, NULL, 0.00, 0.00, NULL),
(4, 4, 1, 1, 4.99, 4.99, '50%', 'Regular Ice', NULL, NULL, 0.00, 0.00, NULL),
(5, 5, 11, 1, 100.00, 100.00, '50%', 'Regular Ice', NULL, NULL, 0.00, 0.00, NULL),
(6, 6, 11, 1, 100.00, 100.00, '50%', 'Regular Ice', NULL, NULL, 0.00, 0.00, NULL),
(7, 7, 11, 1, 100.00, 100.00, '50%', 'Regular Ice', NULL, NULL, 0.00, 0.00, NULL),
(8, 8, 11, 1, 100.00, 100.00, '50%', 'Regular Ice', NULL, NULL, 0.00, 0.00, NULL),
(9, 9, 11, 1, 100.00, 100.00, 'N/A', 'N/A', NULL, NULL, 0.00, 0.00, NULL),
(10, 10, 11, 1, 100.00, 100.00, '50%', 'Regular Ice', 'Small', '[]', 0.00, 0.00, NULL),
(11, 11, 11, 1, 130.00, 130.00, '100%', 'Extra Ice', 'Extra Large', '[]', 30.00, 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','cashier','inventory_staff','customer') NOT NULL DEFAULT 'customer',
  `branch_id` int(11) DEFAULT NULL,
  `firstname` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `hrms_user_id` int(11) DEFAULT NULL,
  `hrms_employee_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verification_token` varchar(100) DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `email_verified`, `is_active`, `password`, `role`, `branch_id`, `firstname`, `lastname`, `hrms_user_id`, `hrms_employee_id`, `created_at`, `verification_token`, `reset_token`, `reset_token_expires`) VALUES
(1, 'admin', 'admin@hrms.com', 1, 1, '$2b$10$O0lYwf1x0i14L1In58dOwOMwv2tmljur1vhqh/swKgsVrIwQnCY6m', 'super_admin', NULL, '', '', 1, 1, '2026-08-05 02:47:31', NULL, NULL, NULL),
(16, 'customer', 'customer@email.com', 1, 1, '$2y$10$gk8hjpwJhhwmuC7HTwMMnOhX7w1bsVXFpoGcB6.IEvh159ik63NPq', 'customer', NULL, NULL, NULL, NULL, NULL, '2026-09-07 05:49:46', NULL, NULL, NULL),
(17, 'garibay', 'sahur@gmail.com', 1, 1, '$2y$10$26HaznTxbBbX9cenG7DPQehtrIAGSijmT1/qvPWBWq9/mthgPkhdu', 'cashier', 5, 'Vincent', 'Garibay', 5, 6, '2026-09-08 12:52:38', NULL, NULL, NULL),
(18, 'otero', 'lance1@gmail.com', 1, 1, '$2y$10$0me3wH1iLEvVMpl4RnFj1e8Mvdg9y9aOO/994Jy.x9fWkf0O4oNfK', 'inventory_staff', NULL, 'lance', 'otero', 6, 7, '2026-09-08 13:47:19', NULL, NULL, NULL),
(19, 'law', 'lawrence@gmail.com', 1, 1, '$2y$10$Uq8pE.uyAaF.IUOIE04TYOVJve2LcT9OPtpNQdaIMxQQvxWvga3by', 'cashier', 1, 'Lawrence', 'Segunial', 7, 8, '2026-09-09 06:15:47', NULL, NULL, NULL),
(20, 'Inventory', 'lance11@gmail.com', 1, 1, '$2y$10$DV6tAcF4k6nSv6rgLo2aS.WsNfZSqbJNaOaBD22lBd9nU6xKKlhl6', 'inventory_staff', 1, 'lhanbert', 'otero', 8, 9, '2026-09-21 14:56:35', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `branch_code` (`branch_code`);

--
-- Indexes for table `cash_fund`
--
ALTER TABLE `cash_fund`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_cart_items`
--
ALTER TABLE `customer_cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_customer_cart_variant_v2` (`user_id`,`variant_key`),
  ADD KEY `idx_customer_cart_user` (`user_id`),
  ADD KEY `idx_customer_cart_product` (`product_id`);

--
-- Indexes for table `delivery_riders`
--
ALTER TABLE `delivery_riders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ingredients`
--
ALTER TABLE `ingredients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_ingredients`
--
ALTER TABLE `product_ingredients`
  ADD PRIMARY KEY (`product_id`,`ingredient_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- Indexes for table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_staff_day` (`pos_user_id`,`date`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transaction_details`
--
ALTER TABLE `transaction_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_users_hrms_user_id` (`hrms_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `customer_cart_items`
--
ALTER TABLE `customer_cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `delivery_riders`
--
ALTER TABLE `delivery_riders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ingredients`
--
ALTER TABLE `ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `transaction_details`
--
ALTER TABLE `transaction_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_ingredients`
--
ALTER TABLE `product_ingredients`
  ADD CONSTRAINT `product_ingredients_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_ingredients_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transaction_details`
--
ALTER TABLE `transaction_details`
  ADD CONSTRAINT `transaction_details_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
