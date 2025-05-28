<?php

namespace App\Actions;

use Illuminate\Support\Collection;

class ProcessData
{
    public function __invoke(): bool
    {
        $employees = [
            ['name' => 'John', 'city' => 'Dallas'],
            ['name' => 'Jane', 'city' => 'Austin'],
            ['name' => 'Jake', 'city' => 'Dallas'],
            ['name' => 'Jill', 'city' => 'Dallas'],
        ];

        $offices = [
            ['office' => 'Dallas HQ', 'city' => 'Dallas'],
            ['office' => 'Dallas South', 'city' => 'Dallas'],
            ['office' => 'Austin Branch', 'city' => 'Austin'],
        ];

        $output = [
            'Dallas' => [
                'Dallas HQ' => ['John', 'Jake', 'Jill'],
                'Dallas South' => ['John', 'Jake', 'Jill'],
            ],
            'Austin' => [
                'Austin Branch' => ['Jane'],
            ],
        ];

        // Convert arrays to collections then group by "city"
        $employees = collect($employees)->groupBy('city')->map(fn (Collection $arr) => $arr->pluck('name'));
        $offices = collect($offices)->groupBy('city')->map(fn (Collection $arr) => $arr->pluck('office'));

        // Assuming both collections might not have the number of similar keys
        // Therefore, we need to combine the unique "city" keys then get the unique ones
        $keys = $employees->keys()->merge($offices->keys())->unique();

        // Use mapWithKeys
        $result = $keys->mapWithKeys(fn ($key) => [$key => $offices->get($key)->mapWithKeys(fn ($office) => [$office => $employees->get($key)->values()])]);

        // When compared, this should return true
        return $result->toArray() == $output;
    }
}
