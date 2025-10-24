<?php

namespace App\Http\Controllers;

use App\Models\ImapAccount;
use App\Services\ImapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImapAccountController extends Controller
{
    public function index(): Response
    {
        $accounts = ImapAccount::withCount('emails')
            ->orderBy('name')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'host' => $account->host,
                    'username' => $account->username,
                    'folder' => $account->folder,
                    'is_active' => $account->is_active,
                    'last_fetch_at' => $account->last_fetch_at,
                    'total_emails' => $account->total_emails,
                    'total_size_bytes' => $account->total_size_bytes,
                    'formatted_size' => $account->formatted_size,
                    'emails_count' => $account->emails_count,
                ];
            });

        return Inertia::render('imap-accounts/index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('imap-accounts/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'required|in:ssl,tls,none',
            'validate_cert' => 'boolean',
            'username' => 'required|string|max:255',
            'password' => 'required|string',
            'folder' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        ImapAccount::create($validated);

        return redirect()->route('imap-accounts.index')
            ->with('success', 'IMAP account created successfully.');
    }

    public function edit(ImapAccount $imapAccount): Response
    {
        return Inertia::render('imap-accounts/edit', [
            'account' => [
                'id' => $imapAccount->id,
                'name' => $imapAccount->name,
                'host' => $imapAccount->host,
                'port' => $imapAccount->port,
                'encryption' => $imapAccount->encryption,
                'validate_cert' => $imapAccount->validate_cert,
                'username' => $imapAccount->username,
                'folder' => $imapAccount->folder,
                'is_active' => $imapAccount->is_active,
            ],
        ]);
    }

    public function update(Request $request, ImapAccount $imapAccount): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'required|in:ssl,tls,none',
            'validate_cert' => 'boolean',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'folder' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        // Only update password if provided
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $imapAccount->update($validated);

        return redirect()->route('imap-accounts.index')
            ->with('success', 'IMAP account updated successfully.');
    }

    public function destroy(ImapAccount $imapAccount): RedirectResponse
    {
        $imapAccount->delete();

        return redirect()->route('imap-accounts.index')
            ->with('success', 'IMAP account deleted successfully.');
    }

    public function test(ImapAccount $imapAccount, ImapService $imapService): RedirectResponse
    {
        try {
            $imapService->connectToAccount($imapAccount);
            $folders = $imapService->getFolders();

            return back()->with('success', sprintf(
                'Connection successful! Found %d folders.',
                count($folders)
            ));
        } catch (\Exception $e) {
            return back()->with('error', 'Connection failed: '.$e->getMessage());
        }
    }
}
