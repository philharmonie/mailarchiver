<?php

use App\Models\Email;
use App\Models\ImapAccount;
use App\Services\EmailParserService;
use App\Services\ImapService;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\MessageCollection;

/**
 * A folder that behaves like the real thing under `delete_after_archive`:
 * every deleted message is expunged, so everything behind it moves up a page.
 *
 * Returns the state the assertions read: the pages that were asked for, and
 * the messages still sitting in the folder when the run is over.
 */
function fakeFolder(int $messageCount, array $undeletable = []): object
{
    $state = new stdClass;
    $state->pages = [];
    $state->sizes = [];
    $state->remaining = [];
    $state->take = 0;

    for ($uid = 1; $uid <= $messageCount; $uid++) {
        $message = Mockery::mock(Message::class);
        $message->shouldReceive('getUid')->andReturn($uid);
        $message->shouldReceive('getMessageId')->andReturn("<mail-{$uid}@example.com>");
        $message->shouldReceive('delete')->andReturnUsing(function () use ($state, $uid, $undeletable) {
            if (in_array($uid, $undeletable, true)) {
                throw new RuntimeException('message stays on the server');
            }

            $state->remaining = array_values(array_filter(
                $state->remaining,
                fn (Message $m) => $m->getUid() !== $uid
            ));

            return true;
        });

        $state->remaining[] = $message;
    }

    $query = Mockery::mock(WhereQuery::class);
    $query->shouldReceive('whereAll')->andReturnSelf();
    $query->shouldReceive('count')->andReturn($messageCount);
    $query->shouldReceive('limit')->andReturnUsing(function (int $count, int $page) use ($state, &$query) {
        $state->take = $count;
        $state->pages[] = $page;
        $state->sizes[] = $count;

        return $query;
    });
    $query->shouldReceive('get')->andReturnUsing(function () use ($state) {
        $page = end($state->pages) ?: 1;

        return MessageCollection::make(
            array_slice($state->remaining, ($page - 1) * 25, $state->take)
        );
    });

    $folder = Mockery::mock(Folder::class);
    $folder->shouldReceive('query')->andReturn($query);

    $state->client = Mockery::mock(Client::class);
    $state->client->shouldReceive('getFolder')->andReturn($folder);
    $state->client->shouldReceive('isConnected')->andReturn(false);

    return $state;
}

function serviceFor(Client $client, ImapAccount $account): ImapService
{
    $parser = Mockery::mock(EmailParserService::class);
    $parser->shouldReceive('parseAndStoreFromImap')->andReturnUsing(
        fn (Message $message) => Email::factory()->create([
            'message_id' => $message->getMessageId(),
            'size_bytes' => 100,
        ])
    );

    $service = new ImapService($parser);

    foreach (['client' => $client, 'currentAccount' => $account] as $name => $value) {
        (new ReflectionProperty(ImapService::class, $name))->setValue($service, $value);
    }

    return $service;
}

test('a shrinking folder is drained in a single run', function () {
    $account = ImapAccount::factory()->create([
        'username' => 'archive@testdomain.com',
        'folder' => 'Backfill/INBOX',
        'delete_after_archive' => true,
    ]);

    $folder = fakeFolder(60);

    $archived = serviceFor($folder->client, $account)->fetchAndArchiveEmails();

    // Everything archived, nothing left behind for a later run to find - and
    // the folder was never asked for a page that had already moved away.
    expect($archived)->toHaveCount(60)
        ->and($folder->remaining)->toBeEmpty()
        ->and(array_unique($folder->pages))->toBe([1]);
});

test('mail that cannot be deleted does not stall the run', function () {
    $account = ImapAccount::factory()->create([
        'username' => 'archive@testdomain.com',
        'folder' => 'Backfill/INBOX',
        'delete_after_archive' => true,
    ]);

    // The first two refuse to leave the server, so page 1 keeps handing them
    // back. The run has to step over them instead of reading them forever.
    $folder = fakeFolder(30, undeletable: [1, 2]);

    $archived = serviceFor($folder->client, $account)->fetchAndArchiveEmails();

    // All 30 archived, the two that could not be deleted stay on the server,
    // and the last window reached past them - it asked for more messages than
    // there were left to archive.
    expect($archived)->toHaveCount(30)
        ->and($folder->remaining)->toHaveCount(2)
        ->and(array_unique($folder->pages))->toBe([1])
        ->and(end($folder->sizes))->toBeGreaterThan(2);
});

test('a folder nothing is deleted from is still paged forward', function () {
    $account = ImapAccount::factory()->create([
        'username' => 'contact@testdomain.com',
        'folder' => 'INBOX',
        'delete_after_archive' => false,
    ]);

    $folder = fakeFolder(60);

    $archived = serviceFor($folder->client, $account)->fetchAndArchiveEmails();

    expect($archived)->toHaveCount(60)
        ->and($folder->remaining)->toHaveCount(60)
        ->and($folder->pages)->toBe([1, 2, 3]);
});
