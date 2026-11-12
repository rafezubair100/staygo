<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Review;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function admin()
    {
        $stats = [
            'users' => User::count(), 'hotels' => Hotel::count(),
            'bookings' => Booking::count(), 'pending_reviews' => Review::where('status', 'pending')->count(),
        ];
        $recentBookings = Booking::with(['user', 'hotel'])->latest()->take(8)->get();
        return view('staff.admin', compact('stats', 'recentBookings'));
    }

    public function users() { $users = User::latest()->paginate(20); return view('staff.users', compact('users')); }

    public function deleteUser(User $user)
    {
        abort_if($user->role === 'admin' && User::where('role', 'admin')->count() <= 1, 422, 'You cannot delete the only administrator.');
        $user->delete();
        return back()->with('success', 'User deleted.');
    }

    public function reviews()
    {
        $reviews = Review::with(['user', 'hotel'])->latest()->paginate(20);
        return view('staff.reviews', compact('reviews'));
    }

    public function moderateReview(Request $request, Review $review)
    {
        $data = $request->validate(['status' => ['required', 'in:published,rejected']]);
        $review->update($data);
        return back()->with('success', 'Review moderation updated.');
    }

    public function manager()
    {
        $properties = Hotel::latest()->paginate(20);
        return view('staff.manager', compact('properties'));
    }
}
