<?php
session_start();

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require('vendor/autoload.php');

if (! isset($argv[1])) {
    exit("Agent username not defined!\n\n");
}
$agentUsername = $argv[1];
echo "Agent username: $agentUsername\n";

if (! isset($argv[2])) {
    exit("Agent skill group not defined!\n\n");
}
$agentSkillGroups = explode(',', $argv[2]);
echo "Agent skill groups: " . print_r($agentSkillGroups, true) . "\n";

//  get properties like -> $x = $properties['main']['ws_type']; to get ws_type from 'main' part in .ini
$properties = parse_ini_file('stressTestConfig.ini', true);

$extension = microtime(true) * 10000;
echo "Extension: $extension\n\n";

/**
 * Init websocket connection
 *
 * @var unknown $initWS
 */
$initWS = function ($url, $sessionId) use ($properties, $agentUsername, $agentSkillGroups)
{
    $groups = array();
    foreach ($agentSkillGroups as $gr) {
        $groups[] = ['name' => 'groups', 'value' => $gr];
    }

    echo "WebSocket url: $url\n";
    echo "WebSocket session: $sessionId\n\n";

    //  using Rachet library to connect to websocket server
    \Ratchet\Client\connect($url)->then(function($conn) use ($properties, $agentUsername, $groups, $sessionId) {
        $conn->on('message', function($msg) use ($conn, $properties, $agentUsername, $groups, $sessionId) {

            //  if we are successfully logged in -> set blocked reason to READY(UNBLOCKED)
            if (strpos($msg, '<event>LOGIN</event>') !== FALSE) {
                // currently logged in agent
                echo "($agentUsername) Logged in! \n\n";

				$backend = new SoapClient($properties['soap']['soap_wsdl_url'], array(
					'location' => $properties['soap']['soap_url']
				));
                $backend->agentSendEvt(array(
                    'sessionid' => $sessionId,
                    'event'     => 'UNBLOCKED',
                    'lineid'    => '0',
                    'data'      => $groups
                ));

                echo "($agentUsername) Agent state UNBLOCKED at " . date("Y-m-d H:i:s", time()) . "\n";
                echo "($agentUsername) Waiting for incoming call... \n\n";
            }

            //  accept incoming call
            else if (strpos($msg, '<event>ALERTING</event>') !== FALSE) {
                echo "($agentUsername) Received new ALERTING event at " . date("Y-m-d H:i:s", time()) . "\n";
              
			    $backend = new SoapClient($properties['soap']['soap_wsdl_url'], array(
					'location' => $properties['soap']['soap_url']
				));
			  
                $backend->agentSendEvt(array(
                    'sessionid' => $sessionId,
                    'event'     => 'ACCEPT',
                    'lineid'    => '0'
                ));
                echo "($agentUsername) ACCEPTED call at " . date("Y-m-d H:i:s", time()) . "\n";

                // disconnect from call after 2-6 seconds after accepting it
                sleep(mt_rand(6, 10));
                $backend->agentSendEvt(array(
                    'sessionid' => $sessionId,
                    'event'     => 'DISCONNECT',
                    'lineid'    => '0'
                ));
                echo "($agentUsername) DISCONNECTED call at " . date("Y-m-d H:i:s", time()) . "\n";

                // set blocked reason to READY(UNBLOCKED) after 1 second
                sleep(1);
                $backend->agentSendEvt(array(
                    'sessionid' => $sessionId,
                    'event'     => 'UNBLOCKED',
                    'lineid'    => '0',
                    'data'      => $groups
                ));
                echo "($agentUsername) placed in state UNBLOCKED " . date("Y-m-d H:i:s", time()) . "\n";
            }
        });
    }, function ($e) {
        echo "Could not connect: {$e->getMessage()}\n";
    });
};

/**
 * Get url for opening websocket
 *
 * @var unknown $getWSUrl
 */

$getWSUrl = function () use ($properties, $agentUsername, $extension, $initWS)
{   
    $wsType = $properties['main']['ws_type'];
    echo "WS type: $wsType\n";

    switch ($wsType)
    {
        case 'Go':
            $url = $properties['go']['go_ws_url'];
            echo "Agent login url: $url\n";

            //  zend http client with curl adapter
            $client = new Zend_Http_Client($url, array(
                'adapter'      => 'Zend_Http_Client_Adapter_Curl',
                'curloptions'  => array(
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                )
            ));
            $client->setMethod(Zend_Http_Client::POST);
            $client->setParameterPost(array(
                'name'=> $agentUsername
            ));

            // make request
            try {
                echo "Fetching agent login data...\n";

                $response = $client->request();
                if ($response->isSuccessful()) {
                    $body = $response->getBody();

                    // extract websocket url from response
                    $webSocketURL = json_decode($body, true);

                    $initWS(
                        $webSocketURL['url'] . '/?sessionid=' . $webSocketURL['_id']['$oid'] . '&extension' . $extension .'&keepalive=300',
                        $webSocketURL['_id']['$oid']
                    );
                    break;
                }
                else {
                    exit("Failed: $response\n");
                }
            } catch (Exception $e) {
                exit("Error: " . $e->getMessage() . "\n\n");
            }

        case 'noGo':
            $sessionId = session_id();
			
			$backend = new SoapClient($properties['soap']['soap_wsdl_url'], array(
				'location' => $properties['soap']['soap_url']
			));
            $backend->agentLogin(array(
                'agentLoginData' => array(
                    'userName'     => $agentUsername,
                    'sessionID'    => $sessionId,
                    'directNumber' => '',
                    'firstName'    => $agentUsername,
                    'surname'      => $agentUsername,
                    'lifeTime'     => 0
                )
            ));
            $socket_url = str_replace('http', 'ws', $properties['nogo']['socket_url']);
            $initWS(
                $socket_url . '/?sessionid=' . $sessionId . '&extension=' . $extension .'&keepalive=300',
                $sessionId
            );
            break;

        default:
            exit("Unknown WS type defined!\n\n");
    }
};

$getWSUrl();



