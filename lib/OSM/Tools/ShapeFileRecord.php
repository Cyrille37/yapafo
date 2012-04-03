<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OSM\Tools;

/**
 * Description of ShapeFileRecord
 *
 * @author cyrille
 */
class ShapeFileRecord {
	const DEBUG = false ;
	
	const INEXISTENT_RECORD_CLASS = "Unable to determine shape record type [%i]";
	const INEXISTENT_FUNCTION = "Unable to find reading function [%s]";
	const INEXISTENT_DBF_FILE = "Unable to open (read/write) SHP's DBF file [%s]";
	const INCORRECT_DBF_FILE = "Unable to read SHP's DBF file [%s]";
	const UNABLE_TO_WRITE_DBF_FILE = "Unable to write DBF file [%s]";

	const XY_POINT_RECORD_LENGTH = 16;

	private $fp;
	private $fpos = null;
	private $dbf = null;
	private $record_number = null;
	private $content_length = null;
	private $record_shape_type = null;
	private $error_message = "";
	private $shp_data = array();
	private $dbf_data = array();
	private $file_name = "";
	private $record_class = array(0 => "RecordNull",
		1 => "RecordPoint",
		8 => "RecordMultiPoint",
		3 => "RecordPolyLine",
		5 => "RecordPolygon",
		13 => "RecordMultiPointZ",
		11 => "RecordPointZ");

	function __construct(&$fp, $file_name, $options) {
		
		$this->fp = $fp;
		$this->fpos = ftell($fp);
		$this->options = $options;

		self::_d("Shape record created at byte ".ftell($fp));

		if (feof($fp))
		{
			echo "ShapeFileRecord say end ";
			exit;
		}
		$this->_readHeader();

		$this->file_name = $file_name;
	}

	public function getNextRecordPosition() {
		$nextRecordPosition = $this->fpos + ((4 + $this->content_length ) * 2);
		return $nextRecordPosition;
	}

	private function _readHeader() {
		$this->record_number = ShapeFile::readAndUnpack("N", fread($this->fp, 4));
		$this->content_length = ShapeFile::readAndUnpack("N", fread($this->fp, 4));
		$this->record_shape_type = ShapeFile::readAndUnpack("i", fread($this->fp, 4));

		self::_d("Shape Record ID=".$this->record_number." ContentLength=".$this->content_length." RecordShapeType=".$this->record_shape_type."\nEnding byte ".ftell($this->fp)."\n");
	}

	public function getRecordClass() {
		if (!isset($this->record_class[$this->record_shape_type]))
		{
			//_d("Unable to find record class ($this->record_shape_type) [".getArray($this->record_class)."]");
			return $this->setError(sprintf(self::INEXISTENT_RECORD_CLASS, $this->record_shape_type));
		}
		//_d("Returning record class ($this->record_shape_type) ".$this->record_class[$this->record_shape_type]);
		return $this->record_class[$this->record_shape_type];
	}

	private function setError($error) {
		$this->error_message = $error;
		return false;
	}

	public function getError() {
		return $this->error_message;
	}

	public function getShpData() {
		
		$function_name = "read" . $this->getRecordClass();

		self::_d("Calling reading function [$function_name] starting at byte ".ftell($this->fp));

		if (method_exists(__CLASS__,$function_name))
		{
			//call_user_func(array($obj, $method_name), $parameter /* , ... */);
			$this->shp_data = ShapeFileRecord::$function_name($this->fp, $this->options);
		}
		else
		{
			$this->setError(sprintf(self::INEXISTENT_FUNCTION, $function_name));
		}

		return $this->shp_data ;
	}

	public function getDbfData() {

		$this->_fetchDBFInformation();

		return $this->dbf_data;
	}

	private function _openDBFFile($check_writeable = false) {
		$check_function = $check_writeable ? "is_writable" : "is_readable";
		if ($check_function($this->file_name))
		{
			$this->dbf = dbase_open($this->file_name, ($check_writeable ? 2 : 0));
			if (!$this->dbf)
			{
				$this->setError(sprintf(self::INCORRECT_DBF_FILE, $this->file_name));
			}
		}
		else
		{
			$this->setError(sprintf(self::INEXISTENT_DBF_FILE, $this->file_name));
		}
	}

