<?php
define('RUNMODE', 'dev');
return [
    'settings' => [

        'admin' => [

            'db' => [
                'default' => [
                    'driver' => 'mysql',
                    'host' => '13.70.3.15',
                    'port' => '3306',
                    'database' => 'super_admin',
                    'username' => 'chess_user',
                    'password' => '6yaQY69TbfgNDGbY',
                    'charset' => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix' => ''
                ],
                'common' => [
                    'driver' => 'mysql',
                    'host' => '13.70.3.15',
                    'database' => 'common',
                    'username' => 'chess_user',
                    'password' => '6yaQY69TbfgNDGbY',
                    'charset' => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix' => ''
                ],
                'state' => [
                    'driver'    => 'mysql',
                    'host' => '13.70.3.156',
                    'port' => '3306',
                    'database' => 'super_admin',
                    'username' => 'chess_user',
                    'password' => '6yaQY69TbfgNDGbY',
                    'charset'   => 'utf8',
                    'collation' => 'utf8_general_ci',
                    'prefix'    => '',
                ],
            ],

           //jwt
            'jsonwebtoken' => [
                'public_key' => 'B6c5c11fe441acf6F71cab9c5debb6ee-=master-backend',
                'uid_digital' => 116624, // 简单的伪装码key
                'expire' => 3600, //单位：秒
            ],
        ],

        'ip' => ['192.168.50.*','0.0.0.0'],   //总的IP白名单
        'test' => true,
        'displayErrorDetails' => false, // set to false in production        正式上线改为false
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header
        'db' => [
            'default' => [                  //改为线上的SQL地址
                'driver' => 'mysql',
                'host' => '13.70.3.15',
                'database' => 'recharge',
                'username' => 'chess_user',
                'password' => '6yaQY69TbfgNDGbY',
                'charset' => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix' => ''
            ],
            'common' => [
                'driver' => 'mysql',
                'host' => '13.70.3.15',
                'database' => 'common',
                'username' => 'chess_user',
                'password' => '6yaQY69TbfgNDGbY',
                'charset' => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix' => ''
            ]
        ],



        // 平台mongodb
        'mongodb' => [  //改为线上的地址
            'host' => '10.200.124.16',
            'port' => '27017',
            'user' => null,
            'password' => null,
        ],

        // rabbitmq
        'rabbitmq' => [  //改为线上的SQL地址
            'host' => '127.0.0.1',
            'port' => '5672',
            'user' => 'tcpay',
            'password' => 't4O8JnKav0S38tcpay',
            'vhost' => '/pay',
        ],
        // redis
        'cache' => [
            'schema' => 'tcp',
            'host' => '13.70.3.15',
            'port' => '6382',
            'database' => 1,
            'password' => 'cv6bkkfBtIvIIUz9'
        ],
        // 平台通用cache
        'cacheCommon' => [
            'schema' => 'tcp',
            'host' => '13.70.3.15',
            'port' => '6382',
            'database' => 0,
            'password' => 'cv6bkkfBtIvIIUz9'
        ],
        // 名称   payrequest   host  改为线上的
        //'website' => ['name'=>'支付平台','payrequest' => 'https','host'=>'pay-api.qingyugu.net','alias' => 'recharge'],
        //'website' => ['name'=>'支付平台','payrequest' => 'https','host'=>'pay-api.23card.net','alias' => 'recharge'],

        'customer' => ['dir' => 'funds/recharge','method'=>'GET'],
        // app
        'app' => [
            'tid' => 888888, // 支付平台
            'app_secret' => 'cf28e0cede9662e034bd68a55acec1213', // 不可逆，用以校验签名，支付平台及各客户业务平台各存一份
            'app_key' => 'Fv72XW9Vw5sHbd1p', // 双向加密密钥，长度16字节(字符可以是多字节)。一旦写入不可更改
        ],
        // 开彩网配置
        'apiplus' => ['url' => 'http://e.apiplus.net', 'token' => 'td0ccdedc47e4245dk'],
        // 彩票按配置
        'caipaokong' => ['url' => 'http://api.kaijiangtong.com/lottery/', 'uid' => '1668777', 'token' => '16cac26114de2721ca872f498aa0908a2e68409d'],
        // 名称   payrequest   host  改为线上的
        //'website' => ['name'=>'支付平台','payrequest' => 'https','host'=>'pay-api.qingyugu.net','alias' => 'recharge'],
        //'website' => ['name'=>'支付平台','payrequest' => 'https','host'=>'pay-api.23card.net','alias' => 'recharge'],

        // Monolog 日志设置
        'logger' => [
            'name' => 'cp-plat',
            'path' => '/data/logs/php/',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ],
];

