<?php

namespace Scit\General;

class Response{
    private $managers;

    public function __construct(&$managers){
        $this->mangers = &$managers;
    }

    private function isHeadersSent(){
        return \headers_sent();
    }

    public function write($text){
        echo $text;
    }

    public function addHeader($key,$value){
        if(!$this->isHeadersSent()){
            \header("$key: $value",true);
        }
        return $this;
    }

    public function status($code){
        if(!$this->isHeadersSent()){
            \http_response_code($code);
        }
        return $this;
    }

    public function redirect($page,$status = 302){
        $this->status($status)->addHeader('Location',$page);
        return $this;
    }

    public function sendJson(array $response){
        $this->addHeader('Content-Type','application/json')->status(200);
        echo \json_encode($response);
    }
}