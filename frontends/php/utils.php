<?php
require 'config.inc';
require 'ez_sql.php';
require 'phpLinkCheck.php';

global $db;
$db = new db(EZSQL_DB_USER, EZSQL_DB_PASSWORD, EZSQL_DB_NAME, EZSQL_DB_HOST);

function genAuctionfile($artnr,$bid) {
    $fn="/tmp/$artnr.ebaysnipe";
    $text="$artnr $bid\n";
    $fp=fopen($fn,"w");
    fwrite($fp,$text);
    fclose($fp);
    chmod($fn, 0666);
}

function startEsniper($artnr) {
    $fn="/tmp/".$artnr.".ebaysnipe";
    $fnl="/tmp/".$artnr.".ebaysnipelog";
    touch($fnl);
    chmod($fnl, 0666);
    $pid = exec("./esniperstart.sh $fn $fnl ".PATH_TO_ESNIPER." ".PATH_TO_ESNIPERCONFIG." > /dev/null & echo \$!", $results,$status);
    return($pid);
}

function auktionBeendet($artnr) {
    $fn="/tmp/".$artnr.".ebaysnipelog";
    if (file_exists($fn)) {
	$fp=fopen($fn,"r");
	$text=fread($fp, filesize ($fn));
	fclose($fp);
	if (ereg("You have already won", $text)) {return(1);}
	elseif (ueberbotenStatus($text)) {return(2);}
	else {return(0);}
    }
}

function auktionEndtime($text) {
	ereg("End time: [0-9]{2}\/[0-9]{2}\/[0-9]{4} [0-2][0-9]:[0-5][0-9]:[0-5][0-9]",$text, $zeitArr);
	$zeitStr = $zeitArr[count($zeitArr)-1];
	$tag = substr($zeitStr,10,2);
	$monat = substr($zeitStr,13,2);
	$jahr = substr($zeitStr,16,4);

	$stunde = substr($zeitStr,21,2);
	$minute = substr($zeitStr,24,2);
	$sekunde = substr($zeitStr,27,2);

	$unixzeit = mktime($stunde,$minute,$sekunde,$monat,$tag,$jahr);
	return($unixzeit);
}

function ueberbotenStatus($text) {
    ereg("bid: [0-9]+\.?[0-9]+",$text,$meineGebote);
    if (getHighestBid($text) - substr($meineGebote[count($meineGebote)-1],5) > 0) {
	return(true);
    } else {
	return(false);
    }
}

function statusPruefen($artnr,$db) {
    $status = auktionBeendet($artnr);
    if ($status != 0) {
	$sql = "UPDATE snipe SET status = ".$status." WHERE artnr=".$artnr;
	$db->query($sql);
	if ($status == 1) {
	//Andere zur Gruppe geh�rende Auktionen beenden/updaten.
	    $sql = "SELECT gruppe FROM snipe WHERE artnr = ".$artnr;
	    $gruppennr = $db->get_var($sql);
	    $sql = "UPDATE snipe SET status = 3 WHERE gruppe = ".$gruppennr." AND artnr <> ".$artnr;
	    $db->query($sql);
	}
    }
}

function snipeEinstellen($artnr,$bid,$db) {
    $bid = str_replace(",",".",$bid);
    $sql = "SELECT * FROM snipe WHERE artnr=".$artnr;
    $snipe = $db->get_row($sql);
    if (empty($snipe)) {
		genAuctionfile($artnr,$bid);
        //PID auslesen und in Datenbank schreiben
        $pid = startEsniper($artnr);
        $sql = "INSERT INTO snipe (artnr,bid,pid,status) VALUES (\"$artnr\",\"$bid\",\"$pid\",0)";
        $db->query($sql);
    } else {
		//Snipe bereits in Datenbank vorhanden
		if ($bid != $snipe->bid) {
			killSniper($artnr,$db);
			genAuctionfile($artnr,$bid);
			$pid = startEsniper($artnr);
			$sql="UPDATE snipe SET bid = ".$bid.",pid = ".$pid.",status = 0 WHERE artnr = ".$snipe->artnr;
			$db->query($sql);
		} elseif (!snipeRunCheck($snipe->pid)) {
			genAuctionfile($artnr,$bid);
			$pid = startEsniper($artnr);
			$sql = "UPDATE snipe SET pid = ".$pid." WHERE artnr = ".$artnr;
			$db->query($sql);
		}
    }
    exec("./updateDB.php &");  //Nach 10 Sekunden aus den Logs die Endtime in der DB updaten - multi Thread
}

