<?php

namespace App\Console\Commands\Benchmark;

use WishKnish\KnishIO\Helpers\Cleaner;
use WishKnish\KnishIO\Models\Cell;

/**
 * Class BenchmarkCellFactory
 */
class BenchmarkCellFactory
{
    /**
     * @param string $slug
     * @return Cell
     */
    public static function create ( string $slug ): Cell
    {
        $cell = Cell::find( $slug );

        if(!$cell) {
            $cell = new Cell();
            $cell->cell_slug = $slug;
            $cell->save();
        }

        return $cell;
    }

    /**
     * @param string $slug
     */
    public static function destroy ( string $slug ): void
    {
        Cleaner::byCell( $slug );
    }
}
