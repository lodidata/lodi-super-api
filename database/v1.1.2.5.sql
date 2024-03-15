
#创建WM游戏注单表game_order_wm

CREATE TABLE `lodi_super_admin`.`game_order_wm`(
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增id',
`tid` tinyint(3) UNSIGNED NOT NULL COMMENT '分站id',
`user` varchar(50) NOT NULL DEFAULT '' COMMENT '账号',
`order_number` varchar(64) NOT NULL DEFAULT '' COMMENT '注单id',
`betTime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '下注時間',
`beforeCash` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '下注前金额',
`bet` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '下注金额',
`validbet` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '有效下注',
`water` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '退水金额',
`result` json NOT NULL COMMENT '下注结果',
`betCode` varchar(50) NOT NULL DEFAULT '' COMMENT '下注代碼',
`betResult` varchar(255) NOT NULL DEFAULT '' COMMENT '下注内容',
`waterbet` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '下注退水金额',
`winLoss` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '输赢金额',
`prize_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '派奖金额',
`ip` varchar(16) NOT NULL DEFAULT '' COMMENT 'ip',
`gid` tinyint(5) NOT NULL COMMENT '游戏类别编号',
`event` varchar(20) NOT NULL DEFAULT '' COMMENT '场次编号',
`eventChild` varchar(20) NOT NULL DEFAULT '' COMMENT '子场次编号',
`tableId` varchar(50) NOT NULL DEFAULT '' COMMENT '桌台编号',
`gameResult` varchar(100) NOT NULL DEFAULT '' COMMENT '牌型',
`gname` varchar(50) NOT NULL DEFAULT '' COMMENT '游戏名称',
`commission` tinyint(5) NOT NULL COMMENT '0:一般, 1:免佣',
`reset` char(1) NOT NULL DEFAULT '' COMMENT 'Y:有重对, N:非重对',
`settime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '结算时间',
`slotGameId` varchar(20) NOT NULL DEFAULT '' COMMENT '电子游戏代码',
`GameCategoryId` tinyint(5) NOT NULL COMMENT '游戏类型： 1: 视讯 2:电子',
PRIMARY KEY (`id`) USING BTREE,
UNIQUE INDEX `uniq_order`(`order_number`) USING BTREE,
INDEX `idx_betime`(`betTime`) USING BTREE,
INDEX `idx_tid`(`tid`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COMMENT = 'wm注单表';



#创建YGG游戏注单表game_order_ygg

CREATE TABLE `lodi_super_admin`.`game_order_ygg`(
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tid` int(5) UNSIGNED NOT NULL COMMENT '分系统tid',
    `reference` varchar(255) NOT NULL COMMENT '注单号',
    `last_id` int(10) NOT NULL COMMENT '第三方ID',
    `loginname` varchar(20) NULL DEFAULT NULL COMMENT '用户名',
    `currency` varchar(20) NULL DEFAULT NULL COMMENT '货币',
    `type` varchar(20) NULL DEFAULT NULL COMMENT '投注类型',
    `amount` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '投注金额',
    `afterAmount` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '投注前金额',
    `beforeAmount` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '投注后金额',
    `profit` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '利润',
    `prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '奖金',
    `gameName` varchar(50) NULL DEFAULT NULL COMMENT '游戏名称',
    `DCGameID` varchar(50) NULL DEFAULT NULL COMMENT '游戏ID',
    `createTime` datetime NULL DEFAULT NULL COMMENT '记录时间',
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE INDEX `order_number`(`reference`) USING BTREE,
    INDEX `tid`(`tid`) USING BTREE,
    INDEX `index_gameDate`(`createTime`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COMMENT = 'YGG注单' ;

#创建AWS表同步AWC表
create table `lodi_super_admin`.`game_order_aws` like `lodi_super_admin`.`game_order_awc`;


#添加游戏账号

INSERT INTO `lodi_super_admin`.`game_api` (`id`, `type`, `name`, `currency`, `lobby`, `cagent`, `des_key`, `key`, `pub_key`, `loginUrl`, `orderUrl`, `apiUrl`, `update_at`, `site_type`)
VALUES
(89, 'WM', 'WM视讯', 'PHP', '', 'tgtg', NULL, '4c3c60254e96fdf3e05c8df0a9474eec', NULL, '', 'https://ddwb-316.wmapi88.com/api/public/Gateway.php', 'https://ddwb-316.wmapi88.com/api/public/Gateway.php', NULL, 'lodi'),
(90, 'AWS', 'AE电子', 'PHP', '{\"LIVE\":{\"limitId\":[262001]}}', 'tgtg', NULL, 'Af7C4ZoVjcieWPrWkje', NULL, '', 'https://fetch.onlinegames22.com', 'https://api.onlinegames22.com', '2022-07-19 17:28:15', 'lodi'),
(91, 'YGG', 'YGG', 'PHP', '', 'tgtg', '24techGroup', 'zDUbLxTdaGrZXFHgAc9', NULL, '', 'https://adapter-prod-tw.248ka.com', 'https://adapter-prod-tw.248ka.com', '2022-07-19 17:31:48', 'lodi');

#添加游戏分类WM,AWS,YGG

INSERT INTO `lodi_super_admin`.`game_menu` (`id`, `pid`, `type`, `name`, `alias`, `rename`,`status`,`switch`, `across_status`) VALUES (104, 15, 'WM', 'WM', 'WM', 'WM视讯','enabled','enabled', 'enabled'),(106, 4, 'AWS', 'AWS', 'AWS', 'AE电子','enabled', 'enabled', 'enabled'),(107, 20, 'AWSTAB', 'AWSTABLE', 'AWS', 'AWS桌面游戏', 'enabled', 'enabled', 'enabled'),(108, 4, ' YGG', ' YGG', 'YGG', 'YGG', 'enabled', 'enabled','enabled');
