<?php

namespace Scit\General;

class Request{
    private $requestUrl,$location,$current = false,$path,$managers,$haystack = [];
    
    public function __construct(&$managers){
        $this->managers = &$managers;
        $path = ltrim($_SERVER['REQUEST_URI'],' /');
        $this->requestUrl = $path;
        $urlProcessor = $this->getUtils()->init('General-UrlProcessor',[
            'url' => $path
        ]);
        $this->location = ltrim($urlProcessor->getPath(),' /');

        if($this->isGet()){
            $this->haystack = &$_GET;
        }else{
            $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? false;
            if(is_string($contentType) && (strtolower($contentType) == 'application/json')){
                $this->haystack = \json_decode(\file_get_contents('php://input'),true);
            }else{
                $this->haystack = &$_POST;
            }
        }
    }

    public function for($path){
        $this->path = $path;
        return $this;
    }

    public function only(){
        $path = $this->getPath();
        $location = $this->location;
        return (bool)($path === $location || $path.'/' === $location);
    }

    private function resetPath(){
        $this->path = false;
        return $this;
    }

    private function getPath(){
        return (is_string($this->current) ? ($this->path ? $this->current.'/'.$this->path : $this->current) : $this->path);
    }

    public function all(){
        $path = $this->getPath();
        $count = strlen($path);
        return (substr($this->location,0,$count) === $path);
    }

    public function processChild(){
        $this->current = (is_string($this->current) ? $this->current.'/'.$this->path : $this->path);
        $this->resetPath();
        return $this;
    }

    public function getMethod(){
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    public function getRequestUrl(){
        return $this->requestUrl;
    }

    private function getUtils(){
        return $this->managers['General']['Utils'];
    }

    public function getFullRequestUrl(){
        return $this->managers['adminSettings']['webUrl'].'/'.$this->getRequestUrl();
    }

    public function isGet(){
        return ($this->getMethod() === 'get');
    }

    public function isPost(){
        return ($this->getMethod() === 'post');
    }

    public function getQueryDataFor($name = false){
        if($name){
            $data = $this->getUtils()::getDataFromArray($_GET,$name);
            return (!is_null($data) ? $data : false);
        }
        return $_GET;
    }

    public function addQueryDataFor(string $key,$value){
        $this->getUtils()::setDataInArray($_GET,$key,$value);
        return true;
    }

    public function getDataForPath(int $location){
        $urlProcessor = $this->getUtils()->init('General-UrlProcessor',[
            '__settings' => [
                'retain' => false
            ],
            'url' => $this->getFullRequestUrl()
        ]);
        $parts = explode('/',$urlProcessor->getPath(false));
        $result = $this->getUtils()->init('General-Validator')->filter([
            'part' => ($parts[$location] ?? ''),
        ],[
            'part' => 'sanitize_string'
        ]);

        return $result['part'];
    }

    public function get($name = false){
        $haystack = &$this->haystack;
        if($name){
            $data = $this->getUtils()::getDataFromArray($haystack,$name);
            return (!is_null($data) ? $data : false);
        }
        return $haystack;
    }

    public function addDataFor(string $key,$value){
        $this->getUtils()::setDataInArray($haystack,$key,$value);
        return true;
    }

    public function hasFileUpload(){
        return !empty($_FILES);
    }

    public function getUploadedFile($name = false){
        if($name){
            $data = $this->getUtils()::getDataFromArray($_FILES,$name);
            return (!is_null($data) ? $data : false);
        }
        return $_FILES;
    }

    public function hasHeader($header){
        return !is_null($this->getUtils()::getDataFromArray($_SERVER,$header));
    }

    public function getHeader($header){
        return ($this->getUtils()::getDataFromArray($_SERVER,$header) ?: false);
    }
}