<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use App\Jobs\ProcessResumeJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

use OpenAI\Laravel\Facades\OpenAI; // আমরা OpenAI ফ্যাসাডটিই ব্যবহার করব
use Exception;

class ResumeController extends Controller
{
    
    public function index(Request $request)
    {
        $resumes = $request->user()->resumes()->orderBy('created_at', 'desc')->get();
        return response()->json(['resumes' => $resumes], 200);
    }

public function upload(Request $request)
    {
        $request->validate([
            'resume' => 'required|mimes:pdf|max:5120',
            'version_name' => 'nullable|string|max:100'
        ]);

        try {
            $user = $request->user();
            $file = $request->file('resume');

            // ১. প্রাইভেট স্টোরেজে ফাইল রাখা
            $filePath = $file->store('resumes', 'local');
            $fileName = $file->getClientOriginalName();
            $absolutePath = Storage::disk('local')->path($filePath);

            // ২. PDF Parsing (এটি খুব ফাস্ট হয়, তাই রিকোয়েস্টেই রাখলাম)
            $parser = new Parser();
            $pdf = $parser->parseFile($absolutePath);
            $extractedText = $pdf->getText();

            if (empty(trim($extractedText))) {
                throw new Exception("Could not extract text from PDF. The file might be scanned or empty.");
            }

            $cleanedText = mb_convert_encoding($extractedText, 'UTF-8', 'UTF-8');
            $safeText = mb_substr($cleanedText, 0, 5000, 'UTF-8');

            // ৩. 🗄️ DATABASE STORAGE (শুরুতেই স্ট্যাটাস processing করে সেভ করা)
            $resume = Resume::create([
                'user_id' => $user?->id ?? 1, // টেস্টিংয়ের সুবিধার্থে ইউজার নাল থাকলে আইডি ১ সেট করবে
                'file_name' => $fileName,
                'file_path' => $filePath,
                'version_name' => $request->version_name ?? 'Main Version',
                'status' => 'processing', // 🔥 এই স্ট্যাটাস দেখেই রিঅ্যাক্ট পোলিং করা শুরু করবে
                'ats_score' => 0,
                'parsed_content' => json_encode(['status' => 'pending'])
            ]);

            // ৪. 🚀 লারাভেল কিউকে কাজ বুঝিয়ে দেওয়া
            // এই জবের ভেতরেই Groq এপিআই কলের ভারী কাজটি হবে
            ProcessResumeJob::dispatch($resume, $safeText);

            // ৫. রিয়েক্টকে সাথে সাথে রেসপন্স দিয়ে দেওয়া (১ সেকেন্ডেরও কম সময়ে!)
            return response()->json([
                'success' => true,
                'message' => 'Resume uploaded successfully. AI processing started in the background.',
                'resume' => $resume
            ], 202); // 202 মানে Accepted (প্রসেস করা শুরু হয়েছে কিন্তু শেষ হয়নি)

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to process resume',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $resume = Resume::findOrFail($id);
        return response()->json([
            'resume' => [
                'id' => $resume->id,
                'status' => $resume->status,
                'ats_score' => $resume->ats_score, // ফ্রন্টএন্ডে যাচ্ছে
                'ai_suggestions' => $resume->ai_suggestions, // ফ্রন্টএন্ডে যাচ্ছে
                'parsed_content' => $resume->parsed_content,
                'version_name' => $resume->version_name,
            ]
        ]);
    }
    public function update(Request $request, $id)
    {
        $resume = Resume::findOrFail($id);

        // ফ্রন্টএন্ড থেকে পাঠানো ডাটা ভ্যালিডেশন
        $validated = $request->validate([
            'name' => 'nullable|string',
            'skills' => 'nullable|array',
            'experience' => 'nullable|array',
            'education' => 'nullable|array',
        ]);

        // ডাটাবেজের structured_data এর ভেতর নতুন এডিটেড ডাটা মার্জ করা
        $currentParsedContent = $resume->parsed_content;
        
        $currentParsedContent['structured_data'] = [
            'name' => $validated['name'] ?? ($currentParsedContent['structured_data']['name'] ?? ''),
            'skills' => $validated['skills'] ?? ($currentParsedContent['structured_data']['skills'] ?? []),
            'experience' => $validated['experience'] ?? ($currentParsedContent['structured_data']['experience'] ?? []),
            'education' => $validated['education'] ?? ($currentParsedContent['structured_data']['education'] ?? []),
        ];

        // ডাটাবেজ আপডেট
        $resume->update([
            'parsed_content' => $currentParsedContent
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully!',
            'resume' => $resume
        ]);
    }
}