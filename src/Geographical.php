<?php
/**
 *  Laravel-Geographical (http://github.com/malhal/Laravel-Geographical)
 *
 *  Created by Malcolm Hall on 4/10/2016.
 *  Copyright © 2016 Malcolm Hall. All rights reserved.
 */

namespace Malhal\Geographical;

use Illuminate\Database\Eloquent\Builder;

trait Geographical
{
    /**
     * @param Builder $query
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @return Builder
     */
    public function scopeDistance($query, $latitude, $longitude)
    {
        $latName = $this->getQualifiedLatitudeColumn();
        $lonName = $this->getQualifiedLongitudeColumn();
        $query->select($this->getTable() . '.*');
        $sql = "((ACOS(SIN(? * PI() / 180) * SIN(" . $latName . " * PI() / 180) + COS(? * PI() / 180) * COS(" .
            $latName . " * PI() / 180) * COS((? - " . $lonName . ") * PI() / 180)) * 180 / PI()) * 60 * ?) as distance";

        $kilometers = false;
        if (property_exists(static::class, 'kilometers')) {
            $kilometers = static::$kilometers;
        }

        if ($kilometers) {
            $query->selectRaw($sql, [$latitude, $latitude, $longitude, 1.1515 * 1.609344]);
        } else {
            // miles
            $query->selectRaw($sql, [$latitude, $latitude, $longitude, 1.1515]);
        }

        //echo $query->toSql();
        //var_export($query->getBindings());
        return $query;
    }

    public function scopeGeofence($query, $latitude, $longitude, $inner_radius, $outer_radius)
    {
        $query = $this->scopeDistance($query, $latitude, $longitude);
        return $query->havingRaw('distance BETWEEN ? AND ?', [$inner_radius, $outer_radius]);
    }

    public function scopeGeofenceCutOff($query, $latitude, $longitude, $inner_radius, $outer_radius)
    {
        $R = 6371;
        $latName = $this->getQualifiedLatitudeColumn();
        $lonName = $this->getQualifiedLongitudeColumn();
        $correctionRadius = $this->getCorrectionRadius();

        if ($correctionRadius <= $outer_radius) {
          $maxLat = $latitude + rad2deg($outer_radius / $R);
          $minLat = $latitude - rad2deg($outer_radius / $R);
        } else {
          $maxLat = $latitude + asin(rad2deg($outer_radius / $R)) / cos($longitude);
          $minLat = $latitude - asin(rad2deg($outer_radius / $R)) / cos($longitude);
        }

        $maxLon = $longitude + rad2deg(asin($outer_radius / $R) / cos(deg2rad($latitude)));
        $minLon = $longitude - rad2deg(asin($outer_radius / $R) / cos(deg2rad($latitude)));
        $query = $query->fromSub(function ($query) use ($latName, $lonName, $minLat, $maxLat, $minLon, $maxLon) {
          $query->from($this->getTable())
              ->whereBetween($latName, [$minLat, $maxLat])
              ->whereBetween($lonName, [$minLon, $maxLon]);
        }, $this->getTable()); 
        return $this->scopeDistance($query, $latitude, $longitude, $inner_radius, $outer_radius);
  }

    protected function getQualifiedLatitudeColumn()
    {
        return $this->getTable() . '.' . $this->getLatitudeColumn();
    }

    protected function getQualifiedLongitudeColumn()
    {
        return $this->getTable() . '.' . $this->getLongitudeColumn();
    }

    public function getLatitudeColumn()
    {
        return defined('static::LATITUDE') ? static::LATITUDE : 'latitude';
    }

    public function getLongitudeColumn()
    {
        return defined('static::LONGITUDE') ? static::LONGITUDE : 'longitude';
    }
    
    public function getCorrectionRadius()
    {
        return defined('static::CORRECTION_RADIUS') ? static ::CORRECTION_RADIUS : 'correction_radius';
    }
}

?>
