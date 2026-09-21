<?php

namespace Database\Factories;

use App\Enums\AgreementStatus;
use App\Models\AgreementVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementVersion>
 */
class AgreementVersionFactory extends Factory
{
    protected $model = AgreementVersion::class;

    private static int $sequence = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$sequence++;

        return [
            'kind' => AgreementVersion::KIND_PARTNER_TERMS,
            'version' => '1.'.self::$sequence,
            'title_ar' => 'شروط الشراكة',
            'title_en' => 'Partner Terms',
            'body_ar' => 'نص الاتفاقية '.self::$sequence,
            'body_en' => 'Agreement text '.self::$sequence,
            'status' => AgreementStatus::Draft,
            'requires_reacceptance' => true,
        ];
    }

    /**
     * In force, with the hash taken as publishing would.
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => AgreementStatus::Published,
            'published_at' => now(),
        ])->afterCreating(function (AgreementVersion $version) {
            $version->forceFill(['content_hash' => $version->hashContent()])->saveQuietly();
        });
    }
}
