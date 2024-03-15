#++++++++++++++++++++++++++++++++++lodi_super_admin`

INSERT INTO `game_menu` (`id`, `pid`, `type`, `name`, `alias`, `rename`, `status`, `switch`, `across_status`) VALUES (103, 15, 'DG', 'DG', 'DG', 'DG视讯', 'enabled', 'enabled', 'enabled');INSERT INTO `game_3th` (`kind_id`, `game_id`, `game_name`, `rename`, `type`, `alias`) VALUES ('dg', 103, 'DreamGame', 'DG游戏', 'LIVE', 'DreamGame');

INSERT INTO `game_api` (`type`, `name`, `lobby`, `cagent`, `key`, `loginUrl`, `orderUrl`, `apiUrl`) VALUES ('DG', 'DG', '{\"limitGroup\":\"F\",\"winLimit\":\"0\"}', 'DG02170117', '32c4f2d145ea45808ebd3e413bebd5bb', 'https://api.dg0.co', 'https://api.dg0.co', 'https://api.dg0.co');


create table game_order_dg(
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增id',
      `tid` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '分站ID',
      `order_number` varchar(64) NOT NULL DEFAULT '' COMMENT '注单ID',
      `lobbyId` int(11) NOT NULL DEFAULT 0 COMMENT '游戏大厅号 1:旗舰厅；3，4:现场厅；5:欧美厅,7:国际厅,8:区块链厅',
      `tableId` int(11) NOT NULL DEFAULT 0 COMMENT '游戏桌号',
      `shoeId` int(11) NOT NULL DEFAULT 0 COMMENT '游戏靴号',
      `playId` int(11) NOT NULL DEFAULT 0 COMMENT '游戏局号',
      `gameType` tinyint(4) NOT NULL DEFAULT 0 COMMENT '游戏类型',
      `GameId` tinyint(4) NOT NULL DEFAULT 0 COMMENT '游戏Id',
      `memberId` int(11) NOT NULL DEFAULT 0 COMMENT '会员Id',
      `parentId` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级ID',
      `betTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '游戏下注时间',
      `calTime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '游戏结算时间',
      `winOrLoss` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '派彩金额 (输赢应扣除下注金额)',
      `winOrLossz` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '好路追注派彩金额',
      `betPoints` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '下注金额',
      `betPointsz` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '好路追注金额',
      `availableBet` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '有效下注金额',
      `profit` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '输赢=派彩-下注',
      `userName` varchar(64) NOT NULL DEFAULT '' COMMENT '会员登入账号',
      `result` json NOT NULL COMMENT '游戏结果',
      `betDetail` json NOT NULL COMMENT '下注注单',
      `betDetailz` varchar(500)  NOT NULL DEFAULT '' COMMENT '好路追注注单',
      `ip` varchar(16)  NOT NULL DEFAULT '' COMMENT '下注时客户端IP',
      `isRevocation` tinyint(1) NOT NULL DEFAULT 0 COMMENT '否结算：1：已结算 2:撤销',
      `balanceBefore` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '余额',
      `parentBetId` int(11) NOT NULL DEFAULT 0 COMMENT '撤销的那比注单的ID',
      `currencyId` int(11) NOT NULL DEFAULT 0 COMMENT '货币ID',
      `deviceType` int(11) NOT NULL DEFAULT 0 COMMENT '下注时客户端类型',
      `pluginid` int(11) NOT NULL DEFAULT 0 COMMENT '追注转账流水号',
      `roadid` tinyint(5) NOT NULL DEFAULT 0 COMMENT '局号ID',
      PRIMARY KEY (`id`, `betTime`) USING BTREE,
      UNIQUE INDEX `uniq_on`(`order_number`, `betTime`) USING BTREE,
      INDEX `idx_bettime`(`betTime`) USING BTREE,
      INDEX `idx_tid`(`tid`) USING BTREE
)engine=innodb character set=utf8mb4 comment 'DG视讯注单表' ;

#++++++++++++++++++++++++++++++进程操作+++++++++++++++++++++++++
#
# 重启进程 sudo php gameServer.php restart -d
#
#
#++++++++++++++++++++++++++++redis操作+++++++++++++++++++++++++
#
# del api_third__game_jump_data
#
#
