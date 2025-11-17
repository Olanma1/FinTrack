@component('mail::message')
# Bank Account Unlinked

Hi {{ $user->name }},

Your bank account has been successfully unlinked from your FinTrack dashboard.

You will no longer receive new automatic transactions from this bank.

If this wasn’t you, please secure your account immediately by updating your password.

Thanks,  
**FinTrack Team**
@endcomponent
