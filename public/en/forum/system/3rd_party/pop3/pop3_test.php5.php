<?php
// vi: expandtab sw=4 ts=4 sts=4 nowrap nu:
/**
 *
 * @author: j0inty.sL
 * @email: bestmischmaker@web.de
 */
error_reporting(E_ALL);
$strRootPath = dirname(__FILE__) . DIRECTORY_SEPARATOR;
require_once( $strRootPath ."pop3.class.php5.inc");
echo $strRootPath ."pop3.class.php5.inc";
// Socket Options
/**
 * Remember that the encryption support doesn't work at time for the socket extension
 * This will I implement later.
 * 
 */
$bUseSockets = FALSE;
$bUseTLS = TRUE;
$bIPv6 = FALSE;
$arrConnectionTimeout = array( "sec" => 10,
                               "usec" => 500 );
// POP3 Options
$strProtocol= "tls";
$strHost = "pop.gmail.com";
$intPort = 995;
$strUser = "test21";
$strPass = "test32";
$bAPopAutoDetect = TRUE;
$bHideUsernameAtLog = FALSE;

// Logging Options
$strLogFile = "php://stdout";//$strRootPath. "pop3.log";

// EMail store Sptions
$strPathToDir = $strRootPath."mails" .DIRECTORY_SEPARATOR;
$strFileEndings = ".eml";


try
{
    // Instance the POP3 object
    $objPOP3 = new POP3( $strLogFile, $bAPopAutoDetect, $bHideUsernameAtLog, $strProtocol, $bUseSockets );
    
    // Connect to the POP3 server
    $objPOP3->connect($strHost,$intPort,$arrConnectionTimeout,$bIPv6);
    
    // Logging in
    $objPOP3->login($strUser, $strPass);
    
    // Get the office status
    $arrOfficeStatus = $objPOP3->getOfficeStatus();
	    
    /**
     * This for loop store the messages under their message number on the server
     * and mark the message as delete on the server.
     */
    for($intMsgNum = 1; $intMsgNum <= $arrOfficeStatus["count"]; $intMsgNum++ )
    {
        $objPOP3->saveToFileFromServer($intMsgNum, $strPathToDir, $strFileEndings);
//        $objPOP3->deleteMsg($intMsgNum);
    }

    // Send the quit command and all as delete marked message will remove from the server.
    // IMPORTANT: 
    // If you deleted many mails it could be that the +OK response will take some time.
    $objPOP3->quit();

    // Disconnect from the server
    // !!! CAUTION !!!
    // - this function does not send the QUIT command to the server
    //   so all as delete marked message will NOT delete
    //   To delete the mails from the server you have to send the quit command themself before disconnecting from the server
    $objPOP3->disconnect();
}
catch( POP3_Exception $e )
{
    die($e);
}

// Your next code

?> 
