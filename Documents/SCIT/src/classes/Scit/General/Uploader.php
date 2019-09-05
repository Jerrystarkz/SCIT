<?php
namespace Scit\General;

class Uploader extends \Scit\General\Templates\General{
    
    public function __construct(&$managers,$options = []){
        parent::__construct($managers);
        $this->set('session',($options['session'] ?? $this->getSession()->use('uploads')));
        $sessionPointer = $this->getUtils()::getDataFromArray($options,'sessionPointer');
        if(!$sessionPointer){
            $sessionPointer = $this->getUtils()::random(40);
            while($this->get('session')->get($sessionPointer)){
                $sessionPointer = $this->getUtils()::random(40);
            }
        }
        $this->set('sessionPointer',$sessionPointer);
    }

    public function getMeta($name = false){
        $meta = $this->getRequest()->get('__meta') ?: [];
        if($name){
            return $meta[$name] ?? false;
        }
        return $meta;
    }

    private function sessionGet(string $item = ''){
        $locator = $this->get('sessionPointer');
        if(strlen($item)){
            $locator .= '-->'.$item;
        }
        return $this->getSession()->get($locator);
    }

    private function sessionRemove(string $item = ''){
        $locator = $this->get('sessionPointer');
        if(strlen($item)){
            $locator .= '-->'.$item;
        }
        $this->getSession()->remove($locator);
        return $this;
    }

    private function sessionSet(string $item,$value){
        if(strlen($item)){
            $locator = $this->get('sessionPointer').'-->'.$item;
            $this->getSession()->set($locator,$value);
        }
        return $this;
    }

    public function beginWatch(){
        if($this->getMeta('signal') === 'reset'){
            $this->sessionRemove();
        }else{
            if(!$this->loadData()->isEnded()){
                $this->process();
            }
        }
        return $this;
    }

    private function addError($error){
        $errors = $this->get('errors') ?: [];
        $errors = array_merge_recursive($errors,['uploader' => $error]);
        $this->set('errors',$errors);
        return false;
    }

    public function getErrors(){
        return $this->get('errors');
    }

    private function addFile($url,$data = []){
        $fileName = false;
        $fileType = false;
        $fileSize = \filesize($url);
        $name = $data['file_holder_name'] ?? 'file';
        $fileName = $data['file_name'] ?? 'file';
        $fileType = $data['file_type'] ?? 'application/octect-stream';
        
        $this->set("data-->files-->{$name}",[
            'error' => 0,
            'name' => $fileName,
            'size' => $fileSize,
            'type' => $fileType,
            'tmp_name' => $url
        ]);
        $this->hasNewUpload = true;
        return $this;
    }

    public function hasFileUpload(){
        return empty($this->get('files'));
    }

    public function isEnded(){
        return $this->sessionGet('hasEnded');
    }

    public function isStarted(){
        return $this->sessionGet('hasStarted');
    }

    private function addData(){
        $data = $this->getRequest()->get();
        if(is_array($data)){
            foreach($data as $key => $value){
                if($key !== '__meta'){
                    $this->set("data-->fields-->{$key}",$value);
                }
            }
        }
        return $this;
    }
    
    private function loadData(){
        if($this->isStarted()){
            $data = $this->sessionGet('data');
            if($data){
                $this->set('data',$data);
            }
        }else{
            if($this->getMeta('signal') == 'uploader_start'){
                $this->sessionSet('hasStarted',1);
            }
        }
        return $this;
    }

    private function touch(){
        $this->sessionSet('data',$this->get('data'));
        if($this->getMeta('signal') == 'uploader_done'){
            $this->sessionSet('hasEnded',1);
        }
        return $this;
    }

