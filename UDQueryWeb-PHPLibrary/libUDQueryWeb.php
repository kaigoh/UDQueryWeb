<?php
/**
 *
 * PHP library for interacting with UniData databases via UDQueryWeb server
 *
 * @author Kai Gohegan
 * @copyright Kai Gohegan, 2017
 * @license GNU General Public License v3.0
 *
 */
namespace kaigoh\UDQueryWeb;

/**
 * Class that interfaces with the UDQueryWeb server
 */
class libUDQueryWeb
{

    // Server fields
    private $_udqUrl = false;
    private $_udqPath = false;
    private $_server = false;
    private $_path = false;
    private $_username = false;
    private $_password = false;

    function libUDQueryWeb($udqUrl = false, $udqPath = false, $server = false, $path = false, $username = false, $password = false)
    {
        if($udqUrl !== false && $udqPath !== false && $server !== false && $path !== false && $username !== false && $password !== false)
        {
            $this->_udqUrl = rtrim($udqUrl, "/")."/";
            $this->_udqPath = $udqPath;
            $this->_server = $server;
            $this->_path = $path;
            $this->_username = $username;
            $this->_password = $password;
        } else {
            // Error checking
            if($udqUrl === false)
            {
                throw new Exception("UDQueryWeb server address not supplied");
            }
            if($udqPath === false)
            {
                throw new Exception("UDQueryWeb server path not supplied");
            }
            if($server === false)
            {
                throw new Exception("Server address not supplied");
            }
            if($path === false)
            {
                throw new Exception("Server path not supplied");
            }
            if($username === false)
            {
                throw new Exception("Account username not supplied");
            }
            if($password === false)
            {
                throw new Exception("Account password not supplied");
            }
        }
    }
    
    private function _checkServerStatus()
    {
        // Check the UDQueryWeb server is up
		$status = file_get_contents($this->_udqUrl."status/");
		if($status !== "up")
		{
			// Server is not running, so try and start it up
			exec("cd ".$this->_udqPath." && screen -d -m -S rtsudqueryweb mono UDQueryWeb.exe");
			sleep(3);
			// Check server is running now...
			$statusRetry = file_get_contents($this->_udqUrl."status/");
			if($statusRetry !== "up")
			{
				// Still not running, error out
				return false;
			} else {
			    return true;
			}
			return false;
		} else {
		    return true;
		}
		return false;
    }
    
    private function _rawQuery($q = false)
    {
        // UDQueryWeb server status
        $serverStatus = $this->_checkServerStatus();
        
        // Query has been supplied and is valid, plus server is up?
        if($q !== false && strlen($q) > 0 && $serverStatus === true)
        {
            //Set request variables
            $fields = array(
                "server" => rawurlencode($this->_server),
                "username" => rawurlencode($this->_username),
                "password" => rawurlencode($this->_password),
                "path" => rawurlencode($this->_path),
                "query" => rawurlencode($q),
            );
            
            // Build the request URI
            $url = $this->_udqUrl."rawquery/?".http_build_query($fields);

            // Query the server
            $xmlString = file_get_contents($url);

            // Check the server response is valid
            if(strlen(trim($xmlString)) > 0)
            {
                // Get XML response
                $xml = simplexml_load_string($xmlString);

                // XML valid?
                if($xml !== false)
                {
                    if((string)$xml->response === "success")
                    {
						// Query was good, so decompress results...
						$data = base64_decode((string)$xml->message);
						if(strlen($data) > 0)
						{
							$decompressed = gzinflate(substr($data, 10, -8));
							if($decompressed !== false)
							{
								return $decompressed;
							} else {
								throw new Exception("Unable to decompress UDQueryWeb server response");
							}
						} else {
							// Server has not found any matching records, so return an empty array
							return array();
						}
                    } else {
                        throw new Exception((string)$xml->message);
                    }
                } else {
                    throw new Exception("UDQueryWeb server response was not valid XML");
                }
            } else {
                throw new Exception("UDQueryWeb server response was empty");
            }
        } else {
            // Error checking
            if($q === false && strlen($q) == 0)
            {
                throw new Exception("No query supplied");
            }
            if($serverStatus === false)
            {
                throw new Exception("UDQueryWeb server is not running");
            }
        }
    }
    
