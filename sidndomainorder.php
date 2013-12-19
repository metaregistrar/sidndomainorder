#!/usr/bin/php
<?php
include_once("./crypt.php");
include_once("./epp.php");
include_once("./analyze.php");
include_once("./setorderperiod.php");
include_once("./infoorderperiod.php");

$epp = null;

declare(ticks = 1);

pcntl_signal(SIGTERM, "signal_handler");
pcntl_signal(SIGINT, "signal_handler");
error_reporting(E_ALL ^ E_NOTICE);
date_default_timezone_set("UTC");
if ($argc<2)
{
    die(usage());
}
else
{
    // Retrieve extra parameters from the arguments
    $simplefile = false;
    foreach ($argv as $arg)
    {
        if (substr($arg,0,2)=='--')
        {
            list($subarg,$param)=explode('=',$arg);
            if ($subarg == '--file')
            {
                if ($param == 'simple')
                {
                    $simplefile = true;
                }
            }
        }
    }
    switch($argv[1])
    {
        case 'connect':
            if ($params = load_settings())
            {
                echo "Settings succesfully loaded from file\n";
            }
            break;
        case 'analyze':
            if ($argc<3)
            {
                die(usage());
            }
            // Analyze the DOMAIN_ORDER_FREQUENCY report from SIDN
            checkinput($argv[2]);
            analyzefile($argv[2]);
            break;
        case 'info':
            if ($argc<3)
            {
                die(usage());
            }
            checkinput($argv[2]);
            if ($params = load_settings())
            {
                infoorderperiod($argv[2],$params);
            }
            break;
        case 'set1month':
            if ($argc<3)
            {
                die(usage());
            }
            // Set all domain names in the specified file to 1-month order frequency
            checkinput($argv[2]);
            if ($params = load_settings())
            {
                if ($simplefile)
                {
                    setsimpleorderperiods($argv[2],1, $params);
                }
                else
                {
                    setorderperiods($argv[2],1, $params);
                }

            }
            break;
        case 'set3month':
            if ($argc<3)
            {
                die(usage());
            }
            // Set all domain names in the specified file to 3-month order frequency
            checkinput($argv[2]);
            if ($params=load_settings())
            {
                if ($simplefile)
                {
                    setsimpleorderperiods($argv[2],3, $params);
                }
                else
                {
                    setorderperiods($argv[2],3, $params);
                }
            }
            break;
        case 'set12month':
            if ($argc<3)
            {
                die(usage());
            }
            // Set all domain names in the specified file to 12-month order frequency
            checkinput($argv[2]);
            if ($params=load_settings())
            {
                if ($simplefile)
                {
                    setsimpleorderperiods($argv[2],12, $params);
                }
                else
                {
                    setorderperiods($argv[2],12, $params);
                }

            }
            break;
        default:
            die(usage());
    }
}


function checkinput($file)
{
    if ($file)
    {
        if (!file_exists($file))
        {
            die("File ".$file." could not be opened. Please specify the correct file name. File names are case sensitive.");
        }
    }
    else
    {
        die(usage());
    }
}

function usage()
{
    return "Usage: sidndomainorder.php connect\n\n       sidndomainorder.php analyze <inputfile>\n\n       Where inputfile is the SIDN domain order report from the registry website (DOMAIN_ORDER_FREQUENCY).\n\n       sidndomainorder.php info <inputfile>\n       Info all domain name order periods from the domain names on file\n\n       sidndomainorder.php set1month <inputfile> [params]\n       Reset all domain names in the report to 1-month order period\n\n       sidndomainorder.php set3month <inputfile> [params]\n       Reset all domain names in the report to 3-month order period\n\n       sidndomainorder.php set12month <inputfile> [params]\n       Reset all domain names in the report to 12-month order period\n\n       [params]\n       --file=simple\n        Accept a simple list of domain names for the set1month, set3month or set12month functions\n\n";
}

function load_settings()
{
    $inifile = "sidndomainorder.ini";
    $crypt = new crypt('$NwkP^RC!wHLz7BDT7z$n09Wq4659Lxo');
    if (file_exists($inifile))
    {
        $params = json_decode($crypt->Decrypt(file_get_contents($inifile)));
        if ($params)
        {
            if ((strlen($params->username)>0) && (strlen($params->password)>0))
            {
                return $params;
            }
            else
            {
                die ("User login credentials not found in $inifile file. Please remove the file and re-enter your login credentials\n");
            }
        }
        else
        {
            die("Decryption of $inifile failed. Please remove the file and re-enter you login credentials\n");
        }
    }
    else
    {
        echo "Settings file not found, please specify EPP user name and password. User name and password will be stored in a secure file\n";
        echo "Please specify the EPP user name: ";
        $char = fgets(STDIN, 64);
        $username = trim($char);
        echo "Please specify the EPP password: ";
        $char = fgets(STDIN, 64);
        $password = trim($char);
        echo "Connecting to SIDN EPP server with your login credentials...";
        $epp = new epp($username,$password);
        if ($epp->testconnection())
        {
            echo "\nConnection to EPP server successful, saving user credentials in a secure file.\n";
            $params = array('username'=>$username,'password'=>$password);
            $encodedparams = json_encode($params);
            file_put_contents($inifile, $crypt->Crypt($encodedparams));
            return json_decode($encodedparams);
        }
        else
        {
            echo "\nConnection to EPP service failed, please re-check your login details.\n";
            return null;
        }
    }
}

function signal_handler($signal)
{
    global $epp;
    switch($signal)
    {
        case SIGTERM:
        case SIGKILL:
        case SIGINT:
            print "Program aborted - closing connections...T\n";
            if ($epp)
            {
                $epp->disconnect();
            }
            exit;
    }
}
