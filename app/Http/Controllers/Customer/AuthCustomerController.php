<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailCode;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as password_rule;

class AuthCustomerController extends Controller
{
    public function registerCustomer(Request $request)
    {
        $validateData = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:55', 'regex:/^[\p{Arabic}a-zA-Z\s]+$/u'], //mixed from arabic and english litters ,and spaces.
            'last_name' => ['required', 'string', 'max:55', 'regex:/^[\p{Arabic}a-zA-Z\s]+$/u'],
            'phone_number' => ['required', 'string', 'unique:customers', 'regex:/^\+\d{12}$/'],
            'password' => ['required', 'confirmed',password_rule::min(8)],

            'email' => ['nullable','email', 'unique:users'],
            'location' => ['nullable','string', 'max:100'],
            'image' => ['nullable','image', 'mimes:jpeg,jpg,png,gif', 'max:2048']
        ]);

        if ($validateData->fails()) {
            return response()->json(['errors' => $validateData->errors()], 401);
        }

        $profileImagePath = null;          //php artisan storage:link

        if ($request->hasFile('image')) {
            $profileImagePath = $request->file('image')->store('profile_images', 'public');
        }
        $user = User::create([
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => 'customer',
        ]);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل معلوماتك'
            ]);
        }

        if ($request->filled('email')) {
            $code = rand(100000, 999999);
            EmailVerification::create([
                'email' => $request->email,
                'code' => $code,
                'expires_at' => now()->addMinutes(10),
            ]);
            Mail::to($request->email)->send(new VerifyEmailCode($code));
        }

        $user->customer()->create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'location' => $request->location,
            'phone_number' => $request->phone_number,
            'image' => $profileImagePath,
            'points_payment' => 600000,
        ]);

        $otp = rand(100000, 999999);

        DB::table('phone_otps')->insert([
            'user_id' => $user->id,
            'phone' => $request->phone_number,
            'code' => $otp,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $message = "رمز التحقق الخاص بك هو: {$otp}. صالح لمدة 10 دقائق.";
        sendWhatsAppMessage($request->phone_number, $message);

        $user->assignRole('customer');

        $accesstoken = $user->createToken('Customer Access Token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل حسابك بنجاح',
            'data' => [
                'customer_id' => $user->customer->id,
                'role' => $user->getRoleNames(),
                'first_name' => $user->customer->first_name,
                'last_name' => $user->customer->last_name,
                'location' => $user->customer->location,
                'email' => $user->email,
                'phone_number' => $user->customer->phone_number,
                'image' => $user->customer->image,
            ],
            'access_token' => $accesstoken,
        ], 200);
    }

    public function verifyEmailCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
        ]);

        $record = EmailVerification::where('email', $request->email)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->where('verified', false)
            ->first();

        if (!$record) {
            return response()->json(['message' => 'رمز غير صالح أو منتهي الصلاحية'], 422);
        }

        $record->update(['verified' => true]);

        User::where('email', $request->email)->update(['email_verified_at' => now()]);

        return response()->json(['message' => 'تم التحقق من بريدك الإلكتروني بنجاح']);
    }
    public function resendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'هذا البريد الإلكتروني تم تأكيده مسبقًا'
            ], 400);
        }

        EmailVerification::where('email', $request->email)
            ->where('verified', false)
            ->delete();

        $code = rand(100000, 999999);

        EmailVerification::create([
            'email' => $request->email,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($request->email)->send(new VerifyEmailCode($code));

        return response()->json(['message' => 'تم إرسال رمز تحقق جديد إلى بريدك الإلكتروني']);
    }

    public function verifyPhoneOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|regex:/^\+\d{12}$/',
            'code' => 'required|numeric',
        ]);

        $otpData = DB::table('phone_otps')
            ->where('phone', $request->phone_number)
            ->where('verified', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otpData) {
            return response()->json(['message' => 'الكود غير صالح أو منتهي'], 422);
        }

        if ($otpData->code != $request->code) {
            return response()->json(['message' => 'رمز خاطئ'], 422);
        }

        DB::table('phone_otps')->where('id', $otpData->id)->update([
            'verified' => true,
            'updated_at' => now(),
        ]);

        User::where('id', $otpData->user_id)->update(['phone_verified_at' => now()]);

        return response()->json(['message' => 'تم التحقق من رقم الهاتف بنجاح']);
    }

    public function resendPhoneOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|regex:/^\+\d{12}$/',
        ]);

        $phone = $request->phone_number;
        $user = Customer::where('phone_number', $phone)->first();

        if (!$user) {
            return response()->json([
                'message' => 'الرقم غير موجود في قاعدة البيانات.'
            ], 404);
        }

        if ($user->phone_verified_at !== null) {
            return response()->json([
                'message' => 'هذا الرقم تم تأكيده مسبقًا'
            ], 400);
        }

        $otpData = DB::table('phone_otps')
            ->where('phone', $phone)
            ->where('verified', false)
            ->latest()
            ->first();

        if (!$otpData || now()->gt($otpData->expires_at)) {
            $otp = rand(100000, 999999);

            DB::table('phone_otps')->insert([
                'user_id' => $user ? $user->user_id : null,
                'phone' => $phone,
                'code' => $otp,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $message = "رمز التحقق الخاص بك هو: {$otp}. صالح لمدة 10 دقائق.";
            sendWhatsAppMessage($phone, $message);

            return response()->json([
                'message' => 'تم إرسال رمز التحقق بنجاح',
                'otp' => $otp
            ]);

        } else {
            return response()->json([
                'message' => 'رمز التحقق الحالي لا يزال صالحًا. انتظر حتى تنتهي صلاحيته لإعادة الإرسال.'
            ], 422);
        }
    }


    public function loginCustomer(Request $request)
    {
        $request->validate([
            'phone_number' => ['required', 'string','regex:/^\+\d{12}$/' ],
            'password' => ['required', password_rule::min(8)],
        ]);

        $customer = Customer::where('phone_number', $request->phone_number)->first();

        if (! $customer || ! Hash::check($request->password, $customer->user->password)) {
            return response()->json([
                'success' => false,
                'message' => ['عذراً هذه المعلومات غير متطابقة']
            ], 422);
        }

        $accesstoken = $customer->user->createToken('Customer Access Token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'customer_id' => $customer->id,
                'phone_number' => $customer->phone_number,
            ],
            'access_token' => $accesstoken,
        ],200);
    }

    public function logoutCustomer()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'هذا الحساب غير موجود , الرجاء تسجيل الدخول أو تسجيل الحساب أولاً.'], 401);
        }

        if ($user && $user->customer) {
            DeliveryOrder::where('customer_id', $user->customer->id)
                ->where('status', 'pending')
                ->delete();
        }

        Auth::user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح.'
        ]);
    }

}
