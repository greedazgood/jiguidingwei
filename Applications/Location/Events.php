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
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        // 向当前client_id发送数据
        Gateway::sendToClient($client_id, "Hello $client_id\r\n");
        // 向所有人发送
        Gateway::sendToAll("$client_id login\r\n");
    }

   /**
    * 当客户端发来消息时触发
    * @param int $client_id 连接id
    * @param mixed $message 具体消息
    */
   public static function onMessage($client_id, $data)
   {
       //16进制数据
       $hex_data = bin2hex($data);
       $all_info = substr($hex_data,0,68);
       var_dump($all_info);
       $position_info = substr($hex_data,24,36);
       $uid = substr($hex_data,14,8);
       echo "uid:".hexdec($uid).PHP_EOL;
        for ($i=0 ;$i<6;$i++){
             $position_hex = substr($position_info,$i*6,6);
             $front_data = substr($position_hex,0,4);
             $end_data = substr($position_hex,4,2);
             $front_bin = hex2bin($front_data);
             $end_bin = hex2bin($end_data);
             $triger_id = bindec(substr($front_bin,0,13));
             $wire_id = bindec(substr($front_bin,13,3));
             echo "天线编号:".$wire_id.PHP_EOL;
             if ($triger_id <8){
                 echo "测试数据".PHP_EOL;
             }elseif ($triger_id >=8 && $triger_id<=8159){
                 echo "触发器id:".$triger_id.PHP_EOL;
             }elseif ($triger_id == 8160){
                 echo "标签在触发区域外".PHP_EOL;
             }else{
                 echo "特殊控制字符".PHP_EOL;
             }
             $xyz = bindec(substr($end_bin,0,2));
             $status = bindec(substr($end_bin,2,1));
             $rss = bindec(substr($end_bin,3,5));
             echo "rss".PHP_EOL;
             if ($xyz ==0){
                 echo "xyz:保留".PHP_EOL;
             }elseif ($xyz == 1){
                 echo "xyz:x".PHP_EOL;
             }elseif ($xyz == 2){
                 echo "xyz:y".PHP_EOL;
             }else{
                 echo "xyz:z".PHP_EOL;
             }
             if ($status==0){
                 echo "正常当前触发器触发".PHP_EOL;
             }elseif ($status ==1){
                 echo "上次最后离场的触发器触发".PHP_EOL;
             }
             //echo "Location".($i+1).":".$data.PHP_EOL;
        }
        $time = substr($hex_data,62,6);
        echo "time:".$time.PHP_EOL;
        // 向所有人发送
        //Gateway::sendToAll("$client_id said $message\r\n");
   }

   /**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
   public static function onClose($client_id)
   {
       // 向所有人发送
       GateWay::sendToAll("$client_id logout\r\n");
   }
}
