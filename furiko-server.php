<?php
	echo "starting me";
	require_once 'PAMI/Autoloader/Autoloader.php';  
	require_once 'log4php/Logger.php';
	PAMI\Autoloader\Autoloader::register(); 
	
	use PAMI\Client\Impl\ClientImpl as PamiClient;  
	use PAMI\Message\Action\OriginateAction;
	use PAMI\Message\Event\EventMessage;

	declare(ticks=1);

	require 'JAXL/jaxl.php';

	// include 'XMPPHP/XMPP.php';
	function sendMessage($to, $body)
	{
		$stanza = new XMPPStanza("message");
		$stanza->to = $to;
		$stanza->body = $body;
		$stanza->type = "headline";
		$stanza->from = "frk@avanpx";
		global $xmpp_client;
		$xmpp_client->send($stanza);
	}

	function processMessage( $stanza )
	{
		global $pamiClient;
		global $xmpp_client;
		global $db;
		global $astdb;
		global $users;
		global $originating_calls;
<<<<<<< HEAD
=======
		// echo "got new message";
		// var_dump($stanza);
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
		$msg_type = "headline";

		$error_401 = array(	'Action' => 'Error',
											 	'ErrorCode' => '401',
											 	'ErrorMessage' => 'Not registered. Handshake first');

		// $users['nimdraugtest@avanpbx'] = '011';
		// $users['nimdraug@avanpbx'] = '010';
		// var_dump($users);
		// $body = $msg['body'];
		$body = str_replace("&quot;", '"', $stanza->body);

		// echo "body :> $body";
    $request = json_decode($body, true);
    $from = bare_jid($stanza->from);
    // echo bare_jid($stanza->from);
    // --
    // unset( $request );
    if ( isset($request) ) 
    {
    	if ( isset($request['Action']) )
    		switch($request['Action']) {
            case 'Handshake':
              $ext = $astdb->getExtension($from);
              $response_array = array(	'Action' => 'Handshake', 
<<<<<<< HEAD
              							'Success' => True,
                							'Extension' => $ext );
=======
                							'Success' => True );
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
              $response = json_encode($response_array);
              $users[$from] = $ext;
            	sendMessage($from, $response);
            break;
            case 'Goodbye':
          		unset($users[$from]);
            	$response_array = array(	'Action' => 'Goodbye', 
                							'Success' => True );
              $response = json_encode($response_array);
          		sendMessage($from, $response);
          	break;
            
            break;
            case 'History':
            	if (isset($users[$from]))
            	{
            		if ( isset($request['With']) )
            		{
	            		$from = bare_jid($from);
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
			                							'Error' => 'No history for entry' );		            			
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
            		$response_array = $error_401;
            	}
                $response = json_encode($response_array);
            		sendMessage($from, $response);
            break;
            case 'OutgoingCall':
<<<<<<< HEAD
=======
            	echo "Got outgoing call\n";
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
            	if (isset($users[$from]))
            	{
            		if ( isset($request['With']) )
            		{
	            		$with = $request['With'];
	            		// echo "getHistory: from $from , with $with";
	            		$from_ext = $astdb->getExtension($from);
	            		$with_ext = $astdb->getExtension($with);
<<<<<<< HEAD
=======

	            		echo "\nNew call from $from to $with";
	            		echo "\nFound extensions: from $from_ext, with $with_ext";
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
	            		if ( ($from_ext) && ($with_ext) )
	            		{
		            		$oa = new OriginateAction("SIP/$from_ext");
		            		$oa->setExtension($with_ext);
		            		$oa->setContext("from-internal");
		            		$oa->setPriority("1");
		            		$oa->setCallerId($from);
		            		try {
		            			$response = $pamiClient->send($oa);
			            		$originating_calls[] = array(	'from_ext' => $from_ext,
			            										'with_ext' => $with_ext );
			            		$response_array = array(	'Action' => 'OutgoingCall', 
			                								'Success' => $response->isSuccess() );
		            		} catch (Exception $e) {
		            			$response_array = array(	'Action' => 'OutgoingCall', 
			                								'Success' => false );		            		
		            		}
	            		}
	            		else 
	            		{
	            			$response_array = array(	'Action' => 'OutgoingCall', 
		                													'Success' => false,
<<<<<<< HEAD
		                													'Error' => '401' );
=======
		                													'Error' => 'Extensions not found' );
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
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
            		$response_array = $error_401;
            	}
<<<<<<< HEAD
                $response = json_encode($response_array);
            		sendMessage($from, $response);
=======
              $response = json_encode($response_array);
          		sendMessage($from, $response);
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
            break;
        }
    }
	}

	function bare_jid($jid)
	{
		return preg_replace('/([^\/]+)\/[^\/]+/i', '$1', $jid);		
	}

	function bare_ext($channel)
	{
		preg_match('/[^\/]+\/([^\-]+)/i', $channel, $result);
		if (isset($result[1])) return $result[1];
	}

	class AsteriskDB
	{
		public $users;

		public function getList()
		{
			try {
<<<<<<< HEAD
				system("sudo /usr/sbin/asterisk -rx \"database show AMPUSER\" | grep jid > /tmp/asterisk_jid_list.txt");
				$fd=fopen("/tmp/asterisk_jid_list.txt","r");
				while ($line=fgets($fd,1000)) {
					preg_match('/\/AMPUSER\/([^\/]+)\/jid[\s]+:[\s]+([^\s]+)/i', $line, $result);
					$list[trim($result[2])] = trim($result[1]);
=======
				system("asterisk -rx \"database show\" | grep jid > /tmp/asterisk_jid_list.txt");
				$fd=fopen("/tmp/asterisk_jid_list.txt","r");
				while ($line=fgets($fd,1000)) {
					preg_match('/\/AMPUSER\/([^\/]+)\/jid[\s]+:[\s]+([^\s]+)/i', $line, $result);
					if ($result) {
						$list[trim($result[2])] = trim($result[1]);
					} 
					else 
					{
						echo "\n Cannot get info from asterisk db";
						echo "\n line from astdb: $line";						
					}
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
				}
				fclose ($fd);
				return $list;
			} catch (Exception $e) {
				echo "Exception in AsteriskDB ~> $e";
			}
		}

		public function getExtension($jid)
		{
			$list = $this->getList();
<<<<<<< HEAD
			var_dump($list);
=======
			// var_dump($list);
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
			if ($list) {
				if (isset($list[$jid])) {
					return $list[$jid];
				}
			}
<<<<<<< HEAD
=======
			return null;
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
		}

		public function getJid($extension)
		{
			$list = $this->getList();
<<<<<<< HEAD
			var_dump($list);
			if ($list) {
				return array_search($extension, $this->getList());	
			}
=======
			// var_dump($list);
			if ($list) {
				return array_search($extension, $this->getList());	
			}
			return null;
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
		}
	}

	class Database 
	{
		private $mysqli_asterisk; // connection
<<<<<<< HEAD
		private $mysqli_openfire; // connection
=======
		private $mysqli_openfire;
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914

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
			if ($res != null)
			{
				$res->data_seek(0);
				$row = $res->fetch_assoc();
				return ($row['extension']);
			}
			else 
			{
				return null;
			}
		}

		public function getJid($extension) {
			$sql = "select jid 
					from users 
					where extension='$extension'";
			$res = $this->mysqli_asterisk->query($sql);
			$res->data_seek(0);
			$row = $res->fetch_assoc();
			return ($row['jid']) ? $row['jid'] : $extension;
		}

		public function getHistory($from, $with)
		{
			$sql ='select 
<<<<<<< HEAD
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
			if ($res != null) {
				// var_dump($res);
=======
					from_unixtime(m.time/1000, "%Y-%m-%d %H:%i:%s") time,
					if( m.direction = "to", c.ownerJid , c.withJid ) jid,
					c.withJid as "with",
				    body
					from archiveMessages m, archiveConversations c
					where m.conversationId = c.conversationId
					and ownerJid = "'.$from.'"
					and withJid = "'.$with.'"';
					// 	$sql ='select 
					// from_unixtime(m.time/1000, "%Y-%m-%d %h:%i:%s") time,
					// if( m.direction = "to", c.ownerJid , c.withJid ) jid,
					// c.withJid as "with",
				 //    body
					// from archiveMessages m, archiveConversations c
					// where m.conversationId = c.conversationId';
			// var_dump($this->mysqli_openfire);
			// echo "$from ~> $with";
			$res = $this->mysqli_openfire->query($sql);
			// var_dump($res);
			if ($res)
			{
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
				$res->data_seek(0);
				while ($row = $res->fetch_assoc()) {
				    $output[] = array(	'time' 	=> $row['time'],
				    								 		'with' 	=> $row['with'],
				    								 		'jid' 	=> $row['jid'],
				    								 		'body' 	=> $row['body'] );
				}
<<<<<<< HEAD
				// var_dump($output);
				return $output;
			}
=======
				var_dump($output);
				return $output;
			}
			else 
			{
				echo "mysqli error : " . $this->mysqli_openfire->error . "\n";				
			}
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
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
	        if ($event instanceof PAMI\Message\Event\BridgeEvent)
	        {
	        	global $originating_calls;
	        	global $users;
	        	$channel1 = $event->getChannel1();
	        	$channel2 = $event->getChannel2();
<<<<<<< HEAD
	        	echo "Calls now $channel1~$channel2: ";
	        	var_dump($originating_calls);
	        	if ($originating_calls != null) {
		        	foreach ($originating_calls as $call) {
						print_r($call);
						if ( $call["from_ext"] ==  bare_ext($channel1) ) {
							$jid = array_search(bare_ext($channel1), $users);
							if ($jid != null) {
								$response = json_encode(
									array(	'Action' 	=> 'BridgeEvent',
	        								'Success' 	=> 'True' ));
								sendMessage($jid, $response);
							}
						}	
						if ( $call["with_ext"] ==  bare_ext($channel2) ) {
							$jid = array_search(bare_ext($channel2), $users);
							if ($jid != null) {
								$response = json_encode(
									array(	'Action' 	=> 'BridgeEvent',
	        								'Success' 	=> 'True' ));
								sendMessage($jid, $response);
							}
						}		        		
		        	}
	        	}
	        	else {
	        		$from_jid = array_search(bare_ext($channel1), $users);
	        		if ($from_jid != null) {
	        			$response = json_encode(
									array(	'Action' 	=> 'BridgeEvent',
	        								'Success' 	=> 'True' ));
								sendMessage($from_jid, $response);
	        		}
					$with_jid = array_search(bare_ext($channel2), $users);
					var_dump($users);
	        		if ($with_jid != null) {
	        			echo "WITH BRIDH!";
	        			$response = json_encode(
									array(	'Action' 	=> 'BridgeEvent',
	        								'Success' 	=> 'True' ));
								sendMessage($with_jid, $response);
	        		}		
	        	}
=======
	        	echo "Calls now $channel1~$channel2:> \n";
	        	if ($users != null ) {
		        		$from_jid = array_search(bare_ext($channel1), $users);
		        		if ($from_jid != null) {
		        			$response = json_encode(
										array(	'Action' 	=> 'BridgeEvent',
		        								'Success' 	=> 'True' ));
									sendMessage($from_jid, $response);
		        		}
								$with_jid = array_search(bare_ext($channel2), $users);
		        		if ($with_jid != null) {
		        			$response = json_encode(
										array(	'Action' 	=> 'BridgeEvent',
		        								'Success' 	=> 'True' ));
									sendMessage($with_jid, $response);
		        		}		
		        }
						// var_dump($users);
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
	        } 
	        if ($event instanceof PAMI\Message\Event\HangupEvent)
	        {
	        	global $users;
	        	$channel = $event->getChannel();
	        	echo "Hangup channel $channel";
	        	if ($users) {
<<<<<<< HEAD
					$jid = array_search(bare_ext($channel), $users);
					if ($jid != null) {
						$response = json_encode(
							array(	'Action' 	=> 'HangupEvent',
									'Success' 	=> 'True' ));
						sendMessage($jid, $response);
					}
=======
							$jid = array_search(bare_ext($channel), $users);
							if ($jid != null) {
								$response = json_encode(
									array(	'Action' 	=> 'HangupEvent',
											'Success' 	=> 'True' ));
								sendMessage($jid, $response);
							}
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
	        	}
	        } 
	        if ($event instanceof PAMI\Message\Event\DialEvent)
	        {
	        	global $users;
	        	global $db;
	        	global $astdb;
	        	$sub_event = $event->getSubEvent();
	        	$channel = bare_ext($event->getChannel());
<<<<<<< HEAD
	        	echo "Dial event\n";
				$from = $channel;
				$from_jid = $astdb->getJid($from);

	        	$destination = bare_ext($event->getDestination());
	        	if ($sub_event == "Begin" && $users != null) {
					$jid = array_search($destination, $users);
					if ($jid != null) {
						$response = json_encode(
							array(	'Action' 	=> 'IncomingCallEvent',
									'Success' 	=> 'True',
									'From' => $from,
									'FromJid' => $from_jid ));
						sendMessage($jid, $response);
					}
	        	}
=======
						$from = $channel;
						$from_caller_id = $event->getCallerIDName();
						$from_jid = $astdb->getJid($from);
	        	$destination = bare_ext($event->getDestination());
	        	echo "\nDial event -> \n" ;
	        	echo "from $from to $destination\n";
	        	try {
		        	if ($sub_event == "Begin" && $users != null) {
								$jid = array_search($destination, $users);
								if ($jid != null) {
									$response = json_encode(
										array(	'Action' 	=> 'IncomingCallEvent',
														'Success' 	=> 'True',
														'From' => $from_caller_id,
														'FromJid' => $from_jid ));
									sendMessage($jid, $response);
								}
		        	}	
	        	} catch (Exception $e) {
	        		echo "Excepition in DialEvent: $e\n";
	        	}
	        	
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
	        } 
	    }
    );  
	
	// register_tick_function(array($pamiClient, 'process'));

	$db = new Database();
	$astdb = new AsteriskDB();

	$xmpp_client = new JAXL(array(
<<<<<<< HEAD
			'jid' => 'frk',
=======
			'jid' => 'pbx',
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914
			'pass' => '123456',
			'host' => 'avanpbx:5222'
		));

	$xmpp_client->require_xep(array(
		'0199'	// XMPP Ping
	));

	$connected = true;
	
	$xmpp_client->add_cb('on_auth_failure', function($reason) {
		global $xmpp_client;
		$xmpp_client->send_end_stream();
		_info("CALLBACK! got on_auth_failure cb with reason $reason");
	});

	$xmpp_client->add_cb('on_connect_error', function($reason) {
		_info("connect error $reason");
	});
  
	$xmpp_client->add_cb('on_auth_success', function() {
		_info("connected!!");
		global $xmpp_client;
		$xmpp_client->set_status("available!", "dnd", 10);
	});

	$xmpp_client->add_cb('on_disconnect', function() {
		_info("disconnected!!");
		// _info("reconnecting");
		// global $xmpp_client;
		// $xmpp_client->con();
		// global $xmpp_client;
		// $xmpp_client->set_status("available!", "dnd", 10);
	});

	$xmpp_client->add_cb('on_headline_message', function($stanza) {
		global $xmpp_client;
		// var_dump($stanza);
		processMessage($stanza);
	});

<<<<<<< HEAD
	$xmpp_client->start(null, $pamiClient);
=======
	$xmpp_client->start(array(
				'--with-unix-sock' => true), $pamiClient);
>>>>>>> 034129a5ee6e98b53ac37481ce59275c1c113914

?>