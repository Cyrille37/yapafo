<?php
namespace Cyrille37\OSM\Yapafo\Tools ;

use Cyrille37\OSM\Yapafo\Objects\OSM_Object;
use Cyrille37\OSM\Yapafo\OSM_Api;
use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
use Cyrille37\OSM\Yapafo\Objects\Way;

class OsmXmlToCsv
{
    public static function toCsv( OSM_Api $osmApi, $fp, $options=[] )
    {

        $opts = [
            'ways_gravitycenter' => false,
            'relations_gravitycenter' => false
        ];
		// Check that all options exists then override defaults
		foreach ($options as $k => $v)
		{
			if (!array_key_exists($k, $opts))
				throw new OSM_Exception('Unknow option "' . $k . '"');
			$opts[$k] = $v;
		}

        // Prefill headers, just for ordering columns
        $headers = ['osm_type','id','lat','lon','version','changeset'];
        $rows = [];
        /**
         * @var OSM_Object $obj
         */
        foreach( $osmApi->getObjects() as $obj )
        {
            $row = ['osm_type'=>$obj->getObjectType()];
            foreach( $obj->getAttributes() as $k => $v )
            {
                if( ! in_array($k, $headers) )
                {
                    $headers[] = $k ;
                }
                $row[$k] = $v ;
            }
            foreach( $obj->getTags() as $t )
            {
                if( ! in_array($t->getKey(), $headers) )
                {
                    $headers[] = $t->getKey() ;
                }
                $row[$t->getKey()] = $t->getValue() ;
            }

            switch( $obj->getObjectType() )
            {
                case OSM_Object::OBJTYPE_WAY:
                    if( $opts['ways_gravitycenter'])
                    {
                        $latLng = ((object)$obj)->getGravityCenter( $osmApi );
                        $row['lat'] = $latLng[0];
                        $row['lon'] = $latLng[1];
                    }
                    break;

                case OSM_Object::OBJTYPE_RELATION:
                    if( $opts['relations_gravitycenter'])
                    {
                        $latLng = ((object)$obj)->getGravityCenter( $osmApi );
                        $row['lat'] = $latLng[0];
                        $row['lon'] = $latLng[1];
                    }
                    break;
            }

            $rows[] = $row ;
        }

        fputcsv($fp, $headers);
        foreach( $rows as $row )
        {
            $csv = array_fill( 0, count($headers), '');
            foreach( $headers as $i => $h )
            {
                if( isset($row[$h]) )
                    $csv[$i] = $row[$h];
            }
            fputcsv($fp, $csv);
        }
    }
}