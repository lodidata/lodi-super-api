<?php
//Linux定时器以防进程没跑了
$callback = new Logic\Recharge\Recharge($app->getContainer());
echo 'test',PHP_EOL;
$callback->notifyCustomer();