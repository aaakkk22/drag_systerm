<?php

namespace app\api\controller;

use app\api\logic\UsersLogic;
use app\api\controller\Base;
use app\common\model\User;
use think\Db;
use think\Redis;

class Login extends Base
{
    public $userLogic;

    /**
     * 析构流函数.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function _initialize()
    {
        parent::_initialize();
    }

    public function login()
    {

        $code = input('code');
        if(!$code){
            ajaxReturn(array('status' => -1, 'msg' => 'code不能为空'));
            exit;
        }
        $config = Db::name('config')->where(['id' => 1])->find();
        if(!$config){
            ajaxReturn(array('status' => -1, 'msg' => 'wepro_appid、wepro_appsecret不能为空'));
        }

        $appid = $config['wepro_appid'];
        if(!$appid){
            ajaxReturn(array('status' => -1, 'msg' => 'appid不存在'));
        }


        $appsecret = $config['wepro_appsecret'];
        if(!$appsecret){
            ajaxReturn(array('status' => -1, 'msg' => 'appsecret不存在'));
        }

        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=' . $appid . '&secret=' . $appsecret . '&js_code=' . $code .  '&grant_type=authorization_code';
    
        $josn = $this->httpRequest($url, 'GET'); 
    
        $result = json_decode($josn, true);
        
        if(!$result){
            ajaxReturn(array('status' => -1, 'msg' => '获取openid失败' ,'url' => $url ,'json' => $josn));
            exit;
        }

        if(!isset($result['openid'])){
            ajaxReturn(array('status' => -1, 'msg' => '获取openid失败,没有openid'));
            exit;
        }

        $openid = $result['openid'];
      
        $userinfo = User::where(array('openid' => $openid))->find();

        if ($userinfo) {
            //生成token，登录时间
            $userinfo['token'] = $this->create_user_token($userinfo['user_id']);

            $userinfo['on_status'] = Db::name('config')->where(['id' => 1])->value('is_show');
            
            ajaxReturn(array('status' => 1, 'msg' => '登录成功', 'data' => $userinfo));

        } else {

            $userinfo = array(
                'openid' => $openid,
                'nickname' => '请点击授权',
                'avatar' => 'https://www.c3w.com.cn/public/images/avatar.png',
                'last_login' => time(),
                'add_time' => time()
            );
          
            User::insert($userinfo);

            $userinfo = User::where(array('openid' => $openid))->find();
           
            $userinfo['token'] = $this->create_user_token($userinfo['user_id']);

            $userinfo['on_status'] = Db::name('config')->where(['id' => 1])->value('is_show');

            ajaxReturn(array('status' => 1, 'msg' => '登录成功', 'data' => $userinfo));
        }
    }
    /**
     * CURL请求
     * @param $url 请求url地址
     * @param $method 请求方法 get post
     * @param null $postfields post数据数组
     * @param array $headers 请求header信息
     * @param bool|false $debug 调试开启 默认false
     * @return mixed
     */
    function httpRequest($url, $method = "GET", $postfields = null, $headers = array(), $debug = false)
    {
        $method = strtoupper($method);
        $ci = curl_init();
        /* Curl settings */
        curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ci, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:34.0) Gecko/20100101 Firefox/34.0");
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 60); /* 在发起连接前等待的时间，如果设置为0，则无限等待 */
        curl_setopt($ci, CURLOPT_TIMEOUT, 7); /* 设置cURL允许执行的最长秒数 */
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
        switch ($method) {
            case "POST":
                curl_setopt($ci, CURLOPT_POST, true);
                if (!empty($postfields)) {
                    $tmpdatastr = is_array($postfields) ? http_build_query($postfields) : $postfields;
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $tmpdatastr);
                }
                break;
            default:
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, $method); /* //设置请求方式 */
                break;
        }
        $ssl = preg_match('/^https:\/\//i', $url) ? TRUE : FALSE;
        curl_setopt($ci, CURLOPT_URL, $url);
        if ($ssl) {
            curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE); // 不从证书中检查SSL加密算法是否存在
        }
        //curl_setopt($ci, CURLOPT_HEADER, true); /*启用时会将头文件的信息作为数据流输出*/
        //curl_setopt($ci, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ci, CURLOPT_MAXREDIRS, 2);/*指定最多的HTTP重定向的数量，这个选项是和CURLOPT_FOLLOWLOCATION一起使用的*/
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ci, CURLINFO_HEADER_OUT, true);
        /*curl_setopt($ci, CURLOPT_COOKIE, $Cookiestr); * *COOKIE带过去** */
        $response = curl_exec($ci);
        $requestinfo = curl_getinfo($ci);
        $http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
        if ($debug) {
            echo "=====post data======\r\n";
            var_dump($postfields);
            echo "=====info===== \r\n";
            print_r($requestinfo);
            echo "=====response=====\r\n";
            print_r($response);
        }
        curl_close($ci);
        return $response;
        //return array($http_code, $response,$requestinfo);
    }

}
