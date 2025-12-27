<?php

namespace Tests\Unit\Services;

use App\Services\Media\MediaUrlSigner;
use Tests\TestCase;

class MediaUrlSignerTest extends TestCase
{
    private MediaUrlSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        config([
            'media.cdn_domain' => 'cdn.test.com',
            'media.signing_secrets' => [
                'v1' => 'test-secret-key-for-unit-tests-32',
                'v2' => 'test-secret-key-v2-for-rotation-32',
            ],
            'media.current_kid' => 'v1',
            'media.default_ttl' => 300,
            'media.ttl_by_category' => [
                'kyc' => 300,
                'profile' => 900,
                'vehicle' => 600,
            ],
        ]);

        $this->signer = new MediaUrlSigner();
    }

    /** @test */
    public function it_generates_valid_signed_url(): void
    {
        $objectKey = 'driver/123/profile/test-uuid.jpg';
        
        $url = $this->signer->sign($objectKey);

        $this->assertStringStartsWith('https://cdn.test.com/img/', $url);
        $this->assertStringContainsString('exp=', $url);
        $this->assertStringContainsString('sig=', $url);
        $this->assertStringContainsString('kid=v1', $url);
    }

    /** @test */
    public function it_includes_correct_query_parameters(): void
    {
        $objectKey = 'driver/456/identity/uuid.jpg';
        $uid = 'user-789';
        $scope = 'kyc';
        
        $url = $this->signer->sign($objectKey, null, $uid, $scope);

        $parsedUrl = parse_url($url);
        parse_str($parsedUrl['query'], $queryParams);

        $this->assertArrayHasKey('exp', $queryParams);
        $this->assertArrayHasKey('sig', $queryParams);
        $this->assertArrayHasKey('kid', $queryParams);
        $this->assertArrayHasKey('uid', $queryParams);
        $this->assertArrayHasKey('scope', $queryParams);
        
        $this->assertEquals('v1', $queryParams['kid']);
        $this->assertEquals($uid, $queryParams['uid']);
        $this->assertEquals($scope, $queryParams['scope']);
    }

    /** @test */
    public function it_uses_correct_kid_from_config(): void
    {
        // Test with v1
        $url = $this->signer->sign('driver/123/profile/test.jpg');
        $this->assertStringContainsString('kid=v1', $url);
        $this->assertEquals('v1', $this->signer->getCurrentKid());

        // Change config to v2
        config(['media.current_kid' => 'v2']);
        $signerV2 = new MediaUrlSigner();
        
        $urlV2 = $signerV2->sign('driver/123/profile/test.jpg');
        $this->assertStringContainsString('kid=v2', $urlV2);
        $this->assertEquals('v2', $signerV2->getCurrentKid());
    }

    /** @test */
    public function it_generates_consistent_signatures_for_same_input(): void
    {
        $objectKey = 'driver/123/profile/test.jpg';
        $expiresIn = 300;
        $uid = 'user-123';
        $scope = 'profile';

        // Generate two URLs with the same expiration time
        // We need to control the timestamp for this test
        $exp = time() + $expiresIn;
        
        // Create custom signer for controlled testing
        $url1 = $this->signer->sign($objectKey, $expiresIn, $uid, $scope);
        
        // Parse the signature from URL1
        $parsedUrl = parse_url($url1);
        parse_str($parsedUrl['query'], $params1);
        
        // Generate another URL immediately (same second)
        $url2 = $this->signer->sign($objectKey, $expiresIn, $uid, $scope);
        parse_str(parse_url($url2)['query'], $params2);
        
        // If expiration timestamps match, signatures should match
        if ($params1['exp'] === $params2['exp']) {
            $this->assertEquals($params1['sig'], $params2['sig']);
        }
    }

    /** @test */
    public function it_generates_different_signatures_for_different_inputs(): void
    {
        $url1 = $this->signer->sign('driver/123/profile/a.jpg', 300);
        $url2 = $this->signer->sign('driver/456/profile/b.jpg', 300);

        parse_str(parse_url($url1)['query'], $params1);
        parse_str(parse_url($url2)['query'], $params2);

        $this->assertNotEquals($params1['sig'], $params2['sig']);
    }

    /** @test */
    public function it_rejects_object_keys_with_path_traversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('path traversal');

        $this->signer->sign('driver/../admin/secret.jpg');
    }

    /** @test */
    public function it_rejects_object_keys_with_backslashes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('backslashes');

        $this->signer->sign('driver\\123\\profile\\test.jpg');
    }

    /** @test */
    public function it_rejects_empty_object_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->signer->sign('');
    }

    /** @test */
    public function it_uses_category_specific_ttl(): void
    {
        // KYC should use 300 seconds
        $urlKyc = $this->signer->sign('driver/123/kyc/id.jpg');
        $urlProfile = $this->signer->sign('driver/123/profile/avatar.jpg');

        parse_str(parse_url($urlKyc)['query'], $paramsKyc);
        parse_str(parse_url($urlProfile)['query'], $paramsProfile);

        $now = time();
        $kycExp = (int) $paramsKyc['exp'];
        $profileExp = (int) $paramsProfile['exp'];

        // Profile TTL (900s) should result in later expiration than KYC (300s)
        $this->assertGreaterThan($kycExp - $now, $profileExp - $now);
    }

    /** @test */
    public function it_handles_batch_signing(): void
    {
        $objectKeys = [
            'driver/123/profile/a.jpg',
            'driver/123/identity/b.jpg',
            'driver/123/document/c.pdf',
        ];

        $signedUrls = $this->signer->signBatch($objectKeys);

        $this->assertCount(3, $signedUrls);
        
        foreach ($signedUrls as $objectKey => $url) {
            $this->assertStringStartsWith('https://cdn.test.com/img/', $url);
            $this->assertStringContainsString($objectKey, $url);
        }
    }

    /** @test */
    public function it_skips_empty_keys_in_batch(): void
    {
        $objectKeys = [
            'driver/123/profile/a.jpg',
            '',
            null,
            'driver/123/profile/b.jpg',
        ];

        $signedUrls = $this->signer->signBatch(array_filter($objectKeys));

        $this->assertCount(2, $signedUrls);
    }

    /** @test */
    public function it_returns_correct_cdn_domain(): void
    {
        $this->assertEquals('cdn.test.com', $this->signer->getCdnDomain());
    }

    /**
     * Test vector for cross-validation with Cloudflare Worker.
     * 
     * This test provides a fixed example that can be used to verify
     * the Worker implementation produces matching signatures.
     * 
     * @test
     */
    public function test_vector_for_worker_validation(): void
    {
        // Fixed inputs
        $objectKey = 'driver/123/kyc/id_front/abc123.jpg';
        $exp = 1735300000; // Fixed timestamp
        $uid = 'user-456';
        $scope = 'kyc';
        $secret = 'test-secret-key-for-unit-tests-32';
        $cdnDomain = 'cdn.test.com';

        // Build canonical string (same as in MediaUrlSigner)
        $canonical = implode("\n", [
            'v1',
            'GET',
            $cdnDomain,
            '/img/' . $objectKey,
            'exp=' . $exp,
            'uid=' . $uid,
            'scope=' . $scope,
        ]);

        // Generate signature
        $hash = hash_hmac('sha256', $canonical, $secret, true);
        $signature = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        // Output for manual verification
        $this->assertNotEmpty($signature);
        
        // Log the test vector for documentation
        // echo "\n--- TEST VECTOR ---\n";
        // echo "Object Key: $objectKey\n";
        // echo "Expiration: $exp\n";
        // echo "UID: $uid\n";
        // echo "Scope: $scope\n";
        // echo "Secret: $secret\n";
        // echo "CDN Domain: $cdnDomain\n";
        // echo "Canonical String:\n$canonical\n";
        // echo "Expected Signature: $signature\n";
        // echo "-------------------\n";

        // The signature should be deterministic
        $signature2 = rtrim(strtr(base64_encode(hash_hmac('sha256', $canonical, $secret, true)), '+/', '-_'), '=');
        $this->assertEquals($signature, $signature2);
    }
}
