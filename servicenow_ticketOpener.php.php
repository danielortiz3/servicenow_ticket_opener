#!/usr/bin/php
<?php


//error_reporting(E_ALL);

/*******************************************************************************
* Header
********************************************************************************

TICKET: 
AUTHOR: Daniel Ortiz
CREATED 24/09/2015 14:05:56

REVISÕES:
--------------------------------------------------------------
<versão> <Data da Revisão>, <autor da revisao>
    - <Descrição das imodificações>

*******************************************************************************/

require_once("../../Nagios_Plugin.php");

$np = null;
define("VERSION","1.22");
define("DEFAULT_MIN_VALUE",0);
define("DEFAULT_MAX_VALUE",100);

/*******************************************************************************
* Exibe o manual do software
********************************************************************************/

function getGuideLine() {

print <<<EOF

------------------------------------------
Manual de exemplo.
------------------------------------------


Plugin Description:
    This plugin monitors tickets per category via API
    SolarWinds.

Installation:

    1. Copy to the plugins directory

    2. After installing, change the values ​​of url and credentials variables
    according to the client URL where it will be installed

    3. Change '../../Nagios_Plugin.php' for the correct directory on ur enviroment. 


Dependencies:
    PHP-Nagios-Plugins

Operation:
    The plugin receives user parameters and collects the number of tickets.
    and if the value is greater than the threshold it alarms.

Command execution and return examples:
    <List all possible>

    ./servicenow_ticketOpener.php -o "status: open" -w 35 -c 40
    WARNING - Total: 39 Tickets | 'Total' = 39tickets; 35,40,00

    ./servicenow_ticketOpener.php -o "Priority: international order" -w 10 -c 15
    OK - Total: 1 Tickets | 'Total' = 1tickets; 10,15,10,100

    ./servicenow_ticketOpener.php -o 'Tech: douglas moreno' -w 0 -c 1
    WARNING - Total: 1 Tickets | 'Total' = 1tickets; 0, 1, 0, 100

   ./servicenow_ticketOpener.php -g -w 35 -c 40
    WARNING: Group1: 24 / Other: 16 | 'Group' = 24,35,40,01 'Other' = 16,35,40,01 100

EOF;

    exit (OK);

}

/*******************************************************************************
* Seta os atributos da variável global $np
*******************************************************************************/

function setNagios() {

    # Constructor
    global $np;

    $np = new Nagios_Plugin(
        array (
            'version' => VERSION,
            'blurb' => "Developed by:\n\tDaniel Ortiz\n"
                . "Author: \n\tDaniel Ortiz - danielortiz3\@gmail.com\n",
            'usage' => "Usage:\n\t%s -o <option> [-g <group>]\n"
                . " \t [-w <warning>] [-c <critical>]\n"
                . " \t [-min <min threshold] [-max <max threshold>",

        )
    );
    //            option     ,help             ,required
    $np->add_arg("option|o=s", "Option");
    $np->add_arg("group|g", "Group Tickets");
    $np->add_arg("warning|w=i", "Warning value", true);
    $np->add_arg("critical|c=i", "Critical value", true);
    $np->add_arg("max|max=s", "Maximum Value");
    $np->add_arg("min|min=s", "Minimal Value");
    $np->add_arg("perfdata", "Show perfdata");
    $np->add_arg("man","Show guideline");
    
    $np->getopts();

    if (!isset($np->opts['max']))
        $np->opts['max'] = DEFAULT_MAX_VALUE;

    if (!isset($np->opts['min']))
        $np->opts['min'] = DEFAULT_MIN_VALUE;
} 

