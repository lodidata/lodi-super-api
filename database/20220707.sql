ALTER TABLE `game_api` ADD COLUMN `site_type` enum('lodi','ncg') NOT NULL DEFAULT 'lodi' COMMENT '网站类型 lodi,ncg' AFTER `update_at`;