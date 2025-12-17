<?php

namespace App\Http\Controllers\Customer;

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
            // customer
            'phone_number'=>['required'],
            'address'=>['required'],
            'email'=>['required','email']
        ]);
        $inputs['type'] = 'customer';

        //TODO:: make db transaction
        $user = User::create($inputs);
        $user->customer()->create($inputs);

        return response()->json([
            'message'=>'you sign up '
        ],201);



    }
    public function editProfile(Request $request)
    {
        $user = $request->user();
        $customer = $user->customer;

        $validated = $request->validate([
            'phone_number' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|max:250|unique:users,username,' . $user->id,
            'address' => 'sometimes|string|min:8',
        ]);

        if (isset($validated['phone_number'])) {
            $customer->phone_number = $validated['phone_number'];
        }

        if (isset($validated['email'])) {
            $customer->email = $validated['email'];
        }

        if (isset($validated['address'])) {
            $customer->address = $validated['address'];
        }

        $customer->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'customer' => [
                'phone_number' => $customer->phone_number,
                'email' => $customer->email,
                'address' => $customer->address,
            ]
        ]);
    }
}
