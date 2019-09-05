<?php

namespace Scit\General;

class Utils{

    private $managers,$handlers = [];
    
    public static function getResponseFor($reason){
        $out = [
            'status' => 'ok',
            'from' => 'general'
        ];
        
        switch(true){
            case (isset(array_flip([
                'no-auth'
            ])[$reason])):
                self::setDataInArray($out,'status','error');
                self::setDataInArray($out,'type','authentication');
                self::setDataInArray($out,'response','Ooops you must be logged to continue');
            break;

            case (isset(array_flip([
                'invalid-request'
            ])[$reason])):
                self::setDataInArray($out,'status','error');
                self::setDataInArray($out,'from','general');
                self::setDataInArray($out,'response','Invalid Request');
            break;

            case (isset(array_flip([
                'invalid-db-response',
                'invalid-response'
            ])[$reason])):
                self::setDataInArray($out,'status','error');
                self::setDataInArray($out,'from','general');
                self::setDataInArray($out,'response','Ongoing maintenence please try agian later');
            break;
                
            case (isset(array_flip([
                'parameters-error',
                'invalid-parameters'
            ])[$reason])):
                self::setDataInArray($out,'status','error');
                self::setDataInArray($out,'from','general');
                self::setDataInArray($out,'response','Invalid parameters');
            break;

            case (isset(array_flip([
                'malformed-db-response'
            ])[$reason])):
                self::setDataInArray($out,'status','error');
                self::setDataInArray($out,'from','general');
                self::setDataInArray($out,'response','Malformed data encountered... Repair in progress');
            break;
        }
        
        return $out;
    }
    
    public static function arrayChangeValueCase($array, $case = CASE_LOWER){
        if(!is_array($array)){
            return false;
        }
        foreach($array as $key => &$value){
            if(is_array($value)){
                call_user_func_array(__function__,array(&$value,$case));
            }else{
                $array[$key] = (($case == CASE_UPPER) ? strtoupper($array[$key]) : strtolower($array[$key]));
            }
        }
        return $array;
    }

    public static function random($length){
        $char = '';
        while(strlen($char) < (int)$length){
            $arr = [mt_rand(48,57),mt_rand(65,90),mt_rand(97,122)];
            $char .= chr($arr[mt_rand(0,2)]);
        }
        return $char;
    }

    public static function splitCamelCase($input){
        return preg_split(
            '/(^[^A-Z]+|[A-Z][^A-Z]+)/',
            $input,
            -1,
            PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE
        );
    }

    public static function getReadableDateInterval($start,$current = false){
        try{
            $dateAdded = new \DateTime(Date('c',strtotime($start)));
            if($current){
                $current = new \DateTime(Date('c',\strtotime($current)));
            }else{
                $current = new \DateTime();
            }

            $interval = $dateAdded->diff($current);
            $years = $interval->y;
            $months = $interval->m;
            $days = $interval->d;
            $hours = $interval->h;
            $minutes = $interval->i;
            $seconds = $interval->s;

            $word = '';
            $singleYear = 0;
            $singleMonth = 0;
            $singleDay = 0;
            $singleHour = 0;
            $singleMinute = 0;
            $singleSecond = 0;
            $end = 0;

            $endCallback = function() use (&$word,&$end){
                $word .= ' ago';
                $end = 1;
            };

            if(!$end && $years){
                $singleYear = ($years == 1);
                $word .= (!$singleYear ? $years.' years' : 'a year ');
            }

            if(!$end && $months){
                $singleMonth = ($months == 1);
                $month = ($singleMonth ? 'a month' : $months.' months ');
                $month = ($years ? ', '.$month : $month);
                $word .= $month.' ';
                if($years){
                    $endCallback();
                }
            }

            if(!$end && $days){
                $singleDay = ($days == 1);
                $day = ($singleDay ? 'a day' : $days.' days');
                $day = ($months ? ', '.$day : $day);
                $word .= $day.' ';
                if($months){
                    $endCallback();
                }
            }

            if(!$end && $hours){
                $singleHour = ($hours == 1);
                $hour = ($singleHour ? 'an hour' : $hours.' hours');
                $hour = ($days ? ', '.$hour : $hour);
                $word .= $hour.' ';
                if($days){
                    $endCallback();
                }
            }

            if(!$end && $minutes){
                $singleMinute = ($minutes == 1);
                $minute = ($singleMinute ? 'a minute' : $minutes.' minutes');
                $minute = ($hours ? ', '.$minute : $minute);
                $word .= $minute.' ';
                if($days){
                    $endCallback();
                }
            }

            if(!$end && $seconds){
                $singleSecond = ($seconds == 1);
                $second = ($singleSecond ? 'a second' : $seconds.' seconds');
                $second = ($minutes ? ', '.$second : $second);
                $word .= $second;
                $endCallback();
            }

            return $word;
        }catch(\Exception $e){
            return false;
        }
    }

