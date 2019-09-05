<?php

namespace Scit\General;

class Session{
 
    protected $name,$cookie,$location,$use,$pointer = '';

    public function __construct(&$managers){
        $this->location = $managers['documentRoot'].'/server/sessions/';
        $this->name = $managers['adminSettings']['sessionName'] ?? 'church';
        $customCookieOption = $managers['adminSettings']['sessionCookie'] ?? [];
        $this->cookie = array_merge(
            [
                'lifetime' => 0,
                'path'=>ini_get('session.cookie_path'),
                'domain'=>ini_get('session.cookie_domain'),
                'secure'=>false,
                'httponly' => true
            ],
            $customCookieOption
        );
        @register_shutdown_function([$this,'touch']);
        $this->setup();  
    }

    public function use(string $use = '',$parent = true){
        $createSessData = function(&$arr){
            $arr['__data'] = [
                'last_activity' => microtime(true),
                'expires' => 3600,
                'groupUpdate' => true,
                'groupToUpdate' => '*',
                'groupIsolate' => false
            ];
            unset($arr);
        };

        if(strlen($use)){
            $loc = explode('-->',$use);
            $arr = &$_SESSION;
            for($i = 0,$j = count($loc); $i < $j; $i++){
                if(!isset($arr[trim($loc[$i])])){
                    $arr[trim($loc[$i])] = [
                        '__data' => [
                            'last_activity' => microtime(true),
                            'expires' => 3600,
                            'groupUpdate' => true,
                            'groupToUpdate' => '*',
                            'groupIsolate' => false
                        ]
                    ];    
                }
                $arr = &$arr[trim($loc[$i])];   
            }
            $this->use = &$arr;  
        }else{
            $this->use = &$_SESSION;  
        }

        if(!isset($_SESSION['__data'])){
            $createSessData($_SESSION);
        }

        if(!isset($this->use['__data'])){
            $createSessData($this->use);
        }

        $this->pointer = $use;
        return $this;
    }

