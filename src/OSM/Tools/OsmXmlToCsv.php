<?php
namespace Cyrille37\OSM\Yapafo\Tools;

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
            'relations_gravitycenter' => false,
            'tags' => null ,
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
            //
            // Pre filters
            //
            if( is_array($opts['tags']) )
            {
                foreach( $opts['tags'] as $key=>$val )
                    if( ! $obj->getTag($key,$val) )
                        continue 2 ;
            }

            switch( $obj->getObjectType() )
            {
                case OSM_Object::OBJTYPE_WAY:
                    break;
                case OSM_Object::OBJTYPE_RELATION:
                    break;
                case OSM_Object::OBJTYPE_NODE:
                    break;
            }

            //
            // Columns processing
            //

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

            //
            // Post filters
            //

            switch( $obj->getObjectType() )
            {
                case OSM_Object::OBJTYPE_WAY:
                    if( $opts['ways_gravitycenter'])
                    {
                        $latLng = ((object)$obj)->getGravityCenter( $osmApi );
                        if( $latLng )
                        {
                            $row['lat'] = $latLng[0];
                            $row['lon'] = $latLng[1];
                        }
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

                case OSM_Object::OBJTYPE_NODE:
                    break;
            }

            if( $row )
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