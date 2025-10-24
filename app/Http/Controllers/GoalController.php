<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoalRequest;
use App\Models\Goal;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return response()->json($request->user()->goals()->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGoalRequest $request)
    {
        $goal = $request->user()->goals()->create($request->validated());
        return response()->json($goal, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Goal $goal)
    {
        return response()->json([
            'goal' => $goal,
            'progress_percent' => $goal->progress(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Goal $goal)
    {
        if ($request->has('add_progress')) {
            $goal->addProgress($request->input('add_progress'));
        } else {
            $goal->update($request->only(['name', 'target_amount', 'deadline']));
        }

        return response()->json($goal);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Goal $goal)
    {
        // $this->authorize('delete', $goal);
        $goal->delete();
        return response()->json(['message' => 'Goal deleted']);
    }
}
