-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 08, 2025 at 07:01 AM
-- Server version: 10.1.29-MariaDB
-- PHP Version: 7.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `agrotradehub`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Vegetables', 'Fresh farm vegetables', '2025-11-22 15:00:22'),
(2, 'Grains', 'Various types of grains', '2025-11-22 15:00:22'),
(3, 'Fruits', 'Seasonal fresh fruits', '2025-11-22 15:00:22'),
(4, 'Fish', 'Freshwater and seawater fish', '2025-11-22 15:00:22'),
(5, 'Meat', 'Fresh meat products', '2025-11-22 15:00:22'),
(6, 'Dairy', 'Milk and dairy products', '2025-11-22 15:00:22');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','processing','completed','cancelled') DEFAULT 'pending',
  `order_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `shipping_address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `order_number` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `seller_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `product_type` enum('vegetable','grain','fruit','fish','meat','dairy') NOT NULL,
  `is_available` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `category_id`, `name`, `description`, `price`, `quantity`, `image_url`, `product_type`, `is_available`, `created_at`) VALUES
(7, 23, 1, 'Toamto', 'Fresh Tomato', '50.00', 10, 'https://static.vecteezy.com/system/resources/thumbnails/048/051/186/small/tomatoes-on-transparent-background-png.png', 'vegetable', 1, '2025-12-08 05:47:05'),
(8, 23, 3, 'Apple', 'Fresh Apple', '100.00', 20, 'https://static.vecteezy.com/system/resources/previews/020/899/515/non_2x/red-apple-isolated-on-white-png.png', 'fruit', 1, '2025-12-08 05:48:13'),
(9, 23, 5, 'Beef', 'Fresh beef', '800.00', 10, 'https://static.vecteezy.com/system/resources/thumbnails/049/799/009/small/steak-meat-beef-isolated-transparent-background-png.png', 'meat', 1, '2025-12-08 05:49:55'),
(10, 23, 2, 'Wheat', 'Fresh wheat', '50.00', 11, 'https://w7.pngwing.com/pngs/703/631/png-transparent-cereal-rice-food-whole-grain-wheat-whole-grains-nutrition-oat-bran-thumbnail.png', 'grain', 1, '2025-12-08 05:52:14'),
(11, 23, 4, 'Hilsha fish', 'Fresh Hilsha Fish', '1500.00', 5, 'https://png.pngtree.com/png-vector/20250221/ourlarge/pngtree-hilsha-fish-png-image_15552378.png', 'fish', 1, '2025-12-08 05:54:43'),
(12, 23, 6, 'Milk', 'Fresh milk', '80.00', 20, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTTVNJXzJm560I5GjhY8Cgedrs79dwjPo565w&s', 'dairy', 1, '2025-12-08 05:55:55'),
(13, 24, 1, 'Potato', 'Fresh potato', '25.00', 14, 'https://png.pngtree.com/png-vector/20240615/ourlarge/pngtree-potatoes-image-png-image_12749734.png', 'vegetable', 1, '2025-12-08 05:57:14'),
(14, 24, 3, 'Mango', 'Fresh mango', '100.00', 5, 'https://static.vecteezy.com/system/resources/previews/026/795/004/non_2x/mango-fruit-tropical-transparent-png.png', 'fruit', 1, '2025-12-08 05:58:32');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('customer','seller','admin') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_banned` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `user_type`, `full_name`, `phone`, `address`, `created_at`, `is_banned`) VALUES
(20, 'abir1', 'abir@gmail.com', '123456', 'customer', 'Abir Ahmed', '01403161496', 'Mohammadpur', '2025-12-08 05:34:52', 0),
(21, 'reazul1', 'reazul@gmail.com', '123456', 'customer', 'Reazul Islam', '01617063610', 'Adabor', '2025-12-08 05:36:47', 0),
(22, 'nakhil1', 'nakhil@gmail.com', '123456', 'customer', 'Nakhil Jahan Akanda', '01403161496', 'Mohammadpur', '2025-12-08 05:37:57', 0),
(23, 'nafis1', 'nafis@gmail.com', '123456', 'seller', 'Nafis Ahmed', '01617063610', 'Azimpur', '2025-12-08 05:39:41', 0),
(24, 'zawad1', 'zawad@gmail.com', '123456', 'seller', 'Zawad Bin', '01617063610', 'Mohammadpur', '2025-12-08 05:40:35', 0),
(25, 'admin', 'admin@gmail.com', '123456', 'admin', 'Real Admin', NULL, NULL, '2025-11-22 09:00:42', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
--Changes in the orders table for the payment process
--
ALTER TABLE orders 
ADD COLUMN payment_method VARCHAR(50) DEFAULT 'bkash',
ADD COLUMN payment_status ENUM('pending','paid','failed') DEFAULT 'pending',
ADD COLUMN payment_transaction_id VARCHAR(100) DEFAULT NULL,
ADD COLUMN payment_mobile VARCHAR(20) DEFAULT NULL;
ADD COLUMN customer_phone VARCHAR(20) DEFAULT NULL;


--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


