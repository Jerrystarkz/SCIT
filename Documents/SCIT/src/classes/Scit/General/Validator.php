<?php

namespace Scit\General;

class Validator extends \Gump{
    
    private $managers;

    public function __construct(&$managers){
        $this->managers = &$managers;
        parent::__construct();
    }
    
    public function add_custom_validator($name,$callback,$error){
        return static::add_validator($name,$callback,$error);
    }

    public function add_custom_filter($name,$callback){
        return static::add_filter($name,$callback);
    }

    public function getUtils(){
        return $this->managers['General']['Utils'];
    }

    public function filterString(string $value){
        return $this->filter([
            'name' => $value
        ],[
            'name' => 'trim|sanitize_string'
        ])['name'];
    }

    public function isUrlFromOrigin($url){
        $urlProcessor = $this->getUtils()->init('General-UrlProcessor',[
            '__settings' => [
                'retain' => false
            ],
            'url' => $url
        ]);
        if("{$urlProcessor->getScheme()}://{$urlProcessor->getHost()}" === $this->managers['adminSettings']['webUrl']){
            return true;
        }
        return false;
    }

    public function checkIfExists(array $data){
        $db = $this->getUtils()->init('General-Database');
        $for = $this->getUtils()::getDataFromArray($data,'for') ?: false;
        $value = $this->getUtils()::getDataFromArray($data,'value') ?: false;
        $query;
        $data;

        if($for === false || $value === false){
            return [
                'error' => 'fatal',
                'response' => 'Invalid data checked by server'
            ];
        }

        switch($for){

            case 'country-id':
                $query = <<<'query'
                    select concat('{"exists":',if(country.id,1,0),'}') as response from countries as country where country.id = :countryId limit 1
query;
                $data = [
                    'countryId' => &$value
                ];
            break;
                
            case 'region-id':
                $query = <<<'query'
                    select concat('{"exists":',if(region.id,1,0),'}') as response from regions as region join countries as country on (region._country_id = country.id) where region.id = :regionId and country.id = :countryId limit 1
query;
                $data = [
                    'regionId' => &$value,
                    'countryId' => $this->getUtils()::getDataFromArray($data,'countryId')
                ];
            break;
            
            case 'lga-id':
                $query = <<<'query'
                    select concat('{"exists":',if(lga.id,1,0),'}') as response from lgas as lga join regions as region on (lga._region_id = region.id) join countries as country on (region._country_id = country.id) where lga.id = :lgaId and region.id = :regionId and country.id = :countryId limit 1
query;
                $data = [
                    'lgaId' => &$value,
                    'regionId' => $this->getUtils()::getDataFromArray($data,'regionId'),
                    'countryId' => $this->getUtils()::getDataFromArray($data,'countryId')
                ];
            break;
            
            case 'general-email':
                $query = <<<'query'
                    select concat('{"exists":',if(user.id,1,0),',"isVerified":',if(user._is_verified,1,0),',"isApproved":',if(user._is_approved,1,0),'}') as response from users as user where user._email = :userEmail limit 1
query;
                $data = [
                    'userEmail' => $value
                ];
            break;

            case 'general-phone':
                $query = <<<'query'
                    select concat('{"exists":',if(user.id,1,0),',"isVerified":',if(user._is_verified,1,0),',"isApproved":',if(user._is_approved,1,0),'}') as response from users as user where user._phone_number = :userPhone limit 1
query;
                $data = [
                    'userPhone' => $value
                ];
            break;

            case 'subject-name':
                $query = <<<'query'
                    select concat('{"exists":',if(subject.id,1,0),'}') as response from subjects_db as subject where subject._name = :subjectName limit 1
query;
                $data = [
                    'subjectName' => $value
                ];
            break;

            case 'discipline-name':
                $query = <<<'query'
                    select concat('{"exists":',if(discipline.id,1,0),'}') as response from disciplines_db as discipline where discipline._name = :disciplineName limit 1
query;
                $data = [
                    'disciplineName' => $value
                ];
            break;

            case 'stakeholder-unique-name':
                $query = <<<'query'
                    select concat('{"exists":',if(pointer.id,1,0),'}') as response from miscellaneous_db as miscellaneous join miscellaneous as pointer on (miscellaneous.id = pointer._miscellaneous_id) where pointer._key = :uniqueName and miscellaneous._name = :holderName limit 1
query;
                $data = [
                    'uniqueName' => $value,
                    'holderName' => $this->getUtils()::getDataFromArray($data,'forData')
                ];
            break;
        }

        $result = $db->prepare($query)->bind($data)->result();

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(is_array($result)){
                return $result;
            }

            return [
                'error' => 'decode',
                'response' => 'error decoding server resoponse'
            ];
        }

        return [
            'exists' => 0
        ];
    }

}