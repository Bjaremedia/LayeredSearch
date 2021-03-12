<?php

/**
 * Sql search layer
 * Matches all needles at the same time for each filter
 * Cannot determine which inidividual needles matched or not until all search is completed
 */
class Sql extends LayersAbstract
{
    /**
     * Get the query string used to add all sub filters for layer
     * @return String Query string
     */
    protected function getSubfiltersQuery(): string
    {
        return
            'SELECT     sls.`id`,
                        sls.`filters_id`,
                        sls.`select_pdoquery` \'select\',
                        sls.`base_pdoquery` \'base\',
                        sls.`needle_column` \'needle_col\'
            FROM        `search_v2_layer_sql` sls
            WHERE       sls.`filters_id` IN ({bind_params})
                AND     sls.`active` = 1
            ORDER BY    sls.`priority` DESC';
    }


    /**
     * Search all filters for matches vs needle
     * Result is saved to matching_needles and no_matches
     * @param Array $needles Needles to search for
     * @param String $search_key Array key in needles to search for
     */
    public function searchAllNeedles(array $needles, string $search_key): void
    {
        if (empty($this->sub_filters) || empty($needles) || empty($search_key)) {
            return;
        }
        foreach ($this->sub_filters as $filter) {
            foreach ($needles as $key => $needle) {
                if ($this->doEarlyReturn($key, $needle, $search_key)) {
                    continue;
                }
                $search_needle = $this->getNeedleForFilter($needle, $search_key, $filter['filters_id']);
                if (empty($search_needle)) {
                    continue;
                }
                $filter['needles'][$key] = $search_needle;
            }
            if (isset($filter['needles'])) {
                $this->searchWithFilter($filter, $search_key);
            }
        }
    }

    /**
     * Search for value in SQL and match with filters from sql
     * @param Array $filter Array with active filters for this type
     * @param String $search_key Array key in needles to search for
     */
    protected function searchWithFilter(array $filter, string $search_key): void
    {
        $query = $this->prepareQuery($filter);
        $sql_result = $this->PDOSearch->run($query, array_values($filter['needles']));
        while ($result = $sql_result->fetch()) {
            $needle = array_shift($result);
            $keys = array_keys($filter['needles'], $needle);
            if (empty($keys)) {
                continue;
            }
            foreach ($keys as $key) {
                $this->setMatchingNeedle($key, $search_key, $needle, $filter['filters_id'], $result);
                /*$f_key = array_search($needle, $filter['needles']);
                unset($filter['needles'][$f_key]);*/
            }
        }
    }

    /**
     * Prepare the sql query string and parameters
     * Adds needle, custom params, limit and offset
     * @param Array $filter The filter array
     * @return String The query string for db searcg
     */
    private function prepareQuery(array $filter): string
    {
        $params_string = '';
        foreach ($filter['needles'] as $n) {
            $params_string .= '?, ';
        }
        if ($this->quick_search) {
            $query = 'SELECT DISTINCT ' . $filter['needle_col'] . ' ';
        } else {
            $query = $filter['select'] . ' ';
        }
        $query .= str_replace('{needle}', $filter['needle_col'] . ' IN(' . substr($params_string, 0, -2) . ')', $filter['base']);
        return $query;
    }
}
