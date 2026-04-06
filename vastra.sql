-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 05, 2025 at 06:26 PM
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
-- Database: `vastra`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `recipient_fname` varchar(100) NOT NULL COMMENT 'First name of the recipient',
  `recipient_lname` varchar(100) NOT NULL COMMENT 'Last name of the recipient',
  `mobile_number` varchar(15) NOT NULL,
  `house_address` varchar(255) NOT NULL COMMENT 'House Name, House Number, Building Name',
  `landmark` varchar(255) DEFAULT NULL COMMENT 'Optional: e.g., Near Post Office',
  `locality_or_town` varchar(255) NOT NULL COMMENT 'e.g., Thampanoor, Edappally',
  `district` varchar(100) NOT NULL COMMENT 'e.g., Thiruvananthapuram, Ernakulam, Kozhikode',
  `pincode` varchar(6) NOT NULL,
  `state` varchar(100) NOT NULL DEFAULT 'Kerala',
  `address_type` enum('Home','Office') NOT NULL DEFAULT 'Home',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 for the primary address, 0 for others'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`address_id`, `user_id`, `recipient_fname`, `recipient_lname`, `mobile_number`, `house_address`, `landmark`, `locality_or_town`, `district`, `pincode`, `state`, `address_type`, `is_default`) VALUES
(1, 2, 'Anu', 'Thomas', '9876543210', 'Pulickal House, 12/B', 'Opposite St. Marys Church', 'Kadavanthra', 'Ernakulam', '682020', 'Kerala', 'Home', 1),
(4, 2, 'Anu', 'John', '9876543210', 'Sky line, 12/B', 'Opposite St. Marys Church', 'Kadavanthra', 'Ernakulam', '682020', 'Kerala', 'Office', 0),
(7, 4, 'Afin', 'John', '1111111111', 'iuas', 'lajso', 'lkksa', ' aklsml', '625252', 'Kerala', 'Home', 0),
(8, 16, 'Demo', 'name', '1111111111', 'qw', 'wqw', 'wdq', 'dwa', '686565', 'Kerala', 'Home', 0);

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `product_id`, `quantity`, `added_at`) VALUES
(21, 4, 12, 1, '2025-09-02 14:52:28'),
(42, 2, 24, 1, '2025-11-05 17:12:45');

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `img_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`category_id`, `category_name`, `img_url`) VALUES
(1, 'Men', 'category-men.jpg'),
(2, 'Women', 'category-women.jpg'),
(3, 'Kids', 'category-kids.jpg'),
(4, 'Home', 'category-bedroom.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied') NOT NULL DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`message_id`, `user_id`, `subject`, `message`, `status`, `created_at`) VALUES
(1, 2, 'Testing the contact page', 'Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in section 1.10.32.\r\n\r\nThe standard chunk of Lorem Ipsum used since the 1500s is reproduced below for those interested. Sections 1.10.32 and 1.10.33 from \"de Finibus Bonorum et Malorum\" by Cicero are also reproduced in their exact original form, accompanied by English versions from the 1914 translation by H. Rackham.', 'read', '2025-08-12 05:42:15'),
(2, 2, 'Again testing', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.', 'unread', '2025-09-04 05:51:02');

-- --------------------------------------------------------

--
-- Table structure for table `login`
--

CREATE TABLE `login` (
  `login_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','buyer','seller','') NOT NULL DEFAULT 'buyer',
  `status` enum('active','inactive','banned') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login`
--

INSERT INTO `login` (`login_id`, `user_id`, `email`, `password`, `role`, `status`) VALUES
(1, 1, 'admin@vastra.com', '$2y$10$Sx9Nm2VSuX.3tO4HdYjkaeWj0g3aUhklHD3FZ7OrAjmhhHK7drlu2', 'admin', 'active'),
(2, 2, 'test@example.com', '$2y$10$1RwKqNAbXu9ZpHbJKTI2feuiUJGTbQFltKF0E.1uvpbjPCc55JjTi', 'buyer', 'active'),
(4, 4, 'afinjohn@gmail.com', '$2y$10$hntyfKhcO3EtbNDmWYZMyOpx3sLHs4BJAjBlaQ13eekCV9KcOau3W', 'seller', 'active'),
(7, 10, 'priya@gmail.com', '$2y$10$L5NVWJx8wsB0k67XFzgbSefBXK52HwkOmD5Dqf1iiPxPGrbRTcPN2', 'seller', 'active'),
(13, 16, 'adithyankphere@gmail.com', '$2y$10$j6iysWbi2s7F5/TbwWVEIuWJwwkiCSxPpBKSAei1YkSATpIt/hfem', 'seller', 'active'),
(16, 19, 'username@example.com', '$2y$10$Jf6/6LTX/g.DcNDvnrTBhOUXCcXO3u1JhwWPuwVkx1QeDIrTnT2RO', 'buyer', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Processing','Shipped','Delivered') NOT NULL DEFAULT 'Pending',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `razorpay_payment_id` varchar(255) DEFAULT NULL,
  `razorpay_order_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `address_id`, `total_amount`, `status`, `order_date`, `razorpay_payment_id`, `razorpay_order_id`) VALUES
