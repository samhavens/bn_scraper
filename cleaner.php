<?php

/*
*  This script opens a db connection to scraper and newcars
*  It deletes "bad" entries from bom_negocio.scraper (bad= no CEP, phone shows up more than once,
*  phone is not a cell phone)
*  Then it takes the listings left, and for each one, finds its distance to a suzuki dealership
*  (this is why it needs to connect to newcars db, to access br_haendler)
*  If that distance is less than 80k (value set in Proc in mysql), it copies that listing to the table
*  suzuki, and adds a column, nearest_dealer, which is set to the value corresponding to the hd_id of the
*  suzuki dealer nearest the listing
*/

const MYSQL_DSN = 'mysql:host=localhost;dbname=scraper;charset=utf8';
const USERNAME  = 'revmaker';
const PASS      = 'revmaker';


//scraper db
try {
	$db = new PDO(MYSQL_DSN, USERNAME, PASS);
} catch(PDOException $ex) {
	echo "Yo dawg, I heard you liked errors! Good news!" 
	, PHP_EOL , "something went wrong connecting to SCRAPER database: " . $ex;
}

//newcars db
try {
	$db_nc = new PDO('mysql:host=localhost;dbname=newcars;charset=utf8', USERNAME, PASS);
} catch(PDOException $ex) {
	echo "Yo dawg, I heard you liked errors! Good news!" 
	, PHP_EOL , "something went wrong connecting to NEWCARS database: " . $ex;
}

function deleteNoCEP($db)
{
	$sql = 
	"DELETE FROM bom_negocio WHERE id IN (
		SELECT * FROM (
			(SELECT bom_negocio.id FROM bom_negocio WHERE cep IS NULL OR  cep='')
		) as bullshit
	);";

	$delete = $db->prepare($sql);
	return $delete->execute();
}

function deleteDupePhone($db)
{
	$sql = 
	"DELETE FROM bom_negocio WHERE id IN (
		SELECT * FROM 
			(SELECT bom_negocio.id
			FROM bom_negocio
			INNER JOIN (
				SELECT phone 
				FROM bom_negocio 
				group by phone having count(*) > 1
			) dup ON bom_negocio.phone = dup.phone
			ORDER BY bom_negocio.phone
		) as p
	)";

	$delete = $db->prepare($sql);
	return $delete->execute();
}

function phoneDigit($phone)
{
    $phone_number = preg_replace("/\D/", '', $phone);

    $ddd = substr($phone_number, 0, 2);
    $tel = substr($phone_number, 2, strlen($phone_number) - 2);

    $phone_split = array('ddd' => $ddd, 'tel' => $tel);

    return $phone_split['tel'][0];
}

function deleteNonCellPhones($db)
{
	
	$sql = "SELECT id, phone FROM bom_negocio ORDER BY id;";
	$query = $db->query($sql);

	$phone_arrays = $query->fetchAll(PDO::FETCH_ASSOC);

	$bad_ids = array();

	foreach ($phone_arrays as $phone_array) {
		$id          = $phone_array['id'];
		$phone       = $phone_array['phone'];
		$first_digit = phoneDigit($phone);

		if ($first_digit != 9) {
			$sql = "DELETE FROM bom_negocio WHERE id=" . $id . ";";
			$delete = $db->prepare($sql);
			if ($delete->execute()) {
				//good
			} else {
				$bad_ids[] = $id;
			}
		}
	}

	return $bad_ids;
}

function ifNearDealerAddToSuzukiTable($db, $db_nc)
{
	$sql = "SELECT id, name, phone, cep, title, description, url FROM bom_negocio ORDER BY id;";
	$query = $db->query($sql);

	$cep_arrays = $query->fetchAll(PDO::FETCH_ASSOC);

	$bad_ids = array();
	foreach ($cep_arrays as $cep_array) {
		$id          = $cep_array['id'];
		$name        = $cep_array['name'];
		$phone       = $cep_array['phone'];
		$cep         = $cep_array['cep'];
		$title       = $cep_array['title'];
		$description = $cep_array['description'];
		$url         = $cep_array['url'];

		$proc_call = $db_nc->prepare('CALL P_br_suzuki_distance(:postal_code_id);');
		$proc_call->bindParam(':postal_code_id', $cep, PDO::PARAM_STR);

	    try
	    {
	        $proc_call->execute();
	        $dealer_info = $proc_call->fetchAll(PDO::FETCH_ASSOC);
	    }
	    catch(PDOException $ex)
	    {
	        echo '***ERROR : An Error occured fetching dealer records:' . "\n" . $ex->getMessage() . "\n";
	        exit(1);
	    }
	    $proc_call->closeCursor(); // not sure why this is necessary, but will throw exception without

	    //fix unnecesary nesting of array. don't use helper function because slightly different
	    if(!empty($dealer_info))
	    {
	        $dealer_info = $dealer_info[0];
	        $nearest_dealer = $dealer_info["hd_id"];

	        $sql = "INSERT INTO suzuki (name, phone, cep, title, description, url, nearest_dealer)
	        	VALUES (:name, :phone, :cep, :title, :description, :url, :nearest_dealer)";

	        $update = $db->prepare($sql);
	        try {
	      	    $update->execute(array(
	        	':name' => $name, 
	        	':phone' => $phone, 
	        	':cep' => $cep, 
	        	':title' => $title, 
	        	':description' => $description, 
	        	':url' => $url, 
	        	':nearest_dealer' => $nearest_dealer
	        	));  	
	        } catch(PDOException $ex) {
	        	echo 'something went wrong adding the record to the suzuki db' , $ex;
	        }

	    }

	}

}



//uncomment to delete stuff:
//MAKE A BACKUP OF THE TABLE FIRST!!!

$bad_ids = deleteNonCellPhones($db);

if(!empty($bad_ids)) {
	echo 'some entries with non-cell phones could be deleted!', PHP_EOL;
	var_dump($bad_ids);
}

deleteDupePhone($db);
deleteNoCEP($db);

ifNearDealerAddToSuzukiTable($db, $db_nc);
