-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-09-2025 a las 21:36:49
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `ecobici`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bikes`
--

CREATE TABLE `bikes` (
  `id` int(11) NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `tipo` enum('tradicional','electrica') NOT NULL DEFAULT 'tradicional',
  `estado` enum('disponible','uso','mantenimiento') NOT NULL DEFAULT 'disponible',
  `station_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bikes`
--

INSERT INTO `bikes` (`id`, `codigo`, `tipo`, `estado`, `station_id`, `created_at`) VALUES
(1, 'EB-0001', 'tradicional', 'disponible', 1, '2025-09-13 17:20:28'),
(2, 'EB-0002', 'tradicional', 'uso', NULL, '2025-09-13 17:20:28'),
(3, 'EB-0003', 'electrica', 'uso', NULL, '2025-09-13 17:20:28'),
(4, 'EB-0004', 'tradicional', 'uso', 1, '2025-09-13 17:20:28'),
(5, 'EB-0005', 'tradicional', 'uso', NULL, '2025-09-13 17:20:28'),
(6, 'EB-0006', 'electrica', 'uso', NULL, '2025-09-13 17:20:28'),
(7, 'EB-0007', 'tradicional', 'mantenimiento', 2, '2025-09-13 17:20:28'),
(8, 'EB-0008', 'tradicional', 'disponible', 3, '2025-09-13 17:20:28'),
(9, 'EB-0009', 'electrica', 'uso', NULL, '2025-09-13 17:20:28'),
(10, 'EB-0010', 'tradicional', 'uso', NULL, '2025-09-13 17:20:28'),
(11, 'EB-0011', 'electrica', 'uso', 4, '2025-09-13 17:20:28'),
(12, 'EB-0012', 'tradicional', 'uso', NULL, '2025-09-13 17:20:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `damage_reports`
--

CREATE TABLE `damage_reports` (
  `id` int(11) NOT NULL,
  `bike_id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `nota` text NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `estado` enum('nuevo','en_proceso','resuelto') NOT NULL DEFAULT 'nuevo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `subscription_id` bigint(20) UNSIGNED NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo` varchar(50) NOT NULL DEFAULT 'simulado',
  `referencia` varchar(191) DEFAULT NULL,
  `estado` enum('pendiente','completado','fallido') NOT NULL DEFAULT 'pendiente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `payments`
--