(14, 2, 4, 489.00, 'Delivered', '2025-09-04 06:23:33', 'pay_RDQMXG6ximU7cR', 'order_RDQMPVX8cBkjMl'),
(15, 2, 4, 789.00, 'Delivered', '2025-09-04 06:25:49', 'pay_RDQOxODXQzw7Ev', 'order_RDQOoXZCGlJAto'),
(16, 2, 4, 789.00, 'Delivered', '2025-09-08 07:54:50', 'pay_RF233MrQOs5Jsg', 'order_RF2246mMa2pUuU'),
(17, 2, 4, 598.00, 'Delivered', '2025-09-12 05:33:10', 'pay_RGZm6hTbSzP5Nh', 'order_RGZlnIq5ik0AS5'),
(18, 2, 4, 489.00, 'Delivered', '2025-09-12 05:34:24', 'pay_RGZnT4S6Dhn90d', 'order_RGZnG6OKB1POOc'),
(19, 2, 4, 789.00, 'Delivered', '2025-09-19 05:13:27', 'pay_RJLBFsifjx03GU', 'order_RJLAMVg3w4VZSG'),
(20, 2, 1, 598.00, 'Delivered', '2025-09-25 15:05:10', 'pay_RLsT1jcSD4kx7P', 'order_RLsSqGpNNLtjbH'),
(21, 16, 8, 598.00, 'Processing', '2025-09-26 14:26:44', 'pay_RMGLZGn7TKU8SS', 'order_RMGKlXje6hNtCK'),
(22, 16, 8, 598.00, 'Processing', '2025-09-26 14:29:40', 'pay_RMGOhL0c7xj8RT', 'order_RMGOaE8SsBztM8'),
(23, 16, 8, 489.00, 'Delivered', '2025-09-26 14:32:22', 'pay_RMGRYCcPWfhVzF', 'order_RMGRPXxcBhooZv');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`item_id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(16, 14, 10, 1, 489.00),
(17, 15, 11, 1, 789.00),
(18, 16, 11, 1, 789.00),
(19, 17, 12, 1, 598.00),
(20, 18, 10, 1, 489.00),
(21, 19, 11, 1, 789.00),
(22, 20, 12, 1, 598.00),
(23, 21, 12, 1, 598.00),
(24, 22, 12, 1, 598.00),
(25, 23, 10, 1, 489.00);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`) VALUES
(16, 'adithyankphere@gmail.com', '943795', '2025-11-05 22:59:22');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `subcategory_id` int(11) NOT NULL,
  `product_name` varchar(50) NOT NULL,
  `product_description` text NOT NULL,
  `price` decimal(10,0) NOT NULL,
  `quantity_available` int(11) NOT NULL,
  `product_image` varchar(255) NOT NULL,
  `status` enum('active','inactive','out-of-stock','banned','expired') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_update` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `shop_id`, `category_id`, `subcategory_id`, `product_name`, `product_description`, `price`, `quantity_available`, `product_image`, `status`, `created_at`, `last_update`) VALUES
(10, 2, 1, 1, 'Denim-Style Shirt', 'This oversized denim-style shirt is designed for a relaxed, streetwear-inspired look. Made from soft jeans-like fabric (lightweight denim/chambray), it gives the classic denim vibe while staying breathable and comfortable for all-day wear. The shirt features a loose, drop-shoulder fit with full sleeves, front button closure, and large utility-style pockets—adding to its casual and trendy appeal.\r\n\r\nThe faded denim texture and rugged stitching make it perfect to wear as a shirt or as an open-layer jacket over a t-shirt. Suitable for both men and women who prefer a Korean/urban oversized street style.', 489, 2, 'prod_688dcfa4a30ac4.59834463.jpg', 'active', '2024-08-02 08:43:16', '2025-11-05 14:43:08'),
(11, 5, 2, 5, 'Red Silk Saree', 'A premium red saree crafted from soft silk-blend fabric, finished with an intricate golden zari border. The saree includes a matching blouse piece. Lightweight, flowy, and perfect for weddings, traditional ceremonies, and festive celebrations. The rich color and golden accents create a royal ethnic appeal.', 789, 13, 'prod_6890c698dbf9c3.69993845-saree-1.jpg', 'active', '2025-08-02 11:55:37', '2025-11-05 14:52:08'),
(12, 4, 1, 9, 'Black T-Shirt', 'This classic black T-shirt is designed for everyday comfort and modern style. Made from 100% premium cotton, it offers a soft feel against the skin and allows excellent breathability, making it ideal for all-day wear. The fabric has a smooth, stretchable finish to ensure ease of movement without losing shape after washing.\r\n\r\nThe T-shirt features a round neck, half sleeves, and a clean silhouette that suits all body types. A minimal graphic/printed design on the front adds a trendy and youthful touch without overpowering its simplicity. The rich black color gives it a versatile appeal — perfect for pairing with jeans, joggers, chinos, or layering under jackets and shirts.', 598, 12, 'prod_68a9d3901c18c4.23086424.jpg', 'active', '2025-08-23 14:43:28', '2025-11-05 14:53:27'),
(13, 8, 1, 2, 'UrbanEdge Blue Denim Jeans', 'Step up your style with the UrbanEdge Blue Denim Jeans, crafted from premium-quality cotton for all-day comfort and durability. These classic-fit jeans feature a mid-rise waist, straight leg cut, and a timeless blue wash that pairs effortlessly with any outfit. Whether you\'re heading out for a casual day or dressing up for a night out, these versatile jeans are your go-to choice for effortless style.', 768, 15, 'prod_68b941bc7d5422.57731572.jpg', 'active', '2024-09-04 07:37:32', '2025-09-04 08:05:44'),
(15, 8, 1, 10, 'Formal Slim-Fit Trousers', 'A pair of sleek formal trousers tailored for a neat and structured appearance. Made from wrinkle-resistant fabric with a smooth finish. Features include waistband with belt loops, front zip closure, and slim straight fit. Suitable for office, interviews, and formal events.', 599, 19, 'prod_690b61f4943843.29757555.jpg', 'active', '2025-11-05 14:40:52', '2025-11-05 14:40:52'),
(16, 8, 2, 3, 'Peach Floral Printed Kurti', 'A peach-colored kurti featuring delicate floral prints and a straight-cut silhouette. Made from light cotton fabric, it offers breathable comfort for daily wear. Comes with three-quarter sleeves, round neckline, and side slits for easy movement. Ideal for casual outings, office wear, and festive pairings with leggings or palazzos.', 799, 21, 'prod_690b6651567414.09742120-Women 3.jpg', 'active', '2025-11-05 14:42:18', '2025-11-05 14:59:29'),
(17, 2, 1, 2, 'Dark Navy Slim-Fit Jeans', 'A modern slim-fit jean in a deep navy shade, offering a contemporary and clean look. Crafted from stretchable denim that hugs the body comfortably without restricting movement. Features include a narrow ankle cut, button closure, belt loops, and subtle whisker detailing. Ideal for parties, casual office days, or everyday fashion.', 899, 23, 'prod_690b62b3925b87.15086349.jpg', 'active', '2025-11-05 14:44:03', '2025-11-05 14:44:03'),
(18, 3, 4, 13, 'Blue Geometric Print Bedsheet Set', 'A modern bedsheet set with geometric blue patterns. Crafted from high-quality cotton with durable stitching. Comes with two matching pillow covers. Fade-resistant and easy to maintain, ideal for regular bedroom use.', 1200, 10, 'prod_690b62ef3b13e8.84980956.jpg', 'active', '2025-11-05 14:45:03', '2025-11-05 14:45:03'),
(19, 3, 4, 15, 'Light Beige Semi-Sheer Window Curtain', 'A light beige semi-sheer curtain designed to allow soft natural light while maintaining privacy. Made of blended polyester fabric with subtle wave patterns. Suitable for living rooms, bedrooms, or offices.', 499, 16, 'prod_690b6356cc0f80.85479715.jpg', 'active', '2025-11-05 14:46:46', '2025-11-05 14:46:46'),
(20, 4, 3, 11, 'White & Pink Baby Frock with Bow', 'A soft and comfortable infant frock in white and pink combination. Made using gentle cotton blend fabric safe for baby skin. Features lace detailing, round neck, back button closure, and a cute waist bow. Ideal for toddlers and special occasions like naming ceremonies or baby photoshoots.', 679, 26, 'prod_690b7d61057156.46349331-Kid 3.jpg', 'active', '2025-11-05 14:49:48', '2025-11-05 16:37:53'),
(21, 4, 3, 11, 'Blue Casual Girls Top & Skirt Set', 'A stylish 2-piece girls\' outfit that includes a light blue printed top paired with a matching skirt. The outfit is made from breathable cotton fabric and designed for both comfort and fashion. Suitable for casual outings, picnics, and summer wear.', 659, 16, 'prod_690b64684056a9.58279463.jpg', 'active', '2025-11-05 14:51:20', '2025-11-05 14:51:20'),
(22, 5, 2, 3, 'Navy Blue Ethnic Kurti with Embroidery', 'A stylish navy blue kurti adorned with light embroidery work around the neckline and sleeves. Fabric is soft rayon/cotton blend, providing good drape and comfort. Designed with a straight fit, 3/4 sleeves, and side slit styling. Suitable for office, ethnic functions, or casual gatherings.', 999, 16, 'prod_690b652a3cb757.79901943.jpg', 'active', '2025-11-05 14:54:34', '2025-11-05 14:54:34'),
(23, 8, 1, 1, 'Blue Solid Casual Cotton Shirt', 'This solid black shirt is a must-have wardrobe essential. Made from breathable cotton, it offers a relaxed yet sharp appearance. The shirt includes a front button closure, full sleeves, a single chest pocket, and a straight hem. The matte black tone makes it versatile for party wear, outings, and casual Fridays at the office.', 679, 16, 'prod_690b66d17abb33.13302914.jpg', 'active', '2025-11-05 15:01:37', '2025-11-05 15:01:37'),
(24, 4, 1, 1, 'Grey Micro-Checked Formal Shirt', 'A sophisticated grey shirt with a micro checkered pattern that delivers a formal and refined vibe. Made from a wrinkle-resistant cotton-polyester blend, it ensures a crisp and structured look throughout the day. Features include a classic collar, full sleeves with cuff buttons, and a tapered fit. Ideal for interviews, office presentations, meetings, or formal events.', 699, 15, 'prod_690b674f38e108.73499932.jpg', 'active', '2025-11-05 15:03:43', '2025-11-05 15:03:43'),
(25, 8, 1, 1, 'Olive Green Checked Casual Shirt', 'This olive green checkered shirt blends modern casual styling with a classic check pattern. Crafted from soft cotton fabric, it offers a breathable and comfortable feel for all-day wear. The olive green base is complemented by subtle contrasting checks, giving it a stylish yet earthy appeal.\r\n\r\nDesigned with full sleeves, a standard collar, button-down front, and a curved hemline, this shirt works well for both casual outings and semi-formal settings. The balanced color tones make it easy to pair with denim jeans, chinos, or even layered over a solid T-shirt for a more relaxed look.', 680, 19, 'prod_690b67b4e5b501.96650585.jpg', 'active', '2025-11-05 15:05:24', '2025-11-05 15:05:24'),
(26, 2, 4, 15, 'Blue Leaf Design Long Curtain', 'A beautiful blue curtain featuring soft leaf patterns. Made from premium polyester fabric that drapes well. Perfect for bedrooms or living rooms to add a calming, elegant touch.', 399, 10, 'prod_690b6844488094.98908455.jpg', 'active', '2025-11-05 15:07:48', '2025-11-05 15:07:48'),
(27, 3, 4, 15, 'Brown and Cream Patterned Door Curtain', 'A thick fabric curtain in a stylish brown and cream print. Provides light blocking and privacy. Features stainless steel eyelets for easy installation. Ideal for doorways, large windows, or balconies.', 590, 12, 'prod_690b68842bc545.14787812.jpg', 'active', '2025-11-05 15:08:52', '2025-11-05 15:08:52'),
(28, 3, 4, 13, 'Floral Cotton Double Bedsheet Set', 'A soft cotton double bedsheet set with an elegant floral print design. Includes matching pillow covers. The fabric is skin-friendly, breathable, and has a smooth texture suitable for all seasons. Enhances bedroom décor with a fresh and cozy look.', 1099, 13, 'prod_690b68d6cf45c3.97965503.jpg', 'active', '2025-11-05 15:10:14', '2025-11-05 15:10:14'),
(29, 2, 1, 2, 'Classic Blue Straight-Fit Jeans', 'A timeless blue denim jean made using durable cotton with slight stretch for better movement. Designed with a mid-rise waist, straight-fit legs, faded wash on thigh areas, and traditional 5-pocket styling. Suitable for daily wear, college, and casual outings. The denim is soft yet sturdy enough for long-term use.', 899, 16, 'prod_690b773d6dd829.41821157.jpg', 'active', '2025-11-05 16:11:41', '2025-11-05 16:11:41'),
(30, 8, 1, 1, 'Green Horizontal Striped Casual Shirt', 'This green horizontal striped shirt is a perfect blend of casual style and everyday comfort. Featuring clean horizontal stripes across a fresh green base, it offers a trendy yet minimal look. Made from soft and breathable cotton fabric, it ensures a relaxed feel while keeping you stylish throughout the day.\r\n\r\nThe shirt comes with a standard collar, full sleeves, button-down closure, and a straight/curved hemline (as per your shirt). Whether worn tucked-in for a neat appearance or left untucked for a laid-back vibe, this shirt works well in both casual and smart-casual settings.', 729, 12, 'prod_690b7af704d201.22387773.jpg', 'active', '2025-11-05 16:27:35', '2025-11-05 16:27:35'),
(31, 8, 1, 9, 'Oversized Orange Cotton T-Shirt', 'This loose fit orange T-shirt is designed for ultimate comfort and a relaxed streetwear vibe. Made from soft, breathable cotton fabric, it has a lightweight and airy feel, perfect for everyday casual wear. The oversized fit gives it a trendy, laid-back appearance with dropped shoulders and a slightly extended sleeve length.\r\n\r\nThe solid orange color adds a fresh and youthful touch, making it stand out whether worn alone or layered over a vest or inside an open shirt. Ideal for college, travel, casual outings, or lounge wear.', 759, 10, 'prod_690b7ce3a5afb2.02287664.jpg', 'active', '2025-11-05 16:35:47', '2025-11-05 16:35:47'),
(32, 4, 3, 12, 'Violet Flared Skirt for Baby Girls', 'This adorable violet skirt is designed especially for baby girls to bring a cute and charming look to their outfit. Made from soft, skin-friendly cotton/poly-blend fabric, it ensures gentle comfort on delicate baby skin. The skirt features a flared/A-line design with a comfortable elastic waistband, making it easy to wear and perfect for active movement.\r\n\r\nThe subtle violet shade gives it a sweet and elegant appearance, suitable for casual outings, photoshoots, birthday celebrations, and festive occasions. It can be paired with plain tops, printed T-shirts, or cute blouses to create a stylish mini-outfit.', 605, 6, 'prod_690b7e6059dee2.33627270.jpg', 'active', '2025-11-05 16:42:08', '2025-11-05 16:42:08');

-- --------------------------------------------------------

--
-- Table structure for table `product_reports`
--

CREATE TABLE `product_reports` (
  `report_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `reason` enum('Misleading Description','Incorrect Item','Low Quality','Inappropriate Content','Other') NOT NULL,
  `comment` text DEFAULT NULL,
  `status` enum('pending','reviewed','resolved') NOT NULL DEFAULT 'pending',
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `review_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL COMMENT 'Rating from 1 to 5',
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved' COMMENT 'For moderation by admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`review_id`, `product_id`, `user_id`, `order_id`, `rating`, `review_text`, `created_at`, `status`) VALUES
(1, 11, 2, 15, 4, 'This is a good product.', '2025-09-14 07:13:56', 'approved'),
(2, 12, 2, 17, 4, 'Good quality product.', '2025-09-14 07:22:43', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `seller_requests`
--

CREATE TABLE `seller_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shop_name` varchar(50) NOT NULL,
  `shop_description` text NOT NULL,
  `building_name_or_number` varchar(255) NOT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `locality_or_town` varchar(255) NOT NULL,
  `district` varchar(100) NOT NULL,
  `pincode` varchar(6) NOT NULL,
  `state` varchar(100) NOT NULL DEFAULT 'Kerala',
  `shop_image` varchar(255) DEFAULT NULL,
  `business_phone` varchar(10) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seller_requests`
