<?php

/*
 * This object contains all the logic to create a standard EPP command
 */


class eppResponse extends DomDocument
{
    const RESULT_SUCCESS = '1000';
    const RESULT_SUCCESS_ACTION_PENDING = '1001';
    const RESULT_NO_MESSAGES = '1300';
    const RESULT_MESSAGE_ACK = '1301';
    const RESULT_LOGOFF_SUCCESS = '1500';
    #
    # ERROR RESPONSES
    #
    const RESULT_UNKNOWN_COMMAND = '2000';
    const RESULT_SYNTAX_ERROR = '2001';
    const RESULT_USE_ERROR = '2002';
    const RESULT_PARAMETER_MISSING = '2003';
    const RESULT_PARAMETER_RANGE_ERROR = '2004';
    const RESULT_PARAMETER_ERROR = '2005';
    const RESULT_INVALID_PROTOCOL_VERSION = '2100';
    const RESULT_INVALID_COMMAND = '2101';
    const RESULT_INVALID_OPTION = '2102';
    const RESULT_INVALID_EXTENSION = '2103';
    const RESULT_BILLING_FAILURE = '2104';
    const RESULT_NO_RENEW_POSSIBLE = '2105';
    const RESULT_NO_TRANSFER_POSSIBLE = '2106';
    const RESULT_AUTHENTICATION_ERROR = '2200';
    const RESULT_AUTHORIZATION_ERROR = '2201';
    const RESULT_INVALID_AUTHINFO = '2202';
    const RESULT_TRANSFER_PENDING = '2300';
    const RESULT_TRANSFER_NOT_PENDING = '2301';
    const RESULT_ALREADY_EXISTS = '2302';
    const RESULT_NOT_EXISTS = '2303';
    const RESULT_OBJECT_STATUS_WRONG = '2304';
    const RESULT_ASSOCIATION_EXISTS = '2305';
    const RESULT_POLICY_ERROR = '2306';
    const RESULT_UNIMPLEMENTED_SERVICE = '2307';
    const RESULT_POLICY_VIOLATION = '2308';
    const RESULT_COMMAND_FAILED = '2400';
    const RESULT_COMMAND_FAILED_CONNECTION_CLOSE = '2500';
    const RESULT_AUTHENTICATION_ERROR_CONNECTION_CLOSE = '2501';
    const RESULT_SESSION_LIMIT_EXCEEDED_CONNECTION_CLOSE = '2502';

    /**
     *
     * @var string Category of problem
     */
    private $problemtype;
    /**
     *
     * @var array of object uri
     */
    public $objuri;
    /**
     *
     * @var array of extended uri
     */
    public $exturi;
    /**
     * @var array of xpath uri
     */
    public $xpathuri;
    /*
     * @var array of supported languages
     */
    public $language;
    /*
     * @var array of supported versions
     */
    public $version;
    
    /**
     *
     * @var string $publicnamespace
     */
    public $defaultnamespace;

    public function __construct()
    {
        parent::__construct();
        $this->formatOutput=true;
        #$this->validateOnParse = true;
    }

    public function __destruct()
    {
    }

    public function saveXML(DOMNode $node = NULL, $options = NULL)
    {
        return str_replace("\t",'  ',parent::saveXML($node, LIBXML_NOEMPTYTAG));
    }

    public function setParameters($language,$version,$objuri,$exturi,$xpathuri)
    {
        $this->language = $language;
        $this->version = $version;
        $this->objuri = $objuri;
        $this->exturi = $exturi;
        $this->xpathuri = $xpathuri;
    }

    /**
     * @return boolean
     */
    public function Success()
    {
        $resultcode = $this->getResultCode();
        $success = ($resultcode{0}=='1');
        if (!$success)
        {
            switch($resultcode{1})
            {
                case '0':
                    $this->setProblemtype('syntax');
                    break;
                case '1':
                    $this->setProblemtype('implementation-specific');
                    break;
                case '2':
                    $this->setProblemtype('security');
                    break;
                case '3':
                    $this->setProblemtype('data management');
                    break;
                case '4':
                    $this->setProblemtype('server system');
                    break;
                case '5':
                    $this->setProblemtype('connection management');
                    break;
            }
            $resultmessage = $this->getResultMessage();

            $errorstring = "Error $resultcode: $resultmessage";
            $id = null;
            $value = $this->getResultValue();
            if ($value)
            {
                $id = 'value:'.$value;
            }            
            $resultcontactid = $this->getResultContactId();
            if ($resultcontactid)
            {
                $id = 'contactid:'.$resultcontactid;
            }
           $resulthostname = $this->getResultHostName();
            if ($resulthostname)
            {
                $id = 'hostname:'.$resulthostname;
            }
           $resultdomainname = $this->getResultDomainName();
            if ($resultdomainname)
            {
                $id = 'domainname:'.$resultdomainname;
            }
            $resultstatus = $this->getResultHostStatus();
            if ($resultstatus)
            {
                $id = 'status:'.$resultstatus;
            }
            $resultaddr = $this->getResultHostAddr();
            if ($resultaddr)
            {
                $id = 'hostaddr:'.$resultaddr;
            }
            if ($id)
            {
                $errorstring .= '; '.$id;
            }
            $resultreason = $this->getResultReason();
            if (strlen($resultreason))
            {
                $errorstring .= ' ('.$resultreason.')';
            }
            throw new eppException($errorstring,$resultcode,null,$resultreason,$id);
        }
        else
        {
            return true;
        }
    }
    /**
     *
     * @param string $problemtype
     */
    public function setProblemtype($problemtype)
    {
        $this->problemtype = $problemtype;
    }
    /**
     *
     * @return string
     */
    public function getProblemtype()
    {
        return $this->problemtype;
    }

