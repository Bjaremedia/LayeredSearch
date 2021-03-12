<?php

/**
 * Autoloader for Search Layers
 * @param String $name Php class and file name
 */
spl_autoload_register(function ($name) {
    $file = CORE . 'Classes/Search_v2/layers/' . $name . '.php';
    if (file_exists($file)) require $file;
});

/**
 * Core search function
 * 
 * Instructions
 * 1. Initiate using Search::initSearch();
 * 2. Add search filters using addFilters();
 * 3. Set config (optional) using setConfig();
 * 4. Do search using newSearch(); or get SearchObject using prepareSearch();
 * 
 * Note that page and limit can be used for newSearch(); aswell as getResultsForPage();
 * 
 * SearchObject is used by initiating a new search with it as input parameter.
 * Then use getResultsForPage(); to get results by page number from search object
 * 
 * A successful search will return a SearchResult object
 * Please note that search is limited by input needles. It will return 50 results, but these may contain not matching needles.
 * Therefore number of pages is including not matching needles and indecisive mathces.
 * There is simply no effective way of limiting search by matches since the sql is matching chunks of needles at the same time to limit amount of queries.
 */
class Core extends Layers
{
    protected $PDOfw;
    protected $config;

    private static $needles = [];

    private $search_key;
    private $SearchResult;
    private $page;
    private $offset;

    /**
     * __construct
     * Set config, initiate db and load SearchObject needles and filters
     * @param SearchObject $SearchObject Contains all data for a full search
     */
    function __construct(SearchObject $SearchObject)
    {
        $this->setConfig((array) $SearchObject->config);
        $this->PDOfw = SlimPDO::initSlimDb(DATABASE, $this->config->db_server);
        $this->loadSearchObject($SearchObject);
    }

