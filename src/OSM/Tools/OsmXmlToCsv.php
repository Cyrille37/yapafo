<?php
namespace Cyrille37\OSM\Yapafo\Tools ;

use Cyrille37\OSM\Yapafo\Objects\OSM_Object;
use Cyrille37\OSM\Yapafo\OSM_Api;

class OsmXmlToCsv
{
    public static function toCsv( OSM_Api $osmApi, $fp )
    {
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