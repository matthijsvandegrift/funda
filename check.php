<?
include_once('../general_include/general_functions.php');
include_once('../general_include/general_config.php');
include_once('../general_include/class.phpmailer.php');

include_once('include/functions.php');
include_once('include/config.php');

connect_db();

if(isset($_REQUEST[OpdrachtID])) {
	$Opdrachten = array($_REQUEST[OpdrachtID]);
	$enkeleOpdracht = true;
} else {
	$Opdrachten = getZoekOpdrachten(1);
	$enkeleOpdracht = false;
}

foreach($Opdrachten as $OpdrachtID) {
	$OpdrachtData = getOpdrachtData($OpdrachtID);
	$OpdrachtURL	= $OpdrachtData['url'];
	toLog('info', $OpdrachtID, '', 'Start controle '. $OpdrachtData['naam']);
	
	$contents	= file_get_contents_retry($OpdrachtURL);
	
	$NrHuizen	= getString('<span class="hits"> (', ')', $contents, 0);

	if(!is_numeric($NrHuizen[0])) {
		$ErrorMessage[] = $OpdrachtData['naam'] ."; Het totaal aantal huizen klopt niet : ". $NrHuizen[0];	
		toLog('error', $OpdrachtID, '', 'Ongeldig aantal huizen');
	}
	
	echo "<a href='$OpdrachtURL'>". $OpdrachtData['naam'] ."</a> -> ". $NrHuizen[0] ." huizen<br>\n";
	
	// Alles initialiseren
	$HTMLMessage = $UpdatePrice = $Subject = $sommatie = array();
	$nextPage = true;
	$p=0;

	//for($p=1; $p<=$NrPages ; $p++) {
	while($nextPage) {
		$AdressenArray = $VerlopenArray = array();
		$p++;
		
		$PageURL		= $OpdrachtURL.'p'.$p.'/';
		$contents = file_get_contents_retry($PageURL, 5);
		
		if(is_numeric(strpos($contents, "paging next")) AND $debug == 0) {
			$nextPage = true;
		} else {
			$nextPage = false;
		}
				
		$HuizenNVM		= explode(' nvm" >', $contents);			array_shift($HuizenNVM);
		$HuizenNVMlst	= explode(' nvm lst" >', $contents);	array_shift($HuizenNVMlst);
		$HuizenVBO		= explode(' vbo" >', $contents);			array_shift($HuizenVBO);
		$HuizenVBOlst	= explode(' vbo lst" >', $contents);	array_shift($HuizenVBOlst);
		$HuizenLMV		= explode(' lmv" >', $contents);			array_shift($HuizenLMV);
		$HuizenLMVlst	= explode(' lmv lst" >', $contents);	array_shift($HuizenLMVlst);
		$HuizenExt		= explode(' ext" >', $contents);			array_shift($HuizenExt);
		$HuizenExtlst	= explode(' ext lst" >', $contents);	array_shift($HuizenExtlst);
		$HuizenProject= explode('closed" >', $contents);		array_shift($HuizenProject);
		$Huizen				= array_merge($HuizenNVM, $HuizenNVMlst, $HuizenVBO, $HuizenVBOlst, $HuizenLMV, $HuizenLMVlst, $HuizenExt, $HuizenExtlst, $HuizenProject);
		$NrPageHuizen		= count($Huizen);
		
		// funda heeft sinds 18-02-2013 de gekke gewoonte om ook verkochte huizen op te nemen.
		// Op deze manier wordt de teller van gevonden huizen wel kloppend gehouden.
		$HuizenExpNVM	= explode(' nvm exp" >', $contents);		array_shift($HuizenExpNVM);
		$HuizenExpVBO	= explode(' vbo exp" >', $contents);		array_shift($HuizenExpVBO);
		$HuizenExpLMV	= explode(' lmv exp" >', $contents);		array_shift($HuizenExpLMV);		
		$verlopenHuizen			= array_merge($HuizenExpNVM, $HuizenExpVBO, $HuizenExpLMV);
		
		foreach($verlopenHuizen as $HuisText) {
			$verlopenAdres = getString('<h3>', '<a class=', $HuisText, 0);
			$VerlopenArray[] = $verlopenAdres[0];
		}
				
		if($debug == 1) {
			echo "Aantal huizen op <a href='$PageURL'>pagina $p</a> : ". $NrPageHuizen ."<br>\n";;
			$NrPageHuizen = 7;
		}
				
		foreach($Huizen as $HuisText) {
			$data = extractFundaData($HuisText);
			$AdressenArray[] = $data['adres'];
						
			// Huis is nog niet bekend bij het script
			if(!knownHouse($data['id'])) {
				$extraData = extractDetailedFundaData("http://www.funda.nl". $data['url']);				
				if(!saveHouse($data, $extraData)) {
					echo "Ging niet goed [1]";
					$ErrorMessage[] = "Toevoegen van ". $data['adres'] ." aan het script ging niet goed";
					toLog('error', $OpdrachtID, $data['id'], 'Huis toevoegen aan script mislukt');
				} else {					
					toLog('info', $OpdrachtID, $data['id'], 'Huis toevoegen aan script');
				}
				
				if(!addCoordinates($data['adres'], $data['PC_c'], $data['plaats'], $data['id'])) {					
					$ErrorMessage[] = "Toevoegen van coordinaten aan ". $data['adres'] ." ging niet goed";	
					toLog('error', $OpdrachtID, $data['id'], 'Coordinaten toevoegen mislukt');
				} else {
					toLog('debug', $OpdrachtID, $data['id'], "Coordinaten toegevoegd");
				}
				
				if(!updatePrice($data['id'], $data['prijs'])) {
					$ErrorMessage[] = "Toevoegen van prijs (". $data['prijs'] .") aan ". $data['adres'] ." ging niet goed";
					toLog('error', $OpdrachtID, $data['id'], 'Prijs toevoegen mislukt');
				} else {
					toLog('debug', $OpdrachtID, $data['id'], "Prijs toegevoegd");
				}
				
			} else {				
				// Huis is al bekend bij het script, aangeven dat huis nog bestaat
				if(!updateAvailability($data['id'])) {
					echo "<font color='red'>Updaten van <b>". $data['adres'] ."</b> is mislukt</font> | $sql<br>\n";
					$ErrorMessage[] = "Updaten van ". $data['adres'] ." is mislukt";
					toLog('error', $OpdrachtID, $data['id'], "Update van huis kon niet worden gedaan");
				} else {
					toLog('debug', $OpdrachtID, $data['id'], 'Huis geupdate');
				}

				// Huis is al bekend bij het script, controleer of de prijs gedaald is
				if(newPrice($data['id'], $data['prijs'])) {							
					if(!updatePrice($data['id'], $data['prijs'])) {
						echo "Toevoegen van de prijs van <b>". $data['adres'] ."</b> is mislukt | $sql<br>\n";
						$ErrorMessage[] = "Updaten van prijs (". $data['prijs'] .") aan ". $data['adres'] ." ging niet goed";
						toLog('error', $OpdrachtID, $data['id'], "Nieuwe prijs van ". $data['prijs'] ." kon niet worden toegevoegd");
					} else {
						toLog('debug', $OpdrachtID, $data['id'], "Prijs geupdate");
					}
				}
			}
			
			// Kijk of dit huis al vaker gevonden is voor deze opdracht
			if(newHouse($data['id'], $OpdrachtID)) {
				if(!addHouse($data, $OpdrachtID)) {
					echo "Ging niet goed [4]";
					$ErrorMessage[] = "Toevoegen van ". $data['adres'] ." aan opdracht $OpdrachtID ging niet goed";
					toLog('error', $OpdrachtID, $data['id'], 'Huis toekennen aan opdracht mislukt');
				} else {
					toLog('debug', $OpdrachtID, $data['id'], 'Huis toegekend aan opdracht');
				}
				
				$fundaData = getFundaData($data['id']);
				$kenmerken = getFundaKenmerken($data['id']);
				$fotos	= explode('|', $kenmerken['foto']);

				$Item = array();				
				$Item[] = "<table width='100%'>";
				$Item[] = "<tr>";
				$Item[] = "	<td colspan='2' align='center'><h1><a href='". $ScriptURL ."extern/redirect.php?id=". $data['id'] ."'>". $data['adres'] ."</a></h1><br>". $fundaData['wijk'] ."<br>\n<br></td>";
				$Item[] = "</tr>";
				$Item[] = "<tr>";
				$Item[] = "	<td align='center' width='60%'><a href='http://www.funda.nl". $fundaData['url'] ."'><img src='". str_replace ('_klein.jpg', '_middel.jpg',  $fundaData['thumb']) ."' alt='klik hier om naar funda.nl te gaan' border='0'></a></td>";
				$Item[] = "	<td align='left' width='40%'>";
				$Item[] = "  ". $fundaData['PC_c'] ." ". $fundaData['PC_l'] ." ". $fundaData['plaats'] ."<br>";
				$Item[] = "  ". $kenmerken['Aantal kamers'] ."<br>";
				//$Item[] = "  ". $extraData['perceel'] ." (". $extraData['inhoud'] ."/". $extraData['oppervlakte'] .")<br>";
				$Item[] = "  ". $kenmerken['Perceeloppervlakte'] ." (". $kenmerken['Inhoud'] .")<br>";
				$Item[] = "  <b>&euro;&nbsp;". number_format($data['prijs'], 0,'','.') ."</b></td>";
				$Item[] = "</tr>";
				$Item[] = "<tr>";
				$Item[] = "	<td colspan='2'>&nbsp;</td>";
				$Item[] = "</tr>";
				$Item[] = "<tr>";
				$Item[] = "	<td colspan='2'>". makeTextBlock($kenmerken['descr'], 750) ."</td>";
				$Item[] = "</tr>";
				$Item[] = "<tr>";
				$Item[] = "	<td colspan='2'>&nbsp;</td>";
				$Item[] = "</tr>";
				$Item[] = "<tr>";
				$Item[] = "	<td colspan='2' align='center'>";
					
				if(is_array($fotos)) { $selectie = array_slice ($fotos, 0, (($colPhoto * $rowPhoto)-1)); } else { $selectie = array(); }
					
				$Item[] = "	<table>";
				$Item[] = "	<tr>";
					
				foreach($selectie as $key => $foto) {
					$Item[] = "		<td><a href='http://www.funda.nl". $data['url'] ."fotos/#groot&foto-". ($key + 1) ."'><img src='$foto' border='0'></a></td>";
					if(fmod($key, $colPhoto) == ($colPhoto - 1)) {
						$Item[] = "	</tr>";
						$Item[] = "	<tr>";
					}
				}
					
				if (count($fotos) > (($colPhoto * $rowPhoto)-1)) {
					$Item[] = "		<td align='center'><a href='http://www.funda.nl". $data['url'] ."fotos/#groot&foto-$key'>bekijk<br>meer<br>foto's</a></td>";
				}				
				$Item[] = "	</tr>";
				$Item[] = "	</table>";				
				$Item[] = "	</td>";
				$Item[] = "</tr>";
				$Item[] = "</table>";
					
				$HTMLMessage[] = showBlock(implode("\n", $Item));					
			} else {
				if(changedPrice($data['id'], $data['prijs'], $OpdrachtID)) {						
					$prijzen = getPriceHistory($data['id']);
					
					array_shift($prijzen);	// De eerste prijs is al de huidige prijs. Die moeten we dus vergeten						
					$percentageAll	= 100*($data['prijs'] - end($prijzen))/end($prijzen);
					$percentageNu		= 100*($data['prijs'] - current($prijzen))/current($prijzen);
					
					$Item = array();				
					$Item[] = "<img src='". $data['thumb'] ."'><br>";
					$Item[] = "<a href='http://www.funda.nl". $data['url'] ."'>". $data['adres'] ."</a>, van &euro;&nbsp;". number_format(current($prijzen), 0,'','.') ." naar &euro;&nbsp;". number_format($data['prijs'], 0,'','.') ." (".number_format($percentageNu, 0) ."%/".number_format($percentageAll, 0) ."%).";
				
					$UpdatePrice[] = implode("\n", $Item);
				}
			}
		}
		echo "<a href='$PageURL'>Pagina $p</a> verwerkt en ". (count($AdressenArray) + count($VerlopenArray))  ." huizen gevonden :<br>\n";
		
		if($enkeleOpdracht) {
			echo '<ol>';
			
			foreach($AdressenArray as $key => $value) {
				echo "<li>$value</li>\n";
			}
			
			echo '</ol>';
			echo '<ul>';
						
			foreach($VerlopenArray as $key => $value) {
				echo "<li>$value</li>\n";
			}
			
			echo '</ul>';
		}
		
		//$sommatie[] = count($AdressenArray);
		toLog('debug', $OpdrachtID, '', "Einde pagina $p (". count($AdressenArray) ." huizen)");
		
		// Dus niet de laatste pagina en minder dan 15 => niet goed
		if((count($AdressenArray) + count($AdressenArray)) < 15 AND $nextPage) {
			$ErrorMessage[] = $OpdrachtData['naam'] ."; Script vond maar ". (count($AdressenArray) + count($VerlopenArray)) .' huizen op pagina '. $p;
			toLog('error', $OpdrachtID, '', "script vond maar ". (count($AdressenArray) + count($VerlopenArray)) ." huizen; pag. $p");
		}				
		sleep(3);	// Pauzeer even 3 seconden voor de volgende pagina op te vragen
	}
		
	
	echo "<br>\n<br>\n";
	
	if(count($HTMLMessage) > 0 OR count($UpdatePrice) > 0) {		
		$FooterText  = "Google Maps (";
		$FooterText .= "<a href='http://maps.google.nl/maps?q=". urlencode($ScriptURL."extern/showKML_mail.php?regio=$OpdrachtID") ."'>vandaag</a>, ";
		$FooterText .= "<a href='http://maps.google.nl/maps?q=". urlencode($ScriptURL."extern/showKML.php?regio=$OpdrachtID&datum=1") ."'>wijk</a>, ";
		$FooterText .= "<a href='http://maps.google.nl/maps?q=". urlencode($ScriptURL."extern/showKML_prijs.php?regio=$OpdrachtID&datum=1") ."'>prijs</a>) | ";
		$FooterText .= "<a href='". $ScriptURL ."admin/edit_opdrachten.php?id=$OpdrachtID'>Zoekopdracht</a> | ";
		$FooterText .= "<a href='$OpdrachtURL'>funda.nl</a>";
		
		include('include/HTML_TopBottom.php');
		
		$HTMLMail = $HTMLHeader;
		
		if(count($HTMLMessage) > 0) {
			$omslag = round(count($HTMLMessage)/2);
			$KolomEen = array_slice ($HTMLMessage, 0, $omslag);
			$KolomTwee = array_slice ($HTMLMessage, $omslag, $omslag);
			
			$HTMLMail .= "<tr>\n";
			$HTMLMail .= "<td width='50%' valign='top' align='center'>\n";
			$HTMLMail .= implode("\n<p>\n", $KolomEen);
			$HTMLMail .= "</td><td width='50%' valign='top' align='center'>\n";
			if(count($KolomTwee) > 0) {
				$HTMLMail .= implode("\n<p>\n", $KolomTwee);	
			} else {
				$HTMLMail .= "&nbsp;";	
			}
			$HTMLMail .= "</td>\n";
			$HTMLMail .= "</tr>\n";
			$HTMLMail .= "<tr>\n";
			$HTMLMail .= "	<td colspan='2' align='center'>&nbsp;</td>\n";
			$HTMLMail .= "</tr>\n";
			
			$Subject[] = count($HTMLMessage) ." ". (count($HTMLMessage) == 1 ? 'nieuw huis' : 'nieuwe huizen');
		}
		
		if(count($UpdatePrice) > 0) {
			$HTMLMail .= "<tr>\n";
			$HTMLMail .= "<td colspan='2' align='center'>". showBlock("De volgende huizen zijn in prijs gedaald :<p>". implode("<p>", $UpdatePrice)) ."</td>\n";
			$HTMLMail .= "<tr>\n";			
			$HTMLMail .= "<tr>\n";
			$HTMLMail .= "	<td colspan='2' align='center'>&nbsp;</td>\n";
			$HTMLMail .= "</tr>\n";
			
			$Subject[] = count($UpdatePrice) ." ". (count($UpdatePrice) == 1 ? 'huis' : 'huizen') . " in prijs gedaald";
		}
		
		$HTMLMail .= $HTMLPreFooter;
		$HTMLMail .= $HTMLFooter;
		
		if($OpdrachtData['mail'] == 1 AND $debug == 0) {
			$adressen = explode(';', $OpdrachtData['adres']);
			
			foreach($adressen as $key => $adres) {	
				$mail = new PHPMailer;				
				$mail->AddAddress($adres);
				$mail->From     = $ScriptMailAdress;
				$mail->FromName = $ScriptTitle;
				$mail->Subject	= $SubjectPrefix.implode(' en ', $Subject) ." voor '". $OpdrachtData['naam'] ."'";
				$mail->IsHTML(true);
				$mail->Body			= $HTMLMail;
		
				if(!$mail->Send()) {
					echo "Versturen van mail naar $adres is mislukt<br>";
					$ErrorMessage[] = "Het versturen van een mail voor ". $OpdrachtData['naam'] ." is mislukt";
					toLog('error', $OpdrachtID, '', "Kon geen mail versturen naar $adres");
				} else {
					toLog('info', $OpdrachtID, '', "Mail verstuurd naar $adres");
				}
			}
		}
	} else {
		//toLog('info', $OpdrachtID, '', "Geen mail hoeven versturen");
	}
}

if(count($ErrorMessage) > 0 AND $debug == 0) {	
	include('include/HTML_TopBottom.php');
	$HTMLMail = $HTMLHeader;
	$HTMLMail .= showBlock(implode("<br>", $ErrorMessage));
	$HTMLMail .= $HTMLFooter;
	
	$mail = new PHPMailer;
	$mail->From     = $ScriptMailAdress;
	$mail->FromName = $ScriptTitle;
	//$mail->WordWrap = 90;
	$mail->AddAddress($ScriptMailAdress, 'Matthijs');
	$mail->Subject	= $SubjectPrefix."problemen met ".$ScriptTitle;
	$mail->IsHTML(true);
	$mail->Body			= $HTMLMail;
	$mail->Send();	
}
?>