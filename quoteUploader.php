<?php
//
//  Program: quoteUploader.php - G.J. Watson
//     Desc: upload quotes supplied in a CSV file formatted <author>,<quote>
//  Version: 1.04
//

    set_include_path("/var/sites/s/shiny-ideas.tech/lib");
    require_once("Common.php");

    $version  = "v1.04";
    $wrksp    = "/var/sites/s/shiny-ideas.tech/WorkSpace/";
    $filename = $wrksp."/quote_import.csv";
    $debug    = TRUE;

    function getRecordCountFromTable($mysqli, $table) {
        $total = 0;
        if ($stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM ".$table)) {
            /* execute query */
            $stmt->execute();
            /* bind result variables */
            $stmt->bind_result($total);
            /* fetch value */
            $stmt->fetch();
            /* close statement */
            $stmt->close();
        } else {
            throw new Exception("Unable to prepare SELECT in getRecordCountFromTable() for ".$table);
        }
        return $total;
    }

    function getTotalAuthorCount($mysqli) {
        return getRecordCountFromTable($mysqli, "author");
    }

    function getTotalQuoteCount($mysqli) {
        return getRecordCountFromTable($mysqli, "quote");
    }

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
                    $messageStr = "Author and Quote already in the database";
                    break;
                case 1:
                    $messageStr = "Quote added to database, Author already exists";
                    $added = 1;
                    break;
                case 3:
                    $messageStr = "Quote and Author added to the database";
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

    $database = "";
    $username = "";
    $password = "";
    $hostname = "";

    $common = new Common();
    try {
        $common->printINFOMessage("Commencing ".basename(__FILE__)." ".$GLOBALS['version']."...");
        $server = new mysqli($hostname, $username, $password, $database);
        if ($server->connect_errno) {
            throw new Exception("Unable to retrieve information from the database");
        }
        $common->printINFOMessage("Connected to host (".$server->host_info.")...");
        //
        // check we have an import file
        //
        if (file_exists($filename) == FALSE) {
            throw new Exception("Quote upload file ".$filename." not found...");
        }
        $row = 1;
        $new = 0;
        if (($handle = fopen($filename, "r")) !== FALSE) {
            $common->printINFOMessage("Opened quote upload file ".$filename." ready to import...");
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
                $common->printINFOMessage($messageStr);
                $row++;
            }
            $messageStr = "Database now contains ".getTotalAuthorCount($server)." authors, with ".getTotalQuoteCount($server)." quotes...";
            $common->printINFOMessage($messageStr);
            fclose($handle);
        }
        $common->printINFOMessage("Completed processing upload file, added ".$new." quotes to the system...");
        $server->close();
    } catch (Exception $e) {
        $common->printERRORMessage($e->getMessage());
    }
    exit();
?>
