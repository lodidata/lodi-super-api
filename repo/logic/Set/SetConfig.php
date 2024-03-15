<?php
/**
 * Created by PhpStorm.
 * User: tyleryang
 * Date: 2018/3/23
 * Time: 12:08
 */

namespace Logic\Set;

class SetConfig
{
    /**
     * 全局设置 tz
     */
    const SET_GLOBAL = 'system.config.global';
    /**
     * 站点设置
     */
    const SET_WEBSITE = 'system.config.website';


    const DATA = [
        SetConfig::SET_GLOBAL               => [
            'base'  => [
                'Duplicate_LoginCheck'  => true,
                'IP_limit'                => false,
            ],
            'stopshelling' =>[
                'date_start'=> '2020-01-22',
                'date_end'  => '2020-02-01',
                'lottery'   => []
            ]
        ],

        SetConfig::SET_WEBSITE              => [
            'name'           => '',
            'title'          => '',
            'keywords'       => '',
            'description'    => '',
            'template'       => '',
            'logo'           => '',
            'message_bottom' => '',
        ],

    ];
}
