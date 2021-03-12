<?php

/**
 * Will contain result from search
 * Used by search Core
 */
class SearchResult
{
    public $message = ''; // Result message
    public $page = 1; // Page number
    public $total_pages = 1; // Total pages calculated from limit and needles
    public $total_matches = 0; // Total items in matches array
    public $total_indecisive_matches = 0; // Total items in indecisive matches array
    public $total_no_matches = 0; // Total items in no matches array
    public $matches = []; // Definitive matches
    public $indecisive_matches = []; // Indecisive matches
    public $no_matches = []; // Needles not matching anything
}
