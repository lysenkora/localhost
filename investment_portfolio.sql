-- phpMyAdmin SQL Dump
-- version 4.8.3
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Апр 04 2026 г., 10:39
-- Версия сервера: 8.0.12
-- Версия PHP: 7.2.10

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
(17, 'STX', 'Stacks', 'crypto', NULL, 'USD', 1, '2026-03-11 16:00:07'),
(18, 'ZK', 'ZKsync', 'crypto', NULL, 'USD', 1, '2026-03-11 17:14:42'),
(19, 'FIL', 'Filecoin', 'crypto', NULL, 'USD', 1, '2026-03-12 16:38:18'),
(20, 'ONDO', 'Ondo', 'crypto', NULL, 'USD', 1, '2026-03-12 16:43:59'),
(21, 'RENDER', 'RENDER', 'crypto', NULL, 'USD', 1, '2026-03-12 16:46:39'),
(22, 'GRT', 'The Graph', 'crypto', NULL, 'USD', 1, '2026-03-15 16:56:55'),
(23, 'TWT', 'Trust Wallet Token', 'crypto', NULL, 'USD', 1, '2026-03-15 16:58:35'),
(24, 'APE', 'ApeCoin', 'crypto', NULL, 'USD', 1, '2026-03-15 17:00:05'),
(25, 'CELO', 'Celo', 'crypto', NULL, 'USD', 1, '2026-03-15 17:00:56'),
(26, 'GOAT', 'Goatseus Maximus', 'crypto', NULL, 'USD', 1, '2026-03-15 17:01:55'),
(27, 'SOL', 'Solana', 'crypto', NULL, 'USD', 1, '2026-03-22 04:30:49'),
(28, 'TRUMP', 'TRUMP', 'crypto', NULL, 'USD', 1, '2026-03-22 04:39:01'),
(29, 'IMX', 'Immutable', 'crypto', NULL, 'USD', 1, '2026-03-22 04:47:40'),
(30, 'POL', 'Polygon (prev. MATIC)', 'crypto', NULL, 'USD', 1, '2026-03-22 04:48:55'),
(31, 'LINK', 'Chainlink', 'crypto', NULL, 'USD', 1, '2026-03-22 04:50:19'),
(32, 'BNB', 'BNB', 'crypto', NULL, 'USD', 1, '2026-03-22 04:51:15'),
(33, 'ARKM', 'Arkham', 'crypto', NULL, 'USD', 1, '2026-03-22 05:01:19'),
(34, 'ARC', 'ARCReactorAI', 'crypto', NULL, 'USD', 1, '2026-03-24 12:50:20'),
(35, 'NEUR', 'neur.sh', 'crypto', NULL, 'USD', 1, '2026-03-24 13:14:05'),
(36, 'ALCH', 'ALCH', 'crypto', NULL, 'USD', 1, '2026-03-24 13:17:28'),
(37, 'HIMS.US', 'HIMS', 'stock', 'Healthcare', 'USD', 1, '2026-03-26 13:18:45'),
(38, 'COIN.US', 'Coinbase Global Inc', 'stock', 'Technology', 'USD', 1, '2026-03-26 18:42:35'),
(50, 'ASTER', 'Aster', 'crypto', '', 'USD', 1, '2026-03-27 18:41:07'),
(51, 'CRV', 'Curve DAO Token', 'crypto', '', 'USD', 1, '2026-03-27 18:46:07'),
(52, 'PUMP', 'Pump.fun', 'crypto', '', 'USD', 1, '2026-03-27 18:49:19'),
(53, 'ES', 'Eclipse', 'crypto', NULL, 'ES', 1, '2026-03-31 11:34:22');

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
('ASTER', 'Aster', NULL, 'crypto'),
('BNB', 'BNB', NULL, 'crypto'),
('BTC', 'Биткоин', '₿', 'crypto'),
('CELO', 'Celo', NULL, 'crypto'),
('CRV', 'Curve DAO Token', NULL, 'crypto'),
('ES', 'Eclipse', '', 'crypto'),
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
('PUMP', 'Pump.fun', NULL, 'crypto'),
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
(10, 1, '500000.00000000', 'RUB', '2025-01-25', '', '2026-03-07 19:52:13'),
(11, 1, '5200.00000000', 'RUB', '2025-03-14', '', '2026-03-24 17:12:11'),
(12, 1, '4991.00000000', 'RUB', '2025-03-29', '', '2026-03-24 17:38:44'),
(13, 1, '11138.00000000', 'RUB', '2025-04-06', '', '2026-03-24 17:54:12'),
(14, 1, '2137.00000000', 'RUB', '2025-04-13', '', '2026-03-24 17:54:38'),
(15, 1, '8200.00000000', 'RUB', '2025-04-18', '', '2026-03-24 17:54:57'),
(16, 1, '44606.00000000', 'RUB', '2025-05-17', '', '2026-03-24 17:56:29'),
(17, 1, '62493.00000000', 'RUB', '2025-06-09', '', '2026-03-24 17:57:26'),
(18, 1, '12216.00000000', 'RUB', '2025-06-29', '', '2026-03-24 17:57:45'),
(19, 1, '17042.00000000', 'RUB', '2025-08-05', '', '2026-03-24 17:58:10'),
(20, 1, '65492.00000000', 'RUB', '2025-09-28', '', '2026-03-24 17:58:43'),
(21, 1, '35000.00000000', 'RUB', '2025-08-28', '', '2026-03-26 09:24:40'),
(22, 1, '111112.00000000', 'RUB', '2025-09-30', '', '2026-03-26 09:25:02'),
(23, 1, '100009.00000000', 'RUB', '2026-02-16', '', '2026-03-27 17:56:27'),
(24, 1, '98891.00000000', 'RUB', '2026-02-18', '', '2026-03-27 17:56:52'),
(25, 8, '75.00000000', 'USDT', '2025-11-21', '', '2026-03-27 18:48:22'),
(26, 8, '40.00000000', 'ES', '2025-08-05', 'получил ретродроп от Eclipse', '2026-03-31 11:38:43');

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
-- Структура таблицы `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `expense_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `expenses`
--

