<?php
include_once('EPP/eppConnection.php');
include_once('EPP/eppRequests/eppIncludes.php');
include_once('EPP/eppResponses/eppIncludes.php');
include_once('EPP/eppData/eppIncludes.php');
include_once('EPP/sidnEppConnection.php');

class epp
{
    private $conn;

    public function __construct($username, $password)
    {
        $useliveserver = true;
        $this->conn = new sidnEppConnection($username, $password, $useliveserver);
    }

    public function connect()
    {
        if ($this->conn->connect())
        {
            if ($this->login())
            {
                return true;
            }
            $this->disconnect();
        }
        return false;
    }

    public function disconnect()
    {
        $this->logout();
        $this->conn->disconnect();
    }

    public function testconnection()
    {
        if ($this->conn->connect())
        {
            if ($this->login())
            {
                $this->logout();
                $this->conn->disconnect();
                return true;
            }
            else
            {
                $this->conn->disconnect();
            }
        }
        return false;
    }

    private function login()
    {
        try
        {
            $login = new eppLoginRequest();
            if ((($response = $this->conn->writeandread($login)) instanceof eppLoginResponse) && ($response->Success()))
            {
                return true;
            }
        }
        catch (eppException $e)
        {
            echo $e->getMessage()."\n";
            return false;
        }
    }

    private function logout()
    {
        try
        {
            $logout = new eppLogoutRequest();
            if ((($response = $this->conn->writeandread($logout)) instanceof eppLogoutResponse) && ($response->Success()))
            {
                return true;
            }
            else
            {
                echo "Logout failed with message ".$response->getResultMessage()."\n";
                return false;
            }
        }
        catch (eppException $e)
        {
            echo $e->getMessage()."\n";
            return false;
        }
    }

    public function infodomainperiod($domainname)
    {
        $domain = new eppDomain($domainname);
        $info = new eppInfoDomainRequest($domain, 'all');
        if ((($response = $this->conn->writeandread($info)) instanceof sidnEppInfoDomainResponse) && ($response->Success()))
        {
            /* @var $response sidnEppInfoDomainResponse */
            return $response->getDomainPeriod();
        }
    }

    public function setdomainperiod($domainname, $period)
    {
        $domain = new eppDomain($domainname);
        $renew = new sidnEppRenewRequest($domain, '1970-01-01', $period);
        if ((($response = $this->conn->writeandread($renew)) instanceof eppRenewResponse) && ($response->Success()))
        {
            /* @var $response eppRenewResponse */
            echo $response->getResultMessage();
            return true;
        }
    }
}