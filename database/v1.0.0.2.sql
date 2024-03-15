CREATE TABLE `rpt_orders_middle_day`  (
                                          `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                                          `tid` smallint(5) UNSIGNED NOT NULL COMMENT '厅ID',
                                          `game_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '游戏分类',
                                          `game_user_cnt` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '有效用户',
                                          `game_order_cnt` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '注单数',
                                          `game_bet_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '投注额',
                                          `game_prize_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '派彩金额',
                                          `game_order_profit` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '盈亏情况',
                                          `count_date` date NULL DEFAULT NULL COMMENT '统计日期',
                                          PRIMARY KEY (`id`) USING BTREE,
                                          INDEX `index_tid_game_type_date`(`count_date`, `tid`, `game_type`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '报表统计表' ROW_FORMAT = Dynamic;
