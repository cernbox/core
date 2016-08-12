<?php

# File used by SSO

try
{

        require_once 'lib/base.php';

        OC::handleRequest();

} 
catch(\OC\ServiceUnavailableException $ex) 
{
        \OCP\Util::logException('index', $ex);

        //show the user a detailed error page
        OC_Response::setStatus(OC_Response::STATUS_SERVICE_UNAVAILABLE);
        OC_Template::printExceptionErrorPage($ex);
} 
catch (\OC\HintException $ex) 
{
        OC_Response::setStatus(OC_Response::STATUS_SERVICE_UNAVAILABLE);
        OC_Template::printErrorPage($ex->getMessage(), $ex->getHint());
} 
catch (Exception $ex) 
{
        \OCP\Util::logException('index', $ex);

        //show the user a detailed error page
        OC_Response::setStatus(OC_Response::STATUS_INTERNAL_SERVER_ERROR);
        OC_Template::printExceptionErrorPage($ex);
}

?>
