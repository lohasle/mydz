<?php
/**
 * 测试 php restful 调用
 */
require_once("restclient.php");
define("BASE_URL","http://192.168.199.130:8090/server/");
header('Content-type: application/json');

function sendGet($url,$params = array()){
    $api = new RestClient(array(
        'base_url' => constant('BASE_URL'),
        'format' => "json",
        'parameters'=>$params
//    'headers' => array('Content-Type' => 'application/json'),
    ));
    $result = $api->get($url);
    if($result->info->http_code == 200){
        return json_encode($result->decode_response(),true);
    }
}

function sendPost($url,$params = array()){
    $api = new RestClient(array(
        'base_url' => constant('BASE_URL'),
        'format' => "json",
        'parameters'=>$params
//    'headers' => array('Content-Type' => 'application/json'),
    ));
    $result = $api->post($url);
    if($result->info->http_code == 200){
        return json_encode($result->decode_response(),true);
    }
}

$str = $_GET['test'];

if($str=='showdoctor'){
    echo sendGet('doctor/show/1');
}else if($str=='register'){
    echo sendPost('user/register',array("phone"=>11223344,"password"=>123456));
}


