<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailWebhookController extends Controller
{
    public function __construct(
        protected EmailParserService $emailParser
    ) {}

    public function receive(Request $request): JsonResponse
    {
        try {
            $rawEmail = $request->getContent();

            if (empty($rawEmail)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No email content received',
                ], 400);
            }

            $email = $this->emailParser->parseAndStore($rawEmail);

            Log::info('Email archived successfully', [
                'email_id' => $email->id,
                'message_id' => $email->message_id,
                'from' => $email->from_address,
                'subject' => $email->subject,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email archived successfully',
                'data' => [
                    'email_id' => $email->id,
                    'message_id' => $email->message_id,
                    'hash' => $email->hash,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to archive email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to archive email: '.$e->getMessage(),
            ], 500);
        }
    }
}