INSERT INTO `expenses` (`id`, `amount`, `currency_code`, `category_id`, `description`, `expense_date`, `created_at`, `updated_at`) VALUES
(1, '0.01', 'USD', 7, 'Расходы на ретродроп Eclipse', '2025-08-05', '2026-03-31 11:31:53', '2026-03-31 11:31:53');

-- --------------------------------------------------------

--
-- Структура таблицы `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ru` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'fas fa-tag',
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#ff9f4a',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `name`, `name_ru`, `icon`, `color`, `is_active`, `sort_order`, `created_at`) VALUES
(7, 'retrodrop', 'Ретродроп', 'fas fa-gift', '#ff9f4a', 1, 1, '2026-03-31 11:22:28');

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
(8, 'OPTIMISM', 'fas fa-chart-line', '#ff0420', 'Optimism', 1, '2026-03-23 08:06:45'),
(9, 'AVALANCHE', 'fas fa-chart-line', '#e84142', 'Avalanche C-Chain', 1, '2026-03-23 08:06:45'),
(10, 'FTM', 'fas fa-chart-line', '#1969ff', 'Fantom', 1, '2026-03-23 08:06:45'),
(11, 'ARBITRUM', 'fas fa-network-wired', '#ff9f4a', 'Arbitrum', 1, '2026-03-24 13:20:40'),
(12, 'ABSTRACT', 'fas fa-network-wired', '#ff9f4a', 'Abstract', 1, '2026-03-28 13:19:32');

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
(18, 'Rubby', 'wallet', 'США', 1, '2026-03-24 16:58:27'),
(19, 'Freedom Global (фонда)', 'broker', 'Казахстан', 1, '2026-03-26 09:25:37'),
(20, 'Abstract', 'wallet', 'США', 1, '2026-03-27 18:17:35'),
(21, 'TrustWallet', 'wallet', 'США', 1, '2026-03-27 18:24:51'),
(22, 'CifraMarkets (крипта)', 'broker', 'Казахстан', 1, '2026-03-28 12:00:25');

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
(15, 1, 1, '201264.50783020', NULL, 'RUB', NULL, '2026-03-07 19:52:13', '2026-03-27 17:59:16'),
(16, 6, 8, '207.89505300', '90.72821098', 'RUB', NULL, '2026-03-07 19:53:44', '2026-04-01 17:39:10'),
(17, 17, 8, '58.31163000', '1.23680000', 'USDT', NULL, '2026-03-11 16:49:31', '2026-03-11 16:49:31'),
(18, 18, 8, '537.12143800', '0.13690000', 'USDT', NULL, '2026-03-11 17:15:22', '2026-03-11 17:15:22'),
(19, 19, 8, '61.41392000', '2.87103713', 'USDT', NULL, '2026-03-12 16:39:21', '2026-03-27 18:28:18'),
(20, 20, 8, '297.70389800', '0.90688349', 'USDT', NULL, '2026-03-12 16:45:59', '2026-03-27 18:29:12'),
(21, 21, 8, '12.91670800', '5.72700000', 'USDT', NULL, '2026-03-12 16:47:27', '2026-03-12 16:47:27'),
(22, 22, 8, '435.35494800', '0.16994000', 'USDT', NULL, '2026-03-15 16:57:27', '2026-03-15 16:57:27'),
(23, 23, 8, '350.91537800', '0.81208923', 'USDT', NULL, '2026-03-15 16:59:08', '2026-03-27 18:27:18'),
(24, 24, 8, '116.71254400', '0.84889781', 'USDT', NULL, '2026-03-15 17:00:30', '2026-03-22 05:00:55'),
(25, 25, 8, '139.69809000', '0.52960000', 'USDT', NULL, '2026-03-15 17:01:26', '2026-03-15 17:01:26'),
(26, 26, 8, '123.77680000', '0.19880000', 'USDT', NULL, '2026-03-15 17:02:23', '2026-03-15 17:02:23'),
(27, 6, 14, '393.95890000', '90.72821098', 'USD', NULL, '2026-03-15 17:07:48', '2026-03-27 18:21:01'),
(28, 5, 8, '0.29712100', '2291.33906594', 'USDT', NULL, '2026-03-22 04:03:09', '2026-04-01 17:38:31'),
(29, 27, 8, '2.41173880', '136.77530023', 'USDT', NULL, '2026-03-22 04:32:39', '2026-03-27 18:47:43'),
(30, 29, 8, '89.63000000', '0.83800000', 'USDT', NULL, '2026-03-22 04:48:23', '2026-03-22 04:48:23'),
(31, 30, 8, '400.95000000', '0.24821092', 'USDT', NULL, '2026-03-22 04:49:32', '2026-03-27 18:23:51'),
(32, 31, 8, '7.07700000', '16.38423151', 'USDT', NULL, '2026-03-22 04:50:47', '2026-03-27 19:00:46'),
(33, 32, 8, '0.00000018', '577.36000000', 'USDT', NULL, '2026-03-22 04:51:44', '2026-03-24 05:20:10'),
(34, 33, 8, '34.95000000', '0.71800000', 'USDT', NULL, '2026-03-22 05:01:52', '2026-03-22 05:01:52'),
(38, 32, 14, '0.01708882', NULL, NULL, NULL, '2026-03-24 05:20:10', '2026-03-24 05:20:10'),
(39, 4, 8, '0.01098316', '94695.13176328', 'USDT', NULL, '2026-03-24 05:26:04', '2026-03-27 18:50:08'),
(41, 5, 14, '0.13958697', '2711.72141951', 'USD', NULL, '2026-03-24 08:23:48', '2026-03-28 13:20:17'),
(42, 27, 17, '0.58304600', NULL, NULL, NULL, '2026-03-24 08:51:16', '2026-03-28 12:49:49'),
(43, 28, 17, '1.10310000', '0.09884800', 'SOL', 'В долларовом эквиваленте покупку совершил по цене $21.2000', '2026-03-24 12:24:19', '2026-03-24 13:08:54'),
(44, 34, 17, '122.00000000', '0.00089100', 'SOL', NULL, '2026-03-24 13:12:13', '2026-03-24 13:12:13'),
(45, 35, 17, '2770.00000000', '0.00003900', 'SOL', NULL, '2026-03-24 13:15:11', '2026-03-24 13:15:11'),
(51, 5, 18, '0.04500000', NULL, 'USD', NULL, '2026-03-24 17:02:02', '2026-03-24 17:02:02'),
(52, 30, 18, '44.80000000', NULL, NULL, NULL, '2026-03-24 17:10:06', '2026-03-24 17:10:06'),
(66, 2, 19, '3971.42000000', '82.18015842', 'RUB', NULL, '2026-03-26 09:26:31', '2026-03-27 17:59:16'),
(67, 37, 19, '2.00000000', '44.91000000', 'USD', NULL, '2026-03-26 13:19:24', '2026-03-26 13:19:24'),
(68, 38, 19, '1.00000000', '240.52000000', 'USD', NULL, '2026-03-26 19:07:20', '2026-03-26 19:07:20'),
(72, 4, 21, '0.00794002', NULL, 'USD', NULL, '2026-03-27 18:25:46', '2026-03-27 18:25:46'),
(73, 50, 8, '96.82000000', '1.28070000', 'USDT', NULL, '2026-03-27 18:41:47', '2026-03-27 18:41:47'),
(74, 51, 8, '200.20000000', '0.41450000', 'USDT', NULL, '2026-03-27 18:46:36', '2026-03-27 18:46:36'),
(76, 52, 8, '24333.50000000', '0.00262000', 'USDT', NULL, '2026-03-27 18:49:39', '2026-03-27 18:49:39'),
(80, 5, 20, '0.02963000', NULL, 'USD', NULL, '2026-03-28 13:20:17', '2026-03-28 13:20:17'),
(81, 53, 8, '40.00000000', NULL, 'ES', NULL, '2026-03-31 11:38:43', '2026-03-31 11:38:43');

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
-- Структура таблицы `sectors`
--

