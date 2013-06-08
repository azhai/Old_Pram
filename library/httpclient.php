<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */

require dirname(__FILE__) . '/Requests/library/Requests.php';
Requests::register_autoloader();


/**
 * HTTP客户端，用于发送REST请求
 */
class HTTPClient
{
    public $uri_prefix = '';
    public $options = array();
    public $response = null;
    
    public function __construct($uri_prefix='')
    {
        $this->uri_prefix = rtrim($uri_prefix, '/');
    }
    
    //设置Basic Auth
    public function setBasicAuth($username, $password)
    {
        $this->options['auth'] = new Requests_Auth_Basic(array($username, $password));
        return $this;
    }
    
    //GET、DELETE、HEAD请求
    protected function _sendGetRequest($method, $url, $data=array(), $headers=array())
    {
        $url = $this->uri_prefix . '/' . ltrim(rtrim($url, '?&'), '/'); //用/连结起来
        $get_data = is_array($data) ? http_build_query($data) : ltrim($data, '?&');
        $url .= (strpos($url, '?') === false ? '?' : '&') . $get_data; //用?或&连结起来
        if ($method === 'DELETE') {
            $this->response = Requests::delete($url, $headers, $this->options);
        }
        else if ($method === 'HEAD') {
            $this->response = Requests::head($url, $headers, $this->options);
        }
        else {
            $this->response = Requests::get($url, $headers, $this->options);
        }
        return $this->response;
    }
    
    //POST、PUT、PATCH请求
    protected function _sendPostRequest($method, $url, $data=array(), $headers=array())
    {
        $url = $this->uri_prefix . '/' . ltrim($url, '/');
        if ($method === 'PUT') {
            $this->response = Requests::put($url, $headers, $data, $this->options);
        }
        else if ($method === 'PATCH') {
            $this->response = Requests::patch($url, $headers, $data, $this->options);
        }
        else {
            $this->response = Requests::post($url, $headers, $data, $this->options);
        }
        return $this->response;
    }
    
    //发送Request
    public function __call($method, $params)
    {
        //规范化数据
        $method = strtoupper($method);
        @list($url, $data, $headers) = $params;
        $data = empty($data) ? array() : $data;
        $headers = empty($headers) ? array() : $headers;
        //发送请求
        switch ($method) {
            case 'GET':
            //case 'DELETE':
            //case 'HEAD':
                $this->_sendGetRequest($method, $url, $data, $headers);
                break;
            case 'POST':
            //case 'PUT':
            //case 'PATCH':
                $this->_sendPostRequest($method, $url, $data, $headers);
                break;
            default:
                $data['_METHOD'] = $method;
                $this->_sendPostRequest('POST', $url, $data, $headers);
        }
        return $this;
    }
    
    public function postRaw($url, $data, $headers=array())
    {
        $data = is_string($data) ? $data : json_encode($data);
        $headers = empty($headers) ? array('Content-Type'=>'application/json') : array();
        return $this->post($url, $data, $headers);
    }
    
    public function isSuccess()
    {
        return $this->response && $this->response->success;
    }

    //处理JSON格式的Response
    public function json()
    {
        if ($this->isSuccess()) {
            $body = json_decode($this->response->body, true);
            if (is_array($body)) {
                $result = new StdClass($body);
                foreach ($body as $key => $value) {
                    $result->$key = $value;
                }
                return $result;
            }
            else {
                return $body;
            }
        }
        else {
            $this->response->throw_for_status();
            return;
        }
    }
}
?>