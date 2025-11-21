<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use App\Mail\WelcomeUserMail;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Cloudinary\Api\Upload\UploadApi;

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

        Mail::to($user->email)->send(new OtpMail($user));

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
            Mail::to($user->email)->send(new WelcomeUserMail($user));
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
            'name'   => 'sometimes|string|max:255',
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            try {
                // Initialize UploadApi with config array from .env
                $uploadApi = new UploadApi([
                    'cloud' => [
                        'cloud_name' => config('cloudinary.cloud.cloud_name'),
                        'api_key'    => config('cloudinary.cloud.api_key'),
                        'api_secret' => config('cloudinary.cloud.api_secret'),
                    ],
                    'url' => [
                        'secure' => true,
                    ],
                ]);

                $uploaded = $uploadApi->upload($request->file('avatar')->getRealPath(), [
                    'folder' => 'avatars',
                ]);

                $validated['avatar'] = $uploaded['secure_url'];
            } catch (\Exception $e) {
                Log::error('Cloudinary upload failed: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Failed to upload avatar',
                    'error'   => $e->getMessage(),
                ], 500);
            }
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();

        $user->delete();

        return response()->json([
            'message' => 'Your account has been deleted. You can restore it within 30 days.',
        ]);
    }

}
