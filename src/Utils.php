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
}