<?php
include DIRNAME(__FILE__).'/../vendor/autoload.php';

use Metaregistrar\EPP\eppConnection;
use Metaregistrar\EPP\eppException;

class epp {

    protected eppConnection $connection;

    public function __construct($username, $password) {
        echo "Connecting to SIDN EPP service\n";
        try {
            $this->connection = new eppConnection();
            $this->connection->setHostname('');
            $this->connection->setport(700);
            $this->connection->setUsername($username);
            $this->connection->setPassword($password);
            $this->connection->enableDnssec();
            $this->connection->useExtension('sidn-ext-epp-1.0');
            if ($this->connection->connect())  {
                if (!$this->connection->login()) {
                    unset($this->connection);
                }
            } else {
                unset($this->connection);
            }
        } catch (eppException $e) {
            echo "ERROR connecting to SIDN: ".$e->getMessage()."\n";
        }
    }

    public function __destruct() {
        if ($this->connection instanceof eppConnection) {
            $this->connection->logout();
            $this->connection->disconnect();
        }
    }

    public function infoorderperiod($filename) {
        $this->checkfileexists($filename);
        $domains = file($filename, FILE_IGNORE_NEW_LINES);
        foreach ($domains as $domainname) {
            try {
                $period = $this->infodomainperiod($domainname);
                echo "Next invoice period set for $domainname: $period months\n";
            }
            catch (eppException $e) {
                echo "ERROR occurred for domain name $domainname: ".$e->getMessage()."\n";
            }
        }
    }

    public function setorderperiods($filename, $period) {
        $this->checkfileexists($filename);
        $reportmonth = ($period == 1 ? "month" : "months");
        echo "Setting order period to $period $reportmonth for al domain names in file $filename.\n";
        $linenumber = 0;
        $domains = file($filename, FILE_IGNORE_NEW_LINES);
        foreach ($domains as $domain) {
            $linenumber++;
            // Skip first 2 lines of the report
            if (($linenumber > 2) && (strlen($domain) > 1)) {
                list ($domainname, $startperiod, $frequency, $endperiod, $nextperiod) = explode(';', $domain);
                if ($frequency == $period) {
                    echo "Domain name $domainname already has a period of $period $reportmonth, skipped this domain name\n";
                } else {
                    echo "Setting new period of $period $reportmonth for domain name $domainname\n";
                    try {
                        $currentperiod = $epp->infodomainperiod($domainname);
                        if ($currentperiod == $period) {
                            echo "WARNING: Current period in EPP is already set to $period $reportmonth, downloaded report file may be wrong!\n";
                        } else {
                            $epp->setdomainperiod($domainname, $period);
                        }
                    } catch (eppException $e) {
                        echo "ERROR occurred for domain name $domainname: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        $epp->disconnect();
    }




    public function setsimpleorderperiods($filename, $period) {
        $this->checkfileexists($filename);
        $reportmonth = ($period==1?"month":"months");
        echo "Setting order period to $period $reportmonth for al domain names in file $filename.\n";
        $linenumber = 0;
        $domains = file($filename, FILE_IGNORE_NEW_LINES);
        foreach ($domains as $domainname) {
            echo "Setting new period of $period $reportmonth for domain name $domainname\n";
            try {
                $this->setdomainperiod($domainname, $period);
            }
            catch (eppException $e) {
                echo "ERROR occurred for domain name $domainname: ".$e->getMessage()." for domain name $domainname\n";
            }
        }
    }

    private function checkfileexists($filename) {
        if (!is_file($filename)) {
            die("File $filename cannot be openend\n");
        }
    }
}