    public function runQuery($q, $f, $dictionary = false)
    {
        // UDQueryWeb server status
        $serverStatus = $this->_checkServerStatus();
        
        // Query has been supplied and is valid, plus server is up?
        if($serverStatus === true)
        {
        	
        	// Check dictionary
        	if($dictionary === false)
        	{
        		$dictionary = $this->getDictionary($f);
        	}
        	
        	// Process the fields
        	$fieldsProcessed = array();
        	foreach($dictionary as $field)
        	{
        		$fieldsProcessed[] = $field["key"];
        	}
        	
        	// Clean the query
        	$q = trim(str_replace(array("HDR.SUPP", "COUNT.SUP", "VERTICAL", "DBL.SPC", "NO.SPLIT"), "", $q));
        	$q .= " HDR.SUPP COUNT.SUP VERTICAL DBL.SPC NO.SPLIT";
        	
            //Set request variables
            $fields = array(
                "server" => rawurlencode($this->_server),
                "username" => rawurlencode($this->_username),
                "password" => rawurlencode($this->_password),
                "path" => rawurlencode($this->_path),
                "file" => rawurlencode($f),
                "query" => rawurlencode($q),
                "fields" => rawurlencode(implode("|", $fieldsProcessed)),
            );
            
            // Build the request URI
            $url = $this->_udqUrl."xmlquery/?".http_build_query($fields);
            
            // Query the server
            $xmlString = file_get_contents($url);

            // Check the server response is valid
            if(strlen(trim($xmlString)) > 0)
            {
                // Get XML response
                $xml = simplexml_load_string($xmlString);

                // XML valid?
                if($xml !== false)
                {
                    if((string)$xml->response === "success")
                    {
						// Query was good, so decompress results...
						$data = base64_decode((string)$xml->message);
						if(strlen($data) > 0)
						{
							$decompressed = gzinflate(substr($data, 10, -8));
							if($decompressed !== false)
							{
								$recordSet = simplexml_load_string($decompressed);
								$dataset = array();
								if(isset($recordSet->Records))
								{
									foreach($recordSet->Records[0] as $record)
									{
										$recordData = array();
										$recordData["_raw"] = $record;
										$recordData["_dictionary"] = $dictionary;
										$recordData["_id"] = (string)$record->ID;
										$recordData["_multiValue"] = array();
										foreach($record->Columns[0] as $column)
										{
											foreach($recordData["_dictionary"] as $entry)
											{
												if((string)$column->Key === $entry["key"])
												{
												    // Multivalue Record?
												    if(isset($column->MultiValue->string))
												    {
                                                        $mvClean = array();
                                                        foreach($column->MultiValue->string as $mValue)
                                                        {
                                                            $mvClean[] = (string)$mValue;
                                                        }
                                                        $recordData[$entry["_cleanKeyName"]] = $mvClean;
                                                        $recordData["_multiValue"][$entry["_cleanKeyName"]] = true;
												    } else {
												        $recordData[$entry["_cleanKeyName"]] = (string)$column->Value;
												    }
												}
											}
										}
										$dataset[] = new UDQueryWebRecord($recordData);
									}
								}
								return new UDQueryWebResultSet($dictionary, $dataset);
							} else {
								throw new Exception("Unable to decompress UDQueryWeb server response");
							}
						} else {
							// Server has not found any matching records, so return an empty array
							return array();
						}
                    } else {
                        throw new Exception((string)$xml->message);
                    }
                } else {
                    throw new Exception("UDQueryWeb server response was not valid XML");
                }
            } else {
                throw new Exception("UDQueryWeb server response was empty");
            }
        } else {
            // Error checking
            if($q === false && strlen($q) == 0)
            {
                throw new Exception("No query supplied");
            }
            if($serverStatus === false)
            {
                throw new Exception("UDQueryWeb server is not running");
            }
        }
    }
    
