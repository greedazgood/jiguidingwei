<?php
return [
    //对外端口号8888 ip默认为本机ip
    'url' => 'http://112.64.125.182:8083/cdcs/trigger/flow',
    'interval' => 30,//向后台发送固定发送数据时间间隔 单位s，数据变动时会立即发送数据，不受此配置影响
];
