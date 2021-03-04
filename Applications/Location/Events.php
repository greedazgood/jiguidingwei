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
        try {
            $hex_data = bin2hex($data);
            $position_info = substr($hex_data, 24, 36);
            $uid = substr($hex_data, 14, 8);
            $para1 = substr($hex_data, 22, 2);
            $para1_bin = str_pad(base_convert($para1, 16, 2), 8, '0', STR_PAD_LEFT);
            $battery = $para1_bin[0];
            $reset = $para1_bin[1];
            $alert = $para1_bin[2];
            $trap = $para1_bin[3];
            $model = $para1_bin[4];
            $vol = self::getVoltage($para1_bin);
            $result_data = [];
            for ($i = 0 ;$i < 6;$i++) {
                $position_hex = substr($position_info, $i * 6, 6);
                $front_data = substr($position_hex, 0, 4);
                $end_data = substr($position_hex, 4, 2);
                $front_bin = base_convert($front_data, 16, 2);
                $end_bin = base_convert($end_data, 16, 2);
                $result_data[] = [
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
            $uid_dec = hexdec($uid);
            $result = [
                'uid'    => $uid_dec,
                'time'   => $new_time,
                'lfUid1' => $result_data[0]['trigger_id'],
                'rss1'   => $result_data[0]['rss'],
                'lfUid2' => $result_data[1]['trigger_id'],
                'rss2'   => $result_data[1]['rss'],
                'lfUid3' => $result_data[2]['trigger_id'],
                'rss3'   => $result_data[2]['rss']
            ];
            $_SESSION['interval'] = $result;
            $info = [
                'sys_time' => date('Y-m-d H:i:s'),
                'addr'     => $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'],
                'uid'      => $uid_dec,
                'time'     => $new_time,
                'para1'    => $battery,
                'para2'    => $reset,
                'para3'    => $alert,
                'para4'    => $trap,
                'para5'    => $model,
                'para6'    => $vol,
                'lfUid1'   => $result_data[0]['trigger_id'],
                'rss1'     => $result_data[0]['rss'],
                'lfUid2'   => $result_data[1]['trigger_id'],
                'rss2'     => $result_data[1]['rss'],
                'lfUid3'   => $result_data[2]['trigger_id'],
                'rss3'     => $result_data[2]['rss'],
                'lfUid4'   => $result_data[3]['trigger_id'],
                'rss4'     => $result_data[3]['rss'],
                'lfUid5'   => $result_data[4]['trigger_id'],
                'rss5'     => $result_data[4]['rss'],
                'lfUid6'   => $result_data[5]['trigger_id'],
                'rss6'     => $result_data[5]['rss'],
            ];
            self::putCsv($info);
            $config = require __DIR__.'/../../config.php';
            $time_interval = $config['interval'];
            $url = $config['url'];
            $client = new Client();
            self::$time2 = microtime(true);
            $diff = self::$time2 - self::$time1;
            if (1000 * $diff >= $time_interval) {
                self::$time1 = microtime(true);
                $client_info = array_column(array_values(Gateway::getAllClientSessions()), 'interval');
                $client->request('POST', $url, [
                    'json'=> $client_info
                ]);
            }
        } catch (\Throwable $throwable) {
            $myfile = fopen(__DIR__.'/../../location.log', 'ab');
            fwrite($myfile, $throwable->getTraceAsString().PHP_EOL);
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

    public static function getTriggerId($bin)
    {
        $triger_id = bindec(substr($bin, 0, 13));
        if ($triger_id < 8) {
            return 0;
        } elseif ($triger_id >= 8) {
            return $triger_id;
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

    public static function getVoltage($bin)
    {
        $vol = bindec(substr($bin, 5, 3));
        $status = [
            '2.00~2.09', '2.10~2.19', '2.20~2.29', '2.30~2.39', '2.40~2.49', '2.50~2.59', '2.60~2.69', '2.70~2.79'
        ];
        if (array_key_exists($vol, $status)) {
            return $status[$vol];
        } else {
            return '范围外电压';
        }
    }

    public static function putCsv($data)
    {
        $fp = fopen(__DIR__.'/../../info.csv', 'ab');
        fputcsv($fp, $data);
        fclose($fp);
    }
}