	private function _closeDBFFile() {
		if ($this->dbf)
		{
			dbase_close($this->dbf);
			$this->dbf = null;
		}
	}

	private function _fetchDBFInformation() {
		$this->_openDBFFile();
		if ($this->dbf)
		{
			//En este punto salta un error si el registro 0 está vacio.
			//Ignoramos los errores, ja que aún así todo funciona perfecto.
			$this->dbf_data = dbase_get_record_with_names($this->dbf, $this->record_number);
		}
		else
		{
			$this->setError(sprintf(self::INCORRECT_DBF_FILE, $this->file_name));
		}
		$this->_closeDBFFile();
	}

	public function setDBFInformation($row_array) {
		$this->_openDBFFile(true);
		if ($this->dbf)
		{
			unset($row_array["deleted"]);

			if (!dbase_replace_record($this->dbf, array_values($row_array), $this->record_number))
			{
				$this->setError(sprintf(self::UNABLE_TO_WRITE_DBF_FILE, $this->file_name));
			}
			else
			{
				$this->dbf_data = $row_array;
			}
		}
		else
		{
			$this->setError(sprintf(self::INCORRECT_DBF_FILE, $this->file_name));
		}
		$this->_closeDBFFile();
	}

	protected static function readRecordPointZ(&$fp, $create_object = false, $options = null) {

		$data = array();

		$data["x"] = ShapeFile::readAndUnpack("d", fread($fp, 8));
		$data["y"] = ShapeFile::readAndUnpack("d", fread($fp, 8));
// 	$data["z"] = ShapeFile::readAndUnpack("d", fread($fp, 8));
// 	$data["m"] = ShapeFile::readAndUnpack("d", fread($fp, 8));
		////_d("Returning Point shp_data array = ".getArray($data));
		
		return $data;
	}

	protected static function readRecordPointZSP($data, &$fp) {

		$data["z"] = ShapeFile::readAndUnpack("d", fread($fp, 8));

		return $data;
	}

	protected static function readRecordPointMSP($data, &$fp) {

		$data["m"] = ShapeFile::readAndUnpack("d", fread($fp, 8));

		return $data;
	}

	protected static function readRecordMultiPoint(&$fp, $options = null) {

		$data = ShapeFile::readBoundingBox($fp);
		$data["numpoints"] = ShapeFile::readAndUnpack("i", fread($fp, 4));
		//_d("MultiPoint numpoints = ".$data["numpoints"]);

		for ($i = 0; $i <= $data["numpoints"]; $i++)
		{
			$data["points"][] = self::readRecordPoint($fp);
		}

		//_d("Returning MultiPoint shp_data array = ".getArray($data));
		return $data;
	}

