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
        $ret = preg_replace('/[\x00-\x1F]/','', $ret);
        $result = json_decode($ret,true);
        var_dump($result);
        try {
            if (isset($result['head'])){
                Gateway::bindUid($client_id,$result['head']['exInfo']);
                $if_exist = self::$db->select('*')->from('basic_info')->where("exInfo=".$result['head']['exInfo'])->row();
                var_dump($if_exist);
                if ($if_exist){
                    echo '数据存在';
                    if ($result['head']['runMode'] ==1){
                        //数据一致 发送runMode = 3
                        //数据不一致 发送runMode = 2
                    }
                    if ($result['head']['runMode'] ==2){
                        //数据一致 发送runMode =3
                        //数据不一致 写入数据库 更新 只有这个情况更新了数据库 会上传数据
                    }
                    if ($result['head']['runMode'] ==3 ){
                        //一致发送runMode = 3
                        //不一致发送runMode = 2
                    }
                }else{
                    $data = $result['head'];
                    self::$db->insert('basic_info')->cols($data)->query();
                    var_dump(1);
                }
            }
            if ($result->code){
                //需要对应的uid
                Gateway::sendToUid('uid',$data);
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
}
