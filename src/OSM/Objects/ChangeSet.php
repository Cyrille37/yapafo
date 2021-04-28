<?php
namespace Cyrille37\OSM\Yapafo\Objects ;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
/**
 * OSM/Objects/ChangeSet.php
 */

/**
 * Description of OSM_Objects_ChangeSet
 *
 * @author cyrille
 */
class ChangeSet {

	protected $_createdObjects=array();
	protected $_modifiedObjects=array();
	protected $_deleteObjects=array();
	protected $_id;

	public function __construct($id) {

		if ($id == null || $id == '' || $id == 0)
		{
			throw new OSM_Exception('Invalid ChangeSet id');
		}
		$this->_id = $id;
	}

	/**
	 * @return string
	 */
	public function getId()
	{
		return $this->_id ;
	}

	/**
	 * Add an new or modified object to the ChangeSet.
	 * It will self compute the "New" or "Modified" object's status.
	 * @param OSM_Objects_Object $obj
	 * @throws OSM_Exception
	 */
	public function addObject(OSM_Objects_Object $obj) {

		$objectId = $obj->getId();
		if (empty($objectId))
			throw new OSM_Exception('Object Id must be set');

		if ($objectId < 0)
		{
			$obj->setAttribute('changeset', $this->_id);
			$this->_createdObjects[$objectId] = $obj;
		}
		else
		{
			$obj->setAttribute('changeset', $this->_id);
			// do not increment the version, it's need by the server to detect conflict.
			//$obj->setAttribute('version', $obj->getAttribute('version') + 1);
			$this->_modifiedObjects[$objectId] = $obj;
		}
	}

	/**
	 * Add a deleted object to the ChangeSet.
	 * @param OSM_Objects_Object $obj
	 * @throws OSM_Exception
	 */
	public function deleteObject(OSM_Objects_Object $obj) {

		$objectId = $obj->getId();
		if (empty($objectId))
			throw new OSM_Exception('Object Id must be set');

		$obj->setAttribute('changeset', $this->_id);
		// do not increment the version, it's need by the server to detect conflict.
		//$obj->setAttribute('version', $obj->getAttribute('version') + 1);
		$this->_deleteObjects[$objectId] = $obj;
	}

	/**
	 * http://wiki.openstreetmap.org/wiki/API_v0.6#Create:_PUT_.2Fapi.2F0.6.2Fchangeset.2Fcreate
	 * 
	 * @param string $comment
	 * @return string 
	 */
	public static function getCreateXmlStr($comment,$generator) {

		$xmlStr = "<?xml version='1.0' encoding=\"UTF-8\"?>\n" .
			'<osm version="0.6" generator="' . $generator . '">'
			. "<changeset id='0' open='false'>"
			. '<tag k="created_by" v="' . $generator . '"/>'
			. '<tag k="comment" v="' . str_replace('"', '\'', $comment) . '"/>'
			. '</changeset></osm>';
		return $xmlStr;
	}

	/**
	 * Get the ChangeSet Xml document ready to upload.
	 * 
	 * With this API call files in the OsmChange format (OSC) can be uploaded to the server.
	 * If a diff is successfully applied a XML (content type text/xml) is returned in the following format.
	 * http://wiki.openstreetmap.org/wiki/API_v0.6#Diff_upload:_POST_.2Fapi.2F0.6.2Fchangeset.2F.23id.2Fupload
	 * @param string $generator The name of the generator, like user-agent.
	 * @return string The ChangeSet Xml document ready to upload.
	 */
	public function getUploadXmlStr($generator) {

		$xmlStr = '<osmChange version="0.6" generator="' . $generator . '">'."\n";

		if( count($this->_createdObjects) >0 )
		{
			$xmlStr.= '<create version="0.3" generator="' . $generator . '">'."\n";
			foreach ($this->_createdObjects as $id=>$obj)
			{
				$xmlStr.= $obj->asXmlStr();
			}
			$xmlStr.= '</create>'."\n";
		}

		if( count($this->_modifiedObjects) >0 )
		{
			$xmlStr.= '<modify version="0.3" generator="' . $generator . '">'."\n";
			foreach ($this->_modifiedObjects as $id=>$obj)
			{
				$xmlStr.= $obj->asXmlStr();			
			}
			$xmlStr.= '</modify>'."\n";
		}

		if( count($this->_deleteObjects) >0 )
		{
			$xmlStr.= '<delete version="0.3" generator="' . $generator . '">'."\n";
			foreach ($this->_deleteObjects as $id=>$obj)
			{
				$xmlStr.= $obj->asXmlStr();			
			}
			$xmlStr.= '</delete>'."\n";
		}

		$xmlStr.= '</osmChange>';

		return $xmlStr ;
	}

}