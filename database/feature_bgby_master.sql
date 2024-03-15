INSERT INTO `game_menu`(`id`, `pid`, `type`, `name`, `alias`, `rename`, `qp_img`, `qp_img2`, `img`, `qp_icon`, `qp_un_icon`, `list_mode`, `quit`, `sort`, `status`, `update_at`, `switch`, `m_start_time`, `m_end_time`, `across_sort`, `across_status`) VALUES (115, 22, 'BGBY', 'BGFH', 'BG', 'BG捕鱼', NULL, NULL, NULL, NULL, NULL, '1', '1', NULL, 'enabled', NULL, 'enabled', NULL, NULL, NULL, 'enabled');

ALTER TABLE `game_order_bg`
    MODIFY COLUMN `moduleId` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '模块ID' AFTER `loginId`,
    MODIFY COLUMN `moduleName` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '模块名称' AFTER `moduleId`,
    MODIFY COLUMN `gameId` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '游戏ID' AFTER `moduleName`,
    MODIFY COLUMN `gameName` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '玩法名称' AFTER `gameId`,
    MODIFY COLUMN `orderStatus` smallint(3) NULL DEFAULT 0 COMMENT '注单状态' AFTER `gameName`,
    MODIFY COLUMN `lastUpdateTime` datetime(0) NULL COMMENT '最后修改时间' AFTER `orderTime`,
    MODIFY COLUMN `fromIp` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '0.0.0.0' COMMENT '下注来源IP' AFTER `lastUpdateTime`,
    MODIFY COLUMN `playId` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '0' COMMENT '玩法ID' AFTER `issueId`,
    MODIFY COLUMN `playName` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '玩法名称' AFTER `playId`,
    MODIFY COLUMN `playNameEn` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '玩法名称(En)' AFTER `playName`,
    ADD COLUMN `gameCategory` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'LIVE' COMMENT '游戏类型' AFTER `payment`;