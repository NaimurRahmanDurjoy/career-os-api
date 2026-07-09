<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use OpenAI\Laravel\Facades\OpenAI;
use Exception;

class ResumeController extends Controller
{
public function upload(Request $request)
{
    $request->validate([
        'resume' => 'required|mimes:pdf|max:5120',
        'version_name' => 'nullable|string|max:100'
    ]);

    try {
        $user = $request->user();
        $file = $request->file('resume');

        $filePath = $file->store('resumes', 'local');
        $fileName = $file->getClientOriginalName();
        $absolutePath = Storage::disk('local')->path($filePath);

        // PDF Parsing
        $parser = new Parser();
        $pdf = $parser->parseFile($absolutePath);
        $extractedText = $pdf->getText();

        if (empty(trim($extractedText))) {
            throw new \Exception("Could not extract text from PDF.");
        }

        $cleanedText = mb_convert_encoding($extractedText, 'UTF-8', 'UTF-8');
        $safeText = mb_substr($cleanedText, 0, 5000, 'UTF-8');

        // --- OPENAI GPT LAYER (PROMPT ENGINEERING) ---
        // We are using GPT-4o-mini model for fast and cost-effective processing. The prompt is designed to extract structured information from the raw resume text.
        $prompt = "You are an expert ATS (Applicant Tracking System) and professional recruiter. 
        Analyze the following raw resume text and extract structured information.
        You MUST return ONLY a valid JSON object. Do not include markdown blocks like ```json or any conversational text.
        
        The JSON structure MUST look exactly like this:
        {
            \"name\": \"Candidate Name\",
            \"skills\": [\"Skill 1\", \"Skill 2\"],
            \"experience\": [
                {
                    \"company\": \"Company Name\",
                    \"role\": \"Job Title\",
                    \"duration\": \"Jan 2025 - Present\",
                    \"description\": \"Brief summary of work\"
                }
            ],
            \"education\": [
                {
                    \"institution\": \"University Name\",
                    \"degree\": \"Degree Name\",
                    \"year\": \"Passing Year\"
                }
            ]
        }

        Raw Resume Text:
        " . $safeText;

        // OpenAI API Call (We use gpt-4o-mini as it is super fast and cheap)
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.3, // Lower temperature for more deterministic output
        ]);

        $aiResponseText = $response->choices[0]->message->content;
        
        // Parse the AI response to ensure it's valid JSON
        $parsedJsonData = json_decode($aiResponseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("AI returned an invalid JSON structure.");
        }

        // Store the resume and its parsed content in the database
        $resume = Resume::create([
            'user_id' => $user->id,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'version_name' => $request->version_name ?? 'Main Version',
            'status' => 'completed', 
            'parsed_content' => $parsedJsonData, // Store the parsed JSON content
            'ats_score' => rand(75, 95), // Randomly generated ATS score for demonstration purposes
            'ai_suggestions' => ['grammar_fixes' => 'Good', 'missing_keywords' => []]
        ]);

        return response()->json([
            'message' => 'Resume analyzed by AI successfully!',
            'resume' => $resume
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to process resume',
            'error' => $e->getMessage()
        ], 500);
    }
}

    
    public function index(Request $request)
    {
        $resumes = $request->user()->resumes()->orderBy('created_at', 'desc')->get();
        return response()->json(['resumes' => $resumes], 200);
    }
}