<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Email;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Email::query()
            ->with('attachments:id,email_id,filename,size_bytes,mime_type')
            ->orderBy('received_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('from_address', 'like', "%{$search}%")
                    ->orWhere('body_text', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from')) {
            $query->where('from_address', 'like', "%{$request->input('from')}%");
        }

        if ($request->filled('date_from')) {
            $query->where('received_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('received_at', '<=', $request->input('date_to'));
        }

        $emails = $query->paginate(25)->withQueryString();

        return Inertia::render('emails/index', [
            'emails' => $emails,
            'filters' => $request->only(['search', 'from', 'date_from', 'date_to']),
        ]);
    }

    public function show(Email $email): Response
    {
        $email->load('attachments', 'auditLogs.user');

        AuditLog::log($email, 'viewed', 'Email viewed by user');

        return Inertia::render('emails/show', [
            'email' => $email,
        ]);
    }
}
