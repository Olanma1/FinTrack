<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Models\Budget;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return response()->json($request->user()->budgets()->with('category')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBudgetRequest $request)
    {
        $budget = $request->user()->budgets()->create($request->validated());
        return response()->json($budget, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Budget $budget)
    {
        // $this->authorize('view', $budget);
        return response()->json([
            'budget' => $budget,
            'progress' => $budget->progress(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreBudgetRequest $request, Budget $budget)
    {
        // $this->authorize('update', $budget);
        $budget->update($request->validated());
        return response()->json($budget);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Budget $budget)
    {
        //  $this->authorize('delete', $budget);
        $budget->delete();
        return response()->json(['message' => 'Budget deleted']);
    }
}
