<?php
$api = php_sapi_name();
if ( $api != "cli" ) {
    echo "<html>\n<head>\n<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>\n</head>\n<body>\n";
    @apache_setenv('no-gzip', 1);
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    $shopnr = $_GET["Shop"];
    $nofiles = ( $_GET["nofiles"] == '1' )?true:false;
} else {
    if ( $argc > 1 ) {
        $tmp = explode("=",trim($argv[1]));
        if ( count($tmp) != 2 ) {
            echo "Falscher Aufruf: php <scriptname.php> shop=1\n";
            exit (-1);
        } else {
             $shopnr = $tmp[1];
        }
    }
}

include_once("conf$shopnr.php");
include_once("error.php");
include_once("dblib.php");
include_once("pepper.php");
include_once("erplib.php");
//Fehlerinstanz
$err = new error();

$err->out("Shop $shopnr, Bestellimport",true);

//ERP-Instanz
$erpdb = new mydb($ERPhost,$ERPdbname,$ERPuser,$ERPpass,$ERPport,'pgsql',$err);
if ($erpdb->db->connected_database_name == $ERPdbname) {
    $erp = new erp($erpdb,$err,$divStd,$divVerm,$auftrnr,$kdnum,$preA,$preK,$invbrne,$mwstS,$OEinsPart,$lager);
} else {
    $err->out('Keine Verbindung zur ERP',true);
    exit();
}

//Shop-Instanz
$shopdb = new mydb($SHOPhost,$SHOPdbname,$SHOPuser,$SHOPpass,$SHOPport,'mysql',$err);
if ($shopdb->db->connected_database_name == $SHOPdbname) {
     $shop = new pepper($shopdb,$err,$SHOPdbname,$divStd,$divVerm,$minder,$nachn,$versandS,$versandV,$paypal,$treuhand,$mwstLX,$mwstS,$variantnr);
//echo "<pre>"; print_r($shopdb->db); print_r($shopnr); echo "</pre>";
} else {
    $err->out('Keine Verbindung zum Shop',true);
    exit();
}

$bestellungen = $shop->getBestellung($ERPusrID);
//print_r($bestellungen); exit(1);
$cnt = 0;
$errors = 0;

$err->out("Bestellimport vom Shop $shopnr",true);

if ($bestellungen) foreach ($bestellungen as $row) {
    $rc = $erp->mkAuftrag($row,$shopnr,$longtxt);
    if ($rc>0) {
        $rc = $shop->setKundenNr($row['customer']['shopid'],$rc);
        if ($rc>0) {
           $shop->setAbgeholt($row['cusordnumber']); 
           $cnt++;
           $err->out("ok",true);
        } else {
           $errors++;
           $err->out("Fehler setKdNr ".$row['customer']['shopid'],true);
        }
    } else if ($rc == -1) {
           $errors++;
           $err->out("Fehler mkAuftrag ".$row['cusordnumber'],true);
    } else {
        $err->out("Fehler Kunde zuordnen ".$row['customer']['shopid'].":".$row['cusordnumber'],true);
        $errors++;
    } 
}
$err->out('Von '.count($bestellungen)." Bestellungen $cnt übertragen, $errors nicht",true);
if ( $api != "cli" ) {
    echo "<br /><a href='../oe.pl?vc=customer&type=sales_order&nextsub=orders&action=Weiter&open=1&notdelivered=1&delivered=1&l_ordnumber=Y&l_transdate=Y&l_reqdate=1&l_name=Y&l_employee=Y&l_amount=Y'>Auftragsliste</a>";
    echo "</body>\n</html>\n";
}

?>
