INSERT INTO `game_api`(`id`, `type`, `name`, `currency`, `lobby`, `cagent`, `des_key`, `key`, `pub_key`, `loginUrl`, `orderUrl`, `apiUrl`, `update_at`, `site_type`) VALUES (99, 'QT', 'QT', 'MXN', '{\"api_password\":\"p(jqfoB7\"}', 'api_live646', NULL, 'b04ae5a5fa5bd32fe12ad676442b6f3ba3c552146688430d16a6e0f9994431a2ede3282701a3b316f073ff84aea5de3484f6585da16d70b798c7a811e204f06d', NULL, 'https://api-int.qtplatform.com/', 'https://api-int.qtplatform.com/', 'https://api-int.qtplatform.com/', '2022-10-28 14:20:48', 'mxn');

INSERT INTO `game_menu`(`id`, `pid`, `type`, `name`, `alias`, `rename`, `qp_img`, `qp_img2`, `img`, `qp_icon`, `qp_un_icon`, `list_mode`, `quit`, `sort`, `status`, `update_at`, `switch`, `m_start_time`, `m_end_time`, `across_sort`, `across_status`) VALUES (132, 4, 'QT', 'QT', 'QT', 'QT电子', NULL, NULL, 'https://img.caacaya.com/lodi/menu/qt.png', NULL, NULL, '1', '1', NULL, 'enabled', NULL, 'enabled', NULL, NULL, NULL, 'enabled');
INSERT INTO `game_menu`(`id`, `pid`, `type`, `name`, `alias`, `rename`, `qp_img`, `qp_img2`, `img`, `qp_icon`, `qp_un_icon`, `list_mode`, `quit`, `sort`, `status`, `update_at`, `switch`, `m_start_time`, `m_end_time`, `across_sort`, `across_status`) VALUES (131, 20, 'QTTAB', 'QTTABLE', 'QT', 'QT桌面游戏', NULL, NULL, 'https://img.caacaya.com/lodi/menu/qt.png', NULL, NULL, '1', '1', NULL, 'enabled', NULL, 'enabled', NULL, NULL, NULL, 'enabled');

