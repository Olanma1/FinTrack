@component('mail::message')
# Your OTP is here 🎉

Hi,

You're almost there!!

Your FinTrack verification code is: {{ $user->otp }}.

If you didn’t sign up for this account, please ignore this email or contact our support team.

Thanks,  
**FinTrack Team**
@endcomponent
