## SqlSyncHandler

This is a class written in PHP for the WebSqlSync Script.
This class allow to manage the json flow sended by the client (with the WebSqlSync script by orbitaloop), and make a reply, simple without error.

This is a small implementation, but I hope it will be useful.
Orbitaloop I would like to thank for the excellent work.

## Example of Usage

	<?php
		
	include("SqlSyncHandler.php");
	
	// initialize the json handler from 'php://input' 
	$handler = new SqlSyncHandler();
	
	// initialize the json handler from a file
	//$handler = new SqlSyncHandler('flow.json');
	
	// call a custom function which will make a job with parsed data
	$handler -> call('myJob',$handler);
	
	// myJob function
	function myJob($handler){
		
		// getting a clientData
		print_r($handler -> get_clientData());
		
		// getting a row json flow
		echo $handler -> get_jsonData();
		
		// this is an example of job
		echo "myJob";
		
		// a positive reply for client
		$handler -> reply(true,"this is a reply",array('browser' => 'firefox'));
		// a error reply example
		//$handler -> reply(false,"this is a error reply",array('browser' => 'firefox'));
		
	}

	?>
