<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index()
    {
        $tickets = SupportTicket::with('user:id,name,email')->orderBy('created_at', 'desc')->get();
        return response()->json($tickets);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:open,in_progress,resolved,closed']);
        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $request->status;
        $ticket->save();
        return response()->json(['success' => true]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => request()->user()->id,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your support ticket has been submitted successfully.',
            'data' => $ticket
        ], 201);
    }
}
