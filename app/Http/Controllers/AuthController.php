<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;


class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            ...$request->validated(),
            'is_verified' => false,
        ]);

        // Generate OTP
        $otp = rand(100000, 999999);
        $user->otp = $otp;
        $user->is_verified = false;
        $user->save();

        // Send OTP to user

        Mail::raw("Your FinTrack OTP code is: {$otp}", function ($message) use ($user) {
        $message->to($user->email)
                ->subject('Your FinTrack OTP Code');
        });
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(10),
        ]);

        return response()->json(['message' => 'OTP sent to your email. Please verify to continue.'], 201);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->is_verified) {
            return response()->json(['message' => 'Account already verified'], 400);
        }

        if ($user->otp !== $request->otp) {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        if (Carbon::now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'OTP has expired'], 400);
        }

        $user->update([
            'is_verified' => true,
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        if ($user->is_verified) {
            Mail::raw("Welcome to FinTrack!!! Your one stop shop for finacial Book keeping", function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Welcome');
        });
        }
        
        return response()->json([
            'message' => 'Account verified successfully',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->is_verified) {
            return response()->json(['message' => 'User already verified'], 400);
        }

        // Prevent resending too soon
        if ($user->otp_expires_at && Carbon::now()->lessThan($user->otp_expires_at->subMinutes(9))) {
            return response()->json([
                'message' => 'Please wait before requesting another OTP.'
            ], 429);
        }

        $this->sendOtp($user);

        return response()->json(['message' => 'New OTP sent to your email.']);
    }

    private function sendOtp($user)
    {
        $otp = rand(100000, 999999);
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(10),
        ]);

        Mail::raw("Your FinTrack verification code is: {$otp}", function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('FinTrack Account Verification Code');
        });
    }


    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        $token = $user->createToken('api')->plainTextToken;
        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function user(Request $request)
    {
        return $request->user();
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('avatar')) {

            Log::info('Cloudinary URL: ' . env('CLOUDINARY_URL'));
            $uploaded = Cloudinary::upload(
                $request->file('avatar')->getRealPath(),
                ['folder' => 'avatars']
            );

            $validated['avatar'] = $uploaded->getSecurePath();
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }


    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
