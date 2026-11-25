<x-mail::message>
# Verify Your Email Address

Hello {{ explode(' ', $user->name)[0] }},

Thank you for signing up for **Career OS**! To protect your account and unlock your workspace, please enter the 6-digit verification code below:

<div style="text-align: center; margin: 30px 0;">
  <div style="display: inline-block; background: #0f172a; color: #10b981; font-size: 32px; font-weight: 800; letter-spacing: 10px; padding: 16px 32px; border-radius: 12px; border: 1px solid #334155;">
    {{ $otp }}
  </div>
</div>

> **Note:** This code is valid for **15 minutes**. Never share this code with anyone.

If you did not sign up for Career OS, no further action is required and you can safely ignore this email.

Best regards,<br>
The Career OS Security Team
</x-mail::message>
