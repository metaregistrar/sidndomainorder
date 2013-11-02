<?php
include_once(dirname(__FILE__).'/../eppRequest.php');
/*
 * This object contains all the logic to create an EPP hello command
 */

class eppRenewRequest extends eppRequest
{
    function __construct($domain, $expdate=null)
    {
        parent::__construct();

        #
        # Sanity checks
        #
        if (!($domain instanceof eppDomain))
        {
            throw new eppException('eppRenewRequest needs valid eppDomain object as parameter');
        }
        $this->setDomain($domain,$expdate);
        $this->addSessionId();
    }

    function __destruct()
    {
        parent::__destruct();
    }

    public function setDomain(eppDomain $domain, $expdate=null)
    {
        #
        # Create command structure
        #
        $this->command = $this->createElement('command');
        #
        # Object create structure
        #
        $renew = $this->createElement('renew');
        $this->domainobject = $this->createElement('domain:renew');
        $this->domainobject->appendChild($this->createElement('domain:name',$domain->getDomainname()));
        if ($expdate)
        {
            $this->domainobject->appendChild($this->createElement('domain:curExpDate',$expdate));
        }
        if ($domain->getPeriod()>0)
        {
            $domainperiod = $this->createElement('domain:period',$domain->getPeriod());
            $domainperiod->setAttribute('unit',$domain->getPeriodUnit());
            $this->domainobject->appendChild($domainperiod);
        }
        $renew->appendChild($this->domainobject);
        $this->command->appendChild($renew);
        $this->epp->appendChild($this->command);
    }
}