<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常.
 */
//declare(ticks=1);

use GuzzleHttp\Client;
use \GatewayWorker\Lib\Gateway;
use Workerman\MySQL\Connection;
use Workerman\Worker;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除.
 */
class Events
{
    public static $db = null;

    public static function onWorkerStart($businessWorker)
    {
        self::$db = new Connection('127.0.0.1','3306','root','root','location');
    }
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect.
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {

    }

    /**
     * 当客户端发来消息时触发.
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $data)
    {
        $ret = openssl_decrypt($data, 'AES-128-ECB', '0214578654125847',2);
        //$ret = preg_replace('/[\x00-\x1F]/','', $ret);
        $result = json_decode($ret,true);
        try {
            $order['exInfo'] = $result['exInfo'];
            $order['order'] = $data;
            $order['status'] = 0;
            self::$db->insert('order_info')->cols($order)->query();
            echo "接收指令入库";
            $result = [
                'code'=>1000
            ];
            $info =  openssl_encrypt(json_encode($result),'AES-128-ECB', '0214578654125847');
            Gateway::sendToCurrentClient($info);
        } catch (\Throwable $throwable) {
            $myfile = fopen(__DIR__.'/../../location.log', 'ab');
            fwrite($myfile, $throwable->getMessage().PHP_EOL);
            fclose($myfile);
        }
    }

    /**
     * 当用户断开连接时触发.
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        // 向所有人发送
       //GateWay::sendToAll("$client_id logout\r\n");
    }
}
