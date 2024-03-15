<?php
namespace Logic\Shop;

use Logic\Shop\Traits\RechargePayWebsite;
use Logic\Shop\Traits\RechargeHandDeposit;
use Logic\Shop\Traits\RechargeOnlinePay;
use Logic\Shop\Traits\RechargePay;
use Logic\Shop\Traits\RechargeDeposit;

//use Logic\Shop\Traits\RechargeExchange;
//use Logic\Shop\Traits\RechargeTzHandRecharge;
//use Logic\Shop\Traits\RechargeHandoutActivity;
//use Logic\Shop\Traits\RechargeAddActivity;
//use Logic\Shop\Traits\RechargeHandApply;
//use Logic\Shop\Traits\RechargeAdminOptMoney;

class Recharge extends \Logic\Logic {
    use RechargePayWebsite;
    use RechargeHandDeposit;
    use RechargeOnlinePay;
    use RechargePay;
    use RechargeDeposit;

//    use RechargeTzHandRecharge;
//    use RechargeHandoutActivity;
//    use RechargeAddActivity;
//    use RechargeAdminOptMoney;
//    use RechargeHandApply;
}