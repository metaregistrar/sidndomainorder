<?php

function infoorderperiod($filename, $params)
{
    global $epp;
    echo "Connecting to SIDN EPP service\n";
    if ($epp = new epp($params->username, $params->password))
    {
        if ($epp->connect())
        {
            $domains = file($filename, FILE_IGNORE_NEW_LINES);
            foreach ($domains as $domainname)
            {
                try
                {
                    $period = $epp->infodomainperiod($domainname);
                    echo "Next invoice period set for $domainname: $period months\n";
                }
                catch (eppException $e)
                {
                    echo "ERROR occurred for domain name $domainname: ".$e->getMessage()."\n";
                }

            }
        }
    }
}