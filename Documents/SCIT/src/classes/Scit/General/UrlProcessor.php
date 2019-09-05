<?php

namespace Scit\General;

class UrlProcessor{
    private $urlString,$processedParsing = false,$parsedUrl = [],$added = [],$errors = [];

    public function __construct($urlString){
        $this->urlString = $urlString;
    }

    private function processParsing(){
        if(!$this->processedParsing){
            $this->parsedUrl = \parse_url($this->urlString);
            if(!is_array($this->parsedUrl)){
                $this->parsedUrl = [];
            }
            $this->processedParsing = true;
        }
        return $this->processedParsing;
    }

    public function getScheme($updated = true){
        if(!$updated){
            if($this->processParsing()){
                $scheme = $this->parsedUrl['scheme'] ?? false;
                return $scheme ?: false;
            }
        }else{
            $scheme = (isset($this->added['scheme']) ? $this->added['scheme'] : $this->getScheme(false));
            if($scheme){
                return $scheme;
            }
            return false;
        }
    }

    public function changeScheme($newScheme){
        $this->added['scheme'] = $newScheme;
        return $this;
    }

    public function getHost($updated = true){
        if(!$updated){
            if($this->processParsing()){
                $host = $this->parsedUrl['host'] ?? false;
                return $host ?: false;
            }
        }else{
            $host = (isset($this->added['host']) ? $this->added['host'] : $this->getHost(false));
            if($host){
                return $host;
            }else{
                return false;
            }
        }
    }

    public function changeHost($newHost){
        $this->added['host'] = $newHost;
        return $this;
    }

    public function getPort($updated = true){
        if(!$updated){
            if($this->processParsing()){
                $port = $this->parsedUrl['port'] ?? false;
                return $port ?: false;
            }
        }else{
            $port = (isset($this->added['port']) ? $this->added['port'] : $this->getPort(false));
            if($port){
                return $port;
            }else{
                return false;
            }
        }
    }

    public function changePort($newPort){
        $this->added['port'] = $newPort;
        return $this;
    }

    public function getUsername($updated = true){
        if(!$updated){
            if($this->processParsing()){
                $username = $this->parsedUrl['username'] ?? false;
                return $username ?: false;
            }
        }else{
            $username = (isset($this->added['username']) ? $this->added['username'] : $this->getUsername(false));
            if($username){
                return $username;
            }else{
                return false;
            }
        }
    }

    public function changeUsername($newUsername){
        $this->added['username'] = $newUsername;
        return $this;
    }

    public function getPassword($updated = true){
        if(!$updated){
            if($this->processParsing()){
                $password = $this->parsedUrl['password'] ?? false;
                return $password ?: false;
            }
        }else{
            $password = (isset($this->added['password']) ? $this->added['password'] : $this->getPassword(false));
            if($password){
                return $password;
            }else{
                return false;
            }
        }
    }

    public function changePassword($newPassword){
        $this->added['password'] = $newPassword;
        return $this;
    }

    public function getPath($updated = true){
        if(!$updated){
            if($this->processParsing()){
                $path = $this->parsedUrl['path'] ?? false;
                return $path ?: false;
            }
        }else{
            $path = '';

            $addPath = function($append,$first = false) use(&$path){
                if($append){
                    if($first){
                        $path = '';
                        if(substr($append,0,1) !== '/'){
                            $path = '/';
                        }
                        $path .= $append;
                    }else{
                        if(substr($append,0,1) !== '/'){
                            $path .= '/';
                        }
                        $path .= $append;
                    }
                }
            };

            $useDefaultPath = \Closure::bind(function() use($addPath){
                $addPath(($this->getPath(false) ?: '/'),true);
            },$this);
            
            if(isset($this->added['path']) && \is_array($this->added['path'])){
                if(isset($this->added['path']['new']) && \is_string($this->added['path']['new'])){
                    $addPath($this->added['path']['new'],true);
                }else{
                    $useDefaultPath();
                }

                if(isset($this->added['path']['sub']) && \is_array($this->added['path']['sub'])){
                    foreach ($this->added['path']['sub'] as $value) {
                        if((!$path) || ($path === '/')){
                            $addPath($value,true);
                        }else{
                            $addPath($value);
                        }
                    }
                }
            }else{
                $useDefaultPath();
            }

            if($path){
                return $path;
            }else{
                return false;
            }
        }
    }

    public function changePath($newPath){
        if(isset($this->added['path']) && is_array($this->added['path'])){
            $this->added['path']['new'] = $newPath;
        }else{
            $this->added['path'] = [
                'new' => $newPath
            ];
        }
        return $this;
    }

    public function addSubPath($subPath){
        $addSubPath = function(array &$path) use($subPath){
            if(!(isset($path['sub']) && is_array($path['sub']))){
                $path['sub'] = [];
            }
            $path['sub'][] = $subPath;
            return true;
        };

        if(isset($this->added['path']) && is_array($this->added['path'])){
            $addSubPath($this->added['path']);
        }else{
            $this->added['path'] = [];
            $addSubPath($this->added['path']);
        }
        return $this;
    }

