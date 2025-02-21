<?php
include DIRNAME(__FILE__).'/../vendor/autoload.php';

use Metaregistrar\EPP\eppConnection;
use Metaregistrar\EPP\eppException;
use Metaregistrar\EPP\eppDomain;
use Metaregistrar\EPP\sidnEppRenewRequest;
use Metaregistrar\EPP\eppRenewResponse;
use Metaregistrar\EPP\sidnEppInfoDomainRequest;
use Metaregistrar\EPP\sidnEppInfoDomainResponse;

class epp {

    protected eppConnection $connection;
    protected $connected = false;

    public function __construct($username, $password) {
        //echo "Connecting to SIDN EPP service\n";
        try {
            $this->connection = new eppConnection();
            $this->connection->setHostname('ssl://drs.domain-registry.nl');
            $this->connection->setport(700);
            $this->connection->setUsername($username);
            $this->connection->setPassword($password);
            $this->connection->enableDnssec();
            $this->connection->useExtension('sidn-ext-epp-1.0');
            if ($this->connection->connect())  {
                if ($this->connection->login()) {
                    echo "Connection to SIDN EPP is succesful!\n";
                    $this->connected = true;
                } else {
                    echo "ERROR connecting to SIDN\n";
                    unset($this->connection);
                }
            } else {
                echo "ERROR connecting to SIDN\n";
                unset($this->connection);
            }
        } catch (eppException $e) {
            echo "ERROR connecting to SIDN: ".$e->getMessage()."\n";
        }
    }

    public function __destruct() {
        if (isset($this->connection)) {
            if ($this->connection instanceof eppConnection) {
                try {
                    if ($this->connected) {
                        $this->connected = false;
                        $this->connection->logout();
                        $this->connection->disconnect();
                    }
                } catch (eppException $e) {
                    echo "ERROR disconnecting from SIDN: ".$e->getMessage()."\n";
                }

            }
        }
    }

    public function connected() {
        return $this->connected;
    }

    public function infoorderperiod($filename): void {
        $this->checkfileexists($filename);
        $lines = file($filename, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $line) {
            list($domainname) = explode("\t",$line);
            if ($domainname == 'Domainname') {
                continue;
            }
            $period = $this->infodomainperiod($domainname);
            echo "Next invoice period for $domainname: $period months\n";
        }
    }

    public function setorderperiods($filename): void {
        $currentmonth = (int) date('m');
        $currentyear = (int) date('Y');
        echo "Setting order period to 12 months for specified domain names in file $filename.\nCurrent month is $currentmonth - $currentyear\n\n";
        $lines = file($filename, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            list($domainname,$invoiceyear,$invoicemonth,$orderperiod,$nextorderperiod,$wantedinvoiceyear,$wantedinvoicemonth) = explode("\t",$line);
            if ($domainname == 'Domainname') {
                continue;
            }
            if ($orderperiod == 1) {
                // For the monthly domains, the desired month must match the current month
                if (($wantedinvoiceyear==$currentyear) && ($wantedinvoicemonth==$currentmonth)) {
                    echo "$domainname has an invoice period of $orderperiod and must be changed to 12 months at $wantedinvoicemonth - $wantedinvoiceyear, that is now\n";
                }
            } else {
                $nextperiodmonth = $currentmonth + (int) $orderperiod;
                $nextperiodyear = $currentyear;
                if ($nextperiodmonth > 12) {
                    $nextperiodmonth = 1;
                    $nextperiodyear += 1;
                }
                if (($nextperiodyear==$wantedinvoiceyear) && ($nextperiodmonth > $wantedinvoicemonth)) {
                    echo "$domainname has an invoice period of $orderperiod months, invoice month will be $invoicemonth - $invoiceyear\n";
                    echo "$domainname order period must be set to 12 months at $wantedinvoicemonth - $wantedinvoiceyear\n";
                    if ($this->setdomainperiod($domainname, '12')) {
                        echo "$domainname order period was changed to 12 months\n";
                    } else {
                        echo "ERROR occurred setting order period for domain name $domainname\n";
                    }
                }
            }
        }
    }

    private function infodomainperiod($domainname): string|null {
        try {
            $domain = new eppDomain($domainname);
            $info = new sidnEppInfoDomainRequest($domain, 'all');
            $response = $this->connection->writeandread($info);
            if (($response instanceof sidnEppInfoDomainResponse) && ($response->Success())) {
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
            } else {
                echo $response->getResultMessage()."\n";
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