    private function _processDictionary($file, $fields = false)
    {
        try
        {
            // Get the UniData dictionary for the supplied file
            $response = $this->_rawQuery("LIST DICT ".$file." WITH TYP = \"D\" OR WITH TYP = \"I\" OR WITH TYP = \"V\" OR WITH TYP = \"PH\" HDR.SUPP COUNT.SUP VERTICAL");
            
            // Match the field entries against the regular expression
            $matches = array();
            preg_match_all("/(((Key) *(?P<key>.*))\r*\n*((TYP) *(?P<type>.*))\r*\n*((LOC) *(?P<location>.*))\r*\n*((CONV) *(?P<conversion>.*))\r*\n*((NAME) *(?P<name>.*))\r*\n*((FORMAT) *(?P<format>.*))\r*\n*((SM) *(?P<sm>.*))\r*\n*((ASSOC) *(?P<association>.*)))/", $response, $matches, PREG_SET_ORDER);
            
            // Clean up the array, removing any unnecessary entries
            foreach($matches as &$match)
            {
                foreach($match as $key => $value)
                {
                    // Remove illegal characters from the key
                    //$key = preg_replace("#[[:punct:]]#", "_", $key);
                    
                    if(is_int($key))
                    {
                        unset($match[$key]);
                    } else {
                        $match[$key] = trim($value);
                    }
                }
                
                // Give the field a "clean" name...
                $match["_cleanKeyName"] = strtolower(preg_replace("/[^A-Za-z0-9]/", "_", trim($match["key"])));
                
            }
            
            // If we have an array of fields,
            // filter out the ones we don't want...
            if($fields !== false && is_array($fields))
            {
                $filteredMatches = array();
                foreach($fields as $field => $rename)
                {
                    foreach($matches as &$match)
                    {
                    	// So we can rename the fields (similar to SQL SELECT x AS y), check the array key is numeric...
						if(is_int($field))
						{
							if($rename === $match["key"])
							{
								$filteredMatches[] = $match;
							}
						} else {
							// If not, rename the field!
							if($field === $match["key"])
							{
								// Clean the key name
								$match["_cleanKeyName"] = preg_replace("/[^A-Za-z0-9]/", "_", trim($rename));
								$filteredMatches[] = $match;
							}
						}
                    }
                }
                $matches = $filteredMatches;
            }
            
            // If we have any matches, return the array
            if(count($matches) > 0)
            {
                return $matches;
            } else {
                return false;
            }
        }
        catch(Exception $ex)
        {
        	var_dump($ex);
            return false;
        }
    }
    
    private static function _topHeavyArray($a, $b)
    {
    	return strlen($b["name"]) - strlen($a["name"]);
    }
    
    /**
     * Get the dictionary for the given file
     */
    public function getDictionary($file = false, $fields = false)
    {
    	return $this->_processDictionary($file, $fields);
    }
    
