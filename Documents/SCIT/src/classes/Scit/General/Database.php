<?php

namespace Scit\General;

class Database{
   
	private $conn,$debug,$data = [];
	
	public function __construct(&$managers){
        $this->debug = &$managers['adminSettings']['db_debug'];
        $this->conn = $this->open($managers['adminSettings']['db_host'],$managers['adminSettings']['db_database'],$managers['adminSettings']['db_user'],$managers['adminSettings']['db_pass']);
        if(isset($managers['adminSettings']['db_timezone'])){
            $this->query("set time_zone = '{$managers['adminSettings']['db_timezone']}'")->result(false);
        }
    }

	private function open($host,$db,$user,$pass){
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8";
        $opt = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => true,
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            \PDO::ATTR_PERSISTENT => true,
            \PDO::NULL_NATURAL => true,
            \PDO::ATTR_TIMEOUT => 20
        ];
		return new \PDO($dsn,$user,$pass,$opt);
	}
    
    public function use($host,$db,$user,$pass){
        $this->conn = $this->open($host,$db,$user,$pass);
        return $this;
    }

    public function quote(string $param){
        return $this->conn->quote($param);
    }

	public function query($query){
		$this->data[] = ['query_type' => 'q','stmt' => $query,'bind' => false];
		return $this;
	}
	
	public function prepare($query,$bind = false){
		$arr = ['query_type' => 'p','stmt' => $query];
		if($bind){
		    $arr['bind'] = $bind;
		}
		$this->data[] = $arr;
		return $this;
	}
	
	public function bind(){
        $last = end($this->data);
        $last_count = (count($this->data) - 1);
        if($last){
            if($last['query_type'] === 'p'){
                $bind = [];
                if(func_num_args() > 0){
                    $args = func_get_args();
                    if(is_array($args[0])){
                        $bind = $args[0];
                    }else{
                        $bind = $args;
                    }
                }
                $this->data[$last_count]['bind'] = $bind;
            }
        }
		return $this;
	}
	
	public function update($debug = null){
	    return $this->process('update',$debug);
	}
	
	public function insert($debug = false){
		return $this->process('insert',$debug);
	}
	
	public function result($debug = null){
		return $this->process('select',$debug);
	}
	
	public function process($type,$debug = null){
        $data = end($this->data);
        if(!$data){
            return false;
        }
        try{
		    if($data['stmt']){
		        if($data['query_type'] === 'q'){
		            switch(true){
		    	        case ($type === 'select'):
			                $result = [];
			                foreach($this->conn->query($data['stmt']) as $row){
					            $result[] = $row;
				            }
				            array_pop($this->data);
				            return ($result !== [] ? $result : false);
		    	        break;
		    	        case ($type === 'insert' || $type === 'update'):
		    	            $this->conn->query($data['stmt']);
				            array_pop($this->data);
				            return ($type === 'insert' ? ((int)$this->conn->lastInsertId() ?: 0) : true);
		    	        break;
	    	        }
	            }elseif($data['query_type'] === 'p'){
	   	            $stmt = $this->conn->prepare($data['stmt']);
	   	            if(isset($data['bind']) && !is_null($data['bind'])){
				        foreach($data['bind'] as $k => $v){
				            if(is_int($v)){
			                    $stmt->bindValue((is_int($k) ?($k + 1) : $k),$v,\PDO::PARAM_INT);			     
			                }else{
			                    $stmt->bindValue((is_int($k) ?($k + 1) : $k),$v,\PDO::PARAM_STR);
			   	            }
	    	            }
	                }
	                $stmt->execute();
	                switch($type){
	    	            case 'select':
	    		            $result = [];
	    		            while($row = $stmt->fetch()){
	    		                $result[] = $row;  		 
                            }
                            array_pop($this->data);
	    		            return ($result !== [] ? $result : false);
	    	            break;
	    	            case 'update':
	    		            return true;
	    	            break;
	    	            case 'insert':
	    		            return ((int)$this->conn->lastInsertId() ?: 0);
	    	            break;
	                }
	            }
	        }
	        return false;
	    }catch(\Exception $e){
            array_pop($this->data);
	        $showError = function() use ($e){
	            echo var_dump($e->getMessage());
	        };
	        if($debug === true){
	            $showError();
	        }elseif(is_null($debug)){
	            if($this->debug === true){
	                $showError();
	            }
	        }
	 	    return false;
	    }
	}
}