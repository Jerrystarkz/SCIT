<?php

namespace Scit\General;

class Cron{
	private$managers;
	
	public function __construct(&$managers){
		$this->managers = &$managers;
	}
	
}