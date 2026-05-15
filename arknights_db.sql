-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 28, 2026 at 10:23 AM
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
-- Database: `arknights_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `operators`
--

CREATE TABLE `operators` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `rarity` int(11) NOT NULL,
  `class` varchar(50) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image_path_e2` varchar(255) NOT NULL,
  `hp` int(11) NOT NULL,
  `hp_e2` int(11) NOT NULL,
  `atk` int(11) NOT NULL,
  `atk_e2` int(11) NOT NULL,
  `def` int(11) NOT NULL,
  `def_e2` int(11) NOT NULL,
  `block_count` int(11) NOT NULL,
  `block_count_e2` int(11) NOT NULL,
  `deploy_cost` int(11) NOT NULL,
  `deploy_cost_e2` int(11) NOT NULL,
  `lore` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `operators`
--

INSERT INTO `operators` (`id`, `name`, `rarity`, `class`, `image_path`, `image_path_e2`, `hp`, `hp_e2`, `atk`, `atk_e2`, `def`, `def_e2`, `block_count`, `block_count_e2`, `deploy_cost`, `deploy_cost_e2`, `lore`) VALUES
(3, 'Dusk', 6, 'Caster', 'https://arknights.wiki.gg/images/Dusk.png?d408fe', 'https://arknights.wiki.gg/images/Dusk_Elite_2.png?5fe7cc', 1404, 1801, 771, 919, 106, 127, 1, 1, 31, 34, 'Coming on a whim, the great painter, Dusk, puts only the finishing touches on all jobs.'),
(6, 'Exusiai the New Covenant', 6, 'Specialist', 'https://arknights.wiki.gg/images/Exusiai_the_New_Covenant.png?bf6b90', 'https://arknights.wiki.gg/images/Exusiai_the_New_Covenant_Elite_2.png?47b77d', 1720, 2150, 587, 708, 120, 150, 1, 1, 11, 13, 'Veteran employee of Penguin Logistics and boss of Apple Pie Logistics, Exusiai, only just back at Rhodes Island from Laterano.'),
(7, 'Ling', 6, 'Supporter', 'https://arknights.wiki.gg/images/Ling.png?69d1c7', 'https://arknights.wiki.gg/images/Ling_Elite_2.png?d8dbf0', 852, 1079, 406, 473, 114, 138, 1, 1, 10, 12, 'Ling, poet from Yan, unchecked and unbridled, content as she wishes. As long as she has wine, anything can go into her poems.'),
(8, 'Nian', 6, 'Defender', 'https://arknights.wiki.gg/images/Nian.png?c8bb3f', 'https://arknights.wiki.gg/images/Nian_Elite_2.png?117dc5', 2737, 3699, 513, 619, 529, 726, 3, 3, 21, 23, 'Nian, a mysterious visitor from the faraway land of Yan, is willing to provide a little bit of assistance.'),
(11, 'Muelsyse', 6, 'Vanguard', 'https://arknights.wiki.gg/images/thumb/Muelsyse_story.png/800px-Muelsyse_story.png?55587f', 'https://arknights.wiki.gg/images/Muelsyse_Elite_2.png?ce422a', 1486, 1813, 407, 497, 98, 117, 1, 1, 13, 16, 'Muelsyse, Rhine Lab Collaborating Operator. A strange, peculiar ecologist.'),
(12, 'Ch\'en the Dawnstreak', 6, 'Guard', 'https://arknights.wiki.gg/images/Ch%27en_the_Dawnstreak.png?42203d', 'https://arknights.wiki.gg/images/Ch%27en_the_Dawnstreak_Elite_2.png?a472ca', 2211, 2910, 542, 670, 352, 425, 1, 1, 19, 21, 'Rhodes Island Operator Ch\'en still wields her sword for \"justice\".'),
(13, 'Rosmontis', 6, 'Sniper', 'https://arknights.wiki.gg/images/Rosmontis.png?76ef2f', 'https://arknights.wiki.gg/images/Rosmontis_Elite_2.png?be752d', 1477, 1944, 584, 688, 200, 245, 1, 1, 23, 25, 'Rosmontis, Rhodes Island Elite Operator, pushes open your office door by hand.'),
(14, 'Civilight Eterna', 6, 'Supporter', 'https://arknights.wiki.gg/images/Civilight_Eterna.png?69ff5b', 'https://arknights.wiki.gg/images/Civilight_Eterna_Elite_2.png?1d5f89', 1221, 1628, 306, 369, 188, 226, 1, 1, 8, 10, 'An echo of the past, loaded with love and hope for the future.'),
(15, 'Hoshiguma the Breacher', 6, 'Defender', 'https://arknights.wiki.gg/images/Hoshiguma_the_Breacher.png?18eac2', 'https://arknights.wiki.gg/images/Hoshiguma_the_Breacher_Elite_2.png?8e3a20', 2592, 3551, 533, 667, 431, 561, 3, 3, 24, 26, 'L.G.D. Special Inspection Unit Chief Hoshiguma, awaiting your most difficult missions.'),
(16, 'Wiš\'adel', 6, 'Sniper', 'https://arknights.wiki.gg/images/Wi%C5%A1%27adel.png?82cc0a', 'https://arknights.wiki.gg/images/Wi%C5%A1%27adel_Elite_2.png?b739cf', 1434, 1888, 583, 687, 209, 256, 1, 1, 23, 25, 'Sarkaz mercenary Wiš\'adel, the most dangerous callsign the battlefield knew.'),
(17, 'Cantabile', 5, 'Vanguard', 'https://arknights.wiki.gg/images/Cantabile.png?834874', 'https://arknights.wiki.gg/images/Cantabile_Elite_2.png?125b72', 1571, 1917, 459, 560, 225, 267, 1, 1, 8, 10, 'A woman who grew up in the midst of disorder, with hopes of seeking a new direction here.'),
(18, 'Myrtle', 4, 'Vanguard', 'https://arknights.wiki.gg/images/Myrtle.png?eb1bc4', 'https://arknights.wiki.gg/images/Myrtle_Elite_2.png?74d962', 1142, 1565, 436, 520, 255, 300, 1, 1, 8, 10, 'Myrtle, Vanguard of Rhodes Island, is ready to lead the charge into battle!'),
(19, 'Vanilla', 3, 'Vanguard', 'https://arknights.wiki.gg/images/Vanilla.png?f26c24', '', 1270, 0, 355, 0, 240, 0, 2, 0, 11, 0, 'Vanilla, Vanguard Operator of Blacksteel, will make way for the squad on the battlefield with her professional skills.'),
(20, 'Ceylon', 5, 'Medic', 'https://arknights.wiki.gg/images/Ceylon.png?fab56c', 'https://arknights.wiki.gg/images/Ceylon_Elite_2.png?cfa3d3', 1251, 1455, 374, 468, 100, 126, 1, 1, 20, 22, 'Ceylon, researcher of Rhodes Island, will provide you with knowledge and technical support on Originium in her spare time.'),
(21, 'Durin', 2, 'Caster', 'https://arknights.wiki.gg/images/Durin.png?fd3647', '', 952, 0, 340, 0, 62, 0, 1, 0, 12, 0, 'Durin, Caster Operator of Rhodes Island, will give the squad a tactical advantage with her Originium Arts.'),
(22, 'U-Official', 1, 'Supporter', 'https://arknights.wiki.gg/images/U-Official.png?4bbe90', '', 385, 0, 102, 0, 28, 0, 1, 0, 3, 0, 'U-Official, \"The Ultimate Beautiful Girl\", has recently completed a perilous career leap from TV host to streamer.'),
(23, 'Haruka', 6, 'Supporter', 'https://arknights.wiki.gg/images/Haruka.png?9c875b', 'https://arknights.wiki.gg/images/Haruka_Elite_2.png?505f8e', 1415, 1887, 398, 480, 142, 178, 1, 1, 11, 13, 'Haruka of Higashi hopes to have some time she can truly call her own aboard Rhodes Island.'),
(24, 'SilverAsh the Reignfrost', 6, 'Vanguard', 'https://arknights.wiki.gg/images/SilverAsh_the_Reignfrost.png?967f5b', 'https://arknights.wiki.gg/images/SilverAsh_the_Reignfrost_Elite_2.png?b10749', 1552, 2218, 507, 618, 317, 397, 2, 2, 11, 13, 'An ordinary citizen of Kjerag, with a renewed Rhodes Island contract, joins the battlefield once more.'),
(25, 'Thorns the Lodestar', 6, 'Specialist', 'https://arknights.wiki.gg/images/Thorns_the_Lodestar.png?d7fd1b', 'https://arknights.wiki.gg/images/Thorns_the_Lodestar_Elite_2.png?c57b5c', 926, 1173, 405, 501, 89, 106, 1, 1, 16, 18, 'Thorns, captain of the Cuna de Nene, looks back from atop the mast and stretches out his hand in invitation. \"Come with me,\" he says.'),
(26, 'Necrass', 6, 'Caster', 'https://arknights.wiki.gg/images/Necrass.png?2ef0f6', 'https://arknights.wiki.gg/images/Necrass_Elite_2.png?f750da', 1443, 1924, 531, 633, 110, 133, 1, 1, 19, 21, 'Necrass, former leader of Dublinn, visiting Rhodes Island now that all of Dublinn is dead.');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `operators`
--
ALTER TABLE `operators`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `operators`
--
ALTER TABLE `operators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