		protected static function readRecordPolyLine(&$fp, $options = null) {

		//self::_d(__METHOD__.' ');

		$data = ShapeFile::readBoundingBox($fp);
		$data['numparts'] = ShapeFile::readAndUnpack('i', fread($fp, 4));
		$data['numpoints'] = ShapeFile::readAndUnpack('i', fread($fp, 4));
		
		//_d("PolyLine numparts = ".$data["numparts"]." numpoints = ".$data["numpoints"]);
		if (isset($options['noparts']) && $options['noparts'] == true)
		{
			//Skip the parts
			$points_initial_index = ftell($fp) + 4 * $data['numparts'];
			$points_read = $data['numpoints'];
		}
		else
		{
			$data['parts'] = array();
			for ($i = 0; $i < $data['numparts']; $i++)
			{
				$data['parts'][$i] = ShapeFile::readAndUnpack("i", fread($fp, 4));
				//_d("PolyLine adding point index= ".$data["parts"][$i]);
			}

			$points_initial_index = ftell($fp);

			//_d("Reading points; initial index = $points_initial_index");
			$points_read = 0;
			foreach ($data["parts"] as $part_index => $point_index)
			{
				//fseek($fp, $points_initial_index + $point_index);
				//_d("Seeking initial index point [".($points_initial_index + $point_index)."]");
				if (!isset($data["parts"][$part_index]["points"]) || !is_array($data["parts"][$part_index]["points"]))
				{
					$data["parts"][$part_index] = array();
					$data["parts"][$part_index]["points"] = array();
				}
				while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp))
				{
					$data["parts"][$part_index]["points"][] = self::readRecordPoint($fp, true);
					$points_read++;
				}
			}
		}

		fseek($fp, $points_initial_index + ($points_read * self::XY_POINT_RECORD_LENGTH));

		//_d("Seeking end of points section [".($points_initial_index + ($points_read * XY_POINT_RECORD_LENGTH))."]");
		return $data;
	}

	static function readRecordMultiPointZ(&$fp, $options = null) {

		$data = ShapeFile::readBoundingBox($fp);
		$data["numparts"] = ShapeFile::readAndUnpack("i", fread($fp, 4));
		$data["numpoints"] = ShapeFile::readAndUnpack("i", fread($fp, 4));
// 	$fileX = 40 + (16*$data["numpoints"]);
// 	$fileY = $fileX + 16 + (8*$data["numpoints"]);
		$fileX = 44 + (4 * $data["numparts"]);
		$fileY = $fileX + (16 * $data["numpoints"]);
		$fileZ = $fileY + 16 + (8 * $data["numpoints"]);
		/*
		  Note: X = 44 + (4 * NumParts), Y = X + (16 * NumPoints), Z = Y + 16 + (8 * NumPoints)
		 */

		//_d("PolyLine numparts = ".$data["numparts"]." numpoints = ".$data["numpoints"]);
		if (isset($options['noparts']) && $options['noparts'] == true)
		{
			//Skip the parts
			$points_initial_index = ftell($fp) + 4 * $data["numparts"];
			$points_read = $data["numpoints"];
		}
		else
		{
			for ($i = 0; $i < $data["numparts"]; $i++)
			{
				$data["parts"][$i] = ShapeFile::readAndUnpack("i", fread($fp, 4));
				//_d("PolyLine adding point index= ".$data["parts"][$i]);
			}
			$points_initial_index = ftell($fp);

			//_d("Reading points; initial index = $points_initial_index");
			$points_read = 0;
			foreach ($data["parts"] as $part_index => $point_index)
			{
				//fseek($fp, $points_initial_index + $point_index);
				//_d("Seeking initial index point [".($points_initial_index + $point_index)."]");
				if (!isset($data["parts"][$part_index]["points"]) || !is_array($data["parts"][$part_index]["points"]))
				{
					$data["parts"][$part_index] = array();
					$data["parts"][$part_index]["points"] = array();
				}
				while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"]/* && !feof($fp) */)
				{
					$data["parts"][$part_index]["points"][] = self::readRecordPoint($fp, true);
					$points_read++;
				}
			}

			$data['Zmin'] = ShapeFile::readAndUnpack("d", fread($fp, 8));
			$data['Zmax'] = ShapeFile::readAndUnpack("d", fread($fp, 8));

			foreach ($data["parts"] as $part_index => $point_index)
			{
				foreach ($point_index["points"] as $n => $p)
				{
					$data["parts"][$part_index]['points'][$n] = self::readRecordPointZSP($p, $fp, true);
				}
			}

			$data['Mmin'] = ShapeFile::readAndUnpack("d", fread($fp, 8));
			$data['Mmax'] = ShapeFile::readAndUnpack("d", fread($fp, 8));

			foreach ($data["parts"] as $part_index => $point_index)
			{
				foreach ($point_index["points"] as $n => $p)
				{
					$data["parts"][$part_index]['points'][$n] = self::readRecordPointMSP($p, $fp, true);
				}
			}
		}

		fseek($fp, $points_initial_index + ($points_read * XY_POINT_RECORD_LENGTH));

		//_d("Seeking end of points section [".($points_initial_index + ($points_read * XY_POINT_RECORD_LENGTH))."]");
		return $data;
	}

	static function readRecordPolygon(&$fp, $options = null) {
		//_d("Polygon reading; applying readRecordPolyLine function");
		return self::readRecordPolyLine($fp, $options);
	}

	static function readRecordPoint(&$fp, $create_object = false, $options = null) {

		$data = array();

		$data['x'] = ShapeFile::readAndUnpack('d', fread($fp, 8));
		$data['y'] = ShapeFile::readAndUnpack('d', fread($fp, 8));

		////_d("Returning Point shp_data array = ".getArray($data));
		
		return $data;
	}

	static function _d($debug_text) {
		if (self::DEBUG)
		{
			echo '['.__CLASS__.'] '.$debug_text . "\n";
		}
	}
}
