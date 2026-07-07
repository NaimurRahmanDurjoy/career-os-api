<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
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

            $filePath = $file->store('resumes');
            $fileName = $file->getClientOriginalName();

            // Parsing the PDF to extract text content
            $parser = new Parser();
            // parsing the PDF file from the storage path
            $pdf = $parser->parseFile(storage_path('app/private/' . $filePath));
            $extractedText = $pdf->getText(); // Extracted text from the PDF

            // if the extracted text is empty, throw an exception
            if (empty(trim($extractedText))) {
                throw new Exception("Could not extract text. The PDF might be scanned or empty.");
            }

            // Creating a new resume record in the database
            $resume = Resume::create([
                'user_id' => $user->id,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'version_name' => $request->version_name ?? 'Main Version',
                'status' => 'processing', // initial status
                'parsed_content' => [
                    'raw_text' => substr($extractedText, 0, 2000) // store only the first 2000 characters of the extracted text
                ]
            ]);

            return response()->json([
                'message' => 'Resume uploaded and processed successfully',
                'resume' => $resume
            ], 201);

        } catch (Exception $e) {
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