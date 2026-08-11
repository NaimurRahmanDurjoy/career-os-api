<x-mail::message>
# Thank you for subscribing!

Your payment for **{{ $planName }}** was successful. We've immediately upgraded your workspace limits.

### Receipt Details
<x-mail::panel>
**Amount Paid:** {{ $amount }} <br>
**Transaction ID:** {{ $transactionId }}
</x-mail::panel>

You now have instant access to our advanced AI career tools, mock testing, and automated application tracking features. 

<x-mail::button :url="config('app.frontend_url', 'http://localhost:5173') . '/billing'" color="primary">
View Billing Dashboard
</x-mail::button>

If you have any questions about your subscription, please do not hesitate to contact us.

Thanks again,<br>
The Career OS Team
</x-mail::message>
