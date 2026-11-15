<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\EnquiryReply;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Recording and answering enquiries.
 *
 * The thing worth being careful about here is `responded_at`: it is the only
 * honest measure of whether anybody is actually answering people, so it is
 * set when a reply goes *to the customer* and not when staff leave a note
 * for each other.
 */
class EnquiryService
{
    public function __construct(
        protected OrderNumberService $numbers,
        protected ImageService $images,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $attachments
     */
    public function create(array $data, array $attachments = []): Enquiry
    {
        return DB::transaction(function () use ($data, $attachments) {
            $enquiry = Enquiry::create($data + [
                'reference' => $this->numbers->next('enquiry'),
                'status' => 'new',
            ]);

            foreach ($attachments as $file) {
                $this->attach($enquiry, $file);
            }

            return $enquiry->fresh('attachments');
        });
    }

    /**
     * A photo of the space, or a sketch of what they want made.
     */
    public function attach(Enquiry $enquiry, UploadedFile $file): void
    {
        // Through the same optimiser as catalogue images: converted to WebP,
        // resized, and refused if it is not really an image. 1400px is plenty
        // for a photo of a room or a sketch on paper.
        $path = $this->images->storeSingle($file, 'enquiries', maxEdge: 1400);

        $enquiry->attachments()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Add to the thread.
     *
     * Only something the customer can actually see counts as a response —
     * an internal note is staff talking to each other.
     */
    public function reply(
        Enquiry $enquiry,
        string $body,
        string $channel = 'email',
        ?User $actor = null,
        bool $emailed = false,
    ): EnquiryReply {
        return DB::transaction(function () use ($enquiry, $body, $channel, $actor, $emailed) {
            $reply = $enquiry->replies()->create([
                'user_id' => $actor?->id,
                'user_name' => $actor?->name,
                'channel' => $channel,
                'body' => $body,
                'emailed_at' => $emailed ? now() : null,
            ]);

            if ($channel !== 'note') {
                $enquiry->forceFill([
                    'responded_at' => $enquiry->responded_at ?? now(),
                    // An answered enquiry is being handled, unless somebody
                    // has already moved it further along.
                    'status' => $enquiry->status === 'new' ? 'in_progress' : $enquiry->status,
                ])->save();
            }

            return $reply;
        });
    }

    public function assign(Enquiry $enquiry, ?User $to): Enquiry
    {
        $enquiry->update([
            'assigned_to' => $to?->id,
            'status' => $enquiry->status === 'new' ? 'in_progress' : $enquiry->status,
        ]);

        return $enquiry;
    }

    public function close(Enquiry $enquiry, string $status, ?string $reason = null): Enquiry
    {
        $enquiry->update([
            'status' => $status,
            'close_reason' => $reason,
            'closed_at' => now(),
        ]);

        return $enquiry;
    }

    public function reopen(Enquiry $enquiry): Enquiry
    {
        $enquiry->update([
            'status' => $enquiry->quotations()->exists() ? 'quoted' : 'in_progress',
            'close_reason' => null,
            'closed_at' => null,
        ]);

        return $enquiry;
    }

    /**
     * Rough throttle on the public form, by email address.
     *
     * Not a security control — the route is rate limited by IP for that —
     * but it stops one person filling the inbox by hitting submit again
     * because nothing seemed to happen.
     */
    public function recentlySubmitted(string $email, int $withinMinutes = 2): bool
    {
        return Enquiry::where('email', $email)
            ->where('created_at', '>=', now()->subMinutes($withinMinutes))
            ->exists();
    }
}