DROP TABLE IF EXISTS `game_order_qt`;
CREATE TABLE `game_order_qt`  (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增id',
      `tid` int(10) UNSIGNED NOT NULL COMMENT '分系统tid',
      `round_id` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL COMMENT '游戏交易 id',
      `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '状态（PENDING等待  COMPLETED完成  FAILED失败）',
      `totalBet` decimal(10, 2) UNSIGNED NOT NULL COMMENT '总投注',
      `totalPayout` decimal(10, 2) UNSIGNED NOT NULL COMMENT '该游戏局的总派彩，包括所有的奖池奖金和游戏奖金',
      `totalBonusBet` decimal(10, 2) UNSIGNED NOT NULL COMMENT '总奖金投注金额',
      `currency` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '货币代码',
      `operatorId` int(10) UNSIGNED NOT NULL COMMENT '运营商在 QT 平台中的唯一标识符',
      `playerId` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '玩家账号',
      `device` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '玩家的设备（MOBILE or DESKTOP）',
      `gameProvider` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '游戏提供者的标识符',
      `gameId` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '游戏的标示符',
      `gameCategory` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '游戏类路径',
      `gameClientType` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '游戏客户端平台（Flash 或 HTML5）',
      `gameProviderRoundId` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '提供商的独有的游戏局 ID',
      `totalJpContribution` decimal(10, 5) UNSIGNED NOT NULL COMMENT '该游戏局的奖池贡献',
      `totalJpPayout` decimal(10, 2) UNSIGNED NOT NULL COMMENT '该游戏局的总奖池派彩金额',
      `tableId` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '赌桌Id',
      `initiated` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '游戏局创建的日期和时间',
      `completed` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '游戏局完成的日期和时间',
      PRIMARY KEY (`id`) USING BTREE,
      UNIQUE INDEX `uniq_roundId`(`round_id`) USING BTREE,
      INDEX `idx_completed`(`completed`) USING BTREE,
      INDEX `idx_pc`(`playerId`, `completed`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13748, 'RLX-bananatown', 132, 'Banana Town', '香蕉镇', 'Slot', 'BananaTown', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-bananatown.png', 3, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13749, 'RLX-beastmode', 132, 'Beast Mode', '野兽模式', 'Slot', 'BeastMode', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-beastmode.png', 4, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13750, 'RLX-blenderblitz', 132, 'Blender Blitz', '闪速搅拌器', 'Slot', 'BlenderBlitz', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-blenderblitz.png', 5, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13751, 'RLX-bookof99', 132, 'Book of 99', '99之书', 'Slot', 'Bookof99', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-bookof99.png', 6, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13752, 'RLX-cavemanbob', 132, 'Caveman Bob', '原始人鲍勃', 'Slot', 'CavemanBob', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-cavemanbob.png', 7, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13753, 'RLX-chipspin', 132, 'Chip Spin', '筹码旋转', 'Slot', 'ChipSpin', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-chipspin.png', 8, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13754, 'RLX-clustertumble', 132, 'Cluster Tumble', '群集坠落', 'Slot', 'ClusterTumble', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-clustertumble.png', 9, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13755, 'RLX-deadmanstrail', 132, 'Dead Man\'s Trail', '亡灵踪迹', 'Slot', 'DeadMan\'sTrail', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-deadmanstrail.png', 10, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13756, 'RLX-deadriderstrail', 132, 'Dead Riders Trail', '骑士步道', 'Slot', 'DeadRidersTrail', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-deadriderstrail.png', 11, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13757, 'RLX-deepdescent', 132, 'Deep Descent', '深度侵袭', 'Slot', 'DeepDescent', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-deepdescent.png', 12, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13758, 'RLX-dragonsawakening', 132, 'Dragons Awakening', '巨龙苏醒', 'Slot', 'DragonsAwakening', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-dragonsawakening.png', 13, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13759, 'RLX-emeraldsinfinityreels', 132, 'Emerald\'s Infinity Reels', '翡翠岛无限卷轴', 'Slot', 'Emerald\'sInfinityReels', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-emeraldsinfinityreels.png', 14, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13761, 'RLX-epicjoker', 132, 'Epic Joker', '无敌小丑', 'Slot', 'EpicJoker', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-epicjoker.png', 16, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13760, 'RLX-erikthered', 132, 'Erik the Red', '红胡子埃里克', 'Slot', 'EriktheRed', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-erikthered.png', 15, '2022-11-05 18:33:02', '2022-11-05 18:33:02', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13762, 'RLX-frequentflyer', 132, 'Frequent Flyer', '飞行常客', 'Slot', 'FrequentFlyer', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-frequentflyer.png', 17, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13763, 'RLX-hazakuraways', 132, 'Hazakura Ways', '页樱之路', 'Slot', 'HazakuraWays', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-hazakuraways.png', 18, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13764, 'RLX-heliosfury', 132, 'Helios\' Fury', '赫利俄斯之怒', 'Slot', 'Helios\'Fury', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-heliosfury.png', 19, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13765, 'RLX-hellcatraz', 132, 'Hellcatraz', '地狱岛', 'Slot', 'Hellcatraz', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-hellcatraz.png', 20, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13766, 'RLX-heroesgathering', 132, 'Heroes Gathering', '英雄集聚', 'Slot', 'HeroesGathering', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-heroesgathering.png', 21, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13767, 'RLX-hex', 132, 'Hex', '魔法', 'Slot', 'Hex', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-hex.png', 22, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13768, 'RLX-ignitethenight', 132, 'Ignite The Night', '点亮黑夜', 'Slot', 'IgniteTheNight', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-ignitethenight.png', 23, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13769, 'RLX-ironbank', 132, 'Iron Bank', '铁银行', 'Slot', 'IronBank', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-ironbank.png', 24, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13770, 'RLX-itstime', 132, 'It\'s Time!!', '是时候了', 'Slot', 'It\'sTime!!', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-itstime.png', 25, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13771, 'RLX-jurassicparty', 132, 'Jurassic Party', '侏罗纪派对', 'Slot', 'JurassicParty', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-jurassicparty.png', 26, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13772, 'RLX-kingofkings', 132, 'King of Kings', '众王之王', 'Slot', 'KingofKings', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-kingofkings.png', 27, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13773, 'RLX-klusterkrystalsmegaclusters', 132, 'Kluster Krystals Megaclusters', '水晶大集群', 'Slot', 'KlusterKrystalsMegaclusters', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-klusterkrystalsmegaclusters.png', 28, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13774, 'RLX-lafiesta', 132, 'La Fiesta', '嘉年华', 'Slot', 'LaFiesta', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-lafiesta.png', 29, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13775, 'RLX-letsgetreadytorumble', 132, 'Let\'s get ready to Rumble', '开始比赛吧', 'Slot', 'Let\'sgetreadytoRumble', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-letsgetreadytorumble.png', 30, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13776, 'RLX-marchinglegions', 132, 'Marching Legions', '行进的军团', 'Slot', 'MarchingLegions', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-marchinglegions.png', 31, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13777, 'RLX-megaflip', 132, 'Mega Flip', '大翻转', 'Slot', 'MegaFlip', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-megaflip.png', 32, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13778, 'RLX-megamasks', 132, 'Mega Masks', '无敌大面具', 'Slot', 'MegaMasks', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-megamasks.png', 33, '2022-11-05 18:33:03', '2022-11-05 18:33:03', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13779, 'RLX-megamine', 132, 'Mega Mine', '超级矿山', 'Slot', 'MegaMine', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-megamine.png', 34, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13780, 'RLX-midnightmarauder', 132, 'Midnight Marauder', '午夜掠夺者', 'Slot', 'MidnightMarauder', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-midnightmarauder.png', 35, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13781, 'RLX-moneycart', 132, 'Money Cart', '金钱列车', 'Slot', 'MoneyCart', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-moneycart.png', 36, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13782, 'RLX-moneycart2', 132, 'Money Cart 2', '金钱列车2', 'Slot', 'MoneyCart2', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-moneycart2.png', 37, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13783, 'RLX-moneytrain', 132, 'Money Train', '金钱列车', 'Slot', 'MoneyTrain', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-moneytrain.png', 38, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13784, 'RLX-moneytrain2', 132, 'Money Train 2', '金钱列车2', 'Slot', 'MoneyTrain2', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-moneytrain2.png', 39, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13785, 'RLX-multiplierodyssey', 132, 'Multiplier Odyssey', '翻倍奥德赛', 'Slot', 'MultiplierOdyssey', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-multiplierodyssey.png', 40, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13786, 'RLX-plunderland', 132, 'Plunderland', '海盗乐园', 'Slot', 'Plunderland', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-plunderland.png', 41, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13787, 'RLX-powerspin', 132, 'Powerspin', '超级旋转', 'Slot', 'Powerspin', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-powerspin.png', 42, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13788, 'RLX-ramsesrevenge', 132, 'Ramses Revenge', '拉美西斯复仇', 'Slot', 'RamsesRevenge', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-ramsesrevenge.png', 43, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13789, 'RLX-sailsoffortune', 132, 'Sails Of Fortune', '寻宝远航', 'Slot', 'SailsOfFortune', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-sailsoffortune.png', 44, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13790, 'RLX-santasstack', 132, 'Santa\'s Stack', '圣诞老人堆叠', 'Slot', 'Santa\'sStack', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-santasstack.png', 45, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13791, 'RLX-snakearena', 132, 'Snake Arena', '神蛇竞技', 'Slot', 'SnakeArena', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-snakearena.png', 46, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13792, 'RLX-spaceminers', 132, 'Space Miners', '星际矿工', 'Slot', 'SpaceMiners', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-spaceminers.png', 47, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13793, 'RLX-spiritofthebeast', 132, 'Spirit of the Beast', '神灵之兽', 'Slot', 'SpiritoftheBeast', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-spiritofthebeast.png', 48, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13794, 'RLX-templartumble', 132, 'Templar Tumble', '圣殿武士滚动', 'Slot', 'TemplarTumble', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-templartumble.png', 49, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13795, 'RLX-templetumblemegaways', 132, 'Temple Tumble Megaways', '神庙坠落', 'Slot', 'TempleTumbleMegaways', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-templetumblemegaways.png', 50, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13796, 'RLX-thegreatpigsby', 132, 'The Great Pigsby', '伟大的猪茨比', 'Slot', 'TheGreatPigsby', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-thegreatpigsby.png', 51, '2022-11-05 18:33:04', '2022-11-05 18:33:04', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13797, 'RLX-tigerkingdominfinityreels', 132, 'Tiger Kingdom Infinity Reels', '猛虎之国', 'Slot', 'TigerKingdomInfinityReels', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-tigerkingdominfinityreels.png', 52, '2022-11-05 18:33:05', '2022-11-05 18:33:05', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13798, 'RLX-tnttumble', 132, 'TNT Tumble', 'TNT滚落', 'Slot', 'TNTTumble', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-tnttumble.png', 53, '2022-11-05 18:33:05', '2022-11-05 18:33:05', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13799, 'RLX-topdawg', 132, 'Top Dawg$', '名犬(道格斯)', 'Slot', 'TopDawg$', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-topdawg.png', 54, '2022-11-05 18:33:05', '2022-11-05 18:33:05', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13800, 'RLX-towertumble', 132, 'Tower Tumble', '高塔坠落', 'Slot', 'TowerTumble', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-towertumble.png', 55, '2022-11-05 18:33:05', '2022-11-05 18:33:05', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13801, 'RLX-trollsgold', 132, 'Troll\'s Gold', '巨魔黄金', 'Slot', 'Troll\'sGold', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-trollsgold.png', 56, '2022-11-05 18:33:05', '2022-11-05 18:33:05', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13802, 'RLX-volatilevikings', 132, 'Volatile Vikings', '残暴维京人', 'Slot', 'VolatileVikings', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-volatilevikings.png', 57, '2022-11-05 18:33:05', '2022-11-05 18:33:05', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13803, 'RLX-wildchapo', 132, 'Wild Chapo', '狂野矮子', 'Slot', 'WildChapo', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-wildchapo.png', 58, '2022-11-05 18:33:05', '2022-11-05 18:33:05', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13804, 'RLX-wildchemy', 132, 'Wildchemy', '百搭实验室', 'Slot', 'Wildchemy', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-wildchemy.png', 59, '2022-11-05 18:33:05', '2022-11-05 18:33:05', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13805, 'RLX-zombiecircus', 132, 'Zombie Circus', '僵尸马戏团', 'Slot', 'ZombieCircus', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-zombiecircus.png', 60, '2022-11-05 18:33:05', '2022-11-05 18:33:05', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13806, 'RLX-blackjackneo', 131, 'Blackjack Neo', '全新二十一点', 'TABLE', 'BlackjackNeo', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-blackjackneo.png', 59, '2022-11-05 18:25:12', '2022-11-05 18:25:12', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);
INSERT INTO `game_3th`(`id`, `kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`, `qp_img`, `game_img`, `sort`, `created`, `updated`, `maintain`, `is_hot`, `status`, `across_sort`, `across_status`, `extension_img`, `is_freespin`) VALUES (13807, 'RLX-roulettenouveau', 131, 'Roulette Nouveau', '全新轮盘赌', 'TABLE', 'RouletteNouveau', NULL, 'https://img.caacaya.com/lodi/game/vert/qt/RLX-roulettenouveau.png', 60, '2022-11-05 18:25:12', '2022-11-05 18:25:12', 'tobemaintain', 0, 'enabled', NULL, 'enabled', NULL, 0);