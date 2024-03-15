<?php

namespace Logic\Define;

/**
 * Class CacheKey
 * 缓存key定义 前缀定义类
 * @package Logic\Define
 */
class CacheKey
{

    /**
     * key
     * @var array
     */
    public static $perfix = [

        'adminCacheToken' => 'admin_cache_token_',

        'authVCode' => 'image_code',

        // 密码错误校验前缀
        'pwdErrorLimit' => 'loginpwd_wrong_times_',

        // 用户登录token
        'token' => 'token_',

        // 短信通知码
        'captchaRefresh' => 'cache_refresh_',

        // 短信通知码
        'captchaText' => 'cache_text_',

        // 用户在状在线最后时间
        'userOnlineLastTime' => 'user_online_last_time',

        // 用户禁用状态
        'userRejectLoginStatus' => 'user_reject_login_status',

        // 平台结果缓存
        'commonLotteryPeriod' => 'common_lottery_period_',

        // 平台开奖结果集合
        'commonLotteryPeriodSet' => 'common_lottery_period_set',

        //common API 查询最后一期期号key
        'ApiCommonLotteryInfoLast' => 'api_common_lottery_info_last_',

        //common API 查询core彩期列表
        'ApiCommonLotteryInfoList' => 'api_common_lottery_info_list_',

        //common API 查询已开奖的
        'ApiCommonLotteryOpenList' => 'api_common_lottery_open_list_',

        //common API 查询已开奖的
        'ApiCommonLotteryDayOpenList' => 'api_common_lottery_day_open_list_',

        //API 第三方游戏配置信息
        'ApiThirdGameJumpMsg' => 'api_third__game_jump_data',

        //包管第三方菜单 ========start=====================
        'gameList' => 'game.list',

        'gameJumpUrl' => 'game.jump.url',
        //最后拉单时间
        'gameGetOrderLastTime' => 'game_get_order_last_time_',
        //最后拉单id
        'gameGetOrderLastId' => 'game_get_order_last_id_',
        //最后拉单校验时间
        'gameOrderCheckTime' => 'game_order_check_time_',
        //最多校验3次
        'gameOrderCheckCount' => 'game_order_check_count_',
        //是否在校验中
        'gameOrderChecking' => 'game_order_checking_',

        'gameGetOrderRequestInfo' => 'game_get_order_request_info:',
        //统计
        'queryOperatesOrder' => 'query_operates_order',
        'queryOperatesOrderTime' => 'query_operates_order_time_',

        //最后一笔订单betUID
        'gameGetOrderLastBetUid' => 'game_get_order_last_bet_uid_',

        //最后拉取订单详情时间
        'gameGetPlayDetailTime' => 'game_get_play_detail_last_time_'
    ];

}