<?php

/**
 * This class is under GPL Licencense Agreement
 * @author Juan Carlos Gonzalez Ulloa <jgonzalez@innox.com.mx>
 * Innovacion Inteligente S de RL de CV (Innox)
 * Lopez Mateos Sur 2077 - Z16
 * Col. Jardines de Plaza del Sol
 * Guadalajara, Jalisco
 * CP 44510
 * Mexico
 *
 * http://www.phpclasses.org/package/1741-PHP-Read-vectorial-data-from-geographic-shape-files.html
 * 
 * Class to read SHP files and modify the DBF related information
 * Just create the object and all the records will be saved in $shp->records
 * Each record has the "shp_data" and "dbf_data" arrays with the record information
 * You can modify the DBF information using the $shp_record->setDBFInformation($data)
 * The $data must be an array with the DBF's row data.
 *
 * Performance issues:
 * Because the class opens and fetches all the information (records/dbf info)
 * from the file, the loading time and memory amount neede may be way to much.
 * Example:
 *   15 seconds loading a 210907 points shape file
 *   60Mb memory limit needed
 *   Athlon XP 2200+
 *   Mandrake 10 OS
 *
 *
 *
 * Edited by David Granqvist March 2008 for better performance on large files
 * This version only get the information it really needs
 * Get one record at a time to save memory, means that you can work with very large files.
 * Does not load the information until you tell it too (saves time)
 * Added an option to not read the polygon points can be handy sometimes, and saves time :-)
 * 
 * Example:

  //sets the options to show the polygon points, 'noparts' => true would skip that and save time
  $options = array('noparts' => false);
  $shp = new ShapeFile("../../php/shapefile/file.shp",$options);

  //Dump the ten first records
  $i = 0;
  while ($record = $shp->getNext() and $i<10) {
  $dbf_data = $record->getDbfData();
  $shp_data = $record->getShpData();
  //Dump the information
  var_dump($dbf_data);
  var_dump($shp_data);
  $i++;
  }
 * 
 */

namespace OSM\Tools;

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'ShapeFileRecord.php');

class ShapeFile {
	const DEBUG = false;

	const ERROR_FILE_NOT_FOUND = 'SHP File not found [%s]';

	private $file_name;
	private $fp;
	//Used to fasten up the search between records;
	private $dbf_filename = null;
	//Starting position is 100 for the records
	private $fpos = 100;
	private $error_message = "";
	private $show_errors = true;
	private $shp_bounding_box = array();
	private $shp_type = 0;

	/**
	 * noparts bool
	 */
	protected $options ;

	function __construct($file_name, array $options=null) {

		if( !function_exists('dbase_open'))
		{
			die('package DBase for Php must be present');
		}
		$this->options = $options;

		$this->file_name = $file_name;
		$this->_d("Opening [$file_name]");
		if (!is_readable($file_name))
		{
			return $this->setError(sprintf(self::ERROR_FILE_NOT_FOUND, $file_name));
		}

		$this->fp = fopen($this->file_name, 'rb');

		$this->_fetchShpBasicConfiguration();

		//Set the dbf filename
		$this->dbf_filename = self::processDBFFileName($this->file_name);
	}
	
	/**
	 *
	 * @return string
	 */
	public function getError() {
		return $this->error_message;
	}

	function __destruct() {
		$this->closeFile();
	}

	// Data fetchers
	private function _fetchShpBasicConfiguration() {

		$this->_d("Reading basic information");

		fseek($this->fp, 32, SEEK_SET);
		$this->shp_type = self::readAndUnpack('i', fread($this->fp, 4));
		$this->_d("SHP type detected: ".$this->shp_type);

		$this->shp_bounding_box = self::readBoundingBox($this->fp);
		$this->_d("SHP bounding box detected: miX=".$this->shp_bounding_box["xmin"]." miY=".$this->shp_bounding_box["ymin"]." maX=".$this->shp_bounding_box["xmax"]." maY=".$this->shp_bounding_box["ymax"]);
	}

	/**
	 * @return ShapeFileRecord 
	 */
	public function getNext() {
		if (!feof($this->fp))
		{
			fseek($this->fp, $this->fpos);
			$shp_record = new ShapeFileRecord($this->fp, $this->dbf_filename, $this->options);
			if ($shp_record->getError() != '')
			{
				echo 'SHAPE FILE ERROR: '.$shp_record->getError() ;
				return false;
			}
			$this->fpos = $shp_record->getNextRecordPosition();
			return $shp_record;
		}
		return false;
	}

	/* Alpha, not working
	  public function _resetFileReading(){
	  rewind($this->fp);
	  $this->fpos = 0;

	  $this->_fetchShpBasicConfiguration();
	  } */

	/* Takes too much memory
	  function _fetchRecords(){
	  fseek($this->fp, 100);
	  while(!feof($this->fp)){
	  $shp_record = new ShapeFileRecord($this->fp, $this->file_name);
	  if($shp_record->error_message != ""){
	  return false;
	  }
	  $this->records[] = $shp_record;
	  }
	  }
	 */

//Not Used
	/* 	private function getDBFHeader(){
	  $dbf_data = array();
	  if(is_readable($dbf_data)){
	  $dbf = dbase_open($this->dbf_filename , 1);
	  // solo en PHP5 $dbf_data = dbase_get_header_info($dbf);
	  echo dbase_get_header_info($dbf);
	  }
	  }
	 */

	// General functions        
	private function setError($error) {
		$this->error_message = $error;
		if ($this->show_errors)
		{
			echo $error . "\n";
		}
		return false;
	}

	private function closeFile() {
		if ($this->fp)
		{
			fclose($this->fp);
		}
	}

	/**
	 * General functions
	 */
	protected static function processDBFFileName($dbf_filename) {
		//_d("Received filename [$dbf_filename]");
		if (!strstr($dbf_filename, '.'))
		{
			$dbf_filename .= '.dbf';
		}

		if (substr($dbf_filename, strlen($dbf_filename) - 3, 3) != 'dbf')
		{
			$dbf_filename = substr($dbf_filename, 0, strlen($dbf_filename) - 3) . 'dbf';
		if( !file_exists($dbf_filename))
		{
			$dbf_filename = substr($dbf_filename, 0, strlen($dbf_filename) - 3) . 'DBF';
			
		}
		}
		//_d("Ended up like [$dbf_filename]");
		return $dbf_filename;
	}

	public static function readBoundingBox(&$fp) {
		$data = array();
		$data["xmin"] = self::readAndUnpack("d", fread($fp, 8));
		$data["ymin"] = self::readAndUnpack("d", fread($fp, 8));
		$data["xmax"] = self::readAndUnpack("d", fread($fp, 8));
		$data["ymax"] = self::readAndUnpack("d", fread($fp, 8));

		//_d("Bounding box read: miX=".$data["xmin"]." miY=".$data["ymin"]." maX=".$data["xmax"]." maY=".$data["ymax"]);
		return $data;
	}

	public static function readAndUnpack($type, $data) {
		if (!$data)
			return $data;
		return current(unpack($type, $data));
	}

	function _d($debug_text) {
		if (self::DEBUG)
		{
			echo '['.__CLASS__.'] '.$debug_text . "\n";
		}
	}

	function getArray($array) {
		ob_start();
		print_r($array);
		$contents = ob_get_contents();
		ob_get_clean();
		return $contents;
	}

}

/**
 * Reading functions
 */
/*
  function readRecordNull(&$fp, $read_shape_type = false,$options = null){
  $data = array();
  if($read_shape_type) $data += readShapeType($fp);
  //_d("Returning Null shp_data array = ".getArray($data));
  return $data;
  } */

