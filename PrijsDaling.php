<?
include_once('../general_include/general_functions.php');
include_once('../general_include/general_config.php');
include_once('include/functions.php');
include_once('include/config.php');
include_once('include/HTML_TopBottom.php');
connect_db();

echo $HTMLHeader;

if(isset($_REQUEST['regio'])) {
	$regio = $_REQUEST['regio'];
	$opdrachtData = getOpdrachtData($regio);
	
	$sql		= "SELECT * FROM $TableResultaat, $TableHuizen WHERE $TableResultaat.$ResultaatID = $TableHuizen.$HuizenID AND $TableResultaat.$ResultaatZoekID = $regio ORDER BY $TableHuizen.$HuizenAdres";
	$result	= mysql_query($sql);
	$row		= mysql_fetch_array($result);
	
	$max_percentage = 33;
	$gisteren = time() - (24*60*60);
	
	echo "<table width='100%' border=0>\n";
	echo "<tr>\n";
	echo "	<td align='center' colspan='4'><h1>Prijsdaling '". $opdrachtData['naam'] ."'</h1></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td colspan='4'>&nbsp;</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td width='25%'>&nbsp;</td>\n";
	echo "	<td width='34%' align='left'>0 %</td>\n";
	echo "	<td width='34%' align='right'>$max_percentage %</td>\n";
	echo "	<td width='7%'>&nbsp;</td>\n";
	echo "</tr>\n";
		
	do {
		$prijzen	= getPriceHistory($row[$HuizenID]);
		$laatste	= current($prijzen);
		$eerste		= end($prijzen);
				
		if(max($prijzen) > 0) {
			$percentageAll		= 100*($eerste - $laatste)/$eerste;
			$breedte_1				= round(100*$percentageAll/$max_percentage);
			$breedte_2				= round(100*($max_percentage-$percentageAll)/$max_percentage);
			if($percentageAll > 0) {
				$percGemiddeld[]	= $percentageAll;
			}
		} else {
			$breedte_1 = 0;
			$breedte_2 = 100;
		}
		
		if($row[$HuizenOffline] == '1') {
			if($row[$HuizenVerkocht] != '1') {
				$class = 'offline';
			} else {
				$class = 'offlineVerkocht';
			}			
		} elseif($row[$HuizenVerkocht] == '1') {
			$class = 'onlineVerkocht';
		} else {
			$class = '';
		}
				
		echo "<tr>\n";
		echo "	<td width='25%'><a id='". $row[$HuizenID] ."'><a href='admin/HouseDetails.php?regio=". $regio ."&id=". $row[$HuizenID] ."'><img src='http://www.vvaltena.nl/styles/img/details/report.png'></a> <a id='". $row[$HuizenID] ."'><a href='http://www.funda.nl". urldecode($row[$HuizenURL]) ."' target='_blank' class='$class'>". urldecode($row[$HuizenAdres]) ."</a></td>\n";
		echo "	<td colspan=2>\n";
		echo "	<table width='100%' border=0><tr>\n";
		if($breedte_1 != 0) {
			echo "		<td width='". $breedte_1 ."%' bgcolor='#FF6D6D' title='Gedaald van &euro;&nbsp;". number_format($eerste, 0,'','.') ." naar &euro;&nbsp;". number_format($laatste, 0,'','.') ." (".number_format($percentageAll, 0) ."%)'>&nbsp;</td>\n";
		}
		echo "		<td width='". $breedte_2 ."%'>&nbsp;</td>\n";
		echo "	</tr></table>\n";
		echo "	</td>\n";
		echo "	<td width='7%'><a href='TimeLine.php?regio=$regio#". $row[$HuizenID] ."'>". getDoorloptijd($row[$HuizenID]) ."</a></td>\n";		
		echo "</tr>\n";
	} while($row = mysql_fetch_array($result));
	
	$percentage = array_sum($percGemiddeld)/count($percGemiddeld);
	$breedte_1	= round(100*$percentage/$max_percentage);
	$breedte_2	= round(100*($max_percentage-$percentage)/$max_percentage);
	
	echo "<tr>\n";
	echo "	<td width='25%'><b>Gemiddeld</b></td>\n";
	echo "	<td colspan=2>\n";
	echo "	<table width='100%' border=0><tr>\n";
	echo "		<td width='". $breedte_1 ."%' bgcolor='#FF6D6D' title='Gemiddeld ". number_format($percentage, 0) ."%'>&nbsp;</td>\n";
	echo "		<td width='". $breedte_2 ."%'>&nbsp;</td>\n";
	echo "	</tr></table>\n";
	echo "	</td>\n";
	echo "	<td width='7%'>&nbsp;</td>\n";		
	echo "</tr>\n";
	echo "</table>\n";
} else {
	echo "<form method='post' action='$_SERVER[PHP_SELF]'>\n";
	echo "<table>\n";
	echo "<tr>\n";
	echo "	<td>Regio</td>\n";
	echo "	<td>&nbsp;</td>\n";
	echo "	<td><select name='regio'>\n";

	$Opdrachten = getZoekOpdrachten(1);
	foreach($Opdrachten as $OpdrachtID) {
		$OpdrachtData = getOpdrachtData($OpdrachtID);
		echo "	<option value='$OpdrachtID'". ($OpdrachtID == $regio ? ' selected' : '') .">". $OpdrachtData['naam'] ."</option>\n";
	}
	echo "	</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "	<td colspan=3><input type='submit' name='submit' value='Weergeven'></td>\n";
	echo "</tr>\n";
	echo "<table>\n";
	echo "</form>\n";
}

echo $HTMLFooter;
?>
	