<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Hotel $hotel)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:15', 'max:3000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('review-photos', 'public');
        }
        unset($data['photo']);
        $hotel->reviews()->create($data + [
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'verified_stay' => false,
        ]);
        return back()->with('success', 'Thanks. Your review has been submitted for moderation.');
    }
}
