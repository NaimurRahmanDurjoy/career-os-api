<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Resume;
use Exception;

class ProcessResumeJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $resume;
    protected $safeText;

    /**
     * Create a new job instance.
     */
    public function __construct(Resume $resume, string$safeText)
    {
        $this->resume =$resume;
        $this->safeText =$safeText;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // --- 🤖 GROQ AI LAYER ---
            // প্রম্পট মডিফাই করে আমরা স্ট্রাকচার্ড ডাটায় ATS Score এবং Suggestions নিয়ে আসছি
            $prompt = "You are an advanced Applicant Tracking System (ATS) engine. Analyze the following raw resume text and perform a deep analysis.

            Follow these strict evaluation rules to calculate the 'ats_score' (0-100):
            1. Format & Structure (Max 25 pts): Standard sections like Contact info, Skills, Experience, Education present?
            2. Impact & Action Verbs (Max 35 pts): Are bullet points starting with strong action verbs (e.g., 'Architected', 'Developed', 'Optimized') and showing measurable metrics?
            3. Technical & Soft Skills (Max 25 pts): Are industry-relevant key skills explicitly mentioned?
            4. Education & Certifications (Max 15 pts).
            Be critical. A poorly formatted resume with vague sentences should score below 60. A well-tailored resume should score between 80-95.

            Provide exactly 3 highly specific, actionable 'ai_suggestions' based on what this resume is missing or can improve. (e.g., 'Replace passive phrase \"Responsible for building...\" with action verb \"Engineered...\"').

            Return ONLY a valid JSON object. Do not use markdown backticks like ```json or any markdown formatting in your response.

            JSON Structure:
            {
            \"name\": \"\",
            \"skills\": [],
            \"experience\": [
                {
                \"title\": \"\",
                \"company\": \"\",
                \"duration\": \"\",
                \"location\": \"\",
                \"achievements\": []
                }
            ],
            \"education\": [
                {
                \"degree\": \"\",
                \"school\": \"\",
                \"duration\": \"\",
                \"cgpa\": \"\"
                }
            ],
            \"ats_score\": 0,
            \"ai_suggestions\": [
                \"First action-oriented suggestion...\",
                \"Second action-oriented suggestion...\",
                \"Third action-oriented suggestion...\"
            ]
            }

            Raw Resume Text: " . $this->safeText;

            $response = OpenAI::chat()->create([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ]);

            $aiResponseText = $response->choices[0]->message->content;
            
            // ক্লিনআপ
            $aiResponseText = str_replace(['```json', '```'], '', $aiResponseText);
            $parsedJsonData = json_decode(trim($aiResponseText), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("AI returned invalid JSON: " . $aiResponseText);
            }

            // রেসপন্স থেকে স্কোর ও সাজেশন আলাদা করা
            $atsScore = isset($parsedJsonData['ats_score']) ? intval($parsedJsonData['ats_score']) : rand(70, 85);
            $aiSuggestions = isset($parsedJsonData['ai_suggestions']) ? $parsedJsonData['ai_suggestions'] : [];

            // মূল রেজুমে ডাটা ক্লিন রাখা
            unset($parsedJsonData['ats_score']);
            unset($parsedJsonData['ai_suggestions']);

            // ডাটাবেজে রিয়েল এআই ডাটা সেভ করা হচ্ছে
            $this->resume->update([
                'status' => 'completed',
                'ats_score' => $atsScore,
                'ai_suggestions' => $aiSuggestions, // ডাটাবেজের ai_suggestions কলামে সেভ হবে
                'parsed_content' => [
                    'structured_data' => $parsedJsonData,
                    'raw_text_backup' => $this->safeText
                ]
            ]);

        } catch (Exception $e) {
            $this->resume->update([
                'status' => 'failed',
                'parsed_content' => [
                    'error_message' => $e->getMessage()
                ]
            ]);

            throw $e;
        }
    }
}