    /**
     * Get the result set as a UDQueryResultSet
     */
    public function get($file = false, $query = false, $fields = false)
    {
        if($file !== false && strlen($file) > 0 && $query !== false && strlen($query) > 0)
        {
            try
            {
                // Get the dictionary
                $dictionary = $this->_processDictionary($file, $fields);
                
                // Run the query
                $response = $this->_rawQuery(trim($query)." HDR.SUPP COUNT.SUP VERTICAL DBL.SPC NO.SPLIT");
                
                if($dictionary !== false && $response !== false)
                {
                    
                    // Get the @ID field
                    $idField = false;
                    $idLocation = false;
                    foreach($dictionary as $dictEntry)
                    {
                        if($dictEntry["key"] === "@ID")
                        {
                            $idField = $dictEntry["name"];
                            $idLocation = $dictEntry["location"];
                        }
                    }
                    
					// Copy the dictionary before we sort it...
					$dictionaryOriginal = $dictionary;
					
					// Sort the dictionary so that the fields with the longest names
					// are on the top of the array. This is so we don't get false matches
					// where a field with a shorter but similar name gets matched when a
					// longer name is the correct one.
					usort($dictionary, array("UDQueryWeb", "_topHeavyArray"));
					
					// Is the response an empty array? This denotes that the server ran the query,
					// but returned no matching records
					if(!is_array($response) && strlen($response) > 0)
					{
						
						// Create array to hold the result set
						$recordSet = array();
						
						// Process the results...
						
						// Remove any duplicate lines from the response
						$lastLine = null;
						$cleanResponse = "";
						foreach(explode(PHP_EOL, $response) as $line)
						{
						    if($line !== $lastLine)
						    {
						        $cleanResponse .= $line.PHP_EOL;
						        $lastLine = $line;
						    }
						}
						$response = $cleanResponse;
						
						// Need to split by either the file name, or Key or key...
						$entries = preg_split( "/(".$file." |".($idField !== false ? $idField : str_replace("_", ".", $file))." |Key |key )/", $response);
						
						// Remove any duplicates
						$entries = array_unique($entries);
						
						foreach($entries as $entry)
						{
							if(strlen($entry) > 0)
							{
								$recordArray = array();
								$recordLines = explode(PHP_EOL, trim($entry));
								foreach($recordLines as $line)
								{
									
									$line = trim($line);
									$match = false;
									$lastEntry = null;
									
									// Run through each field for this file, and try and get a match (except the record ID)
									foreach($dictionary as $fieldData)
									{
										if($match === false && strlen(trim($fieldData["name"])) > 0)
										{
											if(strpos($line, $fieldData["name"]) === 0)
											{
												// Clean the data up
												$clean = trim(str_replace($fieldData["name"], "", $line));
												
												// Is this the ID?
												if($fieldData["key"] === "@ID")
												{
												    $recordArray["_id"] = $clean;
													$recordArray["id"] = $clean;
												} else {
												    
													// Check if there is a better match for this field
													foreach($dictionary as $field)
													{
														if($field["key"] === strtoupper(trim($fieldData["name"])) && strlen($clean) > 0)
														{
															$match = true;
															$fieldClean = strtolower(trim($field["key"]));
															$keyName = strtolower(preg_replace("/[^A-Za-z0-9]/", "_", trim($field["key"])));
															$recordArray[$keyName] = $clean;
														}
													}
													
													// If not, match the first key by name...
													if($match === false)
													{
													    // Is this field in the query?
														$match = true;
														$fieldClean = strtolower(trim($fieldData["key"]));
														$keyName = strtolower(preg_replace("/[^A-Za-z0-9]/", "_", trim($fieldData["key"])));
														if(strlen($clean) > 0)
														{
														    $recordArray[$keyName] = $clean;
														} else {
														    $recordArray[$keyName] = null;
														}
													}
													
												}
											}
										}
									}
									
									// Has this line been matched? Or does the data it contains need to be appended to the last entry?
									if($match === false)
									{
									    // Get lsat key in the array
									    end($recordArray);
                                        $lastEntry = key($recordArray);
                                        if($lastEntry !== null)
                                        {
                                            $recordArray[$lastEntry] .= "\r\n".$line;
                                        } else {
                                            // This is probably the ID field...
                                            $recordArray["_id"] = trim($line);
                                            $recordArray["id"] = trim($line);
                                        }
									}
									
								}
								
								// Add the record to the array
								if(count($recordArray) > 0)
								{
									$recordArray["_raw"] = trim($entry);
                                    $recordArray["_dictionary"] = $dictionary;
                                    $recordSet[] = new UDQueryWebRecord($recordArray);
								}
								
							}
						}
						
						// Return record set object
						return new UDQueryWebResultSet($dictionaryOriginal, $recordSet);
						
					} else {
						
						// Return an empty record set object
						return new UDQueryWebResultSet($dictionaryOriginal, $response);
						
					}
					
                } else {
                    throw new Exception("Query error: ".$query);
                }
            }
            catch(Exception $e)
            {
                throw new Exception($e);
            }
        } else {
            if($file === false || strlen($file) == 0)
            {
                throw new Exception("No file supplied");
            }
            if($query === false || strlen($query) == 0)
            {
                throw new Exception("No query supplied");
            }
        }
    }
    
