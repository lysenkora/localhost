-- phpMyAdmin SQL Dump
-- version 4.8.3
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Мар 24 2026 г., 20:05
-- Версия сервера: 8.0.12
-- Версия PHP: 7.1.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `investment_portfolio`
--

-- --------------------------------------------------------

--
-- Структура таблицы `action_plan`
--

CREATE TABLE `action_plan` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `is_completed` tinyint(1) DEFAULT '0',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `action_plan`
--

INSERT INTO `action_plan` (`id`, `title`, `description`, `is_completed`, `priority`, `due_date`, `completed_date`, `created_at`, `updated_at`) VALUES
(1, 'Купить облигации на спаде', NULL, 0, 'medium', NULL, NULL, '2026-02-18 19:01:50', '2026-02-18 19:01:50'),
(2, 'Диверсификация в золото', NULL, 0, 'medium', NULL, NULL, '2026-02-18 19:01:50', '2026-02-18 19:01:50'),
(3, 'Открыть вклад в юанях', NULL, 0, 'low', NULL, NULL, '2026-02-18 19:01:50', '2026-02-18 19:01:50'),
(4, 'Продать убыточные ETF', NULL, 1, 'high', NULL, NULL, '2026-02-18 19:01:50', '2026-02-18 19:01:50'),
(5, 'Пополнить ИИС до 400k', NULL, 1, 'high', NULL, NULL, '2026-02-18 19:01:50', '2026-02-18 19:01:50');

-- --------------------------------------------------------

--
-- Структура таблицы `assets`
--

