<?php
// SCRIPT PHP DE RECUPERATION ET MISE EN CACHE DES DATA ECOWATT
// NECESSITE JUSTE LES DROITS EN ECRITURE DANS LE DOSSIER
// NOVEMBRE 2022

$dataArray = array();
if(file_exists("cache-".date('H').".json"))
{
	// RECUPERATION DES DONNEES DEPUIS LE CACHE
	$jsonData = file_get_contents("cache-".date('H').".json");
	if($dataArray = json_decode($jsonData,true))
	{
		// SI LE JOUR EST DIFFERENT
		// ON FORCE LA MISE A JOUR DU CACHE
		if($dataArray[0]['day']!=date('Y-m-d'))
			$dataArray = array();
	}
}

if(empty($dataArray))
{
	// A RECUPERER DEPUIS L INTERFACE RTE
	// DANS L ONGLET "MES APPLICATIONS"
	// https://data.rte-france.com/group/guest/apps
	// SELECTIONNEZ VOTRE APP ET RECUPEREZ VOTRE CLE BASE64
	// ID Client | ID Secret ---> bouton "Copier en base 64"
	$_BASE64KEY = "...";


	// RECUPERATION DU TOKEN OAUTH
	$url = "https://digital.iservices.rte-france.com/token/oauth/";
	$header = array(
		"Content-Type: application/x-www-form-urlencoded",
		"Authorization: Basic ".$_BASE64KEY
	);

	$curl = curl_init();
	curl_setopt($curl,CURLOPT_URL,$url);
	curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl,CURLOPT_HTTPHEADER,$header);

	$response = curl_exec($curl);
	if(!empty($response))
	{
		// RECUPERATION DU TOKEN EN JSON
		// VALABLE 2H
		$auth = json_decode($response);

		// SI TOKEN RECUPERE
		if(!empty($auth->access_token))
		{
			// RECUPERATION DES DONNEES ECOWATT
			// DEPUIS L API RTE
			$url = "https://digital.iservices.rte-france.com/open_api/ecowatt/v4/signals";
			$header = array(
				"Content-Type: application/x-www-form-urlencoded",
				"Authorization: Bearer ".$auth->access_token
			);

			$curl = curl_init();
			curl_setopt($curl,CURLOPT_URL,$url);
			curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl,CURLOPT_HTTPHEADER,$header);

			$jsonData = curl_exec($curl);

			// FORMATAGE DE LA DATA
			if($data = json_decode($jsonData))
			{
				if(!empty($data->signals))
				{
					for($d=0;$d<count($data->signals);$d++)
					{
						$dataArray[$d] = array(
							'day'		=> substr($data->signals[$d]->jour,0,10),
							'message' 	=> $data->signals[$d]->message,
							'status' 	=> $data->signals[$d]->dvalue,
							'hours' 	=> array()
						);
						for($h=0;$h<24;$h++){
							$dataArray[$d]['hours'][$h] = $data->signals[$d]->values[$h]->hvalue;
						}
					}
				}
			}
			// ENREGISTREMENT DANS UN CACHE PAR HEURE
			file_put_contents("cache-".date('H').".json",json_encode($dataArray),LOCK_EX);
		}
	}
}



if(!empty($dataArray))
{
    // UTILISEZ CA COMME BON VOUS SEMBLE :)
	var_dump($dataArray);
}
else
	echo 'no_data';