    /**
     * Get the result set as XML
     */
    public function getXML($file = false, $query = false)
    {
        if($file !== false && strlen($file) > 0 && $query !== false && strlen($query) > 0)
        {
            try
            {
                // Create the XML document
                $fileClean = strtolower(preg_replace("/[^A-Za-z0-9]/", "_", $file));
				$xml = simplexml_load_string("<".$fileClean."></".$fileClean.">");
				
				// Put the row count in the opening tag
				$xml->addAttribute("records", $resultSet->num_records());
				
				// Run the query
				$resultSet = $this->get($file, $query);
				if($resultSet->num_records() > 0)
				{
					foreach($resultSet->result() as $entry)
					{
						$recordXml = $xml->addChild("record");
						$entryArray = $entry->getArray();
						foreach($entryArray as $key => $value)
						{
							$recordXml->addChild($key, $value);
						}
					}
				}
				
				// Return the XML
				return $xml;
            }
            catch(Exception $e)
            {
                return false;
            }
        } else {
            if($file === false || strlen($file) == 0)
            {
                throw new Exception("No file supplied");
            }
            if($query === false || strlen($query) == 0)
            {
                throw new Exception("No query supplied");
            }
        }
    }

    /**
     * Get a query builder
     */
    public function getQueryBuilder()
    {
        return new UDQueryBuilder($this);
    }

}

/**
 * Query builder class
 */
class UDQueryBuilder
{
    // Which file are we querying?
    private $_file = false;
    
    // What type of query is this? List, Sort, Count
    private $_queryType = "LIST";
    
    // Which fields do we want?
    private $_fields = array();
    private $_fieldExtras = array();
    
    // Query criteria
    private $_sortCriteria = false;
    private $_withCriteria = array();
    private $_whenCriteria = array();
    private $_rawCriteria = array();
    
    // Limit / Sample
    private $_sample = false;
    
    // By.Exp
    private $_byExp = array();
    
    // Compiled query
    private $_query = false;
    
    // UDQueryWeb instance
    private $_udq = false;
    
    function UDQueryBuilder($udq)
    {
        if(is_a($udq, "UDQueryWeb"))
        {
            $this->_udq = $udq;
        } else {
            throw new Exception("A valid UDQueryWeb object must be passed to the constructor");
        }
    }
    