INSERT INTO `payments` (`id`, `subscription_id`, `monto`, `metodo`, `referencia`, `estado`, `created_at`, `updated_at`) VALUES
(1, 1, 60.00, 'card', 'SIM-40E57E00', 'completado', '2025-09-13 15:25:13', '2025-09-13 15:25:13'),
(2, 4, 45.00, 'card', 'SIM-9F51C454', 'completado', '2025-09-13 16:54:42', '2025-09-13 16:54:42'),
(3, 5, 60.00, 'card', 'SIM-3004B3BB', 'completado', '2025-09-13 16:59:14', '2025-09-13 16:59:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plans`
--

CREATE TABLE `plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `plans`
--

INSERT INTO `plans` (`id`, `nombre`, `descripcion`, `precio`, `created_at`, `updated_at`) VALUES
(1, 'Paseo', 'Ideal para pasear por la ciudad.', 30.00, '2025-09-13 04:58:35', '2025-09-13 04:58:35'),
(2, 'Ruta', 'Para rodadas medias con rutas recomendadas.', 45.00, '2025-09-13 04:58:35', '2025-09-13 04:58:35'),
(3, 'Maratón', 'Para entrenos intensos y eventos.', 60.00, '2025-09-13 04:58:35', '2025-09-13 04:58:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `settings`
--

CREATE TABLE `settings` (
  `key` varchar(64) NOT NULL,
  `value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `settings`
--

INSERT INTO `settings` (`key`, `value`) VALUES
('co2_factor_kg_km', '0.21'),
('points_per_km', '1');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stations`
--

CREATE TABLE `stations` (
  `id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `tipo` enum('dock','punto') NOT NULL DEFAULT 'dock',
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `capacidad` int(11) NOT NULL DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `stations`
--

INSERT INTO `stations` (`id`, `nombre`, `tipo`, `lat`, `lng`, `capacidad`, `created_at`) VALUES
(1, 'Malecón Puerto Barrios', 'dock', 15.7271000, -88.5949000, 12, '2025-09-13 17:20:28'),
(2, 'Parque Reina Barrios', 'dock', 15.7335000, -88.5902000, 10, '2025-09-13 17:20:28'),
(3, 'Terminal Ferroviaria', 'punto', 15.7298000, -88.5958000, 8, '2025-09-13 17:20:28'),
(4, 'Hospital Puerto Barrios', 'dock', 15.7319000, -88.5856000, 10, '2025-09-13 17:20:28'),
(5, 'Universidad Idearte', 'punto', 15.7362000, -88.5875000, 6, '2025-09-13 17:20:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `plan_id` bigint(20) UNSIGNED NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('activa','inactiva','pendiente') NOT NULL DEFAULT 'pendiente',
  `activa_flag` tinyint(4) GENERATED ALWAYS AS (case when `estado` = 'activa' then 1 else NULL end) VIRTUAL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `user_id`, `plan_id`, `fecha_inicio`, `fecha_fin`, `estado`, `created_at`, `updated_at`) VALUES
(1, 5, 3, '2025-09-13', '2025-10-13', 'activa', '2025-09-13 15:25:13', '2025-09-13 15:25:13'),
(4, 4, 2, '2025-09-13', '2025-10-13', 'activa', '2025-09-13 16:54:42', '2025-09-13 16:54:42'),
(5, 12, 3, '2025-09-13', '2025-10-13', 'activa', '2025-09-13 16:59:14', '2025-09-13 16:59:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `bike_id` int(11) NOT NULL,
  `start_station_id` int(11) DEFAULT NULL,
  `end_station_id` int(11) DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime DEFAULT NULL,
  `distancia_km` decimal(8,2) DEFAULT 0.00,
  `costo` decimal(10,2) DEFAULT 0.00,
  `co2_kg` decimal(10,3) DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `trips`
--

INSERT INTO `trips` (`id`, `user_id`, `bike_id`, `start_station_id`, `end_station_id`, `start_at`, `end_at`, `distancia_km`, `costo`, `co2_kg`) VALUES
(1, 12, 10, 4, NULL, '2025-09-13 11:26:06', NULL, 0.00, 0.00, 0.000),
(2, 12, 6, 2, NULL, '2025-09-13 11:26:12', NULL, 0.00, 0.00, 0.000),
(3, 12, 12, 5, NULL, '2025-09-13 11:26:16', NULL, 0.00, 0.00, 0.000),
(4, 12, 3, 1, NULL, '2025-09-13 11:26:27', NULL, 0.00, 0.00, 0.000),
(5, 12, 2, 1, NULL, '2025-09-13 11:26:36', NULL, 0.00, 0.00, 0.000),
(6, 5, 1, 1, 3, '2025-09-13 11:30:49', '2025-09-13 11:31:02', 0.32, 0.00, 0.066),
(7, 5, 5, 2, 5, '2025-09-13 11:31:21', '2025-09-13 11:32:47', 0.42, 0.00, 0.088),
(8, 5, 9, 3, 4, '2025-09-13 11:31:40', '2025-09-13 11:32:11', 1.12, 0.00, 0.234),
(9, 5, 1, 3, 1, '2025-09-13 11:32:22', '2025-09-13 11:32:36', 0.32, 0.00, 0.066),
(10, 5, 5, 5, NULL, '2025-09-13 11:35:28', NULL, 0.00, 0.00, 0.000),
(11, 5, 9, 4, NULL, '2025-09-13 11:40:20', NULL, 0.00, 0.00, 0.000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','cliente') NOT NULL DEFAULT 'cliente',
  `dpi` varchar(20) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fecha_nacimiento` date DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `name`, `apellido`, `email`, `password`, `role`, `dpi`, `telefono`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`, `fecha_nacimiento`, `foto`) VALUES
(1, 'Admin EcoBici', NULL, 'admin@ecobici.local', '$2y$12$exampleHashReemplazaEsto', 'admin', NULL, NULL, NULL, NULL, '2025-09-13 04:58:35', '2025-09-13 04:58:35', NULL, NULL),
(2, 'miku', NULL, 'cajiw12705@ceoshub.com', '$2y$12$QJiuew66hNi8dCdrXBjgiuLWjzxKXqISsqpa0ErU/HUlMcVKELZPe', 'cliente', NULL, NULL, NULL, NULL, '2025-09-13 07:05:48', '2025-09-13 07:05:48', NULL, NULL),
(3, 'Mig Admin', NULL, 'admin@local.com', '$2y$10$YZG.efuCgvx9PNNnbGbhk.fu2ZiU0k7zde1Xw9UJo6X6Xo1xw7Ez6', 'admin', NULL, NULL, NULL, NULL, '2025-09-13 09:31:08', '2025-09-13 09:31:08', NULL, NULL),
(4, 'miku miku', NULL, 'ionirodas69@gmail.com', '$2y$12$Hpd3tPPrZKj85fn29f92Z.r81MzQ8lIivQdLinvsV5ZejVn6mlMdu', 'cliente', NULL, NULL, NULL, NULL, '2025-09-13 10:29:12', '2025-09-13 10:29:12', '2000-02-12', 'uploads/users/65326aeb25040d7c_1757759352.png'),
(5, 'luz', NULL, 'ionirodas20@gmail.com', '$2y$12$9NZl1LaRbE6OURaOTh1UoOLs/e/Nau2I406d1vTijIwqB2YqgdpE6', 'cliente', NULL, NULL, NULL, NULL, '2025-09-13 11:07:43', '2025-09-13 11:07:43', '2000-02-12', 'uploads/users/2a1baf026f275e54_1757761663.png'),
(6, 'cafetera', NULL, 'luzmadev98@gmail.com', '$2y$12$Jz0OptfXyo6rKQS9cnc1pu4Kjvndwj0udLApNJY0P3tEno.kFbUJm', 'cliente', NULL, NULL, NULL, NULL, '2025-09-13 11:08:20', '2025-09-13 11:08:20', '2000-03-04', 'uploads/users/ba37f93fa048e2b9_1757761700.png'),
(7, 'servidoresioni_69', NULL, 'admirrrn@local.com', '$2y$12$ypkV/2F3MIcP530wLTBOq.sjzpanpCRf7a9KqtvEbOj5lqeQ1z/Fa', 'cliente', NULL, NULL, NULL, NULL, '2025-09-13 11:13:15', '2025-09-13 11:13:15', '1999-05-13', 'uploads/users/8a3628d558db9e07_1757761995.png'),
(8, 'qer', NULL, 'hola@gmail.com', '$2y$12$VH17uuzcuibx9RofcbIsSuw4/yvdNGNATZt2i2BdqoP5hXTC15p5a', 'cliente', NULL, NULL, NULL, NULL, '2025-09-13 11:18:46', '2025-09-13 11:18:46', '2000-02-12', 'uploads/users/4bf6a24115978587_1757762326.png'),
(9, 'io', NULL, 'io@gmail.com', '$2y$12$jmKDEVM0STDMZhnD2yxm0.sJidNjTUt/MEu3xOH9WnZqF9BhFE7SK', 'cliente', NULL, NULL, NULL, NULL, '2025-09-13 11:20:28', '2025-09-13 11:20:28', '2000-03-12', 'uploads/users/00c750c028e9cbfe_1757762427.png'),
(10, 'rut', NULL, 'tun@gmail.com', '$2y$12$MzoxQ8gTn4.ySLFj9xpoxeFXSjqO6PdMcQeF/ZKFgWj4f/LAfjd3y', 'cliente', NULL, NULL, NULL, NULL, '2025-09-13 13:41:06', '2025-09-13 13:41:06', '2000-04-23', NULL),
(11, 'car', NULL, 'car@gmail.com', '$2y$12$rTcS4AyiaaDVp/Cg.niMoOXKieTJuRxZ48GRscN5XhbXbZzeqjXra', 'cliente', NULL, NULL, NULL, NULL, '2025-09-13 14:47:13', '2025-09-13 14:47:13', '1999-02-23', NULL),
(12, 'Hatsune Miku', NULL, 'miku@gmail.com', '$2y$12$RG334gax3hfmtPgqGYhBs.IpnfGfHdNYu5MVaQ9/i3XdQC4RmdS4W', 'cliente', NULL, NULL, NULL, NULL, '2025-09-13 16:58:47', '2025-09-13 16:58:47', '2004-12-04', 'uploads/users/87743dd6471ae586_1757782726.png');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `bikes`
--
ALTER TABLE `bikes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `fk_bike_station` (`station_id`);

--
-- Indices de la tabla `damage_reports`
--
ALTER TABLE `damage_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dr_bike` (`bike_id`),
  ADD KEY `fk_dr_user` (`user_id`),
  ADD KEY `estado` (`estado`),
  ADD KEY `created_at` (`created_at`);

--
-- Indices de la tabla `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pay_sub` (`subscription_id`);

--
-- Indices de la tabla `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plans_nombre` (`nombre`);

--
-- Indices de la tabla `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indices de la tabla `stations`
--
ALTER TABLE `stations`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_active_subscription` (`user_id`,`activa_flag`),
  ADD KEY `idx_sub_user` (`user_id`),
  ADD KEY `idx_sub_plan` (`plan_id`);

--
-- Indices de la tabla `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_trip_user` (`user_id`),
  ADD KEY `fk_trip_bike` (`bike_id`),
  ADD KEY `fk_trip_s1` (`start_station_id`),
  ADD KEY `fk_trip_s2` (`end_station_id`),
  ADD KEY `start_at` (`start_at`),
  ADD KEY `end_at` (`end_at`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `bikes`
--
ALTER TABLE `bikes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `damage_reports`
--
ALTER TABLE `damage_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `plans`
--
ALTER TABLE `plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `stations`
--
ALTER TABLE `stations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `bikes`
--
ALTER TABLE `bikes`
  ADD CONSTRAINT `fk_bike_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `damage_reports`
--
ALTER TABLE `damage_reports`
  ADD CONSTRAINT `fk_dr_bike` FOREIGN KEY (`bike_id`) REFERENCES `bikes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_pay_sub` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `fk_sub_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `fk_trip_bike` FOREIGN KEY (`bike_id`) REFERENCES `bikes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_trip_s1` FOREIGN KEY (`start_station_id`) REFERENCES `stations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_trip_s2` FOREIGN KEY (`end_station_id`) REFERENCES `stations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_trip_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
