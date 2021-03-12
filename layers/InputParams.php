<?php
/**
 * Search for match in input parameters ($needles)
 */
class InputParams extends LayersAbstract {

    /**
     * Get the query string used to add all sub filters for layer
     * @return String Query string
     */
    protected function getSubfiltersQuery(): string
    {
        return
        'SELECT     slip.`id`,
                    slip.`filters_id`,
                    slip.`param_key`,
                    slip.`match_value`
        FROM        `search_v2_layer_input_params` slip
        WHERE       slip.`filters_id` IN ({bind_params})
        AND         slip.`active` = 1
        ORDER BY    slip.`priority` DESC';
    }

    /**
     * Search all filters for matches vs needle
     * Result is saved to matching_needles and no_matches
     * @param Array $needles Needles to search for
     * @param String $search_key Array key in needles to search for
     */
    public function searchAllNeedles(array $needles, string $search_key): void
    {
        if(empty($this->sub_filters) || empty($needles) || empty($search_key)) {
            return;
        }
        foreach($needles as $key => $needle) {
            if($this->doEarlyReturn($key, $needle, $search_key)) {
                continue;
            }
            foreach ($this->sub_filters as $filter) {
                $match_val = Core::getValueFromNeedle($key, $filter['param_key']);
                if($match_val === null) {
                    return;
                }
                $search_needle = $this->getNeedleForFilter($needle, $search_key, $filter['filters_id']);
                if(empty($search_needle)) {
                    continue;
                }
                if($match_val === $filter['match_value']){
                    $this->setMatchingNeedle($key, $search_key, $search_needle, $filter['filters_id']);
                }
            }
        }
    }
}