    /**
     * Compile the query
     */
    private function _compileQuery()
    {
        if($this->_file !== false)
        {
            $query = $this->_queryType." ".trim($this->_file)." ";
            
            // Sorting
            if($this->_queryType === "SORT")
            {
                $query .= $this->_sortCriteria." ";
            }
            
            // By.Exp
            if(count($this->_byExp) > 0)
            {
                foreach($this->_byExp as $byExp)
                {
                    $query .= "BY.EXP ".$byExp." ";
                }
            }
            
            // Criteria
            if(count($this->_withCriteria) > 0)
            {
                $query .= "WITH ".implode(" AND ", array_unique($this->_withCriteria));
            }
            
            // Add a space between them if required...
            if(count($this->_withCriteria) > 0 && count($this->_whenCriteria) > 0)
            {
                $query .= " ";
            }
            
            if(count($this->_whenCriteria) > 0)
            {
                $query .= "WHEN ".implode(" AND ", array_unique($this->_whenCriteria));
            }
            
            // Raw query components
            if(count($this->_rawCriteria) > 0)
            {
                foreach($this->_rawCriteria as $raw)
                {
                    $query .= " (".$raw.")";
                }
            }
            
            // Fields
            if(count($this->_fields) > 0)
            {
                if(count($this->_fieldExtras) === 0)
                {
                    $query .= " ".implode(" ", array_unique($this->_fields));
                } else {
                    $fieldsClean = array();
                    foreach($this->_fields as $field)
                    {
                        if(isset($this->_fieldExtras[$field]))
                        {
                            $fieldsClean[] = $field." ".trim($this->_fieldExtras[$field]);
                        } else {
                            $fieldsClean[] = $field;
                        }
                    }
                    $query .= " ".implode(" ", array_unique($fieldsClean));
                }
            } else {
                $query .= " ALL";
            }
            
            if(is_int($this->_sample) && $this->_sample !== false && $this->_sample > 0)
            {
                $query .= " SAMPLE ".$this->_sample;
            }
            
            // Remove any double spaces
            return str_replace("  ", " ", $query);
        } else {
            return false;
        }
    }
    
    /**
     * Run the query
     */
    private function _runQuery()
    {
        if($this->_compileQuery() !== false)
        {
            return $this->_udq->get($this->_file, $this->_compileQuery(), (count($this->_fields) > 0 ? $this->_fields : null));
        } else {
            throw new Exception("The supplied query does not compile");
        }
    }
    
    /**
     * Return the last executed query
     */
    public function lastQuery()
    {
        if($this->_file !== false)
        {
            return $this->_compileQuery();
        } else {
            return false;
        }
    }
    
    /**
     * Run a LIST query
     */
    public function getList($file = false)
    {
        if($file !== false && strlen($file) > 0)
        {
            $this->_queryType = "LIST";
            $this->_file = $file;
            return $this->_runQuery();
        }
    }
    
    /**
     * Run a SORT query
     */
    public function getSort($file = false, $sortCriteria = false)
    {
        if($file !== false && strlen($file) > 0 && $sortCriteria !== false && strlen($sortCriteria) > 0)
        {
            $this->_queryType = "SORT";
            $this->_sortCriteria = $sortCriteria;
            $this->_file = $file;
            return $this->_runQuery();
        }
    }
    
    /**
     * Run a COUNT query
     */
    public function getCount($file = false)
    {
        if($file !== false && strlen($file) > 0)
        {
            $this->_queryType = "COUNT";
            $this->_file = $file;
            return $this->_runQuery();
        }
    }
    
    /**
     * Default to getting a list
     */
    public function get($file)
    {
        return $this->getList($file);
    }
    
    /**
     * Query criteria, WITH
     */
    public function with($criteria = false)
    {
        if($criteria !== false && strlen($criteria) > 0)
        {
            $this->_withCriteria[] = trim($criteria);
        }
    }
    
    /**
     * Query criteria, WHEN
    */
    public function when($criteria = false)
    {
        if($criteria !== false && strlen($criteria) > 0)
        {
            $this->_whenCriteria[] = trim($criteria);
        }
    }
    
    /**
     * Fields to fetch
     */
    public function field($field = false, $extra = false)
    {
        if($field !== false && (is_array($field) || strlen($field) > 0))
        {
            if(is_string($field) && strlen(trim($field)) > 0)
            {
                $this->_fields[] = trim($field);
                if($extra !== false && strlen(trim($extra)) > 0)
                {
                    $this->_fieldExtras[trim($field)] = trim($extra);
                }
            } else {
                if(is_array($field))
                {
                    foreach($field as $f)
                    {
                        $this->_fields[] = trim($f);
                        if($extra !== false && is_array($extra) && isset($extra[$f]))
                        {
                            $this->_fieldExtras[trim($f)] = trim($extra[$f]);
                        }
                    }
                } else {
                    return false;
                }
            }
        }
    }
    
