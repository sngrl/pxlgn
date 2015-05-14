--------------------------------------------------------------------------------
--------------------------pop3.class.inc---Version: 1.14------------------------
----------------------------------README----------------------------------------
--------------------------------------------------------------------------------
----------------------Created by Hemp "Jointy" Cluster -------------------------
-------------------------Email: BestMischMaker@web.de---------------------------
--------------------------------------------------------------------------------
----------------------------Readme Version: 0.11--------------------------------

                               -- Index --

 Part: I    - Description in german
 Part: II   - Documentation in german
 Part: III  - Documentation in english (written by Urb LeJeune <urb@e-government.com>)
 
 
                              -- Part: I --


Hallo !

Um nicht lange rum zuschwaffeln, werd ich mal gleich auf den Punkt kommen.
Also es handelt sich hier um eine Klasse für PHP4 ab Version 4.3.1.

Die "pop3.class.inc" ist geschrieben worden um mit POP3 Servern kommunizieren
zu können. Dabei wird der RFC1939 Standard voll unterstützt.


                              -- Part: II --

Fehlerverhalten
---------------

Sollte ein Fehler innerhalb der Klasse auftretten so wird immer die öffentliche Error
Variable mit der Fehlermeldung gesetzt...aber die Variable ist im normal Fall nicht
($pop3->error = FALSE)

z.B.

