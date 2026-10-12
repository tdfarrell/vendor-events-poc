<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\Organizer;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Sends plain emails in the organizer's name, with replies going to the
 * organizer, and records each one in the email log the organizer can see.
 */
class Notifier
{
    /**
     * @param  string[]  $paragraphs
     * @param  array{label:string,url:string}|null  $button
     */
    public static function send(?Organizer $organizer, string $to, string $subject, array $paragraphs, ?array $button = null,
        string $kind = '', ?int $eventId = null): void
    {
        $body = implode("\n\n", $paragraphs);
        if ($button) {
            $body .= "\n\n".$button['label'].': '.$button['url'];
        }
        $fromName = $organizer?->fromName() ?? config('platform.name');
        $body .= "\n\n— ".$fromName;

        $status = 'sent';
        try {
            Mail::raw($body, function ($m) use ($to, $subject, $organizer, $fromName) {
                $m->to($to)->subject($subject)->from(config('mail.from.address'), $fromName);
                if ($organizer?->contact_email) {
                    $m->replyTo($organizer->contact_email, $fromName);
                }
            });
        } catch (\Throwable $e) {
            report($e);
            $status = 'failed';
        }

        $row = ['event_id' => $eventId, 'kind' => $kind, 'to_email' => $to, 'subject' => $subject, 'body' => $body, 'status' => $status];
        if ($organizer) {
            Tenancy::as($organizer, fn () => EmailLog::create($row));
        } else {
            DB::table('email_log')->insert($row + ['organizer_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
