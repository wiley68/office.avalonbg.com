<?php

namespace Database\Seeders;

use App\Enums\ProfitTypeKind;
use App\Models\Group;
use Illuminate\Database\Seeder;

class ProfitTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Продажби', 'kind' => ProfitTypeKind::Income],
            ['name' => 'Услуги', 'kind' => ProfitTypeKind::Income],
            ['name' => 'Други приходи', 'kind' => ProfitTypeKind::Income],
            ['name' => 'Наем', 'kind' => ProfitTypeKind::Expense],
            ['name' => 'Заплати', 'kind' => ProfitTypeKind::Expense],
            ['name' => 'Материали', 'kind' => ProfitTypeKind::Expense],
            ['name' => 'Други разходи', 'kind' => ProfitTypeKind::Expense],
        ];

        foreach (Group::query()->lazyById() as $group) {
            foreach ($types as $type) {
                $group->profitTypes()->firstOrCreate([
                    'name' => $type['name'],
                    'kind' => $type['kind']->value,
                ]);
            }
        }
    }
}
