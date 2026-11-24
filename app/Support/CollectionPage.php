<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Pages a collection already in memory (report rows, a course's doses) the
 * same way a query is paged, keeping the page's other query string values.
 */
final class CollectionPage
{
    public static function make(Collection $items, int $perPage, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = max(1, (int) request()->query($pageName, 1));

        return (new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName,
        ]))->withQueryString();
    }
}
