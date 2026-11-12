<?php

namespace App\Services;

class PackingCalculator
{
    /**
     * Calculate base_qty for each level.
     * Input $levels should be ordered from Level 1 (largest) down to Level N (base unit).
     * Returns array ordered in ascending level order (Level 1..N) with contains_qty and base_qty set.
     * 
     * Example: Carton(10) > Bottle(5) > Litre gives base_qty: 50 / 5 / 1.
     */
    public static function withBaseQty(array $levels): array
    {
        $n = count($levels);
        if ($n === 0) {
            return [];
        }

        // Assign levels 1..N if not explicitly set
        $indexed = [];
        $lvl = 1;
        foreach ($levels as $item) {
            $item['level'] = isset($item['level']) ? (int) $item['level'] : $lvl;
            $indexed[] = $item;
            $lvl++;
        }

        // Sort so the smallest level (base unit, largest level number) is first
        usort($indexed, function ($a, $b) {
            return $b['level'] <=> $a['level'];
        });

        // Smallest level gets contains_qty = 1, base_qty = 1
        $prevBaseQty = 1.0;
        foreach ($indexed as $i => &$item) {
            if ($i === 0) {
                $item['contains_qty'] = 1.0000;
                $item['base_qty'] = 1.0000;
                $prevBaseQty = 1.0000;
            } else {
                $contains = (float) ($item['contains_qty'] ?? 1);
                $item['contains_qty'] = round($contains, 4);
                $item['base_qty'] = round($contains * $prevBaseQty, 4);
                $prevBaseQty = $item['base_qty'];
            }
        }
        unset($item);

        // Return in ascending level order (1..N)
        usort($indexed, function ($a, $b) {
            return $a['level'] <=> $b['level'];
        });

        return array_values($indexed);
    }
}

