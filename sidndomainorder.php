<?php
include_once("./crypt.php");
include_once("./epp.php");

error_reporting(E_ALL ^ E_NOTICE);
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
            // Analyze the DOMAIN_ORDER_FREQUENCY report from SIDN
            checkinput($argv[2]);
            analyzefile($argv[2]);
            break;
        case 'set1month':
            // Set all domain names in the specified file to 1-month order frequency
            checkinput($argv[2]);
            if ($params = load_settings())
            {
                setorderperiods($argv[2],1, $params);
            }
            break;
        case 'set3month':
            // Set all domain names in the specified file to 3-month order frequency
            checkinput($argv[2]);
            if ($params=load_settings())
            {
                setorderperiods($argv[2],3, $params);
            }
            break;
        case 'set12month':
            // Set all domain names in the specified file to 12-month order frequency
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
                    list ($domainname, $startperiod, $frequency, $endperiod, $nextperiod)= explode(';',$domain);
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
    // Variable initializations
    $frequencycount = array(3=>0,12=>0,1=>0);
    $toprocess = array();
    $starts = array();
    $totalindex = 0;
    $stacked = 0;

    // Lets do the analysis
    echo "Analyzing file $filename\n";
    $domains = file($filename, FILE_IGNORE_NEW_LINES);
    $linenumber = 0;
    foreach ($domains as $domain)
    {
        $linenumber++;
        // Skip first 2 lines of the report, they contain header data
        if (($linenumber > 2) && (strlen($domain)>1))
        {
            list ($domainname, $startperiod, $frequency, $endperiod, $nextperiod)= explode(';',$domain);
            if (isset($toprocess[$domainname]))
            {
                $doubles[]=$domainname;
            }
            else
            {
                // For each and every domain name, fill an array with the order period
                $toprocess[$domainname]=$frequency;
                $startmonth = substr($startperiod,3,7);
                $starts[$startmonth][$frequency]++;
            }
            // If the last variable is filled, this is a stacked order, these domain names appear twice in the report.
            if (strlen($nextperiod)>0)
            {
                #echo "Found a stacked order of $nextperiod months for domain name $domainname\n";
                $stacked++;
            }
            $frequencycount[$frequency]++;
        }
    }
    unset($domains);
    // This array contains all orders counted by number of periods
    echo "\nProcessed ".count($toprocess)." domain names.\n";
    foreach ($frequencycount as $period=>$count)
    {
        $namestring = ($count>1?"names":"name");
        echo "Found $count domain $namestring with ".$period." month order period.\n";
        $totalindex += $count;
    }
    echo "Found $stacked stacked order".($stacked>1?"s":"")."\n";

    // The total number of domain names in the list plus the stacked orders must match the total number of domain names found in the report
    if ($totalindex!=count($toprocess)+$stacked)
    {
        echo "\n\nWARNING: Total uniqe domain names in this report (".count($toprocess).") differs from order periods reported ($totalindex).\nThis means that some domain names are listed more then once in this report.\nDouble domain names:\n";
        foreach ($doubles as $double)
        {
            echo '-> '.$double."\n";
        }
    }

    // This list tries to show at what dates you can expect to receive an invoice for the domain names
    echo "\n\nOverview of ordering periods and invoices\n\n";
    $invoices = array();
    foreach ($starts as $start=>$count)
    {
        $startyear = substr($start,3,4);
        if ($startyear == '2013')
        {
            $invoicedate = '01-01-2014';
        }
        else
        {
            $startmonth = substr($start,0,2);
            if ($startmonth=='12')
            {
                $startmonth = '01';
                $startyear+=1;
            }
            else
            {
                $startmonth= sprintf("%02d",$startmonth+1);
            }
            $invoicedate = '01-'.$startmonth.'-'.$startyear;
        }
        foreach ($count as $period=>$counter)
        {
            $invoice[$invoicedate][$period]+=$counter;

        }
    }
    foreach ($invoice as $date=>$valuecount)
    {
        echo "On $date the invoice will contain:\n";
        $grandtotal = 0;
        foreach ($valuecount as $period=>$counter)
        {
            $year = substr($date,6,4);
            switch ($period)
            {
                case '1':
                    if ($year>2014)
                    {
                        $price = 0.33;
                    }
                    else
                    {
                        $price = 0.30;
                    }
                    break;
                case '12':
                    $price = 3.40;
                    break;
                default:
                    $price = 0.92;
                    break;
            }
            $grandtotal += ($counter*$price);
            $total = number_format($counter*$price,2,'.','');
            echo "     $counter domain names with period ".$period."m for EUR ".number_format($price,2,'.','').": $total EUR\n";
        }
        $grandtotal = number_format($grandtotal,2,'.','');
        echo "     Total invoice amount: $grandtotal\n\n";


    }

}