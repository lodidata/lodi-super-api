<?php
namespace Utils;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
/**
 * rabbitmq 模块
 */
class MQServer {

    /**
     * exchange 加上厅主 id
     * @param  [type] $exchange [description]
     * @return [type]           [description]
     */
    protected static function getExchange($exchange) {
        global $app;
        $tid = $app->getContainer()->get('settings')['app']['tid'];
        return $exchange.'_'.$tid;
    } 

    /**
     * 启动服务
     * @param  [type] $exchange     [description]
     * @param  [type] $queue        [description]
     * @param  [type] $callback     [description]
     * @param  array  $rabbitmqConf [description]
     * @return [type]               [description]
     */
    public static function startServer($exchange, $queue, $callback, $rabbitmqConf = []) {
        global $app;
        $rabbitmqConf = !empty($rabbitmqConf) ? $rabbitmqConf : $app->getContainer()->get('settings')['rabbitmq'];
        $exchange = self::getExchange($exchange);
        $connection = new AMQPStreamConnection($rabbitmqConf['host'], $rabbitmqConf['port'], $rabbitmqConf['user'], $rabbitmqConf['password'], $rabbitmqConf['vhost']);
        $channel = $connection->channel();
        $channel->exchange_declare($exchange, 'fanout', false, true, false);
        $channel->queue_declare($queue, false, false, true, false);
        $channel->queue_bind($queue, $exchange);

        $channel->basic_qos(null, 1, null);
        $channel->basic_consume($queue, '', false, false, false, false, $callback);
        while (count($channel->callbacks)) {
            $channel->wait();
        }
        $channel->close();
        $connection->close();
    }

    /**
     * 发送广播
     * @param  [type] $exchange     [description]
     * @param  [type] $msg          [description]
     * @param  array  $rabbitmqConf [description]
     * @return [type]               [description]
     */
    public static function send($exchange, $msg, $rabbitmqConf = []) {
        global $app;
        $msg = is_array($msg) ? json_encode($msg, JSON_UNESCAPED_UNICODE) : $msg;
        $exchange = self::getExchange($exchange);
        $rabbitmqConf = !empty($rabbitmqConf) ? $rabbitmqConf : $app->getContainer()->get('settings')['rabbitmq'];
        $connection = new AMQPStreamConnection($rabbitmqConf['host'],
        $rabbitmqConf['port'],
        $rabbitmqConf['user'],
        $rabbitmqConf['password'],
        $rabbitmqConf['vhost'],
        $insist = false,
        $login_method = 'AMQPLAIN',
        $login_response = null,
        $locale = 'en_US',
        $connection_timeout = 10.0,
        $read_write_timeout = 10.0);
        $channel = $connection->channel();
        $msg = new AMQPMessage($msg);
        $channel->exchange_declare($exchange, 'fanout', false, true, false);
        $channel->basic_publish($msg, $exchange);
        $channel->close();
        $connection->close();
    }

}