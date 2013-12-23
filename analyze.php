<?php

function distill($filename,$period)
{
    echo "Distilling all $period orders from file $filename\n";
    $period = str_replace('m','',$period);
    $domains = file($filename, FILE_IGNORE_NEW_LINES);
    $linenumber = 0;
    foreach ($domains as $domain)
    {
        $linenumber++;
        // Skip first 2 lines of the report, they contain header data
        if (($linenumber > 2) && (strlen($domain)>1))
        {
            list ($domainname, $startperiod, $frequency, $endperiod, $nextperiod)= explode(';',$domain);
            if ($frequency==$period)
            {
                echo $domain."\n";
            }
        }
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
                echo "Double domain name alert: $domainname\n";
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
                //echo "Found a stacked order of $nextperiod months for domain name $domainname\n";
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
    $total = count($toprocess);
    if ($totalindex!=$total)
    {
        echo "\n\nWARNING: Total uniqe domain names in this report ($total) differs from order periods reported ($totalindex).\nThis means that some domain names are listed more than once in this report.\nDouble domain names:\n";
        if (is_array($doubles))
        {
            foreach ($doubles as $double)
            {
                echo '-> '.$double."\n";
            }
        }
    }

    // This list tries to show at what dates you can expect to receive an invoice for the domain names
    echo "\n\nOverview of ordering periods and invoices\n\n";
    $invoice = array();
    foreach ($starts as $start=>$count)
    {
        $startyear = substr($start,3,4);
        if ($startyear == '2013')
        {
            $invoicedate = '01-01-2014';
            $invoicedate = '20140101';
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
            $invoicedate = $startyear.$startmonth.'01';
        }
        foreach ($count as $period=>$counter)
        {
            $invoice[$invoicedate][$period]+=$counter;
        }
    }
    ksort($invoice);
    foreach ($invoice as $date=>$valuecount)
    {
        echo "On ".substr($date,6,2).'-'.substr($date,4,2).'-'.substr($date,0,4)." the invoice will contain:\n";
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