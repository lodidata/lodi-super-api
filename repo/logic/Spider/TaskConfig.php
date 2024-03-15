<?php

use  Logic\Spider\Utils;

return [

    //------------------------------【快乐8 & 28配置】----------------------------
    [
        'id'            => 42,
        'pid'           => 41,
        'name'          => '北京快乐8', // 彩票名字
        'after'         => [
            [
                'id'           => 2,
                'pid'          => 1,
                'name'         => '北京幸运28',
                'resultFormat' => function ($opencode) {
                    $opencodes = explode(',', $opencode);
                    $opencodes = array_map("intval", $opencodes);
                    sort($opencodes);
                    $n = [];
                    $n[] = array_sum(array_slice($opencodes, 0, 6)) % 10;
                    $n[] = array_sum(array_slice($opencodes, 6, 6)) % 10;
                    $n[] = array_sum(array_slice($opencodes, 12, 6)) % 10;
                    // 子类结果格式化
                    return join(',', $n);
                },
            ],
        ],
        'createLottery' => [
            'delay'    => 10,
            'interval' => 300,
            'saleTime' => ['9:00:00', '23:50:00', 179], // 彩期时间
//            'start'    => ['2019-10-10', '977255'], // 彩期开始日期和期号
            'start'    => ['2020-11-23'], // 彩期开始日期和期号

        ],
    ],

    [
        'id'    => 44,
        'pid'   => 41,
        'name'  => '加拿大快乐8', // 彩票名字
        'after' => [
            [

                'id'           => 43,
                'pid'          => 1,
                'name'         => '加拿大幸运28',
                'resultFormat' => function ($opencode) {
                    $opencodes = explode(',', $opencode);
                    $n = [];
                    $n[] = array_sum(\Logic\Spider\Utils::getArray($opencodes, 1, 17, 3)) % 10;
                    $n[] = array_sum(\Logic\Spider\Utils::getArray($opencodes, 2, 18, 3)) % 10;
                    $n[] = array_sum(\Logic\Spider\Utils::getArray($opencodes, 3, 19, 3)) % 10;
                    // 子类结果格式化
                    return join(',', $n);
                },
            ],
        ],
    ],

    [
        'id'            => 17,
        'pid'           => 10,
        'name'          => '五分彩', // 彩票名字
        'after'         => [
            [

                'id'           => 4,
                'pid'          => 1,
                'name'         => '台湾幸运28',
                'resultFormat' => function ($opencode) {
                    $opencodes = explode(',', $opencode);
                    $opencodes = array_map("intval", $opencodes);
                    sort($opencodes);
                    $n = [];
                    $n[] = array_sum(array_slice($opencodes, 0, 6)) % 10;
                    $n[] = array_sum(array_slice($opencodes, 6, 6)) % 10;
                    $n[] = array_sum(array_slice($opencodes, 12, 6)) % 10;
                    // 子类结果格式化
                    return join(',', $n);
                },
            ],
        ],
        'createLottery' => [
            'delay'    => 75,
            'interval' => 300,
            'saleTime' => ['07:00:00', '23:55:00', 203],
            // 'start' => ['2017-09-28', '106054811'],
            'start'    => ['2020-11-23'],
        ],
        'resultFormat'  => function ($opencode) {
            $opencodes = explode(',', $opencode);
            $opencodes = array_map("intval", $opencodes);
            sort($opencodes);
            $n = [];
            $n[] = array_sum(array_slice($opencodes, 0, 4)) % 10;
            $n[] = array_sum(array_slice($opencodes, 4, 4)) % 10;
            $n[] = array_sum(array_slice($opencodes, 8, 4)) % 10;
            $n[] = array_sum(array_slice($opencodes, 12, 4)) % 10;
            $n[] = array_sum(array_slice($opencodes, 16, 4)) % 10;
            // 子类结果格式化
            return join(',', $n);
        },
    ],
    //---------------------------------------【时时类】---------------------------------------------------
    [
        'id'            => 11,
        'pid'           => 10,
        'name'          => '重庆时时彩', // 彩票名字
        'createLottery' => [
            'delay'         => 75 - 40,
            'interval'      => 0,
            'fetchInterval' => function ($fetch, $fetchOne, $lotteryNumber, $startDate) {
                // """重庆时时彩每天10:00-22:00（72期）10分钟一期，22:00-02:00（48期）5分钟一期"""
                $intervalConfig = [
                    ['00:30:00', '03:10:00', 20 * 60, 9],
                    ['07:30:00', '23:50:00', 20 * 60, 50],
                ];
                \Logic\Spider\Utils::createIntervalList($fetch, $fetchOne, $intervalConfig, $lotteryNumber, $startDate);
            },
            'saleTime'      => ["07:30:00", "03:10:00", 59],
            'start'         => ['', '01'],
            'fetchNo'       => '\Logic\Spider\Utils::getNo4y',
        ],
    ],

    [
        'id'            => 13,
        'pid'           => 10,
        'name'          => '天津时时彩', // 彩票名字
        'createLottery' => [
            'delay'    => 150,
            'interval' => 1200,
            'saleTime' => ["09:00:00", "22:40:00", 42],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo3',
        ],
    ],

    [
        'id'            => 14,
        'pid'           => 10,
        'name'          => '新疆时时彩', // 彩票名字
        'createLottery' => [
            'delay'    => 100,
            'interval' => 1200,
            'saleTime' => ["10:00:00", "01:40:00", 48],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo3y',
        ],
    ],

    // [
    //     'id' => 16,
    //     'pid' => 10,
    //     'name' => '分分彩', // 彩票名字
    //     'createLottery' => [
    //         'delay' => 75,
    //         // 'delay' => 120,
    //         'interval' => 60,
    //         'saleTime' => ["00:00:00", "24:00:00", 1440],
    //         'start' => ['', '1'],
    //         'fetchNo' => '\Logic\Spider\Utils::getNo2',
    //     ],
    // ],

    // [
    //     'id' => 19,
    //     'pid' => 10,
    //     'name' => '三分彩', // 彩票名字
    //     'createLottery' => [
    //         'delay' => 5 * 60,
    //         'interval' => 180,
    //         'saleTime' => ["00:00:00", "24:00:00", 480],
    //         'start' => ['', '1'],
    //         'fetchNo' => '\Logic\Spider\Utils::getNo3',
    //     ],
    // ],

    // ----------------------------------------------【M选N】---------------------------------------------------
    [
        'id'            => 25,
        'pid'           => 24,
        'name'          => '江西11选5', // 彩票名字
        'createLottery' => [
            'delay'    => 100,
            'interval' => 1200,
            'saleTime' => ["09:10:00", "22:50:00", 42],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo5',
        ],
        'stand'         => 'http://www.jxlottery.cn/',
    ],

    [
        'id'            => 26,
        'pid'           => 24,
        'name'          => '江苏11选5', // 彩票名字
        'createLottery' => [
            'delay'    => 120,
            'interval' => 1200,
            'saleTime' => ["08:26:00", "21:46:00", 41],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo5',
        ],
    ],

    [
        'id'            => 27,
        'pid'           => 24,
        'name'          => '广东11选5', // 彩票名字
        'createLottery' => [
            'delay'    => 120,
            'interval' => 1200,
            'saleTime' => ["09:10:00", "22:50:00", 42],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo5',
        ],
    ],

    [
        'id'            => 28,
        'pid'           => 24,
        'name'          => '山东11选5', // 彩票名字
        'createLottery' => [
            'delay'    => 120,
            'interval' => 1200,
            'saleTime' => ["08:42:00", "22:42:00", 43],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo5',
        ],
    ],

    [
        'id'            => 29,
        'pid'           => 24,
        'name'          => '安徽11选5', // 彩票名字
        'createLottery' => [
            'delay'    => 100,
            'interval' => 1200,
            'saleTime' => ["08:42:00", "21:42:00", 40],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo5',
        ],
        'stand'         => 'http://www.ahtc.gov.cn/',
    ],

    [
        'id'            => 30,
        'pid'           => 24,
        'name'          => '上海11选5', // 彩票名字
        'createLottery' => [
            'delay'    => 100,
            'interval' => 1200,
            'saleTime' => ["09:00:00", "23:40:00", 45],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo5',
        ],
    ],

    //------------------------------------------【赛车】-----------------------------------------------------
    [
        'id'            => 40,
        'pid'           => 39,
        'name'          => '北京赛车', // 彩票名字
        'createLottery' => [
            'delay'    => 120,
            'interval' => 1200,
            'saleTime' => ["09:10:00", "23:30:00", 44],
            //'start' => ['2017-09-28', '642219'],
//            'start'    => ['2019-10-09', '739644'],
            'start'    => ['2020-05-06', '744264'],
            // 'fetchNo' => '\Logic\Spider\Utils::getNo3',
        ],
    ],

    [
        'id'            => 45,
        'pid'           => 39,
        'name'          => '幸运飞艇', // 彩票名字
        'createLottery' => [
            'delay'    => 10,
            'interval' => 300,
            'saleTime' => ["13:04:00", "04:09:00", 180],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo3y',
        ],
    ],

    //---------------------------------------【快3类】---------------------------------------------------
    [
        'id'            => 6,
        'pid'           => 5,
        'name'          => '广西快三', // 彩票名字
        'createLottery' => [
            'delay'    => 120,
            'interval' => 1200,
            'saleTime' => ["09:10:00", "22:10:00", 40],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo3',
        ],
    ],

    [
        'id'            => 7,
        'pid'           => 5,
        'name'          => '江苏快三', // 彩票名字
        'createLottery' => [
            'delay'    => 120,
            'interval' => 1200,
            'saleTime' => ["08:30:00", "21:50:00", 41],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo3',
        ],
    ],

    [
        'id'            => 8,
        'pid'           => 5,
        'name'          => '安徽快三', // 彩票名字
        'createLottery' => [
            'delay'    => 120,
            'interval' => 1200,
            'saleTime' => ["08:40:00", "21:40:00", 40],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo3',
        ],
    ],

    [
        'id'            => 9,
        'pid'           => 5,
        'name'          => '吉林快三', // 彩票名字
        'createLottery' => [
            'delay'    => 120,
            'interval' => 1190,
            'saleTime' => ["08:20:30", "21:15:30", 40],
            'start'    => ['', '1'],
            'fetchNo'  => '\Logic\Spider\Utils::getNo3',
        ],
    ],

    //---------------------------------------【六合彩】---------------------------------------------------
    // [
    //     'id' => 52,
    //     'pid' => 51,
    //     'name' => '香港六合彩', // 彩票名字
    //     'createLottery' => [
    //         'delay' => 0,
    //         'interval' => 86400,
    //         'saleTime' => ["21:30:00", "21:31:00", 1],
    //         'start' => ['db', '2018-03-31', '2018034'],
    //         'fetchNo' => '\Logic\Spider\Utils::getNo0',
    //         'fetchInterval' => function ($fetch, $fetchOne, $lotteryNumber, $startDate) {
    //             // """重庆时时彩每天10:00-22:00（72期）10分钟一期，22:00-02:00（48期）5分钟一期"""
    //             // $intervalConfig = [
    //             //     ['10:00:00', '22:00:00', 10 * 60, 72],
    //             //     ['22:00:00', '02:00:00', 5 * 60, 48],
    //             // ];
    //             // \Logic\Spider\Utils::createIntervalList($fetch, $fetchOne, $intervalConfig, $lotteryNumber, $startDate);
    //             echo 'Test', PHP_EOL;
    //             print_r($fetchOne);
    //         },

    //     ],

    // ],

    //---------------------------------------【六合彩】---------------------------------------------------
    [
        'id'            => 52,
        'pid'           => 51,
        'name'          => '香港六合彩', // 彩票名字
        'createLottery' => [
            'delay'         => 0,
            'interval'      => 86400,
            'saleTime'      => ["21:30:00", "21:31:00", 1],
            'start'         => ['db', '2018-07-21', '2018081'],
            'fetchNo'       => '\Logic\Spider\Utils::getNo0',
            'fetchInterval' => function ($fetch, $fetchOne, $lotteryNumber, $startDate) {
                // """六合彩周二 周四 周六 21：30 - 21:40""
                $intervalConfig = [
                    ['21:30:00', '21:40:00', 10 * 60, 1],
                ];
                \Logic\Spider\Utils::createIntervalList($fetch, $fetchOne, $intervalConfig, $lotteryNumber, $startDate);
                echo 'Test', PHP_EOL;
                print_r($fetchOne);
            },

        ],

    ],


];
