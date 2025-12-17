<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuthorResource;
use App\Http\Resources\Admin\AuthroResource;
use App\Models\Author;
use App\Models\User;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
       
        // prepare query
        $authorQuery = User::query();

        $authorQuery->where('type', 'author');

        if ($request->has('status')) {
            $authorQuery->where('status', $request->status);
        }
        $authors = $authorQuery->with('author')->get();
        // ->with(relationName) query
        // ->load(relationName) collection
        // return $authors;
        return AuthorResource::collection($authors);
    }

    public function approve($user_id)
    {
        $user = User::where('type', 'author')
            ->where('status','!=','approve')
            ->where('id', $user_id)
            ->firstOrFail();

        $user->approve();
        return response()->json([
            'message' => 'author approved'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
