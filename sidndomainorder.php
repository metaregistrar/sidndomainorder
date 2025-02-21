<?php
// Functions to analyze the SIDN domainorder file
include DIRNAME(__FILE__).'/functions/analyze.php';
// Functions to connect to EPP and view or modify domain order periods
include DIRNAME(__FILE__).'/functions/epp.php';
// Contains EPP username and password. DO NOT PUSH TO GIT!!!!
include DIRNAME(__FILE__).'/config.php';


// I don't like globals, but the abort handler must be able to close the EPP connection
date_default_timezone_set("UTC");

if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    declare(ticks = 1);
    pcntl_signal(SIGTERM, "signal_handler");
    pcntl_signal(SIGINT, "signal_handler");
}

error_reporting(E_ALL ^ E_NOTICE);

if ($argc<2) {
    die(usage());
}
else {
    // Retrieve extra parameters from the arguments
    $simplefile = false;
    $next = null;
    foreach ($argv as $arg) {
        if (str_starts_with($arg,'--')) {
            list($subarg,$param)=explode('=',$arg);
            if ($subarg == '--file') {
                if ($param == 'simple') {
                    $simplefile = true;
                }
            }
            if ($subarg == '--next') {
                $next = $param;
            }
        }
    }
    switch($argv[1]) {
        case 'connect':
            // Test if the connection is valid and works
            $epp = new epp(EPPUSERNAME,EPPPASSWORD);
            break;

        case 'analyze':
            if ($argc<3) {
                die(usage());
            }
            // Analyze the DOMAIN_ORDER_FREQUENCY report from SIDN
            $analyze = new analyze($argv[2]);
            $analyze->analyzefile();
            break;

        case 'info':
            if ($argc<3) {
                die(usage());
            }
            // Info all domain names in a csv file
            $epp = new epp(EPPUSERNAME,EPPPASSWORD);
            $epp->infoorderperiod($argv[2]);
            break;

        case 'distill':
            if ($argc<3) {
                die(usage());
            }
            $analyze = new analyze($argv[2]);
            $analyze->distill();
            break;

        case 'set1month':
            if ($argc<3) {
                die(usage());
            }
            // Set all domain names in the specified file to 1-month order frequency
            $epp = new epp(EPPUSERNAME,EPPPASSWORD);
            $epp->setsimpleorderperiods($argv[2],1);
            break;

        case 'set3month':
            if ($argc<3) {
                die(usage());
            }
            // Set all domain names in the specified file to 3-month order frequency
            $epp = new epp(EPPUSERNAME, EPPPASSWORD);
            $epp->setsimpleorderperiods($argv[2],3);
            break;

        case 'set12month':
            if ($argc<3) {
                die(usage());
            }
            $epp = new epp(EPPUSERNAME,EPPPASSWORD);
            // Set all domain names in the specified file to 12-month order frequency
            $epp->setsimpleorderperiods($argv[2],12);
            break;

        default:
            die(usage());
    }
}


function usage(): string {
    return "Usage: sidndomainorder.php connect\n\n       sidndomainorder.php analyze <inputfile>\n       Where inputfile is the SIDN domain order report from the registry website (DOMAIN_ORDER_FREQUENCY).\n\n       sidndomainorder.php distill <inputfile> <1m|3m|12m> [--next=12m]\n       Distill orders from the input file.\n\n       sidndomainorder.php info <inputfile>\n       Info all domain name order periods from the domain names on file\n\n       sidndomainorder.php set1month <inputfile> [params]\n       Reset all domain names in the report to 1-month order period\n\n       sidndomainorder.php set3month <inputfile> [params]\n       Reset all domain names in the report to 3-month order period\n\n       sidndomainorder.php set12month <inputfile> [params]\n       Reset all domain names in the report to 12-month order period\n\n       [params]\n       --file=simple\n        Accept a simple list of domain names for the set1month, set3month or set12month functions\n\n";
}

function signal_handler($signal): void {
    global $epp;
    switch($signal) {
        case SIGTERM:
        case SIGKILL:
        case SIGINT:
            print "Program aborted\n";
            if ($epp) {
                echo "Closing SIDN EPP connection\n";
                $epp->forcedisconnect();
            }
            exit;
    }
}