    private function setup(){
        if(session_id() == '' || session_status() == PHP_SESSION_NONE){
            ini_set('session.use_cookies', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.serialize_handler', 'php_serialize');
            ini_set('session.gc_probability', 5);
            ini_set('session.gc_divisor', 50);
            ini_set('session.gc_maxlifetime', 10800);
            session_name($this->name);
            session_set_cookie_params(
                $this->cookie['lifetime'],
                $this->cookie['path'],
                $this->cookie['domain'],
                $this->cookie['secure'],
                $this->cookie['httponly']
            );  
        }
    }

    public function start(){
        if(session_id() == '' || session_status() == PHP_SESSION_NONE){
            session_set_save_handler(
                [$this,'open'],
                [$this,'close'],
                [$this,'read'],
                [$this,'write'],
                [$this,'destroy'],
                [$this,'gc']
            );
            @register_shutdown_function('session_write_close');
            session_start();
            if(mt_rand(0,100) < 5){
                $this->refresh();    
            }   
        }
        return $this->use('',true)->use();
    }

    public function forget(){
        if(is_string($this->pointer) && $this->pointer !== ''){
            $pointers = explode('-->',$this->pointer);
            $section = array_pop($pointers);
            return $this->use(implode('-->',$pointers),true)->remove($section);
        }
        $_SESSION = [];
        return $this->use();
    }
    
    public function parent(){
        $pointers = explode('-->',$this->pointer);
        $section = array_pop($pointers);
        $this->use(implode('-->',$pointers),true);
        return $this;
    }

    public function refresh(){
        if (session_status() == PHP_SESSION_NONE){
            $this->start();   
        }
        $_SESSION['__OBSOLETE'] = true;
        $_SESSION['__EXPIRES'] = microtime(true) + 60;
        session_regenerate_id(false);
        $newSession = session_id();
        $_SESSION['__SESSION_ID'] = $newSession;
        return $this->set_new_session_id();  
    }

    private function set_new_session_id(){
        if(isset($_SESSION['__SESSION_ID']) && isset($_SESSION['__OBSOLETE']) && isset($_SESSION['__EXPIRES'])){
            $newSession = $_SESSION['__SESSION_ID'];
            session_write_close();
            session_id($newSession);
            session_start();
            unset($_SESSION['__OBSOLETE']);
            unset($_SESSION['__EXPIRES']);
            unset($_SESSION['__SESSION_ID']);
        }
        return $this;
    }

    public function crypt_engine($string, $action = 'e',$secret_key = 'session'){
        $secret_iv = 'Encode_Decode';
        $output = false;
        $encrypt_method = "AES-256-CBC";
        $key = hash('sha512', $secret_key);
        $iv = substr(hash('sha512', $secret_iv), 0,openssl_cipher_iv_length($encrypt_method));
        if($action == 'e'){
            $output = base64_encode(openssl_encrypt($string, $encrypt_method, $key, 0, $iv));   
        }else if($action == 'd'){
            $output = openssl_decrypt(base64_decode($string ),$encrypt_method,$key,0,$iv);   
        }
        return $output;  
    }//session crypt engine

    public function open($path,$id){
        if(!is_dir($this->location)){
            mkdir($this->location);   
        }
        return true;  
    }

    public function read($id,$prepend = true){
        $file = $this->location."sess_{$id}";
        if(is_file($file)){
            $data = file_get_contents($file);
        return $this->crypt_engine($data,'d');   
        }
        return serialize(array()); 
    }//session read function

    public function write($id, $data){
        return file_put_contents($this->location."sess_{$id}",$this->crypt_engine($data,'e')) !== false;
    }//session write function

    public function close(){
        return true;
    }

    public function destroy(){
        if(!session_id()){
            $this->start();
        }

        $_SESSION = [];
        $this->use = &$_SESSION;
        $this->pointer = '';
        $id = session_id();
        setcookie(session_name(),'',(time() - 4200),$this->cookie['path'],$this->cookie['domain'],$this->cookie['secure'],$this->cookie['httponly']);
        $file = $this->location."sess_{$id}";
        \clearstatcache(true);
        if(file_exists($file)){
            unlink($file);
        }
        return $this;
    }

    public function gc($max){
        if(is_dir($this->location)){
            if($dh = opendir($this->location)){
                while(($file = readdir($dh)) !== false){
                    $file_data = $this->location.$file;
                    if(is_file($file_data)){
                        $loc = stripos($file,'sess_');
                        if($loc === 0 AND $loc !== false){
                            if((microtime(true) - filemtime($file_data)) > $max){
                                unlink($file_data);        
                            }       
                        }
                    }     
                }
                closedir($dh);    
            }   
        }
        return true;  
    }

    public function touch($external = true){
        if(!session_id()){
            $this->start();
        }

        $update = \Closure::bind(function() use(&$external){
            $process = \Closure::bind(function (&$arr) use (&$process){
                if(isset($arr['__data']) && is_array($arr['__data'])){
                    if(isset($arr['__data']['groupIsolate']) && (!$arr['__data']['groupIsolate'])){
                        $arr['__data']['last_activity'] = microtime(true);
                    }
                    if(isset($arr['__data']['groupUpdate']) && ($arr['__data']['groupUpdate'])){
                        if(isset($arr['__data']['groupToUpdate'])){

                            $handleAllUpdates = \Closure::bind(function() use(&$process,&$arr){
                                foreach($arr as $key => &$value){
                                    if($key !== '__data'){
                                        if(is_array($value)){
                                            $process($value);
                                        }
                                    }
                                }
                            },$this);

                            $handleSingleUpdate = \Closure::bind(function(string $groupToUpdate) use(&$process){
                                $process($this->use($groupToUpdate,true)->use);
                            },$this);

                            if(is_string($arr['__data']['groupToUpdate'])){
                                if($arr['__data']['groupToUpdate'] === '*'){
                                    $handleAllUpdates();
                                }else{
                                    $handleSingleUpdate($arr['__data']['groupToUpdate']);
                                }
                            }elseif(is_array($arr['__data']['groupToUpdate'])){
                                if(isset(\array_flip($arr['__data']['groupToUpdate'])['*'])){
                                    $handleAllUpdates();
                                }else{
                                    foreach($arr['__data']['groupToUpdate'] as $groupToUpdate){
                                        if(is_string($groupToUpdate)){
                                            $handleSingleUpdate($groupToUpdate);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                unset($arr);
            },$this);

            $current = $this->pointer;
            $haystack;
            if($external){
                $haystack = &$_SESSION;
            }else{
                $haystack = &$this->use;
            }

            $process($haystack);
            unset($haystack);
            $this->use($current,true);

            return $this;
        },$this);
        
        return $update();
    }
    
    private function isExpired(){  
        $last = $this->internalGet('__data-->last_activity');
        $ttl = $this->internalGet('__data-->expires');
        if($last && $ttl && ((microtime(true) - $last) < ($ttl))){
            $this->touch(false);
            return false;
        }else{
            $locationPointer = $this->pointer;
            $previousSettings = $this->internalGet('__data');
            $this->forget();
            $this->use($locationPointer);
            if(is_array($previousSettings)){
                $this->setConfig($previousSettings);
            }
            return true;
        }
    }

    private function isValid(){
        if(isset($_SESSION['__OBSOLETE'])){
            if((isset($_SESSION['__EXPIRES'])) && ($_SESSION['__EXPIRES'] < microtime(true))){
                $_SESSION = [];
                return false;    
            }
            if(isset($_SESSION['__SESSION_ID'])){
                $this->set_new_session_id();
            }
        }
        return !$this->isExpired(); 
    }
    
    public function setConfig($config = []){
        if(isset($this->use['__data']) && is_array($this->use['__data'])){
            $this->use['__data'] = array_merge($this->use['__data'],$config);
        }else{
            $this->internalSet('__data',$config);
        }
        return $this;
    }

    private function internalGet($name){
        $getData = \Closure::bind(function($name){
            $pointer = &$this->use;
            $parts = explode('-->',$name);
            for($i = 0,$j = count($parts);$i < $j;$i++){
                $part = $parts[$i];
                if(($i + 1) !== $j){
                    if(!(isset($pointer[$part]) && is_array($pointer[$part]))){
                        break;
                    }else{
                        $pointer = &$pointer[$part];
                        continue;
                    }
                }else{
                    return $pointer[$part] ?? false;
                }
            }
            return false;
        },$this);

        $dataItem = $getData($name);

        $process = function($item) use (&$process){
            if(is_array($item)){
                if(isset($item['__data'])){
                    unset($item['__data']);
                }
                foreach($item as $key => $value){
                    if(is_array($value)){
                        $item[$key] = $process($value);
                    }    
                }
            }
            return $item;
        };
        
        return $process($dataItem);
    }

    public function get($name){
        if($name === '__data'){
            return false;
        }
        if($this->isValid()){
            return $this->internalGet($name);
        }
    }
    
    public function internalSet(string $name,$value){
        $processAdd = \Closure::bind(function (string $key,$value){
            $pointer = &$this->use;
            $parts = explode('-->',$key);

            for($i = 0,$j = count($parts);$i < $j;$i++){
                $part = $parts[$i];
                if(($i + 1) === $j){
                    if((strlen($part) > 2) && (substr($part,-2) === '[]')){
                        $part = substr($part,0,-2);
                        if(isset($pointer[$part]) && is_array($pointer[$part])){
                            $pointer[$part][] = $value;
                        }else{
                            $pointer[$part] = [$value];
                        }
                    }else{
                        $pointer[$part] = $value;
                    }
                }else{
                    if(!(isset($pointer[$part]) && is_array($pointer[$part]))){
                        $pointer[$part] = [];
                    }
                    $pointer = &$pointer[$part];
                }
            }
            return $this;
        },$this);
        return $processAdd($name,$value);
    }

    public function set($name, $value = null){
        if($this->isValid()){
            if(is_array($name)){
                foreach($name as $k => $v){
                    if($k !== '__data'){
                        $this->internalSet($k,$v);   
                    }  
                }    
            }elseif(is_string($name) && ($name !== '__data')){
                $this->internalSet($name,$value);   
            }   
        }
        return $this;  
    }

    public function remove($name){
        $pointer = &$this->use;
        $parts = explode('-->',$name);
        
        for($i = 0,$j = count($parts);$i < $j;$i++){
            $part = $parts[$i];

            if(($i + 1) === $j){
                if(isset($pointer[$part])){
                    $pointer[$part] = null;
                }
                break;
            }else{
                if(isset($pointer[$part]) && is_array($pointer[$part])){
                    $pointer = &$pointer[$part];
                }else{
                    break;
                }
            }
        }
        return $this;   
    }
    
    public function expires(int $value){
        return $this->setConfig(['expires' => $value]);
    }
    
    public function isReceiver(bool $value){
        return $this->setConfig(['groupIsolate' => !$value]);
    }
    
    public function isEmitter(bool $value){
        return $this->setConfig(['groupUpdate' => $value]);
    }
    
    public function emitTo(string $value){
        return $this->setConfig(['groupToUpdate' => $value]);
    }

}//class session