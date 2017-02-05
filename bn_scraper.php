<?php

// scrape bomnegocio

require_once(__DIR__ . '/tesseract-ocr-for-php/TesseractOCR/TesseractOCR.php');

const SP_BASE_URL = 'http://sp.bomnegocio.com';
const RJ_BASE_URL = 'http://rj.bomnegocio.com';

const SC_BASE_URL = 'http://sc.bomnegocio.com';
const RS_BASE_URL = 'http://rs.bomnegocio.com';
const MG_BASE_URL = 'http://mg.bomnegocio.com';
const RN_BASE_URL = 'http://rn.bomnegocio.com';
const PR_BASE_URL = 'http://pr.bomnegocio.com';
const BA_BASE_URL = 'http://ba.bomnegocio.com';

const DEFAULT_SEARCH = '/veiculos/carros/';

const MYSQL_DSN = $_ENV['DSN'];
const USERNAME  = $_ENV['USERNAME'];
const PASS      = $_ENV['PASS'];

const SLEEP_TIME = 4;

$base_URLs = array(
	BA_BASE_URL, 
	RJ_BASE_URL, 
	SC_BASE_URL, 
	RS_BASE_URL,
	MG_BASE_URL,
	RN_BASE_URL,
	PR_BASE_URL,
	SP_BASE_URL
	);

// $url_to_location = array (
// 	SP_BASE_URL => "São Paulo",
// 	RJ_BASE_URL=> "Rio de Janeiro"
// 	);

// restricts the year to 2011-2015 and no ads from dealers:
$restrict_year_and_no_dealers = '?f=p&re=29&rs=33';

// go to the 4th page when available
$page_numbers = array('', '&o=2');//, '&o=3', '&o=4');

// there are a shitload of corollas and civics and cruzes
// get 2 pages (well, 83 ads each) of:

$multi_page_makes_and_models = array(
	// 'vw-volkswagen/golf',
	// 'ford/fiesta',
	// 'hyundai/i30',
	// 'honda/city',
	// 'honda/fit',
	// 'vw-volkswagen/polo'
	'toyota/corolla',
	'honda/civic',
	'ford/focus'
	);

// to make sure they aren't too old, only get like the first page of each of these.
$just_get_a_few = array(
	// 'suzuki/swift',
	// 'suzuki/sx4',
	// 'audi/a1',
	// 'citron/ds3',
	// 'mini/cooper'
	'peugeot/308',
	'gm-chevrolet/cruze',
	'gm-chevrolet/s10',
	'vw-volkswagen/jetta',
	'fiat/500',
	'fiat/punto',
	'fiat/bravo',
	'hyundai/tucson',
	'hyundai/elantra',
	'nissan/sentra',
	'nissan/livina',
	'renault/duster',
	'kia-motors/cerato',
	'mitsubishi/asx',
	'citroen/aircross'
	);

//debugging function since var_dump doesnt work with DOMNodeLists
function debugDom($dom_node_list)
{
	$temp_dom = new DOMDocument();
	foreach($dom_node_list as $n) {
		$temp_dom->appendChild($temp_dom->importNode($n,true));
	}
	print_r($temp_dom->saveHTML());
}

function curlGetPage($url)
{
	echo 'fetching ' . $url . PHP_EOL;
	$ch = curl_init();
	$timeout = 5;
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	$html = curl_exec($ch);
	curl_close($ch);

	return $html;
}

function findListings($base_URL, $make_model, $restrict_year_and_no_dealers, $page_number='')
{
	/*
	* Loop through the make/models list
	*/
		$url = $base_URL . DEFAULT_SEARCH . $make_model . $restrict_year_and_no_dealers . $page_number;

		$search_results_html = curlGetPage($url);
		$search_results_html = mb_convert_encoding($search_results_html, 'HTML-ENTITIES', "UTF-8"); 

		// the @ suppresses errors if, somehow, the HTML isn't well-formed (it probably isn't... brazil)
		$results_dom = new DOMDocument();
		$results_dom->preserve_whitespace = false;
		@$results_dom->loadHTML($search_results_html);

		//get the uls
		$uls = $results_dom->getElementsByTagName('ul');

		//find the right ul
		foreach ($uls as $ul) {
			//we want ul#list_adsBN, but the first one is an ad, so check nodepath
			$right_class = $ul->getAttribute('class') == 'list_adsBN';
			//$right_path = $ul->getNodePath() == '/html/body/div[3]/div[2]/div[1]/div[1]/div/div[2]/div[2]/div/div[3]/ul';
			$right_path = true;

			if( $right_class AND $right_path) {
				//find all links, should be 50 or less
				foreach($ul->getElementsByTagName('a') as $node) {
					//make sure it isn't a link to an image
					if($node->getAttribute('class') == 'whole_area_link') {
						//add the link to the list
						$href = $node->getAttribute('href');
						// $pages_to_hit[$href] = $base_URL;
						//Pages to hit is an array that looks like
						// (listing_URL => base_URL)
						// this is useful because base_URL is needed for
						// scrapePhone function
					}
				}
			}
		}
	return $href;
}

