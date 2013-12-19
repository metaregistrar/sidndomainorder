<?php


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
                            echo "ERROR occurred: ".$e->getMessage()." for domain name $domainname\n";
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

function setsimpleorderperiods($filename, $period, $params)
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
            foreach ($domains as $domainname)
            {
                echo "Setting new period of $period $reportmonth for domain name $domainname\n";
                try
                {
                    $epp->setdomainperiod($domainname, $period);
                }
                catch (eppException $e)
                {
                    echo "ERROR occurred: ".$e->getMessage()." for domain name $domainname\n";
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