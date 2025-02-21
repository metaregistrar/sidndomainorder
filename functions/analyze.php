<?php

class analyze {
    protected string $filename = '';

    public function __construct($filename) {
        if (!file_exists($filename)) {
            die("ERROR: File ".$filename." could not be opened. Please specify the correct file name. File names are case sensitive.\n\n");
        }
        $this->filename = $filename;
    }

    public function distill($period, $next): void {
        if ($period) {
            $period = str_replace('m', '', $period);
        }
        if ($next) {
            $next = str_replace('m', '', $next);
        }
        $lines = file($this->filename, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            // Skip the header lines of the report
            if (str_contains($line, 'View orderperiod per domain')) {
                echo $line."\n";
                continue;
            }
            if (str_contains($line, 'START ORDERPERIOD')) {
                echo $line."\n";
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
        $frequencycount = [3 => 0, 12 => 0, 1 => 0];
        $processed = [];
        $starts = [];
        $invoices = [];
        $stacked = 0;

        // Let's do the analysis
        echo "Analyzing file ".$this->filename."\n";
        $lines = file($this->filename, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            // Skip the header lines of the report
            if (str_contains($line, 'View orderperiod per domain')) {
                list(,$date) = explode(' till ',$line);
                $startyear = (int) date("Y",strtotime($date));
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
                $frequency = '12';
            }
            if (isset($processed[$domainname])) {
                echo "Double domain name alert: $domainname\n";
            } else {
                // For each and every domain name, fill an array with the order period
                if ((strlen($nextperiod) > 0) && ($nextperiod > 0)) {
                    //echo "Found a stacked order of $nextperiod months for domain name $domainname\n";
                    $nextfrequency = $nextperiod;
                    $stacked++;
                } else {
                    $nextfrequency = $frequency;
                }
                $processed[$domainname] = $frequency;
                $startmonth = date("Y-m-01", strtotime($startperiod));
                @$starts[$startmonth][$nextfrequency]++;
                $invoicemonth = date("Y-m-01", strtotime($startperiod.'+'.$nextfrequency.' months'));
                @$invoices[$invoicemonth][$frequency]++;

            }
            // If the last variable is filled, this is a stacked order, these domain names appear twice in the report.

            @$frequencycount[$frequency]++;
        }

        unset($lines);
        // This array contains all orders counted by number of periods
        echo "\nProcessed " . count($processed) . " domain names.\n";
        foreach ($frequencycount as $period => $count) {
            $namestring = ($count > 1 ? "names" : "name");
            $periodstring = $period.' month';
            if ($period == 0) {
                $periodstring = 'unknown';
            }
            echo "Found $count domain $namestring with " . $periodstring . " order period.\n";
        }
        echo "\nFound $stacked domain name" . ($stacked > 1 ? "s" : "") . " with a future order period\n";

        // This list tries to show at what dates you can expect to receive an invoice for the domain names
        echo "\n\nOverview of ordering periods and invoices\n\n";
        foreach ($invoices as $invoicemonth => $count) {
            $invoicedate = date("M Y",strtotime($invoicemonth));
            $totalmoney = 0;
            echo "In $invoicedate you will receive an invoice for: \n";
            foreach ($count as $frequency=>$amount) {
                $money = 0;
                switch($frequency) {
                    case 0:
                        break;
                    case 1:
                        $money = 0.35 * $amount;
                        break;
                    case 3:
                        $money = 0.95 * $amount;
                        break;
                    case 12:
                        $money = 3.55 * $amount;
                        break;
                    default:
                        echo "UNKNOWN FREQUENCY: $frequency\n";
                        die();
                }
                $totalmoney += $money;
                $money = number_format($money,2,'.','');
                echo "   $frequency-month domains: $amount ($money euro)\n";
            }
            $totalmoney = number_format($totalmoney,2,'.','');
            echo "Total amount in $invoicedate: $totalmoney euro\n\n";
        }
        echo "Disclaimer: Amounts for 2026 may differ: SIDN pricing for 2026 is not yet available.\n\n";
    }
}