<?php

/**
 * Contains default settings for search
 * Used by search Core to export/import searches
 */
class SearchObject
{
    public $search_key; // Key to use as needle in input array
    public $config; // Search config
    public $sub_filters = []; // Active sub filters (export)
    public $layers = []; //  Active layers (export)
    public $needles = []; // Active needles (export)

    /**
     * Constructor
     * Set default config for search Core
     */
    public function __construct(string $db_server = '127.0.0.1')
    {
        $this->config = new stdClass();
        $this->config->prefix = 's_';
        $this->config->database = DATABASE;
        $this->config->db_server = $db_server;
        $this->config->limit = 0;
        $this->config->quick_search = true;
        $this->config->return_values = [];
        $this->config->conjoined_result = false;
    }
}