function pagesToHit($base_URLs)
{
	global $multi_page_makes_and_models;
	global $just_get_a_few;
	global $restrict_year_and_no_dealers;
	global $page_numbers;

	$pages_to_hit = array();

	foreach ($base_URLs as $base_URL) {
		//go to the main search results page for each make and model
		foreach ($multi_page_makes_and_models as $make_model) {
			// Loop through the make/models list
			foreach($page_numbers as $page_number) {
				
				//$href = findListings($base_URL, $make_model, $restrict_year_and_no_dealers, $page_number);
				//$pages_to_hit[$href] = $base_URL;

				$url = $base_URL . DEFAULT_SEARCH . $make_model . $restrict_year_and_no_dealers . $page_number;

				$search_results_html = curlGetPage($url);
				$search_results_html = mb_convert_encoding($search_results_html, 'HTML-ENTITIES', "UTF-8"); 

				// the @ suppresses errors if, somehow, the HTML isn't well-formed (it probably isn't... brazil)
				$results_dom = new DOMDocument();
				$results_dom->preserve_whitespace = false;
				@$results_dom->loadHTML($search_results_html);

				//get the uls
				$uls = $results_dom->getElementsByTagName('ul');

				//find the right ul
				foreach ($uls as $ul) {
					//we want ul#list_adsBN, but the first one is an ad, so check nodepath
					$right_class = $ul->getAttribute('class') == 'list_adsBN';
					//$right_path = $ul->getNodePath() == '/html/body/div[3]/div[2]/div[1]/div[1]/div/div[2]/div[2]/div/div[3]/ul';
					$right_path = true;

					if( $right_class AND $right_path) {
						//find all links, should be 50 or less
						foreach($ul->getElementsByTagName('a') as $node) {
							//make sure it isn't a link to an image
							if($node->getAttribute('class') == 'whole_area_link') {
								//add the link to the list
								$href = $node->getAttribute('href');
								$pages_to_hit[$href] = $base_URL;
								//Pages to hit is an array that looks like
								// (listing_URL => base_URL)
								// this is useful because base_URL is needed for
								// scrapePhone function
							}
						}
					}
				}
				
				sleep(SLEEP_TIME);
			}
		}
		foreach ($just_get_a_few as $make_model) {
			// $href = findListings($base_URL, $make_model, $restrict_year_and_no_dealers);
			// $pages_to_hit[$href] = $base_URL;
			$url = $base_URL . DEFAULT_SEARCH . $make_model . $restrict_year_and_no_dealers;

				$search_results_html = curlGetPage($url);
				$search_results_html = mb_convert_encoding($search_results_html, 'HTML-ENTITIES', "UTF-8"); 

				// the @ suppresses errors if, somehow, the HTML isn't well-formed (it probably isn't... brazil)
				$results_dom = new DOMDocument();
				$results_dom->preserve_whitespace = false;
				@$results_dom->loadHTML($search_results_html);

				//get the uls
				$uls = $results_dom->getElementsByTagName('ul');

				//find the right ul
				foreach ($uls as $ul) {
					//we want ul#list_adsBN, but the first one is an ad, so check nodepath
					$right_class = $ul->getAttribute('class') == 'list_adsBN';
					//$right_path = $ul->getNodePath() == '/html/body/div[3]/div[2]/div[1]/div[1]/div/div[2]/div[2]/div/div[3]/ul';
					$right_path = true;

					if( $right_class AND $right_path) {
						//find all links, should be 50 or less
						foreach($ul->getElementsByTagName('a') as $node) {
							//make sure it isn't a link to an image
							if($node->getAttribute('class') == 'whole_area_link') {
								//add the link to the list
								$href = $node->getAttribute('href');
								$pages_to_hit[$href] = $base_URL;
								//Pages to hit is an array that looks like
								// (listing_URL => base_URL)
								// this is useful because base_URL is needed for
								// scrapePhone function
							}
						}
					}
				}
			sleep(SLEEP_TIME);
		}
	}
	return $pages_to_hit;
}

function scrapeName($dom)
{
	$name_p = $dom->getElementById('visible_phone')->parentNode->parentNode->firstChild;
	$name = $name_p->nodeValue;
	$name = preg_replace('/(\s)+/', ' ', $name);
	$name = trim($name);
	$name = str_replace('.', ' ', $name);
	$name = ucwords($name);

	return $name;
}

