<?php

namespace App\Http\Controllers\Admin;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::where('type', '!=', 'admin')->paginate(15);
        return $users;
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:6',
            'type' => 'required|in:seller,manufacturer,supervisor',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['status'] = 'approve';

        $user = User::create($validated);

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    public function block($user_id)
    {
        $user = User::where('type', '!=', 'admin')
            ->where('id', $user_id)
            ->firstOrFail();

        $user->block();
        $user->tokens()->delete();
        return response()->json([
            'message' => 'user blocked'
        ]);
    }

    public function unblock($user_id)
    {
        $user = User::where('type', '!=', 'admin')
            ->where('id', $user_id)
            ->firstOrFail();

        $user->approve();
        return response()->json([
            'message' => 'user unblocked'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
