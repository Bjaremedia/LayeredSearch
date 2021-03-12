<?php

/**
 * TODO: 
 * - Borde kunna sätta extra parametrar som t.ex. datumintervall mm. för att begränsa SQL ytterligare
 * 
 * - Testa hastighet och resultat mot snabbstatistiken för kundorderpackning (ny)
 *      Gamla mot nya, minne, hastighet och matchningar
 */

/**
 * Autoloader for core files
 * @param String $name Php class and file name
 */
spl_autoload_register(function ($name) {
    $file = CORE . 'Classes/Search_v2/' . $name . '.php';
    if (file_exists($file)) require $file;
});

/**
 * Wrapper for search function
 * Run Search::initSearch to create a new search instance.
 * Uses autoloaders for cleaner code and efficiency
 * 
 * @author Emil Johansson 2020-12-18
 */
class Search
{
    protected static $search_cores;

    /**
     * If no SearchObject is specified, initiate new search.
     * Already existing Search instance can be loaded by specifying the name of the instance in initSearch()
     * Can be loaded with SearchObject from earlier search (returned from Core->prepareSearch()) (mostly for use with Ajax where static php var cannot be accessed)
     * Set third parameter to specify db server (debugging)
     * @param String $instance_name If set, Search instance is saved to static variable, else new core is returned
     * @param SearchObject $SearchObj Existing Search object or null if new search
     * @param String $db_server Set target database server
     * @return Object New instance of SearchFunction object loaded with existing or new Search Object, or false if wrong object is loaded
     */
    public static function initSearch(string $instance_name = null, SearchObject $SearchObject = null, string $db_server = null): Core
    {
        // Check if instance exists and return it
        if (self::$search_cores === null) {
            self::$search_cores = [];
        }
        if ($instance_name !== null) {
            foreach (self::$search_cores as $key => $instance) {
                if ($key === $instance_name) {
                    return self::$search_cores[$key];
                }
            }
        }

        // Validate object and use or create new
        if (is_object($SearchObject)) {
            if (!$SearchObject instanceof SearchObject) {
                return false;
            }
        } elseif ($SearchObject === null) {
            if (!empty($db_server)) {
                $SearchObject = new SearchObject($db_server);
            } else {
                $SearchObject = new SearchObject();
            }
        } else {
            return false;
        }

        // Return new core class
        if ($instance_name !== null) {
            self::$search_cores[$instance_name] = new Core($SearchObject);
            return self::$search_cores[$instance_name];
        }else {
            return new Core($SearchObject);
        }
    }
}
