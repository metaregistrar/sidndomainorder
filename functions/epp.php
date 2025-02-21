<?php
include DIRNAME(__FILE__).'/../vendor/autoload.php';

use Metaregistrar\EPP\eppConnection;
use Metaregistrar\EPP\eppException;
use Metaregistrar\EPP\eppDomain;
use Metaregistrar\EPP\sidnEppRenewRequest;
use Metaregistrar\EPP\eppRenewResponse;
use Metaregistrar\EPP\eppInfoDomainRequest;
use Metaregistrar\EPP\sidnEppInfoDomainResponse;

class epp {

    protected eppConnection $connection;

    public function __construct($username, $password) {
        echo "Connecting to SIDN EPP service\n";
        try {
            $this->connection = new eppConnection();
            $this->connection->setHostname('ssl://drs.domain-registry.nl');
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
            try {
                $this->connection->logout();
                $this->connection->disconnect();
            } catch (eppException $e) {
                echo "ERROR disconnecting from SIDN: ".$e->getMessage()."\n";
            }

        }
    }

    public function infoorderperiod($filename): void {
        $this->checkfileexists($filename);
        $domains = file($filename, FILE_IGNORE_NEW_LINES);
        foreach ($domains as $domainname) {
            $period = $this->infodomainperiod($domainname);
            echo "Next invoice period set for $domainname: $period months\n";
        }
    }

    public function setorderperiods($filename, $period): void {
        $this->checkfileexists($filename);
        $reportmonth = ($period == 1 ? "month" : "months");
        echo "Setting order period to $period $reportmonth for al domain names in file $filename.\n";
        $linenumber = 0;
        $domains = file($filename, FILE_IGNORE_NEW_LINES);
        foreach ($domains as $domain) {
            $linenumber++;
            // Skip first 2 lines of the report
            if (($linenumber > 2) && (strlen($domain) > 1)) {
                list ($domainname,, $frequency,,) = explode(';', $domain);
                if ($frequency == $period) {
                    echo "Domain name $domainname already has a period of $period $reportmonth, skipped this domain name\n";
                } else {
                    echo "Setting new period of $period $reportmonth for domain name $domainname\n";
                    $currentperiod = $this->infodomainperiod($domainname);
                    if ($currentperiod == $period) {
                        echo "WARNING: Current period in EPP is already set to $period $reportmonth, downloaded report file may be wrong!\n";
                    } else {
                        $this->setdomainperiod($domainname, $period);
                    }

                }
            }
        }
    }


    public function setsimpleorderperiods($filename, $period): void {
        $this->checkfileexists($filename);
        $reportmonth = ($period==1?"month":"months");
        echo "Setting order period to $period $reportmonth for al domain names in file $filename.\n";
        //$linenumber = 0;
        $domains = file($filename, FILE_IGNORE_NEW_LINES);
        foreach ($domains as $domainname) {
            echo "Setting new period of $period $reportmonth for domain name $domainname\n";
            if (!$this->setdomainperiod($domainname, $period)) {
                echo "ERROR occurred setting order period for domain name $domainname\n";
            }
        }
    }

    private function infodomainperiod($domainname): string|null {
        try {
            $domain = new eppDomain($domainname);
            $info = new eppInfoDomainRequest($domain, 'all');
            if ((($response = $this->connection->writeandread($info)) instanceof sidnEppInfoDomainResponse) && ($response->Success())) {
                /* @var $response sidnEppInfoDomainResponse */
                return $response->getDomainPeriod();
            } else {
                echo "InfoDomain failed with message: ".$response->getResultMessage()."\n";
            }
        } catch (eppException $e) {
            echo "InfoDomain failed with message: ".$e->getMessage()."\n";
        }
        return null;
    }

    private function setdomainperiod($domainname, $period): bool {
        try {
            $domain = new eppDomain($domainname);
            $renew = new sidnEppRenewRequest($domain, '1970-01-01', $period);
            if ((($response = $this->connection->writeandread($renew)) instanceof eppRenewResponse) && ($response->Success())) {
                /* @var $response eppRenewResponse */
                echo $response->getResultMessage()."\n";
                return true;
            }
        } catch (eppException $e) {
            echo "RenewDomain failed with message: ".$e->getMessage()."\n";
        }
        return false;
    }

    private function checkfileexists($filename): void {
        if (!is_file($filename)) {
            die("File $filename cannot be openend\n");
        }
    }
}