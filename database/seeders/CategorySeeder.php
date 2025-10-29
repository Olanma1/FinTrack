<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Income Categories
            ['name' => 'Salary', 'type' => 'income'],
            ['name' => 'Freelance', 'type' => 'income'],
            ['name' => 'Investments', 'type' => 'income'],
            ['name' => 'Gifts', 'type' => 'income'],
            ['name' => 'Refunds', 'type' => 'income'],

            // Expense Categories
            ['name' => 'Groceries', 'type' => 'expense'],
            ['name' => 'Transport', 'type' => 'expense'],
            ['name' => 'Bills', 'type' => 'expense'],
            ['name' => 'Subscriptions', 'type' => 'expense'],
            ['name' => 'Entertainment', 'type' => 'expense'],
            ['name' => 'Health', 'type' => 'expense'],
            ['name' => 'Education', 'type' => 'expense'],
            ['name' => 'Others', 'type' => 'expense'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate($category);
        }
    }
}