<?php
if(!$pop3->connect($server,$port,$conn_timeout,$sock_timeout){
   echo $pop3->error;
   return;
}
?>

Erwarten sie Werte koennen sie wie folgt auf Fehlerprüfen.
z.B.
<?php
if(!$message = $pop3->get_mail($msgnum)){
    echo $pop3->error;
    return;
}
?>

Zusatz:
!!! Sollte ein Fehler in einer Funktion auftretten die die Verbindung zum Server schliesst,
!!! heisst das nicht das das "QUIT" Commando gesendet wird, es wird nur der Socket geschlossen...
!!! somit bleiben alle als gelöscht markierten mails erhalten...


Constructor
---------------

// Hier wird nur festgelegt ob die POP Session geloggt werden soll, wenn ja
// auch wohin !!!
// Und seit neustem, ob die APOP Autodetection aktiv sein soll oder nicht...
// ( Ist nur dazu da wenn man sich nicht sicher ist ansonsten sollte APOP
// immer aktiv sein !! )
   
new POP3($log = FALSE, $log_file = "", $apop_detect = FALSE)

  - $log wenn log aktiv sein soll $log = TRUE setzen.
  - $log_file muss einen Dateinamen enthalten ( folgenes geht auch )
  ( win32 z.B. ".//log//pop3.class.log" wobei der Ordner "log" existieren muss !!! )
  - $apop_detect sollte die var TRUE sein wird geprüft ob ein banner übergeben wurde
    der für apop notwendig ist, wenn ja wird der "APOP" Command zum login verwendet.



Funktionen
---------------
--connect($server,$port = "110",$conn_timeout = "25",$sock_timeout = "15,000")

// Baut die Verbindung zum POP3 Server auf, und überprüft ob die erste Nachricht
// ein "+OK" ist...wenn nicht wird die Fehlervariable gesetzt und FALSE zurückgeben
// sowie die Verbindung wieder geschlossen.

  - $server kann der DNS Name oder auch die IP Addresse sein !!!
  - $port Welcher Port wird verwendet (default = "110")
  - $conn_timeout Der Timeout in Sekunden für den Aufbau der Verbindung !!!
  - $sock_timeout Der Socket Timeout für spätere Verbindungshandling !!!
    (muss immer im folgenen Format angegeben werden !!
     Z.B.: 10 Sekunden (,) 000 Milisekunden = 10 Sec...
     $sock_timeout = "15,000"



--login($user,$pass,$apop)

// Logt sich über die ausgewählte Authorisierungsart beim Server ein.
// Tritt ein Fehler auf gibt die Funktion "FALSE" zurück und die Verbindung
// wird geschlossen.

 - $user muss gesetzt werden.
 - $pass muss gesetzt werden.
 - $apop (default = "0") hier setzt man manuell APOP(TRUE("1") oder FALSE("0"))
 


--$msg_array = get_office_status()
// Diese Funktion holt sich den kompletten Status des Postfaches...
// Als Return erhalten sie ein mehrdimensionalles Array...wie dieses mal als Beispiel.
// In Fehlerfall "return = FALSE" und Verbindung wird geschlossen.

Array
(
    [count_mails] => 3
    [octets] => 3257477
    [1] => Array
        (
            [size] => 832
            [uid] => 617999468
        )

    [2] => Array
        (
            [size] => 3253781
            [uid] => 617999616
        )

    [3] => Array
        (
            [size] => 2864
            [uid] => 617999782
        )


)

  $msg_array["count_mails"] die Anzahl der sich im Postfach befindenen Mails
  $msg_array["octets"] die Grösse aller Mails in Octets (Bytes) !!
  $msg_array["msgnum"] Nummer der Nachricht im Postfach !!!
  $msg_array["msgnum"]["uid"] unique-id (Eindeutige ID auf den POP3 Server)
  $msg_array["msgnum"]["size"] Grösse der Nachricht in Octets !!



--$message = get_mail($msgnum, $qmailer = FALSE)
// Holt die Nachricht vom Server ab !!!
// Jede vom Server übertragene Zeile wird in einen extra Key gelegt...
// das Array ist immer nummerisch !!!
// Message und Header sind eindeutig durch (<header></header> und <message></message>)
// getrennt.
// PS: Im Fehlerfall wird der Socket nicht geschlossen !!!

  $msgnum Nachrichtennummer die abgeholt werden soll.
  $qmailer Sollte sie mit einem QMail Server aggieren, dann hier bitte TRUE
  setzten
  


--$header = get_top($msgnum,$lines="0")
// Diese Funktion kann benutzt werden um nur die Header Info's einer E-Mail
// zu bekommen. Durch die Angabe von $lines werden weitere body lines mit ausgegeben.
// PS: Im Fehlerfall wird der Socket nicht geschlossen !!!

  $msgnum Nummer der Nachricht im Postfach !!!
  $lines wieviele zeilen sollen von body mit ausgegeben werden.
  (default = "0")

  z.B
  $header = get_top($msgnum);

  $header_w_lines = get_top($msgnum,"5");
  


--$uid = uidl($msgnum)
// Holt sich die Unique-ID (Eindeutige-ID) für die angegeben Nachricht im Postfach !!!
// Ist $msgnum = "0" oder $uid_list = uidl(); dann wird die komplette uid liste für alle
// sich im Postfach befindenen Mails geholt
// PS: Im Fehlerfall wird der Socket nicht geschlossen !!!

   $msgnum Nummer der Nachricht (default = "0")
   
   
   
--noop()
// Sendet ein "NOOP" Command...prüft ob server noch lebt !!!
// PS: Im Fehlerfall wird der Socket nicht geschlossen !!!


--delete_mail($msgnum)
// Markiert die angegeben Nachricht als gelöscht.
// Die als gelöscht markierten Nachrichten werden erst nachdem "QUIT" Command gelöscht !!!
// PS: Im Fehlerfall wird der Socket nicht geschlossen !!!

   $msgnum Nummer der Nachricht im Postfach !
   

--reset()
// Sendet das "RSET" Command...alle als gelöscht markierten Nachrichten werden wieder
// deselectiert.
// PS: Im Fehlerfall wird der Socket nicht geschlossen !!!


--close()
// Sendet das "QUIT" Command zum Server !!!
// Alle als gelöscht markierten Nachrichten werden nun endgültig gelöscht und
// die Verbindung geschlossen...


--save2file($msgnum,$filename)
// Schreibt die Email in die angegebene Datei, die Nachricht muss als Numerisches
// Array übergeben werden.
// Diese Funktion ist passent zur get_mail() geschrieben worden.

   $msgnum Nachrichtennummer im Postfach !!
   $filename Dateiname wo die Mail abgespeichert werden soll.
   ( es geht auch )
   ( win32  "//mails//filename"  wobei der Ordner "mails" existieren muss. )
   



-- save2mysql($message, $mysql_socket, $dir_table = "inbox", $msg_table = "messages", $read = "0")
// Diese Funktion ist passent zur get_mail() geschrieben worden.
// Speichert die Message folgener Maßen in ein MySQL Datenbank !!!
//
// ?? Was muss ich tun ???
// - MySQL Verbindung öffenen und die DB selektieren.
//
// Die beiden notwendigen Tabellen werden, wenn nicht schon vorhanden, von der Funktion selbst erstellt !!!
// Die beiden Tabellen haben zu einem die Funktion nur die Wichtigen Header Daten zuspeichern
// sowie die komplette Nachricht.

    $dir_table - Tabelle wo die Headerdaten gespeichert werden !!!
    $msg_table hier kommen alle Nachrichten im vollen Umfang rein
    $mysql_socket der göffnete Socket zum MySQL Server mit Zugriff auf eine selektierte Datenbank !!!
    $read soll die gelesen variable spielen !! 0 = nicht gelesen , 1 = gelesen !!! :)
    $message Numerisches Array wobei jeder key = zeile ist !!!

// Wichtige Tabellenstrukturen
// In "$msg_table" gibt es ein Feld Namens "unique_id", den wert bildet
// der MD5 Fingerprint von $dir_table["msg_id"]
// z.B. md5("Message-ID: <1052913281007071@lycos-europe.com>");
// !!!! Das ist wichtig um die Nachrichten später zu ordnen zukönnen !!!!

// für das feld "received" in tabelle "$dir_table" gilt,
// mehrere Received Strings werden durch ein "<next>" getrennt !!!
// z.B.
// $dir_table["received"] = Received: from ns10493a.cobalthosting.net by mx02.web.de with smtp <next> Received: (from httpd@localhost)
////////////////////////////////////////////////////////////////////////////////



                             Part: III

(written by Urb LeJeune <urb@e-government.com>)
!!! Thanks a Lot !!!
////////////////////////////////////////////////////////////////////////////////
////////////////////////////// //////////////////////////////////////////////////

Public Functions

  // Constructor

  Your POP3 session can be logged by passing two variables when you instantiate
  the pop3 class as in the following example.

  $PerformLogging = TRUE;
  $LogFileName = "pop3.class.log";

  $pop3 = new POP3($PerformLogging,$LogFileName);

  - Only the POP3 commands and server responses are save in the log file.
  - Error messages are displayed on your browser.

  APOP (Authenticated Post Office Protocol)
  Every mail server connection you make sends your username and password across
  the network in clear text. This is a popular way for hackers to see your
  password using a "sniffer" program. With APOP, your password is encrypted
  while being transmitted over the network.

  Before establishing a connection set:
  $apop_detect = TRUE or FALSE

  Now all mailservers support ASOP.

  // Functions
  The follow class methods are available.

  //////////
  connect($server, $port = "110", $timeout = "25", $sock_timeout = "10,500")

  // Vars:
    - $server ( Server IP or DNS )
    - $port ( Server port default is "110" )
    - $timeout ( Connection timeout for connect to server )
    - $sock_timeout ( Socket timeout for all actions   (10 sec 500 msec) = (10,500))

  If connection is established the method returns TRUE. If the connection is not
  successfully established FALSE is returned and $this->error = msg is displayed.

  //////////
  login($user,$pass,$apop = "0")
  // Vars:
    - $apop  ( 1 = true and 0 = false)  (default = 0)

  //////////
  get_office_status()
    - If an error Connection will closed.
    - A successful Connection will return an associated array such as the following:

Array
(
    [count_mails] => 3
    [octets] => 3257477
    [1] => Array
        (
            [size] => 832
            [uid] => 617999468
        )

    [2] => Array
        (
            [size] => 3253781
            [uid] => 617999616
        )

    [3] => Array
        (
            [size] => 2864
            [uid] => 617999782
        )
)

  /////////
  get_mail($msg_number)
    - If the command fails the connection is not closed and FALSE is returned.
    - If get_mail() succeeds, an array is returned where every line of the
    mail message, including the header, is an element of the array. Such as:

  Array(
     [0] => "line1"
     [1] => "line2"
     ....
     )

//////////////////////////////////////////////////////////////////////////
//  IMPORTANT                                                           //
// If your mail count is high or your connection time is slow, or both, //
// you may exceed the default execution time of 30 seconds. In that     //
// case set the execution time to more than 30 seconds.                 //
//////////////////////////////////////////////////////////////////////////

  /////////
  delete_mail($msg_number)
    - Mark an email as delete
    - You must executed the close() method for the mail to be deleted.
    - If program terminates without executing a close() method the
      command, connection will not closed.
    - Execute reset() to unmark messages previously marked as deleted.
    - If the command fails the connection is not closed and FALSE is returned.

  /////////
  save2file($message,$filename)
    - $message must be a numeric array with each line terminated with a "CRLF".
    - If the command fails the connection is not closed and FALSE is returned.
  Array(
     [0] => "line1"
     [1] => "line2"
     ....
     )

   $filename
     - The default file name is  base64_encode(uid).".txt"
     - To check if you have download this mail
       base64_encode(uid).".txt" == $filename

   // Example directories and file name
   win32  .//mails// or c://ownfiles//etc...
   linux  ./mails/   or /dev/hda1/ownfiles/etc...

  /////////
  save2mysql($message,$mysql_socket,$dir_table = "inbox", $msg_table = "messages",$read = "0")
  // If the command fails the connection is not closed and FALSE is returned.
  // If mail already in $msg_table exists the method return false.
  // If there is a mysql error the method returns false and
  // mysql_errno() ." -- ". mysql error is set on $pop3-error
  // like this: "1054 -- Unknown column '' in 'field list'"
  //
  // The method checks toe establish if mail exists or not.
  // When mail exists the method returns false and an errormsg is sent on $pop3->error
  //
  // !!!!!!!!!!!!!!!!!!!!!!! IMPORTANT !!!!!!!!!!!!!!!!!!!!!!!!!!
  // In $msg_table give a field named "unique_id", the value
  // is a md5 fingerprint from $dir_table field "msg_id"
  //
  // If the "received" field in $dir_table spans more than one Server the Received
  // Strings are split with a "<next>"
  // As an example: $dir_table["received"] =
  //   Received: from ns10493a.cobalthosting.net by mx02.web.de with smtp
  //   <next> Received: (from httpd@localhost)
  // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

  /////////
  noop()
  - Send the "NOOP" command to server
  - If the command fails, the connection will not closed !!
  /////////
  get_top($msg_number,$lines="0")
    - If you only want header information use get_top().
    - The format is get_top($msg_number,"number-of-lines")
    - The "number-of-lines" parameter determines the number of lines to be read.
    - If "number-of-lines" exceeds the line count, the entire mail message is returned.
    - If the command fails the connection is not closed and FALSE is returned.

  /////////
  reset()
    - All mail previously marked as deleted will be marked as undeleted.
    - If the command fails the connection is not closed and FALSE is returned.

  /////////
  uidl($msg_number) (default = "0")
  - If $msg_number is set to FALSE (0 or null) the entire uid list is returned.
  - If $msg_number is set to a valid message number, the uid for the designed
    mail is returned..
  - If the command fails the connection is not closed and FALSE is returned.

////////////////////////////////////////////////////////////////////////////////

Private Functions

  /////////
  _putline($string)
    - Put a command to server socket.
    - $string should not be terminated with "CRLF".

  /////////
  _getnextstring()
    - optional: $buffer_size (default = "512")
    - get the next String from server socket.

  /////////
  _logging($string)
    - $string is written to the log_file as established in the class constructor.
    - $string should not be terminated with "CRLF".

  /////////
  _checkstate($string)
    - Check the pop3 server connection state.
    - $string is set to method (function) function name.

  /////////
  _parse_banner($server_text)
    - $server_text = first response after connect.
    - Returns the server banner for APOP Login command.

  /////////
  _cleanup()
    - unset some vars
    - close log_file
    - close server socket

  /////////
  _stats()
    - Get maildrop stats
    - If successful return an associative array containing:
      ["count_mails"]
      ["octets"]

////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////



--------------------------------------------------------------------------------
----------------------------------END-------------------------------------------
--------------------------------------------------------------------------------

