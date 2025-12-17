<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function signup(Request $request)
    {
        $inputs = $request->validate([
            //user
            'name'=>['required'],
            'username'=>['required','unique:users'],
            'password'=>['required'],
            // author
            'bio'=>['required'],
            'country'=>['required']
        ]);
        $inputs['type'] = 'author';
        $inputs['status'] = 'pending';
        //TODO:: make db transaction
        $user = User::create($inputs);
        $user->author()->create($inputs);

        return response()->json([
            'message'=>'you ware signup wait for approve'
        ],201);



    }
}