CREATE TABLE `sectors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `name_ru` varchar(100) NOT NULL,
  `type` enum('stock','etf','bond') DEFAULT 'stock',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `sectors`
--

INSERT INTO `sectors` (`id`, `name`, `name_ru`, `type`, `is_active`, `created_at`) VALUES
(1, 'Technology', 'Технологии', 'stock', 1, '2026-03-26 17:33:57'),
(2, 'Healthcare', 'Здравоохранение', 'stock', 1, '2026-03-26 17:33:57'),
(3, 'Financial', 'Финансы', 'stock', 1, '2026-03-26 17:33:57'),
(4, 'Consumer', 'Потребительский сектор', 'stock', 1, '2026-03-26 17:33:57'),
(5, 'Energy', 'Энергетика', 'stock', 1, '2026-03-26 17:33:57'),
(6, 'Industrial', 'Промышленность', 'stock', 1, '2026-03-26 17:33:57'),
(7, 'Telecom', 'Телекоммуникации', 'stock', 1, '2026-03-26 17:33:57'),
(8, 'Utilities', 'Коммунальные услуги', 'stock', 1, '2026-03-26 17:33:57'),
(9, 'Real Estate', 'Недвижимость', 'stock', 1, '2026-03-26 17:33:57'),
(10, 'Materials', 'Материалы', 'stock', 1, '2026-03-26 17:33:57'),
(11, 'Other', 'Другое', 'stock', 1, '2026-03-26 17:33:57');

-- --------------------------------------------------------

--
-- Структура таблицы `sold_lots`
--

CREATE TABLE `sold_lots` (
  `id` int(11) NOT NULL,
  `sell_trade_id` int(11) NOT NULL COMMENT 'ID сделки продажи из trades',
  `buy_trade_id` int(11) NOT NULL COMMENT 'ID сделки покупки из trades',
  `quantity` decimal(20,8) NOT NULL COMMENT 'Количество, проданное из этого лота',
  `buy_price` decimal(20,8) NOT NULL COMMENT 'Цена покупки этого лота на момент сделки',
  `buy_price_currency` varchar(10) NOT NULL COMMENT 'Валюта цены покупки',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(95, 'buy', 36, 17, 17, '648.00000000', '0.00015200', 'SOL', '0.00000000', 'SOL', NULL, '2025-02-02', 'в эквиваленте USDT покупка совершена по цене $0.0327', '2026-03-24 13:18:44'),
(96, 'buy', 4, 8, 8, '0.00316900', '82353.46000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-03-09', '', '2026-03-24 17:10:59'),
(97, 'buy', 6, 8, 1, '114.60010000', '87.26000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-15', '', '2026-03-24 17:13:06'),
(98, 'buy', 6, 8, 1, '84.45720000', '87.50000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-15', '', '2026-03-24 17:13:51'),
(99, 'buy', 6, 8, 1, '198.73420000', '86.90000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-16', '', '2026-03-24 17:14:38'),
(100, 'buy', 5, 8, 8, '0.04066000', '1967.53000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-28', '', '2026-03-24 17:37:31'),
(101, 'buy', 23, 8, 8, '89.96000000', '0.88920000', 'USDT', '0.00000000', 'USDT', NULL, '2025-02-28', '', '2026-03-24 17:38:12'),
(102, 'buy', 6, 8, 1, '90.52720000', '84.98000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-30', '', '2026-03-24 17:47:52'),
(103, 'buy', 6, 8, 1, '91.51030000', '84.69000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-30', '', '2026-03-24 17:48:59'),
(104, 'buy', 6, 8, 1, '62.86550000', '85.50000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-30', '', '2026-03-24 17:49:30'),
(105, 'buy', 6, 8, 1, '60.37520000', '85.30000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-30', '', '2026-03-24 17:50:09'),
(106, 'buy', 6, 8, 1, '64.21430000', '84.95000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-30', '', '2026-03-24 17:50:40'),
(107, 'buy', 6, 8, 1, '59.10170000', '84.60000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-03-30', '', '2026-03-24 17:51:14'),
(108, 'buy', 6, 8, 1, '129.66560000', '82.52000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-04-21', '', '2026-03-24 17:55:31'),
(110, 'buy', 6, 8, 1, '192.75100000', '83.15000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-04-21', '', '2026-03-24 17:56:58'),
(111, 'buy', 27, 8, 8, '0.64400000', '125.72000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-03-30', '', '2026-03-24 18:00:47'),
(112, 'buy', 5, 8, 8, '0.07361000', '1833.85000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-03-30', '', '2026-03-24 18:01:16'),
(113, 'buy', 31, 8, 8, '3.45300000', '13.72000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-03-30', '', '2026-03-24 18:02:05'),
(114, 'buy', 2, 19, 1, '421.95000000', '82.95000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-08-28', '', '2026-03-26 09:26:31'),
(115, 'buy', 2, 19, 1, '1313.38000000', '84.60000000', 'RUB', '0.00000000', 'RUB', NULL, '2025-09-30', '', '2026-03-26 09:29:11'),
(116, 'buy', 37, 19, 19, '2.00000000', '44.91000000', 'USD', '0.00000000', 'USD', NULL, '2025-08-28', '', '2026-03-26 13:19:24'),
(117, 'buy', 38, 19, 19, '1.00000000', '240.52000000', 'USD', '0.00000000', 'USD', NULL, '2025-12-19', '', '2026-03-26 19:07:20'),
(119, 'buy', 2, 19, 1, '1288.11000000', '77.64000000', 'RUB', '0.00000000', 'RUB', NULL, '2026-02-16', '', '2026-03-27 17:58:05'),
(120, 'buy', 2, 19, 1, '1278.32000000', '77.36000000', 'RUB', '0.00000000', 'RUB', NULL, '2026-02-18', '', '2026-03-27 17:59:16'),
(121, 'buy', 31, 8, 8, '2.45000000', '13.72000000', 'USDT', '0.00000000', 'USDT', NULL, '2026-03-30', '', '2026-03-27 18:15:40'),
(122, 'buy', 4, 8, 8, '0.00176800', '79164.60000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-04-06', '', '2026-03-27 18:21:53'),
(123, 'buy', 5, 8, 8, '0.08657000', '1617.15000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-04-06', '', '2026-03-27 18:22:30'),
(124, 'buy', 27, 8, 8, '0.77800000', '107.88000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-04-06', '', '2026-03-27 18:23:08'),
(125, 'buy', 30, 8, 8, '164.41000000', '0.17030000', 'USDT', '0.00000000', 'USDT', NULL, '2025-04-06', '', '2026-03-27 18:23:51'),
(126, 'buy', 23, 8, 8, '38.10000000', '0.73490000', 'USDT', '0.00000000', 'USDT', NULL, '2025-04-06', '', '2026-03-27 18:24:31'),
(127, 'buy', 20, 8, 8, '110.33000000', '0.84290000', 'USDT', '0.00000000', 'USDT', NULL, '2025-06-09', '', '2026-03-27 18:26:47'),
(128, 'buy', 23, 8, 8, '148.20000000', '0.69500000', 'USDT', '0.00000000', 'USDT', NULL, '2025-06-29', '', '2026-03-27 18:27:18'),
(130, 'buy', 19, 8, 8, '45.35000000', '2.27100000', 'USDT', '0.00000000', 'USDT', NULL, '2025-06-29', '', '2026-03-27 18:28:18'),
(131, 'buy', 20, 8, 8, '134.08000000', '0.76830000', 'USDT', '0.00000000', 'USDT', NULL, '2025-06-29', '', '2026-03-27 18:29:12'),
(132, 'buy', 5, 8, 8, '0.03352000', '2809.73000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-08-24', '', '2026-03-27 18:30:42'),
(133, 'buy', 4, 8, 8, '0.00171200', '110344.40000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-09-28', '', '2026-03-27 18:31:25'),
(134, 'buy', 5, 8, 8, '0.03190000', '3886.65000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-10-16', '', '2026-03-27 18:32:11'),
(135, 'buy', 27, 8, 8, '0.66150000', '187.43000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-10-16', '', '2026-03-27 18:32:57'),
(136, 'buy', 50, 8, 8, '96.82000000', '1.28070000', 'USDT', '0.00000000', 'USDT', NULL, '2025-10-16', '', '2026-03-27 18:41:47'),
(137, 'buy', 4, 8, 8, '0.00296000', '101467.00000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-11-04', '', '2026-03-27 18:42:24'),
(138, 'buy', 4, 8, 8, '0.00211718', '94278.00000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-11-14', '', '2026-03-27 18:43:48'),
(139, 'buy', 4, 8, 8, '0.00169900', '88267.90000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-11-20', '', '2026-03-27 18:44:23'),
(140, 'buy', 5, 8, 8, '0.05230000', '2868.02000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-11-20', '', '2026-03-27 18:45:07'),
(141, 'buy', 51, 8, 8, '200.20000000', '0.41450000', 'USDT', '0.00000000', 'USDT', NULL, '2025-11-20', '', '2026-03-27 18:46:36'),
(142, 'buy', 4, 8, 8, '0.00061300', '81475.70000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-11-21', '', '2026-03-27 18:47:09'),
(143, 'buy', 27, 8, 8, '0.32740000', '124.64000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-11-21', '', '2026-03-27 18:47:43'),
(144, 'buy', 52, 8, 8, '24333.50000000', '0.00262000', 'USDT', '0.00000000', 'USDT', NULL, '2025-11-23', '', '2026-03-27 18:49:39'),
(145, 'buy', 4, 8, 8, '0.00187000', '80375.00000000', 'USDT', '0.00000000', 'USDT', NULL, '2026-01-31', '', '2026-03-27 18:50:08'),
(147, 'sell', 31, 8, 8, '2.45000000', '25.69400000', 'USDT', '0.00000000', 'USDT', NULL, '2025-08-24', '', '2026-03-27 19:00:46'),
(148, 'sell', 5, 8, 8, '0.03138000', '4509.76000000', 'USDT', '0.00000000', 'USDT', NULL, '2025-10-16', '', '2026-03-27 19:04:07'),
(149, 'sell', 36, 17, 17, '648.00000000', '0.00084900', 'SOL', '0.00000000', 'SOL', NULL, '2025-06-29', '', '2026-03-28 12:49:48');

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
(21, 14, 18, 5, '0.04500000', '0.00063000', 'ETH', 'ERC20', 'ERC20', '2025-03-02', '', '2026-03-24 17:02:02'),
(22, 8, 18, 30, '44.80000000', '0.20000000', 'POL', 'POLYGON', 'POLYGON', '2025-03-09', '', '2026-03-24 17:10:06'),
(23, 8, 14, 6, '1132.05760000', '1.00000000', 'USDT', 'BEP20', 'BEP20', '2025-03-16', '', '2026-03-24 17:52:24'),
(24, 14, 8, 6, '2500.00000000', '0.00000000', NULL, 'BEP20', 'BEP20', '2025-04-06', '', '2026-03-27 18:21:01'),
(25, 8, 21, 4, '0.00794002', '0.00011100', 'BTC', 'BTC', 'BTC', '2025-05-25', '', '2026-03-27 18:25:46'),
(26, 14, 20, 5, '0.02963000', '0.00003000', 'ETH', 'ERC20', 'ABSTRACT', '2025-02-15', '', '2026-03-28 13:20:17');

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
(1, 'theme', 'dark', '2026-03-29 11:21:27');

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_portfolio_summary`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_portfolio_summary` (
`id` int(11)
,`symbol` varchar(20)
,`name` varchar(100)
,`type` enum('stock','bond','crypto','currency','deposit','etf','other')
,`sector` varchar(50)
,`quantity` decimal(20,8)
,`average_buy_price` decimal(20,8)
,`currency_code` varchar(10)
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
-- Индексы таблицы `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`expense_date`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_currency` (`currency_code`);

--
-- Индексы таблицы `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

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
-- Индексы таблицы `sectors`
--
ALTER TABLE `sectors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_name` (`name`);

--
-- Индексы таблицы `sold_lots`
--
ALTER TABLE `sold_lots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sell_trade_id` (`sell_trade_id`),
  ADD KEY `buy_trade_id` (`buy_trade_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT для таблицы `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

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
-- AUTO_INCREMENT для таблицы `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `limit_orders`
--
ALTER TABLE `limit_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `networks`
--
ALTER TABLE `networks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `platforms`
--
ALTER TABLE `platforms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT для таблицы `portfolio`
--
ALTER TABLE `portfolio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT для таблицы `portfolio_structure`
--
ALTER TABLE `portfolio_structure`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `sectors`
--
ALTER TABLE `sectors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `sold_lots`
--
ALTER TABLE `sold_lots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT для таблицы `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT для таблицы `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

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
-- Ограничения внешнего ключа таблицы `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`);

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
-- Ограничения внешнего ключа таблицы `sold_lots`
--
ALTER TABLE `sold_lots`
  ADD CONSTRAINT `sold_lots_ibfk_1` FOREIGN KEY (`sell_trade_id`) REFERENCES `trades` (`id`),
  ADD CONSTRAINT `sold_lots_ibfk_2` FOREIGN KEY (`buy_trade_id`) REFERENCES `trades` (`id`);

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
