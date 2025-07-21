<?php
namespace DMJohnson\Miroql\SqlBuilder;

/**
 * Utility class for paginating a data set. Typically passed as the last parameter to a data access method.
 */
class Paginator{
    public $pageResultCount;
    public $totalResults;
    public $maxPerPage;
    public $queryParam;

    public function __construct($maxPerPage=100, $queryParam='page'){
        $this->maxPerPage = $maxPerPage;
        $this->queryParam = $queryParam;
    }

    /**
     * Use request data to build a limit appropriate for Data::buildLimitClause
     * 
     * @return array{page:int,count:int}
     */
    function paginate(){
        return [
            'page' => $this->currentPage(),
            'count' => $this->maxPerPage
        ];
    }

    /**
     * Get the current page number.
     * 
     * @return int
     */
    function currentPage(){
        return isset($_REQUEST[$this->queryParam]) ? $_REQUEST[$this->queryParam] : 1;
    }

    /**
     * Get the next page number. Does not guarantee that the returned page actually is valid or exists.
     * 
     * @return ?int
     */
    function nextPage(){
        if ($this->hasNext()) return $this->currentPage() + 1;
    }

    /**
     * Get the previous page number. Does not guarantee that the returned page actually is valid or exists.
     * 
     * @return ?int
     */
    function prevPage(){
        if ($this->hasPrev()) return $this->currentPage() - 1;
    }

    /**
     * Check if there is a valid next page. (Either `$totalResults` or `$pageResultCount` must be set).
     * 
     * @return bool
     */
    function hasNext(){
        $lastPage = $this->lastPage();
        if (isset($lastPage)){
            return $this->currentPage() < $lastPage;
        }
        elseif (isset($this->pageResultCount)){
            return $this->pageResultCount == $this->maxPerPage;
        }
        else return true; // just guess
    }


    /**
     * Check if there is a valid previous page
     * 
     * @return bool
     */
    function hasPrev(){
        return $this->currentPage() > 1;
    }

    /**
     * Get the page number of the last valid page. (`$totalResults` must be set)
     * 
     * @return ?int
     */
    function lastPage(){
        if (isset($this->totalResults)){
            return intval(ceil($this->totalResults / $this->maxPerPage));
        }
    }
}