<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(protected DeliveryService $delivery) {}

    /**
     * Pincode serviceability check, called from the product page and header.
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'pincode' => ['required', 'string', 'max:6'],
        ]);

        $result = $this->delivery->check((string) $request->input('pincode'));

        // Remember it so the next product page can show it straight away.
        if ($result['found']) {
            $request->session()->put('pincode', $result['pincode']);
        }

        return response()->json($result);
    }
}