function killSniper($artnr,$db) {
    $sql = "SELECT * FROM snipe WHERE artnr=".$artnr;
    $snipe = $db->get_row($sql);

    if (snipeRunCheck($snipe->pid) == true) {
       //Sicherheitsabfrag eeinbauen, ob PID auch ein esniper Programm
//	printf("Sniperprozess mit PID ".$snipe->pid."beendet.");
	    exec("kill -15 ".getEsniperPid($snipe->pid));
    }
    exec("rm /tmp/".$artnr.".*");
}

function getPids() {
    $output = shell_exec("pidof -x esniperstart.sh");
    $pids = split(" ",rtrim($output));
    return($pids);
}


function getEsniperPid($shpid) {
//Workaround
	$output = shell_exec("pstree -p|grep ".$shpid);
	if (preg_match_all("/\([0-9]+\)/",$output,$pids,PREG_SET_ORDER)) {
		return(substr($pids[1][0],1,strlen($pids[1][0])-2));
	}
}


function snipeRunCheck($pid) {
    $pids = getPids();
    return(in_array($pid,$pids));
}


function fileList($dir) {
    $fp = opendir($dir);
    while($datei = readdir($fp)) {
        if (substr($datei,-12) == "ebaysnipelog" || substr($datei,-9) == "ebaysnipe") {
            $dateien[] = "$datei";
        }
    }
    closedir($fp);
    return($dateien);
}


function getLogData($artnr) {
	$fn="/tmp/".$artnr.".ebaysnipelog";
	if (file_exists($fn)) {
		$fp=fopen($fn,"r");
		$text=fread($fp, filesize ($fn));
		fclose($fp);
	}
	return($text);
}


function getHighestBid($logData) {
//Filtert das h�chste Gebot aus den Logs
	ereg("Currently: [0-9]+\.?[0-9]+",$logData,$aktGebote);
    return(substr($aktGebote[count($aktGebote)-1],11));
}


function updateHighestBid($db) {
	$sql = "SELECT * FROM snipe WHERE status = 0";
	$snipelist = $db->get_results($sql);
	if (!empty($snipelist)) {
		foreach($snipelist as $snipe) {
			$logData = getLogData($snipe->artnr);
			$sql = "UPDATE snipe SET highestBid = \"".getHighestBid($logData)."\" WHERE artnr = ".$snipe->artnr;
			$db->query($sql);
		}
	}
}


function updateEndtime($db) {
	$sql = "SELECT * FROM snipe WHERE endtime = 0";
	$snipelist = $db->get_results($sql);
	if (!empty($snipelist)) {
		foreach($snipelist as $snipe) {
			$logData = getLogData($snipe->artnr);
			$unixtime = auktionEndtime($logData);
			$sql = "UPDATE snipe SET endtime = ".$unixtime." WHERE artnr = ".$snipe->artnr;
			$db->query($sql);
		}
	}
}


function snipeGenerate($db) {
//Generiert anhand der Datenbankdaten esniper Prozesse
    $msg = "";
    $sql = "SELECT * FROM snipe WHERE status = 0";
    $snipelist = $db->get_results($sql);
    foreach($snipelist as $snipe) {
		if (!snipeRunCheck($snipe->pid)) {
		//Prozess l�uft nicht
			snipeEinstellen($snipe->artnr,$snipe->bid,$db);
			$msg = $msg . "Snipe f�r ".$snipe->artnr." gestartet.\n";
		} else {
			$msg = $msg . "Snipe f�r ".$snipe->artnr." l�uft bereits.\n";
		}
    }
    return($msg);
}

function collectGarbage($db) {
    //Pids abschiessen, welche nicht laufen d�rfen
    $sql = "SELECT pid FROM snipe WHERE status = 0";
    $snipePids = $db->get_col($sql);
    $sql = "SELECT artnr FROM snipe WHERE status = 0";
    $snipeArtnr = $db->get_col($sql);
    $pids = getPids();
    foreach($pids as $pid) {
		if (!in_Array($pid,$snipePids)) {
			exec("kill -15 ".getEsniperPid($pid));
		}
    }

	//Logs l�schen, von Snipes, welche nicht in der Datenbank sind.
    $sql = "SELECT artnr FROM snipe";
    $snipeArtnr = $db->get_col($sql);
    $dateien = fileList("/tmp");
    foreach($dateien as $datei) {
	if (!in_Array(substr($datei,0,10),$snipeArtnr)) {
	    exec("rm /tmp/".$datei);
	}
    }
    foreach($snipeArtnr as $artnr) {
	statusPruefen($artnr,$db);
    }
}
?>