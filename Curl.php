<?php
namespace bookin\curl;
use yii\base\Object;
use \Yii;
use yii\helpers\ArrayHelper;

/**
 * Class Sently
 * @property mixed $curlInfo Getting curl_getinfo() for last request
 * @property string $curlError Getting curl_error() for last request
 * @property array $headers Headers for last request
 * @property string $body Body for last request
 */
class Curl extends Object{

    public $runtimePath = 'application.runtime.curl';
    public $cookieFile = 'curl_cookie';

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';
    const METHOD_HEAD = 'HEAD';

    private $_curlInfo, $_curlError, $_headers = [], $_body;
    protected $_errors=[];

    /**
     * @param $url
     * @param array $body
     * @param array $headers
     * @param array $curlOptions
     * @return $this
     */
    public function get($url, $body=[], $headers=[], $curlOptions=[]){
        $this->curlRequest($url, $body, self::METHOD_GET, $headers, $curlOptions);
        return $this;
    }

    /**
     * @param $url
     * @param array $body
     * @param array $headers
     * @param array $curlOptions
     * @return $this
     */
    public function head($url, $body=[], $headers=[], $curlOptions=[]){
        $this->curlRequest($url, $body, self::METHOD_HEAD, $headers, $curlOptions);
        return $this;
    }

    /**
     * @param $url
     * @param array $body
     * @param array $headers
     * @param array $curlOptions
     * @return $this
     */
    public function post($url, $body=[], $headers=[], $curlOptions=[]){
        $this->curlRequest($url, $body, self::METHOD_POST, $headers, $curlOptions);
        return $this;
    }

    /**
     * @param $url
     * @param array $body
     * @param array $headers
     * @param array $curlOptions
     * @return $this
     */
    public function put($url, $body=[], $headers=[], $curlOptions=[]){
        $this->curlRequest($url, $body, self::METHOD_PUT, $headers, $curlOptions);
        return $this;
    }

    /**
     * @param $url
     * @param array $body
     * @param array $headers
     * @param array $curlOptions
     * @return $this
     */
    public function delete($url, $body=[], $headers=[], $curlOptions=[]){
        $this->curlRequest($url, $body, self::METHOD_DELETE, $headers, $curlOptions);
        return $this;
    }

    /**
     * @param $url
     * @param array $body
     * @param string $method
     * @param array $headers
     * @param array $curl_options
     * @return $this
     */
    protected function curlRequest($url, $body=[], $method=self::METHOD_GET, $headers=[], $curl_options=[]){
        if($body&&is_array($body)){
            array_walk($body, function(&$val, $key){
                if($val === null)
                    $val = '';
            });
        }

        $curlParams = [
            CURLOPT_URL => $url,
            CURLOPT_REFERER => $url,
            CURLOPT_VERBOSE => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0",
            CURLOPT_HEADER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->getCookiesFilePath(),
            CURLOPT_COOKIEFILE => $this->getCookiesFilePath(),
            CURLOPT_COOKIESESSION => true
        ];

        $url_info = parse_url($url);
        $headers_data = [];
        $headers_options = ArrayHelper::merge([
            "Host"=>$url_info['host'],
            "Accept"=>"text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language"=>"ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3",
            "Connection"=>"keep-alive"
        ],$headers);
        foreach($headers_options as $key=>$val){
            $headers_data[]=$key.': '.$val;
        }

        switch(strtoupper($method)){
            case 'POST':
                $curlParams[CURLOPT_POST]=true;
                if($body)
                    $curlParams[CURLOPT_POSTFIELDS]=(is_array($body)?http_build_query($body):$body);
                break;
            case 'PUT':
                $curlParams[CURLOPT_CUSTOMREQUEST]="PUT";
                if($body)
                    $curlParams[CURLOPT_POSTFIELDS]=(is_array($body)?http_build_query($body):$body);
                break;
            case 'DELETE':
                $curlParams[CURLOPT_CUSTOMREQUEST]="DELETE";
                if($body)
                    $curlParams[CURLOPT_URL]=$url.'?'.(is_array($body)?http_build_query($body):$body);
                break;
            default:
                $curlParams[CURLOPT_HTTPGET]=true;
                if($body)
                    $curlParams[CURLOPT_URL]=$url.'?'.(is_array($body)?http_build_query($body):$body);
                break;
        }
        $curlParams[CURLOPT_HTTPHEADER] = $headers_data;

        if(strtolower((substr($url,0,5))=='https')) {
            $curlParams[CURLOPT_SSL_VERIFYPEER]=false;
            $curlParams[CURLOPT_SSL_VERIFYHOST]=false;
        }

        $ch = curl_init();
        if($curl_options)
            $curlParams = ArrayHelper::merge($curlParams, $curl_options);
        curl_setopt_array($ch, $curlParams);
        $result=curl_exec($ch);

        $this->curlInfo = curl_getinfo($ch);
        $this->curlError = curl_error($ch);
        if($this->curlError){
            $this->setErrors([
                'code'=>curl_errno($ch),
                'line'=>__LINE__,
                'message'=>$this->curlError
            ]);
        }

        $this->headers = explode("\r\n\r\n", trim(substr($result, 0, $this->curlInfo['header_size'])));
        $body = substr($result, $this->curlInfo['header_size']);
        $this->body = $body;

        curl_close($ch);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCurlInfo()
    {
        return $this->_curlInfo;
    }

    /**
     * @param mixed $curlInfo
     */
    protected function setCurlInfo($curlInfo)
    {
        $this->_curlInfo = $curlInfo;
    }


    /**
     * @return mixed
     */
    public function getCurlError()
    {
        return $this->_curlError;
    }

    /**
     * @param mixed $curlError
     */
    protected function setCurlError($curlError)
    {
        $this->_curlError = $curlError;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * @param array $headers
     */
    protected function setHeaders($headers)
    {
        $this->_headers = $headers;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->_body;
    }

    /**
     * @param mixed $body
     */
    protected function setBody($body)
    {
        $this->_body = $body;
    }

    /**
     * @return string
     */
    protected function getCookiesFilePath(){
        $folder = $this->getRuntimePath();
        $filePath = $folder.DIRECTORY_SEPARATOR.$this->cookieFile;
        if(!is_file($filePath)){
            $f = fopen($filePath, 'w');
            fclose($f);
        }
        return $filePath;
    }

    /**
     * @return bool|string
     */
    protected function getRuntimePath(){
        $folder = Yii::getAlias($this->runtimePath);
        if(!is_dir($folder)){
            @mkdir($folder, 0755);
        }
        return $folder;
    }

    /**
     * @return mixed
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * @param mixed $errors
     */
    protected function setErrors($errors)
    {
        $this->_errors[] = $errors;
    }
}