<?php

// scrape bomnegocio

require_once(__DIR__ . '/tesseract-ocr-for-php/TesseractOCR/TesseractOCR.php');

const BASE_URL   = 'http://sp.bomnegocio.com';
const WRITE_TO   = 'contacts.txt';

$recent          = "https://api.import.io/store/data/14e436c2-07cf-4841-8c11-baf42faf87ff/_query?input/webpage/url=http%3A%2F%2Fsp.bomnegocio.com%2Fveiculos%2Fcarros&_user=95360e76-2882-4ac1-a387-5385f42e608b&_apikey=";
$mitsubishi_only = 'https://api.import.io/store/data/521d4254-5375-416a-b89a-7c66d5f4e198/_query?input/webpage/url=http%3A%2F%2Fsp.bomnegocio.com%2Fveiculos%2Fcarros%2Fmitsubishi%2Fpajero&_user=95360e76-2882-4ac1-a387-5385f42e608b&_apikey=';

$contacts = fopen(WRITE_TO, 'r+');

# Use the Curl extension to query import.io and get back a page of results
$url = $mitsubishi_only . "/ItGfSALPah1PN6xYup/vStUdxpyJ2M3FcehKDb7apCXnO2ottCrSpTmBxpwMh64HtH2FJWm77FnzugorJRqiA==";
$ch = curl_init();
$timeout = 5;
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
$json = curl_exec($ch);
curl_close($ch);

$json = json_decode($json, true);


$data = $json["results"];

foreach ($data as $datum) {
	$url = $datum['whole_area_link_link'];
	$ch = curl_init();
	$timeout = 5;
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	$html = curl_exec($ch);
	curl_close($ch);

	$dom = new DOMDocument();
	@$dom->loadHTML($html);

	// this is to get Name:
	$xpath = new DOMXpath($dom);
	$elements = $xpath->query('/html/body/div/div/div/div/div/div/div/div/ul/li/div/div/p');
	foreach ($elements as $element) {
		$name = ucwords($element->nodeValue);
    	fwrite($contacts, $name . " : ");
    	break;
    }

	//this is to get phone

	$span_children = $dom->getElementById("visible_phone")->childNodes;
	foreach ($span_children as $img) {
		$src = $img->getAttribute("src");

		copy(BASE_URL . $src, 'phone.gif');
		
		$tesseract = new TesseractOCR('phone.gif');
		$phone = $tesseract->recognize() . PHP_EOL;
		fwrite($contacts, $phone);
	}

	fwrite($contacts, "from url: " . $url . PHP_EOL . PHP_EOL);
	
}

fclose($contacts);
?>