    /**
     * Set config for search
     * @param Array $config An array with configurations for search function. Only values present in array will be updated. {
     *  @type String $database Target database for search (default = DATABASE)
     *  @type String $db_server Database server to use for search and filters (default = '127.0.0.1')
     *  @type String $prefix Array key prefix for all data added by Search Core function. (default = 's_')
     *  @type Int $limit Amount of results to get (default = 1000)
     *  @type Bool $quick_search Will stop searching for each needle as soon as definitive match is found (default = true), uses SELECT DISTINCT
     *  @type Bool $conjoined_result True = return all extra data prepended to input needles, False = return extra data in separate array
     *  @type Array $return_values Specify which returnvalues from filter result should be returned. If empty, all results from sql filters table will be returned (default = [])
     * }
     */
    public function setConfig(array $config): void
    {
        if (!is_object($this->config)) {
            $this->config = new stdClass();
        }
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'limit':
                    $this->config->$option = (int) $value;
                    break;
                case 'database':
                case 'db_server':
                case 'prefix':
                    $this->config->$option = (string) $value;
                    break;
                case 'quick_search':
                case 'conjoined_result':
                    $this->config->$option = (bool) $value;
                    break;
                case 'return_values':
                    $this->config->$option = (array) $value;
                default:
                    break;
            }
        }
    }

    /**
     * Get current config
     * @return stdClass The current config for Search
     */
    public function getConfig(): stdClass
    {
        return $this->config;
    }

    /**
     * Wrapper for new search when searching by a single needle
     * @param Mixed $needle Search needle
     * @return String/SearchResult Result of search or error message
     */
    public function singleSearch($needle)
    {
        return $this->newSearch('needle', [['needle' => $needle]]);
    }

    /**
     * New Search
     * Get search results for $needles and $page, limited by $limit
     * Filters must be set first!
     * @param String $search_key Key for value to search for in needles array
     * @param Array $needles Array with needle(s) to search for. Must be array where needles are in separate subarrays ie. $needles[0][$search_key] = needle
     * @param Int $page Page number to get results for
     * @return String/SearchResult Result of search or error message
     */
    public function newSearch(string $search_key, array $needles, int $page = 1)
    {
        if (empty($this->layers)) {
            return 'Error: Inga filter har angivits!';
        }
        if ($page < 1) {
            return 'Error: Sida måste vara större än 0!';
        }
        if ($search_key === null) {
            return 'Error: Söknyckel måste anges!';
        }
        if (empty($needles)) {
            return 'Error: Inga sökvärden finns!';
        }
        $this->page = $page;
        $this->search_key = $search_key;
        $this->SearchResult = new SearchResult();
        $this->setNeedles($needles);
        $this->layeredSearch();
        if ($this->config->conjoined_result) {
            $this->getConjoinedResult();
        } else {
            $this->getResult();
            $this->countSearchResults();
        }
        return $this->returnResult();
    }

    /**
     * Use this to get a new instance of the SearchObject for current config and input needles
     * @param String $search_key Key for value to search for in needles array
     * @param Array $needles Array with needle(s) to search for
     * @return String/SearchObject A SearchObject containing current filters, needles and config.
     */
    public function prepareSearch(string $search_key, array $needles)
    {
        if (empty($this->layers)) {
            return 'Error: Inga filter har angivits!';
        }
        if (empty($search_key)) {
            return 'Error: Söknyckel måste anges!';
        }
        $this->setNeedles($needles);
        $SearchObject = new SearchObject();
        $sub_filters = [];
        foreach ($this->layers as $Layer) {
            $SearchObject->layers[] = $Layer->getLayerName();
            $sub_filters = array_merge($sub_filters, $Layer->getUniqueFiltersId());
        }
        $SearchObject->sub_filters = array_unique($sub_filters);
        $SearchObject->config = $this->config;
        $SearchObject->needles = self::$needles;
        $SearchObject->search_key = $search_key;
        return $SearchObject;
    }

    /**
     * Get results for page number using earlier prepared Search Object
     * Requires a prepared SearchObject loaded on initiation
     * @param Int $page Page number to get results for
     * @return String/SearchResult Result of search or error message
     */
    public function getResultsForPage(int $page = 1)
    {
        if (empty($this->layers)) {
            return 'Error: Inga filter har angivits!';
        }
        if ($page < 1) {
            return 'Error: Sida måste vara större än 0!';
        }
        if (empty(self::$needles)) {
            return 'Error: Inga sökvärden finns!';
        }
        if (empty($this->search_key)) {
            return 'Error: Söknyckel måste anges!';
        }
        $this->page = $page;
        $this->SearchResult = new SearchResult();
        $this->layeredSearch();
        if ($this->config->conjoined_result) {
            $this->getConjoinedResult();
        } else {
            $this->getResult();
            $this->countSearchResults();
        }
        return $this->returnResult();
    }

    /**
     * Load the SearchObjects needles and filters if present
     * @param SearchObject $SearchObject An instance of SearchObject class
     */
    private function loadSearchObject(SearchObject $SearchObject): void
    {
        if (!empty($SearchObject->layers) && !empty($SearchObject->sub_filters)) {
            $this->addFilters(['id' => $SearchObject->sub_filters], $SearchObject->layers);
        }
        $this->search_key = $SearchObject->search_key;
        $this->setNeedles($SearchObject->needles);
    }

    /**
     * Trim and set needles to search for to array, resets array keys to numerical keys
     * @param Array $new_needles Array with needles
     */
    private function setNeedles(array $new_needles): void
    {
        self::$needles = [];
        if (empty($new_needles)) {
            return;
        }
        self::$needles = array_values($new_needles);
    }

    /**
     * Get a specific value from original needle input array
     * @param Int $key Index for needle
     * @param String $sub_key Array key for value
     * @return Mixed Value if found, else null
     */
    public static function getValueFromNeedle(int $key, string $sub_key)
    {
        if (isset(self::$needles[$key][$sub_key])) {
            return self::$needles[$key][$sub_key];
        }
        return null;
    }

    /**
     * Get a copy of the needles for the active page
     * @return Array Needles for active page
     */
    private function getNeedlesResult(): array
    {
        $result = [];
        $end = $this->config->limit + $this->offset;
        for ($key = $this->offset; $key < $end; $key++) {
            $result[] = self::$needles[$key];
        }
        return $result;
    }

    /**
     * Layered Search
     * Will set matches, indecisive matches and no matches to SearchResult
     * 
     *  Deep search - always search through all layers with all needles
     *  Quick search - search for each needle until definitive match is found, or all layers are searched. Will use "SELECT DISTINCT"
     */
    private function layeredSearch(): void
    {
        $this->setLimitAndOffset();
        $needles = $this->getNeedlesForPage();
        foreach ($this->layers as $Layer) {
            $Layer->quick_search = $this->config->quick_search;
            $Layer->resetNeedles();
            $Layer->searchAllNeedles($needles, $this->search_key);
            $needles = $Layer->getNeedles();
        }
        $this->saveNeedlesForPage($needles);
    }

    /**
     * Will return needles to search based on limit and page
     * @return Array Needles to search
     */
    private function getNeedlesForPage(): array
    {
        $needles = [];
        $end = $this->config->limit + $this->offset;
        for ($key = $this->offset; $key < $end; $key++) {
            if (isset(self::$needles[$key][$this->search_key])) {
                $needles[$key][$this->search_key] = self::$needles[$key][$this->search_key];
            } else {
                $needles[$key] = [];
            }
        }
        return $needles;
    }

    /**
     * Merge result from matching needles with input needles
     * @param Array $needles Needles to merge with input needles
     */
    private function saveNeedlesForPage(array $needles): void
    {
        $end = $this->config->limit + $this->offset;
        for ($key = $this->offset; $key < $end; $key++) {
            if (isset($needles[$key])) {
                if(isset($needles[$key]['matching_filters'])) {
                    $needles[$key]['matching_filters'] = array_values($needles[$key]['matching_filters']);
                }
                self::$needles[$key] = array_merge(self::$needles[$key], $needles[$key]);
            }
        }
    }

    /**
     * Used to calculate limit and offset for search
     */
    private function setLimitAndOffset()
    {
        $this->offset = ($this->page - 1) * $this->config->limit;
        if ($this->config->limit == 0) {
            $this->config->limit = count(self::$needles);
        }
    }

    /**
     * Get results from active needles array and add to SearchResult (for current page)
     */
    private function getResult(): void
    {
        $matches = $this->getNeedlesResult();
        foreach ($matches as $n_key => $match) {
            if (isset($match['matching_filters'])) {
                $match_count = count($match['matching_filters']);
                if ($match_count === 1) {   // Save Matches
                    $match = $this->getReturnValues($match);
                    $match = $this->getLayerData($match);
                    unset($match['matching_filters']);
                    $this->SearchResult->matches[$n_key] = $match;
                    continue;
                } elseif ($match_count > 1) {   // Save Indecisive
                    $result = [];
                    $matching_filters = $match['matching_filters'];
                    unset($match['matching_filters']);
                    foreach ($matching_filters as $filter) {
                        $result[] = $this->getReturnValues($match, $filter['filter_id']);
                    }
                    $match[$this->config->prefix . 'matching_filters'] = $result;
                    $this->SearchResult->indecisive_matches[$n_key] = $match;
                    continue;
                }
            }
            // Save no matches
            $this->SearchResult->no_matches[$n_key] = $match;
        }
    }

    /**
     * Get conjoined results from active needles array and add to SearchResult (for current page)
     * This will return all needles for page with same key as input, in same order.
     * All will be in matches array regardless of match or not.
     * If filter_match_count is 0 = no match, 1 = definitive match and 1 < = indecisive match (the lower the better)
     */
    private function getConjoinedResult(): void
    {
        $tot_match = 0;
        $tot_indecisive = 0;
        $tot_no_match = 0;
        $matches = $this->getNeedlesResult();
        foreach ($matches as $n_key => $match) {
            if (isset($match['matching_filters'])) {
                $match[$this->config->prefix . 'filter_match_count'] = count($match['matching_filters']);
            } else {
                $match[$this->config->prefix . 'filter_match_count'] = 0;
            }
            if ($match[$this->config->prefix . 'filter_match_count'] === 1) {
                $tot_match++;
                $match = $this->getReturnValues($match);
                $match = $this->getLayerData($match);
                unset($match['matching_filters']);
            } elseif ($match[$this->config->prefix . 'filter_match_count'] > 1) {
                unset($match['matching_filters']);
                $tot_indecisive++;
            } else {
                $tot_no_match++;
            }
            $this->SearchResult->matches[$n_key] = $match;
        }
        $this->SearchResult->total_matches = $tot_match;
        $this->SearchResult->total_indecisive_matches = $tot_indecisive;
        $this->SearchResult->total_no_matches = $tot_no_match;
    }

    /**
     * Get all data from layers and append to result
     * @param Array $result Current result set to add data to
     * @return Array Current result set with prepended values
     */
    private function getLayerData(array $result): array
    {
        if (!isset($result['matching_filters'][0]['data'])) {
            return $result;
        }
        return array_merge($result, $result['matching_filters'][0]);
    }

    /**
     * Get return values based on config->return_values, return all if empty
     * All keys will be prefixed with config->prefix
     * @param Array $result Current result set to add data to
     * @return Array Current result set with prepended values
     */
    private function getReturnValues(array $result, int $filters_id = null): array
    {
        if ($filters_id === null) {
            $filters_id = $result['matching_filters'][0]['filter_id'];
        }
        $matching_filters = $this->getFilterType($filters_id);
        foreach ($matching_filters as $key => $value) {
            if (empty($this->config->return_values)) {
                $result[$this->config->prefix . $key] = $value;
            } else {
                if (in_array($key, $this->config->return_values)) {
                    $result[$this->config->prefix . $key] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * Prepares and returns the search result
     * @return SearchResult Contains the result from the Search
     */
    private function returnResult(): SearchResult
    {
        $this->SearchResult->message = $this->SearchResult->total_matches . ' resultat hittades!';
        if ($this->config->limit > 0) {
            $this->SearchResult->total_pages = ceil(count(self::$needles) / $this->config->limit);
        } else {
            $this->SearchResult->total_pages = 1;
        }
        $this->SearchResult->page = $this->page;
        ksort($this->SearchResult->matches);
        return $this->SearchResult;
    }

    /**
     * Count results in each result set
     */
    private function countSearchResults(): void
    {
        $this->SearchResult->total_matches = count($this->SearchResult->matches);
        $this->SearchResult->total_indecisive_matches = count($this->SearchResult->indecisive_matches);
        $this->SearchResult->total_no_matches = count($this->SearchResult->no_matches);
    }
}
