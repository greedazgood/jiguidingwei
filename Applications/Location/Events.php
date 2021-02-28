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

use \GatewayWorker\Lib\Gateway;
use GuzzleHttp\Client;
use Workerman\Lib\Timer;

require_once __DIR__. '/../../config.php';

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除.
 */
class Events
{
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect.
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        // 向当前client_id发送数据
        //Gateway::sendToClient($client_id, "Hello $client_id\r\n");
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
        //16进制数据
        try{
            $hex_data = bin2hex($data);
            $position_info = substr($hex_data, 24, 36);
            $uid = substr($hex_data, 14, 8);
            $data = [];
            for ($i = 0 ;$i < 6;$i++) {
                $position_hex = substr($position_info, $i * 6, 6);
                $front_data = substr($position_hex, 0, 4);
                $end_data = substr($position_hex, 4, 2);
                $front_bin = base_convert($front_data, 16, 2);
                $end_bin = base_convert($end_data, 16, 2);
                $data[] = [
                    'trigger_id' => self::getTriggerId($front_bin),
                    'wire_id'    => bindec(substr($front_bin, 13, 3)),
                    'xyz'        => self::getXyz($end_bin),
                    'status'     => self::getStatus($end_bin),
                    'rss'        => bindec(substr($end_bin, 3, 5))
                ];
            }
            $time = substr($hex_data, 62, 6);
            $hour = hexdec(substr($time, 0, 2));
            $minute = hexdec(substr($time, 2, 2));
            $second = hexdec(substr($time, 4, 2));
            $new_time = strtotime($hour.':'.$minute.':'.$second);
            $result = [
                'uid' =>hexdec($uid),
                'time' => $new_time,
                'lfUid1' => $data[0]['trigger_id'],
                'rss1' => $data[0]['rss'],
                'lfUid2' => $data[1]['trigger_id'],
                'rss2' => $data[1]['rss'],
                'lfUid3' => $data[2]['trigger_id'],
                'rss3' => $data[2]['rss'],
                'lfUid4' => $data[3]['trigger_id'],
                'rss4' => $data[3]['rss'],
                'lfUid5' => $data[4]['trigger_id'],
                'rss5' => $data[4]['rss'],
                'lfUid6' => $data[5]['trigger_id'],
                'rss6' => $data[5]['rss'],
            ];
            global $config;
            $time_interval =$config['interval'];
            $url = $config['url'];
            $client = new Client();
            $_SESSION['auth_timer_id'] = Timer::add($time_interval, function()use($result,$url,$client)
            {
                $response = $client->request('POST',$url,[
                    'json'=>$result
                ]);
                var_dump($response->getBody());
            });
        }catch (\Throwable $throwable){
            $myfile = fopen("../../location.log", "w") or die("Unable to open file!");
            fwrite($myfile,$throwable->getTraceAsString().PHP_EOL);
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
        Timer::del($_SESSION['auth_timer_id']);
    }

    public static function getTriggerId($bin)
    {
        $triger_id = bindec(substr($bin, 0, 13));
        if ($triger_id < 8) {
            return '测试数据';
        } elseif ($triger_id >= 8 && $triger_id <= 8159) {
            return $triger_id;
        } elseif ($triger_id == 8160) {
            return  '标签在触发区域外';
        } else {
            return '特殊控制字符';
        }
    }

    public static function getXyz($bin)
    {
        $xyz = bindec(substr($bin, 0, 2));
        if ($xyz == 0) {
            return '保留';
        } elseif ($xyz == 1) {
            return 'x';
        } elseif ($xyz == 2) {
            return 'y';
        } else {
            return 'z';
        }
    }

    public static function getStatus($bin)
    {
        $status = bindec(substr($bin, 2, 1));
        if ($status == 0) {
            return '正常当前触发器触发';
        } elseif ($status == 1) {
            return '上次最后离场的触发器触发';
        }
    }
}
