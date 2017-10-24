<?php
//
// Program: quoteUploader.php (2017-10-05) G.J. Watson
//
// Purpose: upload quotes supplied in a CSV file formatted <author>,<quote>
//
// Date       Version Note
// ========== ======= ====================================================
// 2017-10-05 v0.01   First cut of code
// 2017-10-16 v1.00   Corrected quote addition flag to properly update 'new' count
// 2017-10-16 v1.01   Return array containing whether quote/author added and message from insertQuote
//

    set_include_path("<LIB GOES HERE>");
    require_once("dbquote.php");
    require_once("common.php");

    $version  = "v1.01";
    $wrksp    = "<WRKSPACE DIR GOES HERE>";
    $filename = $wrksp."/quote_import.csv";
    $debug    = TRUE;

    function insertQuote($mysqli, $author, $quote) {
        $added      = 0;
        $result     = 0;
        $messageStr = "";
        /* create a prepared statement */
        if ($stmt = $mysqli->prepare("SELECT addquote(?, ?) AS result")) {
            /* bind parameters for markers */
            $stmt->bind_param("ss", $author, $quote);
            /* execute query */
            $stmt->execute();
            /* bind result variables */
            $stmt->bind_result($result);
            /* fetch value */
            $stmt->fetch();
            switch ($result) {
                case 0:
                    $messageStr = "Author and Quote already in the database...";
                    break;
                case 1:
                    $messageStr = "Quote added to database, Author already exists...";
                    $added = 1;
                    break;
                case 3:
                    $messageStr = "Quote and Author added to the database...";
                    $added = 1;
                    break;
                default:
                    throw new Exception("Unknown status returned from addquote database function");
            }
            /* close statement */
            $stmt->close();
        } else {
            throw new Exception("Unable to prepare SELECT in insertQuote()");
        }
        return array($added, $messageStr);
    }

    try {
        debugMessage("Commencing ".basename(__FILE__)." ".$GLOBALS['version']."...");
        $server = new mysqli($GLOBALS['hostname'], $GLOBALS['username'], $GLOBALS['password'], $GLOBALS['database']);
        if ($server->connect_errno) {
            throw new Exception("Unable to retrieve information from the database");
        }
        debugMessage("Connected to host (".$server->host_info.")...");
        //
        // check we have an import file
        //
        if (file_exists($filename) == FALSE) {
            throw new Exception("Quote upload file ".$filename." not found...");
        }
        $row = 1;
        $new = 0;
        if (($handle = fopen($filename, "r")) !== FALSE) {
            debugMessage("Opened quote upload file ".$filename." ready to import...");
            while (($data = fgetcsv($handle, 1024, ",")) !== FALSE) {
                $messageStr = "";
                if (count($data) !== 2) {
                    $messageStr = "Line ".$row." in quote upload file has ".count($data)." number of fields it should be 2!!!";
                } else {
                    $author  = trim($data[0]);
                    $quote   = trim($data[1]);
                    $results = insertQuote($server, $author, $quote);
                    $messageStr = "Line ".$row." contains quote for '".$author."', ".$results[1]."...";
                    $new += $results[0];
                }
                debugMessage($messageStr);
                $row++;
            }
            fclose($handle);
        }
        debugMessage("Completed processing upload file, added ".$new." quotes to the system...");
        $server->close();
    } catch (Exception $e) {
        debugMessage("ERROR: ".$e->getMessage());
    }
    exit();
?>
