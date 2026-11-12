<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user()->load(['bookings.hotel', 'favourites']);
        return view('account.dashboard', compact('user'));
    }

    public function toggleFavourite(Request $request, Hotel $hotel)
    {
        $request->user()->favourites()->toggle($hotel->id);
        return back()->with('success', 'Your saved hotels have been updated.');
    }
}
