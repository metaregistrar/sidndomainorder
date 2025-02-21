<?php

class analyze {
    protected string $filename = '';

    public function __construct($filename) {
        if (!file_exists($filename)) {
            die("ERROR: File ".$filename." could not be opened. Please specify the correct file name. File names are case sensitive.\n\n");
        }
        $this->filename = $filename;
    }

    public function distill(): void {
        $lines = file($this->filename, FILE_IGNORE_NEW_LINES);
        echo "Domainname\tInvoice year\tInvoice month\tRenewal period(m)\tNext renewal period(m)\tDesired year\tDesired month\n";
        foreach ($lines as $line) {
            // Skip the header lines of the report
            if (str_contains($line, 'View orderperiod per domain')) {
                continue;
            }
            if (str_contains($line, 'START ORDERPERIOD')) {
                continue;
            }
            list ($domainname,$startperiod,$frequency,,$nextfrequency) = explode(';', $line);
            if (($frequency=='1') || ($frequency=='3')) {
                $invoicemonth = date("m",strtotime($startperiod.' +'.$frequency.' months'));
                $invoiceyear = date("Y",strtotime($startperiod.' +'.$frequency.' months'));
                if ($nextfrequency == '') {
                    $nextfrequency = $frequency;
                }
                echo "$domainname\t$invoiceyear\t$invoicemonth\t$frequency\t$nextfrequency\n";
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
            @$frequencycount[$frequency]++;
        }

        unset($lines);
        echo "===========================\n";
        // This array contains all orders counted by number of periods
        echo "\nProcessed " . count($processed) . " domain names.\n\n";
        foreach ($frequencycount as $period => $count) {
            $namestring = ($count > 1 ? "names" : "name");
            $periodstring = $period.' month';
            if ($period == 0) {
                $periodstring = 'unknown';
            }
            echo "Found $count domain $namestring with " . $periodstring . " order period.\n";
        }
        echo "\nFound $stacked domain name" . ($stacked > 1 ? "s" : "") . " where the future order period differs from the current order period\n";
        echo "\n===========================\n";
        // This list tries to show at what dates you can expect to receive an invoice for the domain names
        echo "\nOverview of ordering periods and invoices\n\n";
        $totaltotal = 0;
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
            $totaltotal += $totalmoney;
            $totalmoney = number_format($totalmoney,2,'.','');
            echo "Total cost in $invoicedate: $totalmoney euro\n\n";
        }
        $average = $totaltotal / count($processed);
        $average = number_format($average,2,'.','');
        $totaltotal = number_format($totaltotal,2,'.','');
        echo "Grand total $totaltotal euro per year for ".count($processed)." domain names, averaging $average euro per domain name\n\n";
        echo "Disclaimer: Amounts for 2026 may differ: SIDN pricing for 2026 is not yet available.\n";
        echo "\n===========================\n";
    }
}