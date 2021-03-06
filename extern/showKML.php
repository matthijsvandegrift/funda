<?php
include_once(__DIR__.'/../include/config.php');
include_once('../include/HTML_TopBottom.php');
connect_db();

$bUur                = getParam('bDag', 0);
$bMin                = getParam('bDag', 0);
$bDag                = getParam('bDag', date("d")-1);
$bMaand                = getParam('bMaand', date("m"));
$bJaar                = getParam('bJaar', date("Y"));
$eUur                = getParam('bDag', 23);
$eMin                = getParam('bDag', 59);
$eDag                = getParam('eDag', date("d"));
$eMaand         = getParam('eMaand', date("m"));
$eJaar                = getParam('eJaar', date("Y"));
$selectie        = getParam('selectie', '');

if($_REQUEST['datum'] == 0) {
	$minUserLevel = 1;
	$cfgProgDir = '../auth/';
	include($cfgProgDir. "secure.php");
	
	$dateSelection = makeDateSelection($bUur, $bMin, $bDag, $bMaand, $bJaar, $eUur, $eMin, $eDag, $eMaand, $eJaar);
	
	$HTML[] = "<form method='post' action='$_SERVER[PHP_SELF]'>";
	$HTML[] = "<input type='hidden' name='datum' value='1'>";
	$HTML[] = "<table>";
	$HTML[] = "<tr>";
	$HTML[] = "	<td>Begin Datum</td>";
	$HTML[] = "	<td>&nbsp;</td>";
	$HTML[] = "	<td>Eind Datum</td>";
	$HTML[] = "	<td>&nbsp;</td>";
	$HTML[] = "	<td>Groep</td>";
	$HTML[] = "	<td>&nbsp;</td>";
	$HTML[] = "	<td rowspan='2'><input type='submit' name='submit' value='Weergeven'></td>";
	$HTML[] = "</tr>";
	$HTML[] = "<tr>";
	$HTML[] = "	<td>". $dateSelection[0] ."</td>";
	$HTML[] = "	<td>&nbsp;</td>";
	$HTML[] = "	<td>". $dateSelection[1] ."</td>";
	$HTML[] = "	<td>&nbsp;</td>";
	$HTML[] = "	<td>". makeSelectionSelection(false, false) ."</td>";
	$HTML[] = "	<td>&nbsp;</td>";
	$HTML[] = "</tr>";
	$HTML[] = "	<td colspan=7><input type=checkbox name=link value=1 checked>Open direct in GoogleMaps ipv downloaden KML-file</td>\n";
	$HTML[] = "</tr>";
	$HTML[] = "</table>";
	$HTML[] = "</form>";
	
	echo $HTMLHeader;
	echo "<tr>\n";
	echo "<td width='8%'>&nbsp;</td>\n";
	echo "<td width='84%' valign='top' align='center'>\n";
	echo showBlock(implode("\n", $HTML));
	echo "</td>\n";
	echo "<td width='8%'>&nbsp;</td>\n";
	echo "</tr>\n";
	echo $HTMLFooter;
} elseif($_REQUEST['link'] == '1') {
	$redirect = "http://maps.google.nl/maps?q=". urlencode($ScriptURL .'extern/showKML.php?datum=1&selectie='. $_POST[selectie] .'&bDag='. $_POST[bDag] .'&bMaand='. $_POST[bMaand] .'&bJaar='. $_POST[bJaar] .'&eDag='. $_POST[eDag] .'&eMaand='. $_POST[eMaand] .'&eJaar='. $_POST[eJaar]);
	$url="Location: ". $redirect;
	header($url);
} else {
        $BeginTijd        = mktime($bUur, $bMin, 0, $bMaand, $bDag, $bJaar);
        $EindTijd                = mktime($eUur, $eMin, 59, $eMaand, $eDag, $eJaar);
	
	$groep	= substr($selectie, 0, 1);
	$id			= substr($selectie, 1);
	
	if($groep == 'Z') {		
		$opdrachtData	= getOpdrachtData($id);
		$Name					= $opdrachtData['naam'];
		$from					= "$TableResultaat, $TableHuizen";
		$where[]			= "$TableResultaat.$ResultaatID = $TableHuizen.$HuizenID";
		$where[]			= "$TableResultaat.$ResultaatZoekID = $id";
		//$where[]			= "(($TableHuizen.$HuizenEind BETWEEN $BeginTijd AND $EindTijd) OR ($TableHuizen.$HuizenStart BETWEEN $BeginTijd AND $EindTijd))";
	} else {
		$LijstData		= getLijstData($id);
		$Name					= $LijstData['naam'];
		$from					= "$TableListResult, $TableHuizen";
		$where[]				= "$TableListResult.$ListResultHuis = $TableHuizen.$HuizenID";
		$where[]				= "$TableListResult.$ListResultList = $id";		
	}
	$where[]				= "(($TableHuizen.$HuizenEind BETWEEN $BeginTijd AND $EindTijd) OR ($TableHuizen.$HuizenStart BETWEEN $BeginTijd AND $EindTijd) OR ($TableHuizen.$HuizenEind > $EindTijd AND $TableHuizen.$HuizenStart < $BeginTijd))";
	
	$KMLTitle = "Nieuwe huizen in $Name van ". date("d-m-Y", $BeginTijd) .' t/m '. date("d-m-Y", $EindTijd);
	include('../include/KML_TopBottom.php');

	$sql_wijk		= "SELECT * FROM $from WHERE ". implode(" AND ", $where) ." GROUP BY $TableHuizen.$HuizenWijk ORDER BY $TableHuizen.$HuizenPC_c, $TableHuizen.$HuizenPC_l";

	$result_wijk= mysql_query($sql_wijk);
	$row_wijk		= mysql_fetch_array($result_wijk);
	
	do {
		$wijk = trim($row_wijk[$HuizenWijk]);
		
		$KML_file[] = '<Folder>';
		$KML_file[] = '<open>0</open>';
		$KML_file[] = '	<name>'. urldecode($wijk) .'</name>';
			
		$sql_huis			= "SELECT * FROM $from WHERE ". implode(" AND ", $where) ." AND $TableHuizen.$HuizenWijk like '$wijk' ORDER BY $TableHuizen.$HuizenPC_c, $TableHuizen.$HuizenPC_l";
				
		$result_huis	= mysql_query($sql_huis);
		$row_huis			= mysql_fetch_array($result_huis);
	
		do {	
			$KML_file[] = makeKMLEntry($row_huis[$HuizenID]);
		} while($row_huis = mysql_fetch_array($result_huis));
		
		$KML_file[] = '</Folder>';	
	} while($row_wijk = mysql_fetch_array($result_wijk));
	
	header("Expires: Mon, 26 Jul 2001 05:00:00 GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false); 
	header("Pragma: no-cache");
	header("Cache-control: private");
	header('Content-type: application/kml');
	header('Content-Disposition: attachment; filename="'.  str_replace(' ', '_', $Name .'-'. date("d.m.Y-H.i")) .'.kml"');
	echo $KML_header.implode("\n", $KML_file).$KML_footer;
}