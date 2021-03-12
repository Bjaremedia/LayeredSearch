<?php

/**
 * Must be implemented by all layer classes
 * Contains all general functionality for layers as well as abstract function to enforce method presence.
 */
abstract class LayersAbstract
{
    public $quick_search = false;

    protected $needles = [];
    protected $sub_filters = [];
    protected $PDOSearch;
    protected $PDOfw;
    protected $name;

    /**
     * Constructor
     * @param String $db_server Target SQL server
     * @param String $database Database to search in
     * @param String $layer The name for the layer
     */
    function __construct(string $db_server, string $database, string $layer)
    {
        $this->PDOSearch = SlimPDO::initSlimDb($database, $db_server);
        $this->PDOfw = SlimPDO::initSlimDb(DATABASE, $db_server);
        $this->name = $layer;
    }

    /**
     * Get the query string used to add all sub filters for layer
     * @return String Query string
     */
    abstract protected function getSubfiltersQuery(): string;

    /**
     * Search all filters for matches vs needle
     * Result is saved to matching_needles and no_matches
     * @param Array $needles Needles to search for
     */
    abstract protected function searchAllNeedles(array $needles, string $search_key): void;

    /**
     * Reset needles array
     */
    public function resetNeedles(): void
    {
        $this->needles = [];
    }

    /**
     * Add relevant filters for layer
     * @param Array $filters Array with all filter id's to add
     */
    public function addActiveFilters(array $filters): void
    {
        $subfilters_query = $this->getSubfiltersQuery();
        if (empty($subfilters_query)) {
            return;
        }
        $params = [];
        $bind_params = [];
        foreach ($filters as $filter) {
            if (!isset($filter['id'])) {
                continue;
            }
            $params[] = $filter['id'];
            $bind_params[] = '?';
        }
        if (empty($params) || empty($bind_params)) {
            return;
        }
        $query = str_replace('{bind_params}', implode(',', $bind_params), $subfilters_query);
        $sql_result = $this->PDOfw->run($query, $params);
        $this->sub_filters = $sql_result->fetchAll();
    }

    /**
     * Get an array with all unique id's for master filters
     * @return Array Array with filter id's
     */
    public function getUniqueFiltersId(): array
    {
        $filters_id = [];
        foreach ($this->sub_filters as $filter) {
            if (in_array($filter['filters_id'], $filters_id)) {
                continue;
            }
            $filters_id[] = $filter['filters_id'];
        }
        return $filters_id;
    }

    /**
     * Return layer name
     * @return String Layer name
     */
    public function getLayerName(): string
    {
        return $this->name;
    }

    /**
     * Set currently matching needle to array with matching filter id (not subfilter)
     * @param Int $key Original array key for needle
     * @param String $search_key Array key for needle
     * @param String $needle Search needle
     * @param Int $filter_id Id for the filter that matched the needle
     * @param Array $data Extra data to add to result set (ie. from SQL)
     */
    protected function setMatchingNeedle(int $key, string $search_key, string $needle, int $filter_id, array $data = []): void
    {
        $this->needles[$key]['matching_filters'][$filter_id]['filter_id'] = $filter_id;
        $this->needles[$key]['matching_filters'][$filter_id][$search_key] = $needle;
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $this->needles[$key]['matching_filters'][$filter_id]['data'][][strtolower($this->name) . '_' . $k] = $v;
            }
        }

        /*if (isset($this->needles[$key]['matching_filters'])) {
            foreach ($this->needles[$key]['matching_filters'] as $this_key => $filter) {
                if ($filter['filter_id'] === $filter_id) {
                    if (!empty($data)) {
                        foreach ($data as $k => $v) {
                            $this->needles[$key]['matching_filters'][$this_key]['data'][][strtolower($this->name) . '_' . $k] = $v;
                        }
                    }
                    return;
                }
            }
        }
        $n_data['filter_id'] = $filter_id;
        $n_data[$search_key] = $needle;
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $n_data['data'][][strtolower($this->name) . '_' . $k] = $v;
            }
        }
        $this->needles[$key]['matching_filters'][] = $n_data;*/
    }

    /**
     * If filter exists for filter_id, get needle for this filter
     * If matching_filters does not exist, get original needle
     * @param Array $needle Current needle data
     * @param String $search_key Needle key to search for
     * @param Int $filter_id Id for current filter
     * @return String Needle to search for, empty if no needle
     */
    protected function getNeedleForFilter(array $needle, string $search_key, int $filter_id): string
    {
        if (isset($needle['matching_filters'])) {
            foreach ($needle['matching_filters'] as $filter) {
                if ($filter['filter_id'] == $filter_id) {
                    return $filter[$search_key];
                }
            }
            return '';
        }
        return $needle[$search_key];
    }

    /**
     * Getter for needles which contains result from this layer
     */
    public function getNeedles(): array
    {
        return $this->needles;
    }

    /**
     * Will return true if this needle should not be searched
     * @param Int $key Main key for needle in original array
     * @param Array $needle Current needle data to validate
     * @param String $search_key Key for target needle in needle array
     * @return Bool True = skip search for this needle, False = do search
     */
    protected function doEarlyReturn(int $key, array $needle, string $search_key): bool
    {
        if (empty($needle)) {
            return true;
        }
        if (isset($needle['matching_filters'])) {
            if (count($needle['matching_filters']) === 1 && $this->quick_search) {
                $this->needles[$key] = $needle;
                return true;
            }
        }
        return false;
    }
}
