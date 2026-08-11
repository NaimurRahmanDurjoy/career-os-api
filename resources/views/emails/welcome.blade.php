<x-mail::message>
# Welcome to Career OS Pro, {{ explode(' ', $user->name)[0] }}!

We're thrilled to have you onboard. Career OS is designed to be your ultimate AI-powered career assistant, helping you land your dream job faster.

Here is a quick overview of what you can do right now:
- **Build Optimized Resumes:** Automatically parse and optimize your resumes against job descriptions.
- **Track Applications:** Manage all your job applications in our drag-and-drop Kanban board.
- **Prepare for Interviews:** Generate custom AI Mock Tests tailored to your exact industry and experience level.
- **Analyze Job Fits:** Use our AI tools to instantly calculate match scores against any job description.

Click below to access your workspace and get started.

<x-mail::button :url="config('app.frontend_url', 'http://localhost:5173') . '/dashboard'" color="success">
Go to Dashboard
</x-mail::button>

If you have any questions or need help, just reply to this email!

Best,<br>
The Career OS Team
</x-mail::message>
