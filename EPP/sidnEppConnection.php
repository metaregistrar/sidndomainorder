<?php
#
# Load the SIDN specific additions
#
include_once(dirname(__FILE__)."/sidnEppInfoDomainResponse.php");
include_once(dirname(__FILE__)."/sidnEppRenewRequest.php");

class sidnEppConnection extends eppConnection
{

    public function __construct($username,$password)
    {
        parent::__construct(false);
        parent::setHostname('ssl://testdrs.domain-registry.nl');
        parent::setPort(700);
        parent::setUsername($username);
        parent::setPassword($password);
        parent::setTimeout(5);
        parent::setLanguage('en');
        parent::setVersion('1.0');
        parent::addExtension('sidn-epp-ext','http://rxsd.domain-registry.nl/sidn-ext-epp-1.0');
        parent::addCommandResponse('eppInfoDomainRequest', 'sidnEppInfoDomainResponse');
        parent::addCommandResponse('sidnEppRenewRequest', 'eppRenewResponse');
    }

}
