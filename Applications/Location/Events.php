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
use Workerman\Timer;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除.
 */
class Events
{
    public static $db = null;
    public static $runMode1;
    public static $runMode2;
    public static $runMode3;

    public static function onWorkerStart($businessWorker)
    {
        $runMode1 = "{\"code\":2000,\"runMode\":1,\"msg\":{}}";
        $runMode2 = "{\"code\":2000,\"runMode\":2,\"msg\":{}}";
        $runMode3 = "{\"code\":2000,\"runMode\":3,\"msg\":{}}";
        self::$runMode1 = openssl_encrypt($runMode1,'AES-128-ECB', '0214578654125847');
        self::$runMode2 = openssl_encrypt($runMode2,'AES-128-ECB', '0214578654125847');
        self::$runMode3 = openssl_encrypt($runMode3,'AES-128-ECB', '0214578654125847');
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
        $config = require __DIR__.'/../../config.php';
        $url = $config['url'];
        $interval = $config['interval'];
        echo "定时".$interval.PHP_EOL;
        Timer::add($interval,function ()use($url,$client_id){
            $uid = Gateway::getUidByClientId($client_id);
            //echo "固定数据发送".PHP_EOL;
            if ($uid){
                $client = new Client();
                $data = self::$db->select('*')->from('basic_info')->where("exInfo=\"".$uid."\"")->row();
                $data['airSpeed'] = unserialize($data['airSpeed']);
                $data['drSwitch'] = unserialize($data['drSwitch']);
                $data['humiture'] = unserialize($data['humiture']);
                $data['label_info'] = unserialize($data['label_info']);
                $json_data['head'] = [
                    'sw_v' =>$data['sw_v'],
                    'index' =>$data['index'],
                    'time' =>$data['time'],
                    'id' =>$data['id'],
                    'uCnt' =>$data['uCnt'],
                    'labelCnt' =>$data['labelCnt'],
                    'humCnt' =>$data['humCnt'],
                    'exInfo' =>$data['exInfo'],
                    'runMode' =>$data['runMode'],
                ];
                $json_data['airSpeed'] = $data['airSpeed'];
                $json_data['drSwitch'] = $data['drSwitch'];
                $json_data['humiture'] = $data['humiture'];
                $json_data['label_info'] = $data['label_info'];
                $info = openssl_encrypt(json_encode($json_data),'AES-128-ECB', '0214578654125847');
                try{
                    $client->request('POST', $url, [
                        'body'=> $info
                    ]);
                }catch (\Throwable $throwable){
                    echo '';
                }
            }
        });
    }

    /**
     * 当客户端发来消息时触发.
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $data)
    {
        $ret = openssl_decrypt($data, 'AES-128-ECB', '0214578654125847',2);
        $ret = preg_replace('/[\x00-\x1F]/','', $ret);
        $result = json_decode($ret,true);
        try {
            if (isset($result['msg'])){
                echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%".PHP_EOL;
                echo "发送灯控指令".PHP_EOL;
                $board_uid = Gateway::getClientIdByUid($result['exInfo']);
                $feedback = [
                    'code'=>1000
                ];
                $msg['code'] = 2000;
                $msg['runMode'] = 1;
                $msg['msg'] = $result['msg'];
                $info =  openssl_encrypt(json_encode($feedback),'AES-128-ECB', '0214578654125847');
                $msg =  openssl_encrypt(json_encode($msg),'AES-128-ECB', '0214578654125847');
                if (isset($board_uid[0])){
                    Gateway::sendToClient($board_uid[0],$msg);
                }
                Gateway::sendToClient($client_id,$info."\r\n");
            }
            if (isset($result['head'])){
                echo '############################################'.PHP_EOL;
                $info = $result['head'];
                echo "标签数量：".count($info['label_info']).PHP_EOL;
                foreach ($info['label_info'] as $k){
                    echo json_encode($k).PHP_EOL;
                }
                $info['board_id'] = $info['id'];
                unset($info['id']);
                $info['airSpeed'] = isset($info['airSpeed'])?serialize($info['airSpeed']):'';
                $info['drSwitch'] = isset($info['drSwitch'])?serialize($info['drSwitch']):'';
                $info['humiture'] = serialize($info['humiture']);
                $info['label_info'] = serialize($info['label_info']);
                Gateway::bindUid($client_id,$result['head']['exInfo']);
                $if_exist = self::$db->select('*')->from('basic_info')->where("exInfo=\"".$info['exInfo']."\"")->row();
                $if_same = $if_exist['label_info'] == $info['label_info']?true:false;
                if ($if_exist){
                    if (isset($info['runMode']) && $info['runMode'] ==1){
                        $sentData = $if_same?self::$runMode3:self::$runMode2;//数据一致 发送runMode = 3;数据不一致 发送runMode = 2
                        Gateway::sendToClient($client_id,$sentData);
                    }
                    if (isset($info['runMode']) && $info['runMode'] ==2){
                        if ($if_same){
                            $sentData = self::$runMode3;
                            Gateway::sendToClient($client_id,$sentData);//数据一致 发送runMode =3
                        }else{
                            self::$db->update('basic_info')->cols($info)->where("exInfo=\"".$info['exInfo']."\"")->query();
                            $config = require __DIR__.'/../../config.php';
                            $url = $config['url'];
                            $fa_data['head'] = [
                                'sw_v' =>$info['sw_v'],
                                'index' =>$info['index'],
                                'time' =>$info['time'],
                                'id' =>$info['board_id'],
                                'uCnt' =>$info['uCnt'],
                                'labelCnt' =>$info['labelCnt'],
                                'humCnt' =>$info['humCnt'],
                                'exInfo' =>$info['exInfo'],
                                'runMode' =>$info['runMode'],
                            ];
                            $fa_data['airSpeed'] = unserialize($info['airSpeed']);
                            $fa_data['drSwitch'] = unserialize($info['drSwitch']);
                            $fa_data['humiture'] = unserialize($info['humiture']);
                            $fa_data['label_info'] = unserialize($info['label_info']);
                            $fa_info = openssl_encrypt(json_encode($fa_data),'AES-128-ECB', '0214578654125847');
                            $client = new Client();
                            //echo "发送到后台数据".json_encode($result).PHP_EOL;
                            try{
                                $client->request('POST', $url, [
                                    'body'=> $fa_info
                                ]);
                            }catch (\Throwable $throwable){
                                echo '';
                            }
                            //echo "发送轮询指令".PHP_EOL;
                            $sentData = self::$runMode2;
                            Gateway::sendToClient($client_id,$sentData);//数据一致 发送runMode =3
                        }
                    }
                    if (isset($info['runMode']) && $info['runMode'] ==3 ){
                        //echo "runMode = 3".PHP_EOL;
                        $sentData = $if_same ? self::$runMode3:self::$runMode2;//一致发送runMode = 3 不一致发送runMode = 2
                        Gateway::sendToClient($client_id,$sentData);
                    }
                }else{
                    //echo "数据添加".PHP_EOL;
                    if (isset($info['runMode'])){
                        self::$db->insert('basic_info')->cols($info)->query();
                        //echo "插入数据".PHP_EOL;
                    }
                }
            }

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