    public function setPathToParentPath(){
        $path = '';
        if(isset($this->added['path']) && is_array($this->added['path']) && isset($this->added['path']) && is_string($this->added['path']['new'])){
            $path = $this->added['path']['new'];
        }else{
            $path = $this->getPath(false);
        }
        if(!$path){
            $this->changePath('/');
        }else{
            $path = explode('/',$path);
            $last = array_pop($path);
            $this->changePath(implode('/',$path));
        }
        return $this;
    }

    public function getQuery($updated = true){
        if(!$updated){
            if($this->processParsing()){
                $query = $this->parsedUrl['query'] ?? false;
                return $query ?: false;
            }
        }else{
            $query = '';

            $addQuery = function($append,$first = false) use(&$query){
                if($append){
                    if($first){
                        $query = '';
                        if(substr($append,0,1) === '&'){
                            $query = substr($append,1);
                        }else{
                            $query = $append;
                        }
                    }else{
                        if(substr($append,0,1) !== '&'){
                            $query .= '&';
                        }
                        $query .= $append;
                    }
                }
            };

            $useDefaultQuery = \Closure::bind(function() use($addQuery){
                $addQuery($this->getQuery(false),true);
            },$this);
            
            if(isset($this->added['query']) && \is_array($this->added['query'])){
                if(isset($this->added['query']['new']) && \is_string($this->added['query']['new'])){
                    $addQuery($this->added['query']['new'],true);
                }else{
                    $useDefaultQuery();
                }

                if(isset($this->added['query']['sub']) && \is_array($this->added['query']['sub'])){
                    foreach ($this->added['query']['sub'] as $value) {
                        if(!$query){
                            $addQuery($value,true);
                        }else{
                            $addQuery($value);
                        }
                    }
                }
            }else{
                $useDefaultQuery();
            }

            if($query){
                return $query;
            }else{
                return false;
            }
        }
    }

    public function changeQuery($newQuery){
        $queryString = '';
        if(is_string($newQuery)){
            $queryString = $newQuery;
        }elseif(is_array($newQuery)){
            $queryString = \http_build_query($newQuery);
        }
        if(isset($this->added['query']) && is_array($this->added['query'])){
            $this->added['query']['new'] = $queryString;
        }else{
            $this->added['query'] = [
                'new' => $queryString
            ];
        }
        return $this;
    }

    public function addQuery($newQuery){
        $addQuery = function(array &$query) use($newQuery){
            $queryString = '';
            if(is_string($newQuery)){
                $queryString = $newQuery;
            }elseif(is_array($newQuery)){
                $queryString = \http_build_query($newQuery);
            }
            if(!(isset($query['sub']) && is_array($query['sub']))){
                $query['sub'] = [];
            }
            $query['sub'][] = $queryString;
            return true;
        };

        if(isset($this->added['query']) && is_array($this->added['query'])){
            $addQuery($this->added['query']);
        }else{
            $this->added['query'] = [];
            $addQuery($this->added['query']);
        }
        return $this;
    }

    public function getFragment($updated = true){
        if(!$updated){
            if($this->processParsing()){
                $fragment = $this->parsedUrl['fragment'] ?? false;
                return $fragment ?: false;
            }
        }else{
            $fragment = (isset($this->added['fragment']) ? $this->added['fragment'] : $this->getFragment(false));
            if($fragment){
                return $fragment;
            }else{
                return false;
            }
        }
    }

    public function changeFragment($newFragment){
        $this->added['fragment'] = $newFragment;
        return $this;
    }

    private function addError($error){
        $this->errors = [
            'url' => $error
        ];
        return false;
    }

    public function getErrors(){
        return $this->errors['url'] ?? false;
    }

    public function getUrlString(){
        $urlString = '';
        $scheme = $this->getScheme();
        if($scheme){
            $urlString = $urlString.$scheme.'://';
        }
        $username = $this->getUsername();
        if($username){
            $urlString = $urlString.$username;
        }

        $password = $this->getPassword();
        if($password && $username){
            $urlString = $urlString.':'.$password;
        }

        if($username){
            $urlString = $urlString.'@';
        }

        $host = $this->getHost();
        if($host){
            $urlString = $urlString.$host;
        }else{
            return $this->addError('Oops a host is required');
        }

        $port = $this->getPort();
        if($port && $host){
            $urlString = $urlString.':'.$port;
        }

        $path = $this->getPath() ?: '/';
        $urlString = $urlString.$path;

        $query = $this->getQuery();
        if($query){
            $urlString .= '?'.$query;
        }

        $fragment = $this->getFragment();
        if($fragment){
            $urlString .= '#'.$fragment;
        }
        
        return $urlString;
    }
}