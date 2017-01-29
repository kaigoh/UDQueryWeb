<?php
/**
 *
 * PHP sample for accessing data via UDQueryWeb
 *
 * @author Kai Gohegan
 * @copyright Kai Gohegan, 2017
 * @license GNU General Public License v3.0
 *
 */

// Include the library in your script:
include("../libUDQueryWeb.php");

// Connect to UniData via UDQueryWeb:
// - First parameter is the UDQueryWeb server address, by default it is localhost with port 8098
// - Second parameter is the path to the UDQueryWeb binaries, this is used to keep the server alive in the background
// - Third parameter is the IP address of the UniData server
// - Fourth parameter is the account path
// - Fifth parameter is the account username
// - Sixth parameter is the account password
$udq = new kaigoh\UDQueryWeb\libUDQueryWeb("http://127.0.0.1:8098", "/path/to/udqueryweb/", "<unidata server address>", "<unidata account path>", "<unidata username>", "<unidata password>");
			                        
// Now we are connected, select the fields we want to fetch from the server:
// - First parameter is the UniData file we wish to query
// - Second parameter is an array of (valid) field names that exist in the file's dictionary
$dictionary = $udq->getDictionary("CONTACTS", array("NAME", "ADDRESS", "JOBTITLE", "EMAIL", "PHONE"));

// Now run the query:
// - First parameter is the UniData query we wish to execute - NOTE: This does NOT escape your query ala MySQL! Don't send unfiltered user input into your query!
// - Second parameter is the file we wish to query
// - Third (optional) parameter is our dictionary we created above
$results = $udq->runQuery("LIST CONTACTS", "CONTACTS", $dictionary);
			                        
// Process the data:
// - You'll find the number of results your query returned in $results->num_records()
if($results->num_records() > 0)
{

    // Now iterate over each record:
    foreach($results->result() as $contact)
    {
        echo $contact->name; // Or $contact->NAME if you prefer...
        echo $contact->address;
        echo $contact->jobtitle;
        echo $contact->email;
        echo $contact->phone;
    }

}

?>