CREATE TABLE `assets` (
  `id` int(11) NOT NULL,
  `symbol` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('stock','bond','crypto','currency','deposit','etf','other') NOT NULL,
  `sector` varchar(50) DEFAULT NULL,
  `currency_code` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `assets`
--

INSERT INTO `assets` (`id`, `symbol`, `name`, `type`, `sector`, `currency_code`, `is_active`, `created_at`) VALUES
(1, 'RUB', 'Российский рубль', 'currency', 'Валюта', 'RUB', 1, '2026-02-18 19:01:50'),
(2, 'USD', 'Доллар США', 'currency', 'Валюта', 'USD', 1, '2026-02-18 19:01:50'),
(3, 'EUR', 'Евро', 'currency', 'Валюта', 'EUR', 1, '2026-02-18 19:01:50'),
(4, 'BTC', 'Биткоин', 'crypto', 'Криптовалюта', 'USD', 1, '2026-02-18 19:01:50'),
(5, 'ETH', 'Ethereum', 'crypto', 'Криптовалюта', 'USD', 1, '2026-02-18 19:01:50'),
(6, 'USDT', 'Tether', 'crypto', 'Stablecoin', 'USD', 1, '2026-02-18 19:01:50'),
(7, 'SBER', 'Сбербанк', 'stock', 'Финансы', 'RUB', 1, '2026-02-18 19:01:50'),
(8, 'GAZP', 'Газпром', 'stock', 'Нефть и газ', 'RUB', 1, '2026-02-18 19:01:50'),
(9, 'LKOH', 'Лукойл', 'stock', 'Нефть и газ', 'RUB', 1, '2026-02-18 19:01:50'),
(10, 'YNDX', 'Яндекс', 'stock', 'Технологии', 'RUB', 1, '2026-02-18 19:01:50'),
(11, 'TSLA', 'Tesla', 'stock', 'Технологии', 'USD', 1, '2026-02-18 19:01:50'),
(12, 'AAPL', 'Apple', 'stock', 'Технологии', 'USD', 1, '2026-02-18 19:01:50'),
(17, 'STX', 'Stacks', 'crypto', NULL, NULL, 1, '2026-03-11 16:00:07'),
(18, 'ZK', 'ZKsync', 'crypto', NULL, NULL, 1, '2026-03-11 17:14:42'),
(19, 'FIL', 'Filecoin', 'crypto', NULL, NULL, 1, '2026-03-12 16:38:18'),
(20, 'ONDO', 'Ondo', 'crypto', NULL, NULL, 1, '2026-03-12 16:43:59'),
(21, 'RENDER', 'RENDER', 'crypto', NULL, NULL, 1, '2026-03-12 16:46:39'),
(22, 'GRT', 'The Graph', 'crypto', NULL, NULL, 1, '2026-03-15 16:56:55'),
(23, 'TWT', 'Trust Wallet Token', 'crypto', NULL, NULL, 1, '2026-03-15 16:58:35'),
(24, 'APE', 'ApeCoin', 'crypto', NULL, NULL, 1, '2026-03-15 17:00:05'),
(25, 'CELO', 'Celo', 'crypto', NULL, NULL, 1, '2026-03-15 17:00:56'),
(26, 'GOAT', 'Goatseus Maximus', 'crypto', NULL, NULL, 1, '2026-03-15 17:01:55'),
(27, 'SOL', 'Solana', 'crypto', NULL, NULL, 1, '2026-03-22 04:30:49'),
(28, 'TRUMP', 'TRUMP', 'crypto', NULL, NULL, 1, '2026-03-22 04:39:01'),
(29, 'IMX', 'Immutable', 'crypto', NULL, NULL, 1, '2026-03-22 04:47:40'),
(30, 'POL', 'Polygon (prev. MATIC)', 'crypto', NULL, NULL, 1, '2026-03-22 04:48:55'),
(31, 'LINK', 'Chainlink', 'crypto', NULL, NULL, 1, '2026-03-22 04:50:19'),
(32, 'BNB', 'BNB', 'crypto', NULL, NULL, 1, '2026-03-22 04:51:15'),
(33, 'ARKM', 'Arkham', 'crypto', NULL, NULL, 1, '2026-03-22 05:01:19'),
(34, 'ARC', 'ARCReactorAI', 'crypto', NULL, NULL, 1, '2026-03-24 12:50:20'),
(35, 'NEUR', 'neur.sh', 'crypto', NULL, NULL, 1, '2026-03-24 13:14:05'),
(36, 'ALCH', 'ALCH', 'crypto', NULL, NULL, 1, '2026-03-24 13:17:28');

-- --------------------------------------------------------

--
-- Структура таблицы `currencies`
--

CREATE TABLE `currencies` (
  `code` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `type` enum('fiat','crypto') DEFAULT 'fiat'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `currencies`
--

INSERT INTO `currencies` (`code`, `name`, `symbol`, `type`) VALUES
('ALCH', 'ALCH', NULL, 'crypto'),
('APE', 'ApeCoin', NULL, 'crypto'),
('ARC', 'ARCReactorAI', NULL, 'crypto'),
('ARKM', 'Arkham', NULL, 'crypto'),
('BNB', 'BNB', NULL, 'crypto'),
('BTC', 'Биткоин', '₿', 'crypto'),
('CELO', 'Celo', NULL, 'crypto'),
('ETH', 'Ethereum', 'Ξ', 'crypto'),
('EUR', 'Евро', '€', 'fiat'),
('FIL', 'Filecoin', NULL, 'crypto'),
('GOAT', 'Goatseus Maximus', NULL, 'crypto'),
('GRT', 'The Graph', NULL, 'crypto'),
('IMX', 'Immutable', NULL, 'crypto'),
('LINK', 'Chainlink', NULL, 'crypto'),
('NEUR', 'neur.sh', NULL, 'crypto'),
('ONDO', 'Ondo', NULL, 'crypto'),
('POL', 'Polygon (prev. MATIC)', NULL, 'crypto'),
('RENDER', 'RENDER', NULL, 'crypto'),
('RUB', 'Российский рубль', '₽', 'fiat'),
('SOL', 'Solana', NULL, 'crypto'),
('STX', 'Stacks', NULL, 'crypto'),
('TRUMP', 'TRUMP', NULL, 'crypto'),
('TWT', 'Trust Wallet Token', NULL, 'crypto'),
('USD', 'Доллар США', '$', 'fiat'),
('USDC', 'USD Coin', '₵', 'crypto'),
('USDT', 'Tether', '₮', 'crypto'),
('ZK', 'ZKsync', NULL, 'crypto');

-- --------------------------------------------------------

--
-- Структура таблицы `deposits`
--

CREATE TABLE `deposits` (
  `id` int(11) NOT NULL,
  `platform_id` int(11) NOT NULL,
  `amount` decimal(20,8) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `deposit_date` date NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `deposits`
--

INSERT INTO `deposits` (`id`, `platform_id`, `amount`, `currency_code`, `deposit_date`, `notes`, `created_at`) VALUES
(10, 1, '500000.00000000', 'RUB', '2025-01-25', '', '2026-03-07 19:52:13');

-- --------------------------------------------------------

--
-- Структура таблицы `deposit_currencies`
--

CREATE TABLE `deposit_currencies` (
  `id` int(11) NOT NULL,
  `currency_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `exchange_rates`
--

CREATE TABLE `exchange_rates` (
  `id` int(11) NOT NULL,
  `from_currency` varchar(10) NOT NULL,
  `to_currency` varchar(10) NOT NULL,
  `rate` decimal(20,8) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `exchange_rates`
--

INSERT INTO `exchange_rates` (`id`, `from_currency`, `to_currency`, `rate`, `date`, `created_at`) VALUES
(1, 'USD', 'RUB', '92.50000000', '2026-02-19', '2026-02-18 19:01:50'),
(4, 'RUB', 'USD', '0.01080000', '2026-02-19', '2026-02-19 07:05:42'),
(5, 'USD', 'RUB', '92.50000000', '2026-03-07', '2026-03-07 19:24:07'),
(6, 'RUB', 'USD', '0.01081081', '2026-03-07', '2026-03-07 19:24:07');

-- --------------------------------------------------------

--
-- Структура таблицы `limit_orders`
--

CREATE TABLE `limit_orders` (
  `id` int(11) NOT NULL,
  `operation_type` enum('buy','sell') NOT NULL,
  `asset_id` int(11) NOT NULL,
  `platform_id` int(11) NOT NULL,
  `quantity` decimal(20,8) NOT NULL,
  `limit_price` decimal(20,8) NOT NULL,
  `price_currency` varchar(10) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `status` enum('active','executed','cancelled','expired') NOT NULL DEFAULT 'active',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `networks`
--

CREATE TABLE `networks` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `icon` varchar(50) DEFAULT 'fas fa-network-wired',
  `color` varchar(20) DEFAULT '#ff9f4a',
  `full_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `networks`
--

INSERT INTO `networks` (`id`, `name`, `icon`, `color`, `full_name`, `is_active`, `created_at`) VALUES
(1, 'ERC20', 'fab fa-ethereum', '#627eea', 'Ethereum (ERC-20)', 1, '2026-03-23 08:06:45'),
(2, 'BEP20', 'fas fa-bolt', '#f3ba2f', 'Binance Smart Chain (BEP-20)', 1, '2026-03-23 08:06:45'),
(3, 'TRC20', 'fab fa-t', '#eb0029', 'TRON (TRC-20)', 1, '2026-03-23 08:06:45'),
(4, 'SOL', 'fas fa-sun', '#14f195', 'Solana', 1, '2026-03-23 08:06:45'),
(5, 'BTC', 'fab fa-bitcoin', '#f7931a', 'Bitcoin', 1, '2026-03-23 08:06:45'),
(6, 'POLYGON', 'fas fa-chart-line', '#8247e5', 'Polygon (MATIC)', 1, '2026-03-23 08:06:45'),
(7, 'ARBITRUM', 'fas fa-chart-line', '#28a0f0', 'Arbitrum', 1, '2026-03-23 08:06:45'),
(8, 'OPTIMISM', 'fas fa-chart-line', '#ff0420', 'Optimism', 1, '2026-03-23 08:06:45'),
(9, 'AVALANCHE', 'fas fa-chart-line', '#e84142', 'Avalanche C-Chain', 1, '2026-03-23 08:06:45'),
(10, 'FTM', 'fas fa-chart-line', '#1969ff', 'Fantom', 1, '2026-03-23 08:06:45'),
(11, 'ARBITRUM ONE', 'fas fa-network-wired', '#ff9f4a', 'Arbitrum One', 1, '2026-03-24 13:20:40');

-- --------------------------------------------------------

--
-- Структура таблицы `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `note_type` enum('general','reminder','idea','important') DEFAULT 'general',
  `reminder_date` date DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `platforms`
--

CREATE TABLE `platforms` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) DEFAULT 'other',
  `country` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `platforms`
--

INSERT INTO `platforms` (`id`, `name`, `type`, `country`, `is_active`, `created_at`) VALUES
(1, 'Т-Банк', 'bank', 'Россия', 1, '2026-02-18 19:01:50'),
(8, 'Bybit', 'exchange', 'Международная', 1, '2026-02-18 19:01:50'),
(14, 'MetaMask', 'wallet', NULL, 1, '2026-02-19 13:56:39'),
(17, 'BonkBot', 'wallet', 'США (кошелек в телеграмме)', 1, '2026-03-22 04:37:38'),
(18, 'Rubby', 'wallet', 'США', 1, '2026-03-24 16:58:27');

-- --------------------------------------------------------

--
-- Структура таблицы `portfolio`
--

CREATE TABLE `portfolio` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `platform_id` int(11) NOT NULL,
  `quantity` decimal(20,8) NOT NULL,
  `average_buy_price` decimal(20,8) DEFAULT NULL,
  `currency_code` varchar(10) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `portfolio`
--

INSERT INTO `portfolio` (`id`, `asset_id`, `platform_id`, `quantity`, `average_buy_price`, `currency_code`, `notes`, `created_at`, `updated_at`) VALUES
(15, 1, 1, '65560.18877620', NULL, 'RUB', NULL, '2026-03-07 19:52:13', '2026-03-24 05:56:26'),
(16, 6, 8, '795.24365240', '89.86838087', 'RUB', NULL, '2026-03-07 19:53:44', '2026-03-24 05:57:15'),
(17, 17, 8, '58.31163000', '1.23680000', 'USDT', NULL, '2026-03-11 16:49:31', '2026-03-11 16:49:31'),
(18, 18, 8, '537.12143800', '0.13690000', 'USDT', NULL, '2026-03-11 17:15:22', '2026-03-11 17:15:22'),
(19, 19, 8, '16.06392000', '4.56500000', 'USDT', NULL, '2026-03-12 16:39:21', '2026-03-12 16:39:21'),
(20, 20, 8, '53.29389800', '1.38800000', 'USDT', NULL, '2026-03-12 16:45:59', '2026-03-12 16:45:59'),
(21, 21, 8, '12.91670800', '5.72700000', 'USDT', NULL, '2026-03-12 16:47:27', '2026-03-12 16:47:27'),
(22, 22, 8, '435.35494800', '0.16994000', 'USDT', NULL, '2026-03-15 16:57:27', '2026-03-15 16:57:27'),
(23, 23, 8, '74.65537800', '0.99100000', 'USDT', NULL, '2026-03-15 16:59:08', '2026-03-15 16:59:08'),
(24, 24, 8, '116.71254400', '0.84889781', 'USDT', NULL, '2026-03-15 17:00:30', '2026-03-22 05:00:55'),
(25, 25, 8, '139.69809000', '0.52960000', 'USDT', NULL, '2026-03-15 17:01:26', '2026-03-15 17:01:26'),
(26, 26, 8, '123.77680000', '0.19880000', 'USDT', NULL, '2026-03-15 17:02:23', '2026-03-15 17:02:23'),
(27, 6, 14, '1761.90130000', NULL, 'USD', NULL, '2026-03-15 17:07:48', '2026-03-22 05:24:24'),
(28, 5, 8, '0.01612603', '2711.72141951', 'USDT', NULL, '2026-03-22 04:03:09', '2026-03-24 16:54:51'),
(29, 27, 8, '0.00083880', '214.47000000', 'USDT', NULL, '2026-03-22 04:32:39', '2026-03-24 08:51:16'),
(30, 29, 8, '89.63000000', '0.83800000', 'USDT', NULL, '2026-03-22 04:48:23', '2026-03-22 04:48:23'),
(31, 30, 8, '281.54000000', '0.30236385', 'USDT', NULL, '2026-03-22 04:49:32', '2026-03-24 05:57:15'),
(32, 31, 8, '3.62400000', '20.72390000', 'USDT', NULL, '2026-03-22 04:50:47', '2026-03-22 04:50:47'),
(33, 32, 8, '0.00000018', '577.36000000', 'USDT', NULL, '2026-03-22 04:51:44', '2026-03-24 05:20:10'),
(34, 33, 8, '34.95000000', '0.71800000', 'USDT', NULL, '2026-03-22 05:01:52', '2026-03-22 05:01:52'),
(38, 32, 14, '0.01708882', NULL, NULL, NULL, '2026-03-24 05:20:10', '2026-03-24 05:20:10'),
(39, 4, 8, '0.00312600', '83455.96000000', 'USDT', NULL, '2026-03-24 05:26:04', '2026-03-24 05:26:04'),
(41, 5, 14, '0.16924697', '2711.72141951', 'USD', NULL, '2026-03-24 08:23:48', '2026-03-24 17:02:02'),
(42, 27, 17, '0.03289400', NULL, NULL, NULL, '2026-03-24 08:51:16', '2026-03-24 13:18:44'),
(43, 28, 17, '1.10310000', '0.09884800', 'SOL', 'В долларовом эквиваленте покупку совершил по цене $21.2000', '2026-03-24 12:24:19', '2026-03-24 13:08:54'),
(44, 34, 17, '122.00000000', '0.00089100', 'SOL', NULL, '2026-03-24 13:12:13', '2026-03-24 13:12:13'),
(45, 35, 17, '2770.00000000', '0.00003900', 'SOL', NULL, '2026-03-24 13:15:11', '2026-03-24 13:15:11'),
(46, 36, 17, '648.00000000', '0.00015200', 'SOL', NULL, '2026-03-24 13:18:44', '2026-03-24 13:18:44'),
(51, 5, 18, '0.04500000', NULL, 'USD', NULL, '2026-03-24 17:02:02', '2026-03-24 17:02:02');

-- --------------------------------------------------------

--
-- Структура таблицы `portfolio_structure`
--

CREATE TABLE `portfolio_structure` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `portfolio_structure`
--

INSERT INTO `portfolio_structure` (`id`, `category`, `percentage`, `description`, `updated_at`) VALUES
(1, 'Фондовый (EN)', '40.00', 'Иностранные акции и ETF', '2026-02-18 19:01:50'),
(2, 'Фондовый (РФ)', '35.00', 'Российские акции', '2026-02-18 19:01:50'),
(3, 'Крипто', '15.00', 'Криптовалюты', '2026-02-18 19:01:50'),
(4, 'Вклады', '7.00', 'Банковские депозиты', '2026-02-18 19:01:50'),
(5, 'Другие', '3.00', 'Прочие активы', '2026-02-18 19:01:50');

-- --------------------------------------------------------

--
-- Структура таблицы `stock_sectors_en`
--

CREATE TABLE `stock_sectors_en` (
  `id` int(11) NOT NULL,
  `sector_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `stock_sectors_ru`
--

CREATE TABLE `stock_sectors_ru` (
  `id` int(11) NOT NULL,
  `sector_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `trades`
--

CREATE TABLE `trades` (
  `id` int(11) NOT NULL,
  `operation_type` enum('buy','sell') NOT NULL,
  `asset_id` int(11) NOT NULL,
  `platform_id` int(11) NOT NULL,
  `from_platform_id` int(11) DEFAULT NULL,
  `quantity` decimal(20,8) NOT NULL,
  `price` decimal(20,8) NOT NULL,
  `price_currency` varchar(10) NOT NULL,
  `commission` decimal(20,8) DEFAULT '0.00000000',
  `commission_currency` varchar(10) DEFAULT NULL,
  `total_amount` decimal(20,8) GENERATED ALWAYS AS (((`quantity` * `price`) + `commission`)) STORED,
  `network` varchar(50) DEFAULT NULL,
  `operation_date` date NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `trades`
--

INSERT INTO `trades` (`id`, `operation_type`, `asset_id`, `platform_id`, `from_platform_id`, `quantity`, `price`, `price_currency`, `commission`, `commission_currency`, `network`, `operation_date`, `notes`, `created_at`) VALUES
(15, 'buy', 6, 8, 1, '100.00000000', '103.83000000', 'RUB', '0.00000000', NULL, 'NULL', '2025-01-27', '', '2026-03-07 19:53:44'),
(16, 'buy', 17, 8, 8, '58.31163000', '1.23680000', 'USDT', '0.00000000', 'USDT', NULL, '2025-01-27', '', '2026-03-11 16:49:31'),
(17, 'buy', 6, 8, 1, '196.73280000', '100.39000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-01-28', '', '2026-03-11 17:12:59'),
(18, 'buy', 18, 8, 8, '537.12143800', '0.13690000', 'USDT', '0.00000000', 'USDT', NULL, '2025-01-28', '', '2026-03-11 17:15:22'),
(19, 'buy', 19, 8, 8, '16.06392000', '4.56500000', 'USDT', '0.00000000', 'USDT', NULL, '2025-01-28', '', '2026-03-12 16:39:21'),
(20, 'buy', 6, 8, 1, '299.55070000', '100.15000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-01-28', '', '2026-03-12 16:42:21'),
(21, 'buy', 6, 8, 1, '466.53390000', '100.40000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-01-28', '', '2026-03-12 16:43:24'),
(22, 'buy', 20, 8, 8, '53.29389800', '1.38800000', 'USDT', '0.00000000', 'USDT', NULL, '2025-01-28', '', '2026-03-12 16:45:59'),
(23, 'buy', 21, 8, 8, '12.91670800', '5.72700000', 'USDT', '0.00000000', 'USDT', NULL, '2025-01-28', '', '2026-03-12 16:47:27'),
(24, 'buy', 22, 8, 8, '435.35494800', '0.16994000', 'USDT', '0.00000000', 'USDT', NULL, '2025-01-28', '', '2026-03-15 16:57:27'),
(25, 'buy', 23, 8, 8, '74.65537800', '0.99100000', 'USDT', '0.00000000', 'USDT', NULL, '2025-01-28', '', '2026-03-15 16:59:08'),
(26, 'buy', 24, 8, 8, '81.77254400', '0.90470000', 'USDT', '0.00000000', 'USDT', NULL, '2025-01-28', '', '2026-03-15 17:00:30'),
(27, 'buy', 25, 8, 8, '139.69809000', '0.52960000', 'USDT', '0.00000000', 'USDT', NULL, '2025-01-28', '', '2026-03-15 17:01:26'),
(28, 'buy', 26, 8, 8, '123.77680000', '0.19880000', 'USDT', '0.00000000', 'USDT', NULL, '2025-01-28', '', '2026-03-15 17:02:23'),
(29, 'buy', 6, 8, 1, '202.29610000', '99.30000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-01-30', '', '2026-03-15 17:03:16'),
(30, 'buy', 6, 8, 1, '142.30540000', '98.38000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-01-31', '', '2026-03-15 17:04:16'),
(31, 'buy', 6, 8, 1, '71.42860000', '98.00000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-01', '', '2026-03-15 17:05:25'),
(32, 'buy', 6, 8, 1, '51.07260000', '97.90000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-01', '', '2026-03-15 17:06:09'),
(33, 'buy', 5, 8, 8, '0.03694300', '3241.50000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-01', '', '2026-03-22 04:03:09'),
(34, 'buy', 6, 8, 1, '254.35320000', '98.32000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-01', '', '2026-03-22 04:10:52'),
(35, 'buy', 27, 8, 8, '0.46600000', '214.47000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-02', '', '2026-03-22 04:32:39'),
(36, 'buy', 6, 8, 1, '146.89660000', '89.90000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-03', '', '2026-03-22 04:43:17'),
(37, 'buy', 5, 8, 8, '0.09104000', '2809.73000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-04', '', '2026-03-22 04:45:29'),
(38, 'buy', 29, 8, 8, '89.63000000', '0.83800000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-04', '', '2026-03-22 04:48:23'),
(39, 'buy', 30, 8, 8, '236.26000000', '0.31790000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-04', '', '2026-03-22 04:49:32'),
(40, 'buy', 31, 8, 8, '3.62400000', '20.72390000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-04', '', '2026-03-22 04:50:47'),
(41, 'buy', 32, 8, 8, '0.01728900', '577.36000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-06', '', '2026-03-22 04:51:44'),
(42, 'buy', 6, 8, 1, '102.04081600', '98.00000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-08', '', '2026-03-22 04:52:45'),
(43, 'buy', 6, 8, 1, '30.51882000', '98.30000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-08', '', '2026-03-22 04:53:43'),
(44, 'buy', 6, 8, 1, '183.29938900', '98.20000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-08', '', '2026-03-22 04:54:47'),
(45, 'buy', 5, 8, 8, '0.05678000', '2641.39000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-11', '', '2026-03-22 04:58:21'),
(46, 'buy', 24, 8, 8, '34.94000000', '0.71830000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-11', '', '2026-03-22 05:00:55'),
(47, 'buy', 33, 8, 8, '34.95000000', '0.71800000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-11', '', '2026-03-22 05:01:52'),
(48, 'buy', 6, 8, 1, '160.61680000', '93.39000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-15', '', '2026-03-22 05:02:38'),
(51, 'buy', 6, 8, 1, '156.36800000', '92.73000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-11', '', '2026-03-22 05:04:32'),
(56, 'buy', 6, 8, 1, '86.78120000', '92.67000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-15', '', '2026-03-22 05:11:02'),
(57, 'buy', 6, 8, 1, '111.64860000', '92.63000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-15', '', '2026-03-22 05:13:31'),
(58, 'buy', 6, 8, 1, '78.34610000', '91.90000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-19', '', '2026-03-22 05:14:54'),
(59, 'buy', 6, 8, 1, '65.30260000', '90.90000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-20', '', '2026-03-22 05:15:26'),
(60, 'buy', 6, 8, 1, '33.26680000', '90.18000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-20', '', '2026-03-22 05:15:55'),
(61, 'buy', 6, 8, 1, '33.26320000', '90.19000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-20', '', '2026-03-22 05:16:24'),
(62, 'buy', 6, 8, 1, '15.69610000', '90.15000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-20', '', '2026-03-22 05:16:55'),
(63, 'buy', 6, 8, 1, '54.70810000', '88.89000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-22', '', '2026-03-22 05:21:39'),
(65, 'buy', 6, 8, 1, '562.74630000', '88.85000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-22', '', '2026-03-22 05:22:44'),
(75, 'buy', 6, 8, 1, '109.37330000', '91.43000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-02-28', '', '2026-03-24 05:25:07'),
(76, 'buy', 4, 8, 8, '0.00312600', '83455.96000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-28', '', '2026-03-24 05:26:04'),
(77, 'buy', 6, 8, 1, '22.24700000', '89.90000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-01', '', '2026-03-24 05:40:10'),
(78, 'buy', 6, 8, 1, '55.55560000', '90.00000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-01', '', '2026-03-24 05:40:45'),
(79, 'buy', 6, 8, 1, '57.05080000', '89.99000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-01', '', '2026-03-24 05:41:17'),
(80, 'buy', 6, 8, 1, '221.85250000', '90.15000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-01', '', '2026-03-24 05:41:57'),
(82, 'buy', 5, 8, 8, '0.04936000', '2215.35000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-28', '', '2026-03-24 05:43:10'),
(83, 'buy', 6, 8, 1, '53.81170000', '89.20000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-02', '', '2026-03-24 05:52:50'),
(84, 'buy', 6, 8, 1, '55.27780000', '90.00000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-09', '', '2026-03-24 05:54:04'),
(85, 'buy', 6, 8, 1, '110.91490000', '89.57000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-09', '', '2026-03-24 05:54:38'),
(86, 'buy', 6, 8, 1, '93.13620000', '89.89000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-09', '', '2026-03-24 05:55:13'),
(87, 'buy', 6, 8, 1, '11.15450000', '89.65000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-09', '', '2026-03-24 05:55:37'),
(88, 'buy', 6, 8, 1, '126.51410000', '89.50000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-09', '', '2026-03-24 05:56:02'),
(89, 'buy', 6, 8, 1, '17.16860000', '89.00000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-09', '', '2026-03-24 05:56:26'),
(90, 'buy', 30, 8, 8, '45.28000000', '0.22130000', 'USDT', '0.00000000', 'USDT', NULL, '2025-03-09', '', '2026-03-24 05:57:15'),
(91, 'buy', 28, 17, 17, '1.10310000', '0.09884800', 'SOL', '0.00000000', 'SOL', NULL, '2025-02-02', '', '2026-03-24 12:24:19'),
(93, 'buy', 34, 17, 17, '122.00000000', '0.00089100', 'SOL', '0.00000000', 'SOL', NULL, '2025-02-02', 'в эквиваленте USDT сделка совершена по цене $0.1910', '2026-03-24 13:12:13'),
(94, 'buy', 35, 17, 17, '2770.00000000', '0.00003900', 'SOL', '0.00000000', 'SOL', NULL, '2025-02-02', 'в эквиваленте USDT покупка совершена по цене $0.0083', '2026-03-24 13:15:11'),
(95, 'buy', 36, 17, 17, '648.00000000', '0.00015200', 'SOL', '0.00000000', 'SOL', NULL, '2025-02-02', 'в эквиваленте USDT покупка совершена по цене $0.0327', '2026-03-24 13:18:44');

-- --------------------------------------------------------

--
-- Структура таблицы `transfers`
--

CREATE TABLE `transfers` (
  `id` int(11) NOT NULL,
  `from_platform_id` int(11) NOT NULL,
  `to_platform_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `quantity` decimal(20,8) NOT NULL,
  `commission` decimal(20,8) DEFAULT '0.00000000',
  `commission_currency` varchar(10) DEFAULT NULL,
  `from_network` varchar(50) DEFAULT NULL,
  `to_network` varchar(50) DEFAULT NULL,
  `transfer_date` date NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `transfers`
--

INSERT INTO `transfers` (`id`, `from_platform_id`, `to_platform_id`, `asset_id`, `quantity`, `commission`, `commission_currency`, `from_network`, `to_network`, `transfer_date`, `notes`, `created_at`) VALUES
(1, 8, 14, 6, '9.00000000', '1.00000000', 'USDT', 'BEP20', 'BEP20', '2025-02-01', '', '2026-03-15 17:07:48'),
(4, 8, 14, 6, '100.00000000', '1.00000000', 'USDT', 'BEP20', 'BEP20', '2025-02-21', '', '2026-03-22 05:19:37'),
(5, 8, 14, 6, '1036.44690000', '1.00000000', 'USDT', 'BEP20', 'BEP20', '2025-02-21', '', '2026-03-22 05:20:37'),
(6, 8, 14, 6, '616.45440000', '1.00000000', 'USDT', 'BEP20', 'BEP20', '2025-02-22', '', '2026-03-22 05:24:24'),
(7, 8, 14, 32, '0.01708882', '0.00020000', 'BNB', 'BEP20', 'BEP20', '2025-02-08', '', '2026-03-24 05:20:10'),
(8, 8, 14, 5, '0.03544338', '0.00150000', 'ETH', 'ERC20', 'ERC20', '2025-02-01', '', '2026-03-24 08:23:48'),
(11, 8, 17, 27, '0.45716120', '0.00800000', 'SOL', 'SOL', 'SOL', '2025-03-02', '', '2026-03-24 08:51:16'),
(12, 8, 14, 5, '0.02996000', '0.00004000', 'ETH', 'ARBITRUM ONE', 'ARBITRUM ONE', '2025-02-15', '', '2026-03-24 13:21:10'),
(13, 8, 14, 5, '0.14951359', '0.00150000', 'ETH', 'ERC20', 'ERC20', '2025-02-21', '', '2026-03-24 13:36:48'),
(19, 8, 14, 5, '0.04923115', '0.00004000', 'ETH', 'ARBITRUM ONE', 'ARBITRUM ONE', '2025-03-02', '', '2026-03-24 16:54:51'),
(21, 14, 18, 5, '0.04500000', '0.00063000', 'ETH', 'ERC20', 'ERC20', '2025-03-02', '', '2026-03-24 17:02:02');

-- --------------------------------------------------------

--
-- Структура таблицы `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `user_settings`
--

INSERT INTO `user_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'theme', 'dark', '2026-03-19 15:56:54');

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_portfolio_summary`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_portfolio_summary` (
`average_buy_price` decimal(20,8)
,`currency_code` varchar(10)
,`id` int(11)
,`name` varchar(100)
,`quantity` decimal(20,8)
,`sector` varchar(50)
,`symbol` varchar(20)
,`type` enum('stock','bond','crypto','currency','deposit','etf','other')
,`value_in_usd` decimal(40,16)
);

-- --------------------------------------------------------

--
-- Структура для представления `v_portfolio_summary`
--
DROP TABLE IF EXISTS `v_portfolio_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_portfolio_summary`  AS  select `a`.`id` AS `id`,`a`.`symbol` AS `symbol`,`a`.`name` AS `name`,`a`.`type` AS `type`,`a`.`sector` AS `sector`,`p`.`quantity` AS `quantity`,`p`.`average_buy_price` AS `average_buy_price`,`p`.`currency_code` AS `currency_code`,(case when (`a`.`currency_code` = 'USD') then `p`.`quantity` when (`a`.`currency_code` = 'RUB') then (`p`.`quantity` / coalesce((select `exchange_rates`.`rate` from `exchange_rates` where ((`exchange_rates`.`from_currency` = 'USD') and (`exchange_rates`.`to_currency` = 'RUB') and (`exchange_rates`.`date` = curdate()))),92.50)) else (`p`.`quantity` * coalesce((select `exchange_rates`.`rate` from `exchange_rates` where ((`exchange_rates`.`from_currency` = `a`.`currency_code`) and (`exchange_rates`.`to_currency` = 'USD') and (`exchange_rates`.`date` = curdate()))),1)) end) AS `value_in_usd` from (`portfolio` `p` join `assets` `a` on((`p`.`asset_id` = `a`.`id`))) ;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `action_plan`
--
ALTER TABLE `action_plan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_completed` (`is_completed`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_due_date` (`due_date`);

--
-- Индексы таблицы `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `symbol` (`symbol`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_symbol` (`symbol`);

--
-- Индексы таблицы `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`code`);

--
-- Индексы таблицы `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currency_code` (`currency_code`),
  ADD KEY `idx_date` (`deposit_date`),
  ADD KEY `idx_platform` (`platform_id`),
  ADD KEY `idx_deposits_date_platform` (`deposit_date`,`platform_id`);

--
-- Индексы таблицы `deposit_currencies`
--
ALTER TABLE `deposit_currencies`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `exchange_rates`
--
ALTER TABLE `exchange_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rate` (`from_currency`,`to_currency`,`date`),
  ADD KEY `to_currency` (`to_currency`),
  ADD KEY `idx_date` (`date`);

--
-- Индексы таблицы `limit_orders`
--
ALTER TABLE `limit_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `platform_id` (`platform_id`),
  ADD KEY `price_currency` (`price_currency`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_date`);

--
-- Индексы таблицы `networks`
--
ALTER TABLE `networks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Индексы таблицы `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`note_type`),
  ADD KEY `idx_reminder` (`reminder_date`),
  ADD KEY `idx_archived` (`is_archived`);

--
-- Индексы таблицы `platforms`
--
ALTER TABLE `platforms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `portfolio`
--
ALTER TABLE `portfolio`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_asset_platform` (`asset_id`,`platform_id`),
  ADD KEY `currency_code` (`currency_code`),
  ADD KEY `idx_platform` (`platform_id`),
  ADD KEY `idx_portfolio_asset` (`asset_id`),
  ADD KEY `idx_portfolio_platform` (`platform_id`);

--
-- Индексы таблицы `portfolio_structure`
--
ALTER TABLE `portfolio_structure`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_category` (`category`);

--
-- Индексы таблицы `stock_sectors_en`
--
ALTER TABLE `stock_sectors_en`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `stock_sectors_ru`
--
ALTER TABLE `stock_sectors_ru`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `trades`
--
ALTER TABLE `trades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `price_currency` (`price_currency`),
  ADD KEY `commission_currency` (`commission_currency`),
  ADD KEY `idx_date` (`operation_date`),
  ADD KEY `idx_asset` (`asset_id`),
  ADD KEY `idx_platform` (`platform_id`),
  ADD KEY `idx_type` (`operation_type`),
  ADD KEY `idx_trades_date_platform` (`operation_date`,`platform_id`),
  ADD KEY `from_platform_id` (`from_platform_id`);

--
-- Индексы таблицы `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `commission_currency` (`commission_currency`),
  ADD KEY `idx_date` (`transfer_date`),
  ADD KEY `idx_from_platform` (`from_platform_id`),
  ADD KEY `idx_to_platform` (`to_platform_id`),
  ADD KEY `idx_transfers_date` (`transfer_date`);

--
-- Индексы таблицы `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting` (`setting_key`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `action_plan`
--
ALTER TABLE `action_plan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT для таблицы `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `deposit_currencies`
--
ALTER TABLE `deposit_currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `exchange_rates`
--
ALTER TABLE `exchange_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `limit_orders`
--
ALTER TABLE `limit_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `networks`
--
ALTER TABLE `networks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `platforms`
--
ALTER TABLE `platforms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT для таблицы `portfolio`
--
ALTER TABLE `portfolio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT для таблицы `portfolio_structure`
--
ALTER TABLE `portfolio_structure`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `stock_sectors_en`
--
ALTER TABLE `stock_sectors_en`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `stock_sectors_ru`
--
ALTER TABLE `stock_sectors_ru`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `trades`
--
ALTER TABLE `trades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT для таблицы `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT для таблицы `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `deposits`
--
ALTER TABLE `deposits`
  ADD CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`),
  ADD CONSTRAINT `deposits_ibfk_2` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`);

--
-- Ограничения внешнего ключа таблицы `exchange_rates`
--
ALTER TABLE `exchange_rates`
  ADD CONSTRAINT `exchange_rates_ibfk_1` FOREIGN KEY (`from_currency`) REFERENCES `currencies` (`code`),
  ADD CONSTRAINT `exchange_rates_ibfk_2` FOREIGN KEY (`to_currency`) REFERENCES `currencies` (`code`);

--
-- Ограничения внешнего ключа таблицы `limit_orders`
--
ALTER TABLE `limit_orders`
  ADD CONSTRAINT `limit_orders_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `limit_orders_ibfk_2` FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`),
  ADD CONSTRAINT `limit_orders_ibfk_3` FOREIGN KEY (`price_currency`) REFERENCES `currencies` (`code`);

--
-- Ограничения внешнего ключа таблицы `portfolio`
--
ALTER TABLE `portfolio`
  ADD CONSTRAINT `portfolio_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `portfolio_ibfk_2` FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`),
  ADD CONSTRAINT `portfolio_ibfk_3` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`);

--
-- Ограничения внешнего ключа таблицы `trades`
--
ALTER TABLE `trades`
  ADD CONSTRAINT `trades_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `trades_ibfk_2` FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`),
  ADD CONSTRAINT `trades_ibfk_3` FOREIGN KEY (`price_currency`) REFERENCES `currencies` (`code`),
  ADD CONSTRAINT `trades_ibfk_4` FOREIGN KEY (`commission_currency`) REFERENCES `currencies` (`code`),
  ADD CONSTRAINT `trades_ibfk_5` FOREIGN KEY (`from_platform_id`) REFERENCES `platforms` (`id`);

--
-- Ограничения внешнего ключа таблицы `transfers`
--
ALTER TABLE `transfers`
  ADD CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`from_platform_id`) REFERENCES `platforms` (`id`),
  ADD CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`to_platform_id`) REFERENCES `platforms` (`id`),
  ADD CONSTRAINT `transfers_ibfk_3` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `transfers_ibfk_4` FOREIGN KEY (`commission_currency`) REFERENCES `currencies` (`code`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
