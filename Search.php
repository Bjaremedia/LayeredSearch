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

    /**
     * Search for order by needles
     * Will always do deep search to get order id from SQL
     * Limit is by default unlimited, can be change by changing the config in the SearchObject that is returned
     * Do multiple ajax calls with Search Object as input to initSearch to load all pages
     * @param Array/String $needles Needles to search for
     * @param String $needle_key Target needle key in $needles array (default = needle, used if input $needles is string)
     * @param Array $search_in Select what types to search by (default = all types that return order id)
     * @param String $database Target database to search in (default = DATABASE)
     * @param String $db_server Target database server ip/url (default = null, local database)
     * @return SearchObject A configured search object, use getResultsForPage() on this
     */
    public static function findOrderId(
        $needle,
        string $needle_key = 'needle',
        array $search_in = ['tracking-number', 'sello-order-number', 'sello-order-number-long'],
        string $database = DATABASE,
        string $db_server = null
    ) {
        if (empty($needle) || empty($needle_key) || empty($search_in)) {
            return false;
        }
        if (!is_array($needle)) {
            $needles[] = ['needle' => $needle];
        } else {
            $needles = $needle;
        }
        $Search = self::initSearch(null, $db_server);
        $Search->addFilters(['needle_type' => $search_in], ['StringCase', 'Sql']);
        $Search->setConfig(['limit' => 0, 'quick_search' => false, 'database' => $database]);
        $SearchObject = $Search->prepareSearch($needle_key, $needles);
        return $SearchObject;
    }
}