function getResults() {
    
    global $np;

    list ($option, $value) = explode(":", $np->opts['option']);
    
    $option = strtolower($option);
    $value = ucwords($value); #Firt letters in Upper Case
        	
               
    #### Default URLS Values

    $username = "your_username";
    $password = "your_secure_password";

    $url = "https://change_here/cgi-bin/WebObjects/HostedHelpdesk.woa/ra/";
    $ids = "Techs?limit=100&username=adm.opmon&password=graded123&accountId=153";
    $credentials = "&username=$username&password=$password&accountId=153";
    $status = "StatusTypes?limit=100$username&password=$password&accountId=153";

    #### Get Techs IDs
    $techId = $url.$ids;
    $statusId = $url.$status;
   
    #### Reading values
    if ( $option == "tech" ) {

        $valueId = "";   
        $getId = file_get_contents($techId);
        $id = json_decode($getId,true);
        $count = sizeof($id);
  
        #### Get Technician ID      
        for ($i = 0; $i <= $count; $i++) {
            if ($id[$i]['displayName'] == $value ) {
                $valueId .= $id[$i]['id'];
                break;
            }
        }

        #### Technician Tickets URL

        if ($value == "Null" ) {
            $valueId = $value;
        }

        $qualifierTech = "Tickets?limit=100&list=group&qualifier&qualifier=(clientTech.clientId%3D$valueId)";
    	$tech = $url.$qualifierTech.$credentials;
        
        if (!$contents = file_get_contents($tech)){
	        $error = error_get_last();
    	    echo "HTTP request failed. Error was: " . $error['message'];
    	    exit(3);
	    } 
    
        $results = json_decode($contents);
        $totalTickets = sizeof($results);
         
        return array(
            'totalTickets' => $totalTickets
        );

    } elseif ( $option == "priority") {

        #### Replace blank space with %20 to query values Ex.: In%20Progress
        $value = str_replace(' ', '%20', $value); 
        #### Priority Tickets URL
        $qualifierPriority = "Tickets?list=group&page=1&limit=100&qualifier=(prioritytype.".
                             "priorityTypeName%3D%27".$value."%27)";
        $priority = $url.$qualifierPriority.$credentials; 
        #$contents = file_get_contents($priority);
	
        if (!$contents = file_get_contents($priority)){
            $error = error_get_last();
            echo "HTTP request failed. Error was: " . $error['message'];
            exit(3);
        }
    
        $results = json_decode($contents);
        $totalTickets = sizeof($results);

        return array(
            'totalTickets' => $totalTickets
        );
           
    } elseif ( $option == "status" )  {

        $valueId = "";
        $getId = file_get_contents($statusId);
        $id = json_decode($getId,true);
        $count = sizeof($id);

        #### Get StatusTypes ID
        for ($i = 0; $i <= $count; $i++) {

            if ( $id[$i]['statusTypeName'] == $value ) {
                $valueId .= $id[$i]['id'];
                break;
            }
        }

        $qualifierStatus = "Tickets?list=group&page=1&limit=100";
        $status = $url.$qualifierStatus.$credentials;
        
        if (!$contents = file_get_contents($status)){
            $error = error_get_last();
            echo "HTTP request failed. Error was: " . $error['message'];
            exit(3);
        }
        
        $results = json_decode($contents);
        $totalTickets = sizeof($results);

        return array(
            'totalTickets' => $totalTickets
        );

    } elseif( isset ($np->opts['group']) ) {
	
    	$totalTickets = "";
	    
        #### Group Tickets URL
        $qualifierGroup = "Tickets?list=group&page=1&limit=100&qualifier=".
                          "(problemtype.problemTypeName%20like%27".$value."*%27)";
        #### Microsiga Tickets |  ID 6060
        $qualifierMicrosiga = "Tickets?list=group&qualifier&qualifier=".
                              "(clientTech.clientId%3D6060)";

        ##### Other Tickets | ID != 6060
        $qualifierOther = "Tickets?list=group&qualifier&qualifier=".
                          "(clientTech.clientId%21%3D6060)";
       
        #### Unassigned Tickets
        $qualifierUnassigned = "Tickets?list=group&qualifier&qualifier=".
                               "(clientTech.clientId%3DNULL)";
       
        #### Department Maintenance Tickets
        $qualifiderDept = "Tickets?limit=100&list=group&qualifier&qualifier=".
                              "(clientTech.clientId%3D1132)";
 
        $group = $url.$qualifierGroup.$credentials;
        $microsiga = $url.$qualifierMicrosiga.$credentials ;
        $other = $url.$qualifierOther.$credentials;
        $unassigned = $url.$qualifierUnassigned.$credentials;
        $dptMaintenance = $url.$qualifiderDept.$credentials;

        #$tktGroup = file_get_contents($group);
        if (!$tktGroup = file_get_contents($group)){
            $error = error_get_last();
            echo "HTTP request failed. Error was: " . $error['message'];
            exit(3);
        }
        $results = json_decode($tktGroup);
        $totalGroup = sizeof($results);
   
        #$tktMicrosiga = file_get_contents($microsiga);
        if (!$tktMicrosiga = file_get_contents($microsiga)) {
            $error = error_get_last();
            echo "HTTP request failed. Error was: " . $error['message'];
            exit(3);                                            
        }
        $rsltMicrosiga = json_decode($tktMicrosiga);
        $totalMicrosiga = sizeof($rsltMicrosiga);

        #$tktOther = file_get_contents($other);
        if (!$tktOther = file_get_contents($other)) {
            $error = error_get_last();
            echo "HTTP request failed. Error was: " . $error['message'];
            exit(3);
        } 
        $rsltOther = json_decode($tktOther);
        $totalOther = sizeof($rsltOther);

        #$tktUnassigned = file_get_contents($unassigned);
        if (!$tktUnassigned = file_get_contents($unassigned)) {
            $error = error_get_last();
            echo "HTTP request failed. Error was: " . $error['message'];
            exit(3);
        } 
        $rsltUnassigned = json_decode($tktUnassigned);
        $totalUnassigned = sizeof($rsltUnassigned);

        #$tktDept = file_get_contents($dptMaintenance);
        if (!$tktDept = file_get_contents($dptMaintenance)) {
            $error = error_get_last();
            echo "HTTP request failed. Error was: " . $error['message'];
            exit(3);
        }
        $rsltDept = json_decode($tktDept);
        $totalDept = sizeof($rsltDept);

        return array(
            'totalGroup' => $totalGroup,
            'totalMicrosiga' => $totalMicrosiga,
            'totalOther' => $totalOther,
            'totalUnassigned' => $totalUnassigned,
            'totalDept' => $totalDept
        );
    
    }

}

