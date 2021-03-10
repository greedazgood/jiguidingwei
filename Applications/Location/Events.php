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

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除.
 */
class Events
{
    public static $time1;
    public static $time2;

    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect.
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        self::$time1 = microtime(true);
        // 向当前client_id发送数据
//        $str = "{\"code\":\"2000\",\"runMode\":1,\"msg\":{\"Infos\":[{\"State\":0,\"UCount\":1,\"ULoc\":5},{\"State\":0,\"UCount\":1,\"ULoc\":8},{\"State\":0,\"UCount\":1,\"ULoc\":12},{\"State\":2,\"UCount\":1,\"ULoc\":16},{\"State\":1,\"UCount\":1,\"ULoc\":3}]}}";
//        $ret = openssl_encrypt($str,'AES-128-ECB', '0214578654125847');
//        var_dump($ret);
//        Gateway::sendToClient($client_id, $ret);
        // 向所有人发送
        //Gateway::sendToAll("$client_id login\r\n");
    }

    /**
     * 当客户端发来消息时触发.
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $data)
    {
        $str = "{\"code\":\"2000\",\"runMode\":1,\"msg\":{\"Infos\":[{\"State\":1,\"UCount\":1,\"ULoc\":5},{\"State\":1,\"UCount\":1,\"ULoc\":8},{\"State\":1,\"UCount\":1,\"ULoc\":12},{\"State\":2,\"UCount\":1,\"ULoc\":16},{\"State\":1,\"UCount\":1,\"ULoc\":3}]}}";
        $ret = openssl_encrypt($str,'AES-128-ECB', '0214578654125847');
        var_dump($ret);
        Gateway::sendToClient($client_id, $ret);
//        $ret = openssl_decrypt($data, 'AES-128-ECB', '0214578654125847',2);
//        $ret = preg_replace('/[\x00-\x1F]/','', $ret);
//        $result = json_decode($ret);
        //$data = $result->head;
        //16进制数据
//        try {
//            $config = require __DIR__.'/../../config.php';
//            $time_interval = $config['interval'];
//            $url = $config['url'];
//            $client = new Client();
//            self::$time2 = microtime(true);
//            $diff = self::$time2 - self::$time1;
//            if (1000 * $diff >= $time_interval) {
//                self::$time1 = microtime(true);
//                $all_session = Gateway::getAllClientSessions();
//                $session_values = array_values($all_session);
//                $session_keys = array_keys($all_session);
//                $client_info = array_column($session_values, 'interval');
//                $client->request('POST', $url, [
//                    'json'=> $client_info
//                ]);
//                foreach ($session_keys as $key){
//                    Gateway::updateSession($key,[]);
//                }
//            }
//        } catch (\Throwable $throwable) {
//            $myfile = fopen(__DIR__.'/../../location.log', 'ab');
//            fwrite($myfile, $throwable->getTraceAsString().PHP_EOL);
//            fclose($myfile);
//        }
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

    public static function putCsv($data)
    {
        $fp = fopen(__DIR__.'/../../info.csv', 'ab');
        fputcsv($fp, $data);
        fclose($fp);
    }
}
