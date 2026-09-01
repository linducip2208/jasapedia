<?php

namespace App\Domain\Trust;

use App\Domain\Auth\DomainException;
use App\Models\Partner;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function create(\App\Models\Order $order, \App\Models\User $author, array $data): Review
    {
        return DB::transaction(function () use ($order, $author, $data) {
            if ($order->user_id !== $author->id) {
                throw new DomainException('Only the customer can review.', 'FORBIDDEN', 403);
            }

            if (! in_array($order->status, ['completed', 'settled', 'closed'], true)) {
                throw new DomainException('Only completed transactions can be reviewed.', 'NOT_REVIEWABLE', 409);
            }

            if (Review::where('order_id', $order->id)->exists()) {
                throw new DomainException('Order already reviewed.', 'ALREADY_REVIEWED', 409);
            }

            $dimensions = $order->service?->reviewDimensions() ?? ['quality', 'communication', 'value'];
            $ratings = $data['dimension_ratings'] ?? [];

            foreach ($dimensions as $dim) {
                if (! isset($ratings[$dim]) || $ratings[$dim] < 1 || $ratings[$dim] > 5) {
                    throw new DomainException("Rating for dimension [{$dim}] required (1-5).", 'INVALID_RATING', 422);
                }
            }

            $review = Review::create([
                'order_id' => $order->id,
                'author_id' => $author->id,
                'partner_id' => $order->partner_id,
                'overall' => $data['overall'],
                'dimension_ratings' => $ratings,
                'comment' => $data['comment'] ?? null,
                'images' => $data['images'] ?? null,
            ]);

            $this->recomputePartnerRating($order->partner_id);

            return $review;
        });
    }

    public function respond(Review $review, User $partnerUser, string $response): Review
    {
        $partner = Partner::where('user_id', $partnerUser->id)->first();

        if (! $partner || $review->partner_id !== $partner->id) {
            throw new DomainException('Only the reviewed partner can respond.', 'FORBIDDEN', 403);
        }

        if ($review->partner_response !== null) {
            throw new DomainException('Already responded.', 'ALREADY_RESPONDED', 409);
        }

        $review->update(['partner_response' => $response, 'responded_at' => now()]);

        return $review->fresh();
    }

    public function moderate(Review $review, User $staff, string $status): Review
    {
        if (! in_array($status, ['published', 'hidden', 'flagged'], true)) {
            throw new DomainException('Invalid status.', 'INVALID_STATUS', 422);
        }

        $review->update(['status' => $status]);
        $this->recomputePartnerRating($review->partner_id);

        return $review->fresh();
    }

    private function recomputePartnerRating(int $partnerId): void
    {
        $agg = Review::where('partner_id', $partnerId)->where('status', 'published')
            ->selectRaw('COUNT(*) as c, AVG(overall) as avg')->first();

        Partner::where('id', $partnerId)->update([
            'rating_count' => (int) $agg->c,
            'rating_avg' => round((float) $agg->avg, 2),
        ]);
    }
}
