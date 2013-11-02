<?php
include_once("./crypt.php");
include_once("./epp.php");

date_default_timezone_set("UTC");
if ($argc<3)
{
    die(usage());
}
else
{

    switch($argv[1])
    {
        case 'connect':
            if ($params = load_settings())
            {
                echo "Settings succesfully loaded from file\n";
            }
            break;
        case 'analyze':
            checkinput($argv[2]);
            analyzefile($argv[2]);
            break;
        case 'set1month':
            checkinput($argv[2]);
            if ($params = load_settings())
            {
                setorderperiods($argv[2],1, $params);
            }
            break;
        case 'set3month':
            checkinput($argv[2]);
            if ($params=load_settings())
            {
                setorderperiods($argv[2],3, $params);
            }
            break;
        case 'set12month':
            checkinput($argv[2]);
            if ($params=load_settings())
            {
                setorderperiods($argv[2],12, $params);
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
    return "Usage: sidndomainorder.php connect\n\n       sidndomainorder.php <analyze> <inputfile>\n       Where inputfile is the SIDN domain order report from the registry website (DOMAIN_ORDER_FREQUENCY).\n\n       sidndomainorder.php <set1month> <inputfile>\n       Reset all domain names in the report to 1-month order period\n\n       sidndomainorder.php <set3month> <inputfile>\n       Reset all domain names in the report to 3-month order period\n\n       sidndomainorder.php <set12month> <inputfile>\n       Reset all domain names in the report to 12-month order period\n\n";
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

function setorderperiods($filename, $period, $params)
{
    echo "Connecting to SIDN EPP service\n";
    if ($epp = new epp($params->username, $params->password))
    {
        if ($epp->connect())
        {
            $reportmonth = ($period==1?"month":"months");
            echo "Setting order period to $period $reportmonth for al domain names in file $filename.\n";
            $linenumber = 0;
            $domains = file($filename, FILE_IGNORE_NEW_LINES);
            foreach ($domains as $domain)
            {
                $linenumber++;
                // Skip first 2 lines of the report
                if (($linenumber > 2) && (strlen($domain)>1))
                {
                    list ($domainname, $startperiod, $frequency, $endperiod)= explode(';',$domain);
                    if ($frequency == $period)
                    {
                        echo "Domain name $domainname already has a period of $period $reportmonth, skipped this domain name\n";
                    }
                    else
                    {
                        echo "Setting new period of $period $reportmonth for domain name $domainname\n";
                        try
                        {
                            $currentperiod = $epp->infodomainperiod($domainname);
                            if ($currentperiod == $period)
                            {
                                echo "WARNING: Current period in EPP is already set to $period $reportmonth, downloaded report file may be wrong!\n";
                            }
                            else
                            {
                                $epp->setdomainperiod($domainname, $period);
                            }
                        }
                        catch (eppException $e)
                        {
                            echo "ERROR occurred: ".$e->getMessage()."\n";
                            $epp->disconnect();
                        }
                    }
                }
            }
            $epp->disconnect();
        }
        else
        {
            echo "ERROR: Unable to connect to SIDN EPP service";
        }
    }
    else
    {
        echo "ERROR: Unable to connect to EPP service\n";
    }


}

function analyzefile($filename)
{
    $frequencycount = array(3=>0,12=>0,1=>0);
    $toprocess = array();
    $totalindex = 0;
    echo "Analyzing file $filename\n";
    $domains = file($filename, FILE_IGNORE_NEW_LINES);
    $linenumber = 0;
    foreach ($domains as $domain)
    {
        $linenumber++;
        // Skip first 2 lines of the report
        if (($linenumber > 2) && (strlen($domain)>1))
        {
            list ($domainname, $startperiod, $frequency, $endperiod)= explode(';',$domain);
            $toprocess[$domainname]=$frequency;
            $frequencycount[$frequency]++;
        }
    }
    unset($domains);
    echo "Processed ".count($toprocess)." domain names.\n";
    foreach ($frequencycount as $period=>$count)
    {
        $namestring = ($count>1?"names":"name");
        echo "Found $count domain $namestring with ".$period." month order period.\n";
        $totalindex += $count;
    }
    if ($totalindex!=count($toprocess))
    {
        echo "WARNING: Total uniqe domain names in this report (".count($toprocess).") differs from order periods reported ($totalindex). This means that some domain names are listed more then once in this report!\n";
    }

}