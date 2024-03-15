<?php

\Utils\MQServer::send('recharge_callback', ['order_number'=>'201806041752537701','money'=>5000]);

//测试命令
//sudo php runBin.php src/test/testCallback

//启动workman
//sudo php callbackServer.php restart -d
//sudo php callbackServer.php restart -t
