<?php

namespace Tests\Unit\Fiscal;

use App\Support\FelAuthorizationNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FelAuthorizationNormalizerTest extends TestCase
{
    #[DataProvider('variants')]
    public function test_uuid_variants_share_one_canonical_value(string $input, string $expected): void
    {
        $this->assertSame($expected, (new FelAuthorizationNormalizer)->normalize($input));
    }

    public static function variants(): array
    {
        return [
            'hyphens' => ['11111111-2222-4333-8444-555555555555', '11111111-2222-4333-8444-555555555555'],
            'compact' => ['11111111222243338444555555555555', '11111111-2222-4333-8444-555555555555'],
            'lowercase' => ['aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE'],
            'uppercase' => ['AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE', 'AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE'],
            'spaces' => [' 11111111 - 2222 - 4333 - 8444 - 555555555555 ', '11111111-2222-4333-8444-555555555555'],
            'braces' => ['{11111111-2222-4333-8444-555555555555}', '11111111-2222-4333-8444-555555555555'],
        ];
    }

    public function test_hexadecimal_letters_are_normalized_to_uppercase(): void
    {
        $this->assertSame(
            'AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE',
            (new FelAuthorizationNormalizer)->normalize('{aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee}'),
        );
    }
}
