<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\AiJobMatch;
use App\Models\Resume;
use App\Models\JobApplication;
use Exception;

class ProcessJobMatchJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $jobMatch;
    protected $resumeData;
    protected $jobDescription;

    public function __construct(AiJobMatch $jobMatch, string $resumeData, string $jobDescription)
    {
        $this->jobMatch = $jobMatch;
        $this->resumeData = $resumeData;
        $this->jobDescription = $jobDescription;
    }

    public function handle(): void
    {
        try {
            $prompt = "You are an expert AI recruiter matching a candidate's resume to a job description.
            
            Based on the following resume and job description, calculate a fit score and provide a recommendation.
            
            Return ONLY a valid JSON object without markdown formatting (NO ```json blocks).
            
            Strict JSON Structure:
            {
                \"match_score\": 85,
                \"verdict\": \"Strong Match\", // or \"Good Match\", \"Weak Match\", \"Consider Skipping\"
                \"missing_skills\": [\"Agile\", \"AWS\"],
                \"strengths\": [\"Laravel\", \"React\"],
                \"recommendation\": \"The candidate has strong technical skills but lacks cloud deployment experience.\"
            }
            
            Resume: " . $this->resumeData . "
            
            Job Description: " . $this->jobDescription;

            $response = OpenAI::chat()->create([
                'model' => config('services.groq.model'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ]);

            $aiResponseText = $response->choices[0]->message->content;
            $aiResponseText = str_replace(['```json', '```'], '', $aiResponseText);
            
            $parsedJsonData = json_decode(trim($aiResponseText), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("AI returned invalid JSON: " . $aiResponseText);
            }

            $this->jobMatch->update([
                'match_score' => $parsedJsonData['match_score'] ?? 0,
                'verdict' => $parsedJsonData['verdict'] ?? 'Analysis Failed',
                // We'll store missing_skills, strengths, and recommendation inside the prep questions column temporarily 
                // until we add a proper JSON column, or we can just append it to verdict for now.
                // Or better, let's just make sure verdict holds the rich data if needed, but the schema has specific columns.
            ]);
            
            // Note: Our migration only has match_score, verdict, cover_letter and interview_prep! 
            // So let's store the full JSON analysis in the verdict column for now (or a new column in the future).
            $fullVerdict = ($parsedJsonData['verdict'] ?? '') . "\n\n" .
                "Strengths: " . implode(', ', $parsedJsonData['strengths'] ?? []) . "\n" .
                "Missing: " . implode(', ', $parsedJsonData['missing_skills'] ?? []) . "\n" .
                "Recommendation: " . ($parsedJsonData['recommendation'] ?? '');
                
            $this->jobMatch->update([
                'verdict' => $fullVerdict
            ]);

        } catch (Exception $e) {
            $this->jobMatch->update([
                'match_score' => 0,
                'verdict' => 'Error: ' . $e->getMessage()
            ]);
            throw $e;
        }
    }
}
