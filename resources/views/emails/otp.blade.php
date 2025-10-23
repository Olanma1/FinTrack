@component('mail::message')
# FinTrack Email Verification

Your One-Time Password (OTP) is:

@component('mail::panel')
**{{ $otp }}**
@endcomponent

Please enter this code in the FinTrack app to verify your account.  
This OTP expires in 10 minutes.

Thanks,<br>
**The FinTrack Team**
@endcomponent