    private function process(){
        $utils = $this->getUtils();
        $holder = $this->getRequest()->getUploadedFile();
        $dir = $this->managers['documentRoot'].$this->managers['adminSettings']['temp_file_storage'];
        $maximumSingleUploadSize = $this->managers['adminSettings']['maximum_single_upload_size'];
        $maximumTotalUploadSize = $this->managers['adminSettings']['maximum_total_upload_size'];
        $builtFileParams = [];
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        
        $this->addData();

        foreach($holder as $placeholder => &$fileData){
            if($placeholder == 'split_file'){
                $fileName = $utils::random(50);
                clearstatcache(true);
                while(file_exists($dir.$fileName)){
                    $fileName = $utils::random(50);
                }
                $fileLocation = $dir.$fileName;

                if(is_array($fileData) && is_array($fileData['name'])){
                    $this->addError('Ooops only a file part can be uploaded at once');
                    continue;
                }

                $file = $fileData['tmp_name'];
                $name = $fileData['name'];
                $chunkNumber = $this->getMeta('chunk_current_number');
                $chunkTotal = $this->getMeta('chunks_total_number');

                if(filesize($file) > $maximumSingleUploadSize){
                    $this->addError('Oops maximum filesize exceeded');
                    continue;
                }

                if($chunkNumber && $chunkTotal && $name && $file){
                    $chunkNumber = (int) $chunkNumber;
                    $chunkTotal = (int) $chunkTotal;

                    \clearstatcache(true);
                    if(file_exists($fileLocation) && filesize($fileLocation) > $maximumTotalUploadSize){
                        unlink($fileLocation);
                        $this->addError('Oops filesize exceeds maximum allocated');
                        continue;
                    }

                    $processChunk = \Closure::bind(function($currentChunk,$totalChunk) use($name){
                        if($currentChunk == $totalChunk){
                            $this->addFile($this->sessionGet("{$name}-->location"),[
                                'file_name' => $this->getMeta('file_name'),
                                'file_type' => $this->getMeta('file_type'),
                                'file_holder_name' => $this->getMeta('file_holder_name')
                            ]);
                        }else{
                            $this->sessionSet("{$name}-->chunkNumber",(int)$currentChunk);
                        }
                    },$this);

                    $processError = \Closure::bind(function() use($name){
                        $this->sessionRemove($name);
                    },$this);

                    if($chunkNumber === 1){
                        $this->reset();

                        if(!$this->hasError()){
                            $source = fopen($file,'rb');
                            $dest = fopen($fileLocation,'wb');
                            stream_copy_to_stream($source,$dest);
                            fclose($source);
                            fclose($dest);
                            $this->sessionSet("{$name}-->chunkTotal",$chunkTotal)->sessionSet("{$name}-->location",$fileLocation)->sessionSet("{$name}-->name",$name);
                            $processChunk($chunkNumber,$chunkTotal);
                        }else{
                            $processError();
                        }
                        unlink($file);
                    }else{
                        $serverChunkNumber = (int) $this->sessionGet("{$name}-->chunkNumber");
                        $serverChunkTotal = (int) $this->sessionGet("{$name}-->chunkTotal");

                        if(($chunkNumber == ($serverChunkNumber + 1)) && ($chunkTotal == $serverChunkTotal)){
                            $fileLocation = $this->sessionGet("{$name}-->location");

                            if($file && $serverChunkNumber && $serverChunkTotal){
                                if(!$this->hasError()){
                                    $source = fopen($file,'rb');
                                    $dest = fopen($fileLocation,'ab');
                                    stream_copy_to_stream($source,$dest);
                                    fclose($source);
                                    fclose($dest);
                                    $processChunk($chunkNumber,$chunkTotal);
                                }else{
                                    $processError();
                                }
                                unlink($file);
                            }
                        }
                    }
                }
            }else{
                if(!count($builtFileParams)){
                    foreach($fileData as $key => &$value){
                        $builtFileParams[] = $key;
                    }
                }
                $this->reset();

                $addFile = \Closure::bind(function(string $tmp_name,string $holderString) use (&$placeholder,&$maximumSingleUploadSize,&$fileData,&$builtFileParams){
                    if(filesize($tmp_name) <= $maximumSingleUploadSize){
                        $holders = (strlen($holderString) ? explode('-->',$holderString) : false);
                        $builtArrayChain = '';

                        if($holders){
                            foreach($holders as &$holder){
                                if(is_numeric($holder)){
                                    $holder = (int) $holder; 
                                    $current[] = [];
                                    $current = &$current[(count($current) - 1)];
                                    $builtArrayChain .= '['.$holder.']';
                                }else{
                                    $current[$holder] = [];
                                    $current = &$current[$holder];
                                    $builtArrayChain .= '[\''.$holder.'\']';
                                }
                            }
                        }


                        $newFileData = serialize($fileData);
                        $query = <<<"query"
                            \$holder = unserialize('{$newFileData}');
                            \$output = [];
query;
                        foreach($builtFileParams as &$param){
                            if($builtArrayChain){
                                $query .= <<<"query"
                                    \$output['{$param}'] = \$holder['{$param}']{$builtArrayChain};
query;
                            }else{
                                $query .= <<<"query"
                                    \$output['{$param}'] = \$holder['{$param}'];
query;
                            }
                        }

                        $query .= 'return $output;';
                        $fileInfo = @eval($query) ?: [];

                        $location = $this->moveTo($tmp_name,$this->managers['documentRoot'].$this->managers['adminSettings']['temp_file_storage']);
                        if($location){
                            $append = (strlen($holderString) ? "-->{$holderString}" : '');
                            $fileHolderName = "{$placeholder}{$append}";

                            $this->addFile($location,[
                                'file_name' => $fileInfo['name'] ?? 'file',
                                'file_type' => $fileInfo['type'] ?? 'application/octet-stream',
                                'file_holder_name' => $fileHolderName
                            ]);
                        }else{
                            $current = null;
                        }
                    }
                },$this);

                if(isset($fileData['tmp_name']) && isset($fileData['name'])){
                    $holders = [];

                    $generateHolderString = function($holder) use(&$holders){
                        if(is_array($holder)){
                            $process = function(array $item,$parentString = '') use(&$process,&$holders){
                                foreach($item as $key => &$value){
                                    $holderString = (strlen($parentString) ? ($parentString.'-->'.$key) : ($key));
                                    if(is_array($value)){
                                        $process($value,$holderString);
                                    }else if(is_string($value)){
                                        $holders[] = [
                                            'holderString' => $holderString,
                                            'tmp_name' => $value
                                        ];
                                    }
                                }
                            };
                            $process($holder);
                        }else if(is_string($holder)){
                            $holders[] = [
                                'holderString' => '',
                                'tmp_name' => $holder
                            ];
                        }
                    };

                    $generateHolderString($fileData['tmp_name']);

                    foreach($holders as &$holder){
                        $addFile($holder['tmp_name'],$holder['holderString']);
                    }
                }
            }
        }
        $this->touch();
    }

