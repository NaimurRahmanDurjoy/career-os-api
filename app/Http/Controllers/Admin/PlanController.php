<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return response()->json(Plan::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'identifier' => 'required|string|unique:plans',
            'name' => 'required|string|max:255',
            'price_bdt' => 'required|integer',
            'price_usd' => 'required|integer',
            'features' => 'nullable|array',
            'is_popular' => 'boolean',
            'is_active' => 'boolean'
        ]);

        $plan = Plan::create($validated);
        return response()->json($plan, 201);
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'identifier' => 'string|unique:plans,identifier,' . $plan->id,
            'name' => 'string|max:255',
            'price_bdt' => 'integer',
            'price_usd' => 'integer',
            'features' => 'nullable|array',
            'is_popular' => 'boolean',
            'is_active' => 'boolean'
        ]);

        $plan->update($validated);
        return response()->json($plan);
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();
        return response()->json(['message' => 'Plan deleted successfully.']);
    }
}
