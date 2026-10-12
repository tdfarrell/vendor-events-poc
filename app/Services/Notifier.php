<?php

namespace App\Services;

use App\Models\Organizer;
use Illuminate\Support\Facades\Mail;

/**
 * Sends plain emails in the organizer's name, with replies going to the
 * organizer. In the POC the mailer is "log", so mail lands in the log file.
 */
class Notifier
{
    /**
     * @param  string[]  $paragraphs
     * @param  array{label:string,url:string}|null  $button
     */
    public static function send(?Organizer $organizer, string $to, string $subject, array $paragraphs, ?array $button = null): void
    {
        $body = implode("\n\n", $paragraphs);
        if ($button) {
            $body .= "\n\n".$button['label'].': '.$button['url'];
        }
        $fromName = $organizer?->fromName() ?? config('platform.name');
        $body .= "\n\n— ".$fromName;

        Mail::raw($body, function ($m) use ($to, $subject, $organizer, $fromName) {
            $m->to($to)->subject($subject)->from(config('mail.from.address'), $fromName);
            if ($organizer?->contact_email) {
                $m->replyTo($organizer->contact_email, $fromName);
            }
        });
    }
}