    public function moveTo($fileResource,$fileLocation){
        $file = false;

        \clearstatcache(true);
        if(is_array($fileResource) && isset($fileResource['tmp_name'])){
            $file = $fileResource['tmp_name'];
        }elseif(is_string($fileResource) && is_file($fileResource)){
            $file = $fileResource;
        }else{
            return false;
        }

        if(\is_dir($fileLocation)){
            $fileName = $this->getUtils()::random(50);
            \clearstatcache(true);
            while(\is_file($fileLocation.$fileName)){
                $fileName = $this->getutils()::random(50);
            }
            $fileLocation = $fileLocation.$fileName;
        }

        $source = fopen($file,'rb');
        $dest = fopen($fileLocation,'wb');

        if($source && $dest){
            rewind($source);
            stream_copy_to_stream($source,$dest);
            fclose($source);
            fclose($dest);
            unlink($file);
            return $fileLocation;
        }
        return false;
    }

    public function hasError(){
        return !$this->get('errors');
    }

    public function complete(){
        return $this->isEnded();
    }

    public function getFileDataFor($name = false){
        if(!$name){
            return $this->get('data-->files');
        }else{
            return $this->get("data-->files-->{$name}");
        }
    }

    public function getFieldParamFor($name = false){
        if(!$name){
            return $this->get('data-->fields');
        }else{
            if($name !== '__meta'){
                return $this->get("data-->fields-->{$name}");
            }
        }
        return false;
    }

    public function getSessionPointer(){
        return $this->get('sessionPointer');
    }

    public function isReset(){
        $meta = $this->getMeta();
        return (isset($meta['signal']) && ($meta['signal'] == 'reset'));
    }
}