--

INSERT INTO `seller_requests` (`request_id`, `user_id`, `shop_name`, `shop_description`, `building_name_or_number`, `landmark`, `locality_or_town`, `district`, `pincode`, `state`, `shop_image`, `business_phone`, `status`, `requested_at`) VALUES
(1, 4, 'Fastion bazar', 'Sample data', '', NULL, '', '', '', 'Kerala', NULL, NULL, 'approved', '2025-07-26 15:16:32'),
(6, 16, 'Thread & Texture', 'Thread & Texture is a vibrant textile shop that weaves tradition with modern flair. Specializing in high-quality fabrics, from rich handlooms and silks to trendy prints and everyday cottons, we cater to designers, tailors, and textile lovers alike. Our curated collection celebrates the art of textiles, offering both local and international weaves that inspire creativity and craftsmanship. Whether you\'re designing a couture outfit or searching for the perfect fabric for home decor, Thread & Texture brings you the finest threads to bring your vision to life.', '', NULL, '', '', '', 'Kerala', 'req_68b9408a9b83e.png', '1111111111', 'approved', '2025-09-04 07:32:26'),
(8, 2, 'MalayaliFashion', 'Testing agian new changes', 'Kalayakkattu', 'Mannanam', 'Kottayam', 'Kottayam', '686561', 'Kerala', 'req_68d6d30ce87e7.png', '1234566789', 'approved', '2025-09-26 17:53:16');