function scrapePhone($dom, $base_URL)
{
	$span_children = $dom->getElementById("visible_phone")->childNodes;
	foreach ($span_children as $img) {
		$src = $img->getAttribute("src");
		//get image of phone number
		copy($base_URL . $src, 'phone.gif');
		
		//OCR that shit
		$tesseract = new TesseractOCR('phone.gif');
		$phone = $tesseract->recognize();
	}

	return $phone;
}

function scrapeTitle($dom)
{
	$title = $dom->getElementById('ad_title')->nodeValue;
	//remove extra whitespace:
	$title = preg_replace('/(\s)+/', ' ', $title);

	return $title;
}

function scrapeDescriptionAndCEP($dom)
{
	// by looking at EVERY DIV and matching classes

	$divs = $dom->getElementsByTagName('div');
	foreach($divs as $div) {

		$description_class = 'ad-description mb20px';
		$cep_class = 'ad-location mb20px';

		if ($div->getAttribute('class') == $description_class ) {
			$ps = $div->childNodes;

			foreach ($ps as $p) {
				//find the right one
				// can only check tagname if it is a DOMElement
				if( get_class($p) == 'DOMElement' AND $p->tagName == 'p'){
					$description = $p->nodeValue;
				 	//remove extra whitespace:
				 	$description = preg_replace('/(\s)+/', ' ', $description);
				 }
			}
			//CEP
		} elseif ($div->getAttribute('class') == $cep_class) {
			$cep = $div->nodeValue; //no idea why you dont have to get child nodes.

			//format
			$cep = preg_replace('/[^0-9 -]/', '', $cep);
			$cep = trim($cep);
		}
	}

	return array($description, $cep);
}

function insertScrapedLead($db, $name, $phone, $cep, $title, $description, $url)
{
	$sql = "INSERT INTO bom_negocio (name, phone, cep, title, description, url) 
						VALUES (:name, :phone, :cep, :title, :description, :url);";
	$update = $db->prepare($sql);

	return $update->execute(array(':name' => $name,
		':phone' => $phone,
		':cep' => $cep,
		':title' => $title,
		':description' => $description,
		':url' => $url
		));
}


try {
	$db = new PDO(MYSQL_DSN, USERNAME, PASS);
} catch(PDOException $ex) {
	echo "Yo dawg, I heard you liked errors! Good news!" , PHP_EOL , "something went wrong connecting to database: " . $ex;
}


// ////////////////////////////
// Script starts here:
// ////////////////////////////


//gather a list of pages to hit
//pretty ugly function

$pages_to_hit = pagesToHit($base_URLs);


//old:
// $pages_to_hit = array();

// foreach ($base_URLs as $base_URL) {
// 	//go to the main search results page for each make and model
// 	foreach ($multi_page_makes_and_models as $make_model) {
// 		// Loop through the make/models list
// 		foreach($page_numbers as $page_number) {
			
// 			$href = findListings($base_URL, $make_model, $restrict_year_and_no_dealers, $page_number);
// 			$pages_to_hit[$href] = $base_URL;
// 			sleep(SLEEP_TIME);
// 		}
// 	}
// 	foreach ($just_get_a_few as $make_model) {
// 		$href = findListings($base_URL, $make_model, $restrict_year_and_no_dealers);
// 		$pages_to_hit[$href] = $base_URL;
// 		sleep(SLEEP_TIME);
// 	}
// }



echo 'about to scrape ' . count($pages_to_hit) . ' listings.' . PHP_EOL;

foreach($pages_to_hit as $listing => $base_URL) {

	$html = curlGetPage($listing);
	$html = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");
	$dom = new DOMDocument();
	$dom->preserve_whitespace = false;
	@$dom->loadHTML($html);

	// get $name:
	$name = scrapeName($dom);

    // get $phone
	$phone = scrapePhone($dom, $base_URL);

	//get URL
	$url = $listing;

	// now $title:
	$title = scrapeTitle($dom);

	// now $description and CEP
	list($description, $cep) = scrapeDescriptionAndCEP($dom);

	// insert into db
	// check if phone got set (some listings dont have it)
	// if it didn't get set, screw it, we dont need it
	if(isset($phone)) {
		if(insertScrapedLead($db, $name, $phone, $cep, $title, $description, $url)) {
			echo PHP_EOL . '***success inserting the following info:' . PHP_EOL . $name . ' ' . $phone . ' '.  $cep . ' '.  $title . PHP_EOL;
		}
		else {
			echo PHP_EOL . '***something went wrong!!' . PHP_EOL;
		}
	}

	// don't go too fast or they'll ban you
	sleep(SLEEP_TIME);
} 
