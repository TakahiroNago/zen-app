<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'concern', 'response', 'topic', 'summary', 'daily_consultation_date', 'share_token_hash', 'contains_personal_info', 'image_number'])]
class ZenConsultation extends Model
{
    public const TOPICS = ['仕事', '恋愛', 'お金', '家族', '人間関係', '健康', '将来', '自分自身', '芸術', 'その他'];

    public const SUMMARY_MAX_LENGTH = 150;

    public const IMAGE_COUNT = 16;

    public const IMAGE_SIZE = 640;

    public static function imageUrl(mixed $imageNumber): string
    {
        if (! is_int($imageNumber) || $imageNumber < 1 || $imageNumber > self::IMAGE_COUNT) {
            $imageNumber = 1;
        }

        return asset(sprintf('images/%02d.webp', $imageNumber));
    }

    protected function casts(): array
    {
        return [
            'daily_consultation_date' => 'date',
            'contains_personal_info' => 'boolean',
            'image_number' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
