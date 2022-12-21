<?php
/**
 * Created by PhpStorm.
 * User: pieter
 * Date: 1-2-18
 * Time: 10:31
 */

namespace Djc\Phalcon;


class Utils
{
    /**
     * @param string $term
     * @param string $module
     * @param string $language
     * @return string
     */
    public static function t($term, $module = 'base', $language = 'nl') {
        $l = [];
        if (file_exists(APP_PATH . '/modules/' . ucfirst($module) . '/language/en.php')) {
            include APP_PATH . '/modules/' . ucfirst($module) . '/language/en.php';
        }
        if (file_exists(APP_PATH . '/modules/' . ucfirst($module) . '/language/' . $language . '.php')) {
            include APP_PATH . '/modules/' . ucfirst($module) . '/language/' . $language . '.php';
        }
        if (!array_key_exists($term, $l)) {
            return $term;
        }
        return $l[$term];
    }

    /**
     * Merge mergeObject into baseObject
     *
     * @param \stdClass $baseObject
     * @param \stdClass $mergeObject
     * @return \stdClass
     */
    public static function objectMerge(\stdClass $baseObject, \stdClass $mergeObject) {
        $newObject = $mergeObject;
        foreach ($baseObject as $key => $value) {
            $newObject->$key = $value;
        }
        return $newObject;
    }

    /**
     * Converts a dateTime format string form PHP-formats to JavaScript-formats
     *
     * @param string $dateFormat
     * @return string
     */
    public static function convertDateTimeFormat($dateFormat) {
        $dateFormat = str_replace('dd', 'd', $dateFormat);
        $dateFormat = str_replace('MM', 'm', $dateFormat);
        $dateFormat = str_replace('yyyy', 'Y', $dateFormat);
        $dateFormat = str_replace('HH', 'H', $dateFormat);
        $dateFormat = str_replace('mm', 'i', $dateFormat);
        return $dateFormat;
    }

    /**
     * Convert strings (formatted as number or € currency) to a float
     *
     * @param string $numberString
     * @return float
     */
    public static function convertNumberString($numberString) {
        // remove € signs
        while ($pos = strpos($numberString, ' ')) {
            $numberString = trim(substr($numberString, $pos));
        }
        $numberString = trim(str_replace('€', '', $numberString));
        // check if decimalseperator is present and of type ','
        if (strpos($numberString, ',') && (strpos($numberString, ',') > strpos($numberString, '.'))) {
            // if there are thousandseperators remove them
            $numberString = str_replace('.', '', $numberString);
            // replace ',' with '.' as decimalseperator
            $numberString = str_replace(',', '.', $numberString);
        }
        return (floatval($numberString));
    }

}