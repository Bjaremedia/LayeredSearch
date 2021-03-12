<?php

/**
 * String Case layer
 * Matching for strings (not inline) and possibly preg_replace for needles
 */
class StringCase extends LayersAbstract
{
    /**
     * Get the query string used to add all sub filters for layer
     * @return String Query string
     */
    protected function getSubfiltersQuery(): string
    {
        return
            'SELECT     slsc.`id`,
                        slsc.`filters_id`,
                        slsc.`minlength`,
                        slsc.`maxlength`,
                        slsc.`regex_case`,
                        slsc.`regex_replace`
            FROM        `search_v2_layer_string_case` slsc
            WHERE       slsc.`filters_id` IN ({bind_params})
                AND     slsc.`active` = 1
            ORDER BY    slsc.`priority` DESC';
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
        foreach ($needles as $key => $needle) {
            if ($this->doEarlyReturn($key, $needle, $search_key)) {
                continue;
            }
            foreach ($this->sub_filters as $filter) {
                $search_needle = $this->getNeedleForFilter($needle, $search_key, $filter['filters_id']);
                if (empty($search_needle)) {
                    continue;
                }
                if ($this->searchWithFilter($search_needle, $filter)) {
                    if (isset($filter['regex_replace'])) {
                        $new_needle = preg_replace($filter['regex_case'], $filter['regex_replace'], $search_needle);
                    } else {
                        $new_needle = $search_needle;
                    }
                    $this->setMatchingNeedle($key, $search_key, $new_needle, $filter['filters_id']);
                }
            }
        }
    }

    /**
     * Match input needle with filters from table (validation)
     * @param String $needle Needle to search for
     * @param Array $filter Array with active filters for this type
     * @return Bool True if matching, false if not
     */
    protected function searchWithFilter(string $needle, array $filter): bool
    {
        if (strlen($needle) < $filter['minlength']) {
            return false;
        }
        if (strlen($needle) > $filter['maxlength']) {
            return false;
        }
        if (preg_match($filter['regex_case'], $needle) === 0) {
            return false;
        }
        return true;
    }
}
