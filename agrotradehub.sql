-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 13, 2025 at 05:59 PM
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
-- Database: `agrotradehub`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `shipping_address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_number` varchar(50) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'bkash',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `payment_transaction_id` varchar(100) DEFAULT NULL,
  `payment_mobile` varchar(20) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `total_amount`, `status`, `order_date`, `shipping_address`, `created_at`, `order_number`, `payment_method`, `payment_status`, `payment_transaction_id`, `payment_mobile`, `customer_phone`) VALUES
(26, 21, 880.00, 'completed', '2025-12-12 11:59:03', 'Adabor', '2025-12-12 11:59:03', 'ORD-20251212-125903-1680', 'bkash', 'paid', 'TRX123456789', '01617063610', '01617063610'),
(27, 21, 60.00, 'completed', '2025-12-12 12:32:21', 'Adabor', '2025-12-12 12:32:21', 'ORD-20251212-133221-4912', 'bkash', 'paid', 'TRX123456789', '01786335550', '01617063610'),
(28, 22, 220.00, 'completed', '2025-12-12 13:37:10', 'Mohammadpur', '2025-12-12 13:37:10', 'ORD-20251212-143710-4847', 'bkash', 'paid', 'TRX123456789', '01234323131', '01403161496'),
(29, 22, 110.00, 'completed', '2025-12-12 13:37:46', 'Mohammadpur', '2025-12-12 13:37:46', 'ORD-20251212-143746-8393', 'bkash', 'paid', 'TRX123456789', '01234323131', '01403161496'),
(30, 20, 110.00, 'completed', '2025-12-13 07:18:00', 'Mohammadpur', '2025-12-13 07:18:00', 'ORD-20251213-081800-8698', 'bkash', 'paid', 'TRX123456789', '01403161496', '01403161496'),
(31, 20, 962.50, 'pending', '2025-12-13 09:33:26', 'Mohammadpur', '2025-12-13 09:33:26', 'ORD-20251213-103326-7501', 'bkash', 'paid', 'TRX1777', '017', '01403161496'),
(32, 20, 32.50, 'pending', '2025-12-13 09:49:59', 'Mohammadpur', '2025-12-13 09:49:59', 'ORD-20251213-104959-2936', 'bkash', 'paid', 'TRX22222', '017', '01403161496'),
(33, 26, 32.50, 'pending', '2025-12-13 16:43:50', 'Dhanmondi', '2025-12-13 16:43:50', 'ORD-20251213-174350-5501', 'bkash', 'paid', 'TRX365353', '01776', '017112227'),
(34, 26, 88.00, 'pending', '2025-12-13 16:44:58', 'Dhanmondi', '2025-12-13 16:44:58', 'ORD-20251213-174458-4943', 'bkash', 'paid', 'TRX534335', '01776', '017112227');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `seller_id`) VALUES
(21, 26, 9, 1, 800.00, 23),
(22, 27, 10, 1, 50.00, 23),
(23, 28, 14, 2, 100.00, 24),
(24, 29, 14, 1, 100.00, 24),
(25, 30, 14, 1, 100.00, 24),
(26, 31, 13, 1, 25.00, 24),
(27, 31, 10, 1, 50.00, 23),
(28, 31, 9, 1, 800.00, 23),
(29, 32, 13, 1, 25.00, 24),
(30, 33, 13, 1, 25.00, 24),
(31, 34, 12, 1, 80.00, 23);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `product_type` enum('vegetable','grain','fruit','fish','meat','dairy') NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `category_id`, `name`, `description`, `price`, `quantity`, `image_url`, `product_type`, `is_available`, `created_at`) VALUES
(8, 23, 3, 'Apple', 'Fresh Apple', 100.00, 20, 'https://static.vecteezy.com/system/resources/previews/020/899/515/non_2x/red-apple-isolated-on-white-png.png', 'fruit', 1, '2025-12-08 05:48:13'),
(9, 23, 5, 'Beef', 'Fresh beef', 800.00, 9, 'https://static.vecteezy.com/system/resources/thumbnails/049/799/009/small/steak-meat-beef-isolated-transparent-background-png.png', 'meat', 1, '2025-12-08 05:49:55'),
(10, 23, 2, 'Wheat', 'Fresh wheat', 50.00, 10, 'https://w7.pngwing.com/pngs/703/631/png-transparent-cereal-rice-food-whole-grain-wheat-whole-grains-nutrition-oat-bran-thumbnail.png', 'grain', 1, '2025-12-08 05:52:14'),
(11, 23, 4, 'Hilsha fish', 'Fresh Hilsha Fish', 1500.00, 4, 'https://png.pngtree.com/png-vector/20250221/ourlarge/pngtree-hilsha-fish-png-image_15552378.png', 'fish', 1, '2025-12-08 05:54:43'),
(12, 23, 6, 'Milk', 'Fresh milk', 80.00, 20, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTTVNJXzJm560I5GjhY8Cgedrs79dwjPo565w&s', 'dairy', 1, '2025-12-08 05:55:55'),
(13, 24, 1, 'Potato', 'Fresh potato', 25.00, 14, 'https://png.pngtree.com/png-vector/20240615/ourlarge/pngtree-potatoes-image-png-image_12749734.png', 'vegetable', 1, '2025-12-08 05:57:14'),
(14, 24, 3, 'Mango', 'Fresh mango', 100.00, 2, 'https://static.vecteezy.com/system/resources/previews/026/795/004/non_2x/mango-fruit-tropical-transparent-png.png', 'fruit', 1, '2025-12-08 05:58:32'),
(15, 28, 3, 'Stawbery', 'Farm Fresh Products from farmers', 230.00, 15, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTgbSM5DX2tlzSf0khV37VpQsM9g06A-7F4Uw&s', 'fruit', 1, '2025-12-13 16:50:59');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `product_id`, `customer_id`, `rating`, `comment`, `created_at`) VALUES
(3, 9, 21, 4, 'BEST BEEEF EVER', '2025-12-12 12:00:13'),
(4, 14, 20, 3, 'thanks zawad bin', '2025-12-13 07:20:07');

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
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_banned` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `user_type`, `full_name`, `phone`, `address`, `created_at`, `is_banned`) VALUES
(20, 'abir1', 'abir@gmail.com', '123456', 'customer', 'Abir Ahmed', '01403161496', 'Mohammadpur', '2025-12-08 05:34:52', 0),
(21, 'reazul1', 'reazul@gmail.com', '123456', 'customer', 'Reazul Islam', '01617063610', 'Adabor', '2025-12-08 05:36:47', 0),
(22, 'nakhil1', 'nakhil@gmail.com', '123456', 'customer', 'Nakhil Jahan Akanda', '01403161496', 'Mohammadpur', '2025-12-08 05:37:57', 0),
(23, 'nafis1', 'nafis@gmail.com', '123456', 'seller', 'Nafis Ahmed', '01617063610', 'Azimpur', '2025-12-08 05:39:41', 0),
(24, 'zawad1', 'zawad@gmail.com', '123456', 'seller', 'Zawad Bin', '01617063610', 'Mohammadpur', '2025-12-08 05:40:35', 0),
(25, 'admin', 'admin@gmail.com', '123456', 'admin', 'Real Admin', NULL, NULL, '2025-11-22 09:00:42', 0),
(26, 'tonmoy1', 'tonmoy@gmai.com', '123456', 'customer', 'Tonmoy', '017112227', 'Dhanmondi', '2025-12-13 16:32:27', 0),
(27, 'sagor1', 'sagor@gmail.com', '123456', 'customer', 'Sagor', '016', 'Bosila', '2025-12-13 16:46:04', 0),
(28, 'jmam1', 'jimam@gmail.com', '123456', 'seller', 'Jimam', '017', 'Rayerbazar', '2025-12-13 16:47:37', 0);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

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
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