-- --------------------------------------------------------

--
-- Table structure for table `seller_subscriptions`
--

CREATE TABLE `seller_subscriptions` (
  `subscription_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `razorpay_payment_id` varchar(255) DEFAULT NULL COMMENT 'For paid renewals',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seller_subscriptions`
--

INSERT INTO `seller_subscriptions` (`subscription_id`, `user_id`, `plan_id`, `start_date`, `end_date`, `status`, `razorpay_payment_id`, `created_at`) VALUES
(1, 2, 1, '2025-09-26 23:35:46', '2025-09-29 23:35:46', 'expired', NULL, '2025-09-26 18:05:46'),
(3, 2, 2, '2025-09-27 00:12:43', '2025-10-08 00:12:43', 'expired', 'pay_RMKhzpt09Rc2wc', '2025-09-26 18:42:43'),
(5, 2, 2, '2025-09-27 21:55:41', '2025-12-26 21:55:41', 'expired', 'pay_RMguJc8mTmEvDH', '2025-09-27 16:25:41'),
(6, 2, 2, '2025-12-26 21:55:41', '2026-03-26 21:55:41', 'active', 'pay_RMi5hSGAinj6Og', '2025-09-27 17:35:08'),
(7, 4, 2, '2025-09-27 23:16:53', '2025-12-26 23:16:53', 'active', 'pay_RMiI7MIEORZhus', '2025-09-27 17:46:53'),
(8, 16, 4, '2025-09-27 23:18:29', '2026-09-27 23:18:29', 'active', 'pay_RMiJpEz9NZd3ue', '2025-09-27 17:48:29'),
(9, 10, 3, '2025-09-27 23:20:21', '2026-03-26 23:20:21', 'active', 'pay_RMiLmpCUrqcFQ3', '2025-09-27 17:50:21');

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `shop_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shop_name` varchar(100) NOT NULL,
  `shop_description` text DEFAULT NULL,
  `shop_image` varchar(255) DEFAULT NULL,
  `business_phone` varchar(20) DEFAULT NULL,
  `shop_status` enum('active','inactive','banned','expired') NOT NULL DEFAULT 'active',
  `warning_sent_at` datetime DEFAULT NULL COMMENT 'Timestamp of when the inactivity warning was sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`shop_id`, `user_id`, `shop_name`, `shop_description`, `shop_image`, `business_phone`, `shop_status`, `warning_sent_at`, `created_at`) VALUES
(2, 4, 'Fastion bazar', 'Fastion Bazar is a trendy clothing store that brings affordable fashion to everyone. We offer a wide range of men\'s, women’s, and kids’ wear – from daily casuals to festive collections. Our shop focuses on stylish designs, quality fabrics, and budget-friendly pricing, making fashion accessible to all.', 'shop_690b82cec7f40.png', '', 'active', NULL, '2025-07-26 16:00:15'),
(3, 4, 'MalayaliFashion', 'MalayaliFashion is a modern ethnic fashion store inspired by Kerala’s traditional and cultural aesthetics. We specialize in Kerala sarees, cotton mundu sets, Kasavu collections, designer blouses, and casual Malayali-style outfits. Our goal is to preserve tradition while offering updated, trendy designs for today’s generation.', 'shop_690b822b7acaa.png', '', 'active', NULL, '2025-08-02 08:02:48'),
(4, 10, 'Priya Studio', 'Priya Studio is a boutique-style clothing store featuring elegant and modern women’s fashion. From ethnic sarees and salwar sets to contemporary dresses and casual wear, we focus on graceful designs and fine detailing. Our goal is to make every woman feel confident and stylish with premium-quality outfits.', 'shop_690b83792c5d0.png', '', 'active', NULL, '2025-08-02 11:53:31'),
(5, 10, 'Priya Women\'s wear', 'Priya Women’s Wear is a dedicated store for fashionable women’s clothing. We bring you handpicked Kurtis, sarees, gowns, casual tops, and festive wear that blend comfort with style. Whether it’s a daily office outfit or festive attire, our collection offers something for every woman’s wardrobe.', 'shop_690b83fc51878.png', '', 'active', NULL, '2025-08-02 11:54:26'),
(8, 16, 'Thread & Texture', 'Thread & Texture is a vibrant textile shop that weaves tradition with modern flair. Specializing in high-quality fabrics, from rich handlooms and silks to trendy prints and everyday cottons, we cater to designers, tailors, and textile lovers alike. Our curated collection celebrates the art of textiles, offering both local and international weaves that inspire creativity and craftsmanship. Whether you\'re designing a couture outfit or searching for the perfect fabric for home decor, Thread & Texture brings you the finest threads to bring your vision to life.', 'req_68b9408a9b83e.png', '9874561230', 'active', NULL, '2025-09-04 07:33:41');

-- --------------------------------------------------------

--
-- Table structure for table `shop_addresses`
--

CREATE TABLE `shop_addresses` (
  `shop_address_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `building_name_or_number` varchar(255) NOT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `locality_or_town` varchar(255) NOT NULL,
  `district` varchar(100) NOT NULL,
  `pincode` varchar(6) NOT NULL,
  `state` varchar(100) NOT NULL DEFAULT 'Kerala'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores normalized addresses for shops';

--
-- Dumping data for table `shop_addresses`
--

INSERT INTO `shop_addresses` (`shop_address_id`, `shop_id`, `building_name_or_number`, `landmark`, `locality_or_town`, `district`, `pincode`, `state`) VALUES
(3, 3, '1nd floor, Plazza Buliding', '', 'Kaloor', 'Ernakulam', '564651', 'Kerala'),
(4, 2, '2nd floor, Arcadia Buliding', '', 'Kadavanthra', 'Ernakulam', '686561', 'Kerala'),
(5, 4, '2nd floor, Plazza Buliding', '', 'Kottayam', 'Kottayam', '625252', 'Kerala'),
(6, 5, 'Pulukayil', 'near MC road', 'Ettumanoor', 'Kottayam', '625252', 'Kerala'),
(7, 8, 'Gokulam Complex', '', 'Kaloor', 'Ernakulam', '684792', 'Kerala');

-- --------------------------------------------------------

--
-- Table structure for table `subcategory`
--

CREATE TABLE `subcategory` (
  `subcategory_id` int(11) NOT NULL,
  `subcategory_name` varchar(50) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subcategory`
--

INSERT INTO `subcategory` (`subcategory_id`, `subcategory_name`, `category_id`) VALUES
(1, 'Shirt', 1),
(2, 'Jean', 1),
(3, 'Kurti', 2),
(5, 'Saree', 2),
(9, 'T-Shirt', 1),
(10, 'Trousers', 1),
(11, 'Frock', 3),
(12, 'Skirt', 3),
(13, 'Bedding Set', 4),
(14, 'Bedsheet', 4),
(15, 'Curtain', 4);

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `plan_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `duration_days` int(11) NOT NULL COMMENT 'e.g., 30 for trial, 90 for 3 months',
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = inactive, 1 = active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`plan_id`, `plan_name`, `duration_days`, `price`, `is_active`) VALUES
(1, 'Free Trial', 30, 0.00, 1),
(2, '3-Month Plan', 90, 599.00, 1),
(3, '6-Month Plan', 180, 899.00, 1),
(4, '1-Year Plan', 365, 1299.00, 1),
(5, '2 year plan', 730, 2699.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `fname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `phone` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `fname`, `lname`, `email`, `phone`) VALUES
(1, 'Admin', '', 'admin@vastra.com', ''),
(2, 'User', 'Name', 'test@example.com', '1234567890'),
(4, 'Afin', 'John', 'afinjohn@gmail.com', '9539497871'),
(10, 'Priya', ' Desai', 'priya@gmail.com', '9876543211'),
(16, 'ihayuhu', 'Here', 'adithyankphere@gmail.com', '1111111111'),
(17, 'Adithyan', 'K', 'adithyankp@gmail.com', '1111111111'),
(18, 'User', 'Name', 'test@gmail.com', '1234567890'),
(19, 'User', 'Name', 'username@example.com', '1234567890');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `login`
--
ALTER TABLE `login`
  ADD PRIMARY KEY (`login_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `address_id` (`address_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email_index` (`email`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `subcategory_id` (`subcategory_id`),
  ADD KEY `shop_id` (`shop_id`);

--
-- Indexes for table `product_reports`
--
ALTER TABLE `product_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD UNIQUE KEY `user_product_order_report_unique` (`user_id`,`product_id`,`order_id`),
  ADD KEY `fk_report_product` (`product_id`),
  ADD KEY `fk_report_user` (`user_id`),
  ADD KEY `fk_report_order` (`order_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `user_product_order_review` (`user_id`,`product_id`,`order_id`),
  ADD KEY `fk_review_product` (`product_id`),
  ADD KEY `fk_review_user` (`user_id`),
  ADD KEY `fk_review_order` (`order_id`);

--
-- Indexes for table `seller_requests`
--
ALTER TABLE `seller_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `seller_subscriptions`
--
ALTER TABLE `seller_subscriptions`
  ADD PRIMARY KEY (`subscription_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`shop_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `shop_addresses`
--
ALTER TABLE `shop_addresses`
  ADD PRIMARY KEY (`shop_address_id`),
  ADD UNIQUE KEY `shop_id` (`shop_id`);

--
-- Indexes for table `subcategory`
--
ALTER TABLE `subcategory`
  ADD PRIMARY KEY (`subcategory_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`plan_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`wishlist_id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `login`
--
ALTER TABLE `login`
  MODIFY `login_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `product_reports`
--
ALTER TABLE `product_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `seller_requests`
--
ALTER TABLE `seller_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `seller_subscriptions`
--
ALTER TABLE `seller_subscriptions`
  MODIFY `subscription_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `shop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `shop_addresses`
--
ALTER TABLE `shop_addresses`
  MODIFY `shop_address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `subcategory`
--
ALTER TABLE `subcategory`
  MODIFY `subcategory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD CONSTRAINT `fk_contact_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `login`
--
ALTER TABLE `login`
  ADD CONSTRAINT `login_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `login_ibfk_2` FOREIGN KEY (`email`) REFERENCES `users` (`email`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategory` (`subcategory_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `products_ibfk_3` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`shop_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_reports`
--
ALTER TABLE `product_reports`
  ADD CONSTRAINT `fk_report_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_report_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_report_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `fk_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `seller_requests`
--
ALTER TABLE `seller_requests`
  ADD CONSTRAINT `seller_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `seller_subscriptions`
--
ALTER TABLE `seller_subscriptions`
  ADD CONSTRAINT `fk_sub_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`plan_id`),
  ADD CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `shops`
--
ALTER TABLE `shops`
  ADD CONSTRAINT `shops_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `shop_addresses`
--
ALTER TABLE `shop_addresses`
  ADD CONSTRAINT `fk_shop_address_to_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`shop_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `subcategory`
--
ALTER TABLE `subcategory`
  ADD CONSTRAINT `subcategory_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