    /**
     *
     * @return string
     */
    public function getResultCode()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:result/@code');
        if (is_object($result) && ($result->length > 0))
        {
            return trim($result->item(0)->nodeValue);
        }
        else
        {
            return '1000';
        }
    }

    /**
     *
     * @return string
     */
    public function getResultMessage()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:msg');
        if (is_object($result) && ($result->length > 0))
        {
            return trim($result->item(0)->nodeValue);
        }
        else
        {
            return null;
        }
    }

    /**
     *
     * @return string
     */
    public function getResultReason()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:extValue/epp:reason');
        if (is_object($result) && ($result->length > 0))
        {
            return trim($result->item(0)->nodeValue);
        }
        else
        {
            return null;
        }
    }

    public function getResultValue()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:extValue/epp:value');
        if (is_object($result) && ($result->length > 0))
        {
            return trim($result->item(0)->nodeValue);
        }
        else
        {
            $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:value');
            if (is_object($result) && ($result->length > 0))
            {
                return trim($result->item(0)->nodeValue);
            }
            else
            {
                return null;
            }
        }
    }
    
    public function getResultContactId()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:extValue/epp:value/contact:id');
        if (is_object($result) && ($result->length > 0))
        {
            return trim($result->item(0)->nodeValue);
        }
        else
        {
            $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:value/contact:id');
            if (is_object($result) && ($result->length > 0))
            {
                return trim($result->item(0)->nodeValue);
            }
            else
            {
                return null;
            }
        }        
    }

    public function getResultDomainName()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:extValue/epp:value/domain:name');
        if (is_object($result) && ($result->length > 0))
        {
            return trim($result->item(0)->nodeValue);
        }
        else
        {
            $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:value/domain:name');
            if (is_object($result) && ($result->length > 0))
            {
                return trim($result->item(0)->nodeValue);
            }
            else
            {
                return null;
            }
        }        
    }    
    
    public function getResultHostName()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:extValue/epp:value/host:name');
        if (is_object($result) && ($result->length > 0))
        {
            return trim($result->item(0)->nodeValue);
        }
        else
        {
        $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:value/host:name');
            if (is_object($result) && ($result->length > 0))
            {
                return trim($result->item(0)->nodeValue);
            }
            else
            {
                return null;
            }
        }        
    }
    
    
    public function getResultHostAddr()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:extValue/epp:value/host:addr');
        if (is_object($result) && ($result->length > 0))
        {
            return trim($result->item(0)->nodeValue);
        }
        else
        {
            $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:extValue/epp:value/host:addr');
            if (is_object($result) && ($result->length > 0))
            {
                return trim($result->item(0)->nodeValue);
            }
            else
            {
                return null;
            }
        }        
    }
    
    public function getResultHostStatus()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:extValue/epp:value/host:status/@s');
        if (is_object($result) && ($result->length > 0))
        {
            return trim($result->item(0)->nodeValue);
        }
        else
        {
            $result = $xpath->query('/epp:epp/epp:response/epp:result/epp:extValue/epp:value/host:status/@s');
            if (is_object($result) && ($result->length > 0))
            {
                return trim($result->item(0)->nodeValue);
            }
            else
            {
                return null;
            }
        }        
    }
    /**
     *
     * @return string
     */
    public function getServerTransactionId()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:trID/epp:svTRID');
        if (is_object($result) && ($result->length > 0))
        {
            return $result->item(0)->nodeValue;
        }
        else
        {
        $result = $xpath->query('/epp:epp/epp:response/epp:trID/epp:svTRID');
            if (is_object($result) && ($result->length > 0))
            {
                return $result->item(0)->nodeValue;
            }
            else
            {
                return null;
            }
        }
    }

    /**
     *
     * @return string
     */
    public function getClientTransactionId()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:trID/epp:clTRID');
        if (is_object($result) && ($result->length > 0))
        {
            return $result->item(0)->nodeValue;
        }
        else
        {
            return null;
        }
    }    
    
    public function setXpath($xpathuri)
    {
        if (!$this->xpathuri)
        {
            $this->xpathuri = $xpathuri;
        }
        else
        {
            $this->xpathuri = array_merge($this->xpathuri, $xpathuri);
        }
    }

    /**
     *
     * @param domDocument $document
     * @return DOMXpath
     */
    public function xPath()
    {
        $xpath = new DOMXpath($this);
        $this->publicnamespace = $this->documentElement->lookupNamespaceURI(NULL);
        $xpath->registerNamespace('epp', $this->publicnamespace);
        if (is_array($this->xpathuri))
        {
            foreach($this->xpathuri as $uri=>$namespace)
            {
                //echo "RegisterNamespace $namespace $uri\n";
                $xpath->registerNamespace($namespace,$uri);
            }
        }
        if (is_array($this->exturi))
        {
            foreach($this->exturi as $uri=>$namespace)
            {
                //echo "RegisterNamespace $namespace $uri\n";
                $xpath->registerNamespace($namespace,$uri);
            }
        }
        return $xpath;
    }
}
