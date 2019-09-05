<?php

namespace Scit\General;

class Mailer extends \Scit\General\Templates\General{
    private $data = [];
    
    public function use($mailerName = 'phpMailer'){
        $$this->getUtils()::setDataInArray($this->data,'use',$mailerName);
        return $this;
    }
    
    public function addAddress($address,$name = ''){
        $this->getUtils()::setDataInArray($this->data,'address[]',[
            'address' => $address,
            'name' => $name
        ]);
        return $this;
    }
	
    public function setSubject($subject){
        $this->getUtils()::setDataInArray($this->data,'subject',$subject);
        return $this;
    }
    
    public function setHtmlBody($body){
        $this->getUtils()::setDataInArray($this->data,'body-->html',$body);
        return $this;
    }
    
    public function setAltBody($body){
        $this->getUtils()::setDataInArray($this->data,'body-->alt',$body);
        return $this;
    }
    
    public function setPlainBody($body){
        $this->getUtils()::setDataInArray($this->data,'body-->plain',$body);
        return $this;
    }
    
    public function addCc($cc,$name = ''){
        $this->getUtils()::setDataInArray($this->data,'cc[]',[
            'address' => $cc,
            'name' => $name
        ]);
        return $this;
    }
    
    public function addBcc($bcc,$name = ''){
        $this->getUtils()::setDataInArray($this->data,'bcc[]',[
            'address' => $bcc,
            'name' => $name
        ]);
        return $this;
    }
    
    public function setFrom($from,$name = ''){
        $this->getUtils()::setDataInArray($this->data,'from',[
            'address' => $from,
            'name' => $name
        ]);
        return $this;
    }
}