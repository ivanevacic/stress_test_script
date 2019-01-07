<?php

if (! isset($argv[1])) {
    exit("Action not defined!\n\n");
}
$action = $argv[1];

$limit = 0;
if (isset($argv[2])) {
	$limit = (int)$argv[2];
}

$pids = null;
exec("ps axf | grep agentStressTest.php | grep -v grep | awk '{print $1}'", $pids);

if ($action === 'start') {
    if (! empty($pids)) {
        exit("There are running processes: " . print_r($pids, true) . "\n");
    }
    
    $mongoUrl = parse_ini_file('stressTestConfig.ini')['mongo_url'];

    $manager = new MongoDB\Driver\Manager($mongoUrl);
    //  get all _ids that contain => agent
    $filter  = ['_id' => array('$regex' => 'agent')];
    $options = [
		'limit' => $limit
	];

    $query = new \MongoDB\Driver\Query($filter, $options);
    $rows  = $manager->executeQuery('live.agents', $query);

    foreach ($rows as $document) {
        echo "Logging $document->_id in...\n";
        exec("php agentStressTest.php $document->_id Telephone_1 >> stress.log &");
    }
}
elseif ($action === 'status') {
    echo "Running processes: " . print_r($pids, true) . "\n";
}
elseif ($action === 'stop') {
    if (empty($pids)) {
        exit("No running processes!!!\n");
    }

    foreach ($pids as $pid) {
        echo "Kill process $pid...\n";
        posix_kill($pid, 9);
    }
}
else {
    echo "Use one of start|status|stop actions!\n";
}
