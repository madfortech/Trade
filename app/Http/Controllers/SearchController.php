<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Sensex\SensexScripService;

class SearchController extends Controller
{
    public function index()
    {
        return view('stock-search.index');
    }

    public function search(
        Request $request,
        SensexScripService $scripService
    ) {

        $query = strtoupper(
            trim($request->get('q', ''))
        );

        if (!$query) {
            return response()->json([
                'data' => []
            ]);
        }

        $master = $scripService->master();

        $exactMatches = [];
        $partialMatches = [];

        foreach ($master as $item) {

            if (strtoupper($item['exch_seg'] ?? '') !== 'NSE') {
                continue;
            }

            $symbol = strtoupper($item['symbol'] ?? '');

            if ($symbol === $query . '-EQ') {
                return response()->json([
                    'data' => [[
                        'symbol'   => $item['symbol'],
                        'exchange' => 'NSE',
                        'token'    => (string)($item['token'] ?? ''),
                    ]]
                ]);
            }
        }

        return response()->json([
            'data' => []
        ]);
        
        $results = array_merge(
            $exactMatches,
            $partialMatches
        );

        // Remove duplicates by token
        $unique = [];

        foreach ($results as $item) {

            $unique[$item['token']] = $item;
        }

        $results = array_values($unique);

        // Limit results
        $results = array_slice(
            $results,
            0,
            20
        );

        return response()->json([
            'data' => $results
        ]);
    }
}