@extends('layouts.app')
@section('title','Manage users')
@section('content')
<div class="container py-5"><h1>Manage users</h1><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th></th></tr></thead><tbody>@foreach($users as $user)<tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->role }}</td><td>{{ $user->created_at->format('d M Y') }}</td><td>@if($user->id !== auth()->id())<form method="POST" action="{{ route('admin.users.delete',$user) }}" onsubmit="return confirm('Delete this user and related records?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endif</td></tr>@endforeach</tbody></table></div>{{ $users->links() }}</div>
@endsection
