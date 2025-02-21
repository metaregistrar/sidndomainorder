<?php

class analyze {
    protected string $filename = '';

    public function __construct($filename) {
        if (!file_exists($filename)) {
            die("File ".$filename." could not be opened. Please specify the correct file name. File names are case sensitive.");
        }
        $this->filename = $filename;
    }

    public function distill($period, $next): void {
        echo "302225;View orderperiod per domain\nDOMAINNAME;START ORDERPERIOD;FREQUENCY (MONTH);END ORDERPERIOD;NEXT FREQUENCY(MONTH)\n";
        $period = str_replace('m', '', $period);
        $next = str_replace('m', '', $next);
        $lines = file($this->filename, FILE_IGNORE_NEW_LINES);
        //$linenumber = 0;
        foreach ($lines as $line) {
            //$linenumber++;
            // Skip the header lines of the report
            if (str_contains($line, 'View orderperiod per domain')) {
                continue;
            }
            if (str_contains($line, 'START ORDERPERIOD')) {
                continue;
            }

            list (,,$frequency,,$nextperiod) = explode(';', $line);
            if ($frequency == $period) {
                if ($next) {
                    if ($next == $nextperiod) {
                        echo $line . "\n";
                    }
                } else {
                    echo $line . "\n";
                }

            }
        }
    }


    public function analyzefile(): void {
        ini_set('memory_limit', '512M');
        // Variable initializations
        $frequencycount = [0 => 0, 3 => 0, 12 => 0, 1 => 0];
        $processed = [];
        $starts = [];
        //$totalindex = 0;
        $stacked = 0;

        // Lets do the analysis
        echo "Analyzing file ".$this->filename."\n";
        $lines = file($this->filename, FILE_IGNORE_NEW_LINES);
        //$linenumber = 0;
        foreach ($lines as $line) {
            //$linenumber++;

            // Skip the header lines of the report
            if (str_contains($line, 'View orderperiod per domain')) {
                continue;
            }
            if (str_contains($line, 'START ORDERPERIOD')) {
                continue;
            }

            //DOMAINNAME;START ORDERPERIOD;FREQUENCY (MONTH);END ORDERPERIOD;NEXT FREQUENCY(MONTH)
            list ($domainname, $startperiod, $frequency,, $nextperiod) = explode(';', $line);
            if ($frequency == '') {
                // No order frequency known yet for this domain name
                // What is the order period?
                $frequency = '0';
            }
            if ($frequency == '3') {
                echo $line . "\n";
            }
            if (isset($processed[$domainname])) {
                //$doubles[]=$domainname;
                echo "Double domain name alert: $domainname\n";
            } else {
                // For each and every domain name, fill an array with the order period
                $processed[$domainname] = $frequency;
                $startmonth = date("m", strtotime($startperiod));
                @$starts[$startmonth][$frequency]++;
            }
            // If the last variable is filled, this is a stacked order, these domain names appear twice in the report.
            if ((strlen($nextperiod) > 0) && ($nextperiod > 0)) {
                //echo "Found a stacked order of $nextperiod months for domain name $domainname\n";
                $stacked++;
            }
            @$frequencycount[$frequency]++;
        }

        unset($lines);
        // This array contains all orders counted by number of periods
        echo "\nProcessed " . count($processed) . " domain names.\n";
        foreach ($frequencycount as $period => $count) {
            $namestring = ($count > 1 ? "names" : "name");
            echo "Found $count domain $namestring with " . $period . " month order period.\n";
            //$totalindex += $count;
        }
        echo "Found $stacked stacked order" . ($stacked > 1 ? "s" : "") . "\n";

        // The total number of domain names in the list plus the stacked orders must match the total number of domain names found in the report
        //$total = count($processed);
        //if ($totalindex != $total) {
            //echo "\n\nWARNING: Total uniqe domain names in this report ($total) differs from order periods reported ($totalindex).\nThis means that some domain names are listed more than once in this report.\nDouble domain names:\n";
            //if (is_array($doubles))
            // {
            //    foreach ($doubles as $double)
            //    {
            //        echo '-> '.$double."\n";
            //    }
            // }
        //}
        $startyear = 2025;
        // This list tries to show at what dates you can expect to receive an invoice for the domain names
        echo "\n\nOverview of ordering periods and invoices\n\n";
        $invoice = array();
        $invoicedate = '';
        foreach ($starts as $startmonth => $count) {
            if ($startmonth == '12') {
                $startmonth = '01';
                $startyear += 1;
            } else {
                $startmonth = sprintf("%02d", $startmonth + 1);
            }
            $invoicedate = $startyear . $startmonth . '01';
        }
        foreach ($count as $period => $counter) {
            @$invoice[$invoicedate][$period] += $counter;
        }

        ksort($invoice);
        foreach ($invoice as $date => $valuecount) {
            echo "On " . substr($date, 6, 2) . '-' . substr($date, 4, 2) . '-' . substr($date, 0, 4) . " the invoice will contain:\n";
            $grandtotal = 0;
            foreach ($valuecount as $period => $counter) {
                $price = match ($period) {
                    '1' => 0.35,
                    '12' => 3.55,
                    default => 0.96,
                };
                $grandtotal += ($counter * $price);
                $total = number_format($counter * $price, 2, '.', '');
                echo "     $counter domain names with period " . $period . "m for EUR " . number_format($price, 2, '.', '') . ": $total EUR\n";
            }
            $grandtotal = number_format($grandtotal, 2, '.', '');
            echo "     Total invoice amount: $grandtotal\n\n";
        }
    }
}