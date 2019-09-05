<?php

namespace Church\Admin;

class Statistics{
    private $managers,$__data = [];

    public function __construct(&$managers){
        $this->managers = &$managers;
    }

    protected function set(array $data){
        $this->__data = $data;
        return $this;
    }

    protected function get($key = false){
        if($key === false){
            return $this->__data;
        }
        return $this->__data[$key] ?? false;
    }

    protected function reset(){
        $this->__data = [];
        return $this;
    }

    private function getUtils(){
        return $this->managers['general']['utils'];
    }

    private function getSession(){
        return $this->getUtils()->init('General-Session')->use('');
    }

    private function getDatabase(){
        return $this->getUtils()->init('general-database');
    }
}