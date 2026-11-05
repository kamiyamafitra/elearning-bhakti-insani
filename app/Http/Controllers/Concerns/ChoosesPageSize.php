<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ChoosesPageSize
{
    public const DEFAULT_PER_PAGE = 5;

    /**
     * Rows per page the admin can pick, in steps of 5.
     *
     * @var list<int>
     */
    public const PER_PAGE_OPTIONS = [5, 10, 15, 20, 25, 30, 35, 40, 45, 50];

    /**
     * Read the requested page size, falling back to the default for anything not on the list.
     */
    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page');

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
    }
}
