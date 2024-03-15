DROP TABLE IF EXISTS `game_order_check_error`;
CREATE TABLE `game_order_check_error`  (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `game_type` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '游戏类型',
  `now` datetime NOT NULL COMMENT '开始查询时间',
  `json` json NULL COMMENT '查询条件',
  `error` json NULL COMMENT '错误数据',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单汇总错误表' ROW_FORMAT = Dynamic;

#game_order_error表
CREATE TABLE `game_order_error`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_type` varchar(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  `json` json NULL,
  `error` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '同步订单错误表' ROW_FORMAT = DYNAMIC;

#更新JOKER表结构
ALTER TABLE `game_order_joker` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新JOKER表tid
update game_order_joker set tid=26;

#更新JILI表结构
ALTER TABLE `game_order_jili` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新JILI表tid
update game_order_jili set tid=26;

#更新EVO表结构
ALTER TABLE `game_order_evo` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新EVO表tid
update game_order_evo set tid=26;

#更新PP表结构
ALTER TABLE `game_order_pp` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新PP表tid
update game_order_pp set tid=26;

#更新PG表结构
ALTER TABLE `game_order_pg` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新PG表tid
update game_order_pg set tid=26;

#更新PNG表结构
ALTER TABLE `game_order_png` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新PNG表tid
update game_order_png set tid=26;

#更新AWC表结构
ALTER TABLE `game_order_awc` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新AWC表tid
update game_order_awc set tid=26;

#更新CQ9表结构
ALTER TABLE `game_order_cq9` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新CQ9表tid
update game_order_cq9 set tid=26;

#更新JDB表结构
ALTER TABLE `game_order_jdb` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新JDB表tid
update game_order_jdb set tid=26;

#更新KMQM表结构
ALTER TABLE `game_order_kmqm` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新KMQM表tid
update game_order_kmqm set tid=26;

#更新RCB表结构
ALTER TABLE `game_order_rcb` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新RCb表tid
update game_order_rcb set tid=26;

#更新SA表结构
ALTER TABLE `game_order_sa` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新SA表tid
update game_order_sa set tid=26;

#更新SBO表结构
ALTER TABLE `game_order_sbo` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新SBO表tid
update game_order_sbo set tid=26;

#更新SEXYBCRT表结构
ALTER TABLE `game_order_sexybcrt` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新SEXYBCRT表tid
update game_order_sexybcrt set tid=26;

#更新SGMK表结构
ALTER TABLE `game_order_sgmk` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新SGMK表tid
update game_order_sgmk set tid=26;

#更新SV388表结构
ALTER TABLE `game_order_sv388` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新SV388表tid
update game_order_sv388 set tid=26;

#更新TF表结构
ALTER TABLE `game_order_tf` 
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新TF表tid
update game_order_tf set tid=26;

#更新AT表结构
ALTER TABLE `game_order_at`
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新AT表tid
update game_order_at set tid=26;

#更新AVIA表结构
ALTER TABLE `game_order_avia`
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新AVIA表tid
update game_order_avia set tid=26;

#更新CG表结构
ALTER TABLE `game_order_cg`
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新CG表tid
update game_order_cg set tid=26;

#更新FC表结构
ALTER TABLE `game_order_fc`
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新FC表tid
update game_order_fc set tid=26;

#更新UG表结构
ALTER TABLE `game_order_ug`
DROP COLUMN `user_id`,
ADD COLUMN `tid` int(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT '分系统tid' AFTER `id`,
DROP INDEX `user_id`,
ADD INDEX `tid`(`tid`) USING BTREE;

#更新UG表tid
update game_order_ug set tid=26;