<?php

/**
 * A mail with an attachment, encoded the way a mail client encodes one:
 * base64 in lines of 76, CRLF throughout.
 */
function mailWithAttachment(string $payload, string $messageId = '<attached@example.com>'): string
{
    $encoded = chunk_split(base64_encode($payload), 76, "\r\n");

    return implode("\r\n", [
        "Message-ID: {$messageId}",
        'From: sender@example.com',
        'To: recipient@example.com',
        'Subject: With an attachment',
        'Date: Mon, 17 Aug 2026 10:00:00 +0000',
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="b1"',
        '',
        '--b1',
        'Content-Type: text/plain; charset=UTF-8',
        '',
        'See attached.',
        '--b1',
        'Content-Type: application/pdf; name="report.pdf"',
        'Content-Transfer-Encoding: base64',
        'Content-Disposition: attachment; filename="report.pdf"',
        '',
        rtrim($encoded, "\r\n"),
        '--b1--',
        '',
    ]);
}

/**
 * The mail as it arrives over IMAP: webklex hands over the raw bytes and the
 * attachments it found in them, which is the pair the parser works from.
 */
function imapMessageFor(string $raw, string $attachmentPayload, string $messageId = '<attached@example.com>'): Webklex\PHPIMAP\Message
{
    [$rawHeader, $rawBody] = explode("\r\n\r\n", $raw, 2);

    $header = Mockery::mock(Webklex\PHPIMAP\Header::class);
    $header->raw = $rawHeader;

    $attachment = Mockery::mock(Webklex\PHPIMAP\Attachment::class);
    $attachment->shouldReceive('getContent')->andReturn($attachmentPayload);
    $attachment->shouldReceive('getName')->andReturn('report.pdf');
    $attachment->shouldReceive('getMimeType')->andReturn('application/pdf');
    $attachment->shouldReceive('getId')->andReturn(null);
    $attachment->shouldReceive('getDisposition')->andReturn('attachment');

    $message = Mockery::mock(Webklex\PHPIMAP\Message::class);
    $message->shouldReceive('getMessageId')->andReturn($messageId);
    $message->shouldReceive('getHeader')->andReturn($header);
    $message->shouldReceive('getRawBody')->andReturn($rawBody);
    $message->shouldReceive('getFrom')->andReturn(collect([
        (object) ['mail' => 'sender@example.com', 'personal' => 'Sender'],
    ]));
    $message->shouldReceive('getTo')->andReturn(collect([(object) ['mail' => 'recipient@example.com']]));
    $message->shouldReceive('getCc')->andReturn(null);
    $message->shouldReceive('getDate')->andReturn(null);
    $message->shouldReceive('getInReplyTo')->andReturn(null);
    $message->shouldReceive('getReferences')->andReturn(null);
    $message->shouldReceive('getSubject')->andReturn('With an attachment');
    $message->shouldReceive('getTextBody')->andReturn('See attached.');
    $message->shouldReceive('getHTMLBody')->andReturn('');
    $message->shouldReceive('getHeaders')->andReturn(collect());
    $message->shouldReceive('hasAttachments')->andReturn(true);
    $message->shouldReceive('getAttachments')->andReturn(
        Webklex\PHPIMAP\Support\AttachmentCollection::make([$attachment])
    );

    return $message;
}

/** Big enough to be worth lifting out, and not compressible into nothing. */
function attachmentPayload(int $bytes = 64 * 1024, int $seed = 1): string
{
    mt_srand($seed);

    return implode('', array_map(fn () => chr(mt_rand(0, 255)), range(1, $bytes)));
}
