<?php

/**
 * Handles layers and filters
 * Is extended by search Core class
 */
class Layers
{
    protected $layers = [];

    private $filters = [];

    /**
     * Return all active filters
     * @return Array Active filters array
     */
    public function getFilters(): array
    {
        return $this->layers;
    }

    /**
     * Remove all active filters and their respective layers
     */
    public function removeAllFilters(): void
    {
        $this->layers = [];
        $this->filters = [];
    }

    /**
     * Add search filters by any column and value combination present in `search_filters` table (DB)
     * Set order for layers and/or specify which layers to use with second parameter
     * Will add more filters if run more times.
     * @param Array $filters Must match a value in the `search_filters` table. Key = column, Value = value
     *      Use array as value if you need to match multiple values in a column.
     * @param Array $layers Must contain strings matching values in `search_filter_functions`.`functionName`.
     *      Only present search types will be active in the same order as input array.
     * @return Bool True if filters is successfully set, false if not
     */
    public function addFilters(array $filters, array $layers): bool
    {
        if (empty($filters) || empty($layers)) {
            return false;
        }

        $params = [];
        $bind_params = [];
        foreach ($filters as $col_name => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $in_vals[] = '?';
                    $params[] = $v;
                }
                $bind_params[] = 'sf.`' . $col_name . '` IN (' . implode(', ', $in_vals) . ')';
            } else {
                $params[] = $value;
                $bind_params[] = 'sf.`' . $col_name . '` = ?';
            }
        }
        if (empty($params) || empty($bind_params)) {
            return false;
        }
        $this->loadMatchingFilters($params, $bind_params);
        $this->addAllActiveFilters($layers);
        return true;
    }

    /**
     * Set all filters matching input parameters to $this->filters
     * @param Array $params The values to bind to SQL query
     * @param Array $bind_params Contains a ? for each $param to bind
     */
    private function loadMatchingFilters(array $params, array $bind_params): void
    {
        if (empty($params) || empty($bind_params)) {
            return;
        }
        $sql_result = $this->PDOfw->run(
            "SELECT     sf.`id`,
                        sf.`name`,
                        sf.`display_name`,
                        sf.`needle_type`,
                        sf.`return_type`
            FROM        `search_v2_filters` sf
            WHERE       " . implode(' AND ', $bind_params) . "
            ORDER BY    sf.`needle_type`, sf.`display_name` DESC",
            $params
        );
        $this->filters = $sql_result->fetchAll();
    }

    /**
     * Add a new instance for each layer to be used, then add it's active filters
     */
    private function addAllActiveFilters(array $layer_names): void
    {
        if (empty($this->filters)) {
            return;
        }
        $layers = $this->getLayers($layer_names);
        if (empty($layers)) {
            return;
        }
        foreach ($layers as $layer) {
            if (!isset($this->layers[$layer])) {
                $this->layers[$layer] = new $layer($this->config->db_server, $this->config->database, $layer);
            }
            $this->layers[$layer]->addActiveFilters($this->filters);
        }
    }

    /**
     * Get all search layers from the database
     * @return Array Return all active layers
     */
    private function getLayers($layer_names): array
    {
        $bind_params = [];
        $order = [];
        $layer_count = count($layer_names);
        for ($i = 1; $i <= $layer_count; $i++) {
            $bind_params[] = '?';
            $order[] = '? THEN ' . $i;
        }
        $sql_result = $this->PDOfw->run(
            'SELECT     `function_name` fname
            FROM        `search_v2_layers`
            WHERE       `function_name` IN (' . implode(', ', $bind_params) . ')
                AND     `active` = 1
            ORDER BY    CASE fname
                WHEN ' . implode(' WHEN ', $order) . ' END',
            array_merge($layer_names, $layer_names)
        );
        return $sql_result->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Get main filter array from id
     * @param Int $id ID for search type
     * @return Array Return matching array, or empty array if no match is found
     */
    protected function getFilterType(int $id): array
    {
        if (empty($this->filters) || empty($id)) {
            return [];
        }
        foreach ($this->filters as $filter) {
            if ($filter['id'] === $id) {
                return $filter;
            }
        }
        return [];
    }
}
