<?php
class DB extends \Illuminate\Database\Capsule\Manager {

    /**
     * Get a fluent raw query expression.
     *
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Expression
     */
    public static function raw($value) {
        return static::$instance->connection()->raw($value);
    }

    /**
     * 合并字段
     */
    public static function mergeColumns($select1, $select2) {

        if (empty($select1)) {
            return [];
        }

        if ($select2 == '*' || empty($select2)) {
            return $select1;
        }

        if (!is_array($select2)) {
            $select2 = explode(',', $select2);  
        }

        $temp = [];
        foreach ($select2 as $key => $val) {
            if (isset($select1[$val])) {
                $temp[$val] = $select1[$val];
            }
        }
        return $temp;
    }

    /**
     * 调用存储过程，合并多个结果集
     * @param string $method
     * @param array $params
     * @return array
     */
    public static function procedureDataSet($method = '',$params=[]){

        if(!$method){
            return [];
        }

        $paramStr = implode(',',$params);
        $dbh = self::connection()->getPdo();

        $stmt = $dbh->prepare("call  $method($paramStr)");
        $stmt->execute();
        $result = [];
        $result[] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        do {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                array_push($result,$rows);
            }
        }while ($stmt->nextRowset());

        return $result;
    }

    /**
     * 对象转数组
     * @param  [type] $val [description]
     * @return [type]      [description]
     */
    public static function resultToArray($val) {
        if ($val instanceof \Illuminate\Database\Eloquent\Model) {
            $val = $val->toArray();
        }

        foreach ($val ?? [] as $k => $v) {
            $val[$k] = (array) $v;
        }
        return $val;
    }

    public static function getIPv6() {
        $ip =  \Utils\Client::getIp();
        return self::raw("inet6_aton('$ip')");
    }
}


function D($data) {
    global $app;
    echo $app->getContainer()->response
            ->withStatus(500)
            // ->withHeader('Access-Control-Allow-Origin', '*')
            // ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            // ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Content-Type', 'application/json')
            ->withJson($data);
    exit;
}