    public static function getDataFromArray(array &$data,string $key){
        $pointer = &$data;
        $parts = explode('-->',$key);
        for($i = 0,$j = count($parts);$i < $j;$i++){
            $part = $parts[$i];
            if(is_numeric($part)){
                $part = (int) $part;
            }
            if(isset($pointer[$part])){
                if(($i + 1) === $j){
                    return $pointer[$part];
                }else{
                    if(is_array($pointer[$part])){
                        $pointer = &$pointer[$part];
                        continue;
                    }
                }
            }
            return null;
        }
    }

    public static function setDataInArray(array &$data,string $key,$value){
        $pointer = &$data;
        $parts = explode('-->',$key);
        for($i = 0,$j = count($parts);$i < $j;$i++){
            $part = $parts[$i];
            if(is_numeric($part)){
                $part = (int) $part;
            }
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
        return true;
    }

    public static function removeDataFromArray(array &$data,string $key){
        $pointer = &$data;
        $parts = explode('-->',$key);
        for($i = 0,$j = count($parts);$i < $j;$i++){
            $part = $parts[$i];
            if(($i + 1) === $j){
                $pointer[$part] = null;
                $pointer = array_filter($pointer);
            }else{
                if((isset($pointer[$part]) && is_array($pointer[$part]))){
                    $pointer = &$pointer[$part];
                }else{
                    return true;
                }
            }
        }
        return true;
    }

    public static function is_valid_bitcoin_address($addr,$version = null){
        $decode = function($data){
            $charsetHex = '0123456789ABCDEF';
            $charsetB58 = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
            $raw = "0";
            for($i = 0; $i < strlen($data);$i++){
                $current = (string)strpos($charsetB58, $data[$i]);
                $raw = (string)bcmul($raw, "58", 0);
                $raw = (string) bcadd($raw, $current, 0);
            }
            $hex = "";
            while (bccomp($raw, 0) == 1){
                $dv = (string)bcdiv($raw, "16", 0);
                $rem = (integer) bcmod($raw, "16");
                $raw = $dv;
                $hex = $hex . $charsetHex[$rem];
            }
            $withPadding = strrev($hex);
            for($i = 0; $i < strlen($data) && $data[$i] == "1"; $i++){
                $withPadding = "00".$withPadding;
            }
            if(strlen($withPadding) % 2 != 0){
                $withPadding = "0".$withPadding;
            }
            return $withPadding;
        };

        $typeOf = function($addr) use ($decode){
            if(preg_match('/[^1-9A-HJ-NP-Za-km-z]/', $addr)){
                return false;
            }
            $decoded = $decode($addr);
            if(strlen($decoded) != 50){
                return false;
            }
            $version = substr($decoded, 0, 2);
            $check = substr($decoded, 0, strlen($decoded) - 8);
            $check = pack("H*", $check);
            $check = hash("sha256", $check, true);
            $check = hash("sha256", $check);
            $check = strtoupper($check);
            $check = substr($check, 0, 8);
            $isValid = ($check == substr($decoded, strlen($decoded) - 8));
            return ($isValid ? $version : false);
        };

        $isValid = function($addr, $version = null) use ($typeOf){
            $type = $typeOf($addr);
            if($type === false){
                return false;
            }
            if(is_null($version)){
                $version = 'main';
            }

            $valid = [];
            switch($version){
                case 'main':
                    $valids = ['00','05'];
                break;

                case 'test':
                    $valids = ['6F','C4'];
                break;
            }
            return in_array($type, $valids);
        };
        return $isValid($addr,$version);
    }

    public function __construct(&$managers){
        $this->managers = &$managers;
    }

    public function launch(){
        $this::setDataInArray($this->managers,'General-->Utils',$this);
        return true;
    }

    public function init($type,array $options = ['__settings' => ['retain' => true]]){
        $retain = self::getDataFromArray($options,'__settings-->retain');
        $newInstance = self::getDataFromArray($options,'__settings-->fresh');

        $pointer = str_replace('-','-->',$type);
        $object = $this::getDataFromArray($this->managers,$pointer);

        if(is_object($object) && is_null($newInstance)){
            return $object;
        }

        switch($type){
            case 'General-HttpClient':
                $object = new \GuzzleHttp\Client([
                    'verify' => true,
                    'timeout' => (float) $this->managers['adminSettings']['http_manager_timeout']
                ]);
            break;

            case 'General-Google-Captcha':
                $object = new \Scit\General\Google\Captcha($this->managers);
                $retain - false;
            break;

            case 'General-Cron':
                $object = new \Scit\General\Cron($this->managers);
            break;

            case 'General-Database':
                $object = new \Scit\General\Database($this->managers);
            break;

            case 'General-Permission':
                $object = new \Scit\General\Permission($this->managers);
            break;

            case 'General-Request':
                $object = new \Scit\General\Request($this->managers);
            break;

            case 'General-Response':
                $object = new \Scit\General\Response($this->managers);
            break;

            case 'General-Processor':
                $object = new \Scit\General\Processor($this->managers);
            break;

            case 'General-Session':
                $object = new \Scit\General\Session($this->managers);
                $object->start();
                $object->expires(2592000);
            break;

            case 'General-HtmlManager':
                $twig_file_system_loader = new \Twig_Loader_Filesystem($this->managers['documentRoot'].$this->managers['adminSettings']['twig_template_dir']);
                $object = new \Twig_Environment($twig_file_system_loader,
                    [
                        'debug' => $this->managers['adminSettings']['twig_debug'],
                        'auto_reload' => true,
                        'optimizations' => 1,
                        'cache' => (is_string($this->managers['adminSettings']['twig_cache']) ? $this->managers['documentRoot'].$this->managers['adminSettings']['twig_cache'] : false)
                    ]
                );

                /*set a test for string */
                $object->addTest(new \Twig_Test('string', function($value){
                    return is_string($value);
                }));

                /* set timezone used by twig*/
                $object->getExtension('Twig_Extension_Core')->setTimezone($this->managers['adminSettings']['php_timezone']);

                if($this->managers['adminSettings']['twig_debug']){
                    $object->addExtension(new \Twig_Extension_Debug());
                };
            break;

            case 'General-Uploader':
                $request = $this->init('General-Request');
                $sessionToken = $request->get('sessionToken');
                $options = self::getDataFromArray($options,'Uploader') ?: [];
                if($sessionToken){
                    $options['sessionToken'] = $sessionToken;
                }
                $object = new \Scit\General\Uploader($this->managers,$options);
            break;
            
            case 'General-Mailer':
                $useException = self::getDataFromArray($options,'useException') ?: false;
                $object = new \PHPMailer\PHPMailer\PHPMailer($useException);
                $retain = false;
            break;
            
            case 'General-UrlProcessor':
                $url = self::getDataFromArray($options,'url');
                $object = new \Scit\General\UrlProcessor($url);
                $retain = false;
            break;

            case 'General-Validator':
                $object = new \Scit\General\Validator($this->managers);
            break;

            case 'General-JsonResponder':
                $object = \Closure::bind(function($stat,$resp = false){
                    $out = [];
                    if(is_array($stat)){
                        $out = &$stat;
                    }else{
                        $out = [
                            'status' => ($stat ? 'ok' : 'error'),
                            'response' => $resp
                        ];
                    }
                    return $this->init('General-Response')->sendJson($out);
                },$this);
            break;

            case 'Users-Manager':
                $object = new \Scit\Users\Manager($this->managers);
            break;

            case 'Users-Admin-Manager':
                $object = new \Scit\Users\Admin\Manager($this->managers);
            break;

            case 'Users-Student-Manager':
                $object = new \Scit\Users\Student\Manager($this->managers);
            break;

            case 'Users-SecondarySchool-Manager':
                $object = new \Scit\Users\SecondarySchool\Manager($this->managers);
            break;

            case 'Users-Institution-Manager':
                $object = new \Scit\Users\Institution\Manager($this->managers);
            break;

            case 'Users-InternshipProvider-Manager':
                $object = new \Scit\Users\InternshipProvider\Manager($this->managers);
            break;
        }

        if(is_object($object)){
            if($retain){
                $this::setDataInArray($this->managers,$pointer,$object);
            }
            return $object;
        }
        return false;
    }

    public function destroy($dependency){
        switch($dependency){
            case 'all':
                $General_Defaults = [];
                $General_Defaults['Utils'] = $this->managers['General']['Utils'];
                if(isset($this->managers['General']['Database']) && !is_null($this->managers['General']['Database'])){
                    $general_defaults['Database'] = $this->managers['General']['Database'];
                }

                if(isset($this->managers['General']['Session']) && !is_null($this->managers['General']['Session'])){
                    $this->managers['General']['Session']->touch();
                }

                $this->managers = [];

                foreach($general_defaults as $key => $value){
                    self::setDataInArray($this->managers,"General-->{$key}",$value);
                }

                $general_defaults = null;
            break;

            default:
                $test = eval(<<<'query'
                    $removeHandler = function($string,&$holder) use(&$removeHandler){
                        $parts = explode('-',$string);
                        $partsCount = count($parts);
                        if($partsCount){
                            if($partsCount > 1){
                                if(isset($holder[$parts[0]]) && is_array($holder[$parts[0]])){
                                    $key = $parts[0];
                                    array_shift($parts);
                                    $removeHandler(implode('-',$parts),$holder[$key]);
                                }
                            }else{
                                if(isset($holder[$parts[0]])){
                                    $holder[$parts[0]] = null;
                                }
                            }
                        }
                    };

                    return $removeHandler($dependency,$this->managers);
query
                );
                return true;
            break;
        }
    }

    public function getHashOfData($data){
        if(!(is_int($data) || (is_string($data) && strlen($data)))){
            return '';
        }
        $cipher = self::getDataFromArray($this->managers,'adminSettings-->hash-->cipher');
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = ($this->init('Users-Manager')->isLogged() ? $this->init('General-Session')->use('')->get('userData-->id') : 0);
        $key = self::getDataFromArray($this->managers,'adminSettings-->hash-->key');
        if(\strlen($iv) < $ivlen){
            $iv = str_pad($iv,$ivlen,self::getDataFromArray($this->managers,'adminSettings-->hash-->salt'),STR_PAD_BOTH);
        }
        return bin2hex(openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv));
    }

    public function getDataOfHash($hash){
        $cipher = self::getDataFromArray($this->managers,'adminSettings-->hash-->cipher');
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = ($this->init('Users-Manager')->isLogged() ? $this->init('General-Session')->use('')->get('userData-->id') : 0);
        $key = self::getDataFromArray($this->managers,'adminSettings-->hash-->key');
        if(\strlen($iv) < $ivlen){
            $iv = str_pad($iv,$ivlen,self::getDataFromArray($this->managers,'adminSettings-->hash-->salt'),STR_PAD_BOTH);
        }
        if(!(is_string($hash) && (strlen($hash) % 2 == 0))){
            return '';
        }
        return openssl_decrypt(hex2bin($hash), $cipher, $key, OPENSSL_RAW_DATA, $iv);
    }
}