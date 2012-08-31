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
		$msg_type = "headline";
		// $users['nimdraugtest@avanpbx'] = '011';
		// $users['nimdraug@avanpbx'] = '010';
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
	                $conn->message($msg['from'], $body=$response, $msg_type);
	            break;
	            case 'History':
	            	if (isset($users[bare_jid($msg['from'])]))
	            	{
	            		if ( isset($request['With']) )
	            		{
		            		$from = bare_jid($msg['from']);
		            		$with = $request['With'];
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
	                $conn->message($msg['from'], $body=$response, $msg_type);
	            break;
	            case 'OutgoingCall':
	            	if (isset($users[bare_jid($msg['from'])]))
	            	{
	            		if ( isset($request['With']) )
	            		{
		            		$from = bare_jid($msg['from']);
		            		$with = $request['With'];
		            		// echo "getHistory: from $from , with $with";
		            		$from_ext = $db->getExtension($from);
		            		$with_ext = $db->getExtension($with);
		            		if ( ($from_ext) && ($with_ext) )
		            		{
			            		$oa = new OriginateAction("SIP/$from_ext");
			            		$oa->setExtension($with_ext);
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
			                													'Success' => false,
			                													'Error' => 'Cannot find extensions' );
		            		}
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
	                $conn->message($msg['from'], $body=$response, $msg_type);
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
			$sql ='select 
				    from_unixtime(m.time/1000, "%Y-%m-%d %h:%i:%s") time,
					if( m.direction = "to", c.ownerjid , c.withjid ) jid,
					c.withjid as "with",
				    body
					from archivemessages m, archiveconversations c
					where m.conversationid = c.conversationid
					and ownerjid = "'.$from.'"
					and withJid = "'.$with.'"';
			// var_dump($this->mysqli_openfire);
			// echo "$from ~> $with";
			$res = $this->mysqli_openfire->query($sql);
			// var_dump($res);
			$res->data_seek(0);
			while ($row = $res->fetch_assoc()) {
			    $output[] = array(	'time' 	=> $row['time'],
			    								 		'with' 	=> $row['with'],
			    								 		'jid' 	=> $row['jid'],
			    								 		'body' 	=> $row['body'] );
			}
			// var_dump($output);
			return $output;
		}
	}

	global $users;


	date_default_timezone_set('Asia/Vladivostok');
	$pamiClientOptions = array(  
			'log4php.properties' => __DIR__ . '/log4php.properties',  
	    'host' => 'avanpbx',        
	    'scheme' => 'tcp://',         
	    'port' => 5038,               
	    'username' => 'furiko',        
	    'secret' => '123456',       
	    'connect_timeout' => 10000,   
	    'read_timeout' => 10000      
	);  

	
	$pamiClient = new PamiClient($pamiClientOptions);  
	  
	// Open the connection  
	$pamiClient->open();  
	$pamiClient->registerEventListener(  
	    function (EventMessage $event) {
				echo "event:\n";  
				// print_r($event);

				echo "EMPTY? -> |" . $event->getName() . "|\n";   
	        // var_dump($event);
	        // if ($event instanceof PAMI\Message\Event\BridgeEvent)
	        // {
	        // 	echo "\n";
	        // 	echo "Channel1 :" . $event->getChannel1();
	        // 	echo "Channel2 :" . $event->getChannel2();
	        // 	echo "\n";
	        // } 
	    }
	 //    ,  
		// function (EventMessage $event) {  
	 //        return  
	 //            $event instanceof BridgeEvent;
	 //        ;  
	 //    }
	    );  
	
	$db = new Database();
	echo $db->getExtension("nimdraugtest@avanpbx/fasdfasdf");
	$db->getHistory("nimdraug@avanpbx", "nimdraugtest@avanpbx");

	$conn = new XMPPHP_XMPP('avanpbx', 5222, 'frk', '123456', 'xmpphp', 'avanpbx', $printlog=false, $loglevel=XMPPHP_Log::LEVEL_INFO);
	$conn->useEncryption(False);
  $conn->processTime(100);

	try {
		$conn->connect();
		$connected = true;
		while($connected) {
		  $payloads = $conn->processUntil(array('message', 'presence', 'end_stream', 'session_start'));
		  var_dump($payloads);
		  // if (isset($payloads)) {
			 //  foreach($payloads as $xmpp_event) {
		  //     $pl = $xmpp_event[1];
		  //     switch($xmpp_event[0]) {
    //         case 'message':
    //         	processMessage($pl, $conn);
	   //          break;
    //         case 'session_start':
    //           $conn->presence($status="Furiko works");
	   //          break;
	   //  		}
		  //   }	
		  // }
		  // PAMI now
	    $pamiClient->process();  
  		// usleep(100);  
  	}
	} 
	catch(XMPPHP_Exception $e) {
	    die($e->getMessage());
	}
	// Close the connection  
	try {
		$pamiClient->close();  
	} catch (Exception $e) {
		echo "Exception!";	
	}

?>