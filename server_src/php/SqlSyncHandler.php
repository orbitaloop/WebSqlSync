<?php
	/**
	 * SyqSyncHandler for WebSqlSync code
	 * sg4r3z
	 * 20/05/2013 
	 */
	 
	final class SqlSyncHandler{
		
		private $clientData,$jsonData;
		
		// well formatted Answer for WebSqlSync Script
		private $serverAnswer = array("result"=> '',"message" => '', "sync_date" => '',"data" => array());
		
		/*
		 * __construct 
		 * Capture the input stream and creates 
		 * an array with the same structure
		 */
		 
		public function __construct($dataFlow = NULL){
			
			if($dataFlow == NULL)
				$this -> jsonData = file_get_contents('php://input');
			else 
				$this -> jsonData = file_get_contents($dataFlow);
		
			$this -> clientData = json_decode($this -> jsonData);
		}
		
		/*
		 * reply
		 * This method create a well-structred reply
		 * for Client in JSON 
		 * This method accept status,message,data
		 * STATUS = boolean value (TRUE for OK, FALSE for ERROR)
		 * MESSAGE = string value for message
		 * DATA = array of data for client
		 */
		
		public function reply($status,$message,$data){
			
			if($status)
				$this -> serverAnswer['result'] = 'OK';
			else
				$this -> serverAnswer['result'] = 'ERROR';
			
			$this -> serverAnswer['message'] = $message;
			$this -> serverAnswer['data'] = $data;
			$this -> serverAnswer['sync_date'] = strtotime("now");
			
			echo json_encode($this -> serverAnswer);
			
		}
		
		/*
		 * call 
		 * This method allows class to call an
		 * external functon to make a custom job
		 */
		 
		public function call($function,SqlSyncHandler $param = NULL){
			call_user_func($function,$param);
		}
		
		/*
		 * getter clientData 
		 * get a clientData property
		 */
		 
		public function get_clientData(){
			return $this -> clientData;
		}
		
		/*
		 * get serverAnswer 
		 * get a serverAnswer property
		 */
		 
		public function get_serverAnswer(){
			return $this -> serverAnswer;
		}
		
		/*
		 * get jsonData
		 * get a jsonData property
		 */
		 
		public function get_jsonData(){
			return $this -> jsonData;
		}
		
	}
	
?>
