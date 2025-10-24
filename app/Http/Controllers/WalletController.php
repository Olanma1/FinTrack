<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        $wallet = $request->user()->wallet()->with('user')->first();
        $wallet->recalculateBalance();

        return response()->json([
            'balance' => $wallet->balance,
            'currency' => $wallet->currency,
        ]);
    }
}
