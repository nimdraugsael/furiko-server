<?php
	echo "starting me";
	require_once 'PAMI/Autoloader/Autoloader.php';  
	require_once 'log4php/Logger.php';
	PAMI\Autoloader\Autoloader::register(); 
	
	use PAMI\Client\Impl\ClientImpl as PamiClient;  
	use PAMI\Message\Action\OriginateAction;
	use PAMI\Message\Event\EventMessage;

	include 'XMPPHP/XMPP.php';


	function processMessage( $msg, $conn )
	{
		global $pamiClient;
		global $db;
		global $users;
		$users['nimdraugtest@avanpbx'] = '011';
		var_dump($users);
		$body = $msg['body'];
	    $request = json_decode($body, true);
	    if ( isset($request) ) 
	    {
	    	if ( isset($request['Action']) )
	    		switch($request['Action']) {
	            case 'Handshake':
	                $ext = $db->getExtension($msg['from']);
	                
	            	$response_array = array(	'Action' => 'Handshake', 
	                							'Success' => True,
	                							'Extension' => $ext );
	                $response = json_encode($response_array);
	                $users[bare_jid($msg['from'])] = $ext;
	                // echo "new Handshake: n";
	                // var_dump($users);
	                $conn->message($msg['from'], $body=$response);
	            break;
	            case 'History':
	            	if (isset($users[bare_jid($msg['from'])]))
	            	{
	            		if ( isset($request['with']) )
	            		{
		            		$from = bare_jid($msg['from']);
		            		$with = $request['with'];
		            		// echo "getHistory: from $from , with $with";
		            		$history = $db->getHistory($from, $with);
		            		if ($history)
		            		{
			            		$response_array = array(	'Action' => 'History', 
			                								'Success' => True,
			                								'History' => $history );
		            		}
		            		else
		            		{
			            		$response_array = array(	'Action' => 'History', 
				                							'Success' => False,
				                							'Error' => 'No history' );		            			
		            		}
	            		}
	            		else
	            		{
	            			$response_array = array(	'Action' => 'History', 
	                									'Success' => False,
	                									'Error' => "Need 'with' parameter" );	
	            		}
	            	}
	            	else
	            	{
	            		$response_array = array(	'Action' => 'History', 
	                								'Success' => False,
	                								'Error' => "Not registered, Handshake first" );	
	            	}
	                $response = json_encode($response_array);
	                $conn->message($msg['from'], $body=$response);
	            break;
	            case 'OutgoingCall':
	            	if (isset($users[bare_jid($msg['from'])]))
	            	{
	            		if ( isset($request['with']) )
	            		{
		            		$from = bare_jid($msg['from']);
		            		$with = $request['with'];
		            		// echo "getHistory: from $from , with $with";
		            		$oa = new OriginateAction("SIP/011");
		            		$oa->setExtension("*65");
		            		$oa->setContext("from-internal");
		            		$oa->setPriority("1");
		            		$oa->setCallerId($from);
		            		$response = $pamiClient->send($oa);
		            		$response_array = array(	'Action' => 'OutgoingCall', 
		                									'Success' => $response->isSuccess() );
	            		}
	            		else
	            		{
	            			$response_array = array(	'Action' => 'OutgoingCall', 
	                									'Success' => False,
	                									'Error' => "Need 'with' parameter" );	
	   					}
	            	}
	            	else
	            	{
	            		$response_array = array(	'Action' => 'OutgoingCall', 
	                								'Success' => False,
	                								'Error' => "Not registered, Handshake first" );	
	            	}
	                $response = json_encode($response_array);
	                $conn->message($msg['from'], $body=$response);
	            break;
	        }
	    }
	}

	function bare_jid($jid)
	{
		return preg_replace('/([^\/]+)\/[^\/]+/i', '$1', $jid);		
	}

	class Database 
	{
		private $mysqli_asterisk; // connection

		public function __construct() {
			$this->mysqli_asterisk = new mysqli("localhost", "root", "123456", "asterisk");
			$this->mysqli_openfire = new mysqli("localhost", "root", "123456", "openfire");
			$this->mysqli_openfire->query("set names utf8;");
		}
		/**
		 * Get user extension
		 *
		 * @param string $jid
		 */

		public function getExtension($jid) {
			$jid = bare_jid($jid);
			$sql = "select extension 
					from users 
					where jid='$jid'";
			echo "\n jid = $jid \n";
			// $sql = "select * from users";
			// var_dump($this);
			$res = $this->mysqli_asterisk->query($sql);
			$res->data_seek(0);
			$row = $res->fetch_assoc();
			var_dump($row['extension']);
			return ($row['extension']);
		}

		public function getHistory($from, $with)
		{
			$sql ='	select 
				    from_unixtime(m.time/1000, "%Y-%m-%d %h:%i:%s") time,
					if( m.direction = "to", c.ownerjid , c.withjid ) jid,
					c.withjid,
				    body
					from archivemessages m, archiveconversations c
					where m.conversationid = c.conversationid
					and ownerjid = "'.$from.'"
					and withJid = "'.$with.'";';
			// var_dump($this->mysqli_openfire);
			// echo "$from ~> $with";
			$res = $this->mysqli_openfire->query($sql);
			// var_dump($res);
			$res->data_seek(0);
			while ($row = $res->fetch_assoc()) {
			    $output[] = $row;
			}
			// var_dump($output);
			return json_encode($output);
		}
	}

	global $users;


	date_default_timezone_set('Asia/Vladivostok');
	$pamiClientOptions = array(  
	    'host' => 'avanpbx',        
	    'scheme' => 'tcp://',         
	    'port' => 5038,               
	    'username' => 'furiko',        
	    'secret' => '123456',       
	    'connect_timeout' => 10000,   
	    'read_timeout' => 1000000       
	);  

	
	$pamiClient = new PamiClient($pamiClientOptions);  
	  
	// Open the connection  
	$pamiClient->open();  
	$pamiClient->registerEventListener(  
	    function (EventMessage $event) {
			echo "-------------MYLISTENER ORIGINATING--------------";     
	        var_dump($event);  
	    },  
		function (EventMessage $event) {  
	        return  
	            $event instanceof DialEvent;
	        ;  
	    });  
	// echo "1234;";
	// print_r($pamiClient);

	
	$db = new Database();
	echo $db->getExtension("nimdraugtest@avanpbx/fasdfasdf");
	$db->getHistory("nimdraug@avanpbx", "nimdraugtest@avanpbx");

	$conn = new XMPPHP_XMPP('avanpbx', 5222, 'nimdraug', '123456', 'xmpphp', 'avanpbx', $printlog=false, $loglevel=XMPPHP_Log::LEVEL_INFO);
	$conn->useEncryption(False);

	try {
		$conn->connect();
		$connected = true;
			while($connected) {
		    $payloads = $conn->processUntil(array('message', 'presence', 'end_stream', 'session_start'));
		    foreach($payloads as $event) {
		        $pl = $event[1];
		        switch($event[0]) {
		            case 'message':
		            	processMessage($pl, $conn);
		                // print "---------------------------------------------------------------------------------\n";
		                // print "Message from: {$pl['from']}\n";
		                // if($pl['subject']) print "Subject: {$pl['subject']}\n";
		                // print $pl['body'] . "\n";
		                // print "---------------------------------------------------------------------------------\n";
		                // $conn->message($pl['from'], $body="Thanks for sending me \"{$pl['body']}\".", $type=$pl['type']);
		                // if($pl ['body'] == 'quit') $conn->disconnect();
		                // if($pl['body'] == 'break') $conn->send("</end>");
		            break;
		            case 'session_start':
		                $conn->presence($status="Furiko works");
		            break;
		        }
		    // PAMI now
		    $pamiClient->process();  
    		usleep(1000);  
	    }
	}} catch(XMPPHP_Exception $e) {
	    die($e->getMessage());
	}
	// Close the connection  
	try {
		$pamiClient->close();  
	} catch (Exception $e) {
		echo "Exception!";	
	}

?>