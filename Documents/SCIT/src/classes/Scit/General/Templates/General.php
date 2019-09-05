<?php

namespace Scit\General\Templates;

class General{
    protected $managers,$__data = [];

    public function __construct(&$managers){
        $this->managers = &$managers;
    }

    protected function set(string $pointer,$value){
        $this->getUtils()::setDataInArray($this->__data,$pointer,$value);
        return $this;
    }

    public function get(string $pointer,$removeAfter = false){
        $out = false;
        if($pointer === ''){
            $out = $this->__data;
            if($removeAfter){
                $this->__data = [];
            }
        }else{
            $out = $this->getUtils()::getDataFromArray($this->__data,$pointer);
            if($removeAfter){
                $this->getUtils()::setDataInArray($this->__data,$pointer,null);
            }
        }
        return $out;
    }

    protected function reset(){
        $this->__data = [];
        return $this;
    }

    protected function getUtils(){
        return $this->managers['General']['Utils'];
    }

    protected function getSession(){
        return $this->getUtils()->init('General-Session')->use('');
    }

    protected function getDatabase(){
        return $this->getUtils()->init('General-Database');
    }

    protected function getRequest(){
        return $this->getUtils()->init('General-Request');
    }
}