function main() {

    setNagios();   
    global $np;
    
    if ($np->opts['man']) {
        getGuideLine();
    }
        
    $t = getResults($totalTickets);
    
    if ( isset($np->opts['perfdata']) or 
       (isset($np->opts['warning']) and isset($np->opts['critical'])) ) {

        $np->set_thresholds('','');

        $np->threshold()->set_thresholds(
             $np->opts['warning'], 
             $np->opts['critical'],
             $np->opts['min'], 
             $np->opts['max']
        );

        if ( isset ($np->opts['group']) )  {

            $np->add_perfdata ( "Microsiga", $t['totalMicrosiga'], "", $np->threshold() );
            $np->add_perfdata ( "Other", $t['totalOther'], "", $np->threshold() );
            $np->add_perfdata ( "Unassigned", $t['totalUnassigned'], "", $np->threshold() );
            $np->add_perfdata ( "D.Maintenance", $t['totalDept'], "", $np->threshold() );
            
            $messages = array();
            $messages[OK] = "HELPDESK_GRADED - OK: Microsiga: {$t['totalMicrosiga']} ".
                            "/ Other: {$t['totalOther']} / Unassigned: {$t['totalUnassigned']} ".
                            "/ D.Maintenance: {$t['totalDept']}";
            $messages[WARNING] = "HELPDESK_GRADED - WARNING: Microsiga: {$t['totalMicrosiga']} ".
                                 "/ Other: {$t['totalOther']} / Unassigned: {$t['totalUnassigned']} ".
                                 "/ D.Maintenance: {$t['totalDept']}";
            $messages[CRITICAL] = "HELPDESK_GRADED - CRITICAL: Microsiga: {$t['totalMicrosiga']} ".
                                  "/ Other: {$t['totalOther']} / Unassigned: {$t['totalUnassigned']} ".
                                  "/ D.Maintenance: {$t['totalDept']}";
            $messages[UNKNOWN] = "HELPDESK_GRADED - UNKNOWN: Microsiga: {$t['totalMicrosiga']} ".
                                 "/ Other: {$t['totalOther']} / Unassigned: {$t['totalUnassigned']} ".
                                 "/ D.Maintenance: {$t['totalDept']}";

            $status = $np->check_threshold($t['totalTickets']);
            
            $np->nagios_exit(
                $status,
                $messages[$status]
            );

        }     
   
            $np->add_perfdata ( "Total", $t['totalTickets'], "tickets", $np->threshold() );
    
    }

    $messages = array();
    $messages[OK] = "HELPDESK_GRADED OK - Total: {$t['totalTickets']} Tickets";
    $messages[WARNING] = "HELPDESK_GRADED WARNING - Total: {$t['totalTickets']} Tickets";
    $messages[CRITICAL] = "HELPDESK_GRADED CRITICAL - Total: {$t['totalTickets']} Tickets";
    $messages[UNKNOWN] = "HELPDESK_GRADED UNKNOWN - Total: {$t['totalTickets']} Tickets";
    
    $status = $np->check_threshold($t['totalTickets']);

    $np->nagios_exit(
        $status,
        $messages[$status]
    );

}

main();

/* vim: set smartindent tabstop=4 shiftwidth=4 softtabstop=4 expandtab[ENTER] */