    // Run a sample, or limit the number of results
    public function sample($size = false)
    {
        if($size !== false && is_numeric($size) && $size > 0)
        {
            $this->_sample = (int)$size;
        }
    }
    
    // Explode the query with multi-values
    public function byExp($field = false)
    {
        if($field !== false && strlen($field) > 0)
        {
            $this->_byExp[] = $field;
        }
    }
    
    // Raw query components
    public function raw($raw = false)
    {
        if($raw !== false && strlen($raw) > 0)
        {
            $this->_rawCriteria[] = $raw;
        }
    }
    
}

/**
 * Class to hold a UDQueryWeb result set
 */
class UDQueryWebResultSet
{
	
	private $_dictionary = false;
	private $_dataSet = false;
	private $_recordCount = 0;
	private $_multiValue = false;
	
	function UDQueryWebResultSet($dictionary, $dataSet)
	{
		if(is_array($dataSet))
		{
			$this->_dictionary = $dictionary;
			$this->_dataSet = $dataSet;
			$this->_recordCount = count($dataSet);
		} else {
			throw new Exception("No data set supplied");
		}
	}
	
	public function dictionary()
	{
		return $this->_dictionary;
	}
	
	public function result()
	{
		return $this->_dataSet;
	}
	
	public function num_records()
	{
		return $this->_recordCount;
	}
	
}

/**
 * Class to hold a UDQueryWeb record
 */
class UDQueryWebRecord
{
	private $_id = false;
	private $_rawRecord = false;
	private $_recordData = false;
	private $_dictionary = false;
	private $_multiValue = false;
	
	function UDQueryWebRecord($data)
	{
		if(is_array($data))
		{
			// Seperate the meta from the data
			$this->_rawRecord = $data["_raw"];
			$this->_dictionary = $data["_dictionary"];
			$this->_id = $data["_id"];
			if(isset($data["_multiValue"]))
			{
			    $this->_multiValue = $data["_multiValue"];
			    unset($data["_multiValue"]);
			} else {
			    $this->_multiValue = array();
			}
			// Clean up the record data
			unset($data["_raw"]);
			unset($data["_dictionary"]);
			// Store the record data
			$this->_recordData = $data;
		} else {
			throw new Exception("No data supplied");
		}
	}
	
	public function getID()
	{
		return $this->_id;
	}
	
	public function getArray()
	{
	    ksort($this->_recordData);
		return $this->_recordData;
	}
	
	public function rawRecord()
	{
	    return $this->_rawRecord;
	}
	
	// PHP Magic Methods
	
	public function __isset($field)
    {
        return isset($this->_recordData[strtolower($field)]);
    }
    
    /**
     * For accessing key as object property
     */
    public function __get($field)
    {
        if(array_key_exists($field, $this->_recordData))
        {
            return $this->_recordData[$field];
        } else {
            // Check for upper case keys
            if(array_key_exists(strtolower($field), $this->_recordData))
            {
                return $this->_recordData[strtolower($field)];
            } else {
        	    return null;
            }
        }
    }
    
    /**
     * For accessing key as array key
     */
    public function offsetGet($field)
    {
    	if(array_key_exists($field, $this->_recordData))
    	{
            return $this->_recordData[$field];
        } else {
        	foreach($this->_dictionary as $entry)
        	{
        		if($entry["key"] === $field || ucwords(strtolower($entry["key"])) === $field)
        		{
        			return $this->_recordData[$entry["_cleanKeyName"]];
        		}
        	}
        	return null;
        }
    }
    
    /**
     * Is the given field a multi value one?
     */
    public function isMultiValued($field)
    {
        if(isset($this->_multiValue[$field]))
        {
            return true;
        } else {
            return false;
        }
    }
	
}