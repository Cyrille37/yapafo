<?php
namespace Cyrille37\OSM\Yapafo\Tools ;

class Math
{
    /**
     * Earth radius https://en.wikipedia.org/wiki/Earth_radius
     */
    CONST EARTH_RADIUS = 6371000; // globally-average

    /**
     * Pythagore c2=√(a2+b2) 
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return void
     */
    public static function vectorLength( $x1, $y1, $x2, $y2 )
    {
        return sqrt(
            pow($y2 - $y1,2)
            + pow($x2 - $x1,2)
        );
    }

    /**
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return void
     */
    public static function distanceOnEarth( $lat1, $lon1, $lat2, $lon2 )
    {
        $rlo1 = deg2rad($lon1);
        $rla1 = deg2rad($lat1);
        $rlo2 = deg2rad($lon2);
        $rla2 = deg2rad($lat2);
        $dlo = ($rlo2 - $rlo1) / 2.0;
        $dla = ($rla2 - $rla1) / 2.0;
        $a = pow(sin($dla), 2) + cos($rla1) * cos($rla2) * pow(sin($dlo),2);
        $d = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return (self::EARTH_RADIUS * $d);
    }
}