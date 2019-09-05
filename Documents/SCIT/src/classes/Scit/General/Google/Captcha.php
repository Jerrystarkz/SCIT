<?php

namespace Scit\General\Google;

class Captcha extends \Scit\General\Templates\General{
    
    private $action,$token,$version;

    public function forAction($action){
        $this->action = $action;
        return $this;
    }   
     
    public function process($token){
        $this->token = $token;
        return $this;
    }

    public function forCaptcha($version){
        $this->version = $version;
        return $this;
    }

    public function verify(){
        $httpClient = $this->getUtils()->init('General-HttpClient');
        try{
            $secretKey = ($this->version === 'v2' ? $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v2-->secret') : ($this->version === 'v3' ? $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->secret') : ''));

            $res = $httpClient->request('POST',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->verifyUrl'),
                [
                    'form_params' => [
                        'secret' => $secretKey,
                        'response' => $this->token
                    ]
                ]
            );
            $body = (string) $res->getBody();
            $body = json_decode($body,true);

            if(!is_array($body)){
                return [
                    'status' => 'error',
                    'response' => 'Invalid captcha check response'
                ];
            }

            $success = $this->getUtils()::getDataFromArray($body,'success');
            $challenge_timestamp = $this->getUtils()::getDataFromArray($body,'challenge_ts');

            switch($this->version){
                case 'v2':
                    if(!$success){
                        return [
                            'status' => 'error',
                            'response' => 'Invalid captcha.'
                        ];
                    }
                break;

                case 'v3':
                    $action = $this->getUtils()::getDataFromArray($body,'action');
                    $score = (float) $this->getUtils()::getDataFromArray($body,'score');

                    $checkAction = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->checkAction');
                    $minimumScore = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->minimumScore');

                    if(!$success || ($checkAction && ($action !== $this->action)) || ($score < $minimumScore)){
                        return [
                            'status' => 'error',
                            'response' => 'Invalid captcha..'
                        ];
                    }
                break;
            }

            return [
                'status' => 'ok',
                'response' => 'Valid captcha'
            ];

        }catch(\Exception $e){
            return [
                'status' => 'error',
                'response' => 'Invalid captcha...'
            ];
        }
    }
}