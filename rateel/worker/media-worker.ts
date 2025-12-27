/**
 * Cloudflare Worker for serving private R2 media with signed URL validation.
 * 
 * This worker validates HMAC-SHA256 signatures on incoming requests and serves
 * files from a private R2 bucket. It implements:
 * - Signature validation using constant-time comparison
 * - Expiration checking
 * - Path traversal protection
 * - Key rotation support via `kid` parameter
 * - Proper cache headers
 */

export interface Env {
    MEDIA: R2Bucket;
    SIGNING_KEY_V1: string;
    SIGNING_KEY_V2?: string;
    CDN_DOMAIN: string;
}

interface SignatureParams {
    exp: number;
    sig: string;
    kid: string;
    uid?: string;
    scope?: string;
}

// Max URL lifetime: 1 hour
const MAX_TTL_SECONDS = 3600;

// Cache duration for successful responses (10 minutes max)
const DEFAULT_CACHE_SECONDS = 600;

export default {
    async fetch(request: Request, env: Env, ctx: ExecutionContext): Promise<Response> {
        const url = new URL(request.url);

        // Only handle GET requests
        if (request.method !== 'GET') {
            return new Response('Method Not Allowed', { status: 405 });
        }

        // Parse the path: /img/<object_key>
        const pathMatch = url.pathname.match(/^\/img\/(.+)$/);
        if (!pathMatch) {
            return new Response('Not Found', { status: 404 });
        }

        const objectKey = decodeURIComponent(pathMatch[1]);

        // Security: Check for path traversal
        if (!isValidObjectKey(objectKey)) {
            return new Response('Bad Request: Invalid path', { status: 400 });
        }

        // Parse query parameters
        const params = parseQueryParams(url.searchParams);
        if (!params) {
            return new Response('Bad Request: Missing required parameters', { status: 400 });
        }

        // Validate expiration
        const now = Math.floor(Date.now() / 1000);

        if (params.exp < now) {
            return new Response('Forbidden: URL expired', { status: 403 });
        }

        if (params.exp > now + MAX_TTL_SECONDS) {
            return new Response('Forbidden: Expiration too far in future', { status: 403 });
        }

        // Get signing key for this kid
        const signingKey = getSigningKey(env, params.kid);
        if (!signingKey) {
            return new Response('Forbidden: Invalid key ID', { status: 403 });
        }

        // Reconstruct canonical string and verify signature
        const cdnDomain = env.CDN_DOMAIN || url.hostname;
        const canonical = buildCanonicalString(objectKey, params.exp, cdnDomain, params.uid, params.scope);

        const expectedSig = await generateSignature(canonical, signingKey);

        if (!constantTimeEqual(params.sig, expectedSig)) {
            return new Response('Forbidden: Invalid signature', { status: 403 });
        }

        // Signature is valid - fetch from R2
        try {
            const object = await env.MEDIA.get(objectKey);

            if (!object) {
                return new Response('Not Found', { status: 404 });
            }

            // Calculate cache duration (min of time until expiration and default max)
            const timeUntilExpiration = params.exp - now;
            const cacheDuration = Math.min(timeUntilExpiration, DEFAULT_CACHE_SECONDS);

            // Build response headers
            const headers = new Headers();
            headers.set('Content-Type', object.httpMetadata?.contentType || 'application/octet-stream');
            headers.set('Cache-Control', `private, max-age=${cacheDuration}`);
            headers.set('ETag', object.httpEtag);

            // Security headers
            headers.set('X-Content-Type-Options', 'nosniff');
            headers.set('X-Frame-Options', 'DENY');

            // Handle conditional requests
            const ifNoneMatch = request.headers.get('If-None-Match');
            if (ifNoneMatch && ifNoneMatch === object.httpEtag) {
                return new Response(null, { status: 304, headers });
            }

            return new Response(object.body, { status: 200, headers });

        } catch (error) {
            console.error('R2 fetch error:', error);
            return new Response('Internal Server Error', { status: 500 });
        }
    },
};

/**
 * Validate object key for security issues.
 */
function isValidObjectKey(objectKey: string): boolean {
    // Check for path traversal
    if (objectKey.includes('..')) {
        return false;
    }

    // Check for backslashes
    if (objectKey.includes('\\')) {
        return false;
    }

    // Check for null bytes
    if (objectKey.includes('\0')) {
        return false;
    }

    // Check for double encoding patterns
    if (/%25|%2e%2e|%2f/i.test(objectKey)) {
        return false;
    }

    // Must have reasonable structure (at least category/id/subcategory/file)
    const parts = objectKey.split('/');
    if (parts.length < 4) {
        return false;
    }

    return true;
}

/**
 * Parse and validate query parameters.
 */
function parseQueryParams(searchParams: URLSearchParams): SignatureParams | null {
    const exp = searchParams.get('exp');
    const sig = searchParams.get('sig');
    const kid = searchParams.get('kid');

    if (!exp || !sig || !kid) {
        return null;
    }

    const expNum = parseInt(exp, 10);
    if (isNaN(expNum)) {
        return null;
    }

    return {
        exp: expNum,
        sig,
        kid,
        uid: searchParams.get('uid') || undefined,
        scope: searchParams.get('scope') || undefined,
    };
}

/**
 * Get signing key for a given key ID.
 */
function getSigningKey(env: Env, kid: string): string | null {
    switch (kid) {
        case 'v1':
            return env.SIGNING_KEY_V1 || null;
        case 'v2':
            return env.SIGNING_KEY_V2 || null;
        default:
            return null;
    }
}

/**
 * Build canonical string for signature verification.
 * Must match the format used by the Laravel MediaUrlSigner.
 */
function buildCanonicalString(
    objectKey: string,
    exp: number,
    cdnDomain: string,
    uid?: string,
    scope?: string
): string {
    const lines = [
        'v1',
        'GET',
        cdnDomain,
        `/img/${objectKey}`,
        `exp=${exp}`,
        `uid=${uid || ''}`,
        `scope=${scope || ''}`,
    ];

    return lines.join('\n');
}

/**
 * Generate HMAC-SHA256 signature (base64url encoded).
 */
async function generateSignature(canonical: string, secret: string): Promise<string> {
    const encoder = new TextEncoder();
    const keyData = encoder.encode(secret);
    const msgData = encoder.encode(canonical);

    const cryptoKey = await crypto.subtle.importKey(
        'raw',
        keyData,
        { name: 'HMAC', hash: 'SHA-256' },
        false,
        ['sign']
    );

    const signature = await crypto.subtle.sign('HMAC', cryptoKey, msgData);

    // Convert to base64url
    const base64 = btoa(String.fromCharCode(...new Uint8Array(signature)));
    return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

/**
 * Constant-time string comparison to prevent timing attacks.
 */
function constantTimeEqual(a: string, b: string): boolean {
    if (a.length !== b.length) {
        // Still do the comparison to avoid timing differences
        b = a;
    }

    let result = 0;
    for (let i = 0; i < a.length; i++) {
        result |= a.charCodeAt(i) ^ b.charCodeAt(i);
    }

    return result === 0 && a.length === b.length;
}
