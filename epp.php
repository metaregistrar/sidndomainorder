<?php
include_once('EPP/eppConnection.php');
include_once('EPP/eppRequests/eppIncludes.php');
include_once('EPP/eppResponses/eppIncludes.php');
include_once('EPP/eppData/eppIncludes.php');
include_once('EPP/sidnEppConnection.php');

class epp
{
    private $conn;
    private $connected;
    private $loggedin;

    public function __construct($username, $password)
    {
        $useliveserver = true;
        $this->connected = false;
        $this->loggedin = false;
        $this->conn = new sidnEppConnection($username, $password, $useliveserver);
    }

    public function connect()
    {
        if ($this->conn->connect())
        {
            $this->connected = true;
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
        if ($this->loggedin)
        {
            $this->logout();
        }
        if ($this->connected)
        {
            $this->conn->disconnect();
            $this->connected = false;
        }
    }

    public function forcedisconnect()
    {
        // Empty buffers
        $buffer  = $this->conn->read();
        $this->disconnect();
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
                $this->loggedin = true;
                return true;
            }
            else
            {
                echo "Login failed with message: ".$response->getResultMessage()."\n";
                return false;
            }
        }
        catch (eppException $e)
        {
            echo "Login failed with message: ".$e->getMessage()."\n";
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
                $this->loggedin = false;
                return true;
            }
            else
            {
                echo "Logout failed with message: ".$response->getResultMessage()."\n";
                return false;
            }
        }
        catch (eppException $e)
        {
            echo "Logout failed with message: ".$e->getMessage()."\n";
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
        else
        {
            echo "InfoDomain failed with message: ".$response->getResultMessage()."\n";
            return false;
        }
    }

    public function setdomainperiod($domainname, $period)
    {
        $domain = new eppDomain($domainname);
        $renew = new sidnEppRenewRequest($domain, '1970-01-01', $period);
        if ((($response = $this->conn->writeandread($renew)) instanceof eppRenewResponse) && ($response->Success()))
        {
            /* @var $response eppRenewResponse */
            echo $response->getResultMessage()."\n";
            return true;
        }
        else
        {
            echo "RenewDomain failed with message: ".$response->getResultMessage()."\n";
            return false;
        }
    }
}