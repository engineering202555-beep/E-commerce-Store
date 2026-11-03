<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as password_rule;


class ProfileCustomerController extends Controller
{
    public function viewProfileCustomer(){

        $customer = auth()->user()->customer;

        if ($customer) {
            return response()->json([
                'customer' => [
                    'id' => $customer->id,
                    'first_name'=>$customer['first_name'],
                    'last_name'=>$customer['last_name'],
                    'email'=>auth()->user()->email ?? null,
                    'phone'=>$customer['phone_number'],
                    'location'=>$customer['location'],
                    'loyalty_points'=>$customer['loyalty_points'],
                    'balance'=>$customer['points_payment'],
                    'birthdate'=>$customer['birthdate'],
                ]
            ],200);
        }
        return response()->json([
            'message' => 'User is not authenticated.'
        ], 401);
    }

    public function updateProfileCustomer(Request $request)
    {
        $customer = auth()->user()->customer;

        if (!$customer) {
            return response()->json(['error' => 'غير مسجل حالياً'], 403);
        }

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'birthdate' => 'nullable|date',
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'تم تعديل البروفايل بنجاح',
            'customer' => [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => auth()->user()->email, // نعرض الإيميل من جدول users
                'phone' => $customer->phone_number,
                'location' => $customer->location,
                'loyalty_points' => $customer->loyalty_points,
                'balance' => $customer->points_payment,
                'birthdate' => $customer->birthdate,
            ]
        ], 200);
    }
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', password_rule::min(8)],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'كلمة المرور الحالية غير صحيحة'
            ], 422);
        }

        $user->password = bcrypt($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'تم تحديث كلمة المرور بنجاح'
        ]);
    }



}
