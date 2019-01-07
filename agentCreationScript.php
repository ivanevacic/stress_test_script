<?php
/**
 * require applications api so we have access to Tinebase classes
 */
 
require_once('../../cpm20' . '/api.php');

/**
 * Create desired numbers of agents
 */

$agentsIds = [];
for($i = 1;$i <= 500; $i++)
{
    $agent = explode(',', "Agent$i, Test, Agent$i . Test, agent$i.test@live.hr, promise, 1");
    $newAgent = array(
        'accountFullName'     => $agent[0] . ' ' . $agent[1],
        'accountDisplayName'  => $agent[1] . ' ' . $agent[0],
        'accountFirstName'    => $agent[0],
        'accountLastName'     => $agent[1],
        'accountEmailAddress' => $agent[3],
        'accountLoginName'    => $agent[0],
        'accountPassword'     => $agent[4],
        'accountPrimaryGroup' => $agent[5]
    );
    $agentObj = new Tinebase_Model_FullUser($newAgent, true);
    $createdAgent = Admin_Controller_User::getInstance()->create($agentObj, $agent[4], $agent[4]);

    echo "Creating Agent $i ....\n";

    //fetch IDs of created agents and store them
    $currentUser =  Tinebase_User::getInstance()->getUserByProperty('accountLoginName', "Agent$i", 'Tinebase_Model_FullUser');
    array_push($agentsIds, $currentUser->getId());
}

/**
 * Put created agents into telephone skill group we created manually
 */

$telephoneSkillGroupID = 1;
$controllerSg = new Telephone_Controller_SkillGroup;
$controllerSg->setSkillGroupMembers($telephoneSkillGroupID, $agentsIds);
$telephoneSkillGroupName = $controllerSg->getSkillGroupById($telephoneSkillGroupID);
echo "Agents added to $telephoneSkillGroupName telephone skill group\n";

/**
 * Delete agents we previously created from application
 */

  /* echo "Deleting created agents from database....\n";
  Admin_Controller_User::getInstance()->delete($agentsIds);
  echo "Finished deleting agents!\n"; */

?>
